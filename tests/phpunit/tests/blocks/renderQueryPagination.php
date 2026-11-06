<?php
/**
 * Tests for the server-side rendering of the Query Pagination block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_query_pagination
 */
class Tests_Blocks_RenderQueryPagination extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Tests that the content is wrapped in a navigation with a label.
	 *
	 * @ticket 66570
	 */
	public function test_should_wrap_the_content_in_a_labelled_navigation() {
		$this->assertSame(
			'<nav aria-label="Pagination"><a href="#">Next</a></nav>',
			render_block_core_query_pagination( array(), '<a href="#">Next</a>' )
		);
	}

	/**
	 * Tests that nothing is rendered without content.
	 *
	 * @ticket 66570
	 *
	 * @dataProvider data_empty_content
	 *
	 * @param string $content Content of the block.
	 */
	public function test_should_render_nothing_without_content( $content ) {
		$this->assertSame( '', render_block_core_query_pagination( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_content() {
		return array(
			'an empty string'    => array( '' ),
			'spaces'             => array( '   ' ),
			'tabs and new lines' => array( "\t\n " ),
		);
	}

	/**
	 * Tests the class for the color of the links.
	 *
	 * @ticket 66570
	 */
	public function test_should_add_the_class_for_the_color_of_the_links() {
		$attributes = array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) );

		$this->assertSame(
			'<nav class="has-link-color" aria-label="Pagination"><a href="#">Next</a></nav>',
			render_block_core_query_pagination( $attributes, '<a href="#">Next</a>' )
		);
	}

	/**
	 * Tests that the class for the color of the links is not added without a color.
	 *
	 * @ticket 66570
	 */
	public function test_should_not_add_the_class_for_the_color_of_the_links_without_a_color() {
		$attributes = array( 'style' => array( 'elements' => array( 'link' => array() ) ) );

		$this->assertStringNotContainsString( 'has-link-color', render_block_core_query_pagination( $attributes, '<a href="#">Next</a>' ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66570
	 *
	 * @covers ::register_block_core_query_pagination
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/query-pagination' );

		if ( $original_block ) {
			unregister_block_type( 'core/query-pagination' );
		}

		try {
			register_block_core_query_pagination();
			$block_type = $registry->get_registered( 'core/query-pagination' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_query_pagination', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/query-pagination' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
