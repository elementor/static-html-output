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
            'detection_level',
            'crawl_delay',
            'crawlPort',
        ];

        $key_sets['processing'] = [
            'removeConditionalHeadComments',
            'allowOfflineUsage',
            'baseHREF',
            'rewrite_rules',
            'rename_rules',
            'removeWPMeta',
            'removeWPLinks',
            'useBaseHref',
            'useRelativeURLs',
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

        $key_sets['folder'] = [
            'baseUrl-folder',
            'targetFolder',
        ];

        $key_sets['zip'] = [
            'baseUrl-zip',
            'allowOfflineUsage',
        ];

        $key_sets['github'] = [
            'baseUrl-github',
            'ghBranch',
            'ghPath',
            'ghToken',
            'ghRepo',
            'ghCommitMessage',
        ];

        $key_sets['bitbucket'] = [
            'baseUrl-bitbucket',
            'bbBranch',
            'bbPath',
            'bbToken',
            'bbRepo',
        ];

        $key_sets['gitlab'] = [
            'baseUrl-gitlab',
            'glBranch',
            'glPath',
            'glToken',
            'glProject',
        ];

        $key_sets['ftp'] = [
            'baseUrl-ftp',
            'ftpPassword',
            'ftpRemotePath',
            'ftpServer',
            'ftpPort',
            'ftpTLS',
            'ftpUsername',
            'useActiveFTP',
        ];

        $key_sets['bunnycdn'] = [
            'baseUrl-bunnycdn',
            'bunnycdnStorageZoneAccessKey',
            'bunnycdnPullZoneAccessKey',
            'bunnycdnPullZoneID',
            'bunnycdnStorageZoneName',
            'bunnycdnRemotePath',
        ];

        $key_sets['s3'] = [
            'baseUrl-s3',
            'cfDistributionId',
            's3Bucket',
            's3Key',
            's3Region',
            's3RemotePath',
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

        // @codingStandardsIgnoreStart
        foreach ( $target_keys as $key ) {
            $settings[ $key ] =
                isset( $_POST[ $key ] ) ?
                $_POST[ $key ] :
                null;
        }
        // @codingStandardsIgnoreEnd

        /*
            Settings requiring transformation
        */

        // @codingStandardsIgnoreStart
        $settings['crawl_increment'] =
            isset( $_POST['crawl_increment'] ) ?
            (int) $_POST['crawl_increment'] :
            1;

        // any baseUrl required if creating an offline ZIP
        $settings['baseUrl'] =
            isset( $_POST['baseUrl'] ) ?
            rtrim( $_POST['baseUrl'], '/' ) . '/' :
            'http://OFFLINEZIP.wpsho';
        // @codingStandardsIgnoreEnd

        return array_filter( $settings );
    }
}

