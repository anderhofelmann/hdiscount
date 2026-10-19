<?php
/**
 * Classe Admin: registra CPT, meta boxes, colunas, salvamento
 */
// Acesso direto ao arquivo e bloqueado: so o WordPress pode caregar.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HDiscount_Admin {

    /**
     * Slug do Custom Post Type
     */
    const CPT_SLUG = 'hdiscount_rule';

    /**
     * Construtor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

        // Atalho de volta para a lista, dentro da caixa "Publicar".
        add_filter( 'post_submitbox_misc_actions', array( $this, 'render_back_link' ) );

        // Aba "Sobre" com o guia de uso.
        add_action( 'admin_menu', array( $this, 'register_about_page' ) );
        add_action( 'save_post', array( $this, 'save_meta_box_data' ), 10, 2 );
        add_filter( 'manage_edit-' . self::CPT_SLUG . '_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_' . self::CPT_SLUG . '_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Registra o Custom Post Type "Regra de Desconto"
     */
    public function register_cpt() {
        $labels = array(
            'name'               => _x( 'Regras de Desconto', 'post type general name', 'hdiscount' ),
            'singular_name'      => _x( 'Regra de Desconto', 'post type singular name', 'hdiscount' ),
            'menu_name'          => _x( 'HDiscount', 'admin menu', 'hdiscount' ),
            'name_admin_bar'     => _x( 'Regra de Desconto', 'add new on admin bar', 'hdiscount' ),
            'add_new'            => _x( 'Adicionar Nova', 'hdiscount' ),
            'add_new_item'       => __( 'Adicionar Nova Regra', 'hdiscount' ),
            'new_item'           => __( 'Nova Regra', 'hdiscount' ),
            'edit_item'          => __( 'Editar Regra', 'hdiscount' ),
            'view_item'          => __( 'Ver Regra', 'hdiscount' ),
            'all_items'          => __( 'Regras de Descontos', 'hdiscount' ),
            'search_items'       => __( 'Buscar Regras', 'hdiscount' ),
            'not_found'          => __( 'Nenhuma regra encontrada', 'hdiscount' ),
            'not_found_in_trash' => __( 'Nenhuma regra na lixeira', 'hdiscount' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'woocommerce', // Aparece no menu WooCommerce
            'menu_icon'          => 'dashicons-tag',
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => array( 'title' ),
            // Nao expor via REST: as regras contem dados comerciais
            // (precos de pack) e nao ha edicao via API.
            'show_in_rest'       => false,
            'exclude_from_search' => true,
            'menu_position'      => 56, // Após "Cupons" no menu WC
        );

        register_post_type( self::CPT_SLUG, $args );
    }

    /**
     * Registra a aba "Sobre" dentro do menu do HDiscount.
     */
    public function register_about_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT_SLUG,
            __( 'Sobre o HDiscount', 'hdiscount' ),
            __( 'Sobre', 'hdiscount' ),
            'edit_posts',
            self::CPT_SLUG . '_about',
            array( $this, 'render_about_page' )
        );
    }

    /**
     * Conteudo da aba "Sobre": explica o plugin antes da primeira regra.
     */
    public function render_about_page() {
        // Guarda final: a tela so existe dentro do painel.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Sem permissao para ver esta pagina.', 'hdiscount' ) );
        }

        $versao = defined( 'HDISCOUNT_VERSION' ) ? HDISCOUNT_VERSION : '';
        ?>
        <div class="wrap about-wrap hdiscount-about">

            <h1>
                <?php esc_html_e( 'HDiscount', 'hdiscount' ); ?>
                <span class="about-text"><?php esc_html_e( 'Regras de desconto por quantidade para WooCommerce', 'hdiscount' ); ?></span>
                <?php printf( '<span class="dashicons dashicons-tag"></span> %s', esc_html( $versao ) ); ?>
            </h1>

            <p class="about-text">
                <?php esc_html_e( 'Crie promocoes do tipo "leve 3 por R$ 100,00". O preco de cada produto continua normal, e o desconto aparece so no total do carrinho, como um cupom.', 'hdiscount' ); ?>
            </p>

            <hr>

            <div class="hdiscount-about-grid">

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( '1. Crie a regra', 'hdiscount' ); ?></h2>
                    <ol>
                        <li><?php esc_html_e( 'Va em HDiscount e clique em "Adicionar Nova".', 'hdiscount' ); ?></li>
                        <li><?php esc_html_e( 'De um NOME a regra: e ele que o cliente ve no total, entre parenteses.', 'hdiscount' ); ?></li>
                        <li><?php esc_html_e( 'Escolha o tipo de desconto:', 'hdiscount' ); ?></li>
                    </ol>
                    <ul class="hdiscount-about-types">
                        <li><strong><?php esc_html_e( 'Preco fixo do pack', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( '3 unidades por um preco total combinado. Ex: "Leve 3 por R$ 100,00".', 'hdiscount' ); ?></li>
                        <li><strong><?php esc_html_e( 'Desconto percentual', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'X% de desconto a partir da quantidade minima.', 'hdiscount' ); ?></li>
                        <li><strong><?php esc_html_e( 'Compre X leve Y', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'O cliente leva mais unidades pagando por menos. Ex: "Compre 4, pague 3".', 'hdiscount' ); ?></li>
                    </ul>
                </div>

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( '2. Escolha os produtos', 'hdiscount' ); ?></h2>
                    <p><?php esc_html_e( 'Busque por nome ou SKU. O produto pai vem primeiro na lista e as variacoes dele aparecem logo abaixo.', 'hdiscount' ); ?></p>
                    <p><strong><?php esc_html_e( 'Aplicar a todas as variacoes', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'marcado, escolher o produto ja vale para todas as cores e tamanhos, inclusive os que forem criados depois.', 'hdiscount' ); ?></p>
                    <p><strong><?php esc_html_e( 'Misturar produtos no pack', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'marcado, as unidades do pack podem vir de produtos diferentes. Com "Camiseta" e "Calca" na lista, comprar 1 + 2 fecha o pack de 3.', 'hdiscount' ); ?></p>
                </div>

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( '3. Ajuste o periodo', 'hdiscount' ); ?></h2>
                    <p><?php esc_html_e( 'Informe a data e hora de inicio e de fim. Sem data de fim, a regra vale por tempo indeterminado.', 'hdiscount' ); ?></p>
                    <p><?php esc_html_e( 'Para interromper a promocao antes da hora, basta colocar a regra como Inativa, sem mexer nas datas.', 'hdiscount' ); ?></p>
                </div>

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( 'Quantidade maxima', 'hdiscount' ); ?></h2>
                    <p><?php esc_html_e( 'Use "Quantidade maxima" para limitar quantas unidades entram no pack.', 'hdiscount' ); ?></p>
                    <p><?php esc_html_e( 'Com minimo 3 e maximo 3, comprando 4 unidades, 3 recebem o preco do pack e a quarta fica pelo preco normal.', 'hdiscount' ); ?></p>
                </div>

            </div>

            <hr>

            <h2><?php esc_html_e( 'Como fica no carrinho', 'hdiscount' ); ?></h2>
            <p><?php esc_html_e( 'Com "3 por R$ 100,00" e 5 unidades de R$ 35,00 (em cores diferentes):', 'hdiscount' ); ?></p>

            <table class="widefat striped hdiscount-about-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Unidades', 'hdiscount' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'hdiscount' ); ?></th>
                        <th><?php esc_html_e( 'Como fecha a conta', 'hdiscount' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>2</td><td>R$ 70,00</td><td><?php esc_html_e( 'abaixo do minimo, sem promocao', 'hdiscount' ); ?></td></tr>
                    <tr><td>3</td><td>R$ 100,00</td><td><?php esc_html_e( '1 pack fechado', 'hdiscount' ); ?></td></tr>
                    <tr><td>4</td><td>R$ 135,00</td><td><?php esc_html_e( '1 pack + 1 avulsa a R$ 35,00', 'hdiscount' ); ?></td></tr>
                    <tr><td>6</td><td>R$ 200,00</td><td><?php esc_html_e( '2 packs fechados', 'hdiscount' ); ?></td></tr>
                </tbody>
            </table>

            <p class="description" style="margin-top:12px;">
                <?php esc_html_e( 'No total do carrinho o desconto aparece em uma linha so, somando todos os produtos que fecharam pack.', 'hdiscount' ); ?>
            </p>

            <hr>

            <h2><?php esc_html_e( 'Dicas', 'hdiscount' ); ?></h2>
            <ul class="hdiscount-about-tips">
                <li><?php esc_html_e( 'Use produtos com o mesmo preco quando ativar "Misturar produtos", para o desconto ser sempre igual.', 'hdiscount' ); ?></li>
                <li><?php esc_html_e( 'O painel mostra um exemplo de calculo enquanto voce preenche a regra. Se algo estiver faltando, ele avisa.', 'hdiscount' ); ?></li>
                <li><?php esc_html_e( 'Varias regras podem valer ao mesmo tempo. Cada uma soma no total separadamente.', 'hdiscount' ); ?></li>
                <li><?php esc_html_e( 'Desconfie de um pack cujo preco seja MAIOR que o total dos produtos: nesse caso a regra simplesmente nao se aplica.', 'hdiscount' ); ?></li>
            </ul>

            <p class="hdiscount-about-footer">
                <?php
                printf(
                    /* translators: 1: nome do autor, 2: link do GitHub */
                    esc_html__( 'Desenvolvido por %1$s - %2$s', 'hdiscount' ),
                    esc_html__( 'Anderson Höfelmann', 'hdiscount' ),
                    '<a href="https://github.com/anderhofelmann" target="_blank" rel="noopener noreferrer">github.com/anderhofelmann</a>'
                );
                ?>
                &nbsp;|&nbsp;
                <?php
                printf(
                    esc_html__( 'Versao %s', 'hdiscount' ),
                    esc_html( $versao )
                );
                ?>
            </p>

        </div>
        <?php
    }

    /**
     * Atalho para voltar a lista de regras.
     *
     * Aparece dentro da caixa "Publicar", logo abaixo do botao, para
     * facilitar a volta depois de salvar ou publicar uma regra.
     *
     * @return void
     */
    public function render_back_link() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( $screen && false === strpos( (string) $screen->post_type, self::CPT_SLUG ) ) {
            return;
        }

        $url = add_query_arg(
            array( 'post_type' => self::CPT_SLUG ),
            admin_url( 'edit.php' )
        );

        printf(
            '<div class="hdiscount-back-link"><a href="%s">&#8592; %s</a></div>',
            esc_url( $url ),
            esc_html__( 'Voltar para as Regras de Descontos', 'hdiscount' )
        );
    }

    /**
     * Adiciona meta boxes na tela de edição da regra
     */
    public function add_meta_boxes() {
        add_meta_box(
            'hdiscount_rule_config',
            __( 'Configuração da Regra de Desconto', 'hdiscount' ),
            array( $this, 'render_meta_box' ),
            self::CPT_SLUG,
            'normal',
            'high'
        );

        add_meta_box(
            'hdiscount_rule_period',
            __( 'Período de Vigência', 'hdiscount' ),
            array( $this, 'render_period_meta_box' ),
            self::CPT_SLUG,
            'side',
            'high'
        );

        add_meta_box(
            'hdiscount_rule_products',
            __( 'Produtos Aplicáveis', 'hdiscount' ),
            array( $this, 'render_products_meta_box' ),
            self::CPT_SLUG,
            'normal',
            'default'
        );
    }

    /**
     * Renderiza meta box principal: tipo de desconto, quantidades, preço
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'hdiscount_save_meta', 'hdiscount_meta_nonce' );

        // Recupera valores salvos
        $discount_type       = get_post_meta( $post->ID, '_hdiscount_type', true );
        $min_qty             = get_post_meta( $post->ID, '_hdiscount_min_qty', true );
        $max_qty             = get_post_meta( $post->ID, '_hdiscount_max_qty', true );
        $pack_price          = get_post_meta( $post->ID, '_hdiscount_pack_price', true );
        $discount_percent    = get_post_meta( $post->ID, '_hdiscount_percent', true );
        $apply_same_variation = get_post_meta( $post->ID, '_hdiscount_same_variation', true );
        $limit_to_max        = get_post_meta( $post->ID, '_hdiscount_limit_to_max', true );
        $mix_products        = get_post_meta( $post->ID, '_hdiscount_mix_products', true );
        $mix_products        = '' === $mix_products ? 'no' : $mix_products;

        // Defaults
        $discount_type        = $discount_type ?: 'pack_price';
        $min_qty              = $min_qty ?: 1;
        $max_qty              = $max_qty ?: 0; // 0 = sem limite
        $apply_same_variation = $apply_same_variation ?: 'yes';
        $limit_to_max         = $limit_to_max ?: 'no';
        ?>
        <table class="form-table" style="width: 100%;">
            <!-- TIPO DE DESCONTO -->
            <tr>
                <th scope="row" style="width: 200px;">
                    <label for="hdiscount_type"><?php _e( 'Tipo de Desconto', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <select name="hdiscount_type" id="hdiscount_type" style="width: 300px;">
                        <option value="pack_price" <?php selected( $discount_type, 'pack_price' ); ?>>
                            <?php _e( 'Preço Fixo do Pack (ex: 3 por R$ 100,00)', 'hdiscount' ); ?>
                        </option>
                        <option value="percent" <?php selected( $discount_type, 'percent' ); ?>>
                            <?php _e( 'Desconto Percentual (ex: 20% off na quantidade)', 'hdiscount' ); ?>
                        </option>
                        <option value="buy_x_get_y" <?php selected( $discount_type, 'buy_x_get_y' ); ?>>
                            <?php _e( 'Compre X Leve Y (ex: Leve 4, Pague 3)', 'hdiscount' ); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e( '<strong>Preço Fixo do Pack:</strong> Define um valor total para X unidades (ex: 3 unidades por R$ 100,00).<br>
                        <strong>Desconto Percentual:</strong> Aplica % de desconto nas unidades que atingirem a quantidade mínima.<br>
                        <strong>Compre X Leve Y:</strong> Cliente leva quantidade maior pagando apenas a quantidade menor.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- QUANTIDADE MÍNIMA -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_min_qty"><?php _e( 'Quantidade Mínima do Pack', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_min_qty" id="hdiscount_min_qty" value="<?php echo esc_attr( $min_qty ); ?>" min="1" step="1" style="width: 100px;" required />
                    <p class="description">
                        <?php _e( 'Quantidade mínima de unidades que o cliente deve comprar para ativar a regra. Ex: se "3", a promoção só vale comprando 3 ou mais.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- QUANTIDADE MÁXIMA -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_max_qty"><?php _e( 'Quantidade Máxima do Pack (0 = sem limite)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_max_qty" id="hdiscount_max_qty" value="<?php echo esc_attr( $max_qty ); ?>" min="0" step="1" style="width: 100px;" />
                    <p class="description">
                        <?php _e( 'Limite máximo de unidades que entram na promoção. Ex: se mín=3 e máx=3, comprando 4 unidades, apenas 3 entram no preço promocional e a 4ª volta ao preço normal. Deixe 0 para aplicar a todas as unidades acima do mínimo.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- APLICAR APENAS ATÉ O MÁXIMO -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_limit_to_max"><?php _e( 'Limitar Desconto à Quantidade Máxima?', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="hdiscount_limit_to_max" id="hdiscount_limit_to_max" value="yes" <?php checked( $limit_to_max, 'yes' ); ?> />
                        <?php _e( 'Sim, aplicar desconto apenas até a quantidade máxima definida acima', 'hdiscount' ); ?>
                    </label>
                    <p class="description">
                        <?php _e( 'Se marcado: o desconto para na quantidade máxima. Com mín=3 e máx=3, ao comprar 4 itens, apenas 3 recebem o preço do pack e a 4ª fica pelo preço normal.<br>
                        Se desmarcado: a regra se repete. Com mín=3 e máx=3, ao comprar 7 itens, fecham-se 2 packs (6 itens) e o 7º fica pelo preço normal.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- PREÇO DO PACK (visível apenas para tipo pack_price) -->
            <tr class="hdiscount-field-pack_price" style="<?php echo 'pack_price' === $discount_type ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="hdiscount_pack_price"><?php _e( 'Preço Total do Pack (R$)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_pack_price" id="hdiscount_pack_price" value="<?php echo esc_attr( $pack_price ); ?>" step="0.01" style="width: 150px;" />
                    <p class="description">
                        <?php _e( 'Valor TOTAL que o cliente pagará pelas unidades do pack. Ex: para "3 por R$ 100,00", digite 100. O preço unitário efetivo será calculado automaticamente (100/3 = 33,33 cada).', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- DESCONTO PERCENTUAL (visível apenas para tipo percent) -->
            <tr class="hdiscount-field-percent" style="<?php echo 'percent' === $discount_type ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="hdiscount_percent"><?php _e( 'Desconto (%)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_percent" id="hdiscount_percent" value="<?php echo esc_attr( $discount_percent ); ?>" step="0.01" style="width: 100px;" />
                    <p class="description">
                        <?php _e( 'Porcentagem de desconto aplicada a cada unidade que atingir a quantidade mínima. Ex: 20% desconto em cada unidade a partir da 3ª.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- COMPRE X LEVE Y (visível apenas para tipo buy_x_get_y) -->
            <tr class="hdiscount-field-buy_x_get_y" style="<?php echo 'buy_x_get_y' === $discount_type ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="hdiscount_buy_qty"><?php _e( 'Comprar (X)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <?php
                    // Defaults: precisao ser sempre validos, mesmo com o campo oculto.
                    // Um input invalido e invisivel trava o envio do formulario no navegador.
                    $buy_qty = (int) get_post_meta( $post->ID, '_hdiscount_buy_qty', true );
                    $buy_qty = $buy_qty > 0 ? $buy_qty : max( 2, $min_qty );

                    $get_qty = (int) get_post_meta( $post->ID, '_hdiscount_get_qty', true );
                    $get_qty = $get_qty > 0 ? $get_qty : max( 1, $buy_qty - 1 );
                    ?>
                    <label for="hdiscount_buy_qty" class="screen-reader-text"><?php _e( 'Quantidade comprada', 'hdiscount' ); ?></label>
                    <input type="number" name="hdiscount_buy_qty" id="hdiscount_buy_qty" value="<?php echo esc_attr( $buy_qty ); ?>" step="1" style="width: 80px;" />

                    <span style="margin: 0 10px;"><?php _e( 'pague', 'hdiscount' ); ?></span>

                    <label for="hdiscount_get_qty" class="screen-reader-text"><?php _e( 'Quantidade paga', 'hdiscount' ); ?></label>
                    <input type="number" name="hdiscount_get_qty" id="hdiscount_get_qty" value="<?php echo esc_attr( $get_qty ); ?>" step="1" style="width: 80px;" />
                    <p class="description">
                        <?php _e( 'Quantas unidades o cliente leva pagando por um grupo menor. Ex: Comprar 4 e pagar 3 = o cliente leva 4 itens e paga o preco de 3. Este campo precisa ser MENOR que o campo "Comprar".', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- VARIAÇÕES IGUAIS OU DIFERENTES -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_same_variation"><?php _e( 'Aplicar a Variações', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="radio" name="hdiscount_same_variation" value="yes" <?php checked( $apply_same_variation, 'yes' ); ?> />
                        <?php _e( 'Apenas variações IDÊNTICAS (mesma variação)', 'hdiscount' ); ?>
                    </label><br>
                    <label>
                        <input type="radio" name="hdiscount_same_variation" value="no" <?php checked( $apply_same_variation, 'no' ); ?> />
                        <?php _e( 'Quaisquer variações do mesmo produto (misturadas)', 'hdiscount' ); ?>
                    </label>
                    <p class="description">
                        <?php _e( '<strong>Apenas variações idênticas:</strong> Cliente deve comprar 3 da MESMA variação (ex: 3 camisetas vermelhas P).<br>
                        <strong>Quaisquer variações:</strong> Cliente pode misturar (ex: 1 vermelha P + 1 azul M + 1 preta G = 3 no pack).<br>
                        <strong>Para produtos simples:</strong> esta opção não afeta o funcionamento.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- MISTURAR PRODUTOS NO PACK -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_mix_products"><?php _e( 'Misturar Produtos no Pack', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="hdiscount_mix_products" id="hdiscount_mix_products" value="yes" <?php checked( $mix_products, 'yes' ); ?> />
                        <?php _e( 'Sim, as unidades do pack podem vir de produtos diferentes', 'hdiscount' ); ?>
                    </label>
                    <p class="description">
                        <?php _e( '<strong>Desmarcado (padrão):</strong> o pack é montado com unidades de UM produto só.<br>
                        <strong>Marcado:</strong> as unidades podem somar entre todos os produtos da lista. Ex: com "Camiseta" e "Calça" na regra, comprar 1 camiseta + 2 calças fecha o pack de 3.<br>
                        <em>Só faz sentido com mais de um produto na lista.</em><br>
                        <strong>Recomendação:</strong> use produtos com o mesmo preço, assim o desconto do pack é sempre igual.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <div id="hdiscount-preview-box" class="hdiscount-preview-box">
            <p class="hdiscount-preview-box__title"><?php _e( 'Como o cliente vai ver:', 'hdiscount' ); ?></p>
            <p id="hdiscount-preview-error" class="hdiscount-preview-box__error" style="display:none;"></p>
            <p id="hdiscount-badge-preview" class="hdiscount-preview-box__text"></p>
            <ul id="hdiscount-examples" class="hdiscount-preview-box__examples"></ul>
            <p class="hdiscount-preview-box__note"><?php _e( 'Voce pode publicar a regra mesmo com campos faltando: ela simplesmente nao sera aplicada enquanto estiver incompleta.', 'hdiscount' ); ?></p>
        </div>

        <script type="text/javascript">
        jQuery(function($){
            function toggleFields(){
                var type = $('#hdiscount_type').val();
                $('.hdiscount-field-pack_price').toggle('pack_price' === type);
                $('.hdiscount-field-percent').toggle('percent' === type);
                $('.hdiscount-field-buy_x_get_y').toggle('buy_x_get_y' === type);
            }
            $('#hdiscount_type').on('change', toggleFields);
            toggleFields();
        });
        </script>
        <?php
    }

    /**
     * Renderiza meta box: Período de Vigência
     */
    public function render_period_meta_box( $post ) {
        $start_date = get_post_meta( $post->ID, '_hdiscount_start_date', true );
        $end_date   = get_post_meta( $post->ID, '_hdiscount_end_date', true );
        $status     = get_post_meta( $post->ID, '_hdiscount_status', true );
        $status     = $status ?: 'active';

        // Formata para input datetime-local
        $start_fmt = $start_date ? date( 'Y-m-d\TH:i', strtotime( $start_date ) ) : '';
        $end_fmt   = $end_date ? date( 'Y-m-d\TH:i', strtotime( $end_date ) ) : '';
        ?>
        <table class="form-table" style="width: 100%;">
            <tr>
                <th scope="row" style="width: 130px;">
                    <label for="hdiscount_status"><?php _e( 'Status', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <select name="hdiscount_status" id="hdiscount_status" style="width: 200px;">
                        <option value="active" <?php selected( $status, 'active' ); ?>><?php _e( 'Ativa', 'hdiscount' ); ?></option>
                        <option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php _e( 'Inativa', 'hdiscount' ); ?></option>
                        <option value="scheduled" <?php selected( $status, 'scheduled' ); ?>><?php _e( 'Agendada (futura)', 'hdiscount' ); ?></option>
                    </select>
                    <p class="description">
                        <?php _e( '<strong>Ativa:</strong> Vigente agora (respeita datas se preenchidas).<br>
                        <strong>Inativa:</strong> Desligada, não aplica em carrinhos.<br>
                        <strong>Agendada:</strong> Ficará ativa automaticamente na data de início.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="hdiscount_start_date"><?php _e( 'Início da Vigência', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="datetime-local" name="hdiscount_start_date" id="hdiscount_start_date" value="<?php echo esc_attr( $start_fmt ); ?>" style="width: 220px;" />
                    <p class="description">
                        <?php _e( 'Data e hora em que a regra passa a valer. Deixe vazio para iniciar imediatamente. Formato: AAAA-MM-DDTHH:MM (ex: 2024-12-25T00:00).', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="hdiscount_end_date"><?php _e( 'Fim da Vigência', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="datetime-local" name="hdiscount_end_date" id="hdiscount_end_date" value="<?php echo esc_attr( $end_fmt ); ?>" style="width: 220px;" />
                    <p class="description">
                        <?php _e( 'Data e hora em que a regra expira. Deixe vazio para não expirar. Após esta data/hora, a regra não será mais aplicada.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderiza meta box: Seleção de Produtos
     */
    public function render_products_meta_box( $post ) {
        $product_ids = get_post_meta( $post->ID, '_hdiscount_product_ids', true );

        // O valor e gravado como CSV ("10,20,30"). Tratar a string inteira
        // como array faria intval() devolver so o primeiro ID.
        $product_ids = $product_ids
            ? array_values( array_filter( array_map( 'intval', array_map( 'trim', explode( ',', (string) $product_ids ) ) ) ) )
            : array();

        // Padrao: sim. Selecionar o produto variavel ja vale para as variacoes.
        $extend = get_post_meta( $post->ID, '_hdiscount_extend_variations', true );
        $extend = '' === $extend ? 'yes' : $extend;

        // Dados para exibicao, distinguindo produto simples, variavel e variacao.
        $items = array();
        foreach ( $product_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) { continue; }

            $parent_id = $product->get_parent_id();

            if ( $parent_id ) {
                // Eh uma variacao: descobre o nome do produto pai.
                $parent    = wc_get_product( $parent_id );
                $items[] = array(
                    'id'        => $pid,
                    'name'      => $product->get_name(),
                    'type'      => 'variation',
                    'parent'    => $parent ? $parent->get_name() : '',
                    'parent_id' => $parent_id,
                    'sku'       => $product->get_sku(),
                );
            } else {
                $count = 0;
                if ( 'variable' === $product->get_type() ) {
                    $children = wc_get_products( array(
                        'post_parent' => $pid,
                        'post_type'   => 'product_variation',
                        'fields'      => 'ids',
                        'posts_per_page' => -1,
                    ) );
                    $count = is_array( $children ) ? count( $children ) : 0;
                }
                $items[] = array(
                    'id'        => $pid,
                    'name'      => $product->get_name(),
                    'type'      => $product->get_type(),
                    'parent'    => '',
                    'parent_id' => 0,
                    'sku'       => $product->get_sku(),
                    'qtd_var'   => $count,
                );
            }
        }
        // Organiza a lista: cada produto pai vem primeiro e as proprias
        // variacoes aparecem logo abaixo, recuadas.
        //
        // A agrupacao e feita pelo "dono" da secao em duas passadas, para
        // funcionar mesmo quando a variacao foi adicionada antes do pai.
        $sections = array();
        $order    = array();

        foreach ( $items as $item ) {
            $is_variation = ( 'variation' === $item['type'] && ! empty( $item['parent_id'] ) );

            $owner = $is_variation ? $item['parent_id'] : $item['id'];

            if ( ! isset( $sections[ $owner ] ) ) {
                $sections[ $owner ] = array( 'parent' => null, 'children' => array() );
                $order[]            = $owner;
            }

            if ( $is_variation ) {
                $sections[ $owner ]['children'][] = $item;
            } else {
                $sections[ $owner ]['parent'] = $item;
            }
        }

        $ordered = array();

        foreach ( $order as $owner ) {
            $section = $sections[ $owner ];

            // Variacao cujo pai nao esta na lista entra como item principal.
            if ( null === $section['parent'] ) {
                foreach ( $section['children'] as $orfa ) {
                    $ordered[] = $orfa;
                }
                continue;
            }

            $ordered[] = $section['parent'];

            foreach ( $section['children'] as $filho ) {
                $filho['is_child'] = true;
                $ordered[]         = $filho;
            }
        }

        // Se a extensao esta desligada, um produto variavel sozinho nao cobre nada.
$sem_extensao = ( 'yes' !== $extend );
$pais_variaveis = array();

if ( $sem_extensao ) {
    foreach ( $product_ids as $pid ) {
        $p = wc_get_product( $pid );
        if ( $p && ! $p->get_parent_id() && 'variable' === $p->get_type() ) {
            $pais_variaveis[] = $p->get_name();
        }
    }
}
// Quando a mistura esta ligada, o preco do pack e fixo. Com produtos de
// preços diferentes, o quanto o cliente economiza varia conforme quais itens
// entram no pack, entao vale avisar.
$mistura = ( 'yes' === get_post_meta( $post->ID, '_hdiscount_mix_products', true ) );
$precos_produto = array();

if ( $mistura ) {
    foreach ( $product_ids as $pid ) {
        $p = wc_get_product( $pid );
        if ( ! $p || $p->get_parent_id() ) {
            continue;
        }
        $preco = (float) $p->get_price();
        if ( $preco > 0 ) {
            $precos_produto[ $pid ] = array( 'nome' => $p->get_name(), 'preco' => $preco );
        }
    }
}

$mesmo_preco = ( count( $precos_produto ) > 0 && 1 === count( array_unique( wp_list_pluck( $precos_produto, 'preco' ) ) ) );
?>
        <div id="hdiscount-products-wrapper">
            <?php if ( $mistura && $precos_produto && $mesmo_preco ) : ?>
                <div class="notice notice-info inline" style="margin: 0 0 12px;">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: quantidade de produtos, 2: preco */
                            esc_html__( 'Os %1$d produtos da lista tem o mesmo preco (%2$s), entao o desconto do pack sera sempre o mesmo.', 'hdiscount' ),
                            count( $precos_produto ),
                            wc_price( reset( $precos_produto )['preco'] )
                        );
                        ?>
                    </p>
                </div>
            <?php elseif ( $mistura && $precos_produto && ! $mesmo_preco ) : ?>
                <div class="notice notice-warning inline" style="margin: 0 0 12px;">
                    <p>
                        <strong><?php esc_html_e( 'Atencao:', 'hdiscount' ); ?></strong>
                        <?php esc_html_e( 'os produtos da lista tem preços diferentes e a regra mistura as unidades em um pack so. Como o preco do pack e fixo, a economia do cliente muda conforme quais produtos entram no pack.', 'hdiscount' ); ?>
                    </p>
                    <ul style="margin: 6px 0 0 18px;">
                        <?php foreach ( $precos_produto as $item ) : ?>
                            <li><?php echo esc_html( $item['nome'] . ': ' . wc_price( $item['preco'] ) ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin: 8px 0 0;"><?php esc_html_e( 'Se voce quer um desconto sempre igual, use produtos com o mesmo preco na lista.', 'hdiscount' ); ?></p>
                </div>
            <?php endif; ?>
            <?php if ( $sem_extensao && $pais_variaveis ) : ?>
                <div class="notice notice-warning inline" style="margin: 0 0 12px;">
                    <p>
                        <strong><?php esc_html_e( 'Atencao:', 'hdiscount' ); ?></strong>
                        <?php
                        printf(
                            /* translators: %s: lista de produtos variaveis */
                            esc_html__( 'A extensao esta desligada, entao produto variavel nao cobre as variacoes automaticamente. Adicione as variacoes desejadas uma a uma, ou ligue a extensao acima. Produtois: %s', 'hdiscount' ),
                            esc_html( implode( ', ', $pais_variaveis ) )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <p class="description" style="margin-bottom: 12px;">
                <?php _e( 'Busque e adicione os produtos que participan desta regra. Funciona com produtos simples e variaveis.', 'hdiscount' ); ?>
            </p>

            <div style="margin-bottom: 10px;">
                <input type="text" id="hdiscount-product-search" autocomplete="off"
                       placeholder="<?php _e( 'Buscar produto, variacao ou SKU...', 'hdiscount' ); ?>"
                       style="width: 100%; max-width: 420px; padding: 8px;" />
            </div>

            <p class="hdiscount-option">
                <label>
                    <input type="checkbox" name="hdiscount_extend_variations" id="hdiscount_extend_variations"
                           value="yes" <?php checked( $extend, 'yes' ); ?> />
                    <?php _e( 'Aplicar a todas as variacoes dos produtos variaveis selecionados', 'hdiscount' ); ?>
                </label>
                <span class="hdiscount-option__hint">
                    <?php _e( 'Marcado: ao escolher "Camiseta" (produto variavel), a regra vale para TODAS as variacoes, inclusive as que forem criadas depois.<br>Desmarque para escolher variacao por variacao - nesse caso, apenas as variacoes listadas abaixo recebem o desconto.', 'hdiscount' ); ?>
                </span>
            </p>

            <ul id="hdiscount-selected-products" class="hdiscount-product-list">
                <?php foreach ( $ordered as $item ) : ?>
                    <li class="hdiscount-product-list__item<?php echo ! empty( $item['is_child'] ) ? ' hdiscount-product-list__item--child' : ''; ?>"
                        data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
                        data-product-type="<?php echo esc_attr( $item['type'] ); ?>"
                        data-parent-id="<?php echo esc_attr( isset( $item['parent_id'] ) ? $item['parent_id'] : 0 ); ?>"
                        data-name="<?php echo esc_attr( $item['name'] ); ?>"
                        data-parent-name="<?php echo esc_attr( isset( $item['parent'] ) ? $item['parent'] : '' ); ?>"
                        data-sku="<?php echo esc_attr( isset( $item['sku'] ) ? $item['sku'] : '' ); ?>">
                        <span class="hdiscount-product-list__info">
                            <strong><?php echo esc_html( $item['name'] ); ?></strong>
                            <?php if ( 'variation' === $item['type'] && $item['parent'] ) : ?>
                                <span class="hdiscount-tag hdiscount-tag--muted">
                                    <?php
                                    printf(
                                        /* translators: %s: nome do produto pai */
                                        esc_html__( 'variacao de: %s', 'hdiscount' ),
                                        esc_html( $item['parent'] )
                                    );
                                    ?>
                                </span>
                            <?php elseif ( 'variable' === $item['type'] ) : ?>
                                <span class="hdiscount-tag hdiscount-tag--variable">
                                    <?php
                                    printf(
                                        /* translators: %d: quantidade de variacoes */
                                        esc_html( _n( '%d variacao', '%d variacoes', (int) $item['qtd_var'], 'hdiscount' ) ),
                                        (int) $item['qtd_var']
                                    );
                                    ?>
                                </span>
                            <?php else : ?>
                                <span class="hdiscount-tag"><?php _e( 'simples', 'hdiscount' ); ?></span>
                            <?php endif; ?>
                            <?php if ( $item['sku'] ) : ?>
                                <code class="hdiscount-product-list__sku"><?php echo esc_html( $item['sku'] ); ?></code>
                            <?php endif; ?>
                        </span>
                        <button type="button" class="hdiscount-product-list__remove"
                                title="<?php esc_attr_e( 'Remover', 'hdiscount' ); ?>">&times;</button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p id="hdiscount-products-empty" class="hdiscount-product-list__empty" <?php echo $items ? 'style="display:none;"' : ''; ?>>
                <?php _e( 'Nenhum produto selecionado. A regra nao sera aplicada enquanto a lista estiver vazia.', 'hdiscount' ); ?>
            </p>

            <input type="hidden" name="hdiscount_product_ids" id="hdiscount_product_ids"
                   value="<?php echo esc_attr( implode( ',', $product_ids ) ); ?>" />
        </div>

        <script type="text/javascript">
        jQuery(function($){
            var $search = $('#hdiscount-product-search');
            var $list   = $('#hdiscount-selected-products');
            var $input  = $('#hdiscount_product_ids');
            var timer;

            // Busca produtos via AJAX
            $search.on('keyup', function(){
                clearTimeout(timer);
                var term = $(this).val().trim();
                if (term.length < 2) return;

                timer = setTimeout(function(){
                    $.post(ajaxurl, {
                        action: 'hdiscount_search_products',
                        term: term,
                        nonce: '<?php echo wp_create_nonce( 'hdiscount_search_products' ); ?>'
                    }, function(response){
                        if (response.success && response.data.length) {
                            showSuggestions(response.data);
                        }
                    });
                }, 300);
            });

            var $suggestions = $('<div id="hdiscount-suggestions" style="position:absolute; background:white; border:1px solid #ddd; max-height:200px; overflow:auto; z-index:1000; display:none; width:400px;"></div>').insertAfter($search);

            function showSuggestions(products){
                $suggestions.empty().show();

                if (!products || !products.length) {
                    $suggestions.append(
                        $('<div style="padding:8px 12px; color:#646970;">')
                            .text(<?php echo wp_json_encode( __( "Nenhum produto encontrado.", 'hdiscount' ) ); ?>)
                    );
                    return;
                }

                products.forEach(function(p){
                    var inList = $list.find('[data-product-id="'+p.id+'"]').length > 0;

                    // Rotulo legivel, distinguindo produto de variacao.
                    var label = p.name;
                    if (p.type === 'variation' && p.parent_name) {
                        label += '  \u2014  ' + p.parent_name;
                    }

                    // Avisa que escolher a variacao inclui o produto inteiro.
                    var aviso = '';
                    if (p.type === 'variation' && p.parent_id && extensaoLigada()) {
                        aviso = <?php echo wp_json_encode( __( 'inclui todas as variacoes', 'hdiscount' ) ); ?>;
                    }

                    $suggestions.append(
                        $('<div class="hdiscount-suggestion">')
                            .toggleClass('already-added', inList)
                            .append($('<span class="hdiscount-suggestion__name">').text(label))
                            .append($('<span class="hdiscount-suggestion__type">').text(
                                p.type === 'variation' ? <?php echo wp_json_encode( __( "Variacao", 'hdiscount' ) ); ?>
                                    : (p.type === 'variable' ? <?php echo wp_json_encode( __( "Variavel", 'hdiscount' ) ); ?>
                                    : <?php echo wp_json_encode( __( "Simples", 'hdiscount' ) ); ?>)
                            ))
                            .append($('<span class="hdiscount-suggestion__sku">').text(p.sku || ''))
                            .append($('<span class="hdiscount-suggestion__hint">').text(aviso))
                            .data('product', p)
                            .on('click', function(){
                                addProduct(p);
                                $suggestions.hide();
                                $search.val('');
                            })
                    );
                });
            }

            // Mantem os itens selecionados para poder reordenar a lista.
            var selecionados = [];

            function carregarDoServidor(){
                var inicial = $list.find('li').map(function(){
                    return {
                        id: $(this).data('product-id'),
                        name: $(this).data('name'),
                        type: $(this).data('product-type'),
                        parent_name: $(this).data('parent-name'),
                        parent_id: $(this).data('parent-id') || 0,
                        sku: $(this).data('sku')
                    };
                }).get();
                selecionados = $.grep(inicial, function(p){ return p && p.id; });
            }

            /**
             * Desenha a lista agrupando: produto pai e, abaixo, as variacoes.
             */
            function renderList(){
                $list.empty();

                var secoes = {};
                var ordem  = [];

                selecionados.forEach(function(p){
                    var ehVariacao = (p.type === 'variation' && p.parent_id);
                    var dono = ehVariacao ? p.parent_id : p.id;

                    if (!secoes[dono]) {
                        secoes[dono] = { pai: null, filhas: [] };
                        ordem.push(dono);
                    }

                    if (ehVariacao) {
                        secoes[dono].filhas.push(p);
                    } else {
                        secoes[dono].pai = p;
                    }
                });

                var linha = function(p, ehFilha){
                    var $li = $('<li class="hdiscount-product-list__item"/>')
                        .addClass(ehFilha ? 'hdiscount-product-list__item--child' : '')
                        .attr('data-product-id', p.id)
                        .attr('data-product-type', p.type || '')
                        .attr('data-parent-id', p.parent_id || 0)
                        .attr('data-name', p.name)
                        .attr('data-parent-name', p.parent_name || '')
                        .attr('data-sku', p.sku || '');

                    var $info = $('<span class="hdiscount-product-list__info"/>')
                        .append($('<strong/>').text(p.name));

                    if (p.type === 'variation') {
                        $info.append($('<span class="hdiscount-tag hdiscount-tag--muted"/>').text(
                            <?php echo wp_json_encode( __( 'variacao', 'hdiscount' ) ); ?>
                        ));
                    } else if (p.type === 'variable') {
                        $info.append($('<span class="hdiscount-tag hdiscount-tag--variable"/>').text(
                            <?php echo wp_json_encode( __( 'variavel', 'hdiscount' ) ); ?>
                        ));
                    } else {
                        $info.append($('<span class="hdiscount-tag"/>').text(
                            <?php echo wp_json_encode( __( 'simples', 'hdiscount' ) ); ?>
                        ));
                    }

                    if (p.sku) {
                        $info.append($('<code class="hdiscount-product-list__sku"/>').text(p.sku));
                    }

                    $li.append($info);
                    $li.append(
                        $('<button type="button" class="hdiscount-product-list__remove"/>')
                            .attr('title', <?php echo wp_json_encode( __( "Remover", "hdiscount" ) ); ?>)
                            .attr('aria-label', <?php echo wp_json_encode( __( "Remover", "hdiscount" ) ); ?>)
                            .text('\u00d7')
                    );

                    return $li;
                };

                ordem.forEach(function(dono){
                    var sec = secoes[dono];
                    if (!sec.pai) {
                        // Variacao sem o pai na lista.
                        sec.filhas.forEach(function(f){ $list.append(linha(f, false)); });
                        return;
                    }
                    $list.append(linha(sec.pai, false));
                    sec.filhas.forEach(function(f){ $list.append(linha(f, true)); });
                });
            }

            /**
             * Descobre se a regra vale para o produto inteiro.
             */
            function extensaoLigada(){
                var $box = $('#hdiscount_extend_variations');
                if (!$box.length) return true;
                return $box.is(':checked');
            }

            function jaSelecionado(id){
                var alvo = String(id);
                var achou = false;
                selecionados.forEach(function(p){
                    if (String(p.id) === alvo) achou = true;
                });
                return achou;
            }

            function adicionarItem(dados){
                if (jaSelecionado(dados.id)) return;
                selecionados.push(dados);
            }

            function addProduct(product){
                // Com a extensao ligada, escolher uma variacao equivale a
                // escolher o produto pai: ele ja cobre todas as variacoes.
                // Sem isso, a lista ficava so com variacoes soltas e o
                // lojista nao achava o produto que pretendia usar.
                if (product.type === 'variation' && product.parent_id && extensaoLigada()) {
                    adicionarItem({
                        id: product.parent_id,
                        name: product.parent_name || product.name,
                        type: product.parent_type || 'variable',
                        parent_name: '',
                        parent_id: 0,
                        sku: ''
                    });
                } else {
                    adicionarItem({
                        id: product.id,
                        name: product.name,
                        type: product.type,
                        parent_name: product.parent_name || '',
                        parent_id: product.parent_id || 0,
                        sku: product.sku || ''
                    });
                }

                renderList();
                updateHiddenInput();
            }

            function updateHiddenInput(){
                var ids = [];
                $list.find('li').each(function(){
                    ids.push($(this).attr('data-product-id'));
                });
                $input.val(ids.join(','));

                // Mensagem de lista vazia.
                var has = ids.length > 0;
                $('#hdiscount-products-empty').toggle(!has);

                // Aviso visual quando ha produto sem preenchimento.
                $('#hdiscount-products-wrapper').toggleClass('hdiscount-has-products', has);
            }

            $list.on('click', '.hdiscount-product-list__remove', function(){
                var id = $(this).closest('li').data('product-id');
                selecionados = $.grep(selecionados, function(p){ return String(p.id) !== String(id); });
                renderList();
                updateHiddenInput();
            });

            $(document).on('click', function(e){
                if (!$(e.target).closest('#hdiscount-product-search, #hdiscount-suggestions').length) {
                    $suggestions.hide();
                }
            });

            // Carrega o que ja estava salvo e redesenha agrupado.
            carregarDoServidor();
            renderList();
            updateHiddenInput();

            // Teclado: Enter busca, Escape fecha as sugestoes.
            $search.on('keydown', function(e){
                if (e.key === 'Escape') { $suggestions.hide(); }
            });
        });
        </script>
        <style>
        #hdiscount-suggestions div:hover { background:#f0f0f1; }
        #hdiscount-suggestions .already-added { color:#999; text-decoration:line-through; }
        </style>
        <?php
    }

    /**
     * Salva os dados dos meta boxes
     */
    public function save_meta_box_data( $post_id, $post ) {
        // Verifica nonce
        if ( ! isset( $_POST['hdiscount_meta_nonce'] ) || ! wp_verify_nonce( $_POST['hdiscount_meta_nonce'], 'hdiscount_save_meta' ) ) {
            return;
        }

        // Verifica autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Verifica permissões
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Verifica se é nosso CPT
        if ( self::CPT_SLUG !== $post->post_type ) {
            return;
        }

        // Campos para salvar
        $fields = array(
            '_hdiscount_type'           => 'hdiscount_type',
            '_hdiscount_min_qty'        => 'hdiscount_min_qty',
            '_hdiscount_max_qty'        => 'hdiscount_max_qty',
            '_hdiscount_pack_price'     => 'hdiscount_pack_price',
            '_hdiscount_percent'        => 'hdiscount_percent',
            '_hdiscount_buy_qty'        => 'hdiscount_buy_qty',
            '_hdiscount_get_qty'        => 'hdiscount_get_qty',
            '_hdiscount_same_variation' => 'hdiscount_same_variation',
            '_hdiscount_limit_to_max'   => 'hdiscount_limit_to_max',
            '_hdiscount_extend_variations' => 'hdiscount_extend_variations',
            '_hdiscount_mix_products'       => 'hdiscount_mix_products',
            '_hdiscount_start_date'     => 'hdiscount_start_date',
            '_hdiscount_end_date'       => 'hdiscount_end_date',
            '_hdiscount_status'         => 'hdiscount_status',
            '_hdiscount_product_ids'    => 'hdiscount_product_ids',
        );

        foreach ( $fields as $meta_key => $post_key ) {

            // Um checkbox desmarcado nao chega no POST. Sem tratamento
            // explicito, a regra continuaria valendo como "marcada".
            if ( '_hdiscount_extend_variations' === $meta_key || '_hdiscount_mix_products' === $meta_key ) {
                update_post_meta(
                    $post_id,
                    $meta_key,
                    empty( $_POST[ $post_key ] ) ? 'no' : 'yes'
                );
                continue;
            }

            if ( ! isset( $_POST[ $post_key ] ) ) {
                continue;
            }

            $raw = wp_unslash( $_POST[ $post_key ] );

            switch ( $meta_key ) {
                case '_hdiscount_min_qty':
                case '_hdiscount_max_qty':
                case '_hdiscount_buy_qty':
                case '_hdiscount_get_qty':
                    $value = absint( $raw );
                    break;

                case '_hdiscount_pack_price':
                case '_hdiscount_percent':
                    $value = (float) str_replace( ',', '.', $raw );
                    break;

                case '_hdiscount_product_ids':
                    // Aceita a lista separada por virgula e devolve CSV de IDs.
                    $parts = array_filter( array_map( 'trim', explode( ',', (string) $raw ) ), 'strlen' );
                    $ids   = array_values( array_unique( array_map( 'absint', $parts ) ) );
                    $ids   = array_filter( $ids );
                    $value = implode( ',', $ids );
                    break;

                case '_hdiscount_start_date':
                case '_hdiscount_end_date':
                    // datetime-local (2024-12-25T18:30) para formato MySQL.
                    $value = '';
                    if ( $raw ) {
                        $ts = strtotime( str_replace( 'T', ' ', (string) $raw ) );
                        if ( $ts ) {
                            $value = gmdate( 'Y-m-d H:i:s', $ts );
                        }
                    }
                    break;

                case '_hdiscount_type':
                case '_hdiscount_status':
                    // Valores fechados: ignora o que nao estiver na lista.
                    $allowed = array(
                        '_hdiscount_type'   => array( 'pack_price', 'percent', 'buy_x_get_y' ),
                        '_hdiscount_status' => array( 'active', 'inactive', 'scheduled' ),
                    );
                    $value = in_array( $raw, $allowed[ $meta_key ], true ) ? $raw : $allowed[ $meta_key ][0];
                    break;

                default:
                    $value = sanitize_text_field( $raw );
            }

            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    /**
     * Define colunas personalizadas na lista de regras
     */
    public function set_custom_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'title' === $key ) {
                $new_columns['hdiscount_type']      = __( 'Tipo', 'hdiscount' );
                $new_columns['hdiscount_products']  = __( 'Produtos', 'hdiscount' );
                $new_columns['hdiscount_qty']       = __( 'Qtd Min/Max', 'hdiscount' );
                $new_columns['hdiscount_period']    = __( 'Período', 'hdiscount' );
                $new_columns['hdiscount_status']    = __( 'Status', 'hdiscount' );
            }
        }
        return $new_columns;
    }

    /**
     * Renderiza conteúdo das colunas personalizadas
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'hdiscount_type':
                $type = get_post_meta( $post_id, '_hdiscount_type', true );
                $labels = array(
                    'pack_price'     => __( 'Preço Fixo do Pack', 'hdiscount' ),
                    'percent'        => __( 'Desconto %', 'hdiscount' ),
                    'buy_x_get_y'    => __( 'Compre X Leve Y', 'hdiscount' ),
                );
                echo isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
                break;

            case 'hdiscount_products':
                $ids = get_post_meta( $post_id, '_hdiscount_product_ids', true );
                $ids = $ids ? array_filter( array_map( 'intval', explode( ',', $ids ) ) ) : array();
                $count = count( $ids );

                if ( ! $count ) {
                    echo '<span style="color:#d63638;">' . esc_html__( 'Nenhum', 'hdiscount' ) . '</span>';
                    break;
                }

                // Nomes curtos dos primeiros itens.
                $nomes = array();
                foreach ( array_slice( $ids, 0, 3 ) as $pid ) {
                    $p = wc_get_product( $pid );
                    if ( $p ) {
                        $nomes[] = $p->get_parent_id() ? $p->get_name() . ' (' . __( 'variacão', 'hdiscount' ) . ')' : $p->get_name();
                    }
                }

                echo esc_html( sprintf(
                    _n( '%d produto', '%d produtos', $count, 'hdiscount' ),
                    $count
                ) );

                if ( $nomes ) {
                    echo ' <span style="color:#646970;">(' . esc_html( implode( ', ', $nomes ) ) . ( $count > 3 ? '…' : '' ) . ')</span>';
                }

                // Sinaliza a extensao para variacoes.
                $extend = get_post_meta( $post_id, '_hdiscount_extend_variations', true );
                $extend = '' === $extend ? 'yes' : $extend;

                if ( 'yes' === $extend ) {
                    echo '<br><span class="hdiscount-tag hdiscount-tag--variable" style="font-size:11px;">'
                        . esc_html__( '+ todas as variações', 'hdiscount' )
                        . '</span>';
                }
                break;

            case 'hdiscount_qty':
                $min = get_post_meta( $post_id, '_hdiscount_min_qty', true );
                $max = get_post_meta( $post_id, '_hdiscount_max_qty', true );
                $limit = get_post_meta( $post_id, '_hdiscount_limit_to_max', true );
                echo esc_html( $min );
                if ( $max > 0 ) {
                    echo ' – ' . esc_html( $max );
                    if ( 'yes' === $limit ) {
                        echo ' <span class="dashicons dashicons-lock" style="font-size:14px; vertical-align:middle;" title="' . esc_attr__( 'Limitado ao máximo', 'hdiscount' ) . '"></span>';
                    }
                } else {
                    echo '+';
                }
                break;

            case 'hdiscount_period':
                $start = get_post_meta( $post_id, '_hdiscount_start_date', true );
                $end   = get_post_meta( $post_id, '_hdiscount_end_date', true );
                if ( $start ) {
                    echo '<strong>' . __( 'Início:', 'hdiscount' ) . '</strong> ' . date_i18n( 'd/m/Y H:i', strtotime( $start ) ) . '<br>';
                }
                if ( $end ) {
                    echo '<strong>' . __( 'Fim:', 'hdiscount' ) . '</strong> ' . date_i18n( 'd/m/Y H:i', strtotime( $end ) );
                }
                if ( ! $start && ! $end ) {
                    echo '<span style="color:#999;">' . __( 'Sem limite de tempo', 'hdiscount' ) . '</span>';
                }
                break;

            case 'hdiscount_status':
                $status = get_post_meta( $post_id, '_hdiscount_status', true );
                $status = $status ?: 'active';
                $labels = array(
                    'active'     => '<span style="color:#00a32a;">● ' . __( 'Ativa', 'hdiscount' ) . '</span>',
                    'inactive'   => '<span style="color:#d63638;">● ' . __( 'Inativa', 'hdiscount' ) . '</span>',
                    'scheduled'  => '<span style="color:#dba617;">● ' . __( 'Agendada', 'hdiscount' ) . '</span>',
                );
                echo isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
                break;
        }
    }

    /**
     * Enqueue assets no admin
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( 'hdiscount_rule' !== $post_type ) {
            return;
        }

        wp_enqueue_script( 'hdiscount-admin', HDISCOUNT_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), HDISCOUNT_VERSION, true );
        wp_enqueue_style( 'hdiscount-admin', HDISCOUNT_PLUGIN_URL . 'assets/admin.css', array(), HDISCOUNT_VERSION );
        wp_localize_script( 'hdiscount-admin', 'hdiscount_admin', array(
            'i18n_confirm_delete' => __( 'Confirma a exclusao desta regra?', 'hdiscount' ),
            'i18n_invalid_dates'  => __( 'A data de fim deve ser posterior a data de inicio.', 'hdiscount' ),
            'i18n_max_lt_min'     => __( 'A quantidade maxima nao pode ser menor que a minima.', 'hdiscount' ),
            'i18n_sure'          => __( 'Tem certeza?', 'hdiscount' ),
            'i18n_min_invalid'   => __( 'A quantidade minima precisa ser 1 ou mais.', 'hdiscount' ),
            'i18n_pack_price_invalid' => __( 'Informe o preco total do pack (maior que zero).', 'hdiscount' ),
            'i18n_percent_invalid'    => __( 'O desconto precisa ser maior que 0 e no maximo 100%.', 'hdiscount' ),
            'i18n_buy_invalid'        => __( 'O campo "Comprar" precisa ser 2 ou mais.', 'hdiscount' ),
            'i18n_get_invalid'        => __( 'O campo "Pague" precisa ser maior que zero e MENOR que "Comprar".', 'hdiscount' ),
        ) );
    }
}