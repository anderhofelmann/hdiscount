<?php
/**
 * Testes do HDiscount.
 *
 * Cenario base (o caso do cliente):
 *   Produto com preco regular R$ 35,00 e a regra "3 por R$ 100,00".
 *
 * Metodo: validamos sempre o TOTAL pago pelo conjunto de unidades,
 * que e o que o cliente enxerga na fatura.
 */

require_once __DIR__ . '/wp-mock.php';

define( 'HDISCOUNT_PLUGIN_PATH', __DIR__ . '/../hdiscount/' );
define( 'HDISCOUNT_PLUGIN_URL', 'https://exemplo.test/' );
define( 'HDISCOUNT_VERSION', '1.6.1' );
define( 'MINUTE_IN_SECONDS', 60 );

require_once HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-autoloader.php';

$fails = 0;
$total = 0;

function check( $desc, $esperado, $obtido ) {
    global $fails, $total;
    $total++;
    $ok = abs( (float) $esperado - (float) $obtido ) < 0.011;
    if ( ! $ok ) { $fails++; }
    printf( "  %s %-56s esp: %-10s obt: %s\n",
        $ok ? 'OK  ' : 'FALHA', $desc,
        number_format( (float) $esperado, 2, ',', '.' ),
        number_format( (float) $obtido, 2, ',', '.' )
    );
}

// ================= SETUP =================
$GLOBALS['rule_posts'] = array( 1 );

$GLOBALS['wc_products'] = array(
    100 => new WC_Product( 100, 35.00, 'Produto Variavel', 'variable' ),
    101 => new WC_Product( 101, 35.00, 'Vermelho P', 'variation', 100 ),
    102 => new WC_Product( 102, 35.00, 'Azul M',     'variation', 100 ),
    103 => new WC_Product( 103, 35.00, 'Preto G',    'variation', 100 ),
    // Segundo produto, para testar a mesma regra em varios produtos.
    200 => new WC_Product( 200, 35.00, 'BLUSA 0002', 'variable' ),
    201 => new WC_Product( 201, 35.00, 'Amarelo',    'variation', 200 ),
    202 => new WC_Product( 202, 35.00, 'Preto',      'variation', 200 ),
    // Terceiro produto.
    300 => new WC_Product( 300, 35.00, 'BLUSA 0003', 'variable' ),
    301 => new WC_Product( 301, 35.00, 'Verde',      'variation', 300 ),
);

function reset_precos() {
    foreach ( array( 101, 102, 103, 201, 202, 301 ) as $id ) {
        if ( isset( $GLOBALS['wc_products'][ $id ] ) ) {
            $GLOBALS['wc_products'][ $id ]->set_price( 35.00 );
        }
    }
}

function reset_carrinho() {
    // As chaves de linha comecam de novo a cada carrinho, entao o preco de
    // referencia em memoria tem de ser limpo junto.
    if ( class_exists( 'HDiscount_Cart_Logic', false ) ) {
        HDiscount_Cart_Logic::reset_baseline();
    }
    $GLOBALS['cart'] = new WC_Cart();
    return $GLOBALS['cart'];
}

/** Monta uma regra. */
function regra( $overrides = array() ) {
    $base = array(
        '_hdiscount_type'           => 'pack_price',
        '_hdiscount_min_qty'        => '3',
        '_hdiscount_max_qty'        => '0',
        '_hdiscount_pack_price'     => '100.00',
        '_hdiscount_percent'        => '20',
        '_hdiscount_buy_qty'        => '4',
        '_hdiscount_get_qty'        => '3',
        '_hdiscount_same_variation' => 'no',
        '_hdiscount_limit_to_max'   => 'no',
        '_hdiscount_status'         => 'active',
        '_hdiscount_product_ids'    => '100',
    );
    foreach ( $overrides as $k => $v ) { $base[ $k ] = $v; }

    $GLOBALS['post_meta'] = array( 1 => array() );
    foreach ( $base as $k => $v ) { $GLOBALS['post_meta'][1][$k] = array( $v ); }
}

/** Soma o subtotal dos itens, SEM tocar nos precos. */
function subtotal_itens( $cart ) {
    $sum = 0.0;
    foreach ( $cart->get_cart() as $item ) {
        $sum += $item['data']->get_price() * $item['quantity'];
    }
    return round( $sum, 2 );
}

/** Total que o cliente paga: itens + taxas (descontos negativos). */
function total_carrinho( $cart, $logic = null ) {
    $total = subtotal_itens( $cart );

    if ( $logic instanceof HDiscount_Cart_Logic ) {
        $cart->remove_all_fees();
        $logic->add_discount_fee( $cart );
        foreach ( $cart->get_fees() as $fee ) {
            $total += $fee['amount'];
        }
    }

    return round( $total, 2 );
}

/** Executa o calculo completo: descontos e taxas. */
function rodar_completo( $logic ) {
    $logic->apply_discounts( $GLOBALS['cart'] );
    return total_carrinho( $GLOBALS['cart'], $logic );
}

