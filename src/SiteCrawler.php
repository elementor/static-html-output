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

    public function generate_discovered_links_list() : void {
        $second_crawl_file_path = $this->settings['wp_uploads_path'] .
        '/WP-STATIC-2ND-CRAWL-LIST.txt';

        $already_crawled = file(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-INITIAL-CRAWL-LIST.txt',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ( ! $already_crawled ) {
            $already_crawled = [];
        }

        $unique_discovered_links = [];

        $discovered_links_file = $this->settings['wp_uploads_path'] .
            '/WP-STATIC-DISCOVERED-URLS.txt';

        if ( is_file( $discovered_links_file ) ) {
            $discovered_links = file(
                $discovered_links_file,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            if ( ! $discovered_links ) {
                $discovered_links = [];
            }

            $unique_discovered_links = array_unique( $discovered_links );
            sort( $unique_discovered_links );
        }

        file_put_contents(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-LOG.txt',
            implode( PHP_EOL, $unique_discovered_links )
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-LOG.txt',
            0664
        );

        file_put_contents(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-TOTAL.txt',
            count( $unique_discovered_links )
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-TOTAL.txt',
            0664
        );

        $discovered_links = array_diff(
            $unique_discovered_links,
            $already_crawled
        );

        if ( ! empty( $this->progress_bar ) ) {
            $this->progress_bar->finish();
            $this->progress_bar = \WP_ClI\Utils\make_progress_bar(
                'Crawling discovered links',
                count( $discovered_links )
            );
        }

        file_put_contents(
            $second_crawl_file_path,
            implode( PHP_EOL, $discovered_links )
        );

        chmod( $second_crawl_file_path, 0664 );

        copy(
            $second_crawl_file_path,
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt'
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt',
            0664
        );
    }

    public function crawl_site() : void {
        if ( CrawlQueue::getTotal() > 0 ) {
            $this->crawlABitMore();
        } else {
            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        }
    }

    public function crawlABitMore() : void {
        $batch_of_links_to_crawl = [];

        $crawl_list = CrawlQueue::getCrawlablePaths();

        if ( ! $crawl_list ) {
            return;
        }

        // get total CrawlQueue
        $total_urls = CrawlQueue::getTotal();

        // get batch size (smaller of total urls or crawl_increment)
        $batch_size = min( $total_urls, $this->settings['crawl_increment'] );

        // fetch just amount of URLs needed (limit to crawl_increment)
        $this->urls_to_crawl = CrawlQueue::getCrawlablePaths( $batch_size );

        $this->archive_dir = $this->settings['wp_uploads_path'] . '/static-html-output/';

        // TODO: modify this to show Detected / Crawled URL progress
        // if ( defined( 'WP_CLI' ) && empty( $this->progress_bar ) ) {
        //     $this->progress_bar =
        //         \WP_CLI\Utils\make_progress_bar( 'Crawling site', $total_urls_to_crawl );
        // }

        // TODO: add these to Exclusions table
        $exclusions = [ 'wp-json' ];

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

        Logger::l(
            'Exclusion rules ' . implode( PHP_EOL, $exclusions )
        );

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
                            continue 2;
                        }

                        // TODO: dummy status to denote skipped due to exclusion rule
                        CrawlLog::updateStatus( $url_path, 777 );
                        CrawlQueue::removeURL( $url_path );

                        // TODO: reimplement progress bar
                        // if ( ! empty( $this->progress_bar ) ) {
                        //     $this->progress_bar->tick();
                        // }

                        // skip the outer foreach loop
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

            // TODO: reimplement progress bar
            // if ( ! empty( $this->progress_bar ) ) {
            //     $this->progress_bar->tick();
            // }
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
}
