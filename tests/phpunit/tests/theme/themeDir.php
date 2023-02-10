<?php

/**
 * Test functions that fetch stuff from the theme directory
 *
 * @group themes
 */
class Tests_Theme_ThemeDir extends WP_UnitTestCase {

	/**
	 * Theme root directory.
	 *
	 * @var string
	 */
	const THEME_ROOT = DIR_TESTDATA . '/themedir1';

	/**
	 * Original theme directory.
	 *
	 * @var string
	 */
	private $orig_theme_dir;

	public function set_up() {
		parent::set_up();

		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', self::THEME_ROOT );

		add_filter( 'theme_root', array( $this, 'filter_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_theme_root' ) );
		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tear_down();
	}

	// Replace the normal theme root directory with our premade test directory.
	public function filter_theme_root( $dir ) {
		return self::THEME_ROOT;
	}

	/**
	 * @expectedDeprecated get_theme
	 * @expectedDeprecated get_themes
	 */
	public function test_theme_default() {
		$themes = get_themes();
		$theme  = get_theme( 'WordPress Default' );
		$this->assertSame( $themes['WordPress Default'], $theme );

		$this->assertNotEmpty( $theme );

		// echo gen_tests_array( 'theme', $theme );

		$this->assertSame( 'WordPress Default', $theme['Name'] );
		$this->assertSame( 'WordPress Default', $theme['Title'] );
		$this->assertSame( 'The default WordPress theme based on the famous <a href="http://binarybonsai.com/kubrick/">Kubrick</a>.', $theme['Description'] );
		$this->assertSame( '<a href="http://binarybonsai.com/">Michael Heilemann</a>', $theme['Author'] );
		$this->assertSame( '1.6', $theme['Version'] );
		$this->assertSame( 'default', $theme['Template'] );
		$this->assertSame( 'default', $theme['Stylesheet'] );

		$this->assertContains( self::THEME_ROOT . '/default/functions.php', $theme['Template Files'] );
		$this->assertContains( self::THEME_ROOT . '/default/index.php', $theme['Template Files'] );
		$this->assertContains( self::THEME_ROOT . '/default/style.css', $theme['Stylesheet Files'] );

		$this->assertSame( self::THEME_ROOT . '/default', $theme['Template Dir'] );
		$this->assertSame( self::THEME_ROOT . '/default', $theme['Stylesheet Dir'] );
		$this->assertSame( 'publish', $theme['Status'] );
		$this->assertSame( '', $theme['Parent Theme'] );
	}

	/**
	 * @expectedDeprecated get_theme
	 * @expectedDeprecated get_themes
	 */
	public function test_theme_sandbox() {
		$theme = get_theme( 'Sandbox' );

		$this->assertNotEmpty( $theme );

		// echo gen_tests_array( 'theme', $theme );

		$this->assertSame( 'Sandbox', $theme['Name'] );
		$this->assertSame( 'Sandbox', $theme['Title'] );
		$this->assertSame( 'A theme with powerful, semantic CSS selectors and the ability to add new skins.', $theme['Description'] );
		$this->assertSame( '<a href="http://andy.wordpress.com/">Andy Skelton</a> &amp; <a href="http://www.plaintxt.org/">Scott Allan Wallick</a>', $theme['Author'] );
		$this->assertSame( '0.6.1-wpcom', $theme['Version'] );
		$this->assertSame( 'sandbox', $theme['Template'] );
		$this->assertSame( 'sandbox', $theme['Stylesheet'] );

		$template_files = $theme['Template Files'];

		$this->assertSame( self::THEME_ROOT . '/sandbox/functions.php', reset( $template_files ) );
		$this->assertSame( self::THEME_ROOT . '/sandbox/index.php', next( $template_files ) );

		$stylesheet_files = $theme['Stylesheet Files'];

		$this->assertSame( self::THEME_ROOT . '/sandbox/style.css', reset( $stylesheet_files ) );

		$this->assertSame( self::THEME_ROOT . '/sandbox', $theme['Template Dir'] );
		$this->assertSame( self::THEME_ROOT . '/sandbox', $theme['Stylesheet Dir'] );
		$this->assertSame( 'publish', $theme['Status'] );
		$this->assertSame( '', $theme['Parent Theme'] );

	}

