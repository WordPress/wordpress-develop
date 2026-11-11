<?php

/**
 * Tests for wp_get_update_data().
 *
 * @group functions
 * @group plugins
 * @group upgrade
 *
 * @covers ::wp_get_update_data
 */
class Tests_Functions_WpGetUpdateData extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * The update transients the tests set.
	 *
	 * @var string[]
	 */
	private static $transients = array( 'update_core', 'update_plugins', 'update_themes' );

	/**
	 * Sets up the users and loads get_core_updates().
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin grant before the fixture users are deleted.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Clears the update transients and dismissed core updates.
	 */
	public function set_up() {
		parent::set_up();

		$this->clear_updates();
	}

	/**
	 * Clears the update transients and dismissed core updates.
	 */
	public function tear_down() {
		$this->clear_updates();

		parent::tear_down();
	}

	/**
	 * Tests that no updates are counted when there are none.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_zero_counts_without_updates() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array(
				'counts' => array(
					'plugins'      => 0,
					'themes'       => 0,
					'wordpress'    => 0,
					'translations' => 0,
					'total'        => 0,
				),
				'title'  => '',
			),
			wp_get_update_data()
		);
	}

	/**
	 * Tests the counts and title for every type of update.
	 *
	 * @ticket 65819
	 */
	public function test_should_count_all_updates() {
		wp_set_current_user( self::$admin_id );

		$this->set_plugin_updates( 2, array( array( 'language' => 'de_DE' ) ) );
		$this->set_theme_updates( 1 );
		$this->set_core_update( 'upgrade' );

		$this->assertSame(
			array(
				'counts' => array(
					'plugins'      => 2,
					'themes'       => 1,
					'wordpress'    => 1,
					'translations' => 1,
					'total'        => 5,
				),
				'title'  => '1 WordPress Update, 2 Plugin Updates, 1 Theme Update, Translation Updates',
			),
			wp_get_update_data()
		);
	}

	/**
	 * Tests the singular and plural titles for plugin and theme updates.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_plugin_and_theme_counts
	 *
	 * @param int    $plugins  The number of plugin updates.
	 * @param int    $themes   The number of theme updates.
	 * @param string $expected The expected title.
	 */
	public function test_should_pluralize_titles( $plugins, $themes, $expected ) {
		wp_set_current_user( self::$admin_id );

		$this->set_plugin_updates( $plugins );
		$this->set_theme_updates( $themes );

		$this->assertSame( $expected, wp_get_update_data()['title'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugin_and_theme_counts() {
		return array(
			'one plugin'  => array( 1, 0, '1 Plugin Update' ),
			'two plugins' => array( 2, 0, '2 Plugin Updates' ),
			'one theme'   => array( 0, 1, '1 Theme Update' ),
			'two themes'  => array( 0, 2, '2 Theme Updates' ),
		);
	}

	/**
	 * Tests which core update responses are counted.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_core_responses
	 *
	 * @param string $response The core update response type.
	 * @param int    $expected The expected count.
	 */
	public function test_should_count_core_update_by_response( $response, $expected ) {
		wp_set_current_user( self::$admin_id );

		$this->set_core_update( $response );

		$this->assertSame( $expected, wp_get_update_data()['counts']['wordpress'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_core_responses() {
		return array(
			'upgrade'     => array( 'upgrade', 1 ),
			'latest'      => array( 'latest', 0 ),
			'development' => array( 'development', 0 ),
			'autoupdate'  => array( 'autoupdate', 0 ),
		);
	}

	/**
	 * Tests that a dismissed core update is not counted.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_count_dismissed_core_update() {
		wp_set_current_user( self::$admin_id );

		$this->set_core_update( 'upgrade' );
		update_site_option( 'dismissed_update_core', array( '99.0|en_US' => true ) );

		$this->assertSame( 0, wp_get_update_data()['counts']['wordpress'] );
	}

	/**
	 * Tests that a user who cannot update anything gets no counts.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_count_updates_for_user_without_capabilities() {
		wp_set_current_user( self::$subscriber_id );

		$this->set_plugin_updates( 2, array( array( 'language' => 'de_DE' ) ) );
		$this->set_theme_updates( 1 );
		$this->set_core_update( 'upgrade' );

		$this->assertSame(
			array(
				'counts' => array(
					'plugins'      => 0,
					'themes'       => 0,
					'wordpress'    => 0,
					'translations' => 0,
					'total'        => 0,
				),
				'title'  => '',
			),
			wp_get_update_data()
		);
	}

	/**
	 * Tests that each capability enables its own count, and any of them the translation count.
	 *
	 * Update capabilities are reserved to super admins on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_capabilities
	 *
	 * @param string $capability The capability granted to the user.
	 * @param array  $expected   The expected counts.
	 */
	public function test_should_count_updates_per_capability( $capability, $expected ) {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$user->add_cap( $capability );
		wp_set_current_user( $user->ID );

		$this->set_plugin_updates( 2, array( array( 'language' => 'de_DE' ) ) );
		$this->set_theme_updates( 1 );
		$this->set_core_update( 'upgrade' );

		$this->assertSame( $expected, wp_get_update_data()['counts'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_capabilities() {
		return array(
			'update_plugins' => array(
				'update_plugins',
				array(
					'plugins'      => 2,
					'themes'       => 0,
					'wordpress'    => 0,
					'translations' => 1,
					'total'        => 3,
				),
			),
			'update_themes'  => array(
				'update_themes',
				array(
					'plugins'      => 0,
					'themes'       => 1,
					'wordpress'    => 0,
					'translations' => 1,
					'total'        => 2,
				),
			),
			'update_core'    => array(
				'update_core',
				array(
					'plugins'      => 0,
					'themes'       => 0,
					'wordpress'    => 1,
					'translations' => 1,
					'total'        => 2,
				),
			),
		);
	}

	/**
	 * Tests that translation updates in any update transient are counted once.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_update_transients
	 *
	 * @param string $transient The update transient holding the translations.
	 */
	public function test_should_count_translation_updates_once( $transient ) {
		wp_set_current_user( self::$admin_id );

		set_site_transient(
			$transient,
			(object) array(
				'translations' => array(
					array( 'language' => 'de_DE' ),
					array( 'language' => 'fr_FR' ),
				),
			)
		);

		$data = wp_get_update_data();

		$this->assertSame( 1, $data['counts']['translations'], 'Translation updates should be counted once.' );
		$this->assertSame( 'Translation Updates', $data['title'], 'The title should mention translation updates.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_update_transients() {
		return self::text_array_to_dataprovider( self::$transients );
	}

	/**
	 * Tests that the update data can be filtered.
	 *
	 * @ticket 65819
	 */
	public function test_should_filter_update_data() {
		wp_set_current_user( self::$admin_id );

		$this->set_plugin_updates( 1 );

		$filter = new MockAction();
		add_filter( 'wp_get_update_data', array( $filter, 'filter' ), 10, 2 );
		add_filter( 'wp_get_update_data', '__return_empty_array', 11 );

		$this->assertSame( array(), wp_get_update_data(), 'The filtered value should be returned.' );

		$args = $filter->get_args();

		$this->assertSame( 1, $args[0][0]['counts']['plugins'], 'The filter should receive the update data.' );
		$this->assertSame( array( 'plugins' => '1 Plugin Update' ), $args[0][1], 'The filter should receive the titles.' );
	}

	/**
	 * Sets plugin updates in the update transient.
	 *
	 * @param int   $count        The number of plugin updates.
	 * @param array $translations The translation updates.
	 */
	private function set_plugin_updates( $count, $translations = array() ) {
		$response = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$response[ "plugin-{$i}/plugin-{$i}.php" ] = (object) array( 'slug' => "plugin-{$i}" );
		}

		set_site_transient(
			'update_plugins',
			(object) array(
				'response'     => $response,
				'translations' => $translations,
			)
		);
	}

	/**
	 * Sets theme updates in the update transient.
	 *
	 * @param int $count The number of theme updates.
	 */
	private function set_theme_updates( $count ) {
		$response = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$response[ "theme-{$i}" ] = array( 'theme' => "theme-{$i}" );
		}

		set_site_transient( 'update_themes', (object) array( 'response' => $response ) );
	}

	/**
	 * Sets a core update in the update transient.
	 *
	 * @param string $response The update response type.
	 */
	private function set_core_update( $response ) {
		set_site_transient(
			'update_core',
			(object) array(
				'updates' => array(
					(object) array(
						'response' => $response,
						'current'  => '99.0',
						'version'  => '99.0',
						'locale'   => 'en_US',
					),
				),
			)
		);
	}

	/**
	 * Clears the update transients and dismissed core updates.
	 */
	private function clear_updates() {
		foreach ( self::$transients as $transient ) {
			delete_site_transient( $transient );
		}

		delete_site_option( 'dismissed_update_core' );
	}
}
