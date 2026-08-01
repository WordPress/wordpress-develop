<?php

/**
 * Tests for block style handles.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.3.0
 *
 * @group blocks
 *
 * @covers ::register_core_block_style_handles
 */
class Tests_Blocks_registerCoreBlockStyleHandles extends WP_UnitTestCase {

	/**
	 * @var WP_Styles|null
	 */
	protected $original_wp_styles;

	/**
	 * @var string
	 */
	private $includes_url;

	/**
	 * @var string
	 */
	private $blocks_url;

	/**
	 * The text direction in place before a test changed it, or null when unchanged.
	 *
	 * @var string|null
	 */
	private $original_text_direction = null;

	const STYLE_FIELDS = array(
		'style'       => 'style',
		'editorStyle' => 'editor',
	);

	/**
	 * Name of the transient holding the list of core block stylesheet paths.
	 */
	const FILE_LIST_TRANSIENT = 'wp_core_block_css_files';

	public function set_up() {
		parent::set_up();

		global $wp_styles;
		$this->original_wp_styles = $wp_styles;
		$wp_styles                = null;
		wp_styles();

		$this->includes_url = includes_url();
		$this->blocks_url   = includes_url( 'blocks/' );

		// Ensure each test starts from a known cache state.
		delete_transient( self::FILE_LIST_TRANSIENT );

		remove_action( 'wp_default_styles', 'wp_default_styles' );
	}

	public function tear_down() {
		global $wp_styles, $wp_locale;
		$wp_styles = $this->original_wp_styles;

		if ( null !== $this->original_text_direction ) {
			$wp_locale->text_direction     = $this->original_text_direction;
			$this->original_text_direction = null;
		}

		unset( $GLOBALS['_wp_tests_development_mode'] );
		delete_transient( self::FILE_LIST_TRANSIENT );

		add_action( 'wp_default_styles', 'wp_default_styles' );

		parent::tear_down();
	}

	/**
	 * @ticket 58528
	 *
	 * @dataProvider data_block_data
	 *
	 * @covers ::register_core_block_style_handles
	 * @covers ::wp_should_load_separate_core_block_assets
	 *
	 * @param string $name   The block name.
	 * @param array  $schema The block's schema.
	 */
	public function test_wp_should_load_separate_core_block_assets_false( $name, $schema ) {
		add_filter( 'should_load_separate_core_block_assets', '__return_false' );
		$this->assertFalse( wp_should_load_separate_core_block_assets(), 'Core blocks are not expected to load separate assets' );
		register_core_block_style_handles();

		foreach ( self::STYLE_FIELDS as $style_field => $filename ) {
			$style_handle = $schema[ $style_field ];
			if ( is_array( $style_handle ) ) {
				continue;
			}

			$this->assertArrayNotHasKey( $style_handle, $GLOBALS['wp_styles']->registered, 'The key should not exist, as this style should not be registered' );
		}
	}


