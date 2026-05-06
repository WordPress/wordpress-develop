<?php

/**
 * @group admin
 * @group misc
 * @covers ::set_screen_options
 */
class Tests_set_screen_options extends WP_UnitTestCase {

	/**
	 * @ticket 65183
	 */
	public function test_set_screen_options_no_data() {
		// Suppress doing_it_wrong if any.
		$result = set_screen_options();
		$this->assertNull( $result, 'set_screen_options should return null when wp_screen_options is not set in $_POST.' );
	}

	/**
	 * @ticket 65183
	 * @dataProvider data_set_screen_options
	 *
	 * Tests saving various screen options, including those that require filters.
	 *
	 * @param string $option   The option name.
	 * @param mixed  $value    The option value.
	 * @param mixed  $expected The expected stored value in user meta.
	 */
	public function test_set_screen_options( $option, $value, $expected ) {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['wp_screen_options'] = array(
			'option' => $option,
			'value'  => $value,
		);

		// Mock request method.
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// Set referer and nonce.
		$_SERVER['HTTP_REFERER']       = admin_url( 'edit.php?pagenum=2' );
		$_POST['screenoptionnonce']    = wp_create_nonce( 'screen-options-nonce' );
		$_REQUEST['screenoptionnonce'] = $_POST['screenoptionnonce'];
		$_REQUEST['_wp_http_referer']  = $_SERVER['HTTP_REFERER'];

		if ( 'my_custom_page' === $option || 'layout_columns' === $option ) {
			add_filter( 'set-screen-option', array( $this, 'filter_set_screen_option_custom' ), 10, 3 );
		}

		// Intercept redirect to prevent exit.
		add_filter( 'wp_redirect', array( $this, 'intercept_redirect' ) );

		// Bypass check_admin_referer() failure by making it pass.
		add_filter( 'check_admin_referer', '__return_null' );

		// Bypass die() in check_admin_referer() by using a die handler that doesn't die.
		add_filter( 'wp_die_handler', array( $this, 'wp_die_handler' ) );

		try {
			set_screen_options();
		} catch ( Exception $e ) {
			if ( 'Redirected' === $e->getMessage() ) {
				// Success!
			} else {
				$this->fail( 'The function failed with message: ' . $e->getMessage() );
			}
		}

		$meta = get_user_meta( $user_id, $option, true );
		$this->assertEquals( $expected, $meta );
	}

	public function wp_die_handler( $message = '', $title = '', $args = array() ) {
		return array( $this, 'die_handler' );
	}

	public function die_handler( $message ) {
		throw new Exception( $message );
	}

	public function intercept_redirect( $location ) {
		throw new Exception( 'Redirected' );
	}

	public function filter_set_screen_option_custom( $status, $option, $value ) {
		if ( 'my_custom_page' === $option || 'layout_columns' === $option ) {
			return $value;
		}
		return $status;
	}

	/**
	 * Data provider for test_set_screen_options.
	 *
	 * @return array<string, array{
	 *     option:   string,
	 *     value:    mixed,
	 *     expected: mixed,
	 * }>
	 */
	public function data_set_screen_options(): array {
		return array(
			'edit_per_page'             => array(
				'option'   => 'edit_per_page',
				'value'    => '20',
				'expected' => 20,
			),
			'edit_per_page_invalid'     => array(
				'option'   => 'edit_per_page',
				'value'    => '1000', // Max is 999.
				'expected' => '',
			),
			'upload_per_page'           => array(
				'option'   => 'upload_per_page',
				'value'    => '50',
				'expected' => 50,
			),
			'custom_option_with_filter' => array(
				'option'   => 'my_custom_page',
				'value'    => '10',
				'expected' => 10,
			),
			'layout_columns'            => array(
				'option'   => 'layout_columns',
				'value'    => '2',
				'expected' => 2,
			),
		);
	}

	/**
	 * @ticket 65183
	 *
	 * Tests that set-screen-option filter is correctly applied for custom options.
	 */
	public function test_set_screen_options_with_filters() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['wp_screen_options']    = array(
			'option' => 'custom_per_page',
			'value'  => '25',
		);
		$_SERVER['HTTP_REFERER']       = admin_url( 'admin.php' );
		$_SERVER['REQUEST_METHOD']     = 'POST';
		$_REQUEST['screenoptionnonce'] = wp_create_nonce( 'screen-options-nonce' );
		$_REQUEST['_wp_http_referer']  = $_SERVER['HTTP_REFERER'];

		// Intercept redirect to prevent exit.
		add_filter( 'wp_redirect', array( $this, 'intercept_redirect' ) );

		// Bypass check_admin_referer() failure.
		add_filter( 'check_admin_referer', '__return_null' );

		// Bypass die() in check_admin_referer() by using a die handler that doesn't die.
		add_filter( 'wp_die_handler', array( $this, 'wp_die_handler' ) );

		// Filter ending with _page.
		add_filter( 'set-screen-option', array( $this, 'filter_set_screen_option' ), 10, 3 );

		try {
			set_screen_options();
		} catch ( Exception $e ) {
			if ( 'Redirected' === $e->getMessage() ) {
				// Success!
			} else {
				$this->fail( 'The function failed with message: ' . $e->getMessage() );
			}
		}

		$this->assertEquals( 25, get_user_meta( $user_id, 'custom_per_page', true ) );
	}

	public function filter_set_screen_option( $status, $option, $value ) {
		if ( 'custom_per_page' === $option ) {
			return $value;
		}
		return $status;
	}
}
