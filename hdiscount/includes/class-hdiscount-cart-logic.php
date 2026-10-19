<?php
/**
 * Logica de aplicacao dos descontos no carrinho/checkout.
 *
 * Estrategia: em vez de tentar marcar "quais unidades" estao em promocao
 * (o WooCommerce nao divide linhas de carrinho nativamente), calculamos o
 * TOTAL ALVO do conjunto de unidades elegiveis e derivamos um preco unitario
 * unico. Isso funciona para qualquer combinacao de quantidades e variacoes.
 */
// Acesso direto ao arquivo e bloqueado: so o WordPress pode caregar.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HDiscount_Cart_Logic {

    /**
     * Referencia ao carrinho em processamento.
     *
     * Permite gravar o preco original direto em cart_contents, e nao numa
     * copia local.
     *
     * @var WC_Cart|null
     */
    protected $cart_refs = null;

    /**
     * Descontos calculados no ultimo passo, aplicados como taxa negativa.
     *
     * @var array
     */
    protected $descontos = array();

    /**
     * Construtor - registra hooks
     */
    public function __construct() {
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_discounts' ), 20, 1 );
        add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'clean_stale_session_data' ), 5 );
        add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'apply_discounts' ), 20, 1 );


        // Desconto aparece no total, como um cupom, sem alterar os itens.
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_discount_fee' ), 20 );

        // Explica o desconto na linha do item (o preco em si nao muda).
        add_filter( 'woocommerce_get_item_data', array( $this, 'append_cart_item_notice' ), 10, 2 );

        // Badge na pagina do produto
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_product_badge' ), 31 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_storefront_assets' ) );

        // Limpa cache ao salvar/alterar regra
        add_action( 'save_post_hdiscount_rule', array( $this, 'clear_rules_cache' ) );
        add_action( 'deleted_post', array( $this, 'clear_rules_cache_on_delete' ) );
        add_action( 'trashed_post', array( $this, 'clear_rules_cache_on_delete' ) );
    }

    /** Limpa cache de regras ao salvar */
    public function clear_rules_cache() {
        wp_cache_delete( 'hdiscount_active_rules', 'hdiscount' );
    }

    /** Limpa cache ao excluir regra */
    public function clear_rules_cache_on_delete( $post_id ) {
        if ( 'hdiscount_rule' === get_post_type( $post_id ) ) {
            $this->clear_rules_cache();
        }
    }

    /**
     * Busca regras aplicaveis no momento atual (com cache).
     *
     * @return array Lista de regras normalizadas.
     */
    private function get_active_rules() {
        $rules = wp_cache_get( 'hdiscount_active_rules', 'hdiscount' );
        if ( is_array( $rules ) ) {
            return $rules;
        }

        $now = current_time( 'mysql' );

        $args = array(
            'post_type'      => 'hdiscount_rule',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_hdiscount_status',
                    'value'   => array( 'active', 'scheduled' ),
                    'compare' => 'IN',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_hdiscount_start_date',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_hdiscount_start_date',
                        'value'   => '',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_hdiscount_start_date',
                        'value'   => $now,
                        'compare' => '<=',
                        'type'    => 'DATETIME',
                    ),
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_hdiscount_end_date',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_hdiscount_end_date',
                        'value'   => '',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_hdiscount_end_date',
                        'value'   => $now,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                ),
            ),
        );

        $rules = array();
        $query = new WP_Query( $args );

        foreach ( (array) $query->posts as $post_id ) {
            $rule = $this->get_rule_data( $post_id );
            if ( $rule && $this->rule_has_valid_products( $rule ) ) {
                $rules[] = $rule;
            }
        }

        wp_cache_set( 'hdiscount_active_rules', $rules, 'hdiscount', 15 * MINUTE_IN_SECONDS );

        return $rules;
    }

    /**
     * Extrai e normaliza os dados de uma regra.
     *
     * @param int $post_id
     * @return array|null
     */
    private function get_rule_data( $post_id ) {
        $product_ids = get_post_meta( $post_id, '_hdiscount_product_ids', true );
        $product_ids = $product_ids ? array_map( 'intval', explode( ',', $product_ids ) ) : array();
        $product_ids = array_values( array_filter( $product_ids ) );

        if ( empty( $product_ids ) ) {
            return null;
        }

        $min_qty = max( 1, (int) get_post_meta( $post_id, '_hdiscount_min_qty', true ) );

        return array(
            'id'             => $post_id,
            'title'          => get_the_title( $post_id ),
            'type'           => get_post_meta( $post_id, '_hdiscount_type', true ) ?: 'pack_price',
            'min_qty'        => $min_qty,
            'max_qty'        => max( 0, (int) get_post_meta( $post_id, '_hdiscount_max_qty', true ) ),
            'pack_price'     => wc_format_decimal( get_post_meta( $post_id, '_hdiscount_pack_price', true ) ),
            'percent'        => (float) get_post_meta( $post_id, '_hdiscount_percent', true ),
            'buy_qty'        => max( 1, (int) get_post_meta( $post_id, '_hdiscount_buy_qty', true ) ),
            'get_qty'        => max( 0, (int) get_post_meta( $post_id, '_hdiscount_get_qty', true ) ),
            'same_variation' => get_post_meta( $post_id, '_hdiscount_same_variation', true ) ?: 'yes',
            'limit_to_max'   => get_post_meta( $post_id, '_hdiscount_limit_to_max', true ) ?: 'no',
            // Padrao "sim": escolher o produto variavel ja vale para as variacoes.
            'extend'         => 'no' === get_post_meta( $post_id, '_hdiscount_extend_variations', true ) ? 'no' : 'yes',
            // Quando ligado, as unidades de produtos diferentes somam no
            // mesmo pack.
            'mix_products'   => 'yes' === get_post_meta( $post_id, '_hdiscount_mix_products', true ) ? 'yes' : 'no',
            'product_ids'    => $product_ids,
        );
    }

    /**
     * Descobre se um item do carrinho (produto + variacao) é coberto pela regra.
     *
     * Com 'extend' ligado, escolher o produto pai vale para todas as suas
     * variacoes, inclusive as criadas depois de a regra ser salva.
     * Com 'extend' desligado, vale apenas para os IDs listados, o que permite
     *hacer promocao em variacoes especificas de um mesmo produto.
     *
     * @param array $rule
     * @param int   $product_id   ID do produto pai (no carrinho e sempre o pai).
     * @param int   $variation_id ID da variacao (igual ao pai em produto simples).
     * @return bool
     */
    private function rule_matches( $rule, $product_id, $variation_id ) {
        $ids = $rule['product_ids'];

        if ( 'yes' === $rule['extend'] ) {
            // Produto simples: o proprio ID.
            if ( in_array( $product_id, $ids, true ) ) {
                return true;
            }
            // Variacao listada explicitamente.
            if ( $variation_id !== $product_id && in_array( $variation_id, $ids, true ) ) {
                return true;
            }
            return false;
        }

        // Sem extensao: vale apenas para os IDs exatos informados.
        // Nao se usa o ID do produto pai aqui de proposito: em um produto
        // variavel ele nunca aparece como 'variation_id' no carrinho, entao
        // um pai listado sem extensao simplesmente nao casa com nada.
        // Produto simples nao sofre com isso, porque variation_id == product_id.
        return in_array( $variation_id, $ids, true );
    }

    /**
     * Verifica se a regra possui ao menos um produto valido e publicado.
     *
     * @param array $rule
     * @return bool
     */
    private function rule_has_valid_products( $rule ) {
        foreach ( $rule['product_ids'] as $pid ) {
            $product = wc_get_product( $pid );
            if ( $product && 'publish' === $product->get_status() ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Preco de referencia (antes de qualquer desconto do HDiscount).
     *
     * Vive apenas na memoria do request, de proposito.
     *
     * O WordPress recalcula o carrinho varias vezes no mesmo request, entao
     * guardar o preco em memoria evita que a segunda passada use o preco ja
     * rebaixado e aplique o desconto em cima dele mesmo.
     *
     * Esse valor NAO pode ser lido de volta de uma passagem anterior. Ele e
     * gravado em cart_contents para exibicao e acaba persistido na sessao:
     * uma sessao antiga (ou um preco alterado pelo lojista) contaminaria a
     * conta para sempre. A cada novo request o WooCommerce recria o produto
     * com o preco atual do banco, entao comecar do zero aqui e seguro.
     *
     * @var array
     */
    private static $baseline = array();

    /**
     * Preco de referencia de uma linha do carrinho.
     *
     * @param string $cart_item_key
     * @param array  $cart_item
     * @return float
     */
    private function get_baseline_price( $cart_item_key, $cart_item ) {
        if ( ! isset( self::$baseline[ $cart_item_key ] ) && isset( $cart_item['data'] ) ) {
            self::$baseline[ $cart_item_key ] = (float) $cart_item['data']->get_price();
        }

        return isset( self::$baseline[ $cart_item_key ] ) ? self::$baseline[ $cart_item_key ] : 0.0;
    }

    /**
     * Limpa os precos de referencia guardados em memoria.
     *
     * Usado quando o carrinho muda de forma. Em producao isso acontece
     * sozinho: cada request comeca com a memoria vazia.
     */
    public static function reset_baseline() {
        self::$baseline = array();
    }

    /**
     * Remove dados do HDiscount que ficaram na sessao de pedidos anteriores.
     *
     * O preco original e reescrito a cada calculo, entao qualquer valor salvo
     * na sessao esta desatualizado. Isso vale para quem upgrading de uma
     * versao que gravava o preco ja rebaixado.
     *
     * @param WC_Cart $cart
     */
    public function clean_stale_session_data( $cart ) {
        if ( ! $cart instanceof WC_Cart ) {
            return;
        }

        self::reset_baseline();

        foreach ( $cart->cart_contents as $key => $item ) {
            unset(
                $cart->cart_contents[ $key ]['hdiscount_original_price'],
                $cart->cart_contents[ $key ]['hdiscount_breakdown'],
                $cart->cart_contents[ $key ]['hdiscount_rule_id'],
                $cart->cart_contents[ $key ]['hdiscount_label'],
                // Dado de versoes anteriores, quando o desconto mexia no item.
                $cart->cart_contents[ $key ]['hdiscount_promo_price'],
                $cart->cart_contents[ $key ]['hdiscount_summary'],
                $cart->cart_contents[ $key ]['hdiscount_split_group']
            );
        }
    }

    /**
     * Aplica os descontos no carrinho.
     *
     * @param WC_Cart $cart
     */
    public function apply_discounts( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }
        if ( ! $cart instanceof WC_Cart ) {
            return;
        }

        $this->cart_refs     = $cart;
        $this->descontos     = array();

        // Restaura o preco de referencia ANTES de olhar as regras.
        // Sem isso, quando a promocao termina o cliente continuaria vendo
        // (e pagando) o preco rebaixado do calculo anterior.
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( empty( $item['data'] ) ) {
                continue;
            }
            $baseline = $this->get_baseline_price( $key, $item );
            if ( $baseline > 0 ) {
                $item['data']->set_price( $baseline );
            }
        }

        $rules = $this->get_active_rules();
        if ( empty( $rules ) ) {
            return;
        }

        // Snapshot do preco original antes de qualquer alteracao.
        $original_prices = array();
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( ! isset( $item['data'] ) ) {
                continue;
            }
            $original_prices[ $key ] = $this->get_baseline_price( $key, $item );
        }

        $applied = array();

        foreach ( $rules as $rule ) {
            $this->process_rule( $cart, $rule, $applied );
        }

        // Grava os dados de exibicao DIRETO em cart_contents.
        // (get_cart_item() devolve copia: atribuir nela seria perdido.)
        foreach ( $applied as $key => $info ) {
            if ( ! isset( $cart->cart_contents[ $key ] ) ) {
                continue;
            }
            $cart->cart_contents[ $key ]['hdiscount_original_price'] = isset( $info['original'] ) ? $info['original'] : 0;
            $cart->cart_contents[ $key ]['hdiscount_rule_id']      = $info['rule_id'];
            $cart->cart_contents[ $key ]['hdiscount_label']        = $info['label'];
            $cart->cart_contents[ $key ]['hdiscount_breakdown']    = isset( $info['breakdown'] ) ? $info['breakdown'] : '';
        }
    }

    /**
     * Agrupa as linhas do carrinho por produto pai e depois por variacao.
     *
     * @param WC_Cart $cart
     * @return array array( product_id => array( variation_id => array(
     *                   'quantity' => int,
     *                   'lines'    => array( cart_item_key ),
     *                   'qty_by_line' => array( cart_item_key => int ),
     *               ) ) )
     */
    private function group_cart_items( $cart ) {
        $grouped = array();

        foreach ( $cart->get_cart() as $cart_item_key => $item ) {
            if ( empty( $item['data'] ) || ! is_object( $item['data'] ) ) {
                continue;
            }

            $product_id   = (int) $item['product_id'];
            $variation_id = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : $product_id;
            $quantity     = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;

            if ( $product_id <= 0 || $quantity <= 0 ) {
                continue;
            }

            if ( ! isset( $grouped[ $product_id ] ) ) {
                $grouped[ $product_id ] = array();
            }
            if ( ! isset( $grouped[ $product_id ][ $variation_id ] ) ) {
                $grouped[ $product_id ][ $variation_id ] = array(
                    'quantity'    => 0,
                    'lines'       => array(),
                    'qty_by_line' => array(),
                );
            }

            $grouped[ $product_id ][ $variation_id ]['quantity']         += $quantity;
            $grouped[ $product_id ][ $variation_id ]['lines'][]          = $cart_item_key;
            $grouped[ $product_id ][ $variation_id ]['qty_by_line'][ $cart_item_key ] = $quantity;
        }

        return $grouped;
    }

    /**
     * Processa uma regra sobre o carrinho.
     *
     * @param WC_Cart $cart
     * @param array   $rule
     * @param array   $applied Preenchido por referencia com os itens alterados.
     */
    private function process_rule( $cart, $rule, &$applied ) {
        $grouped = $this->group_cart_items( $cart );

        // MISTURA ENTRE PRODUTOS: todas as unidades cobertas pela regra vao
        // para um unico conjunto, para que produtos diferentes somem juntos.
        if ( 'yes' === $rule['mix_products'] ) {
            $pool = array();

            foreach ( $grouped as $product_id => $variations ) {
                foreach ( $variations as $variation_id => $data ) {
                    if ( $this->rule_matches( $rule, $product_id, $variation_id ) ) {
                        $pool[ $product_id . '-' . $variation_id ] = $data;
                    }
                }
            }

            if ( $pool ) {
                $this->apply_over_set( $cart, $rule, 0, $pool, $applied );
            }

            return;
        }

        foreach ( $grouped as $product_id => $variations ) {

            // Filtra apenas as variacoes cobertas por esta regra.
            $covered = array();
            foreach ( $variations as $variation_id => $data ) {
                if ( $this->rule_matches( $rule, $product_id, $variation_id ) ) {
                    $covered[ $variation_id ] = $data;
                }
            }

            if ( empty( $covered ) ) {
                continue;
            }

            if ( 'no' === $rule['same_variation'] ) {
                // Soma todas as variacoes cobertas e aplica sobre o total.
                $this->apply_over_set( $cart, $rule, $product_id, $covered, $applied );
            } else {
                // Cada variacao e tratada como um conjunto independente.
                foreach ( $covered as $variation_id => $data ) {
                    $this->apply_over_set(
                        $cart,
                        $rule,
                        $product_id,
                        array( $variation_id => $data ),
                        $applied
                    );
                }
            }
        }
    }

    /**
     * Aplica a regra a um conjunto de unidades.
     *
     * Cada variacao continua na SUA linha: nada e dividido entre cores.
     * O desconto do produto inteiro e repartido entre as linhas de acordo
     * com a quantidade de cada uma.
     *
     * @param WC_Cart $cart
     * @param array   $rule
     * @param int     $product_id
     * @param array   $variations
     * @param array   $applied
     */
    private function apply_over_set( $cart, $rule, $product_id, $variations, &$applied ) {
        $total_qty   = 0;
        $lines       = array();
        $regular_sum = 0.0;

        foreach ( $variations as $variation_id => $data ) {
            $total_qty += $data['quantity'];
            foreach ( $data['lines'] as $key ) {
                $lines[] = $key;
                if ( isset( $cart->cart_contents[ $key ]['data'] ) ) {
                    $regular_sum += $this->get_baseline_price( $key, $cart->cart_contents[ $key ] )
                        * $data['qty_by_line'][ $key ];
                }
            }
        }

        if ( $total_qty < $rule['min_qty'] || $total_qty <= 0 ) {
            return;
        }

        // Preco de cada unidade, na ordem em que aparecem no carrinho.
        // Necessario quando o pack mistura produtos de valores diferentes:
        // a unidade avulsa paga o preco DELA, nao uma media.
        $unit_prices = array();

        foreach ( $variations as $data ) {
            foreach ( $data['lines'] as $key ) {
                if ( ! isset( $cart->cart_contents[ $key ]['data'] ) ) {
                    continue;
                }
                $preco = $this->get_baseline_price( $key, $cart->cart_contents[ $key ] );
                $vezes = isset( $data['qty_by_line'][ $key ] ) ? (int) $data['qty_by_line'][ $key ] : 0;
                for ( $i = 0; $i < $vezes; $i++ ) {
                    $unit_prices[] = $preco;
                }
            }
        }

        $plan = $this->build_plan( $rule, $total_qty, $unit_prices );

        if ( ! $plan ) {
            return;
        }

        // PROTEGAO: nunca cobrar acima do preco regular.
        if ( $plan['target'] >= $regular_sum ) {
            return;
        }

        $economia = round( $regular_sum - $plan['target'], wc_get_price_decimals() );

        if ( $economia <= 0 ) {
            return;
        }

        // Os itens do carrinho mantem o preco normal. O desconto entra
        // como taxa negativa no total, igual a um cupom.
        //
        // A MESMA regra pode valer para varios produtos no carrinho (um pack
        // fechado em cada). Nesse caso os descontos se somam: sobrescrever
        // aqui faria o cliente receber desconto de um pack so.
        if ( isset( $this->descontos[ $rule['id'] ] ) ) {
            $this->descontos[ $rule['id'] ]['valor'] += $economia;
        } else {
            $this->descontos[ $rule['id'] ] = array(
                'valor' => $economia,
                'label' => $this->build_fee_label( $rule, $total_qty ),
            );
        }

        // As linhas sao apenas marcadas, para explicar de onde vem o desconto.
        $breakdown = $this->build_breakdown( $rule, $total_qty, $regular_sum, $plan['target'] );
        $linhas    = array_values( array_unique( $lines ) );
        $index     = 0;

        foreach ( $linhas as $line_key ) {
            $index++;

            if ( ! isset( $cart->cart_contents[ $line_key ] ) ) {
                continue;
            }

            $qty = (int) $cart->cart_contents[ $line_key ]['quantity'];
            if ( $qty <= 0 ) {
                continue;
            }

            $antes = $this->get_baseline_price( $line_key, $cart->cart_contents[ $line_key ] ) * $qty;

            $applied[ $line_key ] = array(
                'original'  => $antes,
                'rule_id'   => $rule['id'],
                'label'     => $this->build_label( $rule ),
                'breakdown' => ( 1 === $index ) ? $breakdown : '',
            );
        }
    }

    /**
     * Adiciona o desconto como taxa negativa no total do carrinho.
     *
     * Nao e tributavel: o desconto e uma concessao comercial, e nao um
     * item que gere imposto.
     *
     * @param WC_Cart $cart
     */
    public function add_discount_fee( $cart ) {
        foreach ( $this->descontos as $desconto ) {
            $cart->add_fee( $desconto['label'], -1 * (float) $desconto['valor'], false );
        }
    }

    /**
     * Rotulo da linha de desconto no total.
     *
     * @param array $rule
     * @param int   $total_qty
     * @return string
     */
    private function build_fee_label( $rule, $total_qty ) {
        // Usa o nome que o lojista deu a regra; sem nome, mostra a regra em si.
        $nome = isset( $rule['title'] ) ? trim( (string) $rule['title'] ) : '';

        if ( '' === $nome ) {
            $nome = wp_strip_all_tags( $this->build_label( $rule ) );
        }

        return sprintf(
            /* translators: %s: nome do desconto */
            esc_html__( 'Desconto (%s)', 'hdiscount' ),
            $nome
        );
    }

    /**
     * Planeja a divisao entre unidades em pack e unidades avulsas.
     *
     * @param array $rule
     * @param int   $total_qty
     * @param float $regular_sum
     * @return array|null
     */
    private function build_plan( $rule, $total_qty, $unit_prices ) {
        $avg = array_sum( $unit_prices ) / max( 1, $total_qty );
        $min = max( 1, (int) $rule['min_qty'] );

        // Soma de uma faixa da lista de precos.
        $somar = function( $ini, $fim ) use ( $unit_prices ) {
            $soma = 0.0;
            for ( $i = $ini; $i < $fim && $i < count( $unit_prices ); $i++ ) {
                $soma += $unit_prices[ $i ];
            }
            return $soma;
        };

        if ( 'pack_price' === $rule['type'] ) {
            $pack_price = (float) $rule['pack_price'];
            if ( $pack_price <= 0 ) {
                return null;
            }

            $eligible = $this->get_eligible_qty( $rule, $total_qty );
            $packs    = intdiv( $eligible, $min );
            if ( $packs < 1 ) {
                return null;
            }

            $promo_qty = $packs * $min;
            $rest_qty  = $total_qty - $promo_qty;

            // O pack cobre as primeiras unidades do carrinho; as demais
            // pagam o preco delas mesmas.
            $promo_total = round( $packs * $pack_price, wc_get_price_decimals() );
            $rest_total  = round( $somar( $promo_qty, $total_qty ), wc_get_price_decimals() );

            return array(
                'promo_qty'   => $promo_qty,
                'promo_unit'  => $pack_price / $min,
                'promo_total' => $promo_total,
                'rest_unit'   => $avg,
                'rest_total'  => $rest_total,
                'target'      => $promo_total + $rest_total,
            );
        }

        if ( 'percent' === $rule['type'] ) {
            if ( $rule['percent'] <= 0 || $rule['percent'] > 100 ) {
                return null;
            }
            $promo_qty = $this->get_eligible_qty( $rule, $total_qty );
            if ( $promo_qty < 1 ) {
                return null;
            }
            $rest_qty  = $total_qty - $promo_qty;
            $promo_unit = $avg * ( 1 - ( $rule['percent'] / 100 ) );

            $promo_total = round( $somar( 0, $promo_qty ) * ( 1 - ( $rule['percent'] / 100 ) ), wc_get_price_decimals() );
            $rest_total  = round( $somar( $promo_qty, $total_qty ), wc_get_price_decimals() );

            return array(
                'promo_qty'   => $promo_qty,
                'promo_unit'  => $promo_unit,
                'promo_total' => $promo_total,
                'rest_unit'   => $avg,
                'rest_total'  => $rest_total,
                'target'      => $promo_total + $rest_total,
            );
        }

        if ( 'buy_x_get_y' === $rule['type'] ) {
            $buy = max( 1, (int) $rule['buy_qty'] );
            $get = min( (int) $rule['get_qty'], $buy - 1 );
            if ( $get < 1 ) {
                return null;
            }

            $eligible = $this->get_eligible_qty( $rule, $total_qty );
            $sets     = intdiv( $eligible, $buy );
            if ( $sets < 1 ) {
                return null;
            }

            // Pagas: preco normal. Gratis: zero.
            $paid_qty = $sets * $get;
            $free_qty = $total_qty - $paid_qty;

            // Paga as primeiras unidades; as demais sao cortesia.
            $promo_total = round( $somar( 0, $paid_qty ), wc_get_price_decimals() );

            return array(
                'promo_qty'   => $free_qty,
                'promo_unit'  => 0.0,
                'promo_total' => 0.0,
                'rest_unit'   => $avg,
                'rest_total'  => $promo_total,
                'target'      => $promo_total,
            );
        }

        return null;
    }

    /**
     * Calcula o TOTAL a pagar pelas unidades do conjunto elegivel.
     *
     * @param array $rule
     * @param int   $total_qty   Quantidade total de unidades no conjunto.
     * @param float $regular_sum Soma dos precos regulares (quantidade x preco).
     * @return float|null Total alvo, ou null se a regra nao se aplica.
     */
    private function calculate_target_total( $rule, $total_qty, $regular_sum ) {
        switch ( $rule['type'] ) {

            case 'pack_price':
                $pack_price = (float) $rule['pack_price'];
                if ( $pack_price <= 0 ) {
                    return null;
                }

                // Quantas unidades sao realmente elegiveis (respeitando o teto).
                $eligible = $this->get_eligible_qty( $rule, $total_qty );

                // Quantos packs completos cabem nas unidades elegiveis.
                $packs = intdiv( $eligible, $rule['min_qty'] );
                if ( $packs < 1 ) {
                    return null;
                }

                $in_pack      = $packs * $rule['min_qty'];
                $out_of_pack  = $eligible - $in_pack;

                // Total dos packs (preco fechado) + excedentes (preco regular).
                $target = ( $packs * $pack_price )
                    + ( ( $total_qty - $eligible + $out_of_pack ) * ( $regular_sum / $total_qty ) );

                return round( $target, wc_get_price_decimals() );

            case 'percent':
                if ( $rule['percent'] <= 0 || $rule['percent'] > 100 ) {
                    return null;
                }
                $eligible = $this->get_eligible_qty( $rule, $total_qty );
                if ( $eligible < 1 ) {
                    return null;
                }
                $avg      = $regular_sum / $total_qty;
                $discount = ( $eligible * $avg ) * ( $rule['percent'] / 100 );

                return round( $regular_sum - $discount, wc_get_price_decimals() );

            case 'buy_x_get_y':
                $buy = $rule['buy_qty'];
                $get = min( $rule['get_qty'], $buy );
                if ( $buy < 1 || $get < 1 || $get >= $buy ) {
                    return null;
                }

                $eligible = $this->get_eligible_qty( $rule, $total_qty );
                $sets     = intdiv( $eligible, $buy );
                if ( $sets < 1 ) {
                    return null;
                }

                // Em cada conjunto de $buy, paga $get.
                $paid_qty = $sets * $get;
                $avg      = $regular_sum / $total_qty;

                return round( $paid_qty * $avg, wc_get_price_decimals() );

            default:
                return null;
        }
    }

    /**
     * Define quantas unidades do conjunto sao elegiveis a regra.
     *
     * - max_qty = 0            -> todas as unidades acima do minimo
     * - limite = sim           -> no maximo o teto
     * - limite = nao           -> pode fechar varios packs
     *
     * @param array $rule
     * @param int   $total_qty
     * @return int
     */
    private function get_eligible_qty( $rule, $total_qty ) {
        $min = $rule['min_qty'];
        $max = $rule['max_qty'];

        // Sem teto: sempre que atingir o minimo, tudo vira elegivel.
        if ( $max <= 0 ) {
            return $total_qty;
        }

        // Com teto e limite desligado: pode repetir o pack quantas vezes couber.
        if ( 'yes' !== $rule['limit_to_max'] ) {
            $packs = intdiv( $total_qty, $min );
            return max( 0, $packs * $min );
        }

        // Com teto e limite ligado: no maximo o teto (mas nunca menos que o minimo).
        return min( $total_qty, max( $min, $max ) );
    }

    /**
     * Monta a conta detalhada do desconto.
     *
     * Sem isso, uma compra de 5 unidades em "3 por R$100" apareceria com
     * preco unitario de R$34,00 (a media entre o pack e o avulso), o que
     * parece erro de preco. Mostrar a conta evita a duvida.
     *
     * @param array $rule
     * @param int   $total_qty
     * @param float $regular_sum Soma dos precos de referencia.
     * @return string Texto em HTML simples.
     */
    private function build_breakdown( $rule, $total_qty, $regular_sum, $target = null ) {
        if ( $total_qty < 1 || $regular_sum <= 0 ) {
            return '';
        }

        $avg = $regular_sum / $total_qty;
        $min = max( 1, (int) $rule['min_qty'] );

        if ( 'pack_price' === $rule['type'] && $rule['pack_price'] > 0 ) {
            $eligible = $this->get_eligible_qty( $rule, $total_qty );
            $packs    = intdiv( $eligible, $min );

            if ( $packs < 1 ) {
                return '';
            }

            $in_pack = $packs * $min;
            $extra   = $total_qty - $in_pack;

            // Distingue "no total" de "misturadas entre produtos".
            $sufixo = ( isset( $rule['mix_products'] ) && 'yes' === $rule['mix_products'] )
                ? __( 'misturadas', 'hdiscount' )
                : __( 'no total', 'hdiscount' );

            // Fecha exato em packs inteiros: nada a detalhar.
            if ( 0 === $extra ) {
                return sprintf(
                    /* translators: 1: total de unidades do produto, 2: pacotes, 3: unidades por pack, 4: preco do pack */
                    esc_html__( '%1$d un %6$s: %2$d pack de %3$d por %4$s = %5$s', 'hdiscount' ),
                    $total_qty,
                    $packs,
                    $min,
                    wc_price( $rule['pack_price'] ),
                    wc_price( $target ),
                    $sufixo
                );
            }

            return sprintf(
                /* translators: 1: total de unidades, 2: pacotes, 3: unidades por pack, 4: preco do pack, 5: avulsas, 6: preco unitario regular */
                esc_html__( '%1$d un %8$s: %2$d pack de %3$d por %4$s + %5$d avulsas a %6$s = %7$s', 'hdiscount' ),
                $total_qty,
                $packs,
                $min,
                wc_price( $rule['pack_price'] ),
                $extra,
                wc_price( $avg ),
                wc_price( $target ),
                $sufixo
            );
        }

        if ( 'percent' === $rule['type'] && $rule['percent'] > 0 ) {
            return sprintf(
                /* translators: 1: percentual, 2: unidades com desconto, 3: preco de referencia */
                esc_html__( '%1$s%% OFF em %2$d un (antes %3$s)', 'hdiscount' ),
                wc_format_decimal( $rule['percent'], 1 ),
                $total_qty,
                wc_price( $avg )
            );
        }

        if ( 'buy_x_get_y' === $rule['type'] ) {
            $buy = max( 1, (int) $rule['buy_qty'] );
            $get = min( (int) $rule['get_qty'], $buy - 1 );
            if ( $get < 1 ) {
                return '';
            }

            $eligible = $this->get_eligible_qty( $rule, $total_qty );
            $sets     = intdiv( $eligible, $buy );
            if ( $sets < 1 ) {
                return '';
            }

            return sprintf(
                /* translators: 1: conjuntos, 2: unidades por conjunto, 3: unidades pagas */
                esc_html__( '%1$d grupo(s) de %2$d: paga %3$d', 'hdiscount' ),
                $sets,
                $buy,
                $sets * $get
            );
        }

        return '';
    }

    /**
     * Monta o texto descritivo da regra (usado no carrinho).
     *
     * @param array $rule
     * @return string
     */
    private function build_label( $rule ) {
        switch ( $rule['type'] ) {
            case 'pack_price':
                return sprintf(
                    /* translators: 1: quantidade, 2: preco do pack */
                    __( 'Leve %1$d por %2$s', 'hdiscount' ),
                    $rule['min_qty'],
                    wc_price( $rule['pack_price'] )
                );
            case 'percent':
                return sprintf(
                    /* translators: 1: quantidade, 2: percentual */
                    __( '%1$d+ unidades: %2$s%% OFF', 'hdiscount' ),
                    $rule['min_qty'],
                    wc_format_decimal( $rule['percent'], 1 )
                );
            case 'buy_x_get_y':
                return sprintf(
                    /* translators: 1: quantidade comprada, 2: quantidade paga */
                    __( 'Compre %1$d, pague %2$d', 'hdiscount' ),
                    $rule['buy_qty'],
                    $rule['get_qty']
                );
        }
        return '';
    }

    /**
     * Adiciona aviso de promocao na linha do carrinho.
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function append_cart_item_notice( $item_data, $cart_item ) {
        if ( empty( $cart_item['hdiscount_rule_id'] ) || empty( $cart_item['hdiscount_label'] ) ) {
            return $item_data;
        }

        // Evita linha duplicada caso o filtro seja registrado mais de uma vez.
        $label = __( 'Promoção', 'hdiscount' );
        foreach ( (array) $item_data as $linha ) {
            if ( isset( $linha['key'] ) && $label === $linha['key'] ) {
                return $item_data;
            }
        }

        $texto = $cart_item['hdiscount_label'];
        $conta = isset( $cart_item['hdiscount_breakdown'] )
            ? (string) $cart_item['hdiscount_breakdown']
            : '';

        // 'display' pode conter HTML, 'value' fica em texto puro.
        $display = $texto;
        $plain   = wp_strip_all_tags( $texto );

        // A conta aparece em uma linha do produto, para o cliente entender
        // que o desconto entra no total e nao no preco unitario.
        if ( '' !== $conta ) {
            $display .= '<br><small>' . $conta . '</small>';
            $plain   .= ' - ' . wp_strip_all_tags( $conta );
        }

        $item_data[] = array(
            'key'     => $label,
            'value'   => $plain,
            'display' => $display,
        );

        return $item_data;
    }

    /**
     * Carrega o CSS do badge na loja.
     */
    public function enqueue_storefront_assets() {
        wp_enqueue_style(
            'hdiscount-storefront',
            HDISCOUNT_PLUGIN_URL . 'assets/admin.css',
            array(),
            HDISCOUNT_VERSION
        );
    }

    /**
     * Exibe o badge da promocao na pagina do produto.
     */
    public function display_product_badge() {
        global $product;

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $product_id   = $product->get_id();
        $variation_id = $product->is_type( 'variation' ) ? $product_id : $product_id;
        $parent_id    = $product->get_parent_id();

        // Sem extensao, uma variacao so recebe o badge se estiver listada.
        // Com extensao, o produto pai cobre todas as variacoes.
        $self_id    = ( $parent_id && ! $this->rule_extends_by_default() ) ? $product_id : $parent_id;
        $candidate  = $parent_id ? array( $parent_id, $product_id ) : array( $product_id );

        $rules = $this->get_active_rules();

        foreach ( $rules as $rule ) {
            if ( 'yes' === $rule['extend'] ) {
                // Produto pai listado cobre tudo.
                if ( in_array( $parent_id, $rule['product_ids'], true )
                    || in_array( $product_id, $rule['product_ids'], true ) ) {
                    $this->render_badge( $rule );
                    return;
                }
                continue;
            }

            // Sem extensao: precisa estar listado exatamente.
            if ( in_array( $candidate[0], $rule['product_ids'], true )
                || in_array( $candidate[1], $rule['product_ids'], true ) ) {
                $this->render_badge( $rule );
                return;
            }
        }
    }

    /**
     * Indica se a regra ja nasce com extensao ligada (usado no badge).
     *
     * @return bool
     */
    private function rule_extends_by_default() {
        return true;
    }

    /**
     * Renderiza o HTML do badge.
     *
     * @param array $rule
     */
    private function render_badge( $rule ) {
        $label = $this->build_label( $rule );
        if ( '' === $label ) {
            return;
        }

        printf(
            '<div class="hdiscount-badge"><span class="hdiscount-badge__tag">%s</span><span class="hdiscount-badge__text">%s</span></div>',
            esc_html__( 'Promocao', 'hdiscount' ),
            esc_html( wp_strip_all_tags( $label ) )
        );
    }
}