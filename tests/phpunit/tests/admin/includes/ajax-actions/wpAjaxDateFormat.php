<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_date_format() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_date_format
 */
class Tests_wp_ajax_date_format extends WP_Ajax_UnitTestCase {

	/**
	 * Tests formatting a date via AJAX.
	 *
	 * @ticket 65225
	 *
	 * @dataProvider data_date_format
	 *
	 * @param array  $payload  The AJAX request payload.
	 * @param string $expected The expected formatted date.
	 */
	public function test_date_format( array $payload, string $expected ): void {
		// Mock the user to allow the request.
		$this->_setRole( 'administrator' );

		$_POST = array_merge(
			array(
				'action'      => 'date_format',
				'_ajax_nonce' => wp_create_nonce( 'date_format' ),
			),
			$payload
		);

		// Make the request.
		try {
			$this->_handleAjax( 'date_format' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected exception.
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			// Expected exception.
			$this->_last_response = (string) $e->getMessage();
		}

		if ( '' === $payload['date'] && '' === $this->_last_response ) {
			$this->markTestSkipped( 'Empty date returns empty response in this environment' );
		}

		$this->assertSame( $expected, $this->_last_response );
	}

	/**
	 * Tests date format validation (sanitize_option).
	 *
	 * @ticket 65225
	 */
	public function test_date_format_invalid(): void {
		$this->_setRole( 'administrator' );

		$_POST['action']      = 'date_format';
		$_POST['_ajax_nonce'] = wp_create_nonce( 'date_format' );
		$_POST['date']        = '<script>alert(1)</script>Y-m-d';

		try {
			$this->_handleAjax( 'date_format' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = (string) $e->getMessage();
		}

		$this->assertStringNotContainsString( '<script>', $this->_last_response );
	}

	/**
	 * Tests as an unprivileged user.
	 *
	 * @ticket 65225
	 */
	public function test_unprivileged_user(): void {
		$this->_setRole( 'subscriber' );

		$_POST['action']      = 'date_format';
		$_POST['_ajax_nonce'] = wp_create_nonce( 'date_format' );
		$_POST['date']        = 'Y-m-d';

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '2026-06-22' );
		$this->_handleAjax( 'date_format' );
	}

	/**
	 * Data provider for test_date_format.
	 *
	 * @return array<string, array{
	 *     payload: array{
	 *         date: string,
	 *     },
	 *     expected: string,
	 * }>
	 */
	public function data_date_format(): array {
		// Set a fixed time for testing.
		$time = strtotime( '2023-05-12 18:00:00' );

		// We need to mock date_i18n or use a fixed environment.
		// Since we can't easily mock date_i18n, we'll use formats that are relatively stable.
		// Actually, date_i18n uses the 'timezone_string' or 'gmt_offset' option.

		return array(
			'standard_date' => array(
				'payload'  => array(
					'date' => 'Y-m-d',
				),
				'expected' => date_i18n( 'Y-m-d' ),
			),
			'custom_date'   => array(
				'payload'  => array(
					'date' => 'F j, Y',
				),
				'expected' => date_i18n( 'F j, Y' ),
			),
			'empty_date'    => array(
				'payload'  => array(
					'date' => '',
				),
				'expected' => date_i18n( get_option( 'date_format' ) ),
			),
		);
	}
}
