<?php
/**
 * Testes do painel: renderiza os meta boxes e valida o HTML gerado.
 *
 * Foco: nenhum campo pode ter restricao HTML5 (min/max/required)combined com
 * valor invalido, porque um controle invalido e invisivel trava o envio do
 * formulario no navegador.
 */

require_once __DIR__ . '/wp-mock.php';

define( 'HDISCOUNT_PLUGIN_PATH', __DIR__ . '/../hdiscount/' );
define( 'HDISCOUNT_PLUGIN_URL', 'https://exemplo.test/' );
define( 'HDISCOUNT_VERSION', '1.7.0' );
define( 'MINUTE_IN_SECONDS', 60 );

$fails = 0;
$total = 0;

function check( $desc, $esperado, $obtido ) {
    global $fails, $total;
    $total++;
    $ok = ( $esperado === $obtido );
    if ( ! $ok ) { $fails++; }
    printf( "  %s %-58s esp: %-14s obt: %s\n",
        $ok ? 'OK  ' : 'FALHA', $desc,
        var_export( $esperado, true ), var_export( $obtido, true ) );
}

// Catalogo minimo, necessario para o meta box de produtos.
$GLOBALS['wc_products'] = array(
    100 => new WC_Product( 100, 35.00, 'Produto Variavel', 'variable' ),
    101 => new WC_Product( 101, 35.00, 'Vermelho P', 'variation', 100 ),
    102 => new WC_Product( 102, 35.00, 'Azul M',     'variation', 100 ),
    103 => new WC_Product( 103, 35.00, 'Preto G',    'variation', 100 ),
    200 => new WC_Product( 200, 35.00, 'Produto Simples' ),
);

require_once HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-autoloader.php';
HDiscount_Autoloader::register();
$admin = new HDiscount_Admin();

/**
 * Renderiza um meta box com um conjunto de meta e devolve o HTML.
 */
function render_meta( $method, $meta = array() ) {
    $GLOBALS['post_meta'] = array( 1 => array() );
    foreach ( $meta as $k => $v ) {
        $GLOBALS['post_meta'][1][ $k ] = array( $v );
    }

    $post = new WP_Post();
    $post->ID = 1;

    $obj = new HDiscount_Admin();
    ob_start();
    $obj->$method( $post );
    return ob_get_clean();
}

/**
 * Extrai um input pelo name.
 */
function campo( $html, $name ) {
    if ( preg_match( '/<input[^>]*name="' . preg_quote( $name, '/' ) . '"[^>]*>/', $html, $m ) ) {
        return $m[0];
    }
    return '';
}

echo "\n[1] Regra nova em branco (o cenario que gerou o erro)\n";

$html = render_meta( 'render_meta_box' );

foreach ( array( 'hdiscount_get_qty', 'hdiscount_buy_qty', 'hdiscount_pack_price', 'hdiscount_percent' ) as $campo ) {
    $tag = campo( $html, $campo );
    check( "campo $campo existe no HTML", true, '' !== $tag );
}

// O erro original: value="0" com min="1".
check( 'hdiscount_get_qty NAO tem value=0', 0,
    substr_count( $tag ?? '', 'value="0"' ) );

$get = campo( $html, 'hdiscount_get_qty' );
check( 'hdiscount_get_qty tem valor >= 1', 1, preg_match( '/value="([1-9]\d*)"/', $get ) ? 1 : 0 );

$buy = campo( $html, 'hdiscount_buy_qty' );
check( 'hdiscount_buy_qty tem valor >= 2', 1, preg_match( '/value="([2-9]\d*)"/', $buy ) ? 1 : 0 );

echo "\n[2] Nenhum campo condicional pode ter restricao HTML5\n";
foreach ( array( 'hdiscount_get_qty', 'hdiscount_buy_qty', 'hdiscount_pack_price', 'hdiscount_percent' ) as $campo ) {
    $tag = campo( $html, $campo );
    check( "$campo sem atributo min", 0, preg_match( '/\smin=/', $tag ) ? 1 : 0 );
    check( "$campo sem atributo max", 0, preg_match( '/\smax=/', $tag ) ? 1 : 0 );
    check( "$campo sem atributo required", 0, preg_match( '/\srequired/', $tag ) ? 1 : 0 );
}

echo "\n[3] Campos sempre visiveis mantem validacao\n";
$min = campo( $html, 'hdiscount_min_qty' );
check( 'hdiscount_min_qty tem required', 1, preg_match( '/\srequired/', $min ) ? 1 : 0 );
check( 'hdiscount_min_qty tem min=1', 1, preg_match( '/\smin="1"/', $min ) ? 1 : 0 );

