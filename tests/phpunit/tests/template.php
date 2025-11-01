<?php
/**
 * test wp-includes/template.php
 *
 * @group themes
 */
class Tests_Template extends WP_UnitTestCase {

	protected $hierarchy = array();

	protected static $page_on_front;
	protected static $page_for_posts;
	protected static $page;
	protected static $post;

	/**
	 * Page For Privacy Policy.
	 *
	 * @since 5.2.0
	 *
	 * @var WP_Post $page_for_privacy_policy
	 */
	protected static $page_for_privacy_policy;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$page_on_front = $factory->post->create_and_get(
			array(
				'post_type' => 'page',
				'post_name' => 'page-on-front-😀',
			)
		);

		self::$page_for_posts = $factory->post->create_and_get(
			array(
				'post_type' => 'page',
				'post_name' => 'page-for-posts-😀',
			)
		);

		self::$page = $factory->post->create_and_get(
			array(
				'post_type' => 'page',
				'post_name' => 'page-name-😀',
			)
		);
		add_post_meta( self::$page->ID, '_wp_page_template', 'templates/page.php' );

		self::$post = $factory->post->create_and_get(
			array(
				'post_type' => 'post',
				'post_name' => 'post-name-😀',
				'post_date' => '1984-02-25 12:34:56',
			)
		);
		set_post_format( self::$post, 'quote' );
		add_post_meta( self::$post->ID, '_wp_page_template', 'templates/post.php' );

