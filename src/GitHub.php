<?php

namespace StaticHTMLOutput;

class GitHub extends SitePublisher {
    /**
     * @var string
     */
    public $api_base;
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
    /**
     * @var string
     */
    public $remote_path;
    /**
     * @var string
     */
    public $repository;
    /**
     * @var string
     */
    public $query;
    /**
     * @var Request
     */
    public $client;
    /**
     * @var mixed
     */
    public $existing_file_object;

    public function __construct() {
        $this->loadSettings( 'github' );

        list($this->user, $this->repository) = explode(
            '/',
            $this->settings['ghRepo']
        );

        $this->api_base = 'https://api.github.com/repos/';

        if ( defined( 'WP_CLI' ) ) {
            return; }
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
                    if ( $this->fileExistsInGitHub() ) {
                        $this->updateFileInGitHub();
                        DeployCache::addFile( $deploy_queue_path );
                    } else {
                        $this->createFileInGitHub();
                        DeployCache::addFile( $deploy_queue_path );
                    }
                }
            } else {
                if ( $this->fileExistsInGitHub() ) {
                    $this->updateFileInGitHub();
                    DeployCache::addFile( $deploy_queue_path );
                } else {
                    $this->createFileInGitHub();
                    DeployCache::addFile( $deploy_queue_path );
                }
            }

            DeployQueue::removeURL( $deploy_queue_path );

            $this->updateProgress();
        }

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function test_upload() : void {
        try {
            $this->remote_path = $this->api_base . $this->settings['ghRepo'] .
                '/contents/' . '.StaticHTMLOutput/' . uniqid();

            $b64_file_contents = base64_encode( 'StaticHTMLOutput test upload' );

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_URL, $this->remote_path );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt( $ch, CURLOPT_POST, 1 );
            curl_setopt( $ch, CURLOPT_USERAGENT, 'StaticHTMLOutput.com' );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );

            $post_options = [
                'message' => 'Test StaticHTMLOutput connectivity',
                'content' => $b64_file_contents,
                'branch' => $this->settings['ghBranch'],
            ];

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode( $post_options )
            );

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                [ "Authorization: token {$this->settings['ghToken']}" ]
            );

            $output = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

            curl_close( $ch );

            $good_response_codes = [ 200, 201, 301, 302, 304 ];

            if ( ! in_array( $status_code, $good_response_codes ) ) {
                Logger::l( "BAD RESPONSE STATUS ($status_code)" );

                throw new StaticHTMLOutputException( 'GitHub API bad response status' );
            }
        } catch ( StaticHTMLOutputException $e ) {
            Logger::l( 'GITHUB EXPORT: error encountered' );
            Logger::l( $e );
            throw new StaticHTMLOutputException( $e );
        }

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function fileExistsInGitHub() : bool {
        $this->remote_path = $this->api_base . $this->settings['ghRepo'] .
            '/contents/' . $this->target_path;
        // GraphQL query to get sha of existing file
        $this->query = <<<JSON
query{
  repository(owner: "{$this->user}", name: "{$this->repository}") {
    object(expression: "{$this->settings['ghBranch']}:{$this->target_path}") {
      ... on Blob {
        oid
        byteSize
      }
    }
  }
}
JSON;
        $this->client = new Request();

        $post_options = [
            'query' => $this->query,
            'variables' => '',
        ];

        $headers = [
            'Authorization: ' .
                    'token ' . $this->settings['ghToken'],
        ];

        $this->client->postWithJSONPayloadCustomHeaders(
            'https://api.github.com/graphql',
            $post_options,
            $headers,
            $curl_options = [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ]
        );

        $this->checkForValidResponses(
            $this->client->status_code,
            [ 200, 201, 301, 302, 304 ]
        );

        $gh_file_info = json_decode( $this->client->body, true );

        $this->existing_file_object =
            $gh_file_info['data']['repository']['object'];

        $action = '';
        $commit_message = '';

        if ( ! empty( $this->existing_file_object ) ) {
            Logger::l( "{$this->target_path} path exists in GitHub" );

            return true;
        }

        return false;
    }

    public function updateFileInGitHub() : void {
        $action = 'UPDATE';
        $existing_sha = $this->existing_file_object['oid'];
        $existing_bytesize = $this->existing_file_object['byteSize'];

        $b64_file_contents = base64_encode( $this->local_file_contents );

        if ( isset( $this->settings['ghCommitMessage'] ) ) {
            $commit_message = str_replace(
                [
                    '%ACTION%',
                    '%FILENAME%',
                ],
                [
                    $action,
                    $this->target_path,
                ],
                $this->settings['ghCommitMessage']
            );
        } else {
            $commit_message = 'StaticHTMLOutput ' .
                $action . ' ' .
                $this->target_path;
        }

        try {
            $post_options = [
                'message' => $commit_message,
                'content' => $b64_file_contents,
                'branch' => $this->settings['ghBranch'],
                'sha' => $existing_sha,
            ];

            $headers = [
                'Authorization: ' .
                        'token ' . $this->settings['ghToken'],
            ];

            $this->client->putWithJSONPayloadCustomHeaders(
                $this->remote_path,
                $post_options,
                $headers
            );

            // note, this was never being used, check if required
            // CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,

            $this->checkForValidResponses(
                $this->client->status_code,
                [ 200, 201, 301, 302, 304 ]
            );
        } catch ( StaticHTMLOutputException $e ) {
            $this->handleException( $e );
        }
    }

    public function createFileInGitHub() : void {
        $action = 'CREATE';

        $b64_file_contents = base64_encode( $this->local_file_contents );

        if ( isset( $this->settings['ghCommitMessage'] ) ) {
            $commit_message = str_replace(
                [
                    '%ACTION%',
                    '%FILENAME%',
                ],
                [
                    $action,
                    $this->target_path,
                ],
                $this->settings['ghCommitMessage']
            );
        } else {
            $commit_message = 'StaticHTMLOutput ' .
                $action . ' ' .
                $this->target_path;
        }

        try {
            $post_options = [
                'message' => $commit_message,
                'content' => $b64_file_contents,
                'branch' => $this->settings['ghBranch'],
            ];

            $headers = [ "Authorization: token {$this->settings['ghToken']}" ];

            $this->client->putWithJSONPayloadCustomHeaders(
                $this->remote_path,
                $post_options,
                $headers
            );

            // note, this was never being used, check if required
            // CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,

            $this->checkForValidResponses(
                $this->client->status_code,
                [ 200, 201, 301, 302, 304 ]
            );
        } catch ( StaticHTMLOutputException $e ) {
            $this->handleException( $e );
        }
    }
}