echo "\n[4] Valores salvos anteriormente sao preservados\n";
$html2 = render_meta( 'render_meta_box', array(
    '_hdiscount_type'           => 'buy_x_get_y',
    '_hdiscount_buy_qty'        => '6',
    '_hdiscount_get_qty'        => '4',
    '_hdiscount_min_qty'        => '6',
) );
check( 'buy_qty preservado = 6', 1, substr_count( campo( $html2, 'hdiscount_buy_qty' ), 'value="6"' ) ? 1 : 0 );
check( 'get_qty preservado = 4', 1, substr_count( campo( $html2, 'hdiscount_get_qty' ), 'value="4"' ) ? 1 : 0 );

echo "\n[5] Meta box de periodo\n";
$periodo = render_meta( 'render_period_meta_box' );
check( 'campo de inicio existe', true, '' !== campo( $periodo, 'hdiscount_start_date' ) );
check( 'campo de fim existe',    true, '' !== campo( $periodo, 'hdiscount_end_date' ) );

echo "\n[6] Meta box de produtos\n";
$produtos = render_meta( 'render_products_meta_box' );
check( 'campo oculto de ids existe', true, false !== strpos( $produtos, 'id="hdiscount_product_ids"' ) );
check( 'lista de produtos existe',   true, false !== strpos( $produtos, 'id="hdiscount-selected-products"' ) );

echo "\n[7] HTML balanceado (tags nao fechadas)\n";
// Tags <p class="description"> devem ter exatamente um </p>.
$abrindo = preg_match_all( '/<p\b/', $html );
$fechando = preg_match_all( '/<\/p>/', $html );
check( 'tags <p> balanceadas no meta box', $abrindo, $fechando );

$abrindo2 = preg_match_all( '/<p\b/', $periodo );
$fechando2 = preg_match_all( '/<\/p>/', $periodo );
check( 'tags <p> balanceadas no periodo', $abrindo2, $fechando2 );

echo "\n[8] Salvamento: checkbox desmarcado precisa ser gravado como 'no'\n";

function salvar( $post_fields ) {
    $GLOBALS['post_meta'] = array( 1 => array() );
    // O nonce e obrigatorio: sem ele a funcao aborta por seguranca.
    $_POST = $post_fields + array( 'hdiscount_meta_nonce' => 'test' );

    $post = new WP_Post();
    $post->ID = 1;
    $post->post_type = 'hdiscount_rule';

    $obj = new HDiscount_Admin();
    $obj->save_meta_box_data( 1, $post );

    $_POST = array();

    // O mock guarda por ID de post: devolve so as metas deste post.
    return isset( $GLOBALS['post_meta'][1] ) ? $GLOBALS['post_meta'][1] : array();
}

$base = array(
    'hdiscount_type'           => 'pack_price',
    'hdiscount_min_qty'        => '3',
    'hdiscount_max_qty'        => '0',
    'hdiscount_pack_price'     => '100.00',
    'hdiscount_percent'        => '20',
    'hdiscount_buy_qty'        => '4',
    'hdiscount_get_qty'        => '3',
    'hdiscount_same_variation' => 'no',
    'hdiscount_limit_to_max'   => 'no',
    'hdiscount_status'         => 'active',
    'hdiscount_product_ids'    => '100, 101 ,102,',
    'hdiscount_start_date'     => '2026-12-25T18:30',
    'hdiscount_end_date'       => '',
);

// Checkbox MARCADO.
$r = salvar( $base + array( 'hdiscount_extend_variations' => 'yes' ) );
check( 'checkbox marcado grava yes', 'yes', $r['_hdiscount_extend_variations'][0] );

// Checkbox DESMARCADO: nao chega no POST.
$r2 = salvar( $base );
check( 'checkbox desmarcado grava no', 'no', $r2['_hdiscount_extend_variations'][0] );

// Sanitizacao dos demais campos.
check( 'product_ids limpa espacos e duplicatas', '100,101,102', $r2['_hdiscount_product_ids'][0] );
check( 'min_qty salva como inteiro', 3, (int) $r2['_hdiscount_min_qty'][0] );
check( 'pack_price salva como float', 100.0, (float) $r2['_hdiscount_pack_price'][0] );
check( 'data datetime convertida para MySQL', '2026-12-25 18:30:00', $r2['_hdiscount_start_date'][0] );
check( 'data vazia fica vazia', '', $r2['_hdiscount_end_date'][0] );

