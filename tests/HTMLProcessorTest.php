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
            'external FQU, protocol relative' => [
                '//example.com/category/travel/photos/001.jpg',
                '',
                false,
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
            false, // $use_relative_urls = false
            '', // $base_href
            '', // $base_url
            '', // $selected_deployment_option = 'folder'
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
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteUnchangedPlaceholderURLs
     * @dataProvider unchangedURLsProvider
     */
    public function testRewritingRemainingPlaceholders(
        $placeholder_url,
        $destination_url,
        $rewrite_rules,
        $processed_html,
        $exp_result
        ) {

        $html_processor = new HTMLProcessor(
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

        $this->assertEquals(
            $exp_result,
            $html_processor->rewriteUnchangedPlaceholderURLs(
                $processed_html,
                $placeholder_url,
                $destination_url,
                $rewrite_rules
            )
        );
    }

    public function unchangedURLsProvider() {
        return [
            'http destination URL with trailing slash and trailing chars' => [
                'http://PLACEHOLDER.wpsho',
                'https://somedomain.com',
                '',
                '<a href="http://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
                '<a href="https://somedomain.com/banana.jpg">Link to some file</a>',
            ],
            'http destination URL with trailing slash' => [
                'http://PLACEHOLDER.wpsho',
                'https://somedomain.com',
                '',
                '<a href="http://PLACEHOLDER.wpsho/">Link to some file</a>',
                '<a href="https://somedomain.com/">Link to some file</a>',
            ],
            'http destination URL without trailing slash' => [
                'http://PLACEHOLDER.wpsho',
                'https://somedomain.com',
                '',
                '<a href="http://PLACEHOLDER.wpsho">Link to some file</a>',
                '<a href="https://somedomain.com">Link to some file</a>',
            ],
            'https destination URL with trailing slash and trailing chars' => [
                'http://PLACEHOLDER.wpsho',
                'https://somedomain.com',
                '',
                '<a href="http://PLACEHOLDER.wpsho/banana.jpg">Link to some file</a>',
                '<a href="https://somedomain.com/banana.jpg">Link to some file</a>',
            ],
            'https destination URL with trailing slash' => [
                'http://PLACEHOLDER.wpsho',
                'https://somedomain.com',
                '',
                '<a href="http://PLACEHOLDER.wpsho/">Link to some file</a>',
                '<a href="https://somedomain.com/">Link to some file</a>',
            ],
            'https destination URL without trailing slash' => [
                'http://PLACEHOLDER.wpsho',
                'https://somedomain.com',
                '',
                '<a href="http://PLACEHOLDER.wpsho">Link to some file</a>',
                '<a href="https://somedomain.com">Link to some file</a>',
            ],
        ];
    }

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::addDiscoveredURL
     * @covers StaticHTMLOutput\HTMLProcessor::convertToRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::detectIfURLsShouldBeHarvested
     * @covers StaticHTMLOutput\HTMLProcessor::getProtocolRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::getTargetSiteProtocol
     * @covers StaticHTMLOutput\HTMLProcessor::normalizeURL
     * @covers StaticHTMLOutput\HTMLProcessor::processHTML
     * @covers StaticHTMLOutput\HTMLProcessor::processHead
     * @covers StaticHTMLOutput\HTMLProcessor::processLink
     * @covers StaticHTMLOutput\HTMLProcessor::removeQueryStringFromInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteBaseURL
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteWPPaths
     * @covers StaticHTMLOutput\HTMLProcessor::shouldCreateBaseHREF
     * @covers StaticHTMLOutput\HTMLProcessor::shouldUseRelativeURLs
     * @covers StaticHTMLOutput\HTMLProcessor::stripHTMLComments
     * @covers StaticHTMLOutput\HTMLProcessor::writeDiscoveredURLs
     * @dataProvider baseHREFProvider
     */
    public function testSetBaseHREF(
        $test_html_content,
        $base_href,
        $exp_detect_existing,
        $exp_result
        ) {

        $html_processor = new HTMLProcessor(
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            false, // $use_relative_urls = false
            $base_href, // $base_href
            'https://mynewdomain.com', // $base_url
            '', // $selected_deployment_option = 'folder'
            'http://mydomain.com', // $wp_site_url
            '/tmp/' // $wp_uploads_path - temp write file during test while refactoring
        );

        $html_processor->processHTML(
            $test_html_content,
            'http://mywpsite.com/a-page/'
        );

        $this->assertEquals(
            $exp_detect_existing,
            $html_processor->base_tag_exists
        );

        $this->assertEquals(
            $exp_result,
            $html_processor->xml_doc->saveHTML()
        );

    }

    public function baseHREFProvider() {
        return [
            // FAILING
            'base HREF of "/" with none existing in source' => [
                '<!DOCTYPE html><html lang="en-US"><head></head><body></body></html>',
                '/',
                false,
                '<!DOCTYPE html>
<html lang="en-US"><head><base href="/"></head><body></body></html>
',
            ],
            // FAILING
            'base HREF with none existing in source' => [
                '<!DOCTYPE html><html lang="en-US"><head></head><body></body></html>',
                'https://mynewdomain.com',
                false,
                '<!DOCTYPE html>
<html lang="en-US"><head><base href="https://mynewdomain.com"></head><body></body></html>
',
            ],
            'base HREF to change existing in source' => [
                '<!DOCTYPE html><html lang="en-US"><head><base href="https://mydomain.com">' .
                '</head><body></body></html>',
                'https://mynewdomain.com',
                true,
                '<!DOCTYPE html>
<html lang="en-US"><head><base href="https://mynewdomain.com"></head><body></body></html>
',
            ],
            'empty base HREF removes existing in source' => [
                '<!DOCTYPE html><html lang="en-US"><head><base href="https://mydomain.com">' .
                '</head><body></body></html>',
                '',
                true,
                '<!DOCTYPE html>
<html lang="en-US"><head></head><body></body></html>
',
            ],
            'no base HREF and none existing in source' => [
                '<!DOCTYPE html><html lang="en-US"><head></head><body></body></html>',
                '',
                false,
                '<!DOCTYPE html>
<html lang="en-US"><head></head><body></body></html>
',
            ],
            'new base HREF becomes first child of <head>' => [
                '<!DOCTYPE html><html lang="en-US"><head><link rel="stylesheet" ' .
                'href="#"></head><body></body></html>',
                '/',
                false,
                '<!DOCTYPE html>
<html lang="en-US"><head><base href="/"><link rel="stylesheet" href="#"></head><body></body></html>
',
            ],
        ];
    }

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::addDiscoveredURL
     * @covers StaticHTMLOutput\HTMLProcessor::convertToRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::detectEscapedSiteURLs
     * @covers StaticHTMLOutput\HTMLProcessor::detectIfURLsShouldBeHarvested
     * @covers StaticHTMLOutput\HTMLProcessor::detectUnchangedPlaceholderURLs
     * @covers StaticHTMLOutput\HTMLProcessor::getHTML
     * @covers StaticHTMLOutput\HTMLProcessor::getProtocolRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::getTargetSiteProtocol
     * @covers StaticHTMLOutput\HTMLProcessor::normalizeURL
     * @covers StaticHTMLOutput\HTMLProcessor::processHTML
     * @covers StaticHTMLOutput\HTMLProcessor::processHead
     * @covers StaticHTMLOutput\HTMLProcessor::processMeta
     * @covers StaticHTMLOutput\HTMLProcessor::removeQueryStringFromInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteBaseURL
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteWPPaths
     * @covers StaticHTMLOutput\HTMLProcessor::shouldCreateBaseHREF
     * @covers StaticHTMLOutput\HTMLProcessor::shouldUseRelativeURLs
     * @covers StaticHTMLOutput\HTMLProcessor::stripHTMLComments
     * @covers StaticHTMLOutput\HTMLProcessor::writeDiscoveredURLs

     * @dataProvider unicodeProvider
     */
    public function testUnicodeOutput(
        $test_html_content,
        $exp_result
        ) {

        $html_processor = new HTMLProcessor(
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            false, // $use_relative_urls = false
            '', // $base_href
            'https://mynewdomain.com', // $base_url
            '', // $selected_deployment_option = 'folder'
            'http://mydomain.com', // $wp_site_url
            '/tmp/' // $wp_uploads_path - temp write file during test while refactoring
        );

        $html_processor->processHTML(
            $test_html_content,
            'http://mywpsite.com/a-page/'
        );

        $this->assertEquals(
            $exp_result,
            $html_processor->getHTML()
        );

    }

    public function unicodeProvider() {
        return [
            'unicode characters in source' => [
                '<!DOCTYPE html><html lang="en-US"><head></head><meta charset="utf-8"/><title>' .
                'wpnotes | Поредният WordPress сайт</title><body></body></html>',
                '<!DOCTYPE html>
<html lang="en-US"><head></head><meta charset="utf-8"><title>wpnotes | ' .
                '&#1055;&#1086;&#1088;&#1077;&#1076;&#1085;&#1080;&#1103;&#1090; WordPress ' .
                '&#1089;&#1072;&#1081;&#1090;</title><body></body></html>
',
            ],
        ];
    }

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::processLink
     * @covers StaticHTMLOutput\HTMLProcessor::addDiscoveredURL
     * @covers StaticHTMLOutput\HTMLProcessor::convertToRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::detectEscapedSiteURLs
     * @covers StaticHTMLOutput\HTMLProcessor::detectIfURLsShouldBeHarvested
     * @covers StaticHTMLOutput\HTMLProcessor::detectUnchangedPlaceholderURLs
     * @covers StaticHTMLOutput\HTMLProcessor::getBaseURLRewritePatterns
     * @covers StaticHTMLOutput\HTMLProcessor::getHTML
     * @covers StaticHTMLOutput\HTMLProcessor::getProtocolRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::getTargetSiteProtocol
     * @covers StaticHTMLOutput\HTMLProcessor::isInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::normalizeURL
     * @covers StaticHTMLOutput\HTMLProcessor::processHTML
     * @covers StaticHTMLOutput\HTMLProcessor::processHead
     * @covers StaticHTMLOutput\HTMLProcessor::removeQueryStringFromInternalLink
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteBaseURL
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteWPPaths
     * @covers StaticHTMLOutput\HTMLProcessor::shouldCreateBaseHREF
     * @covers StaticHTMLOutput\HTMLProcessor::shouldUseRelativeURLs
     * @covers StaticHTMLOutput\HTMLProcessor::stripHTMLComments
     * @covers StaticHTMLOutput\HTMLProcessor::writeDiscoveredURLs
     * @dataProvider processlinkProvider
     */
    public function testProcessLink(
        $remove_wp_links_option,
        $test_html_content,
        $exp_result
        ) {

        $html_processor = new HTMLProcessor(
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            $remove_wp_links_option, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            false, // $use_relative_urls = false
            '', // $base_href
            'https://mynewdomain.com', // $base_url
            '', // $selected_deployment_option = 'folder'
            'http://mydomain.com', // $wp_site_url
            '/tmp/' // $wp_uploads_path - temp write file during test while refactoring
        );

        $html_processor->processHTML(
            $test_html_content,
            'http://mywpsite.com/a-page/'
        );

        $this->assertEquals(
            $exp_result,
            $html_processor->getHTML()
        );

    }

    public function processlinkProvider() {
        return [
            'enabled option strips select rel links' => [
                true,
                '<!DOCTYPE html><html lang="en-US"><head>' .
                '<link rel="alternate" hreflang="es" href="https://example.com">' .
                '<link rel="appendix" href="https://example.com">' .
                '<link rel="author" href="https://example.com">' .
                '<link rel="bookmark" href="https://example.com">' .
                '<link rel="chapter" href="https://example.com">' .
                '<link rel="contents" href="https://example.com">' .
                '<link rel="copyright" href="https://example.com">' .
                '<link rel="dns-prefetch" href="https://example.com">' .
                '<link rel="EditURI" href="https://example.com">' .
                '<link rel="glossary" href="https://example.com">' .
                '<link rel="help" href="https://example.com">' .
                '<link rel="icon" href="favicon.ico">' .
                '<link rel="index" href="https://example.com">' .
                '<link rel="license" href="https://example.com">' .
                '<link rel="shortcut icon" href="https://example.com">' .
                '<link rel="apple-touch-icon" href="https://example.com">' .
                '<link rel="manifest" href="https://example.com">' .
                '<link rel="mask-icon" href="https://example.com">' .
                '<link rel="next" href="https://example.com">' .
                '<link rel="pingback" href="https://example.com">' .
                '<link rel="preconnect" href="https://example.com">' .
                '<link rel="prefetch" href="https://example.com">' .
                '<link rel="dns-prefetch" href="//s.w.org">' .
                '<link rel="preload" href="https://example.com">' .
                '<link rel="prerender" href="https://example.com">' .
                '<link rel="prev" href="https://example.com">' .
                '<link rel="section" href="https://example.com">' .
                '<link rel="start" href="https://example.com">' .
                '<link rel="stylesheet" href="styles.css">' .
                '<link rel="subsection" href="https://example.com">' .
                '<link rel="wlwmanifest" href="https://example.com">' .
                '<link rel="https://api.w.org/" href="http://example.com/wp-json/" />' .
                '</head><title>title</title><body>body</body></html>',
                '<!DOCTYPE html>' . PHP_EOL . '<html lang="en-US"><head>' .
                '<link rel="alternate" hreflang="es" href="https://example.com">' .
                '<link rel="appendix" href="https://example.com">' .
                '<link rel="author" href="https://example.com">' .
                '<link rel="bookmark" href="https://example.com">' .
                '<link rel="chapter" href="https://example.com">' .
                '<link rel="contents" href="https://example.com">' .
                '<link rel="copyright" href="https://example.com">' .
                '<link rel="dns-prefetch" href="https://example.com">' .
                '<link rel="glossary" href="https://example.com">' .
                '<link rel="help" href="https://example.com">' .
                '<link rel="icon" href="favicon.ico">' .
                '<link rel="license" href="https://example.com">' .
                '<link rel="shortcut icon" href="https://example.com">' .
                '<link rel="apple-touch-icon" href="https://example.com">' .
                '<link rel="manifest" href="https://example.com">' .
                '<link rel="mask-icon" href="https://example.com">' .
                '<link rel="next" href="https://example.com">' .
                '<link rel="preconnect" href="https://example.com">' .
                '<link rel="prefetch" href="https://example.com">' .
                '<link rel="preload" href="https://example.com">' .
                '<link rel="prerender" href="https://example.com">' .
                '<link rel="prev" href="https://example.com">' .
                '<link rel="section" href="https://example.com">' .
                '<link rel="stylesheet" href="styles.css">' .
                '<link rel="subsection" href="https://example.com">' .
                '</head><title>title</title><body>body</body></html>' . PHP_EOL,
            ],
            'disabled option strips select rel links' => [
                false,
                '<!DOCTYPE html><html lang="en-US"><head>' .
                '<link rel="alternate" hreflang="es" href="https://example.com">' .
                '<link rel="appendix" href="https://example.com">' .
                '<link rel="author" href="https://example.com">' .
                '<link rel="bookmark" href="https://example.com">' .
                '<link rel="chapter" href="https://example.com">' .
                '<link rel="contents" href="https://example.com">' .
                '<link rel="copyright" href="https://example.com">' .
                '<link rel="dns-prefetch" href="https://example.com">' .
                '<link rel="EditURI" href="https://example.com">' .
                '<link rel="glossary" href="https://example.com">' .
                '<link rel="help" href="https://example.com">' .
                '<link rel="icon" href="favicon.ico">' .
                '<link rel="index" href="https://example.com">' .
                '<link rel="license" href="https://example.com">' .
                '<link rel="shortcut icon" href="https://example.com">' .
                '<link rel="apple-touch-icon" href="https://example.com">' .
                '<link rel="manifest" href="https://example.com">' .
                '<link rel="mask-icon" href="https://example.com">' .
                '<link rel="next" href="https://example.com">' .
                '<link rel="pingback" href="https://example.com">' .
                '<link rel="preconnect" href="https://example.com">' .
                '<link rel="prefetch" href="https://example.com">' .
                '<link rel="dns-prefetch" href="//s.w.org">' .
                '<link rel="preload" href="https://example.com">' .
                '<link rel="prerender" href="https://example.com">' .
                '<link rel="prev" href="https://example.com">' .
                '<link rel="section" href="https://example.com">' .
                '<link rel="start" href="https://example.com">' .
                '<link rel="stylesheet" href="styles.css">' .
                '<link rel="subsection" href="https://example.com">' .
                '<link rel="wlwmanifest" href="https://example.com">' .
                '<link rel="https://api.w.org/" href="http://example.com/wp-json/" />' .
                '</head><title>title</title><body>body</body></html>',
                '<!DOCTYPE html>' . PHP_EOL . '<html lang="en-US"><head>' .
                '<link rel="alternate" hreflang="es" href="https://example.com">' .
                '<link rel="appendix" href="https://example.com">' .
                '<link rel="author" href="https://example.com">' .
                '<link rel="bookmark" href="https://example.com">' .
                '<link rel="chapter" href="https://example.com">' .
                '<link rel="contents" href="https://example.com">' .
                '<link rel="copyright" href="https://example.com">' .
                '<link rel="dns-prefetch" href="https://example.com">' .
                '<link rel="EditURI" href="https://example.com">' .
                '<link rel="glossary" href="https://example.com">' .
                '<link rel="help" href="https://example.com">' .
                '<link rel="icon" href="favicon.ico">' .
                '<link rel="index" href="https://example.com">' .
                '<link rel="license" href="https://example.com">' .
                '<link rel="shortcut icon" href="https://example.com">' .
                '<link rel="apple-touch-icon" href="https://example.com">' .
                '<link rel="manifest" href="https://example.com">' .
                '<link rel="mask-icon" href="https://example.com">' .
                '<link rel="next" href="https://example.com">' .
                '<link rel="pingback" href="https://example.com">' .
                '<link rel="preconnect" href="https://example.com">' .
                '<link rel="prefetch" href="https://example.com">' .
                '<link rel="dns-prefetch" href="//s.w.org">' .
                '<link rel="preload" href="https://example.com">' .
                '<link rel="prerender" href="https://example.com">' .
                '<link rel="prev" href="https://example.com">' .
                '<link rel="section" href="https://example.com">' .
                '<link rel="start" href="https://example.com">' .
                '<link rel="stylesheet" href="styles.css">' .
                '<link rel="subsection" href="https://example.com">' .
                '<link rel="wlwmanifest" href="https://example.com">' .
                '<link rel="https://api.w.org/" href="http://example.com/wp-json/">' .
                '</head><title>title</title><body>body</body></html>' . PHP_EOL,
            ],
            'protocol relative link always rewritten to destination protocol' => [
                false,
                '<!DOCTYPE html><html lang="en-US"><head>' .
                '<link rel="prefetch" href="//mydomain.com">' .
                '</head><title>title</title><body>body</body></html>',
                '<!DOCTYPE html>' . PHP_EOL . '<html lang="en-US"><head>' .
                '<link rel="prefetch" href="https://mynewdomain.com">' .
                '</head><title>title</title><body>body</body></html>' . PHP_EOL,
            ],

            /*
                 'site root relative link rewritten to destination protocol' => [
                   false,
                   '<!DOCTYPE html><html lang="en-US"><head>' .
                   '<link rel="apple-touch-icon" sizes="180x180" ' .
                   'href="/wp-content/uploads/fbrfg/apple-touch-icon.png">' .
                   '<link rel="icon" type="image/png" sizes="32x32" ' .
                   'href="/wp-content/uploads/fbrfg/favicon-32x32.png">' .
                   '</head><title>title</title><body>body</body></html>',
                   '<!DOCTYPE html>' . PHP_EOL . '<html lang="en-US"><head>' .
                   '<link rel="apple-touch-icon" sizes="180x180" ' .
                   'href="/wp-content/uploads/fbrfg/apple-touch-icon.png">' .
                   '<link rel="icon" type="image/png" sizes="32x32" ' .
                   'href="/wp-content/uploads/fbrfg/favicon-32x32.png">' .
                   '</head><title>title</title><body>body</body></html>' . PHP_EOL,
               ],
            */
        ];
    }

    /**
     * @covers StaticHTMLOutput\HTMLProcessor::__construct
     * @covers StaticHTMLOutput\HTMLProcessor::detectEscapedSiteURLs
     * @covers StaticHTMLOutput\HTMLProcessor::detectIfURLsShouldBeHarvested
     * @covers StaticHTMLOutput\HTMLProcessor::detectUnchangedPlaceholderURLs
     * @covers StaticHTMLOutput\HTMLProcessor::getHTML
     * @covers StaticHTMLOutput\HTMLProcessor::getProtocolRelativeURL
     * @covers StaticHTMLOutput\HTMLProcessor::getTargetSiteProtocol
     * @covers StaticHTMLOutput\HTMLProcessor::processHTML
     * @covers StaticHTMLOutput\HTMLProcessor::rewriteSiteURLsToPlaceholder
     * @covers StaticHTMLOutput\HTMLProcessor::shouldCreateBaseHREF
     * @covers StaticHTMLOutput\HTMLProcessor::stripHTMLComments
     * @covers StaticHTMLOutput\HTMLProcessor::writeDiscoveredURLs
     * @dataProvider codeProvider
     */
    public function testCodeProcessing(
        $test_html_content,
        $exp_result
        ) {
        $html_processor = new HTMLProcessor(
            false, // $remove_conditional_head_comments = false
            false, // $remove_html_comments = false
            false, // $remove_wp_links = false
            false, // $remove_wp_meta = false
            '', // $rewrite_rules = false
            false, // $use_relative_urls = false
            '', // $base_href
            'https://mynewdomain.com', // $base_url
            '', // $selected_deployment_option = 'folder'
            'http://mydomain.com', // $wp_site_url
            '/tmp/' // $wp_uploads_path - temp write file during test while refactoring
        );

        $html_processor->processHTML(
            $test_html_content,
            'http://mywpsite.com/a-page/'
        );

        $this->assertEquals(
            $exp_result,
            $html_processor->getHTML()
        );

    }

    public function codeProvider() {
        return [
            'preserves HTML encoding within <code> el' => [
                '<!DOCTYPE html><html lang="en-US"><body>' .
                '<code>&lt;div class="gcse-searchbox-only"&gt;&lt;/div&gt;</code>' .
                '</body></html>',
                '<!DOCTYPE html>' . PHP_EOL . '<html lang="en-US"><body>' .
                '<code>&lt;div class="gcse-searchbox-only"&gt;&lt;/div&gt;</code>' .
                '</body></html>' . PHP_EOL,
            ],
        ];
    }
}
