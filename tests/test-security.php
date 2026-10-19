<?php
/**
 * Verificacoes de seguranca do HDiscount.
 *
 * Cada item aqui cobre um risco real de um plugin WordPress:
 * injecao de SQL, XSS, CSRF, falta de permissao e acesso direto a arquivos.
 */

require_once __DIR__ . '/wp-mock.php';

define( 'HDISCOUNT_PLUGIN_PATH', __DIR__ . '/../hdiscount/' );
define( 'HDISCOUNT_PLUGIN_URL', 'https://exemplo.test/' );
define( 'HDISCOUNT_VERSION', '1.4.0' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

$fails = 0;
$total = 0;

function check( $desc, $esperado, $obtido ) {
    global $fails, $total;
    $total++;
    $ok = ( $esperado === $obtido );
    if ( ! $ok ) { $fails++; }
    printf( "  %s %-58s esp: %-10s obt: %s\n",
        $ok ? 'OK  ' : 'FALHA', $desc,
        var_export( $esperado, true ), var_export( $obtido, true ) );
}

function fonte( $arquivo ) {
    return file_get_contents( HDISCOUNT_PLUGIN_PATH . $arquivo );
}

$arquivos_php = array(
    'hdiscount.php',
    'uninstall.php',
    'includes/class-hdiscount-admin.php',
    'includes/class-hdiscount-ajax.php',
    'includes/class-hdiscount-autoloader.php',
    'includes/class-hdiscount-cart-logic.php',
    'includes/class-hdiscount-updater.php',
    'includes/class-hdiscount-version.php',
);

echo "\n[1] Injecao de SQL\n";

// Nada de SQL montado a mao: o plugin usa apenas as APIs do WordPress.
$com_sql = array();
foreach ( $arquivos_php as $arquivo ) {
    if ( preg_match( '/\$wpdb|->query\(|->prepare\(|SELECT |INSERT |UPDATE |DELETE FROM/', fonte( $arquivo ) ) ) {
        $com_sql[] = $arquivo;
    }
}
check( 'nenhum SQL montado a mao', 0, count( $com_sql ) );

echo "\n[2] Acesso direto aos arquivos\n";

// Sem a guarda, abrir o arquivo direto pelo navegador executaria PHP e
// poderia revelar o caminho do servidor.
foreach ( $arquivos_php as $arquivo ) {
    check( "$arquivo tem guarda ABSPATH", 1,
        false !== strpos( fonte( $arquivo ), "defined( 'ABSPATH' )" ) ? 1 : 0 );
}

echo "\n[3] Protecao dos endpoints AJAX\n";

$ajax = fonte( 'includes/class-hdiscount-ajax.php' );

check( 'valida nonce', 1, false !== strpos( $ajax, 'check_ajax_referer' ) ? 1 : 0 );
check( 'exige permissao do usuario', 1, false !== strpos( $ajax, 'current_user_can' ) ? 1 : 0 );
check( 'a permissao vem DEPOIS do nonce', 1,
    strpos( $ajax, 'check_ajax_referer' ) < strpos( $ajax, 'current_user_can' ) ? 1 : 0 );
check( 'term o e sanitizado', 1, false !== strpos( $ajax, 'sanitize_text_field' ) ? 1 : 0 );
check( 'usa wp_unslash na entrada', 1, false !== strpos( $ajax, 'wp_unslash' ) ? 1 : 0 );
check( 'restringe a produtos publicados', 1, false !== strpos( $ajax, "'post_status'    => 'publish'" ) ? 1 : 0 );
check( 'limita a quantidade de resultados', 1, false !== strpos( $ajax, "'posts_per_page' => 20" ) ? 1 : 0 );
check( 'nao registra acoes paraulogins', 0,
    false !== strpos( $ajax, "add_action( 'wp_ajax_nopriv_" ) ? 1 : 0 );

echo "\n[4] Protecao do salvamento das regras\n";

$admin = fonte( 'includes/class-hdiscount-admin.php' );

check( 'valida nonce ao salvar', 1, false !== strpos( $admin, 'wp_verify_nonce' ) ? 1 : 0 );
check( 'confere permissao de edicao', 1, false !== strpos( $admin, "current_user_can( 'edit_post'" ) ? 1 : 0 );
check( 'ignora autosave', 1, false !== strpos( $admin, 'DOING_AUTOSAVE' ) ? 1 : 0 );
check( 'confere o tipo de post', 1, false !== strpos( $admin, "self::CPT_SLUG !== \$post->post_type" ) ? 1 : 0 );

// Valores de enum sao filtrados: nao podem chegar Strings inesperadas.
check( 'tipo de desconto so aceita valores conhecidos', 1,
    false !== strpos( $admin, "'_hdiscount_type'   => array( 'pack_price', 'percent', 'buy_x_get_y' )" ) ? 1 : 0 );
check( 'status so aceita valores conhecidos', 1,
    false !== strpos( $admin, "'_hdiscount_status' => array( 'active', 'inactive', 'scheduled' )" ) ? 1 : 0 );
check( 'IDs de produto sao convertidos para inteiro', 1,
    false !== strpos( $admin, "array_map( 'absint'" ) ? 1 : 0 );

echo "\n[5] XSS nas telas do painel\n";

// O nome do produto vem do banco e pode conter qualquer coisa.
check( 'nome do produto com escape na listagem', 1,
    false !== strpos( $admin, "esc_html( \$item['name'] )" ) ? 1 : 0 );
check( 'atributo HTML com escape', 1,
    false !== strpos( $admin, 'esc_attr( $item' ) ? 1 : 0 );
check( 'link do menu com esc_url', 1,
    false !== strpos( $admin, 'esc_url( $url )' ) ? 1 : 0 );
// Nenhuma entrada de usuario pode chegar na tela sem passar por escape.
check( 'nenhum echo direto de superglobal', 0,
    preg_match( '/echo\s+\$_(GET|POST|REQUEST|COOKIE|SERVER)/', $admin ) ? 1 : 0 );
check( 'nenhuma tag curta <?= em arquivos do plugin', 0,
    preg_match( '/<\?=/', $admin . $cart . $upd ) ? 1 : 0 );

echo "\n[6] Saidas do carrinho\n";

$cart = fonte( 'includes/class-hdiscount-cart-logic.php' );

// O texto da promocao vai para o carrinho e para o pedido.
check( 'rotulo do desconto com escape', 1,
    false !== strpos( $cart, 'wp_strip_all_tags' ) ? 1 : 0 );
check( 'nao injeta HTML do usuario no rotulo', 0,
    preg_match( '/\$rule\[\'title\'\][^)]*\)\s*;\s*\n\s*\}/', $cart ) ? 1 : 0 );
check( 'fee usa valor numerico', 1,
    false !== strpos( $cart, '(float) $desconto' ) ? 1 : 0 );

echo "\n[7] Atualizador via GitHub\n";

$upd = fonte( 'includes/class-hdiscount-updater.php' );

check( 'a URL da API e fixa (nao vem de entrada do usuario)', 1,
    false !== strpos( $upd, "self::REPO )" ) && false === strpos( $upd, '$_GET' ) && false === strpos( $upd, '$_POST' ) ? 1 : 0 );
check( 'so busca o asset com o nome esperado', 1,
    false !== strpos( $upd, "'hdiscount.zip' === \$asset['name']" ) ? 1 : 0 );
check( 'changelog passa por wp_kses_post', 1,
    false !== strpos( $upd, 'wp_kses_post' ) ? 1 : 0 );
check( 'respeita o limite da API com cache', 1,
    false !== strpos( $upd, 'self::CACHE_TTL' ) ? 1 : 0 );
check( 'aviso so para quem pode atualizar plugins', 1,
    false !== strpos( $upd, "current_user_can( 'update_plugins' )" ) ? 1 : 0 );
check( 'falha de rede nao gera atualizacao', 1,
    false !== strpos( $upd, 'is_wp_error' ) ? 1 : 0 );
check( 'timeout curto na requisicao', 1,
    false !== strpos( $upd, "'timeout'    => 8" ) ? 1 : 0 );

echo "\n[8] Exposicao de dados\n";

check( 'CPT nao exposto via REST', 1,
    preg_match( "/'show_in_rest'\s*=>\s*false/", fonte( 'hdiscount.php' ) ) ? 1 : 0 );
check( 'CPT fora da busca do site', 1,
    preg_match( "/'exclude_from_search'\s*=>\s*true/", fonte( 'hdiscount.php' ) ) ? 1 : 0 );
check( 'CPT e privado', 1,
    preg_match( "/'public'\s*=>\s*false/", fonte( 'hdiscount.php' ) ) ? 1 : 0 );
check( 'desinstalacao apaga as opcoes', 1,
    false !== strpos( fonte( 'uninstall.php' ), "delete_option( 'hdiscount_version' )" ) ? 1 : 0 );
check( 'desinstalacao apaga os transients', 1,
    false !== strpos( fonte( 'uninstall.php' ), "delete_transient( 'hdiscount_update_check' )" ) ? 1 : 0 );

echo "\n[9] Cabecalho do plugin\n";

$cab = fonte( 'hdiscount.php' );

check( 'declara versao minima do PHP', 1,
    false !== strpos( $cab, 'Requires PHP:' ) ? 1 : 0 );
check( 'autor com link para o GitHub', 1,
    false !== strpos( $cab, 'Author URI:        https://github.com/anderhofelmann' ) ? 1 : 0 );
check( 'sem link de site antigo', 0,
    false !== strpos( $cab, 'andersonhofelmann.com' ) ? 1 : 0 );
check( 'texto Domain declarado', 1,
    false !== strpos( $cab, 'Text Domain:' ) ? 1 : 0 );
check( 'licenca declarada', 1,
    false !== strpos( $cab, 'License:' ) ? 1 : 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
if ( 0 === $fails ) {
    echo "RESULTADO: todos os $total verificacoes passaram\n";
} else {
    echo "RESULTADO: $fails de $total verificacoes FALHARAM\n";
}
exit( 0 === $fails ? 0 : 1 );