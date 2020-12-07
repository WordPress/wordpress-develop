<?php

/**
 * Tests the WP_SimplePie_File class, whether it conforms with the SimplePie_File.
 *
 * The SimplePie_File class joins multiple headers of the same name into one with
 * values separated by a comma, except for the content-type header, where only
 * the last one is used. 
 *
 * @group feed
 */
class Tests_WP_SimplePie_File extends WP_UnitTestCase {

	public static function setUpBeforeClass() {
		require_once ABSPATH . '/wp-includes/class-simplepie.php';
		require_once ABSPATH . '/wp-includes/class-wp-simplepie-file.php';
	}

	public function setUp() {
		add_filter( 'pre_http_request', array( $this, 'mocked_http_request' ) );
	}

	public function tearDown() {
		remove_filter( 'pre_http_request', array( $this, 'mocked_http_request' ) );
	}
	
	/**
	 * Multiple headers of the same name should be joint into one, separated by a comma.
	 *
	 * @ticket 51056
	 */
	public function test_link_headers_parsing() {
		$file = new WP_SimplePie_File( 'https://wordpress.org/news/feed/' );

		$this->assertSame( '<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/", <https://wordpress.org/news/wp/v2/categories/3>; rel="alternate"; type="application/json"', $file->headers['link'] );
	}

	/**
	 * Only the last content type header should be used.
	 *
	 * @ticket 51056
	 */
	public function test_content_type_header_parsing() {
		$file = new WP_SimplePie_File( 'https://wordpress.org/news/feed/' );
		$this->assertSame( 'application/rss+xml; charset=UTF-8', $file->headers['content-type'] );
	}

	/**
	 * Mock the http request to a feed.
	 */
	public function mocked_http_request() {
		$headers = new Requests_Utility_CaseInsensitiveDictionary( array(
			'content-type' => 'application/rss+xml; charset=ISO-8859-2',
			'link' => array(
				'<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/"',
				'<https://wordpress.org/news/wp/v2/categories/3>; rel="alternate"; type="application/json"',
			),
			'content-type' => 'application/rss+xml; charset=UTF-8',
		) );
		$body = <<<EOL
<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	xmlns:georss="http://www.georss.org/georss"
	xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"
	>
<channel>
	<title>News &#8211;  &#8211; WordPress.org</title>
	<atom:link href="https://wordpress.org/news/feed/" rel="self" type="application/rss+xml" />
	<link>https://wordpress.org/news</link>
	<description>WordPress News</description>
	<lastBuildDate>Tue, 01 Sep 2020 21:00:15 +0000</lastBuildDate>
	<language>en-US</language>
	<sy:updatePeriod>
	hourly	</sy:updatePeriod>
	<sy:updateFrequency>
	1	</sy:updateFrequency>
	<generator>https://wordpress.org/?v=5.6-alpha-49040</generator>
<site xmlns="com-wordpress:feed-additions:1">14607090</site>	<item>
		<title>WordPress 5.5.1 Maintenance Release</title>
		<link>https://wordpress.org/news/2020/09/wordpress-5-5-1-maintenance-release/</link>
		<dc:creator><![CDATA[Jb Audras]]></dc:creator>
		<pubDate>Tue, 01 Sep 2020 19:13:53 +0000</pubDate>
				<category><![CDATA[Releases]]></category>
		<guid isPermaLink="false">https://wordpress.org/news/?p=8979</guid>

					<description><![CDATA[WordPress 5.5.1 is now available! This maintenance release features&#160;34 bug fixes, 5 enhancements, and&#160;5 bug fixes&#160;for the&#160;block&#160;editor. These bugs affect WordPress version 5.5, so you’ll want to upgrade. You can download WordPress 5.5.1 directly, or visit the&#160;Dashboard → Updates screen&#160;and click&#160;Update Now. If your sites support automatic background updates, they’ve already started the update process. [&#8230;]]]></description>
										<content:encoded><![CDATA[
<p>WordPress 5.5.1 is now available!</p>
]]></content:encoded>
		<post-id xmlns="com-wordpress:feed-additions:1">8979</post-id>	</item>
EOL;
		return array(
			'headers' => $headers,
			'body' => $body,
			'response' => array(
				'code' => 200,
				'message' => 'OK'
			),
			'cookies' => array(),
			'filename' => null,
		);
	}
}
