<?php

namespace StaticHTMLOutput;

class View {

    /**
     * @var mixed[]
     */
    protected $variables = [];
    /**
     * @var string
     */
    protected $path = null;
    /**
     * @var string
     */
    protected $directory = 'views';
    /**
     * @var string
     */
    protected $extension = '.phtml';
    /**
     * @var string
     */
    protected $template = null;

    public function __construct() {
        // Looking for a basic directory where plugin resides
        list($plugin_dir) = explode( '/', plugin_basename( __FILE__ ) );

        // making up an absolute path to views directory
        $path_array = [ WP_PLUGIN_DIR, $plugin_dir, $this->directory ];

        $this->path = implode( '/', $path_array );
    }

    public function setTemplate( string $tpl ) : View {
        $this->template = $tpl;
        $this->variables = [];
        return $this;
    }

    /**
     * @param mixed $value template variable value
     */
    public function __set( string $name, $value ) : View {
        $this->variables[ $name ] = $value;
        return $this;
    }

    /**
     * @param mixed $value template variable value
     */
    public function assign( string $name, $value ) : View {
        return $this->__set( $name, $value );
    }

    /**
     * @return mixed template variable value
     */
    public function __get( string $name ) {
        $value = array_key_exists( $name, $this->variables ) ?
        $this->variables[ $name ] :
        null;

        return $value;
    }

    public function render() : View {
        $file = $this->path . '/' . $this->template . $this->extension;

        include $file;

        return $this;
    }

    public function fetch() : string {
        ob_start();

        $this->render();
        $contents = (string) ob_get_contents();

        ob_end_clean();

        return $contents;
    }
}

