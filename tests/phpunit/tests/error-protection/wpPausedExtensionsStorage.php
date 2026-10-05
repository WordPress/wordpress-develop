<?php
/**
 * Tests for the WP_Paused_Extensions_Storage class.
 *
 * @package WordPress
 * @subpackage Error_Protection
 * @since 5.2.0
 *
 * @group error-protection
 */
class Tests_Error_Protection_wpPausedExtensionsStorage extends WP_UnitTestCase {

	/**
	 * Unique recovery mode session ID for testing.
	 *
	 * @var string
	 */
	const TEST_SESSION_ID = 'test_recovery_session_12345';

	/**
	 * Plugin storage instance.
	 *
	 * @var WP_Paused_Extensions_Storage
	 */
	protected $plugin_storage;

	/**
	 * Theme storage instance.
	 *
	 * @var WP_Paused_Extensions_Storage
	 */
	protected $theme_storage;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_recovery_mode_state( true, self::TEST_SESSION_ID );

		$this->plugin_storage = new WP_Paused_Extensions_Storage( 'plugin' );
		$this->theme_storage  = new WP_Paused_Extensions_Storage( 'theme' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( self::TEST_SESSION_ID . '_paused_extensions' );
		$this->set_recovery_mode_state( false, '' );

		parent::tear_down();
	}

	/**
	 * Sets WP_Recovery_Mode protected state using reflection.
	 *
	 * @param bool   $is_active  Whether recovery mode is active.
	 * @param string $session_id Recovery mode session ID.
	 */
	private function set_recovery_mode_state( $is_active, $session_id ) {
		$recovery_mode = wp_recovery_mode();

		$active_prop = new ReflectionProperty( $recovery_mode, 'is_active' );
		if ( PHP_VERSION_ID < 80100 ) {
			$active_prop->setAccessible( true );
		}
		$active_prop->setValue( $recovery_mode, $is_active );

		$session_prop = new ReflectionProperty( $recovery_mode, 'session_id' );
		if ( PHP_VERSION_ID < 80100 ) {
			$session_prop->setAccessible( true );
		}
		$session_prop->setValue( $recovery_mode, $session_id );
	}

	/**
	 * Helper to generate a dummy error array.
	 *
	 * @param string $message Error message.
	 * @return array Error information array.
	 */
	private function get_dummy_error( $message = 'Fatal error in extension' ) {
		return array(
			'type'    => E_ERROR,
			'file'    => '/wp-content/plugins/test-plugin/test.php',
			'line'    => 42,
			'message' => $message,
		);
	}

	/**
	 * Tests that storage operations return false or empty when recovery mode is not active.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::set
	 * @covers WP_Paused_Extensions_Storage::get
	 * @covers WP_Paused_Extensions_Storage::get_all
	 * @covers WP_Paused_Extensions_Storage::delete
	 * @covers WP_Paused_Extensions_Storage::delete_all
	 */
	public function test_operations_return_false_or_empty_when_recovery_mode_not_active() {
		$this->set_recovery_mode_state( false, self::TEST_SESSION_ID );
		$error = $this->get_dummy_error();

		$this->assertFalse(
			$this->plugin_storage->set( 'plugin-a', $error ),
			'set() should return false when recovery mode is not active.'
		);
		$this->assertNull(
			$this->plugin_storage->get( 'plugin-a' ),
			'get() should return null when recovery mode is not active.'
		);
		$this->assertSame(
			array(),
			$this->plugin_storage->get_all(),
			'get_all() should return an empty array when recovery mode is not active.'
		);
		$this->assertFalse(
			$this->plugin_storage->delete( 'plugin-a' ),
			'delete() should return false when recovery mode is not active.'
		);
		$this->assertFalse(
			$this->plugin_storage->delete_all(),
			'delete_all() should return false when recovery mode is not active.'
		);
	}

	/**
	 * Tests that storage operations return false or empty when the recovery mode session ID is empty.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::set
	 * @covers WP_Paused_Extensions_Storage::get
	 * @covers WP_Paused_Extensions_Storage::get_all
	 * @covers WP_Paused_Extensions_Storage::delete
	 * @covers WP_Paused_Extensions_Storage::delete_all
	 */
	public function test_operations_return_false_or_empty_when_session_id_empty() {
		$this->set_recovery_mode_state( true, '' );
		$error = $this->get_dummy_error();

		$this->assertFalse(
			$this->plugin_storage->set( 'plugin-a', $error ),
			'set() should return false when session ID is empty.'
		);
		$this->assertNull(
			$this->plugin_storage->get( 'plugin-a' ),
			'get() should return null when session ID is empty.'
		);
		$this->assertSame(
			array(),
			$this->plugin_storage->get_all(),
			'get_all() should return an empty array when session ID is empty.'
		);
		$this->assertFalse(
			$this->plugin_storage->delete( 'plugin-a' ),
			'delete() should return false when session ID is empty.'
		);
		$this->assertFalse(
			$this->plugin_storage->delete_all(),
			'delete_all() should return false when session ID is empty.'
		);
	}

