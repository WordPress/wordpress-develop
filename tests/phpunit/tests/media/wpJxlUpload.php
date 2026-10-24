<?php

/**
 * Tests for the JPEG XL (JXL) upload helpers.
 *
 * @group media
 * @covers ::wp_is_jxl_file
 * @covers ::wp_get_jxl_info
 * @covers ::wp_add_jxl_upload_mimes
 * @covers ::wp_filter_jxl_filetype_and_ext
 */
class Tests_Media_wpJxlUpload extends WP_UnitTestCase {

	/**
	 * A naked codestream for a 64x16 image: SizeHeader with div8 set, height
	 * 16, ratio 0, width 64, and no extra ImageMetadata fields.
	 */
	const CODESTREAM_64X16 = "\xFF\x0A\x03\x0E\x00";

	/**
	 * The 64x16 codestream above with orientation 6 (rotate 90 degrees).
	 */
	const CODESTREAM_64X16_ROTATED = "\xFF\x0A\x03\x8E\x05";

	/**
	 * A naked codestream for a 200x150 image: height 150 and a 4:3 ratio.
	 */
	const CODESTREAM_200X150 = "\xFF\x0A\xA8\xB4\x01\x00";

	/**
	 * The ISOBMFF container signature box.
	 */
	const CONTAINER_SIGNATURE = "\x00\x00\x00\x0C\x4A\x58\x4C\x20\x0D\x0A\x87\x0A";

	/**
	 * Wraps a codestream in an ISOBMFF container, after an `ftyp` box.
	 *
	 * @param string $codestream Codestream bytes.
	 * @param string $box_type   Box to hold the codestream, `jxlc` or `jxlp`.
	 * @return string Container bytes.
	 */
	private function container( $codestream, $box_type = 'jxlc' ) {
		$ftyp    = "\x00\x00\x00\x14ftypjxl \x00\x00\x00\x00jxl ";
		$payload = ( 'jxlp' === $box_type ? "\x00\x00\x00\x00" : '' ) . $codestream;

		return self::CONTAINER_SIGNATURE . $ftyp . pack( 'N', 8 + strlen( $payload ) ) . $box_type . $payload;
	}

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
		$path = $this->create_temp_file( $this->container( self::CODESTREAM_64X16 ) );