HDiscount_Autoloader::register();

echo "\n[1] Autoloader (o bug que derrubou o site)\n";
foreach ( array( 'HDiscount_Admin', 'HDiscount_Cart_Logic', 'HDiscount_AJAX' ) as $cls ) {
    check( "classe $cls carrega", 1, class_exists( $cls, true ) ? 1 : 0 );
}

$logic = new HDiscount_Cart_Logic();

// ================= CASOS =================

echo "\n[2] Pack 3 por R\$100 - quantidade unica (mesma variacao)\n";
reset_precos();
foreach ( array( 1, 2, 3 ) as $qty ) {
    regra();
    $c = reset_carrinho();
    $c->add( 100, 101, $qty );
    $esp = array( 1 => 35.00, 2 => 70.00, 3 => 100.00 )[ $qty ];
    check( "$qty un -> total", $esp, rodar_completo( $logic ) );
}

echo "\n[3] Pack 3 por R\$100 - 4 e 6 unidades (packs fechados + excedente)\n";
foreach ( array( 4 => 135.00, 5 => 170.00, 6 => 200.00, 7 => 235.00, 9 => 300.00 ) as $qty => $esp ) {
    reset_precos();
    regra();
    $c = reset_carrinho();
    $c->add( 100, 101, $qty );
    check( "$qty un -> total", $esp, rodar_completo( $logic ) );
}

echo "\n[4] Variacoes MISTURADAS (qualquer variacao do mesmo produto)\n";
foreach ( array( 3 => 100.00, 6 => 200.00 ) as $qty => $esp ) {
    reset_precos();
    regra();
    $c = reset_carrinho();
    $por = intdiv( $qty, 3 );
    $c->add( 100, 101, $por );
    $c->add( 100, 102, $por );
    $c->add( 100, 103, $por );
    check( "$qty un em 3 variacoes -> total", $esp, rodar_completo( $logic ) );
}

