<?php

/**
 * @coversDefaultClass WP_Translation_Controller
 * @group l10n
 * @group i18n
 */
class WP_Translation_Controller_Convert_Tests extends WP_UnitTestCase {
	/**
	 * @covers ::instance
	 */
	public function test_get_instance() {
		$instance  = WP_Translation_Controller::get_instance();
		$instance2 = WP_Translation_Controller::get_instance();

		$this->assertSame( $instance, $instance2 );
	}

	public function test_no_files_loaded_returns_false() {
		$instance = new WP_Translation_Controller();
		$this->assertFalse( $instance->translate( 'singular' ) );
		$this->assertFalse( $instance->translate_plural( array( 'plural0', 'plural1' ), 1 ) );
	}

	/**
	 * @covers ::unload_textdomain
	 */
	public function test_unload_not_loaded() {
		$instance = new WP_Translation_Controller();
		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest' ) );
		$this->assertFalse( $instance->unload_textdomain( 'unittest' ) );
	}

	/**
	 * @covers ::load
	 * @covers ::unload_textdomain
	 * @covers ::is_textdomain_loaded
	 * @covers ::translate
	 * @covers ::locate_translation
	 * @covers ::get_files
	 */
	public function test_unload_entire_textdomain() {
		$instance = new WP_Translation_Controller();
		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest' ) );
		$this->assertTrue( $instance->load_file( DIR_TESTDATA . '/l10n/example-simple.php', 'unittest' ) );
		$this->assertTrue( $instance->is_textdomain_loaded( 'unittest' ) );

		$this->assertSame( 'translation', $instance->translate( 'original', '', 'unittest' ) );

		$this->assertTrue( $instance->unload_textdomain( 'unittest' ) );
		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest' ) );
		$this->assertFalse( $instance->translate( 'original', '', 'unittest' ) );
	}

