<?php
/**
 * Tests for the server-side rendering of the Comments Title block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_comments_title
 */
class Tests_Blocks_RenderCommentsTitle extends WP_UnitTestCase {

	/**
	 * IDs of posts that have a number of comments, keyed by number.
	 *
	 * @var int[]
	 */
	private static $posts = array();

	/**
	 * ID of a post that has a password and a comment.
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

		self::$protected_post_id = $factory->post->create(
			array(
				'post_title'    => 'Protected',
				'post_password' => 'secret',
			)
		);
		$factory->comment->create(
			array(
				'comment_post_ID'  => self::$protected_post_id,
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
	 * Goes to a post that has a number of comments.
	 *
	 * @param int $count Number of comments of the post.
	 */
	private function go_to_post( $count ) {
		$this->go_to( get_permalink( self::$posts[ $count ] ) );
	}

	/**
	 * Tests that nothing is rendered without comments.
	 *
	 * @ticket 66539
	 */
	public function test_should_render_nothing_without_comments() {
		$this->go_to_post( 0 );

		$this->assertNull( render_block_core_comments_title( array( 'showCommentsCount' => true ) ) );
	}

	/**
	 * Tests that nothing is rendered for a post that needs a password.
	 *
	 * @ticket 66539
	 */
	public function test_should_render_nothing_for_a_post_that_needs_a_password() {
		$this->go_to( get_permalink( self::$protected_post_id ) );

		$this->assertNull( render_block_core_comments_title( array() ) );
	}

	/**
	 * Tests the text of the title.
	 *
	 * @ticket 66539
	 *
	 * @dataProvider data_titles
	 *
	 * @param int    $count      Number of comments of the post.
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected text of the title.
	 */
	public function test_should_render_the_title( $count, $attributes, $expected ) {
		$this->go_to_post( $count );

		$this->assertSame( '<h2 id="comments" >' . $expected . '</h2>', render_block_core_comments_title( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_titles() {
		return array(
			'one comment'                       => array( 1, array(), 'Response' ),
			'some comments'                     => array( 3, array(), 'Responses' ),
			'one comment with the post title'   => array( 1, array( 'showPostTitle' => true ), 'Response to &#8220;My post&#8221;' ),
			'some comments with the post title' => array( 3, array( 'showPostTitle' => true ), 'Responses to &#8220;My post&#8221;' ),
			'one comment with the count'        => array( 1, array( 'showCommentsCount' => true ), 'One response' ),
			'some comments with the count'      => array( 3, array( 'showCommentsCount' => true ), '3 responses' ),
			'one comment with the count and the post title' => array(
				1,
				array(
					'showCommentsCount' => true,
					'showPostTitle'     => true,
				),
				'One response to &#8220;My post&#8221;',
			),
			'some comments with the count and the post title' => array(
				3,
				array(
					'showCommentsCount' => true,
					'showPostTitle'     => true,
				),
				'3 responses to &#8220;My post&#8221;',
			),
			'the options that are false'        => array(
				3,
				array(
					'showCommentsCount' => false,
					'showPostTitle'     => false,
				),
				'Responses',
			),
		);
	}

	/**
	 * Tests the tag of the title.
	 *
	 * @ticket 66539
	 *
	 * @dataProvider data_levels
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $tag_name   Expected tag.
	 */
	public function test_should_use_the_tag_of_the_level( $attributes, $tag_name ) {
		$this->go_to_post( 1 );

		$this->assertSame( "<{$tag_name} id=\"comments\" >Response</{$tag_name}>", render_block_core_comments_title( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_levels() {
		return array(
			'the default' => array( array(), 'h2' ),
			'one'         => array( array( 'level' => 1 ), 'h1' ),
			'three'       => array( array( 'level' => 3 ), 'h3' ),
			'six'         => array( array( 'level' => 6 ), 'h6' ),
		);
	}

	/**
	 * Tests the class of the text alignment.
	 *
	 * @ticket 66539
	 *
	 * @dataProvider data_text_alignments
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected class attribute.
	 */
	public function test_should_add_the_class_of_the_text_alignment( $attributes, $expected ) {
		$this->go_to_post( 1 );

		$this->assertSame( '<h2 id="comments" ' . $expected . '>Response</h2>', render_block_core_comments_title( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_text_alignments() {
		return array(
			'no alignment'   => array( array(), '' ),
			'an empty value' => array( array( 'textAlign' => '' ), '' ),
			'center'         => array( array( 'textAlign' => 'center' ), 'class="has-text-align-center"' ),
			'right'          => array( array( 'textAlign' => 'right' ), 'class="has-text-align-right"' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66539
	 *
	 * @covers ::register_block_core_comments_title
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/comments-title' );

		if ( $was_registered ) {
			unregister_block_type( 'core/comments-title' );
		}

		register_block_core_comments_title();
		$block_type = $registry->get_registered( 'core/comments-title' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/comments-title' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_comments_title', $block_type->render_callback );
	}
}
