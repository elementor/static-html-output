<?php

namespace StaticHTMLOutput;

class Options {
    /**
     * @var string
     */
    // phpcs:disable
    public $baseUrl;
    // phpcs:enable
    /**
     * @var mixed
     */
    protected $statichtmloutput_options = [];
    /**
     * @var string
     */
    protected $statichtmloutput_option_key = '';
    /**
     * @var string[]
     */
    protected $statichtmloutput_options_keys = [
        'additionalUrls',
        'baseUrl',
        'baseUrl-bitbucket',
        'baseUrl-bunnycdn',
        'baseUrl-github',
        'baseUrl-gitlab',
        'baseUrl-netlify',
        'baseUrl-s3',
        'baseUrl-zip',
        'baseUrl-zip',
        'basicAuthPassword',
        'basicAuthUser',
        'bbBranch',
        'bbRepo',
        'bbToken',
        'bunnycdnStorageZoneAccessKey',
        'bunnycdnPullZoneAccessKey',
        'bunnycdnPullZoneID',
        'bunnycdnStorageZoneName',
        'bunnycdn_api_host',
        'cfDistributionId',
        'completionEmail',
        'crawl_delay',
        'crawl_increment',
        'crawlPort',
        'delayBetweenAPICalls',
        'deployBatchSize',
        'excludeURLs',
        'ghBranch',
        'ghCommitMessage',
        'ghRepo',
        'ghToken',
        'glBranch',
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
        's3Secret',
        'selected_deployment_option',
        'targetFolder',
        'useBasicAuth',
    ];

    /**
     * @var string[]
     */
    protected $whitelisted_keys = [
        'additionalUrls',
        'baseUrl',
        'baseUrl-bitbucket',
        'baseUrl-bunnycdn',
        'baseUrl-github',
        'baseUrl-gitlab',
        'baseUrl-netlify',
        'baseUrl-s3',
        'baseUrl-zip',
        'baseUrl-zip',
        'basicAuthUser',
        'bbBranch',
        'bbRepo',
        'bunnycdnPullZoneID',
        'bunnycdnStorageZoneName',
        'cfDistributionId',
        'completionEmail',
        'crawl_delay',
        'crawl_increment',
        'crawlPort',
        'delayBetweenAPICalls',
        'deployBatchSize',
        'excludeURLs',
        'ghBranch',
        'ghCommitMessage',
        'ghRepo',
        'glBranch',
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
        'selected_deployment_option',
        'targetFolder',
        'useBasicAuth',
    ];

    public function __construct( string $option_key ) {
        $options = get_option( $option_key );

        if ( false === $options ) {
            $options = [];
        }

        $this->statichtmloutput_options = $options;
        $this->statichtmloutput_option_key = $option_key;
    }

    /**
     * @param mixed[] $value
     */
    public function __set( string $name, $value ) : Options {
        $this->statichtmloutput_options[ $name ] = $value;

        // NOTE: this is required, not certain why, investigate
        // and make more intuitive
        return $this;
    }

    /**
     * @param mixed $value
     */
    public function setOption( string $name, $value ) : Options {
        return $this->__set( $name, $value );
    }

    /**
     * @return mixed option value
     */
    public function __get( string $name ) {
        $value = array_key_exists(
            $name,
            $this->statichtmloutput_options
        ) ? $this->statichtmloutput_options[ $name ] : null;

        return $value;
    }

    /**
     * @return mixed option value
     */
    public function getOption( string $name ) {
        return $this->__get( $name );
    }

    /**
     * @return mixed[] all the options
     */
    public function getAllOptions( bool $reveal_sensitive_values = false ) : array {
        $options_array = [];

        foreach ( $this->statichtmloutput_options_keys as $key ) {

            $value = '*******************';

            if ( in_array( $key, $this->whitelisted_keys ) ) {
                $value = $this->__get( $key );
            } elseif ( $reveal_sensitive_values ) {
                $value = $this->__get( $key );
            }

            $options_array[] = [
                'Option name' => $key,
                'Value' => $value,
            ];
        }

        return $options_array;
    }

    public function optionExists( string $name ) : bool {
        return in_array( $name, $this->statichtmloutput_options_keys );
    }

    public function save() : bool {
        return update_option(
            $this->statichtmloutput_option_key,
            $this->statichtmloutput_options
        );
    }

    public function delete() : bool {
        return delete_option( $this->statichtmloutput_option_key );
    }

    public function saveAllPostData() : void {
        foreach ( $this->statichtmloutput_options_keys as $option ) {
            // TODO: set which fields should get which sanitzation upon saving
            // TODO: validate before save & avoid making empty settings fields
            $this->setOption( $option, filter_input( INPUT_POST, $option ) );
            $this->save();
        }
    }
}

