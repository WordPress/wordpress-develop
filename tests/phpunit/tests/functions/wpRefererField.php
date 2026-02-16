<?php

/**
 * Tests for the wp_referer_field() function.
 *
 * @since 6.1.0
 *
 * @group functions
 *
 * @covers ::wp_referer_field
 */
class Tests_Functions_wpRefererField extends WP_UnitTestCase {

	/**
	 * The original value of `$_SERVER['REQUEST_URI']`.
	 *
	 * @var string
	 */
	private $original_request_uri;

	public function set_up() {
		parent::set_up();

		$this->original_request_uri = $_SERVER['REQUEST_URI'];
	}

	public function tear_down() {
		$_SERVER['REQUEST_URI'] = $this->original_request_uri;

		parent::tear_down();
	}

	/**
	 * @ticket 55578
	 * @ticket 53998
	 */
	public function test_wp_referer_field() {
		$_SERVER['REQUEST_URI'] = '/test/';

		wp_referer_field();
		$this->expectOutputString(
			'<input type="hidden" name="_wp_http_referer" value="' . esc_url( home_url( '/test/' ) ) . '" />'
		);
	}

	/**
	 * @ticket 55578
	 * @ticket 53998
	 */
	public function test_wp_referer_field_return() {
		$_SERVER['REQUEST_URI'] = '/test/';

		$this->assertSame(
			'<input type="hidden" name="_wp_http_referer" value="' . esc_url( home_url( '/test/' ) ) . '" />',
			wp_referer_field( false )
		);
	}

	/**
	 * Tests that the display argument is respected.
	 *
	 * @ticket 54106
	 *
	 * @dataProvider data_wp_referer_field_should_respect_display_arg
	 *
	 * @param mixed $display Whether to echo or return the referer field.
	 */
	public function test_wp_referer_field_should_respect_display_arg( $display ) {
		$actual = $display ? get_echo( 'wp_referer_field' ) : wp_referer_field( false );

		$this->assertStringContainsString( '_wp_http_referer', $actual );
		$this->assertStringContainsString( '<input type="hidden"', $actual );
	}

	/**
	 * Data provider for test_wp_referer_field_should_respect_display_arg().
	 *
	 * @return array[]
	 */
	public function data_wp_referer_field_should_respect_display_arg() {
		return array(
			'true'         => array( true ),
			'(int) 1'      => array( 1 ),
			'(string) "1"' => array( '1' ),
			'false'        => array( false ),
			'null'         => array( null ),
			'(int) 0'      => array( 0 ),
			'(string) "0"' => array( '0' ),
		);
	}

	/**
	 * @ticket 54106
	 * @ticket 53998
	 */
	public function test_wp_referer_field_with_referer() {
		$_SERVER['REQUEST_URI'] = '/edit.php?_wp_http_referer=edit.php';

		$actual = wp_referer_field( false );
		$value  = $this->get_referer_value( $actual );

		$this->assertStringContainsString( 'edit.php', $value );
		$this->assertStringNotContainsString( '_wp_http_referer', $value );
	}

	/**
	 * Tests that the referer value is an absolute URL.
	 *
	 * @ticket 53998
	 */
	public function test_wp_referer_field_value_is_absolute_url() {
		$_SERVER['REQUEST_URI'] = '/my-account/';

		$actual = wp_referer_field( false );
		$value  = $this->get_referer_value( $actual );

		$this->assertStringStartsWith( 'http', $value, 'The _wp_http_referer value should be an absolute URL.' );
	}

	/**
	 * Tests that the referer value includes the home path for subdirectory installs.
	 *
	 * @ticket 53998
	 */
	public function test_wp_referer_field_includes_home_path() {
		$original_home = get_option( 'home' );
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/subdir' );

		$_SERVER['REQUEST_URI'] = '/subdir/my-account/';

		$actual = wp_referer_field( false );
		$value  = $this->get_referer_value( $actual );

		update_option( 'home', $original_home );

		$this->assertStringContainsString( '/subdir/my-account/', $value );
	}

	/**
	 * Tests that the _wp_http_referer query arg is stripped from the value.
	 *
	 * @ticket 54106
	 * @ticket 53998
	 */
	public function test_wp_referer_field_strips_existing_referer_arg() {
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?_wp_http_referer=%2Fwp-admin%2Fedit.php&post_type=page';

		$actual = wp_referer_field( false );
		$value  = $this->get_referer_value( $actual );

		$this->assertStringNotContainsString( '_wp_http_referer', $value );
		$this->assertStringContainsString( 'post_type=page', $value );
	}

	/**
	 * Extracts the value attribute from a wp_referer_field() output string.
	 *
	 * @param string $html The HTML output from wp_referer_field().
	 * @return string The value of the value attribute.
	 */
	private function get_referer_value( $html ) {
		preg_match( '/value="([^"]*)"/', $html, $matches );
		return html_entity_decode( $matches[1] ?? '' );
	}
}
