<?php
/**
 * Plugin Name: Static HTML Output
 * Plugin URI:  https://statichtmloutput.com
 * Description: Security & Performance via static website publishing.
 * Version:     6.6.21
 * Author:      Leon Stafford
 * Author URI:  https://leonstafford.github.io
 * Text Domain: static-html-output-plugin
 *
 * @package     WP_Static_HTML_Output
 */



if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'STATICHTMLOUTPUT_PATH', plugin_dir_path( __FILE__ ) );

if ( file_exists( STATICHTMLOUTPUT_PATH . 'vendor/autoload.php' ) ) {
    require_once STATICHTMLOUTPUT_PATH . 'vendor/autoload.php';
}

StaticHTMLOutput\Controller::init( __FILE__ );

$crawl_progress = filter_input( INPUT_GET, 'statichtmloutput-crawl-progress' );
$crawl_queue = filter_input( INPUT_GET, 'statichtmloutput-crawl-queue' );
$deploy_progress = filter_input( INPUT_GET, 'statichtmloutput-deploy-progress' );
$crawl_log = filter_input( INPUT_GET, 'statichtmloutput-crawl-log' );
$export_log = filter_input( INPUT_GET, 'statichtmloutput-export-log' );

if ( $export_log ) {
    if ( ! is_admin() ) {
        wp_send_json( [ 'message' => 'Not permitted' ], 403 );
    }

    header( 'Content-Type: text/plain' );
    status_header( 200 );

    $log_rows = StaticHTMLOutput\Logger::getAll();

    foreach ( $log_rows as $log_row ) {
        echo "$log_row->time \t $log_row->log \t" . PHP_EOL;
    }

    die();
    return null;
}

if ( $crawl_log ) {
    if ( ! is_admin() ) {
        wp_send_json( [ 'message' => 'Not permitted' ], 403 );
    }

    header( 'Content-Type: text/plain' );
    status_header( 200 );

    $log_rows = StaticHTMLOutput\CrawlLog::getAll();

    foreach ( $log_rows as $log_row ) {
        $crawl_status = '';

        if ( ! $log_row->status ) {
            $crawl_status = 'Pending';
        } elseif ( $log_row->status === '777' ) {
            $crawl_status = 'Skipped';
        } else {
            $crawl_status = $log_row->status;
        }

        echo str_pad( $crawl_status, 9 ) . ' ' . rawurldecode( $log_row->url ) . ' ' .
        "  Note: $log_row->note \t" . PHP_EOL;
    }

    die();
    return null;
}

if ( $crawl_queue ) {
    if ( ! is_admin() ) {
        wp_send_json( [ 'message' => 'Not permitted' ], 403 );
    }

    header( 'Content-Type: text/plain' );
    status_header( 200 );

    $detected_urls = StaticHTMLOutput\CrawlQueue::getCrawlablePaths();

    echo implode( $detected_urls, PHP_EOL );

    die();
    return null;
}

if ( $crawl_progress ) {
    if ( ! is_admin() ) {
        wp_send_json( [ 'message' => 'Not permitted' ], 403 );
    }

    $detected_urls = StaticHTMLOutput\CrawlLog::getTotalCrawlableURLs();
    $crawled_urls = StaticHTMLOutput\CrawlLog::getTotalCrawledURLs();

    $json_response = [
        'detected' => $detected_urls,
        'crawled' => $crawled_urls,
    ];

    wp_send_json( $json_response, 200 );
}

if ( $deploy_progress ) {
    if ( ! is_admin() ) {
        wp_send_json( [ 'message' => 'Not permitted' ], 403 );
    }

    $remaining_urls = StaticHTMLOutput\DeployQueue::getTotal();

    $json_response = [
        'remaining' => $remaining_urls,
    ];

    wp_send_json( $json_response, 200 );
}

function static_html_output_action_links( $links ) {
    $settings_link = '<a href="admin.php?page=statichtmloutput">Settings</a>';
    array_unshift( $links, $settings_link );

    return $links;
}

function wp_static_html_output_server_side_export() {
    $plugin = Controller::getInstance();
    $plugin->doExportWithoutGUI();
    wp_die();
    return null;
}

add_action(
    'static_html_output_server_side_export_hook',
    'static_html_output_server_side_export',
    10,
    0
);



add_filter(
    'plugin_action_links_' . plugin_basename( __FILE__ ),
    'static_html_output_action_links'
);
add_action( 'wp_ajax_wp_static_html_output_ajax', 'static_html_output_ajax' );

