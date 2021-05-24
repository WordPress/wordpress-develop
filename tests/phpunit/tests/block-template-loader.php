<?php
/**
 * REST API: Block_Template_Loader_Test class
 *
 * @package    WordPress
 * @subpackage REST_API
 */

/**
 * Tests for the Block Template Loader abstraction layer.
 */
class Block_Template_Loader_Test extends WP_UnitTestCase {
	private static $post;

	public static function wpSetUpBeforeClass() {
		switch_theme( 'block-template-theme' );

		// Set up a template post corresponding to a different theme.
		// We do this to ensure resolution and slug creation works as expected,
		// even with another post of that same name present for another theme.
		$args       = array(
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
		);
		self::$post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( self::$post->ID, 'this-theme-should-not-resolve', 'wp_theme' );

		// Set up template post.
		$args       = array(
			'post_type'    => 'wp_template',
			'post_name'    => 'my_template',
			'post_title'   => 'My Template',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my template',
			'tax_input'    => array(
				'wp_theme' => array(
					get_stylesheet(),
				),
			),
		);
		self::$post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( self::$post->ID, get_stylesheet(), 'wp_theme' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post->ID );
	}

	function test_gutenberg_build_template_result_from_file() {
		$template = _gutenberg_build_template_result_from_file(
			array(
				'slug' => 'single',
				'path' => get_stylesheet_directory() . '/block-templates/index.html',
			),
			'wp_template'
		);

		$this->assertEquals( get_stylesheet() . '//single', $template->id );
		$this->assertEquals( get_stylesheet(), $template->theme );
		$this->assertEquals( 'single', $template->slug );
		$this->assertEquals( 'publish', $template->status );
		$this->assertEquals( 'theme', $template->source );
		$this->assertEquals( 'Single Post', $template->title );
		$this->assertEquals( 'Template used to display a single blog post.', $template->description );
		$this->assertEquals( 'wp_template', $template->type );
	}

	function test_gutenberg_build_template_result_from_post() {
		$template = _gutenberg_build_template_result_from_post(
			self::$post,
			'wp_template'
		);

		$this->assertNotWPError( $template );
		$this->assertEquals( get_stylesheet() . '//my_template', $template->id );
		$this->assertEquals( get_stylesheet(), $template->theme );
		$this->assertEquals( 'my_template', $template->slug );
		$this->assertEquals( 'publish', $template->status );
		$this->assertEquals( 'custom', $template->source );
		$this->assertEquals( 'My Template', $template->title );
		$this->assertEquals( 'Description of my template', $template->description );
		$this->assertEquals( 'wp_template', $template->type );
	}

	/**
	 * Should retrieve the template from the theme files.
	 */
	function test_gutenberg_get_block_template_from_file() {
		$id       = get_stylesheet() . '//' . 'index';
		$template = gutenberg_get_block_template( $id, 'wp_template' );
		$this->assertEquals( $id, $template->id );
		$this->assertEquals( get_stylesheet(), $template->theme );
		$this->assertEquals( 'index', $template->slug );
		$this->assertEquals( 'publish', $template->status );
		$this->assertEquals( 'theme', $template->source );
		$this->assertEquals( 'wp_template', $template->type );
	}

	/**
	 * Should retrieve the template from the CPT.
	 */
	function test_gutenberg_get_block_template_from_post() {
		$id       = get_stylesheet() . '//' . 'my_template';
		$template = gutenberg_get_block_template( $id, 'wp_template' );
		$this->assertEquals( $id, $template->id );
		$this->assertEquals( get_stylesheet(), $template->theme );
		$this->assertEquals( 'my_template', $template->slug );
		$this->assertEquals( 'publish', $template->status );
		$this->assertEquals( 'custom', $template->source );
		$this->assertEquals( 'wp_template', $template->type );
	}

	/**
	 * Should retrieve block templates (file and CPT)
	 */
	function test_gutenberg_get_block_templates() {
		function get_template_ids( $templates ) {
			return array_map(
				function( $template ) {
					return $template->id;
				},
				$templates
			);
		}

		// All results.
		$templates    = gutenberg_get_block_templates( array(), 'wp_template' );
		$template_ids = get_template_ids( $templates );

		// Avoid testing the entire array because the theme might add/remove templates.
		$this->assertContains( get_stylesheet() . '//' . 'my_template', $template_ids );
		$this->assertContains( get_stylesheet() . '//' . 'index', $template_ids );

		// Filter by slug.
		$templates    = gutenberg_get_block_templates( array( 'slug__in' => array( 'my_template' ) ), 'wp_template' );
		$template_ids = get_template_ids( $templates );
		$this->assertEquals( array( get_stylesheet() . '//' . 'my_template' ), $template_ids );

		// Filter by CPT ID.
		$templates    = gutenberg_get_block_templates( array( 'wp_id' => self::$post->ID ), 'wp_template' );
		$template_ids = get_template_ids( $templates );
		$this->assertEquals( array( get_stylesheet() . '//' . 'my_template' ), $template_ids );
	}

	/**
	 * Should flatten nested blocks
	 */
	function test_flatten_blocks() {
		$content_template_part_inside_group = '<!-- wp:group --><!-- wp:template-part {"slug":"header"} /--><!-- /wp:group -->';
		$blocks                             = parse_blocks( $content_template_part_inside_group );
		$actual                             = _flatten_blocks( $blocks );
		$expected                           = array( $blocks[0], $blocks[0]['innerBlocks'][0] );
		$this->assertEquals( $expected, $actual );

		$content_template_part_inside_group_inside_group = '<!-- wp:group --><!-- wp:group --><!-- wp:template-part {"slug":"header"} /--><!-- /wp:group --><!-- /wp:group -->';
		$blocks   = parse_blocks( $content_template_part_inside_group_inside_group );
		$actual   = _flatten_blocks( $blocks );
		$expected = array( $blocks[0], $blocks[0]['innerBlocks'][0], $blocks[0]['innerBlocks'][0]['innerBlocks'][0] );
		$this->assertEquals( $expected, $actual );

		$content_without_inner_blocks = '<!-- wp:group /-->';
		$blocks                       = parse_blocks( $content_without_inner_blocks );
		$actual                       = _flatten_blocks( $blocks );
		$expected                     = array( $blocks[0] );
		$this->assertEquals( $expected, $actual );
	}
}
