<?php

/**
 * Tests for WP_Automatic_Updater::should_update().
 *
 * @group admin
 * @group upgrade
 *
 * @covers WP_Automatic_Updater::should_update
 */
class Tests_Admin_WpAutomaticUpdaterShouldUpdate extends WP_UnitTestCase {

	/**
	 * Arguments passed to the request_filesystem_credentials filter, one entry per call.
	 *
	 * @var array[]
	 */
	private $credential_requests = array();

	/**
	 * Value returned by the request_filesystem_credentials filter.
	 *
	 * @var bool
	 */
	private $credentials_available = true;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
	}

	public function set_up() {
		parent::set_up();

		add_filter( 'pre_wp_mail', '__return_false' );
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );
		add_filter( 'automatic_updater_disabled', '__return_false' );
		add_filter( 'request_filesystem_credentials', array( $this, 'filter_request_filesystem_credentials' ), 10, 7 );
	}

	/**
	 * Records the filesystem credential request and answers whether credentials are available.
	 *
	 * @param mixed  $credentials                  Credentials to return instead.
	 * @param string $form_post                    The URL to post the form to.
	 * @param string $type                         The chosen filesystem method.
	 * @param bool   $error                        Whether the current request has failed to connect.
	 * @param string $context                      Full path to the directory that is tested for being writable.
	 * @param array  $extra_fields                 Extra POST fields.
	 * @param bool   $allow_relaxed_file_ownership Whether to allow group or world writable files.
	 * @return bool Whether credentials are available.
	 */
	public function filter_request_filesystem_credentials( $credentials, $form_post, $type, $error, $context, $extra_fields, $allow_relaxed_file_ownership ) {
		$this->credential_requests[] = array( $context, $allow_relaxed_file_ownership );

		return $this->credentials_available;
	}

	/**
	 * Gets an updater that records whether a core update notification email would be sent.
	 *
	 * @param int $email_count Expected number of core update notification emails.
	 * @return WP_Automatic_Updater&PHPUnit\Framework\MockObject\MockObject Updater.
	 */
	private function get_updater( $email_count = 0 ) {
		$updater = $this->getMockBuilder( WP_Automatic_Updater::class )
			// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
			->setMethods( array( 'send_core_update_notification_email' ) )
			->getMock();

		$updater->expects( $this->exactly( $email_count ) )->method( 'send_core_update_notification_email' );

		return $updater;
	}

	/**
	 * Gets a plugin update offer.
	 *
	 * @param array $fields Fields to override.
	 * @return object Update offer.
	 */
	private static function plugin_offer( array $fields = array() ) {
		return (object) array_merge(
			array(
				'plugin'      => 'my-plugin/my-plugin.php',
				'new_version' => '2.0',
			),
			$fields
		);
	}

	/**
	 * Gets a core update offer that is compatible with the current environment.
	 *
	 * @param array $fields Fields to override.
	 * @return object Update offer.
	 */
	private static function core_offer( array $fields = array() ) {
		return (object) array_merge(
			array(
				'current'       => '99.0',
				'php_version'   => '5.6',
				'mysql_version' => '5.0',
				'new_files'     => true,
			),
			$fields
		);
	}

	/**
	 * Tests that nothing is updated while background updates are disabled.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_updates_are_disabled() {
		add_filter( 'automatic_updater_disabled', '__return_true', 20 );

		$this->assertFalse( $this->get_updater()->should_update( 'plugin', self::plugin_offer( array( 'autoupdate' => true ) ), WP_PLUGIN_DIR ) );
		$this->assertSame( array(), $this->credential_requests, 'The filesystem should not be checked.' );
	}

	/**
	 * Tests that nothing is updated when filesystem credentials are not available.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_without_filesystem_credentials() {
		$this->credentials_available = false;

		$this->assertFalse( $this->get_updater()->should_update( 'plugin', self::plugin_offer( array( 'autoupdate' => true ) ), WP_PLUGIN_DIR ) );
		$this->assertSame( array( array( WP_PLUGIN_DIR, false ) ), $this->credential_requests, 'The filesystem should be checked for the context.' );
	}

	/**
	 * Tests that nothing is updated in a version-controlled checkout.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_vcs_checkout() {
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true', 20 );

		$this->assertFalse( $this->get_updater()->should_update( 'plugin', self::plugin_offer( array( 'autoupdate' => true ) ), WP_PLUGIN_DIR ) );
	}

	/**
	 * Tests that a core update notification email is sent when core cannot be updated in the background.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_core_updates_that_cannot_run
	 *
	 * @param bool $credentials_available Whether filesystem credentials are available.
	 * @param bool $is_vcs_checkout       Whether the site is a version-controlled checkout.
	 */
	public function test_sends_core_notification_when_core_cannot_be_updated( $credentials_available, $is_vcs_checkout ) {
		$this->credentials_available = $credentials_available;

		if ( $is_vcs_checkout ) {
			add_filter( 'automatic_updates_is_vcs_checkout', '__return_true', 20 );
		}

		$this->assertFalse( $this->get_updater( 1 )->should_update( 'core', self::core_offer(), ABSPATH ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_core_updates_that_cannot_run() {
		return array(
			'no filesystem credentials' => array( false, false ),
			'version-controlled site'   => array( true, true ),
		);
	}

	/**
	 * Tests that relaxed file ownership is only allowed for core updates without new files.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_relaxed_file_ownership
	 *
	 * @param string $type     Update type.
	 * @param object $item     Update offer.
	 * @param bool   $expected Whether relaxed file ownership should be allowed.
	 */
	public function test_allows_relaxed_file_ownership_only_for_core_without_new_files( $type, $item, $expected ) {
		add_filter( "auto_update_{$type}", '__return_false' );

		$this->get_updater( 'core' === $type ? 1 : 0 )->should_update( $type, $item, ABSPATH );

		$this->assertSame( array( array( ABSPATH, $expected ) ), $this->credential_requests );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_relaxed_file_ownership() {
		return array(
			'core without new files'          => array( 'core', self::core_offer( array( 'new_files' => false ) ), true ),
			'core with new files'             => array( 'core', self::core_offer( array( 'new_files' => true ) ), false ),
			'core without the new files flag' => array( 'core', (object) array( 'current' => '99.0' ), false ),
			'plugin without new files'        => array( 'plugin', self::plugin_offer( array( 'new_files' => false ) ), false ),
		);
	}

	/**
	 * Tests whether plugins and themes are updated, from their offer and the site's auto-update settings.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_plugin_and_theme_updates
	 *
	 * @param string   $type               Update type, 'plugin' or 'theme'.
	 * @param array    $fields             Fields of the update offer.
	 * @param string[] $auto_updates       Items with auto-updates enabled by the site admin.
	 * @param bool     $type_enabled       Whether auto-updates are enabled for the type.
	 * @param bool     $expected           Whether the item should be updated.
	 */
	public function test_plugin_and_theme_updates( $type, $fields, $auto_updates, $type_enabled, $expected ) {
		update_site_option( "auto_update_{$type}s", $auto_updates );
		add_filter( "{$type}s_auto_update_enabled", $type_enabled ? '__return_true' : '__return_false' );

		$item = (object) array_merge( array( $type => "my-{$type}" ), $fields );

		$this->assertSame( $expected, $this->get_updater()->should_update( $type, $item, WP_CONTENT_DIR ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugin_and_theme_updates() {
		return array(
			'plugin with the autoupdate flag'           => array( 'plugin', array( 'autoupdate' => true ), array(), false, true ),
			'plugin without the autoupdate flag'        => array( 'plugin', array(), array(), true, false ),
			'plugin with a falsy autoupdate flag'       => array( 'plugin', array( 'autoupdate' => 0 ), array(), true, false ),
			'plugin enabled by the site admin'          => array( 'plugin', array(), array( 'my-plugin' ), true, true ),
			'plugin enabled while auto-updates are off' => array( 'plugin', array(), array( 'my-plugin' ), false, false ),
			'other plugin enabled by the site admin'    => array( 'plugin', array(), array( 'other-plugin' ), true, false ),
			'theme enabled by the site admin'           => array( 'theme', array(), array( 'my-theme' ), true, true ),
			'theme with the autoupdate flag'            => array( 'theme', array( 'autoupdate' => true ), array(), false, true ),
			'theme without the autoupdate flag'         => array( 'theme', array(), array(), true, false ),
		);
	}

	/**
	 * Tests that translations are updated when the offer allows it.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_translation_updates
	 *
	 * @param array $fields   Fields of the update offer.
	 * @param bool  $expected Whether the translation should be updated.
	 */
	public function test_translation_updates( $fields, $expected ) {
		$item = (object) array_merge( array( 'language' => 'de_DE' ), $fields );

		$this->assertSame( $expected, $this->get_updater()->should_update( 'translation', $item, WP_LANG_DIR ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_translation_updates() {
		return array(
			'with the autoupdate flag'     => array( array( 'autoupdate' => true ), true ),
			'without the autoupdate flag'  => array( array(), false ),
			'with a falsy autoupdate flag' => array( array( 'autoupdate' => false ), false ),
		);
	}

	/**
	 * Tests that the disable_autoupdate flag overrides the user's choice, but not the filter.
	 *
	 * @ticket 65819
	 */
	public function test_disable_autoupdate_flag_overrides_choice_but_not_filter() {
		$item = self::plugin_offer(
			array(
				'autoupdate'         => true,
				'disable_autoupdate' => true,
			)
		);

		$this->assertFalse( $this->get_updater()->should_update( 'plugin', $item, WP_PLUGIN_DIR ), 'The flag should prevent the update.' );

		add_filter( 'auto_update_plugin', '__return_true' );

		$this->assertTrue( $this->get_updater()->should_update( 'plugin', $item, WP_PLUGIN_DIR ), 'The filter should still allow the update.' );
	}

	/**
	 * Tests that the auto_update_{$type} filter receives the decision and the offer, and overrides it.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_filter_values
	 *
	 * @param mixed $filtered Value returned by the filter.
	 * @param bool  $expected Whether the item should be updated.
	 */
	public function test_auto_update_filter( $filtered, $expected ) {
		$item = self::plugin_offer( array( 'autoupdate' => true ) );

		$filter = new MockAction();
		add_filter( 'auto_update_plugin', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'auto_update_plugin',
			static function () use ( $filtered ) {
				return $filtered;
			},
			20
		);

		$this->assertSame( $expected, $this->get_updater()->should_update( 'plugin', $item, WP_PLUGIN_DIR ), 'The filtered decision should be used.' );
		$this->assertSame( array( array( true, $item ) ), $filter->get_args(), 'The filter should receive the decision and the offer.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_filter_values() {
		return array(
			'true'         => array( true, true ),
			'false'        => array( false, false ),
			'null'         => array( null, false ),
			'zero'         => array( 0, false ),
			'empty string' => array( '', false ),
			'truthy value' => array( 1, true ),
		);
	}

	/**
	 * Tests that a core update notification email is sent when the filter declines the core update.
	 *
	 * @ticket 65819
	 */
	public function test_sends_core_notification_when_core_update_is_declined() {
		add_filter( 'auto_update_core', '__return_false' );

		$this->assertFalse( $this->get_updater( 1 )->should_update( 'core', self::core_offer(), ABSPATH ) );
	}

	/**
	 * Tests that the core update decision comes from the core update policy when no auto_update_core filter is added.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_core_update_policy
	 *
	 * @param bool $allow_major Whether the core update policy allows major updates.
	 * @param bool $expected    Whether core should be updated.
	 */
	public function test_core_update_follows_core_update_policy( $allow_major, $expected ) {
		add_filter( 'allow_dev_auto_core_updates', '__return_true' );
		add_filter( 'allow_major_auto_core_updates', $allow_major ? '__return_true' : '__return_false' );

		$this->assertSame( $expected, $this->get_updater( $expected ? 0 : 1 )->should_update( 'core', self::core_offer(), ABSPATH ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_core_update_policy() {
		return array(
			'policy allows the update' => array( true, true ),
			'policy denies the update' => array( false, false ),
		);
	}

	/**
	 * Tests the PHP and MySQL requirements of core updates.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_core_requirements
	 *
	 * @param array $fields   Fields of the update offer.
	 * @param bool  $expected Whether core should be updated.
	 */
	public function test_core_update_requirements( $fields, $expected ) {
		add_filter( 'auto_update_core', '__return_true' );

		$this->assertSame( $expected, $this->get_updater()->should_update( 'core', self::core_offer( $fields ), ABSPATH ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_core_requirements() {
		return array(
			'requirements met'      => array( array(), true ),
			'PHP version too old'   => array( array( 'php_version' => '99.0' ), false ),
			'MySQL version too old' => array( array( 'mysql_version' => '99.0' ), false ),
		);
	}

	/**
	 * Tests the PHP requirement of plugin and theme updates.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_plugin_and_theme_php_requirements
	 *
	 * @param string $type         Update type, 'plugin' or 'theme'.
	 * @param mixed  $requires_php Required PHP version of the update.
	 * @param bool   $expected     Whether the item should be updated.
	 */
	public function test_plugin_and_theme_php_requirements( $type, $requires_php, $expected ) {
		$item = (object) array(
			$type          => "my-{$type}",
			'autoupdate'   => true,
			'requires_php' => $requires_php,
		);

		$this->assertSame( $expected, $this->get_updater()->should_update( $type, $item, WP_CONTENT_DIR ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugin_and_theme_php_requirements() {
		return array(
			'plugin, requirement met'  => array( 'plugin', '5.6', true ),
			'plugin, PHP too old'      => array( 'plugin', '99.0', false ),
			'plugin, no requirement'   => array( 'plugin', '', true ),
			'plugin, null requirement' => array( 'plugin', null, true ),
			'theme, PHP too old'       => array( 'theme', '99.0', false ),
			'theme, requirement met'   => array( 'theme', '5.6', true ),
		);
	}

	/**
	 * Tests that the PHP requirement does not apply to translations.
	 *
	 * @ticket 65819
	 */
	public function test_php_requirement_does_not_apply_to_translations() {
		$item = (object) array(
			'language'     => 'de_DE',
			'autoupdate'   => true,
			'requires_php' => '99.0',
		);

		$this->assertTrue( $this->get_updater()->should_update( 'translation', $item, WP_LANG_DIR ) );
	}
}
