<?php

/**
 * Tests for the wp_auth_check_load function.
 *
 * @group functions.php
 *
 * @covers ::wp_auth_check_load
 */
class Tests_functions_wpAuthCheckLoad extends WP_UnitTestCase {

	/**
	 * Check that the function add both actions.
	 *
	 * @ticket 59820
	 */
	public function test_wp_auth_check_load() {
		$user = self::factory()->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user->ID );
		set_current_screen( 'edit.php' );

		$a1 = new MockAction();
		add_filter( 'wp_auth_check_load', array( $a1, 'filter' ) );

		$this->assertFalse( has_action( 'admin_print_footer_scripts', 'wp_auth_check_html' ) );

		wp_auth_check_load();

		$this->assertSame( 1, $a1->get_call_count() );
		$this->assertSame( 5, has_action( 'admin_print_footer_scripts', 'wp_auth_check_html' ) );
		$this->assertSame( 5, has_action( 'wp_print_footer_scripts', 'wp_auth_check_html' ) );
	}

	/**
	 * Check that adction are not add for not loged in user.
	 *
	 * @ticket 59820
	 */
	public function test_wp_auth_check_load_not_loged_in() {
		set_current_screen( 'edit.php' );

		$a1 = new MockAction();
		add_filter( 'wp_auth_check_load', array( $a1, 'filter' ) );

		wp_auth_check_load();

		$this->assertSame( 1, $a1->get_call_count() );
		$this->assertSame( 5, has_action( 'admin_print_footer_scripts', 'wp_auth_check_html' ) );
	}

	/**
	 * Check that the function returns early in not an admin page.
	 *
	 * @ticket 59820
	 */
	public function test_wp_auth_check_load_not_admin_page() {
		$user = self::factory()->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user->ID );

		$a1 = new MockAction();
		add_filter( 'wp_auth_check_load', array( $a1, 'filter' ) );

		wp_auth_check_load();

		$this->assertSame( 0, $a1->get_call_count() );
		$this->assertFalse( has_action( 'admin_print_footer_scripts', 'wp_auth_check_html' ) );
	}

	/**
	 * Check that function returns ealy if not a disabled admin page.
	 *
	 * @ticket 59820
	 */
	public function test_wp_auth_check_load_not_disabled_screen() {
		$user = self::factory()->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user->ID );
		set_current_screen( 'update.php' );

		$a1 = new MockAction();
		add_filter( 'wp_auth_check_load', array( $a1, 'filter' ) );

		wp_auth_check_load();

		$this->assertSame( 1, $a1->get_call_count() );
		$this->assertFalse( has_action( 'admin_print_footer_scripts', 'wp_auth_check_html' ) );
	}

	/**
	 * Check the actions are not is filter returns false.
	 *
	 * @ticket 59820
	 */
	public function test_wp_auth_check_load_filtered() {
		$user = self::factory()->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user->ID );
		set_current_screen( 'edit.php' );

		$a1 = new MockAction();
		add_filter( 'wp_auth_check_load', array( $a1, 'filter' ) );
		add_filter( 'wp_auth_check_load', '__return_false' );

		wp_auth_check_load();

		$this->assertSame( 1, $a1->get_call_count() );
		$this->assertFalse( has_action( 'admin_print_footer_scripts', 'wp_auth_check_html' ) );
		$this->assertFalse( has_action( 'wp_print_footer_scripts', 'wp_auth_check_html' ) );
	}
}
