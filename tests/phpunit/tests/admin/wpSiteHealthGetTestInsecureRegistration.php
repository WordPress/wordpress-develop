<?php

/**
 * Tests for WP_Site_Health::get_test_insecure_registration().
 *
 * @group admin
 * @group site-health
 *
 * @covers WP_Site_Health::get_test_insecure_registration
 */
class Tests_Admin_wpSiteHealthGetTestInsecureRegistration extends WP_UnitTestCase {

	/**
	 * Loads the Site Health class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	}

	/**
	 * Tests the status for each registration setting and default role.
	 *
	 * @ticket 66359
	 *
	 * @dataProvider data_registration_settings
	 *
	 * @param int    $users_can_register Whether anyone can register.
	 * @param string $default_role       The default role for new users.
	 * @param string $expected_status    The expected test status.
	 */
	public function test_status_depends_on_registration_and_default_role( $users_can_register, $default_role, $expected_status ) {
		$this->set_registration( $users_can_register );
		update_option( 'default_role', $default_role );

		$result = ( new WP_Site_Health() )->get_test_insecure_registration();

		$this->assertSame( $expected_status, $result['status'] );
		$this->assertSame( 'insecure_registration', $result['test'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: int, 1: string, 2: string}>
	 */
	public static function data_registration_settings() {
		return array(
			'registration closed, administrator' => array( 0, 'administrator', 'good' ),
			'registration closed, editor'        => array( 0, 'editor', 'good' ),
			'registration open, subscriber'      => array( 1, 'subscriber', 'good' ),
			'registration open, contributor'     => array( 1, 'contributor', 'good' ),
			'registration open, author'          => array( 1, 'author', 'good' ),
			'registration open, editor'          => array( 1, 'editor', 'critical' ),
			'registration open, administrator'   => array( 1, 'administrator', 'critical' ),
		);
	}

	/**
	 * Tests that a passing result does not use the label describing the problem.
	 *
	 * @ticket 66359
	 */
	public function test_passing_result_has_a_label_describing_the_safe_state() {
		$this->set_registration( 1 );
		update_option( 'default_role', 'subscriber' );

		$result = ( new WP_Site_Health() )->get_test_insecure_registration();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'New users are not given a privileged role', $result['label'] );
		$this->assertSame( '', $result['actions'] );
	}

	/**
	 * Tests the label, description markup and action of a critical result.
	 *
	 * @ticket 66359
	 */
	public function test_critical_result_describes_the_problem() {
		$this->set_registration( 1 );
		update_option( 'default_role', 'administrator' );

		$result = ( new WP_Site_Health() )->get_test_insecure_registration();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'Open registration with a privileged default role', $result['label'] );
		$this->assertStringStartsWith( '<p>', $result['description'], 'The description should be wrapped in a paragraph like other Site Health results.' );
		$this->assertStringEndsWith( '</p>', $result['description'] );
		$this->assertStringContainsString( admin_url( 'options-general.php' ), $result['actions'] );
	}

	/**
	 * Opens or closes registration.
	 *
	 * On multisite, the `users_can_register` option is filtered to follow the
	 * network's `registration` setting, see users_can_register_signup_filter().
	 *
	 * @param int $users_can_register Whether anyone can register.
	 */
	private function set_registration( $users_can_register ) {
		if ( is_multisite() ) {
			update_site_option( 'registration', $users_can_register ? 'user' : 'none' );
		} else {
			update_option( 'users_can_register', $users_can_register );
		}
	}
}
