<?php
/**
 * Block_Template_Utils_Test class
 *
 * @package    WordPress
 */

/**
 * Tests for the Block Template Loader abstraction layer.
 */
class Block_Template_Utils_Test extends WP_UnitTestCase {
	private static $post;

	public static function wpSetUpBeforeClass() {
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

	function test_build_template_result_from_post() {
		$template = _build_template_result_from_post(
			self::$post,
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
	}

	/**
	 * Should retrieve the template from the CPT.
	 */
	function test_get_block_template_from_post() {
		$id       = get_stylesheet() . '//' . 'my_template';
		$template = get_block_template( $id, 'wp_template' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'wp_template', $template->type );
	}

	/**
	 * Should retrieve block templates.
	 */
	function test_get_block_templates() {
		function get_template_ids( $templates ) {
			return array_map(
				static function( $template ) {
					return $template->id;
				},
				$templates
			);
		}

		// All results.
		$templates    = get_block_templates( array(), 'wp_template' );
		$template_ids = get_template_ids( $templates );

		// Avoid testing the entire array because the theme might add/remove templates.
		$this->assertContains( get_stylesheet() . '//' . 'my_template', $template_ids );

		// Filter by slug.
		$templates    = get_block_templates( array( 'slug__in' => array( 'my_template' ) ), 'wp_template' );
		$template_ids = get_template_ids( $templates );
		$this->assertSame( array( get_stylesheet() . '//' . 'my_template' ), $template_ids );

		// Filter by CPT ID.
		$templates    = get_block_templates( array( 'wp_id' => self::$post->ID ), 'wp_template' );
		$template_ids = get_template_ids( $templates );
		$this->assertSame( array( get_stylesheet() . '//' . 'my_template' ), $template_ids );
	}
}
