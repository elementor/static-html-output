<?php
/**
 * Plugin Name: Static HTML Output
 * Plugin URI:  https://statichtmloutput.com
 * Description: Security & Performance via static website publishing.
 * Version:     6.6.8
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
        'generate_filelist_preview',
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
        // crawl_again is used to detemine 2nd run of crawling
        if ( $ajax_method === 'crawl_again' ) {
            $ajax_method = 'crawl_discovered_links';
        }
    } elseif ( strpos( $ajax_method, 'bitbucket' ) !== false ) {
        $class = new StaticHTMLOutput\Bitbucket();
    } elseif ( strpos( $ajax_method, 'github' ) !== false ) {
        $class = new StaticHTMLOutput\GitHub();
    } elseif ( strpos( $ajax_method, 'gitlab' ) !== false ) {
        $class = new StaticHTMLOutput\GitLab();
    } elseif ( strpos( $ajax_method, 's3' ) !== false ) {
        $class = new StaticHTMLOutput\S3();
    } elseif ( strpos( $ajax_method, 'cloudfront' ) !== false ) {
        $class = new StaticHTMLOutput\S3();
    } elseif ( strpos( $ajax_method, 'ftp' ) !== false ) {
        $class = new StaticHTMLOutput\FTP();
    } elseif ( strpos( $ajax_method, 'bunny' ) !== false ) {
        $class = new StaticHTMLOutput\BunnyCDN();
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
