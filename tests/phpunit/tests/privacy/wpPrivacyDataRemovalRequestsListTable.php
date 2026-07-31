<?php
/**
 * Test cases for WP_Privacy_Data_Removal_Requests_List_Table::column_email().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.9.0
 *
 * @group privacy
 * @group admin
 * @covers WP_Privacy_Data_Removal_Requests_List_Table::column_email
 */
class Tests_Privacy_WpPrivacyDataRemovalRequestsListTable extends WP_UnitTestCase {

	/**
	 * Request ID.
	 *
	 * @var int
	 */
	protected static $request_id;

	/**
	 * List table instance.
	 *
	 * @var WP_Privacy_Data_Removal_Requests_List_Table
	 */
	protected static $list_table;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-requests-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php';

		self::$request_id = wp_create_user_request( 'requester@example.com', 'remove_personal_data' );
		self::$list_table = new WP_Privacy_Data_Removal_Requests_List_Table( array( 'screen' => 'erase-personal-data' ) );
	}

	/**
	 * @ticket 65735
	 */
	public function test_column_email_close_request_link_text() {
		$request = wp_get_user_request( self::$request_id );
		ob_start();
		echo self::$list_table->column_email( $request );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Close request', $output, 'Row action should be labelled "Close request".' );
		$this->assertStringNotContainsString( 'Complete request', $output, 'Row action should not use the ambiguous "Complete request" label.' );
	}

	/**
	 * @ticket 65735
	 */
	public function test_column_email_aria_label_references_erasure_not_export() {
		$request = wp_get_user_request( self::$request_id );
		ob_start();
		echo self::$list_table->column_email( $request );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'erasure request', $output, 'Aria-label should reference an erasure request, not an export request.' );
		$this->assertStringNotContainsString( 'export request', $output, 'Aria-label should not reference an export request on the erasure screen.' );
	}
}
