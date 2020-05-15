<?php

namespace StaticHTMLOutput;

class ProgressLog {

    public static function l( int $portion, int $total ) : void {
        if ( $total === 0 ) {
            return;
        }

        $target_settings = [
            'wpenv',
        ];

        $wp_uploads_path = '';

        // NOTE: avoiding loading whole PostSettings for speed
        if ( defined( 'WP_CLI' ) ) {
            $settings = DBSettings::get( $target_settings );

            $wp_uploads_path = $settings['wp_uploads_path'];
        } else {
            // @codingStandardsIgnoreStart
            $wp_uploads_path = $_POST['wp_uploads_path'];
            // @codingStandardsIgnoreEnd
        }

        $log_file_path = $wp_uploads_path . '/WP-STATIC-PROGRESS.txt';

        $progress_percent =
            floor( $portion / $total * 100 );

        file_put_contents(
            $log_file_path,
            $progress_percent . PHP_EOL,
            LOCK_EX
        );

        chmod( $log_file_path, 0664 );
    }
}

