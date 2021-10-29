<?php

/**
 * @group formatting
 */
class Tests_Formatting_wpRelUgc extends WP_UnitTestCase {

	/**
	 * @ticket 48022
	 */
	public function test_add_ugc() {
		$content  = '<p>This is some cool <a href="/">Code</a></p>';
		$expected = '<p>This is some cool <a href=\"/\" rel=\"nofollow ugc\">Code</a></p>';
		$this->assertSame( $expected, wp_rel_ugc( $content ) );
	}

	/**
	 * @ticket 48022
	 */
	public function test_convert_ugc() {
		$content  = '<p>This is some cool <a href="/" rel="weird">Code</a></p>';
		$expected = '<p>This is some cool <a href=\"/\" rel=\"weird nofollow ugc\">Code</a></p>';
		$this->assertSame( $expected, wp_rel_ugc( $content ) );
	}

	/**
	 * @ticket 48022
	 * @dataProvider data_wp_rel_ugc
	 */
	public function test_wp_rel_ugc( $input, $output ) {
		return $this->assertSame( wp_slash( $output ), wp_rel_ugc( $input ) );
	}

	public function data_wp_rel_ugc() {
		$home_url_http  = set_url_scheme( home_url(), 'http' );
		$home_url_https = set_url_scheme( home_url(), 'https' );

		return array(
			array(
				'<a href="">Double Quotes</a>',
				'<a href="" rel="nofollow ugc">Double Quotes</a>',
			),
			array(
				'<a href="https://wordpress.org">Double Quotes</a>',
				'<a href="https://wordpress.org" rel="nofollow ugc">Double Quotes</a>',
			),
			array(
				"<a href='https://wordpress.org'>Single Quotes</a>",
				"<a href='https://wordpress.org' rel=\"nofollow ugc\">Single Quotes</a>",
			),
			array(
				'<a href="https://wordpress.org" title="Title">Multiple attributes</a>',
				'<a href="https://wordpress.org" title="Title" rel="nofollow ugc">Multiple attributes</a>',
			),
			array(
				'<a title="Title" href="https://wordpress.org">Multiple attributes</a>',
				'<a title="Title" href="https://wordpress.org" rel="nofollow ugc">Multiple attributes</a>',
			),
			array(
				'<a data-someflag href="https://wordpress.org">Multiple attributes</a>',
				'<a data-someflag href="https://wordpress.org" rel="nofollow ugc">Multiple attributes</a>',
			),
			array(
				'<a  data-someflag  title="Title"  href="https://wordpress.org" onclick=""  >Everything at once</a>',
				'<a  data-someflag  title="Title"  href="https://wordpress.org" onclick=""   rel="nofollow ugc">Everything at once</a>',
			),
			array(
				'<a href="' . $home_url_http . '/some-url">Home URL (http)</a>',
				'<a href="' . $home_url_http . '/some-url">Home URL (http)</a>',
			),
			array(
				'<a href="' . $home_url_https . '/some-url">Home URL (https)</a>',
				'<a href="' . $home_url_https . '/some-url">Home URL (https)</a>',
			),
		);
	}

	public function test_append_ugc_with_valueless_attribute() {
		$content  = '<p>This is some cool <a href="demo.com" download rel="hola">Code</a></p>';
		$expected = '<p>This is some cool <a href=\"demo.com\" download rel=\"hola nofollow ugc\">Code</a></p>';
		$this->assertSame( $expected, wp_rel_ugc( $content ) );
	}
}
