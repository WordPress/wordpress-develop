<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_sample_permalink() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_sample_permalink
 */
class Tests_wp_ajax_sample_permalink extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Tests successful retrieval of a sample permalink.
	 *
	 * @ticket 65252
	 */
	public function test_sample_permalink_success(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Sample Post',
			)
		);

		$_POST = array(
			'action'               => 'sample-permalink',
			'post_id'              => $post_id,
			'new_title'            => 'Updated Title',
			'new_slug'             => 'updated-slug',
			'samplepermalinknonce' => wp_create_nonce( 'samplepermalink' ),
		);

		try {
			$this->_handleAjax( 'sample-permalink' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$this->assertStringContainsString( 'id="sample-permalink"', $this->_last_response );
		$this->assertStringContainsString( 'updated-slug', $this->_last_response );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_sample_permalink_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'               => 'sample-permalink',
			'post_id'              => 123,
			'samplepermalinknonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'sample-permalink' );
	}

	/**
	 * Tests behavior with default slug/title.
	 *
	 * @ticket 65252
	 */
	public function test_sample_permalink_default_inputs(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Initial Title',
			)
		);

		$_POST = array(
			'action'               => 'sample-permalink',
			'post_id'              => $post_id,
			'samplepermalinknonce' => wp_create_nonce( 'samplepermalink' ),
		);

		try {
			$this->_handleAjax( 'sample-permalink' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$this->assertStringContainsString( 'initial-title', $this->_last_response );
	}
}
