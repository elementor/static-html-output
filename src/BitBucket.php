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
    /**
     * @var string
     */
    public $local_file;

    public function __construct() {
        $this->loadSettings( 'bitbucket' );

        list($this->user, $this->repository) = explode(
            '/',
            $this->settings['bbRepo']
        );

        $this->api_base = 'https://api.bitbucket.org/2.0/repositories/';
    }

    public function upload_files() : void {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining < 0 ) {
            echo 'ERROR';
            die(); }
            
        if ( defined( 'WP_CLI' ) ) {
            $this->batch_count++;
            
            $this->progress_bar = 
                \WP_CLI\Utils\make_progress_bar( $this->progressBarMessage(), $this->files_remaining, 10 );
        }

        $this->initiateProgressIndicator();

        $batch_size = $this->settings['deployBatchSize'];

        if ( $batch_size > $this->files_remaining ) {
            $batch_size = $this->files_remaining;
        }

        $lines = $this->getItemsToDeploy( $batch_size );

        $this->files_data = [];

        foreach ( $lines as $i => $line ) {
            if ( defined( 'WP_CLI' ) )
                \WP_CLI::debug( sprintf( 'Batch %d: processing %s', $this->batch_count, $line->url ) );
            
            $this->local_file = $line->url;
            $this->target_path = $line->remote_path;

            $this->local_file = $this->archive->path . $this->local_file;

            $deploy_queue_path = str_replace( $this->archive->path, '', $this->local_file );

            if ( ! is_file( $this->local_file ) ) {
                DeployQueue::removeURL( $deploy_queue_path );
                
                if ( defined( 'WP_CLI' ) )
                    \WP_CLI::debug( sprintf( 'Batch %d: skipped %s', $this->batch_count, $line->url ) );
                
                $this->progressBarTick();
                return;
            }

            $this->local_file_contents = (string) file_get_contents( $this->local_file );

            if ( ! $this->local_file_contents ) {
                DeployQueue::removeURL( $deploy_queue_path );
                
                if ( defined( 'WP_CLI' ) )
                    \WP_CLI::warning( sprintf( 'Batch %d: skipped empty %s', $this->batch_count, $line->url ) );
                
                $this->progressBarTick();
                return;
            }

            $cached_hash = DeployCache::fileIsCached( $deploy_queue_path );

            if ( $cached_hash ) {
                $current_hash = md5( $this->local_file_contents );

                if ( $current_hash != $cached_hash ) {
                    $this->addFileToBatchForCommitting( $line );
                }
            } else {
                $this->addFileToBatchForCommitting( $line );
            }

            DeployQueue::removeURL( $deploy_queue_path );

            // NOTE: progress will indicate file preparation, not the transfer
            $this->updateProgress();
            
            if ( defined( 'WP_CLI' ) )
                \WP_CLI::debug( sprintf( 'Batch %d: processed %s', $this->batch_count, $line->url ) );
            
            $this->progressBarTick();
        }

        $this->progressBarFinish();
        $this->sendBatchToBitbucket();

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function test_upload() : void {
        $this->cliLine( 'Testing upload' );
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
            \WP_CLI::error( 'Upload test failed' );
            $this->handleException( $e );
        }

        $this->finalizeDeployment();
        $this->cliLine( 'Upload test successfully completed' );
    }


    /**
     * @param mixed $line local file and remote path to deploy
     */
    public function addFileToBatchForCommitting( $line ) : void {
        $this->files_data['message'] = 'StaticHTMLOutput deployment';
        $this->local_file = $line->url;
        $this->target_path = $line->remote_path;
        $this->local_file = $this->archive->path . $this->local_file;

        $this->files_data[ '/' . rtrim( $this->target_path ) ] =
            new CURLFile( $this->local_file );
    }

    public function sendBatchToBitbucket() : void {
        if ( ! $this->files_data ) {
            return;
        }
        
        $this->cliLine( sprintf( 'Batch %d: sending %d files', $this->batch_count, count( $this->files_data ) ) );

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

            foreach ( $this->files_data as $curl_file ) {
                $deploy_queue_path =
                    str_replace( $this->archive->path, '', $curl_file->name );

                DeployCache::addFile( $deploy_queue_path );
            }
            
            $this->cliLine( sprintf( 'Batch %d: sent %d files', $this->batch_count, count( $this->files_data ) ) );
        } catch ( StaticHTMLOutputException $e ) {
            \WP_Cli::error( sprintf( 'Batch %d: failed sending files', $this->batch_count ) );
            $this->handleException( $e );
        }
    }
}

