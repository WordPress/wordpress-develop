<?php

/**
 * @coversDefaultClass Ginger_MO
 * @group l10n
 * @group i18n
 */
class Ginger_MO_Convert_Tests extends WP_UnitTestCase {
	/**
	 * @covers ::instance
	 *
	 * @return void
	 */
	public function test_get_instance() {
		$instance  = Ginger_MO::instance();
		$instance2 = Ginger_MO::instance();

		$this->assertSame( $instance, $instance2 );
	}

	/**
	 * @return void
	 */
	public function test_no_files_loaded_returns_false() {
		$instance = new Ginger_MO();
		$this->assertFalse( $instance->translate( 'singular' ) );
		$this->assertFalse( $instance->translate_plural( array( 'plural0', 'plural1' ), 1 ) );
	}

	/**
	 * @covers ::unload
	 *
	 * @return void
	 */
	public function test_unload_not_loaded() {
		$instance = new Ginger_MO();
		$this->assertFalse( $instance->is_loaded( 'unittest' ) );
		$this->assertFalse( $instance->unload( 'unittest' ) );
	}

	/**
	 * @covers ::load
	 * @covers ::unload
	 * @covers ::is_loaded
	 * @covers ::translate
	 * @covers ::locate_translation
	 * @covers ::get_files
	 *
	 * @return void
	 */
	public function test_unload_entire_textdomain() {
		$instance = new Ginger_MO();
		$this->assertFalse( $instance->is_loaded( 'unittest' ) );
		$this->assertTrue( $instance->load( DIR_TESTDATA . '/ginger-mo/example-simple.php', 'unittest' ) );
		$this->assertTrue( $instance->is_loaded( 'unittest' ) );

		$this->assertSame( 'translation', $instance->translate( 'original', '', 'unittest' ) );

		$this->assertTrue( $instance->unload( 'unittest' ) );
		$this->assertFalse( $instance->is_loaded( 'unittest' ) );
		$this->assertFalse( $instance->translate( 'original', '', 'unittest' ) );
	}

