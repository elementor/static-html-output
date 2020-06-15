<?php

namespace StaticHTMLOutput;

use PHPUnit\Framework\TestCase;
use DOMDocument;

final class HTMLProcessorTest extends TestCase {

    public function loadTestHTML( string $filename ) {
        $test_data = file_get_contents( __DIR__ . "/data/HTMLProcessorTest/$filename.html" );

        if ( ! $test_data ) {
            return '<html><body>UNABLE TO LOAD TEST HTML FILE';
        }

        return $test_data;
    }

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
     * @dataProvider internalLinkProvider
     */
    public function testDetectsInternalLink( $link, $expectation ) {
        /*
           $link should match $domain

           $domain defaults to placeholder_url

           we've rewritten all URLs before here to use the
           placeholder one, so internal link usually(always?)
           means it matches our placeholder domain

           TODO: rename function to reflect what it's now doing

        */
        $html_processor = new HTMLProcessor(
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            '', // $base_url
            '', // $selected_deployment_option = 'zip'
            '', // $wp_site_url
            '' // $wp_uploads_path
        );

        $html_processor->placeholder_url = 'https://PLACEHOLDER.wpsho/';

        $result = $html_processor->isInternalLink( $link );

        $this->assertEquals(
            $expectation,
            $result
        );
    }

    public function internalLinkProvider() {
        return [
            'FQU site root' => [
                'https://PLACEHOLDER.wpsho/',
                true,
            ],
            'FQU from site with file in nested subdirs' => [
                'https://PLACEHOLDER.wpsho//category/travel/photos/001.jpg',
                true,
            ],
            'external FQU' => [
                'http://someotherdomain.com/category/travel/photos/001.jpg',
                false,
            ],
            'external FQU, protocol relative' => [
                '//example.com/category/travel/photos/001.jpg',
                false,
            ],
            'external FQU' => [
                'http://someotherdomain.com/category/travel/photos/001.jpg',
                false,
            ],
            'internal FQU' => [
                'http://PLACEHOLDER.wpsho/category/travel/photos/001.jpg',
                true,
            ],
            'subdomain on same domain' => [
                'https://sub.PLACEHOLDER.wpsho/',
                false,
            ],
            'site root relative URL' => [
                '/category/travel/photos/001.jpg',
                true,
            ],
            'doc root relative URL' => [
                './category/travel/photos/001.jpg',
                true,
            ],
            'doc root relative parent URL' => [
                '../category/travel/photos/001.jpg',
                true,
            ],
            'empty link URL' => [
                '',
                false,
            ],
            'doc relative favicon' => [
                'favicon.ico',
                true,
            ],
            'content directive within meta' => [
                'width=device-width, initial-scale=1.0',
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
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            'https://mynewdomain.com', // $base_url
            '', // $selected_deployment_option = 'zip'
            'http://mywpsite.com', // $wp_site_url
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
                '<link rel="stylesheet" type="text/css" ' .
                'href="http://mywpsite.com/category/photos/my-gallery/theme.css">',
            ],
            'link tag with href to site root' => [
                '<link rel="stylesheet" type="text/css" href="/">',
                'link',
                'href',
                '<link rel="stylesheet" type="text/css" href="http://mywpsite.com/">',
            ],
        ];
    }

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\HTMLProcessor::getProtocolRelativeURL
     * @dataProvider rewritePlaceholdersProvider
     */
    public function testRewritingSiteURLsToPlaceholder(
       $site_url,
       $placeholder_url,
       $raw_html,
       $exp_result
       ) {

        $html_processor = new HTMLProcessor(
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            '', // $base_url
            '', // $selected_deployment_option = 'zip'
            $site_url, // $wp_site_url
            '' // $wp_uploads_path
        );

        $this->assertEquals(
            $exp_result,
            $html_processor->rewriteSiteURLsToPlaceholder(
                $raw_html,
                $site_url,
                $placeholder_url
            )
        );
    }

