<?php
/**
 * Tests for the server-side rendering of the links and the numbers of the Comments Pagination blocks.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 */
class Tests_Blocks_RenderCommentsPaginationLinks extends WP_UnitTestCase {

	/**
	 * ID of a post that has five comments.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();

		$factory->comment->create_many(
			5,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 1,
			)
		);
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'page_comments', '1' );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'oldest' );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Goes to a page of the comments of the post.
	 *
	 * @param int $page Page of the comments.
	 */
	private function go_to_page( $page ) {
		$this->go_to( add_query_arg( 'cpage', $page, get_permalink( self::$post_id ) ) );
	}

	/**
	 * Renders a block of the pagination.
	 *
	 * @param string $callback_name   Name of the render function.
	 * @param array  $attributes Attributes of the block.
	 * @param array  $context    Context of the block.
	 * @return string The rendered block.
	 */
	private function render( $callback_name, $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/comments-pagination',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return $callback_name( $attributes, '', $block );
	}

	/**
	 * Gets the attributes of the first tag of a type of the output.
	 *
	 * @param string $output Rendered block.
	 * @param string $tag    Name of the tag.
	 * @return array|null The attributes, keyed by name, or null when there is no such tag.
	 */
	private function get_attributes_of( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( $tag ) ) {
			return null;
		}

