<?php
/**
 * Plugin Name: Static HTML Output
 * Plugin URI:  https://statichtmloutput.com
 * Description: Security & Performance via static website publishing. One plugin to solve WordPress's biggest problems.
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

add_action( 'static_html_output_server_side_export_hook', 'static_html_output_server_side_export', 10, 0 );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'static_html_output_action_links' );
add_action( 'wp_ajax_wp_static_html_output_ajax', 'static_html_output_ajax' );

function static_html_output_ajax() {
    error_log('caling plugin ajax');

    $valid_referer = check_ajax_referer( 'statichtmloutput', 'nonce' );

    if ( ! $valid_referer ) {
        error_log('Invalid ajax referer');
    }

    $instance_method = filter_input( INPUT_POST, 'ajax_action' );

    if ( '' !== $instance_method && is_string( $instance_method ) ) {
        error_log("instance method $instance_method");

        $plugin_instance = StaticHTMLOutput\Controller::getInstance();
        call_user_func( [ $plugin_instance, $instance_method ] );
    }
/*
        } elseif ( $ajax_action === 'crawl_site' || $ajax_action === 'crawl_again' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'bitbucket_prepare_export' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'bitbucket_upload_files' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'github_prepare_export' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'github_upload_files' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'test_github' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'gitlab_prepare_export' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'gitlab_upload_files' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'test_gitlab' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'test_bitbucket' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'test_netlify' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'netlify_do_export' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'test_s3' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 's3_prepare_export' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 's3_transfer_files' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'cloudfront_invalidate_all_items' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'test_ftp' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'ftp_prepare_export' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'ftp_transfer_files' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'test_bunny' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'bunnycdn_prepare_export' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'bunnycdn_transfer_files' ) {

            wp_die();
            return null;
        } elseif ( $ajax_action == 'bunnycdn_purge_cache' ) {
*/

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
    WP_CLI::add_command(
        'statichtmloutput options',
        [ 'StaticHTMLOutput\CLI', 'options' ]
    );
}
