<?php

namespace StaticHTMLOutput;

class MimeTypes {

    public static function guess_type( string $file ) : string {
        $wp_mime_types = wp_get_mime_types();

        // add useful mimetypes missing from defaults
        // TODO; (may have been added by others via filter), should checking first
        // so as not to override user's intended overriding of types
        $wp_mime_types['webp'] = 'image/webp';

        $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

        if ( array_key_exists( $extension, $wp_mime_types ) ) {
            return $wp_mime_types[ $extension ];
        } else {
            // look for match in keys, as WP mime uses regex 'mpeg|mpg|mpe'
            foreach ( $wp_mime_types as $key => $mime_type ) {
                if ( strpos( $key, '|' ) === false ) {
                    continue;
                }

                $extensions = explode( '|', $key );

                if ( in_array( $extension, $extensions ) ) {
                    return $wp_mime_types[ $key ];
                }
            }
        }

        return 'application/octet-stream';
    }
}
