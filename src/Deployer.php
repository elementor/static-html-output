<?php

namespace StaticHTMLOutput;

class Deployer extends StaticHTMLOutput {

    /**
     * @var Archive
     */
    public $archive;

    public function __construct() {
        $this->loadSettings(
            [
                'advanced',
            ]
        );
    }

    public function deploy( bool $test = false ) : string {
        $method = $this->settings['selected_deployment_option'];

        $start_time = microtime( true );

        switch ( $this->settings['selected_deployment_option'] ) {
            case 'zip':
                break;
            case 's3':
                $s3 = new S3();

                if ( $test ) {
                    $s3->test_s3();
                    return '';
                }

                $s3->bootstrap();
                $s3->loadArchive();
                $s3->prepareDeploy();
                $s3->upload_files();
                $s3->cloudfront_invalidate_all_items();
                break;
            case 'bitbucket':
                $bitbucket = new BitBucket();
                if ( $test ) {
                    $bitbucket->test_upload();
                    return '';
                }

                $bitbucket->bootstrap();
                $bitbucket->loadArchive();
                $bitbucket->prepareDeploy( true );
                $bitbucket->upload_files();
                break;
            case 'bunnycdn':
                $bunny = new BunnyCDN();
                if ( $test ) {
                    $bunny->test_deploy();
                    return '';
                }

                $bunny->bootstrap();
                $bunny->loadArchive();
                $bunny->prepareDeploy( true );
                $bunny->upload_files();
                $bunny->purge_all_cache();
                break;
            case 'github':
                $github = new GitHub();

                if ( $test ) {
                    $github->test_upload();
                    return '';
                }

                $github->bootstrap();
                $github->loadArchive();
                $github->prepareDeploy( true );
                $github->upload_files();
                break;
            case 'gitlab':
                $gitlab = new GitLab();

                if ( $test ) {
                    $gitlab->test_file_create();
                    return '';
                }

                $gitlab->bootstrap();
                $gitlab->loadArchive();
                $gitlab->getListOfFilesInRepo();

                $gitlab->prepareDeploy( true );
                $gitlab->upload_files();
                break;
            case 'netlify':
                $netlify = new Netlify();

                if ( $test ) {
                    $netlify->test_netlify();
                    return '';
                }

                $netlify->bootstrap();
                $netlify->loadArchive();
                $netlify->upload_files();
                break;
        }

        $end_time = microtime( true );

        $duration = $end_time - $start_time;

        $deploy_result = 'Deployed to: ' . $method . ' in ' . gmdate( 'H:i:s', (int) $duration );

        return $this->finalizeDeployment( $deploy_result );
    }

    public function finalizeDeployment( string $deploy_result = '' ) : string {
        $this->triggerPostDeployHooks();

        return $deploy_result;
    }

    public function triggerPostDeployHooks() : void {
        $this->archive = new Archive();

        do_action( 'statichtmloutput_post_deploy_trigger', $this->archive );
    }
}