echo "\n[5] Regra 'somente variacoes identicas'\n";
regra( array( '_hdiscount_same_variation' => 'yes' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 1 );
$c->add( 100, 102, 1 );
$c->add( 100, 103, 1 );
check( '3 unidades em variacoes DIFERENTES -> nao aplica', 105.00, rodar_completo( $logic ) );

regra( array( '_hdiscount_same_variation' => 'yes' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( '3 unidades da MESMA variacao -> aplica', 100.00, rodar_completo( $logic ) );

echo "\n[6] Teto de quantidade COM limite ligado (min=3 max=3, compra 4)\n";
regra( array( '_hdiscount_max_qty' => '3', '_hdiscount_limit_to_max' => 'yes' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 4 );
check( '4 un -> 3 no pack + 1 normal', 135.00, rodar_completo( $logic ) );

echo "\n[7] Teto SEM limite (min=3 max=3, compra 4) - repete o pack\n";
regra( array( '_hdiscount_max_qty' => '3', '_hdiscount_limit_to_max' => 'no' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 4 );
check( '4 un -> 1 pack de 3 + 1 normal', 135.00, rodar_completo( $logic ) );

echo "\n[8] Desconto percentual 20% a partir de 3\n";
regra( array( '_hdiscount_type' => 'percent', '_hdiscount_percent' => '20' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( '3 un -> 35 x 0,8 = 28 cada', 84.00, rodar_completo( $logic ) );

reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 2 );
check( '2 un -> nao aplica', 70.00, rodar_completo( $logic ) );

echo "\n[9] Compre 4 pague 3\n";
regra( array( '_hdiscount_type' => 'buy_x_get_y', '_hdiscount_buy_qty' => '4', '_hdiscount_get_qty' => '3' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 4 );
check( '4 un -> paga 3 (105,00)', 105.00, rodar_completo( $logic ) );

regra( array( '_hdiscount_type' => 'buy_x_get_y', '_hdiscount_buy_qty' => '4', '_hdiscount_get_qty' => '3' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 8 );
check( '8 un -> 2 conjuntos (210,00)', 210.00, rodar_completo( $logic ) );

echo "\n[10] Produto simples\n";
foreach ( array( 3 => 100.00, 4 => 135.00 ) as $qty => $esp ) {
    reset_precos();
    regra( array( '_hdiscount_product_ids' => '200' ) );
    $c = reset_carrinho();
    $c->add( 200, 0, $qty );
    check( "$qty un simples -> total", $esp, rodar_completo( $logic ) );
}

echo "\n[11] Precisao: 3 por 100 nao pode virar 99,99\n";
reset_precos();
regra();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$t = rodar_completo( $logic );
check( 'total exato do pack', 100.00, $t );

echo "\n[12] Robustez\n";
regra( array( '_hdiscount_product_ids' => '999999' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( 'produto inexistente nao quebra', 105.00, rodar_completo( $logic ) );

regra( array( '_hdiscount_pack_price' => '0' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( 'pack de preco 0 nao aplica', 105.00, rodar_completo( $logic ) );

regra( array( '_hdiscount_min_qty' => '0' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( 'min=0 nao gera preco absurdo', 105.00, rodar_completo( $logic ) );

$logico2 = new HDiscount_Cart_Logic();
reset_carrinho();
check( 'carrinho vazio', 0.00, total_carrinho( $GLOBALS['cart'], $logico2 ) );

// ================= EXTENSAO A VARIACOES =================

echo "\n[13] Extensao a variacoes\n";

/** Regra "3 por 100" em um produto variavel, com extensao ligada/desligada. */
function regra_extensao( $produtos, $extende, $same = 'no', $mistura = 'no' ) {
    regra( array(
        '_hdiscount_product_ids'        => $produtos,
        '_hdiscount_extend_variations'  => $extende,
        '_hdiscount_same_variation'     => $same,
        '_hdiscount_mix_products'       => $mistura,
    ) );
}

//_EXT = sim, lista apenas o produto pai (ID 100).
// Qualquer variacao no carrinho deve receber o desconto.
regra_extensao( '100', 'yes' );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( 'extensao=SIM, pai listado -> 3 un da var 101', 100.00, rodar_completo( $logic ) );

regra_extensao( '100', 'yes' );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 102, 3 );
check( 'extensao=SIM, outra variacao tambem entra', 100.00, rodar_completo( $logic ) );

// Extensao = nao, lista APENAS a variacao 101.
// A variacao 102 nao deve receber desconto.
regra_extensao( '101', 'no' );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( 'extensao=NAO, variacao listada entra', 100.00, rodar_completo( $logic ) );

regra_extensao( '101', 'no' );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 102, 3 );
check( 'extensao=NAO, variacao NAO listada nao entra', 105.00, rodar_completo( $logic ) );

// Extensao = nao, lista o PAI: nenhuma variacao deve entrar,
// porque sem extensao o pai sozinho nao cobre as variacoes.
regra_extensao( '100', 'no' );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
check( 'extensao=NAO, so o pai listado nao cobre variacoes', 105.00, rodar_completo( $logic ) );

// Variacoes criadas DEPOIS da regra: com extensao=SIM, entram automaticamente.
regra_extensao( '100', 'yes' );
reset_precos();
$GLOBALS['wc_products'][104] = new WC_Product( 104, 35.00, 'Nova Variacao', 'variation', 100 );
$c = reset_carrinho();
$c->add( 100, 104, 3 );
check( 'extensao=SIM, variacao nova entra sozinha', 100.00, rodar_completo( $logic ) );

// Simples nao e afetado por extensao.
regra_extensao( '200', 'yes' );
reset_precos();
$c = reset_carrinho();
$c->add( 200, 0, 3 );
check( 'produto simples continua funcionando', 100.00, rodar_completo( $logic ) );

echo "\n[14] Linha de promocao no carrinho\n";

function rodar_item_data( $logic ) {
    $logic->apply_discounts( $GLOBALS['cart'] );
    $itens = $GLOBALS['cart']->get_cart();
    $chave = array_keys( $itens )[0];
    return apply_filters( 'woocommerce_get_item_data', array(), $itens[ $chave ] );
}

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$linhas = rodar_item_data( $logic );
$promo = end( $linhas );

check( 'adiciona uma linha de promocao (sem duplicar)', 1, count( $linhas ) );
check( 'rotulo e apenas "Promoção"', 1, 'Promoção' === $promo['key'] ? 1 : 0 );
check( 'rotulo nao menciona o nome do plugin', 1,
    false === strpos( $promo['key'], 'HDiscount' ) ? 1 : 0 );

// O item mostra a conta, sem mexer no preco.
check( 'display mostra a conta do desconto', 1,
    false !== strpos( $promo['display'], 'pack' ) ? 1 : 0 );
check( 'item nao mostra preco riscado', 0,
    false !== strpos( $promo['display'], '<del>' ) ? 1 : 0 );
check( 'value traz o texto da promocao', 1,
    false !== strpos( $promo['value'], 'Leve 3' ) ? 1 : 0 );

// Item sem desconto nao recebe a linha.
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 1 );
$linhas = rodar_item_data( $logic );
check( 'item sem promocao nao ganha a linha', 0, count( $linhas ) );

echo "\n[15] O desconto NAO pode se aplicar em cima dele mesmo\n";

// O WordPress recalcula o carrinho varias vezes no mesmo request.
// Cada passada precisa dar o MESMO resultado.

function rodar_repetido( $logic, $cart, $vezes ) {
    $resultados = array();
    for ( $i = 1; $i <= $vezes; $i++ ) {
        $logic->apply_discounts( $cart );
        $resultados[] = total_carrinho( $cart, $logic );
    }
    return $resultados;
}

// Cenario relatado: 2 + 2 unidades em variacoes diferentes = 4 no total.
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 2 );
$c->add( 100, 102, 2 );
$r = rodar_repetido( $logic, $c, 4 );
check( '4 un em 2 variacoes: 1a passada', 135.00, $r[0] );
check( '4 un em 2 variacoes: 2a passada', 135.00, $r[1] );
check( '4 un em 2 variacoes: 3a passada', 135.00, $r[2] );
check( '4 un em 2 variacoes: 4a passada', 135.00, $r[3] );
check( 'todas as passadas iguais', 1, count( array_unique( $r ) ) === 1 ? 1 : 0 );

// 3 unidades, varias passadas.
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$r = rodar_repetido( $logic, $c, 3 );
check( '3 un: 100,00 em todas as passadas', 100.00, $r[0] );
check( '3 un: estavel', 1, count( array_unique( $r ) ) === 1 ? 1 : 0 );

// 6 unidades (2 packs).
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 6 );
$r = rodar_repetido( $logic, $c, 3 );
check( '6 un: 200,00 em todas as passadas', 200.00, $r[0] );
check( '6 un: estavel', 1, count( array_unique( $r ) ) === 1 ? 1 : 0 );

// Desconto percentual tambem nao pode acumular.
regra( array( '_hdiscount_type' => 'percent', '_hdiscount_percent' => '20' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$r = rodar_repetido( $logic, $c, 3 );
check( '20% em 3 un: 84,00 em todas as passadas', 84.00, $r[0] );
check( '20%: estavel', 1, count( array_unique( $r ) ) === 1 ? 1 : 0 );

// Mudar a quantidade deve recalcular a partir dos precos atuais.
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$logic->apply_discounts( $c );
check( 'antes de mudar: 100,00', 100.00, total_carrinho( $c, $logic ) );

$c->cart_contents[ $c->get_cart() ? array_keys( $c->get_cart() )[0] : 0 ]['quantity'] = 2;

$logic->apply_discounts( $c );
check( 'depois de voltar para 2 un: 70,00 (sem promocao)', 70.00, total_carrinho( $c, $logic ) );

echo "\n[16] O carrinho explica a conta do desconto\n";

function conta_do_carrinho( $logic, $qty_total, $linhas = 1 ) {
    reset_precos();
    $c = reset_carrinho();
    if ( 1 === $linhas ) {
        $c->add( 100, 101, $qty_total );
    } else {
        $por = intdiv( $qty_total, 2 );
        $c->add( 100, 101, $por );
        $c->add( 100, 102, $qty_total - $por );
    }
    $logic->apply_discounts( $c );
    $it = $c->get_cart();
    $k  = array_keys( $it )[0];
    return wp_strip_all_tags( $it[$k]['hdiscount_breakdown'] );
}

regra();
check( '3 un: fecha exato em 1 pack',        '3 un no total: 1 pack de 3 por R$ 100,00 = R$ 100.00', conta_do_carrinho( $logic, 3 ) );
check( '6 un: fecha exato em 2 packs',       '6 un no total: 2 pack de 3 por R$ 100,00 = R$ 200.00', conta_do_carrinho( $logic, 6 ) );
check( '4 un: mostra o avulso',              '4 un no total: 1 pack de 3 por R$ 100,00 + 1 avulsas a R$ 35,00 = R$ 135.00', conta_do_carrinho( $logic, 4 ) );
check( '5 un: mostra os 2 avulsos',         '5 un no total: 1 pack de 3 por R$ 100,00 + 2 avulsas a R$ 35,00 = R$ 170.00', conta_do_carrinho( $logic, 5 ) );

// Desconto percentual.
regra( array( '_hdiscount_type' => 'percent', '_hdiscount_percent' => '20' ) );
check( 'percentual mostra o percentual',     '20.0% OFF em 3 un (antes R$ 35.00)', conta_do_carrinho( $logic, 3 ) );

// Compre X pague Y.
regra( array( '_hdiscount_type' => 'buy_x_get_y', '_hdiscount_buy_qty' => '4', '_hdiscount_get_qty' => '3' ) );
check( 'compre X leve Y mostra os grupos',   '1 grupo(s) de 4: paga 3', conta_do_carrinho( $logic, 4 ) );

// Item sem desconto nao ganha essa linha.
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 2 );
$logic->apply_discounts( $c );
$it = $c->get_cart();
$k  = array_keys( $it )[0];
check( 'item sem promocao nao tem conta',    '', (string) ( $it[$k]['hdiscount_breakdown'] ?? '' ) );

echo "\n[17] Sessao antiga contaminada nao pode affectar a conta\n";

// Versoes anteriores gravavam um preco ja rebaixado em cart_contents.
// Esse valor fica na sessao do cliente e nao pode mais ser usado como base.

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 4 );
foreach ( $c->cart_contents as $k => $linha ) {
    $c->cart_contents[ $k ]['hdiscount_original_price'] = 34.00;
}

$logic->apply_discounts( $c );
check( 'total ignora o preco sujo da sessao', 135.00, total_carrinho( $c, $logic ) );

$it = $c->get_cart();
$k  = array_keys( $it )[0];
check( 'item mantem o preco normal de 35,00', 35.00, round( (float) $it[$k]['data']->get_price(), 2 ) );
check( 'o desconto vai para o total, nao para o item', 135.00, total_carrinho( $c, $logic ) );
check( 'avulso usa 35,00, nao 34,00', '4 un no total: 1 pack de 3 por R$ 100,00 + 1 avulsas a R$ 35,00 = R$ 135.00',
    wp_strip_all_tags( $it[$k]['hdiscount_breakdown'] ) );

// O preco original exibido no <del> tambem tem de ser o real.
check( 'nenhum preco foi gravado no item', 35.00, round( (float) $it[$k]['data']->get_price(), 2 ) );

// Lojista muda o preco: o novo valor tem de valer.
HDiscount_Cart_Logic::reset_baseline();
reset_precos();
$GLOBALS['wc_products'][101]->set_price( 40.00 );
$c = reset_carrinho();
$c->add( 100, 101, 4 );
$logic->apply_discounts( $c );
// 1 pack de 100 + 1 avulsa a 40 = 140
check( 'preco alterado para 40 e respeitado', 140.00, total_carrinho( $c, $logic ) );

echo "\n[18] Dados antigos da sessao sao limpos ao carregar o carrinho\n";

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 4 );
$c->cart_contents[ $k0 = array_keys( $c->cart_contents )[0] ]['hdiscount_original_price'] = 33.75;
$c->cart_contents[ $k0 ]['hdiscount_breakdown'] = 'conta antiga';
$c->cart_contents[ $k0 ]['hdiscount_rule_id']  = 99;
$c->cart_contents[ $k0 ]['hdiscount_promo_price'] = 33.75;

$logic->clean_stale_session_data( $c );
$k = array_keys( $c->get_cart() )[0];

check( 'remove preco original antigo',  0, isset( $c->cart_contents[$k]['hdiscount_original_price'] ) ? 1 : 0 );
check( 'remove conta antiga',          0, isset( $c->cart_contents[$k]['hdiscount_breakdown'] ) ? 1 : 0 );
check( 'remove id de regra antigo',    0, isset( $c->cart_contents[$k]['hdiscount_rule_id'] ) ? 1 : 0 );
check( 'remove preco promocional',     0, isset( $c->cart_contents[$k]['hdiscount_promo_price'] ) ? 1 : 0 );

// Depois de limpar, o calculo refaz tudo a partir do preco real.
$logic->apply_discounts( $c );
check( 'recalcula corretamente apos limpar', 135.00, total_carrinho( $c, $logic ) );

echo "\n[19] A conta do produto inteiro aparece so uma vez\n";

// Cenario real: 2 + 2 + 1 unidades em tres variacoes diferentes.

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 2 );  // Marrom
$c->add( 100, 102, 2 );  // Amarelo
$c->add( 100, 103, 1 );  // Azul
$logic->apply_discounts( $c );

check( 'total: 1 pack + 2 avulsas = 170,00', 170.00, total_carrinho( $c, $logic ) );

$contas = array();
foreach ( $c->get_cart() as $linha ) {
    $contas[] = (string) ( $linha['hdiscount_breakdown'] ?? '' );
}

$com_conta = array_filter( $contas );
check( 'apenas 1 das 3 linhas mostra a conta', 1, count( $com_conta ) );
check( 'a conta e a do produto inteiro (5 un)', '5 un no total: 1 pack de 3 por R$ 100,00 + 2 avulsas a R$ 35,00 = R$ 170.00',
    wp_strip_all_tags( reset( $com_conta ) ) );

// Cada variacao continua na SUA linha: nada e dividido entre cores.
$itens = $c->get_cart();
check( 'mantem 3 linhas (uma por variacao)', 3, count( $itens ) );

$vids = array();
foreach ( $itens as $linha ) {
    $vids[] = $linha['variation_id'];
}
check( 'cada variacao numa linha so', 1, count( array_unique( $vids ) ) === 3 ? 1 : 0 );

// Nenhum item muda de preco: todos ficam com os 35,00 originais.
foreach ( $itens as $linha ) {
    check( 'item ' . $linha['variation_id'] . ' mantem o preco normal',
        35.00, round( (float) $linha['data']->get_price(), 2 ) );
}

// A conta do produto inteiro aparece uma vez.
$contas = array_filter( array_map( function( $l ) { return $l['hdiscount_breakdown'] ?? ''; }, $itens ) );
check( 'conta aparece so uma vez', 1, count( $contas ) );

// 4 unidades da mesma variacao: continua UMA linha.
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 4 );
$logic->apply_discounts( $c );
check( '4 un mesma variacao: total 135,00', 135.00, total_carrinho( $c, $logic ) );
check( '4 un mesma variacao: continua 1 linha', 1, count( $c->get_cart() ) );
$linha = array_values( $c->get_cart() )[0];
check( '4 un mesma variacao: 4 un na linha', 4, (int) $linha['quantity'] );
check( '4 un mesma variacao: preco normal preservado', 35.00, round( (float) $linha['data']->get_price(), 2 ) );

// 6 unidades fecham 2 packs.
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 6 );
$logic->apply_discounts( $c );
check( '6 un mesma variacao: total 200,00', 200.00, total_carrinho( $c, $logic ) );
check( '6 un mesma variacao: continua 1 linha', 1, count( $c->get_cart() ) );
check( '6 un mesma variacao: continua 1 linha', 1, count( $c->get_cart() ) );

echo "\n[20] Divisao sobrevive a recarregar a pagina e a mudanca de quantidade\n";

// Reproduz o ciclo real: a sessao e lida, o carrinho e recalculado
// varias vezes e o cliente mexe na quantidade.

function ciclo( $logic, $cart ) {
    $logic->apply_discounts( $cart );
    return $cart;
}

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 5 );
ciclo( $logic, $c );

check( '5 un: total 170,00', 170.00, total_carrinho( $c, $logic ) );
check( '5 un: continua em 1 linha', 1, count( $c->get_cart() ) );

// Recarregar a pagina nao pode alterar nada.
for ( $i = 0; $i < 3; $i++ ) { ciclo( $logic, $c ); }
check( 'apos 3 recargas: total continua 170,00', 170.00, total_carrinho( $c, $logic ) );
check( 'apos 3 recargas: continua em 1 linha', 1, count( $c->get_cart() ) );

// Cliente aumenta a quantidade: 5 -> 6 (dois packs).
$chave = array_keys( $c->get_cart() )[0];
$c->cart_contents[ $chave ]['quantity'] = 6;
ciclo( $logic, $c );
check( '6 un: total 200,00', 200.00, total_carrinho( $c, $logic ) );
check( '6 un: continua em 1 linha', 1, count( $c->get_cart() ) );

// Cliente reduz para 2: perde a promocao e o preco volta ao normal.
$c->cart_contents[ $chave ]['quantity'] = 2;
ciclo( $logic, $c );
check( '2 un: total 70,00 (sem promocao)', 70.00, total_carrinho( $c, $logic ) );

// Volta para 5.
$c->cart_contents[ $chave ]['quantity'] = 5;
ciclo( $logic, $c );
check( 'volta a 5 un: total 170,00', 170.00, total_carrinho( $c, $logic ) );
check( 'volta a 5 un: continua em 1 linha', 1, count( $c->get_cart() ) );

echo "\n[21] Se a promocao terminar, o carrinho volta ao normal\n";

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 5 );
ciclo( $logic, $c );
check( 'com regra: 1 linha', 1, count( $c->get_cart() ) );

// Regra expira / e desativada.
regra( array( '_hdiscount_status' => 'inactive' ) );
$GLOBALS['rule_posts'] = array();  // sem regras ativas
ciclo( $logic, $c );
check( 'sem regra: continua 1 linha', 1, count( $c->get_cart() ) );
check( 'sem regra: total 175,00 (preco normal)', 175.00, total_carrinho( $c, $logic ) );
$GLOBALS['rule_posts'] = array( 1 );

echo "\n[22] Itens intactos, desconto so no total\n";

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 2 );
$c->add( 100, 102, 2 );
$c->add( 100, 103, 1 );

$logic->apply_discounts( $c );

// Nenhum preco de item pode ter mudado.
check( 'subtotal dos itens fica no preco normal', 175.00, subtotal_itens( $c ) );
foreach ( $c->get_cart() as $linha ) {
    check( 'item ' . $linha['variation_id'] . ' continua a 35,00',
        35.00, round( (float) $linha['data']->get_price(), 2 ) );
}

// O desconto entra como taxa negativa.
$c->remove_all_fees();
$logic->add_discount_fee( $c );
$fees = $c->get_fees();

check( 'cria uma unica linha de desconto', 1, count( $fees ) );
check( 'desconto de R\$5,00 (175 - 170)', -5.00, $fees[0]['amount'] );
check( 'desconto nao e tributavel', 1, $fees[0]['taxable'] ? 0 : 1 );
check( 'total final 170,00', 170.00, total_carrinho( $c, $logic ) );
check( 'rotulo no formato Desconto (nome)', 'Desconto (BLUSA Pack Inverno)', $fees[0]['name'] );

echo "\n[23] Sem desconto, sem taxa\n";

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 2 );
$logic->apply_discounts( $c );
$c->remove_all_fees();
$logic->add_discount_fee( $c );
check( 'abaixo do minimo: nenhuma taxa', 0, count( $c->get_fees() ) );
check( 'abaixo do minimo: total 70,00', 70.00, total_carrinho( $c, $logic ) );

echo "\n[24] Rotulo do desconto no total\n";

$GLOBALS['rule_titles'] = array( 1 => 'BLUSA Pack Inverno' );

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$logic->apply_discounts( $c );
$c->remove_all_fees();
$logic->add_discount_fee( $c );
$f = $c->get_fees()[0];
check( 'usa o nome dado a regra', 'Desconto (BLUSA Pack Inverno)', $f['name'] );
check( '3 un: desconto de R\$5,00', -5.00, $f['amount'] );

// Regra sem titulo: mostra a descricao da propria regra.
$GLOBALS['rule_titles'] = array();
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$logic->apply_discounts( $c );
$c->remove_all_fees();
$logic->add_discount_fee( $c );
$f = $c->get_fees()[0];
check( 'sem titulo: mostra a regra', 'Desconto (Leve 3 por R$ 100,00)', $f['name'] );

// 9 unidades = 3 packs = R\$15,00 de desconto.
$GLOBALS['rule_titles'] = array( 1 => 'BLUSA Pack Inverno' );
regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 9 );
$logic->apply_discounts( $c );
$c->remove_all_fees();
$logic->add_discount_fee( $c );
check( '9 un: desconto de R\$15,00', -15.00, $c->get_fees()[0]['amount'] );
check( '9 un: total 300,00', 300.00, total_carrinho( $c, $logic ) );
check( '9 un: subtotal 315,00', 315.00, subtotal_itens( $c ) );

