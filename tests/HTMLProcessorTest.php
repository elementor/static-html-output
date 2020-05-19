<?php

use PHPUnit\Framework\TestCase;

final class HTMLProcessorTest extends TestCase {

    /**
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

        $processor = $this->getMockBuilder( 'StaticHTMLOutput\HTMLProcessor' )
            ->setMethods(
                [
                    'loadSettings',
                    'isInternalLink',
                ]
            )
            ->getMock();

        $processor->settings = [];

        $processor->placeholder_url = 'https://PLACEHOLDER.wpsho/';

        $result = $processor->isInternalLink( $link, $domain );

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
        ];
    }
}
