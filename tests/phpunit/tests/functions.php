<?php

/**
 * @group functions.php
 */
class Tests_Functions extends WP_UnitTestCase {
	function test_wp_parse_args_object() {
		$x        = new MockClass;
		$x->_baba = 5;
		$x->yZ    = 'baba'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$x->a     = array( 5, 111, 'x' );
		$this->assertSame(
			array(
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $x )
		);
		$y = new MockClass;
		$this->assertSame( array(), wp_parse_args( $y ) );
	}

	function test_wp_parse_args_array() {
		// Arrays.
		$a = array();
		$this->assertSame( array(), wp_parse_args( $a ) );
		$b = array(
			'_baba' => 5,
			'yZ'    => 'baba',
			'a'     => array( 5, 111, 'x' ),
		);
		$this->assertSame(
			array(
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $b )
		);
	}

	function test_wp_parse_args_defaults() {
		$x        = new MockClass;
		$x->_baba = 5;
		$x->yZ    = 'baba'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$x->a     = array( 5, 111, 'x' );
		$d        = array( 'pu' => 'bu' );
		$this->assertSame(
			array(
				'pu'    => 'bu',
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $x, $d )
		);
		$e = array( '_baba' => 6 );
		$this->assertSame(
			array(
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $x, $e )
		);
	}

	function test_wp_parse_args_other() {
		$b = true;
		wp_parse_str( $b, $s );
		$this->assertSame( $s, wp_parse_args( $b ) );
		$q = 'x=5&_baba=dudu&';
		wp_parse_str( $q, $ss );
		$this->assertSame( $ss, wp_parse_args( $q ) );
	}

	/**
	 * @ticket 30753
	 */
	function test_wp_parse_args_boolean_strings() {
		$args = wp_parse_args( 'foo=false&bar=true' );
		$this->assertIsString( $args['foo'] );
		$this->assertIsString( $args['bar'] );
	}

	/**
	 * @ticket 35972
	 */
	function test_bool_from_yn() {
		$this->assertTrue( bool_from_yn( 'Y' ) );
		$this->assertTrue( bool_from_yn( 'y' ) );
		$this->assertFalse( bool_from_yn( 'n' ) );
	}

	function test_path_is_absolute() {
		$absolute_paths = array(
			'/',
			'/foo/',
			'/foo',
			'/FOO/bar',
			'/foo/bar/',
			'/foo/../bar/',
			'\\WINDOWS',
			'C:\\',
			'C:\\WINDOWS',
			'\\\\sambashare\\foo',
		);
		foreach ( $absolute_paths as $path ) {
			$this->assertTrue( path_is_absolute( $path ), "path_is_absolute('$path') should return true" );
		}
	}

	function test_path_is_not_absolute() {
		$relative_paths = array(
			'',
			'.',
			'..',
			'../foo',
			'../',
			'../foo.bar',
			'foo/bar',
			'foo',
			'FOO',
			'..\\WINDOWS',
		);
		foreach ( $relative_paths as $path ) {
			$this->assertFalse( path_is_absolute( $path ), "path_is_absolute('$path') should return false" );
		}
	}

	/**
	 * @ticket 33265
	 * @ticket 35996
	 *
	 * @dataProvider data_wp_normalize_path
	 */
	function test_wp_normalize_path( $path, $expected ) {
		$this->assertSame( $expected, wp_normalize_path( $path ) );
	}

	function data_wp_normalize_path() {
		return array(
			// Windows paths.
			array( 'C:\\www\\path\\', 'C:/www/path/' ),
			array( 'C:\\www\\\\path\\', 'C:/www/path/' ),
			array( 'c:/www/path', 'C:/www/path' ),
			array( 'c:\\www\\path\\', 'C:/www/path/' ), // Uppercase drive letter.
			array( 'c:\\\\www\\path\\', 'C:/www/path/' ),
			array( '\\\\Domain\\DFSRoots\\share\\path\\', '//Domain/DFSRoots/share/path/' ),
			array( '\\\\Server\\share\\path', '//Server/share/path' ),
			array( '\\\\Server\\share', '//Server/share' ),

			// Linux paths.
			array( '/www/path/', '/www/path/' ),
			array( '/www/path/////', '/www/path/' ),
			array( '/www/path', '/www/path' ),

			// PHP stream wrappers.
			array( 'php://input', 'php://input' ),
			array( 'http://example.com//path.ext', 'http://example.com/path.ext' ),
			array( 'file://c:\\www\\path\\', 'file://C:/www/path/' ),
		);
	}

	function test_wp_unique_filename() {

		$testdir = DIR_TESTDATA . '/images/';

		// Sanity check.
		$this->assertSame( 'abcdefg.png', wp_unique_filename( $testdir, 'abcdefg.png' ), 'Test non-existing file, file name should be unchanged.' );

		// Ensure correct images exist.
		$this->assertFileExists( $testdir . 'test-image.png', 'Test image does not exist' );
		$this->assertFileDoesNotExist( $testdir . 'test-image-1.png' );

		// Check number is appended if file already exists.
		$this->assertSame( 'test-image-1.png', wp_unique_filename( $testdir, 'test-image.png' ), 'File name not unique, number not appended.' );

		// Check file with uppercase extension.
		$this->assertSame( 'test-image-1.png', wp_unique_filename( $testdir, 'test-image.PNG' ), 'File name with uppercase extension not unique, number not appended.' );

		// Check file name with already added number.
		$this->assertSame( 'test-image-2-1.gif', wp_unique_filename( $testdir, 'test-image-2.gif' ), 'File name not unique, number not appended correctly.' );

		// Check special chars.
		$this->assertSame( 'testtest-image.png', wp_unique_filename( $testdir, 'testtést-imagé.png' ), 'Filename with special chars failed' );

		// Check special chars with potential conflicting name.
		$this->assertSame( 'test-image-1.png', wp_unique_filename( $testdir, 'tést-imagé.png' ), 'Filename with special chars failed' );

		// Check with single quotes in name (somehow).
		$this->assertSame( 'abcdefgh.png', wp_unique_filename( $testdir, "abcdefg'h.png" ), 'File with quote failed' );

		// Check with double quotes in name (somehow).
		$this->assertSame( 'abcdefgh.png', wp_unique_filename( $testdir, 'abcdefg"h.png' ), 'File with quote failed' );

		// Test crazy name (useful for regression tests).
		$this->assertSame( '12af34567890@..^_qwerty-fghjkl-zx.png', wp_unique_filename( $testdir, '12%af34567890#~!@#$..%^&*()|_+qwerty  fgh`jkl zx<>?:"{}[]="\'/?.png' ), 'Failed crazy file name' );

		// Test slashes in names.
		$this->assertSame( 'abcdefg.png', wp_unique_filename( $testdir, 'abcde\fg.png' ), 'Slash not removed' );
		$this->assertSame( 'abcdefg.png', wp_unique_filename( $testdir, 'abcde\\fg.png' ), 'Double slashed not removed' );
		$this->assertSame( 'abcdefg.png', wp_unique_filename( $testdir, 'abcde\\\fg.png' ), 'Tripple slashed not removed' );
	}

	/**
	 * @ticket 42437
	 */
	function test_unique_filename_with_dimension_like_filename() {
		$testdir = DIR_TESTDATA . '/images/';

		add_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );

