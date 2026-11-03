<?php

/**
 * Tests for the `user_can_richedit()` function.
 *
 * @group general
 * @group editor
 *
 * @covers ::user_can_richedit
 */
class Tests_General_UserCanRichedit extends WP_UnitTestCase {

	/**
	 * Original $is_chrome global.
	 *
	 * @var bool|null
	 */
	private $orig_is_chrome;

	public function set_up() {
		parent::set_up();

		global $is_chrome;
		$this->orig_is_chrome = isset( $is_chrome ) ? $is_chrome : null;
	}

	/**
	 * Cleans up global rich edit cache and restores browser flag.
	 */
	public function tear_down() {
		global $wp_rich_edit, $is_chrome;

		$wp_rich_edit = null;

		if ( null !== $this->orig_is_chrome ) {
			$is_chrome = $this->orig_is_chrome;
		} else {
			unset( $GLOBALS['is_chrome'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that user_can_richedit() returns true when browser supports it and user option is enabled.
	 *
	 * @ticket 65819
	 */
	public function test_user_can_richedit_enabled() {
		global $wp_rich_edit, $is_chrome;

		$wp_rich_edit = null;
		$is_chrome    = true;

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'rich_editing', 'true' );

		$this->assertTrue( user_can_richedit() );
	}

	/**
	 * Tests that user_can_richedit() returns false when user option is disabled.
	 *
	 * @ticket 65819
	 */
	public function test_user_can_richedit_disabled_by_user_option() {
		global $wp_rich_edit, $is_chrome;

		$wp_rich_edit = null;
		$is_chrome    = true;

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'rich_editing', 'false' );

		$this->assertFalse( user_can_richedit() );
	}
}
