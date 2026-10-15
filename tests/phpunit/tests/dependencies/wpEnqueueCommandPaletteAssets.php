<?php
/**
 * Tests for the admin menu commands of {@see wp_enqueue_command_palette_assets()}.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 *
 * @covers ::wp_enqueue_command_palette_assets
 * @covers ::wp_get_admin_menu_command_keywords
 */
class Tests_Dependencies_WpEnqueueCommandPaletteAssets extends WP_UnitTestCase {

	/**
	 * Globals each test replaces, with the values they had before it.
	 *
	 * @var array<string, mixed>
	 */
	private $original_globals = array();

	/**
	 * Gives each test fresh script and style registries, an administrator, and a small admin menu.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'wp_scripts', 'wp_styles', 'menu', 'submenu' ) as $name ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
			unset( $GLOBALS[ $name ] );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$GLOBALS['menu'] = array(
			80  => array( 'Settings', 'manage_options', 'options-general.php' ),
			100 => array( 'My Plugin', 'manage_options', 'my-plugin.php' ),
		);

		$GLOBALS['submenu'] = array(
			'options-general.php' => array(
				10 => array( 'General', 'manage_options', 'options-general.php' ),
			),
		);
	}

	/**
	 * Restores the globals the test replaced.
	 */
	public function tear_down(): void {
		foreach ( $this->original_globals as $name => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $name ] );
			} else {
				$GLOBALS[ $name ] = $value;
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that core admin screens have keywords, on both top-level and submenu commands.
	 *
	 * @ticket 66322
	 */
	public function test_core_menu_commands_have_keywords(): void {
		$commands = $this->get_menu_commands();

		$this->assertContains( 'favicon', $commands['options-general.php']['keywords'], 'The top-level command should have keywords.' );
		$this->assertContains( 'favicon', $commands['options-general.php-options-general.php']['keywords'], 'The submenu command should have keywords.' );
	}

	/**
	 * Tests that a menu command without keywords gets an empty list.
	 *
	 * @ticket 66322
	 */
	public function test_menu_command_without_keywords_has_empty_keywords(): void {
		$commands = $this->get_menu_commands();

		$this->assertSame( array(), $commands['my-plugin.php']['keywords'] );
	}

	/**
	 * Tests that the {@see 'admin_menu_command_keywords'} filter adds keywords to plugin pages.
	 *
	 * @ticket 66322
	 */
	public function test_filter_adds_keywords_to_plugin_menu_commands(): void {
		add_filter(
			'admin_menu_command_keywords',
			static function ( $keywords ) {
				$keywords['my-plugin.php'] = array( 'shop', 'orders' );
				return $keywords;
			}
		);

		$commands = $this->get_menu_commands();

		$this->assertSame( array( 'shop', 'orders' ), $commands['my-plugin.php']['keywords'] );
	}

	/**
	 * Tests that keywords from the filter are sent as a list, whatever their keys.
	 *
	 * @ticket 66322
	 */
	public function test_filtered_keywords_are_a_list(): void {
		add_filter(
			'admin_menu_command_keywords',
			static function ( $keywords ) {
				$keywords['my-plugin.php'] = array(
					'first'  => 'shop',
					'second' => 'orders',
				);
				return $keywords;
			}
		);

		$commands = $this->get_menu_commands();

		$this->assertSame( array( 'shop', 'orders' ), $commands['my-plugin.php']['keywords'] );
	}

	/**
	 * Tests that a menu item the current user cannot access gets no command.
	 *
	 * @ticket 66322
	 */
	public function test_inaccessible_menu_item_has_no_command(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$commands = $this->get_menu_commands();

		$this->assertArrayNotHasKey( 'options-general.php', $commands );
	}

	/**
	 * Enqueues the Command Palette assets and returns the admin menu commands they are initialized with.
	 *
	 * @return array<string, array<string, mixed>> Menu commands keyed by name.
	 */
	private function get_menu_commands(): array {
		wp_enqueue_command_palette_assets();

		$script = implode( "\n", (array) wp_scripts()->get_data( 'wp-core-commands', 'after' ) );

		$this->assertSame( 1, preg_match( '/initializeCommandPalette\( (.*) \);/s', $script, $matches ), 'The Command Palette should be initialized.' );

		$settings = json_decode( $matches[1], true );

		return array_column( $settings['menu_commands'], null, 'name' );
	}
}
