<?php

/**
 * @group formatting
 */
class Tests_Formatting_wpRelCallback extends WP_UnitTestCase {

	/**
	 * @ticket 11360
	 * @dataProvider data_wp_rel_callback
	 */
	public function test_wp_rel_callback( $matches, $rel, $expected ) {
		add_filter( 'home_url', '__return_null' );
		add_filter( 'pre_option_home', '__return_null' );
		add_filter( 'option_home', '__return_false' );

		$this->assertSame( $expected, wp_rel_callback( $matches, $rel ) );
	}

	public function data_wp_rel_callback() {
		$home_url_http  = set_url_scheme( home_url(), 'http' );
		$home_url_https = set_url_scheme( home_url(), 'https' );

		return array(
			array(
				'matches'  => array(
					'<a href="https://wordpress.org">',
					'href="https://wordpress.org',
				),
				'rel'      => 'nofollow',
				'expected' => '<a href="https://wordpress.org" rel="nofollow">'
			),
			array(
				'matches'  => array(
					"<a href='https://wordpress.org'>",
					"href='https://wordpress.org'",
				),
				'rel'      => 'nofollow',
				'expected' => "<a href='https://wordpress.org' rel=\"nofollow\">",
			),
			array(
				'matches'  => array(
					'<a href="https://wordpress.org" title="Title">',
					'href="https://wordpress.org" title="Title"',
				),
				'rel'      => 'nofollow',
				'expected' => '<a href="https://wordpress.org" title="Title" rel="nofollow">',
			),
			array(
				'matches'  => array(
					'<a title="Title" href="https://wordpress.org">',
					'"title="Title" href="https://wordpress.org"',
				),
				'rel'      => 'nofollow',
				'expected' => '<a title="Title" href="https://wordpress.org" rel="nofollow">',
			),
			array(
				'matches'  => array(
					'<a data-someflag href="https://wordpress.org">',
					'data-someflag href="https://wordpress.org"',
				),
				'rel'      => 'nofollow',
				'expected' => '<a data-someflag href="https://wordpress.org" rel="nofollow">',
			),
			array(
				'matches'  => array(
					'<a  data-someflag  title="Title"  href="https://wordpress.org" onclick=""  >',
					' data-someflag  title="Title"  href="https://wordpress.org" onclick=""  ',
				),
				'rel'      => 'nofollow',
				'expected' => '<a  data-someflag  title="Title"  href="https://wordpress.org" onclick=""   rel="nofollow">',
			),
			array(
				'matches'  => array(
					'<a href="http://example.org/some-url">',
					'href="http://example.org/some-url"',
				),
				'rel'      => '',
				'expected' => '<a href="http://example.org/some-url">',
			),
			array(
				'matches'  => array(
					'<a href="https://example.org/some-url">',
					'href="https://example.org/some-url"',
				),
				'rel'      => '',
				'expected' => '<a href="https://example.org/some-url">',
			),
		);
	}
}
