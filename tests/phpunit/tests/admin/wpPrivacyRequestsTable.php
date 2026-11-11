<?php
/**
 * Test the `WP_Privacy_Requests_Table` class.
 *
 * @package WordPress\UnitTests
 *
 * @since 5.1.0
 *
 * @group admin
 * @group privacy
 */
class Tests_Admin_wpPrivacyRequestsTable extends WP_UnitTestCase {

	/**
	 * Temporary storage for SQL to allow a filter to access it.
	 *
	 * Used in the `test_columns_should_be_sortable()` test method.
	 *
	 * @var string
	 */
	private $sql;

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		unset( $this->sql );

		parent::tear_down();
	}

	/**
	 * Get instance for mocked class.
	 *
	 * @since 5.1.0
	 *
	 * @return PHPUnit_Framework_MockObject_MockObject|WP_Privacy_Requests_Table Mocked class instance.
	 */
	public function get_mocked_class_instance() {
		$args = array(
			'plural'   => 'privacy_requests',
			'singular' => 'privacy_request',
			'screen'   => 'export_personal_data',
		);

		$instance = $this
			->getMockBuilder( 'WP_Privacy_Requests_Table' )
			->setConstructorArgs( array( $args ) )
			->getMockForAbstractClass();

		$reflection = new ReflectionClass( $instance );

		// Set the request type as 'export_personal_data'.
		$reflection_property = $reflection->getProperty( 'request_type' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection_property->setAccessible( true );
		}
		$reflection_property->setValue( $instance, 'export_personal_data' );

		// Set the post type as 'user_request'.
		$reflection_property = $reflection->getProperty( 'post_type' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection_property->setAccessible( true );
		}
		$reflection_property->setValue( $instance, 'user_request' );

		return $instance;
	}

	/**
	 * Test columns should be sortable.
	 *
	 * @since 5.1.0
	 *
	 * @param string|null $order    Order.
	 * @param string|null $orderby  Order by.
	 * @param string|null $search   Search term.
	 * @param string      $expected Expected in SQL query.

	 * @dataProvider data_columns_should_be_sortable
	 * @covers WP_Privacy_Requests_Table::prepare_items
	 * @ticket 43960
	 */
	public function test_columns_should_be_sortable( $order, $orderby, $search, $expected ) {
		global $wpdb;

		$table     = $this->get_mocked_class_instance();
		$this->sql = '';

		$_REQUEST['order']   = $order;
		$_REQUEST['orderby'] = $orderby;
		$_REQUEST['s']       = $search;

		add_filter( 'posts_request', array( $this, 'filter_posts_request' ) );
		$table->prepare_items();
		remove_filter( 'posts_request', array( $this, 'filter_posts_request' ) );

		unset( $_REQUEST['order'] );
		unset( $_REQUEST['orderby'] );
		unset( $_REQUEST['s'] );

		$this->assertStringContainsString( "ORDER BY {$wpdb->posts}.{$expected}", $this->sql );
	}

	/**
	 * Filter to grab the complete SQL query.
	 *
	 * @since 5.1.0
	 *
	 * @param string $request The complete SQL query.
	 * @return string The complete SQL query.
	 */
	public function filter_posts_request( $request ) {
		$this->sql = $request;
		return $request;
	}

	/**
	 * Data provider for `test_columns_should_be_sortable()`.
	 *
	 * @since 5.1.0
	 *
	 * @return array {
	 *     @type array {
	 *         @type string|null Order.
	 *         @type string|null Order by.
	 *         @type string|null Search term.
	 *         @type string      Expected in SQL query.
	 *     }
	 * }
	 */
	public static function data_columns_should_be_sortable() {
		return array(
			// Default order (ID) DESC.
			array(
				'order'    => null,
				'orderby'  => null,
				's'        => null,
				'expected' => 'post_date DESC',
			),
			// Default order (ID) DESC.
			array(
				'order'    => '',
				'orderby'  => '',
				's'        => '',
				'expected' => 'post_date DESC',
			),
			// Order by requester (post_title) ASC.
			array(
				'order'    => 'ASC',
				'orderby'  => 'requester',
				's'        => '',
				'expected' => 'post_title ASC',
			),
			// Order by requester (post_title) DESC.
			array(
				'order'    => 'DESC',
				'orderby'  => 'requester',
				's'        => null,
				'expected' => 'post_title DESC',
			),
			// Order by requested (post_date) ASC.
			array(
				'order'    => 'ASC',
				'orderby'  => 'requested',
				's'        => null,
				'expected' => 'post_date ASC',
			),
			// Order by requested (post_date) DESC.
			array(
				'order'    => 'DESC',
				'orderby'  => 'requested',
				's'        => null,
				'expected' => 'post_date DESC',
			),
			// Search and order by relevance.
			array(
				'order'    => null,
				'orderby'  => null,
				's'        => 'foo',
				'expected' => 'post_title LIKE',
			),
			// Search and order by requester (post_title) ASC.
			array(
				'order'    => 'ASC',
				'orderby'  => 'requester',
				's'        => 'foo',
				'expected' => 'post_title ASC',
			),
			// Search and order by requested (post_date) ASC.
			array(
				'order'    => 'ASC',
				'orderby'  => 'requested',
				's'        => 'foo',
				'expected' => 'post_date ASC',
			),
		);
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_Privacy_Requests_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		$expected = array(
			'all' => '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/export-personal-data.php" class="current" aria-current="page">All <span class="count">(0)</span></a>',
		);

		$this->assertSame( $expected, $this->get_mocked_class_instance()->get_views() );
	}

	/**
	 * Test the get_timestamp_as_date method formats timestamps correctly.
	 *
	 * @ticket 44267
	 *
	 * @covers WP_Privacy_Requests_Table::get_timestamp_as_date
	 */
	public function test_get_timestamp_as_date() {
		$table = $this->get_mocked_class_instance();

		$reflection = new ReflectionClass( $table );
		$method     = $reflection->getMethod( 'get_timestamp_as_date' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$date_format = __( 'Y/m/d' );
		$time_format = __( 'g:i a' );

		$current_time = time();

		$this->assertSame( '', $method->invoke( $table, '' ) );

		// Test recent timestamp (less than 24 hours ago).
		$recent_time = $current_time - HOUR_IN_SECONDS;
		$result      = $method->invoke( $table, $recent_time );
		$this->assertStringContainsString( 'ago', $result );

		$old_time = $current_time - 2 * DAY_IN_SECONDS;
		$result   = $method->invoke( $table, $old_time );

		$date_part = date_i18n( $date_format, $old_time );
		$time_part = date_i18n( $time_format, $old_time );

		$this->assertStringContainsString( $date_part, $result );
		$this->assertStringContainsString( 'at', $result );
		$this->assertStringContainsString( $time_part, $result );
	}

	/**
	 * The requester's email is escaped in the email and checkbox columns of each request table.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_request_tables
	 *
	 * @covers WP_Privacy_Requests_Table::column_email
	 * @covers WP_Privacy_Requests_Table::column_cb
	 * @covers WP_Privacy_Data_Export_Requests_List_Table::column_email
	 * @covers WP_Privacy_Data_Removal_Requests_List_Table::column_email
	 *
	 * @param string $table_class  The list table class, or an empty string for the base class.
	 * @param string $request_type The request type.
	 */
	public function test_request_email_is_escaped_in_columns( $table_class, $request_type ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request_id     = wp_create_user_request( 'requester@example.org', $request_type );
		$request        = wp_get_user_request( $request_id );
		$request->email = '<img src=x onerror=alert(1)>@example.org';

		if ( '' === $table_class ) {
			$table = $this->get_mocked_class_instance();
		} else {
			$table = new $table_class(
				array(
					'plural'   => 'privacy_requests',
					'singular' => 'privacy_request',
					'screen'   => $request_type,
				)
			);
		}

		foreach ( array( 'column_email', 'column_cb' ) as $column ) {
			$output = $table->$column( $request );

			$this->assertStringNotContainsString( '<img', $output, "The email should be escaped in {$column}()." );
			$this->assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;@example.org', $output, "The escaped email should be shown in {$column}()." );
		}
	}

	/**
	 * Data provider for test_request_email_is_escaped_in_columns().
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_request_tables() {
		return array(
			'base requests table'   => array( '', 'export_personal_data' ),
			'export requests list'  => array( 'WP_Privacy_Data_Export_Requests_List_Table', 'export_personal_data' ),
			'removal requests list' => array( 'WP_Privacy_Data_Removal_Requests_List_Table', 'remove_personal_data' ),
		);
	}

	/**
	 * Archived requests get their own view and are not counted in "All".
	 *
	 * @ticket 44222
	 *
	 * @covers WP_Privacy_Requests_Table::get_views
	 */
	public function test_get_views_should_exclude_archived_requests_from_all() {
		$completed = wp_create_user_request( 'completed@example.com', 'export_personal_data' );
		$archived  = wp_create_user_request( 'archived@example.com', 'export_personal_data' );
		wp_create_user_request( 'pending@example.com', 'export_personal_data' );

		_wp_privacy_completed_request( $completed );
		_wp_privacy_completed_request( $archived );
		_wp_privacy_archive_request( $archived );

		$views = $this->get_mocked_class_instance()->get_views();

		$this->assertStringContainsString( 'All <span class="count">(2)</span>', $views['all'] );
		$this->assertArrayHasKey( 'request-archived', $views );
		$this->assertStringContainsString( 'filter-status=request-archived', $views['request-archived'] );
		$this->assertStringContainsString( 'Archived <span class="count">(1)</span>', $views['request-archived'] );
	}

	/**
	 * @ticket 44222
	 *
	 * @covers WP_Privacy_Requests_Table::get_bulk_actions
	 */
	public function test_get_bulk_actions_should_depend_on_archived_view() {
		$method = new ReflectionMethod( 'WP_Privacy_Requests_Table', 'get_bulk_actions' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$table = $this->get_mocked_class_instance();

		$actions = $method->invoke( $table );
		$this->assertArrayHasKey( 'archive', $actions );
		$this->assertArrayNotHasKey( 'unarchive', $actions );

		$_REQUEST['filter-status'] = 'request-archived';
		$actions                   = $method->invoke( $table );
		unset( $_REQUEST['filter-status'] );

		$this->assertSame( array( 'unarchive', 'delete' ), array_keys( $actions ) );
	}

	/**
	 * @ticket 44222
	 *
	 * @covers WP_Privacy_Requests_Table::process_bulk_action
	 */
	public function test_process_bulk_action_should_archive_and_restore_requests() {
		global $wp_settings_errors;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$completed = wp_create_user_request( 'completed@example.com', 'export_personal_data' );
		$pending   = wp_create_user_request( 'pending@example.com', 'export_personal_data' );
		_wp_privacy_completed_request( $completed );

		$table = $this->get_mocked_class_instance();

		$_REQUEST['_wpnonce']   = wp_create_nonce( 'bulk-privacy_requests' );
		$_REQUEST['request_id'] = array( $completed, $pending );
		$_REQUEST['action']     = 'archive';
		$table->process_bulk_action();

		$this->assertSame( 'request-archived', get_post_status( $completed ), 'The completed request should be archived.' );
		$this->assertSame( 'request-pending', get_post_status( $pending ), 'The pending request should not be archived.' );
		$this->assertSame( array( 'error', 'success' ), wp_list_pluck( $wp_settings_errors, 'type' ) );

		$wp_settings_errors     = array();
		$_REQUEST['request_id'] = array( $completed );
		$_REQUEST['action']     = 'unarchive';
		$table->process_bulk_action();

		unset( $_REQUEST['_wpnonce'], $_REQUEST['request_id'], $_REQUEST['action'] );

		$this->assertSame( 'request-completed', get_post_status( $completed ) );
		$this->assertSame( array( 'success' ), wp_list_pluck( $wp_settings_errors, 'type' ) );
		$wp_settings_errors = array();
	}

	/**
	 * Completed requests offer "Archive", archived ones offer "Restore" and "Delete permanently".
	 *
	 * @ticket 44222
	 *
	 * @dataProvider data_request_list_tables
	 *
	 * @param string $table_class  List table class name.
	 * @param string $request_type Request type.
	 */
	public function test_next_steps_should_offer_archive_actions( $table_class, $request_type ) {
		$request_id = wp_create_user_request( 'requester@example.org', $request_type );
		_wp_privacy_completed_request( $request_id );

		$table = new $table_class(
			array(
				'plural'   => 'privacy_requests',
				'singular' => 'privacy_request',
				'screen'   => $request_type,
			)
		);

		ob_start();
		$table->column_next_steps( wp_get_user_request( $request_id ) );
		$completed_output = ob_get_clean();

		$this->assertStringContainsString( 'action=archive', $completed_output );
		$this->assertStringNotContainsString( 'action=delete', $completed_output );

		_wp_privacy_archive_request( $request_id );
		$request = wp_get_user_request( $request_id );

		ob_start();
		$table->column_next_steps( $request );
		$archived_output = ob_get_clean();

		$this->assertStringContainsString( 'action=unarchive', $archived_output );
		$this->assertStringContainsString( 'action=delete', $archived_output );
		$this->assertStringNotContainsString( 'complete-request', $table->column_email( $request ), 'Archived requests should not offer "Complete request".' );
	}

	/**
	 * Data provider for test_next_steps_should_offer_archive_actions().
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_request_list_tables() {
		return array(
			'export requests list'  => array( 'WP_Privacy_Data_Export_Requests_List_Table', 'export_personal_data' ),
			'removal requests list' => array( 'WP_Privacy_Data_Removal_Requests_List_Table', 'remove_personal_data' ),
		);
	}
}