		$attributes = array();
		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_next
	 * @covers ::render_block_core_comments_pagination_numbers
	 *
	 * @dataProvider data_functions_that_need_a_post
	 *
	 * @param string $callback_name Name of the render function.
	 */
	public function test_should_render_nothing_without_a_post( $callback_name ) {
		$this->go_to_page( 1 );

		$this->assertSame( '', $this->render( $callback_name, array(), array() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_functions_that_need_a_post() {
		return array(
			'the next link' => array( 'render_block_core_comments_pagination_next' ),
			'the numbers'   => array( 'render_block_core_comments_pagination_numbers' ),
		);
	}

	/**
	 * Tests the link to the next page.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_next
	 */
	public function test_should_render_the_link_to_the_next_page() {
		$this->go_to_page( 1 );

		$output = $this->render( 'render_block_core_comments_pagination_next' );
		$link   = $this->get_attributes_of( $output, 'a' );

		$this->assertMatchesRegularExpression( '/comment-page-2|cpage=2/', $link['href'] );
		$this->assertStringEndsWith( '>Newer Comments</a>', $output );
	}

	/**
	 * Tests that there is no link to the next page on the last page.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_next
	 */
	public function test_should_render_nothing_on_the_last_page_for_the_next_link() {
		$this->go_to_page( 3 );

		$this->assertSame( '', $this->render( 'render_block_core_comments_pagination_next' ) );
	}

	/**
	 * Tests the label and the arrow of the next link.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_next
	 *
	 * @dataProvider data_next_labels
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param array  $context    Extra values of the context.
	 * @param string $expected   Expected content of the link.
	 */
	public function test_should_render_the_label_of_the_next_link( $attributes, $context, $expected ) {
		$this->go_to_page( 1 );

		$output = $this->render( 'render_block_core_comments_pagination_next', $attributes, array_merge( array( 'postId' => self::$post_id ), $context ) );

		$this->assertStringEndsWith( '>' . $expected . '</a>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_next_labels() {
		return array(
			'the default label' => array( array(), array(), 'Newer Comments' ),
			'a label'           => array( array( 'label' => 'More' ), array(), 'More' ),
			'an empty label'    => array( array( 'label' => '' ), array(), 'Newer Comments' ),
			'an escaped label'  => array( array( 'label' => 'A & <b>B</b>' ), array(), 'A &amp; &lt;b&gt;B&lt;/b&gt;' ),
			'an arrow'          => array( array(), array( 'comments/paginationArrow' => 'arrow' ), "Newer Comments<span class='wp-block-comments-pagination-next-arrow is-arrow-arrow' aria-hidden='true'>→</span>" ),
			'a chevron'         => array( array( 'label' => 'More' ), array( 'comments/paginationArrow' => 'chevron' ), "More<span class='wp-block-comments-pagination-next-arrow is-arrow-chevron' aria-hidden='true'>»</span>" ),
			'no arrow'          => array( array(), array( 'comments/paginationArrow' => 'none' ), 'Newer Comments' ),
		);
	}

	/**
	 * Tests the link to the previous page.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_previous
	 */
	public function test_should_render_the_link_to_the_previous_page() {
		$this->go_to_page( 3 );

		$output = $this->render( 'render_block_core_comments_pagination_previous' );
		$link   = $this->get_attributes_of( $output, 'a' );

		$this->assertMatchesRegularExpression( '/comment-page-2|cpage=2/', $link['href'] );
		$this->assertStringEndsWith( '>Older Comments</a>', $output );
	}

	/**
	 * Tests that there is no link to the previous page on the first page.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_previous
	 */
	public function test_should_render_nothing_on_the_first_page_for_the_previous_link() {
		$this->go_to_page( 1 );

		$this->assertSame( '', $this->render( 'render_block_core_comments_pagination_previous' ) );
	}

	/**
	 * Tests the label and the arrow of the previous link.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_previous
	 *
	 * @dataProvider data_previous_labels
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param array  $context    Extra values of the context.
	 * @param string $expected   Expected content of the link.
	 */
	public function test_should_render_the_label_of_the_previous_link( $attributes, $context, $expected ) {
		$this->go_to_page( 3 );

		$output = $this->render( 'render_block_core_comments_pagination_previous', $attributes, array_merge( array( 'postId' => self::$post_id ), $context ) );

		$this->assertStringEndsWith( '>' . $expected . '</a>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_previous_labels() {
		return array(
			'the default label' => array( array(), array(), 'Older Comments' ),
			'a label'           => array( array( 'label' => 'Back' ), array(), 'Back' ),
			'an empty label'    => array( array( 'label' => '' ), array(), 'Older Comments' ),
			'an escaped label'  => array( array( 'label' => 'A & <b>B</b>' ), array(), 'A &amp; &lt;b&gt;B&lt;/b&gt;' ),
			'an arrow'          => array( array(), array( 'comments/paginationArrow' => 'arrow' ), "<span class='wp-block-comments-pagination-previous-arrow is-arrow-arrow' aria-hidden='true'>←</span>Older Comments" ),
			'a chevron'         => array( array( 'label' => 'Back' ), array( 'comments/paginationArrow' => 'chevron' ), "<span class='wp-block-comments-pagination-previous-arrow is-arrow-chevron' aria-hidden='true'>«</span>Back" ),
		);
	}

	/**
	 * Tests the links when the newest comments are shown by default and the page is not in the URL.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_next
	 * @covers ::render_block_core_comments_pagination_previous
	 */
	public function test_should_use_the_newest_page_when_it_is_the_default_and_not_in_the_url() {
		update_option( 'default_comments_page', 'newest' );

		$this->go_to( get_permalink( self::$post_id ) );

		$this->assertSame( '', $this->render( 'render_block_core_comments_pagination_next' ), 'There should be no next page after the newest one.' );
		$this->assertMatchesRegularExpression( '/comment-page-2|cpage=2/', $this->render( 'render_block_core_comments_pagination_previous' ), 'The previous page should be the one before the newest.' );
	}

	/**
	 * Tests the page numbers.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_numbers
	 */
	public function test_should_render_the_page_numbers() {
		$this->go_to_page( 2 );

		$output = $this->render( 'render_block_core_comments_pagination_numbers' );

		$this->assertStringStartsWith( '<div >', $output );
		$this->assertStringEndsWith( '</div>', $output );
		$this->assertSame( 3, substr_count( $output, 'page-numbers' ), 'There should be a number for each page.' );
		$this->assertStringContainsString( '<span aria-current="page" class="page-numbers current">2</span>', $output, 'The current page should be marked.' );
		$this->assertStringContainsString( '>1</a>', $output );
		$this->assertStringContainsString( '>3</a>', $output );
		$this->assertStringNotContainsString( 'next', $output, 'There should be no next link.' );
		$this->assertStringNotContainsString( 'prev', $output, 'There should be no previous link.' );
	}

	/**
	 * Tests that nothing is rendered when there is only one page.
	 *
	 * @ticket 66538
	 *
	 * @covers ::render_block_core_comments_pagination_numbers
	 */
	public function test_should_render_nothing_for_the_numbers_when_there_is_one_page() {
		update_option( 'comments_per_page', 50 );

		$this->go_to_page( 1 );

		$this->assertSame( '', $this->render( 'render_block_core_comments_pagination_numbers' ) );
	}

	/**
	 * Tests that the blocks are registered with the render callbacks.
	 *
	 * @ticket 66538
	 *
	 * @covers ::register_block_core_comments_pagination_next
	 * @covers ::register_block_core_comments_pagination_previous
	 * @covers ::register_block_core_comments_pagination_numbers
	 *
	 * @dataProvider data_blocks
	 *
	 * @param string $name     Name of the block.
	 * @param string $callback_name Name of the function that registers the block.
	 * @param string $callback Name of the render callback.
	 */
	public function test_should_register_the_blocks_with_the_render_callbacks( $name, $callback_name, $callback ) {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( $name );

		if ( $was_registered ) {
			unregister_block_type( $name );
		}

		$callback_name();
		$block_type = $registry->get_registered( $name );

		if ( ! $was_registered ) {
			unregister_block_type( $name );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( $callback, $block_type->render_callback );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_blocks() {
		return array(
			'next'     => array( 'core/comments-pagination-next', 'register_block_core_comments_pagination_next', 'render_block_core_comments_pagination_next' ),
			'previous' => array( 'core/comments-pagination-previous', 'register_block_core_comments_pagination_previous', 'render_block_core_comments_pagination_previous' ),
			'numbers'  => array( 'core/comments-pagination-numbers', 'register_block_core_comments_pagination_numbers', 'render_block_core_comments_pagination_numbers' ),
		);
	}
}
