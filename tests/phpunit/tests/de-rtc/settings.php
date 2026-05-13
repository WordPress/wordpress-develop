<?php
/**
 * Tests for Distributed Editing settings.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Settings extends WP_UnitTestCase {

	public function tear_down() {
		global $wp_registered_settings, $wp_settings_fields;

		delete_option( 'wp_de_rtc_enabled' );
		unregister_setting( 'writing', 'wp_de_rtc_enabled' );
		unset( $wp_registered_settings['wp_de_rtc_enabled'] );

		if ( isset( $wp_settings_fields['writing']['default']['wp_de_rtc_enabled'] ) ) {
			unset( $wp_settings_fields['writing']['default']['wp_de_rtc_enabled'] );
		}

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_register_settings
	 * @covers ::wp_de_rtc_sanitize_enabled_setting
	 */
	public function test_registers_disabled_by_default_writing_setting() {
		global $wp_registered_settings;

		wp_de_rtc_register_settings();

		$this->assertArrayHasKey( 'wp_de_rtc_enabled', $wp_registered_settings );
		$this->assertSame( 'writing', $wp_registered_settings['wp_de_rtc_enabled']['group'] );
		$this->assertSame( 'boolean', $wp_registered_settings['wp_de_rtc_enabled']['type'] );
		$this->assertFalse( $wp_registered_settings['wp_de_rtc_enabled']['show_in_rest'] );
		$this->assertFalse( get_option( 'wp_de_rtc_enabled' ) );
	}

	/**
	 * @covers ::wp_de_rtc_is_enabled
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_site_option_controls_post_enablement() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC settings post',
				'post_content' => '<!-- wp:paragraph --><p>Settings.</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertFalse( wp_de_rtc_is_enabled() );
		$this->assertFalse( wp_de_rtc_is_enabled_for_post( $post_id ) );

		update_option( 'wp_de_rtc_enabled', true );

		$this->assertTrue( wp_de_rtc_is_enabled() );
		$this->assertTrue( wp_de_rtc_is_enabled_for_post( $post_id ) );

		update_option( 'wp_de_rtc_enabled', false );

		$this->assertFalse( wp_de_rtc_is_enabled() );
		$this->assertFalse( wp_de_rtc_is_enabled_for_post( $post_id ) );
	}

	/**
	 * @covers ::wp_de_rtc_register_settings
	 * @covers ::wp_de_rtc_render_enabled_setting
	 */
	public function test_registers_writing_settings_field_with_guidance() {
		global $wp_settings_fields;

		if ( ! function_exists( 'add_settings_field' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		wp_de_rtc_register_settings();

		$this->assertArrayHasKey( 'wp_de_rtc_enabled', $wp_settings_fields['writing']['default'] );

		ob_start();
		call_user_func( $wp_settings_fields['writing']['default']['wp_de_rtc_enabled']['callback'] );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="wp_de_rtc_enabled"', $output );
		$this->assertStringContainsString( 'Distributed Editing is experimental', $output );
		$this->assertStringContainsString( 'constrained hosting', $output );
	}
}
