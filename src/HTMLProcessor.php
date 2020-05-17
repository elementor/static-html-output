<?php

namespace StaticHTMLOutput;

use DOMDocument;
use DOMComment;
use Net_URL2;
use DOMXPath;
use DOMElement;

class HTMLProcessor extends StaticHTMLOutput {

    /**
     * @var string
     */
    public $raw_html;
    /**
     * @var bool
     */
    public $harvest_new_urls;
    /**
     * @var bool
     */
    public $base_tag_exists;
    /**
     * @var string
     */
    public $destination_protocol;
    /**
     * @var string
     */
    public $destination_protocol_relative_url;
    /**
     * @var string
     */
    public $placeholder_url;
    /**
     * @var Net_URL2
     */
    public $page_url;
    /**
     * @var DOMDocument
     */
    public $xml_doc;
    /**
     * @var string[]
     */
    public $discovered_urls;
    /**
     * @var string[]
     */
    public $processed_urls;

    public function __construct() {
        $this->loadSettings(
            [
                'github',
                'wpenv',
                'processing',
                'advanced',
            ]
        );

        $this->processed_urls = [];
    }

    public function processHTML( string $html_document, string $page_url ) : bool {
        if ( $html_document == '' ) {
            return false;
        }

        // instantiate the XML body here
        $this->xml_doc = new DOMDocument();

        // NOTE: set placeholder_url to same protocol as target
        // making it easier to rewrite URLs without considering protocol
        $this->destination_protocol =
            $this->getTargetSiteProtocol( $this->settings['baseUrl'] );

        $this->placeholder_url =
            $this->destination_protocol . 'PLACEHOLDER.wpsho/';

        // initial rewrite of all site URLs to placeholder URLs
        $this->raw_html = $this->rewriteSiteURLsToPlaceholder(
            $html_document
        );

        // detect if a base tag exists while in the loop
        // use in later base href creation to decide: append or create
        $this->base_tag_exists = false;

        $this->page_url = new Net_URL2( $page_url );

        $this->detectIfURLsShouldBeHarvested();

        $this->discovered_urls = [];

        // PERF: 70% of function time
        // prevent warnings, via https://stackoverflow.com/a/9149241/1668057
        libxml_use_internal_errors( true );
        $this->xml_doc->loadHTML( $this->raw_html );
        libxml_use_internal_errors( false );

        // start the full iterator here, along with copy of dom
        $elements = iterator_to_array(
            $this->xml_doc->getElementsByTagName( '*' )
        );

        foreach ( $elements as $element ) {
            switch ( $element->tagName ) {
                case 'meta':
                    $this->processMeta( $element );
                    break;
                case 'a':
                    $this->processAnchor( $element );
                    break;
                case 'img':
                    $this->processImage( $element );
                    $this->processImageSrcSet( $element );
                    break;
                case 'head':
                    $this->processHead( $element );
                    break;
                case 'link':
                    // NOTE: not to confuse with anchor element
                    $this->processLink( $element );
                    break;
                case 'script':
                    // can contain src=,
                    // can also contain URLs within scripts
                    // and escaped urls
                    $this->processScript( $element );
                    break;

                    // TODO: how about other places that can contain URLs
                    // data attr, reacty stuff, etc?
            }
        }

        if ( $this->base_tag_exists ) {
            $base_element =
                $this->xml_doc->getElementsByTagName( 'base' )->item( 0 );

            if ( $base_element ) {
                if ( $this->shouldCreateBaseHREF() ) {
                    $base_element->setAttribute(
                        'href',
                        $this->settings['baseHREF']
                    );
                } else {
                    $element_parent = $base_element->parentNode;

                    if ( $element_parent ) {
                        $element_parent->removeChild( $base_element );
                    }
                }
            }
        } elseif ( $this->shouldCreateBaseHREF() ) {
            $base_element = $this->xml_doc->createElement( 'base' );
            $base_element->setAttribute(
                'href',
                $this->settings['baseHREF']
            );
            $head_element =
                $this->xml_doc->getElementsByTagName( 'head' )->item( 0 );
            if ( $head_element ) {
                $first_head_child = $head_element->firstChild;

                if ( $first_head_child ) {
                    $head_element->insertBefore(
                        $base_element,
                        $first_head_child
                    );
                }
            } else {
                WsLog::l(
                    'WARNING: no valid head elemnent to attach base to: ' .
                        $this->page_url
                );
            }
        }

        // strip comments
        $this->stripHTMLComments();

        $this->writeDiscoveredURLs();

        return true;
    }

