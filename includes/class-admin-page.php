<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TRP_CO_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_trp_co_search_pages', array( $this, 'ajax_search_pages' ) );
        add_action( 'admin_init', array( $this, 'handle_form_actions' ) );
    }

    /**
     * Register the admin menu page under Settings.
     */
    public function add_menu_page() {
        add_options_page(
            'TRP Context Overrides',
            'TRP Context Overrides',
            'manage_options',
            'trp-context-overrides',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS only on our page.
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'settings_page_trp-context-overrides' ) {
            return;
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
            wp_safe_redirect( admin_url( 'options-general.php?page=trp-context-overrides&msg=deleted' ) );
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

            if ( $original && $translated && $page_id ) {
                TRP_CO_Database::insert( $original, $translated, $page_id, 'en', $override_type );
            }

            wp_safe_redirect( admin_url( 'options-general.php?page=trp-context-overrides&msg=added' ) );
            exit;
        }

        if ( $action === 'edit' ) {
            check_admin_referer( 'trp_co_edit' );
            $id            = isset( $_POST['override_id'] ) ? absint( $_POST['override_id'] ) : 0;
            $original      = isset( $_POST['original'] ) ? wp_unslash( $_POST['original'] ) : '';
            $translated    = isset( $_POST['translated'] ) ? wp_unslash( $_POST['translated'] ) : '';
            $page_id       = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
            $override_type = isset( $_POST['override_type'] ) && $_POST['override_type'] === 'selector' ? 'selector' : 'string';

            if ( $id && $original && $translated && $page_id ) {
                TRP_CO_Database::update( $id, $original, $translated, $page_id, 'en', $override_type );
            }

            wp_safe_redirect( admin_url( 'options-general.php?page=trp-context-overrides&msg=updated' ) );
            exit;
        }
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
        <div class="wrap">
            <h1>TRP Context Overrides</h1>

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
                            <th style="width:27%">Original string</th>
                            <th style="width:27%">Contextual translation</th>
                            <th style="width:18%">Page</th>
                            <th style="width:15%">Actions</th>
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
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=trp-context-overrides&action=edit&id=' . $o->id ) ); ?>"
                                       class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url( wp_nonce_url(
                                        admin_url( 'options-general.php?page=trp-context-overrides&trp_co_action=delete&id=' . $o->id ),
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
        <?php
    }
}
