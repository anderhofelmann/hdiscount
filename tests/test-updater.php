<?php
/**
 * Testes do atualizador via GitHub.
 *
 * A API do GitHub e simulada em $GLOBALS['http_response'].
 */

require_once __DIR__ . '/wp-mock.php';

define( 'HDISCOUNT_PLUGIN_PATH', __DIR__ . '/../hdiscount/' );
define( 'HDISCOUNT_PLUGIN_URL', 'https://exemplo.test/' );
define( 'HDISCOUNT_VERSION', '1.4.0' );
define( 'MINUTE_IN_SECONDS', 60 );

require_once HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-updater.php';

$fails = 0;
$total = 0;

function check( $desc, $esperado, $obtido ) {
    global $fails, $total;
    $total++;
    $ok = ( $esperado === $obtido );
    if ( ! $ok ) { $fails++; }
    printf( "  %s %-56s esp: %-22s obt: %s\n",
        $ok ? 'OK  ' : 'FALHA', $desc,
        var_export( $esperado, true ), var_export( $obtido, true ) );
}

/** Monta a resposta simulada da API do GitHub. */
function github( $tag, $com_zip = true, $code = 200 ) {
    $assets = array();
    if ( $com_zip ) {
        $assets[] = array(
            'name'                 => 'hdiscount.zip',
            'browser_download_url' => 'https://github.com/anderhofelmann/hdiscount/releases/download/' . $tag . '/hdiscount.zip',
        );
    }

    $GLOBALS['http_response'] = array(
        'code' => $code,
        'body' => wp_json_encode_compat( array(
            'tag_name' => $tag,
            'html_url' => 'https://github.com/anderhofelmann/hdiscount/releases/tag/' . $tag,
            'body'     => '### Novidades\n\n- Correcoes e melhorias.',
            'assets'   => $assets,
        ) ),
    );
}

function wp_json_encode_compat( $v ) {
    return json_encode( $v );
}

/** Transiente do WordPress com um objeto vazio de resposta. */
function transiente_vazio() {
    $t = new stdClass();
    $t->response     = array();
    $t->no_update    = array();
    $t->checked      = array();
    $t->last_checked = time();
    return $t;
}

/** Zera caches entre testes. */
function limpar() {
    $GLOBALS['transients'] = array();
    $GLOBALS['http_calls'] = array();
}

echo "\n[1] Normalizacao de versao\n";

check( 'remove o "v" da tag',        '1.2.3', HDiscount_Updater::normalize_version( 'v1.2.3' ) );
check( 'remove "V" maiusculo',       '1.2.3', HDiscount_Updater::normalize_version( 'V1.2.3' ) );
check( 'remove barra final',         '1.2.3', HDiscount_Updater::normalize_version( '1.2.3/' ) );
check( 'versao instalada',          HDISCOUNT_VERSION, HDiscount_Updater::current_version() );

echo "\n[2] Sem versao nova: nada muda\n";

limpar( github( 'v1.2.0' ) );  // menor que a instalada
$t = HDiscount_Updater::check_update( transiente_vazio() );
check( 'versao anterior e ignorada', 0, count( $t->response ) );

limpar( github( 'v1.3.0' ) );  // igual a instalada
$t = HDiscount_Updater::check_update( transiente_vazio() );
check( 'mesma versao e ignorada', 0, count( $t->response ) );

echo "\n[3] Versao nova detectada\n";

limpar( github( 'v2.0.0' ) );
$t = HDiscount_Updater::check_update( transiente_vazio() );

check( 'plugin aparece na lista de atualizacao', 1, count( $t->response ) );
check( 'chama a API do GitHub', 1, count( $GLOBALS['http_calls'] ) );
check( 'url correta da API', 'https://api.github.com/repos/anderhofelmann/hdiscount/releases/latest', $GLOBALS['http_calls'][0] );

$u = $t->response['hdiscount/hdiscount.php'];
check( 'nova versao preenchida', '2.0.0', $u->new_version );
check( 'slug do plugin', 'hdiscount', $u->slug );
check( 'caminho do plugin', 'hdiscount/hdiscount.php', $u->plugin );
check( 'ZIP aponta para a release',
    'https://github.com/anderhofelmann/hdiscount/releases/download/v2.0.0/hdiscount.zip', $u->package );
