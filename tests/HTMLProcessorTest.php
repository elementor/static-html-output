<?php

namespace StaticHTMLOutput;

use PHPUnit\Framework\TestCase;

final class HTMLProcessorTest extends TestCase {

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @dataProvider internalLinkProvider
     */
    public function testDetectsInternalLink( $link, $domain, $expectation ) {
        /*
            $link should match $domain

            $domain defaults to placeholder_url

            we've rewritten all URLs before here to use the
            placeholder one, so internal link usually(always?)
            means it matches our placeholder domain

            TODO: rename function to reflect what it's now doing

        */
        $html_processor = new HTMLProcessor(
            false, // $allow_offline_usage = false
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            false, // $use_relative_urls = false
            '', // $base_href
            '', // $base_url
            '', // $selected_deployment_option = 'folder'
            '', // $wp_site_url
            '' // $wp_uploads_path
        );

        $html_processor->placeholder_url = 'https://PLACEHOLDER.wpsho/';

        $result = $html_processor->isInternalLink( $link, $domain );

        $this->assertEquals(
            $expectation,
            $result
        );
    }

    public function internalLinkProvider() {
        return [
            'FQU site root' => [
                'https://PLACEHOLDER.wpsho/',
                '',
                true,
            ],
            'FQU from site with file in nested subdirs' => [
                'https://PLACEHOLDER.wpsho//category/travel/photos/001.jpg',
                '',
                true,
            ],
            'external FQU with matching domain as 2nd arg' => [
                'http://someotherdomain.com/category/travel/photos/001.jpg',
                'http://someotherdomain.com',
                true,
            ],
            'not external FQU' => [
                'http://someothersite.com/category/travel/photos/001.jpg',
                '',
                false,
            ],
            'not internal FQU with different domain as 2nd arg' => [
                'https://PLACEHOLDER.wpsho//category/travel/photos/001.jpg',
                'http://someotherdomain.com',
                false,
            ],
            'subdomain on same domain' => [
                'https://sub.PLACEHOLDER.wpsho/',
                '',
                false,
            ],
            'site root relative URL' => [
                '/category/travel/photos/001.jpg',
                '',
                true,
            ],
            'doc root relative URL' => [
                './category/travel/photos/001.jpg',
                '',
                true,
            ],
            'doc root relative parent URL' => [
                '../category/travel/photos/001.jpg',
                '',
                true,
            ],
            'empty link URL' => [
                '',
                '',
                false,
            ],
        ];
    }
}
