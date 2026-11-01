<?php
/**
 * Plugin Name:       HDiscount
 * Plugin URI:        https://github.com/anderhofelmann/hdiscount
 * Description:       Sistema de regras de desconto por quantidade (packs) para WooCommerce. Permite criar promoções do tipo "Leve X por R$ Y" com controle de período, produtos, variações e limites de quantidade.
 * Version:           1.6.1
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Tested up to:      6.4
 * WC requires at least: 3.0
 * WC tested up to:   8.0
 * Author:            Anderson Höfelmann
 * Author URI:        https://github.com/anderhofelmann
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Icon URI:          https://raw.githubusercontent.com/anderhofelmann/hdiscount/main/hdiscount/assets/icon.svg
 * Text Domain:       hdiscount
 * Domain Path:       /languages
 *
 * HDiscount é um plugin para WooCommerce que permite ao lojista criar regras de desconto
 * baseadas em quantidade (packs). Exemplos de uso:
 * - "Leve 3 por R$ 100,00" (preço fixo do pack)
 * - "Leve 4, pague 3" (quantidade grátis)
 * - Desconto progressivo por volume
 *
 * Cada regra pode ser configurada com:
 * - Produtos aplicáveis (simples ou variáveis)
 * - Período de vigência (data/hora início e fim)
 * - Quantidade mínima e máxima para o pack
 * - Se aplica a variações iguais ou diferentes
 * - Preço especial do pack ou desconto percentual
 *
 * Desenvolvido por Anderson Höfelmann.
 */

// Evita acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Versão do plugin
 */
define( 'HDISCOUNT_VERSION', '1.6.1' );

/**
 * Caminho do plugin no filesystem
 */
define( 'HDISCOUNT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL do plugin
 */
define( 'HDISCOUNT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Carrega as dependências do plugin
 */
require_once HDISCOUNT_PLUGIN_PATH . 'includes/class-hdiscount-autoloader.php';

/**
 * Inicializa o plugin quando o WooCommerce estiver ativo
 */
add_action( 'plugins_loaded', 'hdiscount_init', 10 );

/**
 * Função principal de inicialização
 *
 * Verifica se o WooCommerce está ativo antes de carregar o plugin.
 * Registra o autoloader e instancia as classes principais.
 *
 * @return void
 */
function hdiscount_init() {
    // Versão mínima do PHP. Precisa vir antes de qualquer outra checagem,
    // porque o resto do plugin usa sintaxe do PHP 7.0.
    if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
        add_action( 'admin_notices', 'hdiscount_php_version_notice' );
        return;
    }

    // WooCommerce precisa estar carregado.
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'hdiscount_missing_wc_notice' );
        return;
    }

    // Funções do WooCommerce usadas pelo plugin. Se alguma faltar,
    // aborta em vez de gerar erro fatal e derrubar o site inteiro.
    $required = array(
        'wc_get_product',
        'wc_price',
        'wc_format_decimal',
        'wc_get_price_decimals',
    );

    foreach ( $required as $function ) {
        if ( ! function_exists( $function ) ) {
            add_action( 'admin_notices', 'hdiscount_incompatible_wc_notice' );
            return;
        }
    }

    HDiscount_Autoloader::register();

    // Cada classe é carregada por conta própria: se uma falhar,
    // as outras continuam funcionando.
    $classes = array(
        'HDiscount_Admin',
        'HDiscount_Cart_Logic',
        'HDiscount_AJAX',
        'HDiscount_Version',
        'HDiscount_Updater',
    );

    foreach ( $classes as $class ) {
        if ( ! class_exists( $class ) ) {
            add_action( 'admin_notices', 'hdiscount_missing_class_notice' );
            continue;
        }

        try {
            new $class();
        } catch ( Throwable $e ) {
            // Nunca deixar a exceção subir e derrubar o site.
            if ( function_exists( 'error_log' ) ) {
                error_log( '[HDiscount] Falha ao iniciar ' . $class . ': ' . $e->getMessage() );
            }
            add_action( 'admin_notices', 'hdiscount_runtime_error_notice' );
        }
    }
}

/**
 * Aviso admin: WooCommerce não encontrado
 */
function hdiscount_missing_wc_notice() {
    echo '<div class="notice notice-error"><p><strong>HDiscount:</strong> O plugin precisa do WooCommerce ativo para funcionar. O HDiscount foi pausado e seu site segue normal.</p></div>';
}

/**
 * Aviso admin: versão do WooCommerce incompatível
 */
function hdiscount_incompatible_wc_notice() {
    echo '<div class="notice notice-error"><p><strong>HDiscount:</strong> Este plugin precisa de uma versão do WooCommerce mais recente. O HDiscount foi pausado para não afetar seu site. Atualize o WooCommerce e ative o plugin novamente.</p></div>';
}

/**
 * Aviso admin: classe não encontrada
 */
function hdiscount_missing_class_notice() {
    echo '<div class="notice notice-error"><p><strong>HDiscount:</strong> Não foi possível carregar um componente do plugin. Reinstale o HDiscount pelo painel de plugins. O restante do site não foi afetado.</p></div>';
}

/**
 * Aviso admin: erro em tempo de execução
 */
function hdiscount_runtime_error_notice() {
    echo '<div class="notice notice-error"><p><strong>HDiscount:</strong> O plugin encontrou um problema e foi pausado para proteger seu site. Verifique o registro de erros do servidor para mais detalhes.</p></div>';
}

/**
 * Aviso admin: PHP versão incompatível
 */
function hdiscount_php_version_notice() {
    echo '<div class="notice notice-error"><p><strong>HDiscount:</strong> Requer PHP 7.0 ou superior. Versão atual: ' . esc_html( PHP_VERSION ) . '</p></div>';
}

/**
 * Ativação do plugin
 * Registra CPT e limpa rewrite rules
 */
register_activation_hook( __FILE__, 'hdiscount_activate' );
function hdiscount_activate() {
    // Registra CPT manualmente na ativação (antes do init)
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
        'show_in_menu'       => 'woocommerce',
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => array( 'title' ),
        'show_in_rest'       => false,
        'exclude_from_search' => true,
        'menu_position'      => 56,
    );

    // O icone vem da classe do admin, que ja foi carregada na ativacao.
    $icone = class_exists( 'HDiscount_Admin' )
        ? HDiscount_Admin::menu_icon()
        : 'dashicons-tag';

    register_post_type( 'hdiscount_rule', array_merge( $args, array( 'menu_icon' => $icone ) ) );

    flush_rewrite_rules();
}

/**
 * Desativação do plugin
 * Limpa rewrite rules
 */
register_deactivation_hook( __FILE__, 'hdiscount_deactivate' );
function hdiscount_deactivate() {
    flush_rewrite_rules();
}