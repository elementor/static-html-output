<?php

namespace StaticHTMLOutput;

class FileWriter extends StaticHTMLOutput {

    /**
     * @var string
     */
    public $url;
    /**
     * @var string
     */
    public $content;
    /**
     * @var string
     */
    public $file_type;
    /**
     * @var string
     */
    public $content_type;

    public function __construct(
        string $url,
        string $content,
        string $file_type,
        string $content_type
    ) {
        $this->url = $url;
        $this->content = $content;
        $this->file_type = $file_type;
        $this->content_type = $content_type;

        $this->loadSettings(
            [
                'wpenv',
            ]
        );
    }

    public function saveFile( string $archive_dir ) : void {
        $url_info = parse_url( $this->url, PHP_URL_PATH );

        if ( ! $url_info ) {
            return;
        }

        // set what the new path will be based on the given url
        $path_info = $url_info === '/' ? pathinfo( 'index.html' ) : pathinfo( $url_info );

        $directory_in_archive = $path_info['dirname'] ? $path_info['dirname'] : '';

        // url decode dirname
        $directory_in_archive = urldecode( $directory_in_archive );

        if ( ! empty( $this->settings['wp_site_subdir'] ) ) {
            $directory_in_archive = str_replace(
                $this->settings['wp_site_subdir'],
                '',
                $directory_in_archive
            );
        }

        $file_dir = $archive_dir . ltrim( $directory_in_archive, '/' );

        // set filename to index if no extension && base and filename are  same
        if ( empty( $path_info['extension'] ) &&
            $path_info['basename'] === $path_info['filename'] ) {
            $file_dir .= '/' . urldecode( $path_info['basename'] );
            $path_info['filename'] = 'index';
        }

        if ( ! file_exists( $file_dir ) ) {
            wp_mkdir_p( $file_dir );
        }

        $file_extension = '';

        if ( isset( $path_info['extension'] ) ) {
            $file_extension = $path_info['extension'];
        } elseif ( $this->file_type == 'html' ) {
            $file_extension = 'html';
        } elseif ( $this->file_type == 'xml' ) {
            $file_extension = 'html';
        }

        $filename = '';

        // set path for homepage to index.html, else build filename
        if ( $url_info === '/' ) {
            // TODO: isolate and fix the cause requiring this trim:
            $filename = rtrim( $file_dir, '.' ) . 'index.html';
        } else {
            // TODO: deal with this hard to read, but functioning code
            if ( ! empty( $this->settings['wp_site_subdir'] ) ) {
                $file_dir = str_replace(
                    '/' . $this->settings['wp_site_subdir'],
                    '/',
                    $file_dir
                );
            }

            $filename =
                $file_dir . '/' . urldecode( $path_info['filename'] ) .
                '.' . $file_extension;
        }

        $file_contents = $this->content;

        if ( $file_contents ) {
            $write_result = file_put_contents( $filename, $file_contents );

            if ( ! $write_result ) {
                Logger::l( "Failed saving $this->url to $filename" );
                return;
            }

            $modified = chmod( $filename, 0664 );

            if ( ! $modified ) {
                Logger::l( "Failed chmod'ing $filename" );
            }
        } else {
            Logger::l( "Not saving empty file $this->url" );
        }
    }
}