// Valor fora da lista permitida e descartado.
$r3 = salvar( array_merge( $base, array( 'hdiscount_type' => 'hacker', 'hdiscount_status' => 'x' ) ) );
check( 'tipo invalido volta para pack_price', 'pack_price', $r3['_hdiscount_type'][0] );
check( 'status invalido volta para active', 'active', $r3['_hdiscount_status'][0] );

echo "\n[9] Aviso quando extensao esta desligada com produto variavel\n";

$html3 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids'       => '100',
    '_hdiscount_extend_variations' => 'no',
) );
check( 'mostra aviso com produto variavel', true,
    ( false !== strpos( $html3, 'notice-warning' ) ) );
check( 'aviso cita o nome do produto', true,
    ( false !== strpos( $html3, 'Produto Variavel' ) ) );

$html4 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids'       => '100',
    '_hdiscount_extend_variations' => 'yes',
) );
check( 'sem aviso quando extensao esta ligada', 0,
    substr_count( $html4, 'notice-warning' ) );

$html5 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids'       => '101',
    '_hdiscount_extend_variations' => 'no',
) );
check( 'sem aviso quando lista e so variacao', 0,
    substr_count( $html5, 'notice-warning' ) );

echo "\n[10] Campo de extensao existe e o default e ligado\n";
check( 'checkbox de extensao presente', true,
    ( false !== strpos( $html4, 'hdiscount_extend_variations' ) ) );
check( 'default ja vem marcado', 1,
    preg_match( '/id="hdiscount_extend_variations"[^>]*checked|checked[^>]*id="hdiscount_extend_variations"/', $html4 ) ? 1 : 0 );

