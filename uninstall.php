<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/*
 * Si hay otra copia de este plugin instalada (p. ej. alguien subió el ZIP de
 * "Code → Download ZIP" de GitHub, que trae otra carpeta), la tabla es compartida:
 * borrarla aquí dejaría a la otra copia sin overrides. En ese caso no se borra nada.
 */
if ( ! function_exists( 'get_plugins' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

foreach ( get_plugins() as $trp_co_basename => $trp_co_data ) {
    if ( $trp_co_basename === WP_UNINSTALL_PLUGIN ) {
        continue;
    }

    $trp_co_is_copy = basename( $trp_co_basename ) === 'hm-trp-context-overrides.php'
        || ( isset( $trp_co_data['Name'] ) && $trp_co_data['Name'] === 'HM TRP Context Overrides' );

    if ( $trp_co_is_copy ) {
        return;
    }
}

global $wpdb;

$table = $wpdb->prefix . 'trp_context_overrides';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'trp_co_version' );
