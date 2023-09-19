<?php
/**
 * Tests for the Post Template block rendering.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.4.0
 *
 * @group blocks
 *
 * @covers ::render_block_core_post_template
 */
class Tests_Blocks_RenderPostTemplate extends WP_UnitTestCase {

	private $post;

	public function set_up() {
		parent::set_up();

		$this->post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_name'    => 'metaldog',
				'post_title'   => 'Metal Dog',
				'post_content' => 'Metal Dog content',
				'post_excerpt' => 'Metal Dog',
			)
		);
	}

	/**
	 * Tests that the `core/post-template` block triggers the main query loop when rendering within a corresponding
	 * `core/query` block.
	 *
	 * @ticket 59225
	 */
	public function test_rendering_post_template_with_main_query_loop() {
		global $wp_query, $wp_the_query;

		// Query block with post template block.
		$content  = '<!-- wp:query {"query":{"inherit":true}} -->';
		$content .= '<!-- wp:post-template {"align":"wide"} -->';
		$content .= '<!-- wp:post-title /--><!-- wp:test/in-the-loop-logger /-->';
		$content .= '<!-- /wp:post-template -->';
		$content .= '<!-- /wp:query -->';

		$expected  = '<ul class="alignwide wp-block-post-template is-layout-flow wp-block-post-template-is-layout-flow wp-block-query-is-layout-flow">';
		$expected .= '<li class="wp-block-post post-' . $this->post->ID . ' post type-post status-publish format-standard hentry category-uncategorized">';
		$expected .= '<h2 class="wp-block-post-title">' . $this->post->post_title . '</h2>';
		$expected .= '</li>';
		$expected .= '</ul>';

		// Set main query to single post.
		$wp_query     = new WP_Query( array( 'p' => $this->post->ID ) );
		$wp_the_query = $wp_query;

		// Register test block to log `in_the_loop()` results.
		$in_the_loop_logs = array();
		register_block_type(
			'test/in-the-loop-logger',
			array(
				'render_callback' => static function() use ( &$in_the_loop_logs ) {
					$in_the_loop_logs[] = in_the_loop();
					return '';
				},
			)
		);

		$output = do_blocks( $content );
		unregister_block_type( 'test/in-the-loop-logger' );
		$this->assertSame( $expected, $output, 'Unexpected parsed blocks content' );
		$this->assertSame( array( true ), $in_the_loop_logs, 'Unexpected in_the_loop() result' );
	}

	/**
	 * Tests that the `core/post-template` block does not tamper with the main query loop when rendering within a post
	 * as the main query loop has already been started. In this case, the main query object needs to be cloned to
	 * prevent an infinite loop.
	 *
	 * @ticket 59225
	 */
	public function test_rendering_post_template_with_main_query_loop_already_started() {
		global $wp_query, $wp_the_query;

		// Query block with post template block.
		$content  = '<!-- wp:query {"query":{"inherit":true}} -->';
		$content .= '<!-- wp:post-template {"align":"wide"} -->';
		$content .= '<!-- wp:post-title /-->';
		$content .= '<!-- /wp:post-template -->';
		$content .= '<!-- /wp:query -->';

		$expected  = '<ul class="alignwide wp-block-post-template is-layout-flow wp-block-post-template-is-layout-flow wp-block-query-is-layout-flow">';
		$expected .= '<li class="wp-block-post post-' . $this->post->ID . ' post type-post status-publish format-standard hentry category-uncategorized">';
		$expected .= '<h2 class="wp-block-post-title">' . $this->post->post_title . '</h2>';
		$expected .= '</li>';
		$expected .= '</ul>';

		// Update the post's content to have a query block for the same query as the main query.
		wp_update_post(
			array(
				'ID'                    => $this->post->ID,
				'post_content'          => $content,
				'post_content_filtered' => $content,
			)
		);

		// Set main query to single post.
		$wp_query     = new WP_Query( array( 'p' => $this->post->ID ) );
		$wp_the_query = $wp_query;

		// Get post content within main query loop.
		$output = '';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();

			$output = get_echo( 'the_content' );
		}

		$this->assertSame( $expected, $output, 'Unexpected parsed post content' );
	}
}