echo "\n[11] Rotulo do menu\n";
check( 'menu usa "Regras de Descontos"', true,
    ( false !== strpos( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' ), "'Regras de Descontos'" ) ) );
check( 'rotulo antigo removido', 0,
    substr_count( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' ), "'Todas as Regras'" ) );

echo "\n[12] JavaScript embutido: nenhum HTML malformado\n";

/**
 * O jQuery trata qualquer string que comece com '<' como HTML.
 * Uma tag incompleta (ex: '<button title="') quebra o script inteiro
 * e impede o usuario de adicionar produtos.
 */
function tags_jquery_incompletas( $html ) {
    $problemas = array();

    // Pega $('<tag ...') e verifica se a tag foi fechada.
    if ( preg_match_all( "/\$\('(<[^']*)/", $html, $m ) ) {
        foreach ( $m[1] as $trecho ) {
            // Remove a parte do PHP inline antes de validar.
            $limpo = preg_replace( '/<\?php.*?\?>/s', '', $trecho );

            // Uma tag completa tem '>' logo apos os atributos.
            if ( ! preg_match( '/^<[a-zA-Z][a-zA-Z0-9]*\b[^>]*>/', $limpo ) ) {
                $problemas[] = $limpo;
            }
        }
    }

    return $problemas;
}

foreach ( array( 'render_meta_box', 'render_products_meta_box', 'render_period_meta_box' ) as $metodo ) {
    $saida = render_meta( $metodo );
    $malformadas = tags_jquery_incompletas( $saida );

    check( "$metodo sem tag HTML quebrada no JS", 0, count( $malformadas ) );

    if ( $malformadas ) {
        foreach ( $malformadas as $p ) {
            printf( "      >> %s\n", $p );
        }
    }
}

echo "\n[13] O botao de remover e criado com atributos, nao com HTML solto\n";

$saidaProd = render_meta( 'render_products_meta_box' );
check( 'cria o botao remover por atributo', true,
    ( false !== strpos( $saidaProd, "addClass('hdiscount-product-list__remove')" )
      || false !== strpos( $saidaProd, "class=\"hdiscount-product-list__remove\"" ) ) );
check( 'nao existe title= sem fechamento', 0,
    substr_count( $saidaProd, 'class="hdiscount-product-list__remove" title="' ) );

echo "\n[14] Strings vindas do PHP usam wp_json_encode\n";
check( 'nenhum esc_js remanescente', 0,
    substr_count( $saidaProd, 'esc_js(' ) );
$fonteAdmin = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' );
check( 'usa wp_json_encode no fonte', 1,
    substr_count( $fonteAdmin, 'wp_json_encode' ) > 0 ? 1 : 0 );
check( 'nenhum wp_json_encode com aspas duplas quebrando JS', 0,
    preg_match( '/wp_json_encode\([^)]*\)\s*\?>[\'\"]/', $fonteAdmin ) ? 1 : 0 );

echo "\n[15] Atalho de volta para a lista de regras\n";

$objBack = new HDiscount_Admin();

function render_back( $post_type ) {
    $GLOBALS['current_screen'] = new WP_Screen();
    $GLOBALS['current_screen']->post_type = $post_type;

    $obj = new HDiscount_Admin();
    ob_start();
    $obj->render_back_link();
    return ob_get_clean();
}

$back = render_back( 'hdiscount_rule' );
check( 'exibe o atalho na tela da regra', true, false !== strpos( $back, 'hdiscount-back-link' ) );
check( 'aponta para edit.php do CPT', true, false !== strpos( $back, 'edit.php' ) );
check( 'usa o post_type correto', true, false !== strpos( $back, 'post_type=hdiscount_rule' ) );
check( 'texto em portugues', true, false !== strpos( $back, 'Regras de Descontos' ) );
// A URL vem de admin_url(): nenhum dominio fixo no codigo.
check( 'URL passa por wp-admin', true, false !== strpos( $back, 'wp-admin/edit.php' ) );

// So o GitHub do autor pode estar fixo; nenhum site pessoal antigo.
$dominios = array();
if ( preg_match_all( '#https?://([a-z0-9.-]+)#i', file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' ), $dm ) ) {
    $dominios = array_values( array_unique( $dm[1] ) );
}
check( 'apenas dominios permitidos no fonte', 0,
    count( array_diff( $dominios, array( 'github.com' ) ) ) );

$outro = render_back( 'page' );
check( 'nao aparece em outros tipos de post', '', trim( $outro ) );

echo "\n[16] Atalho registrado como filtro do WordPress\n";
$fonteAdmin = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' );
check( 'usa post_submitbox_misc_actions', true,
    false !== strpos( $fonteAdmin, 'post_submitbox_misc_actions' ) );
check( 'filtro registrado no construtor', 1,
    preg_match( "/add_filter\\(\s*'post_submitbox_misc_actions'/", $fonteAdmin ) ? 1 : 0 );

echo "\n[17] Lista de produtos agrupada por produto pai\n";

// O painel precisa mandar o produto pai antes das variacoes.
$html6 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids' => '101,102,100,103',
) );

// Extrai a ordem dos IDs como aparecem na lista.
preg_match_all( '/data-product-id="(\d+)"/', $html6, $m );
$ordem = $m[1];

check( 'quatro itens na lista', 4, count( $ordem ) );
check( 'pai (100) vem antes das variacoes', '100', $ordem[0] );
check( 'variacoes depois do pai', 1,
    ( intval( $ordem[0] ) === 100 && in_array( '101', $ordem, true ) && in_array( '102', $ordem, true ) ) ? 1 : 0 );
check( 'todas as variacoes presentes', 1,
    count( array_intersect( array( '101', '102', '103' ), $ordem ) ) === 3 ? 1 : 0 );

// As variacoes sao marcadas para receber o recuo.
check( 'variacao 101 recebe a classe de filha', 1,
    preg_match( '/hdiscount-product-list__item--child"\s+data-product-id="101"/', $html6 ) ? 1 : 0 );
check( 'pai 100 nao recebe a classe de filha', 1,
    preg_match( '/hdiscount-product-list__item"\s+data-product-id="100"/', $html6 ) ? 1 : 0 );

// Variacao sem o pai na lista entra como item principal.
$html7 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids' => '101,200',
) );
preg_match_all( '/data-product-id="(\d+)"/', $html7, $m7 );
check( 'variacao orfa continua na lista', 2, count( $m7[1] ) );
check( 'variacao orfa aparece sem recuo', 1,
    preg_match( '/hdiscount-product-list__item"\s+data-product-id="101"/', $html7 ) ? 1 : 0 );

// JS de reordenacao presente.
check( 'JS redesenha a lista agrupada', 1,
    false !== strpos( $saidaProd, 'function renderList' ) ? 1 : 0 );
check( 'JS agrupa variacoes sob o pai', 1,
    false !== strpos( $saidaProd, 'filhas' ) ? 1 : 0 );
check( 'CSS recua as variacoes', 1,
    false !== strpos( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'assets/admin.css' ), 'hdiscount-product-list__item--child' ) ? 1 : 0 );

echo "\n[18] O JS precisa conseguir reler a lista salva\n";

// Ao abrir uma regra, o redesenho acontece a partir dos atributos do HTML.
// Se faltar algum, o produto aparece sem nome.
foreach ( array( 'data-name', 'data-parent-id', 'data-parent-name', 'data-sku', 'data-product-type' ) as $attr ) {
    check( "atributo $attr presente nos itens", 1,
        preg_match( '/' . preg_quote( $attr, '/' ) . '="/', $html6 ) ? 1 : 0 );
}

preg_match( '/data-product-id="100"[^>]*data-name="([^"]*)"/', $html6, $m );
check( 'nome do produto preservado no atributo', 'Produto Variavel', $m[1] ?? '' );

preg_match_all( '/data-product-id="(10[123])"[^>]*data-name="([^"]*)"/', $html6, $mv );
$nomes = $mv[2];
check( 'variacoes tambem trazem o nome', 3, count( $nomes ) );
check( 'nenhum nome vazio', 0, count( array_filter( $nomes, function( $n ) { return '' === trim( $n ); } ) ) );

check( 'JS le o nome do item ao carregar', 1,
    false !== strpos( $saidaProd, "data('name')" ) ? 1 : 0 );

echo "\n[19] Escolher uma variacao leva o produto pai\n";

check( 'JS tem a regra que troca variacao pelo pai', 1,
    false !== strpos( $saidaProd, "product.type === 'variation' && product.parent_id && extensaoLigada()" ) ? 1 : 0 );
check( 'JS consulta a opcao de extensao', 1,
    false !== strpos( $saidaProd, 'function extensaoLigada' ) ? 1 : 0 );
check( 'JS usa o id do pai ao adicionar', 1,
    false !== strpos( $saidaProd, 'id: product.parent_id' ) ? 1 : 0 );
check( 'busca devolve o tipo do produto pai', 1,
    false !== strpos( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-ajax.php' ), "'parent_type'" ) ? 1 : 0 );
check( 'sugestao avisa que inclui as variacoes', 1,
    false !== strpos( $saidaProd, 'inclui todas as variacoes' ) ? 1 : 0 );
check( 'CSS do aviso na busca', 1,
    false !== strpos( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'assets/admin.css' ), 'hdiscount-suggestion__hint' ) ? 1 : 0 );

echo "\n[20] Aviso sobre precos diferentes na mistura\n";

// Dois produtos simples para o teste de mistura.
$GLOBALS['wc_products'][400] = new WC_Product( 400, 35.00, 'CAMISETA', 'simple' );
$GLOBALS['wc_products'][500] = new WC_Product( 500, 35.00, 'CALCA', 'simple' );

// Produtos com o MESMO preco: a informacao tranquiliza.
$h1 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids'  => '400,500',
    '_hdiscount_mix_products'  => 'yes',
) );
check( 'mesmo preco: mostra aviso informativo', 1,
    false !== strpos( $h1, 'notice-info' ) ? 1 : 0 );