    public function detectIfURLsShouldBeHarvested() : void {
        if ( ! defined( 'WP_CLI' ) ) {
            // @codingStandardsIgnoreStart
            $this->harvest_new_urls = (
                 $_POST['ajax_action'] === 'crawl_site'
            );
            // @codingStandardsIgnoreEnd
        } else {
            // we shouldn't harvest any while we're in the second crawl
            if ( defined( 'CRAWLING_DISCOVERED' ) ) {
                return;
            } else {
                $this->harvest_new_urls = true;
            }
        }
    }

    public function processLink( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'href' );
        $this->removeQueryStringFromInternalLink( $element );
        $this->addDiscoveredURL( $element->getAttribute( 'href' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
        $this->convertToRelativeURL( $element );
        $this->convertToOfflineURL( $element );

        if ( isset( $this->settings['removeWPLinks'] ) ) {
            $relative_links_to_rm = [
                'shortlink',
                'canonical',
                'pingback',
                'alternate',
                'EditURI',
                'wlwmanifest',
                'index',
                'profile',
                'prev',
                'next',
                'wlwmanifest',
            ];

            $link_rel = $element->getAttribute( 'rel' );

            $element_parent = $element->parentNode;

            if ( ! $element_parent ) {
                return;
            }

            if ( in_array( $link_rel, $relative_links_to_rm ) ) {
                $element_parent->removeChild( $element );
            } elseif ( strpos( $link_rel, '.w.org' ) !== false ) {
                $element_parent->removeChild( $element );
            }
        }
    }

    public function isValidURL( string $url ) : bool {
        // NOTE: not using native URL filter as it won't accept
        // non-ASCII URLs, which we want to support
        $url = trim( $url );

        if ( $url == '' ) {
            return false;
        }

        if ( strpos( $url, '.php' ) !== false ) {
            return false;
        }

        if ( strpos( $url, ' ' ) !== false ) {
            return false;
        }

        if ( $url[0] == '#' ) {
            return false;
        }

        return true;
    }

    public function addDiscoveredURL( string $url ) : void {
        // trim any query strings or anchors
        $url = strtok( (string) $url, '#' );
        $url = strtok( (string) $url, '?' );

        if ( in_array( (string) $url, $this->processed_urls ) ) {
            return;
        }

        if ( trim( (string) $url ) === '' ) {
            return;
        }

        $this->processed_urls[] = (string) $url;

        if ( $this->harvest_new_urls ) {
            if ( ! $this->isValidURL( (string) $url ) ) {
                return;
            }

            if ( $this->isInternalLink( (string) $url ) ) {
                $discovered_url_without_site_url =
                    str_replace(
                        rtrim( $this->placeholder_url, '/' ),
                        '',
                        (string) $url
                    );

                $this->discovered_urls[] = $discovered_url_without_site_url;
            }
        }
    }

    public function processImageSrcSet( DOMElement $element ) : void {
        if ( ! $element->hasAttribute( 'srcset' ) ) {
            return;
        }

        $new_src_set = [];

        $src_set = $element->getAttribute( 'srcset' );

        $src_set_lines = explode( ',', $src_set );

        foreach ( $src_set_lines as $src_set_line ) {
            $all_pieces = explode( ' ', $src_set_line );

            // rm empty elements
            $pieces = array_filter( $all_pieces );
            // reindex array
            $pieces = array_values( $pieces );

            $url = $pieces[0];
            $dimension = $pieces[1];

            // normalize urls
            if ( $this->isInternalLink( $url ) ) {
                $url = $this->page_url->resolve( $url );

                // rm query string
                $url = strtok( $url, '?' );
                $this->addDiscoveredURL( (string) $url );
                $url = $this->rewriteWPPathsSrcSetURL( (string) $url );
                $url = $this->rewriteBaseURLSrcSetURL( $url );
                $url = $this->convertToRelativeURLSrcSetURL( $url );
                $url = $this->convertToOfflineURLSrcSetURL( $url );
            }

            $new_src_set[] = "{$url} {$dimension}";
        }

        $element->setAttribute( 'srcset', implode( ',', $new_src_set ) );
    }

    public function processImage( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'src' );
        $this->removeQueryStringFromInternalLink( $element );
        $this->addDiscoveredURL( $element->getAttribute( 'src' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
        $this->convertToRelativeURL( $element );
        $this->convertToOfflineURL( $element );
    }

    public function stripHTMLComments() : void {
        if ( isset( $this->settings['removeHTMLComments'] ) ) {
            $xpath = new DOMXPath( $this->xml_doc );

            $comments = $xpath->query( '//comment()' );

            if ( ! $comments ) {
                return;
            }

            foreach ( $comments as $comment ) {
                $element_parent = $comment->parentNode;

                if ( ! $element_parent ) {
                    return;
                }

                $element_parent->removeChild( $comment );
            }
        }
    }

    public function processHead( DOMElement $element ) : void {
        $head_elements = iterator_to_array(
            $element->childNodes
        );

        foreach ( $head_elements as $node ) {
            if ( $node instanceof DOMComment ) {
                if (
                    isset( $this->settings['removeConditionalHeadComments'] )
                ) {
                    $element_parent = $node->parentNode;

                    if ( ! $element_parent ) {
                        return;
                    }

                    $element_parent->removeChild( $node );
                }
            } elseif ( isset( $node->tagName ) ) {
                if ( $node->tagName === 'base' ) {
                    // as smaller iteration to run conditional
                    // against here
                    $this->base_tag_exists = true;
                }
            }
        }
    }

    public function processScript( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'src' );
        $this->removeQueryStringFromInternalLink( $element );
        $this->addDiscoveredURL( $element->getAttribute( 'src' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
        $this->convertToRelativeURL( $element );
        $this->convertToOfflineURL( $element );
    }

    public function processAnchor( DOMElement $element ) : void {
        $url = $element->getAttribute( 'href' );

        // TODO: DRY this up/move to higher exec position
        // early abort invalid links as early as possible
        // to save overhead/potential errors
        // apply to other functions
        if ( $url[0] === '#' ) {
            return;
        }

        if ( substr( $url, 0, 7 ) == 'mailto:' ) {
            return;
        }

        if ( ! $this->isInternalLink( $url ) ) {
            return;
        }

        $this->normalizeURL( $element, 'href' );
        $this->removeQueryStringFromInternalLink( $element );
        $this->addDiscoveredURL( $url );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
        $this->convertToRelativeURL( $element );
        $this->convertToOfflineURL( $element );
    }

    public function processMeta( DOMElement $element ) : void {
        // TODO: detect meta redirects here + build list for rewriting
        if ( isset( $this->settings['removeWPMeta'] ) ) {
            $meta_name = $element->getAttribute( 'name' );

            if ( strpos( $meta_name, 'generator' ) !== false ) {
                $element_parent = $element->parentNode;

                if ( ! $element_parent ) {
                    return;
                }

                $element_parent->removeChild( $element );

                return;
            }

            if ( strpos( $meta_name, 'robots' ) !== false ) {
                $content = $element->getAttribute( 'content' );

                if ( strpos( $content, 'noindex' ) !== false ) {
                    $element_parent = $element->parentNode;

                    if ( ! $element_parent ) {
                        return;
                    }

                    $element_parent->removeChild( $element );
                }
            }
        }

        $url = $element->getAttribute( 'content' );
        $this->normalizeURL( $element, 'content' );
        $this->removeQueryStringFromInternalLink( $element );
        $this->addDiscoveredURL( $url );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
        $this->convertToRelativeURL( $element );
        $this->convertToOfflineURL( $element );
    }

    public function writeDiscoveredURLs() : void {
        // @codingStandardsIgnoreStart
        if ( isset( $_POST['ajax_action'] ) &&
            $_POST['ajax_action'] === 'crawl_again' ) {
            return;
        }
        // @codingStandardsIgnoreEnd

        if ( defined( 'WP_CLI' ) ) {
            if ( defined( 'CRAWLING_DISCOVERED' ) ) {
                return;
            }
        }

        file_put_contents(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS.txt',
            PHP_EOL .
                implode( PHP_EOL, array_unique( $this->discovered_urls ) ),
            FILE_APPEND | LOCK_EX
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS.txt',
            0664
        );
    }

    // make link absolute, using current page to determine full path
    public function normalizeURL( DOMElement $element, string $attribute ) : void {
        $original_link = $element->getAttribute( $attribute );

        if ( $this->isInternalLink( $original_link ) ) {
            $abs = $this->page_url->resolve( $original_link );
            $element->setAttribute( $attribute, $abs );
        }

    }

    public function isInternalLink( string $link, string $domain = '' ) : bool {
        if ( ! $domain ) {
            $domain = $this->placeholder_url;
        }

        // TODO: apply only to links starting with .,..,/,
        // or any with just a path, like banana.png
        // check link is same host as $this->url and not a subdomain
        $is_internal_link = parse_url( $link, PHP_URL_HOST ) === parse_url(
            $domain,
            PHP_URL_HOST
        );

        return $is_internal_link;
    }

    public function removeQueryStringFromInternalLink( DOMElement $element ) : void {
        $attribute_to_change = '';
        $url_to_change = '';

        if ( $element->hasAttribute( 'href' ) ) {
            $attribute_to_change = 'href';
        } elseif ( $element->hasAttribute( 'src' ) ) {
            $attribute_to_change = 'src';
        } elseif ( $element->hasAttribute( 'content' ) ) {
            $attribute_to_change = 'content';
        } else {
            return;
        }

        $url_to_change = $element->getAttribute( $attribute_to_change );

        if ( $this->isInternalLink( $url_to_change ) ) {
            // strip anything from the ? onwards
            // https://stackoverflow.com/a/42476194/1668057
            $element->setAttribute(
                $attribute_to_change,
                (string) strtok( $url_to_change, '?' )
            );
        }
    }

    public function detectEscapedSiteURLs( string $processed_html ) : string {
        // NOTE: this does return the expected http:\/\/172.18.0.3
        // but your error log may escape again and
        // show http:\\/\\/172.18.0.3
        $escaped_site_url = addcslashes( $this->placeholder_url, '/' );

        if ( strpos( $processed_html, $escaped_site_url ) !== false ) {
            return $this->rewriteEscapedURLs( $processed_html );
        }

        return $processed_html;
    }

    public function detectUnchangedPlaceholderURLs( string $processed_html ) : string {
        $placeholder_url = $this->placeholder_url;

        if ( strpos( $processed_html, $placeholder_url ) !== false ) {
            return $this->rewriteUnchangedPlaceholderURLs(
                $processed_html
            );
        }

        return $processed_html;
    }

    public function rewriteUnchangedPlaceholderURLs( string $processed_html ) : string {
        if ( ! isset( $this->settings['rewrite_rules'] ) ) {
            $this->settings['rewrite_rules'] = '';
        }

        $placeholder_url = rtrim( $this->placeholder_url, '/' );
        $destination_url = rtrim(
            $this->settings['baseUrl'],
            '/'
        );

        // add base URL to rewrite_rules
        $this->settings['rewrite_rules'] .=
            PHP_EOL .
                $placeholder_url . ',' .
                $destination_url;

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->settings['rewrite_rules'] )
        );

        foreach ( $rewrite_rules as $rewrite_rule_line ) {
            if ( $rewrite_rule_line ) {
                list($from, $to) = explode( ',', $rewrite_rule_line );

                $rewrite_from[] = $from;
                $rewrite_to[] = $to;
            }
        }

        $rewritten_source = str_replace(
            $rewrite_from,
            $rewrite_to,
            $processed_html
        );

        return $rewritten_source;
    }

    public function rewriteEscapedURLs( string $processed_html ) : string {
        // NOTE: fix input HTML, which can have \ slashes modified to %5C
        $processed_html = str_replace(
            '%5C/',
            '\\/',
            $processed_html
        );

        /*
        This function will be a bit more costly. To cover bases like:

         data-images="[&quot;https:\/\/mysite.example.com\/wp...
        from the onepress(?) theme, for example

        */
        $site_url = addcslashes( $this->placeholder_url, '/' );
        $destination_url = addcslashes( $this->settings['baseUrl'], '/' );

        if ( ! isset( $this->settings['rewrite_rules'] ) ) {
            $this->settings['rewrite_rules'] = '';
        }

        // add base URL to rewrite_rules
        $this->settings['rewrite_rules'] .=
            PHP_EOL .
                $site_url . ',' .
                $destination_url;

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->settings['rewrite_rules'] )
        );

