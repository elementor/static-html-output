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

        foreach ( $lines as $line ) {
            $this->local_file = $line->url;
            $this->target_path = $line->remote_path;

            $this->local_file = $this->archive->path . $this->local_file;

            $deploy_queue_path = str_replace( $this->archive->path, '', $this->local_file );

            if ( ! is_file( $this->local_file ) ) {
                DeployQueue::removeURL( $deploy_queue_path );
                continue;
            }

            $this->local_file_contents = (string) file_get_contents( $this->local_file );

            if ( ! $this->local_file_contents ) {
                DeployQueue::removeURL( $deploy_queue_path );
                continue;
            }

            $cached_hash = DeployCache::fileIsCached( $deploy_queue_path );

            if ( $cached_hash ) {
                $current_hash = md5( $this->local_file_contents );

                if ( $current_hash != $cached_hash ) {
                    $this->createFileInBunnyCDN();
                    DeployCache::addFile( $deploy_queue_path );
                }
            } else {
                $this->createFileInBunnyCDN();

                DeployCache::addFile( $deploy_queue_path );
            }

            DeployQueue::removeURL( $deploy_queue_path );

            $this->updateProgress();
        }

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
            Logger::l( 'BUNNYCDN PURGE CACHE: error encountered' );
            Logger::l( $e );
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
            Logger::l( 'BUNNYCDN TEST EXPORT: error encountered' );
            Logger::l( $e );
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
            Logger::l( 'BUNNYCDN EXPORT: error encountered' );
            Logger::l( $e );
            $this->handleException( $e );
        }
    }
}

