<?php
/**
 * Tests for the server-side rendering of the Query No Results block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_query_no_results
 */
class Tests_Blocks_RenderQueryNoResults extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * The original value of the request data.
	 *
	 * @var array
	 */
	private $original_get;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
		$this->original_get                 = $_GET;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$_GET                               = $this->original_get;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $content    Content of the block.
	 * @param array  $query      The query of the block. Default is the posts that have a title with the word `Findable`.
	 * @param array  $context    Optional. Other context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $content = '<p>Nothing here.</p>', $query = array( 'search' => 'Findable' ), $context = array() ) {
		$context['query'] = array_merge(
			array(
				'perPage'  => 5,
				'postType' => 'post',
				'inherit'  => false,
			),
			$query
		);

		$block = new WP_Block(
			array(
				'blockName'    => 'core/query-no-results',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			$context
		);

		return render_block_core_query_no_results( $attributes, $content, $block );
	}

	/**
	 * Tests that the content is rendered when the query has no posts.
	 *
	 * @ticket 66569
	 */
	public function test_should_render_the_content_when_the_query_has_no_posts() {
		$this->assertSame( '<div ><p>Nothing here.</p></div>', $this->render() );
	}

	/**
	 * Tests that nothing is rendered when the query has posts.
	 *
	 * @ticket 66569
	 */
	public function test_should_render_nothing_when_the_query_has_posts() {
		self::factory()->post->create( array( 'post_title' => 'Findable post' ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests that nothing is rendered without content.
	 *
	 * @ticket 66569
	 *
	 * @dataProvider data_empty_content
	 *
	 * @param string $content Content of the block.
	 */
	public function test_should_render_nothing_without_content( $content ) {
		$this->assertSame( '', $this->render( array(), $content ) );
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
	 * @ticket 66569
	 */
	public function test_should_add_the_class_for_the_color_of_the_links() {
		$attributes = array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) );

		$this->assertSame( '<div class="has-link-color"><p>Nothing here.</p></div>', $this->render( $attributes ) );
	}

	/**
	 * Tests that the class for the color of the links is not added without the color.
	 *
	 * @ticket 66569
	 */
	public function test_should_not_add_the_class_for_the_color_of_the_links_without_a_color() {
		$attributes = array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array() ) ) ) );

		$this->assertSame( '<div ><p>Nothing here.</p></div>', $this->render( $attributes ) );
	}

	/**
	 * Tests the page of the query that has an ID.
	 *
	 * @ticket 66569
	 */
	public function test_should_render_the_content_for_a_page_that_has_no_posts() {
		self::factory()->post->create_many( 2, array( 'post_title' => 'Findable post' ) );

		$_GET['query-3-page'] = 2;

		$this->assertSame(
			'<div ><p>Nothing here.</p></div>',
			$this->render(
				array(),
				'<p>Nothing here.</p>',
				array(
					'search'  => 'Findable',
					'perPage' => 2,
				),
				array( 'queryId' => 3 )
			),
			'The second page of two posts should have none.'
		);

		$_GET['query-3-page'] = 1;

		$this->assertSame(
			'',
			$this->render(
				array(),
				'<p>Nothing here.</p>',
				array(
					'search'  => 'Findable',
					'perPage' => 2,
				),
				array( 'queryId' => 3 )
			),
			'The first page should have posts.'
		);
	}

	/**
	 * Tests that the query of the page is used when the block inherits it, and has no posts.
	 *
	 * @ticket 66569
	 */
	public function test_should_use_the_query_of_the_page_when_it_has_no_posts() {
		// A query of its own would find this post, and the query of the page does not.
		self::factory()->post->create( array( 'post_title' => 'Other post' ) );

		$this->go_to( '/?s=nothingmatchesthis' );

		$this->assertSame( '<div ><p>Nothing here.</p></div>', $this->render( array(), '<p>Nothing here.</p>', array( 'inherit' => true ) ) );
	}

	/**
	 * Tests that the query of the page is used when the block inherits it, and has posts.
	 *
	 * @ticket 66569
	 */
	public function test_should_render_nothing_when_the_query_of_the_page_has_posts() {
		self::factory()->post->create( array( 'post_title' => 'Findable post' ) );

		$this->go_to( '/?s=Findable' );

		$this->assertSame( '', $this->render( array(), '<p>Nothing here.</p>', array( 'inherit' => true ) ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66569
	 *
	 * @covers ::register_block_core_query_no_results
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/query-no-results' );

		if ( $original_block ) {
			unregister_block_type( 'core/query-no-results' );
		}

		try {
			register_block_core_query_no_results();
			$block_type = $registry->get_registered( 'core/query-no-results' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_query_no_results', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/query-no-results' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
