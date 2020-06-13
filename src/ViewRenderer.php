<?php

namespace StaticHTMLOutput;

class ViewRenderer {

    public static function renderCrawlQueue() : void {
        if ( ! is_admin() ) {
            http_response_code( 403 );
            die( 'Forbidden' );
        }

        $view = [];
        $view['urls'] = CrawlQueue::getCrawlablePaths();

        require_once WP2STATIC_PATH . 'views/crawl-queue-page.php';
    }
}
