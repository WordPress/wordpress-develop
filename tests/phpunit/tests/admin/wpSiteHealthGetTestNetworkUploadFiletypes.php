<?php

/**
 * Tests for WP_Site_Health::get_test_network_upload_filetypes().
 *
 * @group admin
 * @group site-health
 * @group multisite
 *
 * @covers WP_Site_Health::get_test_network_upload_filetypes
 */
class Tests_Admin_wpSiteHealthGetTestNetworkUploadFiletypes extends WP_UnitTestCase {

	/**
	 * Loads the Site Health class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	}

	/**
	 * Tests that the test is not registered on a single site install.
	 *
	 * @group ms-excluded
	 *
	 * @covers WP_Site_Health::get_tests
	 */
	public function test_is_not_registered_on_single_site() {
		$this->assertArrayNotHasKey( 'network_upload_filetypes', WP_Site_Health::get_tests()['direct'] );
	}

	/**
	 * Tests that the test is registered on multisite.
	 *
	 * @group ms-required
	 *
	 * @covers WP_Site_Health::get_tests
	 */
	public function test_is_registered_on_multisite() {
		$this->assertArrayHasKey( 'network_upload_filetypes', WP_Site_Health::get_tests()['direct'] );
	}

	/**
	 * Tests that a network created before WordPress 5.8 is told about the image
	 * formats added since.
	 *
	 * @group ms-required
	 */
	public function test_recommends_missing_default_types() {
		update_site_option( 'upload_filetypes', 'jpg jpeg png gif' );

		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'network_upload_filetypes', $result['test'] );
		$this->assertStringContainsString( '<code>webp</code>', $result['description'] );
		$this->assertStringContainsString( '<code>avif</code>', $result['description'] );
	}

	/**
	 * Tests that the network's default list is used when the option is missing.
	 *
	 * @group ms-required
	 */
	public function test_recommends_missing_default_types_without_option() {
		delete_site_option( 'upload_filetypes' );

		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( '<code>webp</code>', $result['description'] );
	}

	/**
	 * Tests that a network allowing every default type passes.
	 *
	 * @group ms-required
	 */
	public function test_passes_with_default_types() {
		update_site_option( 'upload_filetypes', $this->get_default_upload_filetypes() );

		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();

		$this->assertSame( 'good', $result['status'] );
	}

	/**
	 * Tests that types the network allows beyond the defaults are not reported.
	 *
	 * @group ms-required
	 */
	public function test_ignores_additional_types() {
		update_site_option( 'upload_filetypes', $this->get_default_upload_filetypes() . ' svg json' );

		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();

		$this->assertSame( 'good', $result['status'] );
	}

	/**
	 * Tests that an extension is not reported when another extension of the same
	 * file type already allows it.
	 *
	 * check_upload_mimes() keeps `jpg|jpeg|jpe` when the network allows `jpg`.
	 *
	 * @group ms-required
	 */
	public function test_does_not_report_extensions_of_allowed_types() {
		update_site_option( 'upload_filetypes', 'jpg png gif' );

		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringNotContainsString( '<code>jpe</code>', $result['description'] );
		$this->assertStringNotContainsString( '<code>jpeg</code>', $result['description'] );
	}

	/**
	 * Tests that HTML and JavaScript are never recommended, even to a user who
	 * can upload them.
	 *
	 * @group ms-required
	 */
	public function test_does_not_recommend_html_or_javascript() {
		wp_set_current_user( self::factory()->user->create() );
		grant_super_admin( get_current_user_id() );
		$this->assertTrue( current_user_can( 'unfiltered_html' ), 'A super admin should be able to upload HTML.' );

		update_site_option( 'upload_filetypes', 'jpg jpeg png gif' );

		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();

		$this->assertStringNotContainsString( '<code>html</code>', $result['description'] );
		$this->assertStringNotContainsString( '<code>htm</code>', $result['description'] );
		$this->assertStringNotContainsString( '<code>js</code>', $result['description'] );
	}

	/**
	 * Tests that the link to the network settings is only shown to users who can
	 * change them.
	 *
	 * @group ms-required
	 */
	public function test_links_to_network_settings_for_super_admins_only() {
		update_site_option( 'upload_filetypes', 'jpg jpeg png gif' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();
		$this->assertSame( '', $result['actions'], 'A site administrator should not get the link.' );

		grant_super_admin( get_current_user_id() );
		$result = ( new WP_Site_Health() )->get_test_network_upload_filetypes();
		$this->assertStringContainsString( network_admin_url( 'settings.php#upload_filetypes' ), $result['actions'], 'A super admin should get the link.' );
	}

	/**
	 * Tests that the test leaves check_upload_mimes() hooked to `upload_mimes`.
	 *
	 * @group ms-required
	 */
	public function test_restores_check_upload_mimes() {
		$priority = has_filter( 'upload_mimes', 'check_upload_mimes' );

		( new WP_Site_Health() )->get_test_network_upload_filetypes();

		$this->assertSame( $priority, has_filter( 'upload_mimes', 'check_upload_mimes' ) );
	}

	/**
	 * Gets the file types a network created today allows, as populate_network_meta()
	 * stores them.
	 *
	 * @return string The file extensions, separated by spaces.
	 */
	private function get_default_upload_filetypes() {
		$extensions = array();
		foreach ( array_keys( wp_get_mime_types() ) as $extension ) {
			array_push( $extensions, ...explode( '|', $extension ) );
		}

		return implode( ' ', array_unique( $extensions ) );
	}
}
