<?php
/**
 * Test wp_default_font_dir().
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @covers ::wp_default_font_dir
 */
class Tests_Fonts_WpDefaultFontDir extends WP_UnitTestCase {

	public function test_default_font_dir() {
		$font_dir = wp_default_font_dir();

		$this->assertSame(
			$font_dir,
			array(
				'path'    => WP_CONTENT_DIR . '/fonts',
				'url'     => content_url( 'fonts' ),
				'subdir'  => '',
				'basedir' => WP_CONTENT_DIR . '/fonts',
				'baseurl' => content_url( 'fonts' ),
				'error'   => false,
			),
			'The font directory should be a dir inside wp-content'
		);
	}

	public function test_uploads_font_dir() {
		$font_dir = wp_default_font_dir( true );
		chmod( $font_dir['path'], 0000 );

		$upload_dir = wp_upload_dir();

		$this->assertSame(
			$font_dir,
			array(
				'path'    => path_join( $upload_dir['basedir'], 'fonts' ),
				'url'     => $upload_dir['baseurl'] . '/fonts',
				'subdir'  => '/fonts',
				'basedir' => $upload_dir['basedir'],
				'baseurl' => $upload_dir['baseurl'],
				'error'   => false,
			),
			'The font directory should be a subdir in the uploads directory.'
		);
	}
}
