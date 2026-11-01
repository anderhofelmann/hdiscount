<?php
/**
 * Classe Admin: registra CPT, meta boxes, colunas, salvamento
 */
// Acesso direto ao arquivo e bloqueado: so o WordPress pode caregar.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HDiscount_Admin {

    /**
     * Guarda em qual menu a aba "Sobre" foi registrada.
     *
     * Depende de o menu do tipo de post existir no momento do registro.
     * Serve para o atalho da lista de regras apontar para a URL certa.
     *
     * @var string
     */
    protected static $about_parent = 'edit.php?post_type=hdiscount_rule';


    /**
     * Slug do Custom Post Type
     */
    const CPT_SLUG = 'hdiscount_rule';

    /**
     * Dados do desenvolvedor, mostrados no cabecalho do painel e no guia.
     */
    const AUTHOR_NAME = 'Anderson Höfelmann';
    const AUTHOR_URL  = 'https://github.com/anderhofelmann';
    const REPO_URL    = 'https://github.com/anderhofelmann/hdiscount';

    /**
     * Construtor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

        // Botao de volta para a lista, no topo da edicao (acima do titulo), bem visivel.
        add_action( 'edit_form_top', array( $this, 'render_back_link' ) );

        // Cabecalho de marca (versao, atualizacao, desenvolvedor e guia) nas telas do plugin.
        add_action( 'in_admin_header', array( $this, 'render_brand_header' ) );
        add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );

        // Guia do plugin em modal: o botao fica no cabecalho e na linha do plugin.
        add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_link' ), 10, 2 );
        add_action( 'admin_footer', array( $this, 'render_about_modal' ) );

        add_action( 'save_post', array( $this, 'save_meta_box_data' ), 10, 2 );
        add_filter( 'manage_edit-' . self::CPT_SLUG . '_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_' . self::CPT_SLUG . '_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Versao atual do plugin, lida da constante do bootstrap.
     *
     * @return string
     */
    public static function get_version() {
        return defined( 'HDISCOUNT_VERSION' ) ? HDISCOUNT_VERSION : '0.0.0';
    }

    /**
     * Escolhe o icone de menu mais adequado a versao do WordPress.
     *
     * O dashicon de desconto (uma etiqueta com %) so existe a partir do
     * WordPress 5.3. Em versoes anteriores cairia um quadrado vazio, entao
     * mantemos a etiqueta simples como reserva.
     *
     * @return string
     */
    public static function menu_icon() {
        $versao = get_bloginfo( 'version' );

        if ( version_compare( $versao, '5.3', '>=' ) ) {
            return 'dashicons-discount';
        }

        return 'dashicons-tag';
    }

    /**
     * Registra o CPT "Regra de Desconto"
     */
    public function register_cpt() {
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
            'show_in_menu'       => 'woocommerce', // Aparece no menu WooCommerce
            'menu_icon'          => self::menu_icon(),
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => array( 'title' ),
            // Nao expor via REST: as regras contem dados comerciais
            // (precos de pack) e nao ha edicao via API.
            'show_in_rest'       => false,
            'exclude_from_search' => true,
            'menu_position'      => 56, // Após "Cupons" no menu WC
        );

        register_post_type( self::CPT_SLUG, $args );
    }

    /**
     * A tela atual e uma das telas do plugin (lista de regras ou edicao de uma regra)?
     *
     * @return bool
     */
    private function is_plugin_screen() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        return $screen && in_array( (string) $screen->id, array( 'edit-' . self::CPT_SLUG, self::CPT_SLUG ), true );
    }

    /**
     * Ha versao nova do plugin? Le o que o WordPress ja sabe (sem nenhuma consulta extra).
     *
     * @return array array( 'available' => bool, 'version' => string )
     */
    public static function update_status() {
        $out  = array( 'available' => false, 'version' => '' );
        $info = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_plugins' ) : false;
        $file = 'hdiscount/hdiscount.php';

        if ( is_object( $info ) && ! empty( $info->response[ $file ] ) ) {
            $novo = is_object( $info->response[ $file ] ) ? $info->response[ $file ] : (object) $info->response[ $file ];
            if ( ! empty( $novo->new_version ) && version_compare( (string) $novo->new_version, self::get_version(), '>' ) ) {
                $out = array( 'available' => true, 'version' => (string) $novo->new_version );
            }
        }

        return $out;
    }

    /**
     * Marca do plugin: etiqueta de preco dourada com o simbolo de desconto (SVG inline, sem requisicao).
     *
     * @param int $size Lado em pixels.
     * @return string
     */
    private static function brand_mark( $size = 44 ) {
        $size = (int) $size;

        return '<svg class="hdw-mark" viewBox="0 0 64 64" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">'
            . '<path fill="#b08d57" d="M6 6h30l22 22a6 6 0 0 1 0 8.5l-21.5 21.5a6 6 0 0 1-8.5 0L6 36z"/>'
            . '<circle cx="17" cy="17" r="4.5" fill="#1c1b1a"/>'
            . '<path d="M27 45 45 27" stroke="#1c1b1a" stroke-width="4.5" stroke-linecap="round" fill="none"/>'
            . '<circle cx="30" cy="30" r="4.5" fill="#1c1b1a"/><circle cx="42" cy="42" r="4.5" fill="#1c1b1a"/>'
            . '</svg>';
    }

    /**
     * Cabecalho de marca: nome, versao, situacao da atualizacao, desenvolvedor e atalhos.
     */
    public function render_brand_header() {
        if ( ! $this->is_plugin_screen() ) {
            return;
        }

        $versao = self::get_version();
        $update = self::update_status();
        ?>
        <div class="hdw-brand">
            <div class="hdw-brand__id">
                <?php echo self::brand_mark( 44 ); // SVG fixo, sem dados externos. ?>
                <div class="hdw-brand__name">
                    <span class="hdw-brand__title"><?php esc_html_e( 'HDiscount', 'hdiscount' ); ?></span>
                    <span class="hdw-brand__tag"><?php esc_html_e( 'Regras de desconto para a sua loja', 'hdiscount' ); ?></span>
                </div>
            </div>

            <div class="hdw-brand__meta">
                <span class="hdw-chip hdw-chip--version"><?php echo esc_html( sprintf( /* translators: %s: versao do plugin */ __( 'Versão %s', 'hdiscount' ), $versao ) ); ?></span>
                <?php if ( $update['available'] ) : ?>
                    <a class="hdw-chip hdw-chip--update" href="<?php echo esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ); ?>">
                        <?php echo esc_html( sprintf( /* translators: %s: nova versao */ __( 'Nova versão %s disponível', 'hdiscount' ), $update['version'] ) ); ?>
                    </a>
                <?php else : ?>
                    <span class="hdw-chip hdw-chip--ok"><?php esc_html_e( 'Atualizado', 'hdiscount' ); ?></span>
                <?php endif; ?>
                <span class="hdw-brand__by">
                    <?php
                    printf(
                        /* translators: %s: link com o nome do desenvolvedor */
                        esc_html__( 'Desenvolvido por %s', 'hdiscount' ),
                        '<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( self::AUTHOR_NAME ) . '</a>'
                    );
                    ?>
                </span>
            </div>

            <div class="hdw-brand__actions">
                <button type="button" class="hdw-btn" data-hdiscount-about="1"><?php esc_html_e( 'Guia e sobre', 'hdiscount' ); ?></button>
                <a class="hdw-btn hdw-btn--ghost" href="<?php echo esc_url( self::REPO_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub', 'hdiscount' ); ?></a>
            </div>
        </div>
        <?php
    }

    /**
     * Rodape do painel nas telas do plugin: versao e desenvolvedor.
     *
     * @param string $texto Texto original do rodape.
     * @return string
     */
    public function admin_footer_text( $texto ) {
        if ( ! $this->is_plugin_screen() ) {
            return $texto;
        }

        return sprintf(
            /* translators: 1: versao, 2: link com o nome do desenvolvedor */
            esc_html__( 'HDiscount %1$s · Desenvolvido por %2$s', 'hdiscount' ),
            esc_html( self::get_version() ),
            '<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( self::AUTHOR_NAME ) . '</a>'
        );
    }

    /**
     * Link "Sobre" na linha do plugin, na tela Plugins do WordPress.
     *
     * @param array  $links
     * @param string $plugin_file
     * @return array
     */
    public function add_plugin_row_link( $links, $plugin_file ) {
        if ( 'hdiscount/hdiscount.php' !== $plugin_file ) {
            return $links;
        }

        $links[] = sprintf(
            '<button type="button" class="hdiscount-about-trigger hdiscount-about-trigger--inline" data-hdiscount-about="1">%s</button>',
            esc_html__( 'Sobre', 'hdiscount' )
        );
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( self::REPO_URL ),
            esc_html__( 'GitHub', 'hdiscount' )
        );

        return $links;
    }


    /**
     * Conteudo do guia, exibido em modal.
     *
     * Fica no rodape do painel e so aparece quando o usuario clica no botao.
     */
    public function render_about_modal() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        // So nas telas onde existe um botao que abre o guia.
        if ( ! $screen ) {
            return;
        }

        $telas = array( 'plugins', 'edit-hdiscount_rule', 'hdiscount_rule' );
        if ( ! in_array( (string) $screen->id, $telas, true ) ) {
            return;
        }

        $versao = self::get_version();
        ?>
        <div class="hdiscount-modal" id="hdiscount-about-modal" role="dialog" aria-modal="true"
             aria-labelledby="hdiscount-about-title" hidden>
            <div class="hdiscount-modal__backdrop" data-hdiscount-about-close="1"></div>
            <div class="hdiscount-modal__box" role="document">
                <div class="hdiscount-modal__header">
                    <h2 id="hdiscount-about-title">
                        <?php echo self::brand_mark( 34 ); // SVG fixo, sem dados externos. ?>
                        <span class="hdw-modal-title"><?php esc_html_e( 'HDiscount', 'hdiscount' ); ?></span>
                        <span class="hdiscount-modal__version"><?php echo esc_html( 'v' . $versao ); ?></span>
                    </h2>
                    <button type="button" class="hdiscount-modal__close"
                            data-hdiscount-about-close="1" aria-label="<?php esc_attr_e( 'Fechar', 'hdiscount' ); ?>">&times;</button>
                </div>

                <div class="hdiscount-modal__body">
                <div class="hdiscount-about">

            <h1>
                <img class="hdiscount-about-logo"
                     src="<?php echo esc_url( HDISCOUNT_PLUGIN_URL . 'assets/icon.svg' ); ?>"
                     alt="" width="40" height="40" />
                <?php esc_html_e( 'HDiscount', 'hdiscount' ); ?>
                <span class="about-text"><?php esc_html_e( 'Regras de desconto por quantidade para WooCommerce', 'hdiscount' ); ?></span>
                <?php printf( '<span class="dashicons %s"></span> %s', esc_attr( self::menu_icon() ), esc_html( $versao ) ); ?>
            </h1>

            <p class="about-text">
                <?php esc_html_e( 'Crie promoções do tipo "leve 3 por R$ 100,00". O preço de cada produto continua normal, e o desconto aparece só no total do carrinho, como um cupom.', 'hdiscount' ); ?>
            </p>

            <hr>

            <div class="hdiscount-about-grid">

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( '1. Crie a regra', 'hdiscount' ); ?></h2>
                    <ol>
                        <li><?php esc_html_e( 'Vá em HDiscount e clique em "Adicionar Nova".', 'hdiscount' ); ?></li>
                        <li><?php esc_html_e( 'Dê um NOME à regra: é ele que o cliente vê no total, entre parênteses.', 'hdiscount' ); ?></li>
                        <li><?php esc_html_e( 'Escolha o tipo de desconto:', 'hdiscount' ); ?></li>
                    </ol>
                    <ul class="hdiscount-about-types">
                        <li><strong><?php esc_html_e( 'Preço fixo do pack', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( '3 unidades por um preço total combinado. Ex: "Leve 3 por R$ 100,00".', 'hdiscount' ); ?></li>
                        <li><strong><?php esc_html_e( 'Desconto percentual', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'X% de desconto a partir da quantidade mínima.', 'hdiscount' ); ?></li>
                        <li><strong><?php esc_html_e( 'Compre X leve Y', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'O cliente leva mais unidades pagando por menos. Ex: "Compre 4, pague 3".', 'hdiscount' ); ?></li>
                    </ul>
                </div>

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( '2. Escolha os produtos', 'hdiscount' ); ?></h2>
                    <p><?php esc_html_e( 'Busque por nome ou SKU. O produto pai vem primeiro na lista e as variações dele aparecem logo abaixo.', 'hdiscount' ); ?></p>
                    <p><strong><?php esc_html_e( 'Aplicar a todas as variações', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'marcado, escolher o produto já vale para todas as cores e tamanhos, inclusive os que forem criados depois.', 'hdiscount' ); ?></p>
                    <p><strong><?php esc_html_e( 'Misturar produtos no pack', 'hdiscount' ); ?></strong> &mdash; <?php esc_html_e( 'marcado, as unidades do pack podem vir de produtos diferentes. Com "Camiseta" e "Calça" na lista, comprar 1 + 2 fecha o pack de 3.', 'hdiscount' ); ?></p>
                </div>

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( '3. Ajuste o período', 'hdiscount' ); ?></h2>
                    <p><?php esc_html_e( 'Informe a data e hora de início e de fim. Sem data de fim, a regra vale por tempo indeterminado.', 'hdiscount' ); ?></p>
                    <p><?php esc_html_e( 'Para interromper a promoção antes da hora, basta colocar a regra como Inativa, sem mexer nas datas.', 'hdiscount' ); ?></p>
                </div>

                <div class="hdiscount-about-card">
                    <h2><?php esc_html_e( 'Quantidade máxima', 'hdiscount' ); ?></h2>
                    <p><?php esc_html_e( 'Use "Quantidade máxima" para limitar quantas unidades entram no pack.', 'hdiscount' ); ?></p>
                    <p><?php esc_html_e( 'Com mínimo 3 e máximo 3, comprando 4 unidades, 3 recebem o preço do pack e a quarta fica pelo preço normal.', 'hdiscount' ); ?></p>
                </div>

            </div>

            <hr>

            <h2><?php esc_html_e( 'Como fica no carrinho', 'hdiscount' ); ?></h2>
            <p><?php esc_html_e( 'Com "3 por R$ 100,00" e 5 unidades de R$ 35,00 (em cores diferentes):', 'hdiscount' ); ?></p>

            <table class="widefat striped hdiscount-about-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Unidades', 'hdiscount' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'hdiscount' ); ?></th>
                        <th><?php esc_html_e( 'Como fecha a conta', 'hdiscount' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>2</td><td>R$ 70,00</td><td><?php esc_html_e( 'abaixo do mínimo, sem promoção', 'hdiscount' ); ?></td></tr>
                    <tr><td>3</td><td>R$ 100,00</td><td><?php esc_html_e( '1 pack fechado', 'hdiscount' ); ?></td></tr>
                    <tr><td>4</td><td>R$ 135,00</td><td><?php esc_html_e( '1 pack + 1 avulsa a R$ 35,00', 'hdiscount' ); ?></td></tr>
                    <tr><td>6</td><td>R$ 200,00</td><td><?php esc_html_e( '2 packs fechados', 'hdiscount' ); ?></td></tr>
                </tbody>
            </table>

            <p class="description" style="margin-top:12px;">
                <?php esc_html_e( 'No total do carrinho o desconto aparece em uma linha só, somando todos os produtos que fecharam pack.', 'hdiscount' ); ?>
            </p>

            <hr>

            <h2><?php esc_html_e( 'Dicas', 'hdiscount' ); ?></h2>
            <ul class="hdiscount-about-tips">
                <li><?php esc_html_e( 'Use produtos com o mesmo preço quando ativar "Misturar produtos", para o desconto ser sempre igual.', 'hdiscount' ); ?></li>
                <li><?php esc_html_e( 'O painel mostra um exemplo de cálculo enquanto você preenche a regra. Se algo estiver faltando, ele avisa.', 'hdiscount' ); ?></li>
                <li><?php esc_html_e( 'Várias regras podem valer ao mesmo tempo. Cada uma soma no total separadamente.', 'hdiscount' ); ?></li>
                <li><?php esc_html_e( 'Desconfie de um pack cujo preço seja MAIOR que o total dos produtos: nesse caso a regra simplesmente não se aplica.', 'hdiscount' ); ?></li>
            </ul>

            <div class="hdw-dev">
                <div class="hdw-dev__avatar" aria-hidden="true">AH</div>
                <div class="hdw-dev__info">
                    <p class="hdw-dev__label"><?php esc_html_e( 'Desenvolvedor', 'hdiscount' ); ?></p>
                    <p class="hdw-dev__name"><?php echo esc_html( self::AUTHOR_NAME ); ?></p>
                    <p class="hdw-dev__links">
                        <a href="<?php echo esc_url( self::AUTHOR_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Perfil no GitHub', 'hdiscount' ); ?></a>
                        <a href="<?php echo esc_url( self::REPO_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Repositório', 'hdiscount' ); ?></a>
                        <a href="<?php echo esc_url( self::REPO_URL . '/issues' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Relatar um problema', 'hdiscount' ); ?></a>
                        <a href="<?php echo esc_url( self::REPO_URL . '/releases' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Novidades de cada versão', 'hdiscount' ); ?></a>
                    </p>
                </div>
                <dl class="hdw-dev__facts">
                    <div><dt><?php esc_html_e( 'Versão', 'hdiscount' ); ?></dt><dd><?php echo esc_html( $versao ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Licença', 'hdiscount' ); ?></dt><dd><?php esc_html_e( 'GPL v2 ou posterior', 'hdiscount' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Requer', 'hdiscount' ); ?></dt><dd><?php esc_html_e( 'WordPress 5.0 · PHP 7.0 · WooCommerce 3.0', 'hdiscount' ); ?></dd></div>
                </dl>
            </div>

                </div>
                </div>

                <div class="hdiscount-modal__footer">
                    <a href="https://github.com/anderhofelmann/hdiscount" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'Documentação no GitHub', 'hdiscount' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Botao para voltar a lista de regras.
     *
     * Fica no topo da tela de edicao, acima do titulo, onde o olhar passa primeiro. Antes era um
     * link pequeno dentro da caixa "Publicar", facil de nao ver e colado ao link da lixeira.
     *
     * @return void
     */
    public function render_back_link() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( $screen && false === strpos( (string) $screen->post_type, self::CPT_SLUG ) ) {
            return;
        }

        $url = add_query_arg(
            array( 'post_type' => self::CPT_SLUG ),
            admin_url( 'edit.php' )
        );

        printf(
            '<p class="hdw-back"><a class="hdw-back__btn" href="%s"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>%s</a></p>',
            esc_url( $url ),
            esc_html__( 'Voltar para as Regras de Descontos', 'hdiscount' )
        );
    }

    /**
     * Caixa "Excluir regra": a lixeira fica longe do botao Publicar, para ninguem clicar sem querer.
     * O link e o mesmo do WordPress (com nonce); o JavaScript pede confirmacao antes.
     *
     * @param WP_Post $post Regra em edicao.
     */
    public function render_trash_meta_box( $post ) {
        $url = function_exists( 'get_delete_post_link' ) && $post ? get_delete_post_link( $post->ID ) : '';

        if ( ! $url ) {
            echo '<p class="hdw-trash__text">' . esc_html__( 'Você não tem permissão para excluir esta regra.', 'hdiscount' ) . '</p>';
            return;
        }

        echo '<p class="hdw-trash__text">' . esc_html__( 'Move esta regra para a lixeira: ela deixa de valer nos carrinhos. Dá para restaurá-la depois.', 'hdiscount' ) . '</p>';
        printf(
            '<p><a class="submitdelete hdw-trash__btn" href="%s">%s</a></p>',
            esc_url( $url ),
            esc_html__( 'Mover para a lixeira', 'hdiscount' )
        );
    }

    /**
     * Adiciona meta boxes na tela de edição da regra
     */
    public function add_meta_boxes() {
        add_meta_box(
            'hdiscount_rule_config',
            __( 'Configuração da Regra de Desconto', 'hdiscount' ),
            array( $this, 'render_meta_box' ),
            self::CPT_SLUG,
            'normal',
            'high'
        );

        add_meta_box(
            'hdiscount_rule_period',
            __( 'Período de Vigência', 'hdiscount' ),
            array( $this, 'render_period_meta_box' ),
            self::CPT_SLUG,
            'side',
            'high'
        );

        add_meta_box(
            'hdiscount_rule_products',
            __( 'Produtos Aplicáveis', 'hdiscount' ),
            array( $this, 'render_products_meta_box' ),
            self::CPT_SLUG,
            'normal',
            'default'
        );

        // Regra nova (ainda nao salva) nao tem o que excluir.
        global $post;
        if ( ! ( $post && 'auto-draft' === $post->post_status ) ) {
            add_meta_box(
                'hdiscount_rule_trash',
                __( 'Excluir regra', 'hdiscount' ),
                array( $this, 'render_trash_meta_box' ),
                self::CPT_SLUG,
                'side',
                'low'
            );
        }
    }

    /**
     * Renderiza meta box principal: tipo de desconto, quantidades, preço
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'hdiscount_save_meta', 'hdiscount_meta_nonce' );

        // Recupera valores salvos
        $discount_type       = get_post_meta( $post->ID, '_hdiscount_type', true );
        $min_qty             = get_post_meta( $post->ID, '_hdiscount_min_qty', true );
        $max_qty             = get_post_meta( $post->ID, '_hdiscount_max_qty', true );
        $pack_price          = get_post_meta( $post->ID, '_hdiscount_pack_price', true );
        $discount_percent    = get_post_meta( $post->ID, '_hdiscount_percent', true );
        $apply_same_variation = get_post_meta( $post->ID, '_hdiscount_same_variation', true );
        $limit_to_max        = get_post_meta( $post->ID, '_hdiscount_limit_to_max', true );
        $mix_products        = get_post_meta( $post->ID, '_hdiscount_mix_products', true );
        $mix_products        = '' === $mix_products ? 'no' : $mix_products;

        // Defaults
        $discount_type        = $discount_type ?: 'pack_price';
        $min_qty              = $min_qty ?: 1;
        $max_qty              = $max_qty ?: 0; // 0 = sem limite
        $apply_same_variation = $apply_same_variation ?: 'yes';
        $limit_to_max         = $limit_to_max ?: 'no';
        ?>
        <table class="form-table" style="width: 100%;">
            <!-- TIPO DE DESCONTO -->
            <tr>
                <th scope="row" style="width: 200px;">
                    <label for="hdiscount_type"><?php _e( 'Tipo de Desconto', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <select name="hdiscount_type" id="hdiscount_type" style="width: 300px;">
                        <option value="pack_price" <?php selected( $discount_type, 'pack_price' ); ?>>
                            <?php _e( 'Preço Fixo do Pack (ex: 3 por R$ 100,00)', 'hdiscount' ); ?>
                        </option>
                        <option value="percent" <?php selected( $discount_type, 'percent' ); ?>>
                            <?php _e( 'Desconto Percentual (ex: 20% off na quantidade)', 'hdiscount' ); ?>
                        </option>
                        <option value="buy_x_get_y" <?php selected( $discount_type, 'buy_x_get_y' ); ?>>
                            <?php _e( 'Compre X Leve Y (ex: Leve 4, Pague 3)', 'hdiscount' ); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e( '<strong>Preço Fixo do Pack:</strong> Define um valor total para X unidades (ex: 3 unidades por R$ 100,00).<br>
                        <strong>Desconto Percentual:</strong> Aplica % de desconto nas unidades que atingirem a quantidade mínima.<br>
                        <strong>Compre X Leve Y:</strong> Cliente leva quantidade maior pagando apenas a quantidade menor.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- QUANTIDADE MÍNIMA -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_min_qty"><?php _e( 'Quantidade Mínima do Pack', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_min_qty" id="hdiscount_min_qty" value="<?php echo esc_attr( $min_qty ); ?>" min="1" step="1" style="width: 100px;" required />
                    <p class="description">
                        <?php _e( 'Quantidade mínima de unidades que o cliente deve comprar para ativar a regra. Ex: se "3", a promoção só vale comprando 3 ou mais.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- QUANTIDADE MÁXIMA -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_max_qty"><?php _e( 'Quantidade Máxima do Pack (0 = sem limite)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_max_qty" id="hdiscount_max_qty" value="<?php echo esc_attr( $max_qty ); ?>" min="0" step="1" style="width: 100px;" />
                    <p class="description">
                        <?php _e( 'Limite máximo de unidades que entram na promoção. Ex: se mín=3 e máx=3, comprando 4 unidades, apenas 3 entram no preço promocional e a 4ª volta ao preço normal. Deixe 0 para aplicar a todas as unidades acima do mínimo.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- APLICAR APENAS ATÉ O MÁXIMO -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_limit_to_max"><?php _e( 'Limitar Desconto à Quantidade Máxima?', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="hdiscount_limit_to_max" id="hdiscount_limit_to_max" value="yes" <?php checked( $limit_to_max, 'yes' ); ?> />
                        <?php _e( 'Sim, aplicar desconto apenas até a quantidade máxima definida acima', 'hdiscount' ); ?>
                    </label>
                    <p class="description">
                        <?php _e( 'Se marcado: o desconto para na quantidade máxima. Com mín=3 e máx=3, ao comprar 4 itens, apenas 3 recebem o preço do pack e a 4ª fica pelo preço normal.<br>
                        Se desmarcado: a regra se repete. Com mín=3 e máx=3, ao comprar 7 itens, fecham-se 2 packs (6 itens) e o 7º fica pelo preço normal.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- PREÇO DO PACK (visível apenas para tipo pack_price) -->
            <tr class="hdiscount-field-pack_price" style="<?php echo 'pack_price' === $discount_type ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="hdiscount_pack_price"><?php _e( 'Preço Total do Pack (R$)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_pack_price" id="hdiscount_pack_price" value="<?php echo esc_attr( $pack_price ); ?>" step="0.01" style="width: 150px;" />
                    <p class="description">
                        <?php _e( 'Valor TOTAL que o cliente pagará pelas unidades do pack. Ex: para "3 por R$ 100,00", digite 100. O preço unitário efetivo será calculado automaticamente (100/3 = 33,33 cada).', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- DESCONTO PERCENTUAL (visível apenas para tipo percent) -->
            <tr class="hdiscount-field-percent" style="<?php echo 'percent' === $discount_type ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="hdiscount_percent"><?php _e( 'Desconto (%)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="number" name="hdiscount_percent" id="hdiscount_percent" value="<?php echo esc_attr( $discount_percent ); ?>" step="0.01" style="width: 100px;" />
                    <p class="description">
                        <?php _e( 'Porcentagem de desconto aplicada a cada unidade que atingir a quantidade mínima. Ex: 20% desconto em cada unidade a partir da 3ª.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- COMPRE X LEVE Y (visível apenas para tipo buy_x_get_y) -->
            <tr class="hdiscount-field-buy_x_get_y" style="<?php echo 'buy_x_get_y' === $discount_type ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="hdiscount_buy_qty"><?php _e( 'Comprar (X)', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <?php
                    // Defaults: precisao ser sempre validos, mesmo com o campo oculto.
                    // Um input invalido e invisivel trava o envio do formulario no navegador.
                    $buy_qty = (int) get_post_meta( $post->ID, '_hdiscount_buy_qty', true );
                    $buy_qty = $buy_qty > 0 ? $buy_qty : max( 2, $min_qty );

                    $get_qty = (int) get_post_meta( $post->ID, '_hdiscount_get_qty', true );
                    $get_qty = $get_qty > 0 ? $get_qty : max( 1, $buy_qty - 1 );
                    ?>
                    <label for="hdiscount_buy_qty" class="screen-reader-text"><?php _e( 'Quantidade comprada', 'hdiscount' ); ?></label>
                    <input type="number" name="hdiscount_buy_qty" id="hdiscount_buy_qty" value="<?php echo esc_attr( $buy_qty ); ?>" step="1" style="width: 80px;" />

                    <span style="margin: 0 10px;"><?php _e( 'pague', 'hdiscount' ); ?></span>

                    <label for="hdiscount_get_qty" class="screen-reader-text"><?php _e( 'Quantidade paga', 'hdiscount' ); ?></label>
                    <input type="number" name="hdiscount_get_qty" id="hdiscount_get_qty" value="<?php echo esc_attr( $get_qty ); ?>" step="1" style="width: 80px;" />
                    <p class="description">
                        <?php _e( 'Quantas unidades o cliente leva pagando por um grupo menor. Ex: Comprar 4 e pagar 3 = o cliente leva 4 itens e paga o preco de 3. Este campo precisa ser MENOR que o campo "Comprar".', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- VARIAÇÕES IGUAIS OU DIFERENTES -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_same_variation"><?php _e( 'Aplicar a Variações', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="radio" name="hdiscount_same_variation" value="yes" <?php checked( $apply_same_variation, 'yes' ); ?> />
                        <?php _e( 'Apenas variações IDÊNTICAS (mesma variação)', 'hdiscount' ); ?>
                    </label><br>
                    <label>
                        <input type="radio" name="hdiscount_same_variation" value="no" <?php checked( $apply_same_variation, 'no' ); ?> />
                        <?php _e( 'Quaisquer variações do mesmo produto (misturadas)', 'hdiscount' ); ?>
                    </label>
                    <p class="description">
                        <?php _e( '<strong>Apenas variações idênticas:</strong> Cliente deve comprar 3 da MESMA variação (ex: 3 camisetas vermelhas P).<br>
                        <strong>Quaisquer variações:</strong> Cliente pode misturar (ex: 1 vermelha P + 1 azul M + 1 preta G = 3 no pack).<br>
                        <strong>Para produtos simples:</strong> esta opção não afeta o funcionamento.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <!-- MISTURAR PRODUTOS NO PACK -->
            <tr>
                <th scope="row">
                    <label for="hdiscount_mix_products"><?php _e( 'Misturar Produtos no Pack', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="hdiscount_mix_products" id="hdiscount_mix_products" value="yes" <?php checked( $mix_products, 'yes' ); ?> />
                        <?php _e( 'Sim, as unidades do pack podem vir de produtos diferentes', 'hdiscount' ); ?>
                    </label>
                    <p class="description">
                        <?php _e( '<strong>Desmarcado (padrão):</strong> o pack é montado com unidades de UM produto só.<br>
                        <strong>Marcado:</strong> as unidades podem somar entre todos os produtos da lista. Ex: com "Camiseta" e "Calça" na regra, comprar 1 camiseta + 2 calças fecha o pack de 3.<br>
                        <em>Só faz sentido com mais de um produto na lista.</em><br>
                        <strong>Recomendação:</strong> use produtos com o mesmo preço, assim o desconto do pack é sempre igual.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <div id="hdiscount-preview-box" class="hdiscount-preview-box">
            <p class="hdiscount-preview-box__title"><?php _e( 'Como o cliente vai ver:', 'hdiscount' ); ?></p>
            <p id="hdiscount-preview-error" class="hdiscount-preview-box__error" style="display:none;"></p>
            <p id="hdiscount-badge-preview" class="hdiscount-preview-box__text"></p>
            <ul id="hdiscount-examples" class="hdiscount-preview-box__examples"></ul>
            <p class="hdiscount-preview-box__note"><?php _e( 'Voce pode publicar a regra mesmo com campos faltando: ela simplesmente nao sera aplicada enquanto estiver incompleta.', 'hdiscount' ); ?></p>
        </div>

        <script type="text/javascript">
        jQuery(function($){
            function toggleFields(){
                var type = $('#hdiscount_type').val();
                $('.hdiscount-field-pack_price').toggle('pack_price' === type);
                $('.hdiscount-field-percent').toggle('percent' === type);
                $('.hdiscount-field-buy_x_get_y').toggle('buy_x_get_y' === type);
            }
            $('#hdiscount_type').on('change', toggleFields);
            toggleFields();
        });
        </script>
        <?php
    }

    /**
     * Renderiza meta box: Período de Vigência
     */
    public function render_period_meta_box( $post ) {
        $start_date = get_post_meta( $post->ID, '_hdiscount_start_date', true );
        $end_date   = get_post_meta( $post->ID, '_hdiscount_end_date', true );
        $status     = get_post_meta( $post->ID, '_hdiscount_status', true );
        $status     = $status ?: 'active';

        // Formata para input datetime-local
        $start_fmt = $start_date ? date( 'Y-m-d\TH:i', strtotime( $start_date ) ) : '';
        $end_fmt   = $end_date ? date( 'Y-m-d\TH:i', strtotime( $end_date ) ) : '';
        ?>
        <table class="form-table" style="width: 100%;">
            <tr>
                <th scope="row" style="width: 130px;">
                    <label for="hdiscount_status"><?php _e( 'Status', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <select name="hdiscount_status" id="hdiscount_status" style="width: 200px;">
                        <option value="active" <?php selected( $status, 'active' ); ?>><?php _e( 'Ativa', 'hdiscount' ); ?></option>
                        <option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php _e( 'Inativa', 'hdiscount' ); ?></option>
                        <option value="scheduled" <?php selected( $status, 'scheduled' ); ?>><?php _e( 'Agendada (futura)', 'hdiscount' ); ?></option>
                    </select>
                    <p class="description">
                        <?php _e( '<strong>Ativa:</strong> Vigente agora (respeita datas se preenchidas).<br>
                        <strong>Inativa:</strong> Desligada, não aplica em carrinhos.<br>
                        <strong>Agendada:</strong> Ficará ativa automaticamente na data de início.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="hdiscount_start_date"><?php _e( 'Início da Vigência', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="datetime-local" name="hdiscount_start_date" id="hdiscount_start_date" value="<?php echo esc_attr( $start_fmt ); ?>" style="width: 220px;" />
                    <p class="description">
                        <?php _e( 'Data e hora em que a regra passa a valer. Deixe vazio para iniciar imediatamente. Formato: AAAA-MM-DDTHH:MM (ex: 2024-12-25T00:00).', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="hdiscount_end_date"><?php _e( 'Fim da Vigência', 'hdiscount' ); ?></label>
                </th>
                <td>
                    <input type="datetime-local" name="hdiscount_end_date" id="hdiscount_end_date" value="<?php echo esc_attr( $end_fmt ); ?>" style="width: 220px;" />
                    <p class="description">
                        <?php _e( 'Data e hora em que a regra expira. Deixe vazio para não expirar. Após esta data/hora, a regra não será mais aplicada.', 'hdiscount' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderiza meta box: Seleção de Produtos
     */
    public function render_products_meta_box( $post ) {
        $product_ids = get_post_meta( $post->ID, '_hdiscount_product_ids', true );

        // O valor e gravado como CSV ("10,20,30"). Tratar a string inteira
        // como array faria intval() devolver so o primeiro ID.
        $product_ids = $product_ids
            ? array_values( array_filter( array_map( 'intval', array_map( 'trim', explode( ',', (string) $product_ids ) ) ) ) )
            : array();

        // Padrao: sim. Selecionar o produto variavel ja vale para as variacoes.
        $extend = get_post_meta( $post->ID, '_hdiscount_extend_variations', true );
        $extend = '' === $extend ? 'yes' : $extend;

        // Dados para exibicao, distinguindo produto simples, variavel e variacao.
        $items = array();
        foreach ( $product_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) { continue; }

            $parent_id = $product->get_parent_id();

            if ( $parent_id ) {
                // Eh uma variacao: descobre o nome do produto pai.
                $parent    = wc_get_product( $parent_id );
                $items[] = array(
                    'id'        => $pid,
                    'name'      => $product->get_name(),
                    'type'      => 'variation',
                    'parent'    => $parent ? $parent->get_name() : '',
                    'parent_id' => $parent_id,
                    'sku'       => $product->get_sku(),
                );
            } else {
                $count = 0;
                if ( 'variable' === $product->get_type() ) {
                    $children = wc_get_products( array(
                        'post_parent' => $pid,
                        'post_type'   => 'product_variation',
                        'fields'      => 'ids',
                        'posts_per_page' => -1,
                    ) );
                    $count = is_array( $children ) ? count( $children ) : 0;
                }
                $items[] = array(
                    'id'        => $pid,
                    'name'      => $product->get_name(),
                    'type'      => $product->get_type(),
                    'parent'    => '',
                    'parent_id' => 0,
                    'sku'       => $product->get_sku(),
                    'qtd_var'   => $count,
                );
            }
        }
        // Organiza a lista: cada produto pai vem primeiro e as proprias
        // variacoes aparecem logo abaixo, recuadas.
        //
        // A agrupacao e feita pelo "dono" da secao em duas passadas, para
        // funcionar mesmo quando a variacao foi adicionada antes do pai.
        $sections = array();
        $order    = array();

        foreach ( $items as $item ) {
            $is_variation = ( 'variation' === $item['type'] && ! empty( $item['parent_id'] ) );

            $owner = $is_variation ? $item['parent_id'] : $item['id'];

            if ( ! isset( $sections[ $owner ] ) ) {
                $sections[ $owner ] = array( 'parent' => null, 'children' => array() );
                $order[]            = $owner;
            }

            if ( $is_variation ) {
                $sections[ $owner ]['children'][] = $item;
            } else {
                $sections[ $owner ]['parent'] = $item;
            }
        }

        $ordered = array();

        foreach ( $order as $owner ) {
            $section = $sections[ $owner ];

            // Variacao cujo pai nao esta na lista entra como item principal.
            if ( null === $section['parent'] ) {
                foreach ( $section['children'] as $orfa ) {
                    $ordered[] = $orfa;
                }
                continue;
            }

            $ordered[] = $section['parent'];

            foreach ( $section['children'] as $filho ) {
                $filho['is_child'] = true;
                $ordered[]         = $filho;
            }
        }

        // Se a extensao esta desligada, um produto variavel sozinho nao cobre nada.
$sem_extensao = ( 'yes' !== $extend );
$pais_variaveis = array();

if ( $sem_extensao ) {
    foreach ( $product_ids as $pid ) {
        $p = wc_get_product( $pid );
        if ( $p && ! $p->get_parent_id() && 'variable' === $p->get_type() ) {
            $pais_variaveis[] = $p->get_name();
        }
    }
}
// Quando a mistura esta ligada, o preco do pack e fixo. Com produtos de
// preços diferentes, o quanto o cliente economiza varia conforme quais itens
// entram no pack, entao vale avisar.
$mistura = ( 'yes' === get_post_meta( $post->ID, '_hdiscount_mix_products', true ) );
$precos_produto = array();

if ( $mistura ) {
    foreach ( $product_ids as $pid ) {
        $p = wc_get_product( $pid );
        if ( ! $p || $p->get_parent_id() ) {
            continue;
        }
        $preco = (float) $p->get_price();
        if ( $preco > 0 ) {
            $precos_produto[ $pid ] = array( 'nome' => $p->get_name(), 'preco' => $preco );
        }
    }
}

$mesmo_preco = ( count( $precos_produto ) > 0 && 1 === count( array_unique( wp_list_pluck( $precos_produto, 'preco' ) ) ) );
?>
        <div id="hdiscount-products-wrapper">
            <?php if ( $mistura && $precos_produto && $mesmo_preco ) : ?>
                <div class="notice notice-info inline" style="margin: 0 0 12px;">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: quantidade de produtos, 2: preco */
                            esc_html__( 'Os %1$d produtos da lista tem o mesmo preco (%2$s), entao o desconto do pack sera sempre o mesmo.', 'hdiscount' ),
                            count( $precos_produto ),
                            wc_price( reset( $precos_produto )['preco'] )
                        );
                        ?>
                    </p>
                </div>
            <?php elseif ( $mistura && $precos_produto && ! $mesmo_preco ) : ?>
                <div class="notice notice-warning inline" style="margin: 0 0 12px;">
                    <p>
                        <strong><?php esc_html_e( 'Atencao:', 'hdiscount' ); ?></strong>
                        <?php esc_html_e( 'os produtos da lista tem preços diferentes e a regra mistura as unidades em um pack so. Como o preco do pack e fixo, a economia do cliente muda conforme quais produtos entram no pack.', 'hdiscount' ); ?>
                    </p>
                    <ul style="margin: 6px 0 0 18px;">
                        <?php foreach ( $precos_produto as $item ) : ?>
                            <li><?php echo esc_html( $item['nome'] . ': ' . wc_price( $item['preco'] ) ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin: 8px 0 0;"><?php esc_html_e( 'Se voce quer um desconto sempre igual, use produtos com o mesmo preco na lista.', 'hdiscount' ); ?></p>
                </div>
            <?php endif; ?>
            <?php if ( $sem_extensao && $pais_variaveis ) : ?>
                <div class="notice notice-warning inline" style="margin: 0 0 12px;">
                    <p>
                        <strong><?php esc_html_e( 'Atencao:', 'hdiscount' ); ?></strong>
                        <?php
                        printf(
                            /* translators: %s: lista de produtos variaveis */
                            esc_html__( 'A extensao esta desligada, entao produto variavel nao cobre as variacoes automaticamente. Adicione as variacoes desejadas uma a uma, ou ligue a extensao acima. Produtois: %s', 'hdiscount' ),
                            esc_html( implode( ', ', $pais_variaveis ) )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <p class="description" style="margin-bottom: 12px;">
                <?php _e( 'Busque e adicione os produtos que participan desta regra. Funciona com produtos simples e variaveis.', 'hdiscount' ); ?>
            </p>

            <div style="margin-bottom: 10px;">
                <input type="text" id="hdiscount-product-search" autocomplete="off"
                       placeholder="<?php _e( 'Buscar produto, variacao ou SKU...', 'hdiscount' ); ?>"
                       style="width: 100%; max-width: 420px; padding: 8px;" />
            </div>

            <p class="hdiscount-option">
                <label>
                    <input type="checkbox" name="hdiscount_extend_variations" id="hdiscount_extend_variations"
                           value="yes" <?php checked( $extend, 'yes' ); ?> />
                    <?php _e( 'Aplicar a todas as variacoes dos produtos variaveis selecionados', 'hdiscount' ); ?>
                </label>
                <span class="hdiscount-option__hint">
                    <?php _e( 'Marcado: ao escolher "Camiseta" (produto variavel), a regra vale para TODAS as variacoes, inclusive as que forem criadas depois.<br>Desmarque para escolher variacao por variacao - nesse caso, apenas as variacoes listadas abaixo recebem o desconto.', 'hdiscount' ); ?>
                </span>
            </p>

            <ul id="hdiscount-selected-products" class="hdiscount-product-list">
                <?php foreach ( $ordered as $item ) : ?>
                    <li class="hdiscount-product-list__item<?php echo ! empty( $item['is_child'] ) ? ' hdiscount-product-list__item--child' : ''; ?>"
                        data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
                        data-product-type="<?php echo esc_attr( $item['type'] ); ?>"
                        data-parent-id="<?php echo esc_attr( isset( $item['parent_id'] ) ? $item['parent_id'] : 0 ); ?>"
                        data-name="<?php echo esc_attr( $item['name'] ); ?>"
                        data-parent-name="<?php echo esc_attr( isset( $item['parent'] ) ? $item['parent'] : '' ); ?>"
                        data-sku="<?php echo esc_attr( isset( $item['sku'] ) ? $item['sku'] : '' ); ?>">
                        <span class="hdiscount-product-list__info">
                            <strong><?php echo esc_html( $item['name'] ); ?></strong>
                            <?php if ( 'variation' === $item['type'] && $item['parent'] ) : ?>
                                <span class="hdiscount-tag hdiscount-tag--muted">
                                    <?php
                                    printf(
                                        /* translators: %s: nome do produto pai */
                                        esc_html__( 'variacao de: %s', 'hdiscount' ),
                                        esc_html( $item['parent'] )
                                    );
                                    ?>
                                </span>
                            <?php elseif ( 'variable' === $item['type'] ) : ?>
                                <span class="hdiscount-tag hdiscount-tag--variable">
                                    <?php
                                    printf(
                                        /* translators: %d: quantidade de variacoes */
                                        esc_html( _n( '%d variacao', '%d variacoes', (int) $item['qtd_var'], 'hdiscount' ) ),
                                        (int) $item['qtd_var']
                                    );
                                    ?>
                                </span>
                            <?php else : ?>
                                <span class="hdiscount-tag"><?php _e( 'simples', 'hdiscount' ); ?></span>
                            <?php endif; ?>
                            <?php if ( $item['sku'] ) : ?>
                                <code class="hdiscount-product-list__sku"><?php echo esc_html( $item['sku'] ); ?></code>
                            <?php endif; ?>
                        </span>
                        <button type="button" class="hdiscount-product-list__remove"
                                title="<?php esc_attr_e( 'Remover', 'hdiscount' ); ?>">&times;</button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p id="hdiscount-products-empty" class="hdiscount-product-list__empty" <?php echo $items ? 'style="display:none;"' : ''; ?>>
                <?php _e( 'Nenhum produto selecionado. A regra nao sera aplicada enquanto a lista estiver vazia.', 'hdiscount' ); ?>
            </p>

            <input type="hidden" name="hdiscount_product_ids" id="hdiscount_product_ids"
                   value="<?php echo esc_attr( implode( ',', $product_ids ) ); ?>" />
        </div>

        <script type="text/javascript">
        jQuery(function($){
            var $search = $('#hdiscount-product-search');
            var $list   = $('#hdiscount-selected-products');
            var $input  = $('#hdiscount_product_ids');
            var timer;

            // Busca produtos via AJAX
            $search.on('keyup', function(){
                clearTimeout(timer);
                var term = $(this).val().trim();
                if (term.length < 2) return;

                timer = setTimeout(function(){
                    $.post(ajaxurl, {
                        action: 'hdiscount_search_products',
                        term: term,
                        nonce: '<?php echo wp_create_nonce( 'hdiscount_search_products' ); ?>'
                    }, function(response){
                        if (response.success && response.data.length) {
                            showSuggestions(response.data);
                        }
                    });
                }, 300);
            });

            var $suggestions = $('<div id="hdiscount-suggestions" style="position:absolute; background:white; border:1px solid #ddd; max-height:200px; overflow:auto; z-index:1000; display:none; width:400px;"></div>').insertAfter($search);

            function showSuggestions(products){
                $suggestions.empty().show();

                if (!products || !products.length) {
                    $suggestions.append(
                        $('<div style="padding:8px 12px; color:#646970;">')
                            .text(<?php echo wp_json_encode( __( "Nenhum produto encontrado.", 'hdiscount' ) ); ?>)
                    );
                    return;
                }

                products.forEach(function(p){
                    var inList = $list.find('[data-product-id="'+p.id+'"]').length > 0;

                    // Rotulo legivel, distinguindo produto de variacao.
                    var label = p.name;
                    if (p.type === 'variation' && p.parent_name) {
                        label += '  \u2014  ' + p.parent_name;
                    }

                    // Avisa que escolher a variacao inclui o produto inteiro.
                    var aviso = '';
                    if (p.type === 'variation' && p.parent_id && extensaoLigada()) {
                        aviso = <?php echo wp_json_encode( __( 'inclui todas as variacoes', 'hdiscount' ) ); ?>;
                    }

                    $suggestions.append(
                        $('<div class="hdiscount-suggestion">')
                            .toggleClass('already-added', inList)
                            .append($('<span class="hdiscount-suggestion__name">').text(label))
                            .append($('<span class="hdiscount-suggestion__type">').text(
                                p.type === 'variation' ? <?php echo wp_json_encode( __( "Variacao", 'hdiscount' ) ); ?>
                                    : (p.type === 'variable' ? <?php echo wp_json_encode( __( "Variavel", 'hdiscount' ) ); ?>
                                    : <?php echo wp_json_encode( __( "Simples", 'hdiscount' ) ); ?>)
                            ))
                            .append($('<span class="hdiscount-suggestion__sku">').text(p.sku || ''))
                            .append($('<span class="hdiscount-suggestion__hint">').text(aviso))
                            .data('product', p)
                            .on('click', function(){
                                addProduct(p);
                                $suggestions.hide();
                                $search.val('');
                            })
                    );
                });
            }

            // Mantem os itens selecionados para poder reordenar a lista.
            var selecionados = [];

            function carregarDoServidor(){
                var inicial = $list.find('li').map(function(){
                    return {
                        id: $(this).data('product-id'),
                        name: $(this).data('name'),
                        type: $(this).data('product-type'),
                        parent_name: $(this).data('parent-name'),
                        parent_id: $(this).data('parent-id') || 0,
                        sku: $(this).data('sku')
                    };
                }).get();
                selecionados = $.grep(inicial, function(p){ return p && p.id; });
            }

            /**
             * Desenha a lista agrupando: produto pai e, abaixo, as variacoes.
             */
            function renderList(){
                $list.empty();

                var secoes = {};
                var ordem  = [];

                selecionados.forEach(function(p){
                    var ehVariacao = (p.type === 'variation' && p.parent_id);
                    var dono = ehVariacao ? p.parent_id : p.id;

                    if (!secoes[dono]) {
                        secoes[dono] = { pai: null, filhas: [] };
                        ordem.push(dono);
                    }

                    if (ehVariacao) {
                        secoes[dono].filhas.push(p);
                    } else {
                        secoes[dono].pai = p;
                    }
                });

                var linha = function(p, ehFilha){
                    var $li = $('<li class="hdiscount-product-list__item"/>')
                        .addClass(ehFilha ? 'hdiscount-product-list__item--child' : '')
                        .attr('data-product-id', p.id)
                        .attr('data-product-type', p.type || '')
                        .attr('data-parent-id', p.parent_id || 0)
                        .attr('data-name', p.name)
                        .attr('data-parent-name', p.parent_name || '')
                        .attr('data-sku', p.sku || '');

                    var $info = $('<span class="hdiscount-product-list__info"/>')
                        .append($('<strong/>').text(p.name));

                    if (p.type === 'variation') {
                        $info.append($('<span class="hdiscount-tag hdiscount-tag--muted"/>').text(
                            <?php echo wp_json_encode( __( 'variacao', 'hdiscount' ) ); ?>
                        ));
                    } else if (p.type === 'variable') {
                        $info.append($('<span class="hdiscount-tag hdiscount-tag--variable"/>').text(
                            <?php echo wp_json_encode( __( 'variavel', 'hdiscount' ) ); ?>
                        ));
                    } else {
                        $info.append($('<span class="hdiscount-tag"/>').text(
                            <?php echo wp_json_encode( __( 'simples', 'hdiscount' ) ); ?>
                        ));
                    }

                    if (p.sku) {
                        $info.append($('<code class="hdiscount-product-list__sku"/>').text(p.sku));
                    }

                    $li.append($info);
                    $li.append(
                        $('<button type="button" class="hdiscount-product-list__remove"/>')
                            .attr('title', <?php echo wp_json_encode( __( "Remover", "hdiscount" ) ); ?>)
                            .attr('aria-label', <?php echo wp_json_encode( __( "Remover", "hdiscount" ) ); ?>)
                            .text('\u00d7')
                    );

                    return $li;
                };

                ordem.forEach(function(dono){
                    var sec = secoes[dono];
                    if (!sec.pai) {
                        // Variacao sem o pai na lista.
                        sec.filhas.forEach(function(f){ $list.append(linha(f, false)); });
                        return;
                    }
                    $list.append(linha(sec.pai, false));
                    sec.filhas.forEach(function(f){ $list.append(linha(f, true)); });
                });
            }

            /**
             * Descobre se a regra vale para o produto inteiro.
             */
            function extensaoLigada(){
                var $box = $('#hdiscount_extend_variations');
                if (!$box.length) return true;
                return $box.is(':checked');
            }

            function jaSelecionado(id){
                var alvo = String(id);
                var achou = false;
                selecionados.forEach(function(p){
                    if (String(p.id) === alvo) achou = true;
                });
                return achou;
            }

            function adicionarItem(dados){
                if (jaSelecionado(dados.id)) return;
                selecionados.push(dados);
            }

            function addProduct(product){
                // Com a extensao ligada, escolher uma variacao equivale a
                // escolher o produto pai: ele ja cobre todas as variacoes.
                // Sem isso, a lista ficava so com variacoes soltas e o
                // lojista nao achava o produto que pretendia usar.
                if (product.type === 'variation' && product.parent_id && extensaoLigada()) {
                    adicionarItem({
                        id: product.parent_id,
                        name: product.parent_name || product.name,
                        type: product.parent_type || 'variable',
                        parent_name: '',
                        parent_id: 0,
                        sku: ''
                    });
                } else {
                    adicionarItem({
                        id: product.id,
                        name: product.name,
                        type: product.type,
                        parent_name: product.parent_name || '',
                        parent_id: product.parent_id || 0,
                        sku: product.sku || ''
                    });
                }

                renderList();
                updateHiddenInput();
            }

            function updateHiddenInput(){
                var ids = [];
                $list.find('li').each(function(){
                    ids.push($(this).attr('data-product-id'));
                });
                $input.val(ids.join(','));

                // Mensagem de lista vazia.
                var has = ids.length > 0;
                $('#hdiscount-products-empty').toggle(!has);

                // Aviso visual quando ha produto sem preenchimento.
                $('#hdiscount-products-wrapper').toggleClass('hdiscount-has-products', has);
            }

            $list.on('click', '.hdiscount-product-list__remove', function(){
                var id = $(this).closest('li').data('product-id');
                selecionados = $.grep(selecionados, function(p){ return String(p.id) !== String(id); });
                renderList();
                updateHiddenInput();
            });

            $(document).on('click', function(e){
                if (!$(e.target).closest('#hdiscount-product-search, #hdiscount-suggestions').length) {
                    $suggestions.hide();
                }
            });

            // Carrega o que ja estava salvo e redesenha agrupado.
            carregarDoServidor();
            renderList();
            updateHiddenInput();

            // Teclado: Enter busca, Escape fecha as sugestoes.
            $search.on('keydown', function(e){
                if (e.key === 'Escape') { $suggestions.hide(); }
            });
        });
        </script>
        <style>
        #hdiscount-suggestions div:hover { background:#f0f0f1; }
        #hdiscount-suggestions .already-added { color:#999; text-decoration:line-through; }
        </style>
        <?php
    }

    /**
     * Salva os dados dos meta boxes
     */
    public function save_meta_box_data( $post_id, $post ) {
        // Verifica nonce
        if ( ! isset( $_POST['hdiscount_meta_nonce'] ) || ! wp_verify_nonce( $_POST['hdiscount_meta_nonce'], 'hdiscount_save_meta' ) ) {
            return;
        }

        // Verifica autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Verifica permissões
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Verifica se é nosso CPT
        if ( self::CPT_SLUG !== $post->post_type ) {
            return;
        }

        // Campos para salvar
        $fields = array(
            '_hdiscount_type'           => 'hdiscount_type',
            '_hdiscount_min_qty'        => 'hdiscount_min_qty',
            '_hdiscount_max_qty'        => 'hdiscount_max_qty',
            '_hdiscount_pack_price'     => 'hdiscount_pack_price',
            '_hdiscount_percent'        => 'hdiscount_percent',
            '_hdiscount_buy_qty'        => 'hdiscount_buy_qty',
            '_hdiscount_get_qty'        => 'hdiscount_get_qty',
            '_hdiscount_same_variation' => 'hdiscount_same_variation',
            '_hdiscount_limit_to_max'   => 'hdiscount_limit_to_max',
            '_hdiscount_extend_variations' => 'hdiscount_extend_variations',
            '_hdiscount_mix_products'       => 'hdiscount_mix_products',
            '_hdiscount_start_date'     => 'hdiscount_start_date',
            '_hdiscount_end_date'       => 'hdiscount_end_date',
            '_hdiscount_status'         => 'hdiscount_status',
            '_hdiscount_product_ids'    => 'hdiscount_product_ids',
        );

        foreach ( $fields as $meta_key => $post_key ) {

            // Um checkbox desmarcado nao chega no POST. Sem tratamento
            // explicito, a regra continuaria valendo como "marcada".
            if ( '_hdiscount_extend_variations' === $meta_key || '_hdiscount_mix_products' === $meta_key ) {
                update_post_meta(
                    $post_id,
                    $meta_key,
                    empty( $_POST[ $post_key ] ) ? 'no' : 'yes'
                );
                continue;
            }

            if ( ! isset( $_POST[ $post_key ] ) ) {
                continue;
            }

            $raw = wp_unslash( $_POST[ $post_key ] );

            switch ( $meta_key ) {
                case '_hdiscount_min_qty':
                case '_hdiscount_max_qty':
                case '_hdiscount_buy_qty':
                case '_hdiscount_get_qty':
                    $value = absint( $raw );
                    break;

                case '_hdiscount_pack_price':
                    // Preco nunca negativo (o carrinho tambem recusa valor <= 0).
                    $value = max( 0, (float) str_replace( ',', '.', $raw ) );
                    break;

                case '_hdiscount_percent':
                    // Percentual entre 0 e 100 (o carrinho tambem recusa o que estiver fora disso).
                    $value = min( 100, max( 0, (float) str_replace( ',', '.', $raw ) ) );
                    break;

                case '_hdiscount_product_ids':
                    // Aceita a lista separada por virgula e devolve CSV de IDs.
                    $parts = array_filter( array_map( 'trim', explode( ',', (string) $raw ) ), 'strlen' );
                    $ids   = array_values( array_unique( array_map( 'absint', $parts ) ) );
                    $ids   = array_filter( $ids );
                    $value = implode( ',', $ids );
                    break;

                case '_hdiscount_start_date':
                case '_hdiscount_end_date':
                    // datetime-local (2024-12-25T18:30) para formato MySQL.
                    $value = '';
                    if ( $raw ) {
                        $ts = strtotime( str_replace( 'T', ' ', (string) $raw ) );
                        if ( $ts ) {
                            $value = gmdate( 'Y-m-d H:i:s', $ts );
                        }
                    }
                    break;

                case '_hdiscount_type':
                case '_hdiscount_status':
                    // Valores fechados: ignora o que nao estiver na lista.
                    $allowed = array(
                        '_hdiscount_type'   => array( 'pack_price', 'percent', 'buy_x_get_y' ),
                        '_hdiscount_status' => array( 'active', 'inactive', 'scheduled' ),
                    );
                    $value = in_array( $raw, $allowed[ $meta_key ], true ) ? $raw : $allowed[ $meta_key ][0];
                    break;

                default:
                    $value = sanitize_text_field( $raw );
            }

            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    /**
     * Define colunas personalizadas na lista de regras
     */
    public function set_custom_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'title' === $key ) {
                $new_columns['hdiscount_type']      = __( 'Tipo', 'hdiscount' );
                $new_columns['hdiscount_products']  = __( 'Produtos', 'hdiscount' );
                $new_columns['hdiscount_qty']       = __( 'Qtd Min/Max', 'hdiscount' );
                $new_columns['hdiscount_period']    = __( 'Período', 'hdiscount' );
                $new_columns['hdiscount_status']    = __( 'Status', 'hdiscount' );
            }
        }
        return $new_columns;
    }

    /**
     * Resumo da regra em uma linha ("Leve 3 por R$ 100,00"), para a lista ser lida de relance.
     *
     * @param int    $post_id ID da regra.
     * @param string $type    Tipo da regra.
     * @return string Texto simples (o chamador escapa).
     */
    private function rule_summary( $post_id, $type ) {
        $min = (int) get_post_meta( $post_id, '_hdiscount_min_qty', true );

        if ( 'pack_price' === $type ) {
            $price = (float) get_post_meta( $post_id, '_hdiscount_pack_price', true );
            if ( $min > 0 && $price > 0 ) {
                /* translators: 1: quantidade, 2: preco do pack */
                return sprintf( __( 'Leve %1$d por %2$s', 'hdiscount' ), $min, html_entity_decode( strip_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' ) );
            }
        } elseif ( 'percent' === $type ) {
            $pct = (float) get_post_meta( $post_id, '_hdiscount_percent', true );
            if ( $min > 0 && $pct > 0 ) {
                /* translators: 1: percentual, 2: quantidade minima */
                return sprintf( __( '%1$s%% a partir de %2$d un.', 'hdiscount' ), rtrim( rtrim( number_format( $pct, 2, ',', '' ), '0' ), ',' ), $min );
            }
        } elseif ( 'buy_x_get_y' === $type ) {
            $buy = (int) get_post_meta( $post_id, '_hdiscount_buy_qty', true );
            $get = (int) get_post_meta( $post_id, '_hdiscount_get_qty', true );
            if ( $buy > 0 && $get > 0 ) {
                /* translators: 1: leva, 2: paga */
                return sprintf( __( 'Compre %1$d, pague %2$d', 'hdiscount' ), $buy, $get );
            }
        }

        return '';
    }

    /**
     * Renderiza conteúdo das colunas personalizadas
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'hdiscount_type':
                $type = get_post_meta( $post_id, '_hdiscount_type', true );
                $labels = array(
                    'pack_price'     => __( 'Preço Fixo do Pack', 'hdiscount' ),
                    'percent'        => __( 'Desconto %', 'hdiscount' ),
                    'buy_x_get_y'    => __( 'Compre X Leve Y', 'hdiscount' ),
                );
                echo '<span class="hdw-pill hdw-pill--type">' . ( isset( $labels[ $type ] ) ? esc_html( $labels[ $type ] ) : esc_html( $type ) ) . '</span>';

                $resumo = $this->rule_summary( $post_id, (string) $type );
                if ( '' !== $resumo ) {
                    echo '<span class="hdw-sub">' . esc_html( $resumo ) . '</span>';
                }
                break;

            case 'hdiscount_products':
                $ids = get_post_meta( $post_id, '_hdiscount_product_ids', true );
                $ids = $ids ? array_filter( array_map( 'intval', explode( ',', $ids ) ) ) : array();
                $count = count( $ids );

                if ( ! $count ) {
                    echo '<span class="hdw-warn">' . esc_html__( 'Nenhum', 'hdiscount' ) . '</span>';
                    break;
                }

                // Nomes curtos dos primeiros itens.
                $nomes = array();
                foreach ( array_slice( $ids, 0, 3 ) as $pid ) {
                    $p = wc_get_product( $pid );
                    if ( $p ) {
                        $nomes[] = $p->get_parent_id() ? $p->get_name() . ' (' . __( 'variacão', 'hdiscount' ) . ')' : $p->get_name();
                    }
                }

                echo '<strong class="hdw-count">' . esc_html( sprintf(
                    _n( '%d produto', '%d produtos', $count, 'hdiscount' ),
                    $count
                ) ) . '</strong>';

                if ( $nomes ) {
                    echo ' <span class="hdw-sub hdw-sub--inline">(' . esc_html( implode( ', ', $nomes ) ) . ( $count > 3 ? '…' : '' ) . ')</span>';
                }

                // Sinaliza a extensao para variacoes.
                $extend = get_post_meta( $post_id, '_hdiscount_extend_variations', true );
                $extend = '' === $extend ? 'yes' : $extend;

                if ( 'yes' === $extend ) {
                    echo '<br><span class="hdiscount-tag hdiscount-tag--variable">'
                        . esc_html__( '+ todas as variações', 'hdiscount' )
                        . '</span>';
                }
                break;

            case 'hdiscount_qty':
                $min = get_post_meta( $post_id, '_hdiscount_min_qty', true );
                $max = get_post_meta( $post_id, '_hdiscount_max_qty', true );
                $limit = get_post_meta( $post_id, '_hdiscount_limit_to_max', true );
                echo '<span class="hdw-qty">' . esc_html( $min );
                if ( $max > 0 ) {
                    echo ' – ' . esc_html( $max );
                    if ( 'yes' === $limit ) {
                        echo ' <span class="dashicons dashicons-lock hdw-lock" title="' . esc_attr__( 'Limitado ao máximo', 'hdiscount' ) . '"></span>';
                    }
                } else {
                    echo '+';
                }
                echo '</span>';
                break;

            case 'hdiscount_period':
                $start = get_post_meta( $post_id, '_hdiscount_start_date', true );
                $end   = get_post_meta( $post_id, '_hdiscount_end_date', true );
                if ( $start ) {
                    echo '<span class="hdw-period"><strong>' . esc_html__( 'Início:', 'hdiscount' ) . '</strong> ' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $start ) ) ) . '</span>';
                }
                if ( $end ) {
                    echo '<span class="hdw-period"><strong>' . esc_html__( 'Fim:', 'hdiscount' ) . '</strong> ' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $end ) ) ) . '</span>';
                }
                if ( ! $start && ! $end ) {
                    echo '<span class="hdw-sub hdw-sub--inline">' . esc_html__( 'Sem limite de tempo', 'hdiscount' ) . '</span>';
                }
                break;

            case 'hdiscount_status':
                $status = get_post_meta( $post_id, '_hdiscount_status', true );
                $status = $status ?: 'active';
                $labels = array(
                    'active'     => __( 'Ativa', 'hdiscount' ),
                    'inactive'   => __( 'Inativa', 'hdiscount' ),
                    'scheduled'  => __( 'Agendada', 'hdiscount' ),
                );
                if ( isset( $labels[ $status ] ) ) {
                    echo '<span class="hdw-pill hdw-pill--' . esc_attr( $status ) . '"><span class="hdw-dot"></span>' . esc_html( $labels[ $status ] ) . '</span>';
                } else {
                    echo '<span class="hdw-pill">' . esc_html( $status ) . '</span>';
                }
                break;
        }
    }

    /**
     * Enqueue assets no admin
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        // Telas do plugin e a lista de Plugins (onde fica o link "Sobre", que abre o guia em modal).
        if ( 'hdiscount_rule' !== $post_type && 'plugins.php' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'hdiscount-admin', HDISCOUNT_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), HDISCOUNT_VERSION, true );
        wp_enqueue_style( 'hdiscount-admin', HDISCOUNT_PLUGIN_URL . 'assets/admin.css', array(), HDISCOUNT_VERSION );
        wp_localize_script( 'hdiscount-admin', 'hdiscount_admin', array(
            'i18n_confirm_delete' => __( 'Mover esta regra para a lixeira? Ela deixa de valer nos carrinhos. Você poderá restaurá-la depois, na lixeira.', 'hdiscount' ),
            'i18n_invalid_dates'  => __( 'A data de fim deve ser posterior a data de inicio.', 'hdiscount' ),
            'i18n_max_lt_min'     => __( 'A quantidade maxima nao pode ser menor que a minima.', 'hdiscount' ),
            'i18n_sure'          => __( 'Tem certeza?', 'hdiscount' ),
            'i18n_min_invalid'   => __( 'A quantidade minima precisa ser 1 ou mais.', 'hdiscount' ),
            'i18n_pack_price_invalid' => __( 'Informe o preco total do pack (maior que zero).', 'hdiscount' ),
            'i18n_percent_invalid'    => __( 'O desconto precisa ser maior que 0 e no maximo 100%.', 'hdiscount' ),
            'i18n_buy_invalid'        => __( 'O campo "Comprar" precisa ser 2 ou mais.', 'hdiscount' ),
            'i18n_get_invalid'        => __( 'O campo "Pague" precisa ser maior que zero e MENOR que "Comprar".', 'hdiscount' ),
        ) );
    }
}