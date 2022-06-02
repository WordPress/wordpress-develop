<?php

/**
 * @group image
 * @group media
 * @group upload
 * @group resize
 */
require_once __DIR__ . '/base.php';

abstract class WP_Tests_Image_Resize_UnitTestCase extends WP_Image_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'wp_image_editors', array( $this, 'wp_image_editors' ) );
	}

	public function wp_image_editors() {
		return array( $this->editor_engine );
	}

	public function test_resize_jpg() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/test-image.jpg', 25, 25 );

		$this->assertSame( 'test-image-25x25.jpg', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 25, $w );
		$this->assertSame( 25, $h );
		$this->assertSame( IMAGETYPE_JPEG, $type );

		unlink( $image );
	}

	public function test_resize_png() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/test-image.png', 25, 25 );

		if ( ! is_string( $image ) ) {  // WP_Error, stop GLib-GObject-CRITICAL assertion.
			$this->fail( sprintf( 'No PNG support in the editor engine %s on this system.', $this->editor_engine ) );
		}

		$this->assertSame( 'test-image-25x25.png', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 25, $w );
		$this->assertSame( 25, $h );
		$this->assertSame( IMAGETYPE_PNG, $type );

		unlink( $image );
	}

	public function test_resize_gif() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/test-image.gif', 25, 25 );

		if ( ! is_string( $image ) ) {  // WP_Error, stop GLib-GObject-CRITICAL assertion.
			$this->fail( sprintf( 'No GIF support in the editor engine %s on this system.', $this->editor_engine ) );
		}

		$this->assertSame( 'test-image-25x25.gif', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 25, $w );
		$this->assertSame( 25, $h );
		$this->assertSame( IMAGETYPE_GIF, $type );

		unlink( $image );
	}

	public function test_resize_webp() {
		$file   = DIR_TESTDATA . '/images/test-image.webp';
		$editor = wp_get_image_editor( $file );

		// Check if the editor supports the webp mime type.
		if ( is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/webp' ) ) {
			$this->markTestSkipped( sprintf( 'No WebP support in the editor engine %s on this system.', $this->editor_engine ) );
		}

		$image = $this->resize_helper( $file, 25, 25 );
		$this->assertSame( 'test-image-25x25.webp', wp_basename( $image ) );
		list($w, $h, $type) = wp_getimagesize( $image );
		$this->assertSame( 25, $w );
		$this->assertSame( 25, $h );
		$this->assertSame( IMAGETYPE_WEBP, $type );
		unlink( $image );
	}

	public function test_resize_larger() {
		// image_resize() should refuse to make an image larger.
		$image = $this->resize_helper( DIR_TESTDATA . '/images/test-image.jpg', 100, 100 );

		$this->assertInstanceOf( 'WP_Error', $image );
		$this->assertSame( 'error_getting_dimensions', $image->get_error_code() );
	}

	public function test_resize_thumb_128x96() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG', 128, 96 );

		$this->assertSame( '2007-06-17DSC_4173-64x96.jpg', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 64, $w );
		$this->assertSame( 96, $h );
		$this->assertSame( IMAGETYPE_JPEG, $type );

		unlink( $image );
	}

	public function test_resize_thumb_128x0() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG', 128, 0 );

		$this->assertSame( '2007-06-17DSC_4173-128x193.jpg', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 128, $w );
		$this->assertSame( 193, $h );
		$this->assertSame( IMAGETYPE_JPEG, $type );

		unlink( $image );
	}

	public function test_resize_thumb_0x96() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG', 0, 96 );

		$this->assertSame( '2007-06-17DSC_4173-64x96.jpg', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 64, $w );
		$this->assertSame( 96, $h );
		$this->assertSame( IMAGETYPE_JPEG, $type );

		unlink( $image );
	}

	public function test_resize_thumb_150x150_crop() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG', 150, 150, true );

		$this->assertSame( '2007-06-17DSC_4173-150x150.jpg', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 150, $w );
		$this->assertSame( 150, $h );
		$this->assertSame( IMAGETYPE_JPEG, $type );

		unlink( $image );
	}

	public function test_resize_thumb_150x100_crop() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG', 150, 100, true );

		$this->assertSame( '2007-06-17DSC_4173-150x100.jpg', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 150, $w );
		$this->assertSame( 100, $h );
		$this->assertSame( IMAGETYPE_JPEG, $type );

		unlink( $image );
	}

	public function test_resize_thumb_50x150_crop() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG', 50, 150, true );

		$this->assertSame( '2007-06-17DSC_4173-50x150.jpg', wp_basename( $image ) );
		list($w, $h, $type) = getimagesize( $image );
		$this->assertSame( 50, $w );
		$this->assertSame( 150, $h );
		$this->assertSame( IMAGETYPE_JPEG, $type );

		unlink( $image );
	}

	/**
	 * Try resizing a non-existent image
	 *
	 * @ticket 6821
	 */
	public function test_resize_non_existent_image() {
		$image = $this->resize_helper( DIR_TESTDATA . '/images/test-non-existent-image.jpg', 25, 25 );

		$this->assertInstanceOf( 'WP_Error', $image );
		$this->assertSame( 'error_loading_image', $image->get_error_code() );
	}

	/**
	 * Function to help out the tests
	 */
	protected function resize_helper( $file, $width, $height, $crop = false ) {
		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$resized = $editor->resize( $width, $height, $crop );
		if ( is_wp_error( $resized ) ) {
			return $resized;
		}

		$dest_file = $editor->generate_filename();
		$saved     = $editor->save( $dest_file );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return $dest_file;
	}
}
