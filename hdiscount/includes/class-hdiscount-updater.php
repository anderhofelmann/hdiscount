<?php
/**
 * Atualizacao automatica a partir das releases do GitHub.
 *
 * O WordPress so sabe instalar ZIPs. Este classe consulta a API do GitHub,
 * compara com a versao instalada e, havendo versao nova, injeta o endereco
 * do arquivo na tela "Atualizar plugins", sem depender de biblioteca externa.
 *
 * Como funciona:
 *  1. filters_pre_set_site_transient_update_plugins: em toda verificacao,
 *     consulta a API do GitHub (com cache de 6 horas) e acrescenta o
 *     HDiscount em $transient->response quando ha versao nova;
 *  2. plugins_api: devolve os detalhes para a tela de "ver detalhes".
 *
 * A API do GitHub permite 60 requisicoes por hora sem autenticacao. Por isso
 * o resultado fica em cache e nunca e buscado duas vezes no mesmo request.
 */
// Acesso direto ao arquivo e bloqueado: so o WordPress pode caregar.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HDiscount_Updater {

    /**
     * Repositorio no GitHub, no formato "usuario/repositorio".
     */
    const REPO = 'anderhofelmann/hdiscount';

    /**
     * Chave do cache do resultado da consulta.
     */
    const CACHE_KEY = 'hdiscount_update_check';

    /**
     * Chave do cache que marca a consulta ja feita neste request.
     */
    const CACHE_BRANCH = 'hdiscount_update_check_branch';

    /**
     * Tempo do cache: 6 horas. A API do GitHub tem limite de requisicoes.
     */
    const CACHE_TTL = 21600;

    /**
     * Arquivo do plugin (caminho dentro do ZIP).
     */
    const PLUGIN_FILE = 'hdiscount/hdiscount.php';

    /**
     * Registra os hooks.
     */
    public function __construct() {
        self::init();
    }

    /**
     * Registra os hooks.
     */
    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
    }

    /**
     * Verifica se existe versao nova e alimenta o transiente do WordPress.
     *
     * @param object $transient Objeto update_plugins do WordPress.
     * @return object
     */
    public static function check_update( $transient ) {
        // Só Age em plugins e telas do painel.
        if ( ! is_object( $transient ) || ! isset( $transient->response ) ) {
            return $transient;
        }

        $release = self::get_release_info();

        // Sem acesso à API (sem rede, limite atingido): mantém como estava.
        if ( ! $release ) {
            return $transient;
        }

        $atual   = self::current_version();
        $nova    = isset( $release['version'] ) ? $release['version'] : '';

        if ( '' === $nova || version_compare( $atual, $nova, '>=' ) ) {
            return $transient;
        }

        // O pacote precisa ser https: um espelho em http permitiria alterar
        // o arquivo no caminho.
        if ( 0 !== stripos( (string) $release['package'], 'https://' ) ) {
            return $transient;
        }

        // O hash publicado precisa bater com o arquivo que o GitHub
        // informa. Divergencia significa arquivo trocado no caminho.
        if ( ! self::hash_matches_release( $release ) ) {
            return $transient;
        }

        $transient->response[ self::PLUGIN_FILE ] = self::build_update_object( $release );

        return $transient;
    }

    /**
     * Monta o objeto de atualizacao no formato esperado pelo WordPress.
     *
     * @param array $release
     * @return object
     */
    private static function build_update_object( $release ) {
        $atual = self::current_version();

        $obj = new stdClass();
        $obj->id          = 'hdiscount';
        $obj->slug        = 'hdiscount';
        $obj->plugin      = self::PLUGIN_FILE;
        $obj->new_version = $release['version'];
        $obj->package     = $release['package'];
        $obj->url         = $release['url'];
        $obj->icons       = array();
        $obj->requires    = '5.0';
        $obj->requires_php = '7.0';

        $obj->banners = array();
        $obj->banners['low']  = $release['url'];
        $obj->banners['high'] = $release['url'];

        $obj->sections = array(
            'changelog' => isset( $release['changelog'] ) ? $release['changelog'] : '',
        );

        // Melhorias e correções desta versão.
        $obj->upgrade_notice = sprintf(
            /* translators: 1: versao nova, 2: versao atual */
            __( 'HDiscount %1$s esta disponivel (voce usa a %2$s).', 'hdiscount' ),
            $release['version'],
            $atual
        );

        return $obj;
    }

    /**
     * Detalhes do plugin para a tela de informacoes.
     *
     * @param false|object|array $resultado
     * @param string             $action
     * @param object             $args
     * @return false|object|array
     */
    public static function plugin_info( $resultado, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $resultado;
        }

        if ( ! isset( $args->slug ) || 'hdiscount' !== $args->slug ) {
            return $resultado;
        }

        $release = self::get_release_info();

        if ( ! $release ) {
            return $resultado;
        }

        $obj = new stdClass();
        $obj->name          = 'HDiscount';
        $obj->slug          = 'hdiscount';
        $obj->version       = $release['version'];
        $obj->author        = 'Anderson Höfelmann';
        $obj->homepage      = 'https://github.com/' . self::REPO;
        $obj->requires      = '5.0';
        $obj->requires_php  = '7.0';
        $obj->tested        = '6.4';
        $obj->download_link = $release['package'];
        $obj->sections      = array(
            'description' => 'Regras de desconto por quantidade para WooCommerce: leve 3 por R$ 100,00 e afins.',
            'changelog'   => isset( $release['changelog'] ) ? $release['changelog'] : '',
        );

        return $obj;
    }

    /**
     * Consulta a API do GitHub, com cache.
     *
     * @return array|null
     */
    public static function get_release_info() {
        // Uma consulta por request, no maximo.
        if ( get_transient( self::CACHE_BRANCH ) ) {
            return get_transient( self::CACHE_KEY );
        }
        set_transient( self::CACHE_BRANCH, 1, HOUR_IN_SECONDS );

        $cache = get_transient( self::CACHE_KEY );

        if ( is_array( $cache ) ) {
            return $cache;
        }

        $release = self::fetch_latest_release();

        // Guarda por 6 horas. Mesmo sem resultado, para nao repetir a chamada.
        set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

        return $release;
    }

    /**
     * Busca a release mais recente na API do GitHub.
     *
     * @return array|null
     */
    private static function fetch_latest_release() {
        $url = sprintf( 'https://api.github.com/repos/%s/releases/latest', self::REPO );

        $resposta = wp_remote_get(
            $url,
            array(
                'timeout'    => 8,
                'user-agent' => 'HDiscount/' . self::current_version() . '; ' . home_url(),
                'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
            )
        );

        // Falha de rede ou limite de requisicoes: devolve null e tenta depois.
        if ( is_wp_error( $resposta ) || 200 !== (int) wp_remote_retrieve_response_code( $resposta ) ) {
            return null;
        }

        $corpo = json_decode( wp_remote_retrieve_body( $resposta ), true );

        if ( ! is_array( $corpo ) || empty( $corpo['tag_name'] ) ) {
            return null;
        }

        $pacote = '';
        foreach ( (array) $corpo['assets'] as $asset ) {
            if ( isset( $asset['name'] ) && 'hdiscount.zip' === $asset['name'] && isset( $asset['browser_download_url'] ) ) {
                $pacote = $asset['browser_download_url'];
                break;
            }
        }

        // Sem ZIP anexado, nao ha o que instalar.
        if ( '' === $pacote ) {
            return null;
        }

        return array(
            'version'   => self::normalize_version( $corpo['tag_name'] ),
            'package'   => $pacote,
            'url'       => isset( $corpo['html_url'] ) ? $corpo['html_url'] : '',
            'changelog' => isset( $corpo['body'] ) ? wp_kses_post( $corpo['body'] ) : '',
            // Hash publicado na propria release: protege contra arquivo
            // corrompido ou substituido no caminho.
            'sha256'    => self::extract_sha256( isset( $corpo['body'] ) ? $corpo['body'] : '' ),
        );
    }

    /**
     * Confere o hash do pacote com o publicado na release.
     *
     * Sem hash publicado, a atualizacao segue (repositorios antigos nao tem).
     * Com hash, ele precisa bater: caso contrario o update e recusado.
     *
     * @param array $release
     * @return bool
     */
    private static function hash_matches_release( $release ) {
        $esperado = isset( $release['sha256'] ) ? $release['sha256'] : '';

        // Release sem hash: nao ha como conferir, aceita.
        if ( '' === $esperado ) {
            return true;
        }

        $arquivo = download_url( $release['package'], 30 );

        if ( is_wp_error( $arquivo ) || ! $arquivo ) {
            return false;
        }

        $hash = hash_file( 'sha256', $arquivo );

        // Remove o arquivo temporario.
        if ( function_exists( 'unlink' ) ) {
            @unlink( $arquivo );
        }

        return hash_equals( $esperado, (string) $hash );
    }

    /**
     * Extrai o SHA-256 publicado no corpo da release.
     *
     * O autor publica no topo da release:
     *     sha256: 3f7a...  (ou "SHA256: 3f7a...")
     *
     * @param string $corpo
     * @return string Hexadecimal de 64 caracteres, ou string vazia.
     */
    public static function extract_sha256( $corpo ) {
        if ( ! is_string( $corpo ) || '' === $corpo ) {
            return '';
        }

        if ( preg_match( '/sha-?256\s*[:=]\s*`?([a-f0-9]{64})`?/i', $corpo, $m ) ) {
            return strtolower( $m[1] );
        }

        return '';
    }

    /**
     * Normaliza "v1.2.3" para "1.2.3".
     *
     * @param string $tag
     * @return string
     */
    public static function normalize_version( $tag ) {
        $tag = trim( (string) $tag );
        $tag = trim( $tag, '/' );
        return ltrim( $tag, 'vV' );
    }

    /**
     * Sem uso, mantido por compatibilidade.
     */
    private function _unused() {
        return ltrim( trim( (string) $tag ), 'vV/' );
    }

    /**
     * Versao instalada, lida do cabecalho do proprio arquivo.
     *
     * @return string
     */
    public static function current_version() {
        return defined( 'HDISCOUNT_VERSION' ) ? HDISCOUNT_VERSION : '0.0.0';
    }

    /**
     * Avisa no painel quando ha versao nova.
     */
    public static function render_notice() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        // Só nas telas de plugins, para nao poluir o resto do painel.
        if ( ! $screen || false === strpos( (string) $screen->id, 'plugins' ) ) {
            return;
        }

        $release = self::get_release_info();

        if ( ! $release || version_compare( self::current_version(), $release['version'], '>=' ) ) {
            return;
        }

        $url = add_query_arg(
            array(
                'action' => 'upgrade-plugin',
                'plugin' => self::PLUGIN_FILE,
            ),
            admin_url( 'update.php' )
        );

        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <a href="%s" class="button button-primary">%s</a></p></div>',
            esc_html__( 'HDiscount:', 'hdiscount' ),
            sprintf(
                /* translators: %s: versao disponivel */
                esc_html__( 'a versao %s esta disponivel.', 'hdiscount' ),
                esc_html( $release['version'] )
            ),
            esc_url( $url ),
            esc_html__( 'Atualizar agora', 'hdiscount' )
        );
    }
}