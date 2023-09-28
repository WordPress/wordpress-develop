<?php
/**
 * Tests for the hooks of the block rendering functions.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.4.0
 *
 * @group blocks
 */
class Tests_Blocks_Render_Hooks extends WP_UnitTestCase {
	/**
	 * @ticket 59131
	 */
	public function test_do_blocks_pre_render_filter() {
		$block_content = '
			<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->
			<!-- wp:core/paragraph --><p>WordPress</p><!-- /wp:core/paragraph -->
		';

		add_filter( 'do_blocks_pre_render', array( $this, 'remove_blocks_containing_wordpress' ) );

		$result = do_blocks( $block_content );

		remove_filter( 'do_blocks_pre_render', array( $this, 'remove_blocks_containing_wordpress' ) );

		$this->assertSame( '<p>Hello</p>', trim( $result ) );
	}
	public function remove_blocks_containing_wordpress( $blocks ) {
		foreach ( $blocks as $index => $block ) {
			if ( strpos( $block['innerHTML'], 'WordPress' ) !== false ) {
				unset( $blocks[ $index ] );
			}
		}
		return $blocks;
	}

	/**
	 * @ticket 59131
	 */
	public function test_do_blocks_post_render_filter() {
		$block_content = '
			<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->
			<!-- wp:core/paragraph --><p>WordPress</p><!-- /wp:core/paragraph -->
		';

		add_filter( 'do_blocks_post_render', array( $this, 'remove_wordpress_paragraph' ) );

		$result = do_blocks( $block_content );

		remove_filter( 'do_blocks_post_render', array( $this, 'remove_wordpress_paragraph' ) );

		$this->assertSame( '<p>Hello</p>', trim( $result ) );
	}
	public function remove_wordpress_paragraph( $content ) {
		$content = str_replace( '<p>WordPress</p>', '', $content );
		return $content;
	}
}