check( 'mesmo preco: cita o valor', 1,
    false !== strpos( $h1, '35' ) ? 1 : 0 );
check( 'mesmo preco: nao mostra alerta de atencao', 0,
    substr_count( $h1, 'notice-warning' ) );

// Produtos com precos DIFERENTES: alerta.
$GLOBALS['wc_products'][500]->set_price( 50.00 );
$h2 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids'  => '400,500',
    '_hdiscount_mix_products'  => 'yes',
) );
check( 'precos diferentes: mostra atencao', 1,
    false !== strpos( $h2, 'notice-warning' ) ? 1 : 0 );
check( 'precos diferentes: lista os dois valores', 1,
    ( false !== strpos( $h2, '35' ) && false !== strpos( $h2, '50' ) ) ? 1 : 0 );

// Mistura desligada: nenhum aviso sobre preco.
$h3 = render_meta( 'render_products_meta_box', array(
    '_hdiscount_product_ids'  => '400,500',
    '_hdiscount_mix_products'  => 'no',
) );
check( 'sem mistura: sem aviso de preco', 0,
    substr_count( $h3, 'notice-info' ) + substr_count( $h3, 'notice-warning' ) );
$GLOBALS['wc_products'][500]->set_price( 50.00 );

echo "\n[21] Guia em modal\n";

$srcModal = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' );

