<?php
/**
 * Test deleting wp_font_family and wp_font_face post types.
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 */
class Tests_Fonts_FontLibraryHooks extends WP_UnitTestCase {

	public function test_deleting_font_family_deletes_child_font_faces() {
		$font_family_id       = self::factory()->post->create(
			array(
				'post_type' => 'wp_font_family',
			)
		);
		$font_face_id         = self::factory()->post->create(
			array(
				'post_type'   => 'wp_font_face',
				'post_parent' => $font_family_id,
			)
		);
		$other_font_family_id = self::factory()->post->create(
			array(
				'post_type' => 'wp_font_family',
			)
		);
		$other_font_face_id   = self::factory()->post->create(
			array(
				'post_type'   => 'wp_font_face',
				'post_parent' => $other_font_family_id,
			)
		);

		wp_delete_post( $font_family_id, true );

		$this->assertNull( get_post( $font_face_id ), 'Font face post should also have been deleted.' );
		$this->assertNotNull( get_post( $other_font_face_id ), 'The other post should exist.' );
	}

	public function test_deleting_font_faces_deletes_associated_font_files() {
		list( $font_face_id, $font_path ) = $this->create_font_face_with_file( 'OpenSans-Regular.woff2' );
		list( , $other_font_path )        = $this->create_font_face_with_file( 'OpenSans-Regular.ttf' );

		wp_delete_post( $font_face_id, true );

		$this->assertFileDoesNotExist( $font_path, 'The font file should have been deleted when the post was deleted.' );
		$this->assertFileExists( $other_font_path, 'The other font file should exist.' );
	}

	protected function create_font_face_with_file( $filename ) {
		$font_face_id = self::factory()->post->create(
			array(
				'post_type' => 'wp_font_face',
			)
		);

		$font_file = $this->upload_font_file( $filename );

		// Make sure the font file uploaded successfully.
		$this->assertFalse( $font_file['error'] );

		$font_path     = $font_file['file'];
		$font_filename = basename( $font_path );
		add_post_meta( $font_face_id, '_wp_font_face_file', $font_filename );

		return array( $font_face_id, $font_path );
	}

	protected function upload_font_file( $font_filename ) {
		$font_file_path = DIR_TESTDATA . '/fonts/' . $font_filename;

		add_filter( 'upload_mimes', array( 'WP_Font_Utils', 'get_allowed_font_mime_types' ) );
		add_filter( 'upload_dir', '_wp_filter_font_directory' );
		$font_file = wp_upload_bits(
			$font_filename,
			null,
			file_get_contents( $font_file_path )
		);
		remove_filter( 'upload_dir', '_wp_filter_font_directory' );
		remove_filter( 'upload_mimes', array( 'WP_Font_Utils', 'get_allowed_font_mime_types' ) );

		return $font_file;
	}
}
