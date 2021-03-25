<?php

/**
 * @group media
 * @group upload
 * @requires extension gd
 * @requires extension exif
 */
class Tests_Media_WpGetimagesize extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';

		stream_wrapper_register( 'wptestmediawpgetimagesize', 'WP_Test_Stream' );
		WP_Test_Stream::$data = array(
			'Tests_Media_WpGetimagesize' => array(
				'/read.jpg' => file_get_contents( DIR_TESTDATA . '/images/test-image-upside-down.jpg' ),
				'/exif.jpg' => file_get_contents( DIR_TESTDATA . '/images/2004-07-22-DSC_0008.jpg' ),
			),
		);
	}

	public function test_get_jpeg_size_only() {
		$size = wp_getimagesize( 'wptestmediawpgetimagesize://Tests_Media_WpGetimagesize/read.jpg' );

		$expected = array(
			600,
			450,
			2,
			'width="600" height="450"',
			'bits'     => 8,
			'channels' => 3,
			'mime'     => 'image/jpeg',
		);

		$this->assertSame( $expected, $size );
	}

	public function test_get_jpeg_with_meta_data_via_stream() {
		$out = wp_read_image_metadata( 'wptestmediawpgetimagesize://Tests_Media_WpGetimagesize/exif.jpg' );

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
}
