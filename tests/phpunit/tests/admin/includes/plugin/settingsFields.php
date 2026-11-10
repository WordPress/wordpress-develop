<?php

/**
 * Tests for settings_fields().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::settings_fields
 */
class Tests_Admin_Includes_Plugin_SettingsFields extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_option_groups
	 *
	 * @param string $option_group Settings group name.
	 */
	public function test_outputs_option_page_and_action_fields( $option_group ) {
		ob_start();
		settings_fields( $option_group );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			"<input type='hidden' name='option_page' value='" . $option_group . "' />",
			$output,
			'Should output the option_page hidden field.'
		);
		$this->assertStringContainsString(
			'<input type="hidden" name="action" value="update" />',
			$output,
			'Should output the action hidden field.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_option_groups() {
		return array(
			'general'      => array( 'general' ),
			'custom group' => array( 'my_plugin_settings' ),
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_escapes_option_group_in_option_page_value() {
		$option_group = 'general&"\'<>';

		ob_start();
		settings_fields( $option_group );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			"<input type='hidden' name='option_page' value='general&amp;&quot;&#039;&lt;&gt;' />",
			$output,
			'option_page value should escape special characters for an HTML attribute.'
		);
		$this->assertStringNotContainsString(
			'value=\'general&"\'',
			$output,
			'Unescaped special characters should not appear in the option_page value.'
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_nonce_option_groups
	 *
	 * @param string $option_group Settings group name.
	 */
	public function test_outputs_valid_nonce_for_option_group( $option_group ) {
		ob_start();
		settings_fields( $option_group );
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<input type="hidden" id="_wpnonce" name="_wpnonce" value="[^"]+" \/>/',
			$output,
			'Should output the _wpnonce field.'
		);

		preg_match( '/name="_wpnonce" value="([^"]+)"/', $output, $matches );

		$this->assertNotEmpty( $matches[1], 'Nonce value should be present.' );
		$this->assertNotFalse(
			wp_verify_nonce( $matches[1], $option_group . '-options' ),
			'Nonce should verify for the "{option_group}-options" action.'
		);

		$this->assertStringContainsString(
			'name="_wp_http_referer"',
			$output,
			'Should include the referer field from wp_nonce_field().'
		);
	}

	/**
	 * Data provider for nonce action coverage across option groups.
	 *
	 * @return array[]
	 */
	public static function data_nonce_option_groups() {
		return array(
			'general' => array( 'general' ),
			'reading' => array( 'reading' ),
		);
	}
}
