<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table = $wpdb->prefix . 'trp_context_overrides';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'trp_co_version' );
