<?php

/**
 * Tests for the Gettext_Translations::make_headers() method.
 *
 * @group pomo
 *
 * @covers Gettext_Translations::make_headers
 */
class Tests_POMO_GettextTranslationsMakeHeaders extends WP_UnitTestCase {

	/**
	 * Tests that the headers are parsed.
	 *
	 * @ticket 66511
	 *
	 * @dataProvider data_translations
	 *
	 * @param string $translation Translation with the headers.
	 * @param array  $expected    Expected headers.
	 */
	public function test_should_parse_the_headers( $translation, $expected ) {
		$translations = new Gettext_Translations();

		$this->assertSame( $expected, $translations->make_headers( $translation ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_translations() {
		return array(
			'one header'                      => array( 'Project-Id-Version: WordPress', array( 'Project-Id-Version' => 'WordPress' ) ),
			'real line breaks'                => array(
				"Project-Id-Version: WordPress\nLanguage: de_DE\n",
				array(
					'Project-Id-Version' => 'WordPress',
					'Language'           => 'de_DE',
				),
			),
			'escaped line breaks'             => array(
				'Project-Id-Version: WordPress\nLanguage: de_DE\n',
				array(
					'Project-Id-Version' => 'WordPress',
					'Language'           => 'de_DE',
				),
			),
			'value with colons'               => array( 'POT-Creation-Date: 2026-11-06 08:30+0000', array( 'POT-Creation-Date' => '2026-11-06 08:30+0000' ) ),
			'spaces around the name'          => array( "  Language  :  de_DE  \n", array( 'Language' => 'de_DE' ) ),
			'empty value'                     => array( 'Report-Msgid-Bugs-To:', array( 'Report-Msgid-Bugs-To' => '' ) ),
			'plural forms'                    => array( 'Plural-Forms: nplurals=2; plural=n != 1;', array( 'Plural-Forms' => 'nplurals=2; plural=n != 1;' ) ),
			'a repeated header'               => array(
				"Language: de_DE\nLanguage: fr_FR",
				array( 'Language' => 'fr_FR' ),
			),
			'lines that are not headers'      => array(
				"text without a colon\nLanguage: de_DE\n\n",
				array( 'Language' => 'de_DE' ),
			),
			'empty string'                    => array( '', array() ),
			'only lines that are not headers' => array( "one\ntwo", array() ),
		);
	}
}