check( 'modal tem role dialog', 1, false !== strpos( $srcModal, 'role="dialog"' ) ? 1 : 0 );
check( 'modal tem aria-modal', 1, false !== strpos( $srcModal, 'aria-modal="true"' ) ? 1 : 0 );
check( 'modal comeca oculto', 1, false !== strpos( $srcModal, 'hidden>' ) ? 1 : 0 );
check( 'botao de fechar acessivel', 1, false !== strpos( $srcModal, 'data-hdiscount-about-close' ) ? 1 : 0 );
check( 'link externo com rel=noopener', 1, false !== strpos( $srcModal, 'rel="noopener noreferrer"' ) ? 1 : 0 );
check( 'CSS do modal', 1,
    false !== strpos( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'assets/admin.css' ), 'hdiscount-modal__box' ) ? 1 : 0 );
check( 'JS trava e devolve o foco', 1,
    false !== strpos( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'assets/admin.js' ), 'hdiscount-modal-open' ) ? 1 : 0 );
check( 'explica os tipos de desconto', 1,
    false !== strpos( $srcModal, 'Preço fixo do pack' ) ? 1 : 0 );

// ---------------------------------------------------------------------------
// Visual premium (1.7): cabecalho de marca, colunas, modal do desenvolvedor.
// ---------------------------------------------------------------------------
function get_site_transient( $k ) { return $GLOBALS['site_transients'][ $k ] ?? false; }

function tela( $id ) { $GLOBALS['current_screen'] = null === $id ? null : (object) array( 'id' => $id, 'post_type' => 'hdiscount_rule' ); }
function captura( $cb ) { ob_start(); $cb(); return ob_get_clean(); }

echo "\n[Cabecalho de marca]\n";
foreach ( array( 'edit-hdiscount_rule', 'hdiscount_rule' ) as $id ) {
    tela( $id );
    $h = captura( array( $admin, 'render_brand_header' ) );
    check( "aparece na tela $id", 1, false !== strpos( $h, 'hdw-brand' ) ? 1 : 0 );
}
foreach ( array( 'plugins', 'dashboard', 'edit-post', 'woocommerce_page_wc-settings' ) as $id ) {
    tela( $id );
    check( "nao aparece na tela $id", '', captura( array( $admin, 'render_brand_header' ) ) );
}
tela( null );
check( 'nao aparece sem tela (nem quebra)', '', captura( array( $admin, 'render_brand_header' ) ) );

tela( 'edit-hdiscount_rule' );
$GLOBALS['site_transients'] = array();
$h = captura( array( $admin, 'render_brand_header' ) );
check( 'mostra o nome do plugin', 1, false !== strpos( $h, 'HDiscount' ) ? 1 : 0 );
check( 'mostra a versao', 1, false !== strpos( $h, 'Versão ' . HDISCOUNT_VERSION ) ? 1 : 0 );
check( 'mostra o nome do desenvolvedor', 1, false !== strpos( $h, 'Anderson H' ) ? 1 : 0 );
check( 'link do desenvolvedor aponta para o GitHub dele', 1, false !== strpos( $h, 'href="https://github.com/anderhofelmann"' ) ? 1 : 0 );
check( 'link do repositorio', 1, false !== strpos( $h, 'href="https://github.com/anderhofelmann/hdiscount"' ) ? 1 : 0 );
check( 'botao que abre o guia', 1, false !== strpos( $h, 'data-hdiscount-about="1"' ) ? 1 : 0 );
check( 'links externos com rel=noopener noreferrer', substr_count( $h, 'target="_blank"' ), substr_count( $h, 'rel="noopener noreferrer"' ) );
check( 'sem script nem estilo embutido no cabecalho', 0, preg_match( '/<script|<style|onclick=|style=/i', $h ) );
check( 'sem atualizacao: mostra "Atualizado"', 1, false !== strpos( $h, 'hdw-chip--ok' ) ? 1 : 0 );
check( 'sem atualizacao: nao mostra aviso de versao nova', 0, false !== strpos( $h, 'hdw-chip--update' ) ? 1 : 0 );

$GLOBALS['site_transients']['update_plugins'] = (object) array( 'response' => array( 'hdiscount/hdiscount.php' => (object) array( 'new_version' => '9.9.9' ) ) );
$h = captura( array( $admin, 'render_brand_header' ) );
check( 'com versao nova: avisa e informa qual', 1, ( false !== strpos( $h, 'hdw-chip--update' ) && false !== strpos( $h, '9.9.9' ) ) ? 1 : 0 );
check( 'com versao nova: some o "Atualizado"', 0, false !== strpos( $h, 'hdw-chip--ok' ) ? 1 : 0 );
$GLOBALS['site_transients']['update_plugins'] = (object) array( 'response' => array( 'hdiscount/hdiscount.php' => (object) array( 'new_version' => '0.0.1' ) ) );
check( 'versao "nova" mais antiga que a instalada e ignorada', 0, HDiscount_Admin::update_status()['available'] ? 1 : 0 );
$GLOBALS['site_transients']['update_plugins'] = (object) array( 'response' => array( 'hdiscount/hdiscount.php' => (object) array( 'new_version' => '9.9<script>alert(1)</script>' ) ) );
$h = captura( array( $admin, 'render_brand_header' ) );
check( 'versao nova com HTML sai escapada', 1, ( false === strpos( $h, '<script>alert' ) && false !== strpos( $h, '&lt;script&gt;' ) ) ? 1 : 0 );
$GLOBALS['site_transients'] = array();

