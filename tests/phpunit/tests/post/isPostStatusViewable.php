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
	public static function wpTearDownAfterClass() {
		global $wp_post_statuses;
		unset( $wp_post_statuses['wp_tests_ps'] );
	}

	/**
	 * Test custom post status.
	 *
	 * This may include emulations of built in (_builtin) statuses.
	 *
	 * @ticket 49380
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
	 * Test built-in and unregistered post status.
	 *
	 * @dataProvider data_built_unregistered_in_status_types
	 * @ticket 49380
	 *
	 * @param mixed $status   Post status to check.
	 * @param bool  $expected Expected viewable status.
	 */
	public function test_built_unregistered_in_status_types( $status, $expected ) {
		// Test status passed as string.
		$this->assertSame( $expected, is_post_status_viewable( $status ) );
		// Test status passed as object.
		$this->assertSame( $expected, is_post_status_viewable( get_post_status_object( $status ) ) );
	}

	/**
	 * Data provider for built-in and unregistered post status tests.
	 *
	 * @return array[] {
	 *     @type mixed $status   Post status to check.
	 *     @type bool  $expected Expected viewable status.
	 * }
	 */
	public function data_built_unregistered_in_status_types() {
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

			// Various unregistered statuses.
			array( 'unregistered-status', false ),
			array( false, false ),
			array( true, false ),
			array( 20, false ),
			array( null, false ),
			array( '', false ),
		);
	}

	/**
	 * Sanitize key should not be run when testing.
	 *
	 * @ticket 49380
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

		// Sanitized key should return true.
		$this->assertTrue( is_post_status_viewable( 'wp_tests_ps' ) );
		$this->assertTrue( is_post_status_viewable( get_post_status_object( 'wp_tests_ps' ) ) );

		// Unsanitized key should return false.
		$this->assertFalse( is_post_status_viewable( 'WP_tests_ps' ) );
		$this->assertFalse( is_post_status_viewable( get_post_status_object( 'WP_tests_ps' ) ) );
	}
}
