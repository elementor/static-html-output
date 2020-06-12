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

        $this->path = $this->settings['wp_uploads_path'] . '/static-html-output/';
        $this->name = basename( $this->path );
    }

    public function create() : void {
        if ( ! wp_mkdir_p( $this->path ) ) {
            Logger::l( "Couldn't create archive directory at $this->path" );
        }
    }
}

