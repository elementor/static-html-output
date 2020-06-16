<?php

namespace StaticHTMLOutput;

class V6Cleanup {
    public static function cleanup() : void {
        global $wpdb;

        // TODO: check if wp2static-options version is 6.6.x
        // this will ignore WP2Static 1.0-alpha versions from having DB messed up
        $upload_dir_info = wp_upload_dir();
        $wp_upload_path = trailingslashit( $upload_dir_info['basedir'] );

        // rm obosolete txt files used in v6
        $txt_files = (array) glob( $wp_upload_path . 'WP-STATIC-*.txt' );
        $txt_files_alternate = (array) glob( $wp_upload_path . 'WP2STATIC-*.txt' );
        $archives = (array) glob( $wp_upload_path . 'wp-static*' );

        $files = array_merge(
            $txt_files,
            $txt_files_alternate,
            $archives
        );

        foreach ( $files as $file ) {
            $file = (string) $file;

            if ( is_dir( $file ) ) {
                Logger::l( 'Deleting Version 6 directory: ' . $file );
                FilesHelper::delete_dir_with_files( $file );
            } elseif ( is_file( $file ) ) {
                $deleted_file = unlink( $file );

                if ( $deleted_file ) {
                    Logger::l( 'Deleted Version 6 file: ' . $file );
                }
            }
        }
    }
}

