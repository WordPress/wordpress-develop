<?php

/**
 * @group formatting
 *
 * @covers ::wp_rel_nofollow
 */
class Tests_Formatting_wpRelNofollow extends WP_UnitTestCase {

	/**
	 * @ticket 9959
	 */
	public function test_add_no_follow() {
		$content  = '<p>This is some cool <a href="/">Code</a></p>';
		$expected = '<p>This is some cool <a href="/" rel="nofollow">Code</a></p>';
		$this->assertEqualHTML( $expected, stripslashes( wp_rel_nofollow( $content ) ) );
	}

	/**
	 * @ticket 9959
	 */
	public function test_convert_no_follow() {
		$content  = '<p>This is some cool <a href="/" rel="weird">Code</a></p>';
		$expected = '<p>This is some cool <a href="/" rel="weird nofollow">Code</a></p>';
		$this->assertEqualHTML( $expected, stripslashes( wp_rel_nofollow( $content ) ) );
	}

	/**
	 * @ticket 11360
	 * @dataProvider data_wp_rel_nofollow
	 */
	public function test_wp_rel_nofollow( $input, $output, $expect_deprecation = false ) {
		$this->assertEqualHTML( $output, stripslashes( wp_rel_nofollow( $input ) ) );
	}

	public static function data_wp_rel_nofollow() {
		$home_url_http  = set_url_scheme( home_url(), 'http' );
		$home_url_https = set_url_scheme( home_url(), 'https' );

		return array(
			array(
				'<a href="">Double Quotes</a>',
				'<a href="" rel="nofollow">Double Quotes</a>',
				true,
			),
			array(
				'<a href="https://wordpress.org">Double Quotes</a>',
				'<a href="https://wordpress.org" rel="nofollow">Double Quotes</a>',
			),
			array(
				"<a href='https://wordpress.org'>Single Quotes</a>",
				"<a href='https://wordpress.org' rel=\"nofollow\">Single Quotes</a>",
			),
			array(
				'<a href="https://wordpress.org" title="Title">Multiple attributes</a>',
				'<a href="https://wordpress.org" title="Title" rel="nofollow">Multiple attributes</a>',
			),
			array(
				'<a title="Title" href="https://wordpress.org">Multiple attributes</a>',
				'<a title="Title" href="https://wordpress.org" rel="nofollow">Multiple attributes</a>',
			),
			array(
				'<a data-someflag href="https://wordpress.org">Multiple attributes</a>',
				'<a data-someflag href="https://wordpress.org" rel="nofollow">Multiple attributes</a>',
			),
			array(
				'<a  data-someflag  title="Title"  href="https://wordpress.org" onclick=""  >Everything at once</a>',
				'<a  data-someflag  title="Title"  href="https://wordpress.org" onclick=""   rel="nofollow">Everything at once</a>',
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

	public function test_append_no_follow_with_valueless_attribute() {
		$content  = '<p>This is some cool <a href="demo.com" download rel="hola">Code</a></p>';
		$expected = '<p>This is some cool <a href="demo.com" download rel="hola nofollow">Code</a></p>';
		$this->assertEqualHTML( $expected, stripslashes( wp_rel_nofollow( $content ) ) );
	}

	/**
	 * @ticket 65984
	 */
	public function test_wp_rel_nofollow_ignores_links_in_comments() {
		$content = '<!-- <a href="https://wordpress.org">Commented link</a> -->';
		$this->assertSame( $content, stripslashes( wp_rel_nofollow( $content ) ) );
	}

	/**
	 * @ticket 65984
	 */
	public function test_wp_rel_nofollow_ignores_links_in_script_and_style() {
		$content = '<script>var a = "<a href=\"https://wordpress.org\">JS link</a>";</script>';
		$this->assertSame( $content, stripslashes( wp_rel_nofollow( wp_slash( $content ) ) ) );
	}

	/**
	 * @ticket 65984
	 */
	public function test_wp_rel_nofollow_handles_multiline_and_case() {
		$content  = "<A\n\thref=\"https://wordpress.org\"\n\tclass=\"btn\">Caps and Multiline</A>";
		$expected = '<a class="btn" href="https://wordpress.org" rel="nofollow">Caps and Multiline</a>';
		$this->assertEqualHTML( $expected, stripslashes( wp_rel_nofollow( $content ) ) );
	}

	/**
	 * @ticket 65984
	 */
	public function test_wp_rel_nofollow_handles_empty_or_non_link_content() {
		$this->assertSame( '', wp_rel_nofollow( '' ) );
		$this->assertSame( 'Just plain text', stripslashes( wp_rel_nofollow( 'Just plain text' ) ) );
	}

	/**
	 * @ticket 65984
	 * @covers ::wp_rel_callback
	 */
	public function test_wp_rel_callback_back_compat() {
		$matches  = array(
			'<a href="https://wordpress.org" class="btn">',
			'href="https://wordpress.org" class="btn"',
		);
		$result   = wp_rel_callback( $matches, 'nofollow' );
		$expected = '<a href="https://wordpress.org" class="btn" rel="nofollow">';
		$this->assertEqualHTML( $expected, $result );
	}
}
