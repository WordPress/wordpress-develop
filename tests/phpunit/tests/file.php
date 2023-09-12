<?php

/**
 * @group file
 */
class Tests_File extends WP_UnitTestCase {

	const BADCHARS = '"\'[]*&?$';

	private $dir;

	public function set_up() {
		parent::set_up();

		$this->dir = untrailingslashit( get_temp_dir() );
	}

	/**
	 * @group plugins
	 * @group themes
	 */
	public function test_get_file_data() {
		$theme_headers = array(
			'Name'        => 'Theme Name',
			'ThemeURI'    => 'Theme URI',
			'Description' => 'Description',
			'Version'     => 'Version',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
		);

		$actual = get_file_data( DIR_TESTDATA . '/themedir1/default/style.css', $theme_headers );

		$expected = array(
			'Name'        => 'WordPress Default',
			'ThemeURI'    => 'http://wordpress.org/',
			'Description' => 'The default WordPress theme based on the famous <a href="http://binarybonsai.com/kubrick/">Kubrick</a>.',
			'Version'     => '1.6',
			'Author'      => 'Michael Heilemann',
			'AuthorURI'   => 'http://binarybonsai.com/',
		);

		foreach ( $actual as $header => $value ) {
			$this->assertSame( $expected[ $header ], $value, $header );
		}
	}

	/**
	 * @ticket 19854
	 * @group plugins
	 * @group themes
	 */
	public function test_get_file_data_with_cr_line_endings() {
		$headers = array(
			'SomeHeader'  => 'Some Header',
			'Description' => 'Description',
			'Author'      => 'Author',
		);

		$actual   = get_file_data( DIR_TESTDATA . '/formatting/file-header-cr-line-endings.php', $headers );
		$expected = array(
			'SomeHeader'  => 'Some header value!',
			'Description' => 'This file is using CR line endings for a testcase.',
			'Author'      => 'A Very Old Mac',
		);

		foreach ( $actual as $header => $value ) {
			$this->assertSame( $expected[ $header ], $value, $header );
		}
	}

	/**
	 * @ticket 47186
	 * @group plugins
	 * @group themes
	 */
	public function test_get_file_data_with_php_open_tag_prefix() {
		$headers = array(
			'TemplateName' => 'Template Name',
		);

		$actual   = get_file_data( DIR_TESTDATA . '/formatting/file-header-php-open-tag-prefix.php', $headers );
		$expected = array(
			'TemplateName' => 'Something',
		);

		foreach ( $actual as $header => $value ) {
			$this->assertSame( $expected[ $header ], $value, $header );
		}
	}

	private function is_unique_writable_file( $path, $filename ) {
		$fullpath = $path . DIRECTORY_SEPARATOR . $filename;

		$fp = fopen( $fullpath, 'x' );
		// File already exists?
		if ( ! $fp ) {
			return false;
		}

		// Write some contents.
		$c = 'foo';
		fwrite( $fp, $c );
		fclose( $fp );

		if ( file_get_contents( $fullpath ) === $c ) {
			$result = true;
		} else {
			$result = false;
		}

		return $result;
	}

	public function test_unique_filename_is_valid() {
		// Make sure it produces a valid, writable, unique filename.
		$filename = wp_unique_filename( $this->dir, __FUNCTION__ . '.txt' );

		$this->assertTrue( $this->is_unique_writable_file( $this->dir, $filename ) );

		unlink( $this->dir . DIRECTORY_SEPARATOR . $filename );
	}

	public function test_unique_filename_is_unique() {
		// Make sure it produces two unique filenames.
		$name = __FUNCTION__;

		$filename1 = wp_unique_filename( $this->dir, $name . '.txt' );
		$this->assertTrue( $this->is_unique_writable_file( $this->dir, $filename1 ) );
		$filename2 = wp_unique_filename( $this->dir, $name . '.txt' );
		$this->assertTrue( $this->is_unique_writable_file( $this->dir, $filename2 ) );

		// The two should be different.
		$this->assertNotEquals( $filename1, $filename2 );

		unlink( $this->dir . DIRECTORY_SEPARATOR . $filename1 );
		unlink( $this->dir . DIRECTORY_SEPARATOR . $filename2 );
	}