		self::$page_for_privacy_policy = $factory->post->create_and_get(
			array(
				'post_type'  => 'page',
				'post_title' => 'Privacy Policy',
			)
		);
	}

	/**
	 * @var string
	 */
	protected $original_default_mimetype;

	/**
	 * @var WP_Scripts|null
	 */
	protected $original_wp_scripts;

	/**
	 * @var WP_Styles|null
	 */
	protected $original_wp_styles;

	/**
	 * @var array|null
	 */
	protected $original_theme_features;

	public function set_up() {
		parent::set_up();
		$this->original_default_mimetype = ini_get( 'default_mimetype' );
		register_post_type(
			'cpt',
			array(
				'public' => true,
			)
		);
		register_taxonomy(
			'taxo',
			'post',
			array(
				'public'       => true,
				'hierarchical' => true,
			)
		);
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		// Remove hooks which are added by wp_load_classic_theme_block_styles_on_demand() during bootstrapping.
		remove_filter( 'wp_should_output_buffer_template_for_enhancement', '__return_true', 0 );
		remove_filter( 'should_load_separate_core_block_assets', '__return_true', 0 );
		remove_filter( 'should_load_block_assets_on_demand', '__return_true', 0 );
		remove_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' );

		global $wp_scripts, $wp_styles;
		$this->original_wp_scripts = $wp_scripts;
		$this->original_wp_styles  = $wp_styles;
		$wp_scripts                = null;
		$wp_styles                 = null;
		wp_scripts();
		wp_styles();

		$this->original_theme_features = $GLOBALS['_wp_theme_features'];
	}

	public function tear_down() {
		global $wp_scripts, $wp_styles;
		$wp_scripts = $this->original_wp_scripts;
		$wp_styles  = $this->original_wp_styles;

		$GLOBALS['_wp_theme_features'] = $this->original_theme_features;

		ini_set( 'default_mimetype', $this->original_default_mimetype );
		unregister_post_type( 'cpt' );
		unregister_taxonomy( 'taxo' );
		$this->set_permalink_structure( '' );
		parent::tear_down();
	}


	public function test_404_template_hierarchy() {
		$url = add_query_arg(
			array(
				'p' => '-1',
			),
			home_url()
		);

		$this->assertTemplateHierarchy(
			$url,
			array(
				'404.php',
			)
		);
	}

	public function test_author_template_hierarchy() {
		$author = self::factory()->user->create_and_get(
			array(
				'user_nicename' => 'foo',
			)
		);

		$this->assertTemplateHierarchy(
			get_author_posts_url( $author->ID ),
			array(
				'author-foo.php',
				"author-{$author->ID}.php",
				'author.php',
				'archive.php',
			)
		);
	}

	public function test_category_template_hierarchy() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_term_link( $term ),
			array(
				'category-foo-😀.php',
				'category-foo-%f0%9f%98%80.php',
				"category-{$term->term_id}.php",
				'category.php',
				'archive.php',
			)
		);
	}

	public function test_tag_template_hierarchy() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_term_link( $term ),
			array(
				'tag-foo-😀.php',
				'tag-foo-%f0%9f%98%80.php',
				"tag-{$term->term_id}.php",
				'tag.php',
				'archive.php',
			)
		);
	}

	public function test_taxonomy_template_hierarchy() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'taxo',
				'slug'     => 'foo-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_term_link( $term ),
			array(
				'taxonomy-taxo-foo-😀.php',
				'taxonomy-taxo-foo-%f0%9f%98%80.php',
				"taxonomy-taxo-{$term->term_id}.php",
				'taxonomy-taxo.php',
				'taxonomy.php',
				'archive.php',
			)
		);
	}

	public function test_date_template_hierarchy_for_year() {
		$this->assertTemplateHierarchy(
			get_year_link( 1984 ),
			array(
				'date.php',
				'archive.php',
			)
		);
	}

	public function test_date_template_hierarchy_for_month() {
		$this->assertTemplateHierarchy(
			get_month_link( 1984, 2 ),
			array(
				'date.php',
				'archive.php',
			)
		);
	}

	public function test_date_template_hierarchy_for_day() {
		$this->assertTemplateHierarchy(
			get_day_link( 1984, 2, 25 ),
			array(
				'date.php',
				'archive.php',
			)
		);
	}

	public function test_search_template_hierarchy() {
		$url = add_query_arg(
			array(
				's' => 'foo',
			),
			home_url()
		);

		$this->assertTemplateHierarchy(
			$url,
			array(
				'search.php',
			)
		);
	}

	public function test_front_page_template_hierarchy_with_posts_on_front() {
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertTemplateHierarchy(
			home_url(),
			array(
				'front-page.php',
				'home.php',
				'index.php',
			)
		);
	}

	public function test_front_page_template_hierarchy_with_page_on_front() {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$page_on_front->ID );
		update_option( 'page_for_posts', self::$page_for_posts->ID );

		$this->assertTemplateHierarchy(
			home_url(),
			array(
				'front-page.php',
				'page-page-on-front-😀.php',
				'page-page-on-front-%f0%9f%98%80.php',
				'page-' . self::$page_on_front->ID . '.php',
				'page.php',
				'singular.php',
			)
		);
	}

	public function test_home_template_hierarchy_with_page_on_front() {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$page_on_front->ID );
		update_option( 'page_for_posts', self::$page_for_posts->ID );

		$this->assertTemplateHierarchy(
			get_permalink( self::$page_for_posts ),
			array(
				'home.php',
				'index.php',
			)
		);
	}

	public function test_page_template_hierarchy() {
		$this->assertTemplateHierarchy(
			get_permalink( self::$page ),
			array(
				'templates/page.php',
				'page-page-name-😀.php',
				'page-page-name-%f0%9f%98%80.php',
				'page-' . self::$page->ID . '.php',
				'page.php',
				'singular.php',
			)
		);
	}

	/**
	 * @ticket 44005
	 * @group privacy
	 */
	public function test_privacy_template_hierarchy() {
		update_option( 'wp_page_for_privacy_policy', self::$page_for_privacy_policy->ID );

		$this->assertTemplateHierarchy(
			get_permalink( self::$page_for_privacy_policy->ID ),
			array(
				'privacy-policy.php',
				'page-privacy-policy.php',
				'page-' . self::$page_for_privacy_policy->ID . '.php',
				'page.php',
				'singular.php',
			)
		);
	}

	/**
	 * @ticket 18375
	 */
	public function test_single_template_hierarchy_for_post() {
		$this->assertTemplateHierarchy(
			get_permalink( self::$post ),
			array(
				'templates/post.php',
				'single-post-post-name-😀.php',
				'single-post-post-name-%f0%9f%98%80.php',
				'single-post.php',
				'single.php',
				'singular.php',
			)
		);
	}

	public function test_single_template_hierarchy_for_custom_post_type() {
		$cpt = self::factory()->post->create_and_get(
			array(
				'post_type' => 'cpt',
				'post_name' => 'cpt-name-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_permalink( $cpt ),
			array(
				'single-cpt-cpt-name-😀.php',
				'single-cpt-cpt-name-%f0%9f%98%80.php',
				'single-cpt.php',
				'single.php',
				'singular.php',
			)
		);
	}

	/**
	 * @ticket 18375
	 */
	public function test_single_template_hierarchy_for_custom_post_type_with_template() {
		$cpt = self::factory()->post->create_and_get(
			array(
				'post_type' => 'cpt',
				'post_name' => 'cpt-name-😀',
			)
		);
		add_post_meta( $cpt->ID, '_wp_page_template', 'templates/cpt.php' );

		$this->assertTemplateHierarchy(
			get_permalink( $cpt ),
			array(
				'templates/cpt.php',
				'single-cpt-cpt-name-😀.php',
				'single-cpt-cpt-name-%f0%9f%98%80.php',
				'single-cpt.php',
				'single.php',
				'singular.php',
			)
		);
	}

	public function test_attachment_template_hierarchy() {
		$attachment = self::factory()->attachment->create_and_get(
			array(
				'post_name'      => 'attachment-name-😀',
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->assertTemplateHierarchy(
			get_permalink( $attachment ),
			array(
				'image-jpeg.php',
				'jpeg.php',
				'image.php',
				'attachment.php',
				'single-attachment-attachment-name-😀.php',
				'single-attachment-attachment-name-%f0%9f%98%80.php',
				'single-attachment.php',
				'single.php',
				'singular.php',
			)
		);
	}

	/**
	 * @ticket 18375
	 */
	public function test_attachment_template_hierarchy_with_template() {
		$attachment = self::factory()->attachment->create_and_get(
			array(
				'post_name'      => 'attachment-name-😀',
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		add_post_meta( $attachment, '_wp_page_template', 'templates/cpt.php' );

		$this->assertTemplateHierarchy(
			get_permalink( $attachment ),
			array(
				'image-jpeg.php',
				'jpeg.php',
				'image.php',
				'attachment.php',
				'single-attachment-attachment-name-😀.php',
				'single-attachment-attachment-name-%f0%9f%98%80.php',
				'single-attachment.php',
				'single.php',
				'singular.php',
			)
		);
	}

	public function test_embed_template_hierarchy_for_post() {
		$this->assertTemplateHierarchy(
			get_post_embed_url( self::$post ),
			array(
				'embed-post-quote.php',
				'embed-post.php',
				'embed.php',
				'templates/post.php',
				'single-post-post-name-😀.php',
				'single-post-post-name-%f0%9f%98%80.php',
				'single-post.php',
				'single.php',
				'singular.php',
			)
		);
	}

	public function test_embed_template_hierarchy_for_page() {
		$this->assertTemplateHierarchy(
			get_post_embed_url( self::$page ),
			array(
				'embed-page.php',
				'embed.php',
				'templates/page.php',
				'page-page-name-😀.php',
				'page-page-name-%f0%9f%98%80.php',
				'page-' . self::$page->ID . '.php',
				'page.php',
				'singular.php',
			)
		);
	}

	/**
	 * Tests that `locate_template()` uses the current theme even after switching the theme.
	 *
	 * @ticket 18298
	 *
	 * @covers ::locate_template
	 */
	public function test_locate_template_uses_current_theme() {
		$themes = wp_get_themes();

		// Look for parent themes with an index.php template.
		$relevant_themes = array();
		foreach ( $themes as $theme ) {
			if ( $theme->get_stylesheet() !== $theme->get_template() ) {
				continue;
			}
			$php_templates = $theme['Template Files'];
			if ( ! isset( $php_templates['index.php'] ) ) {
				continue;
			}
			$relevant_themes[] = $theme;
		}
		if ( count( $relevant_themes ) < 2 ) {
			$this->markTestSkipped( 'Test requires at least two parent themes with an index.php template.' );
		}

		$template_names = array( 'index.php' );

		$old_theme = $relevant_themes[0];
		$new_theme = $relevant_themes[1];

		switch_theme( $old_theme->get_stylesheet() );
		$this->assertSame( $old_theme->get_stylesheet_directory() . '/index.php', locate_template( $template_names ), 'Incorrect index template found in initial theme.' );

		switch_theme( $new_theme->get_stylesheet() );
		$this->assertSame( $new_theme->get_stylesheet_directory() . '/index.php', locate_template( $template_names ), 'Incorrect index template found in theme after switch.' );
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() does not start a buffer in a block theme when no filters are present.
	 *
	 * @ticket 43258
	 * @ticket 64099
	 *
	 * @covers ::wp_should_output_buffer_template_for_enhancement
	 * @covers ::wp_start_template_enhancement_output_buffer
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 */
	public function test_wp_start_template_enhancement_output_buffer_without_filters_and_no_override_in_block_theme(): void {
		switch_theme( 'block-theme' );
		wp_load_classic_theme_block_styles_on_demand();

		$level = ob_get_level();
		$this->assertFalse( wp_should_output_buffer_template_for_enhancement(), 'Expected wp_should_output_buffer_template_for_enhancement() to return false when there are no wp_template_enhancement_output_buffer filters added.' );
		$this->assertFalse( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return false because the output buffer should not be started.' );
		$this->assertSame( 0, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to not have fired.' );
		$this->assertSame( $level, ob_get_level(), 'Expected the initial output buffer level to be unchanged.' );
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() does start a buffer in classic theme.
	 *
	 * @ticket 43258
	 * @ticket 64099
	 *
	 * @covers ::wp_should_output_buffer_template_for_enhancement
	 * @covers ::wp_start_template_enhancement_output_buffer
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 */
	public function test_wp_start_template_enhancement_output_buffer_in_classic_theme(): void {
		switch_theme( 'default' );
		wp_load_classic_theme_block_styles_on_demand();

		$level = ob_get_level();
		$this->assertTrue( wp_should_output_buffer_template_for_enhancement(), 'Expected wp_should_output_buffer_template_for_enhancement() to return true because wp_load_classic_theme_block_styles_on_demand() adds wp_template_enhancement_output_buffer filters.' );
		$this->assertTrue( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return true because the output buffer should be started.' );
		$this->assertSame( 1, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to have fired.' );
		$this->assertSame( $level + 1, ob_get_level(), 'Expected the initial output buffer level to be incremented by one.' );
		ob_end_clean();
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() does start a buffer when no filters are present but there is an override.
	 *
	 * @ticket 43258
	 * @covers ::wp_should_output_buffer_template_for_enhancement
	 * @covers ::wp_start_template_enhancement_output_buffer
	 */
	public function test_wp_start_template_enhancement_output_buffer_begins_without_filters_but_overridden(): void {
		$level = ob_get_level();
		add_filter( 'wp_should_output_buffer_template_for_enhancement', '__return_true' );
		$this->assertTrue( wp_should_output_buffer_template_for_enhancement(), 'Expected wp_should_output_buffer_template_for_enhancement() to return true when overridden with the wp_should_output_buffer_template_for_enhancement filter.' );
		$this->assertTrue( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return true because the output buffer should be started due to the override.' );
		$this->assertSame( 1, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to have fired.' );
		$this->assertSame( $level + 1, ob_get_level(), 'Expected the output buffer level to have been incremented.' );
		ob_end_clean();
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() does not start a buffer even when there are filters present due to override.
	 *
	 * @ticket 43258
	 *
	 * @covers ::wp_should_output_buffer_template_for_enhancement
	 * @covers ::wp_start_template_enhancement_output_buffer
	 */
	public function test_wp_start_template_enhancement_output_buffer_begins_with_filters_but_blocked(): void {
		add_filter(
			'wp_template_enhancement_output_buffer',
			static function () {
				return '<html>Hey!</html>';
			}
		);
		$level = ob_get_level();
		add_filter( 'wp_should_output_buffer_template_for_enhancement', '__return_false' );
		$this->assertFalse( wp_should_output_buffer_template_for_enhancement(), 'Expected wp_should_output_buffer_template_for_enhancement() to return false since wp_should_output_buffer_template_for_enhancement was filtered to be false even though there is a wp_template_enhancement_output_buffer filter added.' );
		$this->assertFalse( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return false because the output buffer should not be started.' );
		$this->assertSame( 0, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to not have fired.' );
		$this->assertSame( $level, ob_get_level(), 'Expected the initial output buffer level to be unchanged.' );
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() starts the expected output buffer and that the expected hooks fire for
	 * an HTML document and that the response is not incrementally flushable.
	 *
	 * @ticket 43258
	 * @ticket 64126
	 *
	 * @covers ::wp_start_template_enhancement_output_buffer
	 * @covers ::wp_finalize_template_enhancement_output_buffer
	 */
	public function test_wp_start_template_enhancement_output_buffer_for_html(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$mock_filter_callback = new MockAction();
		add_filter(
			'wp_template_enhancement_output_buffer',
			array( $mock_filter_callback, 'filter' ),
			10,
			PHP_INT_MAX
		);

		$mock_action_callback = new MockAction();
		add_filter(
			'wp_finalized_template_enhancement_output_buffer',
			array( $mock_action_callback, 'action' ),
			10,
			PHP_INT_MAX
		);

		add_filter(
			'wp_template_enhancement_output_buffer',
			static function ( string $buffer ): string {
				$p = WP_HTML_Processor::create_full_parser( $buffer );
				while ( $p->next_tag() ) {
					switch ( $p->get_tag() ) {
						case 'HTML':
							$p->set_attribute( 'lang', 'es' );
							break;
						case 'TITLE':
							$p->set_modifiable_text( 'Saludo' );
							break;
						case 'H1':
							if ( $p->next_token() && '#text' === $p->get_token_name() ) {
								$p->set_modifiable_text( '¡Hola, mundo!' );
							}
							break;
					}
				}
				return $p->get_updated_html();
			}
		);

		$this->assertCount( 0, headers_list(), 'Expected no headers to have been sent during unit tests.' );
		ini_set( 'default_mimetype', 'text/html' ); // Since sending a header won't work.

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return true indicating the output buffer started.' );
		$this->assertSame( 1, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to have fired.' );
		$this->assertSame( $initial_ob_level + 1, ob_get_level(), 'Expected the output buffer level to have been incremented' );

		?>
		<!DOCTYPE html>
		<html lang="en">
			<head>
				<title>Greeting</title>
			</head>
			<?php
			$this->assertFalse(
				@ob_flush(), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				'Expected output buffer to not be incrementally flushable.'
			);
			?>
			<body>
				<h1>Hello World!</h1>
			</body>
		</html>
		<?php

		$ob_status = ob_get_status();
		$this->assertSame( 'wp_finalize_template_enhancement_output_buffer', $ob_status['name'], 'Expected name to be WP function.' );
		$this->assertSame( 1, $ob_status['type'], 'Expected type to be user supplied handler.' );
		$this->assertSame( 0, $ob_status['chunk_size'], 'Expected unlimited chunk size.' );

		ob_end_flush(); // End the buffer started by wp_start_template_enhancement_output_buffer().
		$this->assertSame( $initial_ob_level, ob_get_level(), 'Expected the output buffer to be back at the initial level.' );

		$this->assertSame( 1, $mock_filter_callback->get_call_count(), 'Expected the wp_template_enhancement_output_buffer filter to have applied.' );
		$filter_args = $mock_filter_callback->get_args()[0];
		$this->assertIsArray( $filter_args, 'Expected the wp_template_enhancement_output_buffer filter to have applied.' );
		$this->assertCount( 2, $filter_args, 'Expected two args to be supplied to the wp_template_enhancement_output_buffer filter.' );
		$this->assertIsString( $filter_args[0], 'Expected the $filtered_output param to the wp_template_enhancement_output_buffer filter to be a string.' );
		$this->assertIsString( $filter_args[1], 'Expected the $output param to the wp_template_enhancement_output_buffer filter to be a string.' );
		$this->assertSame( $filter_args[1], $filter_args[0], 'Expected the initial $filtered_output to match $output in the wp_template_enhancement_output_buffer filter.' );
		$original_output = $filter_args[0];
		$this->assertStringContainsString( '<!DOCTYPE html>', $original_output, 'Expected original output to contain string.' );
		$this->assertStringContainsString( '<html lang="en">', $original_output, 'Expected original output to contain string.' );
		$this->assertStringContainsString( '<title>Greeting</title>', $original_output, 'Expected original output to contain string.' );
		$this->assertStringContainsString( '<h1>Hello World!</h1>', $original_output, 'Expected original output to contain string.' );
		$this->assertStringContainsString( '</html>', $original_output, 'Expected original output to contain string.' );

		$processed_output = ob_get_clean(); // Obtain the output via the wrapper output buffer.
		$this->assertIsString( $processed_output );
		$this->assertNotEquals( $original_output, $processed_output );

		$this->assertStringContainsString( '<!DOCTYPE html>', $processed_output, 'Expected processed output to contain string.' );
		$this->assertStringContainsString( '<html lang="es">', $processed_output, 'Expected processed output to contain string.' );
		$this->assertStringContainsString( '<title>Saludo</title>', $processed_output, 'Expected processed output to contain string.' );
		$this->assertStringContainsString( '<h1>¡Hola, mundo!</h1>', $processed_output, 'Expected processed output to contain string.' );
		$this->assertStringContainsString( '</html>', $processed_output, 'Expected processed output to contain string.' );

		$this->assertSame( 1, did_action( 'wp_finalized_template_enhancement_output_buffer' ), 'Expected the wp_finalized_template_enhancement_output_buffer action to have fired.' );
		$this->assertSame( 1, $mock_action_callback->get_call_count(), 'Expected wp_finalized_template_enhancement_output_buffer action callback to have been called once.' );
		$action_args = $mock_action_callback->get_args()[0];
		$this->assertCount( 1, $action_args, 'Expected the wp_finalized_template_enhancement_output_buffer action to have been passed only one argument.' );
		$this->assertSame( $processed_output, $action_args[0], 'Expected the arg passed to wp_finalized_template_enhancement_output_buffer to be the same as the processed output buffer.' );
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() starts the expected output buffer but ending with cleaning prevents any processing.
	 *
	 * @ticket 43258
	 * @ticket 64126
	 *
	 * @covers ::wp_start_template_enhancement_output_buffer
	 * @covers ::wp_finalize_template_enhancement_output_buffer
	 */
	public function test_wp_start_template_enhancement_output_buffer_ended_cleaned(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$mock_filter_callback = new MockAction();
		add_filter(
			'wp_template_enhancement_output_buffer',
			array( $mock_filter_callback, 'filter' )
		);

		add_filter(
			'wp_template_enhancement_output_buffer',
			static function ( string $buffer ): string {
				$p = WP_HTML_Processor::create_full_parser( $buffer );
				if ( $p->next_tag( array( 'tag_name' => 'TITLE' ) ) ) {
					$p->set_modifiable_text( 'Processed' );
				}
				return $p->get_updated_html();
			}
		);

		$mock_action_callback = new MockAction();
		add_filter(
			'wp_finalized_template_enhancement_output_buffer',
			array( $mock_action_callback, 'action' ),
			10,
			PHP_INT_MAX
		);

		$this->assertCount( 0, headers_list(), 'Expected no headers to have been sent during unit tests.' );
		ini_set( 'default_mimetype', 'text/html' ); // Since sending a header won't work.

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return true indicating the output buffer started.' );
		$this->assertSame( 1, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to have fired.' );
		$this->assertSame( $initial_ob_level + 1, ob_get_level(), 'Expected the output buffer level to have been incremented' );

		?>
		<!DOCTYPE html>
			<html lang="en">
			<head>
				<title>Unprocessed</title>
			</head>
			<body>
				<h1>Hello World!</h1>
				<!-- ... -->
		<?php ob_end_clean(); // Clean and end the buffer started by wp_start_template_enhancement_output_buffer(). ?>
		<!DOCTYPE html>
		<html lang="en">
			<head>
				<title>Output Buffer Not Processed</title>
			</head>
			<body>
				<h1>Template rendering aborted!!!</h1>
			</body>
		</html>
		<?php

		$this->assertSame( $initial_ob_level, ob_get_level(), 'Expected the output buffer to be back at the initial level.' );

		$this->assertSame( 0, $mock_filter_callback->get_call_count(), 'Expected the wp_template_enhancement_output_buffer filter to not have applied.' );

		// Obtain the output via the wrapper output buffer.
		$output = ob_get_clean();
		$this->assertIsString( $output, 'Expected ob_get_clean() to return a string.' );
		$this->assertStringNotContainsString( '<title>Unprocessed</title>', $output, 'Expected output buffer to not have string since the template was overridden.' );
		$this->assertStringNotContainsString( '<title>Processed</title>', $output, 'Expected output buffer to not have string since the filter did not apply.' );
		$this->assertStringContainsString( '<title>Output Buffer Not Processed</title>', $output, 'Expected output buffer to have string since the output buffer was ended with cleaning.' );

		$this->assertSame( 0, did_action( 'wp_finalized_template_enhancement_output_buffer' ), 'Expected the wp_finalized_template_enhancement_output_buffer action to not have fired.' );
		$this->assertSame( 0, $mock_action_callback->get_call_count(), 'Expected wp_finalized_template_enhancement_output_buffer action callback to have been called once.' );
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() starts the expected output buffer and cleaning allows the template to be replaced.
	 *
	 * @ticket 43258
	 * @ticket 64126
	 *
	 * @covers ::wp_start_template_enhancement_output_buffer
	 * @covers ::wp_finalize_template_enhancement_output_buffer
	 */
	public function test_wp_start_template_enhancement_output_buffer_cleaned_and_replaced(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$mock_filter_callback = new MockAction();
		add_filter(
			'wp_template_enhancement_output_buffer',
			array( $mock_filter_callback, 'filter' )
		);

		add_filter(
			'wp_template_enhancement_output_buffer',
			static function ( string $buffer ): string {
				$p = WP_HTML_Processor::create_full_parser( $buffer );
				if ( $p->next_tag( array( 'tag_name' => 'TITLE' ) ) ) {
					$p->set_modifiable_text( 'Processed' );
				}
				return $p->get_updated_html();
			}
		);

		$mock_action_callback = new MockAction();
		add_filter(
			'wp_finalized_template_enhancement_output_buffer',
			array( $mock_action_callback, 'action' ),
			10,
			PHP_INT_MAX
		);

		$this->assertCount( 0, headers_list(), 'Expected no headers to have been sent during unit tests.' );
		ini_set( 'default_mimetype', 'application/xhtml+xml' ); // Since sending a header won't work.

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return true indicating the output buffer started.' );
		$this->assertSame( 1, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to have fired.' );
		$this->assertSame( $initial_ob_level + 1, ob_get_level(), 'Expected the output buffer level to have been incremented.' );

		?>
		<!DOCTYPE html>
			<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>Unprocessed</title>
			</head>
			<body>
				<h1>Hello World!</h1>
				<!-- ... -->
		<?php ob_clean(); // Clean the buffer started by wp_start_template_enhancement_output_buffer(), allowing the following document to replace the above.. ?>
		<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
		<!DOCTYPE html>
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
			<head>
				<meta charset="utf-8" />
				<title>Template Replaced</title>
			</head>
			<body>
				<h1>Template Replaced</h1>
				<p>The original template called <code>ob_clean()</code> which allowed this template to take its place.</p>
			</body>
		</html>
		<?php

		ob_end_flush(); // End the buffer started by wp_start_template_enhancement_output_buffer().
		$this->assertSame( $initial_ob_level, ob_get_level(), 'Expected the output buffer to be back at the initial level.' );

		$this->assertSame( 1, $mock_filter_callback->get_call_count(), 'Expected the wp_template_enhancement_output_buffer filter to have applied.' );

		// Obtain the output via the wrapper output buffer.
		$output = ob_get_clean();
		$this->assertIsString( $output, 'Expected ob_get_clean() to return a string.' );
		$this->assertStringNotContainsString( '<title>Unprocessed</title>', $output, 'Expected output buffer to not have string due to template override.' );
		$this->assertStringContainsString( '<title>Processed</title>', $output, 'Expected output buffer to have string due to filtering.' );
		$this->assertStringContainsString( '<h1>Template Replaced</h1>', $output, 'Expected output buffer to have string due to replaced template.' );

		$this->assertSame( 1, did_action( 'wp_finalized_template_enhancement_output_buffer' ), 'Expected the wp_finalized_template_enhancement_output_buffer action to have fired.' );
		$this->assertSame( 1, $mock_action_callback->get_call_count(), 'Expected wp_finalized_template_enhancement_output_buffer action callback to have been called once.' );
		$action_args = $mock_action_callback->get_args()[0];
		$this->assertCount( 1, $action_args, 'Expected the wp_finalized_template_enhancement_output_buffer action to have been passed only one argument.' );
		$this->assertSame( $output, $action_args[0], 'Expected the arg passed to wp_finalized_template_enhancement_output_buffer to be the same as the processed output buffer.' );
	}

	/**
	 * Tests that wp_start_template_enhancement_output_buffer() starts the expected output buffer and that the output buffer is not processed.
	 *
	 * @ticket 43258
	 * @ticket 64126
	 *
	 * @covers ::wp_start_template_enhancement_output_buffer
	 * @covers ::wp_finalize_template_enhancement_output_buffer
	 */
	public function test_wp_start_template_enhancement_output_buffer_for_json(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$mock_filter_callback = new MockAction();
		add_filter( 'wp_template_enhancement_output_buffer', array( $mock_filter_callback, 'filter' ) );

		$mock_action_callback = new MockAction();
		add_filter(
			'wp_finalized_template_enhancement_output_buffer',
			array( $mock_action_callback, 'action' ),
			10,
			PHP_INT_MAX
		);

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return true indicating the output buffer started.' );
		$this->assertSame( 1, did_action( 'wp_template_enhancement_output_buffer_started' ), 'Expected the wp_template_enhancement_output_buffer_started action to have fired.' );
		$this->assertSame( $initial_ob_level + 1, ob_get_level(), 'Expected the output buffer level to have been incremented.' );

		$this->assertCount( 0, headers_list(), 'Expected no headers to have been sent during unit tests.' );
		ini_set( 'default_mimetype', 'application/json' ); // Since sending a header won't work.

		$json = wp_json_encode(
			array(
				'success' => true,
				'data'    => array(
					'message' => 'Hello, world!',
					'fish'    => '<o><', // Something that looks like HTML.
				),
			)
		);
		echo $json;

		$ob_status = ob_get_status();
		$this->assertSame( 'wp_finalize_template_enhancement_output_buffer', $ob_status['name'], 'Expected name to be WP function.' );
		$this->assertSame( 1, $ob_status['type'], 'Expected type to be user supplied handler.' );
		$this->assertSame( 0, $ob_status['chunk_size'], 'Expected unlimited chunk size.' );

		ob_end_flush(); // End the buffer started by wp_start_template_enhancement_output_buffer().
		$this->assertSame( $initial_ob_level, ob_get_level(), 'Expected the output buffer to be back at the initial level.' );

		$this->assertSame( 0, $mock_filter_callback->get_call_count(), 'Expected the wp_template_enhancement_output_buffer filter to not have applied.' );

		// Obtain the output via the wrapper output buffer.
		$output = ob_get_clean();
		$this->assertIsString( $output, 'Expected ob_get_clean() to return a string.' );
		$this->assertSame( $json, $output, 'Expected output to not be processed.' );

		$this->assertSame( 1, did_action( 'wp_finalized_template_enhancement_output_buffer' ), 'Expected the wp_finalized_template_enhancement_output_buffer action to have fired even though the wp_template_enhancement_output_buffer filter did not apply.' );
		$this->assertSame( 1, $mock_action_callback->get_call_count(), 'Expected wp_finalized_template_enhancement_output_buffer action callback to have been called once.' );
		$action_args = $mock_action_callback->get_args()[0];
		$this->assertCount( 1, $action_args, 'Expected the wp_finalized_template_enhancement_output_buffer action to have been passed only one argument.' );
		$this->assertSame( $output, $action_args[0], 'Expected the arg passed to wp_finalized_template_enhancement_output_buffer to be the same as the processed output buffer.' );
	}

	/**
	 * Tests that wp_load_classic_theme_block_styles_on_demand() does not add hooks for block themes.
	 *
	 * @ticket 64099
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 */
	public function test_wp_load_classic_theme_block_styles_on_demand_in_block_theme(): void {
		switch_theme( 'block-theme' );

		wp_load_classic_theme_block_styles_on_demand();

		$this->assertFalse( has_filter( 'should_load_separate_core_block_assets' ), 'Expect should_load_separate_core_block_assets filter NOT to be added for block themes.' );
		$this->assertFalse( has_filter( 'should_load_block_assets_on_demand', '__return_true' ), 'Expect should_load_block_assets_on_demand filter NOT to be added for block themes.' );
		$this->assertFalse( has_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' ), 'Expect wp_template_enhancement_output_buffer_started action NOT to be added for block themes.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{theme: string, set_up: Closure|null, expected_on_demand: bool, expected_buffer_started: bool}>
	 */
	public function data_wp_load_classic_theme_block_styles_on_demand(): array {
		return array(
			'block_theme'                                => array(
				'theme'                   => 'block-theme',
				'set_up'                  => static function () {},
				'expected_on_demand'      => false,
				'expected_buffer_started' => false,
			),
			'classic_theme_with_output_buffer_blocked'   => array(
				'theme'                   => 'default',
				'set_up'                  => static function () {
					add_filter( 'wp_should_output_buffer_template_for_enhancement', '__return_false' );
				},
				'expected_on_demand'      => false,
				'expected_buffer_started' => false,
			),
			'classic_theme_with_block_styles_support'    => array(
				'theme'                   => 'default',
				'set_up'                  => static function () {
					add_theme_support( 'wp-block-styles' );
				},
				'expected_on_demand'      => true,
				'expected_buffer_started' => true,
			),
			'classic_theme_without_block_styles_support' => array(
				'theme'                   => 'default',
				'set_up'                  => static function () {
					remove_theme_support( 'wp-block-styles' );
				},
				'expected_on_demand'      => false,
				'expected_buffer_started' => true,
			),
		);
	}

	/**
	 * Tests that wp_load_classic_theme_block_styles_on_demand() adds the expected hooks (or not).
	 *
	 * @ticket 64099
	 * @ticket 64150
	 *
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 *
	 * @dataProvider data_wp_load_classic_theme_block_styles_on_demand
	 */
	public function test_wp_load_classic_theme_block_styles_on_demand( string $theme, ?Closure $set_up, bool $expected_on_demand, bool $expected_buffer_started ) {
		$this->assertFalse( wp_should_load_separate_core_block_assets(), 'Expected wp_should_load_separate_core_block_assets() to return false initially.' );
		$this->assertFalse( wp_should_load_block_assets_on_demand(), 'Expected wp_should_load_block_assets_on_demand() to return true' );
		$this->assertFalse( has_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' ), 'Expected wp_template_enhancement_output_buffer_started action to be added for classic themes.' );

		switch_theme( $theme );
		if ( $set_up ) {
			$set_up();
		}

		wp_load_classic_theme_block_styles_on_demand();

		$this->assertSame( $expected_on_demand, wp_should_load_separate_core_block_assets(), 'Expected wp_should_load_separate_core_block_assets() return value.' );
		$this->assertSame( $expected_on_demand, wp_should_load_block_assets_on_demand(), 'Expected wp_should_load_block_assets_on_demand() return value.' );
		$this->assertSame( $expected_buffer_started, (bool) has_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' ), 'Expected wp_template_enhancement_output_buffer_started action added status.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure|null}>
	 */
	public function data_wp_hoist_late_printed_styles(): array {
		return array(
			'no_actions_removed'              => array(
				'set_up' => null,
			),
			'_wp_footer_scripts_removed'      => array(
				'set_up' => static function () {
					remove_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
				},
			),
			'wp_print_footer_scripts_removed' => array(
				'set_up' => static function () {
					remove_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
				},
			),
			'both_actions_removed'            => array(
				'set_up' => static function () {
					remove_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
					remove_action( 'wp_footer', 'wp_print_footer_scripts' );
				},
			),
			'block_library_removed'           => array(
				'set_up' => static function () {
					wp_deregister_style( 'wp-block-library' );
				},
			),
		);
	}

	/**
	 * Tests that wp_hoist_late_printed_styles() adds a placeholder for delayed CSS, then removes it and adds all CSS to the head including late enqueued styles.
	 *
	 * @ticket 64099
	 * @covers ::wp_hoist_late_printed_styles
	 *
	 * @dataProvider data_wp_hoist_late_printed_styles
	 */
	public function test_wp_hoist_late_printed_styles( ?Closure $set_up ): void {
		if ( $set_up ) {
			$set_up();
		}

		switch_theme( 'default' );

		// Enqueue a style
		wp_enqueue_style( 'early', 'http://example.com/style.css' );
		wp_add_inline_style( 'early', '/* EARLY */' );

		wp_hoist_late_printed_styles();

		// Ensure late styles are printed.
		add_filter( 'print_late_styles', '__return_false', 1000 );
		$this->assertTrue( apply_filters( 'print_late_styles', true ), 'Expected late style printing to be forced.' );

		// Simulate wp_head.
		$head_output = get_echo( 'wp_head' );

		$this->assertStringContainsString( 'early', $head_output, 'Expected the early-enqueued stylesheet to be present.' );

		// Enqueue a late style (after wp_head).
		wp_enqueue_style( 'late', 'http://example.com/late-style.css', array(), null );
		wp_add_inline_style( 'late', '/* EARLY */' );

		// Simulate footer scripts.
		$footer_output = get_echo( 'wp_footer' );

		// Create a simulated output buffer.
		$buffer = '<html><head>' . $head_output . '</head><body><main>Content</main>' . $footer_output . '</body></html>';

		// Apply the output buffer filter.
		$filtered_buffer = apply_filters( 'wp_template_enhancement_output_buffer', $buffer );

		$this->assertStringContainsString( '</head>', $buffer, 'Expected the closing HEAD tag to be in the response.' );

		$this->assertDoesNotMatchRegularExpression( '#/\*wp_late_styles_placeholder:[a-f0-9-]+\*/#', $filtered_buffer, 'Expected the placeholder to be removed.' );
		$found_styles = array(
			'HEAD' => array(),
			'BODY' => array(),
		);
		$processor    = WP_HTML_Processor::create_full_parser( $filtered_buffer );
		while ( $processor->next_tag() ) {
			$group = in_array( 'HEAD', $processor->get_breadcrumbs(), true ) ? 'HEAD' : 'BODY';
			if (
				'LINK' === $processor->get_tag() &&
				$processor->get_attribute( 'rel' ) === 'stylesheet'
			) {
				$found_styles[ $group ][] = $processor->get_attribute( 'id' );
			} elseif ( 'STYLE' === $processor->get_tag() ) {
				$found_styles[ $group ][] = $processor->get_attribute( 'id' );
			}
		}

		$expected = array(
			'early-css',
			'early-inline-css',
			'late-css',
			'late-inline-css',
		);
		foreach ( $expected as $style_id ) {
			$this->assertContains( $style_id, $found_styles['HEAD'], 'Expected stylesheet with ID to be in the HEAD.' );
		}
		$this->assertSame(
			$expected,
			array_values( array_intersect( $found_styles['HEAD'], $expected ) ),
			'Expected styles to be printed in the same order.'
		);
		$this->assertCount( 0, $found_styles['BODY'], 'Expected no styles to be present in the footer.' );
	}

	public function assertTemplateHierarchy( $url, array $expected, $message = '' ) {
		$this->go_to( $url );
		$hierarchy = $this->get_template_hierarchy();

		$this->assertSame( $expected, $hierarchy, $message );
	}

	protected static function get_query_template_conditions() {
		return array(
			'embed'             => 'is_embed',
			'404'               => 'is_404',
			'search'            => 'is_search',
			'front_page'        => 'is_front_page',
			'home'              => 'is_home',
			'privacy_policy'    => 'is_privacy_policy',
			'post_type_archive' => 'is_post_type_archive',
			'taxonomy'          => 'is_tax',
			'attachment'        => 'is_attachment',
			'single'            => 'is_single',
			'page'              => 'is_page',
			'singular'          => 'is_singular',
			'category'          => 'is_category',
			'tag'               => 'is_tag',
			'author'            => 'is_author',
			'date'              => 'is_date',
			'archive'           => 'is_archive',
			'paged'             => 'is_paged',
		);
	}

	protected function get_template_hierarchy() {
		foreach ( self::get_query_template_conditions() as $type => $condition ) {

			if ( call_user_func( $condition ) ) {
				$filter = str_replace( '_', '', $type );
				add_filter( "{$filter}_template_hierarchy", array( $this, 'log_template_hierarchy' ) );
				call_user_func( "get_{$type}_template" );
				remove_filter( "{$filter}_template_hierarchy", array( $this, 'log_template_hierarchy' ) );
			}
		}
		$hierarchy       = $this->hierarchy;
		$this->hierarchy = array();
		return $hierarchy;
	}

	public function log_template_hierarchy( array $hierarchy ) {
		$this->hierarchy = array_merge( $this->hierarchy, $hierarchy );
		return $hierarchy;
	}
}
