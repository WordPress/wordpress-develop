<?php
/**
 * Tests for the server-side rendering of the Query Title block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_query_title
 */
class Tests_Blocks_RenderQueryTitle extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * ID of a category.
	 *
	 * @var int
	 */
	private static $category_id;

	/**
	 * Creates a category.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category_id = $factory->category->create( array( 'name' => 'News' ) );
	}

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
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Optional. Context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes, $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/query-title',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			$context
		);

		return render_block_core_query_title( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a type.
	 *
	 * @ticket 66567
	 *
	 * @dataProvider data_attributes_without_a_type
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_render_nothing_without_a_type( $attributes ) {
		$this->go_to( get_category_link( self::$category_id ) );

		$this->assertSame( '', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attributes_without_a_type() {
		return array(
			'no attributes' => array( array() ),
			'an empty type' => array( array( 'type' => '' ) ),
			'a null type'   => array( array( 'type' => null ) ),
		);
	}

	/**
	 * Tests that nothing is rendered when the page is not of the type.
	 *
	 * @ticket 66567
	 *
	 * @dataProvider data_types_of_another_page
	 *
	 * @param string $type Type of the block.
	 * @param string $url  URL of the page.
	 */
	public function test_should_render_nothing_when_the_page_is_not_of_the_type( $type, $url ) {
		$this->go_to( $url );

		$this->assertSame( '', $this->render( array( 'type' => $type ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_types_of_another_page() {
		return array(
			'archive on the home page'      => array( 'archive', '/' ),
			'archive on a search'           => array( 'archive', '/?s=news' ),
			'search on the home page'       => array( 'search', '/' ),
			'search on an archive'          => array( 'search', '/?cat=1' ),
			'post type without a post type' => array( 'post-type', '/' ),
		);
	}

	/**
	 * Tests the title of an archive.
	 *
	 * @ticket 66567
	 */
	public function test_should_render_the_title_of_an_archive_with_the_prefix_by_default() {
		$this->go_to( get_category_link( self::$category_id ) );

		$this->assertSame( '<h1 >Category: <span>News</span></h1>', $this->render( array( 'type' => 'archive' ) ) );
	}

	/**
	 * Tests the title of an archive without the prefix.
	 *
	 * @ticket 66567
	 */
	public function test_should_render_the_title_of_an_archive_without_the_prefix() {
		$this->go_to( get_category_link( self::$category_id ) );

		$this->assertSame(
			'<h1 >News</h1>',
			$this->render(
				array(
					'type'       => 'archive',
					'showPrefix' => false,
				)
			)
		);
	}

	/**
	 * Tests that the prefix is only removed while the title is made.
	 *
	 * @ticket 66567
	 */
	public function test_should_not_leave_the_filter_that_removes_the_prefix() {
		$this->go_to( get_category_link( self::$category_id ) );

		$this->render(
			array(
				'type'       => 'archive',
				'showPrefix' => false,
			)
		);

		$this->assertFalse( has_filter( 'get_the_archive_title_prefix', '__return_empty_string' ) );
		$this->assertSame( 'Category: <span>News</span>', get_the_archive_title() );
	}

	/**
	 * Tests the title of a search.
	 *
	 * @ticket 66567
	 */
	public function test_should_render_the_title_of_a_search() {
		$this->go_to( '/?s=cats' );

		$this->assertSame( '<h1 >Search results</h1>', $this->render( array( 'type' => 'search' ) ) );
	}

	/**
	 * Tests the title of a search with the search term.
	 *
	 * @ticket 66567
	 */
	public function test_should_render_the_search_term_in_the_title_of_a_search() {
		$this->go_to( '/?s=cats+%26+%3Cb%3Edogs%3C%2Fb%3E' );

		$this->assertSame(
			'<h1 >Search results for: &#8220;cats &amp; &lt;b&gt;dogs&lt;/b&gt;&#8221;</h1>',
			$this->render(
				array(
					'type'           => 'search',
					'showSearchTerm' => true,
				)
			)
		);
	}

	/**
	 * Tests that the search term is not rendered when it is turned off.
	 *
	 * @ticket 66567
	 */
	public function test_should_not_render_the_search_term_when_it_is_off() {
		$this->go_to( '/?s=cats' );

		$this->assertSame(
			'<h1 >Search results</h1>',
			$this->render(
				array(
					'type'           => 'search',
					'showSearchTerm' => false,
				)
			)
		);
	}

	/**
	 * Tests the title of a post type from the context of the query.
	 *
	 * @ticket 66567
	 */
	public function test_should_render_the_title_of_the_post_type_of_the_query() {
		$this->go_to( '/' );

		$this->assertSame(
			'<h1 >Post Type: &#8220;Page&#8221;</h1>',
			$this->render( array( 'type' => 'post-type' ), array( 'query' => array( 'postType' => 'page' ) ) )
		);
	}

	/**
	 * Tests the title of a post type without the prefix.
	 *
	 * @ticket 66567
	 */
	public function test_should_render_the_title_of_a_post_type_without_the_prefix() {
		$this->go_to( '/' );

		$this->assertSame(
			'<h1 >Page</h1>',
			$this->render(
				array(
					'type'       => 'post-type',
					'showPrefix' => false,
				),
				array( 'query' => array( 'postType' => 'page' ) )
			)
		);
	}

	/**
	 * Tests that the post type of the current post is used without a query.
	 *
	 * @ticket 66567
	 */
	public function test_should_use_the_post_type_of_the_current_post_without_a_query() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->go_to( get_permalink( $page_id ) );

		$this->assertSame( '<h1 >Post Type: &#8220;Page&#8221;</h1>', $this->render( array( 'type' => 'post-type' ) ) );
	}

	/**
	 * Tests that nothing is rendered for a post type that does not exist.
	 *
	 * @ticket 66567
	 */
	public function test_should_render_nothing_for_a_post_type_that_does_not_exist() {
		$this->go_to( '/' );

		$this->assertSame( '', $this->render( array( 'type' => 'post-type' ), array( 'query' => array( 'postType' => 'not_a_post_type' ) ) ) );
	}

	/**
	 * Tests the tag of each level.
	 *
	 * @ticket 66567
	 *
	 * @dataProvider data_levels
	 *
	 * @param mixed  $level    Level of the block.
	 * @param string $tag_name Expected tag.
	 */
	public function test_should_use_the_tag_of_the_level( $level, $tag_name ) {
		$this->go_to( '/?s=cats' );

		$this->assertSame(
			"<{$tag_name} >Search results</{$tag_name}>",
			$this->render(
				array(
					'type'  => 'search',
					'level' => $level,
				)
			)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_levels() {
		return array(
			'zero is a paragraph' => array( 0, 'p' ),
			'two'                 => array( 2, 'h2' ),
			'six'                 => array( 6, 'h6' ),
			'a numeric string'    => array( '3', 'h3' ),
		);
	}

	/**
	 * Tests the class of the alignment of the text.
	 *
	 * @ticket 66567
	 */
	public function test_should_add_the_class_of_the_text_alignment() {
		$this->go_to( '/?s=cats' );

		$output = $this->render(
			array(
				'type'      => 'search',
				'textAlign' => 'center',
			)
		);

		$this->assertSame( '<h1 class="has-text-align-center">Search results</h1>', $output );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66567
	 *
	 * @covers ::register_block_core_query_title
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/query-title' );

		if ( $original_block ) {
			unregister_block_type( 'core/query-title' );
		}

		try {
			register_block_core_query_title();
			$block_type = $registry->get_registered( 'core/query-title' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_query_title', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/query-title' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
