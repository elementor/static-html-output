<?php

namespace StaticHTMLOutput;

class DetectArchiveURLs {

    /**
     * Detect Archive URLs
     *
     * Get list of archive URLs
     * ie
     *      /2020/04/
     *      /2020/05/
     *      /2020/
     *
     * @return string[] list of archive URLs
     */
    public static function detect( string $wp_site_url ) : array {
        global $wpdb;

        $archive_urls = [];

        $archive_urls_with_markup = '';

        $yearly_archives = wp_get_archives(
            [
                'type'            => 'yearly',
                'echo'            => 0,
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

        $archive_urls_with_markup .=
            is_string( $daily_archives ) ? $daily_archives : '';

        error_log( print_r( $archive_urls_with_markup, true ) );

        /*
            returns each url, title and total post count

            from this, we can extract the URL and calculate the pagination by the total / post per page

            <li><a href='http://localhost/2020/'>2020</a></li>
            <li><a href='http://localhost/2020/05/'>May 2020</a>&nbsp;(3)</li>
            <li><a href='http://localhost/2020/04/'>April 2020</a>&nbsp;(1)</li>
            <li><a href='http://localhost/2020/05/23/'>May 23, 2020</a>&nbsp;(1)</li>
            <li><a href='http://localhost/2020/05/13/'>May 13, 2020</a>&nbsp;(2)</li>
            <li><a href='http://localhost/2020/04/03/'>April 3, 2020</a>&nbsp;(1)</li>
        */

        $url_matching_regex = '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#';
        preg_match_all( $url_matching_regex, $archive_urls_with_markup, $matches );

        foreach ( $matches[0] as $url ) {
            $archive_urls[] = str_replace(
                $wp_site_url,
                '/',
                $url
            );
        }

        return $archive_urls;
    }
}
