<?php

/**
 * @group post
 */
class Tests_Post_IsPostStatusViewable extends WP_UnitTestCase {

	/**
	 * Remove the test status from the global when finished.
	 *
	 * @global $wp_post_statuses
	 */
	static function wpTearDownAfterClass() {
		global $wp_post_statuses;
		unset( $wp_post_statuses['wp_tests_ps'] );
	}

	/**
	 * Test custom post status.
	 *
	 * This may include emulations of built in (_builtin) statuses.
	 *
	 * @dataProvider data_custom_post_statuses
	 *
	 * @param array $cps_args Registration arguments.
	 * @param bool  $expected Expected result.
	 */
	public function test_custom_post_statuses( $cps_args, $expected ) {
		register_post_status(
			'wp_tests_ps',
			$cps_args
		);

		// Test status passed as string.
		$this->assertSame( $expected, is_post_status_viewable( 'wp_tests_ps' ) );
		// Test status passed as object.
		$this->assertSame( $expected, is_post_status_viewable( get_post_status_object( 'wp_tests_ps' ) ) );
	}

	/**
	 * Data provider for custom post status tests.
	 *
	 * @return array[] {
	 *     array CPS registration args.
	 *     bool  Expected result.
	 * }
	 */
	public function data_custom_post_statuses() {
		return array(
			// 0. False for non-publically queryable types.
			array(
				array(
					'publicly_queryable' => false,
					'_builtin'           => false,
					'public'             => true,
				),
				false,
			),
			// 1. True for publically queryable types.
			array(
				array(
					'publicly_queryable' => true,
					'_builtin'           => false,
					'public'             => false,
				),
				true,
			),
			// 2. False for built-in non-public types.
			array(
				array(
					'publicly_queryable' => false,
					'_builtin'           => true,
					'public'             => false,
				),
				false,
			),
			// 3. False for non-built-in public types.
			array(
				array(
					'publicly_queryable' => false,
					'_builtin'           => false,
					'public'             => true,
				),
				false,
			),
			// 4. True for built-in public types.
			array(
				array(
					'publicly_queryable' => false,
					'_builtin'           => true,
					'public'             => true,
				),
				true,
			),
		);
	}

	/**
	 * Test built-in post status.
	 *
	 * @dataProvider data_built_in_status_types
	 *
	 * @param array $status   Post status to test.
	 * @param bool  $expected Expected result.
	 */
	function test_built_in_status_types( $status, $expected ) {
		// Test status passed as string.
		$this->assertSame( $expected, is_post_status_viewable( $status ) );
		// Test status passed as object.
		$this->assertSame( $expected, is_post_status_viewable( get_post_status_object( $status ) ) );
	}

	/**
	 * Data provider for built-in post status tests.
	 *
	 * @return array[] {
	 *     string CPS name.
	 *     bool   Expected result.
	 * }
	 */
	public function data_built_in_status_types() {
		return array(
			array( 'publish', true ),
			array( 'future', false ),
			array( 'draft', false ),
			array( 'pending', false ),
			array( 'private', false ),
			array( 'trash', false ),
			array( 'auto-draft', false ),
			array( 'inherit', false ),
			array( 'request-pending', false ),
			array( 'request-confirmed', false ),
			array( 'request-failed', false ),
			array( 'request-completed', false ),
		);
	}

	/**
	 * Unregistered post statuses should fail.
	 */
	public function test_unregistered_post_status() {
		$this->assertFalse( is_post_status_viewable( 'test_unregistered_post_status' ) );
		$this->assertFalse( is_post_status_viewable( get_post_status_object( 'test_unregistered_post_status' ) ) );
	}

	/**
	 * Sanitize key should not be run when testing.
	 */
	public function test_sanitize_key_not_run() {
		register_post_status(
			'WP_Tests_ps',
			array(
				'publicly_queryable' => true,
				'_builtin'           => false,
				'public'             => true,
			)
		);

		// Sanitized key should pass.
		$this->assertTrue( is_post_status_viewable( 'wp_tests_ps' ) );
		$this->assertTrue( is_post_status_viewable( get_post_status_object( 'wp_tests_ps' ) ) );

		// Unsanitized key should fail.
		$this->assertFalse( is_post_status_viewable( 'WP_tests_ps' ) );
		$this->assertFalse( is_post_status_viewable( get_post_status_object( 'WP_tests_ps' ) ) );
	}
}
