<?php

namespace StaticHTMLOutput;

class StaticHTMLOutput {

    public function loadSettings( $target_settings ) {
        $general_settings = array(
            'general',
        );

        $target_settings = array_merge(
            $general_settings,
            $target_settings
        );

        if ( isset( $_POST['selected_deployment_option'] ) ) {
            $this->settings = WPSHO_PostSettings::get( $target_settings );
        } else {
            $this->settings = WPSHO_DBSettings::get( $target_settings );
        }
    }
}

