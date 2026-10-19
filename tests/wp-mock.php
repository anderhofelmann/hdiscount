<?php
/**
 * Simulador minimo de WordPress + WooCommerce para testar o HDiscount
 * fora de um ambiente WordPress real.
 */

// ============ CONSTANTES WP ============
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DOING_AUTOSAVE', false );

// ============ FUNCOES WP BASICAS ============
$GLOBALS['wp_actions']    = array();
$GLOBALS['wp_filters']    = array();
$GLOBALS['post_meta']     = array();
$GLOBALS['wc_products']   = array();

function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
    $GLOBALS['wp_actions'][$hook][$priority][] = $cb;
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
    $GLOBALS['wp_filters'][$hook][$priority][] = $cb;
}
function do_action( $hook ) {
    $args = array_slice( func_get_args(), 1 );
    if ( empty( $GLOBALS['wp_actions'][$hook] ) ) { return; }
    $by_prio = $GLOBALS['wp_actions'][$hook];
    ksort( $by_prio );
    foreach ( $by_prio as $cbs ) {
        foreach ( $cbs as $cb ) { call_user_func_array( $cb, $args ); }
    }
}
function apply_filters( $hook, $value ) {
    $args = array_slice( func_get_args(), 2 );
    if ( empty( $GLOBALS['wp_filters'][$hook] ) ) { return $value; }
    $by_prio = $GLOBALS['wp_filters'][$hook];
    ksort( $by_prio );
    foreach ( $by_prio as $cbs ) {
        foreach ( $cbs as $cb ) { $value = call_user_func_array( $cb, array_merge( array( $value ), $args ) ); }
    }
    return $value;
}
function add_meta_box() {}
function selected( $a, $b = true, $echo = true ) {
    $r = ( (string) $a === (string) $b ) ? ' selected' : '';
    if ( $echo ) { echo $r; }
    return $r;
}
function checked( $a, $b = true, $echo = true ) {
    $r = ( (string) $a === (string) $b ) ? ' checked' : '';
    if ( $echo ) { echo $r; }
    return $r;
}
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
function esc_html_e( $t, $d = '' ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = '' ) { echo esc_attr( $t ); }
function esc_html_x( $t, $c = '', $d = '' ) { return esc_html( $t ); }
function esc_attr_x( $t, $c = '', $d = '' ) { return esc_attr( $t ); }
function _e( $t, $d = '' ) { echo $t; }
function esc_html__x( $t, $c = '', $d = '' ) { return esc_html( $t ); }
function esc_attr__( $t, $d = '' ) { return esc_attr( $t ); }
function __( $t, $d = '' ) { return $t; }
function _x( $t, $c = '', $d = '' ) { return $t; }
function _n( $s, $p, $n, $d = '' ) { return $n == 1 ? $s : $p; }
function sanitize_text_field( $t ) { return strip_tags( (string) $t ); }
function wp_nonce_field() {}
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( $v ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_verify_nonce() { return true; }
function current_user_can() { return true; }
function wp_create_nonce() { return 'test'; }
function check_ajax_referer() {}
function wp_send_json_success( $d ) {}
function wp_send_json_error( $d ) {}
function wp_enqueue_script() {}
function wp_enqueue_style() {}
function wp_localize_script() {}
function wp_cache_get( $k, $g = '' ) { return false; }
function wp_cache_set( $k, $v, $g = '', $e = 0 ) { return true; }
function wp_cache_delete( $k, $g = '' ) { return true; }
function register_post_type() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function flush_rewrite_rules() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://exemplo.test/wp-content/plugins/hdiscount/'; }
function is_admin() { return false; }
function did_action( $h ) { return $GLOBALS['did_counts'][$h] ?? 0; }
function current_time( $t = 'mysql' ) { return gmdate( 'Y-m-d H:i:s' ); }
function date_i18n( $f, $ts = null ) { return date( $f, $ts ?: time() ); }
function wc_price( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); }
function get_post_meta( $id, $key, $single = false ) {
    return $GLOBALS['post_meta'][$id][$key][0] ?? '';
}
function update_post_meta( $id, $key, $value ) {
    $GLOBALS['post_meta'][$id][$key] = array( $value );
    return true;
}
function wc_get_product( $id ) {
    if ( ! isset( $GLOBALS['wc_products'][$id] ) ) { return false; }
    // WooCommerce devolve uma nova instancia a cada chamada.
    return clone $GLOBALS['wc_products'][$id];
}