	/**
	 * Tests setting and getting a paused extension error.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::set
	 * @covers WP_Paused_Extensions_Storage::get
	 */
	public function test_set_and_get_error() {
		$error = $this->get_dummy_error( 'Syntax error' );

		$this->assertTrue(
			$this->plugin_storage->set( 'plugin-a', $error ),
			'set() should return true on successful storage.'
		);
		$this->assertSame(
			$error,
			$this->plugin_storage->get( 'plugin-a' ),
			'get() should return the stored error array.'
		);
	}

	/**
	 * Tests that get() returns null when an extension is not paused.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::get
	 */
	public function test_get_returns_null_when_extension_not_paused() {
		$this->assertNull(
			$this->plugin_storage->get( 'non-existent-plugin' ),
			'get() should return null for an unpaused extension.'
		);
	}

	/**
	 * Tests that get_all() returns all paused extensions for the storage type.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::get_all
	 */
	public function test_get_all_returns_all_paused_extensions_for_type() {
		$error_a = $this->get_dummy_error( 'Error A' );
		$error_b = $this->get_dummy_error( 'Error B' );

		$this->plugin_storage->set( 'plugin-a', $error_a );
		$this->plugin_storage->set( 'plugin-b', $error_b );

		$expected = array(
			'plugin-a' => $error_a,
			'plugin-b' => $error_b,
		);

		$this->assertSame(
			$expected,
			$this->plugin_storage->get_all(),
			'get_all() should return all stored errors for the current type.'
		);
	}

	/**
	 * Tests that set() overrides a previously recorded error for the same extension.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::set
	 */
	public function test_set_overrides_previous_error() {
		$initial_error = $this->get_dummy_error( 'Initial error' );
		$updated_error = $this->get_dummy_error( 'Updated error' );

		$this->plugin_storage->set( 'plugin-a', $initial_error );
		$this->assertSame( $initial_error, $this->plugin_storage->get( 'plugin-a' ) );

		$this->plugin_storage->set( 'plugin-a', $updated_error );
		$this->assertSame(
			$updated_error,
			$this->plugin_storage->get( 'plugin-a' ),
			'Subsequent set() calls should override previously recorded error.'
		);
	}

	/**
	 * Tests that set() is idempotent when given an identical error.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::set
	 */
	public function test_set_idempotent_when_error_identical() {
		$error = $this->get_dummy_error( 'Identical error' );

		$this->plugin_storage->set( 'plugin-a', $error );

		// Setting the same error again should return true without failure.
		$this->assertTrue(
			$this->plugin_storage->set( 'plugin-a', $error ),
			'set() should return true when the identical error is already stored.'
		);
	}

	/**
	 * Tests namespace isolation between plugin and theme storage types.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::set
	 * @covers WP_Paused_Extensions_Storage::get
	 * @covers WP_Paused_Extensions_Storage::get_all
	 */
	public function test_type_namespace_isolation() {
		$plugin_error = $this->get_dummy_error( 'Plugin error' );
		$theme_error  = $this->get_dummy_error( 'Theme error' );

		$this->plugin_storage->set( 'shared-slug', $plugin_error );
		$this->theme_storage->set( 'shared-slug', $theme_error );

		$this->assertSame(
			$plugin_error,
			$this->plugin_storage->get( 'shared-slug' ),
			'Plugin storage should retrieve only plugin errors.'
		);
		$this->assertSame(
			$theme_error,
			$this->theme_storage->get( 'shared-slug' ),
			'Theme storage should retrieve only theme errors.'
		);

		$raw_option = get_option( self::TEST_SESSION_ID . '_paused_extensions' );
		$this->assertIsArray( $raw_option );
		$this->assertArrayHasKey( 'plugin', $raw_option );
		$this->assertArrayHasKey( 'theme', $raw_option );
		$this->assertSame( $plugin_error, $raw_option['plugin']['shared-slug'] );
		$this->assertSame( $theme_error, $raw_option['theme']['shared-slug'] );
	}

