<?php

namespace StaticHTMLOutput;

class Exporter extends StaticHTMLOutput {

    public function __construct() {
        $this->loadSettings(
            [
                'wpenv',
                'crawling',
                'advanced',
            ]
        );
    }

    public function pre_export_cleanup() : void {
        $files_to_clean = [
            'WP-STATIC-2ND-CRAWL-LIST.txt',
            'WP-STATIC-404-LOG.txt',
            'WP-STATIC-CRAWLED-LINKS.txt',
            'WP-STATIC-DISCOVERED-URLS-LOG.txt',
            'WP-STATIC-DISCOVERED-URLS.txt',
            'WP2STATIC-FILES-TO-DEPLOY.txt',
            'WP-STATIC-EXPORT-LOG.txt',
            'WP-STATIC-FINAL-2ND-CRAWL-LIST.txt',
            'WP-STATIC-FINAL-CRAWL-LIST.txt',
            'WP2STATIC-GITLAB-FILES-IN-REPO.txt',
        ];

        foreach ( $files_to_clean as $file_to_clean ) {
            if ( file_exists(
                $this->settings['wp_uploads_path'] . '/' . $file_to_clean
            ) ) {
                unlink(
                    $this->settings['wp_uploads_path'] . '/' .
                        $file_to_clean
                );
            }
        }
    }

    public function cleanup_working_files() : void {
        $files_to_clean = [
            '/WP-STATIC-2ND-CRAWL-LIST.txt',
            '/WP-STATIC-CRAWLED-LINKS.txt',
            '/WP-STATIC-DISCOVERED-URLS.txt',
            '/WP2STATIC-FILES-TO-DEPLOY.txt',
            '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt',
            '/WP-STATIC-FINAL-CRAWL-LIST.txt',
            '/WP2STATIC-GITLAB-FILES-IN-REPO.txt',
        ];

        foreach ( $files_to_clean as $file_to_clean ) {
            if ( file_exists(
                $this->settings['wp_uploads_path'] . '/' . $file_to_clean
            ) ) {
                unlink(
                    $this->settings['wp_uploads_path'] . '/' . $file_to_clean
                );
            }
        }
    }

    public function initialize_cache_files() : void {
        // TODO: is this still necessary?
        $crawled_links_file =
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-CRAWLED-LINKS.txt';

        $resource = fopen( $crawled_links_file, 'w' );

        if ( ! is_resource( $resource ) ) {
            return;
        }

        fwrite( $resource, '' );
        fclose( $resource );
    }

    public function cleanup_leftover_archives() : void {
        $upload_dir_paths = scandir( $this->settings['wp_uploads_path'] );

        if ( ! $upload_dir_paths ) {
            return;
        }

        $leftover_files =
            preg_grep(
                '/^([^.])/',
                $upload_dir_paths
            );

        foreach ( $leftover_files as $filename ) {
            if ( strpos( $filename, 'static-html-output' ) !== false ) {
                $deletion_target = $this->settings['wp_uploads_path'] .
                    '/' . $filename;
                if ( is_dir( $deletion_target ) ) {
                    FilesHelper::delete_dir_with_files(
                        $deletion_target
                    );
                } else {
                    unlink( $deletion_target );
                }
            }
        }

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function generateModifiedFileList() : void {
        // if no excludes or includes, no changes to CrawlLog
        if ( ! isset( $this->settings['excludeURLs'] ) &&
            ! isset( $this->settings['additionalUrls'] ) ) {
            return;
        }

        // TODO: inclusions get added to CrawlQueue if not in CrawlLog

        // applying exclusions before inclusions
        if ( isset( $this->settings['excludeURLs'] ) ) {
            $exclusions = explode(
                "\n",
                str_replace( "\r", '', $this->settings['excludeURLs'] )
            );

            Exclusions::addPatterns( $exclusions );
        }

        if ( isset( $this->settings['additionalUrls'] ) ) {
            $inclusion_cadidates = explode(
                "\n",
                str_replace( "\r", '', $this->settings['additionalUrls'] )
            );

            // check inclusion isn't already in CrawlLog, else inesert unique into CrawlQueue
            $inclusions = [];

            foreach ( $inclusions as $inclusion ) {
                $inclusion = trim( $inclusion );

                if ( ! CrawlLog::hasUrl( $inclusion ) ) {
                    $inclusions[] = $inclusion;
                }
            }

            CrawlLog::addUrls( $inclusions, 'Included by user' );
            CrawlQueue::addUrls( $inclusions );
        }
    }
}

