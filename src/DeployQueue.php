<?php

namespace StaticHTMLOutput;

class DeployQueue {

    public static function createTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_deploy_queue';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url VARCHAR(2083) NOT NULL,
            remote_path VARCHAR(2083) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Add all Url to deploy queue
     */
    public static function addUrl( string $url, string $remote_path ) : void {
        if ( ! $url ) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_deploy_queue';

        $placeholders = [];
        $values = [];

        $placeholders[] = '(%s, %s)';
        $values[] = rawurldecode( $url );
        $values[] = rawurldecode( $remote_path );

        $query_string =
            'INSERT INTO ' . $table_name . ' (url, remote_path) VALUES ' .
            implode( ', ', $placeholders );
        $query = $wpdb->prepare( $query_string, $values );

        $wpdb->query( $query );
    }

    /**
     *  Get all deployable URLs
     *
     *  @return mixed[] All deployable URLs and remote_paths
     */
    public static function getDeployablePaths( int $limit = 500 ) : array {
        global $wpdb;
        $urls = [];

        $table_name = $wpdb->prefix . 'statichtmloutput_deploy_queue';

        $rows = $wpdb->get_results(
            "SELECT url, remote_path FROM $table_name ORDER by url ASC LIMIT $limit"
        );

        return $rows;
    }

    /**
     *  Get total deployable URLs
     *
     *  @return int Total deployable URLs
     */
    public static function getTotalDeployableURLs() : int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_deploy_queue';

        $total_deploy_queue = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        return $total_deploy_queue;
    }

    /**
     *  Clear DeployQueue via truncate or deletion
     */
    public static function truncate() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_deploy_queue';

        $wpdb->query( "TRUNCATE TABLE $table_name" );

        $total_deploy_queue = self::getTotalDeployableURLs();

        if ( $total_deploy_queue > 0 ) {
            Logger::l( 'failed to truncate DeployQueue: try deleting instead' );
        }
    }

    /**
     *  Count URLs in Deploy Queue
     */
    public static function getTotal() : int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_deploy_queue';

        $total = $wpdb->get_var( "SELECT count(*) FROM $table_name" );

        return $total;
    }

    /**
     *  Remove single URL from DeployQueue
     */
    public static function removeURL( string $url ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'statichtmloutput_deploy_queue';

        $result = $wpdb->delete(
            $table_name,
            [ 'url' => $url ]
        );
    }
}
