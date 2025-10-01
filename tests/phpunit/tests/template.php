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
	}

	public function tear_down() {
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
	 * Tests that wp_start_template_output_buffer() starts the expected output buffer and that the expected hooks fire for
	 * an HTML document and that the response is not incrementally flushable.
	 *
	 * @ticket 43258
	 * @covers ::wp_start_template_output_buffer
	 * @covers ::wp_finalize_template_output_buffers
	 */
	public function test_wp_start_template_output_buffer_for_html(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$action_args = null;
		add_action(
			'wp_final_template_output_buffer',
			function () use ( &$action_args ): void {
				$action_args = func_get_args();
			},
			10,
			PHP_INT_MAX
		);

		$html_filter_args = null;
		add_filter(
			'wp_template_output_buffer_html',
			static function ( string $buffer ) use ( &$html_filter_args ): string {
				$html_filter_args   = func_get_args();

				$p = WP_HTML_Processor::create_full_parser( $buffer );
				while ( $p->next_tag() ) {
					echo $p->get_tag() . PHP_EOL;
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
			},
			10,
			PHP_INT_MAX
		);

		$filter_args = null;
		add_filter(
			'wp_template_output_buffer',
			function ( string $buffer ) use ( &$filter_args ): string {
				$filter_args = func_get_args();
				$this->assertStringNotContainsString( 'Hello', $buffer );
				if ( str_starts_with( ltrim( $buffer ), '<!DOCTYPE html>' ) ) {
					$buffer .= "\n<!-- Output buffer was processed! -->\n";
				}
				return $buffer;
			},
			10,
			PHP_INT_MAX
		);

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_output_buffer() );
		$this->assertSame( $initial_ob_level + 1, ob_get_level() );

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
		$this->assertSame( 'wp_finalize_template_output_buffer', $ob_status['name'], 'Expected name to be WP function.' );
		$this->assertSame( 1, $ob_status['type'], 'Expected type to be user supplied handler.' );
		$this->assertSame( 0, $ob_status['chunk_size'], 'Expected unlimited chunk size.' );

		ob_end_flush(); // End the buffer started by wp_start_template_output_buffer().
		$this->assertSame( $initial_ob_level, ob_get_level() );

		$this->assertIsArray( $html_filter_args, 'Expected the wp_template_output_buffer_html filter to have applied.' );
		$this->assertCount( 2, $html_filter_args, 'Expected two args to be supplied to the wp_template_output_buffer_html filter.' );
		$this->assertIsString( $html_filter_args[0], 'Expected the $filtered_output param to the wp_template_output_buffer_html filter to be a string.' );
		$this->assertIsString( $html_filter_args[1], 'Expected the $output param to the wp_template_output_buffer_html filter to be a string.' );
		$this->assertSame( $html_filter_args[1], $html_filter_args[0], 'Expected the initial $filtered_output to match $output in the wp_template_output_buffer_html filter.' );

		$this->assertIsArray( $filter_args, 'Expected the wp_template_output_buffer filter to have applied.' );
		$this->assertCount( 2, $filter_args, 'Expected two args to be supplied to the wp_template_output_buffer filter.' );
		$this->assertIsString( $filter_args[0], 'Expected the $filtered_output param to the wp_template_output_buffer filter to be a string.' );
		$this->assertIsString( $filter_args[1], 'Expected the $output param to the wp_template_output_buffer filter to be a string.' );
		$this->assertNotSame( $filter_args[1], $filter_args[0], 'Expected the $output and $filtered_output args to not match when applying the wp_template_output_buffer filter since the first latter was modified by a wp_template_output_buffer_html filter.' );
		$this->assertSame( $filter_args[1], $html_filter_args[1], 'Expected the $output param to match between the wp_template_output_buffer and wp_template_output_buffer_html filters.' );

		$output = ob_get_clean(); // Obtain the output via the wrapper output buffer.
		$this->assertIsString( $output );
		$this->assertSame( 1, did_action( 'wp_final_template_output_buffer' ) );
		$this->assertIsArray( $action_args, 'Expected the wp_final_template_output_buffer callback to have executed.' );
		$this->assertCount( 2, $action_args, 'Expected two params to be passed to the wp_final_template_output_buffer action.' );
		$this->assertSame( $action_args[1], $html_filter_args[1], 'Expected the $output param to match between the wp_template_output_buffer_html filter and the wp_final_template_output_buffer action.' );
		$this->assertNotSame( $action_args[1], $action_args[0], 'Expected the $output and $filtered_output args to not match when applying the wp_final_template_output_buffer action.' );
		$this->assertSame( $action_args[0], $output, 'Expected the filtered output buffer passed as the first param to the wp_final_template_output_buffer action to match the output buffer.' );
		$this->assertStringContainsString( '<!DOCTYPE html>', $action_args[0] );
		$this->assertStringContainsString( '<html lang="es">', $action_args[0] );
		$this->assertStringContainsString( '<title>Saludo</title>', $action_args[0] );
		$this->assertStringContainsString( '<h1>¡Hola, mundo!</h1>', $action_args[0] );
		$this->assertStringContainsString( '</html>', $action_args[0] );
		$this->assertStringContainsString( '<!-- Output buffer was processed! -->', $action_args[0] );
		$this->assertIsString( $action_args[1] );
		$this->assertStringContainsString( '<!DOCTYPE html>', $action_args[1] );
		$this->assertStringContainsString( '<html lang="en">', $action_args[1] );
		$this->assertStringContainsString( '<title>Greeting</title>', $action_args[1] );
		$this->assertStringContainsString( '<h1>Hello World!</h1>', $action_args[1] );
		$this->assertStringContainsString( '</html>', $action_args[1] );
		$this->assertStringNotContainsString( '<!-- Output buffer was processed! -->', $action_args[1] );
	}

	/**
	 * Tests that wp_start_template_output_buffer() starts the expected output buffer but ending with cleaning prevents any processing.
	 *
	 * @ticket 43258
	 * @covers ::wp_start_template_output_buffer
	 * @covers ::wp_finalize_template_output_buffer
	 */
	public function test_wp_start_template_output_buffer_ended_cleaned(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$called_html_filter = false;
		add_filter(
			'wp_template_output_buffer_html',
			static function ( string $buffer ) use ( &$called_html_filter ): string {
				$called_html_filter = true;

				$p = WP_HTML_Processor::create_full_parser( $buffer );
				if ( $p->next_tag( array( 'tag_name' => 'TITLE' ) ) ) {
					$p->set_modifiable_text( 'Processed' );
				}
				return $p->get_updated_html();
			}
		);

		$called_filter = false;
		add_filter(
			'wp_template_output_buffer',
			function ( string $buffer ) use ( &$called_filter ): string {
				$called_filter = true;

				$buffer .= "\n<!-- Output buffer was processed! -->\n";
				return $buffer;
			}
		);

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_output_buffer() );
		$this->assertSame( $initial_ob_level + 1, ob_get_level() );

		?>
		<!DOCTYPE html>
			<html lang="en">
			<head>
				<title>Unprocessed</title>
			</head>
			<body>
				<h1>Hello World!</h1>
				<!-- ... -->
		<?php ob_end_clean(); // Clean and end the buffer started by wp_start_template_output_buffer(). ?>
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

		$this->assertSame( $initial_ob_level, ob_get_level() );

		$this->assertFalse( $called_html_filter );
		$this->assertFalse( $called_filter );
		$this->assertSame( 0, did_action( 'wp_final_template_output_buffer' ) );

		// Obtain the output via the wrapper output buffer.
		$output = ob_get_clean();
		$this->assertIsString( $output );
		$this->assertStringNotContainsString( '<title>Unprocessed</title>', $output );
		$this->assertStringNotContainsString( '<title>Processed</title>', $output );
		$this->assertStringNotContainsString( '<!-- Output buffer was processed! -->', $output );
		$this->assertStringContainsString( '<title>Output Buffer Not Processed</title>', $output );
	}

	/**
	 * Tests that wp_start_template_output_buffer() starts the expected output buffer and cleaning allows the template to be replaced.
	 *
	 * @ticket 43258
	 * @covers ::wp_start_template_output_buffer
	 * @covers ::wp_finalize_template_output_buffer
	 */
	public function test_wp_start_template_output_buffer_cleaned_and_replaced(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$called_html_filter = false;
		add_filter(
			'wp_template_output_buffer_html',
			static function ( string $buffer ) use ( &$called_html_filter ): string {
				$called_html_filter = true;

				$p = WP_HTML_Processor::create_full_parser( $buffer );
				if ( $p->next_tag( array( 'tag_name' => 'TITLE' ) ) ) {
					$p->set_modifiable_text( 'Processed' );
				}
				return $p->get_updated_html();
			}
		);

		$called_filter = false;
		add_filter(
			'wp_template_output_buffer',
			function ( string $buffer ) use ( &$called_filter ): string {
				$called_filter = true;

				$buffer .= "\n<!-- Output buffer was processed! -->\n";
				return $buffer;
			}
		);

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_output_buffer() );
		$this->assertSame( $initial_ob_level + 1, ob_get_level() );

		?>
		<!DOCTYPE html>
			<html lang="en">
			<head>
				<title>Unprocessed</title>
			</head>
			<body>
				<h1>Hello World!</h1>
				<!-- ... -->
		<?php ob_clean(); // Clean the buffer started by wp_start_template_output_buffer(), allowing the following document to replace the above.. ?>
		<!DOCTYPE html>
		<html lang="en">
			<head>
				<title>Template Replaced</title>
			</head>
			<body>
				<h1>Template Replaced</h1>
				<p>The original template called <code>ob_clean()</code> which allowed this template to take its place.</p>
			</body>
		</html>
		<?php

		ob_end_flush(); // End the buffer started by wp_start_template_output_buffer().
		$this->assertSame( $initial_ob_level, ob_get_level() );

		$this->assertTrue( $called_html_filter );
		$this->assertTrue( $called_filter );
		$this->assertSame( 1, did_action( 'wp_final_template_output_buffer' ) );

		// Obtain the output via the wrapper output buffer.
		$output = ob_get_clean();
		$this->assertIsString( $output );
		$this->assertStringNotContainsString( '<title>Unprocessed</title>', $output );
		$this->assertStringContainsString( '<title>Processed</title>', $output );
		$this->assertStringContainsString( '<!-- Output buffer was processed! -->', $output );
		$this->assertStringContainsString( '<h1>Template Replaced</h1>', $output );
	}

	/**
	 * Tests that wp_start_template_output_buffer() starts the expected output buffer and that the expected hooks fire for JSON response.
	 *
	 * @ticket 43258
	 * @covers ::wp_start_template_output_buffer
	 * @covers ::wp_finalize_template_output_buffer
	 */
	public function test_wp_start_template_output_buffer_for_json(): void {
		// Start a wrapper output buffer so that we can flush the inner buffer.
		ob_start();

		$action_args = null;
		add_action(
			'wp_final_template_output_buffer',
			function () use ( &$action_args ): void {
				$action_args = func_get_args();
			},
			10,
			PHP_INT_MAX
		);

		$called_html_filter = false;
		add_filter(
			'wp_template_output_buffer_html',
			static function ( string $buffer ) use ( &$called_html_filter ): string {
				$called_html_filter = true;
				return $buffer;
			}
		);

		$called_filter = false;
		add_filter(
			'wp_template_output_buffer',
			function ( string $buffer ) use ( &$called_filter ): string {
				$called_filter = true;

				$data                    = json_decode( $buffer, true );
				$data['data']['message'] = '¡Hola, mundo!';
				return json_encode( $data );
			}
		);

		$initial_ob_level = ob_get_level();
		$this->assertTrue( wp_start_template_output_buffer() );
		$this->assertSame( $initial_ob_level + 1, ob_get_level() );

		ini_set( 'default_mimetype', 'application/json' ); // Since sending a header won't work.
		echo wp_json_encode(
			array(
				'success' => true,
				'data'    => array(
					'message' => 'Hello, world!',
					'fish'    => '<o><', // Something that looks like HTML.
				),
			)
		);

		$ob_status = ob_get_status();
		$this->assertSame( 'wp_finalize_template_output_buffer', $ob_status['name'], 'Expected name to be WP function.' );
		$this->assertSame( 1, $ob_status['type'], 'Expected type to be user supplied handler.' );
		$this->assertSame( 0, $ob_status['chunk_size'], 'Expected unlimited chunk size.' );

		ob_end_flush(); // End the buffer started by wp_start_template_output_buffer().
		$this->assertSame( $initial_ob_level, ob_get_level() );

		$this->assertFalse( $called_html_filter );
		$this->assertTrue( $called_filter );
		$this->assertSame( 1, did_action( 'wp_final_template_output_buffer' ) );

		// Obtain the output via the wrapper output buffer.
		$output = ob_get_clean();
		$this->assertIsString( $output );
		$this->assertIsArray( $action_args );
		$this->assertCount( 2, $action_args );
		$this->assertSame( $action_args[0], $output );
		$this->assertIsString( $action_args[1] );

		$filtered_data = json_decode( $action_args[0], true );
		$original_data = json_decode( $action_args[1], true );

		$this->assertSame( 'Hello, world!', $original_data['data']['message'] );
		$this->assertSame( '¡Hola, mundo!', $filtered_data['data']['message'] );
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
