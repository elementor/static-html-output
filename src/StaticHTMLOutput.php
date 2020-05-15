<?php

namespace StaticHTMLOutput;

class StaticHTMLOutput {

    /**
     * @var mixed[]
     */
    public $settings;

    /**
     * @param mixed[] $target_settings group of settings we want to load
     */
    public function loadSettings( array $target_settings ) : void {
        $general_settings = [
            'general',
        ];

        $target_settings = array_merge(
            $general_settings,
            $target_settings
        );

        if ( isset( $_POST['selected_deployment_option'] ) ) {
            $this->settings = PostSettings::get( $target_settings );
        } else {
            $this->settings = DBSettings::get( $target_settings );
        }
    }
}

