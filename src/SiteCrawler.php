<?php

namespace StaticHTMLOutput;

use Net_URL2;

class SiteCrawler extends StaticHTMLOutput {

    /**
     * @var mixed
     */
    public $progress_bar;
    /**
     * @var string
     */
    public $processed_file;
    /**
     * @var string
     */
    public $file_type;
    /**
     * @var string
     */
    public $response;
    /**
     * @var string
     */
    public $content_type;
    /**
     * @var string
     */
    public $url;
    /**
     * @var string
     */
    public $full_url;
    /**
     * @var string
     */
    public $extension;
    /**
     * @var string
     */
    public $archive_dir;
    /**
     * @var string
     */
    public $list_of_urls_to_crawl_path;
    /**
     * @var mixed[]
     */
    public $urls_to_crawl;
    /**
     * @var string
     */
    public $curl_content_type;
    /**
     * @var string
     */
    public $file_extension;
    /**
     * @var string
     */
    public $crawled_links_file;

    public function __construct() {
        $this->loadSettings(
            [
                'wpenv',
                'crawling',
                'processing',
                'advanced',
            ]
        );

        if ( isset( $this->settings['crawl_delay'] ) ) {
            sleep( $this->settings['crawl_delay'] );
        }

        $this->processed_file = '';
        $this->file_type = '';
        $this->response = '';
        $this->content_type = '';
        $this->url = '';
        $this->extension = '';
        $this->archive_dir = '';
        $this->list_of_urls_to_crawl_path = '';
        $this->urls_to_crawl = [];
    }

