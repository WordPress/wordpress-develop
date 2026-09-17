<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_inline_save_tax() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_inline_save_tax
 */
class Tests_wp_ajax_inline_save_tax extends WP_Ajax_UnitTestCase {

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

	/**
	 * Tests successful term update via Quick Edit.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_tax_success(): void {
		wp_set_current_user( self::$admin_id );

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Initial Name',
				'slug'     => 'initial-slug',
			)
		);

		$_POST = array(
			'action'       => 'inline-save-tax',
			'taxonomy'     => 'category',
			'tax_ID'       => $term_id,
			'name'         => 'Updated Name',
			'slug'         => 'updated-slug',
			'_inline_edit' => wp_create_nonce( 'taxinlineeditnonce' ),
		);

		try {
			$this->_handleAjax( 'inline-save-tax' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$term = get_term( $term_id, 'category' );
		$this->assertSame( 'Updated Name', $term->name );
		$this->assertSame( 'updated-slug', $term->slug );
		$this->assertStringContainsString( 'Updated Name', $this->_last_response );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_tax_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'inline-save-tax',
			'taxonomy'     => 'category',
			'tax_ID'       => 1,
			'_inline_edit' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'inline-save-tax' );
	}

	/**
	 * Tests failure due to invalid taxonomy.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_tax_invalid_taxonomy(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'inline-save-tax',
			'taxonomy'     => 'non_existent_taxonomy',
			'_inline_edit' => wp_create_nonce( 'taxinlineeditnonce' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '0' );

		$this->_handleAjax( 'inline-save-tax' );
	}

	/**
	 * Tests failure due to missing tax_ID.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_tax_missing_tax_id(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'inline-save-tax',
			'taxonomy'     => 'category',
			'_inline_edit' => wp_create_nonce( 'taxinlineeditnonce' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'inline-save-tax' );
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_tax_insufficient_permissions(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'category' ) );

		$_POST = array(
			'action'       => 'inline-save-tax',
			'taxonomy'     => 'category',
			'tax_ID'       => $term_id,
			'_inline_edit' => wp_create_nonce( 'taxinlineeditnonce' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'inline-save-tax' );
	}
}
