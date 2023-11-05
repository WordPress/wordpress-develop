<?php

/**
 * @group image
 * @group media
 * @group upload
 * @requires extension gd
 * @requires extension exif
 *
 * @covers ::wp_read_image_metadata
 */
class Tests_Image_Meta extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';
		stream_wrapper_register( 'testimagemeta', 'WP_Test_Stream' );

		WP_Test_Stream::$data = array(
			'wp_read_image_metadata' => array(
				'/image1.jpg' => file_get_contents( DIR_TESTDATA . '/images/test-image-upside-down.jpg' ),
				'/image2.jpg' => file_get_contents( DIR_TESTDATA . '/images/2004-07-22-DSC_0007.jpg' ),
				'/image3.jpg' => file_get_contents( DIR_TESTDATA . '/images/33772.jpg' ),
			),
		);
	}

	public static function wpTearDownAfterClass() {
		stream_wrapper_unregister( 'testimagemeta' );
	}

	public function test_exif_d70() {
		// Exif from a Nikon D70.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/2004-07-22-DSC_0008.jpg' );

		$this->assertEquals( 6.3, $out['aperture'], 'Aperture value not equivalent' );
		$this->assertSame( '', $out['credit'], 'Credit value not the same' );
		$this->assertSame( 'NIKON D70', $out['camera'], 'Camera value not the same' );
		$this->assertSame( '', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( strtotime( '2004-07-22 17:14:59' ), $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( '', $out['copyright'], 'Copyright value not the same' );
		$this->assertEquals( 27, $out['focal_length'], 'Focal length value not equivalent' );
		$this->assertEquals( 400, $out['iso'], 'Iso value not equivalent' );
		$this->assertEquals( 1 / 40, $out['shutter_speed'], 'Shutter speed value not equivalent' );
		$this->assertSame( '', $out['title'], 'Title value not the same' );
	}

	public function test_exif_d70_mf() {
		// Exif from a Nikon D70 - manual focus lens, so some data is unavailable.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/2007-06-17DSC_4173.JPG' );

		$this->assertSame( '0', $out['aperture'], 'Aperture value not the same' );
		$this->assertSame( '', $out['credit'], 'Credit value not the same' );
		$this->assertSame( 'NIKON D70', $out['camera'], 'Camera value not the same' );
		$this->assertSame( '', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( strtotime( '2007-06-17 21:18:00' ), $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( '', $out['copyright'], 'Copyright value not the same' );
		$this->assertEquals( 0, $out['focal_length'], 'Focal length value not equivalent' );
		$this->assertEquals( 0, $out['iso'], 'Iso value not equivalent' ); // Interesting - a Nikon bug?
		$this->assertEquals( 1 / 500, $out['shutter_speed'], 'Shutter speed value not equivalent' );
		$this->assertSame( '', $out['title'], 'Title value not the same' );
		// $this->assertSame( array( 'Flowers' ), $out['keywords'] );
	}

	public function test_exif_d70_iptc() {
		// Exif from a Nikon D70 with IPTC data added later.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/2004-07-22-DSC_0007.jpg' );

		$this->assertEquals( 6.3, $out['aperture'], 'Aperture value not equivalent' );
		$this->assertSame( 'IPTC Creator', $out['credit'], 'Credit value not the same' );
		$this->assertSame( 'NIKON D70', $out['camera'], 'Camera value not the same' );
		$this->assertSame( 'IPTC Caption', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( strtotime( '2004-07-22 17:14:35' ), $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( 'IPTC Copyright', $out['copyright'], 'Copyright value not the same' );
		$this->assertEquals( 18, $out['focal_length'], 'Focal length value not equivalent' );
		$this->assertEquals( 200, $out['iso'], 'Iso value not equivalent' );
		$this->assertEquals( 1 / 25, $out['shutter_speed'], 'Shutter speed value not equivalent' );
		$this->assertSame( 'IPTC Headline', $out['title'], 'Title value not the same' );
	}

	public function test_exif_fuji() {
		// Exif from a Fuji FinePix S5600 (thanks Mark).
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/a2-small.jpg' );

		$this->assertEquals( 4.5, $out['aperture'], 'Aperture value not equivalent' );
		$this->assertSame( '', $out['credit'], 'Credit value not the same' );
		$this->assertSame( 'FinePix S5600', $out['camera'], 'Camera value not the same' );
		$this->assertSame( '', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( strtotime( '2007-09-03 10:17:03' ), $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( '', $out['copyright'], 'Copyright value not the same' );
		$this->assertEquals( 6.3, $out['focal_length'], 'Focal length value not equivalent' );
		$this->assertEquals( 64, $out['iso'], 'Iso value not equivalent' );
		$this->assertEquals( 1 / 320, $out['shutter_speed'], 'Shutter speed value not equivalent' );
		$this->assertSame( '', $out['title'], 'Title value not the same' );
	}

	/**
	 * @ticket 6571
	 */
	public function test_exif_error() {
		// https://core.trac.wordpress.org/ticket/6571
		// This triggers a warning mesage when reading the Exif block.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/waffles.jpg' );

		$this->assertEquals( 0, $out['aperture'], 'Aperture value not equivalent' );
		$this->assertSame( '', $out['credit'], 'Credit value not the same' );
		$this->assertSame( '', $out['camera'], 'Camera value not the same' );
		$this->assertSame( '', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( 0, $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( '', $out['copyright'], 'Copyright value not the same' );
		$this->assertEquals( 0, $out['focal_length'], 'Focal length value not equivalent' );
		$this->assertEquals( 0, $out['iso'], 'Iso value not equivalent' );
		$this->assertEquals( 0, $out['shutter_speed'], 'Shutter speed value not equivalent' );
		$this->assertSame( '', $out['title'], 'Title value not the same' );
	}

	public function test_exif_no_data() {
		// No Exif data in this image (from burningwell.org).
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/canola.jpg' );

		$this->assertEquals( 0, $out['aperture'], 'Aperture value not equivalent' );
		$this->assertSame( '', $out['credit'], 'Credit value not the same' );
		$this->assertSame( '', $out['camera'], 'Camera value not the same' );
		$this->assertSame( '', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( 0, $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( '', $out['copyright'], 'Copyright value not the same' );
		$this->assertEquals( 0, $out['focal_length'], 'Focal length value not equivalent' );
		$this->assertEquals( 0, $out['iso'], 'Iso value not equivalent' );
		$this->assertEquals( 0, $out['shutter_speed'], 'Shutter speed value not equivalent' );
		$this->assertSame( '', $out['title'], 'Title value not the same' );
	}

	/**
	 * @ticket 9417
	 */
	public function test_utf8_iptc_tags() {
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

		$this->assertSame( '8', $out['aperture'], 'Aperture value not the same' );
		$this->assertSame( 'Photoshop Author', $out['credit'], 'Credit value not the same' );
		$this->assertSame( 'DMC-LX2', $out['camera'], 'Camera value not the same' );
		$this->assertSame( 'Photoshop Description', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( 1306315327, $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( 'Photoshop Copyrright Notice', $out['copyright'], 'Copyright value not the same' );
		$this->assertSame( '6.3', $out['focal_length'], 'Focal length value not the same' );
		$this->assertSame( '100', $out['iso'], 'Iso value not the same' );
		$this->assertSame( '0.0025', $out['shutter_speed'], 'Shutter speed value not the same' );
		$this->assertSame( 'Photoshop Document Ttitle', $out['title'], 'Title value not the same' );
		$this->assertEquals( 1, $out['orientation'], 'Orientation value not equivalent' );
		$this->assertSame( array( 'beach', 'baywatch', 'LA', 'sunset' ), $out['keywords'], 'Keywords not the same' );
	}

	/**
	 * @dataProvider data_stream
	 *
	 * @ticket 52826
	 * @ticket 52922
	 *
	 * @param string Stream's URI.
	 * @param array  Expected metadata.
	 */
	public function test_stream( $file, $expected ) {
		$actual = wp_read_image_metadata( $file );

		$this->assertSame( $expected, $actual );
	}

	public function data_stream() {
		return array(
			'Orientation only metadata'                => array(
				'file'     => 'testimagemeta://wp_read_image_metadata/image1.jpg',
				'metadata' => array(
					'aperture'          => '0',
					'credit'            => '',
					'camera'            => '',
					'caption'           => '',
					'created_timestamp' => '0',
					'copyright'         => '',
					'focal_length'      => '0',
					'iso'               => '0',
					'shutter_speed'     => '0',
					'title'             => '',
					'orientation'       => '3',
					'keywords'          => array(),
				),
			),
			'Exif from a Nikon D70 with IPTC data added later' => array(
				'file'     => 'testimagemeta://wp_read_image_metadata/image2.jpg',
				'metadata' => array(
					'aperture'          => '6.3',
					'credit'            => 'IPTC Creator',
					'camera'            => 'NIKON D70',
					'caption'           => 'IPTC Caption',
					'created_timestamp' => '1090516475',
					'copyright'         => 'IPTC Copyright',
					'focal_length'      => '18',
					'iso'               => '200',
					'shutter_speed'     => '0.04',
					'title'             => 'IPTC Headline',
					'orientation'       => '0',
					'keywords'          => array(),
				),
			),
			'Exif from a DMC-LX2 camera with keywords' => array(
				'file'     => 'testimagemeta://wp_read_image_metadata/image3.jpg',
				'metadata' => array(
					'aperture'          => '8',
					'credit'            => 'Photoshop Author',
					'camera'            => 'DMC-LX2',
					'caption'           => 'Photoshop Description',
					'created_timestamp' => '1306315327',
					'copyright'         => 'Photoshop Copyrright Notice',
					'focal_length'      => '6.3',
					'iso'               => '100',
					'shutter_speed'     => '0.0025',
					'title'             => 'Photoshop Document Ttitle',
					'orientation'       => '1',
					'keywords'          => array( 'beach', 'baywatch', 'LA', 'sunset' ),
				),
			),
		);
	}

	/**
	 * @ticket 54385
	 */
	public function test_exif_unexpected_data() {
		// Unexpected Exif data: FNumber is "0/0", aperture should be 0.
		$out = wp_read_image_metadata( DIR_TESTDATA . '/images/sugarloaf-mountain.jpg' );

		$this->assertEquals( 0, $out['aperture'], 'Aperture value not equivalent' );
		$this->assertSame( '', $out['credit'], 'Credit value not the same' );
		$this->assertSame( 'X-T1', $out['camera'], 'Camera value not the same' );
		$this->assertSame( '', $out['caption'], 'Caption value not the same' );
		$this->assertEquals( 0, $out['created_timestamp'], 'Timestamp value not equivalent' );
		$this->assertSame( '', $out['copyright'], 'Copyright value not the same' );
		$this->assertEquals( 50, $out['focal_length'], 'Focal length value not equivalent' );
		$this->assertEquals( 200, $out['iso'], 'Iso value not equivalent' );
		$this->assertEquals( 2, $out['shutter_speed'], 'Shutter speed value not equivalent' );
		$this->assertSame( 'Sugarloaf Panorama', $out['title'], 'Title value not the same' );
	}
}
