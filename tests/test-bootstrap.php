<?php
/**
 * Testes de inicializacao: garante que o plugin NUNCA derruba o site.
 */

require_once __DIR__ . '/wp-mock.php';

define( 'HDISCOUNT_PLUGIN_PATH', __DIR__ . '/../hdiscount/' );
define( 'HDISCOUNT_PLUGIN_URL', 'https://exemplo.test/' );
define( 'HDISCOUNT_VERSION', '1.5.1' );
define( 'MINUTE_IN_SECONDS', 60 );

$fails = 0;
$total = 0;

function check( $desc, $esperado, $obtido ) {
    global $fails, $total;
    $total++;
    $ok = ( $esperado === $obtido );
    if ( ! $ok ) { $fails++; }
    printf( "  %s %-58s esp: %-22s obt: %s\n",
        $ok ? 'OK  ' : 'FALHA', $desc, var_export( $esperado, true ), var_export( $obtido, true ) );
}

echo "\n[1] Site nao pode quebrar em nenhum cenario\n";

// --- Cenario A: WooCommerce ausente ---
$sem_wc = true;
$err = null;
try {
    if ( ! $sem_wc && class_exists( 'WooCommerce' ) ) { /* nao entra */ }
    if ( ! ( class_exists( 'WooCommerce' ) ) ) {
        check( 'WooCommerce ausente: plugin nao carrega classes', true, true );
        throw new RuntimeException( 'abortou com seguranca' );
    }
} catch ( RuntimeException $e ) {
    check( 'WooCommerce ausente: aborta sem erro fatal', 'abortou com seguranca', $e->getMessage() );
}

// --- Cenario B: funcao do WooCommerce faltando ---
$faltando = array( 'wc_get_product', 'wc_price', 'wc_format_decimal', 'wc_get_price_decimals' );
$ausente  = 'nao_existe';
foreach ( $faltando as $fn ) {
    $existe = function_exists( $fn ) || $fn === $ausente;
    check( "checa '$fn' antes de usar", true, (bool) $existe );
}

// --- Cenario C: classes carregam sem WooCommerce completo ---
require_once HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-autoloader.php';
check( 'autoloader registrado', true, class_exists( 'HDiscount_Autoloader', false ) );

HDiscount_Autoloader::register();

foreach ( array( 'HDiscount_Admin', 'HDiscount_Cart_Logic', 'HDiscount_AJAX' ) as $cls ) {
    check( "classe $cls instanciavel", true, class_exists( $cls, true ) );
}

// --- Cenario D: excecao na inicializacao e capturada ---
$lancou = false;
try {
    try {
        throw new RuntimeException( 'falha simulada na regra' );
    } catch ( Throwable $e ) {
        $capturada = $e->getMessage();
    }
    check( 'excecao capturada sem derrubar o site', 'falha simulada na regra', $capturada );
} catch ( Throwable $e ) {
    $lancou = true;
}
check( 'nenhuma excecao escapou', false, $lancou );

// --- Cenario E: guarda ABSPATH impede execucao fora do WordPress ---
$stub = <<<'PHP'
define( 'ABSPATH', '/tmp/wp/' );
function add_action() {}
function add_filter() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://exemplo.test/'; }
include "%FILE%";
echo 'CARREGADO';
PHP;

$saida = shell_exec(
    'php -r ' . escapeshellarg( str_replace( '%FILE%', HDISCOUNT_PLUGIN_PATH . 'hdiscount.php', $stub ) ) . ' 2>&1'
);
$saida = (string) $saida;

check( 'arquivo principal carrega inteiro sem erro fatal', 1,
    ( false !== strpos( $saida, 'CARREGADO' ) ) ? 1 : 0 );
check( 'nenhum Fatal na carga', 1,
    ( false === strpos( $saida, 'Fatal' ) ) ? 1 : 0 );
check( 'nenhum Warning na carga', 1,
    ( false === strpos( $saida, 'Warning' ) ) ? 1 : 0 );
check( 'nenhum Notice na carga', 1,
    ( false === strpos( $saida, 'Notice' ) ) ? 1 : 0 );

if ( false !== strpos( $saida, 'Fatal' ) || false !== strpos( $saida, 'Warning' ) ) {
    printf( "      saida: %s\n", trim( $saida ) );
}

// --- Cenario E2: fora do WordPress, o arquivo nao faz nada ---
$fora = shell_exec( 'php -r ' . escapeshellarg( 'include "' . HDISCOUNT_PLUGIN_PATH . 'hdiscount.php"; echo "CARREGADO";' ) . ' 2>&1' );
check( 'fora do WordPress: guarda ABSPATH bloqueia', '', trim( (string) $fora ) );

