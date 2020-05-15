<?php

namespace StaticHTMLOutput;

class StaticHTMLOutput {

    public function loadSettings( $target_settings ) {
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