$GLOBALS['rule_titles'] = array( 1 => 'BLUSA Pack Inverno' );

echo "\n[25] Calculo repetido nao duplica a taxa\n";

regra();
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 4 );
$antes = array();
for ( $i = 0; $i < 4; $i++ ) {
    $logic->apply_discounts( $c );
    $c->remove_all_fees();
    $logic->add_discount_fee( $c );
    $antes[] = count( $c->get_fees() ) . ':' . round( array_sum( array_column( $c->get_fees(), 'amount' ) ), 2 );
}
check( 'taxa estavel em 4 passadas', 1, count( array_unique( $antes ) ) === 1 ? 1 : 0 );
check( 'sempre 1 taxa de -5,00', '1:-5', $antes[0] );

echo "\n[26] Mesma regra em varios produtos: os descontos se somam\n";

// Cenario real: BLUSA 0001 com 3 un e BLUSA 0002 com 3 un, mesma regra.
$GLOBALS['rule_titles'] = array( 1 => '3 por 100' );

regra( array( '_hdiscount_product_ids' => '100,200' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 2 );
$c->add( 100, 102, 1 );
$c->add( 200, 201, 2 );
$c->add( 200, 202, 1 );

$logic->apply_discounts( $c );

check( 'subtotal: 6 un x 35,00 = 210,00', 210.00, subtotal_itens( $c ) );

$c->remove_all_fees();
$logic->add_discount_fee( $c );
$fees = $c->get_fees();

check( 'uma unica linha de desconto', 1, count( $fees ) );
check( 'desconto SOMADO: -10,00 (2 packs)', -10.00, $fees[0]['amount'] );
check( 'total final 200,00', 200.00, total_carrinho( $c, $logic ) );
check( 'rotulo mantem o nome da regra', 'Desconto (3 por 100)', $fees[0]['name'] );

// Nenhum item pode ter mudado de preco.
foreach ( $c->get_cart() as $linha ) {
    check( 'item ' . $linha['variation_id'] . ' continua a 35,00',
        35.00, round( (float) $linha['data']->get_price(), 2 ) );
}

// Cada produto continua mostrando a propria conta.
$contas = array();
foreach ( $c->get_cart() as $linha ) {
    if ( ! empty( $linha['hdiscount_breakdown'] ) ) { $contas[] = $linha['hdiscount_breakdown']; }
}
check( 'cada produto mostra sua conta', 2, count( $contas ) );

// Tres produtos com a mesma regra.
regra( array( '_hdiscount_product_ids' => '100,200,300' ) );
$GLOBALS['wc_products'][300] = new WC_Product( 300, 35.00, 'BLUSA 0003', 'variable' );
$GLOBALS['wc_products'][301] = new WC_Product( 301, 35.00, 'Verde', 'variation', 300 );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );
$c->add( 200, 201, 3 );
$c->add( 300, 301, 3 );

