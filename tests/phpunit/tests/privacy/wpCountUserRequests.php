<?php
/**
 * Test cases for the `wp_count_user_requests()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.2.0
 *
 * @group privacy
 * @covers ::wp_count_user_requests
 */
class Tests_Privacy_wpCountUserRequests extends WP_UnitTestCase {

	/**
	 * Export request ID.
	 *
	 * @since 7.2.0
	 *
	 * @var int $export_request_id
	 */
	protected static $export_request_id;

	/**
	 * Erase request ID.
	 *
	 * @since 7.2.0
	 *
	 * @var int $erase_request_id
	 */
	protected static $erase_request_id;

	/**
	 * Create fixtures.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$export_request_id = $factory->post->create(
			array(
				'post_type'   => 'user_request',
				'post_name'   => 'export_personal_data',
				'post_status' => 'request-pending',
				'post_title'  => 'export@local.test',
			)
		);

		self::$erase_request_id = $factory->post->create(
			array(
				'post_type'   => 'user_request',
				'post_name'   => 'remove_personal_data',
				'post_status' => 'request-confirmed',
				'post_title'  => 'erase@local.test',
			)
		);
	}

	/**
	 * Set up before each test.
	 *
	 * @since 7.2.0
	 */
	public function set_up() {
		parent::set_up();
		// Clear counts cache before each test.
		wp_cache_delete( 'user-request-export_personal_data', 'counts' );
		wp_cache_delete( 'user-request-remove_personal_data', 'counts' );
	}

	/**
	 * Returns an empty stdClass for an invalid or empty type string.
	 *
	 * @ticket 44034
	 */
	public function test_returns_empty_object_for_invalid_type() {
		$actual_empty = wp_count_user_requests( '' );
		$this->assertInstanceOf( 'stdClass', $actual_empty );
		$this->assertEmpty( (array) $actual_empty );

		$actual_invalid = wp_count_user_requests( 'invalid_type' );
		$this->assertInstanceOf( 'stdClass', $actual_invalid );
		$this->assertEmpty( (array) $actual_invalid );
	}

	/**
	 * Counts export requests correctly by status.
	 *
	 * @ticket 44034
	 */
	public function test_counts_export_requests_by_status() {
		$actual = wp_count_user_requests( 'export_personal_data' );

		$this->assertSame( 1, (int) $actual->{'request-pending'} );
		$this->assertSame( 0, (int) $actual->{'request-confirmed'} );
	}

	/**
	 * Counts erase requests correctly by status.
	 *
	 * @ticket 44034
	 */
	public function test_counts_erase_requests_by_status() {
		$actual = wp_count_user_requests( 'remove_personal_data' );

		$this->assertSame( 0, (int) $actual->{'request-pending'} );
		$this->assertSame( 1, (int) $actual->{'request-confirmed'} );
	}

	/**
	 * Result contains all registered post statuses as keys.
	 *
	 * @ticket 44034
	 */
	public function test_result_contains_all_post_statuses() {
		$actual      = wp_count_user_requests( 'export_personal_data' );
		$stati       = get_post_stati();
		$actual_keys = array_keys( (array) $actual );

		foreach ( $stati as $status ) {
			$this->assertContains( $status, $actual_keys, "Missing status: $status" );
		}
	}

	/**
	 * Result is cached after first call.
	 *
	 * @ticket 44034
	 */
	public function test_result_is_cached() {
		wp_count_user_requests( 'export_personal_data' );

		$last_changed = wp_cache_get_last_changed( 'posts' );
		$cached       = wp_cache_get_salted( 'user-request-export_personal_data', 'counts', $last_changed );

		$this->assertNotFalse( $cached );
		$this->assertInstanceOf( 'stdClass', $cached );
	}

	/**
	 * The `wp_count_user_requests` filter can modify the returned counts.
	 *
	 * @ticket 44034
	 */
	public function test_filter_can_modify_counts() {
		add_filter(
			'wp_count_user_requests',
			function ( $counts, $type ) {
				$this->assertSame( 'export_personal_data', $type );
				$modified                      = new stdClass();
				$modified->{'request-pending'} = 999;
				return $modified;
			},
			10,
			2
		);

		$actual = wp_count_user_requests( 'export_personal_data' );

		$this->assertSame( 999, (int) $actual->{'request-pending'} );
	}
}
