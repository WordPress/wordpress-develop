<?php
/**
 * Tests for the server-side rendering of the Post Title block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_post_title
 */
class Tests_Blocks_RenderPostTitle extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * ID of a post.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post without a title.
	 *
	 * @var int
	 */
	private static $untitled_post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id          = $factory->post->create( array( 'post_title' => 'Hello world' ) );
		self::$untitled_post_id = $factory->post->create( array( 'post_title' => '' ) );
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

	public function set_up_post() {
		$this->go_to( get_permalink( self::$post_id ) );
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
				'blockName'    => 'core/post-title',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_title( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66542
	 */
	public function test_should_render_nothing_without_a_post() {
		$this->set_up_post();

		$this->assertSame( '', $this->render( array(), array() ) );
	}

	/**
	 * Tests that nothing is rendered without a title.
	 *
	 * @ticket 66542
	 */
	public function test_should_render_nothing_without_a_title() {
		$this->go_to( get_permalink( self::$untitled_post_id ) );

		$this->assertSame( '', $this->render( array(), array( 'postId' => self::$untitled_post_id ) ) );
	}

	/**
	 * Tests the title of the post in a heading.
	 *
	 * @ticket 66542
	 */
	public function test_should_render_the_title_in_a_heading_level_2_by_default() {
		$this->set_up_post();

		$this->assertSame( '<h2 >Hello world</h2>', $this->render() );
	}

	/**
	 * Tests the tag of the level.
	 *
	 * @ticket 66542
	 *
	 * @dataProvider data_levels
	 *
	 * @param int    $level    Level of the block.
	 * @param string $tag_name Expected tag.
	 */
	public function test_should_use_the_tag_of_the_level( $level, $tag_name ) {
		$this->set_up_post();

		$this->assertSame( "<{$tag_name} >Hello world</{$tag_name}>", $this->render( array( 'level' => $level ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_levels() {
		return array(
			'zero is a paragraph' => array( 0, 'p' ),
			'one'                 => array( 1, 'h1' ),
			'three'               => array( 3, 'h3' ),
			'six'                 => array( 6, 'h6' ),
		);
	}

	/**
	 * Tests the link to the post.
	 *
	 * @ticket 66542
	 */
	public function test_should_link_the_title_to_the_post() {
		$this->set_up_post();

		$this->assertSame(
			'<h2 ><a href="' . esc_url( get_permalink( self::$post_id ) ) . '" target="_self" >Hello world</a></h2>',
			$this->render(
				array(
					'isLink'     => true,
					'linkTarget' => '_self',
				)
			)
		);
	}

	/**
	 * Tests that the link is the one of the post of the context.
	 *
	 * @ticket 66542
	 */
	public function test_should_link_to_the_post_of_the_context() {
		$this->set_up_post();

		$output = $this->render(
			array(
				'isLink'     => true,
				'linkTarget' => '_self',
			),
			array( 'postId' => self::$untitled_post_id )
		);

		$this->assertStringContainsString( 'href="' . esc_url( get_permalink( self::$untitled_post_id ) ) . '"', $output );
		$this->assertStringNotContainsString( get_permalink( self::$post_id ), $output );
	}

	/**
	 * Tests the target and the rel of the link.
	 *
	 * @ticket 66542
	 *
	 * @dataProvider data_link_attributes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected attributes of the link, after the URL.
	 */
	public function test_should_add_the_target_and_the_rel_of_the_link( $attributes, $expected ) {
		$this->set_up_post();

		$this->assertStringContainsString(
			'" ' . $expected . '>Hello world</a>',
			$this->render( array_merge( array( 'isLink' => true ), $attributes ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_attributes() {
		return array(
			'a target'           => array( array( 'linkTarget' => '_blank' ), 'target="_blank" ' ),
			'a target and a rel' => array(
				array(
					'linkTarget' => '_blank',
					'rel'        => 'noopener nofollow',
				),
				'target="_blank" rel="noopener nofollow"',
			),
			'an empty rel'       => array(
				array(
					'linkTarget' => '_self',
					'rel'        => '',
				),
				'target="_self" ',
			),
			'an escaped target'  => array( array( 'linkTarget' => '"><script>' ), 'target="&quot;&gt;&lt;script&gt;" ' ),
			'an escaped rel'     => array(
				array(
					'linkTarget' => '_self',
					'rel'        => '"><script>',
				),
				'target="_self" rel="&quot;&gt;&lt;script&gt;"',
			),
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66542
	 */
	public function test_should_escape_the_url_of_the_link() {
		$this->set_up_post();

		add_filter(
			'post_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString(
			'<a href="https://example.org/?a=1&#038;b=2" ',
			$this->render(
				array(
					'isLink'     => true,
					'linkTarget' => '_self',
				)
			)
		);
	}

	/**
	 * Tests that the title is not linked unless it is requested.
	 *
	 * @ticket 66542
	 *
	 * @dataProvider data_values_that_do_not_link
	 *
	 * @param mixed $is_link Value of the attribute.
	 */
	public function test_should_not_link_the_title_unless_it_is_requested( $is_link ) {
		$this->set_up_post();

		$this->assertSame( '<h2 >Hello world</h2>', $this->render( array( 'isLink' => $is_link ) ) );
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
	 * Tests the classes of the block.
	 *
	 * @ticket 66542
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->set_up_post();

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
			'no classes'                  => array( array(), '<h2 >' ),
			'a text alignment'            => array( array( 'textAlign' => 'center' ), '<h2 class="has-text-align-center">' ),
			'a link color'                => array( array( 'style' => $link_color ), '<h2 class="has-link-color">' ),
			'a text alignment and a link' => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				'<h2 class="has-text-align-right has-link-color">',
			),
			'another element'             => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '<h2 >' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66542
	 *
	 * @covers ::register_block_core_post_title
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-title' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-title' );
		}

		register_block_core_post_title();
		$block_type = $registry->get_registered( 'core/post-title' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-title' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_title', $block_type->render_callback );
	}
}