	/**
	 * A CSS-only theme
	 *
	 * @expectedDeprecated get_themes
	 */
	public function test_theme_stylesheet_only() {
		$themes = get_themes();

		$theme = $themes['Stylesheet Only'];
		$this->assertNotEmpty( $theme );

		// echo gen_tests_array( 'theme', $theme );

		$this->assertSame( 'Stylesheet Only', $theme['Name'] );
		$this->assertSame( 'Stylesheet Only', $theme['Title'] );
		$this->assertSame( 'A three-column widget-ready theme in dark blue.', $theme['Description'] );
		$this->assertSame( '<a href="http://www.example.com/">Henry Crun</a>', $theme['Author'] );
		$this->assertSame( '1.0', $theme['Version'] );
		$this->assertSame( 'sandbox', $theme['Template'] );
		$this->assertSame( 'stylesheetonly', $theme['Stylesheet'] );
		$this->assertContains( self::THEME_ROOT . '/sandbox/functions.php', $theme['Template Files'] );
		$this->assertContains( self::THEME_ROOT . '/sandbox/index.php', $theme['Template Files'] );

		$this->assertContains( self::THEME_ROOT . '/stylesheetonly/style.css', $theme['Stylesheet Files'] );

		$this->assertSame( self::THEME_ROOT . '/sandbox', $theme['Template Dir'] );
		$this->assertSame( self::THEME_ROOT . '/stylesheetonly', $theme['Stylesheet Dir'] );
		$this->assertSame( 'publish', $theme['Status'] );
		$this->assertSame( 'Sandbox', $theme['Parent Theme'] );

	}

	/**
	 * @expectedDeprecated get_themes
	 */
	public function test_theme_list() {
		$themes = get_themes();

		// Ignore themes in the default /themes directory.
		foreach ( $themes as $theme_name => $theme ) {
			if ( $theme->get_theme_root() !== self::THEME_ROOT ) {
				unset( $themes[ $theme_name ] );
			}
		}

		$theme_names = array_keys( $themes );
		$expected    = array(
			'WordPress Default',
			'Default Child Theme with no theme.json',
			'Sandbox',
			'Stylesheet Only',
			'My Theme',
			'My Theme/theme1',                    // Duplicate theme should be given a unique name.
			'My Subdir Theme',                    // Theme in a subdirectory should work.
			'Page Template Child Theme',          // Theme which inherits page templates.
			'Page Template Theme',                // Theme with page templates for other test code.
			'Theme with Spaces in the Directory',
			'Internationalized Theme',
			'Custom Internationalized Theme',
			'camelCase',
			'REST Theme',
			'Block Theme',
			'Block Theme Child Theme',
			'Block Theme Child with no theme.json',
			'Block Theme Child Theme With Fluid Typography',
			'Block Theme Child Theme With Fluid Typography Config',
			'Block Theme Non Latin',
			'Block Theme [0.4.0]',
			'Block Theme [1.0.0] in subdirectory',
			'Block Theme Deprecated Path',
			'Webfonts theme',
			'Empty `fontFace` in theme.json - no webfonts defined',
			'A theme with the Update URI header',
		);

		$this->assertSameSets( $expected, $theme_names );
	}

	/**
	 * @expectedDeprecated get_themes
	 * @expectedDeprecated get_broken_themes
	 */
	public function test_broken_themes() {
		$themes = get_themes();

		$expected = array(
			'broken-theme'           => array(
				'Name'        => 'broken-theme',
				'Title'       => 'broken-theme',
				'Description' => __( 'Stylesheet is missing.' ),
			),
			'Child and Parent Theme' => array(
				'Name'        => 'Child and Parent Theme',
				'Title'       => 'Child and Parent Theme',
				'Description' => sprintf( __( 'The theme defines itself as its parent theme. Please check the %s header.' ), '<code>Template</code>' ),
			),
		);

		$this->assertSame( $expected, get_broken_themes() );
	}

