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

	public function tear_down() {
		parent::tear_down();

		unset( $GLOBALS['_wp_tests_development_mode'] );
	}

	public function test_build_block_template_result_from_post() {
		$template = _build_block_template_result_from_post(
			self::$template_post,
			'wp_template'
		);

		$this->assertNotWPError( $template );
		$this->assertSame( get_stylesheet() . '//my_template', $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'My Template', $template->title );
		$this->assertSame( 'Description of my template', $template->description );
		$this->assertSame( 'wp_template', $template->type );
		$this->assertSame( self::$template_post->post_modified, $template->modified, 'Template result properties match' );

		// Test template parts.
		$template_part = _build_block_template_result_from_post(
			self::$template_part_post,
			'wp_template_part'
		);
		$this->assertNotWPError( $template_part );
		$this->assertSame( get_stylesheet() . '//my_template_part', $template_part->id );
		$this->assertSame( get_stylesheet(), $template_part->theme );
		$this->assertSame( 'my_template_part', $template_part->slug );
		$this->assertSame( 'publish', $template_part->status );
		$this->assertSame( 'custom', $template_part->source );
		$this->assertSame( 'My Template Part', $template_part->title );
		$this->assertSame( 'Description of my template part', $template_part->description );
		$this->assertSame( 'wp_template_part', $template_part->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template_part->area );
		$this->assertSame( self::$template_part_post->post_modified, $template_part->modified, 'Template part result properties match' );
	}

	public function test_build_block_template_result_from_file() {
		$template = _build_block_template_result_from_file(
			array(
				'slug' => 'single',
				'path' => __DIR__ . '/../data/templates/template.html',
			),
			'wp_template'
		);

		$this->assertSame( get_stylesheet() . '//single', $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'single', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'theme', $template->source );
		$this->assertSame( 'Single Posts', $template->title );
		$this->assertSame( 'Displays single posts on your website unless a custom template has been applied to that post or a dedicated template exists.', $template->description );
		$this->assertSame( 'wp_template', $template->type );
		$this->assertEmpty( $template->modified );

		// Test template parts.
		$template_part = _build_block_template_result_from_file(
			array(
				'slug' => 'header',
				'path' => __DIR__ . '/../data/templates/template.html',
				'area' => WP_TEMPLATE_PART_AREA_HEADER,
			),
			'wp_template_part'
		);
		$this->assertSame( get_stylesheet() . '//header', $template_part->id );
		$this->assertSame( get_stylesheet(), $template_part->theme );
		$this->assertSame( 'header', $template_part->slug );
		$this->assertSame( 'publish', $template_part->status );
		$this->assertSame( 'theme', $template_part->source );
		$this->assertSame( 'header', $template_part->title );
		$this->assertSame( '', $template_part->description );
		$this->assertSame( 'wp_template_part', $template_part->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template_part->area );
		$this->assertEmpty( $template_part->modified );
	}

	/**
	 * @ticket 59325
	 *
	 * @covers ::_build_block_template_result_from_file
	 *
	 * @dataProvider data_build_block_template_result_from_file_injects_theme_attribute
	 *
	 * @param string $filename The template's filename.
	 * @param string $expected The expected block markup.
	 */
	public function test_build_block_template_result_from_file_injects_theme_attribute( $filename, $expected ) {
		$template = _build_block_template_result_from_file(
			array(
				'slug' => 'single',
				'path' => DIR_TESTDATA . "/templates/$filename",
			),
			'wp_template'
		);
		$this->assertSame( $expected, $template->content );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_build_block_template_result_from_file_injects_theme_attribute() {
		$theme = 'block-theme';
		return array(
			'a template with a template part block'  => array(
				'filename' => 'template-with-template-part.html',
				'expected' => sprintf(
					'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->',
					$theme
				),
			),
			'a template with a template part block nested inside another block' => array(
				'filename' => 'template-with-nested-template-part.html',
				'expected' => sprintf(
					'<!-- wp:group -->
<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->
<!-- /wp:group -->',
					$theme
				),
			),
			'a template with a template part block with an existing theme attribute' => array(
				'filename' => 'template-with-template-part-with-existing-theme-attribute.html',
				'expected' => '<!-- wp:template-part {"slug":"header","theme":"fake-theme","align":"full","tagName":"header","className":"site-header"} /-->',
			),
			'a template with no template part block' => array(
				'filename' => 'template.html',
				'expected' => '<!-- wp:paragraph -->
<p>Just a paragraph</p>
<!-- /wp:paragraph -->',
			),
		);
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
		$this->assertTrue( filesize( $filename ) > 0, 'zip file is larger than 0 bytes' );

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

	/**
	 * Tests `_get_block_template_file_content()` with a file that is part of the current theme.
	 *
	 * This should store the file content in cache.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_current_theme_file() {
		switch_theme( 'block-theme' );

		$template_file = DIR_TESTDATA . '/themedir1/block-theme/parts/small-header.html';

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( file_get_contents( $template_file ), $content, 'Unexpected file content' );

		$cache_result = get_transient( 'wp_theme_template_contents_block-theme' );
		$this->assertArrayHasKey( 'template_content', $cache_result, 'Invalid cache value' );
		$this->assertArrayHasKey( 'parts/small-header.html', $cache_result['template_content'], 'File not set in cache' );
		$this->assertSame( $content, $cache_result['template_content']['parts/small-header.html'], 'File has incorrect content in cache' );
	}

	/**
	 * Tests `_get_block_template_file_content()` with a file that is part of the current parent theme.
	 *
	 * This should store the file content in cache.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_current_parent_theme_file() {
		switch_theme( 'block-theme-child' );

		$template_file = DIR_TESTDATA . '/themedir1/block-theme/templates/index.html';

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( file_get_contents( $template_file ), $content, 'Unexpected file content' );

		$cache_result = get_transient( 'wp_theme_template_contents_block-theme' );
		$this->assertArrayHasKey( 'template_content', $cache_result, 'Invalid cache value' );
		$this->assertArrayHasKey( 'templates/index.html', $cache_result['template_content'], 'File not set in cache' );
		$this->assertSame( $content, $cache_result['template_content']['templates/index.html'], 'File has incorrect content in cache' );
	}

	/**
	 * Tests `_get_block_template_file_content()` with a file that is not part of the current theme.
	 *
	 * This should not set any cache.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_another_theme_file() {
		switch_theme( 'block-theme-patterns' );

		$template_file = DIR_TESTDATA . '/themedir1/block-theme-child/templates/page-1.html';

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( file_get_contents( $template_file ), $content, 'Unexpected file content' );

		$cache_result = get_transient( 'wp_theme_template_contents_block-theme-patterns' );
		$this->assertFalse( $cache_result, 'Cache unexpectedly set for current theme' );

		$cache_result = get_transient( 'wp_theme_template_contents_block-theme' );
		$this->assertFalse( $cache_result, 'Cache unexpectedly set for current parent theme' );

		$cache_result = get_transient( 'wp_theme_template_contents_block-theme-child' );
		$this->assertFalse( $cache_result, 'Cache unexpectedly set for non-current theme' );
	}

	/**
	 * Tests `_get_block_template_file_content()` with a file that is part of the current theme while using 'theme' development mode.
	 *
	 * This should not set any cache.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_current_theme_file_and_theme_development_mode() {
		global $_wp_tests_development_mode;

		$_wp_tests_development_mode = 'theme';

		switch_theme( 'block-theme' );

		$template_file = DIR_TESTDATA . '/themedir1/block-theme/parts/small-header.html';

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( file_get_contents( $template_file ), $content, 'Unexpected file content' );

		$cache_result = get_transient( 'wp_theme_template_contents_block-theme' );
		$this->assertFalse( $cache_result, 'Cache unexpectedly set despite theme development mode' );
	}

	/**
	 * Tests `_get_block_template_file_content()` with files that are part of the current theme expands the existing cache.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_expands_existing_cache() {
		switch_theme( 'block-theme' );

		$template_file1 = DIR_TESTDATA . '/themedir1/block-theme/parts/small-header.html';
		$template_file2 = DIR_TESTDATA . '/themedir1/block-theme/templates/index.html';
		$template_file3 = DIR_TESTDATA . '/themedir1/block-theme/templates/page-home.html';

		$content1 = _get_block_template_file_content( $template_file1 );
		$content2 = _get_block_template_file_content( $template_file2 );
		$content3 = _get_block_template_file_content( $template_file3 );

		$cache_result = get_transient( 'wp_theme_template_contents_block-theme' );
		$this->assertSame(
			array(
				'version'          => '1.0.0',
				'template_content' => array(
					'parts/small-header.html'  => $content1,
					'templates/index.html'     => $content2,
					'templates/page-home.html' => $content3,
				),
			),
			$cache_result
		);
	}

	/**
	 * Tests `_get_block_template_file_content()` with a file that is part of the current theme relies on cached values.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_current_theme_file_relies_on_cache() {
		switch_theme( 'block-theme' );

		$template_file  = DIR_TESTDATA . '/themedir1/block-theme/parts/small-header.html';
		$forced_content = '<div>Some cache content that is not actually the file content.</div>';
		set_transient(
			'wp_theme_template_contents_block-theme',
			array(
				'version'          => '1.0.0',
				'template_content' => array(
					'parts/small-header.html' => $forced_content,
				),
			)
		);

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( $forced_content, $content );
	}

	/**
	 * Tests `_get_block_template_file_content()` with a file that is part of the another theme ignores cached values.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_another_theme_file_ignores_cache() {
		switch_theme( 'block-theme' );

		$template_file  = DIR_TESTDATA . '/themedir1/block-theme-child/templates/page-1.html';
		$forced_content = '<div>Some cache content that is not actually the file content.</div>';
		set_transient(
			'wp_theme_template_contents_block-theme-child',
			array(
				'version'          => '1.0.0',
				'template_content' => array(
					'templates/page-1.html' => $forced_content,
				),
			)
		);

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( file_get_contents( $template_file ), $content );
	}

	/**
	 * Tests `_get_block_template_file_content()` with a file that is part of the current theme refreshes existing cache when version is outdated.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_current_theme_file_refreshes_cache_when_version_outdated() {
		switch_theme( 'block-theme' );

		$template_file  = DIR_TESTDATA . '/themedir1/block-theme/parts/small-header.html';
		$forced_content = '<div>Some cache content that is not actually the file content.</div>';
		set_transient(
			'wp_theme_template_contents_block-theme',
			array(
				'version'          => '0.9.0',
				'template_content' => array(
					'parts/small-header.html' => $forced_content,
				),
			)
		);

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( file_get_contents( $template_file ), $content, 'Cached file content unexpectedly returned' );

		$this->assertSame(
			array(
				'version'          => '1.0.0',
				'template_content' => array(
					'parts/small-header.html' => $content,
				),
			),
			get_transient( 'wp_theme_template_contents_block-theme' ),
			'Cached transient was not updated'
		);
	}

	/**
	 * Tests `_get_block_template_file_content()` with a file that is part of the current theme ignores cached values while using 'theme' development mode.
	 *
	 * @ticket 59600
	 *
	 * @covers ::_get_block_template_file_content
	 */
	public function test_get_block_template_file_content_with_current_theme_file_and_theme_development_mode_ignores_cache() {
		global $_wp_tests_development_mode;

		$_wp_tests_development_mode = 'theme';

		switch_theme( 'block-theme' );

		$template_file  = DIR_TESTDATA . '/themedir1/block-theme/parts/small-header.html';
		$forced_content = '<div>Some cache content that is not actually the file content.</div>';
		set_transient(
			'wp_theme_template_contents_block-theme',
			array(
				'version'          => '1.0.0',
				'template_content' => array(
					'parts/small-header.html' => $forced_content,
				),
			)
		);

		$content = _get_block_template_file_content( $template_file );
		$this->assertSame( file_get_contents( $template_file ), $content );
	}
}
