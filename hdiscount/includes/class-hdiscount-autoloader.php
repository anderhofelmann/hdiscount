<?php
/**
 * Autoloader simples compatível com PHP 7.0+
 * Carrega classes seguindo padrão PSR-4 simplificado
 */
class HDiscount_Autoloader {

    /**
     * Prefixo do namespace
     */
    const PREFIX = 'HDiscount_';

    /**
     * Diretório base das classes
     */
    const BASE_DIR = HDISCOUNT_PLUGIN_PATH . 'includes/';

    /**
     * Registra o autoloader
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'load_class' ) );
    }

    /**
     * Carrega a classe solicitada
     *
     * @param string $class_name Nome da classe
     * @return void
     */
    public static function load_class( $class_name ) {
        // Verifica se a classe pertence ao nosso namespace
        if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
            return;
        }

        // Remove o prefixo e converte para caminho de arquivo
        // HDiscount_Admin -> class-hdiscount-admin.php
        $relative_class = substr( $class_name, strlen( self::PREFIX ) );
        $file_name      = 'class-hdiscount-' . str_replace( '_', '-', strtolower( $relative_class ) ) . '.php';
        $file_path      = self::BASE_DIR . $file_name;

        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}