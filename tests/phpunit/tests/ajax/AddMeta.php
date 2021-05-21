<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Add Meta AJAX functionality.
 *
 * @group ajax
 */
class Tests_Ajax_AddMeta extends WP_Ajax_UnitTestCase {
	/**
	 * @ticket 43559
	 */
	public function test_post_add_meta_empty_is_allowed_ajax() {
		$p = self::factory()->post->create();

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$_POST = array(
			'post_id'              => $p,
			'metakeyinput'         => 'testkey',
			'metavalue'            => '',
			'_ajax_nonce-add-meta' => wp_create_nonce( 'add-meta' ),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'add-meta' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( '', get_post_meta( $p, 'testkey', true ) );
	}

	/**
	 * @ticket 43559
	 */
	public function test_post_update_meta_empty_is_allowed_ajax() {
		$p = self::factory()->post->create();

		$m = add_post_meta( $p, 'testkey', 'hello' );

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$_POST = array(
			'_ajax_nonce-add-meta' => wp_create_nonce( 'add-meta' ),
			'post_id'              => $p,
			'meta'                 => array(
				$m => array(
					'key'   => 'testkey',
					'value' => '',
				),
			),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'add-meta' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( '', get_post_meta( $p, 'testkey', true ) );
	}
}
