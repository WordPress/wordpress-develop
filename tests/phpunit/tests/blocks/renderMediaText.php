<?php
/**
 * Tests for the server-side rendering of the Media & Text block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_media_text
 */
class Tests_Blocks_RenderMediaText extends WP_UnitTestCase {

	/**
	 * Markup of the block, with the media on the left.
	 */
	const CONTENT = '<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"><p>Text</p></div></div>';

	/**
	 * ID of the attachment that is the featured image.
	 *
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Creates a post with a featured image and goes to it.
	 *
	 * @param string $alt Optional. The alt text of the image. Default none.
	 */
	private function go_to_post_with_featured_image( $alt = '' ) {
		$post_id             = self::factory()->post->create();
		$this->attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'featured.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $post_id,
			)
		);

		if ( '' !== $alt ) {
			update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', $alt );
		}

		set_post_thumbnail( $post_id, $this->attachment_id );

		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['post'] = get_post( $post_id );
	}

	/**
	 * Renders the block.
	 *
	 * @param array       $attributes The attributes of the block that differ from the defaults.
	 * @param string|null $content    Optional. The content of the block. Default the block with the media on the left.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $content = null ) {
		$attributes = array_merge( array( 'useFeaturedImage' => true ), $attributes );

		return render_block_core_media_text( $attributes, $content ?? self::CONTENT );
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $query ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests that the content is returned as it is when the featured image is not used.
	 *
	 * @ticket 66592
	 */
	public function test_should_return_the_content_when_the_featured_image_is_not_used() {
		$this->go_to_post_with_featured_image();

		$this->assertSame( self::CONTENT, $this->render( array( 'useFeaturedImage' => false ) ) );
	}

	/**
	 * Tests that the content is returned as it is when the post has no featured image.
	 *
	 * @ticket 66592
	 */
	public function test_should_return_the_content_when_the_post_has_no_featured_image() {
		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['post'] = get_post( $post_id );

		$this->assertSame( self::CONTENT, $this->render() );
	}

	/**
	 * Tests the featured image in the figure.
	 *
	 * @ticket 66592
	 */
	public function test_should_add_the_featured_image_to_the_figure() {
		$this->go_to_post_with_featured_image();

		$output = $this->render();
		$image  = $this->get_element( $output, 'img' );
		$figure = $this->get_element( $output, 'figure' );

		$this->assertSame( wp_get_attachment_url( $this->attachment_id ), $image['src'] );
		$this->assertSame( "wp-image-{$this->attachment_id} size-full", $image['class'] );
		$this->assertSame( '', $image['alt'] );
		$this->assertArrayNotHasKey( 'style', $image, 'The image should have no position without the image fill.' );
		$this->assertSame( array( 'class' => 'wp-block-media-text__media' ), $figure, 'The temporary ID should be removed from the figure.' );
		$this->assertMatchesRegularExpression( '/<figure\s+class="wp-block-media-text__media"><img /', $output );
		$this->assertStringContainsString( '<div class="wp-block-media-text__content"><p>Text</p></div>', $output );
	}

	/**
	 * Tests the size slug and the alt text of the image.
	 *
	 * @ticket 66592
	 */
	public function test_should_use_the_size_slug_and_the_alt_text() {
		$this->go_to_post_with_featured_image( "  <b>Alt</b> text\n" );

		$image = $this->get_element( $this->render( array( 'mediaSizeSlug' => 'medium' ) ), 'img' );

		$this->assertSame( "wp-image-{$this->attachment_id} size-medium", $image['class'] );
		$this->assertSame( 'Alt text', $image['alt'] );
	}

	/**
	 * Tests the featured image when the media is on the right.
	 *
	 * @ticket 66592
	 */
	public function test_should_add_the_featured_image_to_the_last_figure_when_the_media_is_on_the_right() {
		$this->go_to_post_with_featured_image();

		$content = '<div class="wp-block-media-text has-media-on-the-right"><div class="wp-block-media-text__content"><figure class="wp-block-media-text__media">First</figure></div><figure class="wp-block-media-text__media">Last</figure></div>';
		$output  = $this->render( array( 'mediaPosition' => 'right' ), $content );

		$this->assertStringContainsString( '<figure class="wp-block-media-text__media">First</figure>', $output );
		$this->assertMatchesRegularExpression( '/<figure [^>]*class="wp-block-media-text__media"[^>]*><img [^>]*>Last<\/figure>/', $output );
		$this->assertSame( 1, substr_count( $output, '<img ' ) );
	}

	/**
	 * Tests the featured image when the media is on the left: the first figure is used.
	 *
	 * @ticket 66592
	 */
	public function test_should_add_the_featured_image_to_the_first_figure_when_the_media_is_on_the_left() {
		$this->go_to_post_with_featured_image();

		$content = '<div class="wp-block-media-text"><figure class="wp-block-media-text__media">First</figure><div class="wp-block-media-text__content"><figure class="wp-block-media-text__media">Last</figure></div></div>';
		$output  = $this->render( array( 'mediaPosition' => 'left' ), $content );

		$this->assertMatchesRegularExpression( '/<figure\s+class="wp-block-media-text__media"><img [^>]*>First<\/figure>/', $output );
		$this->assertStringContainsString( '<figure class="wp-block-media-text__media">Last</figure>', $output );
		$this->assertSame( 1, substr_count( $output, '<img ' ) );
	}

	/**
	 * Tests that the content without a figure is left as it is.
	 *
	 * @ticket 66592
	 */
	public function test_should_leave_content_without_a_figure_as_it_is() {
		$this->go_to_post_with_featured_image();

		$content = '<div class="wp-block-media-text"><div class="wp-block-media-text__content"><p>Text</p></div></div>';

		$this->assertSame( $content, $this->render( array(), $content ) );
		$this->assertSame( $content, $this->render( array( 'mediaPosition' => 'right' ), $content ) );
	}

	/**
	 * Tests the image fill.
	 *
	 * @ticket 66592
	 */
	public function test_should_fill_with_the_image() {
		$this->go_to_post_with_featured_image();

		$content = '<div class="wp-block-media-text is-image-fill"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"></div></div>';
		$output  = $this->render( array( 'imageFill' => true ), $content );
		$block   = $this->get_element( $output, array( 'class_name' => 'wp-block-media-text' ) );
		$image   = $this->get_element( $output, 'img' );

		$this->assertSame( 'wp-block-media-text is-image-fill-element', $block['class'] );
		$this->assertSame( 'object-position:50% 50%;', $image['style'] );
	}

	/**
	 * Tests that the classes of the block are kept without the image fill.
	 *
	 * @ticket 66592
	 */
	public function test_should_keep_the_classes_of_the_block_without_the_image_fill() {
		$this->go_to_post_with_featured_image();

		$content = '<div class="wp-block-media-text is-image-fill"><figure class="wp-block-media-text__media"></figure></div>';

		foreach ( array( array(), array( 'imageFill' => false ) ) as $attributes ) {
			$output = $this->render( $attributes, $content );

			$this->assertSame( 'wp-block-media-text is-image-fill', $this->get_element( $output, array( 'class_name' => 'wp-block-media-text' ) )['class'] );
			$this->assertArrayNotHasKey( 'style', $this->get_element( $output, 'img' ) );
		}
	}

	/**
	 * Tests the focal point.
	 *
	 * @ticket 66592
	 *
	 * @dataProvider data_focal_points
	 *
	 * @param mixed  $focal_point The focal point attribute.
	 * @param string $expected    The expected position of the object.
	 */
	public function test_should_position_the_image_at_the_focal_point( $focal_point, $expected ) {
		$this->go_to_post_with_featured_image();

		$image = $this->get_element(
			$this->render(
				array(
					'imageFill'  => true,
					'focalPoint' => $focal_point,
				)
			),
			'img'
		);

		$this->assertSame( "object-position:$expected;", $image['style'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_focal_points() {
		return array(
			'corner'           => array(
				array(
					'x' => 0,
					'y' => 0,
				),
				'0% 0%',
			),
			'point'            => array(
				array(
					'x' => 0.25,
					'y' => 0.75,
				),
				'25% 75%',
			),
			'rounded'          => array(
				array(
					'x' => 0.333,
					'y' => 0.666,
				),
				'33% 67%',
			),
			'numeric strings'  => array(
				array(
					'x' => '0.5',
					'y' => '1',
				),
				'50% 100%',
			),
			'no focal point'   => array( null, '50% 50%' ),
			'not an array'     => array( '0.1,0.2', '50% 50%' ),
			'x is missing'     => array( array( 'y' => 0.2 ), '50% 50%' ),
			'y is missing'     => array( array( 'x' => 0.2 ), '50% 50%' ),
			'x is not numeric' => array(
				array(
					'x' => 'left',
					'y' => 0.2,
				),
				'50% 50%',
			),
			'y is not numeric' => array(
				array(
					'x' => 0.2,
					'y' => 'top',
				),
				'50% 50%',
			),
			'x is null'        => array(
				array(
					'x' => null,
					'y' => 0.2,
				),
				'50% 50%',
			),
		);
	}

	/**
	 * Tests that the focal point is only used with the image fill.
	 *
	 * @ticket 66592
	 */
	public function test_should_not_position_the_image_without_the_image_fill() {
		$this->go_to_post_with_featured_image();

		$image = $this->get_element(
			$this->render(
				array(
					'focalPoint' => array(
						'x' => 0.1,
						'y' => 0.2,
					),
				)
			),
			'img'
		);

		$this->assertArrayNotHasKey( 'style', $image );
	}

	/**
	 * Tests that every block in the content gets the image fill classes.
	 *
	 * @ticket 66592
	 */
	public function test_should_add_the_image_fill_class_to_every_block() {
		$this->go_to_post_with_featured_image();

		$content = self::CONTENT . self::CONTENT;
		$output  = $this->render( array( 'imageFill' => true ), $content );

		$this->assertSame( 2, substr_count( $output, 'is-image-fill-element' ) );
	}

	/**
	 * Tests that the URL of the image is escaped.
	 *
	 * @ticket 66592
	 */
	public function test_should_escape_the_url_of_the_featured_image() {
		$this->go_to_post_with_featured_image();

		add_filter(
			'post_thumbnail_url',
			static function () {
				return 'https://example.org/a b.jpg?x=1&y=2';
			}
		);

		$output = $this->render();

		$this->assertStringContainsString( ' src="https://example.org/a%20b.jpg?x=1&#038;y=2" ', $output );
	}
}
