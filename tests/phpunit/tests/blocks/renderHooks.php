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
			<!-- wp:core/paragraph --><p>WordPress!</p><!-- /wp:core/paragraph -->
		';

		add_filter( 'do_blocks_pre_render', array( $this, 'do_blocks_pre_render_filter' ) );

		$result = do_blocks( $block_content );

		$this->assertSame( '<p>Hello</p>', trim( $result ) );

		remove_filter( 'do_blocks_pre_render', array( $this, 'do_blocks_pre_render_filter' ) );
	}
	public function do_blocks_pre_render_filter( $blocks ) {
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
			<!-- wp:core/paragraph --><p>WordPress!</p><!-- /wp:core/paragraph -->
		';

		add_filter( 'do_blocks_post_render', array( $this, 'do_blocks_post_render_filter' ) );

		$result = do_blocks( $block_content );

		$this->assertSame( '<p>Hello</p>', trim( $result ) );

		remove_filter( 'do_blocks_post_render', array( $this, 'do_blocks_post_render_filter' ) );
	}
	public function do_blocks_post_render_filter( $content ) {
		$content = str_replace( '<p>WordPress!</p>', '', $content );
		return $content;
	}
}
