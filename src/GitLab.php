<?php

namespace StaticHTMLOutput;

class GitLab extends SitePublisher {

    /**
     * @var string
     */
    public $files_in_repo_list_path;

    public function __construct() {
        $this->loadSettings( 'gitlab' );

        $this->files_in_repo_list_path =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-GITLAB-FILES-IN-REPO.txt';

        $this->previous_hashes_path =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-GITLAB-PREVIOUS-HASHES.txt';

        if ( defined( 'WP_CLI' ) ) {
            return; }

        switch ( $_POST['ajax_action'] ) {
            case 'gitlab_prepare_export':
                $this->bootstrap();
                $this->loadArchive();
                $this->getListOfFilesInRepo();

                $this->prepareDeploy( true );
                break;
            case 'gitlab_upload_files':
                $this->bootstrap();
                $this->loadArchive();
                $this->upload_files();
                break;
            case 'test_gitlab':
                $this->test_file_create();
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

        $files_in_tree = file(
            $this->files_in_repo_list_path,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ( is_array( $files_in_tree ) ) {
            $files_in_tree = array_filter( $files_in_tree );
            $files_in_tree = array_unique( $files_in_tree );
        } else {
            $files_in_tree = [];
        }

        $files_data = [];

        $this->openPreviousHashesFile();

        foreach ( $lines as $line ) {
            list($local_file, $target_path) = explode( ',', $line );

            $local_file = $this->archive->path . $local_file;

            if ( ! is_file( $local_file ) ) {
                continue; }

            if ( isset( $this->settings['glPath'] ) ) {
                $target_path = $this->settings['glPath'] . '/' . $target_path;
            }

            $local_file_contents = file_get_contents( $local_file );

            if ( ! $local_file_contents ) {
                continue;
            }

            if ( in_array( $target_path, $files_in_tree ) ) {
                if ( isset( $this->file_paths_and_hashes[ $target_path ] ) ) {
                    $prev = $this->file_paths_and_hashes[ $target_path ];
                    $current = crc32( $local_file_contents );

                    if ( $prev != $current ) {
                        $files_data[] = [
                            'action' => 'update',
                            'file_path' => $target_path,
                            'content' => base64_encode(
                                $local_file_contents
                            ),
                            'encoding' => 'base64',
                        ];
                    }
                } else {
                    $files_data[] = [
                        'action' => 'update',
                        'file_path' => $target_path,
                        'content' => base64_encode(
                            $local_file_contents
                        ),
                        'encoding' => 'base64',
                    ];
                }
            } else {
                $files_data[] = [
                    'action' => 'create',
                    'file_path' => $target_path,
                    'content' => base64_encode(
                        $local_file_contents
                    ),
                    'encoding' => 'base64',
                ];
            }

            $this->recordFilePathAndHashInMemory(
                $target_path,
                $local_file_contents
            );

            // NOTE: delay and progress askew in GitLab as we may
            // upload all in one  request. Progress indicates building
            // of list of files that will be deployed/checking if different
            $this->updateProgress();
        }

        $this->pauseBetweenAPICalls();

        $commits_endpoint = 'https://gitlab.com/api/v4/projects/' .
            $this->settings['glProject'] . '/repository/commits';

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

            $this->writeFilePathAndHashesToFile();
        } catch ( StaticHTMLOutputException $e ) {
            $this->handleException( $e );
        }

        if ( $this->uploadsCompleted() ) {
            $this->createGitLabPagesConfig();
            $this->finalizeDeployment();
        }
    }

    /**
     * @param mixed[] $items file objects
     */
    public function addToListOfFilesInRepos( array $items ) : void {
        file_put_contents(
            $this->files_in_repo_list_path,
            implode( PHP_EOL, $items ) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
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
            WsLog::l( 'BAD RESPONSE STATUS (' . $client->status_code . '): ' );

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
        $export_line = '.gitlab-ci.yml,.gitlab-ci.yml';

        file_put_contents(
            $this->export_file_list,
            $export_line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        chmod( $this->export_file_list, 0664 );
    }
}

