<?php

/**
 * Tests for the user_trailingslashit() function.
 *
 * @group link
 * @covers ::user_trailingslashit
 */
class Tests_Link_UserTrailingslashit extends WP_UnitTestCase {

	/**
	 * Tests user_trailingslashit() when trailing slashes are enabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_user_trailingslashit_enabled
	 *
	 * @param string $url      Input URL.
	 * @param string $expected Expected URL with trailing slash.
	 */
	public function test_user_trailingslashit_when_trailing_slashes_enabled( $url, $expected ) {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( $expected, user_trailingslashit( $url ) );
	}

	/**
	 * Data provider for test_user_trailingslashit_when_trailing_slashes_enabled.
	 *
	 * @return array[]
	 */
	public static function data_user_trailingslashit_enabled() {
		return array(
			'URL without trailing slash' => array(
				'url'      => 'https://example.org/sample-post',
				'expected' => 'https://example.org/sample-post/',
			),
			'URL with trailing slash'    => array(
				'url'      => 'https://example.org/sample-post/',
				'expected' => 'https://example.org/sample-post/',
			),
			'root URL with slash'        => array(
				'url'      => 'https://example.org/',
				'expected' => 'https://example.org/',
			),
		);
	}

	/**
	 * Tests user_trailingslashit() when trailing slashes are disabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_user_trailingslashit_disabled
	 *
	 * @param string $url      Input URL.
	 * @param string $expected Expected URL without trailing slash.
	 */
	public function test_user_trailingslashit_when_trailing_slashes_disabled( $url, $expected ) {
		$this->set_permalink_structure( '/%postname%' );

		$this->assertSame( $expected, user_trailingslashit( $url ) );
	}

	/**
	 * Data provider for test_user_trailingslashit_when_trailing_slashes_disabled.
	 *
	 * @return array[]
	 */
	public static function data_user_trailingslashit_disabled() {
		return array(
			'URL with trailing slash'    => array(
				'url'      => 'https://example.org/sample-post/',
				'expected' => 'https://example.org/sample-post',
			),
			'URL without trailing slash' => array(
				'url'      => 'https://example.org/sample-post',
				'expected' => 'https://example.org/sample-post',
			),
		);
	}

	/**
	 * Tests that user_trailingslashit passes the type_of_url parameter to the filter.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_type_of_url
	 *
	 * @param string $type The type of URL being considered.
	 */
	public function test_user_trailingslashit_passes_type_to_filter( $type ) {
		$passed_type = null;

		$filter_cb = static function ( $url, $type_of_url ) use ( &$passed_type ) {
			$passed_type = $type_of_url;
			return $url;
		};

		add_filter( 'user_trailingslashit', $filter_cb, 10, 2 );
		user_trailingslashit( 'https://example.org/test', $type );
		remove_filter( 'user_trailingslashit', $filter_cb, 10 );

		$this->assertSame( $type, $passed_type );
	}

	/**
	 * Data provider for test_user_trailingslashit_passes_type_to_filter.
	 *
	 * @return array[]
	 */
	public static function data_type_of_url() {
		return array(
			'single'            => array( 'single' ),
			'single_trackback'  => array( 'single_trackback' ),
			'single_feed'       => array( 'single_feed' ),
			'single_paged'      => array( 'single_paged' ),
			'commentpaged'      => array( 'commentpaged' ),
			'paged'             => array( 'paged' ),
			'home'              => array( 'home' ),
			'feed'              => array( 'feed' ),
			'category'          => array( 'category' ),
			'page'              => array( 'page' ),
			'year'              => array( 'year' ),
			'month'             => array( 'month' ),
			'day'               => array( 'day' ),
			'post_type_archive' => array( 'post_type_archive' ),
		);
	}

	/**
	 * Tests that the 'user_trailingslashit' filter can modify the output.
	 *
	 * @ticket 65819
	 */
	public function test_user_trailingslashit_filter_modifies_url() {
		$this->set_permalink_structure( '/%postname%/' );

		$filter_cb = static function ( $url, $type_of_url ) {
			return $url . '?custom_arg=' . $type_of_url;
		};

		add_filter( 'user_trailingslashit', $filter_cb, 10, 2 );
		$result = user_trailingslashit( 'https://example.org/test', 'custom_type' );
		remove_filter( 'user_trailingslashit', $filter_cb, 10 );

		$this->assertSame( 'https://example.org/test/?custom_arg=custom_type', $result );
	}
}
