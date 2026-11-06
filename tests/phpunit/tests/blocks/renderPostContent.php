<?php
/**
 * Tests for the server-side rendering of the Post Content block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_post_content
 */
class Tests_Blocks_RenderPostContent extends WP_UnitTestCase {

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
	 * ID of a post with several pages.
	 *
	 * @var int
	 */
	private static $paged_post_id;

	/**
	 * ID of a post without content.
	 *
	 * @var int
	 */
	private static $empty_post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id       = $factory->post->create( array( 'post_content' => "First paragraph.\n\nSecond one." ) );
		self::$paged_post_id = $factory->post->create(
			array(
				'post_content' => "<!-- wp:paragraph -->\n<p>Page one.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->\n\n<!-- wp:paragraph -->\n<p>Page two.</p>\n<!-- /wp:paragraph -->",
			)
		);
		self::$empty_post_id = $factory->post->create( array( 'post_content' => '' ) );
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
	 * Goes to a post.
	 *
	 * @param int $post_id ID of the post.
	 */
	private function go_to_post( $post_id = null ) {
		$this->go_to( get_permalink( null === $post_id ? self::$post_id : $post_id ) );
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
				'blockName'    => 'core/post-content',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_content( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66542
	 */
	public function test_should_render_nothing_without_a_post() {
		$this->go_to_post();

		$this->assertSame( '', $this->render( array(), array() ) );
	}

	/**
	 * Tests the content of the post.
	 *
	 * @ticket 66542
	 */
	public function test_should_render_the_content_of_the_post_in_a_wrapper() {
		$this->go_to_post();

		$this->assertSame(
			"<div class=\"entry-content\"><p>First paragraph.</p>\n<p>Second one.</p>\n</div>",
			$this->render()
		);
	}

	/**
	 * Tests that nothing is rendered for a post without content.
	 *
	 * @ticket 66542
	 */
	public function test_should_render_nothing_without_content() {
		$this->go_to_post( self::$empty_post_id );

		$this->assertSame( '', $this->render( array(), array( 'postId' => self::$empty_post_id ) ) );
	}

	/**
	 * Tests the filters of the content.
	 *
	 * @ticket 66542
	 */
	public function test_should_apply_the_filters_of_the_content() {
		$this->go_to_post();

		add_filter(
			'the_content',
			static function ( $content ) {
				return $content . '<!-- filtered -->';
			}
		);

		$this->assertStringContainsString( '<!-- filtered --></div>', $this->render() );
	}

	/**
	 * Tests that the end of a CDATA section is escaped.
	 *
	 * @ticket 66542
	 */
	public function test_should_escape_the_end_of_a_cdata_section() {
		$this->go_to_post();

		// The content is set after the post is saved, because saving it already changes the characters.
		$GLOBALS['post']->post_content = 'Before ]]> after';
		setup_postdata( $GLOBALS['post'] );

		$this->assertStringContainsString( 'Before ]]&gt; after', $this->render() );
	}

	/**
	 * Tests the tag of the wrapper.
	 *
	 * @ticket 66542
	 *
	 * @dataProvider data_tag_names
	 *
	 * @param mixed  $tag_name Tag name of the attributes.
	 * @param string $expected Expected tag.
	 */
	public function test_should_use_the_tag_name_when_it_is_allowed( $tag_name, $expected ) {
		$this->go_to_post();

		$this->assertStringStartsWith( "<{$expected} class=\"entry-content\">", $this->render( array( 'tagName' => $tag_name ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tag_names() {
		return array(
			'main'                         => array( 'main', 'main' ),
			'section'                      => array( 'section', 'section' ),
			'article'                      => array( 'article', 'article' ),
			'div'                          => array( 'div', 'div' ),
			'a tag in capitals'            => array( 'ARTICLE', 'article' ),
			'a tag that is not allowed'    => array( 'span', 'div' ),
			'a script'                     => array( 'script', 'div' ),
			'an empty string'              => array( '', 'div' ),
			'a value that is not a string' => array( 5, 'div' ),
			'an array'                     => array( array( 'main' ), 'div' ),

			'null'                         => array( null, 'div' ),
		);
	}

	/**
	 * Tests the links of the pages of a post that has several pages.
	 *
	 * @ticket 66542
	 */
	public function test_should_add_the_links_of_the_pages_for_a_post_with_the_next_page_block() {
		$this->go_to_post( self::$paged_post_id );
		setup_postdata( get_post( self::$paged_post_id ) );

		$output = $this->render( array(), array( 'postId' => self::$paged_post_id ) );

		$this->assertStringContainsString( 'Page one.', $output );
		$this->assertStringNotContainsString( 'Page two.', $output, 'The second page should not be rendered.' );
		$this->assertStringContainsString( 'post-nav-links', $output, 'The links of the pages should be added.' );
	}

	/**
	 * Tests that the links of the pages are not added to a post that has no pages.
	 *
	 * @ticket 66542
	 */
	public function test_should_not_add_the_links_of_the_pages_without_the_next_page_block() {
		$this->go_to_post();

		$this->assertStringNotContainsString( 'post-nav-links', $this->render() );
	}

	/**
	 * Tests that the content of a post is not rendered inside itself.
	 *
	 * @ticket 66542
	 */
	public function test_should_not_render_the_content_of_a_post_inside_itself() {
		$this->go_to_post();

		$inner = null;
		$depth = 0;

		add_filter(
			'the_content',
			function ( $content ) use ( &$inner, &$depth ) {
				// A limit of the depth makes the test fail, instead of running forever, when the block renders itself again.
				if ( null === $inner && $depth < 3 ) {
					++$depth;
					$inner = $this->render();
				}

				return $content;
			},
			1
		);

		$output   = $this->render();
		$expected = ( WP_DEBUG && WP_DEBUG_DISPLAY ) ? '[block rendering halted]' : '';

		$this->assertSame( $expected, $inner, 'The inner block should not render the content again.' );
		$this->assertStringContainsString( 'First paragraph.', $output, 'The outer block should render the content.' );
	}

	/**
	 * Tests that the post can be rendered again after it was rendered.
	 *
	 * @ticket 66542
	 */
	public function test_should_render_the_same_post_again_after_it_was_rendered() {
		$this->go_to_post();

		$this->assertSame( $this->render(), $this->render() );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66542
	 *
	 * @covers ::register_block_core_post_content
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-content' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-content' );
		}

		register_block_core_post_content();
		$block_type = $registry->get_registered( 'core/post-content' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-content' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_content', $block_type->render_callback );
	}
}
