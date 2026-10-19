<?php
/**
 * Versao e informacoes do plugin.
 *
 * A versao fica em UM unico lugar (a constante abaixo). O cabecalho do
 * arquivo principal repete o mesmo numero, entao os dois precisam ser
 * atualizados juntos quando houver release.
 */
class HDiscount_Version {

    /**
     * Data da ultima release (YYYY-MM-DD).
     */
    const RELEASED = '2026-10-18';

    /**
     * Opcao onde fica a versao registrada na instalacao.
     */
    const OPTION = 'hdiscount_version';

    /**
     * Versao vigente.
     *
     * Usa a constante definida em hdiscount.php em vez de repetir o numero
     * aqui: duas copias do mesmo valor sao uma fonte classica de bug.
     *
     * @return string
     */
    public static function get_version() {
        return defined( 'HDISCOUNT_VERSION' ) ? HDISCOUNT_VERSION : '0.0.0';
    }

    /**
     * Registra/verifica a versao a cada carregamento do admin.
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'check_version' ) );
        add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
        add_action( 'admin_footer', array( __CLASS__, 'footer_text' ) );
    }

    /**
     * Detecta mudanca de versao e limpa o cache de regras.
     */
    public static function check_version() {
        if ( get_option( self::OPTION ) === self::get_version() ) {
            return;
        }

        // Regra nova ou atualizada: o cache pode estar desatualizado.
        wp_cache_delete( 'hdiscount_active_rules', 'hdiscount' );

        update_option( self::OPTION, self::get_version() );
    }

    /**
     * Mostra um aviso curto sobre a versao instalada.
     */
    public static function render_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        // Aparece so nas telas do proprio plugin.
        if ( ! $screen || false === strpos( (string) $screen->post_type, 'hdiscount_rule' ) ) {
            return;
        }

        $installed = get_option( self::OPTION, '' );

        if ( self::get_version() !== $installed ) {
            printf(
                '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
                esc_html__( 'HDiscount:', 'hdiscount' ),
                sprintf(
                    /* translators: 1: versao instalada, 2: versao do plugin */
                    esc_html__( 'Esta tela mostra a versao %2$s. Recarregue a pagina para atualizar de %1$s.', 'hdiscount' ),
                    esc_html( $installed ? $installed : '?' ),
                    esc_html( self::get_version() )
                )
            );
        }
    }

    /**
     * Marcador de versao exibido no rodape do painel.
     */
    public static function footer_text() {
        printf(
            '<span id="footer-hdiscount" style="float:left">%s</span>',
            sprintf(
                /* translators: %s: versao */
                esc_html__( 'HDiscount %s', 'hdiscount' ),
                esc_html( self::get_version() )
            )
        );
    }
}