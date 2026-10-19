<?php
/**
 * Desinstalação do plugin - limpa dados
 */
// Acesso direto ao arquivo e bloqueado: so o WordPress pode caregar.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove Custom Post Type posts
$posts = get_posts( array(
    'post_type'      => 'hdiscount_rule',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

foreach ( $posts as $post_id ) {
    wp_delete_post( $post_id, true ); // true = force delete (skip trash)
}

// Remove opcoes gravadas pelo plugin
delete_option( 'hdiscount_version' );

// Remove os transients do verificador de atualizacao
delete_transient( 'hdiscount_update_check' );
delete_transient( 'hdiscount_update_check_branch' );

// Limpa o cache das regras em memoria
wp_cache_delete( 'hdiscount_active_rules', 'hdiscount' );