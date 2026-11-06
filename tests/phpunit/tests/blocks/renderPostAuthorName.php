<?php
/**
 * Tests for the server-side rendering of the Post Author Name block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group author
 *
 * @covers ::render_block_core_post_author_name
 */
class Tests_Blocks_RenderPostAuthorName extends WP_UnitTestCase {

	/**
	 * Author of the posts.
	 *
	 * @var int
	 */
	private static $author_id;

	/**
	 * ID of a post of the author.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Jane Doe',
			)
		);
		self::$post_id   = $factory->post->create( array( 'post_author' => self::$author_id ) );
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
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-author-name',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_author_name( $attributes, '', $block );
	}

	/**
	 * Tests the name of the author of the post of the context.
	 *
	 * @ticket 66540
	 */
	public function test_should_render_the_name_of_the_author_of_the_post() {
		$this->assertSame( '<div >Jane Doe</div>', $this->render() );
	}

	/**
	 * Tests that the author of the archive is used without a post.
	 *
	 * @ticket 66540
	 */
	public function test_should_use_the_author_of_the_archive_without_a_post() {
		$this->go_to( get_author_posts_url( self::$author_id ) );

		$this->assertSame( '<div >Jane Doe</div>', $this->render( array(), array() ) );
	}

	/**
	 * Tests that nothing is rendered without an author.
	 *
	 * @ticket 66540
	 */
	public function test_should_render_nothing_without_an_author() {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->render( array(), array() ), 'There should be no author without a post or an archive.' );
		$this->assertSame( '', $this->render( array(), array( 'postId' => 999999 ) ), 'There should be no author for a post that does not exist.' );
	}

	/**
	 * Tests that nothing is rendered for a post type that does not support authors.
	 *
	 * @ticket 66540
	 */
	public function test_should_render_nothing_for_a_post_type_that_does_not_support_authors() {
		register_post_type( 'wp_test_no_author', array( 'supports' => array( 'title' ) ) );

		try {
			$this->assertSame(
				'',
				$this->render(
					array(),
					array(
						'postId'   => self::$post_id,
						'postType' => 'wp_test_no_author',
					)
				)
			);
			$this->assertSame(
				'<div >Jane Doe</div>',
				$this->render(
					array(),
					array(
						'postId'   => self::$post_id,
						'postType' => 'post',
					)
				),
				'A post type that supports authors should be rendered.'
			);
		} finally {
			unregister_post_type( 'wp_test_no_author' );
		}
	}

	/**
	 * Tests the link to the posts of the author.
	 *
	 * @ticket 66540
	 */
	public function test_should_link_the_name_to_the_posts_of_the_author() {
		$this->assertSame(
			'<div ><a href="' . esc_url( get_author_posts_url( self::$author_id ) ) . '" target="_blank" class="wp-block-post-author-name__link">Jane Doe</a></div>',
			$this->render(
				array(
					'isLink'     => true,
					'linkTarget' => '_blank',
				)
			)
		);
	}

	/**
	 * Tests that the target of the link is escaped.
	 *
	 * @ticket 66540
	 */
	public function test_should_escape_the_target_of_the_link() {
		$this->assertStringContainsString(
			'target="&quot;&gt;&lt;script&gt;"',
			$this->render(
				array(
					'isLink'     => true,
					'linkTarget' => '"><script>',
				)
			)
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66540
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'author_link',
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
	 * Tests that the name is not linked unless it is requested.
	 *
	 * @ticket 66540
	 *
	 * @dataProvider data_values_that_do_not_link
	 *
	 * @param mixed $is_link Value of the attribute.
	 */
	public function test_should_not_link_the_name_unless_it_is_requested( $is_link ) {
		$this->assertSame( '<div >Jane Doe</div>', $this->render( array( 'isLink' => $is_link ) ) );
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
	 * @ticket 66540
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
	 * @ticket 66540
	 *
	 * @covers ::register_block_core_post_author_name
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-author-name' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-author-name' );
		}

		register_block_core_post_author_name();
		$block_type = $registry->get_registered( 'core/post-author-name' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-author-name' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_author_name', $block_type->render_callback );
	}
}