    public function rewritePlaceholdersProvider() {
        return [
            'http site url without trailing slash, https destination' => [
                'http://mywpdevsite.com',
                'https://PLACEHOLDER.wpsho',
                '<a href="http://mywpdevsite.com/banana.jpg">Link to some file</a>',
                '<a href="https://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
            ],
            'http site url with trailing slash, https destination' => [
                'http://mywpdevsite.com',
                'https://PLACEHOLDER.wpsho',
                '<a href="http://mywpdevsite.com/banana.jpg">Link to some file</a>',
                '<a href="https://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
            ],
            'https site url without trailing slash, https destination' => [
                'https://mywpdevsite.com',
                'https://PLACEHOLDER.wpsho',
                '<a href="https://mywpdevsite.com/banana.jpg">Link to some file</a>',
                '<a href="https://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
            ],
            'https site url with trailing slash, https destination' => [
                'https://mywpdevsite.com',
                'https://PLACEHOLDER.wpsho',
                '<a href="https://mywpdevsite.com/banana.jpg">Link to some file</a>',
                '<a href="https://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
            ],
            'https site url without trailing slash, http destination' => [
                'https://mywpdevsite.com',
                'http://PLACEHOLDER.wpsho',
                '<a href="https://mywpdevsite.com/banana.jpg">Link to some file</a>',
                '<a href="http://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
            ],
            'https site url with trailing slash, http destination' => [
                'https://mywpdevsite.com',
                'http://PLACEHOLDER.wpsho',
                '<a href="https://mywpdevsite.com/banana.jpg">Link to some file</a>',
                '<a href="http://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
            ],
            'https site url with trailing slash, http destination, escaped link' => [
                'https://mywpdevsite.com',
                'http://PLACEHOLDER.wpsho',
                '<a href="https:\/\/mywpdevsite.com\/banana.jpg">Link to some file</a>',
                '<a href="http:\/\/PLACEHOLDER.wpsho\/banana.jpg">Link to some file</a>',
            ],
            'http site url without trailing slash, https destination, escaped link' => [
                'http://mywpdevsite.com',
                'https://PLACEHOLDER.wpsho',
                '<a href="http:\/\/mywpdevsite.com\/banana.jpg">Link to some file</a>',
                '<a href="https:\/\/PLACEHOLDER.wpsho\/banana.jpg">Link to some file</a>',
            ],
            'https site url with http leftovers in original source' => [
                'https://mywpdevsite.com',
                'https://PLACEHOLDER.wpsho',
                '<a href="http://mywpdevsite.com/banana.jpg">Link to some file</a>',
                '<a href="http://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
            ],
        ];
    }

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\CSSProcessor::__construct
     * @covers StaticHTMLOutput\CSSProcessor::addDiscoveredURL
     * @covers StaticHTMLOutput\CSSProcessor::getCSS
     * @covers StaticHTMLOutput\CSSProcessor::getProtocolRelativeURL
     * @covers StaticHTMLOutput\CSSProcessor::getTargetSiteProtocol
     * @covers StaticHTMLOutput\CSSProcessor::isInternalLink
     * @covers StaticHTMLOutput\CSSProcessor::isValidURL
     * @covers StaticHTMLOutput\CSSProcessor::processCSS
     * @covers StaticHTMLOutput\CSSProcessor::rewritePlaceholderURLsToDestination
     * @covers StaticHTMLOutput\CSSProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\HTMLProcessor::addDiscoveredURL
     * @covers StaticHTMLOutput\HTMLProcessor::detectEscapedSiteURLs
     * @covers StaticHTMLOutput\HTMLProcessor::detectUnchangedPlaceholderURLs
     * @covers StaticHTMLOutput\HTMLProcessor::forceHTTPS
     * @covers StaticHTMLOutput\HTMLProcessor::getBaseURLRewritePatterns
     * @covers StaticHTMLOutput\HTMLProcessor::getHTML
     * @covers StaticHTMLOutput\HTMLProcessor::getProtocolRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::getTargetSiteProtocol
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::isValidURL
     * @covers StaticHTMLOutput\HTMLProcessor::normalizeURL
     * @covers StaticHTMLOutput\HTMLProcessor::processAnchor
     * @covers StaticHTMLOutput\HTMLProcessor::processGenericHref
     * @covers StaticHTMLOutput\HTMLProcessor::processGenericSrc
     * @covers StaticHTMLOutput\HTMLProcessor::processHTML
     * @covers StaticHTMLOutput\HTMLProcessor::processHead
     * @covers StaticHTMLOutput\HTMLProcessor::processImage
     * @covers StaticHTMLOutput\HTMLProcessor::processImageSrcSet
     * @covers StaticHTMLOutput\HTMLProcessor::processLink
     * @covers StaticHTMLOutput\HTMLProcessor::processMeta
     * @covers StaticHTMLOutput\HTMLProcessor::processStyle
     * @covers StaticHTMLOutput\HTMLProcessor::processStyleAttribute
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteBaseURL
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteEncodedSiteURLAndHostName
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteWPPaths
     * @covers StaticHTMLOutput\HTMLProcessor::stripHTMLComments
     * @dataProvider processHTMLProvider
     */
    public function testProcessHTML(
        $remove_conditional_head_comments,
        $remove_html_comments,
        $remove_wp_links,
        $remove_wp_meta,
        $rewrite_rules,
        $base_url, // deployment URL
        $selected_deployment_option,
        $wp_site_url,
        $wp_uploads_path, // temp write file during test while refactoring
        $page_url, // url of current HTML page being processed
        $input_html_content,
        $output_html_content
        ) {
        $html_processor = new HTMLProcessor(
            $remove_conditional_head_comments,
            $remove_html_comments,
            $remove_wp_links,
            $remove_wp_meta,
            $rewrite_rules,
            $base_url,
            $selected_deployment_option,
            $wp_site_url,
            $wp_uploads_path
        );

        $html_processor->processHTML( $input_html_content, $page_url );

        $this->assertEquals( $output_html_content, $html_processor->getHTML() );
    }