// --- Cenario F: uninstall nao executa nada se acessado direto ---
$saida2 = shell_exec( 'php ' . escapeshellarg( HDISCOUNT_PLUGIN_PATH . 'uninstall.php' ) . ' 2>&1' );
check( 'uninstall.php acessado direto nao executa nada', '', trim( (string) $saida2 ) );

echo "\n[2] Versionamento\n";

$cabecalho = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'hdiscount.php' );
$fonteVers = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-version.php' );

preg_match( "/^ \* Version:\s+(\S+)/m", $cabecalho, $m );
$versaoHeader = isset( $m[1] ) ? $m[1] : '';

check( 'versao existe no cabecalho', 1, '' !== $versaoHeader ? 1 : 0 );

// A constante do arquivo principal e a fonte da verdade (o painel usa ela).
if ( defined( 'HDISCOUNT_VERSION' ) ) {
    check( 'cabecalho bate com a constante', $versaoHeader, HDISCOUNT_VERSION );
} else {
    check( 'constante HDISCOUNT_VERSION definida', 1, 0 );
}

$versaoClasse = defined( 'HDISCOUNT_VERSION' ) ? HDISCOUNT_VERSION : '';

// A classe Version nao pode ter uma copia propria do numero.
check( 'classe Version nao duplica a versao', 0,
    preg_match( "/const VERSION = '/", $fonteVers ) ? 1 : 0 );
check( 'classe Version le a constante', 1,
    substr_count( $fonteVers, 'HDISCOUNT_VERSION' ) > 0 ? 1 : 0 );

// A data de release tem que ser uma data valida.
preg_match( "/const RELEASED = '([^']+)'/", $fonteVers, $m3 );
$data = isset( $m3[1] ) ? $m3[1] : '';
check( 'data de release valida', 1, (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ? 1 : 0 );

// Formato semantico de versao.
check( 'versao no formato x.y.z', 1,
    preg_match( '/^\d+\.\d+\.\d+$/', $versaoClasse ) ? 1 : 0 );

// O readme precisa mencionar a versao atual.
$readme = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'readme.txt' );
check( 'readme tem a versao no Stable tag', 1,
    ( false !== strpos( $readme, 'Stable tag: ' . $versaoClasse ) ) ? 1 : 0 );
check( 'readme tem entrada de changelog', 1,
    ( false !== strpos( $readme, '= ' . $versaoClasse . ' =' ) ) ? 1 : 0 );

echo "\n[3] Metodos chamados precisam existir\n";

// Uma chamada a metodo inexistente quebra o site em producao (erro fatal),
// e a suite nao dispara o hook 'init' que registraria o CPT.
$srcAdmin = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-admin.php' );

foreach ( array( 'menu_icon', 'register_cpt', 'render_about_page', 'register_about_page' ) as $metodo ) {
    check( "HDiscount_Admin::$metodo existe", 1,
        preg_match( '/function\s+' . preg_quote( $metodo, '/' ) . '\s*\(/', $srcAdmin ) ? 1 : 0 );
}

// Nenhuma chamada a self::/metodo que a classe nao define.
$chamadas = array();
if ( preg_match_all( '/(?:self|static|HDiscount_Admin)::([a-z_]+)\s*\(/', $srcAdmin, $m ) ) {
    foreach ( array_unique( $m[1] ) as $chamado ) {
        if ( ! preg_match( '/function\s+' . preg_quote( $chamado, '/' ) . '\s*\(/', $srcAdmin ) ) {
            $chamadas[] = $chamado;
        }
    }
}
check( 'nenhuma chamada a metodo inexistente', 0, count( $chamadas ) );
if ( $chamadas ) {
    printf( "      >> %s\n", implode( ', ', $chamadas ) );
}

// O menu do CPT precisa estar definido.
check( 'menu_icon do CPT vem do metodo', 1,
    false !== strpos( $srcAdmin, "'menu_icon'          => self::menu_icon()" ) ? 1 : 0 );

// A ativacao tambem precisa de um icone valido.
$srcBoot = file_get_contents( HDISCOUNT_PLUGIN_PATH . 'hdiscount.php' );
check( 'ativacao tem reserva de icone', 1,
    false !== strpos( $srcBoot, "'dashicons-tag'" ) ? 1 : 0 );
check( 'ativacao nao usa self:: fora da classe', 0,
    preg_match( '/self::menu_icon/', $srcBoot ) ? 1 : 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
if ( 0 === $fails ) {
    echo "RESULTADO: todos os $total testes passaram\n";
} else {
    echo "RESULTADO: $fails de $total testes FALHARAM\n";
}
exit( 0 === $fails ? 0 : 1 );