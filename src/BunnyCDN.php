<?php

namespace StaticHTMLOutput;

use GuzzleHttp\Client;

class BunnyCDN extends SitePublisher {

    /**
     * @var Client
     */
    public $account_client;
    /**
     * @var mixed[]
     */
    public $account_headers;
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

    public function __construct() {
        $this->loadSettings( 'bunnycdn' );

        if ( isset( $this->settings['bunnycdn_api_host'] ) ) {
            $this->api_base = 'https://' . $this->settings['bunnycdn_api_host'];
        } else {
            $this->api_base = 'https://storage.bunnycdn.com';
        }

        $this->previous_hashes_path =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-BUNNYCDN-PREVIOUS-HASHES.txt';

        if ( defined( 'WP_CLI' ) ) {
            return;
        }

        $this->account_client = new Client( [ 'base_uri' => $this->api_base ] );
        $this->account_headers =
            [ 'AccessKey' => $this->settings['bunnycdnStorageZoneAccessKey'] ];

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
            $client = new Client( [ 'base_uri' => 'https://bunnycdn.com' ] );
            $headers =
                [ 'AccessKey' => $this->settings['bunnycdnPullZoneAccessKey'] ];

            $res = $client->request(
                'POST',
                '/api/pullzone/' . $this->settings['bunnycdnPullZoneID'] . '/purgeCache',
                [
                    'headers' => $headers,
                ]
            );

            $result = json_decode( (string) $res->getBody() );

            if ( $result ) {
                $this->checkForValidResponses(
                    $result->HttpCode,
                    [ 200, 201, 301, 302, 304 ]
                );
            }

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        } catch ( StaticHTMLOutputException $e ) {
            WsLog::l( 'BUNNYCDN PURGE CACHE: error encountered' );
            WsLog::l( $e );
            throw new StaticHTMLOutputException( $e );
        }
    }

    public function test_deploy() : void {
        try {
            $remote_path = $this->api_base . '/' .
                $this->settings['bunnycdnStorageZoneName'] .
                '/tmpFile';

            $res = $this->account_client->request(
                'PUT',
                "$remote_path",
                [
                    'headers' => $this->account_headers,
                    'body' => 'Testing Static HTML Output settings',
                ]
            );

            $result = json_decode( (string) $res->getBody() );

            if ( $result ) {
                $this->checkForValidResponses(
                    $result->HttpCode,
                    [ 200, 201, 301, 302, 304 ]
                );

            }
        } catch ( StaticHTMLOutputException $e ) {
            WsLog::l( 'BUNNYCDN TEST EXPORT: error encountered' );
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

            $res = $this->account_client->request(
                'PUT',
                "$remote_path",
                [
                    'headers' => $this->account_headers,
                    'body' => file_get_contents( $this->local_file ),
                ]
            );

            $result = json_decode( (string) $res->getBody() );

            if ( $result ) {
                $this->checkForValidResponses(
                    $result->HttpCode,
                    [ 200, 201, 301, 302, 304 ]
                );

            }
        } catch ( StaticHTMLOutputException $e ) {
            WsLog::l( 'BUNNYCDN EXPORT: error encountered' );
            WsLog::l( $e );
            $this->handleException( $e );
        }
    }
}

