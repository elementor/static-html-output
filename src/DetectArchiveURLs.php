<?php

namespace StaticHTMLOutput;

use DOMDocument;

class DetectArchiveURLs {

    /**
     * Detect Archive URLs
     *
     * Get list of archive URLs
     * ie
     *      /2020/04/
     *      /2020/05/
     *      /2020/05/page/2/
     *      /2020/
     *
     * @return string[] list of archive URLs
     */
    public static function detect() : array {
        global $wpdb, $wp_rewrite;

        $archive_urls = [];

        $archive_urls_with_markup = '';

        $yearly_archives = wp_get_archives(
            [
                'type'            => 'yearly',
                'echo'            => 0,
                'show_post_count' => true,
            ]
        );

        $archive_urls_with_markup .=
            is_string( $yearly_archives ) ? $yearly_archives : '';

        $monthly_archives = wp_get_archives(
            [
                'type'            => 'monthly',
                'echo'            => 0,
                'show_post_count' => true,
            ]
        );

        $archive_urls_with_markup .=
            is_string( $monthly_archives ) ? $monthly_archives : '';

        $daily_archives = wp_get_archives(
            [
                'type'            => 'daily',
                'echo'            => 0,
                'show_post_count' => true,
            ]
        );

        $archive_urls_with_markup .= is_string( $daily_archives ) ? $daily_archives : '';

        $archive_lists = explode( '</li>', $archive_urls_with_markup );

        $pagination_base = $wp_rewrite->pagination_base;

        foreach ( $archive_lists as $list_element ) {
            // capture first page of archives
            $pieces = explode( "'", $list_element );

            if ( ! isset( $pieces[1] ) ) {
                continue;
            }

            $main_url = parse_url( $pieces[1], PHP_URL_PATH );

            $archive_urls[] = (string) $main_url;
            // get total count for archive
            preg_match( '#\((.*?)\)#', $list_element, $count_match );

            $count = count( $count_match ) > 1 ? $count_match[1] : 0;

            // build pagination URLs
            $default_posts_per_page = get_option( 'posts_per_page' );
            $total_pages = ceil( $count / $default_posts_per_page );

            // first page is canonical to archive page, so skip that
            if ( $total_pages > 1 ) {
                for ( $page = 2; $page <= $total_pages; $page++ ) {
                    $pagination_url = $main_url . $pagination_base . '/' . $page . '/';
                    $archive_urls[] = (string) $pagination_url;
                }
            }
        }

        return $archive_urls;
    }
}
