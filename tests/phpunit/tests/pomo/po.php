<?php

/**
 * @group pomo
 */
class Tests_POMO_PO extends WP_UnitTestCase {

	/**
	 * Mail content.
	 *
	 * @var string
	 */
	const MAIL_TEXT = 'Your new WordPress blog has been successfully set up at:

%1$s

You can log in to the administrator account with the following information:

Username: %2$s
Password: %3$s

We hope you enjoy your new blog. Thanks!

--The WordPress Team
http://wordpress.org/
';

	/**
	 * Mail content for translation readiness.
	 *
	 * @var string
	 */
	const PO_MAIL = '""
"Your new WordPress blog has been successfully set up at:\n"
"\n"
"%1$s\n"
"\n"
"You can log in to the administrator account with the following information:\n"
"\n"
"Username: %2$s\n"
"Password: %3$s\n"
"\n"
"We hope you enjoy your new blog. Thanks!\n"
"\n"
"--The WordPress Team\n"
"http://wordpress.org/\n"';

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-includes/pomo/po.php';
	}

	public function test_prepend_each_line() {
		$po = new PO();
		$this->assertSame( 'baba_', $po->prepend_each_line( '', 'baba_' ) );
		$this->assertSame( 'baba_dyado', $po->prepend_each_line( 'dyado', 'baba_' ) );
		$this->assertSame( "# baba\n# dyado\n# \n", $po->prepend_each_line( "baba\ndyado\n\n", '# ' ) );
	}

	public function test_poify() {
		$po = new PO();
		// Simple.
		$this->assertSame( '"baba"', $po->poify( 'baba' ) );
		// Long word.
		$long_word    = str_repeat( 'a', 90 );
		$po_long_word = "\"$long_word\"";
		$this->assertSame( $po_long_word, $po->poify( $long_word ) );
		// Tab.
		$this->assertSame( '"ba\tba"', $po->poify( "ba\tba" ) );
		// Do not add leading empty string of one-line string ending on a newline.
		$this->assertSame( '"\\\\a\\\\n\\n"', $po->poify( "\a\\n\n" ) );
		// Backslash.
		$this->assertSame( '"ba\\\\ba"', $po->poify( 'ba\\ba' ) );
		// Random wordpress.pot string.
		$src = 'Categories can be selectively converted to tags using the <a href="%s">category to tag converter</a>.';
		$this->assertSame( '"Categories can be selectively converted to tags using the <a href=\\"%s\\">category to tag converter</a>."', $po->poify( $src ) );

		$mail = str_replace( "\r\n", "\n", self::MAIL_TEXT );
		$this->assertSameIgnoreEOL( self::PO_MAIL, $po->poify( $mail ) );
	}

	public function test_unpoify() {
		$po = new PO();
		$this->assertSame( 'baba', $po->unpoify( '"baba"' ) );
		$this->assertSame( "baba\ngugu", $po->unpoify( '"baba\n"' . "\t\t\t\n" . '"gugu"' ) );

		$long_word    = str_repeat( 'a', 90 );
		$po_long_word = "\"$long_word\"";
		$this->assertSame( $long_word, $po->unpoify( $po_long_word ) );
		$this->assertSame( '\\t\\n', $po->unpoify( '"\\\\t\\\\n"' ) );
		// Wordwrapped.
		$this->assertSame( 'babadyado', $po->unpoify( "\"\"\n\"baba\"\n\"dyado\"" ) );

		$mail = str_replace( "\r\n", "\n", self::MAIL_TEXT );
		$this->assertSameIgnoreEOL( $mail, $po->unpoify( self::PO_MAIL ) );
	}

	public function test_export_entry() {
		$po    = new PO();
		$entry = new Translation_Entry( array( 'singular' => 'baba' ) );
		$this->assertSame( "msgid \"baba\"\nmsgstr \"\"", $po->export_entry( $entry ) );
		// Plural.
		$entry = new Translation_Entry(
			array(
				'singular' => 'baba',
				'plural'   => 'babas',
			)
		);
		$this->assertSameIgnoreEOL(
			'msgid "baba"
msgid_plural "babas"
msgstr[0] ""
msgstr[1] ""',
			$po->export_entry( $entry )
		);
		$entry = new Translation_Entry(
			array(
				'singular'            => 'baba',
				'translator_comments' => "baba\ndyado",
			)
		);
		$this->assertSameIgnoreEOL(
			'#  baba
#  dyado
msgid "baba"
msgstr ""',
			$po->export_entry( $entry )
		);
		$entry = new Translation_Entry(
			array(
				'singular'           => 'baba',
				'extracted_comments' => 'baba',
			)
		);
		$this->assertSameIgnoreEOL(
			'#. baba
msgid "baba"
msgstr ""',
			$po->export_entry( $entry )
		);
		$entry = new Translation_Entry(
			array(
				'singular'           => 'baba',
				'extracted_comments' => 'baba',
				'references'         => range( 1, 29 ),
			)
		);
		$this->assertSameIgnoreEOL(
			'#. baba
#: 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24 25 26 27 28
#: 29
msgid "baba"
msgstr ""',
			$po->export_entry( $entry )
		);
		$entry = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'translations' => array(),
			)
		);
		$this->assertSame( "msgid \"baba\"\nmsgstr \"\"", $po->export_entry( $entry ) );

		$entry = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'translations' => array( 'куку', 'буку' ),
			)
		);
		$this->assertSame( "msgid \"baba\"\nmsgstr \"куку\"", $po->export_entry( $entry ) );

		$entry = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'plural'       => 'babas',
				'translations' => array( 'кукубуку' ),
			)
		);
		$this->assertSameIgnoreEOL(
			'msgid "baba"
msgid_plural "babas"
msgstr[0] "кукубуку"',
			$po->export_entry( $entry )
		);

		$entry = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'plural'       => 'babas',
				'translations' => array( 'кукубуку', 'кукуруку', 'бабаяга' ),
			)
		);
		$this->assertSameIgnoreEOL(
			'msgid "baba"
msgid_plural "babas"
msgstr[0] "кукубуку"
msgstr[1] "кукуруку"
msgstr[2] "бабаяга"',
			$po->export_entry( $entry )
		);
		// Context.
		$entry = new Translation_Entry(
			array(
				'context'      => 'ctxt',
				'singular'     => 'baba',
				'plural'       => 'babas',
				'translations' => array( 'кукубуку', 'кукуруку', 'бабаяга' ),
				'flags'        => array( 'fuzzy', 'php-format' ),
			)
		);
		$this->assertSameIgnoreEOL(
			'#, fuzzy, php-format
msgctxt "ctxt"
msgid "baba"
msgid_plural "babas"
msgstr[0] "кукубуку"
msgstr[1] "кукуруку"
msgstr[2] "бабаяга"',
			$po->export_entry( $entry )
		);
	}

	public function test_export_entries() {
		$entry  = new Translation_Entry( array( 'singular' => 'baba' ) );
		$entry2 = new Translation_Entry( array( 'singular' => 'dyado' ) );
		$po     = new PO();
		$po->add_entry( $entry );
		$po->add_entry( $entry2 );
		$this->assertSame( "msgid \"baba\"\nmsgstr \"\"\n\nmsgid \"dyado\"\nmsgstr \"\"", $po->export_entries() );
	}

	public function test_export_headers() {
		$po = new PO();
		$po->set_header( 'Project-Id-Version', 'WordPress 2.6-bleeding' );
		$po->set_header( 'POT-Creation-Date', '2008-04-08 18:00+0000' );
		$this->assertSame( "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: WordPress 2.6-bleeding\\n\"\n\"POT-Creation-Date: 2008-04-08 18:00+0000\\n\"", $po->export_headers() );
	}

	public function test_export() {
		$po     = new PO();
		$entry  = new Translation_Entry( array( 'singular' => 'baba' ) );
		$entry2 = new Translation_Entry( array( 'singular' => 'dyado' ) );
		$po->set_header( 'Project-Id-Version', 'WordPress 2.6-bleeding' );
		$po->set_header( 'POT-Creation-Date', '2008-04-08 18:00+0000' );
		$po->add_entry( $entry );
		$po->add_entry( $entry2 );
		$this->assertSame( "msgid \"baba\"\nmsgstr \"\"\n\nmsgid \"dyado\"\nmsgstr \"\"", $po->export( false ) );
		$this->assertSame( "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: WordPress 2.6-bleeding\\n\"\n\"POT-Creation-Date: 2008-04-08 18:00+0000\\n\"\n\nmsgid \"baba\"\nmsgstr \"\"\n\nmsgid \"dyado\"\nmsgstr \"\"", $po->export() );
	}


	public function test_export_to_file() {
		$po     = new PO();
		$entry  = new Translation_Entry( array( 'singular' => 'baba' ) );
		$entry2 = new Translation_Entry( array( 'singular' => 'dyado' ) );
		$po->set_header( 'Project-Id-Version', 'WordPress 2.6-bleeding' );
		$po->set_header( 'POT-Creation-Date', '2008-04-08 18:00+0000' );
		$po->add_entry( $entry );
		$po->add_entry( $entry2 );

		$temp_fn = $this->temp_filename();
		$po->export_to_file( $temp_fn, false );
		$this->assertSame( $po->export( false ), file_get_contents( $temp_fn ) );

		$temp_fn2 = $this->temp_filename();
		$po->export_to_file( $temp_fn2 );
		$this->assertSame( $po->export(), file_get_contents( $temp_fn2 ) );
	}

	public function test_import_from_file() {
		$po  = new PO();
		$res = $po->import_from_file( DIR_TESTDATA . '/pomo/simple.po' );
		$this->assertTrue( $res );

		$this->assertSame(
			array(
				'Project-Id-Version' => 'WordPress 2.6-bleeding',
				'Plural-Forms'       => 'nplurals=2; plural=n != 1;',
			),
			$po->headers
		);

		$simple_entry = new Translation_Entry( array( 'singular' => 'moon' ) );
		$this->assertEquals( $simple_entry, $po->entries[ $simple_entry->key() ] );

		$all_types_entry = new Translation_Entry(
			array(
				'singular'     => 'strut',
				'plural'       => 'struts',
				'context'      => 'brum',
				'translations' => array( 'ztrut0', 'ztrut1', 'ztrut2' ),
			)
		);
		$this->assertEquals( $all_types_entry, $po->entries[ $all_types_entry->key() ] );

		$multiple_line_entry = new Translation_Entry(
			array(
				'singular'     => 'The first thing you need to do is tell Blogger to let WordPress access your account. You will be sent back here after providing authorization.',
				'translations' => array( "baba\ndyadogugu" ),
			)
		);
		$this->assertEquals( $multiple_line_entry, $po->entries[ $multiple_line_entry->key() ] );

		$multiple_line_all_types_entry = new Translation_Entry(
			array(
				'context'      => 'context',
				'singular'     => 'singular',
				'plural'       => 'plural',
				'translations' => array( 'translation0', 'translation1', 'translation2' ),
			)
		);
		$this->assertEquals( $multiple_line_all_types_entry, $po->entries[ $multiple_line_all_types_entry->key() ] );

		$comments_entry = new Translation_Entry(
			array(
				'singular'            => 'a',
				'translator_comments' => "baba\nbrubru",
				'references'          => array( 'wp-admin/x.php:111', 'baba:333', 'baba' ),
				'extracted_comments'  => 'translators: buuu',
				'flags'               => array( 'fuzzy' ),
			)
		);
		$this->assertEquals( $comments_entry, $po->entries[ $comments_entry->key() ] );

		$end_quote_entry = new Translation_Entry( array( 'singular' => 'a"' ) );
		$this->assertEquals( $end_quote_entry, $po->entries[ $end_quote_entry->key() ] );
	}

	public function test_import_from_entry_file_should_give_false() {
		$po = new PO();
		$this->assertFalse( $po->import_from_file( DIR_TESTDATA . '/pomo/empty.po' ) );
	}

	public function test_import_from_file_with_windows_line_endings_should_work_as_with_unix_line_endings() {
		$po = new PO();
		$this->assertTrue( $po->import_from_file( DIR_TESTDATA . '/pomo/windows-line-endings.po' ) );
		$this->assertCount( 1, $po->entries );
	}

	public function test_import_from_file_various_line_endings() {

		$temp_fn = $this->temp_filename();
		foreach ( array(
			"\\r"    => "\r",
			"\\n"    => "\n",
			"\\r\\n" => "\r\n",
		) as $printable_new_line => $newline ) {
			$file  = 'msgid ""' . $newline;
			$file .= 'msgstr ""' . $newline;
			$file .= '"Project-Id-Version: WordPress 6.7\n"' . $newline;
			$file .= '"Plural-Forms: nplurals=2; plural=n != 1;\n"';

			$entries = array();
			for ( $i = 1; $i <= 3; $i++ ) {
				$file .= $newline;
				$file .= $newline;
				$line  = "Entry $i";
				$file .= 'msgid "' . $line . '"' . $newline;
				$file .= 'msgstr ""';
				$entry = new Translation_Entry( array( 'singular' => $line ) );

				$entries[ $entry->key() ] = $entry;
			}

			file_put_contents( $temp_fn, $file );

			$po  = new PO();
			$res = $po->import_from_file( $temp_fn );
			$this->assertTrue( $res );

			$this->assertSame(
				array(
					'Project-Id-Version' => 'WordPress 6.7',
					'Plural-Forms'       => 'nplurals=2; plural=n != 1;',
				),
				$po->headers
			);

			$this->assertEquals( $po->entries, $entries, "Failed for $printable_new_line" );

			$temp_fn2 = $this->temp_filename();
			$po->export_to_file( $temp_fn2 );
			$this->assertSame( str_replace( $newline, "\n", $file ), file_get_contents( $temp_fn2 ) );

			unlink( $temp_fn );
		}
	}


	// TODO: Add tests for bad files.
}
