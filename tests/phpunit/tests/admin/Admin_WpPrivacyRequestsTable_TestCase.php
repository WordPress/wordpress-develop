<?php

abstract class Admin_WpPrivacyRequestsTable_TestCase extends WP_UnitTestCase {

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
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $instance, 'export_personal_data' );

		// Set the post type as 'user_request'.
		$reflection_property = $reflection->getProperty( 'post_type' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $instance, 'user_request' );

		return $instance;
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
}
