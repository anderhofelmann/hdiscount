<?php
/**
 * Handlers AJAX para busca de produtos no admin.
 */
// Acesso direto ao arquivo e bloqueado: so o WordPress pode caregar.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HDiscount_AJAX {

    public function __construct() {
        add_action( 'wp_ajax_hdiscount_search_products', array( $this, 'search_products' ) );
    }

    /**
     * Busca produtos para autocomplete.
     *
     * Protecoes aplicadas, nesta ordem:
     *  1. acesso apenas a quem logou;
     *  2. nonce valido ( impede replay e CSRF );
     *  3. permissao de editar a regra ( impede que um assinante, por exemplo,
     *     enumerate o catalogo mesmo tendo acesso ao nonce).
     */
    public function search_products() {
        check_ajax_referer( 'hdiscount_search_products', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Sem permissao para buscar produtos.', 'hdiscount' ), 403 );
        }

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

        if ( strlen( $term ) < 2 ) {
            wp_send_json_error( __( 'Digite pelo menos 2 caracteres.', 'hdiscount' ), 400 );
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