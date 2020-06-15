<?php

namespace StaticHTMLOutput;

class GitLab extends SitePublisher {

    /**
     * @var string[]
     */
    public $files_in_repo;
    /**
     * @var string
     */
    public $local_file;
    /**
     * @var string
     */
    public $local_file_contents;
    /**
     * @var string
     */
    public $target_path;

    public function __construct() {
        $this->loadSettings( 'gitlab' );

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

        $this->getListOfFilesInRepo();

        $files_in_tree = $this->files_in_repo;
        $files_in_tree = array_filter( $files_in_tree );
        $files_in_tree = array_unique( $files_in_tree );

        $files_data = [];

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

            // does file exist in GitLab?
            if ( in_array( $this->target_path, $files_in_tree ) ) {
                $cached_hash = DeployCache::fileIsCached( $deploy_queue_path );

                // does plugin have cache of file?
                if ( $cached_hash ) {
                    $current_hash = md5( $this->local_file_contents );
                    // plugin cache doesn't match current file hash
                    if ( $current_hash != $cached_hash ) {
                        $files_data[] = [
                            'action' => 'update',
                            'file_path' => $this->target_path,
                            'content' => base64_encode( $this->local_file_contents ),
                            'encoding' => 'base64',
                        ];
                    }
                    // plugin has no cache for file that exists in GitLab
                } else {
                    $files_data[] = [
                        'action' => 'update',
                        'file_path' => $this->target_path,
                        'content' => base64_encode( $this->local_file_contents ),
                        'encoding' => 'base64',
                    ];
                }
                // file doesn't exist in GitLab
            } else {
                $files_data[] = [
                    'action' => 'create',
                    'file_path' => $this->target_path,
                    'content' => base64_encode( $this->local_file_contents ),
                    'encoding' => 'base64',
                ];
            }

            DeployQueue::removeURL( $deploy_queue_path );

            // NOTE: delay and progress askew in GitLab as we may
            // upload all in one  request. Progress indicates building
            // of list of files that will be deployed/checking if different
            $this->updateProgress();
        }

        $this->pauseBetweenAPICalls();

        $commits_endpoint = 'https://gitlab.com/api/v4/projects/' .
            $this->settings['glProject'] . '/repository/commits';

        if ( $files_data ) {
            try {
                $client = new Request();

                $post_options = [
                    'branch' => 'master',
                    'commit_message' => 'StaticHTMLOutput Deployment',
                    'actions' => $files_data,
                ];

                $headers = [
                    'PRIVATE-TOKEN: ' . $this->settings['glToken'],
                    'Content-Type: application/json',
                ];

                $client->postWithJSONPayloadCustomHeaders(
                    $commits_endpoint,
                    $post_options,
                    $headers
                );

                $this->checkForValidResponses(
                    $client->status_code,
                    [ 200, 201, 301, 302, 304 ]
                );

                foreach ( $files_data as $file ) {
                    $deploy_queue_path =
                        str_replace( $this->archive->path, '', $file['file_path'] );

                    DeployCache::addFile( $deploy_queue_path );
                }
            } catch ( StaticHTMLOutputException $e ) {
                $this->handleException( $e );
            }
        }

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    /**
     * @param mixed[] $items file objects
     */
    public function addToListOfFilesInRepos( array $items ) : void {
        $this->files_in_repo = $this->files_in_repo ? $this->files_in_repo : [];

        $this->files_in_repo = array_merge( $this->files_in_repo, $items );
    }

    /**
     * @return mixed[] array of file objects
     */
    public function getFilePathsFromTree( string $json_response ) : array {
        $partial_tree_array = json_decode( (string) $json_response, true );

        $formatted_elements = [];

        foreach ( $partial_tree_array as $object ) {
            if ( $object['type'] === 'blob' ) {
                $formatted_elements[] = $object['path'];
            }
        }

        return $formatted_elements;
    }

    public function getRepositoryTree( int $page ) : void {
        $tree_endpoint = 'https://gitlab.com/api/v4/projects/' .
            $this->settings['glProject'] .
            '/repository/tree?recursive=true&per_page=100&page=' . $page;

        $client = new Request();

        $headers = [
            'PRIVATE-TOKEN: ' . $this->settings['glToken'],
            'Content-Type: application/json',
        ];

        $client->getWithCustomHeaders(
            $tree_endpoint,
            $headers
        );

        $good_response_codes = [ '200', '201', '301', '302', '304' ];

        if ( ! in_array( $client->status_code, $good_response_codes ) ) {
            Logger::l( 'BAD RESPONSE STATUS (' . $client->status_code . '): ' );

            throw new StaticHTMLOutputException( 'GitLab API bad response status' );
        }

        $total_pages = $client->headers['x-total-pages'];
        $next_page = $client->headers['x-next-page'];
        $current_page = $client->headers['x-page'];

        $json_items = $client->body;

        $this->addToListOfFilesInRepos(
            $this->getFilePathsFromTree( $json_items )
        );

        if ( $current_page < $total_pages ) {
            $this->getRepositoryTree( (int) $next_page );
        }
    }

    public function getListOfFilesInRepo() : void {
        $this->getRepositoryTree( 1 );
    }

    public function test_file_create() : void {
        $remote_path = 'https://gitlab.com/api/v4/projects/' .
            $this->settings['glProject'] . '/repository/commits';

        try {
            $client = new Request();

            $post_options = [
                'branch' => 'master',
                'commit_message' => 'test deploy from plugin',
                'actions' => [
                    [
                        'action' => 'create',
                        'file_path' => '.wpsho_' . time(),
                        'content' => 'test file',
                    ],
                    [
                        'action' => 'create',
                        'file_path' => '.wpsho2_' . time(),
                        'content' => 'test file 2',
                    ],
                ],
            ];

            $headers = [
                'PRIVATE-TOKEN: ' . $this->settings['glToken'],
                'Content-Type: application/json',
            ];

            $client->postWithJSONPayloadCustomHeaders(
                $remote_path,
                $post_options,
                $headers
            );

            $this->checkForValidResponses(
                $client->status_code,
                [ 200, 201, 301, 302, 304 ]
            );
        } catch ( StaticHTMLOutputException $e ) {
            $this->handleException( $e );
        }

        $this->finalizeDeployment();
    }

    public function createGitLabPagesConfig() : void {
        // NOTE: required for GitLab Pages to build static site
        $config_file = <<<EOD
pages:
  stage: deploy
  script:
  - mkdir .public
  - cp -r * .public
  - mv .public public
  artifacts:
    paths:
    - public
  only:
  - master

EOD;

        $target_path = $this->archive->path . '.gitlab-ci.yml';
        file_put_contents( $target_path, $config_file );
        chmod( $target_path, 0664 );

        DeployQueue::addUrl( '.gitlab-ci.yml', '.gitlab-ci.yml' );
    }
}

