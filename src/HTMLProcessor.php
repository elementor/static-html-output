<?php

namespace StaticHTMLOutput;

use DOMDocument;
use DOMComment;
use Net_URL2;
use DOMXPath;
use DOMElement;

class HTMLProcessor extends StaticHTMLOutput {

    /**
     * @var bool
     */
    public $remove_conditional_head_comments;
    /**
     * @var bool
     */
    public $remove_html_comments;
    /**
     * @var bool
     */
    public $remove_wp_links;
    /**
     * @var bool
     */
    public $remove_wp_meta;
    /**
     * @var string
     */
    public $rewrite_rules;
    /**
     * @var string Destination URL
     */
    public $base_url;
    /**
     * @var string
     */
    public $selected_deployment_option;
    /**
     * @var string
     */
    public $wp_site_url;
    /**
     * @var string
     */
    public $wp_uploads_path;
    /**
     * @var string
     */
    public $raw_html;
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
    /**
     * @var string[]
     */
    public $tlds;

    public function __construct(
        bool $remove_conditional_head_comments = false,
        bool $remove_html_comments = false,
        bool $remove_wp_links = false,
        bool $remove_wp_meta = false,
        string $rewrite_rules = '',
        string $base_url,
        string $selected_deployment_option = 'zip',
        string $wp_site_url,
        string $wp_uploads_path
    ) {
        $this->remove_conditional_head_comments = $remove_conditional_head_comments;
        $this->remove_html_comments = $remove_html_comments;
        $this->remove_wp_links = $remove_wp_links;
        $this->remove_wp_meta = $remove_wp_meta;
        $this->rewrite_rules = $rewrite_rules;
        $this->base_url = $base_url;
        $this->selected_deployment_option = $selected_deployment_option;
        $this->wp_site_url = $wp_site_url;
        $this->wp_uploads_path = $wp_uploads_path;
        $this->processed_urls = [];
        $this->tlds = [
            'app',
            'aero',
            'asia',
            'biz',
            'cat',
            'com',
            'coop',
            'dev',
            'info',
            'int',
            'jobs',
            'mobi',
            'museum',
            'name',
            'net',
            'org',
            'pro',
            'tel',
            'travel',
            'xxx',
            'edu',
            'gov',
            'mil',
            'ac',
            'ad',
            'ae',
            'af',
            'ag',
            'ai',
            'al',
            'am',
            'an',
            'ao',
            'aq',
            'ar',
            'as',
            'at',
            'au',
            'aw',
            'ax',
            'az',
            'ba',
            'bb',
            'bd',
            'be',
            'bf',
            'bg',
            'bh',
            'bi',
            'bj',
            'bm',
            'bn',
            'bo',
            'br',
            'bs',
            'bt',
            'bv',
            'bw',
            'by',
            'bz',
            'ca',
            'cc',
            'cd',
            'cf',
            'cg',
            'ch',
            'ci',
            'ck',
            'cl',
            'cm',
            'cn',
            'co',
            'cr',
            'cs',
            'cu',
            'cv',
            'cx',
            'cy',
            'cz',
            'dd',
            'de',
            'dj',
            'dk',
            'dm',
            'do',
            'dz',
            'ec',
            'ee',
            'eg',
            'eh',
            'er',
            'es',
            'et',
            'eu',
            'fi',
            'fj',
            'fk',
            'fm',
            'fo',
            'fr',
            'ga',
            'gb',
            'gd',
            'ge',
            'gf',
            'gg',
            'gh',
            'gi',
            'gl',
            'gm',
            'gn',
            'gp',
            'gq',
            'gr',
            'gs',
            'gt',
            'gu',
            'gw',
            'gy',
            'hk',
            'hm',
            'hn',
            'hr',
            'ht',
            'hu',
            'id',
            'ie',
            'il',
            'im',
            'in',
            'io',
            'iq',
            'ir',
            'is',
            'it',
            'je',
            'jm',
            'jo',
            'jp',
            'ke',
            'kg',
            'kh',
            'ki',
            'km',
            'kn',
            'kp',
            'kr',
            'kw',
            'ky',
            'kz',
            'la',
            'lb',
            'lc',
            'li',
            'lk',
            'lr',
            'ls',
            'lt',
            'lu',
            'lv',
            'ly',
            'ma',
            'mc',
            'md',
            'me',
            'mg',
            'mh',
            'mk',
            'ml',
            'mm',
            'mn',
            'mo',
            'mp',
            'mq',
            'mr',
            'ms',
            'mt',
            'mu',
            'mv',
            'mw',
            'mx',
            'my',
            'mz',
            'na',
            'nc',
            'ne',
            'nf',
            'ng',
            'ni',
            'nl',
            'no',
            'np',
            'nr',
            'nu',
            'nz',
            'om',
            'pa',
            'pe',
            'pf',
            'pg',
            'ph',
            'pk',
            'pl',
            'pm',
            'pn',
            'pr',
            'ps',
            'pt',
            'pw',
            'py',
            'qa',
            're',
            'ro',
            'rs',
            'ru',
            'rw',
            'sa',
            'sb',
            'sc',
            'sd',
            'se',
            'sg',
            'sh',
            'si',
            'sj',
            'sk',
            'sl',
            'sm',
            'sn',
            'so',
            'sr',
            'ss',
            'st',
            'su',
            'sv',
            'sy',
            'sz',
            'tc',
            'td',
            'tf',
            'tg',
            'th',
            'tj',
            'tk',
            'tl',
            'tm',
            'tn',
            'to',
            'tp',
            'tr',
            'tt',
            'tv',
            'tw',
            'tz',
            'ua',
            'ug',
            'uk',
            'us',
            'uy',
            'uz',
            'va',
            'vc',
            've',
            'vg',
            'vi',
            'vn',
            'vu',
            'wf',
            'ws',
            'ye',
            'yt',
            'yu',
            'za',
            'zm',
            'zw',
        ];
    }

