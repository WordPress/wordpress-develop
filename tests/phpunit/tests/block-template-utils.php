<?php
/**
 * Tests for the Block Templates abstraction layer.
 *
 * @package WordPress
 *
 * @group block-templates
 */
class Tests_Block_Template_Utils extends WP_UnitTestCase {

	const TEST_THEME = 'block-theme';

	private static $template_post;
	private static $template_part_post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		/*
		 * Set up a template post corresponding to a different theme.
		 * We do this to ensure resolution and slug creation works as expected,
		 * even with another post of that same name present for another theme.
		 */
		self::$template_post = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'my_template',
				'post_title'   => 'My Template',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template',
				'tax_input'    => array(
					'wp_theme' => array(
						'this-theme-should-not-resolve',
					),
				),
			)
		);

		wp_set_post_terms( self::$template_post->ID, 'this-theme-should-not-resolve', 'wp_theme' );

		// Set up template post.
		self::$template_post = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'my_template',
				'post_title'   => 'My Template',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template',
				'tax_input'    => array(
					'wp_theme' => array(
						self::TEST_THEME,
					),
				),
			)
		);

		wp_set_post_terms( self::$template_post->ID, self::TEST_THEME, 'wp_theme' );

		// Set up template part post.
		self::$template_part_post = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template_part',
				'post_name'    => 'my_template_part',
				'post_title'   => 'My Template Part',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template part',
				'tax_input'    => array(
					'wp_theme'              => array(
						self::TEST_THEME,
					),
					'wp_template_part_area' => array(
						WP_TEMPLATE_PART_AREA_HEADER,
					),
				),
			)
		);

		wp_set_post_terms( self::$template_part_post->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
		wp_set_post_terms( self::$template_part_post->ID, self::TEST_THEME, 'wp_theme' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$template_post->ID );
	}

	public function set_up() {
		parent::set_up();
		switch_theme( self::TEST_THEME );
	}

	/**
	 * Tear down after each test.
	 *
	 * @since 6.5.0
	 */
	public function tear_down() {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'tests/hooked-block' ) ) {
			unregister_block_type( 'tests/hooked-block' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 59338
	 *
	 * @covers ::_inject_theme_attribute_in_template_part_block
	 */
	public function test_inject_theme_attribute_in_template_part_block() {
		$template_part_block = array(
			'blockName'    => 'core/template-part',
			'attrs'        => array(
				'slug'      => 'header',
				'align'     => 'full',
				'tagName'   => 'header',
				'className' => 'site-header',
			),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);

		_inject_theme_attribute_in_template_part_block( $template_part_block );
		$expected = array(
			'blockName'    => 'core/template-part',
			'attrs'        => array(
				'slug'      => 'header',
				'align'     => 'full',
				'tagName'   => 'header',
				'className' => 'site-header',
				'theme'     => get_stylesheet(),
			),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);
		$this->assertSame(
			$expected,
			$template_part_block,
			'`theme` attribute was not correctly injected in template part block.'
		);
	}

	/**
	 * @ticket 59338
	 *
	 * @covers ::_inject_theme_attribute_in_template_part_block
	 */
	public function test_not_inject_theme_attribute_in_template_part_block_theme_attribute_exists() {
		$template_part_block = array(
			'blockName'    => 'core/template-part',
			'attrs'        => array(
				'slug'      => 'header',
				'align'     => 'full',
				'tagName'   => 'header',
				'className' => 'site-header',
				'theme'     => 'fake-theme',
			),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);

		$expected = $template_part_block;
		_inject_theme_attribute_in_template_part_block( $template_part_block );
		$this->assertSame(
			$expected,
			$template_part_block,
			'Existing `theme` attribute in template part block was not respected by attribute injection.'
		);
	}

	/**
	 * @ticket 59338
	 *
	 * @covers ::_inject_theme_attribute_in_template_part_block
	 */
	public function test_not_inject_theme_attribute_non_template_part_block() {
		$non_template_part_block = array(
			'blockName'    => 'core/post-content',
			'attrs'        => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);

		$expected = $non_template_part_block;
		_inject_theme_attribute_in_template_part_block( $non_template_part_block );
		$this->assertSame(
			$expected,
			$non_template_part_block,
			'`theme` attribute injection modified non-template-part block.'
		);
	}

	/**
	 * @ticket 59452
	 *
	 * @covers ::_inject_theme_attribute_in_block_template_content
	 *
	 * @expectedDeprecated _inject_theme_attribute_in_block_template_content
	 */
	public function test_inject_theme_attribute_in_block_template_content() {
		$theme                           = get_stylesheet();
		$content_without_theme_attribute = '<!-- wp:template-part {"slug":"header","align":"full", "tagName":"header","className":"site-header"} /-->';
		$template_content                = _inject_theme_attribute_in_block_template_content(
			$content_without_theme_attribute,
			$theme
		);
		$expected                        = sprintf(
			'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->',
			get_stylesheet()
		);
		$this->assertSame( $expected, $template_content );

		$content_without_theme_attribute_nested = '<!-- wp:group --><!-- wp:template-part {"slug":"header","align":"full", "tagName":"header","className":"site-header"} /--><!-- /wp:group -->';
		$template_content                       = _inject_theme_attribute_in_block_template_content(
			$content_without_theme_attribute_nested,
			$theme
		);
		$expected                               = sprintf(
			'<!-- wp:group --><!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /--><!-- /wp:group -->',
			get_stylesheet()
		);
		$this->assertSame( $expected, $template_content );

		// Does not inject theme when there is an existing theme attribute.
		$content_with_existing_theme_attribute = '<!-- wp:template-part {"slug":"header","theme":"fake-theme","align":"full", "tagName":"header","className":"site-header"} /-->';
		$template_content                      = _inject_theme_attribute_in_block_template_content(
			$content_with_existing_theme_attribute,
			$theme
		);
		$this->assertSame( $content_with_existing_theme_attribute, $template_content );

		// Does not inject theme when there is no template part.
		$content_with_no_template_part = '<!-- wp:post-content /-->';
		$template_content              = _inject_theme_attribute_in_block_template_content(
			$content_with_no_template_part,
			$theme
		);
		$this->assertSame( $content_with_no_template_part, $template_content );
	}

	/**
	 * @ticket 54448
	 * @ticket 59460
	 *
	 * @dataProvider data_remove_theme_attribute_in_block_template_content
	 *
	 * @expectedDeprecated _remove_theme_attribute_in_block_template_content
	 */
	public function test_remove_theme_attribute_in_block_template_content( $template_content, $expected ) {
		$this->assertSame( $expected, _remove_theme_attribute_in_block_template_content( $template_content ) );
	}

	/**
	 * @ticket 59460
	 *
	 * @covers ::_remove_theme_attribute_from_template_part_block
	 * @covers ::traverse_and_serialize_blocks
	 *
	 * @dataProvider data_remove_theme_attribute_in_block_template_content
	 *
	 * @param string $template_content The template markup.
	 * @param string $expected         The expected markup after removing the theme attribute from Template Part blocks.
	 */
	public function test_remove_theme_attribute_from_template_part_block( $template_content, $expected ) {
		$template_content_parsed_blocks = parse_blocks( $template_content );

		$this->assertSame(
			$expected,
			traverse_and_serialize_blocks(
				$template_content_parsed_blocks,
				'_remove_theme_attribute_from_template_part_block'
			)
		);
	}

	public function data_remove_theme_attribute_in_block_template_content() {
		return array(
			array(
				'<!-- wp:template-part {"slug":"header","theme":"tt1-blocks","align":"full","tagName":"header","className":"site-header"} /-->',
				'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
			),
			array(
				'<!-- wp:group --><!-- wp:template-part {"slug":"header","theme":"tt1-blocks","align":"full","tagName":"header","className":"site-header"} /--><!-- /wp:group -->',
				'<!-- wp:group --><!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /--><!-- /wp:group -->',
			),
			// Does not modify content when there is no existing theme attribute.
			array(
				'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
				'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
			),
			// Does not remove theme when there is no template part.
			array(
				'<!-- wp:post-content /-->',
				'<!-- wp:post-content /-->',
			),
		);
	}

	/**
	 * Tests that the skip link is added and a missing main ID is created.
	 *
	 * @ticket 64361
	 *
	 * @covers ::_block_template_skip_link_markup
	 */
	public function test_block_template_skip_link_inserts_link_and_adds_main_id_when_missing() {
		global $_wp_current_template_content;

		$previous_template_content = null;
		if ( isset( $_wp_current_template_content ) ) {
			$previous_template_content = $_wp_current_template_content;
		}

		$_wp_current_template_content = 'Template content.';

		$has_existing_hook           = has_action( 'wp_footer', 'the_block_template_skip_link' );
		$had_block_templates_support = current_theme_supports( 'block-templates' );
		if ( ! $has_existing_hook ) {
			add_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			add_theme_support( 'block-templates' );
		}

		$template_html = '<div class="wp-site-blocks"><main>Content</main></div>';
		$result        = _block_template_skip_link_markup( $template_html );

		if ( ! $has_existing_hook ) {
			remove_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			remove_theme_support( 'block-templates' );
		}

		if ( null === $previous_template_content ) {
			unset( $_wp_current_template_content );
		} else {
			$_wp_current_template_content = $previous_template_content;
		}

		$this->assertNotSame( $template_html, $result, 'Skip link markup was not added.' );
		$this->assertStringContainsString( 'id="wp--skip-link--target"', $result, 'Main element ID was not added.' );
		$this->assertStringContainsString( 'href="#wp--skip-link--target"', $result, 'Skip link does not point to the expected target.' );
	}

	/**
	 * Tests that an existing main ID is preserved and used by the skip link.
	 *
	 * @ticket 64361
	 *
	 * @covers ::_block_template_skip_link_markup
	 */
	public function test_block_template_skip_link_uses_existing_main_id() {
		global $_wp_current_template_content;

		$previous_template_content = null;
		if ( isset( $_wp_current_template_content ) ) {
			$previous_template_content = $_wp_current_template_content;
		}

		$_wp_current_template_content = 'Template content.';

		$has_existing_hook           = has_action( 'wp_footer', 'the_block_template_skip_link' );
		$had_block_templates_support = current_theme_supports( 'block-templates' );
		if ( ! $has_existing_hook ) {
			add_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			add_theme_support( 'block-templates' );
		}

		$template_html = '<div class="wp-site-blocks"><main id="custom-id">Content</main></div>';
		$result        = _block_template_skip_link_markup( $template_html );

		if ( ! $has_existing_hook ) {
			remove_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			remove_theme_support( 'block-templates' );
		}

		if ( null === $previous_template_content ) {
			unset( $_wp_current_template_content );
		} else {
			$_wp_current_template_content = $previous_template_content;
		}

		$this->assertStringContainsString( 'id="custom-id"', $result, 'Existing main element ID was not preserved.' );
		$this->assertStringContainsString( 'href="#custom-id"', $result, 'Skip link does not point to the existing main element ID.' );
		$this->assertStringNotContainsString( 'wp--skip-link--target', $result, 'Unexpected default skip link target ID was added.' );
	}

	/**
	 * Tests that no skip link is added when there is no main element.
	 *
	 * @ticket 64361
	 *
	 * @covers ::_block_template_skip_link_markup
	 */
	public function test_block_template_skip_link_not_inserted_when_main_missing() {
		global $_wp_current_template_content;

		$previous_template_content = null;
		if ( isset( $_wp_current_template_content ) ) {
			$previous_template_content = $_wp_current_template_content;
		}

		$_wp_current_template_content = 'Template content.';

		$has_existing_hook           = has_action( 'wp_footer', 'the_block_template_skip_link' );
		$had_block_templates_support = current_theme_supports( 'block-templates' );
		if ( ! $has_existing_hook ) {
			add_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			add_theme_support( 'block-templates' );
		}

		$template_html = '<div class="wp-site-blocks"><div>Content</div></div>';
		$result        = _block_template_skip_link_markup( $template_html );

		if ( ! $has_existing_hook ) {
			remove_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			remove_theme_support( 'block-templates' );
		}

		if ( null === $previous_template_content ) {
			unset( $_wp_current_template_content );
		} else {
			$_wp_current_template_content = $previous_template_content;
		}

		$this->assertSame( $template_html, $result, 'Skip link markup should not be added when there is no main element.' );
	}

	/**
	 * Tests that an existing skip link anchor is not duplicated.
	 *
	 * @ticket 64361
	 *
	 * @covers ::_block_template_skip_link_markup
	 */
	public function test_block_template_skip_link_not_duplicated_when_existing_anchor_present() {
		global $_wp_current_template_content;

		$previous_template_content = null;
		if ( isset( $_wp_current_template_content ) ) {
			$previous_template_content = $_wp_current_template_content;
		}

		$_wp_current_template_content = 'Template content.';

		$has_existing_hook           = has_action( 'wp_footer', 'the_block_template_skip_link' );
		$had_block_templates_support = current_theme_supports( 'block-templates' );
		if ( ! $has_existing_hook ) {
			add_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			add_theme_support( 'block-templates' );
		}

		$template_html = '<a class="skip-link screen-reader-text" id="wp-skip-link" href="#existing-main">Skip to content</a><div class="wp-site-blocks"><main id="existing-main">Content</main></div>';
		$result        = _block_template_skip_link_markup( $template_html );

		if ( ! $has_existing_hook ) {
			remove_action( 'wp_footer', 'the_block_template_skip_link' );
		}
		if ( ! $had_block_templates_support ) {
			remove_theme_support( 'block-templates' );
		}

		if ( null === $previous_template_content ) {
			unset( $_wp_current_template_content );
		} else {
			$_wp_current_template_content = $previous_template_content;
		}

		$this->assertSame( $template_html, $result, 'Existing skip link anchor should be preserved and not duplicated.' );
	}

	/**
	 * Should retrieve the template from the theme files.
	 */
	public function test_get_block_template_from_file() {
		$id       = get_stylesheet() . '//' . 'index';
		$template = get_block_template( $id, 'wp_template' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'index', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'theme', $template->source );
		$this->assertSame( 'wp_template', $template->type );

		// Test template parts.
		$id       = get_stylesheet() . '//' . 'small-header';
		$template = get_block_template( $id, 'wp_template_part' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'small-header', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'theme', $template->source );
		$this->assertSame( 'wp_template_part', $template->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template->area );
	}

	/**
	 * Should retrieve the template from the CPT.
	 */
	public function test_get_block_template_from_post() {
		$id       = get_stylesheet() . '//' . 'my_template';
		$template = get_block_template( $id, 'wp_template' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'wp_template', $template->type );

		// Test template parts.
		$id       = get_stylesheet() . '//' . 'my_template_part';
		$template = get_block_template( $id, 'wp_template_part' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template_part', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'wp_template_part', $template->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template->area );
	}

	/**
	 * Should flatten nested blocks
	 */
	public function test_flatten_blocks() {
		$content_template_part_inside_group = '<!-- wp:group --><!-- wp:template-part {"slug":"header"} /--><!-- /wp:group -->';
		$blocks                             = parse_blocks( $content_template_part_inside_group );
		$actual                             = _flatten_blocks( $blocks );
		$expected                           = array( $blocks[0], $blocks[0]['innerBlocks'][0] );
		$this->assertSame( $expected, $actual );

		$content_template_part_inside_group_inside_group = '<!-- wp:group --><!-- wp:group --><!-- wp:template-part {"slug":"header"} /--><!-- /wp:group --><!-- /wp:group -->';
		$blocks   = parse_blocks( $content_template_part_inside_group_inside_group );
		$actual   = _flatten_blocks( $blocks );
		$expected = array( $blocks[0], $blocks[0]['innerBlocks'][0], $blocks[0]['innerBlocks'][0]['innerBlocks'][0] );
		$this->assertSame( $expected, $actual );

		$content_without_inner_blocks = '<!-- wp:group /-->';
		$blocks                       = parse_blocks( $content_without_inner_blocks );
		$actual                       = _flatten_blocks( $blocks );
		$expected                     = array( $blocks[0] );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Should generate block templates export file.
	 *
	 * @ticket 54448
	 * @requires extension zip
	 */
	public function test_wp_generate_block_templates_export_file() {
		$filename = wp_generate_block_templates_export_file();
		$this->assertFileExists( $filename, 'zip file is created at the specified path' );
		$this->assertGreaterThan( 0, filesize( $filename ), 'zip file is larger than 0 bytes' );

		// Open ZIP file and make sure the directories exist.
		$zip = new ZipArchive();
		$zip->open( $filename );
		$has_theme_json               = $zip->locateName( 'theme.json' ) !== false;
		$has_block_templates_dir      = $zip->locateName( 'templates/' ) !== false;
		$has_block_template_parts_dir = $zip->locateName( 'parts/' ) !== false;
		$this->assertTrue( $has_theme_json, 'theme.json exists' );
		$this->assertTrue( $has_block_templates_dir, 'theme/templates directory exists' );
		$this->assertTrue( $has_block_template_parts_dir, 'theme/parts directory exists' );

		// ZIP file contains at least one HTML file.
		$has_html_files = false;
		$num_files      = $zip->numFiles;
		for ( $i = 0; $i < $num_files; $i++ ) {
			$filename = $zip->getNameIndex( $i );
			if ( '.html' === substr( $filename, -5 ) ) {
				$has_html_files = true;
				break;
			}
		}
		$this->assertTrue( $has_html_files, 'contains at least one html file' );
	}
}