	/**
	 * @covers ::unload
	 * @covers Ginger_MO_Translation_File::get_file
	 *
	 * @return void
	 */
	public function test_unload_file_is_not_actually_loaded() {
		$ginger_mo = new Ginger_MO();
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'unittest' ) );
		$this->assertTrue( $ginger_mo->unload( 'unittest', DIR_TESTDATA . '/ginger-mo/simple.mo' ) );

		$this->assertTrue( $ginger_mo->is_loaded( 'unittest' ) );
		$this->assertSame( 'translation', $ginger_mo->translate( 'original', '', 'unittest' ) );
	}

	/**
	 * @covers ::unload
	 * @covers ::is_loaded
	 *
	 * @return void
	 */
	public function test_unload_specific_locale() {
		$instance = new Ginger_MO();
		$this->assertFalse( $instance->is_loaded( 'unittest' ) );
		$this->assertTrue( $instance->load( DIR_TESTDATA . '/ginger-mo/example-simple.php', 'unittest' ) );
		$this->assertTrue( $instance->is_loaded( 'unittest' ) );

		$this->assertFalse( $instance->is_loaded( 'unittest', 'es_ES' ) );
		$this->assertTrue( $instance->load( DIR_TESTDATA . '/ginger-mo/example-simple.php', 'unittest', 'es_ES' ) );
		$this->assertTrue( $instance->is_loaded( 'unittest', 'es_ES' ) );

		$this->assertSame( 'translation', $instance->translate( 'original', '', 'unittest' ) );
		$this->assertSame( 'translation', $instance->translate( 'original', '', 'unittest', 'es_ES' ) );

		$this->assertTrue( $instance->unload( 'unittest', null, $instance->get_locale() ) );
		$this->assertFalse( $instance->is_loaded( 'unittest' ) );
		$this->assertFalse( $instance->translate( 'original', '', 'unittest' ) );

		$this->assertTrue( $instance->is_loaded( 'unittest', 'es_ES' ) );
		$this->assertTrue( $instance->unload( 'unittest', null, 'es_ES' ) );
		$this->assertFalse( $instance->is_loaded( 'unittest', 'es_ES' ) );
		$this->assertFalse( $instance->translate( 'original', '', 'unittest', 'es_ES' ) );
	}

	/**
	 * @dataProvider data_invalid_files
	 *
	 * @param string $type
	 * @param string $file_contents
	 * @param string|bool $expected_error
	 * @return void
	 *
	 * @phpstan-param 'mo'|'php' $type
	 */
	public function test_invalid_files( string $type, string $file_contents, $expected_error = null ) {
		$file = $this->temp_filename( $file_contents );

		$this->assertNotFalse( $file );

		$instance = Ginger_MO_Translation_File::create( $file, $type );

		$this->assertInstanceOf( Ginger_MO_Translation_File::class, $instance );

		// Not an error condition until it attempts to parse the file.
		$this->assertFalse( $instance->error() );

		// Trigger parsing.
		$instance->headers();

		$this->assertNotFalse( $instance->error() );

		if ( null !== $expected_error ) {
			$this->assertSame( $expected_error, $instance->error() );
		}
	}

	/**
	 * @return array{0: array{0: 'mo'|'php', 1: string|false, 2?: string}}
	 */
	public function data_invalid_files(): array {
		return array(
			array( 'php', '' ),
			array( 'php', '<?php // This is a php file without a payload' ),
			array( 'mo', '', 'Invalid Data.' ),
			array( 'mo', 'Random data in a file long enough to be a real header', "Magic Marker doesn't exist" ),
			array( 'mo', pack( 'V*', 0x950412de ), 'Invalid Data.' ),
			array( 'mo', pack( 'V*', 0x950412de ) . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'Unsupported Revision.' ),
			array( 'mo', pack( 'V*', 0x950412de, 0x0 ) . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'Invalid Data.' ),
		);
	}

	/**
	 * @covers Ginger_MO::load
	 * @covers Ginger_MO::is_loaded
	 *
	 * @return void
	 */
	public function test_load_non_existent_file() {
		$instance = new Ginger_MO();

		$this->assertFalse( $instance->load( DIR_TESTDATA . '/ginger-mo/file-that-doesnt-exist.mo', 'unittest' ) );
		$this->assertFalse( $instance->is_loaded( 'unittest' ) );
	}

	/**
	 * @covers Ginger_MO_Translation_File::create
	 *
	 * @return void
	 */
	public function test_create_non_existent_file() {
		$this->assertFalse( Ginger_MO_Translation_File::create( 'this-file-does-not-exist' ) );
	}

	/**
	 * @covers Ginger_MO_Translation_File::create
	 *
	 * @return void
	 */
	public function test_create_invalid_filetype() {
		$file = $this->temp_filename( '' );
		$this->assertNotFalse( $file );
		$this->assertFalse( Ginger_MO_Translation_File::create( $file, 'invalid' ) );
	}

	/**
	 * @covers ::load
	 * @covers ::is_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 * @covers ::locate_translation
	 * @covers ::get_files
	 *
	 * @dataProvider data_simple_example_files
	 *
	 * @param string $file
	 * @return void
	 */
	public function test_simple_translation_files( string $file ) {
		$ginger_mo = new Ginger_MO();
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/' . $file, 'unittest' ) );

		$this->assertTrue( $ginger_mo->is_loaded( 'unittest' ) );
		$this->assertFalse( $ginger_mo->is_loaded( 'textdomain not loaded' ) );

		$this->assertFalse( $ginger_mo->translate( "string that doesn't exist", '', 'unittest' ) );
		$this->assertFalse( $ginger_mo->translate( 'original', '', 'textdomain not loaded' ) );

		$this->assertSame( 'translation', $ginger_mo->translate( 'original', '', 'unittest' ) );
		$this->assertSame( 'translation with context', $ginger_mo->translate( 'original with context', 'context', 'unittest' ) );

		$this->assertSame( 'translation1', $ginger_mo->translate_plural( array( 'plural0', 'plural1' ), 0, '', 'unittest' ) );
		$this->assertSame( 'translation0', $ginger_mo->translate_plural( array( 'plural0', 'plural1' ), 1, '', 'unittest' ) );
		$this->assertSame( 'translation1', $ginger_mo->translate_plural( array( 'plural0', 'plural1' ), 2, '', 'unittest' ) );

		$this->assertSame( 'translation1 with context', $ginger_mo->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 0, 'context', 'unittest' ) );
		$this->assertSame( 'translation0 with context', $ginger_mo->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 1, 'context', 'unittest' ) );
		$this->assertSame( 'translation1 with context', $ginger_mo->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 2, 'context', 'unittest' ) );
	}

	/**
	 * @return array<array{0: string}>
	 */
	public function data_simple_example_files(): array {
		return array(
			array( 'example-simple.mo' ),
			array( 'example-simple.php' ),
		);
	}

	/**
	 * @covers ::load
	 * @covers ::unload
	 * @covers ::is_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 * @covers ::locate_translation
	 * @covers ::get_files
	 * @covers Ginger_MO_Translation_File::get_plural_form
	 * @covers Ginger_MO_Translation_File::make_plural_form_function
	 *
	 * @return void
	 */
	public function test_load_multiple_files() {
		$ginger_mo = new Ginger_MO();
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'unittest' ) );
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/simple.mo', 'unittest' ) );
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/plural.mo', 'unittest' ) );

		$this->assertTrue( $ginger_mo->is_loaded( 'unittest' ) );

		$this->assertFalse( $ginger_mo->translate( "string that doesn't exist", '', 'unittest' ) );
		$this->assertFalse( $ginger_mo->translate( 'original', '', 'textdomain not loaded' ) );

		// From example-simple.mo

		$this->assertSame( 'translation', $ginger_mo->translate( 'original', '', 'unittest' ) );
		$this->assertSame( 'translation with context', $ginger_mo->translate( 'original with context', 'context', 'unittest' ) );

		$this->assertSame( 'translation1', $ginger_mo->translate_plural( array( 'plural0', 'plural1' ), 0, '', 'unittest' ) );
		$this->assertSame( 'translation0', $ginger_mo->translate_plural( array( 'plural0', 'plural1' ), 1, '', 'unittest' ) );
		$this->assertSame( 'translation1', $ginger_mo->translate_plural( array( 'plural0', 'plural1' ), 2, '', 'unittest' ) );

		$this->assertSame( 'translation1 with context', $ginger_mo->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 0, 'context', 'unittest' ) );
		$this->assertSame( 'translation0 with context', $ginger_mo->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 1, 'context', 'unittest' ) );
		$this->assertSame( 'translation1 with context', $ginger_mo->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 2, 'context', 'unittest' ) );

		// From simple.mo.

		$this->assertSame( 'dyado', $ginger_mo->translate( 'baba', '', 'unittest' ) );

		// From plural.mo.

		$this->assertSame( 'oney dragoney', $ginger_mo->translate_plural( array( 'one dragon', '%d dragons' ), 1, '', 'unittest' ), 'Actual translation does not match expected one' );
		$this->assertSame( 'twoey dragoney', $ginger_mo->translate_plural( array( 'one dragon', '%d dragons' ), 2, '', 'unittest' ), 'Actual translation does not match expected one' );
		$this->assertSame( 'twoey dragoney', $ginger_mo->translate_plural( array( 'one dragon', '%d dragons' ), -8, '', 'unittest' ), 'Actual translation does not match expected one' );

		$this->assertTrue( $ginger_mo->unload( 'unittest', DIR_TESTDATA . '/ginger-mo/simple.mo' ) );

		$this->assertFalse( $ginger_mo->translate( 'baba', '', 'unittest' ) );
	}

	/**
	 * @covers ::set_locale
	 * @covers ::get_locale
	 * @covers ::load
	 * @covers ::unload
	 * @covers ::is_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 *
	 * @return void
	 */
	public function test_load_multiple_locales() {
		$ginger_mo = new Ginger_MO();

		$this->assertSame( 'en_US', $ginger_mo->get_locale() );

		$ginger_mo->set_locale( 'de_DE' );

		$this->assertSame( 'de_DE', $ginger_mo->get_locale() );

		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'unittest' ) );
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/simple.mo', 'unittest', 'es_ES' ) );
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/plural.mo', 'unittest', 'en_US' ) );

		$this->assertTrue( $ginger_mo->is_loaded( 'unittest' ) );

		// From example-simple.mo

		$this->assertSame( 'translation', $ginger_mo->translate( 'original', '', 'unittest' ), 'String should be translated in de_DE' );
		$this->assertFalse( $ginger_mo->translate( 'original', '', 'unittest', 'es_ES' ), 'String should not be translated in es_ES' );
		$this->assertFalse( $ginger_mo->translate( 'original', '', 'unittest', 'en_US' ), 'String should not be translated in en_US' );

		// From simple.mo.

		$this->assertFalse( $ginger_mo->translate( 'baba', '', 'unittest' ), 'String should not be translated in de_DE' );
		$this->assertSame( 'dyado', $ginger_mo->translate( 'baba', '', 'unittest', 'es_ES' ), 'String should be translated in es_ES' );
		$this->assertFalse( $ginger_mo->translate( 'baba', '', 'unittest', 'en_US' ), 'String should not be translated in en_US' );

		$this->assertTrue( $ginger_mo->unload( 'unittest', DIR_TESTDATA . '/ginger-mo/plural.mo', 'de_DE' ) );

		$this->assertSame( 'oney dragoney', $ginger_mo->translate_plural( array( 'one dragon', '%d dragons' ), 1, '', 'unittest', 'en_US' ), 'String should be translated in en_US' );

		$this->assertTrue( $ginger_mo->unload( 'unittest', DIR_TESTDATA . '/ginger-mo/plural.mo', 'en_US' ) );

		$this->assertFalse( $ginger_mo->translate_plural( array( 'one dragon', '%d dragons' ), 1, '', 'unittest', 'en_US' ), 'String should not be translated in en_US' );
	}

	/**
	 * @covers ::load
	 * @covers ::locate_translation
	 *
	 * @return void
	 */
	public function test_load_with_default_textdomain() {
		$ginger_mo = new Ginger_MO();
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo' ) );
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo' ) );
		$this->assertFalse( $ginger_mo->is_loaded( 'unittest' ) );
		$this->assertSame( 'translation', $ginger_mo->translate( 'original' ) );
	}

	/**
	 * @covers ::load
	 *
	 * @return void
	 */
	public function test_load_same_file_twice() {
		$ginger_mo = new Ginger_MO();
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'unittest' ) );
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'unittest' ) );

		$this->assertTrue( $ginger_mo->is_loaded( 'unittest' ) );
	}

	/**
	 * @covers ::load
	 *
	 * @return void
	 */
	public function test_load_file_is_already_loaded_for_different_textdomain() {
		$ginger_mo = new Ginger_MO();
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'foo' ) );
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'bar' ) );

		$this->assertTrue( $ginger_mo->is_loaded( 'foo' ) );
		$this->assertTrue( $ginger_mo->is_loaded( 'bar' ) );
	}

	/**
	 * @covers ::load
	 * @covers ::unload
	 * @covers ::is_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 * @covers ::locate_translation
	 * @covers ::get_files
	 * @covers Ginger_MO_Translation_File::get_plural_form
	 * @covers Ginger_MO_Translation_File::make_plural_form_function
	 *
	 * @return void
	 */
	public function test_load_no_plurals() {
		$ginger_mo = new Ginger_MO();
		$this->assertTrue( $ginger_mo->load( DIR_TESTDATA . '/ginger-mo/fa_IR.mo', 'unittest' ) );

		$this->assertTrue( $ginger_mo->is_loaded( 'unittest' ) );

		$this->assertFalse( $ginger_mo->translate( "string that doesn't exist", '', 'unittest' ) );

		$this->assertSame( 'رونوشت‌ها فعال نشدند.', $ginger_mo->translate( 'Revisions not enabled.', '', 'unittest' ) );
		$this->assertSame( 'افزودن جدید', $ginger_mo->translate( 'Add New', 'file', 'unittest' ) );

		$this->assertSame( '%s دیدگاه', $ginger_mo->translate_plural( array( '%s comment', '%s comments' ), 0, '', 'unittest' ) );
		$this->assertSame( '%s دیدگاه', $ginger_mo->translate_plural( array( '%s comment', '%s comments' ), 1, '', 'unittest' ) );
		$this->assertSame( '%s دیدگاه', $ginger_mo->translate_plural( array( '%s comment', '%s comments' ), 2, '', 'unittest' ) );
	}

	/**
	 * @covers ::get_headers
	 *
	 * @return void
	 */
	public function test_get_headers_no_loaded_translations() {
		$ginger_mo = new Ginger_MO();
		$headers   = $ginger_mo->get_headers();
		$this->assertEmpty( $headers );
	}

	/**
	 * @covers ::get_headers
	 *
	 * @return void
	 */
	public function test_get_headers_with_default_textdomain() {
		$ginger_mo = new Ginger_MO();
		$ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo' );
		$headers = $ginger_mo->get_headers();
		$this->assertSame(
			array(
				'Po-Revision-Date' => '2016-01-05 18:45:32+1000',
			),
			$headers
		);
	}

	/**
	 * @covers ::get_headers
	 *
	 * @return void
	 */
	public function test_get_headers_no_loaded_translations_for_domain() {
		$ginger_mo = new Ginger_MO();
		$ginger_mo->load( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'foo' );
		$headers = $ginger_mo->get_headers( 'bar' );
		$this->assertEmpty( $headers );
	}


	/**
	 * @covers ::get_entries
	 *
	 * @return void
	 */
	public function test_get_entries_no_loaded_translations() {
		$ginger_mo = new Ginger_MO();
		$headers   = $ginger_mo->get_entries();
		$this->assertEmpty( $headers );
	}

	/**
	 * @covers ::get_entries
	 *
	 * @return void
	 */
	public function test_get_entries_with_default_textdomain() {
		$ginger_mo = new Ginger_MO();
		$ginger_mo->load( DIR_TESTDATA . '/ginger-mo/simple.mo' );
		$headers = $ginger_mo->get_entries();
		$this->assertSame(
			array(
				'baba'       => 'dyado',
				"kuku\nruku" => 'yes',
			),
			$headers
		);
	}

	/**
	 * @covers ::get_entries
	 *
	 * @return void
	 */
	public function test_get_entries_no_loaded_translations_for_domain() {
		$ginger_mo = new Ginger_MO();
		$ginger_mo->load( DIR_TESTDATA . '/ginger-mo/simple.mo', 'foo' );
		$headers = $ginger_mo->get_entries( 'bar' );
		$this->assertEmpty( $headers );
	}

	/**
	 * @dataProvider data_export_matrix
	 *
	 * @param string $source_file
	 * @param string $destination_format
	 * @return void
	 *
	 * @phpstan-param 'mo'|'php' $destination_format
	 */
	public function test_convert_format( string $source_file, string $destination_format ) {
		$destination_file = $this->temp_filename();

		$this->assertNotFalse( $destination_file );

		$source = Ginger_MO_Translation_File::create( $source_file );

		$this->assertInstanceOf( Ginger_MO_Translation_File::class, $source );

		$contents = Ginger_MO_Translation_File::transform( $source_file, $destination_format );

		$this->assertNotFalse( $contents );

		file_put_contents( $destination_file, $contents );

		$destination = Ginger_MO_Translation_File::create( $destination_file, $destination_format );

		$this->assertInstanceOf( Ginger_MO_Translation_File::class, $destination );
		$this->assertFalse( $destination->error() );

		$this->assertTrue( filesize( $destination_file ) > 0 );

		$destination_read = Ginger_MO_Translation_File::create( $destination_file, $destination_format );

		$this->assertInstanceOf( Ginger_MO_Translation_File::class, $destination_read );
		$this->assertFalse( $destination_read->error() );

		$source_headers      = $source->headers();
		$destination_headers = $destination_read->headers();

		$this->assertEquals( $source_headers, $destination_headers );

		foreach ( $source->entries() as $original => $translation ) {
			// Verify the translation is in the destination file
			if ( false !== strpos( $original, "\0" ) ) {
				// Plurals:
				$new_translation = $destination_read->translate( $original );

				$this->assertSame( $translation, $new_translation );

			} else {
				// Single
				$new_translation = $destination_read->translate( $original );

				$this->assertSame( $translation, $new_translation );
			}
		}
	}

	/**
	 * @return array<array{0:string, 1: 'mo'|'php'}>
	 */
	public function data_export_matrix(): array {
		$formats = array( 'mo', 'php' );

		$matrix = array();

		foreach ( $formats as $input_format ) {
			foreach ( $formats as $output_format ) {
				$matrix[ "$input_format to $output_format" ] = array( DIR_TESTDATA . '/ginger-mo/example-simple.' . $input_format, $output_format );
			}
		}

		return $matrix;
	}

	/**
	 * @covers Ginger_MO_Translation_File::transform
	 *
	 * @return void
	 */
	public function test_convert_format_invalid_source() {
		$this->assertFalse( Ginger_MO_Translation_File::transform( 'this-file-does-not-exist', 'invalid' ) );
		$this->assertFalse( Ginger_MO_Translation_File::transform( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'invalid' ) );
		$this->assertNotFalse( Ginger_MO_Translation_File::transform( DIR_TESTDATA . '/ginger-mo/example-simple.mo', 'php' ) );
	}
}
