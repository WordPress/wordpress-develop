<?php
/**
 * Tests for the server-side rendering of the Post Comments Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_post_comments_link
 */
class Tests_Blocks_RenderPostCommentsLink extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * IDs of posts that have a number of comments, keyed by number.
	 *
	 * @var int[]
	 */
	private static $posts = array();

	/**
	 * ID of a post that has comments that are closed.
	 *
	 * @var int
	 */
	private static $closed_post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		foreach ( array( 0, 1, 3 ) as $count ) {
			self::$posts[ $count ] = $factory->post->create( array( 'post_title' => 'My post' ) );

			if ( $count > 0 ) {
				$factory->comment->create_many(
					$count,
					array(
						'comment_post_ID'  => self::$posts[ $count ],
						'comment_approved' => 1,
					)
				);
			}
		}

		self::$closed_post_id = $factory->post->create( array( 'comment_status' => 'closed' ) );
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
	 * @param array $context    Context of the block, with the post by default.
	 * @return string|null The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-comments-link',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$posts[1] ) : $context;

		return render_block_core_post_comments_link( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a post, or when the comments are closed.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_nothing_without_a_post_or_with_closed_comments() {
		$this->assertSame( '', $this->render( array(), array() ), 'There should be nothing without a post.' );
		$this->assertSame( '', $this->render( array(), array( 'postId' => self::$closed_post_id ) ), 'There should be nothing when the comments are closed.' );
	}

	/**
	 * Tests the text of the link.
	 *
	 * @ticket 66541
	 *
	 * @dataProvider data_texts
	 *
	 * @param int    $count    Number of comments of the post.
	 * @param string $expected Expected content of the link.
	 */
	public function test_should_render_the_number_of_comments( $count, $expected ) {
		$post_id = self::$posts[ $count ];

		$this->assertSame(
			'<div ><a href=' . esc_url( get_comments_link( $post_id ) ) . '>' . $expected . '</a></div>',
			$this->render( array(), array( 'postId' => $post_id ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_texts() {
		return array(
			'no comments'   => array( 0, 'No comments<span class="screen-reader-text"> on My post</span>' ),
			'one comment'   => array( 1, '1 comment<span class="screen-reader-text"> on My post</span>' ),
			'some comments' => array( 3, '3 comments<span class="screen-reader-text"> on My post</span>' ),
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66541
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'get_comments_link',
			static function () {
				return 'https://example.org/?a=1&b=2#comments';
			}
		);

		$this->assertStringContainsString( '<a href=https://example.org/?a=1&#038;b=2#comments>', $this->render() );
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66541
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'no classes'                  => array( array(), '<div >' ),
			'a text alignment'            => array( array( 'textAlign' => 'center' ), '<div class="has-text-align-center">' ),
			'a link color'                => array( array( 'style' => $link_color ), '<div class="has-link-color">' ),
			'a text alignment and a link' => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				'<div class="has-text-align-right has-link-color">',
			),
			'another element'             => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '<div >' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66541
	 *
	 * @covers ::register_block_core_post_comments_link
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/post-comments-link' );

		if ( $original_block ) {
			unregister_block_type( 'core/post-comments-link' );
		}

		try {
			register_block_core_post_comments_link();
			$block_type = $registry->get_registered( 'core/post-comments-link' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_post_comments_link', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/post-comments-link' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
