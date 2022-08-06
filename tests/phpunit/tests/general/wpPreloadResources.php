<?php

/**
 * @group general
 * @group template
 * @ticket 42438
 * @covers ::wp_preload_resources
 */
class Tests_General_wpPreloadResources extends WP_UnitTestCase {

	/**
	 * @dataProvider data_preload_resources
	 *
	 * @ticket 42438
	 */
	public function test_preload_resources( $expected, $preload_resources ) {
		$callback = function () use ( $preload_resources ) {
			return $preload_resources;
		};

		add_filter( 'wp_preload_resources', $callback, 10 );
		$actual = get_echo( 'wp_preload_resources' );
		remove_filter( 'wp_preload_resources', $callback );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test provider for all preload link possible combinations.
	 *
	 * @return array[]
	 */
	public function data_preload_resources() {
		return array(
			'basic_preload'          => array(
				'expected' => "<link rel='preload' href='https://example.com/style.css' as='style' />\n",
				'urls'     => array(
					array(
						'href' => 'https://example.com/style.css',
						'as'   => 'style',
					),
				),
			),
			'multiple_links'         => array(
				'expected' => "<link rel='preload' href='https://example.com/style.css' as='style' />\n" .
							"<link rel='preload' href='https://example.com/main.js' as='script' />\n",
				'urls'     => array(
					array(
						'href' => 'https://example.com/style.css',
						'as'   => 'style',
					),
					array(
						'href' => 'https://example.com/main.js',
						'as'   => 'script',
					),
				),
			),
			'MIME_types'             => array(
				'expected' => "<link rel='preload' href='https://example.com/style.css' as='style' />\n" .
							"<link rel='preload' href='https://example.com/video.mp4' as='video' type='video/mp4' />\n" .
							"<link rel='preload' href='https://example.com/main.js' as='script' />\n",
				'urls'     => array(
					array(
						// Should ignore not valid attributes.
						'not'  => 'valid',
						'href' => 'https://example.com/style.css',
						'as'   => 'style',
					),
					array(
						'href' => 'https://example.com/video.mp4',
						'as'   => 'video',
						'type' => 'video/mp4',
					),
					array(
						'href' => 'https://example.com/main.js',
						'as'   => 'script',
					),
				),
			),
			'CORS'                   => array(
				'expected' => "<link rel='preload' href='https://example.com/style.css' as='style' crossorigin='anonymous' />\n" .
							"<link rel='preload' href='https://example.com/video.mp4' as='video' type='video/mp4' />\n" .
							"<link rel='preload' href='https://example.com/main.js' as='script' />\n" .
							"<link rel='preload' href='https://example.com/font.woff2' as='font' type='font/woff2' crossorigin />\n",
				'urls'     => array(
					array(
						'href'        => 'https://example.com/style.css',
						'as'          => 'style',
						'crossorigin' => 'anonymous',
					),
					array(
						'href' => 'https://example.com/video.mp4',
						'as'   => 'video',
						'type' => 'video/mp4',
					),
					array(
						'href' => 'https://example.com/main.js',
						'as'   => 'script',
					),
					array(
						// Should ignore not valid attributes.
						'ignore' => 'ignore',
						'href'   => 'https://example.com/font.woff2',
						'as'     => 'font',
						'type'   => 'font/woff2',
						'crossorigin',
					),
				),
			),
			'media'                  => array(
				'expected' => "<link rel='preload' href='https://example.com/style.css' as='style' crossorigin='anonymous' />\n" .
							"<link rel='preload' href='https://example.com/video.mp4' as='video' type='video/mp4' />\n" .
							"<link rel='preload' href='https://example.com/main.js' as='script' />\n" .
							"<link rel='preload' href='https://example.com/font.woff2' as='font' type='font/woff2' crossorigin />\n" .
							"<link rel='preload' href='https://example.com/image-narrow.png' as='image' media='(max-width: 600px)' />\n" .
							"<link rel='preload' href='https://example.com/image-wide.png' as='image' media='(min-width: 601px)' />\n",
				'urls'     => array(
					array(
						'href'        => 'https://example.com/style.css',
						'as'          => 'style',
						'crossorigin' => 'anonymous',
					),
					array(
						'href' => 'https://example.com/video.mp4',
						'as'   => 'video',
						'type' => 'video/mp4',
					),
					// Duplicated href should be ignored.
					array(
						'href' => 'https://example.com/video.mp4',
						'as'   => 'video',
						'type' => 'video/mp4',
					),
					array(
						'href' => 'https://example.com/main.js',
						'as'   => 'script',
					),
					array(
						'href' => 'https://example.com/font.woff2',
						'as'   => 'font',
						'type' => 'font/woff2',
						'crossorigin',
					),
					array(
						'href'  => 'https://example.com/image-narrow.png',
						'as'    => 'image',
						'media' => '(max-width: 600px)',
					),
					array(
						'href'  => 'https://example.com/image-wide.png',
						'as'    => 'image',
						'media' => '(min-width: 601px)',
					),

				),
			),
			'media_extra_attributes' => array(
				'expected' => "<link rel='preload' href='https://example.com/style.css' as='style' crossorigin='anonymous' />\n" .
							"<link rel='preload' href='https://example.com/video.mp4' as='video' type='video/mp4' />\n" .
							"<link rel='preload' href='https://example.com/main.js' as='script' />\n" .
							"<link rel='preload' href='https://example.com/font.woff2' as='font' type='font/woff2' crossorigin />\n" .
							"<link rel='preload' href='https://example.com/image-640.png' as='image' imagesrcset='640.png 640w, 800.png 800w, 1024.png 1024w' imagesizes='100vw' />\n" .
							"<link rel='preload' as='image' imagesrcset='640.png 640w, 800.png 800w, 1024.png 1024w' imagesizes='100vw' />\n" .
							"<link rel='preload' href='https://example.com/image-wide.png' as='image' media='(min-width: 601px)' />\n" .
							"<link rel='preload' href='https://example.com/image-800.png' as='image' imagesrcset='640.png 640w, 800.png 800w, 1024.png 1024w' />\n",
				'urls'     => array(
					array(
						'href'        => 'https://example.com/style.css',
						'as'          => 'style',
						'crossorigin' => 'anonymous',
					),
					array(
						'href' => 'https://example.com/video.mp4',
						'as'   => 'video',
						'type' => 'video/mp4',
					),
					array(
						'href' => 'https://example.com/main.js',
						'as'   => 'script',
					),
					array(
						'href' => 'https://example.com/font.woff2',
						'as'   => 'font',
						'type' => 'font/woff2',
						'crossorigin',
					),
					// imagesrcset only possible when using image, ignore.
					array(
						'href'        => 'https://example.com/font.woff2',
						'as'          => 'font',
						'type'        => 'font/woff2',
						'imagesrcset' => '640.png 640w, 800.png 800w, 1024.png 1024w',
					),
					// imagesizes only possible when using image, ignore.
					array(
						'href'       => 'https://example.com/font.woff2',
						'as'         => 'font',
						'type'       => 'font/woff2',
						'imagesizes' => '100vw',
					),
					// Duplicated href should be ignored.
					array(
						'href' => 'https://example.com/font.woff2',
						'as'   => 'font',
						'type' => 'font/woff2',
						'crossorigin',
					),
					array(
						'href'        => 'https://example.com/image-640.png',
						'as'          => 'image',
						'imagesrcset' => '640.png 640w, 800.png 800w, 1024.png 1024w',
						'imagesizes'  => '100vw',
					),
					// Omit href so that unsupporting browsers won't request a useless image.
					array(
						'as'          => 'image',
						'imagesrcset' => '640.png 640w, 800.png 800w, 1024.png 1024w',
						'imagesizes'  => '100vw',
					),
					// Duplicated imagesrcset should be ignored.
					array(
						'as'          => 'image',
						'imagesrcset' => '640.png 640w, 800.png 800w, 1024.png 1024w',
						'imagesizes'  => '100vw',
					),
					array(
						'href'  => 'https://example.com/image-wide.png',
						'as'    => 'image',
						'media' => '(min-width: 601px)',
					),
					// No href but not imagesrcset, should be ignored.
					array(
						'as'    => 'image',
						'media' => '(min-width: 601px)',
					),
					// imagesizes is optional.
					array(
						'href'        => 'https://example.com/image-800.png',
						'as'          => 'image',
						'imagesrcset' => '640.png 640w, 800.png 800w, 1024.png 1024w',
					),
					// imagesizes should be ignored since imagesrcset not present.
					array(
						'href'       => 'https://example.com/image-640.png',
						'as'         => 'image',
						'imagesizes' => '100vw',
					),
				),
			),
		);
	}

}
