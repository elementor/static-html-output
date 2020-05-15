<?php

namespace StaticHTMLOutput;

class Options {
    protected $statichtmloutput_options = array();
    protected $statichtmloutput_option_key = null;
    protected $statichtmloutput_options_keys = array(
        'additionalUrls',
        'allowOfflineUsage',
        'baseHREF',
        'baseUrl',
        'baseUrl-bitbucket',
        'baseUrl-bunnycdn',
        'baseUrl-folder',
        'baseUrl-ftp',
        'baseUrl-github',
        'baseUrl-gitlab',
        'baseUrl-netlify',
        'baseUrl-s3',
        'baseUrl-zip',
        'baseUrl-zip',
        'basicAuthPassword',
        'basicAuthUser',
        'bbBranch',
        'bbPath',
        'bbRepo',
        'bbToken',
        'bunnycdnStorageZoneAccessKey',
        'bunnycdnPullZoneAccessKey',
        'bunnycdnPullZoneID',
        'bunnycdnStorageZoneName',
        'bunnycdnRemotePath',
        'cfDistributionId',
        'completionEmail',
        'crawl_delay',
        'crawl_increment',
        'crawlPort',
        'debug_mode',
        'detection_level',
        'delayBetweenAPICalls',
        'deployBatchSize',
        'excludeURLs',
        'ftpPassword',
        'ftpRemotePath',
        'ftpServer',
        'ftpPort',
        'ftpTLS',
        'ftpUsername',
        'ghBranch',
        'ghCommitMessage',
        'ghPath',
        'ghRepo',
        'ghToken',
        'glBranch',
        'glPath',
        'glProject',
        'glToken',
        'netlifyHeaders',
        'netlifyPersonalAccessToken',
        'netlifyRedirects',
        'netlifySiteID',
        'removeConditionalHeadComments',
        'removeHTMLComments',
        'removeWPLinks',
        'removeWPMeta',
        'rewrite_rules',
        'rename_rules',
        's3Bucket',
        's3Key',
        's3Region',
        's3RemotePath',
        's3Secret',
        'selected_deployment_option',
        'targetFolder',
        'useActiveFTP',
        'useBaseHref',
        'useBasicAuth',
        'useRelativeURLs',
    );

    protected $whitelisted_keys = array(
        'additionalUrls',
        'allowOfflineUsage',
        'baseHREF',
        'baseUrl',
        'baseUrl-bitbucket',
        'baseUrl-bunnycdn',
        'baseUrl-folder',
        'baseUrl-ftp',
        'baseUrl-github',
        'baseUrl-gitlab',
        'baseUrl-netlify',
        'baseUrl-s3',
        'baseUrl-zip',
        'baseUrl-zip',
        'basicAuthUser',
        'bbBranch',
        'bbPath',
        'bbRepo',
        'bunnycdnPullZoneID',
        'bunnycdnStorageZoneName',
        'bunnycdnRemotePath',
        'cfDistributionId',
        'completionEmail',
        'crawl_delay',
        'crawl_increment',
        'crawlPort',
        'debug_mode',
        'detection_level',
        'delayBetweenAPICalls',
        'deployBatchSize',
        'excludeURLs',
        'ftpRemotePath',
        'ftpServer',
        'ftpPort',
        'ftpTLS',
        'ftpUsername',
        'ghBranch',
        'ghCommitMessage',
        'ghPath',
        'ghRepo',
        'glBranch',
        'glPath',
        'glProject',
        'netlifyHeaders',
        'netlifyRedirects',
        'netlifySiteID',
        'removeConditionalHeadComments',
        'removeHTMLComments',
        'removeWPLinks',
        'removeWPMeta',
        'rewrite_rules',
        'rename_rules',
        's3Bucket',
        's3Key',
        's3Region',
        's3RemotePath',
        'selected_deployment_option',
        'targetFolder',
        'useActiveFTP',
        'useBaseHref',
        'useBasicAuth',
        'useRelativeURLs',
    );

    public function __construct( $option_key ) {
        $options = get_option( $option_key );

        if ( false === $options ) {
            $options = array();
        }

        $this->statichtmloutput_options = $options;
        $this->statichtmloutput_option_key = $option_key;
    }

    public function __set( $name, $value ) {
        $this->statichtmloutput_options[ $name ] = $value;

        // NOTE: this is required, not certain why, investigate
        // and make more intuitive
        return $this;
    }

    public function setOption( $name, $value ) {
        return $this->__set( $name, $value );
    }

    public function __get( $name ) {
        $value = array_key_exists( $name, $this->statichtmloutput_options ) ?
            $this->statichtmloutput_options[ $name ] : null;
        return $value;
    }

    public function getOption( $name ) {
        return $this->__get( $name );
    }

    public function getAllOptions( $reveal_sensitive_values = false ) {
        $options_array = array();

        foreach ( $this->statichtmloutput_options_keys as $key ) {

            $value = '*******************';

            if ( in_array( $key, $this->whitelisted_keys ) ) {
                $value = $this->__get( $key );
            } elseif ( $reveal_sensitive_values ) {
                $value = $this->__get( $key );
            }

            $options_array[] = array(
                'Option name' => $key,
                'Value' => $value,
            );
        }

        return $options_array;
    }

    public function optionExists( $name ) {
        return in_array( $name, $this->statichtmloutput_options_keys );
    }

    public function save() {
        return update_option(
            $this->statichtmloutput_option_key,
            $this->statichtmloutput_options
        );
    }

    public function delete() {
        return delete_option( $this->statichtmloutput_option_key );
    }

    public function saveAllPostData() {
        foreach ( $this->statichtmloutput_options_keys as $option ) {
            // TODO: set which fields should get which sanitzation upon saving
            // TODO: validate before save & avoid making empty settings fields
            $this->setOption( $option, filter_input( INPUT_POST, $option ) );
            $this->save();
        }
    }
}

