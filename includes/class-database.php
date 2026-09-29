<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TRP_CO_Database {

    private static $table_name_suffix = 'trp_context_overrides';

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name_suffix;
    }

    public static function create_table() {
        global $wpdb;
        $table  = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            original TEXT NOT NULL,
            translated TEXT NOT NULL,
            page_id BIGINT(20) UNSIGNED NOT NULL,
            language VARCHAR(10) NOT NULL DEFAULT 'en',
            override_type VARCHAR(20) NOT NULL DEFAULT 'string',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_page_id (page_id),
            KEY idx_original_hash (original(191))
        ) ENGINE=InnoDB {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Add override_type column if it doesn't exist (upgrade from v1.0.0).
     */
    public static function maybe_upgrade_table() {
        global $wpdb;
        $table = self::get_table_name();

        $column = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'override_type'" );
        if ( empty( $column ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN override_type VARCHAR(20) NOT NULL DEFAULT 'string' AFTER language" );
        }
    }

    /**
     * Get all overrides, optionally filtered by page_id.
     */
    public static function get_overrides( $page_id = null ) {
        global $wpdb;
        $table = self::get_table_name();

        if ( $page_id ) {
            return $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE page_id = %d ORDER BY id DESC", $page_id )
            );
        }

        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
    }

    /**
     * Get a single override by ID.
     */
    public static function get_override( $id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
        );
    }

    /**
     * Insert a new override.
     */
    public static function insert( $original, $translated, $page_id, $language = 'en', $override_type = 'string' ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->insert(
            $table,
            array(
                'original'      => $original,
                'translated'    => $translated,
                'page_id'       => $page_id,
                'language'      => $language,
                'override_type' => $override_type,
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
    }

    /**
     * Update an existing override.
     */
    public static function update( $id, $original, $translated, $page_id, $language = 'en', $override_type = 'string' ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->update(
            $table,
            array(
                'original'      => $original,
                'translated'    => $translated,
                'page_id'       => $page_id,
                'language'      => $language,
                'override_type' => $override_type,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Delete an override by ID.
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }
}
