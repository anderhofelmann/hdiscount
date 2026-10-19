<?php
/**
 * Handlers AJAX para busca de produtos no admin
 */
class HDiscount_AJAX {

    public function __construct() {
        add_action( 'wp_ajax_hdiscount_search_products', array( $this, 'search_products' ) );
    }

    /**
     * Busca produtos para autocomplete
     */
    public function search_products() {
        check_ajax_referer( 'hdiscount_search_products', 'nonce' );

        $term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

        if ( strlen( $term ) < 2 ) {
            wp_send_json_error( 'Termo muito curto' );
        }

        $args = array(
            'post_type'      => array( 'product', 'product_variation' ),
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $term,
            'fields'         => 'ids',
        );

        $query = new WP_Query( $args );
        $results = array();

        foreach ( $query->posts as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) continue;

            $parent_id = $product->get_parent_id();

            // Evita repetir o mesmo item quando ha varias linhas parecidas.
            $key = ( $parent_id ? 'v' : 'p' ) . $product_id;
            if ( isset( $results[ $key ] ) ) continue;

            $parent_name = '';
            $parent_type = '';
            if ( $parent_id ) {
                $parent = wc_get_product( $parent_id );
                if ( $parent ) {
                    $parent_name = $parent->get_name();
                    $parent_type = $parent->get_type();
                }
            }

            // Para variacao devolvemos o ID da VARIACAO, nao do pai:
            // quem decide se a regra se estende ao produto inteiro e a opcao
            // "Aplicar a todas as variacoes" na tela de edicao.
            $results[ $key ] = array(
                'id'          => $product_id,
                'name'        => $product->get_name(),
                'type'        => $product->get_type(),
                'parent_id'   => $parent_id,
                'parent_name' => $parent_name,
                'parent_type' => $parent_type,
                'sku'         => $product->get_sku(),
            );
        }

        wp_send_json_success( array_values( $results ) );
    }
}