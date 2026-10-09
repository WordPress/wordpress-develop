<?php

/**
 * Tests for reading theme metadata from theme.json.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @group themes
 *
 * @covers WP_Theme::read_json_metadata
 */
class Tests_Theme_wpThemeJsonMetadata extends WP_UnitTestCase {

	/**
	 * Theme root directory.
	 *
	 * @var string
	 */
	private $theme_root;

	/**
	 * Original theme directory.
	 *
	 * @var string
	 */
	private $orig_theme_dir;

	/**
	 * Original template option.
	 *
	 * @var string
	 */
	private $orig_template;

	/**
	 * Original stylesheet option.
	 *
	 * @var string
	 */
	private $orig_stylesheet;

	public function set_up() {
		parent::set_up();
		$this->theme_root = realpath( DIR_TESTDATA . '/themedir1' );

		$this->orig_theme_dir            = $GLOBALS['wp_theme_directories'];
		$GLOBALS['wp_theme_directories'] = array( $this->theme_root );
		$this->orig_template             = get_option( 'template' );
		$this->orig_stylesheet           = get_option( 'stylesheet' );

		add_filter( 'theme_root', array( $this, '_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, '_theme_root' ) );
		add_filter( 'template_root', array( $this, '_theme_root' ) );
		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		update_option( 'template', $this->orig_template );
		update_option( 'stylesheet', $this->orig_stylesheet );
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tear_down();
	}

	public function _theme_root( $dir ) {
		return $this->theme_root;
	}

	/**
	 * Registers an extra theme header for testing.
	 *
	 * @param string[] $headers Existing extra headers.
	 * @return string[] Filtered extra headers.
	 */
	public function filter_extra_theme_headers( $headers ) {
		$headers[] = 'Custom Header';
		return $headers;
	}

	/**
	 * Tests that theme.json metadata takes priority over style.css headers.
	 *
	 * @ticket 24152
	 */
	public function test_theme_json_metadata_takes_priority_over_stylesheet_headers() {
		$theme = new WP_Theme( 'json-metadata-theme', $this->theme_root );

		$this->assertSame( 'JSON Metadata Theme', $theme->get( 'Name' ) );
		$this->assertSame( 'https://example.com/json-metadata-theme', $theme->get( 'ThemeURI' ) );
		$this->assertSame( 'A theme using JSON metadata in theme.json', $theme->get( 'Description' ) );
		$this->assertSame( 'Theme Author', $theme->get( 'Author' ) );
		$this->assertSame( 'https://example.com/author', $theme->get( 'AuthorURI' ) );
		$this->assertSame( '1.0.0', $theme->get( 'Version' ) );
		$this->assertSame( 'json-metadata-theme', $theme->get( 'TextDomain' ) );
		$this->assertSame( '6.0', $theme->get( 'RequiresWP' ) );
		$this->assertSame( '8.0', $theme->get( 'RequiresPHP' ) );
	}

	/**
	 * Tests that tags are properly converted from JSON array to comma-separated string.
	 *
	 * @ticket 24152
	 */
	public function test_theme_json_metadata_tags_are_parsed() {
		$theme = new WP_Theme( 'json-metadata-theme', $this->theme_root );

		$this->assertSame( array( 'blog', 'custom-colors' ), $theme->get( 'Tags' ) );
	}

	/**
	 * Tests that a theme with theme.json metadata but no style.css is valid.
	 *
	 * @ticket 24152
	 */
	public function test_theme_json_metadata_without_stylesheet() {
		$theme = new WP_Theme( 'json-metadata-no-stylesheet', $this->theme_root );

		$this->assertSame( 'JSON Only Theme', $theme->get( 'Name' ) );
		$this->assertSame( '2.0.0', $theme->get( 'Version' ) );
		$this->assertSame( '7.0', $theme->get( 'RequiresWP' ) );
		$this->assertSame( '8.2', $theme->get( 'RequiresPHP' ) );
		$this->assertFalse( $theme->errors() );
	}

	/**
	 * Tests that style.css headers are used when theme.json has no metadata property.
	 *
	 * @ticket 24152
	 */
	public function test_falls_back_to_stylesheet_headers_without_json_metadata() {
		$theme = new WP_Theme( 'default', $this->theme_root );

		$this->assertSame( 'WordPress Default', $theme->get( 'Name' ) );
		$this->assertFalse( $theme->errors() );
	}

	/**
	 * Tests that malformed theme.json silently falls back to style.css headers.
	 *
	 * @ticket 24152
	 */
	public function test_malformed_theme_json_falls_back_to_stylesheet_headers() {
		$theme = new WP_Theme( 'block-theme-non-latin', $this->theme_root );

		// Should fall back to style.css headers without errors from JSON parsing.
		$this->assertNotEmpty( $theme->get( 'Name' ) );
	}

	/**
	 * Tests that a block theme with theme.json but no metadata property uses style.css.
	 *
	 * @ticket 24152
	 */
	public function test_block_theme_without_metadata_property_uses_stylesheet() {
		$theme = new WP_Theme( 'block-theme', $this->theme_root );

		// block-theme has theme.json for settings but no metadata property.
		$this->assertSame( 'Block Theme', $theme->get( 'Name' ) );
		$this->assertFalse( $theme->errors() );
	}

	/**
	 * Tests that custom theme headers registered via extra_theme_headers are read from theme.json metadata.
	 *
	 * @ticket 24152
	 */
	public function test_theme_json_supports_extra_theme_headers() {
		$theme_slug = 'json-custom-header-theme-' . wp_generate_password( 8, false );
		$theme_dir  = $this->theme_root . '/' . $theme_slug;

		add_filter( 'extra_theme_headers', array( $this, 'filter_extra_theme_headers' ) );
		mkdir( $theme_dir );
		try {
			file_put_contents(
				$theme_dir . '/theme.json',
				wp_json_encode(
					array(
						'version'  => 3,
						'metadata' => array(
							'name'          => 'JSON Custom Header Theme',
							'Custom Header' => 'Custom Theme Header Value',
						),
					)
				)
			);
			file_put_contents( $theme_dir . '/style.css', "/*\nTheme Name: CSS Fallback\n*/\n" );
			file_put_contents( $theme_dir . '/index.php', "<?php\n// Test theme.\n" );

			wp_clean_themes_cache();
			unset( $GLOBALS['wp_themes'] );

			$theme = new WP_Theme( $theme_slug, $this->theme_root );
			$this->assertSame( 'Custom Theme Header Value', $theme->get( 'Custom Header' ) );
		} finally {
			remove_filter( 'extra_theme_headers', array( $this, 'filter_extra_theme_headers' ) );
			if ( file_exists( $theme_dir . '/theme.json' ) ) {
				unlink( $theme_dir . '/theme.json' );
			}
			if ( file_exists( $theme_dir . '/style.css' ) ) {
				unlink( $theme_dir . '/style.css' );
			}
			if ( file_exists( $theme_dir . '/index.php' ) ) {
				unlink( $theme_dir . '/index.php' );
			}
			if ( is_dir( $theme_dir ) ) {
				rmdir( $theme_dir );
			}
			wp_clean_themes_cache();
			unset( $GLOBALS['wp_themes'] );
		}
	}

	/**
	 * Tests that active theme validation allows a JSON-metadata theme without style.css.
	 *
	 * @ticket 24152
	 */
	public function test_validate_current_theme_allows_json_metadata_without_stylesheet() {
		update_option( 'template', 'json-metadata-no-stylesheet' );
		update_option( 'stylesheet', 'json-metadata-no-stylesheet' );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		$this->assertTrue( validate_current_theme() );
	}

	/**
	 * Tests that active theme validation allows a child theme without style.css when JSON metadata is present.
	 *
	 * @ticket 24152
	 */
	public function test_validate_current_theme_allows_child_json_metadata_without_stylesheet() {
		$child_slug = 'json-metadata-child-no-stylesheet-' . wp_generate_password( 8, false );
		$child_dir  = $this->theme_root . '/' . $child_slug;

		mkdir( $child_dir );
		try {
			file_put_contents(
				$child_dir . '/theme.json',
				wp_json_encode(
					array(
						'version'  => 3,
						'metadata' => array(
							'name'     => 'Temporary JSON Child Theme',
							'template' => 'json-metadata-theme',
						),
					)
				)
			);
			file_put_contents( $child_dir . '/index.php', "<?php\n// Test child theme.\n" );

			update_option( 'template', 'json-metadata-theme' );
			update_option( 'stylesheet', $child_slug );
			wp_clean_themes_cache();
			unset( $GLOBALS['wp_themes'] );

			$this->assertTrue( validate_current_theme() );
		} finally {
			if ( file_exists( $child_dir . '/theme.json' ) ) {
				unlink( $child_dir . '/theme.json' );
			}
			if ( file_exists( $child_dir . '/index.php' ) ) {
				unlink( $child_dir . '/index.php' );
			}
			if ( is_dir( $child_dir ) ) {
				rmdir( $child_dir );
			}
		}
	}

	/**
	 * Tests that active theme validation rejects malformed theme.json when style.css is missing.
	 *
	 * @ticket 24152
	 */
	public function test_validate_current_theme_rejects_malformed_json_without_stylesheet() {
		$theme_slug = 'json-metadata-malformed-no-stylesheet-' . wp_generate_password( 8, false );
		$theme_dir  = $this->theme_root . '/' . $theme_slug;

		mkdir( $theme_dir );
		try {
			file_put_contents( $theme_dir . '/theme.json', '{ this is not valid json }' );
			file_put_contents( $theme_dir . '/index.php', "<?php\n// Test theme.\n" );

			update_option( 'template', $theme_slug );
			update_option( 'stylesheet', $theme_slug );
			wp_clean_themes_cache();
			unset( $GLOBALS['wp_themes'] );

			$this->assertFalse( validate_current_theme() );
			$this->assertNotSame( $theme_slug, get_option( 'template' ) );
			$this->assertNotSame( $theme_slug, get_option( 'stylesheet' ) );
		} finally {
			if ( file_exists( $theme_dir . '/theme.json' ) ) {
				unlink( $theme_dir . '/theme.json' );
			}
			if ( file_exists( $theme_dir . '/index.php' ) ) {
				unlink( $theme_dir . '/index.php' );
			}
			if ( is_dir( $theme_dir ) ) {
				rmdir( $theme_dir );
			}
		}
	}
}
