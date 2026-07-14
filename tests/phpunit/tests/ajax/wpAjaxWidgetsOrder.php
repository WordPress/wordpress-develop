<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax widgets order functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_widgets_order
 */
class Tests_Ajax_wpAjaxWidgetsOrder extends WP_Ajax_UnitTestCase {

	/**
	 * Tests that a missing sidebars payload returns -1 without notices.
	 */
	public function test_widgets_order_missing_sidebars() {
		$this->_setRole( 'administrator' );

		$_POST['savewidgets'] = wp_create_nonce( 'save-sidebar-widgets' );

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'widgets-order' );
	}
}

