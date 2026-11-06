<?php
/**
 * Tests for the server-side rendering of the Read More block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_read_more
 */
class Tests_Blocks_RenderReadMore extends WP_UnitTestCase {

	/**
	 * ID of a post with a title.
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

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

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

	/**
	 * Renders the block for a post.
	 *
	 * @param array    $attributes Attributes of the block.
	 * @param int|null $post_id    ID of the post of the context, or null for no post.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $post_id = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/read-more',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $post_id ? array() : array( 'postId' => $post_id );

		return render_block_core_read_more( array_merge( array( 'linkTarget' => '_self' ), $attributes ), '', $block );
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66519
	 */
	public function test_should_render_nothing_without_a_post_in_the_context() {
		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests the link of the default block.
	 *
	 * @ticket 66519
	 */
	public function test_should_render_a_link_to_the_post() {
		$this->assertSame(
			'<a  href="' . get_permalink( self::$post_id ) . '" target="_self">Read more<span class="screen-reader-text">: Hello world</span></a>',
			$this->render( array(), self::$post_id )
		);
	}

	/**
	 * Tests that the text of the link is the one of the block.
	 *
	 * @ticket 66519
	 *
	 * @dataProvider data_contents
	 *
	 * @param string $content  Content of the attributes.
	 * @param string $expected Expected text of the link.
	 */
	public function test_should_use_the_text_of_the_block( $content, $expected ) {
		$this->assertStringContainsString(
			' target="_self">' . $expected . '<span class="screen-reader-text">',
			$this->render( array( 'content' => $content ), self::$post_id )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contents() {
		return array(
			'a text'                       => array( 'Continue reading', 'Continue reading' ),
			'a text with allowed markup'   => array( 'Read <strong>all</strong>', 'Read <strong>all</strong>' ),
			'a script that is not allowed' => array( 'Go<script>alert( 1 );</script> on', 'Go on' ),
			'an empty text'                => array( '', 'Read more' ),
		);
	}

	/**
	 * Tests that the default text is used when the block has no text.
	 *
	 * @ticket 66519
	 */
	public function test_should_use_the_default_text_without_a_content_attribute() {
		$this->assertStringContainsString( ' target="_self">Read more<span', $this->render( array(), self::$post_id ) );
	}

	/**
	 * Tests that the text for the screen readers uses the title of the post.
	 *
	 * @ticket 66519
	 */
	public function test_should_describe_the_link_with_the_title_of_the_post() {
		$this->assertStringEndsWith(
			'<span class="screen-reader-text">: Hello world</span></a>',
			$this->render( array(), self::$post_id )
		);
	}

	/**
	 * Tests that the text for the screen readers uses the ID of a post that has no title.
	 *
	 * @ticket 66519
	 */
	public function test_should_describe_the_link_with_the_id_of_a_post_without_a_title() {
		$this->assertStringEndsWith(
			'<span class="screen-reader-text">: untitled post ' . self::$untitled_post_id . '</span></a>',
			$this->render( array(), self::$untitled_post_id )
		);
	}

	/**
	 * Tests the target of the link.
	 *
	 * @ticket 66519
	 *
	 * @dataProvider data_link_targets
	 *
	 * @param string $target   Link target of the attributes.
	 * @param string $expected Expected value of the attribute.
	 */
	public function test_should_use_the_link_target( $target, $expected ) {
		$this->assertStringContainsString(
			' target="' . $expected . '">',
			$this->render( array( 'linkTarget' => $target ), self::$post_id )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_targets() {
		return array(
			'self'           => array( '_self', '_self' ),
			'blank'          => array( '_blank', '_blank' ),
			'an escaped one' => array( '"><script>', '&quot;&gt;&lt;script&gt;' ),
		);
	}

	/**
	 * Tests the class of the justification.
	 *
	 * @ticket 66519
	 *
	 * @dataProvider data_justifications
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the link.
	 */
	public function test_should_add_the_class_of_the_justification( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes, self::$post_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_justifications() {
		return array(
			'no justification' => array( array(), '<a  href=' ),
			'an empty value'   => array( array( 'justifyContent' => '' ), '<a  href=' ),
			'left'             => array( array( 'justifyContent' => 'left' ), '<a class="is-justified-left" href=' ),
			'space between'    => array( array( 'justifyContent' => 'space-between' ), '<a class="is-justified-space-between" href=' ),
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66519
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'post_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString( ' href="https://example.org/?a=1&#038;b=2" ', $this->render( array(), self::$post_id ) );
	}

	/**
	 * Tests that the link is the one of the post of the context.
	 *
	 * @ticket 66519
	 */
	public function test_should_link_to_the_post_of_the_context() {
		$this->assertStringContainsString( ' href="' . get_permalink( self::$untitled_post_id ) . '" ', $this->render( array(), self::$untitled_post_id ) );
		$this->assertStringNotContainsString( get_permalink( self::$post_id ), $this->render( array(), self::$untitled_post_id ) );
	}
}
