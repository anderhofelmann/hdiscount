<?php
/**
 * Desinstalação do plugin - limpa dados
 */
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

// Remove opções/transients relacionados
delete_option( 'hdiscount_version' );
wp_cache_flush();