<?php
/**
 * Tests for the server-side rendering of the Comment Date block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_comment_date
 */
class Tests_Blocks_RenderCommentDate extends WP_UnitTestCase {

	/**
	 * Comment of the tests.
	 *
	 * @var WP_Comment
	 */
	private static $comment;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$post_id = $factory->post->create();

		self::$comment = $factory->comment->create_and_get(
			array(
				'comment_post_ID'  => $post_id,
				'comment_date'     => '2023-05-06 10:11:12',
				'comment_date_gmt' => '2023-05-06 10:11:12',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'date_format', 'Y-m-d' );
		update_option( 'timezone_string', 'UTC' );
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
				'blockName'    => 'core/comment-date',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'commentId' => self::$comment->comment_ID ) : $context;

		return render_block_core_comment_date( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a comment.
	 *
	 * @ticket 66535
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
			'a comment ID that is zero'     => array( array( 'commentId' => 0 ) ),
		);
	}

	/**
	 * Tests the date of the comment.
	 *
	 * @ticket 66535
	 */
	public function test_should_render_the_date_of_the_comment() {
		$this->assertSame(
			'<div ><time datetime="2023-05-06T10:11:12+00:00">2023-05-06</time></div>',
			$this->render()
		);
	}

	/**
	 * Tests the format of the date.
	 *
	 * @ticket 66535
	 *
	 * @dataProvider data_formats
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected text of the date.
	 */
	public function test_should_use_the_format_of_the_block( $attributes, $expected ) {
		$this->assertStringContainsString( '>' . $expected . '</time>', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_formats() {
		return array(
			'no format'    => array( array(), '2023-05-06' ),
			'an empty one' => array( array( 'format' => '' ), '2023-05-06' ),
			'a format'     => array( array( 'format' => 'F j, Y' ), 'May 6, 2023' ),
			'a time'       => array( array( 'format' => 'H:i' ), '10:11' ),
		);
	}

	/**
	 * Tests the date that is the time difference.
	 *
	 * @ticket 66535
	 */
	public function test_should_render_the_time_difference() {
		$output = $this->render( array( 'format' => 'human-diff' ) );

		$this->assertMatchesRegularExpression( '#<time datetime="2023-05-06T10:11:12\+00:00">\d+ (year|years|month|months) ago</time>#', $output );
	}

	/**
	 * Tests that the datetime attribute does not depend on the format.
	 *
	 * @ticket 66535
	 */
	public function test_should_always_use_the_iso_date_for_the_datetime_attribute() {
		$this->assertStringContainsString( '<time datetime="2023-05-06T10:11:12+00:00">', $this->render( array( 'format' => 'F j, Y' ) ) );
	}

	/**
	 * Tests the link to the comment.
	 *
	 * @ticket 66535
	 */
	public function test_should_link_the_date_to_the_comment() {
		$link = esc_url( get_comment_link( self::$comment ) );

		$this->assertSame(
			'<div ><time datetime="2023-05-06T10:11:12+00:00"><a href="' . $link . '">2023-05-06</a></time></div>',
			$this->render( array( 'isLink' => true ) )
		);
	}

	/**
	 * Tests that the date is not linked unless it is requested.
	 *
	 * @ticket 66535
	 *
	 * @dataProvider data_values_that_do_not_link
	 *
	 * @param mixed $is_link Value of the attribute.
	 */
	public function test_should_not_link_the_date_unless_it_is_requested( $is_link ) {
		$this->assertStringNotContainsString( '<a ', $this->render( array( 'isLink' => $is_link ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_do_not_link() {
		return array(
			'false' => array( false ),
			'null'  => array( null ),
			'zero'  => array( 0 ),
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66535
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'get_comment_link',
			static function () {
				return 'https://example.org/?a=1&b=2#comment-1';
			}
		);

		$this->assertStringContainsString( '<a href="https://example.org/?a=1&#038;b=2#comment-1">', $this->render( array( 'isLink' => true ) ) );
	}

	/**
	 * Tests the class of the link color.
	 *
	 * @ticket 66535
	 *
	 * @dataProvider data_link_colors
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_link_color_class_only_with_a_link_color( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_colors() {
		return array(
			'a link color'    => array( array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '<div class="has-link-color">' ),
			'no style'        => array( array(), '<div >' ),
			'another element' => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '<div >' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66535
	 *
	 * @covers ::register_block_core_comment_date
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/comment-date' );

		if ( $was_registered ) {
			unregister_block_type( 'core/comment-date' );
		}

		register_block_core_comment_date();
		$block_type = $registry->get_registered( 'core/comment-date' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/comment-date' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_comment_date', $block_type->render_callback );
	}
}
