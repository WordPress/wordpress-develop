<?php
require_once __DIR__ . '/../tests/image/base.php';

abstract class DominantColorTestCase extends WP_Image_UnitTestCase {
	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array
	 */
	public function provider_get_dominant_color() {
		$data = array(
			'animated_gif' => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/animated.gif',
				'expected_color'        => array( '874e4e', '864e4e', 'df7f7f' ),
				'expected_transparency' => true,
			),
			'red_jpg'      => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/red.jpg',
				'expected_color'        => array( 'ff0000', 'fe0000' ),
				'expected_transparency' => false,
			),
			'green_jpg'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/green.jpg',
				'expected_color'        => array( '00ff00', '00ff01' ),
				'expected_transparency' => false,
			),
			'white_jpg'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/white.jpg',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),

			'red_gif'      => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/red.gif',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_gif'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/green.gif',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_gif'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/white.gif',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_gif'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/trans.gif',
				'expected_color'        => array( '5a5a5a', '020202' ),
				'expected_transparency' => true,
			),

			'red_png'      => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/red.png',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_png'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/green.png',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_png'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/white.png',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_png'    => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/trans.png',
				'expected_color'        => array( '000000' ),
				'expected_transparency' => true,
			),
		);

//		if ( version_compare( phpversion(), '8', '>=' ) ) {

			$data = array_merge(
				$data,
				array(

					'red_webp'      => array(
						'image_path'            => DIR_TESTDATA . '/images/dominant-color/red.webp',
						'expected_color'        => array( 'ff0000' ),
						'expected_transparency' => false,
					),
					'green_webp'    => array(
						'image_path'            => DIR_TESTDATA . '/images/dominant-color/green.webp',
						'expected_color'        => array( '00ff00' ),
						'expected_transparency' => false,
					),
					'white_webp'    => array(
						'image_path'            => DIR_TESTDATA . '/images/dominant-color/white.webp',
						'expected_color'        => array( 'ffffff' ),
						'expected_transparency' => false,
					),
					'trans_webp'    => array(
						'image_path'            => DIR_TESTDATA . '/images/dominant-color/trans.webp',
						'expected_color'        => array( '000000' ),
						'expected_transparency' => true,
					),
					'balloons_webp' => array(
						'image_path'            => DIR_TESTDATA . '/images/dominant-color/balloons.webp',
						'expected_color'        => array( 'c1bbb9', 'c0bab8', 'c3bdbd' ),
						'expected_transparency' => false,
					),
				)
			);
//		}

		return $data;
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array
	 */
	public function provider_get_dominant_color_invalid_images() {
		$data = array(
			'tiff' => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/test-image.tiff',
				'expected_color'        => array( 'dfdfdf' ),
				'expected_transparency' => true,
			),
			'bmp'  => array(
				'image_path'            => DIR_TESTDATA . '/images/dominant-color/test-image.bmp',
				'expected_color'        => array( 'dfdfdf' ),
				'expected_transparency' => true,
			),
		);

//		if ( version_compare( phpversion(), '8', '<' ) ) {
			$data = array_merge(
				$data,
				array(
					'red_webp' => array(
						'image_path'            => DIR_TESTDATA . '/images/dominant-color/red.webp',
						'expected_color'        => array( 'ff0000' ),
						'expected_transparency' => false,
					),
				)
			);
//		}

		return $data;
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array
	 */
	public function provider_get_dominant_color_none_images() {
		return array(
			'pdf' => array(
				'files_path' => DIR_TESTDATA . '/images/dominant-color/wordpress-gsoc-flyer.pdf',
			),
			'mp4' => array(
				'files_path' => DIR_TESTDATA . '/images/dominant-color/small-video.mp4',
			),
		);
	}
}
