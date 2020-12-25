<?php

namespace StaticHTMLOutput;

class PostSettings {

    /**
     * @param mixed[] $sets
     * @return mixed[] options
     */
    public static function get( array $sets = [] ) : array {
        $settings = [];
        $key_sets = [];
        $target_keys = [];

        $key_sets['general'] = [
            'baseUrl',
            'selected_deployment_option',
        ];

        $key_sets['crawling'] = [
            'additionalUrls',
            'excludeURLs',
            'useBasicAuth',
            'basicAuthPassword',
            'basicAuthUser',
            'crawl_delay',
            'crawlPort',
        ];

        $key_sets['processing'] = [
            'removeConditionalHeadComments',
            'rewrite_rules',
            'rename_rules',
            'removeWPMeta',
            'removeWPLinks',
            'removeConditionalHeadComments',
            'removeWPMeta',
            'removeWPLinks',
            'removeHTMLComments',
        ];

        $key_sets['advanced'] = [
            'crawl_increment',
            'completionEmail',
            'delayBetweenAPICalls',
            'deployBatchSize',
        ];

        $key_sets['zip'] = [
            'baseUrl-zip',
        ];

        $key_sets['github'] = [
            'baseUrl-github',
            'ghBranch',
            'ghToken',
            'ghRepo',
            'ghCommitMessage',
        ];

        $key_sets['bitbucket'] = [
            'baseUrl-bitbucket',
            'bbBranch',
            'bbToken',
            'bbRepo',
        ];

        $key_sets['gitlab'] = [
            'baseUrl-gitlab',
            'glBranch',
            'glToken',
            'glProject',
        ];

        $key_sets['bunnycdn'] = [
            'baseUrl-bunnycdn',
            'bunnycdnStorageZoneAccessKey',
            'bunnycdnPullZoneAccessKey',
            'bunnycdnPullZoneID',
            'bunnycdnStorageZoneName',
            'bunnycdn_api_host',
        ];

        $key_sets['s3'] = [
            'baseUrl-s3',
            'cfDistributionId',
            's3Bucket',
            's3Key',
            's3Region',
            's3Secret',
        ];

        $key_sets['netlify'] = [
            'baseUrl-netlify',
            'netlifyHeaders',
            'netlifyPersonalAccessToken',
            'netlifyRedirects',
            'netlifySiteID',
        ];

        $key_sets['wpenv'] = [
            'wp_site_url',
            'wp_site_path',
            'wp_site_subdir',
            'wp_uploads_path',
            'wp_uploads_url',
            'baseUrl',
            'wp_active_theme',
            'wp_themes',
            'wp_uploads',
            'wp_plugins',
            'wp_content',
            'wp_inc',
        ];

        foreach ( $sets as $set ) {
            $target_keys = array_merge( $target_keys, $key_sets[ $set ] );
        }

        foreach ( $target_keys as $key ) {
            $post_value = filter_input( INPUT_POST, $key );

            $settings[ $key ] = $post_value ? $post_value : null;
        }

        /*
            Settings requiring transformation
        */
        $crawl_incrment = filter_input( INPUT_POST, 'crawl_increment' );
        $settings['crawl_increment'] = $crawl_incrment ? $crawl_incrment : 1;

        return array_filter( $settings );
    }
}

