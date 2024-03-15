<?php

require_once __DIR__ . '/Admin_WpMediaListTable_TestCase.php';

/**
 * @group admin
 *
 * @covers WP_Media_List_Table::prepare_items
 */
class Admin_WpMediaListTable_PrepareItems_Test extends Admin_WpMediaListTable_TestCase {

	/**
	 * Tests that a call to WP_Media_List_Table::prepare_items() on a site without any scheduled events
	 * does not result in a PHP warning.
	 *
	 * The warning that we should not see:
	 * PHP <= 7.4: `Invalid argument supplied for foreach()`.
	 * PHP 8.0 and higher: `Warning: foreach() argument must be of type array|object, bool given`.
	 *
	 * Note: This does not test the actual functioning of the WP_Media_List_Table::prepare_items() method.
	 * It just and only tests for/against the PHP warning.
	 *
	 * @ticket 53949
	 * @group cron
	 */
	public function test_prepare_items_without_cron_option_does_not_throw_warning() {
		global $wp_query;

		// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
		$mock = $this->getMockBuilder( WP_Media_List_Table::class )
			->disableOriginalConstructor()
			->disallowMockingUnknownTypes()
			->setMethods( array( 'set_pagination_args' ) )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'set_pagination_args' );

		$wp_query->query_vars['posts_per_page'] = 10;
		delete_option( 'cron' );

		// Verify that the cause of the error is in place.
		$this->assertIsArray( _get_cron_array(), '_get_cron_array() does not return an array.' );
		$this->assertEmpty( _get_cron_array(), '_get_cron_array() does not return an empty array.' );

		// If this test does not error out due to the PHP warning, we're good.
		$mock->prepare_items();
	}
}
