<?php

namespace StaticHTMLOutput;

class BunnyCDN extends SitePublisher {

    /**
     * @var string
     */
    public $api_base;
    /**
     * @var string
     */
    public $local_file;
    /**
     * @var string
     */
    public $target_path;
    /**
     * @var string
     */
    public $local_file_contents;
    /**
     * @var Request
     */
    public $client;

    public function __construct() {
        $this->loadSettings( 'bunnycdn' );

        $this->api_base = 'https://storage.bunnycdn.com';

        $this->previous_hashes_path =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-BUNNYCDN-PREVIOUS-HASHES.txt';

        if ( defined( 'WP_CLI' ) ) {
            return;
        }

        switch ( $_POST['ajax_action'] ) {
            case 'bunnycdn_prepare_export':
                $this->bootstrap();
                $this->loadArchive();
                $this->prepareDeploy( true );
                break;
            case 'bunnycdn_transfer_files':
                $this->bootstrap();
                $this->loadArchive();
                $this->upload_files();
                break;
            case 'bunnycdn_purge_cache':
                $this->purge_all_cache();
                break;
            case 'test_bunny':
                $this->test_deploy();
                break;
        }
    }

    public function upload_files() : void {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining < 0 ) {
            echo 'ERROR';
            die(); }

        $this->initiateProgressIndicator();

        $batch_size = $this->settings['deployBatchSize'];

        if ( $batch_size > $this->files_remaining ) {
            $batch_size = $this->files_remaining;
        }

        $lines = $this->getItemsToDeploy( $batch_size );

        $this->openPreviousHashesFile();

        foreach ( $lines as $line ) {
            list($this->local_file, $this->target_path) = explode( ',', $line );

            $this->local_file = $this->archive->path . $this->local_file;

            if ( ! is_file( $this->local_file ) ) {
                continue; }

            if ( isset( $this->settings['bunnycdnRemotePath'] ) ) {
                $this->target_path =
                    $this->settings['bunnycdnRemotePath'] . '/' .
                        $this->target_path;
            }

            $this->local_file_contents = (string) file_get_contents( $this->local_file );

            if ( ! $this->local_file_contents ) {
                continue;
            }

            if ( isset( $this->file_paths_and_hashes[ $this->target_path ] ) ) {
                $prev = $this->file_paths_and_hashes[ $this->target_path ];
                $current = crc32( $this->local_file_contents );

                if ( $prev != $current ) {
                    $this->createFileInBunnyCDN();

                    $this->recordFilePathAndHashInMemory(
                        $this->target_path,
                        $this->local_file_contents
                    );
                }
            } else {
                $this->createFileInBunnyCDN();

                $this->recordFilePathAndHashInMemory(
                    $this->target_path,
                    $this->local_file_contents
                );
            }

            $this->updateProgress();
        }

        $this->writeFilePathAndHashesToFile();

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function purge_all_cache() : void {
        try {
            $endpoint = 'https://bunnycdn.com/api/pullzone/' .
                $this->settings['bunnycdnPullZoneID'] . '/purgeCache';

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
            curl_setopt( $ch, CURLOPT_URL, $endpoint );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
            curl_setopt( $ch, CURLOPT_POST, 1 );

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                [
                    'Content-Type: application/json',
                    'Content-Length: 0',
                    'AccessKey: ' .
                        $this->settings['bunnycdnPullZoneAccessKey'],
                ]
            );

            $output = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

            curl_close( $ch );

            $good_response_codes = [ '200', '201' ];

            if ( ! in_array( $status_code, $good_response_codes ) ) {
                WsLog::l(
                    'BAD RESPONSE STATUS (' . $status_code . '): '
                );

                echo 'FAIL';

                throw new StaticHTMLOutputException( 'BunnyCDN API bad response status' );
            }

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        } catch ( StaticHTMLOutputException $e ) {
            WsLog::l( 'BUNNYCDN EXPORT: error encountered' );
            WsLog::l( $e );
            throw new StaticHTMLOutputException( $e );
        }
    }

    public function test_deploy() : void {

        try {
            $remote_path = $this->api_base . '/' .
                $this->settings['bunnycdnStorageZoneName'] .
                '/tmpFile';

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
            curl_setopt( $ch, CURLOPT_URL, $remote_path );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt( $ch, CURLOPT_POST, 1 );

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                [
                    'AccessKey: ' .
                        $this->settings['bunnycdnStorageZoneAccessKey'],
                ]
            );

            $post_options = [
                'body' => 'Test StaticHTMLOutput connectivity',
            ];

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $post_options
            );

            $output = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

            curl_close( $ch );

            $good_response_codes = [ 200, 201, 301, 302, 304 ];

            if ( ! in_array( $status_code, $good_response_codes ) ) {
                WsLog::l( "BAD RESPONSE STATUS ($status_code)" );

                throw new StaticHTMLOutputException( 'BunnyCDN API bad response status' );
            }
        } catch ( StaticHTMLOutputException $e ) {
            WsLog::l( 'BUNNYCDN EXPORT: error encountered' );
            WsLog::l( $e );
            throw new StaticHTMLOutputException( $e );
        }

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function createFileInBunnyCDN() : void {
        try {
            $remote_path = $this->api_base . '/' .
                $this->settings['bunnycdnStorageZoneName'] .
                '/' . $this->target_path;

            $headers = [ 'AccessKey: ' . $this->settings['bunnycdnStorageZoneAccessKey'] ];

            $this->client->putWithFileStreamAndHeaders(
                $remote_path,
                $this->local_file,
                $headers
            );

            $this->checkForValidResponses(
                $this->client->status_code,
                [ 200, 201, 301, 302, 304 ]
            );
        } catch ( StaticHTMLOutputException $e ) {
            $this->handleException( $e );
        }
    }
}