	public function test_unique_filename_is_sanitized() {
		$name     = __FUNCTION__;
		$filename = wp_unique_filename( $this->dir, $name . self::BADCHARS . '.txt' );

		// Make sure the bad characters were all stripped out.
		$this->assertSame( $name . '.txt', $filename );

		$this->assertTrue( $this->is_unique_writable_file( $this->dir, $filename ) );

		unlink( $this->dir . DIRECTORY_SEPARATOR . $filename );
	}

	public function test_unique_filename_with_slashes() {
		$name = __FUNCTION__;
		// "foo/foo.txt"
		$filename = wp_unique_filename( $this->dir, $name . '/' . $name . '.txt' );

		// The slash should be removed, i.e. "foofoo.txt".
		$this->assertSame( $name . $name . '.txt', $filename );

		$this->assertTrue( $this->is_unique_writable_file( $this->dir, $filename ) );

		unlink( $this->dir . DIRECTORY_SEPARATOR . $filename );
	}

	public function test_unique_filename_multiple_ext() {
		$name     = __FUNCTION__;
		$filename = wp_unique_filename( $this->dir, $name . '.php.txt' );

		// "foo.php.txt" becomes "foo.php_.txt".
		$this->assertSame( $name . '.php_.txt', $filename );

		$this->assertTrue( $this->is_unique_writable_file( $this->dir, $filename ) );

		unlink( $this->dir . DIRECTORY_SEPARATOR . $filename );
	}

	public function test_unique_filename_no_ext() {
		$name     = __FUNCTION__;
		$filename = wp_unique_filename( $this->dir, $name );

		$this->assertSame( $name, $filename );

		$this->assertTrue( $this->is_unique_writable_file( $this->dir, $filename ) );

		unlink( $this->dir . DIRECTORY_SEPARATOR . $filename );
	}

	/**
	 * @dataProvider data_wp_tempnam_filenames
	 */
	public function test_wp_tempnam( $filename ) {
		$file = wp_tempnam( $filename );
		unlink( $file );

		$this->assertNotEmpty( basename( basename( $file, '.tmp' ), '.zip' ) );
	}
	public function data_wp_tempnam_filenames() {
		return array(
			array( '0.zip' ),
			array( '0.1.2.3.zip' ),
			array( 'filename.zip' ),
			array( 'directory/0.zip' ),
			array( 'directory/filename.zip' ),
			array( 'directory/0/0.zip' ),
		);
	}

