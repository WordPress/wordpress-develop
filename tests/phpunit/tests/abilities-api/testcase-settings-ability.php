<?php

declare( strict_types=1 );

/**
 * Base test case for the settings abilities.
 *
 * Registers the core abilities the way a request that uses the Abilities API before
 * `rest_api_init` fires does, restores the registered settings and the settings abilities
 * after each test, and provides helpers to register the settings abilities again and to
 * log in as an administrator.
 *
 * @since 7.3.0
 */
abstract class WP_Abilities_Settings_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Backup of the `$wp_registered_settings` global, restored after the class.
	 *
	 * @var array|null
	 */
	private static $registered_settings_backup;

	/**
	 * Backup of the `$new_allowed_options` global, restored after the class.
	 *
	 * @var array|null
	 */
	private static $allowed_options_backup;

	/**
	 * The `$wp_registered_settings` global as the class setup leaves it, restored after each test.
	 *
	 * @var array|null
	 */
	private static $class_registered_settings;

	/**
	 * The `$new_allowed_options` global as the class setup leaves it, restored after each test.
	 *
	 * @var array|null
	 */
	private static $class_allowed_options;

	/**
	 * The administrator that become_admin() logs in as.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Registers the core abilities and creates the administrator before the class.
	 *
	 * The abilities are registered with no settings registered yet and `rest_api_init` never
	 * fired, as on cron, WP-CLI, or any request that uses the Abilities API before the REST
	 * server loads. Core must register its initial settings when abilities initialize (see
	 * _wp_register_initial_settings_for_abilities()).
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		global $wp_registered_settings, $new_allowed_options, $wp_actions;
		self::$registered_settings_backup = $wp_registered_settings;
		self::$allowed_options_backup     = $new_allowed_options;
		$rest_api_init_count              = $wp_actions['rest_api_init'] ?? null;
		$wp_registered_settings           = array();
		unset( $wp_actions['rest_api_init'] );

		static::register_test_settings();

		// Temporarily remove the unhook functions so we can register core abilities.
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );

		/*
		 * Restore the hooks and the `rest_api_init` count right away instead of after the class.
		 * The first test of a run snapshots the hooks and every test resets them to that snapshot,
		 * so changes left here would leak into every later test whenever this class runs first.
		 */
		remove_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		remove_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );
		if ( null !== $rest_api_init_count ) {
			$wp_actions['rest_api_init'] = $rest_api_init_count;
		}

		self::$class_registered_settings = $wp_registered_settings;
		self::$class_allowed_options     = $new_allowed_options;

		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Cleans up registered abilities, categories and settings after the class.
	 *
	 * @since 7.3.0
	 */
	public static function wpTearDownAfterClass(): void {
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		global $wp_registered_settings, $new_allowed_options;
		$wp_registered_settings = self::$registered_settings_backup;
		$new_allowed_options    = self::$allowed_options_backup;
	}

	/**
	 * Restores the settings registered for the class and the settings abilities after each test.
	 *
	 * A test can register or unregister settings and register the abilities again without
	 * cleaning up after itself, even when it fails halfway.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		global $wp_registered_settings, $new_allowed_options;

		$wp_registered_settings = self::$class_registered_settings;
		$new_allowed_options    = self::$class_allowed_options;
		$this->register_ability();

		parent::tear_down();
	}

	/**
	 * Registers the settings a test class needs before the abilities register.
	 *
	 * The settings need no cleanup: the registered settings and the allowed options are restored
	 * after the class.
	 *
	 * @since 7.3.0
	 */
	protected static function register_test_settings(): void {}

	/**
	 * Registers the settings abilities again inside a faked init action.
	 *
	 * The class setup has already registered them through wp_register_core_abilities(), so
	 * the existing copies are unregistered first.
	 *
	 * @since 7.3.0
	 */
	protected function register_ability(): void {
		foreach ( array( 'core/settings-get', 'core/settings-update' ) as $name ) {
			if ( wp_has_ability( $name ) ) {
				wp_unregister_ability( $name );
			}
		}

		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Settings() )->register();
		array_pop( $wp_current_filter );
	}

	/**
	 * Logs in as an administrator so abilities gated behind `manage_options` can run.
	 *
	 * @since 7.3.0
	 */
	protected function become_admin(): void {
		wp_set_current_user( self::$admin_id );
	}
}
