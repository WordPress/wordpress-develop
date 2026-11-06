<?php
/**
 * Tests for the server-side rendering of the Comment Reply Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_comment_reply_link
 */
class Tests_Blocks_RenderCommentReplyLink extends WP_UnitTestCase {

	/**
	 * ID of the post of the comments.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * IDs of a comment, of its reply and of the reply of that reply.
	 *
	 * @var int[]
	 */
	private static $comment_ids = array();

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();

		$parent = 0;
		foreach ( array( 'top', 'child', 'grandchild' ) as $key ) {
			$parent                    = $factory->comment->create(
				array(
					'comment_post_ID' => self::$post_id,
					'comment_parent'  => $parent,
				)
			);
			self::$comment_ids[ $key ] = $parent;
		}
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'thread_comments', 1 );
		update_option( 'thread_comments_depth', 3 );
		update_option( 'comment_registration', 0 );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block.
	 * @return string|null The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/comment-reply-link',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'commentId' => self::$comment_ids['top'] ) : $context;

		return render_block_core_comment_reply_link( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a comment.
	 *
	 * @ticket 66537
	 *
	 * @dataProvider data_contexts_without_a_comment
	 *
	 * @param array $context Context of the block.
	 */
	public function test_should_render_nothing_without_a_comment( $context ) {
		$this->assertSame( '', $this->render( array(), $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contexts_without_a_comment() {
		return array(
			'no comment ID'                 => array( array() ),
			'a comment that does not exist' => array( array( 'commentId' => 999999 ) ),
		);
	}

	/**
	 * Tests that nothing is rendered when the comments are not threaded.
	 *
	 * @ticket 66537
	 */
	public function test_should_render_nothing_when_the_comments_are_not_threaded() {
		update_option( 'thread_comments', 0 );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests the link to reply to a comment.
	 *
	 * @ticket 66537
	 */
	public function test_should_render_the_link_to_reply_to_the_comment() {
		$output = $this->render();

		$this->assertStringStartsWith( '<div >', $output );
		$this->assertStringEndsWith( '</div>', $output );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'a' ), 'There should be a link.' );
		$this->assertContains( 'comment-reply-link', preg_split( '/\s+/', $processor->get_attribute( 'class' ) ) );
		$this->assertStringContainsString( 'replytocom=' . self::$comment_ids['top'], $processor->get_attribute( 'href' ) );
		$this->assertSame( (string) self::$comment_ids['top'], $processor->get_attribute( 'data-commentid' ) );
	}

	/**
	 * Tests the depth of the comments.
	 *
	 * @ticket 66537
	 *
	 * @dataProvider data_depths
	 *
	 * @param string $comment   Key of the comment.
	 * @param int    $max_depth Maximum depth of the comments.
	 * @param bool   $expected  Whether the link is expected.
	 */
	public function test_should_only_render_the_link_when_the_depth_is_not_the_maximum( $comment, $max_depth, $expected ) {
		update_option( 'thread_comments_depth', $max_depth );

		$output = $this->render( array(), array( 'commentId' => self::$comment_ids[ $comment ] ) );

		if ( $expected ) {
			$this->assertStringContainsString( 'comment-reply-link', $output );
		} else {
			$this->assertNull( $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_depths() {
		return array(
			'a comment of the first level, with a maximum of 1' => array( 'top', 1, false ),
			'a comment of the first level, with a maximum of 2' => array( 'top', 2, true ),
			'a reply, with a maximum of 2'            => array( 'child', 2, false ),
			'a reply, with a maximum of 3'            => array( 'child', 3, true ),
			'a reply of a reply, with a maximum of 3' => array( 'grandchild', 3, false ),
			'a reply of a reply, with a maximum of 4' => array( 'grandchild', 4, true ),
		);
	}

	/**
	 * Tests that nothing is rendered when the comments are closed.
	 *
	 * @ticket 66537
	 */
	public function test_should_render_nothing_when_the_comments_are_closed() {
		wp_update_post(
			array(
				'ID'             => self::$post_id,
				'comment_status' => 'closed',
			)
		);

		$this->assertNull( $this->render() );
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66537
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
	 * @ticket 66537
	 *
	 * @covers ::register_block_core_comment_reply_link
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/comment-reply-link' );

		if ( $was_registered ) {
			unregister_block_type( 'core/comment-reply-link' );
		}

		register_block_core_comment_reply_link();
		$block_type = $registry->get_registered( 'core/comment-reply-link' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/comment-reply-link' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_comment_reply_link', $block_type->render_callback );
	}
}
