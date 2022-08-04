<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax handler for deleting a plugin.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_plugin
 */
class Tests_Ajax_Delete_Plugin extends WP_Ajax_UnitTestCase {

	public function test_missing_nonce() {
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'delete-plugin' );
	}

	public function test_missing_plugin() {
		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['slug']        = 'foo';

		// Make the request.
		try {
			$this->_handleAjax( 'delete-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$expected = array(
			'success' => false,
			'data'    => array(
				'slug'         => '',
				'errorCode'    => 'no_plugin_specified',
				'errorMessage' => 'No plugin specified.',
			),
		);

		$this->assertSameSets( $expected, $response );
	}

	public function test_missing_slug() {
		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['plugin']      = 'foo/bar.php';

		// Make the request.
		try {
			$this->_handleAjax( 'delete-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$expected = array(
			'success' => false,
			'data'    => array(
				'slug'         => '',
				'errorCode'    => 'no_plugin_specified',
				'errorMessage' => 'No plugin specified.',
			),
		);

		$this->assertSameSets( $expected, $response );
	}

	public function test_missing_capability() {
		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['plugin']      = 'foo/bar.php';
		$_POST['slug']        = 'foo';

		// Make the request.
		try {
			$this->_handleAjax( 'delete-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$expected = array(
			'success' => false,
			'data'    => array(
				'delete'       => 'plugin',
				'slug'         => 'foo',
				'errorMessage' => 'Sorry, you are not allowed to delete plugins for this site.',
			),
		);

		$this->assertSameSets( $expected, $response );
	}

	public function test_invalid_file() {
		$this->_setRole( 'administrator' );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['plugin']      = '../foo/bar.php';
		$_POST['slug']        = 'foo';

		// Make the request.
		try {
			$this->_handleAjax( 'delete-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$expected = array(
			'success' => false,
			'data'    => array(
				'delete'       => 'plugin',
				'slug'         => 'foo',
				'errorMessage' => 'Sorry, you are not allowed to delete plugins for this site.',
			),
		);

		$this->assertSameSets( $expected, $response );
	}

	/**
	 * @group ms-excluded
	 *
	 * @covers ::wp_ajax_delete_plugin
	 * @covers ::delete_plugins
	 */
	public function test_delete_plugin() {
		$this->_setRole( 'administrator' );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['plugin']      = 'foo.php';
		$_POST['slug']        = 'foo';

		// Make the request.
		try {
			$this->_handleAjax( 'delete-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$expected = array(
			'success' => true,
			'data'    => array(
				'delete'     => 'plugin',
				'slug'       => 'foo',
				'plugin'     => 'foo.php',
				'pluginName' => '',
			),
		);

		$this->assertSameSets( $expected, $response );
	}
}
