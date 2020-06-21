<?php

namespace StaticHTMLOutput;

class CrawlQueue {

    public static function createTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_urls';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url VARCHAR(2083) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Add all Urls to queue
     *
     * @param string[] $urls List of URLs to crawl
     */
    public static function addUrls( array $urls ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_urls';

        $placeholders = [];
        $values = [];

        foreach ( $urls as $url ) {
            if ( ! $url ) {
                continue;
            }

            $placeholders[] = '(%s)';
            $values[] = urlencode( $url );
        }

        $query_string =
            'INSERT INTO ' . $table_name . ' (url) VALUES ' .
            implode( ', ', $placeholders );
        $query = $wpdb->prepare( $query_string, $values );

        $wpdb->query( $query );
    }

    /**
     *  Get all crawlable URLs
     *
     *  @return string[] All crawlable URLs
     */
    public static function getCrawlablePaths( int $limit = 500 ) : array {
        global $wpdb;
        $urls = [];

        $table_name = $wpdb->prefix . 'statichtmloutput_urls';

        $rows = $wpdb->get_results( "SELECT url FROM $table_name ORDER by url ASC LIMIT $limit" );

        foreach ( $rows as $row ) {
            $urls[] = urldecode( $row->url );
        }

        return $urls;
    }

    /**
     *  Get total crawlable URLs
     *
     *  @return int Total crawlable URLs
     */
    public static function getTotalCrawlableURLs() : int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_urls';

        $total_urls = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        return $total_urls;
    }

    /**
     *  Clear CrawlQueue via truncate or deletion
     */
    public static function truncate() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_urls';

        $wpdb->query( "TRUNCATE TABLE $table_name" );

        $total_urls = self::getTotalCrawlableURLs();

        if ( $total_urls > 0 ) {
            Logger::l( 'failed to truncate CrawlQueue: try deleting instead' );
        }
    }

    /**
     *  Count URLs in Crawl Queue
     */
    public static function getTotal() : int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_urls';

        $total = $wpdb->get_var( "SELECT count(*) FROM $table_name" );

        return $total;
    }

    /**
     *  Remove single URL from CrawlQueue
     */
    public static function removeURL( string $url ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_urls';

        $encoded_url = urlencode( $url );

        $result = $wpdb->delete(
            $table_name,
            [ 'url' => $encoded_url ]
        );
    }
}
