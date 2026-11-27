<?php

/**
 * Tests for the `wp_image_add_srcset_and_sizes()` function.
 *
 * @group media
 * @covers ::wp_image_add_srcset_and_sizes
 */
class Tests_Media_Wp_Image_Add_Srcset_And_Sizes extends WP_UnitTestCase {

	protected static $attachment_id;
	protected static $image_meta;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$file                = DIR_TESTDATA . '/images/test-image-large.jpg';
		self::$attachment_id = $factory->attachment->create_upload_object( $file );
		self::$image_meta    = wp_get_attachment_metadata( self::$attachment_id );
	}

	public static function tear_down_after_class() {
		wp_delete_attachment( self::$attachment_id, true );
		parent::tear_down_after_class();
	}

	/**
	 * Tests that srcset and sizes attributes are added to a standard image tag.
	 *
	 * @ticket 65984
	 */
	public function test_add_srcset_and_sizes() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$image_tag = sprintf( '<img src="%s" width="300" height="225" alt="Test">', esc_url( $image_url ) );

		$actual = wp_image_add_srcset_and_sizes( $image_tag, self::$image_meta, self::$attachment_id );

		$processor = new WP_HTML_Tag_Processor( $actual );
		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertNotEmpty( $processor->get_attribute( 'srcset' ) );
		$this->assertNotEmpty( $processor->get_attribute( 'sizes' ) );
	}

	/**
	 * Tests that srcset and sizes attributes are added when src uses single quotes.
	 *
	 * @ticket 65984
	 */
	public function test_add_srcset_and_sizes_with_single_quoted_src() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$image_tag = sprintf( "<img src='%s' width='300' height='225' alt='Test'>", esc_url( $image_url ) );

		$actual = wp_image_add_srcset_and_sizes( $image_tag, self::$image_meta, self::$attachment_id );

		$processor = new WP_HTML_Tag_Processor( $actual );
		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertNotEmpty( $processor->get_attribute( 'srcset' ) );
		$this->assertNotEmpty( $processor->get_attribute( 'sizes' ) );
	}

	/**
	 * Tests that srcset and sizes attributes are added when src has extra whitespace or uppercase.
	 *
	 * @ticket 65984
	 */
	public function test_add_srcset_and_sizes_with_uppercase_and_spaced_attributes() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$image_tag = sprintf( '<img SRC = "%s" WIDTH="300" HEIGHT="225">', esc_url( $image_url ) );

		$actual = wp_image_add_srcset_and_sizes( $image_tag, self::$image_meta, self::$attachment_id );

		$processor = new WP_HTML_Tag_Processor( $actual );
		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertNotEmpty( $processor->get_attribute( 'srcset' ) );
		$this->assertNotEmpty( $processor->get_attribute( 'sizes' ) );
	}

	/**
	 * Tests that HTML5 void closing syntax without slash is preserved.
	 *
	 * @ticket 65984
	 */
	public function test_preserves_html5_void_closing_syntax() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$image_tag = sprintf( '<img src="%s" width="300" height="225">', esc_url( $image_url ) );

		$actual = wp_image_add_srcset_and_sizes( $image_tag, self::$image_meta, self::$attachment_id );

		$this->assertStringEndsWith( '>', $actual );
		$this->assertStringEndsNotWith( '/>', $actual );
	}

	/**
	 * Tests that XHTML self-closing slash is preserved.
	 *
	 * @ticket 65984
	 */
	public function test_preserves_xhtml_self_closing_syntax() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$image_tag = sprintf( '<img src="%s" width="300" height="225" />', esc_url( $image_url ) );

		$actual = wp_image_add_srcset_and_sizes( $image_tag, self::$image_meta, self::$attachment_id );

		$this->assertStringEndsWith( '/>', $actual );
	}

	/**
	 * Tests that wp_image_add_srcset_and_sizes() bails early when metadata has no sizes.
	 *
	 * @ticket 65984
	 */
	public function test_bails_when_no_sizes_in_meta() {
		$image_tag = '<img src="https://example.org/photo.jpg" width="300" height="225">';
		$meta      = array( 'file' => 'photo.jpg' );

		$this->assertSame( $image_tag, wp_image_add_srcset_and_sizes( $image_tag, $meta, self::$attachment_id ) );
	}

	/**
	 * Tests that wp_image_add_srcset_and_sizes() bails early when image tag lacks src.
	 *
	 * @ticket 65984
	 */
	public function test_bails_when_no_src_attribute() {
		$image_tag = '<img width="300" height="225" alt="Missing">';

		$this->assertSame( $image_tag, wp_image_add_srcset_and_sizes( $image_tag, self::$image_meta, self::$attachment_id ) );
	}

	/**
	 * Tests that non-img tags are left untouched.
	 *
	 * @ticket 65984
	 */
	public function test_bails_when_not_an_img_tag() {
		$tag = '<div src="https://example.org/photo.jpg">Not an image</div>';

		$this->assertSame( $tag, wp_image_add_srcset_and_sizes( $tag, self::$image_meta, self::$attachment_id ) );
	}

	/**
	 * Tests that img tags inside HTML comments are ignored.
	 *
	 * @ticket 65984
	 */
	public function test_does_not_modify_img_in_html_comment() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$comment   = sprintf( '<!-- <img src="%s" width="300" height="225"> -->', esc_url( $image_url ) );

		$this->assertSame( $comment, wp_image_add_srcset_and_sizes( $comment, self::$image_meta, self::$attachment_id ) );
	}

	/**
	 * Tests that an existing sizes attribute is preserved rather than overwritten.
	 *
	 * @ticket 65984
	 */
	public function test_preserves_existing_sizes_attribute() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$image_tag = sprintf( '<img src="%s" width="300" height="225" sizes="50vw">', esc_url( $image_url ) );

		$actual = wp_image_add_srcset_and_sizes( $image_tag, self::$image_meta, self::$attachment_id );

		$processor = new WP_HTML_Tag_Processor( $actual );
		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertSame( '50vw', $processor->get_attribute( 'sizes' ) );
		$this->assertNotEmpty( $processor->get_attribute( 'srcset' ) );
	}

	/**
	 * Tests that edited image hash mismatch causes an early bail.
	 *
	 * @ticket 65984
	 */
	public function test_bails_on_edited_image_hash_mismatch() {
		$image_url = wp_get_attachment_image_url( self::$attachment_id, 'medium' );
		$image_tag = sprintf( '<img src="%s" width="300" height="225">', esc_url( $image_url ) );

		$meta         = self::$image_meta;
		$meta['file'] = '2007/06/test-e1234567890123.jpg';

		$this->assertSame( $image_tag, wp_image_add_srcset_and_sizes( $image_tag, $meta, self::$attachment_id ) );
	}
}