$logic->apply_discounts( $c );
$c->remove_all_fees();
$logic->add_discount_fee( $c );
check( '3 produtos: desconto de -15,00', -15.00, $c->get_fees()[0]['amount'] );
check( '3 produtos: total 300,00', 300.00, total_carrinho( $c, $logic ) );

// Regra que NAO se aplica a um dos produtos nao estraga o desconto.
regra( array( '_hdiscount_product_ids' => '100,200,300' ) );
reset_precos();
$c = reset_carrinho();
$c->add( 100, 101, 3 );   // promo
$c->add( 200, 201, 2 );   // sem promocao
$logic->apply_discounts( $c );
$c->remove_all_fees();
$logic->add_discount_fee( $c );
check( 'so um produto tem promocao: -5,00', -5.00, $c->get_fees()[0]['amount'] );
check( 'subtotal correto: 175,00', 175.00, subtotal_itens( $c ) );
check( 'total 170,00', 170.00, total_carrinho( $c, $logic ) );

echo "\n[27] Misturar produtos no mesmo pack\n";

// Regra com dois produtos simples de precos diferentes.
function regra_mistura( $mistura ) {
    regra( array(
        '_hdiscount_product_ids'       => '400,500',
        '_hdiscount_mix_products'      => $mistura,
        '_hdiscount_extend_variations' => 'yes',
        '_hdiscount_same_variation'    => 'no',
    ) );
}