	/**
	 * @covers ::unload_file
	 * @covers WP_Translation_File::get_file
	 */
	public function test_unload_file_is_not_actually_loaded() {
		$controller = new WP_Translation_Controller();
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'unittest' ) );
		$this->assertFalse( $controller->unload_file( DIR_TESTDATA . '/l10n/simple.mo', 'unittest' ) );

		$this->assertTrue( $controller->is_textdomain_loaded( 'unittest' ) );
		$this->assertSame( 'translation', $controller->translate( 'original', '', 'unittest' ) );
	}

	/**
	 * @covers ::unload_textdomain
	 * @covers ::is_textdomain_loaded
	 */
	public function test_unload_specific_locale() {
		$instance = new WP_Translation_Controller();
		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest' ) );
		$this->assertTrue( $instance->load_file( DIR_TESTDATA . '/l10n/example-simple.php', 'unittest' ) );
		$this->assertTrue( $instance->is_textdomain_loaded( 'unittest' ) );

		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest', 'es_ES' ) );
		$this->assertTrue( $instance->load_file( DIR_TESTDATA . '/l10n/example-simple.php', 'unittest', 'es_ES' ) );
		$this->assertTrue( $instance->is_textdomain_loaded( 'unittest', 'es_ES' ) );

		$this->assertSame( 'translation', $instance->translate( 'original', '', 'unittest' ) );
		$this->assertSame( 'translation', $instance->translate( 'original', '', 'unittest', 'es_ES' ) );

		$this->assertTrue( $instance->unload_textdomain( 'unittest', $instance->get_locale() ) );
		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest' ) );
		$this->assertFalse( $instance->translate( 'original', '', 'unittest' ) );

		$this->assertTrue( $instance->is_textdomain_loaded( 'unittest', 'es_ES' ) );
		$this->assertTrue( $instance->unload_textdomain( 'unittest', 'es_ES' ) );
		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest', 'es_ES' ) );
		$this->assertFalse( $instance->translate( 'original', '', 'unittest', 'es_ES' ) );
	}

	/**
	 * @dataProvider data_invalid_files
	 *
	 * @param string $type
	 * @param string $file_contents
	 * @param string|bool $expected_error
	 */
	public function test_invalid_files( string $type, string $file_contents, $expected_error = null ) {
		$file = $this->temp_filename();

		$this->assertNotFalse( $file );

		file_put_contents( $file, $file_contents );

		$instance = WP_Translation_File::create( $file, $type );

		$this->assertInstanceOf( WP_Translation_File::class, $instance );

		// Not an error condition until it attempts to parse the file.
		$this->assertNull( $instance->error() );

		// Trigger parsing.
		$instance->headers();

		$this->assertNotNull( $instance->error() );

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
			array( 'mo', '', 'Invalid data' ),
			array( 'mo', 'Random data in a file long enough to be a real header', 'Magic marker does not exist' ),
			array( 'mo', pack( 'V*', 0x950412de ), 'Invalid data' ),
			array( 'mo', pack( 'V*', 0x950412de ) . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'Unsupported revision' ),
			array( 'mo', pack( 'V*', 0x950412de, 0x0 ) . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'Invalid data' ),
		);
	}

	/**
	 * @covers WP_Translation_Controller::load
	 * @covers WP_Translation_Controller::is_textdomain_loaded
	 */
	public function test_load_non_existent_file() {
		$instance = new WP_Translation_Controller();

		$this->assertFalse( $instance->load_file( DIR_TESTDATA . '/l10n/file-that-doesnt-exist.mo', 'unittest' ) );
		$this->assertFalse( $instance->is_textdomain_loaded( 'unittest' ) );
	}

	/**
	 * @covers WP_Translation_File::create
	 */
	public function test_create_non_existent_file() {
		$this->assertFalse( WP_Translation_File::create( 'this-file-does-not-exist' ) );
	}

	/**
	 * @covers WP_Translation_File::create
	 */
	public function test_create_invalid_filetype() {
		$file = $this->temp_filename();
		$this->assertNotFalse( $file );
		file_put_contents( $file, '' );
		$this->assertFalse( WP_Translation_File::create( $file, 'invalid' ) );
	}

	/**
	 * @covers ::load
	 * @covers ::is_textdomain_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 * @covers ::locate_translation
	 * @covers ::get_files
	 * @covers WP_Translation_File::translate
	 *
	 * @dataProvider data_simple_example_files
	 *
	 * @param string $file
	 */
	public function test_simple_translation_files( string $file ) {
		$controller = new WP_Translation_Controller();
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/' . $file, 'unittest' ) );

		$this->assertTrue( $controller->is_textdomain_loaded( 'unittest' ) );
		$this->assertFalse( $controller->is_textdomain_loaded( 'textdomain not loaded' ) );

		$this->assertFalse( $controller->translate( "string that doesn't exist", '', 'unittest' ) );
		$this->assertFalse( $controller->translate( 'original', '', 'textdomain not loaded' ) );

		$this->assertSame( 'translation', $controller->translate( 'original', '', 'unittest' ) );
		$this->assertSame( 'translation with context', $controller->translate( 'original with context', 'context', 'unittest' ) );

		$this->assertSame( 'translation1', $controller->translate_plural( array( 'plural0', 'plural1' ), 0, '', 'unittest' ) );
		$this->assertSame( 'translation0', $controller->translate_plural( array( 'plural0', 'plural1' ), 1, '', 'unittest' ) );
		$this->assertSame( 'translation1', $controller->translate_plural( array( 'plural0', 'plural1' ), 2, '', 'unittest' ) );

		$this->assertSame( 'translation1 with context', $controller->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 0, 'context', 'unittest' ) );
		$this->assertSame( 'translation0 with context', $controller->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 1, 'context', 'unittest' ) );
		$this->assertSame( 'translation1 with context', $controller->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 2, 'context', 'unittest' ) );

		$this->assertSame( 'Produkt', $controller->translate( 'Product', '', 'unittest' ) );
		$this->assertSame( 'Produkt', $controller->translate_plural( array( 'Product', 'Products' ), 1, '', 'unittest' ) );
		$this->assertSame( 'Produkte', $controller->translate_plural( array( 'Product', 'Products' ), 2, '', 'unittest' ) );
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
	 * @covers ::unload_file
	 * @covers ::is_textdomain_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 * @covers ::locate_translation
	 * @covers ::get_files
	 * @covers WP_Translation_File::get_plural_form
	 * @covers WP_Translation_File::make_plural_form_function
	 */
	public function test_load_multiple_files() {
		$controller = new WP_Translation_Controller();
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'unittest' ) );
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/simple.mo', 'unittest' ) );
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/plural.mo', 'unittest' ) );

		$this->assertTrue( $controller->is_textdomain_loaded( 'unittest' ) );

		$this->assertFalse( $controller->translate( "string that doesn't exist", '', 'unittest' ) );
		$this->assertFalse( $controller->translate( 'original', '', 'textdomain not loaded' ) );

		// From example-simple.mo

		$this->assertSame( 'translation', $controller->translate( 'original', '', 'unittest' ) );
		$this->assertSame( 'translation with context', $controller->translate( 'original with context', 'context', 'unittest' ) );

		$this->assertSame( 'translation1', $controller->translate_plural( array( 'plural0', 'plural1' ), 0, '', 'unittest' ) );
		$this->assertSame( 'translation0', $controller->translate_plural( array( 'plural0', 'plural1' ), 1, '', 'unittest' ) );
		$this->assertSame( 'translation1', $controller->translate_plural( array( 'plural0', 'plural1' ), 2, '', 'unittest' ) );

		$this->assertSame( 'translation1 with context', $controller->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 0, 'context', 'unittest' ) );
		$this->assertSame( 'translation0 with context', $controller->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 1, 'context', 'unittest' ) );
		$this->assertSame( 'translation1 with context', $controller->translate_plural( array( 'plural0 with context', 'plural1 with context' ), 2, 'context', 'unittest' ) );

		// From simple.mo.

		$this->assertSame( 'dyado', $controller->translate( 'baba', '', 'unittest' ) );

		// From plural.mo.

		$this->assertSame( 'oney dragoney', $controller->translate_plural( array( 'one dragon', '%d dragons' ), 1, '', 'unittest' ), 'Actual translation does not match expected one' );
		$this->assertSame( 'twoey dragoney', $controller->translate_plural( array( 'one dragon', '%d dragons' ), 2, '', 'unittest' ), 'Actual translation does not match expected one' );
		$this->assertSame( 'twoey dragoney', $controller->translate_plural( array( 'one dragon', '%d dragons' ), -8, '', 'unittest' ), 'Actual translation does not match expected one' );

		$this->assertTrue( $controller->unload_file( DIR_TESTDATA . '/l10n/simple.mo', 'unittest' ) );

		$this->assertFalse( $controller->translate( 'baba', '', 'unittest' ) );
	}

	/**
	 * @covers ::set_locale
	 * @covers ::get_locale
	 * @covers ::load
	 * @covers ::unload_file
	 * @covers ::is_textdomain_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 */
	public function test_load_multiple_locales() {
		$controller = new WP_Translation_Controller();

		$this->assertSame( 'en_US', $controller->get_locale() );

		$controller->set_locale( 'de_DE' );

		$this->assertSame( 'de_DE', $controller->get_locale() );

		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'unittest' ) );
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/simple.mo', 'unittest', 'es_ES' ) );
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/plural.mo', 'unittest', 'en_US' ) );

		$this->assertTrue( $controller->is_textdomain_loaded( 'unittest' ) );

		// From example-simple.mo

		$this->assertSame( 'translation', $controller->translate( 'original', '', 'unittest' ), 'String should be translated in de_DE' );
		$this->assertFalse( $controller->translate( 'original', '', 'unittest', 'es_ES' ), 'String should not be translated in es_ES' );
		$this->assertFalse( $controller->translate( 'original', '', 'unittest', 'en_US' ), 'String should not be translated in en_US' );

		// From simple.mo.

		$this->assertFalse( $controller->translate( 'baba', '', 'unittest' ), 'String should not be translated in de_DE' );
		$this->assertSame( 'dyado', $controller->translate( 'baba', '', 'unittest', 'es_ES' ), 'String should be translated in es_ES' );
		$this->assertFalse( $controller->translate( 'baba', '', 'unittest', 'en_US' ), 'String should not be translated in en_US' );

		$this->assertTrue( $controller->unload_file( DIR_TESTDATA . '/l10n/plural.mo', 'unittest', 'de_DE' ) );

		$this->assertSame( 'oney dragoney', $controller->translate_plural( array( 'one dragon', '%d dragons' ), 1, '', 'unittest', 'en_US' ), 'String should be translated in en_US' );

		$this->assertTrue( $controller->unload_file( DIR_TESTDATA . '/l10n/plural.mo', 'unittest', 'en_US' ) );

		$this->assertFalse( $controller->translate_plural( array( 'one dragon', '%d dragons' ), 1, '', 'unittest', 'en_US' ), 'String should not be translated in en_US' );
	}

	/**
	 * @covers ::unload_textdomain
	 */
	public function test_unload_with_multiple_locales() {
		$ginger_mo = new WP_Translation_Controller();

		$ginger_mo->set_locale( 'de_DE' );

		$this->assertSame( 'de_DE', $ginger_mo->get_locale() );
		$this->assertTrue( $ginger_mo->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'unittest' ) );
		$ginger_mo->set_locale( 'es_ES' );
		$this->assertTrue( $ginger_mo->load_file( DIR_TESTDATA . '/l10n/simple.mo', 'unittest' ) );
		$ginger_mo->set_locale( 'pl_PL' );
		$this->assertTrue( $ginger_mo->load_file( DIR_TESTDATA . '/l10n/plural.mo', 'unittest' ) );
		$this->assertSame( 'pl_PL', $ginger_mo->get_locale() );

		$this->assertTrue( $ginger_mo->is_textdomain_loaded( 'unittest' ) );

		$ginger_mo->set_locale( 'en_US' );
		$this->assertSame( 'en_US', $ginger_mo->get_locale() );

		$this->assertFalse( $ginger_mo->is_textdomain_loaded( 'unittest' ) );
		$this->assertTrue( $ginger_mo->is_textdomain_loaded( 'unittest', 'pl_PL' ) );
		$this->assertTrue( $ginger_mo->is_textdomain_loaded( 'unittest', 'es_ES' ) );
		$this->assertTrue( $ginger_mo->is_textdomain_loaded( 'unittest', 'de_DE' ) );

		$this->assertTrue( $ginger_mo->unload_textdomain( 'unittest' ) );

		$this->assertFalse( $ginger_mo->is_textdomain_loaded( 'unittest' ) );
		$this->assertFalse( $ginger_mo->is_textdomain_loaded( 'unittest', 'pl_PL' ) );
		$this->assertFalse( $ginger_mo->is_textdomain_loaded( 'unittest', 'es_ES' ) );
		$this->assertFalse( $ginger_mo->is_textdomain_loaded( 'unittest', 'de_DE' ) );
	}

	/**
	 * @covers ::load
	 * @covers ::locate_translation
	 */
	public function test_load_with_default_textdomain() {
		$controller = new WP_Translation_Controller();
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo' ) );
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo' ) );
		$this->assertFalse( $controller->is_textdomain_loaded( 'unittest' ) );
		$this->assertSame( 'translation', $controller->translate( 'original' ) );
	}

	/**
	 * @covers ::load
	 */
	public function test_load_same_file_twice() {
		$controller = new WP_Translation_Controller();
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'unittest' ) );
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'unittest' ) );

		$this->assertTrue( $controller->is_textdomain_loaded( 'unittest' ) );
	}

	/**
	 * @covers ::load
	 */
	public function test_load_file_is_already_loaded_for_different_textdomain() {
		$controller = new WP_Translation_Controller();
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'foo' ) );
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'bar' ) );

		$this->assertTrue( $controller->is_textdomain_loaded( 'foo' ) );
		$this->assertTrue( $controller->is_textdomain_loaded( 'bar' ) );
	}

	/**
	 * @covers ::load
	 * @covers ::is_textdomain_loaded
	 * @covers ::translate
	 * @covers ::translate_plural
	 * @covers ::locate_translation
	 * @covers ::get_files
	 * @covers WP_Translation_File::get_plural_form
	 * @covers WP_Translation_File::make_plural_form_function
	 */
	public function test_load_no_plurals() {
		$controller = new WP_Translation_Controller();
		$this->assertTrue( $controller->load_file( DIR_TESTDATA . '/l10n/fa_IR.mo', 'unittest' ) );

		$this->assertTrue( $controller->is_textdomain_loaded( 'unittest' ) );

		$this->assertFalse( $controller->translate( "string that doesn't exist", '', 'unittest' ) );

		$this->assertSame( 'رونوشت‌ها فعال نشدند.', $controller->translate( 'Revisions not enabled.', '', 'unittest' ) );
		$this->assertSame( 'افزودن جدید', $controller->translate( 'Add New', 'file', 'unittest' ) );

		$this->assertSame( '%s دیدگاه', $controller->translate_plural( array( '%s comment', '%s comments' ), 0, '', 'unittest' ) );
		$this->assertSame( '%s دیدگاه', $controller->translate_plural( array( '%s comment', '%s comments' ), 1, '', 'unittest' ) );
		$this->assertSame( '%s دیدگاه', $controller->translate_plural( array( '%s comment', '%s comments' ), 2, '', 'unittest' ) );
	}

	/**
	 * @covers ::get_headers
	 */
	public function test_get_headers_no_loaded_translations() {
		$controller = new WP_Translation_Controller();
		$headers    = $controller->get_headers();
		$this->assertEmpty( $headers );
	}

	/**
	 * @covers ::get_headers
	 */
	public function test_get_headers_with_default_textdomain() {
		$controller = new WP_Translation_Controller();
		$controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo' );
		$headers = $controller->get_headers();
		$this->assertSame(
			array(
				'Po-Revision-Date' => '2016-01-05 18:45:32+1000',
			),
			$headers
		);
	}

	/**
	 * @covers ::get_headers
	 */
	public function test_get_headers_no_loaded_translations_for_domain() {
		$controller = new WP_Translation_Controller();
		$controller->load_file( DIR_TESTDATA . '/l10n/example-simple.mo', 'foo' );
		$headers = $controller->get_headers( 'bar' );
		$this->assertEmpty( $headers );
	}


	/**
	 * @covers ::get_entries
	 */
	public function test_get_entries_no_loaded_translations() {
		$controller = new WP_Translation_Controller();
		$headers    = $controller->get_entries();
		$this->assertEmpty( $headers );
	}

	/**
	 * @covers ::get_entries
	 */
	public function test_get_entries_with_default_textdomain() {
		$controller = new WP_Translation_Controller();
		$controller->load_file( DIR_TESTDATA . '/l10n/simple.mo' );
		$headers = $controller->get_entries();
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
	 */
	public function test_get_entries_no_loaded_translations_for_domain() {
		$controller = new WP_Translation_Controller();
		$controller->load_file( DIR_TESTDATA . '/l10n/simple.mo', 'foo' );
		$headers = $controller->get_entries( 'bar' );
		$this->assertEmpty( $headers );
	}

	/**
	 * @dataProvider data_export_matrix
	 *
	 * @param string $source_file
	 * @param string $destination_format
	 */
	public function test_convert_format( string $source_file, string $destination_format ) {
		$destination_file = $this->temp_filename();

		$this->assertNotFalse( $destination_file );

		$source = WP_Translation_File::create( $source_file );

		$this->assertInstanceOf( WP_Translation_File::class, $source );

		$contents = WP_Translation_File::transform( $source_file, $destination_format );

		$this->assertNotFalse( $contents );

		file_put_contents( $destination_file, $contents );

		$destination = WP_Translation_File::create( $destination_file, $destination_format );

		$this->assertInstanceOf( WP_Translation_File::class, $destination );
		$this->assertNull( $destination->error() );

		$this->assertGreaterThan( 0, filesize( $destination_file ) );

		$destination_read = WP_Translation_File::create( $destination_file, $destination_format );

		$this->assertInstanceOf( WP_Translation_File::class, $destination_read );
		$this->assertNull( $destination_read->error() );

		$source_headers      = $source->headers();
		$destination_headers = $destination_read->headers();

		$this->assertEquals( $source_headers, $destination_headers );

		foreach ( $source->entries() as $original => $translation ) {
			// Verify the translation is in the destination file
			$new_translation = $destination_read->translate( $original );
			$this->assertSame( $translation, $new_translation );
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
				$matrix[ "$input_format to $output_format" ] = array( DIR_TESTDATA . '/l10n/example-simple.' . $input_format, $output_format );
			}
		}

		return $matrix;
	}

	/**
	 * @covers WP_Translation_File::transform
	 */
	public function test_convert_format_invalid_source() {
		$this->assertFalse( WP_Translation_File::transform( 'this-file-does-not-exist', 'invalid' ) );
		$this->assertFalse( WP_Translation_File::transform( DIR_TESTDATA . '/l10n/example-simple.mo', 'invalid' ) );
		$this->assertNotFalse( WP_Translation_File::transform( DIR_TESTDATA . '/l10n/example-simple.mo', 'php' ) );
	}
}
