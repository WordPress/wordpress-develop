<?php

/**
 * Test get_allowed_mime_types().
 *
 * @group functions
 *
 * @covers ::get_allowed_mime_types
 */
class Tests_Functions_GetAllowedMimeTypes extends WP_UnitTestCase {

	/**
	 * @ticket 55563
	 */
	public function test_returns_array() {
		$mime_types = get_allowed_mime_types();

		$this->assertIsArray( $mime_types );
		$this->assertNotEmpty( $mime_types );
	}

	/**
	 * HTML and JS mime types should be excluded for users without the unfiltered_html capability.
	 *
	 * @ticket 55563
	 */
	public function test_html_and_js_excluded_for_subscriber() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$mime_types = get_allowed_mime_types();

		$this->assertArrayNotHasKey( 'htm|html', $mime_types );
		$this->assertArrayNotHasKey( 'js', $mime_types );
	}

	/**
	 * HTML and JS mime types should be included for users with the unfiltered_html capability.
	 *
	 * @ticket 55563
	 */
	public function test_html_and_js_included_for_administrator() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'unfiltered_html is not available to administrators on multisite.' );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$mime_types = get_allowed_mime_types();

		$this->assertArrayHasKey( 'htm|html', $mime_types );
		$this->assertArrayHasKey( 'js', $mime_types );
	}

	/**
	 * HTML and JS mime types should be excluded when a specific user without unfiltered_html is passed.
	 *
	 * @ticket 55563
	 */
	public function test_html_and_js_excluded_for_passed_subscriber() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$mime_types = get_allowed_mime_types( $user_id );

		$this->assertArrayNotHasKey( 'htm|html', $mime_types );
		$this->assertArrayNotHasKey( 'js', $mime_types );
	}

	/**
	 * HTML and JS mime types should be included when a specific administrator user is passed.
	 *
	 * @ticket 55563
	 */
	public function test_html_and_js_included_for_passed_administrator() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'unfiltered_html is not available to administrators on multisite.' );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$mime_types = get_allowed_mime_types( $user_id );

		$this->assertArrayHasKey( 'htm|html', $mime_types );
		$this->assertArrayHasKey( 'js', $mime_types );
	}

	/**
	 * swf and exe should always be excluded regardless of user.
	 *
	 * @ticket 55563
	 */
	public function test_swf_and_exe_always_excluded() {
		$mime_types = get_allowed_mime_types();

		$this->assertArrayNotHasKey( 'swf', $mime_types );
		$this->assertArrayNotHasKey( 'exe', $mime_types );
	}
}
