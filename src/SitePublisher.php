<?php

namespace StaticHTMLOutput;

abstract class SitePublisher {

    /**
     * @var mixed[]
     */
    public $settings;
    /**
     * @var string
     */
    public $archive_dir;
    /**
     * @var int
     */
    public $total_urls_to_crawl;
    /**
     * @var int
     */
    public $files_remaining;
    /**
     * @var mixed[]
     */
    public $file_paths_and_hashes;
    /**
     * @var Archive
     */
    public $archive;

    abstract public function upload_files() : void;

    public function loadSettings( string $deploy_method ) : void {
        $target_settings = [
            'general',
            'wpenv',
            'advanced',
        ];

        $target_settings[] = $deploy_method;

        if ( isset( $_POST['selected_deployment_option'] ) ) {
            $this->settings = PostSettings::get( $target_settings );
        } else {
            $this->settings = DBSettings::get( $target_settings );
        }
    }

    public function loadArchive() : void {
        $this->archive = new Archive();
    }

    public function bootstrap() : void {
        $this->archive_dir = $this->settings['wp_uploads_path'] . '/static-html-output/';
    }

    public function pauseBetweenAPICalls() : void {
        if ( isset( $this->settings['delayBetweenAPICalls'] ) &&
            $this->settings['delayBetweenAPICalls'] > 0 ) {
            sleep( $this->settings['delayBetweenAPICalls'] );
        }
    }

    // TODO: remove?
    public function updateProgress() : void {

    }

    // TODO: remove?
    public function initiateProgressIndicator() : void {

    }


    public function clearFileList() : void {
        DeployQueue::truncate();

        // TODO: add case for GitLab
        if ( isset( $this->glob_hash_path_list ) ) {
            if ( is_file( $this->glob_hash_path_list ) ) {
                $f = fopen( $this->glob_hash_path_list, 'r+' );

                if ( ! is_resource( $f ) ) {
                    return;
                }

                if ( $f !== false ) {
                    ftruncate( $f, 0 );
                    fclose( $f );
                }
            }
        }
    }

    public function isSkippableFile( string $file ) : bool {
        if ( $file == '.' || $file == '..' || $file == '.git' ) {
            return true;
        }

        return false;
    }

    public function getLocalFileToDeploy( string $file_in_archive, string $replace_path ) : string {
        // NOTE: untested fix for Windows filepaths
        // https://github.com/leonstafford/statichtmloutput/issues/221
        $original_filepath = str_replace(
            '\\',
            '\\\\',
            $file_in_archive
        );

        $original_file_without_archive = str_replace(
            $replace_path,
            '',
            $original_filepath
        );

        $original_file_without_archive = ltrim(
            $original_file_without_archive,
            '/'
        );

        return $original_file_without_archive;
    }

    public function getArchivePathForReplacement( string $archive_path ) : string {
        $local_path_to_strip = $archive_path;
        $local_path_to_strip = rtrim( $local_path_to_strip, '/' );

        $local_path_to_strip = str_replace(
            '//',
            '/',
            $local_path_to_strip
        );

        return $local_path_to_strip;
    }

    public function getRemoteDeploymentPath(
        string $dir,
        string $file_in_archive,
        string $archive_path_to_replace,
        bool $basename_in_target
        ) : string {
        $deploy_path = str_replace(
            $archive_path_to_replace,
            '',
            $dir
        );

        $deploy_path = ltrim( $deploy_path, '/' );
        $deploy_path .= '/';

        if ( $basename_in_target ) {
            $deploy_path .= basename(
                $file_in_archive
            );
        }

        $deploy_path = ltrim( $deploy_path, '/' );

        return $deploy_path;
    }

    public function createDeploymentList( string $dir, bool $basename_in_target ) : void {
        $deployable_files = scandir( $dir );

        if ( ! $deployable_files ) {
            return;
        }

        $archive_path_to_replace =
            $this->getArchivePathForReplacement( $this->archive->path );

        foreach ( $deployable_files as $item ) {
            if ( $this->isSkippableFile( $item ) ) {
                continue;
            }

            $file_in_archive = $dir . '/' . $item;

            if ( is_dir( $file_in_archive ) ) {
                $this->createDeploymentList(
                    $file_in_archive,
                    $basename_in_target
                );
            } elseif ( is_file( $file_in_archive ) ) {
                $local_file_path =
                    $this->getLocalFileToDeploy(
                        $file_in_archive,
                        $archive_path_to_replace
                    );

                $remote_deployment_path =
                    $this->getRemoteDeploymentPath(
                        $dir,
                        $file_in_archive,
                        $archive_path_to_replace,
                        $basename_in_target
                    );

                DeployQueue::addUrl( $local_file_path, $remote_deployment_path );
            }
        }
    }

    public function prepareDeploy( bool $basename_in_target = false ) : void {
        $this->clearFileList();

        $this->createDeploymentList(
            $this->settings['wp_uploads_path'] . '/' . $this->archive->name,
            $basename_in_target
        );

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    /**
     * @return mixed[] pairs of local files and remote deploy paths
     */
    public function getItemsToDeploy( int $batch_size = 1 ) : array {
        // $lines = [];
        $batch_of_links_to_deploy = [];

        $deployable_urls = DeployQueue::getTotalDeployableURLs();

        if ( ! $deployable_urls ) {
            return [];
        }

        // get total DeployQueue
        // TODO: have duplicate total fetching fns in Crawl, Deploy queues
        $total_urls = DeployQueue::getTotal();

        // get batch size (smaller of total urls or crawl_increment)
        $batch_size = min( $total_urls, $this->settings['deployBatchSize'] );

        // fetch just amount of URLs needed (limit to crawl_increment)
        $urls_to_deploy = DeployQueue::getDeployablePaths( $batch_size );

        return $urls_to_deploy;
    }

    public function getRemainingItemsCount() : int {
        $deployable_urls = DeployQueue::getTotalDeployableURLs();

        return $deployable_urls;
    }

    // TODO: rename to signalSuccessfulAction or such
    // as is used in deployment tests/not just finalizing deploys
    public function finalizeDeployment() : void {
        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS'; }
    }

    public function uploadsCompleted() : bool {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining <= 0 ) {
            return true;
        } else {
            if ( defined( 'WP_CLI' ) ) {
                $this->upload_files();
            } else {
                echo $this->files_remaining;
            }

            return false;
        }
    }

    /**
     * @throws StaticHTMLOutputException
     */
    public function handleException( string $e ) : void {
        Logger::l( 'Deployment: error encountered' );
        Logger::l( $e );
        throw new StaticHTMLOutputException( $e );
    }

    /**
     * @param int[] $good_codes valid HTTP response codes
     * @throws StaticHTMLOutputException
     */
    public function checkForValidResponses( int $code, array $good_codes ) : void {
        if ( ! in_array( $code, $good_codes ) ) {
            Logger::l(
                'BAD RESPONSE STATUS FROM API (' . $code . ')'
            );

            http_response_code( $code );

            throw new StaticHTMLOutputException(
                'BAD RESPONSE STATUS FROM API (' . $code . ')'
            );
        }
    }
}