	/**
	 * @ticket 58528
	 *
	 * @dataProvider data_block_data
	 *
	 * @covers ::register_core_block_style_handles
	 * @covers ::wp_should_load_separate_core_block_assets
	 *
	 * @param string $name   The block name.
	 * @param array  $schema The block's schema.
	 */
	public function test_wp_should_load_separate_core_block_assets_true( $name, $schema ) {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		$this->assertTrue( wp_should_load_separate_core_block_assets(), 'Core assets are expected to load separately' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		foreach ( self::STYLE_FIELDS as $style_field => $filename ) {
			$style_handle = $schema[ $style_field ];
			if ( is_array( $style_handle ) ) {
				continue;
			}

			$this->assertArrayHasKey( $style_handle, $wp_styles->registered, 'The key should exist, as this style should be registered' );
			if ( false === $wp_styles->registered[ $style_handle ]->src ) {
				$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, 'If source is false, style path should not be set' );
			} else {
				$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, 'Source of style should contain the includes url' );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, 'The path of the style should exist' );
				$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, 'The path key of the style should exist in extra array' );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], 'The path key of the style should not be empty' );
			}
		}
	}

	/**
	 * @ticket 58560
	 *
	 * @dataProvider data_block_data
	 *
	 * @param string $name The block name.
	 */
	public function test_wp_should_load_separate_core_block_assets_current_theme_supports( $name ) {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		add_theme_support( 'wp-block-styles' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		$style_handle = "wp-block-{$name}-theme";

		$this->assertArrayHasKey( $style_handle, $wp_styles->registered, 'The key should exist, as this style should be registered' );
		if ( false === $wp_styles->registered[ $style_handle ]->src ) {
			$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, 'If source is false, style path should not be set' );
		} else {
			$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, 'Source of style should contain the includes url' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, 'The path of the style should exist' );
			$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, 'The path key of the style should exist in extra array' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], 'The path key of the style should not be empty' );
		}
	}

	/**
	 * @ticket 59715
	 *
	 * @dataProvider data_block_data
	 *
	 * @param string $name The block name.
	 */
	public function test_register_core_block_style_handles_should_load_rtl_stylesheets_for_rtl_text_direction( $name ) {
		global $wp_locale;

		$orig_text_dir             = $wp_locale->text_direction;
		$wp_locale->text_direction = 'rtl';

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		$style_handle = "wp-block-{$name}-theme";

		$wp_locale->text_direction = $orig_text_dir;

		$this->assertArrayHasKey( $style_handle, $wp_styles->registered, 'The key should exist, as this style should be registered' );
		if ( false === $wp_styles->registered[ $style_handle ]->src ) {
			$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, 'If source is false, style path should not be set' );
		} else {
			$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, 'Source of style should contain the includes url' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, 'The path of the style should exist' );
			$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, 'The path key of the style should exist in extra array' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], 'The path key of the style should not be empty' );
			$this->assertArrayHasKey( 'rtl', $wp_styles->registered[ $style_handle ]->extra, 'The rtl key of the style should exist in extra array' );
		}
	}

	/**
	 * Tests that every core block style handle is registered with exactly the source and
	 * extra data implied by the stylesheets that are present on disk.
	 *
	 * The whole registration map is asserted at once, using `file_exists()` as an oracle
	 * that is independent of the file list the function builds internally. Any handle that
	 * gains a source it should not have, loses one it should have, or ends up with the
	 * wrong `path` will fail this test.
	 *
	 * @ticket 65779
	 */
	public function test_registered_block_styles_match_the_stylesheets_on_disk(): void {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		$this->reset_wp_styles();
		$this->assertSame( array(), $GLOBALS['wp_styles']->registered, 'No styles should be registered before the function runs.' );

		register_core_block_style_handles();

		$expected = $this->get_expected_block_styles();

		$this->assertNotEmpty( $expected, 'The expected style list should not be empty.' );
		$this->assertEquals(
			$expected,
			$this->get_registered_block_styles(),
			'The registered block styles should match the stylesheets present on disk.'
		);
	}

	/**
	 * Tests that the RTL data of every core block style handle matches the RTL stylesheets
	 * present on disk, and that handles without an RTL stylesheet are left untouched.
	 *
	 * @ticket 65779
	 */
	public function test_registered_block_styles_match_the_rtl_stylesheets_on_disk(): void {
		$this->set_text_direction( 'rtl' );

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		$this->assertTrue( is_rtl(), 'The text direction should be RTL.' );

		$this->reset_wp_styles();
		register_core_block_style_handles();

		$expected = $this->get_expected_block_styles( true );

		$this->assertNotEmpty( $expected, 'The expected style list should not be empty.' );
		$this->assertEquals(
			$expected,
			$this->get_registered_block_styles(),
			'The registered block styles should match the RTL stylesheets present on disk.'
		);
	}

	/**
	 * Tests that a style whose path is absent from the file list is registered without a
	 * source and without any extra data.
	 *
	 * This covers the early return in the `$register_style` closure: no `path` data may be
	 * recorded for a stylesheet that was not found.
	 *
	 * @ticket 65779
	 */
	public function test_style_is_registered_without_a_source_when_absent_from_the_file_list(): void {
		$this->set_cached_file_list( array( 'not-a-block/not-a-stylesheet.css' ) );

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		$this->reset_wp_styles();
		register_core_block_style_handles();

		$registered = $this->get_registered_block_styles();

		$this->assertNotEmpty( $registered, 'Style handles should still be registered.' );

		foreach ( $registered as $handle => $data ) {
			$this->assertFalse( $data['src'], "The '{$handle}' handle should be registered without a source." );
			$this->assertSame( array(), $data['extra'], "The '{$handle}' handle should not have any extra data." );
		}
	}

	/**
	 * Tests that a file list entry only matches a stylesheet path it is identical to.
	 *
	 * @ticket 65779
	 *
	 * @dataProvider data_file_list_entries_that_should_not_match
	 *
	 * @param string $file A file list entry that must not match the paragraph block stylesheet.
	 */
	public function test_file_list_lookup_requires_an_exact_match( string $file ): void {
		$this->set_cached_file_list( array( $file ) );

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		$this->reset_wp_styles();
		register_core_block_style_handles();

		$style_path = 'paragraph/style' . wp_scripts_get_suffix() . '.css';

		$this->assertFalse(
			$GLOBALS['wp_styles']->registered['wp-block-paragraph']->src,
			"A file list entry of '{$file}' should not match '{$style_path}'."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_file_list_entries_that_should_not_match(): array {
		$suffix     = wp_scripts_get_suffix();
		$style_path = "paragraph/style{$suffix}.css";

		return array(
			'a longer path'            => array( $style_path . '.map' ),
			'a truncated path'         => array( substr( $style_path, 0, -1 ) ),
			'a path without extension' => array( "paragraph/style{$suffix}" ),
			'a differently cased path' => array( ucfirst( $style_path ) ),
			'a padded path'            => array( ' ' . $style_path ),
			'a doubled separator'      => array( "paragraph//style{$suffix}.css" ),
			'the RTL path'             => array( "paragraph/style-rtl{$suffix}.css" ),
		);
	}

	/**
	 * Tests that numeric and duplicate file list entries do not affect lookups.
	 *
	 * Array keys that look like integers are cast to integers by PHP, and duplicate entries
	 * collapse into a single key. Neither may change which stylesheets are found.
	 *
	 * @ticket 65779
	 */
	public function test_file_list_lookup_is_unaffected_by_numeric_and_duplicate_entries(): void {
		$suffix     = wp_scripts_get_suffix();
		$style_path = "paragraph/style{$suffix}.css";

		$this->set_cached_file_list(
			array(
				'0',
				'123',
				'',
				'0123',
				'1e3',
				$style_path,
				$style_path,
			)
		);

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		$this->reset_wp_styles();
		register_core_block_style_handles();

		$registered = $GLOBALS['wp_styles']->registered;

		$this->assertSame(
			$this->blocks_url . $style_path,
			$registered['wp-block-paragraph']->src,
			'The paragraph style should be registered from the file list.'
		);
		$this->assertSame(
			wp_normalize_path( BLOCKS_PATH . $style_path ),
			$registered['wp-block-paragraph']->extra['path'],
			'The paragraph style should point at the paragraph stylesheet.'
		);
		$this->assertFalse(
			$registered['wp-block-paragraph-editor']->src,
			'A style that is absent from the file list should not be registered with a source.'
		);
	}

	/**
	 * Tests that RTL data is added only when the RTL stylesheet is in the file list.
	 *
	 * @ticket 65779
	 *
	 * @dataProvider data_rtl_file_presence
	 *
	 * @param bool $include_rtl_file Whether to include the RTL stylesheet in the file list.
	 */
	public function test_rtl_data_requires_the_rtl_file_in_the_file_list( bool $include_rtl_file ): void {
		$suffix     = wp_scripts_get_suffix();
		$style_path = "paragraph/style{$suffix}.css";
		$rtl_path   = "paragraph/style-rtl{$suffix}.css";

		$files = array( $style_path );
		if ( $include_rtl_file ) {
			$files[] = $rtl_path;
		}

		$this->set_text_direction( 'rtl' );
		$this->set_cached_file_list( $files );

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		$this->reset_wp_styles();
		register_core_block_style_handles();

		$extra = $GLOBALS['wp_styles']->registered['wp-block-paragraph']->extra;

		if ( $include_rtl_file ) {
			$this->assertSame( 'replace', $extra['rtl'], 'The RTL stylesheet should replace the default one.' );
			$this->assertSame( $suffix, $extra['suffix'], 'The suffix should be recorded alongside the RTL data.' );
			$this->assertSame(
				wp_normalize_path( BLOCKS_PATH . $rtl_path ),
				$extra['path'],
				'The path should point at the RTL stylesheet.'
			);
		} else {
			$this->assertArrayNotHasKey( 'rtl', $extra, 'No RTL data should be added without an RTL stylesheet.' );
			$this->assertArrayNotHasKey( 'suffix', $extra, 'No suffix should be added without an RTL stylesheet.' );
			$this->assertSame(
				wp_normalize_path( BLOCKS_PATH . $style_path ),
				$extra['path'],
				'The path should point at the default stylesheet.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_rtl_file_presence(): array {
		return array(
			'an RTL stylesheet in the file list' => array( true ),
			'no RTL stylesheet in the file list' => array( false ),
		);
	}

	/**
	 * Tests that the cached file list produces the same registrations as a fresh glob.
	 *
	 * @ticket 65779
	 */
	public function test_cached_file_list_produces_the_same_registrations_as_a_fresh_glob(): void {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		$this->assertFalse( get_transient( self::FILE_LIST_TRANSIENT ), 'The file list should not be cached yet.' );

		$this->reset_wp_styles();
		register_core_block_style_handles();
		$from_glob = $this->get_registered_block_styles();

		$cached = get_transient( self::FILE_LIST_TRANSIENT );

		$this->assertIsArray( $cached, 'The file list should have been cached.' );
		$this->assertSame( wp_get_wp_version(), $cached['version'], 'The cache should record the current WordPress version.' );
		$this->assertNotEmpty( $cached['files'], 'The cached file list should not be empty.' );

		$this->reset_wp_styles();
		register_core_block_style_handles();
		$from_cache = $this->get_registered_block_styles();

		$this->assertNotEmpty( $from_glob, 'Styles should have been registered from the fresh glob.' );
		$this->assertEquals(
			$from_glob,
			$from_cache,
			'Registrations built from the cached file list should match those built from a fresh glob.'
		);
	}

	/**
	 * Tests that the cached file list is ignored when the development mode is set to 'core'.
	 *
	 * @ticket 65779
	 */
	public function test_cached_file_list_is_ignored_in_core_development_mode(): void {
		$GLOBALS['_wp_tests_development_mode'] = 'core';
		$this->assertTrue( wp_is_development_mode( 'core' ), 'The development mode should be set to core.' );

		$stale = array( 'not-a-block/not-a-stylesheet.css' );
		$this->set_cached_file_list( $stale );

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		$this->reset_wp_styles();
		register_core_block_style_handles();

		$style_path = 'paragraph/style' . wp_scripts_get_suffix() . '.css';

		$this->assertSame(
			$this->blocks_url . $style_path,
			$GLOBALS['wp_styles']->registered['wp-block-paragraph']->src,
			'The stale cached file list should have been ignored in core development mode.'
		);

		$cached = get_transient( self::FILE_LIST_TRANSIENT );

		$this->assertIsArray( $cached, 'The cached file list should still exist.' );
		$this->assertSame( $stale, $cached['files'], 'The cache should not be refreshed in core development mode.' );
	}

	/**
	 * Builds the block style registrations expected from the stylesheets present on disk.
	 *
	 * This mirrors the order in which `register_core_block_style_handles()` registers
	 * handles, including the fact that `WP_Styles::add()` ignores a handle that is already
	 * registered, so that blocks sharing a handle resolve to the first block that claims it.
	 *
	 * @param bool $rtl Optional. Whether RTL stylesheets are expected. Default false.
	 * @return array[] Array of expected `src` and `extra` values, keyed by style handle.
	 */
	private function get_expected_block_styles( bool $rtl = false ): array {
		$suffix           = wp_scripts_get_suffix();
		$core_blocks_meta = require BLOCKS_PATH . 'blocks-json.php';
		$expected         = array();

		foreach ( $core_blocks_meta as $name => $schema ) {
			/** This filter is documented in wp-includes/blocks.php */
			$schema = apply_filters( 'block_type_metadata', $schema );

			// Backfill these properties similar to `register_block_type_from_metadata()`.
			if ( ! isset( $schema['style'] ) ) {
				$schema['style'] = "wp-block-{$name}";
			}
			if ( ! isset( $schema['editorStyle'] ) ) {
				$schema['editorStyle'] = "wp-block-{$name}-editor";
			}

			$styles = array( array( "wp-block-{$name}-theme", 'theme' ) );

			foreach ( self::STYLE_FIELDS as $style_field => $filename ) {
				$style_handle = $schema[ $style_field ];
				if ( is_array( $style_handle ) ) {
					continue;
				}
				$styles[] = array( $style_handle, $filename );
			}

			foreach ( $styles as list( $style_handle, $filename ) ) {
				// WP_Styles::add() is a no-op for an already registered handle.
				if ( isset( $expected[ $style_handle ] ) ) {
					continue;
				}

				$style_path = "{$name}/{$filename}{$suffix}.css";

				if ( ! file_exists( BLOCKS_PATH . $style_path ) ) {
					$expected[ $style_handle ] = array(
						'src'   => false,
						'extra' => array(),
					);
					continue;
				}

				$path  = wp_normalize_path( BLOCKS_PATH . $style_path );
				$extra = array( 'path' => $path );

				$rtl_file = "{$name}/{$filename}-rtl{$suffix}.css";
				if ( $rtl && file_exists( BLOCKS_PATH . $rtl_file ) ) {
					$extra['rtl']    = 'replace';
					$extra['suffix'] = $suffix;
					$extra['path']   = wp_normalize_path( BLOCKS_PATH . $rtl_file );
				}

				$expected[ $style_handle ] = array(
					'src'   => $this->blocks_url . $style_path,
					'extra' => $extra,
				);
			}
		}

		return $expected;
	}

	/**
	 * Returns the source and extra data of every registered style, keyed by handle.
	 *
	 * @return array[] Array of `src` and `extra` values, keyed by style handle.
	 */
	private function get_registered_block_styles(): array {
		$registered = array();

		foreach ( $GLOBALS['wp_styles']->registered as $handle => $dependency ) {
			$registered[ $handle ] = array(
				'src'   => $dependency->src,
				'extra' => $dependency->extra,
			);
		}

		return $registered;
	}

	/**
	 * Caches a list of core block stylesheet paths.
	 *
	 * @param string[] $files List of stylesheet paths relative to the blocks directory.
	 */
	private function set_cached_file_list( array $files ): void {
		set_transient(
			self::FILE_LIST_TRANSIENT,
			array(
				'version' => wp_get_wp_version(),
				'files'   => $files,
			)
		);
	}

	/**
	 * Sets the text direction, remembering the original for tear_down().
	 *
	 * @param string $direction Either 'ltr' or 'rtl'.
	 */
	private function set_text_direction( string $direction ): void {
		global $wp_locale;

		if ( null === $this->original_text_direction ) {
			$this->original_text_direction = $wp_locale->text_direction;
		}

		$wp_locale->text_direction = $direction;
	}

	/**
	 * Replaces the global styles registry with an empty one.
	 *
	 * The registry created in set_up() still holds the default styles, because
	 * `wp_default_styles()` is only unhooked afterwards. Recreating it here leaves a
	 * registry that contains nothing but what the function under test registers.
	 */
	private function reset_wp_styles(): void {
		$GLOBALS['wp_styles'] = null;
		wp_styles();
	}

	public function data_block_data() {
		$core_blocks_meta = require ABSPATH . WPINC . '/blocks/blocks-json.php';

		// Remove this blocks for now, as they are registered elsewhere.
		unset( $core_blocks_meta['archives'] );
		unset( $core_blocks_meta['widget-group'] );

		$data = array();
		foreach ( $core_blocks_meta as $name => $schema ) {
			if ( ! isset( $schema['style'] ) ) {
				$schema['style'] = "wp-block-$name";
			}
			if ( ! isset( $schema['editorStyle'] ) ) {
				$schema['editorStyle'] = "wp-block-{$name}-editor";
			}

			$data[ $name ] = array( $name, $schema );
		}

		return $data;
	}
}