	public function test_wp_get_theme_with_non_default_theme_root() {
		$this->assertFalse( wp_get_theme( 'sandbox', self::THEME_ROOT )->errors() );
		$this->assertFalse( wp_get_theme( 'sandbox' )->errors() );
	}

	/**
	 * @expectedDeprecated get_themes
	 */
	public function test_page_templates() {
		$themes = get_themes();

		$theme = $themes['Page Template Theme'];
		$this->assertNotEmpty( $theme );

		$templates = $theme['Template Files'];
		$this->assertContains( self::THEME_ROOT . '/page-templates/template-top-level.php', $templates );
	}

	/**
	 * @expectedDeprecated get_theme_data
	 */
	public function test_get_theme_data_top_level() {
		$theme_data = get_theme_data( DIR_TESTDATA . '/themedir1/theme1/style.css' );

		$this->assertSame( 'My Theme', $theme_data['Name'] );
		$this->assertSame( 'http://example.org/', $theme_data['URI'] );
		$this->assertSame( 'An example theme', $theme_data['Description'] );
		$this->assertSame( '<a href="http://example.com/">Minnie Bannister</a>', $theme_data['Author'] );
		$this->assertSame( 'http://example.com/', $theme_data['AuthorURI'] );
		$this->assertSame( '1.3', $theme_data['Version'] );
		$this->assertSame( '', $theme_data['Template'] );
		$this->assertSame( 'publish', $theme_data['Status'] );
		$this->assertSame( array(), $theme_data['Tags'] );
		$this->assertSame( 'My Theme', $theme_data['Title'] );
		$this->assertSame( 'Minnie Bannister', $theme_data['AuthorName'] );
	}

	/**
	 * @expectedDeprecated get_theme_data
	 */
	public function test_get_theme_data_subdir() {
		$theme_data = get_theme_data( self::THEME_ROOT . '/subdir/theme2/style.css' );

		$this->assertSame( 'My Subdir Theme', $theme_data['Name'] );
		$this->assertSame( 'http://example.org/', $theme_data['URI'] );
		$this->assertSame( 'An example theme in a sub directory', $theme_data['Description'] );
		$this->assertSame( '<a href="http://wordpress.org/">Mr. WordPress</a>', $theme_data['Author'] );
		$this->assertSame( 'http://wordpress.org/', $theme_data['AuthorURI'] );
		$this->assertSame( '0.1', $theme_data['Version'] );
		$this->assertSame( '', $theme_data['Template'] );
		$this->assertSame( 'publish', $theme_data['Status'] );
		$this->assertSame( array(), $theme_data['Tags'] );
		$this->assertSame( 'My Subdir Theme', $theme_data['Title'] );
		$this->assertSame( 'Mr. WordPress', $theme_data['AuthorName'] );
	}

	/**
	 * @ticket 28662
	 */
	public function test_theme_dir_slashes() {
		$size = count( $GLOBALS['wp_theme_directories'] );

		@mkdir( WP_CONTENT_DIR . '/themes/foo' );
		@mkdir( WP_CONTENT_DIR . '/themes/foo-themes' );

		$this->assertFileExists( WP_CONTENT_DIR . '/themes/foo' );
		$this->assertFileExists( WP_CONTENT_DIR . '/themes/foo-themes' );

		register_theme_directory( '/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( 'themes/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( '/foo/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( 'foo/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( 'themes/foo/' );

		$this->assertCount( $size + 1, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( WP_CONTENT_DIR . '/foo-themes/' );

		$this->assertCount( $size + 1, $GLOBALS['wp_theme_directories'] );

		foreach ( $GLOBALS['wp_theme_directories'] as $dir ) {
			$this->assertNotEquals( '/', substr( $dir, -1 ) );
		}

		rmdir( WP_CONTENT_DIR . '/themes/foo' );
		rmdir( WP_CONTENT_DIR . '/themes/foo-themes' );
	}
}