    public function processHTMLProvider() {
        return [
            'preserves HTML encoding within <code> el' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost:4040', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://mywpsite.com/a-page/',
                $this->loadTestHTML( 'input_preserves_html_entities_within_code_element' ),
                $this->loadTestHTML( 'output_preserves_html_entities_within_code_element' ),
            ],
            'rewrites DNS prefetch with port in WP Site URL' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost:4040', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://mywpsite.com/a-page/',
                $this->loadTestHTML( 'input_prefetch_site_url_with_custom_port' ),
                $this->loadTestHTML( 'output_prefetch_site_url_with_custom_port' ),
            ],
            'Unicode within page' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost:4040', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://mywpsite.com/a-page/',
                $this->loadTestHTML( 'input_unicode_within_page' ),
                $this->loadTestHTML( 'output_unicode_within_page' ),
            ],
            'process link elements without stripping option enabled' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://mydomain.com', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://mydomain.com/',
                $this->loadTestHTML( 'input_process_links_without_stripping' ),
                $this->loadTestHTML( 'output_process_links_without_stripping' ),
            ],
            'process link elements with stripping option enabled' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                true, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://mydomain.com', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://mydomain.com/',
                $this->loadTestHTML( 'input_process_links_with_stripping' ),
                $this->loadTestHTML( 'output_process_links_with_stripping' ),
            ],
            'forces https on safe external URLs if destination protocol is https' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://localhost/',
                $this->loadTestHTML( 'input_force_external_urls_to_https_to_match_destination' ),
                $this->loadTestHTML( 'output_force_external_urls_to_https_to_match_destination' ),
            ],
            'no force https on external URLs if destination protocol is http' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'http://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://localhost/',
                $this->loadTestHTML( 'input_no_force_https_external_urls_for_http_destination' ),
                $this->loadTestHTML( 'output_no_force_https_external_urls_for_http_destination' ),
            ],
            'rewrites encoded site url with custom port to https' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost:4444', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://localhost:4444/',
                $this->loadTestHTML( 'input_encoded_site_url_with_custom_port' ),
                $this->loadTestHTML( 'output_encoded_site_url_with_custom_port' ),
            ],
            'rewrites inline styles forcing https to match destination' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost:4444', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://localhost:4444/',
                $this->loadTestHTML( 'input_inline_style_processing' ),
                $this->loadTestHTML( 'output_inline_style_processing' ),
            ],
            'preserves non-URI meta content values, rewrites Site URL without port' => [
                false, // $remove_conditional_head_comments = false
                false, // $remove_html_comments = false
                false, // $remove_wp_links = false
                false, // $remove_wp_meta = false
                '', // $rewrite_rules = ''
                'https://mynewdomain.com', // $base_url
                '', // $selected_deployment_option = 'zip'
                'http://localhost:4444', // $wp_site_url
                '/tmp/', // $wp_uploads_path - temp write file during test while refactoring
                'http://localhost:4444/',
                $this->loadTestHTML( 'input_meta_contents' ),
                $this->loadTestHTML( 'output_meta_contents' ),
            ],
        ];
    }
}
