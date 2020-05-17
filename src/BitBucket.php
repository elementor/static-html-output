<?php

namespace StaticHTMLOutput;

use CURLFile;

class BitBucket extends SitePublisher {

    /**
     * @var string
     */
    public $api_base;
    /**
     * @var mixed[]
     */
    public $files_data;
    /**
     * @var Request
     */
    public $client;
    /**
     * @var string
     */
    public $user;
    /**
     * @var string
     */
    public $target_path;
    /**
     * @var string
     */
    public $local_file_contents;

    public function __construct() {
        $this->loadSettings( 'bitbucket' );

        list($this->user, $this->repository) = explode(
            '/',
            $this->settings['bbRepo']
        );

        $this->api_base = 'https://api.bitbucket.org/2.0/repositories/';

        $this->previous_hashes_path =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-BITBUCKET-PREVIOUS-HASHES.txt';

        if ( defined( 'WP_CLI' ) ) {
            return; }

        switch ( $_POST['ajax_action'] ) {
            case 'bitbucket_prepare_export':
                $this->bootstrap();
                $this->loadArchive();
                $this->prepareDeploy( true );
                break;
            case 'bitbucket_upload_files':
                $this->bootstrap();
                $this->loadArchive();
                $this->upload_files();
                break;
            case 'test_bitbucket':
                $this->test_upload();
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

        $this->files_data = [];

        foreach ( $lines as $line ) {
            $this->addFileToBatchForCommitting( $line );

            // NOTE: progress will indicate file preparation, not the transfer
            $this->updateProgress();
        }

        $this->sendBatchToBitbucket();

        $this->writeFilePathAndHashesToFile();

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function test_upload() : void {
        $this->client = new Request();

        try {
            $remote_path = $this->api_base . $this->settings['bbRepo'] . '/src';

            $post_options = [
                // @phpstan-ignore-next-line
                '.tmp_statichtmloutput.txt' => 'Test StaticHTMLOutput connectivity',
                '.tmp_statichtmloutput.txt' => 'Test StaticHTMLOutput connectivity #2',
                'message' => 'StaticHTMLOutput deployment test',
            ];

            $this->client->postWithArray(
                $remote_path,
                $post_options,
                $curl_options = [
                    CURLOPT_USERPWD => $this->user . ':' .
                        $this->settings['bbToken'],
                ]
            );

            $this->checkForValidResponses(
                $this->client->status_code,
                [ 200, 201, 301, 302, 304 ]
            );
        } catch ( StaticHTMLOutputException $e ) {
            $this->handleException( $e );
        }

        $this->finalizeDeployment();
    }

    public function addFileToBatchForCommitting( string $line ) : void {
        list($local_file, $this->target_path) = explode( ',', $line );

        $local_file = $this->archive->path . $local_file;

        $this->files_data['message'] = 'StaticHTMLOutput deployment';

        if ( ! is_file( $local_file ) ) {
            return; }

        if ( isset( $this->settings['bbPath'] ) ) {
            $this->target_path =
                $this->settings['bbPath'] . '/' . $this->target_path;
        }

        $this->local_file_contents = (string) file_get_contents( $local_file );

        if ( ! $this->local_file_contents ) {
            return;
        }

        if ( isset( $this->file_paths_and_hashes[ $this->target_path ] ) ) {
            $prev = $this->file_paths_and_hashes[ $this->target_path ];
            $current = crc32( $this->local_file_contents );

            if ( $prev != $current ) {
                $this->files_data[ '/' . rtrim( $this->target_path ) ] =
                    new CURLFile( $local_file );

                $this->recordFilePathAndHashInMemory(
                    $this->target_path,
                    $this->local_file_contents
                );
            }
        } else {
            $this->files_data[ '/' . rtrim( $this->target_path ) ] =
                new CURLFile( $local_file );

            $this->recordFilePathAndHashInMemory(
                $this->target_path,
                $this->local_file_contents
            );
        }

    }

    public function sendBatchToBitbucket() : void {
        $this->client = new Request();

        $remote_path = $this->api_base . $this->settings['bbRepo'] . '/src';

        $post_options = $this->files_data;

        try {
            // note: straight array over http_build_query for Bitbucket
            $this->client->postWithArray(
                $remote_path,
                $post_options,
                $curl_options = [
                    CURLOPT_USERPWD => $this->user . ':' .
                        $this->settings['bbToken'],
                ]
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

