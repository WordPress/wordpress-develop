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
	 *
	 * @param string                               $expected          Expected markup.
	 * @param array<int, array<int|string, mixed>> $preload_resources Resources to preload.
	 */
	public function test_preload_resources( string $expected, array $preload_resources ) {
		$callback = static function () use ( $preload_resources ) {
			return $preload_resources;
		};

		add_filter( 'wp_preload_resources', $callback, 10 );
		$actual = get_echo( 'wp_preload_resources' );
		remove_filter( 'wp_preload_resources', $callback );

		$this->assertEqualHTML( $expected, $actual );
	}

	/**
	 * Data provider for {@see self::test_preload_resources()}.
	 *
	 * @return array[]
	 */
	public static function data_preload_resources() {
		return array(
			'basic_preload'            => array(
				'expected' => "<link rel='preload' href='https://example.com/style.css' as='style' />\n",
				'urls'     => array(
					array(
						'href' => 'https://example.com/style.css',
						'as'   => 'style',
					),
				),
			),
			'multiple_links'           => array(
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
			'MIME_types'               => array(
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
			'CORS'                     => array(
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
			'media'                    => array(
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
			'media_extra_attributes'   => array(
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
			'fetchpriority'            => array(
				'expected'  => "<link rel='preload' href='https://example.com/image.jpg' as='image' fetchpriority='high' />\n",
				'resources' => array(
					array(
						'href'          => 'https://example.com/image.jpg',
						'as'            => 'image',
						'fetchpriority' => 'high',
					),
				),
			),
			'imagesizes_only'          => array(
				'expected'  => "<link rel='preload' as='image' imagesrcset='640.png 640w, 800.png 800w' imagesizes='100vw' />\n",
				'resources' => array(
					// No href and no imagesrcset, so there is nothing to preload.
					array(
						'as'         => 'image',
						'imagesizes' => '100vw',
					),
					array(
						'as'         => 'image',
						'imagesizes' => '50vw',
					),
					array(
						'as'          => 'image',
						'imagesrcset' => '640.png 640w, 800.png 800w',
						'imagesizes'  => '100vw',
					),
				),
			),
			'missing_href_and_as'      => array(
				'expected'  => '',
				'resources' => array(
					array(
						'imagesrcset' => '640.png 640w, 800.png 800w',
					),
				),
			),
			'unsafe_href'              => array(
				'expected'  => '',
				'resources' => array(
					array(
						'href' => 'javascript:alert(1)',
						'as'   => 'script',
					),
				),
			),
			'non_string_href'          => array(
				'expected'  => '',
				'resources' => array(
					array(
						'href' => true,
						'as'   => 'script',
					),
					array(
						'href' => array( 'https://example.com/main.js' ),
						'as'   => 'script',
					),
				),
			),
			'unsafe_href_imagesrcset'  => array(
				'expected'  => "<link rel='preload' as='image' imagesrcset='640.png 640w, 800.png 800w' />\n",
				'resources' => array(
					array(
						'href'        => 'javascript:alert(1)',
						'as'          => 'image',
						'imagesrcset' => '640.png 640w, 800.png 800w',
					),
				),
			),
			'non_string_imagesrcset'   => array(
				'expected'  => '',
				'resources' => array(
					array(
						'as'          => 'image',
						'imagesrcset' => array( '640.png 640w' ),
					),
				),
			),
			'duplicate_sanitized_href' => array(
				'expected'  => "<link rel='preload' href='https://example.com/a%20b.js' as='script' />\n",
				'resources' => array(
					array(
						'href' => 'https://example.com/a b.js',
						'as'   => 'script',
					),
					array(
						'href' => 'https://example.com/a%20b.js',
						'as'   => 'script',
					),
				),
			),
		);
	}

	/**
	 * Tests that boolean attribute values and list items are only printed when valueless.
	 *
	 * @ticket 66435
	 *
	 * @dataProvider data_boolean_attributes
	 *
	 * @param string                   $expected         Expected markup.
	 * @param array<int|string, mixed> $preload_resource Resource to preload.
	 */
	public function test_boolean_attributes( string $expected, array $preload_resource ) {
		add_filter(
			'wp_preload_resources',
			static function () use ( $preload_resource ): array {
				return array( $preload_resource );
			}
		);

		$this->assertEqualHTML( $expected, get_echo( 'wp_preload_resources' ) );
	}

	/**
	 * Data provider for {@see self::test_boolean_attributes()}.
	 *
	 * @return array<non-falsy-string, array{ expected: string, preload_resource: array<int|string, mixed> }>
	 */
	public static function data_boolean_attributes(): array {
		return array(
			'crossorigin true'           => array(
				'expected'         => "<link rel='preload' href='https://example.com/font.woff2' as='font' crossorigin />\n",
				'preload_resource' => array(
					'href'        => 'https://example.com/font.woff2',
					'as'          => 'font',
					'crossorigin' => true,
				),
			),
			'crossorigin false'          => array(
				'expected'         => "<link rel='preload' href='https://example.com/font.woff2' as='font' />\n",
				'preload_resource' => array(
					'href'        => 'https://example.com/font.woff2',
					'as'          => 'font',
					'crossorigin' => false,
				),
			),
			'non-boolean attribute true' => array(
				'expected'         => "<link rel='preload' href='https://example.com/main.js' as='script' />\n",
				'preload_resource' => array(
					'href' => 'https://example.com/main.js',
					'as'   => 'script',
					'type' => true,
				),
			),
			'unsupported list item'      => array(
				'expected'         => "<link rel='preload' href='https://example.com/main.js' as='script' />\n",
				'preload_resource' => array(
					'href' => 'https://example.com/main.js',
					'as'   => 'script',
					'async',
				),
			),
			'non-string list item'       => array(
				'expected'         => "<link rel='preload' href='https://example.com/main.js' as='script' />\n",
				'preload_resource' => array(
					'href' => 'https://example.com/main.js',
					'as'   => 'script',
					true,
				),
			),
			'escaped attribute value'    => array(
				'expected'         => "<link rel='preload' href='https://example.com/style.css' as='style' media='screen and (min-width: 600px)&quot; onload=&quot;alert(1)' />\n",
				'preload_resource' => array(
					'href'  => 'https://example.com/style.css',
					'as'    => 'style',
					'media' => 'screen and (min-width: 600px)" onload="alert(1)',
				),
			),
		);
	}
}
