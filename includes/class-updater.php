<?php
/**
 * Actualizaciones automáticas desde el repositorio público de GitHub. Sin token, sin mirror.
 *
 * Publicar una versión es: git tag vX.Y.Z && git push origin vX.Y.Z. El workflow de
 * release arma el ZIP (con vendor/) y lo adjunta a un GitHub Release; los sitios con el
 * plugin lo ven en Escritorio → Actualizaciones.
 *
 * Dos detalles importan:
 *   - REQUIRE_RELEASE_ASSETS: se instala el ZIP que arma el workflow, nunca el archivo
 *     fuente de GitHub. El fuente trae otra carpeta (trp-por-contexto-X.Y.Z/) y no trae
 *     vendor/, así que instalarlo dejaría el plugin duplicado y sin este updater.
 *   - El "latest release" de GitHub ignora los prereleases, así que tags como
 *     v1.3.0-rc.1 nunca llegan a los sitios normales.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

final class TRP_CO_Updater {

    const SLUG = 'hm-trp-context-overrides';

    const REPO_URL = 'https://github.com/hotmarketing/trp-por-contexto/';

    /** Versión de WordPress que el plugin declara como probada. */
    const WP_TESTED = '7.1';

    /**
     * Api::REQUIRE_RELEASE_ASSETS de PUC. Va como literal porque la clase vive en un
     * namespace atado a la versión menor de PUC (v5p7, v5p8…).
     */
    const REQUIRE_RELEASE_ASSETS = 2;

    public static function boot() {
        if ( ! self::should_run() ) {
            return;
        }
        if ( ! class_exists( PucFactory::class ) ) {
            return; // Sin vendor/ (un checkout de git sin composer install): no hay auto-update.
        }

        $checker = PucFactory::buildUpdateChecker( self::REPO_URL, TRP_CO_PLUGIN_FILE, self::SLUG );
        $checker->setBranch( 'main' );
        $checker->getVcsApi()->enableReleaseAssets( '/^' . preg_quote( self::SLUG, '/' ) . '\.zip$/', self::REQUIRE_RELEASE_ASSETS );

        add_filter( 'puc_request_update_result-' . self::SLUG, array( __CLASS__, 'complete_update' ) );
        add_filter( 'puc_request_info_result-' . self::SLUG, array( __CLASS__, 'complete_info' ) );
    }

    /**
     * Solo donde se pueden buscar o instalar actualizaciones. admin-ajax cuenta como admin
     * incluso para visitantes anónimos, así que ahí además exige update_plugins.
     */
    private static function should_run() {
        if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return true;
        }
        if ( ! is_admin() ) {
            return false;
        }
        if ( wp_doing_ajax() ) {
            return current_user_can( 'update_plugins' );
        }
        return true;
    }

    /**
     * Completa lo que GitHub no provee (compatibilidad) para la pantalla de Actualizaciones.
     */
    public static function complete_update( $update ) {
        if ( is_object( $update ) ) {
            $update->tested       = empty( $update->tested ) ? self::WP_TESTED : $update->tested;
            $update->requires_php = empty( $update->requires_php ) ? '7.4' : $update->requires_php;
        }
        return $update;
    }

    /**
     * Lo mismo para la ventana "Ver detalles".
     */
    public static function complete_info( $info ) {
        if ( is_object( $info ) ) {
            $info->tested       = empty( $info->tested ) ? self::WP_TESTED : $info->tested;
            $info->requires     = empty( $info->requires ) ? '6.0' : $info->requires;
            $info->requires_php = empty( $info->requires_php ) ? '7.4' : $info->requires_php;
        }
        return $info;
    }
}
