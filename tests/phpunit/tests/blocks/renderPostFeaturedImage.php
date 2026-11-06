<?php
/**
 * Tests for the server-side rendering of the Post Featured Image block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group post-thumbnails
 *
 * @covers ::render_block_core_post_featured_image
 */
class Tests_Blocks_RenderPostFeaturedImage extends WP_UnitTestCase {

	/**
	 * ID of an image.
	 *
	 * @var int
	 */
	private static $image_id;

	/**
	 * ID of a post that has a featured image and a title.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post that has a featured image and no title.
	 *
	 * @var int
	 */
	private static $untitled_post_id;

	/**
	 * ID of a post without a featured image.
	 *
	 * @var int
	 */
	private static $post_without_image_id;

	/**
	 * ID of a post without a featured image, with an image in its content.
	 *
	 * @var int
	 */
	private static $post_with_content_image_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$image_id = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		self::$post_id          = $factory->post->create( array( 'post_title' => 'Hello <b>world</b>' ) );
		self::$untitled_post_id = $factory->post->create( array( 'post_title' => '' ) );

		set_post_thumbnail( self::$post_id, self::$image_id );
		set_post_thumbnail( self::$untitled_post_id, self::$image_id );

		self::$post_without_image_id      = $factory->post->create();
		self::$post_with_content_image_id = $factory->post->create(
			array(
				'post_content' => '<p>Text</p><img src="https://example.org/first.jpg" alt="First" class="first" width="10" /><img src="https://example.org/second.jpg" />',
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
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$attributes = array_merge(
			array(
				'useFirstImageFromPost' => false,
				'linkTarget'            => '_self',
			),
			$attributes
		);

		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-featured-image',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_featured_image( $attributes, '', $block );
	}

	/**
	 * Gets the attributes of the first tag of a type of the output.
	 *
	 * @param string $output Rendered block.
	 * @param string $tag    Name of the tag.
	 * @return array|null The attributes, keyed by name, or null when there is no such tag.
	 */
	private function get_attributes_of( $output, $tag ) {
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
	 * Tests that nothing is rendered without a post or without an image.
	 *
	 * @ticket 66546
	 */
	public function test_should_render_nothing_without_a_post_or_an_image() {
		$this->assertSame( '', $this->render( array(), array() ), 'There should be nothing without a post.' );
		$this->assertSame( '', $this->render( array(), array( 'postId' => self::$post_without_image_id ) ), 'There should be nothing without an image.' );
	}

	/**
	 * Tests the featured image.
	 *
	 * @ticket 66546
	 */
	public function test_should_render_the_featured_image_in_a_figure() {
		$output = $this->render();
		$image  = $this->get_attributes_of( $output, 'img' );

		$this->assertStringStartsWith( '<figure >', $output );
		$this->assertStringEndsWith( '</figure>', $output );
		$this->assertContains( 'wp-post-image', preg_split( '/\s+/', $image['class'] ) );
		$this->assertContains( 'size-post-thumbnail', preg_split( '/\s+/', $image['class'] ) );
		$this->assertStringNotContainsString( '<a ', $output, 'The image should not be linked.' );
	}

	/**
	 * Tests the size of the image.
	 *
	 * @ticket 66546
	 */
	public function test_should_use_the_size_of_the_attributes() {
		$image = $this->get_attributes_of( $this->render( array( 'sizeSlug' => 'thumbnail' ) ), 'img' );

		$this->assertContains( 'size-thumbnail', preg_split( '/\s+/', $image['class'] ) );
	}

	/**
	 * Tests the link to the post.
	 *
	 * @ticket 66546
	 */
	public function test_should_link_the_image_to_the_post() {
		$output = $this->render( array( 'isLink' => true ) );
		$link   = $this->get_attributes_of( $output, 'a' );

		$this->assertSame( get_permalink( self::$post_id ), $link['href'] );
		$this->assertSame( '_self', $link['target'] );
		$this->assertArrayNotHasKey( 'rel', $link );
		$this->assertStringStartsWith( '<figure ><a ', $output );
		$this->assertStringEndsWith( '</a></figure>', $output );
	}

	/**
	 * Tests the target and the rel of the link.
	 *
	 * @ticket 66546
	 */
	public function test_should_add_the_target_and_the_rel_of_the_link() {
		$link = $this->get_attributes_of(
			$this->render(
				array(
					'isLink'     => true,
					'linkTarget' => '_blank',
					'rel'        => 'noopener nofollow',
				)
			),
			'a'
		);

		$this->assertSame( '_blank', $link['target'] );
		$this->assertSame( 'noopener nofollow', $link['rel'] );
	}

	/**
	 * Tests that the target, the rel and the URL of the link are escaped.
	 *
	 * @ticket 66546
	 */
	public function test_should_escape_the_attributes_of_the_link() {
		add_filter(
			'post_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$output = $this->render(
			array(
				'isLink'     => true,
				'linkTarget' => '"><script>',
				'rel'        => '"><script>',
			)
		);

		$this->assertStringContainsString( 'href="https://example.org/?a=1&#038;b=2"', $output );
		$this->assertStringContainsString( 'target="&quot;&gt;&lt;script&gt;"', $output );
		$this->assertStringContainsString( 'rel="&quot;&gt;&lt;script&gt;"', $output );
	}

	/**
	 * Tests that the image is not linked unless it is requested.
	 *
	 * @ticket 66546
	 *
	 * @dataProvider data_values_that_do_not_link
	 *
	 * @param mixed $is_link Value of the attribute.
	 */
	public function test_should_not_link_the_image_unless_it_is_requested( $is_link ) {
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
	 * Tests the alternative text of a linked image.
	 *
	 * @ticket 66546
	 *
	 * @dataProvider data_alt_texts
	 *
	 * @param int    $post_key Key of the post: 'titled' or 'untitled'.
	 * @param string $expected Expected alternative text.
	 */
	public function test_should_use_the_title_of_the_post_as_the_alternative_text_of_a_linked_image( $post_key, $expected ) {
		$post_id = 'titled' === $post_key ? self::$post_id : self::$untitled_post_id;

		$image = $this->get_attributes_of( $this->render( array( 'isLink' => true ), array( 'postId' => $post_id ) ), 'img' );

		$this->assertSame( str_replace( '{id}', (string) $post_id, $expected ), $image['alt'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_alt_texts() {
		return array(
			'a post with a title'    => array( 'titled', 'Hello world' ),
			'a post without a title' => array( 'untitled', 'Untitled post {id}' ),
		);
	}

	/**
	 * Tests the styles of the image.
	 *
	 * @ticket 66546
	 *
	 * @dataProvider data_styles
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected style of the image.
	 */
	public function test_should_add_the_styles_of_the_attributes_to_the_image( $attributes, $expected ) {
		$image = $this->get_attributes_of( $this->render( $attributes ), 'img' );

		$this->assertSame( $expected, $image['style'] ?? '' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_styles() {
		return array(
			'nothing'                       => array( array(), '' ),
			'an aspect ratio'               => array( array( 'aspectRatio' => '16/9' ), 'aspect-ratio:16/9;width:100%;' ),
			'an automatic aspect ratio'     => array( array( 'aspectRatio' => 'auto' ), 'width:100%;' ),
			'an empty aspect ratio'         => array( array( 'aspectRatio' => '' ), '' ),
			'a height'                      => array( array( 'height' => '300px' ), 'height:300px;' ),
			'a width'                       => array( array( 'width' => '50%' ), 'height:auto;width:50%;' ),
			'a width and a height'          => array(
				array(
					'width'  => '50%',
					'height' => '200px',
				),
				'height:200px;width:50%;',
			),
			'empty values'                  => array(
				array(
					'width'  => '',
					'height' => null,
				),
				'',
			),
			'a width that is null'          => array( array( 'width' => null ), '' ),
			'a scale'                       => array( array( 'scale' => 'cover' ), 'object-fit:cover;' ),
			'a ratio, a height and a scale' => array(
				array(
					'aspectRatio' => '1',
					'height'      => '10px',
					'scale'       => 'contain',
				),
				'aspect-ratio:1;width:100%;height:10px;object-fit:contain;',
			),
			'a shadow'                      => array( array( 'style' => array( 'shadow' => '1px 1px 2px #000' ) ), 'box-shadow:1px 1px 2px #000;' ),
			'a border'                      => array( array( 'style' => array( 'border' => array( 'radius' => '5px' ) ) ), 'border-radius:5px;' ),
			'a border and a height'         => array(
				array(
					'height' => '10px',
					'style'  => array( 'border' => array( 'radius' => '5px' ) ),
				),
				'border-radius:5px;height:10px;',
			),
		);
	}

	/**
	 * Tests the classes of the border of the image.
	 *
	 * @ticket 66546
	 */
	public function test_should_add_the_classes_of_the_border_to_the_image() {
		$image = $this->get_attributes_of( $this->render( array( 'borderColor' => 'cyan' ) ), 'img' );

		$this->assertContains( 'has-cyan-border-color', preg_split( '/\s+/', $image['class'] ) );
	}

	/**
	 * Tests that the first image of the content is used.
	 *
	 * @ticket 66546
	 */
	public function test_should_use_the_first_image_of_the_content_when_it_is_requested() {
		$context = array( 'postId' => self::$post_with_content_image_id );

		$this->assertSame( '', $this->render( array(), $context ), 'The image should not be used unless it is requested.' );

		$output = $this->render( array( 'useFirstImageFromPost' => true ), $context );
		$image  = $this->get_attributes_of( $output, 'img' );

		$this->assertStringStartsWith( '<figure ><img ', $output );
		$this->assertSame( 'https://example.org/first.jpg', $image['src'] );
		$this->assertSame( 'First', $image['alt'] );
		$this->assertSame( 'first', $image['class'] );
		$this->assertSame( '10', $image['width'] );
		$this->assertSame( 1, substr_count( $output, '<img' ), 'Only the first image should be used.' );
	}

	/**
	 * Tests that the styles are added to the first image of the content.
	 *
	 * @ticket 66546
	 */
	public function test_should_add_the_styles_to_the_first_image_of_the_content() {
		$image = $this->get_attributes_of(
			$this->render(
				array(
					'useFirstImageFromPost' => true,
					'height'                => '10px',
				),
				array( 'postId' => self::$post_with_content_image_id )
			),
			'img'
		);

		$this->assertSame( 'height:10px;', $image['style'] );
	}

	/**
	 * Tests that the style of the first image of the content is kept.
	 *
	 * @ticket 66546
	 */
	public function test_should_keep_the_style_of_the_first_image_of_the_content() {
		$post_id = self::factory()->post->create( array( 'post_content' => '<img src="https://example.org/first.jpg" style="color:red" />' ) );

		$image = $this->get_attributes_of(
			$this->render(
				array(
					'useFirstImageFromPost' => true,
					'height'                => '10px',
				),
				array( 'postId' => $post_id )
			),
			'img'
		);

		$this->assertSame( 'color:red;height:10px;', $image['style'] );
	}

	/**
	 * Tests that the featured image is used before the first image of the content.
	 *
	 * @ticket 66546
	 */
	public function test_should_use_the_featured_image_before_the_first_image_of_the_content() {
		$post_id = self::factory()->post->create( array( 'post_content' => '<img src="https://example.org/first.jpg" />' ) );
		set_post_thumbnail( $post_id, self::$image_id );

		$image = $this->get_attributes_of( $this->render( array( 'useFirstImageFromPost' => true ), array( 'postId' => $post_id ) ), 'img' );

		$this->assertNotSame( 'https://example.org/first.jpg', $image['src'] );
	}

	/**
	 * Tests that nothing is rendered when there is no image in the content.
	 *
	 * @ticket 66546
	 */
	public function test_should_render_nothing_without_an_image_in_the_content() {
		$this->assertSame( '', $this->render( array( 'useFirstImageFromPost' => true ), array( 'postId' => self::$post_without_image_id ) ) );
	}

	/**
	 * Tests the overlay.
	 *
	 * @ticket 66546
	 */
	public function test_should_add_the_overlay_after_the_image() {
		$overlay = get_block_core_post_featured_image_overlay_element_markup( array( 'dimRatio' => 50 ) );

		$this->assertStringEndsWith( $overlay . '</figure>', $this->render( array( 'dimRatio' => 50 ) ) );
		$this->assertStringEndsWith(
			$overlay . '</a></figure>',
			$this->render(
				array(
					'dimRatio' => 50,
					'isLink'   => true,
				)
			),
			'The overlay should be inside the link.'
		);
		$this->assertStringNotContainsString( 'wp-block-post-featured-image__overlay', $this->render(), 'There should be no overlay without a dim ratio.' );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66546
	 *
	 * @covers ::register_block_core_post_featured_image
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-featured-image' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-featured-image' );
		}

		register_block_core_post_featured_image();
		$block_type = $registry->get_registered( 'core/post-featured-image' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-featured-image' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_featured_image', $block_type->render_callback );
	}
}