function reset_precos_mistura() {
    if ( isset( $GLOBALS['wc_products'][400] ) ) { $GLOBALS['wc_products'][400]->set_price( 35.00 ); }
    if ( isset( $GLOBALS['wc_products'][500] ) ) { $GLOBALS['wc_products'][500]->set_price( 50.00 ); }
    if ( isset( $GLOBALS['wc_products'][600] ) ) { $GLOBALS['wc_products'][600]->set_price( 20.00 ); }
}

reset_precos_mistura();
$GLOBALS['wc_products'][400] = new WC_Product( 400, 35.00, 'CAMISETA', 'simple' );
$GLOBALS['wc_products'][500] = new WC_Product( 500, 50.00, 'CALCA', 'simple' );

// SEM mistura: cada produto forma o pack por conta propria.
regra_mistura( 'no' );
reset_precos();
reset_precos_mistura();
$c = reset_carrinho();
$c->add( 400, 0, 1 );
$c->add( 500, 0, 2 );
$logic->apply_discounts( $c );
fwrite(STDERR, "\nDEBUG catalogo: ");
fwrite(STDERR, "\n");
check( 'sem mistura: 1 + 2 nao fecha pack', 135.00, total_carrinho( $c, $logic ) );

// COM mistura: 35 + 50 + 50 = 135 vira um pack de 100.
regra_mistura( 'yes' );
reset_precos();
reset_precos_mistura();
$c = reset_carrinho();
$c->add( 400, 0, 1 );
$c->add( 500, 0, 2 );
$logic->apply_discounts( $c );
check( 'com mistura: 3 un de produtos diferentes = 100,00', 100.00, total_carrinho( $c, $logic ) );
check( 'subtotal continua 135,00', 135.00, subtotal_itens( $c ) );

