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

	/**
	 * @var array
	 */
	const RESTORED_CONFIG_OPTIONS = array(
		'display_errors',
		'error_reporting',
		'log_errors',
		'error_log',
		'default_mimetype',
		'html_errors',
		'error_prepend_string',
		'error_append_string',
	);

	/**
	 * @var array
	 */
	protected $original_ini_config;

	public function set_up() {
		parent::set_up();

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

		foreach ( self::RESTORED_CONFIG_OPTIONS as $option ) {
			$this->original_ini_config[ $option ] = ini_get( $option );
		}
	}

	public function tear_down() {
		global $wp_scripts, $wp_styles;
		$wp_scripts = $this->original_wp_scripts;
		$wp_styles  = $this->original_wp_styles;

		foreach ( $this->original_ini_config as $option => $value ) {
			ini_set( $option, $value );
		}

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
				return '<html lang="en"><head><meta charset="utf-8"></head><body>Hey!</body></html>';
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
	 * Data provider for test_wp_finalize_template_enhancement_output_buffer_with_errors_while_processing.
	 *
	 * @return array<string, array{
	 *             ini_config_options: array<string, int|string|bool>,
	 *             emit_filter_errors: Closure,
	 *             emit_action_errors: Closure,
	 *             expected_processed: bool,
	 *             expected_error_log: string[],
	 *             expected_displayed_errors: string[],
	 *         }>
	 */
	public function data_provider_to_test_wp_finalize_template_enhancement_output_buffer_with_errors_while_processing(): array {
		$log_and_display_all = array(
			'error_reporting' => E_ALL,
			'display_errors'  => true,
			'log_errors'      => true,
			'html_errors'     => true,
		);

		$tests = array(
			'deprecated'                              => array(
				'ini_config_options'        => $log_and_display_all,
				'emit_filter_errors'        => static function () {
					trigger_error( 'You are history during filter.', E_USER_DEPRECATED );
				},
				'emit_action_errors'        => static function () {
					trigger_error( 'You are history during action.', E_USER_DEPRECATED );
				},
				'expected_processed'        => true,
				'expected_error_log'        => array(
					'PHP Deprecated:  You are history during filter. in __FILE__ on line __LINE__',
					'PHP Deprecated:  You are history during action. in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'<b>Deprecated</b>:  You are history during filter. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Deprecated</b>:  You are history during action. in <b>__FILE__</b> on line <b>__LINE__</b>',
				),
			),
			'notice'                                  => array(
				'ini_config_options'        => $log_and_display_all,
				'emit_filter_errors'        => static function () {
					trigger_error( 'POSTED: No trespassing during filter.', E_USER_NOTICE );
				},
				'emit_action_errors'        => static function () {
					trigger_error( 'POSTED: No trespassing during action.', E_USER_NOTICE );
				},
				'expected_processed'        => true,
				'expected_error_log'        => array(
					'PHP Notice:  POSTED: No trespassing during filter. in __FILE__ on line __LINE__',
					'PHP Notice:  POSTED: No trespassing during action. in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'<b>Notice</b>:  POSTED: No trespassing during filter. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Notice</b>:  POSTED: No trespassing during action. in <b>__FILE__</b> on line <b>__LINE__</b>',
				),
			),
			'warning'                                 => array(
				'ini_config_options'        => $log_and_display_all,
				'emit_filter_errors'        => static function () {
					trigger_error( 'AVISO: Piso mojado durante filtro.', E_USER_WARNING );
				},
				'emit_action_errors'        => static function () {
					trigger_error( 'AVISO: Piso mojado durante acción.', E_USER_WARNING );
				},
				'expected_processed'        => true,
				'expected_error_log'        => array(
					'PHP Warning:  AVISO: Piso mojado durante filtro. in __FILE__ on line __LINE__',
					'PHP Warning:  AVISO: Piso mojado durante acción. in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'<b>Warning</b>:  AVISO: Piso mojado durante filtro. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Warning</b>:  AVISO: Piso mojado durante acción. in <b>__FILE__</b> on line <b>__LINE__</b>',
				),
			),
			'error'                                   => array(
				'ini_config_options'        => $log_and_display_all,
				'emit_filter_errors'        => static function () {
					@trigger_error( 'ERROR: Can this mistake be rectified during filter?', E_USER_ERROR ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				},
				'emit_action_errors'        => static function () {
					@trigger_error( 'ERROR: Can this mistake be rectified during action?', E_USER_ERROR ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				},
				'expected_processed'        => false,
				'expected_error_log'        => array(
					'PHP Warning:  Uncaught "Exception" thrown: User error triggered: ERROR: Can this mistake be rectified during filter? in __FILE__ on line __LINE__',
					'PHP Warning:  Uncaught "Exception" thrown: User error triggered: ERROR: Can this mistake be rectified during action? in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'<b>Error</b>:  Uncaught "Exception" thrown: User error triggered: ERROR: Can this mistake be rectified during filter? in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Error</b>:  Uncaught "Exception" thrown: User error triggered: ERROR: Can this mistake be rectified during action? in <b>__FILE__</b> on line <b>__LINE__</b>',
				),
			),
			'exception'                               => array(
				'ini_config_options'        => $log_and_display_all,
				'emit_filter_errors'        => static function () {
					throw new Exception( 'I take exception to this filter!' );
				},
				'emit_action_errors'        => static function () {
					throw new Exception( 'I take exception to this action!' );
				},
				'expected_processed'        => false,
				'expected_error_log'        => array(
					'PHP Warning:  Uncaught "Exception" thrown: I take exception to this filter! in __FILE__ on line __LINE__',
					'PHP Warning:  Uncaught "Exception" thrown: I take exception to this action! in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'<b>Error</b>:  Uncaught "Exception" thrown: I take exception to this filter! in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Error</b>:  Uncaught "Exception" thrown: I take exception to this action! in <b>__FILE__</b> on line <b>__LINE__</b>',
				),
			),
			'multiple_non_errors'                     => array(
				'ini_config_options'        => $log_and_display_all,
				'emit_filter_errors'        => static function () {
					trigger_error( 'You are history during filter.', E_USER_DEPRECATED );
					trigger_error( 'POSTED: No trespassing during filter.', E_USER_NOTICE );
					trigger_error( 'AVISO: Piso mojado durante filtro.', E_USER_WARNING );
				},
				'emit_action_errors'        => static function () {
					trigger_error( 'You are history during action.', E_USER_DEPRECATED );
					trigger_error( 'POSTED: No trespassing during action.', E_USER_NOTICE );
					trigger_error( 'AVISO: Piso mojado durante acción.', E_USER_WARNING );
				},
				'expected_processed'        => true,
				'expected_error_log'        => array(
					'PHP Deprecated:  You are history during filter. in __FILE__ on line __LINE__',
					'PHP Notice:  POSTED: No trespassing during filter. in __FILE__ on line __LINE__',
					'PHP Warning:  AVISO: Piso mojado durante filtro. in __FILE__ on line __LINE__',
					'PHP Deprecated:  You are history during action. in __FILE__ on line __LINE__',
					'PHP Notice:  POSTED: No trespassing during action. in __FILE__ on line __LINE__',
					'PHP Warning:  AVISO: Piso mojado durante acción. in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'<b>Deprecated</b>:  You are history during filter. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Notice</b>:  POSTED: No trespassing during filter. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Warning</b>:  AVISO: Piso mojado durante filtro. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Deprecated</b>:  You are history during action. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Notice</b>:  POSTED: No trespassing during action. in <b>__FILE__</b> on line <b>__LINE__</b>',
					'<b>Warning</b>:  AVISO: Piso mojado durante acción. in <b>__FILE__</b> on line <b>__LINE__</b>',
				),
			),
			'deprecated_without_html'                 => array(
				'ini_config_options'        => array_merge(
					$log_and_display_all,
					array(
						'html_errors' => false,
					)
				),
				'emit_filter_errors'        => static function () {
					trigger_error( 'You are history during filter.', E_USER_DEPRECATED );
				},
				'emit_action_errors'        => null,
				'expected_processed'        => true,
				'expected_error_log'        => array(
					'PHP Deprecated:  You are history during filter. in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'Deprecated: You are history during filter. in __FILE__ on line __LINE__',
				),
			),
			'warning_in_eval_with_prepend_and_append' => array(
				'ini_config_options'        => array_merge(
					$log_and_display_all,
					array(
						'error_prepend_string' => '<details><summary>PHP Problem!</summary>',
						'error_append_string'  => '</details>',
					)
				),
				'emit_filter_errors'        => static function () {
					eval( "trigger_error( 'AVISO: Piso mojado durante filtro.', E_USER_WARNING );" ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- We're in a test!
				},
				'emit_action_errors'        => static function () {
					eval( "trigger_error( 'AVISO: Piso mojado durante acción.', E_USER_WARNING );" ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- We're in a test!
				},
				'expected_processed'        => true,
				'expected_error_log'        => array(
					'PHP Warning:  AVISO: Piso mojado durante filtro. in __FILE__ : eval()\'d code on line __LINE__',
					'PHP Warning:  AVISO: Piso mojado durante acción. in __FILE__ : eval()\'d code on line __LINE__',
				),
				'expected_displayed_errors' => array(
					'<b>Warning</b>:  AVISO: Piso mojado durante filtro. in <b>__FILE__ : eval()\'d code</b> on line <b>__LINE__</b>',
					'<b>Warning</b>:  AVISO: Piso mojado durante acción. in <b>__FILE__ : eval()\'d code</b> on line <b>__LINE__</b>',
				),
			),
			'notice_with_display_errors_stderr'       => array(
				'ini_config_options'        => array_merge(
					$log_and_display_all,
					array(
						'display_errors' => 'stderr',
					)
				),
				'emit_filter_errors'        => static function () {
					trigger_error( 'POSTED: No trespassing during filter.' );
				},
				'emit_action_errors'        => static function () {
					trigger_error( 'POSTED: No trespassing during action.' );
				},
				'expected_processed'        => true,
				'expected_error_log'        => array(
					'PHP Notice:  POSTED: No trespassing during filter. in __FILE__ on line __LINE__',
					'PHP Notice:  POSTED: No trespassing during action. in __FILE__ on line __LINE__',
				),
				'expected_displayed_errors' => array(),
			),
		);

		$tests_error_reporting_warnings_and_above = array();
		foreach ( $tests as $name => $test ) {
			$test['ini_config_options']['error_reporting'] = E_ALL ^ E_USER_NOTICE ^ E_USER_DEPRECATED;

			$test['expected_error_log'] = array_values(
				array_filter(
					$test['expected_error_log'],
					static function ( $log_entry ) {
						return ! ( str_contains( $log_entry, 'Notice' ) || str_contains( $log_entry, 'Deprecated' ) );
					}
				)
			);

			$test['expected_displayed_errors'] = array_values(
				array_filter(
					$test['expected_displayed_errors'],
					static function ( $log_entry ) {
						return ! ( str_contains( $log_entry, 'Notice' ) || str_contains( $log_entry, 'Deprecated' ) );
					}
				)
			);

			$tests_error_reporting_warnings_and_above[ "{$name}_with_warnings_and_above_reported" ] = $test;
		}

		$tests_without_display_errors = array();
		foreach ( $tests as $name => $test ) {
			$test['ini_config_options']['display_errors'] = false;
			$test['expected_displayed_errors']            = array();

			$tests_without_display_errors[ "{$name}_without_display_errors" ] = $test;
		}

		$tests_without_display_or_log_errors = array();
		foreach ( $tests as $name => $test ) {
			$test['ini_config_options']['display_errors'] = false;
			$test['ini_config_options']['log_errors']     = false;
			$test['expected_displayed_errors']            = array();
			$test['expected_error_log']                   = array();

			$tests_without_display_or_log_errors[ "{$name}_without_display_errors_or_log_errors" ] = $test;
		}

		return array_merge( $tests, $tests_error_reporting_warnings_and_above, $tests_without_display_errors, $tests_without_display_or_log_errors );
	}

	/**
	 * Tests that errors are handled as expected when errors are emitted when filtering wp_template_enhancement_output_buffer or doing the wp_finalize_template_enhancement_output_buffer action.
	 *
	 * @ticket 43258
	 * @ticket 64108
	 *
	 * @covers ::wp_finalize_template_enhancement_output_buffer
	 *
	 * @dataProvider data_provider_to_test_wp_finalize_template_enhancement_output_buffer_with_errors_while_processing
	 */
	public function test_wp_finalize_template_enhancement_output_buffer_with_errors_while_processing( array $ini_config_options, ?Closure $emit_filter_errors, ?Closure $emit_action_errors, bool $expected_processed, array $expected_error_log, array $expected_displayed_errors ): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		ini_set( 'error_log', $this->temp_filename() ); // phpcs:ignore WordPress.PHP.IniSet.log_errors_Blacklisted, WordPress.PHP.IniSet.Risky
		foreach ( $ini_config_options as $config => $option ) {
			ini_set( $config, $option );
		}

		add_filter(
			'wp_template_enhancement_output_buffer',
			static function ( string $buffer ) use ( $emit_filter_errors ): string {
				$buffer = str_replace( 'Hello', 'Goodbye', $buffer );
				if ( $emit_filter_errors ) {
					$emit_filter_errors();
				}
				return $buffer;
			}
		);

		if ( $emit_action_errors ) {
			add_action(
				'wp_finalized_template_enhancement_output_buffer',
				static function () use ( $emit_action_errors ): void {
					$emit_action_errors();
				}
			);
		}

		$this->assertTrue( wp_start_template_enhancement_output_buffer(), 'Expected wp_start_template_enhancement_output_buffer() to return true indicating the output buffer started.' );

		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<title>Greeting</title>
		</head>
		<body>
			<h1>Hello World!</h1>
		</body>
		</html>
		<?php

		ob_end_flush(); // End the buffer started by wp_start_template_enhancement_output_buffer().

		$processed_output = ob_get_clean(); // Obtain the output via the wrapper output buffer.

		if ( $expected_processed ) {
			$this->assertStringContainsString( 'Goodbye', $processed_output, 'Expected the output buffer to have been processed.' );
		} else {
			$this->assertStringNotContainsString( 'Goodbye', $processed_output, 'Expected the output buffer to not have been processed.' );
		}

		$actual_error_log = array_values(
			array_map(
				static function ( string $error_log_entry ): string {
					$error_log_entry = preg_replace(
						'/^\[.+?] /',
						'',
						$error_log_entry
					);
					$error_log_entry = preg_replace(
						'#(?<= in ).+?' . preg_quote( basename( __FILE__ ), '#' ) . '(\(\d+\))?#',
						'__FILE__',
						$error_log_entry
					);
					return preg_replace(
						'#(?<= on line )\d+#',
						'__LINE__',
						$error_log_entry
					);
				},
				array_filter( explode( "\n", trim( file_get_contents( ini_get( 'error_log' ) ) ) ) )
			)
		);

		$this->assertSame(
			$expected_error_log,
			$actual_error_log,
			'Expected same error log entries. Snapshot: ' . var_export( $actual_error_log, true )
		);

		$displayed_errors = array_values(
			array_map(
				static function ( string $displayed_error ): string {
					$displayed_error = str_replace( '<br />', '', $displayed_error );
					$displayed_error = preg_replace(
						'#( in (?:<b>)?).+?' . preg_quote( basename( __FILE__ ), '#' ) . '(\(\d+\))?#',
						'$1__FILE__',
						$displayed_error
					);
					return preg_replace(
						'#( on line (?:<b>)?)\d+#',
						'$1__LINE__',
						$displayed_error
					);
				},
				array_filter(
					explode( "\n", trim( $processed_output ) ),
					static function ( $line ): bool {
						return str_contains( $line, ' in ' );
					}
				)
			)
		);

		$this->assertSame(
			$expected_displayed_errors,
			$displayed_errors,
			'Expected the displayed errors to be the same. Snapshot: ' . var_export( $displayed_errors, true )
		);

		if ( count( $expected_displayed_errors ) > 0 ) {
			$this->assertStringEndsNotWith( '</html>', rtrim( $processed_output ), 'Expected the output to have the error displayed.' );
		} else {
			$this->assertStringEndsWith( '</html>', rtrim( $processed_output ), 'Expected the output to not have the error displayed.' );
		}
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
			'block_theme'                              => array(
				'theme'                   => 'block-theme',
				'set_up'                  => static function () {},
				'expected_load_separate'  => true,
				'expected_on_demand'      => true,
				'expected_buffer_started' => false,
			),
			'classic_theme_with_output_buffer_blocked' => array(
				'theme'                   => 'default',
				'set_up'                  => static function () {
					add_filter( 'wp_should_output_buffer_template_for_enhancement', '__return_false' );
				},
				'expected_load_separate'  => false,
				'expected_on_demand'      => false,
				'expected_buffer_started' => false,
			),
			'classic_theme_with_should_load_separate_core_block_assets_opt_out' => array(
				'theme'                   => 'default',
				'set_up'                  => static function () {
					add_filter( 'should_load_separate_core_block_assets', '__return_false' );
				},
				'expected_load_separate'  => false,
				'expected_on_demand'      => false,
				'expected_buffer_started' => false,
			),
			'classic_theme_with_should_load_block_assets_on_demand_out_out' => array(
				'theme'                   => 'default',
				'set_up'                  => static function () {
					add_filter( 'should_load_block_assets_on_demand', '__return_false' );
				},
				'expected_load_separate'  => true,
				'expected_on_demand'      => false,
				'expected_buffer_started' => false,
			),
			'classic_theme_without_any_opt_out'        => array(
				'theme'                   => 'default',
				'set_up'                  => static function () {},
				'expected_load_separate'  => true,
				'expected_on_demand'      => true,
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
	public function test_wp_load_classic_theme_block_styles_on_demand( string $theme, ?Closure $set_up, bool $expected_load_separate, bool $expected_on_demand, bool $expected_buffer_started ) {
		$this->assertFalse( wp_should_load_separate_core_block_assets(), 'Expected wp_should_load_separate_core_block_assets() to return false initially.' );
		$this->assertFalse( wp_should_load_block_assets_on_demand(), 'Expected wp_should_load_block_assets_on_demand() to return true' );
		$this->assertFalse( has_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' ), 'Expected wp_template_enhancement_output_buffer_started action to be added for classic themes.' );

		switch_theme( $theme );
		if ( $set_up ) {
			$set_up();
		}

		wp_load_classic_theme_block_styles_on_demand();
		_add_default_theme_supports();

		$this->assertSame( $expected_load_separate, wp_should_load_separate_core_block_assets(), 'Expected wp_should_load_separate_core_block_assets() return value.' );
		$this->assertSame( $expected_on_demand, wp_should_load_block_assets_on_demand(), 'Expected wp_should_load_block_assets_on_demand() return value.' );
		$this->assertSame( $expected_buffer_started, (bool) has_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' ), 'Expected wp_template_enhancement_output_buffer_started action added status.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure|null, inline_size_limit: int,  expected_styles: array{ HEAD: string[], BODY: string[] }}>
	 */
	public function data_wp_hoist_late_printed_styles(): array {
		$common_expected_head_styles = array(
			'wp-img-auto-sizes-contain-inline-css',
			'early-css',
			'early-inline-css',
			'wp-emoji-styles-inline-css',
			'wp-block-library-css',
			'wp-block-separator-css',
			'global-styles-inline-css',
			'core-block-supports-inline-css',
			'classic-theme-styles-css',
			'normal-css',
			'normal-inline-css',
			'wp-custom-css',
			'late-css',
			'late-inline-css',
		);

		return array(
			'standard_classic_theme_config_with_min_styles_inlined' => array(
				'set_up'            => null,
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => $common_expected_head_styles,
					'BODY' => array(),
				),
			),
			'standard_classic_theme_config_with_max_styles_inlined' => array(
				'set_up'            => null,
				'inline_size_limit' => PHP_INT_MAX,
				'expected_styles'   => array(
					'HEAD' => array(
						'wp-img-auto-sizes-contain-inline-css',
						'early-css',
						'early-inline-css',
						'wp-emoji-styles-inline-css',
						'wp-block-library-inline-css',
						'wp-block-separator-inline-css',
						'global-styles-inline-css',
						'core-block-supports-inline-css',
						'classic-theme-styles-inline-css',
						'normal-css',
						'normal-inline-css',
						'wp-custom-css',
						'late-css',
						'late-inline-css',
					),
					'BODY' => array(),
				),
			),
			'standard_classic_theme_config_extra_block_library_inline_style' => array(
				'set_up'            => static function () {
					add_action(
						'enqueue_block_assets',
						static function () {
							wp_add_inline_style( 'wp-block-library', '/* Extra CSS which prevents empty inline style containing placeholder from being removed. */' );
						}
					);
				},
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => ( function ( $expected_styles ) {
						// Insert 'wp-block-library-inline-css' right after 'wp-block-library-css'.
						$i = array_search( 'wp-block-library-css', $expected_styles, true );
						$this->assertIsInt( $i, 'Expected wp-block-library-css to be among the styles.' );
						array_splice( $expected_styles, $i + 1, 0, 'wp-block-library-inline-css' );
						return $expected_styles;
					} )( $common_expected_head_styles ),
					'BODY' => array(),
				),
			),
			'classic_theme_opt_out_separate_block_styles' => array(
				'set_up'            => static function () {
					add_filter( 'should_load_separate_core_block_assets', '__return_false' );
				},
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => array(
						'wp-img-auto-sizes-contain-inline-css',
						'early-css',
						'early-inline-css',
						'wp-emoji-styles-inline-css',
						'wp-block-library-css',
						'classic-theme-styles-css',
						'global-styles-inline-css',
						'normal-css',
						'normal-inline-css',
						'wp-custom-css',
					),
					'BODY' => array(
						'late-css',
						'late-inline-css',
						'core-block-supports-inline-css',
					),
				),
			),
			'_wp_footer_scripts_removed'                  => array(
				'set_up'            => static function () {
					remove_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
				},
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => $common_expected_head_styles,
					'BODY' => array(),
				),
			),
			'wp_print_footer_scripts_removed'             => array(
				'set_up'            => static function () {
					remove_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
				},
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => $common_expected_head_styles,
					'BODY' => array(),
				),
			),
			'both_actions_removed'                        => array(
				'set_up'            => static function () {
					remove_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
					remove_action( 'wp_footer', 'wp_print_footer_scripts' );
				},
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => $common_expected_head_styles,
					'BODY' => array(),
				),
			),
			'disable_block_library'                       => array(
				'set_up'            => static function () {
					add_action(
						'enqueue_block_assets',
						function (): void {
							wp_deregister_style( 'wp-block-library' );
							wp_register_style( 'wp-block-library', '' );
						}
					);
					add_filter( 'should_load_separate_core_block_assets', '__return_false' );
				},
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => array(
						'wp-img-auto-sizes-contain-inline-css',
						'early-css',
						'early-inline-css',
						'wp-emoji-styles-inline-css',
						'classic-theme-styles-css',
						'global-styles-inline-css',
						'normal-css',
						'normal-inline-css',
						'wp-custom-css',
					),
					'BODY' => array(
						'late-css',
						'late-inline-css',
						'core-block-supports-inline-css',
					),
				),
			),
			'override_block_library_inline_style_late'    => array(
				'set_up'            => static function () {
					add_action(
						'enqueue_block_assets',
						function (): void {
							// This tests what happens when the placeholder comment gets replaced unexpectedly.
							wp_styles()->registered['wp-block-library']->extra['after'] = array( '/* OVERRIDDEN! */' );
						}
					);
				},
				'inline_size_limit' => 0,
				'expected_styles'   => array(
					'HEAD' => array(
						'wp-img-auto-sizes-contain-inline-css',
						'early-css',
						'early-inline-css',
						'wp-emoji-styles-inline-css',
						'wp-block-library-css',
						'wp-block-library-inline-css', // This contains the "OVERRIDDEN" text.
						'wp-block-separator-css',
						'global-styles-inline-css',
						'core-block-supports-inline-css',
						'classic-theme-styles-css',
						'normal-css',
						'normal-inline-css',
						'wp-custom-css',
						'late-css',
						'late-inline-css',
					),
					'BODY' => array(),
				),
			),
		);
	}

	/**
	 * Tests that wp_hoist_late_printed_styles() adds a placeholder for delayed CSS, then removes it and adds all CSS to the head including late enqueued styles.
	 *
	 * @ticket 64099
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 * @covers ::wp_hoist_late_printed_styles
	 *
	 * @dataProvider data_wp_hoist_late_printed_styles
	 */
	public function test_wp_hoist_late_printed_styles( ?Closure $set_up, int $inline_size_limit, array $expected_styles ): void {
		switch_theme( 'default' );
		global $wp_styles;
		$wp_styles = null;

		// Disable the styles_inline_size_limit in order to prevent changes from invalidating the snapshots.
		add_filter(
			'styles_inline_size_limit',
			static function () use ( $inline_size_limit ): int {
				return $inline_size_limit;
			}
		);

		add_filter(
			'wp_get_custom_css',
			static function () {
				return '/* CUSTOM CSS from Customizer */';
			}
		);

		if ( $set_up ) {
			$set_up();
		}

		wp_load_classic_theme_block_styles_on_demand();

		// Ensure that separate core block assets get registered.
		register_core_block_style_handles();
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'core/separator' ), 'Expected the core/separator block to be registered.' );

		// Ensure stylesheet files exist on the filesystem since a build may not have been done.
		$this->ensure_style_asset_file_created(
			'wp-block-library',
			wp_should_load_separate_core_block_assets() ? 'css/dist/block-library/common.css' : 'css/dist/block-library/style.css'
		);
		if ( wp_should_load_separate_core_block_assets() ) {
			$this->ensure_style_asset_file_created( 'wp-block-separator', 'blocks/separator/style.css' );
		}
		$this->assertFalse( wp_is_block_theme(), 'Test is not relevant to block themes (only classic themes).' );

		// Enqueue a style early, before wp_enqueue_scripts.
		wp_enqueue_style( 'early', 'https://example.com/style.css' );
		wp_add_inline_style( 'early', '/* EARLY */' );

		// Enqueue a style at the normal spot.
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_enqueue_style( 'normal', 'https://example.com/normal.css' );
				wp_add_inline_style( 'normal', '/* NORMAL */' );
			}
		);

		// Call wp_hoist_late_printed_styles() if wp_load_classic_theme_block_styles_on_demand() queued it up.
		if ( has_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' ) ) {
			wp_hoist_late_printed_styles();
		}

		// Simulate wp_head.
		$head_output = get_echo( 'wp_head' );

		$this->assertStringContainsString( 'early', $head_output, 'Expected the early-enqueued stylesheet to be present.' );

		// Enqueue a late style (after wp_head).
		wp_enqueue_style( 'late', 'https://example.com/late-style.css', array(), null );
		wp_add_inline_style( 'late', '/* LATE */' );

		// Simulate the_content().
		$content = apply_filters(
			'the_content',
			'<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->'
		);

		// Simulate footer scripts.
		$footer_output = get_echo( 'wp_footer' );

		// Create a simulated output buffer.
		$buffer = '<html lang="en"><head><meta charset="utf-8">' . $head_output . '</head><body><main>' . $content . '</main>' . $footer_output . '</body></html>';

		$placeholder_regexp = '#/\*wp_block_styles_on_demand_placeholder:[a-f0-9]+\*/#';
		if ( has_action( 'wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles' ) ) {
			$this->assertMatchesRegularExpression( $placeholder_regexp, $buffer, 'Expected the placeholder to be present in the buffer.' );
		}

		// Apply the output buffer filter.
		$filtered_buffer = apply_filters( 'wp_template_enhancement_output_buffer', $buffer );

		$this->assertStringContainsString( '</head>', $filtered_buffer, 'Expected the closing HEAD tag to be in the response.' );

		$this->assertDoesNotMatchRegularExpression( $placeholder_regexp, $filtered_buffer, 'Expected the placeholder to be removed.' );
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

		/*
		 * Since new styles could appear at any time and since certain styles leak in from the global scope not being
		 * properly reset somewhere else in the test suite, we only check that the expected styles are at least present
		 * and in the same order. When new styles are introduced in core, they may be added to this array as opposed to
		 * updating the arrays in the data provider, if appropriate.
		 */
		$ignored_styles = array(
			'core-block-supports-duotone-inline-css',
			'wp-block-library-theme-css',
			'wp-block-template-skip-link-inline-css',
		);

		$found_subset_styles = array();
		foreach ( array( 'HEAD', 'BODY' ) as $group ) {
			$found_subset_styles[ $group ] = array_values( array_diff( $found_styles[ $group ], $ignored_styles ) );
		}

		$this->assertSame(
			$expected_styles,
			$found_subset_styles,
			'Expected the same styles. Snapshot: ' . self::get_array_snapshot_export( $found_subset_styles )
		);
	}

	/**
	 * Ensures a CSS file is on the filesystem.
	 *
	 * This is needed because unit tests may be run without a build step having been done. Something similar can be seen
	 * elsewhere in tests for the `wp-emoji-loader.js` script:
	 *
	 *     self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
	 *
	 * @param string $handle        Style handle.
	 * @param string $relative_path Relative path to the CSS file in wp-includes.
	 *
	 * @throws Exception If the supplied style handle is not registered as expected.
	 */
	private function ensure_style_asset_file_created( string $handle, string $relative_path ) {
		$dependency = wp_styles()->query( $handle );
		if ( ! $dependency ) {
			throw new Exception( "The stylesheet for $handle is not registered." );
		}
		$dependency->src = includes_url( $relative_path );
		$path            = ABSPATH . WPINC . '/' . $relative_path;
		if ( ! file_exists( $path ) ) {
			$dir = dirname( $path );
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir, 0777, true );
			}
			file_put_contents( $path, "/* CSS for $handle */" );
		}
		wp_style_add_data( $handle, 'path', $path );
	}

	public function assertTemplateHierarchy( $url, array $expected, $message = '' ) {
		$this->go_to( $url );
		$hierarchy = $this->get_template_hierarchy();

		$this->assertSame( $expected, $hierarchy, $message );
	}

	/**
	 * Exports PHP array as string formatted as a snapshot for pasting into a data provider.
	 *
	 * Unfortunately, `var_export()` always includes array indices even for lists. For example:
	 *
	 *     var_export( array( 'a', 'b', 'c' ) );
	 *
	 * Results in:
	 *
	 *     array (
	 *       0 => 'a',
	 *       1 => 'b',
	 *       2 => 'c',
	 *     )
	 *
	 * This makes it unhelpful when outputting a snapshot to update a unit test. So this function strips out the indices
	 * to facilitate copy/pasting the snapshot from an assertion error message into the data provider. For example:
	 *
	 *      array(
	 *          'a',
	 *          'b',
	 *          'c',
	 *      )
	 *
	 *
	 * @param array $snapshot Snapshot.
	 * @return string Snapshot export.
	 */
	private static function get_array_snapshot_export( array $snapshot ): string {
		$export = var_export( $snapshot, true );
		$export = preg_replace( '/\barray \($/m', 'array(', $export );
		$export = preg_replace( '/^(\s+)\d+\s+=>\s+/m', '$1', $export );
		$export = preg_replace( '/=> *\n +/', '=> ', $export );
		$export = preg_replace( '/array\(\n\s+\)/', 'array()', $export );
		return preg_replace_callback(
			'/(^ +)/m',
			static function ( $matches ) {
				return str_repeat( "\t", strlen( $matches[0] ) / 2 );
			},
			$export
		);
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
