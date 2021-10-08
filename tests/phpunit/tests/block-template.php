<?php
/**
 * Block_Template_Test class
 *
 * @package WordPress
 */

/**
 * Tests for the block template loading algorithm.
 */
class Block_Template_Test extends WP_UnitTestCase {
	private static $post;

	private static $template_canvas_path = ABSPATH . WPINC . '/template-canvas.php';

	public static function wpSetUpBeforeClass() {
		// Set up custom template post.
		$args       = array(
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
		self::$post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( self::$post->ID, get_stylesheet(), 'wp_theme' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post->ID );
	}

	public function tear_down() {
		global $_wp_current_template_content;
		unset( $_wp_current_template_content );
	}

	/**
	 * Regression: https://github.com/WordPress/gutenberg/issues/31399.
	 */
	function test_custom_page_php_template_takes_precedence_over_all_other_templates() {
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
	function test_custom_page_block_template_takes_precedence_over_all_other_templates() {
		global $_wp_current_template_content;

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
		$this->assertSame( self::$post->post_content, $_wp_current_template_content );
	}

	/**
	 * Regression: https://github.com/WordPress/gutenberg/issues/31652.
	 */
	function test_template_remains_unchanged_if_templates_array_is_empty() {
		$resolved_template_path = locate_block_template( '', 'search', array() );
		$this->assertSame( '', $resolved_template_path );
	}
}