    public function processHTML( string $html_document, string $page_url ) : bool {
        if ( $html_document == '' ) {
            return false;
        }

        // instantiate the XML body here
        $this->xml_doc = new DOMDocument( '1.0', 'UTF-8' );
        $this->xml_doc->formatOutput = false;
        $this->xml_doc->preserveWhiteSpace = true;
        $this->xml_doc->strictErrorChecking = false;

        // NOTE: set placeholder_url to same protocol as target
        // making it easier to rewrite URLs without considering protocol
        $this->destination_protocol =
            $this->getTargetSiteProtocol( $this->base_url );

        $this->placeholder_url =
            $this->destination_protocol . 'PLACEHOLDER.wpsho/';

        $site_url = rtrim( $this->wp_site_url, '/' );
        $placeholder_url = rtrim( $this->placeholder_url, '/' );

        // initial rewrite of all site URLs to placeholder URLs
        $this->raw_html = $this->rewriteSiteURLsToPlaceholder(
            $html_document,
            $site_url,
            $placeholder_url
        );

        // rewrite page_url to placeholder URL host
        $page_url = $this->rewriteSiteURLsToPlaceholder(
            $page_url,
            $site_url,
            $placeholder_url
        );

        $this->page_url = new Net_URL2( $page_url );

        $this->discovered_urls = [];

        // PERF: 70% of function time
        // prevent warnings, via https://stackoverflow.com/a/9149241/1668057
        libxml_use_internal_errors( true );

        $this->xml_doc->loadHTML(
            $this->raw_html,
            LIBXML_COMPACT | LIBXML_HTML_NOIMPLIED | LIBXML_NOERROR |
            LIBXML_NOWARNING | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_PARSEHUGE |
            LIBXML_ERR_NONE
        );

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
                case 'style':
                    $this->processStyle( $element );
                    break;
                // process other elements
                default:
                    $elements_with_src_tags = [
                        'amp-img',
                        'audio',
                        'bgsound',
                        'embed',
                        'frame',
                        'iframe',
                        'ilayer',
                        'layer',
                        'script',
                        'source',
                        'video',
                        'xml',
                    ];

                    if ( in_array( $element->tagName, $elements_with_src_tags ) ) {
                        $this->processGenericSrc( $element );
                    }

                    $elements_with_href_tags = [ 'base', 'area' ];

                    if ( in_array( $element->tagName, $elements_with_src_tags ) ) {
                        $this->processGenericHref( $element );
                    }

                    $elements_with_style_attributes = [ 'p', 'div', 'a', 'img', 'b', 'i' ];

                    if ( in_array( $element->tagName, $elements_with_style_attributes ) ) {
                        $this->processStyleAttribute( $element );
                    }

                    break;
            }
        }

        $this->stripHTMLComments();

        return true;
    }

    public function processLink( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'href' );
        $this->forceHTTPS( $element, 'href' );
        $this->addDiscoveredURL( $element->getAttribute( 'href' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );

        if ( $this->remove_wp_links ) {
            $relative_links_to_rm = [
                'shortlink',
                'pingback',
                'EditURI',
                'wlwmanifest',
                'index',
                'profile',
                'start',
                'index',
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
            } elseif (
                $link_rel === 'dns-prefetch' &&
                strpos( $element->getAttribute( 'href' ), 's.w.org' ) !== false
            ) {
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

        if ( ! $this->isValidURL( (string) $url ) ) {
            return;
        }

        if ( $this->isInternalLink( (string) $url ) ) {
            $path = (string) parse_url( (string) $url, PHP_URL_PATH );

            if ( empty( $path ) || $path[0] !== '/' ) {
                return;
            }

            if ( trim( $path ) === '/' ) {
                return;
            }

            $this->discovered_urls[] = $path;
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
                $this->addDiscoveredURL( (string) $url );
                $url = $this->rewriteWPPathsSrcSetURL( (string) $url );
                $url = $this->rewriteBaseURLSrcSetURL( $url );
            } else {
                if ( $this->destination_protocol === 'https://' ) {
                    // force https, don't remove query string
                    if ( strpos( $url, 'http://' ) !== false ) {
                        $url = str_replace(
                            'http://',
                            'https://',
                            $url
                        );
                    }
                }
            }

            $new_src_set[] = "{$url} {$dimension}";
        }

        $element->setAttribute( 'srcset', implode( ',', $new_src_set ) );
    }

    public function processImage( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'src' );
        $this->forceHTTPS( $element, 'src' );
        $this->addDiscoveredURL( $element->getAttribute( 'src' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
    }

    public function processGenericSrc( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'src' );
        $this->forceHTTPS( $element, 'src' );
        $this->addDiscoveredURL( $element->getAttribute( 'src' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
    }

    public function processGenericHref( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'href' );
        $this->forceHTTPS( $element, 'href' );
        $this->addDiscoveredURL( $element->getAttribute( 'href' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
    }

    public function processStyle( DOMElement $element ) : void {
        $inline_stylesheet = $element->nodeValue;

        if ( ! $inline_stylesheet ) {
            return;
        }

        $processor = new CSSProcessor(
            $this->remove_conditional_head_comments,
            $this->remove_html_comments,
            $this->remove_wp_links,
            $this->remove_wp_meta,
            $this->rewrite_rules,
            $this->base_url,
            $this->selected_deployment_option,
            $this->wp_site_url,
            $this->wp_uploads_path
        );

        $processed = $processor->processCSS(
            $inline_stylesheet,
            $this->page_url
        );

        if ( $processed ) {
            $rewritten_css = $processor->getCSS();

            $element->nodeValue = $rewritten_css;
        }
    }

    public function stripHTMLComments() : void {
        if ( $this->remove_html_comments ) {
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
                if ( $this->remove_conditional_head_comments ) {
                    $element_parent = $node->parentNode;

                    if ( ! $element_parent ) {
                        return;
                    }

                    $element_parent->removeChild( $node );
                }
            }
        }
    }

    public function processScript( DOMElement $element ) : void {
        $this->normalizeURL( $element, 'src' );
        $this->forceHTTPS( $element, 'src' );
        $this->addDiscoveredURL( $element->getAttribute( 'src' ) );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
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
        $this->addDiscoveredURL( $url );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
    }

    public function processMeta( DOMElement $element ) : void {
        // TODO: detect meta redirects here + build list for rewriting
        if ( $this->remove_wp_meta ) {
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
        $this->forceHTTPS( $element, 'content' );
        $this->addDiscoveredURL( $url );
        $this->rewriteWPPaths( $element );
        $this->rewriteBaseURL( $element );
    }


    // make link absolute, using current page to determine full path
    public function normalizeURL( DOMElement $element, string $attribute ) : void {
        $original_link = $element->getAttribute( $attribute );

        if ( $this->isInternalLink( $original_link ) ) {
            $abs = $this->page_url->resolve( $original_link );
            $element->setAttribute( $attribute, $abs );
        }
    }

    public function forceHTTPS( DOMElement $element, string $attribute ) : void {
        if ( $this->destination_protocol !== 'https://' ) {
            return;
        }

        $original_link = $element->getAttribute( $attribute );

        if ( $this->isInternalLink( $original_link ) ) {
            return;
        }

        if ( strpos( $original_link, 'http://' ) === false ) {
            return;
        }

        $https_link = str_replace(
            'http://',
            'https://',
            $original_link
        );

        $element->setAttribute( $attribute, $https_link );
    }

    public function isInternalLink( string $link ) : bool {
        if ( ! $link ) {
            return false;
        }

        // if first char is . let's call that internal link
        if ( $link[0] === '.' ) {
            return true;
        }

        // if first char is / and second char isn't / or \, let's call that internal
        if ( $link[0] === '/' ) {
            if ( isset( $link[1] ) && $link[1] !== '/' && $link[1] !== '\\' ) {
                return true;
            }
        }

        // is there a hostname in our link to compare to site URL's host?
        $link_host = parse_url( $link, PHP_URL_HOST );

        if ( $link_host ) {
            $domain = $this->placeholder_url;

            // check link is same host as $this->url and not a subdomain
            return $link_host === parse_url( $domain, PHP_URL_HOST );
        }

        $extension = pathinfo( $link, PATHINFO_EXTENSION );

        if ( ! $extension ) {
            if ( substr( $link, -1 ) !== '/' ) {
                return false;
            }
        }

        // #91 don't detect domain names as assets
        if ( in_array( $extension, $this->tlds ) ) {
            return false;
        }

        // match anything without a colon, comma or space ie favicon.ico, not mailto:, viewport
        if (
            ( strpos( $link, ':' ) === false ) &&
            ( strpos( $link, ' ' ) === false ) &&
            ( strpos( $link, ',' ) === false )
        ) {
            return true;
        }

        return false;
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

    public function rewriteEncodedSiteURLAndHostName( string $processed_html ) : string {
        $site_url = rtrim( $this->wp_site_url, '/\\' );
        $destination_url = rtrim( $this->base_url, '/\\' );

        $encoded_wp_site_url = urlencode( $site_url );
        $encoded_destination_url = '';

        if ( $this->destination_protocol === 'https://' ) {
            $encoded_destination_url =
                urlencode(
                    str_replace(
                        'http://',
                        'https://',
                        $destination_url
                    )
                );
        } else {
            $encoded_destination_url = urlencode( $destination_url );
        }

        return str_replace(
            $encoded_wp_site_url,
            $encoded_destination_url,
            $processed_html
        );

    }

    public function detectUnchangedPlaceholderURLs( string $processed_html ) : string {
        // run just on the hostname of each
        $placeholder_host = 'PLACEHOLDER.wpsho';
        $destination_host = (string) parse_url( $this->base_url, PHP_URL_HOST );

        if ( strpos( $processed_html, $placeholder_host ) !== false ) {
            // bulk replace hosts
            $processed_html = str_replace(
                $placeholder_host,
                $destination_host,
                $processed_html
            );
        }

        // force http -> https if destination is https
        if ( $this->destination_protocol === 'https://' ) {
            $processed_html = str_replace(
                'http://' . $destination_host,
                'https://' . $destination_host,
                $processed_html
            );
        }

        return $processed_html;
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
        $destination_url = addcslashes( $this->base_url, '/' );

        if ( ! $this->rewrite_rules ) {
            $this->rewrite_rules = '';
        }

        // add base URL to rewrite_rules
        $this->rewrite_rules .=
            PHP_EOL .
                $site_url . ',' .
                $destination_url;

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->rewrite_rules )
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
        if ( ! $this->rewrite_rules ) {
            return $url_to_change;
        }

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->rewrite_rules )
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
        if ( ! $this->rewrite_rules ) {
            return;
        }

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->rewrite_rules )
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

    /**
     * @return string[] Discovered URLs
     */
    public function getDiscoveredURLs() : array {
        $discovered_urls = array_unique( $this->discovered_urls );
        array_filter( $discovered_urls );
        sort( $discovered_urls );

        if ( ! $discovered_urls ) {
            return [];
        }

        return $discovered_urls;
    }

    public function getHTML() : string {
        $processed_html = (string) $this->xml_doc->saveHtml();

        return $this->rewriteEncodedSiteURLAndHostName(
            $this->detectUnchangedPlaceholderURLs(
                $this->detectEscapedSiteURLs( $processed_html )
            )
        );
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

    public function rewriteSiteURLsToPlaceholder(
        string $raw_html,
        string $site_url,
        string $placeholder_url
    ) : string {

        // WP creates Canonical links which don't include the Site URL's custom port
        $site_url_host_without_port = parse_url( $site_url, PHP_URL_HOST );

        $patterns = [
            $site_url,
            addcslashes( $site_url, '/' ),
            $this->getProtocolRelativeURL(
                $site_url
            ),
            '//' . $site_url_host_without_port,
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
            $placeholder_url,
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
            $this->base_url,
            addcslashes( $this->base_url, '/' ),
            $this->getProtocolRelativeURL(
                $this->base_url
            ),
            $this->getProtocolRelativeURL(
                rtrim( $this->base_url, '/' )
            ),
            $this->getProtocolRelativeURL(
                $this->base_url . '//'
            ),
            $this->getProtocolRelativeURL(
                addcslashes( $this->base_url, '/' )
            ),
        ];

        return $replacements;
    }

    public function processStyleAttribute( DOMElement $element ) : void {
        $style_attribute = $element->getAttribute( 'style' );

        if ( ! $style_attribute ) {
            return;
        }

        // convert element rule into parsable CSS document (wrap in * selector)
        $css_doc = '* {' . PHP_EOL;
        $css_doc .= $style_attribute . PHP_EOL;
        $css_doc .= '}' . PHP_EOL;

        $processor = new CSSProcessor(
            $this->remove_conditional_head_comments,
            $this->remove_html_comments,
            $this->remove_wp_links,
            $this->remove_wp_meta,
            $this->rewrite_rules,
            $this->base_url,
            $this->selected_deployment_option,
            $this->wp_site_url,
            $this->wp_uploads_path
        );

        $processed = $processor->processCSS(
            $css_doc,
            $this->page_url
        );

        if ( $processed ) {
            $rewritten_css = $processor->getCSS();

            // trim our fake selector from rule
            $parts = explode( PHP_EOL, $rewritten_css );

            $rewritten_script_attribute = $parts[1];

            $element->setAttribute( 'style', $rewritten_script_attribute );
        }
    }
}

