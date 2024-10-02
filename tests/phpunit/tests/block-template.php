<?php
/**
 * Tests for the block template loading algorithm.
 *
 * @package WordPress
 *
 * @group block-templates
 */
class Tests_Block_Template extends WP_UnitTestCase {
	private static $post;

	private static $template_canvas_path = ABSPATH . WPINC . '/template-canvas.php';

	public function set_up() {
		parent::set_up();
		switch_theme( 'block-theme' );
		do_action( 'setup_theme' );
		do_action( 'after_setup_theme' );
	}

	public function tear_down() {
		global $_wp_current_template_id, $_wp_current_template_content;
		unset( $_wp_current_template_id, $_wp_current_template_content );

		parent::tear_down();
	}

	public function test_page_home_block_template_takes_precedence_over_less_specific_block_templates() {
		global $_wp_current_template_content;
		$type                   = 'page';
		$templates              = array(
			'page-home.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path = locate_block_template( get_stylesheet_directory() . '/page-home.php', $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/page-home.html', $_wp_current_template_content );
	}

	public function test_page_block_template_takes_precedence() {
		global $_wp_current_template_content;
		$type                   = 'page';
		$templates              = array(
			'page-slug-doesnt-exist.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path = locate_block_template( get_stylesheet_directory() . '/page.php', $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/page.html', $_wp_current_template_content );
	}

