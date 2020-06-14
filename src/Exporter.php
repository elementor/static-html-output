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

    public function cleanup_leftover_archives() : void {
        $archive_path = $this->settings['wp_uploads_path'] . '/static-html-output/';
        $zip_path = rtrim( $archive_path, '/' ) . '.zip';

        if ( is_dir( $archive_path ) ) {
            FilesHelper::delete_dir_with_files(
                $archive_path
            );
        }

        if ( is_file( $zip_path ) ) {
            unlink( $zip_path );
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
            $inclusions = Exclusions::getAll();

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

