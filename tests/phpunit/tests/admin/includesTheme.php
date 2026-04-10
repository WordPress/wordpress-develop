<?php
/**
 * @group admin
 * @group themes
 */
class Tests_Admin_IncludesTheme extends WP_UnitTestCase {

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

		$this->orig_theme_dir            = $GLOBALS['wp_theme_directories'];
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
		remove_filter( 'theme_root', array( $this, 'filter_theme_root' ) );
		remove_filter( 'stylesheet_root', array( $this, 'filter_theme_root' ) );
		remove_filter( 'template_root', array( $this, 'filter_theme_root' ) );

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tear_down();
	}

	// Replace the normal theme root directory with our premade test directory.
	public function filter_theme_root( $dir ) {
		return self::THEME_ROOT;
	}

	/**
	 * @ticket 10959
	 * @ticket 11216
	 * @expectedDeprecated get_theme
	 * @expectedDeprecated get_themes
	 */
	public function test_page_templates() {
		$theme = get_theme( 'Page Template Theme' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$this->assertSameSetsWithIndex(
			array(
				'Top Level'                           => 'template-top-level.php',
				'Sub Dir'                             => 'subdir/template-sub-dir.php',
				'This Template Header Is On One Line' => 'template-header.php',
			),
			get_page_templates()
		);

		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$this->assertSameSetsWithIndex(
			array(
				'Top Level'                           => 'template-top-level.php',
				'Sub Dir'                             => 'subdir/template-sub-dir.php',
				'This Template Header Is On One Line' => 'template-header.php',
			),
			get_page_templates()
		);
	}

	/**
	 * @ticket 18375
	 */
	public function test_page_templates_different_post_types() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$this->assertSameSetsWithIndex(
			array(
				'Top Level' => 'template-top-level-post-types.php',
				'Sub Dir'   => 'subdir/template-sub-dir-post-types.php',
			),
			get_page_templates( null, 'foo' )
		);
		$this->assertSameSetsWithIndex(
			array(
				'Top Level' => 'template-top-level-post-types.php',
				'Sub Dir'   => 'subdir/template-sub-dir-post-types.php',
			),
			get_page_templates( null, 'post' )
		);
		$this->assertSame( array(), get_page_templates( null, 'bar' ) );
	}

	/**
	 * @ticket 38766
	 */
	public function test_page_templates_for_post_types_with_trailing_periods() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$this->assertSameSetsWithIndex(
			array(
				'No Trailing Period'            => '38766/no-trailing-period-post-types.php',
				'Trailing Period.'              => '38766/trailing-period-post-types.php',
				'Trailing Comma,'               => '38766/trailing-comma-post-types.php',
				'Trailing Period, White Space.' => '38766/trailing-period-whitespace-post-types.php',
				'Trailing White Space, Period.' => '38766/trailing-whitespace-period-post-types.php',
				'Tilde in Post Type.'           => '38766/tilde-post-types.php',
			),
			get_page_templates( null, 'period' )
		);
		$this->assertSameSetsWithIndex(
			array(
				'No Trailing Period'            => '38766/no-trailing-period-post-types.php',
				'Trailing Period.'              => '38766/trailing-period-post-types.php',
				'Trailing Comma,'               => '38766/trailing-comma-post-types.php',
				'Trailing Period, White Space.' => '38766/trailing-period-whitespace-post-types.php',
				'Trailing White Space, Period.' => '38766/trailing-whitespace-period-post-types.php',
			),
			get_page_templates( null, 'full-stop' )
		);
	}

	/**
	 * @ticket 38696
	 */
	public function test_page_templates_child_theme() {
		$theme = wp_get_theme( 'page-templates-child' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$this->assertSameSetsWithIndex(
			array(
				'Top Level'                  => 'template-top-level-post-types.php',
				'Sub Dir'                    => 'subdir/template-sub-dir-post-types.php',
				'Top Level In A Child Theme' => 'template-top-level-post-types-child.php',
				'Sub Dir In A Child Theme'   => 'subdir/template-sub-dir-post-types-child.php',
			),
			get_page_templates( null, 'foo' )
		);

		$this->assertSameSetsWithIndex(
			array(
				'Top Level' => 'template-top-level-post-types.php',
				'Sub Dir'   => 'subdir/template-sub-dir-post-types.php',
			),
			get_page_templates( null, 'post' )
		);

		$this->assertSameSetsWithIndex(
			array(
				'Top Level'                           => 'template-top-level.php',
				'Sub Dir'                             => 'subdir/template-sub-dir.php',
				'This Template Header Is On One Line' => 'template-header.php',
			),
			get_page_templates()
		);

		$this->assertSame( array(), get_page_templates( null, 'bar' ) );
	}

	/**
	 * @ticket 41717
	 */
	public function test_get_post_templates_child_theme() {
		$theme = wp_get_theme( 'page-templates-child' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$post_templates = $theme->get_post_templates();

		$this->assertSameSetsWithIndex(
			array(
				'template-top-level-post-types.php'       => 'Top Level',
				'subdir/template-sub-dir-post-types.php'  => 'Sub Dir',
				'template-top-level-post-types-child.php' => 'Top Level In A Child Theme',
				'subdir/template-sub-dir-post-types-child.php' => 'Sub Dir In A Child Theme',
			),
			$post_templates['foo']
		);

		$this->assertSameSetsWithIndex(
			array(
				'template-top-level-post-types.php'      => 'Top Level',
				'subdir/template-sub-dir-post-types.php' => 'Sub Dir',
			),
			$post_templates['post']
		);

		$this->assertSameSetsWithIndex(
			array(
				'template-top-level.php'      => 'Top Level',
				'subdir/template-sub-dir.php' => 'Sub Dir',
				'template-header.php'         => 'This Template Header Is On One Line',
			),
			$post_templates['page']
		);
	}

	/**
	 * @ticket 42513
	 *
	 * @covers WP_Theme::get_post_templates
	 */
	public function test_get_post_templates_caches_results() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		// First call populates the cache.
		$first_result = $theme->get_post_templates();
		$this->assertNotEmpty( $first_result );

		// Second call should return the same result from cache.
		$second_result = $theme->get_post_templates();
		$this->assertSame( $first_result, $second_result );
	}

	/**
	 * @ticket 42513
	 *
	 * @covers WP_Theme::get_post_templates
	 */
	public function test_get_post_templates_uses_get_file_data() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$post_templates = $theme->get_post_templates();

		// Verify single-line header format is parsed correctly via get_file_data().
		$this->assertArrayHasKey( 'page', $post_templates );
		$this->assertArrayHasKey( 'template-header.php', $post_templates['page'] );
		$this->assertSame( 'This Template Header Is On One Line', $post_templates['page']['template-header.php'] );
	}

	/**
	 * @ticket 42513
	 *
	 * @covers WP_Theme::get_post_templates
	 */
	public function test_get_post_templates_cache_cleared_on_switch() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		// Populate cache.
		$theme->get_post_templates();

		// Clearing theme cache should require a fresh scan on next call.
		wp_clean_themes_cache();

		$child_theme = wp_get_theme( 'page-templates-child' );
		switch_theme( $child_theme['Template'], $child_theme['Stylesheet'] );

		$child_templates = $child_theme->get_post_templates();

		// Child theme should include its own templates.
		$this->assertArrayHasKey( 'foo', $child_templates );
		$this->assertArrayHasKey( 'template-top-level-post-types-child.php', $child_templates['foo'] );
	}

	/**
	 * Test that the list of theme features pulled from the WordPress.org API returns the expected data structure.
	 *
	 * Differences in the structure can also trigger failure by causing PHP notices/warnings.
	 *
	 * @group external-http
	 * @ticket 28121
	 */
	public function test_get_theme_featured_list_api() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$featured_list_api = get_theme_feature_list( true );
		$this->assertNonEmptyMultidimensionalArray( $featured_list_api );
	}

	/**
	 * Test that the list of theme features hardcoded into Core returns the expected data structure.
	 *
	 * Differences in the structure can also trigger failure by causing PHP notices/warnings.
	 *
	 * @ticket 28121
	 */
	public function test_get_theme_featured_list_hardcoded() {
		$featured_list_hardcoded = get_theme_feature_list( false );
		$this->assertNonEmptyMultidimensionalArray( $featured_list_hardcoded );
	}
}