echo "\n[Rodape do painel]\n";
tela( 'edit-hdiscount_rule' );
$f = $admin->admin_footer_text( 'Obrigado por criar com o WordPress.' );
check( 'rodape traz versao e desenvolvedor', 1, ( false !== strpos( $f, HDISCOUNT_VERSION ) && false !== strpos( $f, 'Anderson H' ) ) ? 1 : 0 );
tela( 'dashboard' );
check( 'em outras telas o rodape original e mantido', 'Obrigado por criar com o WordPress.', $admin->admin_footer_text( 'Obrigado por criar com o WordPress.' ) );

echo "\n[Colunas da lista de regras]\n";
function coluna( $col, $meta ) {
    $GLOBALS['post_meta'] = array( 7 => array() );
    foreach ( $meta as $k => $v ) { $GLOBALS['post_meta'][7][ $k ] = array( $v ); }
    global $admin;
    return captura( function () use ( $admin, $col ) { $admin->render_custom_columns( $col, 7 ); } );
}
$c = coluna( 'hdiscount_type', array( '_hdiscount_type' => 'pack_price', '_hdiscount_min_qty' => 3, '_hdiscount_pack_price' => 100 ) );
check( 'tipo pack: pilula', 1, false !== strpos( $c, 'hdw-pill--type' ) ? 1 : 0 );
check( 'tipo pack: resumo "Leve 3 por R$ 100,00"', 1, false !== strpos( $c, 'Leve 3 por R$ 100,00' ) ? 1 : 0 );
$c = coluna( 'hdiscount_type', array( '_hdiscount_type' => 'percent', '_hdiscount_min_qty' => 2, '_hdiscount_percent' => 15 ) );
check( 'tipo percentual: resumo "15% a partir de 2 un."', 1, false !== strpos( $c, '15% a partir de 2 un.' ) ? 1 : 0 );
$c = coluna( 'hdiscount_type', array( '_hdiscount_type' => 'buy_x_get_y', '_hdiscount_buy_qty' => 4, '_hdiscount_get_qty' => 3 ) );
check( 'tipo compre X leve Y: resumo "Compre 4, pague 3"', 1, false !== strpos( $c, 'Compre 4, pague 3' ) ? 1 : 0 );
$c = coluna( 'hdiscount_type', array( '_hdiscount_type' => '<img src=x onerror=alert(1)>' ) );
check( 'tipo desconhecido com HTML sai escapado', 1, ( false === strpos( $c, '<img' ) && false !== strpos( $c, '&lt;img' ) ) ? 1 : 0 );
$c = coluna( 'hdiscount_type', array( '_hdiscount_type' => 'pack_price' ) );
check( 'pack sem dados nao inventa resumo', 0, false !== strpos( $c, 'hdw-sub' ) ? 1 : 0 );
foreach ( array( 'active' => 'Ativa', 'inactive' => 'Inativa', 'scheduled' => 'Agendada' ) as $st => $rot ) {
    $c = coluna( 'hdiscount_status', array( '_hdiscount_status' => $st ) );
    check( "status $st: pilula com o rotulo", 1, ( false !== strpos( $c, "hdw-pill--$st" ) && false !== strpos( $c, $rot ) ) ? 1 : 0 );
}
$c = coluna( 'hdiscount_status', array() );
check( 'sem status: assume Ativa', 1, false !== strpos( $c, 'hdw-pill--active' ) ? 1 : 0 );
$c = coluna( 'hdiscount_status', array( '_hdiscount_status' => '"><script>x</script>' ) );
check( 'status desconhecido com HTML sai escapado', 1, ( false === strpos( $c, '<script>' ) ) ? 1 : 0 );
$todas = '';
foreach ( array( 'hdiscount_type', 'hdiscount_products', 'hdiscount_qty', 'hdiscount_period', 'hdiscount_status' ) as $col ) {
    $todas .= coluna( $col, array( '_hdiscount_type' => 'pack_price', '_hdiscount_min_qty' => 3, '_hdiscount_pack_price' => 100, '_hdiscount_start_date' => '2026-11-01 10:00:00', '_hdiscount_status' => 'active' ) );
}
check( 'colunas sem estilo embutido (style=)', 0, false !== strpos( $todas, 'style=' ) ? 1 : 0 );