        foreach ( $rewrite_rules as $rewrite_rule_line ) {
            if ( $rewrite_rule_line ) {
                list($from, $to) = explode( ',', $rewrite_rule_line );

                $rewrite_from[] = addcslashes( $from, '/' );
                $rewrite_to[] = addcslashes( $to, '/' );
            }
        }

        $rewritten_source = str_replace(
            $rewrite_from,
            $rewrite_to,
            $processed_html
        );

        return $rewritten_source;

    }

    public function rewriteWPPathsSrcSetURL( string $url_to_change ) : string {
        if ( ! isset( $this->settings['rewrite_rules'] ) ) {
            return $url_to_change;
        }

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->settings['rewrite_rules'] )
        );

        foreach ( $rewrite_rules as $rewrite_rule_line ) {
            list($from, $to) = explode( ',', $rewrite_rule_line );

            $rewrite_from[] = $from;
            $rewrite_to[] = $to;
        }

        $rewritten_url = str_replace(
            $rewrite_from,
            $rewrite_to,
            $url_to_change
        );

        return $rewritten_url;
    }

    public function rewriteWPPaths( DOMElement $element ) : void {
        if ( ! isset( $this->settings['rewrite_rules'] ) ) {
            return;
        }

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->settings['rewrite_rules'] )
        );

        foreach ( $rewrite_rules as $rewrite_rule_line ) {
            list($from, $to) = explode( ',', $rewrite_rule_line );

            $rewrite_from[] = $from;
            $rewrite_to[] = $to;
        }

        // array of: wp-content/themes/twentyseventeen/,contents/ui/theme/
        // for each of these, addd the rewrite_from and rewrite_to to their
        // respective arrays
        $attribute_to_change = '';
        $url_to_change = '';

        if ( $element->hasAttribute( 'href' ) ) {
            $attribute_to_change = 'href';
        } elseif ( $element->hasAttribute( 'src' ) ) {
            $attribute_to_change = 'src';
        } elseif ( $element->hasAttribute( 'content' ) ) {
            $attribute_to_change = 'content';
        } else {
            return;
        }

        $url_to_change = $element->getAttribute( $attribute_to_change );

        if ( $this->isInternalLink( $url_to_change ) ) {
            // rewrite URLs, starting with longest paths down to shortest
            // TODO: is the internal link check needed here or these
            // arr values are already normalized?
            $rewritten_url = str_replace(
                $rewrite_from,
                $rewrite_to,
                $url_to_change
            );

            $element->setAttribute( $attribute_to_change, $rewritten_url );
        }
    }

    public function getHTML() : string {
        $processed_html = $this->xml_doc->saveHtml();

        // process the resulting HTML as text
        $processed_html = $this->detectEscapedSiteURLs( (string) $processed_html );
        $processed_html = $this->detectUnchangedPlaceholderURLs(
            $processed_html
        );

        $processed_html = html_entity_decode(
            $processed_html,
            ENT_QUOTES,
            'UTF-8'
        );

        // Note: double-decoding to be safe
        $processed_html = html_entity_decode(
            $processed_html,
            ENT_QUOTES,
            'UTF-8'
        );

        return $processed_html;
    }

    public function convertToRelativeURLSrcSetURL( string $url_to_change ) : string {
        if ( ! $this->shouldUseRelativeURLs() ) {
            return $url_to_change;
        }

        $site_root = '';

        $relative_url = str_replace(
            $this->settings['baseUrl'],
            $site_root,
            $url_to_change
        );

        return $relative_url;
    }

    public function convertToRelativeURL( DOMElement $element ) : void {
        if ( ! $this->shouldUseRelativeURLs() ) {
            return;
        }

        if ( $element->hasAttribute( 'href' ) ) {
            $attribute_to_change = 'href';
        } elseif ( $element->hasAttribute( 'src' ) ) {
            $attribute_to_change = 'src';
        } elseif ( $element->hasAttribute( 'content' ) ) {
            $attribute_to_change = 'content';
        } else {
            return;
        }

        $url_to_change = $element->getAttribute( $attribute_to_change );

        $site_root = '';

        // check it actually needs to be changed
        if ( $this->isInternalLink(
            $url_to_change,
            $this->settings['baseUrl']
        ) ) {
            $rewritten_url = str_replace(
                $this->settings['baseUrl'],
                $site_root,
                $url_to_change
            );

            $element->setAttribute( $attribute_to_change, $rewritten_url );
        }
    }

    public function convertToOfflineURLSrcSetURL( string $url_to_change ) : string {
        if ( ! $this->shouldCreateOfflineURLs() ) {
            return $url_to_change;
        }

        $current_page_path_to_root = '';
        $current_page_path = parse_url( $this->page_url, PHP_URL_PATH );
        $number_of_segments_in_path = explode( '/', (string) $current_page_path );
        $num_dots_to_root = count( $number_of_segments_in_path ) - 2;

        for ( $i = 0; $i < $num_dots_to_root; $i++ ) {
            $current_page_path_to_root .= '../';
        }

        if ( ! $this->isInternalLink(
            $url_to_change
        ) ) {
            return $url_to_change;
        }

        $rewritten_url = str_replace(
            $this->placeholder_url,
            '',
            $url_to_change
        );

        $offline_url = $current_page_path_to_root . $rewritten_url;

        // add index.html if no extension
        if ( substr( $offline_url, -1 ) === '/' ) {
            // TODO: check XML/RSS case
            $offline_url .= 'index.html';
        }

        return $offline_url;
    }

    public function convertToOfflineURL( DOMElement $element ) : void {
        if ( ! $this->shouldCreateOfflineURLs() ) {
            return;
        }

        if ( $element->hasAttribute( 'href' ) ) {
            $attribute_to_change = 'href';
        } elseif ( $element->hasAttribute( 'src' ) ) {
            $attribute_to_change = 'src';
        } elseif ( $element->hasAttribute( 'content' ) ) {
            $attribute_to_change = 'content';
        } else {
            return;
        }

        $url_to_change = $element->getAttribute( $attribute_to_change );
        $current_page_path_to_root = '';
        $current_page_path = parse_url( $this->page_url, PHP_URL_PATH );
        $number_of_segments_in_path = explode( '/', (string) $current_page_path );
        $num_dots_to_root = count( $number_of_segments_in_path ) - 2;

        for ( $i = 0; $i < $num_dots_to_root; $i++ ) {
            $current_page_path_to_root .= '../';
        }

        if ( ! $this->isInternalLink(
            $url_to_change
        ) ) {
            return;
        }

        $rewritten_url = str_replace(
            $this->placeholder_url,
            '',
            $url_to_change
        );

        $offline_url = $current_page_path_to_root . $rewritten_url;

        // add index.html if no extension
        if ( substr( $offline_url, -1 ) === '/' ) {
            // TODO: check XML/RSS case
            $offline_url .= 'index.html';
        }

        $element->setAttribute( $attribute_to_change, $offline_url );
    }

    // TODO: move some of these URLs into settings to avoid extra calls
    public function getProtocolRelativeURL( string $url ) : string {
        $this->destination_protocol_relative_url = str_replace(
            [
                'https:',
                'http:',
            ],
            [
                '',
                '',
            ],
            $url
        );

        return $this->destination_protocol_relative_url;
    }

    public function rewriteBaseURLSrcSetURL( string $url_to_change ) : string {
        $rewritten_url = str_replace(
            $this->getBaseURLRewritePatterns(),
            $this->getBaseURLRewritePatterns(),
            $url_to_change
        );

        return $rewritten_url;
    }

    public function rewriteBaseURL( DOMElement $element ) : void {
        if ( $element->hasAttribute( 'href' ) ) {
            $attribute_to_change = 'href';
        } elseif ( $element->hasAttribute( 'src' ) ) {
            $attribute_to_change = 'src';
        } elseif ( $element->hasAttribute( 'content' ) ) {
            $attribute_to_change = 'content';
        } else {
            return;
        }

        $url_to_change = $element->getAttribute( $attribute_to_change );

        // check it actually needs to be changed
        if ( $this->isInternalLink( $url_to_change ) ) {
            $rewritten_url = str_replace(
                $this->getBaseURLRewritePatterns(),
                $this->getBaseURLRewritePatterns(),
                $url_to_change
            );

            $element->setAttribute( $attribute_to_change, $rewritten_url );
        }
    }

    public function getTargetSiteProtocol( string $url ) : string {
        $this->destination_protocol = '//';

        if ( strpos( $url, 'https://' ) !== false ) {
            $this->destination_protocol = 'https://';
        } elseif ( strpos( $url, 'http://' ) !== false ) {
            $this->destination_protocol = 'http://';
        } else {
            $this->destination_protocol = '//';
        }

        return $this->destination_protocol;
    }

    public function rewriteSiteURLsToPlaceholder( string $raw_html ) : string {
        $site_url = rtrim( $this->settings['wp_site_url'], '/' );
        $placeholder_url = rtrim( $this->placeholder_url, '/' );

        $patterns = [
            $site_url,
            addcslashes( $site_url, '/' ),
            $this->getProtocolRelativeURL(
                $site_url
            ),
            $this->getProtocolRelativeURL(
                $site_url . '//'
            ),
            $this->getProtocolRelativeURL(
                addcslashes( $site_url, '/' )
            ),
        ];

        $replacements = [
            $placeholder_url,
            addcslashes( $placeholder_url, '/' ),
            $this->getProtocolRelativeURL(
                $placeholder_url
            ),
            $this->getProtocolRelativeURL(
                $placeholder_url . '/'
            ),
            $this->getProtocolRelativeURL(
                addcslashes( $placeholder_url, '/' )
            ),
        ];

        $rewritten_source = str_replace(
            $patterns,
            $replacements,
            $raw_html
        );

        return $rewritten_source;
    }

    public function shouldUseRelativeURLs() : bool {
        if ( ! isset( $this->settings['useRelativeURLs'] ) ) {
            return false;
        }

        // NOTE: relative URLs should not be used when creating an offline ZIP
        if ( isset( $this->settings['allowOfflineUsage'] ) ) {
            return false;
        }

        return true;
    }

    public function shouldCreateBaseHREF() : bool {
        if ( empty( $this->settings['baseHREF'] ) ) {
            return false;
        }

        // NOTE: base HREF should not be set when creating an offline ZIP
        if ( isset( $this->settings['allowOfflineUsage'] ) ) {
            return false;
        }

        return true;
    }

    public function shouldCreateOfflineURLs() : bool {
        if ( ! isset( $this->settings['allowOfflineUsage'] ) ) {
            return false;
        }

        if ( $this->settings['selected_deployment_option'] != 'zip' ) {
            return false;
        }

        return true;
    }

    /**
     * @return mixed[] string replacement patterns
     */
    public function getBaseURLRewritePatterns() : array {
        $patterns = [
            $this->placeholder_url,
            addcslashes( $this->placeholder_url, '/' ),
            $this->getProtocolRelativeURL(
                $this->placeholder_url
            ),
            $this->getProtocolRelativeURL(
                $this->placeholder_url
            ),
            $this->getProtocolRelativeURL(
                $this->placeholder_url . '/'
            ),
            $this->getProtocolRelativeURL(
                addcslashes( $this->placeholder_url, '/' )
            ),
        ];

        return $patterns;
    }

    /**
     * @return mixed[] string replacement patterns
     */
    public function getBaseURLRewriteReplacements() : array {
        $replacements = [
            $this->settings['baseUrl'],
            addcslashes( $this->settings['baseUrl'], '/' ),
            $this->getProtocolRelativeURL(
                $this->settings['baseUrl']
            ),
            $this->getProtocolRelativeURL(
                rtrim( $this->settings['baseUrl'], '/' )
            ),
            $this->getProtocolRelativeURL(
                $this->settings['baseUrl'] . '//'
            ),
            $this->getProtocolRelativeURL(
                addcslashes( $this->settings['baseUrl'], '/' )
            ),
        ];

        return $replacements;
    }
}

