<?php

class Deployer extends StaticHTMLOutput {

    public function __construct() {
        $this->loadSettings(
            array(
                'advanced',
            )
        );
    }

    public function deploy( $test = false ) {
        $method = $this->settings['selected_deployment_option'];

        $start_time = microtime( true );

        $deployers_dir = dirname( __FILE__ ) . '/../deployers';

        switch ( $this->settings['selected_deployment_option'] ) {
            case 'folder':
                break;
            case 'zip':
                break;
            case 's3':
                require_once dirname( __FILE__ ) .
                    '/../StaticHTMLOutput/SitePublisher.php';

                require_once $deployers_dir . '/S3.php';

                if ( $test ) {
                    error_log( 'testing s3 deploy' );
                    $s3->test_s3();
                    return;
                }

                $s3->bootstrap();
                $s3->loadArchive();
                $s3->prepareDeploy();
                $s3->upload_files();
                $s3->cloudfront_invalidate_all_items();
                break;
            case 'bitbucket':
                require_once dirname( __FILE__ ) .
                    '/../StaticHTMLOutput/SitePublisher.php';

                require_once $deployers_dir . '/Bitbucket.php';

                if ( $test ) {
                    error_log( 'testing bitbucket deploy' );
                    $bitbucket->test_upload();
                    return;
                }

                $bitbucket->bootstrap();
                $bitbucket->loadArchive();
                $bitbucket->prepareDeploy( true );
                $bitbucket->upload_files();
                break;
            case 'bunnycdn':
                require_once dirname( __FILE__ ) .
                    '/../StaticHTMLOutput/SitePublisher.php';

                require_once $deployers_dir . '/BunnyCDN.php';

                if ( $test ) {
                    error_log( 'testing BunnyCDN deploy' );
                    $bunny->test_deploy();
                    return;
                }

                $bunny->bootstrap();
                $bunny->loadArchive();
                $bunny->prepareDeploy( true );
                $bunny->upload_files();
                $bunny->purge_all_cache();
                break;
            case 'ftp':
                require_once dirname( __FILE__ ) .
                    '/../StaticHTMLOutput/SitePublisher.php';

                require_once $deployers_dir . '/FTP.php';

                if ( $test ) {
                    error_log( 'testing FTP deploy' );
                    $ftp->test_ftp();
                    return;
                }

                $ftp->bootstrap();
                $ftp->loadArchive();
                $ftp->prepareDeploy();
                $ftp->upload_files();
                break;
            case 'github':
                require_once dirname( __FILE__ ) .
                    '/../StaticHTMLOutput/SitePublisher.php';

                require_once $deployers_dir . '/GitHub.php';

                if ( $test ) {
                    error_log( 'testing GitHub deploy' );
                    $github->test_upload();
                    return;
                }

                $github->bootstrap();
                $github->loadArchive();
                $github->prepareDeploy( true );
                $github->upload_files();
                break;
            case 'gitlab':
                require_once dirname( __FILE__ ) .
                    '/../StaticHTMLOutput/SitePublisher.php';

                require_once $deployers_dir . '/GitLab.php';

                if ( $test ) {
                    error_log( 'testing GitLab deploy' );
                    $gitlab->test_file_create();
                    return;
                }

                $gitlab->bootstrap();
                $gitlab->loadArchive();
                $gitlab->getListOfFilesInRepo();

                $gitlab->prepareDeploy( true );
                $gitlab->upload_files();
                break;
            case 'netlify':
                require_once dirname( __FILE__ ) .
                    '/../StaticHTMLOutput/SitePublisher.php';

                require_once $deployers_dir . '/Netlify.php';

                if ( $test ) {
                    error_log( 'testing Netlify deploy' );
                    $gitlab->loadArchive();
                    $netlify->test_netlify();
                    return;
                }

                $netlify->bootstrap();
                $netlify->loadArchive();
                $netlify->deploy();
                break;
        }

        $end_time = microtime( true );

        $duration = $end_time - $start_time;

        $deploy_result = 'Deployed to: ' . $method . ' in ' .  date( 'H:i:s', $duration );

        $this->finalizeDeployment( $deploy_result );
    }

    public function finalizeDeployment( string $deploy_result ) : string {
        $this->triggerPostDeployHooks();

        return $deploy_result;
    }

    public function triggerPostDeployHooks() {
        require_once dirname( __FILE__ ) .
            '/Archive.php';
        $this->archive = new Archive();
        $this->archive->setToCurrentArchive();

        do_action( 'statichtmloutput_post_deploy_trigger', $this->archive );
    }
}