		$this->assertTrue( wp_is_jxl_file( $path ) );
	}

	/**
	 * @ticket 64915
	 */
	public function test_is_jxl_file_rejects_a_container_without_a_codestream() {
		$path = $this->create_temp_file( self::CONTAINER_SIGNATURE . str_repeat( "\x00", 32 ) );

		$this->assertFalse( wp_is_jxl_file( $path ) );
	}

	/**
	 * @ticket 64915
	 *
	 * @dataProvider data_get_jxl_info
	 *
	 * @param string $bytes  File contents.
	 * @param int    $width  Expected width.
	 * @param int    $height Expected height.
	 */
	public function test_get_jxl_info( $bytes, $width, $height ) {
		$path = $this->create_temp_file( $bytes );

		$this->assertSame(
			array(
				'width'  => $width,
				'height' => $height,
			),
			wp_get_jxl_info( $path )
		);
	}

	/**
	 * Data provider for test_get_jxl_info().
	 *
	 * @return array[]
	 */
	public function data_get_jxl_info() {
		return array(
			'multiple of eight' => array( self::CODESTREAM_64X16, 64, 16 ),
			'aspect ratio'      => array( self::CODESTREAM_200X150, 200, 150 ),
			'rotated'           => array( self::CODESTREAM_64X16_ROTATED, 16, 64 ),
			'jxlc container'    => array( $this->container( self::CODESTREAM_200X150 ), 200, 150 ),
			'jxlp container'    => array( $this->container( self::CODESTREAM_200X150, 'jxlp' ), 200, 150 ),
		);
	}

	/**
	 * @ticket 64915
	 */
	public function test_get_jxl_info_fails_for_a_file_that_is_not_a_jxl() {
		$path = $this->create_temp_file( "\x89PNG\r\n\x1a\n" . str_repeat( "\x00", 16 ), 'png' );

		$this->assertSame(
			array(
				'width'  => false,
				'height' => false,
			),
			wp_get_jxl_info( $path )
		);
	}

	/**
	 * @ticket 64915
	 *
	 * @covers ::wp_getimagesize
	 */
	public function test_getimagesize_reads_a_jxl() {
		$path = $this->create_temp_file( self::CODESTREAM_200X150 );

		$size = wp_getimagesize( $path );

		$this->assertSame( 200, $size[0] );
		$this->assertSame( 150, $size[1] );
		$this->assertSame( 'image/jxl', $size['mime'] );
	}

	/**
	 * Without dimensions, core reports no missing sub-sizes and the client
	 * never generates any.
	 *
	 * @ticket 64915
	 *
	 * @covers ::wp_generate_attachment_metadata
	 */
	public function test_generate_attachment_metadata_records_the_dimensions_of_a_jxl() {
		$path          = $this->create_temp_file( self::CODESTREAM_200X150 );
		$attachment_id = self::factory()->attachment->create_upload_object( $path );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertSame( 'image/jxl', get_post_mime_type( $attachment_id ) );
		$this->assertSame( 200, $metadata['width'] );
		$this->assertSame( 150, $metadata['height'] );
		$this->assertSame( _wp_relative_upload_path( get_attached_file( $attachment_id ) ), $metadata['file'] );
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

	/**
	 * A site that removes JXL from its upload types, like a network's
	 * "Upload file types" setting does, must not have it restored.
	 *
	 * @ticket 64915
	 */
	public function test_filetype_and_ext_does_not_restore_jxl_when_the_site_disallows_it() {
		$path = $this->create_temp_file( "\xFF\x0A" . str_repeat( "\x00", 32 ) );

		// At the default priority, as check_upload_mimes() runs on multisite.
		add_filter(
			'upload_mimes',
			static function ( $mimes ) {
				unset( $mimes['jxl'] );
				return $mimes;
			}
		);

		$data = wp_check_filetype_and_ext( $path, 'photo.jxl' );

		$this->assertFalse( $data['ext'], 'A disallowed JXL must not be restored.' );
		$this->assertFalse( $data['type'] );
	}

	/**
	 * @ticket 64915
	 */
	public function test_filetype_and_ext_does_not_restore_jxl_when_the_passed_mimes_exclude_it() {
		$path = $this->create_temp_file( "\xFF\x0A" . str_repeat( "\x00", 32 ) );

		$data = wp_check_filetype_and_ext( $path, 'photo.jxl', array( 'jpg|jpeg|jpe' => 'image/jpeg' ) );

		$this->assertFalse( $data['ext'], 'A JXL outside the passed MIME types must not be restored.' );
		$this->assertFalse( $data['type'] );
	}

	/**
	 * Core does not type the filename, and some callers pass null.
	 *
	 * @ticket 64915
	 */
	public function test_filetype_and_ext_passes_through_a_filename_that_is_not_a_string() {
		$path = $this->create_temp_file( self::CODESTREAM_64X16 );
		$data = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$this->assertSame( $data, wp_filter_jxl_filetype_and_ext( $data, $path, null ) );
	}

	/**
	 * A network's "Upload file types" setting must still decide whether JXL
	 * is allowed.
	 *
	 * @ticket 64915
	 *
	 * @group ms-required
	 */
	public function test_upload_mimes_respects_the_network_upload_file_types() {
		update_site_option( 'upload_filetypes', 'jpg jpeg png' );
		$without_jxl = get_allowed_mime_types();

		update_site_option( 'upload_filetypes', 'jpg jpeg png jxl' );
		$with_jxl = get_allowed_mime_types();

		$this->assertArrayNotHasKey( 'jxl', $without_jxl );
		$this->assertSame( 'image/jxl', $with_jxl['jxl'] ?? null );
	}
}
