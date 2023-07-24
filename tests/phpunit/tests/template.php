<?php
/**
 * test wp-includes/template.php
 *
 * @group themes
 */
class Tests_Template extends WP_UnitTestCase {
	/**
	 * Pre-made test theme directory.
	 * This directory contains a child and a parent theme.
	 *
	 * @var string
	 */
	const TEST_THEME_ROOT = DIR_TESTDATA . '/themedir1';

	/**
	 * Original theme directory.
	 *
	 * @var string[]
	 */
	private $orig_theme_dir;

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

	public function set_up() {
		global $wp_theme_directories;

		parent::set_up();

		$this->orig_theme_dir = $wp_theme_directories;

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
	 * @ticket 17851
	 * @covers ::add_settings_section
	 */
	public function test_add_settings_section() {
		add_settings_section( 'test-section', 'Section title', '__return_false', 'test-page' );

		global $wp_settings_sections;
		$this->assertIsArray( $wp_settings_sections, 'List of sections is not initialized.' );
		$this->assertArrayHasKey( 'test-page', $wp_settings_sections, 'List of sections for the test page has not been added to sections list.' );
		$this->assertIsArray( $wp_settings_sections['test-page'], 'List of sections for the test page is not initialized.' );
		$this->assertArrayHasKey( 'test-section', $wp_settings_sections['test-page'], 'Test section has not been added to the list of sections for the test page.' );

		$this->assertEqualSetsWithIndex(
			array(
				'id'             => 'test-section',
				'title'          => 'Section title',
				'callback'       => '__return_false',
				'before_section' => '',
				'after_section'  => '',
				'section_class'  => '',
			),
			$wp_settings_sections['test-page']['test-section'],
			'Test section data does not match the expected dataset.'
		);
	}

	/**
	 * @ticket 17851
	 *
	 * @param array  $extra_args                   Extra arguments to pass to function `add_settings_section()`.
	 * @param array  $expected_section_data        Expected set of section data.
	 * @param string $expected_before_section_html Expected HTML markup to be rendered before the settings section.
	 * @param string $expected_after_section_html  Expected HTML markup to be rendered after the settings section.
	 *
	 * @covers ::add_settings_section
	 * @covers ::do_settings_sections
	 *
	 * @dataProvider data_extra_args_for_add_settings_section
	 */
	public function test_add_settings_section_with_extra_args( $extra_args, $expected_section_data, $expected_before_section_html, $expected_after_section_html ) {
		add_settings_section( 'test-section', 'Section title', '__return_false', 'test-page', $extra_args );
		add_settings_field( 'test-field', 'Field title', '__return_false', 'test-page', 'test-section' );

		global $wp_settings_sections;
		$this->assertIsArray( $wp_settings_sections, 'List of sections is not initialized.' );
		$this->assertArrayHasKey( 'test-page', $wp_settings_sections, 'List of sections for the test page has not been added to sections list.' );
		$this->assertIsArray( $wp_settings_sections['test-page'], 'List of sections for the test page is not initialized.' );
		$this->assertArrayHasKey( 'test-section', $wp_settings_sections['test-page'], 'Test section has not been added to the list of sections for the test page.' );

		$this->assertEqualSetsWithIndex(
			$expected_section_data,
			$wp_settings_sections['test-page']['test-section'],
			'Test section data does not match the expected dataset.'
		);

		ob_start();
		do_settings_sections( 'test-page' );
		$output = ob_get_clean();

		$this->assertStringContainsString( $expected_before_section_html, $output, 'Test page output does not contain the custom markup to be placed before the section.' );
		$this->assertStringContainsString( $expected_after_section_html, $output, 'Test page output does not contain the custom markup to be placed after the section.' );
	}

	/**
	 * Data provider for `test_add_settings_section_with_extra_args()`.
	 *
	 * @return array
	 */
	public function data_extra_args_for_add_settings_section() {
		return array(
			'class placeholder section_class present' => array(
				array(
					'before_section' => '<div class="%s">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="%s">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				'<div class="test-section-wrap">',
				'</div><!-- end of the test section -->',
			),
			'missing class placeholder section_class' => array(
				array(
					'before_section' => '<div class="testing-section-wrapper">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="testing-section-wrapper">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				'<div class="testing-section-wrapper">',
				'</div><!-- end of the test section -->',
			),
			'empty section_class'                     => array(
				array(
					'before_section' => '<div class="test-section-container">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="test-section-container">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				'<div class="test-section-container">',
				'</div><!-- end of the test section -->',
			),
			'section_class missing'                   => array(
				array(
					'before_section' => '<div class="wp-whitelabel-section">',
					'after_section'  => '</div><!-- end of the test section -->',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="wp-whitelabel-section">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				'<div class="wp-whitelabel-section">',
				'</div><!-- end of the test section -->',
			),
			'disallowed tag in before_section'        => array(
				array(
					'before_section' => '<div class="video-settings-section"><iframe src="https://www.wordpress.org/" />',
					'after_section'  => '</div><!-- end of the test section -->',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="video-settings-section"><iframe src="https://www.wordpress.org/" />',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				'<div class="video-settings-section">',
				'</div><!-- end of the test section -->',
			),
			'disallowed tag in after_section'         => array(
				array(
					'before_section' => '<div class="video-settings-section">',
					'after_section'  => '</div><iframe src="https://www.wordpress.org/" />',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="video-settings-section">',
					'after_section'  => '</div><iframe src="https://www.wordpress.org/" />',
					'section_class'  => '',
				),
				'<div class="video-settings-section">',
				'</div>',
			),
		);
	}

	public function assertTemplateHierarchy( $url, array $expected, $message = '' ) {
		$this->go_to( $url );
		$hierarchy = $this->get_template_hierarchy();

		$this->assertSame( $expected, $hierarchy, $message );
	}

	/**
	 * @ticket 58866
	 * @covers ::locate_template
	 */
	public function test_locate_template_path_for_parent_theme() {
		$this->set_up_alt_theme_root();
		$theme = wp_get_theme( 'page-templates' );
		switch_theme( $theme['Template'], $theme['Stylesheet'] );
		$file_path = locate_template( 'style.css' );
		$this->assertStringContainsString( 'themedir1/page-templates/style.css', $file_path );
		$this->tear_down_alt_theme_root();
	}

	/**
	 * @ticket 58866
	 * @covers ::locate_template
	 */
	public function test_locate_template_path_for_child_theme() {
		$this->set_up_alt_theme_root();
		$theme = wp_get_theme( 'page-templates-child' );
		switch_theme( $theme['Template'], $theme['Stylesheet'] );
		$file_path = locate_template( 'style.css' );
		$this->assertStringContainsString( 'themedir1/page-templates-child/style.css', $file_path );
		$this->tear_down_alt_theme_root();
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

	/**
	 * Switch to premade test theme directory which contains a parent and a child theme.
	 */
	public function set_up_alt_theme_root() {
		global $wp_theme_directories;
		$wp_theme_directories = array( WP_CONTENT_DIR . '/themes', self::TEST_THEME_ROOT );
		add_filter( 'theme_root', array( $this, 'filter_to_alt_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_to_alt_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_to_alt_theme_root' ) );
	}

	/**
	 * Switch back to original theme directory.
	 */
	public function tear_down_alt_theme_root() {
		global $wp_theme_directories;
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		remove_filter( 'theme_root', array( $this, 'filter_to_alt_theme_root' ) );
		remove_filter( 'stylesheet_root', array( $this, 'filter_to_alt_theme_root' ) );
		remove_filter( 'template_root', array( $this, 'filter_to_alt_theme_root' ) );
	}

	/**
	 * Replace the normal theme root directory with our premade test directory.
	 *
	 * @param string $dir Theme directory before filter.
	 */
	public function filter_to_alt_theme_root( $dir ) {
		return self::TEST_THEME_ROOT;
	}
}
