<?php

namespace StaticHTMLOutput;

class WsLog {

    public static function l( string $text ) : void {
        $target_settings = [
            'general',
            'wpenv',
        ];

        $wp_uploads_path = '';
        $settings = '';

        if ( defined( 'WP_CLI' ) ) {
            $settings = DBSettings::get( $target_settings );
        } else {
            $settings = PostSettings::get( $target_settings );
        }

        $wp_uploads_path = $settings['wp_uploads_path'];

        $log_file_path = $wp_uploads_path . '/WP-STATIC-EXPORT-LOG.txt';

        file_put_contents(
            $log_file_path,
            $text . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        chmod( $log_file_path, 0664 );
    }
}

