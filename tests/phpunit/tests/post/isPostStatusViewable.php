<?php

/**
 * @group post
 *
 * @covers is_post_status_viewable
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
	public static function data_custom_post_statuses() {
		return array(
			'false for non-publicly queryable types' => array(
				array(
					'publicly_queryable' => false,
					'_builtin'           => false,
					'public'             => true,
				),
				false,
			),
			'true for publicly queryable types'      => array(
				array(
					'publicly_queryable' => true,
					'_builtin'           => false,
					'public'             => false,
				),
				true,
			),
			'false for built-in non-public types'    => array(
				array(
					'publicly_queryable' => false,
					'_builtin'           => true,
					'public'             => false,
				),
				false,
			),
			'false for non-built-in public types'    => array(
				array(
					'publicly_queryable' => false,
					'_builtin'           => false,
					'public'             => true,
				),
				false,
			),
			'true for built-in public types'         => array(
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
	 * @ticket 49380
	 *
	 * @dataProvider data_built_in_and_unregistered_status_types
	 *
	 * @param mixed $status   Post status to check.
	 * @param bool  $expected Expected viewable status.
	 */
	public function test_built_in_and_unregistered_status_types( $status, $expected ) {
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
	public static function data_built_in_and_unregistered_status_types() {
		return array(
			'publish'           => array( 'publish', true ),
			'future'            => array( 'future', false ),
			'draft'             => array( 'draft', false ),
			'pending'           => array( 'pending', false ),
			'private'           => array( 'private', false ),
			'trash'             => array( 'trash', false ),
			'auto-draft'        => array( 'auto-draft', false ),
			'inherit'           => array( 'inherit', false ),
			'request-pending'   => array( 'request-pending', false ),
			'request-confirmed' => array( 'request-confirmed', false ),
			'request-failed'    => array( 'request-failed', false ),
			'request-completed' => array( 'request-completed', false ),

			// Various unregistered statuses.
			'unregistered'      => array( 'unregistered-status', false ),
			'false'             => array( false, false ),
			'true'              => array( true, false ),
			'number 20'         => array( 20, false ),
			'null'              => array( null, false ),
			'empty string'      => array( '', false ),
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
