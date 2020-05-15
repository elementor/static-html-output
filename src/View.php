<?php

namespace StaticHTMLOutput;

class View {

    protected $variables = [];
    protected $path = null;
    protected $directory = 'views';
    protected $extension = '.phtml';
    protected $template = null;

    public function __construct() {
        // Looking for a basic directory where plugin resides
        list($plugin_dir) = explode( '/', plugin_basename( __FILE__ ) );

        // making up an absolute path to views directory
        $path_array = [ WP_PLUGIN_DIR, $plugin_dir, $this->directory ];

        $this->path = implode( '/', $path_array );
    }

    public function setTemplate( $tpl ) {
        $this->template = $tpl;
        $this->variables = [];
        return $this;
    }

    public function __set( $name, $value ) {
        $this->variables[ $name ] = $value;
        return $this;
    }

    public function assign( $name, $value ) {
        return $this->__set( $name, $value );
    }

    public function __get( $name ) {
        $value = array_key_exists( $name, $this->variables ) ?
        $this->variables[ $name ] :
        null;

        return $value;
    }

    public function render() {
        $file = $this->path . '/' . $this->template . $this->extension;

        include $file;

        return $this;
    }

    public function fetch() {
        ob_start();

        $this->render();
        $contents = ob_get_contents();

        ob_end_clean();

        return $contents;
    }
}

