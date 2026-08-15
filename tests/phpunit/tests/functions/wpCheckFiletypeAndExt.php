<?php

/**
 * @group functions
 * @group upload
 *
 * @covers ::wp_check_filetype_and_ext
 */
class Tests_Functions_WpCheckFiletypeAndExt extends WP_UnitTestCase {

	/**
	 * @ticket 39550
	 * @dataProvider data_wp_check_filetype_and_ext
	 * @requires extension fileinfo
	 */
	public function test_wp_check_filetype_and_ext( $file, $filename, $expected ) {
		$this->assertSame( $expected, wp_check_filetype_and_ext( $file, $filename ) );
	}

	public function data_wp_check_filetype_and_ext() {
		$data = array(
			// Standard image.
			array(
				DIR_TESTDATA . '/images/canola.jpg',
				'canola.jpg',
				array(
					'ext'             => 'jpg',
					'type'            => 'image/jpeg',
					'proper_filename' => false,
				),
			),
			// Image with wrong extension.
			array(
				DIR_TESTDATA . '/images/test-image-mime-jpg.png',
				'test-image-mime-jpg.png',
				array(
					'ext'             => 'jpg',
					'type'            => 'image/jpeg',
					'proper_filename' => 'test-image-mime-jpg.jpg',
				),
			),
			// Image without extension.
			array(
				DIR_TESTDATA . '/images/test-image-no-extension',
				'test-image-no-extension',
				array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				),
			),
			// Valid non-image file with an image extension.
			array(
				DIR_TESTDATA . '/formatting/big5.txt',
				'big5.jpg',
				array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				),
			),
			// Non-image file not allowed.
			array(
				DIR_TESTDATA . '/export/crazy-cdata.xml',
				'crazy-cdata.xml',
				array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				),
			),
			// Non-image file not allowed even if it's named like one.
			array(
				DIR_TESTDATA . '/export/crazy-cdata.xml',
				'crazy-cdata.jpg',
				array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				),
			),
			// Non-image file not allowed if it's named like something else.
			array(
				DIR_TESTDATA . '/export/crazy-cdata.xml',
				'crazy-cdata.doc',
				array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				),
			),
			// Non-image file not allowed even if it's named like one.
			array(
				DIR_TESTDATA . '/export/crazy-cdata.xml',
				'crazy-cdata.jpg',
				array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				),
			),
			// Non-image file not allowed if it's named like something else.
			array(
				DIR_TESTDATA . '/export/crazy-cdata.xml',
				'crazy-cdata.doc',
				array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				),
			),
		);

		// Test a few additional file types on single sites.
		if ( ! is_multisite() ) {
			$data = array_merge(
				$data,
				array(
					// Standard non-image file.
					array(
						DIR_TESTDATA . '/formatting/big5.txt',
						'big5.txt',
						array(
							'ext'             => 'txt',
							'type'            => 'text/plain',
							'proper_filename' => false,
						),
					),
					// Google Docs file for which finfo_file() returns a duplicate mime type.
					array(
						DIR_TESTDATA . '/uploads/double-mime-type.docx',
						'double-mime-type.docx',
						array(
							'ext'             => 'docx',
							'type'            => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
							'proper_filename' => false,
						),
					),
					// Non-image file with wrong sub-type.
					array(
						DIR_TESTDATA . '/uploads/pages-to-word.docx',
						'pages-to-word.docx',
						array(
							'ext'             => 'docx',
							'type'            => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
							'proper_filename' => false,
						),
					),
					// FLAC file.
					array(
						DIR_TESTDATA . '/uploads/small-audio.flac',
						'small-audio.flac',
						array(
							'ext'             => 'flac',
							'type'            => 'audio/flac',
							'proper_filename' => false,
						),
					),
					// Assorted text/* sample files
					array(
						DIR_TESTDATA . '/uploads/test.vtt',
						'test.vtt',
						array(
							'ext'             => 'vtt',
							'type'            => 'text/vtt',
							'proper_filename' => false,
						),
					),
					array(
						DIR_TESTDATA . '/uploads/test.csv',
						'test.csv',
						array(
							'ext'             => 'csv',
							'type'            => 'text/csv',
							'proper_filename' => false,
						),
					),
					// RTF files.
					array(
						DIR_TESTDATA . '/uploads/test.rtf',
						'test.rtf',
						array(
							'ext'             => 'rtf',
							'type'            => 'application/rtf',
							'proper_filename' => false,
						),
					),
				)
			);
		}

		return $data;
	}

	/**
	 * @ticket 39550
	 * @group ms-excluded
	 * @requires extension fileinfo
	 */
	public function test_wp_check_filetype_and_ext_with_filtered_svg() {
		$file     = DIR_TESTDATA . '/uploads/video-play.svg';
		$filename = 'video-play.svg';

		$expected = array(
			'ext'             => 'svg',
			'type'            => 'image/svg+xml',
			'proper_filename' => false,
		);

		add_filter(
			'upload_mimes',
			static function ( $mimes ) {
				$mimes['svg'] = 'image/svg+xml';
				return $mimes;
			}
		);

		$this->assertSame( $expected, wp_check_filetype_and_ext( $file, $filename ) );
	}

	/**
	 * @ticket 39550
	 * @group ms-excluded
	 * @requires extension fileinfo
	 */
	public function test_wp_check_filetype_and_ext_with_filtered_woff() {
		$file     = DIR_TESTDATA . '/uploads/dashicons.woff';
		$filename = 'dashicons.woff';

		$woff_mime_type = 'application/font-woff';

		/*
		 * As of PHP 8.1.12, which includes libmagic/file update to version 5.42,
		 * the expected mime type for WOFF files is 'font/woff'.
		 *
		 * See https://github.com/php/php-src/issues/8805.
		 */
		if ( PHP_VERSION_ID >= 80112 ) {
			$woff_mime_type = 'font/woff';
		}

		$expected = array(
			'ext'             => 'woff',
			'type'            => $woff_mime_type,
			'proper_filename' => false,
		);

		add_filter(
			'upload_mimes',
			static function ( $mimes ) use ( $woff_mime_type ) {
				$mimes['woff'] = $woff_mime_type;
				return $mimes;
			}
		);

		$this->assertSame( $expected, wp_check_filetype_and_ext( $file, $filename ) );
	}
}