// ============ CLASSE WP_Query (fake) ============
class WP_Query {
    public $posts = array();
    public function __construct( $args = array() ) {
        $type = $args['post_type'] ?? '';
        if ( 'hdiscount_rule' !== $type ) { return; }
        foreach ( $GLOBALS['rule_posts'] ?? array() as $pid ) {
            $this->posts[] = $pid; // 'fields' => 'ids' retorna apenas IDs
        }
    }
}
class WP_Post {
    public $ID = 0;
    public $post_title = '';
    public $post_type = '';
}

// ============ CLASSE WooCommerce PRODUCT (fake) ============
class WC_Product {
    private $id, $price, $name, $type, $status, $parent, $sku;
    public function __construct( $id, $price, $name = 'P', $type = 'simple', $parent = 0 ) {
        $this->id = $id; $this->price = $price; $this->name = $name;
        $this->type = $type; $this->status = 'publish';
        $this->parent = $parent; $this->sku = 'SKU' . $id;
    }
    public function get_id() { return $this->id; }
    public function get_price() { return $this->price; }
    public function get_name() { return $this->name; }
    public function get_type() { return $this->type; }
    public function get_status() { return $this->status; }
    public function get_parent_id() { return $this->parent; }
    public function get_sku() { return $this->sku; }
    public function set_price( $p ) { $this->price = (float) $p; }
    public function is_type( $t ) { return $t === $this->type; }
}

function wc_get_price_decimals() { return 2; }
function add_query_arg( $args, $url ) {
    return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function esc_url( $u ) { return $u; }
function admin_url( $p = '' ) { return 'https://exemplo.test/wp-admin/' . ltrim( $p, '/' ); }
function get_option( $k, $d = false ) { return $GLOBALS['wp_options'][$k] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['wp_options'][$k] = $v; return true; }
function get_current_screen() { return $GLOBALS['current_screen'] ?? null; }
class WP_Screen { public $post_type = 'hdiscount_rule'; }
function wp_doing_ajax() { return false; }
function get_the_title( $id ) { return $GLOBALS["rule_titles"][ $id ] ?? "Regra $id"; }
function get_post_type( $id ) { return 'hdiscount_rule'; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function wc_format_decimal( $v, $dp = false ) { return $dp ? number_format( (float) $v, $dp, '.', '' ) : (string) (float) $v; }
function esc_js( $t ) { return addslashes( $t ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wc_get_products( $args = array() ) {
    $parent = $args['post_parent'] ?? 0;
    if ( ! $parent ) { return array(); }
    $out = array();
    foreach ( $GLOBALS['wc_products'] as $id => $p ) {
        if ( $p->get_parent_id() === $parent ) { $out[] = $id; }
    }
    return $out;
}

// ============ CLASSE WC_Cart (fake) ============
class WC_Cart {
    public $cart_contents = array();
    private $seq = 0;
    public function add( $product_id, $variation_id, $qty ) {
        $k = 'item' . ( ++$this->seq );
        $this->cart_contents[$k] = array(
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $qty,
            'line_total'   => $GLOBALS['wc_products'][$variation_id ?: $product_id]->get_price() * $qty,
            'data'         => $GLOBALS['wc_products'][$variation_id ?: $product_id],
        );
        return $k;
    }
    public $fees = array();
    public function add_fee( $name, $amount, $taxable = true ) {
        $this->fees[] = array( 'name' => $name, 'amount' => (float) $amount, 'taxable' => $taxable );
    }
    public function remove_all_fees() { $this->fees = array(); }
    public function get_fees() { return $this->fees; }
    public function get_cart() { return $this->cart_contents; }
    public function get_cart_item( $k ) { return $this->cart_contents[$k] ?? null; }
    public function dump() { return $this->cart_contents; }
}