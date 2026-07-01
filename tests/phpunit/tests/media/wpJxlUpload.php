<?php

/**
 * Tests for the JPEG XL (JXL) upload helpers.
 *
 * @group media
 * @covers ::wp_is_jxl_file
 * @covers ::wp_add_jxl_upload_mimes
 * @covers ::wp_filter_jxl_filetype_and_ext
 */
class Tests_Media_wpJxlUpload extends WP_UnitTestCase {

	/**
	 * @var string[] Absolute paths of temp files created during a test.
	 */
	private $temp_files = array();

	public function tear_down() {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->temp_files = array();

		parent::tear_down();
	}

	/**
	 * Writes `$bytes` to a temp file and returns its absolute path.
	 *
	 * @param string $bytes Raw file contents.
	 * @param string $ext   File extension (without the dot).
	 * @return string Absolute path to the temp file.
	 */
	private function create_temp_file( $bytes, $ext = 'jxl' ) {
		$path = get_temp_dir() . 'jxl-test-' . wp_generate_uuid4() . '.' . $ext;
		file_put_contents( $path, $bytes );
		$this->temp_files[] = $path;
		return $path;
	}

	/**
	 * @ticket 64915
	 */
	public function test_is_jxl_file_recognizes_naked_codestream() {
		$path = $this->create_temp_file( "\xFF\x0A" . str_repeat( "\x00", 32 ) );

		$this->assertTrue( wp_is_jxl_file( $path ) );
	}

	/**
	 * @ticket 64915
	 */
	public function test_is_jxl_file_recognizes_isobmff_container() {
		$signature = "\x00\x00\x00\x0C\x4A\x58\x4C\x20\x0D\x0A\x87\x0A";
		$path      = $this->create_temp_file( $signature . str_repeat( "\x00", 32 ) );

		$this->assertTrue( wp_is_jxl_file( $path ) );
	}

	/**
	 * @ticket 64915
	 */
	public function test_is_jxl_file_rejects_jpeg() {
		// JPEG SOI marker.
		$path = $this->create_temp_file( "\xFF\xD8\xFF\xE0" . str_repeat( "\x00", 16 ) );

		$this->assertFalse( wp_is_jxl_file( $path ) );
	}

	/**
	 * @ticket 64915
	 */
	public function test_is_jxl_file_rejects_png() {
		$path = $this->create_temp_file( "\x89PNG\r\n\x1a\n" . str_repeat( "\x00", 16 ) );

		$this->assertFalse( wp_is_jxl_file( $path ) );
	}

	/**
	 * @ticket 64915
	 */
	public function test_is_jxl_file_returns_false_for_short_file() {
		$path = $this->create_temp_file( "\xFF" );

		$this->assertFalse( wp_is_jxl_file( $path ) );
	}

	/**
	 * @ticket 64915
	 */
	public function test_is_jxl_file_returns_false_for_missing_file() {
		$this->assertFalse( wp_is_jxl_file( '/nonexistent/path/to/file.jxl' ) );
	}

	/**
	 * @ticket 64915
	 */
	public function test_upload_mimes_includes_jxl() {
		$mimes = apply_filters( 'upload_mimes', get_allowed_mime_types() );

		$this->assertArrayHasKey( 'jxl', $mimes );
		$this->assertSame( 'image/jxl', $mimes['jxl'] );
	}

	/**
	 * @ticket 64915
	 */
	public function test_filetype_and_ext_restores_jxl_mime_when_empty() {
		$path = $this->create_temp_file( "\xFF\x0A" . str_repeat( "\x00", 32 ) );

		$data = wp_filter_jxl_filetype_and_ext(
			array(
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			),
			$path,
			wp_basename( $path )
		);

		$this->assertSame( 'jxl', $data['ext'] );
		$this->assertSame( 'image/jxl', $data['type'] );
	}

	/**
	 * @ticket 64915
	 */
	public function test_filetype_and_ext_leaves_already_recognized_file_alone() {
		$path = $this->create_temp_file( "\xFF\x0A" . str_repeat( "\x00", 32 ) );

		$data = wp_filter_jxl_filetype_and_ext(
			array(
				'ext'             => 'jpg',
				'type'            => 'image/jpeg',
				'proper_filename' => 'photo.jpg',
			),
			$path,
			'photo.jpg'
		);

		$this->assertSame( 'jpg', $data['ext'], 'A recognized image must not be overwritten by the JXL filter.' );
		$this->assertSame( 'image/jpeg', $data['type'] );
	}

	/**
	 * The filter must not rescue a file that has a `.jxl` extension but is
	 * actually a different format (e.g. a JPEG renamed to `.jxl`).
	 *
	 * @ticket 64915
	 */
	public function test_filetype_and_ext_rejects_jxl_extension_with_wrong_magic() {
		$path = $this->create_temp_file( "\xFF\xD8\xFF\xE0" . str_repeat( "\x00", 16 ), 'jxl' );

		$data = wp_filter_jxl_filetype_and_ext(
			array(
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			),
			$path,
			'fake.jxl'
		);

		$this->assertFalse( $data['ext'], 'JXL extension on a non-JXL file must not be restored.' );
		$this->assertFalse( $data['type'] );
	}
}
