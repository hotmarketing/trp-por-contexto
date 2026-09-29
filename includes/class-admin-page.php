<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TRP_CO_Admin_Page {

    const PAGE_SLUG = 'trp-context-overrides';

    /**
     * Hook de la página: WordPress antepone "admin_page_" a las páginas sin menú visible.
     */
    const PAGE_HOOK = 'admin_page_trp-context-overrides';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_filter( 'trp_settings_tabs', array( $this, 'add_trp_tab' ) );
        add_action( 'admin_page_access_denied', array( $this, 'redirect_legacy_url' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( TRP_CO_PLUGIN_FILE ), array( $this, 'add_plugin_action_link' ) );
        add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ) );
        add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_trp_co_search_pages', array( $this, 'ajax_search_pages' ) );
        add_action( 'admin_init', array( $this, 'handle_form_actions' ) );
    }

    /**
     * URL de la página, con parámetros opcionales.
     */
    public static function page_url( $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
    }

    /**
     * Registra la página sin entrada propia en el menú: se abre desde la pestaña
     * "Context Overrides" de TranslatePress (Ajustes → TranslatePress), igual que las
     * páginas de los complementos de TranslatePress ('TRPHidden' es su padre oculto).
     */
    public function add_menu_page() {
        add_submenu_page(
            'TRPHidden',
            'Context Overrides',
            'Context Overrides',
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Agrega la pestaña a la barra de TranslatePress.
     */
    public function add_trp_tab( $tabs ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $tabs;
        }

        $tabs[] = array(
            'name' => 'Context Overrides',
            'url'  => self::page_url(),
            'page' => self::PAGE_SLUG,
        );

        return $tabs;
    }

    /**
     * Hasta la 1.2.x la página vivía en Ajustes (options-general.php). Redirige esa URL
     * para no romper marcadores.
     */
    public function redirect_legacy_url() {
        global $pagenow;

        if ( $pagenow === 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] === self::PAGE_SLUG ) {
            $args = array();
            foreach ( array( 'action', 'id', 'msg' ) as $key ) {
                if ( isset( $_GET[ $key ] ) ) {
                    $args[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) );
                }
            }
            wp_safe_redirect( self::page_url( $args ) );
            exit;
        }
    }

    /**
     * La página no tiene entrada de menú propia: marca Ajustes → TranslatePress como
     * activo mientras se está en ella.
     */
    private function is_our_page() {
        global $plugin_page;
        return $plugin_page === self::PAGE_SLUG;
    }

    public function highlight_parent_menu( $parent_file ) {
        if ( ! $this->is_our_page() ) {
            return $parent_file;
        }

        // Justo después de este filtro, get_admin_page_parent() recalcula el padre y
        // encuentra la página bajo 'TRPHidden'. Ese recálculo respeta este mapa.
        global $_wp_real_parent_file;
        $_wp_real_parent_file['TRPHidden'] = 'options-general.php';

        return 'options-general.php';
    }

    public function highlight_submenu( $submenu_file ) {
        return $this->is_our_page() ? 'translate-press' : $submenu_file;
    }

    /**
     * Enlace "Overrides" en la fila del plugin, en la lista de plugins.
     */
    public function add_plugin_action_link( $links ) {
        if ( current_user_can( 'manage_options' ) ) {
            array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">Overrides</a>' );
        }
        return $links;
    }

    /**
     * Enqueue admin CSS and JS only on our page.
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== self::PAGE_HOOK ) {
            return;
        }

        // Estilos de TranslatePress para que la pestaña se vea como las suyas. Si algún día
        // cambia la ruta, la página sigue funcionando con los estilos de WordPress.
        if ( defined( 'TRP_PLUGIN_DIR' ) && defined( 'TRP_PLUGIN_URL' ) && file_exists( TRP_PLUGIN_DIR . 'assets/css/trp-back-end-style.css' ) ) {
            wp_enqueue_style(
                'trp-settings-style',
                TRP_PLUGIN_URL . 'assets/css/trp-back-end-style.css',
                array(),
                defined( 'TRP_PLUGIN_VERSION' ) ? TRP_PLUGIN_VERSION : false
            );
        }

        wp_enqueue_style(
            'trp-co-admin',
            TRP_CO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TRP_CO_VERSION
        );

        wp_enqueue_script(
            'trp-co-admin',
            TRP_CO_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            TRP_CO_VERSION,
            true
        );

        wp_localize_script( 'trp-co-admin', 'trpCoAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'trp_co_admin' ),
        ) );
    }

    /**
     * AJAX handler for page search (autocomplete).
     */
    public function ajax_search_pages() {
        check_ajax_referer( 'trp_co_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $search = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';

        // If the search term is numeric, look up by ID directly.
        if ( is_numeric( $search ) ) {
            $post = get_post( absint( $search ) );
            if ( $post && in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
                wp_send_json_success( array(
                    array(
                        'id'    => $post->ID,
                        'title' => $post->post_title . ' (ID: ' . $post->ID . ')',
                    ),
                ) );
            }
            wp_send_json_success( array() );
        }

        $args = array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            's'              => $search,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $query   = new WP_Query( $args );
        $results = array();

        foreach ( $query->posts as $post ) {
            $results[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title . ' (ID: ' . $post->ID . ')',
            );
        }

        wp_send_json_success( $results );
    }

    /**
     * Handle form submissions (add, edit, delete).
     */
    public function handle_form_actions() {
        if ( ! isset( $_POST['trp_co_action'] ) && ! isset( $_GET['trp_co_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Delete action (via GET).
        if ( isset( $_GET['trp_co_action'] ) && $_GET['trp_co_action'] === 'delete' ) {
            $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
            check_admin_referer( 'trp_co_delete_' . $id );
            TRP_CO_Database::delete( $id );
            wp_safe_redirect( self::page_url( array( 'msg' => 'deleted' ) ) );
            exit;
        }

        if ( ! isset( $_POST['trp_co_action'] ) ) {
            return;
        }

        // Add / Edit guardan HTML crudo que se inyecta en el frontend: además de
        // manage_options se exige unfiltered_html (respeta DISALLOW_UNFILTERED_HTML).
        if ( ! current_user_can( 'unfiltered_html' ) ) {
            wp_die( esc_html__( 'You are not allowed to save raw HTML overrides.' ), '', array( 'response' => 403 ) );
        }

        // Add / Edit action (via POST).
        $action = sanitize_text_field( wp_unslash( $_POST['trp_co_action'] ) );

        if ( $action === 'add' ) {
            check_admin_referer( 'trp_co_add' );
            // Do NOT sanitize original/translated — they contain intentional HTML.
            $original      = isset( $_POST['original'] ) ? wp_unslash( $_POST['original'] ) : '';
            $translated    = isset( $_POST['translated'] ) ? wp_unslash( $_POST['translated'] ) : '';
            $page_id       = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
            $override_type = isset( $_POST['override_type'] ) && $_POST['override_type'] === 'selector' ? 'selector' : 'string';
            $language      = $this->get_posted_language();

            if ( $original && $translated && $page_id ) {
                TRP_CO_Database::insert( $original, $translated, $page_id, $language, $override_type );
            }

            wp_safe_redirect( self::page_url( array( 'msg' => 'added' ) ) );
            exit;
        }

        if ( $action === 'edit' ) {
            check_admin_referer( 'trp_co_edit' );
            $id            = isset( $_POST['override_id'] ) ? absint( $_POST['override_id'] ) : 0;
            $original      = isset( $_POST['original'] ) ? wp_unslash( $_POST['original'] ) : '';
            $translated    = isset( $_POST['translated'] ) ? wp_unslash( $_POST['translated'] ) : '';
            $page_id       = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
            $override_type = isset( $_POST['override_type'] ) && $_POST['override_type'] === 'selector' ? 'selector' : 'string';
            $existing      = $id ? TRP_CO_Database::get_override( $id ) : null;
            $language      = $this->get_posted_language( $existing ? $existing->language : null );

            if ( $id && $original && $translated && $page_id ) {
                TRP_CO_Database::update( $id, $original, $translated, $page_id, $language, $override_type );
            }

            wp_safe_redirect( self::page_url( array( 'msg' => 'updated' ) ) );
            exit;
        }
    }

    /**
     * Idioma enviado en el formulario, validado contra "all" y los idiomas de
     * traducción de TranslatePress. Al editar también se acepta el valor que ya
     * tenía la fila (p. ej. un 'en' legacy que no corresponde a ningún idioma
     * actual), para no cambiarlo sin que el usuario lo elija.
     */
    private function get_posted_language( $stored = null ) {
        $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : TRP_CO_Languages::ALL;

        $allowed = array_keys( TRP_CO_Languages::get_translation_languages() );
        $allowed[] = TRP_CO_Languages::ALL;
        if ( $stored !== null && $stored !== '' ) {
            $allowed[] = $stored;
        }

        return in_array( $language, $allowed, true ) ? $language : TRP_CO_Languages::ALL;
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        $editing  = null;
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            $editing = TRP_CO_Database::get_override( absint( $_GET['id'] ) );
        }

        $overrides = TRP_CO_Database::get_overrides();
        $msg       = isset( $_GET['msg'] ) ? sanitize_text_field( $_GET['msg'] ) : '';
        ?>
        <div id="trp-settings-page" class="wrap trp-co-page">
            <?php
            if ( defined( 'TRP_PLUGIN_DIR' ) && file_exists( TRP_PLUGIN_DIR . 'partials/settings-header.php' ) ) {
                require TRP_PLUGIN_DIR . 'partials/settings-header.php';
            }
            do_action( 'trp_settings_navigation_tabs' );
            ?>
            <div class="trp-co-content">
            <hr class="wp-header-end">

            <?php if ( $msg === 'added' ) : ?>
                <div class="notice notice-success is-dismissible"><p>Override added.</p></div>
            <?php elseif ( $msg === 'updated' ) : ?>
                <div class="notice notice-success is-dismissible"><p>Override updated.</p></div>
            <?php elseif ( $msg === 'deleted' ) : ?>
                <div class="notice notice-success is-dismissible"><p>Override deleted.</p></div>
            <?php endif; ?>

            <!-- Add / Edit Form -->
            <div class="trp-co-form-wrap">
                <h2><?php echo $editing ? 'Edit Override' : 'Add New Override'; ?></h2>
                <form method="post" action="">
                    <?php if ( $editing ) : ?>
                        <input type="hidden" name="trp_co_action" value="edit">
                        <input type="hidden" name="override_id" value="<?php echo esc_attr( $editing->id ); ?>">
                        <?php wp_nonce_field( 'trp_co_edit' ); ?>
                    <?php else : ?>
                        <input type="hidden" name="trp_co_action" value="add">
                        <?php wp_nonce_field( 'trp_co_add' ); ?>
                    <?php endif; ?>

                    <?php
                    $current_type = $editing && isset( $editing->override_type ) ? $editing->override_type : 'string';
                    ?>
                    <table class="form-table">
                        <tr>
                            <th>Override type</th>
                            <td>
                                <label>
                                    <input type="radio" name="override_type" value="string"
                                        <?php checked( $current_type, 'string' ); ?>>
                                    String Replace
                                </label>
                                &nbsp;&nbsp;
                                <label>
                                    <input type="radio" name="override_type" value="selector"
                                        <?php checked( $current_type, 'selector' ); ?>>
                                    Selector (by element ID)
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="trp-co-page-search">Page</label></th>
                            <td>
                                <input type="text"
                                       id="trp-co-page-search"
                                       class="regular-text"
                                       placeholder="Search for a page..."
                                       autocomplete="off"
                                       value="<?php echo $editing ? esc_attr( get_the_title( $editing->page_id ) ) : ''; ?>">
                                <input type="hidden"
                                       name="page_id"
                                       id="trp-co-page-id"
                                       value="<?php echo $editing ? esc_attr( $editing->page_id ) : ''; ?>">
                                <div id="trp-co-search-results" class="trp-co-search-results"></div>
                            </td>
                        </tr>
                        <?php
                        $languages        = TRP_CO_Languages::get_translation_languages();
                        $current_language = $editing ? TRP_CO_Languages::resolve_locale( $editing->language ) : TRP_CO_Languages::ALL;
                        ?>
                        <tr>
                            <th><label for="trp-co-language">Language</label></th>
                            <td>
                                <select name="language" id="trp-co-language">
                                    <option value="<?php echo esc_attr( TRP_CO_Languages::ALL ); ?>" <?php selected( $current_language, TRP_CO_Languages::ALL ); ?>>All languages</option>
                                    <?php foreach ( $languages as $code => $name ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_language, $code ); ?>>
                                            <?php echo esc_html( $name . ' (' . $code . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ( $current_language !== TRP_CO_Languages::ALL && ! isset( $languages[ $current_language ] ) ) : ?>
                                        <option value="<?php echo esc_attr( $current_language ); ?>" selected>
                                            <?php echo esc_html( $current_language . ' (not an active translation language)' ); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <p class="description">Translation language this override applies to. The default language is never translated, so it is not listed.</p>
                            </td>
                        </tr>
                        <tr id="trp-co-row-original">
                            <th><label for="trp-co-original" id="trp-co-original-label">Original string (global translation)</label></th>
                            <td>
                                <textarea name="original"
                                          id="trp-co-original"
                                          rows="4"
                                          class="large-text"
                                          data-string-placeholder="Paste the translated string as it appears in the HTML (may include HTML tags)."
                                          data-selector-placeholder="my-element-id"><?php echo $editing ? esc_textarea( $editing->original ) : ''; ?></textarea>
                                <p class="description" id="trp-co-original-desc">Paste the translated string as it appears in the HTML (may include HTML tags).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="trp-co-translated">Contextual translation (override)</label></th>
                            <td>
                                <textarea name="translated"
                                          id="trp-co-translated"
                                          rows="4"
                                          class="large-text"><?php echo $editing ? esc_textarea( $editing->translated ) : ''; ?></textarea>
                                <p class="description">The replacement translation for this specific page.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( $editing ? 'Update Override' : 'Add Override' ); ?>
                </form>
            </div>

            <!-- Overrides Table -->
            <h2>Existing Overrides</h2>
            <?php if ( empty( $overrides ) ) : ?>
                <p>No overrides yet.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:5%">ID</th>
                            <th style="width:8%">Type</th>
                            <th style="width:24%">Original string</th>
                            <th style="width:24%">Contextual translation</th>
                            <th style="width:16%">Page</th>
                            <th style="width:10%">Language</th>
                            <th style="width:13%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $overrides as $o ) : ?>
                            <tr>
                                <td><?php echo esc_html( $o->id ); ?></td>
                                <td>
                                    <?php
                                    $type = isset( $o->override_type ) ? $o->override_type : 'string';
                                    $badge_class = $type === 'selector' ? 'trp-co-badge-selector' : 'trp-co-badge-string';
                                    ?>
                                    <span class="trp-co-badge <?php echo esc_attr( $badge_class ); ?>">
                                        <?php echo $type === 'selector' ? 'Selector' : 'String'; ?>
                                    </span>
                                </td>
                                <?php
                                // La vista previa muestra solo el texto (escapado): renderizar el HTML
                                // guardado dentro de wp-admin sería un sumidero de XSS almacenado.
                                ?>
                                <td>
                                    <div class="trp-co-html-preview"><?php echo esc_html( wp_strip_all_tags( $o->original ) ); ?></div>
                                    <button type="button" class="button-link trp-co-toggle-source">Show HTML</button>
                                    <pre class="trp-co-html-source" style="display:none"><?php echo esc_html( $o->original ); ?></pre>
                                </td>
                                <td>
                                    <div class="trp-co-html-preview"><?php echo esc_html( wp_strip_all_tags( $o->translated ) ); ?></div>
                                    <button type="button" class="button-link trp-co-toggle-source">Show HTML</button>
                                    <pre class="trp-co-html-source" style="display:none"><?php echo esc_html( $o->translated ); ?></pre>
                                </td>
                                <td>
                                    <?php
                                    $title = get_the_title( $o->page_id );
                                    echo esc_html( $title ? $title : '(ID: ' . $o->page_id . ')' );
                                    ?>
                                    <br><small>ID: <?php echo esc_html( $o->page_id ); ?></small>
                                </td>
                                <td><?php echo esc_html( TRP_CO_Languages::get_label( $o->language ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( self::page_url( array( 'action' => 'edit', 'id' => $o->id ) ) ); ?>"
                                       class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url( wp_nonce_url(
                                        self::page_url( array( 'trp_co_action' => 'delete', 'id' => $o->id ) ),
                                        'trp_co_delete_' . $o->id
                                    ) ); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Delete this override?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
