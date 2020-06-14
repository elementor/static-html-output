<?php

namespace StaticHTMLOutput;

class Exclusions {

    public static function createTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_exclusions';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pattern VARCHAR(2083) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Add all Urls to queue
     *
     * @param string[] $patterns List of URLs to crawl
     */
    public static function addPatterns( array $patterns ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_exclusions';

        $placeholders = [];
        $values = [];

        foreach ( $patterns as $pattern ) {
            if ( ! $pattern ) {
                continue;
            }

            $placeholders[] = '(%s)';
            $values[] = $pattern;
        }

        $query_string =
            'INSERT INTO ' . $table_name . ' (pattern) VALUES ' .
            implode( ', ', $placeholders );
        $query = $wpdb->prepare( $query_string, $values );

        $wpdb->query( $query );
    }

    /**
     *  Get all Exclusions patterns
     *
     *  @return string[] All Exclusions patterns
     */
    public static function getAll() : array {
        global $wpdb;
        $patterns = [];

        $table_name = $wpdb->prefix . 'statichtmloutput_exclusions';

        $rows = $wpdb->get_results( "SELECT pattern FROM $table_name" );

        foreach ( $rows as $row ) {
            $patterns[] = $row->url;
        }

        return $patterns;
    }
}