function static_html_output_ajax() {
    $valid_referer = check_ajax_referer( 'statichtmloutput', 'nonce' );

    if ( ! $valid_referer ) {
        wp_die();
        return null;
    }

    $ajax_method = filter_input( INPUT_POST, 'ajax_action' );

    $controller_methods = [
        'detect_urls',
        'prepare_for_export',
        'post_process_archive_dir',
        'finalize_deployment',
        'save_options',
        'reset_default_settings',
        'delete_deploy_cache',
    ];

    if ( in_array( $ajax_method, $controller_methods ) ) {
        $class = StaticHTMLOutput\Controller::getInstance();
        call_user_func( [ $class, $ajax_method ] );

        wp_die();
        return null;
    } elseif ( strpos( $ajax_method, 'crawl' ) !== false ) {
        $class = new StaticHTMLOutput\SiteCrawler();
    } elseif ( strpos( $ajax_method, 'bitbucket' ) !== false ) {
        $class = new StaticHTMLOutput\BitBucket();

        switch ( $ajax_method ) {
            case 'bitbucket_prepare_export':
                $class->bootstrap();
                $class->loadArchive();
                $class->prepareDeploy( true );
                break;
            case 'bitbucket_upload_files':
                $class->bootstrap();
                $class->loadArchive();
                $class->upload_files();
                break;
            case 'test_bitbucket':
                $class->test_upload();
                break;
        }

        wp_die();
        return null;
    } elseif ( strpos( $ajax_method, 'gitlab' ) !== false ) {
        $class = new StaticHTMLOutput\GitLab();

        switch ( $ajax_method ) {
            case 'gitlab_prepare_export':
                $class->bootstrap();
                $class->loadArchive();
                $class->prepareDeploy( true );
                $class->createGitLabPagesConfig();
                break;
            case 'gitlab_upload_files':
                $class->bootstrap();
                $class->loadArchive();
                $class->upload_files();
                break;
            case 'test_gitlab':
                $class->test_file_create();
                break;
        }

        wp_die();
        return null;
    } elseif ( strpos( $ajax_method, 'github' ) !== false ) {
        $class = new StaticHTMLOutput\GitHub();

        switch ( $ajax_method ) {
            case 'github_prepare_export':
                $class->bootstrap();
                $class->loadArchive();
                $class->prepareDeploy( true );
                break;
            case 'github_upload_files':
                $class->bootstrap();
                $class->loadArchive();
                $class->upload_files();
                break;
            case 'test_github':
                $class->test_upload();
                break;
        }

        wp_die();
        return null;
    } elseif ( strpos( $ajax_method, 'netlify' ) !== false ) {
        $class = new StaticHTMLOutput\Netlify();

        switch ( $ajax_method ) {
            case 'test_netlify':
                $class->loadArchive();
                $class->test_netlify();
                break;
            case 'netlify_do_export':
                $class->bootstrap();
                $class->loadArchive();
                $class->upload_files();
                break;
        }

        wp_die();
        return null;
    } elseif ( strpos( $ajax_method, 's3' ) !== false ) {
        $class = new StaticHTMLOutput\S3();

        switch ( $ajax_method ) {
            case 'test_s3':
                $class->test_s3();
                break;
            case 's3_prepare_export':
                $class->bootstrap();
                $class->loadArchive();
                $class->prepareDeploy();
                break;
            case 's3_transfer_files':
                $class->bootstrap();
                $class->loadArchive();
                $class->upload_files();
                break;
            case 'cloudfront_invalidate_all_items':
                $class->cloudfront_invalidate_all_items();
                break;
        }

        wp_die();
        return null;
    } elseif ( strpos( $ajax_method, 'cloudfront' ) !== false ) {
        $class = new StaticHTMLOutput\S3();
    } elseif ( strpos( $ajax_method, 'bunny' ) !== false ) {
        $class = new StaticHTMLOutput\BunnyCDN();

        switch ( $ajax_method ) {
            case 'bunnycdn_prepare_export':
                $class->bootstrap();
                $class->loadArchive();
                $class->prepareDeploy( true );
                break;
            case 'bunnycdn_transfer_files':
                $class->bootstrap();
                $class->loadArchive();
                $class->upload_files();
                break;
            case 'bunnycdn_purge_cache':
                $class->purge_all_cache();
                break;
            case 'test_bunny':
                $class->test_deploy();
                break;
        }

        wp_die();
        return null;
    } else {
        wp_die();
        return null;
    }

    call_user_func( [ $class, $ajax_method ] );

    wp_die();
    return null;
}

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

function wp_static_html_output_deregister_scripts() {
    wp_deregister_script( 'wp-embed' );
    wp_deregister_script( 'comment-reply' );
}

add_action( 'wp_footer', 'wp_static_html_output_deregister_scripts' );
remove_action( 'wp_head', 'wlwmanifest_link' );

if ( defined( 'WP_CLI' ) ) {
    WP_CLI::add_command( 'statichtmloutput', 'StaticHTMLOutput\CLI' );
    WP_CLI::add_command( 'statichtmloutput options', [ 'StaticHTMLOutput\CLI', 'options' ] );
}
