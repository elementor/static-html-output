<?php

namespace StaticHTMLOutput;

class Archive extends StaticHTMLOutput {
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $path;

    public function __construct() {
        $this->loadSettings(
            [ 'wpenv' ]
        );

        $this->path = '';
        $this->name = '';
    }

    public function setToCurrentArchive() : void {
        $handle = fopen(
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-CURRENT-ARCHIVE.txt',
            'r'
        );

        if ( ! is_resource( $handle ) ) {
            return;
        }

        $path = stream_get_line( $handle, 0 );

        if ( ! $path ) {
            return;
        }

        $this->path = $path;
        $this->name = basename( $this->path );
    }

    public function currentArchiveExists() : bool {
        return is_file(
            $this->settings['wp_uploads_path'] .
            '/WP2STATIC-CURRENT-ARCHIVE.txt'
        );
    }

    public function create() : void {
        $this->name = $this->settings['wp_uploads_path'] .
            '/wp-static-html-output-' . time();

        $this->path = $this->name . '/';
        $this->name = basename( $this->path );

        if ( wp_mkdir_p( $this->path ) ) {
            $result = file_put_contents(
                $this->settings['wp_uploads_path'] .
                    '/WP2STATIC-CURRENT-ARCHIVE.txt',
                $this->path
            );

            if ( ! $result ) {
                WsLog::l( 'USER WORKING DIRECTORY NOT WRITABLE' );
            }

            chmod(
                $this->settings['wp_uploads_path'] .
                    '/WP2STATIC-CURRENT-ARCHIVE.txt',
                0664
            );
        } else {
            WsLog::l( "Couldn't create archive directory at $this->path" );
        }
    }
}

