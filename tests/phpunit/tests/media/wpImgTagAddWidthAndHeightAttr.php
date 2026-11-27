<?php

/**
 * Tests for the `wp_img_tag_add_width_and_height_attr()` function.
 *
 * @group media
 * @covers ::wp_img_tag_add_width_and_height_attr
 */
class Tests_Media_Wp_Img_Tag_Add_Width_And_Height_Attr extends WP_UnitTestCase {

	protected static $attachment_id;
	protected static $attachment_width;
	protected static $attachment_height;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$file                    = DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG';
		self::$attachment_id     = $factory->attachment->create_upload_object( $file );
		self::$attachment_width  = 680;
		self::$attachment_height = 1024;
	}

	public static function tear_down_after_class() {
		wp_delete_attachment( self::$attachment_id, true );
		parent::tear_down_after_class();
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` adds dimension attributes to an image when they are missing.
	 *
	 * @ticket 50367
	 */
	public function test_add_width_and_height_when_missing() {
		$image_tag = '<img src="' . wp_get_attachment_image_url( self::$attachment_id, 'full' ) . '">';

		$this->assertEqualHTML(
			'<img width="' . self::$attachment_width . '" height="' . self::$attachment_height . '" src="' . wp_get_attachment_image_url( self::$attachment_id, 'full' ) . '">',
			wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id )
		);
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` does not add dimension attributes when disabled via filter.
	 *
	 * @ticket 50367
	 */
	public function test_do_not_add_width_and_height_when_disabled_via_filter() {
		add_filter( 'wp_img_tag_add_width_and_height_attr', '__return_false' );
		$image_tag = '<img src="' . wp_get_attachment_image_url( self::$attachment_id, 'full' ) . '">';

		$this->assertSame(
			$image_tag,
			wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id )
		);
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` does not add dimension attributes to an image without src.
	 *
	 * @ticket 50367
	 */
	public function test_do_not_add_width_and_height_without_src() {
		$image_tag = '<img>';

		$this->assertSame(
			$image_tag,
			wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id )
		);
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` respects the style attribute from the inline image format to
	 * correctly set width and height based on that.
	 *
	 * @ticket 59352
	 */
	public function test_consider_inline_image_style_attr_to_set_width_and_height() {
		// '85px' is the original width (680px) divided by 8, so the expected height is equivalently 1024/8=128.
		$image_tag = '<img src="' . wp_get_attachment_image_url( self::$attachment_id, 'full' ) . '" style="width: 85px;">';

		$this->assertEqualHTML(
			'<img width="85" height="128" src="' . wp_get_attachment_image_url( self::$attachment_id, 'full' ) . '" style="width: 85px;">',
			wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id )
		);
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` handles single-quoted `src` and `style` attributes.
	 *
	 * @ticket 65984
	 */
	public function test_add_width_and_height_with_single_quoted_attributes() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'full' );
		$image_tag = "<img src='" . esc_url( $image_url ) . "' style='width: 85px;'>";

		$actual    = wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id );
		$processor = new WP_HTML_Tag_Processor( $actual );

		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertSame( '85', $processor->get_attribute( 'width' ) );
		$this->assertSame( '128', $processor->get_attribute( 'height' ) );
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` handles uppercase tag names and spaced attributes.
	 *
	 * @ticket 65984
	 */
	public function test_add_width_and_height_with_uppercase_tag_and_spaced_attributes() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'full' );
		$image_tag = '<IMG SRC = "' . esc_url( $image_url ) . '" STYLE="width: 85px;">';

		$actual    = wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id );
		$processor = new WP_HTML_Tag_Processor( $actual );

		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertSame( '85', $processor->get_attribute( 'width' ) );
		$this->assertSame( '128', $processor->get_attribute( 'height' ) );
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` does not corrupt attribute values containing `<img`.
	 *
	 * @ticket 65984
	 */
	public function test_does_not_corrupt_img_substring_in_attribute_value() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'full' );
		$image_tag = '<img alt="Use <img tag" src="' . esc_url( $image_url ) . '">';

		$actual    = wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id );
		$processor = new WP_HTML_Tag_Processor( $actual );

		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertSame( 'Use <img tag', $processor->get_attribute( 'alt' ) );
		$this->assertSame( (string) self::$attachment_width, $processor->get_attribute( 'width' ) );
		$this->assertSame( (string) self::$attachment_height, $processor->get_attribute( 'height' ) );
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` ignores `style="width: ..."` inside other attributes.
	 *
	 * @ticket 65984
	 */
	public function test_ignores_style_pattern_inside_other_attributes() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'full' );
		$image_tag = '<img alt=\'style="width: 85px;"\' src="' . esc_url( $image_url ) . '">';

		$actual    = wp_img_tag_add_width_and_height_attr( $image_tag, 'the_content', self::$attachment_id );
		$processor = new WP_HTML_Tag_Processor( $actual );

		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertSame( (string) self::$attachment_width, $processor->get_attribute( 'width' ) );
		$this->assertSame( (string) self::$attachment_height, $processor->get_attribute( 'height' ) );
	}

	/**
	 * Tests that `wp_img_tag_add_width_and_height_attr()` ignores `<img>` markup inside HTML comments.
	 *
	 * @ticket 65984
	 */
	public function test_does_not_modify_img_in_html_comment() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'full' );
		$comment   = '<!-- <img src="' . esc_url( $image_url ) . '"> -->';

		$this->assertSame(
			$comment,
			wp_img_tag_add_width_and_height_attr( $comment, 'the_content', self::$attachment_id )
		);
	}
}