echo "\n[Guia: cartao do desenvolvedor]\n";
tela( 'plugins' );
$m = captura( array( $admin, 'render_about_modal' ) );
check( 'cartao do desenvolvedor presente', 1, false !== strpos( $m, 'hdw-dev' ) ? 1 : 0 );
check( 'nome do desenvolvedor no guia', 1, false !== strpos( $m, 'Anderson H' ) ? 1 : 0 );
check( 'links do guia: perfil, repositorio, problemas, novidades e documentacao', 5, preg_match_all( '#href="https://github\.com/anderhofelmann(?:/hdiscount(?:/issues|/releases)?)?"#', $m ) );
check( 'links externos do guia com rel=noopener noreferrer', substr_count( $m, 'target="_blank"' ), substr_count( $m, 'rel="noopener noreferrer"' ) );
check( 'guia mostra a versao', 1, false !== strpos( $m, 'v' . HDISCOUNT_VERSION ) ? 1 : 0 );
$cab = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'hdiscount.php' );
preg_match( '/Requires at least:\s*([\d.]+)/', $cab, $rw ); preg_match( '/Requires PHP:\s*([\d.]+)/', $cab, $rp ); preg_match( '/WC requires at least:\s*([\d.]+)/', $cab, $rc );
check( 'requisitos do guia batem com o cabecalho do plugin', 1,
    false !== strpos( $m, 'WordPress ' . $rw[1] . ' · PHP ' . $rp[1] . ' · WooCommerce ' . $rc[1] ) ? 1 : 0 );
tela( 'dashboard' );
check( 'modal nao aparece em outras telas', '', captura( array( $admin, 'render_about_modal' ) ) );

echo "\n[Arquivos do visual]\n";
$js  = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'assets/admin.js' );
$css = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'assets/admin.css' );
$src = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' );
check( 'Esc fecha o modal (compara com e.key de verdade)', 1, ( false !== strpos( $js, "'Escape' === e.key" ) && ! preg_match( '/27 === e\.key(?!Code)/', $js ) ) ? 1 : 0 );
check( 'Tab fica preso no modal (compara com e.key de verdade)', 1, ( false !== strpos( $js, "'Tab' !== e.key" ) && ! preg_match( '/9 !== e\.key(?!Code)/', $js ) ) ? 1 : 0 );
check( 'script e estilo tambem na lista de Plugins (link "Sobre")', 1, false !== strpos( $src, "'plugins.php' !== \$hook" ) ? 1 : 0 );
check( 'CSS do cabecalho e do cartao', 1, ( false !== strpos( $css, '.hdw-brand' ) && false !== strpos( $css, '.hdw-dev' ) ) ? 1 : 0 );
check( 'CSS usa a paleta (variaveis)', 1, false !== strpos( $css, '--hdw-gold' ) ? 1 : 0 );
check( 'coluna lateral: campos em 100% (nao cortam)', 1, ( false !== strpos( $css, '#side-sortables .form-table input[type="datetime-local"]' ) && preg_match( '/#side-sortables \.form-table select[^{]*\{[^}]*width:\s*100% !important/s', $css ) ) ? 1 : 0 );
check( 'caixas nao escondem o que transborda (sem overflow:hidden no postbox)', 0, preg_match( '/#poststuff \.postbox\s*\{[^}]*overflow:\s*hidden/s', $css ) );
check( 'CSS escopado: nada de regra solta em body/a/p', 0, preg_match( '/^(body|a|p|h1|h2|table|input)\s*[{,]/m', $css ) );
check( 'icone na cor da marca', 1, false !== strpos( file_get_contents( HDISCOUNT_PLUGIN_PATH . 'assets/icon.svg' ), '#b08d57' ) ? 1 : 0 );
check( 'selo de versao antigo da lista foi removido (fonte unica: o cabecalho)', 0, false !== strpos( $src, 'append_version_to_views' ) ? 1 : 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
if ( 0 === $fails ) {
    echo "RESULTADO: todos os $total testes passaram\n";
} else {
    echo "RESULTADO: $fails de $total testes FALHARAM\n";
}
exit( 0 === $fails ? 0 : 1 );