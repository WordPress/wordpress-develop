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
		$remove_blocks_containing_wordpress = static function ( $blocks ) {
			foreach ( $blocks as $index => $block ) {
				if ( str_contains( $block['innerHTML'], 'WordPress' ) ) {
					unset( $blocks[ $index ] );
				}
			}
			return $blocks;
		};

		$hello_block = '<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->';
		$press_block = '<!-- wp:core/paragraph --><p>WordPress</p><!-- /wp:core/paragraph -->';

		add_filter( 'do_blocks_pre_render', $remove_blocks_containing_wordpress );
		$filtered_output = do_blocks( $hello_block . $press_block );
		remove_filter( 'do_blocks_pre_render', $remove_blocks_containing_wordpress );

		$wanted_output = do_blocks( $hello_block );
		$this->assertSame( $wanted_output, $filtered_output, 'Did not exclude the intended blocks before rendering.' );
	}

	/**
	 * @ticket 59131
	 */
	public function test_do_blocks_post_render_filter() {
		$remove_wordpress_paragraph = static function ( $content ) {
			$content = str_replace( '<p>WordPress</p>', '', $content );
			return $content;
		};

		$hello_block = '<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->';
		$press_block = '<!-- wp:core/paragraph --><p>WordPress</p><!-- /wp:core/paragraph -->';

		add_filter( 'do_blocks_post_render', $remove_wordpress_paragraph );
		$filtered_output = do_blocks( $hello_block . $press_block );
		remove_filter( 'do_blocks_post_render', $remove_wordpress_paragraph );

		$wanted_output = do_blocks( $hello_block );
		$this->assertSame( $wanted_output, $filtered_output, 'Did not exclude the intended content before rendering.' );
	}
}
