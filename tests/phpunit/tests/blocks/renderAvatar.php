<?php
/**
 * Tests for the server-side rendering of the Avatar block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_avatar
 */
class Tests_Blocks_RenderAvatar extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * ID of a user with a display name.
	 *
	 * @var int
	 */
	private static $user_id;

	/**
	 * Creates the user.
	 *
	 * @param WP_UnitTest_Factory $factory The factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'display_name' => 'Jane Writer',
				'user_email'   => 'jane-writer@example.org',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/avatar',
			'attrs'     => array(),
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes of the block.
	 * @param array $context    Optional. The context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName' => 'core/avatar',
				'attrs'     => $attributes,
			),
			$context
		);

		return render_block_core_avatar( $attributes, '', $block );
	}

	/**
	 * Returns the attributes of the first element of the output.
	 *
	 * @param string $output The output.
	 * @param string $tag    The tag name.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( $tag ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests the avatar of the user of the attributes.
	 *
	 * @ticket 66582
	 */
	public function test_should_render_the_avatar_of_the_user_of_the_attributes() {
		$output = $this->render( array( 'userId' => self::$user_id ) );
		$image  = $this->get_element( $output, 'img' );

		$this->assertStringStartsWith( '<div class="wp-block-avatar">', $output );
		$this->assertStringEndsWith( '</div>', $output );
		$this->assertSame( 'Jane Writer Avatar', $image['alt'] );
		$this->assertSame( '96', $image['width'] );
		$this->assertSame( '96', $image['height'] );
		$this->assertStringContainsString( 'wp-block-avatar__image', $image['class'] );
		$this->assertStringContainsString( 'avatar-96', $image['class'] );
		$this->assertStringContainsString( hash( 'sha256', 'jane-writer@example.org' ), $image['src'] );
		$this->assertNull( $this->get_element( $output, 'a' ), 'The avatar should not be linked by default.' );
	}

	/**
	 * Tests the size of the avatar.
	 *
	 * @ticket 66582
	 */
	public function test_should_use_the_size_attribute() {
		$image = $this->get_element(
			$this->render(
				array(
					'userId' => self::$user_id,
					'size'   => 48,
				)
			),
			'img'
		);

		$this->assertSame( '48', $image['width'] );
		$this->assertSame( '48', $image['height'] );
		$this->assertStringContainsString( 'avatar-48', $image['class'] );
	}

	/**
	 * Tests that the user of the attributes wins over the author of the post.
	 *
	 * @ticket 66582
	 */
	public function test_should_prefer_the_user_attribute_to_the_author_of_the_post() {
		$other_id = self::factory()->user->create( array( 'display_name' => 'Other Author' ) );
		$post_id  = self::factory()->post->create( array( 'post_author' => $other_id ) );

		$image = $this->get_element( $this->render( array( 'userId' => self::$user_id ), array( 'postId' => $post_id ) ), 'img' );

		$this->assertSame( 'Jane Writer Avatar', $image['alt'] );
	}

	/**
	 * Tests the avatar of the author of the post of the context.
	 *
	 * @ticket 66582
	 */
	public function test_should_render_the_avatar_of_the_author_of_the_post() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );

		$image = $this->get_element( $this->render( array(), array( 'postId' => $post_id ) ), 'img' );

		$this->assertSame( 'Jane Writer Avatar', $image['alt'] );
	}

	/**
	 * Tests the avatar of the author of the author archive.
	 *
	 * @ticket 66582
	 */
	public function test_should_render_the_avatar_of_the_author_of_the_archive() {
		$this->go_to( get_author_posts_url( self::$user_id ) );

		$image = $this->get_element( $this->render(), 'img' );

		$this->assertSame( 'Jane Writer Avatar', $image['alt'] );
	}

	/**
	 * Tests that nothing is rendered without an author.
	 *
	 * @ticket 66582
	 *
	 * @dataProvider data_no_author
	 *
	 * @param array $attributes The attributes of the block.
	 * @param array $context    The context of the block.
	 */
	public function test_should_render_nothing_without_an_author( $attributes, $context ) {
		$this->go_to( '/' );

		$this->assertSame( '', $this->render( $attributes, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_author() {
		return array(
			'no attributes or context' => array( array(), array() ),
			'empty user'               => array( array( 'userId' => 0 ), array() ),
			'post without author'      => array( array(), array( 'postId' => 0 ) ),
		);
	}

	/**
	 * Tests the link to the author archive.
	 *
	 * @ticket 66582
	 */
	public function test_should_link_the_avatar_to_the_author_archive() {
		$output = $this->render(
			array(
				'userId'     => self::$user_id,
				'isLink'     => true,
				'linkTarget' => '_self',
			)
		);
		$link   = $this->get_element( $output, 'a' );

		$this->assertSame( get_author_posts_url( self::$user_id ), $link['href'] );
		$this->assertSame( '_self', $link['target'] );
		$this->assertSame( 'wp-block-avatar__link', $link['class'] );
		$this->assertArrayNotHasKey( 'aria-label', $link );
		$this->assertStringContainsString( '<img', $output );
		$this->assertStringContainsString( '</a></div>', $output );
	}

	/**
	 * Tests the label of the link to the author archive in a new tab.
	 *
	 * @ticket 66582
	 */
	public function test_should_label_the_author_link_that_opens_in_a_new_tab() {
		$link = $this->get_element(
			$this->render(
				array(
					'userId'     => self::$user_id,
					'isLink'     => true,
					'linkTarget' => '_blank',
				)
			),
			'a'
		);

		$this->assertSame( '_blank', $link['target'] );
		$this->assertSame( '(Jane Writer author archive, opens in a new tab)', $link['aria-label'] );
	}

	/**
	 * Tests that the avatar is not linked when the link is off.
	 *
	 * @ticket 66582
	 */
	public function test_should_not_link_the_avatar_when_the_link_is_off() {
		$output = $this->render(
			array(
				'userId'     => self::$user_id,
				'isLink'     => false,
				'linkTarget' => '_blank',
			)
		);

		$this->assertNull( $this->get_element( $output, 'a' ) );
	}

	/**
	 * Tests that the border and the shadow are applied to the image.
	 *
	 * @ticket 66582
	 */
	public function test_should_apply_the_border_and_the_shadow_to_the_image() {
		$output = $this->render(
			array(
				'userId'      => self::$user_id,
				'borderColor' => 'vivid-red',
				'style'       => array(
					'border' => array(
						'radius' => '9px',
					),
					'shadow' => 'var:preset|shadow|natural',
				),
			)
		);
		$image  = $this->get_element( $output, 'img' );
		$styles = $image['style'];

		$this->assertStringContainsString( 'has-border-color', $image['class'] );
		$this->assertStringContainsString( 'has-vivid-red-border-color', $image['class'] );
		$this->assertStringContainsString( 'border-radius:9px', $styles );
		$this->assertStringContainsString( 'box-shadow:var(--wp--preset--shadow--natural)', $styles );
		$this->assertArrayNotHasKey( 'style', $this->get_element( $output, 'div' ), 'The styles should be on the image, not on the wrapper.' );
	}

	/**
	 * Tests that the image has no style without a border or a shadow.
	 *
	 * @ticket 66582
	 */
	public function test_should_not_add_a_style_to_the_image_without_border_and_shadow() {
		$image = $this->get_element( $this->render( array( 'userId' => self::$user_id ) ), 'img' );

		$this->assertArrayNotHasKey( 'style', $image );
	}

	/**
	 * Tests the avatar of the author of the comment of the context.
	 *
	 * @ticket 66582
	 */
	public function test_should_render_the_avatar_of_the_comment_author() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::factory()->post->create(),
				'comment_author'       => 'Commenter',
				'comment_author_email' => 'commenter@example.org',
			)
		);

		$image = $this->get_element( $this->render( array( 'size' => 64 ), array( 'commentId' => $comment_id ) ), 'img' );

		$this->assertSame( 'Commenter Avatar', $image['alt'] );
		$this->assertSame( '64', $image['width'] );
		$this->assertStringContainsString( hash( 'sha256', 'commenter@example.org' ), $image['src'] );
	}

	/**
	 * Tests that the border and the shadow are applied to the image of a comment author.
	 *
	 * @ticket 66582
	 */
	public function test_should_apply_the_border_and_the_shadow_to_the_image_of_the_comment_author() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );

		$image = $this->get_element(
			$this->render(
				array(
					'borderColor' => 'vivid-red',
					'style'       => array(
						'border' => array(
							'radius' => '9px',
						),
						'shadow' => 'var:preset|shadow|natural',
					),
				),
				array( 'commentId' => $comment_id )
			),
			'img'
		);

		$this->assertStringContainsString( 'has-vivid-red-border-color', $image['class'] );
		$this->assertStringContainsString( 'border-radius:9px', $image['style'] );
		$this->assertStringContainsString( 'box-shadow:var(--wp--preset--shadow--natural)', $image['style'] );
	}

	/**
	 * Tests that the comment wins over the user of the attributes.
	 *
	 * @ticket 66582
	 */
	public function test_should_prefer_the_comment_to_the_user_attribute() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::factory()->post->create(),
				'comment_author'  => 'Commenter',
			)
		);

		$image = $this->get_element( $this->render( array( 'userId' => self::$user_id ), array( 'commentId' => $comment_id ) ), 'img' );

		$this->assertSame( 'Commenter Avatar', $image['alt'] );
	}

	/**
	 * Tests that nothing is rendered for a comment that does not exist.
	 *
	 * @ticket 66582
	 */
	public function test_should_render_nothing_for_a_missing_comment() {
		$this->assertSame( '', $this->render( array( 'userId' => self::$user_id ), array( 'commentId' => 99999999 ) ) );
	}

	/**
	 * Tests the link to the website of the comment author.
	 *
	 * @ticket 66582
	 */
	public function test_should_link_the_avatar_to_the_website_of_the_comment_author() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => self::factory()->post->create(),
				'comment_author'     => 'Commenter',
				'comment_author_url' => 'https://example.org/commenter/',
			)
		);

		$attributes = array(
			'isLink'     => true,
			'linkTarget' => '_self',
		);

		$link = $this->get_element( $this->render( $attributes, array( 'commentId' => $comment_id ) ), 'a' );

		$this->assertSame( 'https://example.org/commenter/', $link['href'] );
		$this->assertSame( '_self', $link['target'] );
		$this->assertSame( 'wp-block-avatar__link', $link['class'] );
		$this->assertArrayNotHasKey( 'aria-label', $link );

		$attributes['linkTarget'] = '_blank';

		$link = $this->get_element( $this->render( $attributes, array( 'commentId' => $comment_id ) ), 'a' );

		$this->assertSame( '_blank', $link['target'] );
		$this->assertSame( '(Commenter website link, opens in a new tab)', $link['aria-label'] );
	}

	/**
	 * Tests that the avatar of a comment without a website is not linked.
	 *
	 * @ticket 66582
	 */
	public function test_should_not_link_the_avatar_of_a_comment_without_a_website() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => self::factory()->post->create(),
				'comment_author_url' => '',
			)
		);

		$output = $this->render(
			array(
				'isLink'     => true,
				'linkTarget' => '_self',
			),
			array( 'commentId' => $comment_id )
		);

		$this->assertNull( $this->get_element( $output, 'a' ) );
		$this->assertStringContainsString( '<img', $output );
	}

	/**
	 * Tests that the wrapper is empty when avatars are turned off.
	 *
	 * @ticket 66582
	 */
	public function test_should_render_an_empty_wrapper_when_avatars_are_off() {
		update_option( 'show_avatars', 0 );

		$this->assertSame( '<div class="wp-block-avatar"></div>', $this->render( array( 'userId' => self::$user_id ) ) );
	}
}