	public function test_block_template_takes_precedence_over_equally_specific_php_template() {
		global $_wp_current_template_content;
		$type                   = 'index';
		$templates              = array(
			'index.php',
		);
		$resolved_template_path = locate_block_template( get_stylesheet_directory() . '/index.php', $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/index.html', $_wp_current_template_content );
	}

	/**
	 * In a hybrid theme, a PHP template of higher specificity will take precedence over a block template
	 * with lower specificity.
	 *
	 * Covers https://github.com/WordPress/gutenberg/pull/29026.
	 */
	public function test_more_specific_php_template_takes_precedence_over_less_specific_block_template() {
		$page_id_template       = 'page-1.php';
		$page_id_template_path  = get_stylesheet_directory() . '/' . $page_id_template;
		$type                   = 'page';
		$templates              = array(
			'page-slug-doesnt-exist.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path = locate_block_template( $page_id_template_path, $type, $templates );
		$this->assertSame( $page_id_template_path, $resolved_template_path );
	}

	/**
	 * If a theme is a child of a block-based parent theme but has php templates for some of its pages,
	 * a php template of the child will take precedence over the parent's block template if they have
	 * otherwise equal specificity.
	 *
	 * Covers https://github.com/WordPress/gutenberg/pull/31123.
	 * Covers https://core.trac.wordpress.org/ticket/54515.
	 *
	 */
	public function test_child_theme_php_template_takes_precedence_over_equally_specific_parent_theme_block_template() {
		switch_theme( 'block-theme-child' );

		$page_slug_template      = 'page-home.php';
		$page_slug_template_path = get_stylesheet_directory() . '/' . $page_slug_template;
		$type                    = 'page';
		$templates               = array(
			'page-home.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path  = locate_block_template( $page_slug_template_path, $type, $templates );
		$this->assertSame( $page_slug_template_path, $resolved_template_path );
	}

	public function test_child_theme_block_template_takes_precedence_over_equally_specific_parent_theme_php_template() {
		global $_wp_current_template_content;

		switch_theme( 'block-theme-child' );

		$page_template                   = 'page-1.php';
		$parent_theme_page_template_path = get_template_directory() . '/' . $page_template;
		$type                            = 'page';
		$templates                       = array(
			'page-slug-doesnt-exist.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path          = locate_block_template( $parent_theme_page_template_path, $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/page-1.html', $_wp_current_template_content );
	}

	/**
	 * Regression: https://github.com/WordPress/gutenberg/issues/31399.
	 */
	public function test_custom_page_php_template_takes_precedence_over_all_other_templates() {
		$custom_page_template      = 'templates/full-width.php';
		$custom_page_template_path = get_stylesheet_directory() . '/' . $custom_page_template;
		$type                      = 'page';
		$templates                 = array(
			$custom_page_template,
			'page-slug.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path    = locate_block_template( $custom_page_template_path, $type, $templates );
		$this->assertSame( $custom_page_template_path, $resolved_template_path );
	}

	/**
	 * Covers: https://github.com/WordPress/gutenberg/pull/30438.
	 */
	public function test_custom_page_block_template_takes_precedence_over_all_other_templates() {
		global $_wp_current_template_content;

		// Set up custom template post.
		$args = array(
			'post_type'    => 'wp_template',
			'post_name'    => 'wp-custom-template-my-block-template',
			'post_title'   => 'My Custom Block Template',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my block template',
			'tax_input'    => array(
				'wp_theme' => array(
					get_stylesheet(),
				),
			),
		);
		$post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( $post->ID, get_stylesheet(), 'wp_theme' );

		$custom_page_block_template = 'wp-custom-template-my-block-template';
		$page_template_path         = get_stylesheet_directory() . '/' . 'page.php';
		$type                       = 'page';
		$templates                  = array(
			$custom_page_block_template,
			'page-slug.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path     = locate_block_template( $page_template_path, $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertSame( $post->post_content, $_wp_current_template_content );

		wp_delete_post( $post->ID );
	}

	/**
	 * Regression: https://github.com/WordPress/gutenberg/issues/31652.
	 */
	public function test_template_remains_unchanged_if_templates_array_is_empty() {
		$resolved_template_path = locate_block_template( '', 'search', array() );
		$this->assertSame( '', $resolved_template_path );
	}

	/**
	 * Tests that `get_the_block_template_html()` wraps block parsing into the query loop when on a singular template.
	 *
	 * This is necessary since block themes do not include the necessary blocks to trigger the main query loop, and
	 * since there is only a single post in the main query loop in such cases anyway.
	 *
	 * @ticket 58154
	 * @ticket 59736
	 * @covers ::get_the_block_template_html
	 */
	public function test_get_the_block_template_html_enforces_singular_query_loop() {
		global $_wp_current_template_id, $_wp_current_template_content, $wp_query, $wp_the_query;

		// Register test block to log `in_the_loop()` results.
		$in_the_loop_logs = array();
		$this->register_in_the_loop_logger_block( $in_the_loop_logs );

		// Set main query to single post.
		$post_id      = self::factory()->post->create( array( 'post_title' => 'A single post' ) );
		$wp_query     = new WP_Query( array( 'p' => $post_id ) );
		$wp_the_query = $wp_query;

		// Force a template ID that is for the current stylesheet.
		$_wp_current_template_id = get_stylesheet() . '//single';
		// Use block template that just renders post title and the above test block.
		$_wp_current_template_content = '<!-- wp:post-title /--><!-- wp:test/in-the-loop-logger /-->';

		$expected  = '<div class="wp-site-blocks">';
		$expected .= '<h2 class="wp-block-post-title">A single post</h2>';
		$expected .= '</div>';

		$output = get_the_block_template_html();
		$this->unregister_in_the_loop_logger_block();
		$this->assertSame( $expected, $output, 'Unexpected block template output' );
		$this->assertSame( array( true ), $in_the_loop_logs, 'Main query loop was not triggered' );
	}

	/**
	 * Tests that `get_the_block_template_html()` does not start the main query loop generally.
	 *
	 * @ticket 58154
	 * @covers ::get_the_block_template_html
	 */
	public function test_get_the_block_template_html_does_not_generally_enforce_loop() {
		global $_wp_current_template_id, $_wp_current_template_content, $wp_query, $wp_the_query;

		// Register test block to log `in_the_loop()` results.
		$in_the_loop_logs = array();
		$this->register_in_the_loop_logger_block( $in_the_loop_logs );

		// Set main query to a general post query (i.e. not for a specific post).
		$post_id      = self::factory()->post->create(
			array(
				'post_title'   => 'A single post',
				'post_content' => 'The content.',
			)
		);
		$wp_query     = new WP_Query(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$wp_the_query = $wp_query;

		// Force a template ID that is for the current stylesheet.
		$_wp_current_template_id = get_stylesheet() . '//home';

		/*
		 * Use block template that renders the above test block, followed by a main query loop.
		 * `get_the_block_template_html()` should not start the loop, but the `core/query` and `core/post-template`
		 * blocks should.
		 */
		$_wp_current_template_content  = '<!-- wp:test/in-the-loop-logger /-->';
		$_wp_current_template_content .= '<!-- wp:query {"query":{"inherit":true}} -->';
		$_wp_current_template_content .= '<!-- wp:post-template -->';
		$_wp_current_template_content .= '<!-- wp:post-title /-->';
		$_wp_current_template_content .= '<!-- wp:post-content /--><!-- wp:test/in-the-loop-logger /-->';
		$_wp_current_template_content .= '<!-- /wp:post-template -->';
		$_wp_current_template_content .= '<!-- /wp:query -->';

		$expected  = '<div class="wp-site-blocks">';
		$expected .= '<ul class="wp-block-post-template is-layout-flow wp-block-post-template-is-layout-flow wp-block-query-is-layout-flow">';
		$expected .= '<li class="wp-block-post post-' . $post_id . ' post type-post status-publish format-standard hentry category-uncategorized">';
		$expected .= '<h2 class="wp-block-post-title">A single post</h2>';
		$expected .= '<div class="entry-content wp-block-post-content is-layout-flow wp-block-post-content-is-layout-flow">' . wpautop( 'The content.' ) . '</div>';
		$expected .= '</li>';
		$expected .= '</ul>';
		$expected .= '</div>';

		$output = get_the_block_template_html();
		$this->unregister_in_the_loop_logger_block();
		$this->assertSame( $expected, $output, 'Unexpected block template output' );
		$this->assertSame( array( false, true ), $in_the_loop_logs, 'Main query loop was triggered incorrectly' );
	}

	/**
	 * Tests that `get_the_block_template_html()` does not start the main query loop when on a template that is not from the current theme.
	 *
	 * @ticket 58154
	 * @ticket 59736
	 * @covers ::get_the_block_template_html
	 */
	public function test_get_the_block_template_html_skips_singular_query_loop_when_non_theme_template() {
		global $_wp_current_template_id, $_wp_current_template_content, $wp_query, $wp_the_query;

		// Register test block to log `in_the_loop()` results.
		$in_the_loop_logs = array();
		$this->register_in_the_loop_logger_block( $in_the_loop_logs );

		// Set main query to single post.
		$post_id      = self::factory()->post->create( array( 'post_title' => 'A single post' ) );
		$wp_query     = new WP_Query( array( 'p' => $post_id ) );
		$wp_the_query = $wp_query;

		// Force a template ID that is not for the current stylesheet.
		$_wp_current_template_id = 'some-plugin-slug//single';
		// Use block template that just renders post title and the above test block.
		$_wp_current_template_content = '<!-- wp:post-title /--><!-- wp:test/in-the-loop-logger /-->';

		$output = get_the_block_template_html();
		$this->unregister_in_the_loop_logger_block();
		$this->assertSame( array( false ), $in_the_loop_logs, 'Main query loop was triggered despite a custom block template outside the current theme being used' );
	}

	/**
	 * @ticket 58319
	 *
	 * @covers ::get_block_theme_folders
	 *
	 * @dataProvider data_get_block_theme_folders
	 *
	 * @param string   $theme    The theme's stylesheet.
	 * @param string[] $expected The expected associative array of block theme folders.
	 */
	public function test_get_block_theme_folders( $theme, $expected ) {
		$wp_theme = wp_get_theme( $theme );
		$wp_theme->cache_delete(); // Clear cache.

		$this->assertSame( $expected, get_block_theme_folders( $theme ), 'Incorrect block theme folders were retrieved.' );
		$reflection = new ReflectionMethod( $wp_theme, 'cache_get' );
		$reflection->setAccessible( true );
		$theme_cache  = $reflection->invoke( $wp_theme, 'theme' );
		$cached_value = $theme_cache['block_template_folders'];
		$reflection->setAccessible( false );

		$this->assertSame( $expected, $cached_value, 'The cached value is incorrect.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_block_theme_folders() {
		return array(
			'block-theme'                       => array(
				'block-theme',
				array(
					'wp_template'      => 'templates',
					'wp_template_part' => 'parts',
				),
			),
			'block-theme-deprecated-path'       => array(
				'block-theme-deprecated-path',
				array(
					'wp_template'      => 'block-templates',
					'wp_template_part' => 'block-template-parts',
				),
			),
			'block-theme-child'                 => array(
				'block-theme-child',
				array(
					'wp_template'      => 'templates',
					'wp_template_part' => 'parts',
				),
			),
			'block-theme-child-deprecated-path' => array(
				'block-theme-child-deprecated-path',
				array(
					'wp_template'      => 'block-templates',
					'wp_template_part' => 'block-template-parts',
				),
			),
			'this-is-an-invalid-theme'          => array(
				'this-is-an-invalid-theme',
				array(
					'wp_template'      => 'templates',
					'wp_template_part' => 'parts',
				),
			),
			'null'                              => array(
				null,
				array(
					'wp_template'      => 'templates',
					'wp_template_part' => 'parts',
				),
			),
		);
	}

	/**
	 * Tests `_get_block_templates_paths()` for an invalid directory.
	 *
	 * @ticket 58196
	 *
	 * @covers ::_get_block_templates_paths
	 */
	public function test_get_block_templates_paths_dir_exists() {
		$theme_dir = $this->normalizeDirectorySeparatorsInPath( get_template_directory() );
		// Templates in the current theme.
		$templates = array(
			'parts/small-header.html',
			'templates/custom-hero-template.html',
			'templates/custom-single-post-template.html',
			'templates/index.html',
			'templates/page-home.html',
			'templates/page.html',
			'templates/single.html',
		);

		$expected_template_paths = array_map(
			static function ( $template ) use ( $theme_dir ) {
				return $theme_dir . '/' . $template;
			},
			$templates
		);

		$template_paths = _get_block_templates_paths( $theme_dir );
		$template_paths = array_map( array( $this, 'normalizeDirectorySeparatorsInPath' ), _get_block_templates_paths( $theme_dir ) );

		$this->assertSameSets( $expected_template_paths, $template_paths );
	}

	/**
	 * Test _get_block_templates_paths() for a invalid dir.
	 *
	 * @ticket 58196
	 *
	 * @covers ::_get_block_templates_paths
	 */
	public function test_get_block_templates_paths_dir_doesnt_exists() {
		// Should return empty array for invalid path.
		$template_paths = _get_block_templates_paths( '/tmp/random-invalid-theme-path' );
		$this->assertSame( array(), $template_paths );
	}

	/**
	 * Tests that get_block_templates() returns plugin-registered templates.
	 *
	 * @ticket 61804
	 *
	 * @covers ::get_block_templates
	 */
	public function test_get_block_templates_from_registry() {
		$template_name = 'test-plugin//test-template';

		wp_register_block_template( $template_name );

		$templates = get_block_templates();

		$this->assertArrayHasKey( $template_name, $templates );

		wp_unregister_block_template( $template_name );
	}

	/**
	 * Tests that get_block_template() returns plugin-registered templates.
	 *
	 * @ticket 61804
	 *
	 * @covers ::get_block_template
	 */
	public function test_get_block_template_from_registry() {
		$template_name = 'test-plugin//test-template';
		$args          = array(
			'title' => 'Test Template',
		);

		wp_register_block_template( $template_name, $args );

		$template = get_block_template( 'block-theme//test-template' );

		$this->assertSame( 'Test Template', $template->title );

		wp_unregister_block_template( $template_name );
	}

	/**
	 * Registers a test block to log `in_the_loop()` results.
	 *
	 * @param array $in_the_loop_logs Array to log function results in. Passed by reference.
	 */
	private function register_in_the_loop_logger_block( array &$in_the_loop_logs ) {
		register_block_type(
			'test/in-the-loop-logger',
			array(
				'render_callback' => function () use ( &$in_the_loop_logs ) {
					$in_the_loop_logs[] = in_the_loop();
					return '';
				},
			)
		);
	}

	/**
	 * Unregisters the test block registered by the `register_in_the_loop_logger_block()` method.
	 */
	private function unregister_in_the_loop_logger_block() {
		unregister_block_type( 'test/in-the-loop-logger' );
	}
}
