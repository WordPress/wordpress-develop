<?php
/**
 * Tests for the server-side rendering of the Comment Edit Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_comment_edit_link
 */
class Tests_Blocks_RenderCommentEditLink extends WP_UnitTestCase {

	/**
	 * Comment of the tests.
	 *
	 * @var WP_Comment
	 */
	private static $comment;

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * ID of a subscriber.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$comment       = $factory->comment->create_and_get( array( 'comment_post_ID' => $factory->post->create() ) );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		wp_set_current_user( self::$admin_id );
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
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/comment-edit-link',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'commentId' => self::$comment->comment_ID ) : $context;

		return render_block_core_comment_edit_link( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a comment.
	 *
	 * @ticket 66536
	 */
	public function test_should_render_nothing_without_a_comment_id() {
		$this->assertSame( '', $this->render( array(), array() ) );
	}

	/**
	 * Tests that nothing is rendered for a user that cannot edit the comment.
	 *
	 * @ticket 66536
	 *
	 * @dataProvider data_users_that_cannot_edit
	 *
	 * @param string $user User that is signed in: 'subscriber' or 'none'.
	 */
	public function test_should_render_nothing_for_a_user_that_cannot_edit_the_comment( $user ) {
		wp_set_current_user( 'subscriber' === $user ? self::$subscriber_id : 0 );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_users_that_cannot_edit() {
		return array(
			'a subscriber' => array( 'subscriber' ),
			'a visitor'    => array( 'none' ),
		);
	}

	/**
	 * Tests the link to edit the comment.
	 *
	 * @ticket 66536
	 */
	public function test_should_render_the_link_to_edit_the_comment() {
		$this->assertSame(
			'<div ><a href="' . esc_url( get_edit_comment_link( self::$comment ) ) . '" >Edit</a></div>',
			$this->render()
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66536
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'get_edit_comment_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString( '<a href="https://example.org/?a=1&#038;b=2" >', $this->render() );
	}

	/**
	 * Tests the target of the link.
	 *
	 * @ticket 66536
	 *
	 * @dataProvider data_targets
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected attributes of the link, after the URL.
	 */
	public function test_should_add_the_target_of_the_link( $attributes, $expected ) {
		$this->assertStringContainsString( '" ' . $expected . '>Edit</a>', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_targets() {
		return array(
			'no target'      => array( array(), '' ),
			'an empty one'   => array( array( 'linkTarget' => '' ), '' ),
			'a blank target' => array( array( 'linkTarget' => '_blank' ), 'target="_blank"' ),
			'an escaped one' => array( array( 'linkTarget' => '"><script>' ), 'target="&quot;&gt;&lt;script&gt;"' ),
		);
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66536
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
	 * @ticket 66536
	 *
	 * @covers ::register_block_core_comment_edit_link
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/comment-edit-link' );

		if ( $was_registered ) {
			unregister_block_type( 'core/comment-edit-link' );
		}

		register_block_core_comment_edit_link();
		$block_type = $registry->get_registered( 'core/comment-edit-link' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/comment-edit-link' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_comment_edit_link', $block_type->render_callback );
	}
}
