<?php
/**
 * Tests for the server-side rendering of the Post Author block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group author
 *
 * @covers ::render_block_core_post_author
 */
class Tests_Blocks_RenderPostAuthor extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

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

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Jane Doe',
				'description'  => 'Writes about tests.',
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
	 * @return string|null The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-author',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		// The block has a default for this attribute, and the function reads it directly.
		$attributes = array_merge( array( 'isLink' => false ), $attributes );

		return render_block_core_post_author( $attributes, '', $block );
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
		} finally {
			unregister_post_type( 'wp_test_no_author' );
		}
	}

	/**
	 * Tests the name of the author of the post.
	 *
	 * @ticket 66540
	 */
	public function test_should_render_the_name_of_the_author() {
		$this->assertSame(
			'<div ><div class="wp-block-post-author__content"><p class="wp-block-post-author__name">Jane Doe</p></div></div>',
			$this->render()
		);
	}

	/**
	 * Tests that the author of the archive is used without a post.
	 *
	 * @ticket 66540
	 */
	public function test_should_use_the_author_of_the_archive_without_a_post() {
		$this->go_to( get_author_posts_url( self::$author_id ) );

		$this->assertStringContainsString( '<p class="wp-block-post-author__name">Jane Doe</p>', $this->render( array(), array() ) );
	}

	/**
	 * Tests the avatar.
	 *
	 * @ticket 66540
	 *
	 * @dataProvider data_avatars
	 *
	 * @param array $attributes Attributes of the block.
	 * @param bool  $has_wrapper Whether the avatar wrapper is expected.
	 * @param bool  $has_image   Whether an image is expected in it.
	 */
	public function test_should_render_the_avatar_when_it_is_requested( $attributes, $has_wrapper, $has_image ) {
		$output = $this->render( $attributes );

		$this->assertSame( $has_wrapper, str_contains( $output, '<div class="wp-block-post-author__avatar">' ), 'The wrapper of the avatar.' );
		$this->assertSame( $has_image, str_contains( $output, '<img ' ), 'The image of the avatar.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_avatars() {
		return array(
			'no avatar'                 => array( array(), false, false ),
			'a size without showing it' => array( array( 'avatarSize' => 48 ), false, false ),
			'showing it without a size' => array( array( 'showAvatar' => true ), true, false ),
			'a size and showing it'     => array(
				array(
					'showAvatar' => true,
					'avatarSize' => 48,
				),
				true,
				true,
			),
		);
	}

	/**
	 * Tests the size of the avatar.
	 *
	 * @ticket 66540
	 */
	public function test_should_use_the_size_of_the_avatar() {
		$output = $this->render(
			array(
				'showAvatar' => true,
				'avatarSize' => 96,
			)
		);

		$this->assertStringContainsString( 'width=\'96\'', $output );
	}

	/**
	 * Tests the byline.
	 *
	 * @ticket 66540
	 *
	 * @dataProvider data_bylines
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected content of the byline, or an empty string for none.
	 */
	public function test_should_render_the_byline( $attributes, $expected ) {
		$output = $this->render( $attributes );

		if ( '' === $expected ) {
			$this->assertStringNotContainsString( 'wp-block-post-author__byline', $output );
		} else {
			$this->assertStringContainsString( '<p class="wp-block-post-author__byline">' . $expected . '</p><p class="wp-block-post-author__name">', $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_bylines() {
		return array(
			'no byline'            => array( array(), '' ),
			'an empty byline'      => array( array( 'byline' => '' ), '' ),
			'a byline'             => array( array( 'byline' => 'Written by' ), 'Written by' ),
			'a byline with markup' => array( array( 'byline' => 'By <strong>the</strong> <script>alert( 1 );</script>' ), 'By <strong>the</strong> ' ),
		);
	}

	/**
	 * Tests the biography.
	 *
	 * @ticket 66540
	 */
	public function test_should_render_the_biography_when_it_is_requested() {
		$this->assertStringNotContainsString( 'wp-block-post-author__bio', $this->render(), 'The biography should be hidden.' );
		$this->assertStringContainsString( '<p class="wp-block-post-author__bio">Writes about tests.</p></div></div>', $this->render( array( 'showBio' => true ) ), 'The biography should be shown after the name.' );
	}

	/**
	 * Tests the link to the posts of the author.
	 *
	 * @ticket 66540
	 *
	 * @dataProvider data_links
	 *
	 * @param array $attributes Attributes of the block.
	 * @param bool  $linked     Whether the name is linked.
	 */
	public function test_should_link_the_name_when_it_is_requested_with_a_target( $attributes, $linked ) {
		$output = $this->render( $attributes );

		if ( $linked ) {
			$this->assertStringContainsString( '<a href="' . esc_url( get_author_posts_url( self::$author_id ) ) . '" target="_blank">Jane Doe</a>', $output );
		} else {
			$this->assertStringNotContainsString( '<a ', $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_links() {
		return array(
			'no link'                 => array( array(), false ),
			'a link without a target' => array( array( 'isLink' => true ), false ),
			'a target without a link' => array( array( 'linkTarget' => '_blank' ), false ),
			'a link that is false'    => array(
				array(
					'isLink'     => false,
					'linkTarget' => '_blank',
				),
				false,
			),
			'a link and a target'     => array(
				array(
					'isLink'     => true,
					'linkTarget' => '_blank',
				),
				true,
			),
		);
	}

	/**
	 * Tests that the URL and the target of the link are escaped.
	 *
	 * @ticket 66540
	 */
	public function test_should_escape_the_url_and_the_target_of_the_link() {
		add_filter(
			'author_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$output = $this->render(
			array(
				'isLink'     => true,
				'linkTarget' => '"><script>',
			)
		);

		$this->assertStringContainsString( '<a href="https://example.org/?a=1&#038;b=2" target="&quot;&gt;&lt;script&gt;">', $output );
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
			'a justification'             => array( array( 'itemsJustification' => 'center' ), '<div class="items-justified-center">' ),
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
	 * @covers ::register_block_core_post_author
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-author' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-author' );
		}

		register_block_core_post_author();
		$block_type = $registry->get_registered( 'core/post-author' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-author' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_author', $block_type->render_callback );
	}
}