// A conta deve avisar que as unidades estao misturadas.
$achou = false;
foreach ( $c->get_cart() as $linha ) {
    if ( ! empty( $linha['hdiscount_breakdown'] ) ) {
        $achou = ( false !== strpos( $linha['hdiscount_breakdown'], 'misturadas' ) );
    }
}
check( 'a conta diz "misturadas"', 1, $achou );

// Sem mistura suficiente, nao aplica.
regra_mistura( 'yes' );
reset_precos();
reset_precos_mistura();
$c = reset_carrinho();
$c->add( 400, 0, 1 );
$c->add( 500, 0, 1 );
$logic->apply_discounts( $c );
check( 'mistura com 2 unidades nao aplica', 85.00, total_carrinho( $c, $logic ) );

// Uma un de cada, mais uma extra do mesmo produto: 35 + 50 + 35 = 120 -> pack 100 + 20 avulso.
regra_mistura( 'yes' );
reset_precos();
reset_precos_mistura();
$c = reset_carrinho();
$c->add( 400, 0, 2 );
$c->add( 500, 0, 1 );
$logic->apply_discounts( $c );
check( '2+1 com mistura = 100,00', 100.00, total_carrinho( $c, $logic ) );

// 4 unidades misturadas fecham 1 pack + 1 avulsa.
regra_mistura( 'yes' );
reset_precos();
reset_precos_mistura();
$c = reset_carrinho();
$c->add( 400, 0, 2 );
$c->add( 500, 0, 2 );
$logic->apply_discounts( $c );
check( '2+2 com mistura = 150,00 (pack 100 + 1 calca avulsa)', 150.00, total_carrinho( $c, $logic ) );

// 6 unidades misturadas = 2 packs = 200,00.
regra_mistura( 'yes' );
reset_precos();
reset_precos_mistura();
$c = reset_carrinho();
$c->add( 400, 0, 3 );
$c->add( 500, 0, 3 );
$logic->apply_discounts( $c );
check( '3+3 com mistura = 200,00', 200.00, total_carrinho( $c, $logic ) );

// Produto fora da lista nao entra na mistura.
regra_mistura( 'yes' );
reset_precos();
$GLOBALS['wc_products'][600] = new WC_Product( 600, 20.00, 'OUTRO', 'simple' );
$c = reset_carrinho();
$c->add( 400, 0, 1 );
$c->add( 500, 0, 1 );
$c->add( 600, 0, 1 );
$logic->apply_discounts( $c );
check( 'produto fora da lista nao entra no pack', 105.00, total_carrinho( $c, $logic ) );

// ================= RESULTADO =================
echo "\n" . str_repeat( '-', 74 ) . "\n";
if ( 0 === $fails ) {
    echo "RESULTADO: todos os $total testes passaram\n";
} else {
    echo "RESULTADO: $fails de $total testes FALHARAM\n";
}
exit( 0 === $fails ? 0 : 1 );