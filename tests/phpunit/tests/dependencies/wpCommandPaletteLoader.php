<?php
/**
 * Tests for loading the Command Palette on demand.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 * @group admin-bar
 *
 * @covers ::wp_enqueue_command_palette_loader
 * @covers ::wp_enqueue_command_palette_assets
 * @covers ::wp_print_command_palette_assets_template
 * @covers ::wp_admin_bar_command_palette_menu
 */
class Tests_Dependencies_WpCommandPaletteLoader extends WP_UnitTestCase {

	/**
	 * Globals each test replaces, with the values they had before it.
	 *
	 * @var array<string, mixed>
	 */
	private $original_globals = array();

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
	}

	/**
	 * Gives each test fresh script and style registries and an admin screen.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'wp_scripts', 'wp_styles', 'menu', 'submenu' ) as $name ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
			unset( $GLOBALS[ $name ] );
		}

		$GLOBALS['menu']    = array();
		$GLOBALS['submenu'] = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'options-general' );
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

		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Tests that the loader is hooked to admin screens in place of the palette's assets.
	 *
	 * @ticket 66642
	 */
	public function test_loader_is_hooked_to_admin_enqueue_scripts(): void {
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', 'wp_enqueue_command_palette_loader' ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', 'wp_enqueue_command_palette_assets' ) );
		$this->assertSame( 20, has_action( 'admin_print_footer_scripts', 'wp_print_command_palette_assets_template' ) );
	}

	/**
	 * Tests that a screen without the commands package only gets the loader, not the palette's
	 * scripts and styles.
	 *
	 * @ticket 66642
	 */
	public function test_loader_does_not_enqueue_palette_assets_on_classic_screen(): void {
		wp_enqueue_command_palette_loader();

		$this->assertTrue( wp_script_is( 'wp-command-palette-loader', 'enqueued' ), 'Expected the loader to be enqueued.' );
		$this->assertFalse( wp_script_is( 'wp-core-commands', 'enqueued' ), 'Expected wp-core-commands not to be enqueued.' );
		$this->assertFalse( wp_script_is( 'wp-commands', 'enqueued' ), 'Expected wp-commands not to be enqueued.' );
		$this->assertFalse( wp_style_is( 'wp-commands', 'enqueued' ), 'Expected the wp-commands style not to be enqueued.' );
		$this->assertFalse( wp_script_is( 'react', 'enqueued' ), 'Expected react not to be enqueued.' );
	}

	/**
	 * Tests that the palette's settings are attached to wp-core-commands even when it is loaded
	 * on demand, so it still initializes with the menu commands.
	 *
	 * @ticket 66642
	 */
	public function test_loader_adds_initialization_with_menu_commands(): void {
		$GLOBALS['menu'] = array(
			array( 'Settings', 'manage_options', 'options-general.php' ),
		);

		wp_enqueue_command_palette_loader();

		$after = implode( '', wp_scripts()->get_data( 'wp-core-commands', 'after' ) );

		$this->assertStringContainsString( 'wp.coreCommands.initializeCommandPalette(', $after );
		$this->assertStringContainsString( '"url":"options-general.php"', $after );
	}

	/**
	 * Tests that a screen which already loads the commands package, such as the block editor,
	 * keeps loading the whole palette up front.
	 *
	 * @ticket 66642
	 */
	public function test_loader_enqueues_palette_assets_when_screen_loads_commands_package(): void {
		wp_register_script( 'test-editor', '/test-editor.js', array( 'wp-commands' ) );
		wp_enqueue_script( 'test-editor' );

		wp_enqueue_command_palette_loader();

		$this->assertTrue( wp_script_is( 'wp-core-commands', 'enqueued' ), 'Expected wp-core-commands to be enqueued.' );
		$this->assertTrue( wp_style_is( 'wp-commands', 'enqueued' ), 'Expected the wp-commands style to be enqueued.' );
	}

	/**
	 * Tests that enqueuing the palette's assets directly still loads all of them up front.
	 *
	 * @ticket 66642
	 */
	public function test_enqueue_assets_loads_palette_up_front(): void {
		wp_enqueue_command_palette_assets();

		$this->assertTrue( wp_script_is( 'wp-command-palette-loader', 'enqueued' ), 'Expected the loader to be enqueued.' );
		$this->assertTrue( wp_script_is( 'wp-commands', 'enqueued' ), 'Expected wp-commands to be enqueued.' );
		$this->assertTrue( wp_script_is( 'wp-core-commands', 'enqueued' ), 'Expected wp-core-commands to be enqueued.' );
		$this->assertTrue( wp_style_is( 'wp-commands', 'enqueued' ), 'Expected the wp-commands style to be enqueued.' );
		$this->assertCount(
			1,
			wp_scripts()->get_data( 'wp-core-commands', 'after' ),
			'Expected the palette to be initialized once.'
		);
	}

	/**
	 * Tests that the template holds the palette's scripts, their inline scripts and its styles,
	 * leaving out what the page already printed.
	 *
	 * @ticket 66642
	 */
	public function test_template_holds_palette_assets_not_yet_printed(): void {
		wp_enqueue_command_palette_loader();
		wp_scripts()->done[] = 'wp-command-palette-loader';
		wp_scripts()->done[] = 'wp-i18n';

		$output = get_echo( 'wp_print_command_palette_assets_template' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'TEMPLATE' ), 'Expected a template element.' );
		$this->assertSame( 'wp-command-palette-assets', $processor->get_attribute( 'id' ) );

		$this->assertMatchesRegularExpression( '#<script[^>]+src="[^"]*/dist/core-commands(\.min)?\.js#', $output );
		$this->assertMatchesRegularExpression( '#<script[^>]+src="[^"]*/dist/commands(\.min)?\.js#', $output );
		$this->assertMatchesRegularExpression( '#<script[^>]+src="[^"]*/vendor/react(\.min)?\.js#', $output );
		$this->assertMatchesRegularExpression( '#<link[^>]+href=[\'"][^\'"]*/css/dist/commands/style(\.min)?\.css#', $output );
		$this->assertMatchesRegularExpression( '#<link[^>]+href=[\'"][^\'"]*/css/dist/components/style(\.min)?\.css#', $output );
		$this->assertStringContainsString( 'wp.coreCommands.initializeCommandPalette(', $output );
		$this->assertDoesNotMatchRegularExpression( '#/dist/i18n(\.min)?\.js#', $output, 'Expected scripts already on the page not to be printed again.' );
		$this->assertStringEndsWith( "</template>\n", $output );
	}

	/**
	 * Tests that no template is printed when the palette is already on the page, or the loader is not.
	 *
	 * @ticket 66642
	 *
	 * @dataProvider data_template_is_not_printed
	 *
	 * @param string[] $done_handles Script handles already printed.
	 */
	public function test_template_is_not_printed( array $done_handles ): void {
		wp_enqueue_command_palette_loader();
		wp_scripts()->done = array_merge( wp_scripts()->done, $done_handles );

		$this->assertSame( '', get_echo( 'wp_print_command_palette_assets_template' ) );
	}

	/**
	 * Data provider for test_template_is_not_printed().
	 *
	 * @return array<string, array{ 0: string[] }>
	 */
	public function data_template_is_not_printed(): array {
		return array(
			'loader not printed'      => array( array() ),
			'palette already printed' => array( array( 'wp-command-palette-loader', 'wp-core-commands' ) ),
		);
	}

	/**
	 * Tests that the toolbar button is added when the loader is enqueued, and opens the palette
	 * through it rather than through an inline handler that needs wp-data on the page.
	 *
	 * @ticket 66642
	 */
	public function test_admin_bar_button_relies_on_loader(): void {
		wp_enqueue_command_palette_loader();

		$admin_bar = new WP_Admin_Bar();
		wp_admin_bar_command_palette_menu( $admin_bar );
		$node = $admin_bar->get_node( 'command-palette' );

		$this->assertNotNull( $node, 'Expected the command palette button.' );
		$this->assertArrayNotHasKey( 'onclick', $node->meta );
	}

	/**
	 * Tests that the toolbar button is left out when the palette is not available.
	 *
	 * @ticket 66642
	 */
	public function test_admin_bar_button_requires_loader(): void {
		$admin_bar = new WP_Admin_Bar();
		wp_admin_bar_command_palette_menu( $admin_bar );

		$this->assertNull( $admin_bar->get_node( 'command-palette' ) );
	}
}
