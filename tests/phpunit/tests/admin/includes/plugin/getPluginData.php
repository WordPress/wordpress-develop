<?php
/**
 * Tests for get_plugin_data().
 *
 * @group plugins
 * @group admin
 *
 * @covers ::get_plugin_data
 * @covers ::_get_plugin_data_markup_translate
 */
class Tests_Admin_Includes_Plugin_GetPluginData extends WP_UnitTestCase {

	/**
	 * Path to the test plugin with an Uninstall Notification header.
	 *
	 * @var string
	 */
	private $plugin_file;

	public function set_up() {
		parent::set_up();
		$this->plugin_file = DIR_TESTDATA . '/plugin-headers/uninstall-notification.php';
	}

	/**
	 * @ticket 31136
	 */
	public function test_uninstall_notification_header_is_parsed_raw() {
		$data = get_plugin_data( $this->plugin_file, false, false );

		$this->assertArrayHasKey( 'UninstallNotification', $data );
		$this->assertSame(
			'Export your data <strong>before</strong> deleting. See <a href="https://example.org/docs" onclick="alert(1)">the docs</a>.<script>alert(1)</script>',
			$data['UninstallNotification']
		);
	}

	/**
	 * @ticket 31136
	 */
	public function test_uninstall_notification_is_sanitized() {
		$data = get_plugin_data( $this->plugin_file, false, true );

		$this->assertSame(
			'Export your data <strong>before</strong> deleting. See <a href="https://example.org/docs">the docs</a>.',
			$data['UninstallNotification'],
			'Disallowed tags and attributes should be stripped.'
		);
	}

	/**
	 * @ticket 31136
	 */
	public function test_uninstall_notification_is_empty_when_header_is_missing() {
		$data = get_plugin_data( DIR_TESTDATA . '/plugins/hello.php' );

		$this->assertArrayHasKey( 'UninstallNotification', $data );
		$this->assertSame( '', $data['UninstallNotification'] );
	}

	/**
	 * @ticket 31136
	 */
	public function test_uninstall_notification_is_translated_with_plugin_text_domain() {
		$filter = static function ( $translation, $text ) {
			if ( str_starts_with( $text, 'Export your data' ) ) {
				return 'Translated notification with <em>markup</em>.';
			}
			return $translation;
		};
		add_filter( 'gettext_uninstall-notification-test', $filter, 10, 2 );

		$data = get_plugin_data( $this->plugin_file, false, true );

		$this->assertSame( 'Translated notification with <em>markup</em>.', $data['UninstallNotification'] );
	}

	/**
	 * @ticket 31136
	 */
	public function test_uninstall_notification_is_not_translated_when_translate_is_false() {
		$filter = new MockAction();
		add_filter( 'gettext_uninstall-notification-test', array( $filter, 'filter' ) );

		get_plugin_data( $this->plugin_file, false, false );

		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * Plugin data cached by get_plugins() before the header existed has no
	 * UninstallNotification key, and must not trigger a PHP warning.
	 *
	 * @ticket 31136
	 */
	public function test_markup_translate_handles_missing_uninstall_notification_key() {
		$data = get_plugin_data( $this->plugin_file, false, false );
		unset( $data['UninstallNotification'] );

		$result = _get_plugin_data_markup_translate( $this->plugin_file, $data );

		$this->assertSame( '', $result['UninstallNotification'] );
	}
}
