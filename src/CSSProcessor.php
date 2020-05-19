<?php

namespace StaticHTMLOutput;

use Sabberworm;
use Net_URL2;

class CSSProcessor extends StaticHTMLOutput {

    /**
     * @var string
     */
    public $destination_protocol_relative_url;
    /**
     * @var bool
     */
    public $allow_offline_usage;
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
     * @var bool
     */
    public $use_relative_urls;
    /**
     * @var string
     */
    public $base_href;
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
    public $placeholder_url;
    /**
     * @var string
     */
    public $raw_css;
    /**
     * @var string
     */
    public $page_url;
    /**
     * @var Sabberworm\CSS\CSSList\Document
     */
    public $css_doc;
    /**
     * @var string[]
     */
    public $discovered_urls;
    /**
     * @var bool
     */
    public $harvest_new_urls;
    /**
     * @var string[]
     */
    public $processed_urls;

    public function __construct(
        bool $allow_offline_usage = false,
        bool $remove_conditional_head_comments = false,
        bool $remove_html_comments = false,
        bool $remove_wp_links = false,
        bool $remove_wp_meta = false,
        string $rewrite_rules = '',
        bool $use_relative_urls = false,
        string $base_href,
        string $base_url,
        string $selected_deployment_option = 'folder',
        string $wp_site_url,
        string $wp_uploads_path
    ) {
        $this->allow_offline_usage = $allow_offline_usage;
        $this->remove_conditional_head_comments = $remove_conditional_head_comments;
        $this->remove_html_comments = $remove_html_comments;
        $this->remove_wp_links = $remove_wp_links;
        $this->remove_wp_meta = $remove_wp_meta;
        $this->rewrite_rules = $rewrite_rules;
        $this->use_relative_urls = $use_relative_urls;
        $this->base_href = $base_href;
        $this->base_url = $base_url;
        $this->selected_deployment_option = $selected_deployment_option;
        $this->wp_site_url = $wp_site_url;
        $this->wp_uploads_path = $wp_uploads_path;
        $this->processed_urls = [];
    }

    public function processCSS( string $css_document, string $page_url ) : bool {
        if ( $css_document == '' ) {
            return false;
        }

        $protocol = $this->getTargetSiteProtocol( $this->base_url );

        $this->placeholder_url = $protocol . 'PLACEHOLDER.wpsho/';

        $site_url = rtrim( $this->wp_site_url, '/' );
        $placeholder_url = rtrim( $this->placeholder_url, '/' );

        // initial rewrite of all site URLs to placeholder URLs
        $this->raw_css = $this->rewriteSiteURLsToPlaceholder(
            $css_document,
            $site_url,
            $placeholder_url
        );

        $css_parser = new Sabberworm\CSS\Parser( $this->raw_css );
        $this->css_doc = $css_parser->parse();

        $this->page_url = new Net_URL2( $page_url );

        $this->detectIfURLsShouldBeHarvested();

        $this->discovered_urls = [];

        foreach ( $this->css_doc->getAllValues() as $node_value ) {
            if ( $node_value instanceof Sabberworm\CSS\Value\URL ) {
                $original_link = $node_value->getURL();

                $original_link = trim( trim( $original_link, "'" ), '"' );

                $inline_img =
                    strpos( $original_link, 'data:image' );

                if ( $inline_img !== false ) {
                    continue;
                }

                $this->addDiscoveredURL( $original_link );

                if ( $this->isInternalLink( $original_link ) ) {
                    if ( ! $this->rewrite_rules ) {
                        $this->rewrite_rules = '';
                    }

                    // add base URL to rewrite_rules
                    $this->rewrite_rules .=
                        PHP_EOL . $this->placeholder_url . ',' . $this->base_url;

                    $this->rewrite_rules .=
                        PHP_EOL . $this->getProtocolRelativeURL( $this->placeholder_url ) .
                        ',' . $this->getProtocolRelativeURL( $this->base_url );

                    $rewrite_from = [];
                    $rewrite_to = [];

                    $rewrite_rules = explode(
                        "\n",
                        str_replace(
                            "\r",
                            '',
                            $this->rewrite_rules
                        )
                    );

                    foreach ( $rewrite_rules as $rewrite_rule_line ) {
                        if ( $rewrite_rule_line ) {
                            list($from, $to) =
                                explode( ',', $rewrite_rule_line );

                            $rewrite_from[] = $from;
                            $rewrite_to[] = $to;
                        }
                    }

                    $rewritten_url = str_replace(
                        $rewrite_from,
                        $rewrite_to,
                        $original_link
                    );

                    $rewritten_url = new Sabberworm\CSS\Value\CSSString(
                        $rewritten_url
                    );

                    $node_value->setURL( $rewritten_url );
                }
            }
        }

        $this->writeDiscoveredURLs();

        return true;
    }

    public function isInternalLink( string $link, string $domain = '' ) : bool {
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

    public function getCSS() : string {
        return $this->css_doc->render();
    }

    public function rewriteSiteURLsToPlaceholder(
        string $raw_css,
        string $site_url,
        string $placeholder_url
    ) : string {
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
            $raw_css
        );

        return $rewritten_source;
    }

    public function detectIfURLsShouldBeHarvested() : void {
        if ( defined( 'WP_CLI' ) ) {
            if ( defined( 'CRAWLING_DISCOVERED' ) ) {
                return;
            } else {
                $this->harvest_new_urls = true;
            }
        } else {
            $ajax_method = filter_input( INPUT_POST, 'ajax_action' );

            $this->harvest_new_urls = $ajax_method === 'crawl_site';
        }
    }

    public function addDiscoveredURL( string $url ) : void {
        // trim any query strings or anchors
        $url = strtok( $url, '#' );
        $url = trim( (string) strtok( (string) $url, '?' ) );

        if ( ! $url ) {
            return;
        }

        if ( $this->harvest_new_urls ) {
            if ( ! $this->isValidURL( $url ) ) {
                return;
            }

            if ( $this->isInternalLink( $url ) ) {
                $discovered_url_without_site_url =
                    str_replace(
                        rtrim( $this->placeholder_url, '/' ),
                        '',
                        $url
                    );

                $this->discovered_urls[] = $discovered_url_without_site_url;
            }
        }
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
            $this->wp_uploads_path .
                '/WP-STATIC-DISCOVERED-URLS.txt',
            PHP_EOL .
                implode( PHP_EOL, array_unique( $this->discovered_urls ) ),
            FILE_APPEND | LOCK_EX
        );

        chmod(
            $this->wp_uploads_path .
                '/WP-STATIC-DISCOVERED-URLS.txt',
            0664
        );
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

    public function getTargetSiteProtocol( string $url ) : string {
        $protocol = '//';

        if ( strpos( $url, 'https://' ) !== false ) {
            $protocol = 'https://';
        } elseif ( strpos( $url, 'http://' ) !== false ) {
            $protocol = 'http://';
        } else {
            $protocol = '//';
        }

        return $protocol;
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
}

