<?php
/**
 * Plugin Name: HM TRP Context Overrides
 * Description: Extiende TranslatePress para permitir (a nivel de página) sobreescritura contextual.
 * Version: 1.2.1
 * Author: Hot Marketing
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Plugin URI: https://github.com/hotmarketing/trp-por-contexto
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/hotmarketing/trp-por-contexto
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TRP_CO_VERSION', '1.2.1');
define('TRP_CO_PLUGIN_FILE', __FILE__);
define('TRP_CO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRP_CO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader de Composer (plugin-update-checker). Solo existe en el ZIP del release.
$trp_co_autoload = TRP_CO_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($trp_co_autoload)) {
    require_once $trp_co_autoload;
}
unset($trp_co_autoload);

/**
 * Actualizaciones desde GitHub Releases. Corre aunque TranslatePress no esté activo:
 * el plugin debe poder actualizarse igual.
 */
function trp_co_boot_updater()
{
    require_once TRP_CO_PLUGIN_DIR . 'includes/class-updater.php';
    TRP_CO_Updater::boot();
}
add_action('plugins_loaded', 'trp_co_boot_updater');

/**
 * Check if TranslatePress is active before loading.
 */
function trp_co_init()
{
    if (!class_exists('TRP_Translate_Press')) {
        add_action('admin_notices', 'trp_co_missing_trp_notice');
        return;
    }

    require_once TRP_CO_PLUGIN_DIR . 'includes/class-database.php';
    require_once TRP_CO_PLUGIN_DIR . 'includes/class-languages.php';
    require_once TRP_CO_PLUGIN_DIR . 'includes/class-override-engine.php';
    require_once TRP_CO_PLUGIN_DIR . 'includes/class-admin-page.php';

    new TRP_CO_Database();
    new TRP_CO_Override_Engine();

    if (is_admin()) {
        new TRP_CO_Admin_Page();
    }
}
add_action('plugins_loaded', 'trp_co_init');

/**
 * Admin notice when TranslatePress is not active.
 */
function trp_co_missing_trp_notice()
{
    echo '<div class="notice notice-error"><p><strong>HM TRP Context Overrides</strong> requires TranslatePress to be installed and active.</p></div>';
}

/**
 * On activation: create the database table and store version.
 */
function trp_co_activate()
{
    require_once TRP_CO_PLUGIN_DIR . 'includes/class-database.php';
    TRP_CO_Database::create_table();
    TRP_CO_Database::maybe_upgrade_table();
    update_option('trp_co_version', TRP_CO_VERSION);
}
register_activation_hook(__FILE__, 'trp_co_activate');

/**
 * On plugins_loaded: run upgrade if version changed (covers updates without deactivate/activate).
 */
function trp_co_maybe_upgrade()
{
    $stored_version = get_option('trp_co_version', '0');
    if (version_compare($stored_version, TRP_CO_VERSION, '<')) {
        require_once TRP_CO_PLUGIN_DIR . 'includes/class-database.php';
        TRP_CO_Database::maybe_upgrade_table();
        update_option('trp_co_version', TRP_CO_VERSION);
    }
}
add_action('plugins_loaded', 'trp_co_maybe_upgrade', 5);