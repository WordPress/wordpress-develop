<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing meta box reordering AJAX functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_meta_box_reordering
 */
class Tests_Ajax_wpAjaxMetaBoxReordering extends WP_Ajax_UnitTestCase {

	/**
	 * @dataProvider data_meta_box_reordering_states
	 *
	 * @param int|string $enabled  Whether meta box reordering is enabled.
	 * @param string     $expected The expected stored user option.
	 */
	public function test_wp_ajax_meta_box_reordering_saves_user_option( $enabled, $expected ) {
		$this->_setRole( 'administrator' );

		$_POST = array(
			'screenoptionnonce' => wp_create_nonce( 'screen-options-nonce' ),
			'enabled'           => $enabled,
		);

		try {
			$this->_handleAjax( 'meta-box-reordering' );
			$this->fail( 'Expected exception: WPAjaxDieStopException' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$this->assertSame( $expected, get_user_option( 'meta_box_reordering', get_current_user_id() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_meta_box_reordering_states() {
		return array(
			'enabled'             => array( 1, 'enabled' ),
			'enabled as string'   => array( '1', 'enabled' ),
			'disabled'            => array( 0, 'disabled' ),
			'disabled as string'  => array( '0', 'disabled' ),
			'disabled when false' => array( 'false', 'disabled' ),
		);
	}
}
