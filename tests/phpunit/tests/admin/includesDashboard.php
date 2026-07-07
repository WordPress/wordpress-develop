<?php
/**
 * Tests for admin dashboard functionality.
 *
 * @group admin
 * @group dashboard
 *
 * @package Tests\Admin
 */

/**
 * Class Tests_Admin_IncludesDashboard
 */
class Tests_Admin_IncludesDashboard extends WP_UnitTestCase {

	/**
	 * Set up the test environment with the dashboard functions.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'wp_dashboard_quick_press' ) ) {
			require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		}
	}

	/**
	 * Tear down the test environment:
	 * Delete last post id option to avoid collisions with other tests.
	 */
	public function tear_down() {
		delete_user_option( get_current_user_id(), 'dashboard_quick_press_last_post_id' );
		parent::tear_down();
	}

	/**
	 * Test that wp_dashboard_quick_press includes default post format hidden field.
	 *
	 * @ticket 42910
	 * @covers ::wp_dashboard_quick_press
	 */
	public function test_wp_dashboard_quick_press_default_post_format_applied_to_draft() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		add_theme_support( 'post-formats', array( 'aside', 'gallery', 'quote' ) );
		update_option( 'default_post_format', 'quote' );

		set_current_screen( 'dashboard' );

		ob_start();
		wp_dashboard_quick_press();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="post_format"', $output );
		$this->assertStringContainsString( 'value="quote"', $output );

		// Clean up or there will be a collision with other tests.
		delete_option( 'default_post_format' );
		remove_theme_support( 'post-formats' );
	}
}