    public function crawl_site() : void {
        if ( CrawlQueue::getTotal() > 0 ) {
            $this->crawlABitMore();
        } else {
            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            } elseif ( ! empty( $this->progress_bar ) ) {
                $this->progress_bar->finish();
            }
        }
    }

    public function progressBarTick() : void {
        if ( empty( $this->progress_bar ) ) {
            return;
        }

        $this->progress_bar->tick(
            1,
            sprintf(
                'Processing URLs  %d / %d',
                (int) filter_var( $this->progress_bar->current(), FILTER_SANITIZE_NUMBER_INT ) + 1,
                CrawlLog::getTotalCrawlableURLs()
            )
        );
    }

    public function crawlABitMore() : void {
        $batch_of_links_to_crawl = [];

        $crawlable_urls = CrawlQueue::getTotalCrawlableURLs();

        if ( ! $crawlable_urls ) {
            return;
        }

        // get total CrawlQueue
        $total_urls = CrawlQueue::getTotal();

        // get batch size (smaller of total urls or crawl_increment)
        $batch_size = min( $total_urls, $this->settings['crawl_increment'] );

        // fetch just amount of URLs needed (limit to crawl_increment)
        $this->urls_to_crawl = CrawlQueue::getCrawlablePaths( $batch_size );

        $this->archive_dir = $this->settings['wp_uploads_path'] . '/static-html-output/';

        if ( defined( 'WP_CLI' ) && empty( $this->progress_bar ) ) {
            $this->progress_bar =
                \WP_CLI\Utils\make_progress_bar(
                    sprintf(
                        'Processing URLs  %d / %d',
                        0,
                        CrawlLog::getTotalCrawlableURLs()
                    ),
                    CrawlLog::getTotalCrawlableURLs()
                );
        }

        if ( ! empty( $this->progress_bar ) ) {
            $this->progress_bar->setTotal( CrawlLog::getTotalCrawlableURLs() );
        }

        // TODO: add these to Exclusions table
        $exclusions = [];

        if ( isset( $this->settings['excludeURLs'] ) ) {
            $user_exclusions = explode(
                "\n",
                str_replace( "\r", '', $this->settings['excludeURLs'] )
            );

            $exclusions = array_merge(
                $exclusions,
                $user_exclusions
            );
        }

        foreach ( $this->urls_to_crawl as $link_to_crawl ) {
            $this->url = $link_to_crawl;

            $this->full_url = $this->settings['wp_site_url'] .
                ltrim( $this->url, '/' );

            foreach ( $exclusions as $exclusion ) {
                $exclusion = trim( $exclusion );
                if ( $exclusion != '' ) {
                    if ( false !== strpos( $this->url, $exclusion ) ) {
                        Logger::l(
                            'Excluding ' . $this->url .
                            ' because of rule ' . $exclusion
                        );

                        $url_path = (string) parse_url( $this->url, PHP_URL_PATH );

                        if ( ! $url_path ) {
                            $this->progressBarTick();
                            continue 2;
                        }

                        // TODO: dummy status to denote skipped due to exclusion rule
                        CrawlLog::updateStatus( $url_path, 777 );
                        CrawlQueue::removeURL( $url_path );

                        $this->progressBarTick();
                        continue 2;
                    }
                }
            }

            $this->file_extension = $this->getExtensionFromURL();

            if ( $this->loadFileForProcessing() ) {
                $this->saveFile();
            }

            // TODO: get crawl status and remove URL from CrawlQueue

            // ProgressLog::l( $completed_urls, $total_urls_to_crawl );

            $this->progressBarTick();
        }

        $this->checkIfMoreCrawlingNeeded();
        // reclaim memory after each crawl
        $url_reponse = null;
        unset( $url_reponse );
    }

    public function loadFileForProcessing() : bool {
        $ch = curl_init();

        if ( isset( $this->settings['crawlPort'] ) ) {
            curl_setopt(
                $ch,
                CURLOPT_PORT,
                $this->settings['crawlPort']
            );
        }

        curl_setopt( $ch, CURLOPT_URL, $this->full_url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
        curl_setopt( $ch, CURLOPT_USERAGENT, 'StaticHTMLOutput.com' );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

        if ( isset( $this->settings['useBasicAuth'] ) ) {
            curl_setopt(
                $ch,
                CURLOPT_USERPWD,
                $this->settings['basicAuthUser'] . ':' .
                    $this->settings['basicAuthPassword']
            );
        }

        $this->response = (string) curl_exec( $ch );

        $this->checkForCurlErrors( $this->response, $ch );

        $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        $this->curl_content_type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );

        curl_close( $ch );

        $this->crawled_links_file =
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-CRAWLED-LINKS.txt';

        $good_response_codes = [ 200, 201, 301, 302, 304 ];

        $url_path = (string) parse_url( $this->url, PHP_URL_PATH );

        if ( ! $url_path ) {
            return false;
        }

        CrawlLog::updateStatus( $url_path, $status_code );
        CrawlQueue::removeURL( $url_path );

        if ( ! in_array( $status_code, $good_response_codes ) ) {
            Logger::l( "BAD RESPONSE STATUS ($status_code): $this->url" );

            return false;
        }

        $base_url = $this->settings['baseUrl'];

        $this->detectFileType();

        switch ( $this->file_type ) {
            case 'html':
                // temp workaround while refactoring settings
                // prepare string settings
                $string_settings = [
                    'baseUrl',
                    'rewrite_rules',
                    'selected_deployment_option',
                    'wp_site_url',
                    'wp_uploads_path',
                ];

                foreach ( $string_settings as $setting ) {
                    if ( ! isset( $this->settings[ $setting ] ) ) {
                        $this->settings[ $setting ] = '';
                    }
                }

                // prepare bool settings
                $bool_settings = [
                    'removeConditionalHeadComments',
                    'removeHTMLComments',
                    'removeWPLinks',
                    'removeWPMeta',
                ];

                foreach ( $bool_settings as $setting ) {
                    if ( ! isset( $this->settings[ $setting ] ) ) {
                        $this->settings[ $setting ] = false;
                    }
                }

                $processor = new HTMLProcessor(
                    $this->settings['removeConditionalHeadComments'],
                    $this->settings['removeHTMLComments'],
                    $this->settings['removeWPLinks'],
                    $this->settings['removeWPMeta'],
                    $this->settings['rewrite_rules'],
                    $this->settings['baseUrl'],
                    $this->settings['selected_deployment_option'],
                    $this->settings['wp_site_url'],
                    $this->settings['wp_uploads_path']
                );

                $processed = $processor->processHTML(
                    $this->response,
                    $this->full_url
                );

                if ( $processed ) {
                    $this->processed_file = $processor->getHTML();
                    $this->saveDiscoveredURLs( $processor->getDiscoveredURLs(), $this->full_url );
                }

                break;

            case 'css':
                // temp workaround while refactoring settings
                // prepare string settings
                $string_settings = [
                    'baseUrl',
                    'rewrite_rules',
                    'selected_deployment_option',
                    'wp_site_url',
                    'wp_uploads_path',
                ];

                foreach ( $string_settings as $setting ) {
                    if ( ! isset( $this->settings[ $setting ] ) ) {
                        $this->settings[ $setting ] = '';
                    }
                }

                // prepare bool settings
                $bool_settings = [
                    'removeConditionalHeadComments',
                    'removeHTMLComments',
                    'removeWPLinks',
                    'removeWPMeta',
                ];

                foreach ( $bool_settings as $setting ) {
                    if ( ! isset( $this->settings[ $setting ] ) ) {
                        $this->settings[ $setting ] = false;
                    }
                }
                $processor = new CSSProcessor(
                    $this->settings['removeConditionalHeadComments'],
                    $this->settings['removeHTMLComments'],
                    $this->settings['removeWPLinks'],
                    $this->settings['removeWPMeta'],
                    $this->settings['rewrite_rules'],
                    $this->settings['baseUrl'],
                    $this->settings['selected_deployment_option'],
                    $this->settings['wp_site_url'],
                    $this->settings['wp_uploads_path']
                );

                $processed = $processor->processCSS(
                    $this->response,
                    $this->full_url
                );

                if ( $processed ) {
                    $this->processed_file = $processor->getCSS();
                    $this->saveDiscoveredURLs( $processor->getDiscoveredURLs(), $this->full_url );
                }

                break;

            case 'txt':
            case 'js':
            case 'json':
            case 'xml':
                $processor = new TXTProcessor();

                $processed = $processor->processTXT(
                    $this->response,
                    $this->full_url
                );

                if ( $processed ) {
                    $this->processed_file = $processor->getTXT();
                }

                break;

            default:
                $this->processed_file = $this->response;

                break;
        }

        if ( defined( 'WP_CLI' ) ) {
            \WP_CLI::debug( sprintf( 'Processing %s', $this->url ) );
        }

        return true;
    }

    public function checkIfMoreCrawlingNeeded() : void {
        $remaining_urls_to_crawl = CrawlQueue::getTotal();

        if ( $remaining_urls_to_crawl > 0 ) {
            if ( ! defined( 'WP_CLI' ) ) {
                echo $remaining_urls_to_crawl;
            } else {
                $this->crawl_site();
            }
        } else {
            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            } elseif ( ! empty( $this->progress_bar ) ) {
                $this->progress_bar->finish();
            }
        }
    }

    public function saveFile() : void {
        $file_writer = new FileWriter(
            $this->url,
            $this->processed_file,
            $this->file_type,
            $this->content_type
        );

        $file_writer->saveFile( $this->archive_dir );

        if ( defined( 'WP_CLI' ) ) {
            \WP_CLI::debug( sprintf( 'Saved %s', $this->url ) );
        }
    }

    public function getExtensionFromURL() : string {
        $url_path = parse_url( $this->url, PHP_URL_PATH );

        if ( ! $url_path ) {
            return '';
        }

        $extension = pathinfo( $url_path, PATHINFO_EXTENSION );

        if ( ! $extension ) {
            return '';
        }

        return $extension;
    }

    public function detectFileType() : void {
        if ( $this->file_extension ) {
            $this->file_type = $this->file_extension;
        } else {
            $type = $this->content_type =
                $this->curl_content_type;

            if ( stripos( $type, 'text/html' ) !== false ) {
                $this->file_type = 'html';
            } elseif ( stripos( $type, 'rss+xml' ) !== false ) {
                $this->file_type = 'xml';
            } elseif ( stripos( $type, 'text/xml' ) !== false ) {
                $this->file_type = 'xml';
            } elseif ( stripos( $type, 'application/xml' ) !== false ) {
                $this->file_type = 'xml';
            } elseif ( stripos( $type, 'application/json' ) !== false ) {
                $this->file_type = 'json';
            } else {
                Logger::l(
                    'no filetype inferred from content-type: ' .
                    $this->curl_content_type .
                    ' url: ' . $this->url
                );
            }
        }
    }

    /**
     * @param resource $curl_handle to the resource
     */
    public function checkForCurlErrors( string $response, $curl_handle ) : void {
        if ( ! $response ) {
            $response = curl_error( $curl_handle );
            Logger::l(
                'cURL error:' .
                stripslashes( $response )
            );
        }
    }

    /**
     * @param string[] $discovered_urls from a processed page
     */
    public function saveDiscoveredURLs( array $discovered_urls, string $parent_page ) : void {
        if ( ! $discovered_urls ) {
            return;
        }

        // get all from CrawlLog
        $known_urls = CrawlLog::getCrawlablePaths();

        // filter only new URLs
        $new_urls = array_diff( $discovered_urls, $known_urls );

        if ( ! $new_urls ) {
            return;
        }

        $page_path = (string) parse_url( $parent_page, PHP_URL_PATH );

        // TODO: also add new URLs to CrawlLog
        CrawlLog::addUrls( $new_urls, 'discovered on: ' . $page_path, 0 );
        CrawlQueue::addUrls( $new_urls );
    }
}
