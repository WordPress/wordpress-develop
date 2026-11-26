<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_time_format() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_time_format
 */
class Tests_wp_ajax_time_format extends WP_Ajax_UnitTestCase {

	/**
	 * Keeps time formatting deterministic across requests and assertions.
	 */
	public function set_up() {
		parent::set_up();

		add_filter(
			'date_i18n',
			static function ( $date, $format ) {
				return wp_date( $format, 1700000000 );
			},
			10,
			2
		);
	}

	/**
	 * Tests formatting a time via AJAX.
	 *
	 * @ticket 65228
	 *
	 * @dataProvider data_time_format
	 *
	 * @param array $payload The AJAX request payload.
	 */
	public function test_time_format( array $payload ): void {
		// Mock the user to allow the request.
		$this->_setRole( 'administrator' );

		$_POST = array_merge(
			array(
				'action'      => 'time_format',
				'_ajax_nonce' => wp_create_nonce( 'time_format' ),
			),
			$payload
		);

		// Make the request.
		try {
			$this->_handleAjax( 'time_format' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected exception.
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			// Expected exception.
			$this->_last_response = (string) $e->getMessage();
		}

		$expected = date_i18n( $payload['date'] );

		$this->assertSame( $expected, $this->_last_response );
	}

	/**
	 * Tests time format validation (sanitize_option).
	 *
	 * @ticket 65228
	 */
	public function test_time_format_invalid(): void {
		$this->_setRole( 'administrator' );

		$_POST['action']      = 'time_format';
		$_POST['_ajax_nonce'] = wp_create_nonce( 'time_format' );
		$_POST['date']        = '<script>alert(1)</script>H:i';

		$actual_format = null;
		add_filter(
			'date_i18n',
			static function ( $date, $format ) use ( &$actual_format ) {
				$actual_format = $format;
				return 'formatted time';
			},
			10,
			2
		);

		try {
			$this->_handleAjax( 'time_format' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = (string) $e->getMessage();
		}

		$this->assertSame( 'alert(1)H:i', $actual_format );
		$this->assertSame( 'formatted time', $this->_last_response );
	}

	/**
	 * Tests as an unprivileged user.
	 *
	 * @ticket 65228
	 */
	public function test_unprivileged_user(): void {
		// wp_ajax_time_format() does not have an explicit permission check,
		// but it is only registered for logged-in users.
		$this->_setRole( 'subscriber' );

		$_POST['action']      = 'time_format';
		$_POST['_ajax_nonce'] = wp_create_nonce( 'time_format' );
		$_POST['date']        = 'H:i';

		try {
			$this->_handleAjax( 'time_format' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = (string) $e->getMessage();
		}

		$this->assertSame( date_i18n( 'H:i' ), $this->_last_response );
	}

	/**
	 * Data provider for test_time_format.
	 *
	 * @return array<string, array{
	 *     payload: array{
	 *         date: string,
	 *     },
	 * }>
	 */
	public static function data_time_format(): array {
		return array(
			'standard_time' => array(
				'payload' => array(
					'date' => 'H:i',
				),
			),
			'custom_time'   => array(
				'payload' => array(
					'date' => 'g:i a',
				),
			),
			'empty_time'    => array(
				'payload' => array(
					'date' => '',
				),
			),
		);
	}
}
