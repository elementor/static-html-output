<?php

namespace StaticHTMLOutput;

use PHPUnit\Framework\TestCase;
use Sabberworm;

final class CSSProcessorTest extends TestCase {

    /**
     * @covers StaticHTMLOutput\CSSProcessor::__construct
     * @covers StaticHTMLOutput\CSSProcessor::processCSS
     * @covers StaticHTMLOutput\CSSProcessor::addDiscoveredURL
     * @covers StaticHTMLOutput\CSSProcessor::detectIfURLsShouldBeHarvested
     * @covers StaticHTMLOutput\CSSProcessor::getCSS
     * @covers StaticHTMLOutput\CSSProcessor::getProtocolRelativeURL
     * @covers StaticHTMLOutput\CSSProcessor::getTargetSiteProtocol
     * @covers StaticHTMLOutput\CSSProcessor::isInternalLink
     * @covers StaticHTMLOutput\CSSProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\CSSProcessor::writeDiscoveredURLs

     * @dataProvider cssSampleContents
     */
    public function testParsingStylesheets( string $raw_css, string $parsed_css ) {
        /*
            $link should match $domain

            $domain defaults to placeholder_url

            we've rewritten all URLs before here to use the
            placeholder one, so internal link usually(always?)
            means it matches our placeholder domain

            TODO: rename function to reflect what it's now doing

        */
        $css_processor = new CSSProcessor(
            false, // $allow_offline_usage = false
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            false, // $use_relative_urls = false
            '', // $base_href
            'https://deploysite.com/', // $base_url
            '', // $selected_deployment_option = 'folder'
            'http://localsite.com/', // $wp_site_url
            '/tmp/' // $wp_uploads_path
        );

        $css_processor->processCSS(
            $raw_css,
            'http://localsite.com/a-page/'
        );

        $css_processor->getCSS();

        $this->assertEquals(
            $parsed_css,
            $css_processor->getCSS()
        );
    }

    public function cssSampleContents() {
        return [
            'background img FQU' => [
                'body { background-image: url("http://localsite.com/wp-content/uploads/' .
                '2020/05/thisguyblogs.jpg");box-sizing: border-box;}',
                'body {background-image: url("https://deploysite.com/wp-content/uploads/' .
                '2020/05/thisguyblogs.jpg");box-sizing: border-box;}',
            ],
            'background img protocol relative' => [
                'body { background-image: url("//localsite.com/wp-content/uploads/' .
                '2020/05/thisguyblogs.jpg");box-sizing: border-box;}',
                'body {background-image: url("//deploysite.com/wp-content/uploads/' .
                '2020/05/thisguyblogs.jpg");box-sizing: border-box;}',
            ],
        ];
    }

}
