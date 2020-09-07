<?php

/**
 * @group image
 * @group media
 * @group upload
 */
class Tests_Image_Meta extends WP_UnitTestCase {
	function setUp() {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'The gd PHP extension is not loaded.' );
		}
		if ( ! extension_loaded( 'exif' ) ) {
			$this->markTestSkipped( 'The exif PHP extension is not loaded.' );
		}

		parent::setUp();
	}

	function test_exif_d70() {
		// Exif from a Nikon D70.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/2004-07-22-DSC_0008.jpg' );

		$this->assertEquals( 6.3, $out['aperture'] );
		$this->assertSame( '', $out['credit'] );
		$this->assertSame( 'NIKON D70', $out['camera'] );
		$this->assertSame( '', $out['caption'] );
		$this->assertEquals( strtotime( '2004-07-22 17:14:59' ), $out['created_timestamp'] );
		$this->assertSame( '', $out['copyright'] );
		$this->assertEquals( 27, $out['focal_length'] );
		$this->assertEquals( 400, $out['iso'] );
		$this->assertEquals( 1 / 40, $out['shutter_speed'] );
		$this->assertSame( '', $out['title'] );
	}

	function test_exif_d70_mf() {
		// Exif from a Nikon D70 - manual focus lens, so some data is unavailable.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG' );

		$this->assertSame( '0', $out['aperture'] );
		$this->assertSame( '', $out['credit'] );
		$this->assertSame( 'NIKON D70', $out['camera'] );
		$this->assertSame( '', $out['caption'] );
		$this->assertEquals( strtotime( '2007-06-17 21:18:00' ), $out['created_timestamp'] );
		$this->assertSame( '', $out['copyright'] );
		$this->assertEquals( 0, $out['focal_length'] );
		$this->assertEquals( 0, $out['iso'] ); // Interesting - a Nikon bug?
		$this->assertEquals( 1 / 500, $out['shutter_speed'] );
		$this->assertSame( '', $out['title'] );
		// $this->assertSame( array( 'Flowers' ), $out['keywords'] );
	}

	function test_exif_d70_iptc() {
		// Exif from a Nikon D70 with IPTC data added later.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/2004-07-22-DSC_0007.jpg' );

		$this->assertEquals( 6.3, $out['aperture'] );
		$this->assertSame( 'IPTC Creator', $out['credit'] );
		$this->assertSame( 'NIKON D70', $out['camera'] );
		$this->assertSame( 'IPTC Caption', $out['caption'] );
		$this->assertEquals( strtotime( '2004-07-22 17:14:35' ), $out['created_timestamp'] );
		$this->assertSame( 'IPTC Copyright', $out['copyright'] );
		$this->assertEquals( 18, $out['focal_length'] );
		$this->assertEquals( 200, $out['iso'] );
		$this->assertEquals( 1 / 25, $out['shutter_speed'] );
		$this->assertSame( 'IPTC Headline', $out['title'] );
	}

	function test_exif_fuji() {
		// Exif from a Fuji FinePix S5600 (thanks Mark).
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/a2-small.jpg' );

		$this->assertEquals( 4.5, $out['aperture'] );
		$this->assertSame( '', $out['credit'] );
		$this->assertSame( 'FinePix S5600', $out['camera'] );
		$this->assertSame( '', $out['caption'] );
		$this->assertEquals( strtotime( '2007-09-03 10:17:03' ), $out['created_timestamp'] );
		$this->assertSame( '', $out['copyright'] );
		$this->assertEquals( 6.3, $out['focal_length'] );
		$this->assertEquals( 64, $out['iso'] );
		$this->assertEquals( 1 / 320, $out['shutter_speed'] );
		$this->assertSame( '', $out['title'] );

	}

	/**
	 * @ticket 6571
	 */
	function test_exif_error() {
		// https://core.trac.wordpress.org/ticket/6571
		// This triggers a warning mesage when reading the Exif block.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/waffles.jpg' );

		$this->assertEquals( 0, $out['aperture'] );
		$this->assertSame( '', $out['credit'] );
		$this->assertSame( '', $out['camera'] );
		$this->assertSame( '', $out['caption'] );
		$this->assertEquals( 0, $out['created_timestamp'] );
		$this->assertSame( '', $out['copyright'] );
		$this->assertEquals( 0, $out['focal_length'] );
		$this->assertEquals( 0, $out['iso'] );
		$this->assertEquals( 0, $out['shutter_speed'] );
		$this->assertSame( '', $out['title'] );
	}

	function test_exif_no_data() {
		// No Exif data in this image (from burningwell.org).
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/canola.jpg' );

		$this->assertEquals( 0, $out['aperture'] );
		$this->assertSame( '', $out['credit'] );
		$this->assertSame( '', $out['camera'] );
		$this->assertSame( '', $out['caption'] );
		$this->assertEquals( 0, $out['created_timestamp'] );
		$this->assertSame( '', $out['copyright'] );
		$this->assertEquals( 0, $out['focal_length'] );
		$this->assertEquals( 0, $out['iso'] );
		$this->assertEquals( 0, $out['shutter_speed'] );
		$this->assertSame( '', $out['title'] );
	}

	/**
	 * @ticket 9417
	 */
	function test_utf8_iptc_tags() {
		// Trilingual UTF-8 text in the ITPC caption-abstract field.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/test-image-iptc.jpg' );

		$this->assertSame( 'This is a comment. / Это комментарий. / Βλέπετε ένα σχόλιο.', $out['caption'] );
	}

	/**
	 * wp_read_image_metadata() should return false if the image file doesn't exist.
	 */
	public function test_missing_image_file() {
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/404_image.png' );
		$this->assertFalse( $out );
	}


	/**
	 * @ticket 33772
	 */
	public function test_exif_keywords() {
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/33772.jpg' );

		$this->assertSame( '8', $out['aperture'] );
		$this->assertSame( 'Photoshop Author', $out['credit'] );
		$this->assertSame( 'DMC-LX2', $out['camera'] );
		$this->assertSame( 'Photoshop Description', $out['caption'] );
		$this->assertEquals( 1306315327, $out['created_timestamp'] );
		$this->assertSame( 'Photoshop Copyrright Notice', $out['copyright'] );
		$this->assertSame( '6.3', $out['focal_length'] );
		$this->assertSame( '100', $out['iso'] );
		$this->assertSame( '0.0025', $out['shutter_speed'] );
		$this->assertSame( 'Photoshop Document Ttitle', $out['title'] );
		$this->assertEquals( 1, $out['orientation'] );
		$this->assertSame( array( 'beach', 'baywatch', 'LA', 'sunset' ), $out['keywords'] );
	}

}