		// Test collision with "dimension-like" original filename.
		$this->assertSame( 'one-blue-pixel-100x100-1.png', wp_unique_filename( $testdir, 'one-blue-pixel-100x100.png' ) );
		// Test collision with existing sub-size filename.
		// Existing files: one-blue-pixel-100x100.png, one-blue-pixel-1-100x100.png.
		$this->assertSame( 'one-blue-pixel-2.png', wp_unique_filename( $testdir, 'one-blue-pixel.png' ) );
		// Same as above with upper case extension.
		$this->assertSame( 'one-blue-pixel-2.png', wp_unique_filename( $testdir, 'one-blue-pixel.PNG' ) );

		remove_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );
	}

	// Callback to patch "basedir" when used in `wp_unique_filename()`.
	function upload_dir_patch_basedir( $upload_dir ) {
		$upload_dir['basedir'] = DIR_TESTDATA . '/images/';
		return $upload_dir;
	}

	/**
	 * @ticket 53668
	 */
	function test_wp_unique_filename_with_additional_image_extension() {
		$testdir = DIR_TESTDATA . '/images/';

		add_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );

		// Set conversions for uploaded images.
		add_filter( 'image_editor_output_format', array( $this, 'image_editor_output_format_handler' ) );

		// Ensure the test images exist.
		$this->assertFileExists( $testdir . 'test-image-1-100x100.jpg', 'test-image-1-100x100.jpg does not exist' );
		$this->assertFileExists( $testdir . 'test-image-2.gif', 'test-image-2.gif does not exist' );
		$this->assertFileExists( $testdir . 'test-image-3.jpg', 'test-image-3.jpg does not exist' );
		$this->assertFileExists( $testdir . 'test-image-4.png', 'test-image-4.png does not exist' );

		// Standard test: file does not exist and there are no possible intersections with other files.
		$this->assertSame(
			'abcdef.png',
			wp_unique_filename( $testdir, 'abcdef.png' ),
			'The abcdef.png, abcdef.gif, and abcdef.jpg images do not exist. The file name should not be changed.'
		);

		// Actual clash recognized.
		$this->assertSame(
			'canola-1.jpg',
			wp_unique_filename( $testdir, 'canola.jpg' ),
			'The canola.jpg image exists. The file name should be unique.'
		);

		// Same name with different extension and the image will be converted.
		$this->assertSame(
			'canola-1.png',
			wp_unique_filename( $testdir, 'canola.png' ),
			'The canola.jpg image exists. Uploading canola.png that will be converted to canola.jpg should produce unique file name.'
		);

		// Same name with different uppercase extension and the image will be converted.
		$this->assertSame(
			'canola-1.png',
			wp_unique_filename( $testdir, 'canola.PNG' ),
			'The canola.jpg image exists. Uploading canola.PNG that will be converted to canola.jpg should produce unique file name.'
		);

		// Actual clash with several images with different extensions.
		$this->assertSame(
			'test-image-5.png',
			wp_unique_filename( $testdir, 'test-image.png' ),
			'The test-image.png, test-image-1-100x100.jpg, test-image-2.gif, test-image-3.jpg, and test-image-4.png images exist.' .
			'All of them may clash when creating sub-sizes or regenerating thumbnails in the future. The filename should be unique.'
		);

		// Possible clash with regenerated thumbnails in the future.
		$this->assertSame(
			'codeispoetry-1.jpg',
			wp_unique_filename( $testdir, 'codeispoetry.jpg' ),
			'The codeispoetry.png image exists. When regenerating thumbnails for it they will be converted to JPG.' .
			'The name of the newly uploaded codeispoetry.jpg should be made unique.'
		);

		remove_filter( 'image_editor_output_format', array( $this, 'image_editor_output_format_handler' ) );
		remove_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );
	}

	/**
	 * Changes the output format when editing images. When uploading a PNG file
	 * it will be converted to JPEG, GIF to JPEG, and PICT to BMP
	 * (if the image editor in PHP supports it).
	 *
	 * @param array $formats
	 *
	 * @return array
	 */
	public function image_editor_output_format_handler( $formats ) {
		$formats['image/png'] = 'image/jpeg';
		$formats['image/gif'] = 'image/jpeg';
		$formats['image/pct'] = 'image/bmp';

		return $formats;
	}

	/**
	 * @dataProvider data_is_not_serialized
	 */
	function test_maybe_serialize( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$expected = serialize( $value );
		} else {
			$expected = $value;
		}

		$this->assertSame( $expected, maybe_serialize( $value ) );
	}

	/**
	 * @dataProvider data_is_serialized
	 */
	function test_maybe_serialize_with_double_serialization( $value ) {
		$expected = serialize( $value );

		$this->assertSame( $expected, maybe_serialize( $value ) );
	}

	/**
	 * @dataProvider data_is_serialized
	 * @dataProvider data_is_not_serialized
	 */
	function test_maybe_unserialize( $value, $is_serialized ) {
		if ( $is_serialized ) {
			$expected = unserialize( trim( $value ) );
		} else {
			$expected = $value;
		}

		if ( is_object( $expected ) ) {
			$this->assertEquals( $expected, maybe_unserialize( $value ) );
		} else {
			$this->assertSame( $expected, maybe_unserialize( $value ) );
		}
	}

	/**
	 * @dataProvider data_is_serialized
	 * @dataProvider data_is_not_serialized
	 */
	function test_is_serialized( $value, $expected ) {
		$this->assertSame( $expected, is_serialized( $value ) );
	}

	/**
	 * @dataProvider data_serialize_deserialize_objects
	 */
	function test_deserialize_request_utility_filtered_iterator_objects( $value ) {
		$serialized = maybe_serialize( $value );
		if ( get_class( $value ) === 'Requests_Utility_FilteredIterator' ) {
			$new_value = unserialize( $serialized );
			$property  = ( new ReflectionClass( 'Requests_Utility_FilteredIterator' ) )->getProperty( 'callback' );
			$property->setAccessible( true );
			$callback_value = $property->getValue( $new_value );
			$this->assertSame( null, $callback_value );
		} else {
			$this->assertSame( $value->count(), unserialize( $serialized )->count() );
		}
	}

	function data_serialize_deserialize_objects() {
		return array(
			array( new Requests_Utility_FilteredIterator( array( 1 ), 'md5' ) ),
			array( new Requests_Utility_FilteredIterator( array( 1, 2 ), 'sha1' ) ),
			array( new ArrayIterator( array( 1, 2, 3 ) ) ),
		);
	}

	function data_is_serialized() {
		return array(
			array( serialize( null ), true ),
			array( serialize( true ), true ),
			array( serialize( false ), true ),
			array( serialize( -25 ), true ),
			array( serialize( 25 ), true ),
			array( serialize( 1.1 ), true ),
			array( serialize( 'this string will be serialized' ), true ),
			array( serialize( "a\nb" ), true ),
			array( serialize( array() ), true ),
			array( serialize( array( 1, 1, 2, 3, 5, 8, 13 ) ), true ),
			array(
				serialize(
					(object) array(
						'test' => true,
						'3',
						4,
					)
				),
				true,
			),
			array( '   s:25:"this string is serialized";   ', true ),
		);
	}

	function data_is_not_serialized() {
		return array(
			array( null, false ),
			array( true, false ),
			array( false, false ),
			array( -25, false ),
			array( 25, false ),
			array( 1.1, false ),
			array( 'this string will be serialized', false ),
			array( "a\nb", false ),
			array( array(), false ),
			array( array( 1, 1, 2, 3, 5, 8, 13 ), false ),
			array(
				(object) array(
					'test' => true,
					'3',
					4,
				),
				false,
			),
			array( 'a string', false ),
			array( 'garbage:a:0:garbage;', false ),
			array( 's:4:test;', false ),
		);
	}

	/**
	 * @ticket 46570
	 * @dataProvider data_is_serialized_should_return_true_for_large_floats
	 */
	function test_is_serialized_should_return_true_for_large_floats( $value ) {
		$this->assertTrue( is_serialized( $value ) );
	}

	function data_is_serialized_should_return_true_for_large_floats() {
		return array(
			array( serialize( 1.7976931348623157E+308 ) ),
			array( serialize( array( 1.7976931348623157E+308, 1.23e50 ) ) ),
		);
	}

	/**
	 * @ticket 17375
	 */
	function test_no_new_serializable_types() {
		$this->assertFalse( is_serialized( 'C:16:"Serialized_Class":6:{a:0:{}}' ) );
	}

	/**
	 * @group add_query_arg
	 */
	function test_add_query_arg() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$urls = array(
			'/',
			'/2012/07/30/',
			'edit.php',
			admin_url( 'edit.php' ),
			admin_url( 'edit.php', 'https' ),
		);

		$frag_urls = array(
			'/#frag',
			'/2012/07/30/#frag',
			'edit.php#frag',
			admin_url( 'edit.php#frag' ),
			admin_url( 'edit.php#frag', 'https' ),
		);

		foreach ( $urls as $url ) {
			$_SERVER['REQUEST_URI'] = 'nothing';

			$this->assertSame( "$url?foo=1", add_query_arg( 'foo', '1', $url ) );
			$this->assertSame( "$url?foo=1", add_query_arg( array( 'foo' => '1' ), $url ) );
			$this->assertSame(
				"$url?foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					),
					$url
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					),
					$url
				)
			);

			$_SERVER['REQUEST_URI'] = $url;

			$this->assertSame( "$url?foo=1", add_query_arg( 'foo', '1' ) );
			$this->assertSame( "$url?foo=1", add_query_arg( array( 'foo' => '1' ) ) );
			$this->assertSame(
				"$url?foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					)
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					)
				)
			);
		}

		foreach ( $frag_urls as $frag_url ) {
			$_SERVER['REQUEST_URI'] = 'nothing';
			$url                    = str_replace( '#frag', '', $frag_url );

			$this->assertSame( "$url?foo=1#frag", add_query_arg( 'foo', '1', $frag_url ) );
			$this->assertSame( "$url?foo=1#frag", add_query_arg( array( 'foo' => '1' ), $frag_url ) );
			$this->assertSame(
				"$url?foo=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					),
					$frag_url
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					),
					$frag_url
				)
			);

			$_SERVER['REQUEST_URI'] = $frag_url;

			$this->assertSame( "$url?foo=1#frag", add_query_arg( 'foo', '1' ) );
			$this->assertSame( "$url?foo=1#frag", add_query_arg( array( 'foo' => '1' ) ) );
			$this->assertSame(
				"$url?foo=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					)
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					)
				)
			);
		}

		$qs_urls = array(
			'baz=1', // #WP4903
			'/?baz',
			'/2012/07/30/?baz',
			'edit.php?baz',
			admin_url( 'edit.php?baz' ),
			admin_url( 'edit.php?baz', 'https' ),
			admin_url( 'edit.php?baz&za=1' ),
			admin_url( 'edit.php?baz=1&za=1' ),
			admin_url( 'edit.php?baz=0&za=0' ),
		);

		foreach ( $qs_urls as $url ) {
			$_SERVER['REQUEST_URI'] = 'nothing';

			$this->assertSame( "$url&foo=1", add_query_arg( 'foo', '1', $url ) );
			$this->assertSame( "$url&foo=1", add_query_arg( array( 'foo' => '1' ), $url ) );
			$this->assertSame(
				"$url&foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					),
					$url
				)
			);
			$this->assertSame(
				"$url&foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					),
					$url
				)
			);

			$_SERVER['REQUEST_URI'] = $url;

			$this->assertSame( "$url&foo=1", add_query_arg( 'foo', '1' ) );
			$this->assertSame( "$url&foo=1", add_query_arg( array( 'foo' => '1' ) ) );
			$this->assertSame(
				"$url&foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					)
				)
			);
			$this->assertSame(
				"$url&foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					)
				)
			);
		}

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	/**
	 * @ticket 31306
	 */
	function test_add_query_arg_numeric_keys() {
		$url = add_query_arg( array( 'foo' => 'bar' ), '1=1' );
		$this->assertSame( '1=1&foo=bar', $url );

		$url = add_query_arg(
			array(
				'foo' => 'bar',
				'1'   => '2',
			),
			'1=1'
		);
		$this->assertSame( '1=2&foo=bar', $url );

		$url = add_query_arg( array( '1' => '2' ), 'foo=bar' );
		$this->assertSame( 'foo=bar&1=2', $url );
	}

	/**
	 * @ticket 21594
	 */
	function test_get_allowed_mime_types() {
		$mimes = get_allowed_mime_types();

		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		add_filter( 'upload_mimes', '__return_empty_array' );
		$mimes = get_allowed_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertEmpty( $mimes );

		remove_filter( 'upload_mimes', '__return_empty_array' );
		$mimes = get_allowed_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );
	}

	/**
	 * @ticket 21594
	 */
	function test_wp_get_mime_types() {
		$mimes = wp_get_mime_types();

		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		add_filter( 'mime_types', '__return_empty_array' );
		$mimes = wp_get_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertEmpty( $mimes );

		remove_filter( 'mime_types', '__return_empty_array' );
		$mimes = wp_get_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		// 'upload_mimes' should not affect wp_get_mime_types().
		add_filter( 'upload_mimes', '__return_empty_array' );
		$mimes = wp_get_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		remove_filter( 'upload_mimes', '__return_empty_array' );
		$mimes2 = wp_get_mime_types();
		$this->assertIsArray( $mimes2 );
		$this->assertNotEmpty( $mimes2 );
		$this->assertSame( $mimes2, $mimes );
	}

	/**
	 * @ticket 23688
	 */
	function test_canonical_charset() {
		$orig_blog_charset = get_option( 'blog_charset' );

		update_option( 'blog_charset', 'utf8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'utf-8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'UTF8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'UTF-8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'ISO-8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'ISO8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'iso8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'iso-8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		// Arbitrary strings are passed through.
		update_option( 'blog_charset', 'foobarbaz' );
		$this->assertSame( 'foobarbaz', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', $orig_blog_charset );
	}

	/**
	 * @ticket 43977
	 * @dataProvider data_wp_parse_list
	 */
	function test_wp_parse_list( $expected, $actual ) {
		$this->assertSame( $expected, array_values( wp_parse_list( $actual ) ) );
	}

	function data_wp_parse_list() {
		return array(
			array( array( '1', '2', '3', '4' ), '1,2,3,4' ),
			array( array( 'apple', 'banana', 'carrot', 'dog' ), 'apple,banana,carrot,dog' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2,apple,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), '1, 2,apple,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2,apple,,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), ',1,2,apple,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2,apple,banana,' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2 ,apple,banana' ),
			array( array(), '' ),
			array( array(), ',' ),
			array( array(), ',,' ),
		);
	}

	/**
	 * @dataProvider data_wp_parse_id_list
	 */
	function test_wp_parse_id_list( $expected, $actual ) {
		$this->assertSame( $expected, array_values( wp_parse_id_list( $actual ) ) );
	}

	function data_wp_parse_id_list() {
		return array(
			array( array( 1, 2, 3, 4 ), '1,2,3,4' ),
			array( array( 1, 2, 3, 4 ), '1, 2,,3,4' ),
			array( array( 1, 2, 3, 4 ), '1,2,2,3,4' ),
			array( array( 1, 2, 3, 4 ), array( '1', '2', '3', '4', '3' ) ),
			array( array( 1, 2, 3, 4 ), array( 1, '2', 3, '4' ) ),
			array( array( 1, 2, 3, 4 ), '-1,2,-3,4' ),
			array( array( 1, 2, 3, 4 ), array( -1, 2, '-3', '4' ) ),
		);
	}

	/**
	 * @dataProvider data_wp_parse_slug_list
	 */
	function test_wp_parse_slug_list( $expected, $actual ) {
		$this->assertSame( $expected, array_values( wp_parse_slug_list( $actual ) ) );
	}

	function data_wp_parse_slug_list() {
		return array(
			array( array( 'apple', 'banana', 'carrot', 'dog' ), 'apple,banana,carrot,dog' ),
			array( array( 'apple', 'banana', 'carrot', 'dog' ), 'apple, banana,,carrot,dog' ),
			array( array( 'apple', 'banana', 'carrot', 'dog' ), 'apple banana carrot dog' ),
			array( array( 'apple', 'banana-carrot', 'd-o-g' ), array( 'apple ', 'banana carrot', 'd o g' ) ),
		);
	}

	/**
	 * @dataProvider data_device_can_upload
	 */
	function test_device_can_upload( $user_agent, $expected ) {
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		$actual                     = _device_can_upload();
		unset( $_SERVER['HTTP_USER_AGENT'] );
		$this->assertSame( $expected, $actual );
	}

	function data_device_can_upload() {
		return array(
			// iPhone iOS 5.0.1, Safari 5.1.
			array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 5_0_1 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Mobile/9A406)',
				false,
			),
			// iPad iOS 3.2, Safari 4.0.4.
			array(
				'Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.10',
				false,
			),
			// iPod iOS 4.3.3, Safari 5.0.2.
			array(
				'Mozilla/5.0 (iPod; U; CPU iPhone OS 4_3_3 like Mac OS X; ja-jp) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5',
				false,
			),
			// iPhone iOS 6.0.0, Safari 6.0.
			array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25',
				true,
			),
			// iPad iOS 6.0.0, Safari 6.0.
			array(
				'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25',
				true,
			),
			// Android 2.2, Android Webkit Browser.
			array(
				'Mozilla/5.0 (Android 2.2; Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4',
				true,
			),
			// BlackBerry 9900, BlackBerry browser.
			array(
				'Mozilla/5.0 (BlackBerry; U; BlackBerry 9900; en) AppleWebKit/534.11+ (KHTML, like Gecko) Version/7.1.0.346 Mobile Safari/534.11+',
				true,
			),
			// Windows Phone 8.0, Internet Explorer 10.0.
			array(
				'Mozilla/5.0 (compatible; MSIE 10.0; Windows Phone 8.0; Trident/6.0; IEMobile/10.0; ARM; Touch; NOKIA; Lumia 920)',
				true,
			),
			// Ubuntu desktop, Firefox 41.0.
			array(
				'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:41.0) Gecko/20100101 Firefox/41.0',
				true,
			),
		);
	}

	/**
	 * @ticket 9064
	 */
	function test_wp_extract_urls() {
		$original_urls = array(
			'http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html',
			'http://this.com',
			'http://127.0.0.1',
			'http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&2134362574863.437',
			'http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html',
			'http://wordpress-core.com:8080/',
			'http://www.website.com:5000',
			'http://wordpress-core/?346236346326&2134362574863.437',
			'http://افغانستا.icom.museum',
			'http://الجزائر.icom.museum',
			'http://österreich.icom.museum',
			'http://বাংলাদেশ.icom.museum',
			'http://беларусь.icom.museum',
			'http://belgië.icom.museum',
			'http://българия.icom.museum',
			'http://تشادر.icom.museum',
			'http://中国.icom.museum',
			// 'http://القمر.icom.museum',         // Comoros http://القمر.icom.museum
			// 'http://κυπρος.icom.museum',        // Cyprus  http://κυπρος.icom.museum
			'http://českárepublika.icom.museum',
			// 'http://مصر.icom.museum',           // Egypt   http://مصر.icom.museum
			'http://ελλάδα.icom.museum',
			'http://magyarország.icom.museum',
			'http://ísland.icom.museum',
			'http://भारत.icom.museum',
			'http://ايران.icom.museum',
			'http://éire.icom.museum',
			'http://איקו״ם.ישראל.museum',
			'http://日本.icom.museum',
			'http://الأردن.icom.museum',
			'http://қазақстан.icom.museum',
			'http://한국.icom.museum',
			'http://кыргызстан.icom.museum',
			'http://ລາວ.icom.museum',
			'http://لبنان.icom.museum',
			'http://македонија.icom.museum',
			// 'http://موريتانيا.icom.museum',     // Mauritania http://موريتانيا.icom.museum
			'http://méxico.icom.museum',
			'http://монголулс.icom.museum',
			// 'http://المغرب.icom.museum',        // Morocco    http://المغرب.icom.museum
			'http://नेपाल.icom.museum',
			// 'http://عمان.icom.museum',          // Oman       http://عمان.icom.museum
			'http://قطر.icom.museum',
			'http://românia.icom.museum',
			'http://россия.иком.museum',
			'http://србијаицрнагора.иком.museum',
			'http://இலங்கை.icom.museum',
			'http://españa.icom.museum',
			'http://ไทย.icom.museum',
			'http://تونس.icom.museum',
			'http://türkiye.icom.museum',
			'http://украина.icom.museum',
			'http://việtnam.icom.museum',
			'ftp://127.0.0.1/',
			'http://www.woo.com/video?v=exvUH2qKLTU',
			'http://taco.com?burrito=enchilada#guac',
		);

		$blob = '
			http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html

			http://this.com

			http://127.0.0.1

			http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&amp;2134362574863.437

			http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html

			http://wordpress-core.com:8080/

			http://www.website.com:5000

			http://wordpress-core/?346236346326&amp;2134362574863.437

			http://افغانستا.icom.museum
			http://الجزائر.icom.museum
			http://österreich.icom.museum
			http://বাংলাদেশ.icom.museum
			http://беларусь.icom.museum
			http://belgië.icom.museum
			http://българия.icom.museum
			http://تشادر.icom.museum
			http://中国.icom.museum
			http://českárepublika.icom.museum
			http://ελλάδα.icom.museum
			http://magyarország.icom.museum
			http://ísland.icom.museum
			http://भारत.icom.museum
			http://ايران.icom.museum
			http://éire.icom.museum
			http://איקו״ם.ישראל.museum
			http://日本.icom.museum
			http://الأردن.icom.museum
			http://қазақстан.icom.museum
			http://한국.icom.museum
			http://кыргызстан.icom.museum
			http://ລາວ.icom.museum
			http://لبنان.icom.museum
			http://македонија.icom.museum
			http://méxico.icom.museum
			http://монголулс.icom.museum
			http://नेपाल.icom.museum
			http://قطر.icom.museum
			http://românia.icom.museum
			http://россия.иком.museum
			http://србијаицрнагора.иком.museum
			http://இலங்கை.icom.museum
			http://españa.icom.museum
			http://ไทย.icom.museum
			http://تونس.icom.museum
			http://türkiye.icom.museum
			http://украина.icom.museum
			http://việtnam.icom.museum
			ftp://127.0.0.1/
			http://www.woo.com/video?v=exvUH2qKLTU

			http://taco.com?burrito=enchilada#guac
		';

		$urls = wp_extract_urls( $blob );
		$this->assertNotEmpty( $urls );
		$this->assertIsArray( $urls );
		$this->assertCount( count( $original_urls ), $urls );
		$this->assertSame( $original_urls, $urls );

		$exploded = array_values( array_filter( array_map( 'trim', explode( "\n", $blob ) ) ) );
		// wp_extract_urls() calls html_entity_decode().
		$decoded = array_map( 'html_entity_decode', $exploded );

		$this->assertSame( $decoded, $urls );
		$this->assertSame( $original_urls, $decoded );

		$blob = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor
			incididunt ut labore http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html et dolore magna aliqua.
			Ut http://this.com enim ad minim veniam, quis nostrud exercitation 16.06. to 18.06.2014 ullamco http://127.0.0.1
			laboris nisi ut aliquip ex http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&amp;2134362574863.437 ea
			commodo consequat. http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html Duis aute irure dolor in reprehenderit in voluptate
			velit esse http://wordpress-core.com:8080/ cillum dolore eu fugiat nulla <A href="http://www.website.com:5000">http://www.website.com:5000</B> pariatur. Excepteur sint occaecat cupidatat non proident,
			sunt in culpa qui officia deserunt mollit http://wordpress-core/?346236346326&amp;2134362574863.437 anim id est laborum.';

		$urls = wp_extract_urls( $blob );
		$this->assertNotEmpty( $urls );
		$this->assertIsArray( $urls );
		$this->assertCount( 8, $urls );
		$this->assertSame( array_slice( $original_urls, 0, 8 ), $urls );

		$blob = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor
			incididunt ut labore <a href="http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html">343462^</a> et dolore magna aliqua.
			Ut <a href="http://this.com">&amp;3640i6p1yi499</a> enim ad minim veniam, quis nostrud exercitation 16.06. to 18.06.2014 ullamco <a href="http://127.0.0.1">localhost</a>
			laboris nisi ut aliquip ex <a href="http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&amp;2134362574863.437">343462^</a> ea
			commodo consequat. <a href="http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html">343462^</a> Duis aute irure dolor in reprehenderit in voluptate
			velit esse <a href="http://wordpress-core.com:8080/">-3-4--321-64-4@#!$^$!@^@^</a> cillum dolore eu <A href="http://www.website.com:5000">http://www.website.com:5000</B> fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident,
			sunt in culpa qui officia deserunt mollit <a href="http://wordpress-core/?346236346326&amp;2134362574863.437">)(*&^%$</a> anim id est laborum.';

		$urls = wp_extract_urls( $blob );
		$this->assertNotEmpty( $urls );
		$this->assertIsArray( $urls );
		$this->assertCount( 8, $urls );
		$this->assertSame( array_slice( $original_urls, 0, 8 ), $urls );
	}

	/**
	 * @ticket 28786
	 */
	function test_wp_json_encode() {
		$this->assertSame( wp_json_encode( 'a' ), '"a"' );
	}

	/**
	 * @ticket 28786
	 */
	function test_wp_json_encode_utf8() {
		$this->assertSame( wp_json_encode( '这' ), '"\u8fd9"' );
	}

	/**
	 * @ticket 28786
	 * @requires function mb_detect_order
	 */
	function test_wp_json_encode_non_utf8() {
		$charsets     = mb_detect_order();
		$old_charsets = $charsets;
		if ( ! in_array( 'EUC-JP', $charsets, true ) ) {
			$charsets[] = 'EUC-JP';
			mb_detect_order( $charsets );
		}

		$eucjp = mb_convert_encoding( 'aあb', 'EUC-JP', 'UTF-8' );
		$utf8  = mb_convert_encoding( $eucjp, 'UTF-8', 'EUC-JP' );

		$this->assertSame( 'aあb', $utf8 );

		$this->assertSame( '"a\u3042b"', wp_json_encode( $eucjp ) );

		mb_detect_order( $old_charsets );
	}

	/**
	 * @ticket 28786
	 * @requires function mb_detect_order
	 */
	function test_wp_json_encode_non_utf8_in_array() {
		$charsets     = mb_detect_order();
		$old_charsets = $charsets;
		if ( ! in_array( 'EUC-JP', $charsets, true ) ) {
			$charsets[] = 'EUC-JP';
			mb_detect_order( $charsets );
		}

		$eucjp = mb_convert_encoding( 'aあb', 'EUC-JP', 'UTF-8' );
		$utf8  = mb_convert_encoding( $eucjp, 'UTF-8', 'EUC-JP' );

		$this->assertSame( 'aあb', $utf8 );

		$this->assertSame( '["c","a\u3042b"]', wp_json_encode( array( 'c', $eucjp ) ) );

		mb_detect_order( $old_charsets );
	}

	/**
	 * @ticket 28786
	 */
	function test_wp_json_encode_array() {
		$this->assertSame( wp_json_encode( array( 'a' ) ), '["a"]' );
	}

	/**
	 * @ticket 28786
	 */
	function test_wp_json_encode_object() {
		$object    = new stdClass;
		$object->a = 'b';
		$this->assertSame( wp_json_encode( $object ), '{"a":"b"}' );
	}

	/**
	 * @ticket 28786
	 */
	function test_wp_json_encode_depth() {
		$data = array( array( array( 1, 2, 3 ) ) );
		$json = wp_json_encode( $data, 0, 1 );
		$this->assertFalse( $json );

		$data = array( 'あ', array( array( 1, 2, 3 ) ) );
		$json = wp_json_encode( $data, 0, 1 );
		$this->assertFalse( $json );
	}

	/**
	 * @ticket 53238
	 */
	function test_wp_json_file_decode() {
		$result = wp_json_file_decode(
			DIR_TESTDATA . '/blocks/notice/block.json'
		);

		$this->assertIsObject( $result );
		$this->assertSame( 'tests/notice', $result->name );
	}

	/**
	 * @ticket 53238
	 */
	function test_wp_json_file_decode_associative_array() {
		$result = wp_json_file_decode(
			DIR_TESTDATA . '/blocks/notice/block.json',
			array( 'associative' => true )
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'tests/notice', $result['name'] );
	}

	/**
	 * @ticket 36054
	 * @dataProvider datetime_provider
	 */
	function test_mysql_to_rfc3339( $expected, $actual ) {
		$date_return = mysql_to_rfc3339( $actual );

		$this->assertIsString( $date_return, 'The date return must be a string' );
		$this->assertNotEmpty( $date_return, 'The date return could not be an empty string' );
		$this->assertSame( $expected, $date_return, 'The date does not match' );
		$this->assertEquals( new DateTime( $expected ), new DateTime( $date_return ), 'The date is not the same after the call method' );
	}

	function datetime_provider() {
		return array(
			array( '2016-03-15T18:54:46', '15-03-2016 18:54:46' ),
			array( '2016-03-02T19:13:25', '2016-03-02 19:13:25' ),
			array( '2016-03-02T19:13:00', '2016-03-02 19:13' ),
			array( '2016-03-02T19:13:00', '16-03-02 19:13' ),
			array( '2016-03-02T19:13:00', '16-03-02 19:13' ),
		);
	}

	/**
	 * @ticket 35987
	 */
	public function test_wp_get_ext_types() {
		$extensions = wp_get_ext_types();

		$this->assertIsArray( $extensions );
		$this->assertNotEmpty( $extensions );

		add_filter( 'ext2type', '__return_empty_array' );
		$extensions = wp_get_ext_types();
		$this->assertSame( array(), $extensions );

		remove_filter( 'ext2type', '__return_empty_array' );
		$extensions = wp_get_ext_types();
		$this->assertIsArray( $extensions );
		$this->assertNotEmpty( $extensions );
	}

	/**
	 * @ticket 35987
	 */
	public function test_wp_ext2type() {
		$extensions = wp_get_ext_types();

		foreach ( $extensions as $type => $extension_list ) {
			foreach ( $extension_list as $extension ) {
				$this->assertSame( $type, wp_ext2type( $extension ) );
				$this->assertSame( $type, wp_ext2type( strtoupper( $extension ) ) );
			}
		}

		$this->assertNull( wp_ext2type( 'unknown_format' ) );
	}

	/**
	 * Tests raising the memory limit.
	 *
	 * Unfortunately as the default for 'WP_MAX_MEMORY_LIMIT' in the
	 * test suite is -1, we can not test the memory limit negotiations.
	 *
	 * @ticket 32075
	 */
	function test_wp_raise_memory_limit() {
		if ( -1 !== WP_MAX_MEMORY_LIMIT ) {
			$this->markTestSkipped( 'WP_MAX_MEMORY_LIMIT should be set to -1.' );
		}

		$ini_limit_before = ini_get( 'memory_limit' );
		$raised_limit     = wp_raise_memory_limit();
		$ini_limit_after  = ini_get( 'memory_limit' );

		$this->assertSame( $ini_limit_before, $ini_limit_after );
		$this->assertFalse( $raised_limit );
		$this->assertEquals( WP_MAX_MEMORY_LIMIT, $ini_limit_after );
	}

	/**
	 * Tests wp_generate_uuid4().
	 *
	 * @covers ::wp_generate_uuid4
	 * @ticket 38164
	 */
	function test_wp_generate_uuid4() {
		$uuids = array();
		for ( $i = 0; $i < 20; $i += 1 ) {
			$uuid = wp_generate_uuid4();
			$this->assertTrue( wp_is_uuid( $uuid, 4 ) );
			$uuids[] = $uuid;
		}

		$unique_uuids = array_unique( $uuids );
		$this->assertSame( $uuids, $unique_uuids );
	}

	/**
	 * Tests wp_is_uuid().
	 *
	 * @covers ::wp_is_uuid
	 * @ticket 39778
	 */
	function test_wp_is_valid_uuid() {
		$uuids_v4 = array(
			'27fe2421-780c-44c5-b39b-fff753092b55',
			'b7c7713a-4ee9-45a1-87ed-944a90390fc7',
			'fbedbe35-7bf5-49cc-a5ac-0343bd94360a',
			'4c58e67e-123b-4290-a41c-5eeb6970fa3e',
			'f54f5b78-e414-4637-84a9-a6cdc94a1beb',
			'd1c533ac-abcf-44b6-9b0e-6477d2c91b09',
			'7fcd683f-e5fd-454a-a8b9-ed15068830da',
			'7962c750-e58c-470a-af0d-ec1eae453ff2',
			'a59878ce-9a67-4493-8ca0-a756b52804b3',
			'6faa519d-1e13-4415-bd6f-905ae3689d1d',
		);

		foreach ( $uuids_v4 as $uuid ) {
			$this->assertTrue( wp_is_uuid( $uuid, 4 ) );
		}

		$uuids = array(
			'00000000-0000-0000-0000-000000000000', // Nil.
			'9e3a0460-d72d-11e4-a631-c8e0eb141dab', // Version 1.
			'2c1d43b8-e6d7-376e-af7f-d4bde997cc3f', // Version 3.
			'39888f87-fb62-5988-a425-b2ea63f5b81e', // Version 5.
		);

		foreach ( $uuids as $uuid ) {
			$this->assertTrue( wp_is_uuid( $uuid ) );
			$this->assertFalse( wp_is_uuid( $uuid, 4 ) );
		}

		$invalid_uuids = array(
			'a19d5192-ea41-41e6-b006',
			'this-is-not-valid',
			1234,
			true,
			array(),
		);

		foreach ( $invalid_uuids as $invalid_uuid ) {
			$this->assertFalse( wp_is_uuid( $invalid_uuid, 4 ) );
			$this->assertFalse( wp_is_uuid( $invalid_uuid ) );
		}
	}

	/**
	 * Tests wp_unique_id().
	 *
	 * @covers ::wp_unique_id
	 * @ticket 44883
	 */
	function test_wp_unique_id() {

		// Test without prefix.
		$ids = array();
		for ( $i = 0; $i < 20; $i += 1 ) {
			$id = wp_unique_id();
			$this->assertIsString( $id );
			$this->assertIsNumeric( $id );
			$ids[] = $id;
		}
		$this->assertSame( $ids, array_unique( $ids ) );

		// Test with prefix.
		$ids = array();
		for ( $i = 0; $i < 20; $i += 1 ) {
			$id = wp_unique_id( 'foo-' );
			$this->assertMatchesRegularExpression( '/^foo-\d+$/', $id );
			$ids[] = $id;
		}
		$this->assertSame( $ids, array_unique( $ids ) );
	}

	/**
	 * @ticket 40017
	 * @dataProvider _wp_get_image_mime
	 */
	public function test_wp_get_image_mime( $file, $expected ) {
		if ( ! is_callable( 'exif_imagetype' ) && ! function_exists( 'getimagesize' ) ) {
			$this->markTestSkipped( 'The exif PHP extension is not loaded.' );
		}

		$this->assertSame( $expected, wp_get_image_mime( $file ) );
	}

	/**
	 * @ticket 35725
	 * @dataProvider data_wp_getimagesize
	 */
	public function test_wp_getimagesize( $file, $expected ) {
		if ( ! is_callable( 'exif_imagetype' ) && ! function_exists( 'getimagesize' ) ) {
			$this->markTestSkipped( 'The exif PHP extension is not loaded.' );
		}

		$result = wp_getimagesize( $file );

		// The getimagesize() function varies in its response, so
		// let's restrict comparison to expected keys only.
		if ( is_array( $expected ) ) {
			foreach ( $expected as $k => $v ) {
				$this->assertArrayHasKey( $k, $result );
				$this->assertSame( $expected[ $k ], $result[ $k ] );
			}
		} else {
			$this->assertSame( $expected, $result );
		}
	}

	/**
	 * @ticket 39550
	 * @dataProvider _wp_check_filetype_and_ext_data
	 * @requires extension fileinfo
	 */
	function test_wp_check_filetype_and_ext( $file, $filename, $expected ) {
		$this->assertSame( $expected, wp_check_filetype_and_ext( $file, $filename ) );
	}

	/**
	 * @ticket 39550
	 * @group ms-excluded
	 * @requires extension fileinfo
	 */
	function test_wp_check_filetype_and_ext_with_filtered_svg() {
		$file     = DIR_TESTDATA . '/uploads/video-play.svg';
		$filename = 'video-play.svg';

		$expected = array(
			'ext'             => 'svg',
			'type'            => 'image/svg+xml',
			'proper_filename' => false,
		);

		add_filter( 'upload_mimes', array( $this, '_filter_mime_types_svg' ) );
		$this->assertSame( $expected, wp_check_filetype_and_ext( $file, $filename ) );

		// Cleanup.
		remove_filter( 'upload_mimes', array( $this, '_test_add_mime_types_svg' ) );
	}

	/**
	 * @ticket 39550
	 * @group ms-excluded
	 * @requires extension fileinfo
	 */
	function test_wp_check_filetype_and_ext_with_filtered_woff() {
		$file     = DIR_TESTDATA . '/uploads/dashicons.woff';
		$filename = 'dashicons.woff';

		$expected = array(
			'ext'             => 'woff',
			'type'            => 'application/font-woff',
			'proper_filename' => false,
		);

		add_filter( 'upload_mimes', array( $this, '_filter_mime_types_woff' ) );
		$this->assertSame( $expected, wp_check_filetype_and_ext( $file, $filename ) );

		// Cleanup.
		remove_filter( 'upload_mimes', array( $this, '_test_add_mime_types_woff' ) );
	}

	public function _filter_mime_types_svg( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	public function _filter_mime_types_woff( $mimes ) {
		$mimes['woff'] = 'application/font-woff';
		return $mimes;
	}

	/**
	 * Data provider for test_wp_get_image_mime().
	 */
	public function _wp_get_image_mime() {
		$data = array(
			// Standard JPEG.
			array(
				DIR_TESTDATA . '/images/test-image.jpg',
				'image/jpeg',
			),
			// Standard GIF.
			array(
				DIR_TESTDATA . '/images/test-image.gif',
				'image/gif',
			),
			// Standard PNG.
			array(
				DIR_TESTDATA . '/images/test-image.png',
				'image/png',
			),
			// Image with wrong extension.
			array(
				DIR_TESTDATA . '/images/test-image-mime-jpg.png',
				'image/jpeg',
			),
			// Animated WebP.
			array(
				DIR_TESTDATA . '/images/webp-animated.webp',
				'image/webp',
			),
			// Lossless WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossless.webp',
				'image/webp',
			),
			// Lossy WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossy.webp',
				'image/webp',
			),
			// Transparent WebP.
			array(
				DIR_TESTDATA . '/images/webp-transparent.webp',
				'image/webp',
			),
			// Not an image.
			array(
				DIR_TESTDATA . '/uploads/dashicons.woff',
				false,
			),
		);

		return $data;
	}

	/**
	 * Data profider for test_wp_getimagesize().
	 */
	public function data_wp_getimagesize() {
		$data = array(
			// Standard JPEG.
			array(
				DIR_TESTDATA . '/images/test-image.jpg',
				array(
					50,
					50,
					IMAGETYPE_JPEG,
					'width="50" height="50"',
					'mime' => 'image/jpeg',
				),
			),
			// Standard GIF.
			array(
				DIR_TESTDATA . '/images/test-image.gif',
				array(
					50,
					50,
					IMAGETYPE_GIF,
					'width="50" height="50"',
					'mime' => 'image/gif',
				),
			),
			// Standard PNG.
			array(
				DIR_TESTDATA . '/images/test-image.png',
				array(
					50,
					50,
					IMAGETYPE_PNG,
					'width="50" height="50"',
					'mime' => 'image/png',
				),
			),
			// Image with wrong extension.
			array(
				DIR_TESTDATA . '/images/test-image-mime-jpg.png',
				array(
					50,
					50,
					IMAGETYPE_JPEG,
					'width="50" height="50"',
					'mime' => 'image/jpeg',
				),
			),
			// Animated WebP.
			array(
				DIR_TESTDATA . '/images/webp-animated.webp',
				array(
					100,
					100,
					IMAGETYPE_WEBP,
					'width="100" height="100"',
					'mime' => 'image/webp',
				),
			),
			// Lossless WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossless.webp',
				array(
					1200,
					675,
					IMAGETYPE_WEBP,
					'width="1200" height="675"',
					'mime' => 'image/webp',
				),
			),
			// Lossy WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossy.webp',
				array(
					1200,
					675,
					IMAGETYPE_WEBP,
					'width="1200" height="675"',
					'mime' => 'image/webp',
				),
			),
			// Transparent WebP.
			array(
				DIR_TESTDATA . '/images/webp-transparent.webp',
				array(
					1200,
					675,
					IMAGETYPE_WEBP,
					'width="1200" height="675"',
					'mime' => 'image/webp',
				),
			),
			// Not an image.
			array(
				DIR_TESTDATA . '/uploads/dashicons.woff',
				false,
			),
		);

		return $data;
	}

	public function _wp_check_filetype_and_ext_data() {
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
	 * Test file path validation
	 *
	 * @ticket 42016
	 * @dataProvider data_test_validate_file()
	 *
	 * @param string $file          File path.
	 * @param array  $allowed_files List of allowed files.
	 * @param int    $expected      Expected result.
	 */
	public function test_validate_file( $file, $allowed_files, $expected ) {
		$this->assertSame( $expected, validate_file( $file, $allowed_files ) );
	}

	/**
	 * Data provider for file validation.
	 *
	 * @return array {
	 *     @type array $0... {
	 *         @type string $0 File path.
	 *         @type array  $1 List of allowed files.
	 *         @type int    $2 Expected result.
	 *     }
	 * }
	 */
	public function data_test_validate_file() {
		return array(

			// Allowed files:
			array(
				null,
				array(),
				0,
			),
			array(
				'',
				array(),
				0,
			),
			array(
				' ',
				array(),
				0,
			),
			array(
				'.',
				array(),
				0,
			),
			array(
				'..',
				array(),
				0,
			),
			array(
				'./',
				array(),
				0,
			),
			array(
				'foo.ext',
				array( 'foo.ext' ),
				0,
			),
			array(
				'dir/foo.ext',
				array(),
				0,
			),
			array(
				'foo..ext',
				array(),
				0,
			),
			array(
				'dir/dir/../',
				array(),
				0,
			),

			// Directory traversal:
			array(
				'../',
				array(),
				1,
			),
			array(
				'../../',
				array(),
				1,
			),
			array(
				'../file.ext',
				array(),
				1,
			),
			array(
				'../dir/../',
				array(),
				1,
			),
			array(
				'/dir/dir/../../',
				array(),
				1,
			),
			array(
				'/dir/dir/../../',
				array( '/dir/dir/../../' ),
				1,
			),

			// Windows drives:
			array(
				'c:',
				array(),
				2,
			),
			array(
				'C:/WINDOWS/system32',
				array( 'C:/WINDOWS/system32' ),
				2,
			),

			// Disallowed files:
			array(
				'foo.ext',
				array( 'bar.ext' ),
				3,
			),
			array(
				'foo.ext',
				array( '.ext' ),
				3,
			),
			array(
				'path/foo.ext',
				array( 'foo.ext' ),
				3,
			),

		);
	}

	/**
	 * Test stream URL validation.
	 *
	 * @dataProvider data_test_wp_is_stream
	 *
	 * @param string $path     The resource path or URL.
	 * @param bool   $expected Expected result.
	 */
	public function test_wp_is_stream( $path, $expected ) {
		if ( ! extension_loaded( 'openssl' ) && false !== strpos( $path, 'https://' ) ) {
			$this->markTestSkipped( 'The openssl PHP extension is not loaded.' );
		}

		$this->assertSame( $expected, wp_is_stream( $path ) );
	}

	/**
	 * Data provider for stream URL validation.
	 *
	 * @return array {
	 *     @type array $0... {
	 *         @type string $0 The resource path or URL.
	 *         @type bool   $1 Expected result.
	 *     }
	 * }
	 */
	public function data_test_wp_is_stream() {
		return array(
			// Legitimate stream examples.
			array( 'http://example.com', true ),
			array( 'https://example.com', true ),
			array( 'ftp://example.com', true ),
			array( 'file:///path/to/some/file', true ),
			array( 'php://some/php/file.php', true ),

			// Non-stream examples.
			array( 'fakestream://foo/bar/baz', false ),
			array( '../../some/relative/path', false ),
			array( 'some/other/relative/path', false ),
			array( '/leading/relative/path', false ),
		);
	}

	/**
	 * Test human_readable_duration().
	 *
	 * @ticket 39667
	 * @dataProvider data_test_human_readable_duration
	 *
	 * @param string $input    Duration.
	 * @param string $expected Expected human readable duration.
	 */
	public function test_human_readable_duration( $input, $expected ) {
		$this->assertSame( $expected, human_readable_duration( $input ) );
	}

	/**
	 * Dataprovider for test_duration_format().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input  Duration.
	 *         @type string $expect Expected human readable duration.
	 *     }
	 * }
	 */
	public function data_test_human_readable_duration() {
		return array(
			// Valid ii:ss cases.
			array( '0:0', '0 minutes, 0 seconds' ),
			array( '00:00', '0 minutes, 0 seconds' ),
			array( '0:5', '0 minutes, 5 seconds' ),
			array( '0:05', '0 minutes, 5 seconds' ),
			array( '01:01', '1 minute, 1 second' ),
			array( '30:00', '30 minutes, 0 seconds' ),
			array( ' 30:00 ', '30 minutes, 0 seconds' ),
			// Valid HH:ii:ss cases.
			array( '0:0:0', '0 hours, 0 minutes, 0 seconds' ),
			array( '00:00:00', '0 hours, 0 minutes, 0 seconds' ),
			array( '00:30:34', '0 hours, 30 minutes, 34 seconds' ),
			array( '01:01:01', '1 hour, 1 minute, 1 second' ),
			array( '1:02:00', '1 hour, 2 minutes, 0 seconds' ),
			array( '10:30:34', '10 hours, 30 minutes, 34 seconds' ),
			array( '1234567890:59:59', '1234567890 hours, 59 minutes, 59 seconds' ),
			// Valid ii:ss cases with negative sign.
			array( '-00:00', '0 minutes, 0 seconds' ),
			array( '-3:00', '3 minutes, 0 seconds' ),
			array( '-03:00', '3 minutes, 0 seconds' ),
			array( '-30:00', '30 minutes, 0 seconds' ),
			// Valid HH:ii:ss cases with negative sign.
			array( '-00:00:00', '0 hours, 0 minutes, 0 seconds' ),
			array( '-1:02:00', '1 hour, 2 minutes, 0 seconds' ),
			// Invalid cases.
			array( null, false ),
			array( '', false ),
			array( ':', false ),
			array( '::', false ),
			array( array(), false ),
			array( 'Batman Begins !', false ),
			array( '', false ),
			array( '-1', false ),
			array( -1, false ),
			array( 0, false ),
			array( 1, false ),
			array( '00', false ),
			array( '30:-10', false ),
			array( ':30:00', false ),   // Missing HH.
			array( 'MM:30:00', false ), // Invalid HH.
			array( '30:MM:00', false ), // Invalid ii.
			array( '30:30:MM', false ), // Invalid ss.
			array( '30:MM', false ),    // Invalid ss.
			array( 'MM:00', false ),    // Invalid ii.
			array( 'MM:MM', false ),    // Invalid ii and ss.
			array( '10 :30', false ),   // Containing a space.
			array( '59:61', false ),    // Out of bound.
			array( '61:59', false ),    // Out of bound.
			array( '3:59:61', false ),  // Out of bound.
			array( '03:61:59', false ), // Out of bound.
		);
	}

	/**
	 * @ticket 49404
	 * @dataProvider data_test_wp_is_json_media_type
	 */
	public function test_wp_is_json_media_type( $input, $expected ) {
		$this->assertSame( $expected, wp_is_json_media_type( $input ) );
	}


	public function data_test_wp_is_json_media_type() {
		return array(
			array( 'application/ld+json', true ),
			array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', true ),
			array( 'application/activity+json', true ),
			array( 'application/json+oembed', true ),
			array( 'application/json', true ),
			array( 'application/nojson', false ),
			array( 'application/no.json', false ),
			array( 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8', false ),
			array( 'application/activity+json, application/nojson', true ),
		);
	}

	/**
	 * @ticket 53668
	 */
	public function test_wp_get_default_extension_for_mime_type() {
		$this->assertEquals( 'jpg', wp_get_default_extension_for_mime_type( 'image/jpeg' ), 'jpg not returned as default extension for "image/jpeg"' );
		$this->assertNotEquals( 'jpeg', wp_get_default_extension_for_mime_type( 'image/jpeg' ), 'jpeg should not be returned as default extension for "image/jpeg"' );
		$this->assertEquals( 'png', wp_get_default_extension_for_mime_type( 'image/png' ), 'png not returned as default extension for "image/png"' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( 'wibble/wobble' ), 'false not returned for unrecognized mime type' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( '' ), 'false not returned when empty string as mime type supplied' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( '   ' ), 'false not returned when empty string as mime type supplied' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( 123 ), 'false not returned when int as mime type supplied' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( null ), 'false not returned when null as mime type supplied' );
	}
}
