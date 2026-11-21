<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_closed_postboxes() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_closed_postboxes
 */
class Tests_Admin_Includes_AjaxActions_closedPostboxes extends WP_Ajax_UnitTestCase {

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
	 * @ticket 65252
	 */
	public function test_closed_postboxes_saves_closed_and_hidden_boxes(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'closedpostboxesnonce' => wp_create_nonce( 'closedpostboxes' ),
			'closed'               => 'postbox1,,postbox2',
			'hidden'               => 'postbox3,submitdiv',
			'page'                 => 'testpage',
		);

		$this->assertSame( '1', $this->get_ajax_response( 'closed-postboxes' ) );
		$this->assertSame( array( 'postbox1', 'postbox2' ), array_values( get_user_meta( self::$admin_id, 'closedpostboxes_testpage', true ) ) );
		$this->assertSame( array( 'postbox3' ), array_values( get_user_meta( self::$admin_id, 'metaboxhidden_testpage', true ) ) );
	}

	/**
	 * @ticket 65252
	 *
	 * @dataProvider data_always_shown_postboxes
	 *
	 * @param string $postbox Postbox ID that can never be hidden.
	 */
	public function test_closed_postboxes_never_hides_always_shown_boxes( string $postbox ): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'closedpostboxesnonce' => wp_create_nonce( 'closedpostboxes' ),
			'hidden'               => "postbox3,{$postbox}",
			'page'                 => 'testpage',
		);

		$this->assertSame( '1', $this->get_ajax_response( 'closed-postboxes' ) );
		$this->assertSame( array( 'postbox3' ), array_values( get_user_meta( self::$admin_id, 'metaboxhidden_testpage', true ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public static function data_always_shown_postboxes(): array {
		return array(
			'submitdiv'     => array( 'submitdiv' ),
			'linksubmitdiv' => array( 'linksubmitdiv' ),
			'manage-menu'   => array( 'manage-menu' ),
			'create-menu'   => array( 'create-menu' ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_closed_postboxes_rejects_unsanitized_page(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'closedpostboxesnonce' => wp_create_nonce( 'closedpostboxes' ),
			'closed'               => 'postbox1',
			'hidden'               => 'postbox2',
			'page'                 => 'invalid page!',
		);

		$this->assertSame( '0', $this->get_ajax_response( 'closed-postboxes' ) );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'closedpostboxes_invalid page!', true ) );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'metaboxhidden_invalid page!', true ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_closed_postboxes_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'closedpostboxesnonce' => 'invalid-nonce',
			'closed'               => 'postbox1',
			'page'                 => 'testpage',
		);

		$this->assertSame( '-1', $this->get_ajax_response( 'closed-postboxes' ) );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'closedpostboxes_testpage', true ) );
	}
}
