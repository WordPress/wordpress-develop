<?php

require_once __DIR__ . '/base.php';

/**
 * Tests for get_block_editor_theme_styles().
 *
 * @group themes
 * @group editor
 *
 * @covers ::get_block_editor_theme_styles
 */
class Tests_Theme_GetBlockEditorThemeStyles extends WP_Theme_UnitTestCase {

	/**
	 * Original stylesheet before tests ran.
	 *
	 * @var string
	 */
	private $original_stylesheet;

	/**
	 * Original global editor_styles before tests ran.
	 *
	 * @var array|null
	 */
	private $orig_editor_styles;

	/**
	 * Original theme features before tests ran.
	 *
	 * @var array
	 */
	private $orig_theme_features;

	/**
	 * List of temporary test files to remove in tear_down.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	public function set_up() {
		parent::set_up();

		$this->original_stylesheet = get_stylesheet();
		$this->orig_editor_styles  = $GLOBALS['editor_styles'] ?? null;
		$this->orig_theme_features = $GLOBALS['_wp_theme_features'] ?? array();
		$this->created_files       = array();

		// Start with an empty editor styles list and features.
		$GLOBALS['editor_styles']      = array();
		$GLOBALS['_wp_theme_features'] = array();
	}

	public function tear_down() {
		foreach ( $this->created_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		$GLOBALS['editor_styles']      = $this->orig_editor_styles;
		$GLOBALS['_wp_theme_features'] = $this->orig_theme_features;

		if ( get_stylesheet() !== $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
		}

		parent::tear_down();
	}

	/**
	 * Creates a temporary file inside a test theme directory.
	 *
	 * @param string $theme_slug    The theme directory name under themedir1.
	 * @param string $relative_path The relative path to create inside the theme.
	 * @param string $content       File contents.
	 * @return string The absolute path of the created file.
	 */
	private function create_theme_file( $theme_slug, $relative_path, $content ) {
		$file_path = DIR_TESTDATA . "/themedir1/{$theme_slug}/{$relative_path}";
		$dir       = dirname( $file_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		file_put_contents( $file_path, $content );
		$this->created_files[] = $file_path;
		return $file_path;
	}

	public function test_should_return_empty_array_when_theme_does_not_support_editor_styles() {
		$GLOBALS['editor_styles'] = array( 'style-editor.css' );

		$this->assertFalse( current_theme_supports( 'editor-styles' ) );
		$this->assertSame( array(), get_block_editor_theme_styles() );
	}

	public function test_should_return_empty_array_when_editor_styles_is_not_set() {
		add_theme_support( 'editor-styles' );
		$GLOBALS['editor_styles'] = null;

		$this->assertSame( array(), get_block_editor_theme_styles() );
	}

	public function test_should_return_empty_array_when_editor_styles_is_empty_array() {
		add_theme_support( 'editor-styles' );
		$GLOBALS['editor_styles'] = array();

		$this->assertSame( array(), get_block_editor_theme_styles() );
	}

	public function test_should_load_standalone_theme_editor_styles() {
		switch_theme( 'block-theme' );
		add_theme_support( 'editor-styles' );

		$css = 'body { font-size: 16px; }';
		$this->create_theme_file( 'block-theme', 'standalone-editor.css', $css );
		add_editor_style( 'standalone-editor.css' );

		$styles = get_block_editor_theme_styles();

		$expected = array(
			array(
				'css'            => $css,
				'baseURL'        => get_stylesheet_directory_uri() . '/standalone-editor.css',
				'__unstableType' => 'theme',
				'isGlobalStyles' => false,
			),
		);

		$this->assertSame( $expected, $styles );
	}

	/**
	 * Tests that both parent and child theme editor styles are loaded when both use the same file name.
	 *
	 * @ticket 64778
	 */
	public function test_should_load_parent_and_child_theme_editor_styles_when_using_same_file_name() {
		switch_theme( 'block-theme-child' );
		add_theme_support( 'editor-styles' );

		$parent_css = 'body { background-color: red; }';
		$child_css  = 'body { background-color: blue; }';

		$this->create_theme_file( 'block-theme', 'shared-editor-style.css', $parent_css );
		$this->create_theme_file( 'block-theme-child', 'shared-editor-style.css', $child_css );

		add_editor_style( 'shared-editor-style.css' );

		$styles = get_block_editor_theme_styles();

		$this->assertCount( 2, $styles, 'Should load both parent and child editor styles.' );

		// Parent stylesheet must be loaded first so child stylesheet overrides it.
		$this->assertSame(
			array(
				'css'            => $parent_css,
				'baseURL'        => get_template_directory_uri() . '/shared-editor-style.css',
				'__unstableType' => 'theme',
				'isGlobalStyles' => false,
			),
			$styles[0],
			'Parent stylesheet should be loaded first with parent baseURL.'
		);

		$this->assertSame(
			array(
				'css'            => $child_css,
				'baseURL'        => get_stylesheet_directory_uri() . '/shared-editor-style.css',
				'__unstableType' => 'theme',
				'isGlobalStyles' => false,
			),
			$styles[1],
			'Child stylesheet should be loaded second with child baseURL.'
		);
	}

	/**
	 * Tests that parent theme editor styles are loaded when not overridden by child theme.
	 *
	 * @ticket 64778
	 */
	public function test_should_load_parent_editor_style_when_not_in_child_theme() {
		switch_theme( 'block-theme-child' );
		add_theme_support( 'editor-styles' );

		$parent_css = 'h1 { color: red; }';
		$this->create_theme_file( 'block-theme', 'parent-only-style.css', $parent_css );

		add_editor_style( 'parent-only-style.css' );

		$styles = get_block_editor_theme_styles();

		$expected = array(
			array(
				'css'            => $parent_css,
				'baseURL'        => get_template_directory_uri() . '/parent-only-style.css',
				'__unstableType' => 'theme',
				'isGlobalStyles' => false,
			),
		);

		$this->assertSame( $expected, $styles );
	}

	/**
	 * Tests that child theme editor styles are loaded when only present in child theme.
	 *
	 * @ticket 64778
	 */
	public function test_should_load_child_editor_style_when_only_in_child_theme() {
		switch_theme( 'block-theme-child' );
		add_theme_support( 'editor-styles' );

		$child_css = 'h2 { color: blue; }';
		$this->create_theme_file( 'block-theme-child', 'child-only-style.css', $child_css );

		add_editor_style( 'child-only-style.css' );

		$styles = get_block_editor_theme_styles();

		$expected = array(
			array(
				'css'            => $child_css,
				'baseURL'        => get_stylesheet_directory_uri() . '/child-only-style.css',
				'__unstableType' => 'theme',
				'isGlobalStyles' => false,
			),
		);

		$this->assertSame( $expected, $styles );
	}

	public function test_should_ignore_non_existent_file() {
		switch_theme( 'block-theme-child' );
		add_theme_support( 'editor-styles' );

		add_editor_style( 'non-existent-editor-file.css' );

		$this->assertSame( array(), get_block_editor_theme_styles() );
	}

	public function test_should_handle_leading_slashes_in_style_path() {
		switch_theme( 'block-theme' );
		add_theme_support( 'editor-styles' );

		$css = 'p { margin: 0; }';
		$this->create_theme_file( 'block-theme', 'slash-editor.css', $css );

		add_editor_style( '/slash-editor.css' );

		$styles = get_block_editor_theme_styles();

		$expected = array(
			array(
				'css'            => $css,
				'baseURL'        => get_stylesheet_directory_uri() . '/slash-editor.css',
				'__unstableType' => 'theme',
				'isGlobalStyles' => false,
			),
		);

		$this->assertSame( $expected, $styles );
	}

	public function test_should_load_remote_editor_style() {
		add_theme_support( 'editor-styles' );

		$remote_url = 'https://example.com/remote-editor-style.css';
		$remote_css = 'a { text-decoration: none; }';

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) use ( $remote_url, $remote_css ) {
				if ( $url === $remote_url ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => $remote_css,
					);
				}
				return $response;
			},
			10,
			3
		);

		add_editor_style( $remote_url );

		$styles = get_block_editor_theme_styles();

		$expected = array(
			array(
				'css'            => $remote_css,
				'__unstableType' => 'theme',
				'isGlobalStyles' => false,
			),
		);

		$this->assertSame( $expected, $styles );
	}

	public function test_should_filter_empty_and_duplicate_styles() {
		switch_theme( 'block-theme' );
		add_theme_support( 'editor-styles' );

		$css = 'code { color: green; }';
		$this->create_theme_file( 'block-theme', 'dedupe.css', $css );

		$GLOBALS['editor_styles'] = array( 'dedupe.css', '', 'dedupe.css', null );

		$styles = get_block_editor_theme_styles();

		$this->assertCount( 1, $styles );
		$this->assertSame( $css, $styles[0]['css'] );
	}
}