check( 'aviso de atualizacao preenchido', 1,
    false !== strpos( $u->upgrade_notice, '2.0.0' )
    && false !== strpos( $u->upgrade_notice, HDiscount_Updater::current_version() ) ? 1 : 0 );
check( 'secoes com changelog', 1, isset( $u->sections['changelog'] ) ? 1 : 0 );

echo "\n[4] Cache: uma unica chamada a API\n";

limpar( github( 'v2.0.0' ) );
HDiscount_Updater::get_release_info();
HDiscount_Updater::get_release_info();
HDiscount_Updater::get_release_info();
check( 'tres leituras, uma so requisicao', 1, count( $GLOBALS['http_calls'] ) );

// Novo request, mas o cache de 6 horas ainda vale.
$antes = count( $GLOBALS['http_calls'] );
HDiscount_Updater::check_update( transiente_vazio() );
check( 'nova verificacao usa o cache', $antes, count( $GLOBALS['http_calls'] ) );

// Depois que o cache expira, consulta de novo.
delete_transient( HDiscount_Updater::CACHE_KEY );
delete_transient( HDiscount_Updater::CACHE_BRANCH );
HDiscount_Updater::get_release_info();
check( 'cache expirado volta a consultar', $antes + 1, count( $GLOBALS['http_calls'] ) );

echo "\n[5] Falhas de rede nao quebram o painel\n";

$GLOBALS['http_response'] = null;  // sem resposta: wp_remote_get devolve WP_Error
limpar();
$t = HDiscount_Updater::check_update( transiente_vazio() );
check( 'sem rede: nenhuma atualizacao forjada', 0, count( $t->response ) );

limpar( github( 'v2.0.0', true, 403 ) );  // limite da API
$t = HDiscount_Updater::check_update( transiente_vazio() );
check( 'HTTP 403: nada acontece', 0, count( $t->response ) );

limpar( github( 'v2.0.0', false ) );  // release sem ZIP
$t = HDiscount_Updater::check_update( transiente_vazio() );
check( 'release sem ZIP nao oferece atualizacao', 0, count( $t->response ) );

$GLOBALS['http_response'] = array( 'code' => 200, 'body' => 'nao e json' );
limpar();
$t = HDiscount_Updater::check_update( transiente_vazio() );
check( 'resposta invalida e ignorada', 0, count( $t->response ) );

echo "\n[6] Tela de detalhes do plugin\n";

limpar( github( 'v2.0.0' ) );
$args        = new stdClass();
$args->slug  = 'hdiscount';
$info = HDiscount_Updater::plugin_info( false, 'plugin_information', $args );
check( 'devolve os detalhes', true, is_object( $info ) );
check( 'nome do plugin', 'HDiscount', $info->name );
check( 'versao mais recente', '2.0.0', $info->version );
check( 'link do download', 1, false !== strpos( $info->download_link, 'hdiscount.zip' ) ? 1 : 0 );

// Outro plugin nao deve ser afetado.
$outro        = new stdClass();
$outro->slug  = 'woocommerce';
check( 'outro slug nao e tocado', false, HDiscount_Updater::plugin_info( false, 'plugin_information', $outro ) );
check( 'outra acao nao e tocada', false, HDiscount_Updater::plugin_info( false, 'query_plugins', $args ) );

echo "\n[7] Registro no bootstrap do plugin\n";

$bootstrap = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'hdiscount.php' );
check( 'classe listada no carregamento', 1,
    false !== strpos( $bootstrap, "'HDiscount_Updater'" ) ? 1 : 0 );
check( 'arquivo existe no plugin', true,
    file_exists( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-updater.php' ) );

echo "\n" . str_repeat( '-', 74 ) . "\n";
if ( 0 === $fails ) {
    echo "RESULTADO: todos os $total testes passaram\n";
} else {
    echo "RESULTADO: $fails de $total testes FALHARAM\n";
}
exit( 0 === $fails ? 0 : 1 );