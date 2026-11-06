<?php
/**
 * Tests for the server-side rendering of the Comments Pagination block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_comments_pagination
 */
class Tests_Blocks_RenderCommentsPagination extends WP_UnitTestCase {

	/**
	 * ID of a post.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post that has a password.
	 *
	 * @var int
	 */
	private static $protected_post_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id           = $factory->post->create();
		self::$protected_post_id = $factory->post->create( array( 'post_password' => 'secret' ) );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		$this->go_to( get_permalink( self::$post_id ) );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Tests that nothing is rendered without content.
	 *
	 * @ticket 66538
	 *
	 * @dataProvider data_content_that_is_empty
	 *
	 * @param string $content Content of the block.
	 */
	public function test_should_render_nothing_without_content( $content ) {
		$this->assertSame( '', render_block_core_comments_pagination( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_that_is_empty() {
		return array(
			'an empty string' => array( '' ),
			'spaces'          => array( '   ' ),
			'new lines'       => array( "\n\t \n" ),
		);
	}

	/**
	 * Tests that nothing is rendered for a post that needs a password.
	 *
	 * @ticket 66538
	 */
	public function test_should_render_nothing_for_a_post_that_needs_a_password() {
		$this->go_to( get_permalink( self::$protected_post_id ) );

		$this->assertNull( render_block_core_comments_pagination( array(), '<a href="#">Next</a>' ) );
	}

	/**
	 * Tests the navigation that wraps the content.
	 *
	 * @ticket 66538
	 */
	public function test_should_wrap_the_content_in_a_navigation_with_a_label() {
		$output = render_block_core_comments_pagination( array(), '<a href="#">Next</a>' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'nav' ) );
		$this->assertSame( 'Comments pagination', $processor->get_attribute( 'aria-label' ) );
		$this->assertNull( $processor->get_attribute( 'class' ), 'There should be no class.' );
		$this->assertStringEndsWith( '><a href="#">Next</a></nav>', $output, 'The content should be kept.' );
	}

	/**
	 * Tests the class of the link color.
	 *
	 * @ticket 66538
	 *
	 * @dataProvider data_link_colors
	 *
	 * @param array       $attributes Attributes of the block.
	 * @param string|null $expected   Expected class.
	 */
	public function test_should_add_the_link_color_class_only_with_a_link_color( $attributes, $expected ) {
		$processor = new WP_HTML_Tag_Processor( render_block_core_comments_pagination( $attributes, '<a href="#">Next</a>' ) );
		$processor->next_tag( 'nav' );

		$this->assertSame( $expected, $processor->get_attribute( 'class' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_colors() {
		return array(
			'a link color'    => array( array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), 'has-link-color' ),
			'no style'        => array( array(), null ),
			'another element' => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), null ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66538
	 *
	 * @covers ::register_block_core_comments_pagination
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/comments-pagination' );

		if ( $was_registered ) {
			unregister_block_type( 'core/comments-pagination' );
		}

		register_block_core_comments_pagination();
		$block_type = $registry->get_registered( 'core/comments-pagination' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/comments-pagination' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_comments_pagination', $block_type->render_callback );
	}
}
