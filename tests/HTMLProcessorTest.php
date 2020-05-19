<?php

namespace StaticHTMLOutput;

use PHPUnit\Framework\TestCase;
use DOMDocument;

final class HTMLProcessorTest extends TestCase {

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
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

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::normalizeURL
     * @dataProvider anchorTagProvider
     */
    public function testNormalizePartialURLInAnchor(
        $node_html,
        $tag_name,
        $attr,
        $exp_result
        ) {
        $html_doc = new DOMDocument();
        $html_header = '<!DOCTYPE html><html lang="en-US" class="no-js no-svg"><body>';
        $html_footer = '</body></html>';
        $html_doc->loadHTML( $html_header . $node_html . $html_footer );
        $links = $html_doc->getElementsByTagName( $tag_name );
        $element = $links[0];

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

        $html_processor->page_url = new \Net_URL2(
            'http://mywpsite.com/category/photos/my-gallery/'
        );

        $html_processor->normalizeURL( $element, $attr );

        $this->assertEquals(
            $exp_result,
            $element->ownerDocument->saveHTML( $element )
        );
    }

    public function anchorTagProvider() {
        return [
            // phpcs:disable
            'anchor tag with relative href' => [
                '<a href="/first_lvl_dir/a_file.jpg">Link to some file</a>',
                'a',
                'href',
                '<a href="http://mywpsite.com/first_lvl_dir/a_file.jpg">Link to some file</a>',
            ],
            'img tag with relative src' => [
                '<img src="/first_lvl_dir/a_file.jpg" />',
                'img',
                'src',
                '<img src="http://mywpsite.com/first_lvl_dir/a_file.jpg">',
            ],
            'script tag with relative src and malformed tag' => [
                '<script src="/some.js" />',
                'script',
                'src',
                '<script src="http://mywpsite.com/some.js"></script>',
            ],
            'link tag with href to file at same hierachy' => [
                '<link rel="stylesheet" type="text/css" href="theme.css">',
                'link',
                'href',
                '<link rel="stylesheet" type="text/css" href="http://mywpsite.com/category/photos/my-gallery/theme.css">',
            ],
            'link tag with href to site root' => [
                '<link rel="stylesheet" type="text/css" href="/">',
                'link',
                'href',
                '<link rel="stylesheet" type="text/css" href="http://mywpsite.com/">',
            ],
        // phpcs:enable
        ];
    }
}
