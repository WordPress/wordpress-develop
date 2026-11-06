<?php
/**
 * Tests for the server-side rendering of the Post Comments Count block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_post_comments_count
 */
class Tests_Blocks_RenderPostCommentsCount extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * ID of a post that has three comments.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post that has no comments.
	 *
	 * @var int
	 */
	private static $post_without_comments_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id                  = $factory->post->create();
		self::$post_without_comments_id = $factory->post->create();

		$factory->comment->create_many(
			3,
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
				'blockName'    => 'core/post-comments-count',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_comments_count( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_nothing_without_a_post() {
		$this->assertSame( '', $this->render( array(), array() ) );
	}

	/**
	 * Tests the number of comments.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_the_number_of_comments_of_the_post() {
		$this->assertSame( '<div >3</div>', $this->render() );
		$this->assertSame( '<div >0</div>', $this->render( array(), array( 'postId' => self::$post_without_comments_id ) ), 'A post without comments should show zero.' );
	}

	/**
	 * Tests the class of the text alignment.
	 *
	 * @ticket 66541
	 *
	 * @dataProvider data_text_alignments
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_class_of_the_text_alignment( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_text_alignments() {
		return array(
			'no alignment' => array( array(), '<div >' ),
			'center'       => array( array( 'textAlign' => 'center' ), '<div class="has-text-align-center">' ),
			'right'        => array( array( 'textAlign' => 'right' ), '<div class="has-text-align-right">' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66541
	 *
	 * @covers ::register_block_core_post_comments_count
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-comments-count' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-comments-count' );
		}

		register_block_core_post_comments_count();
		$block_type = $registry->get_registered( 'core/post-comments-count' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-comments-count' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_comments_count', $block_type->render_callback );
	}
}