	/**
	 * Tests that `wp_tempnam()` limits the filename's length to 252 characters.
	 *
	 * @ticket 35755
	 *
	 * @covers ::wp_tempnam
	 *
	 * @dataProvider data_wp_tempnam_should_limit_filename_length_to_252_characters
	 */
	public function test_wp_tempnam_should_limit_filename_length_to_252_characters( $filename ) {
		$file = wp_tempnam( $filename );

		if ( file_exists( $file ) ) {
			self::unlink( $file );
		}

		$this->assertLessThanOrEqual( 252, strlen( basename( $file ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_tempnam_should_limit_filename_length_to_252_characters() {
		return array(
			'the limit before adding characters for uniqueness' => array( 'filename' => str_pad( '', 241, 'filename' ) ),
			'one more than the limit before adding characters for uniqueness' => array( 'filename' => str_pad( '', 242, 'filename' ) ),
			'251 characters' => array( 'filename' => str_pad( '', 251, 'filename' ) ),
			'252 characters' => array( 'filename' => str_pad( '', 252, 'filename' ) ),
			'253 characters' => array( 'filename' => str_pad( '', 253, 'filename' ) ),
		);
	}

	/**
	 * Tests that `wp_tempnam()` limits the filename's length to 252 characters
	 * when there is a name conflict.
	 *
	 * @ticket 35755
	 *
	 * @covers ::wp_tempnam
	 */
	public function test_wp_tempnam_should_limit_filename_length_to_252_characters_with_name_conflict() {
		// Create a conflict by removing the randomness of the generated password.
		add_filter(
			'random_password',
			static function () {
				return '123456';
			},
			10,
			0
		);

		// A filename at the limit.
		$filename = str_pad( '', 252, 'filename' );

		// Create the initial file.
		$existing_file = wp_tempnam( $filename );

		// Try creating a file with the same name.
		$actual = wp_tempnam( basename( $existing_file ) );

		self::unlink( $existing_file );
		self::unlink( $actual );

		$this->assertLessThanOrEqual( 252, strlen( basename( $actual ) ) );
	}

	/**
	 * Tests that `wp_tempnam()` limits the filename's length to 252 characters
	 * when a 'random_password' filter returns passwords longer than 6 characters.
	 *
	 * @ticket 35755
	 *
	 * @covers ::wp_tempnam
	 */
	public function test_wp_tempnam_should_limit_filename_length_to_252_characters_when_random_password_is_filtered() {
		// Force random passwords to 12 characters.
		add_filter(
			'random_password',
			static function () {
				return '1a2b3c4d5e6f';
			},
			10,
			0
		);

		// A filename at the limit.
		$filename = str_pad( '', 252, 'filename' );
		$actual   = wp_tempnam( $filename );

		self::unlink( $actual );

		$this->assertLessThanOrEqual( 252, strlen( basename( $actual ) ) );
	}

	/**
	 * Tests that `wp_tempnam()` limits the filename's length to 252 characters
	 * when a 'wp_unique_filename' filter returns a filename longer than 252 characters.
	 *
	 * @ticket 35755
	 *
	 * @covers ::wp_tempnam
	 */
	public function test_wp_tempnam_should_limit_filename_length_to_252_characters_when_wp_unique_filename_is_filtered() {
		// Determine the number of additional characters added by `wp_tempnam()`.
		$temp_dir                    = get_temp_dir();
		$additional_chars_filename   = wp_unique_filename( $temp_dir, 'filename' );
		$additional_chars_generated  = wp_tempnam( $additional_chars_filename, $temp_dir );
		$additional_chars_difference = strlen( basename( $additional_chars_generated ) ) - strlen( $additional_chars_filename );

		$filenames_over_limit = 0;

		// Make the filter send the filename over the limit.
		add_filter(
			'wp_unique_filename',
			static function ( $filename ) use ( &$filenames_over_limit ) {
				if ( strlen( $filename ) === 252 ) {
					$filename .= '1';
					++$filenames_over_limit;
				}

				return $filename;
			},
			10,
			1
		);

		// A filename that will hit the limit when `wp_tempnam()` adds characters.
		$filename = str_pad( '', 252 - $additional_chars_difference, 'filename' );
		$actual   = wp_tempnam( $filename );

		self::unlink( $additional_chars_generated );
		self::unlink( $actual );

		$this->assertLessThanOrEqual( 252, strlen( basename( $actual ) ), 'The final filename was over the limit.' );
		$this->assertSame( 1, $filenames_over_limit, 'One filename should have been over the limit.' );
	}

	/**
	 * Tests that `wp_tempnam()` limits the filename's length to 252 characters
	 * when both a 'random_password' filter and a 'wp_unique_filename' filter
	 * cause the filename to be greater than 252 characters.
	 *
	 * @ticket 35755
	 *
	 * @covers ::wp_tempnam
	 */
	public function test_wp_tempnam_should_limit_filename_length_to_252_characters_when_random_password_and_wp_unique_filename_are_filtered() {
		// Force random passwords to 12 characters.
		add_filter(
			'random_password',
			static function () {
				return '1a2b3c4d5e6f';
			},
			10,
			0
		);

		// Determine the number of additional characters added by `wp_tempnam()`.
		$temp_dir                    = get_temp_dir();
		$additional_chars_filename   = wp_unique_filename( $temp_dir, 'filename' );
		$additional_chars_generated  = wp_tempnam( $additional_chars_filename, $temp_dir );
		$additional_chars_difference = strlen( basename( $additional_chars_generated ) ) - strlen( $additional_chars_filename );

		$filenames_over_limit = 0;

		// Make the filter send the filename over the limit.
		add_filter(
			'wp_unique_filename',
			static function ( $filename ) use ( &$filenames_over_limit ) {
				if ( strlen( $filename ) === 252 ) {
					$filename .= '1';
					++$filenames_over_limit;
				}

				return $filename;
			},
			10,
			1
		);

		// A filename that will hit the limit when `wp_tempnam()` adds characters.
		$filename = str_pad( '', 252 - $additional_chars_difference, 'filename' );
		$actual   = wp_tempnam( $filename );

		self::unlink( $additional_chars_generated );
		self::unlink( $actual );

		$this->assertLessThanOrEqual( 252, strlen( basename( $actual ) ), 'The final filename was over the limit.' );
		$this->assertSame( 1, $filenames_over_limit, 'One filename should have been over the limit.' );
	}

	/**
	 * @ticket 47186
	 */
	public function test_file_signature_functions_as_expected() {
		$file = wp_tempnam();
		file_put_contents( $file, 'WordPress' );

		// The signature of 'WordPress' after SHA384 hashing, for verification against the key within self::filter_trust_plus85Tq_key().
		$expected_signature = 'PmNv0b1ziwJAsVhjdpjd4+PQZidZWSlBm5b+GbbwE9m9HVKDFhEyvyRTHkRYOLypB8P2YvbW7CoOMZqGh8mEAA==';

		add_filter( 'wp_trusted_keys', array( $this, 'filter_trust_plus85Tq_key' ) );

		// Measure how long the call takes.
		$timer_start = microtime( 1 );
		$verify      = verify_file_signature( $file, $expected_signature, 'WordPress' );
		$timer_end   = microtime( 1 );
		$time_taken  = ( $timer_end - $timer_start );

		unlink( $file );
		remove_filter( 'wp_trusted_keys', array( $this, 'filter_trust_plus85Tq_key' ) );

		// verify_file_signature() should intentionally never take more than 10s to run.
		$this->assertLessThan( 10, $time_taken, 'verify_file_signature() took longer than 10 seconds.' );

		// Check to see if the system parameters prevent signature verifications.
		if ( is_wp_error( $verify ) && 'signature_verification_unsupported' === $verify->get_error_code() ) {
			$this->markTestSkipped( 'This system does not support Signature Verification.' );
		}

		$this->assertNotWPError( $verify );
		$this->assertTrue( $verify );
	}

	/**
	 * @ticket 47186
	 */
	public function test_file_signature_expected_failure() {
		$file = wp_tempnam();
		file_put_contents( $file, 'WordPress' );

		// Test an invalid signature.
		$expected_signature = base64_encode( str_repeat( 'A', SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ) );
		$verify             = verify_file_signature( $file, $expected_signature, 'WordPress' );
		unlink( $file );

		if ( is_wp_error( $verify ) && 'signature_verification_unsupported' === $verify->get_error_code() ) {
			$this->markTestSkipped( 'This system does not support Signature Verification.' );
		}

		$this->assertWPError( $verify );
		$this->assertSame( 'signature_verification_failed', $verify->get_error_code() );
	}

	public function filter_trust_plus85Tq_key( $keys ) {
		// A static once-off key used to verify verify_file_signature() works as expected.
		$keys[] = '+85TqMhxQVAYVW4BSCVkJQvZH4q7z8I9lePbvngvf7A=';

		return $keys;
	}
}