	/**
	 * Tests deleting a single paused extension.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::delete
	 */
	public function test_delete_extension() {
		$error_a = $this->get_dummy_error( 'Error A' );
		$error_b = $this->get_dummy_error( 'Error B' );

		$this->plugin_storage->set( 'plugin-a', $error_a );
		$this->plugin_storage->set( 'plugin-b', $error_b );

		$this->assertTrue(
			$this->plugin_storage->delete( 'plugin-a' ),
			'delete() should return true when deleting a paused extension.'
		);
		$this->assertNull(
			$this->plugin_storage->get( 'plugin-a' ),
			'Deleted extension should no longer be returned by get().'
		);
		$this->assertSame(
			$error_b,
			$this->plugin_storage->get( 'plugin-b' ),
			'Other paused extensions should remain unaffected.'
		);
	}

	/**
	 * Tests that delete() returns true when deleting an extension that is not paused.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::delete
	 */
	public function test_delete_unpaused_extension_returns_true() {
		$this->assertTrue(
			$this->plugin_storage->delete( 'never-paused-plugin' ),
			'delete() should return true when the extension is not in storage.'
		);
	}

	/**
	 * Tests that delete() cleans up the type key when the last extension of that type is removed.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::delete
	 */
	public function test_delete_last_extension_of_type_cleans_up_type_key() {
		$this->plugin_storage->set( 'only-plugin', $this->get_dummy_error() );
		$this->theme_storage->set( 'only-theme', $this->get_dummy_error() );

		$this->plugin_storage->delete( 'only-plugin' );

		$raw_option = get_option( self::TEST_SESSION_ID . '_paused_extensions' );
		$this->assertIsArray( $raw_option );
		$this->assertArrayNotHasKey(
			'plugin',
			$raw_option,
			'Type key should be removed from the option when its last extension is deleted.'
		);
		$this->assertArrayHasKey(
			'theme',
			$raw_option,
			'Other extension types should remain in the option.'
		);
	}

	/**
	 * Tests that delete() removes the database option entirely when the last extension across all types is deleted.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::delete
	 */
	public function test_delete_last_extension_deletes_entire_option() {
		$this->plugin_storage->set( 'only-plugin', $this->get_dummy_error() );
		$this->assertNotFalse( get_option( self::TEST_SESSION_ID . '_paused_extensions' ) );

		$this->plugin_storage->delete( 'only-plugin' );

		$this->assertFalse(
			get_option( self::TEST_SESSION_ID . '_paused_extensions' ),
			'The option should be deleted from the database when no paused extensions remain.'
		);
	}

	/**
	 * Tests deleting all paused extensions of a specific type.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::delete_all
	 */
	public function test_delete_all_for_type() {
		$this->plugin_storage->set( 'plugin-a', $this->get_dummy_error( 'A' ) );
		$this->plugin_storage->set( 'plugin-b', $this->get_dummy_error( 'B' ) );
		$this->theme_storage->set( 'theme-a', $this->get_dummy_error( 'Theme' ) );

		$this->assertTrue(
			$this->plugin_storage->delete_all(),
			'delete_all() should return true on success.'
		);
		$this->assertSame(
			array(),
			$this->plugin_storage->get_all(),
			'get_all() should return an empty array after delete_all().'
		);
		$this->assertNotNull(
			$this->theme_storage->get( 'theme-a' ),
			'delete_all() for plugins should not affect themes.'
		);
	}

	/**
	 * Tests that delete_all() removes the entire option when no other types exist.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::delete_all
	 */
	public function test_delete_all_removes_option_when_no_other_types() {
		$this->plugin_storage->set( 'plugin-a', $this->get_dummy_error() );
		$this->plugin_storage->set( 'plugin-b', $this->get_dummy_error() );

		$this->plugin_storage->delete_all();

		$this->assertFalse(
			get_option( self::TEST_SESSION_ID . '_paused_extensions' ),
			'The option should be deleted from the database when delete_all() leaves no other types.'
		);
	}

	/**
	 * Tests that is_api_loaded() protected method returns true when get_option function exists.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::is_api_loaded
	 */
	public function test_is_api_loaded() {
		$reflection = new ReflectionMethod( $this->plugin_storage, 'is_api_loaded' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$this->assertTrue(
			$reflection->invoke( $this->plugin_storage ),
			'is_api_loaded() should return true when get_option() is available.'
		);
	}

	/**
	 * Tests that get_option_name() protected method generates the expected option key.
	 *
	 * @ticket 46130
	 * @ticket 44458
	 *
	 * @covers WP_Paused_Extensions_Storage::get_option_name
	 */
	public function test_get_option_name() {
		$reflection = new ReflectionMethod( $this->plugin_storage, 'get_option_name' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$expected_option_name = self::TEST_SESSION_ID . '_paused_extensions';
		$this->assertSame(
			$expected_option_name,
			$reflection->invoke( $this->plugin_storage ),
			'get_option_name() should return session_id appended with _paused_extensions.'
		);
	}
}
