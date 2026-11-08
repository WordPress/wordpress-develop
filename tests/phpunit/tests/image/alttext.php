<?php

/**
 * Tests for wp_get_image_alttext().
 *
 * @group image
 * @group media
 * @ticket 66221
 *
 * @covers ::wp_get_image_alttext
 */
class Tests_Image_Alttext extends WP_UnitTestCase {

	/**
	 * Temporary files to delete in tear_down().
	 *
	 * @var string[]
	 */
	private $temp_files = array();

	public function tear_down() {
		restore_current_locale();

		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_files = array();

		parent::tear_down();
	}

	/**
	 * Creates a temporary file containing XMP metadata with the given alt text entries.
	 *
	 * @param array<string, string> $alt_entries Associative array where key is xml:lang and value is the alt text.
	 * @return string File path to the temporary file.
	 */
	private function create_temp_xmp_file( array $alt_entries ) {
		$li_tags = '';
		foreach ( $alt_entries as $lang => $text ) {
			$li_tags .= "\t\t\t\t" . '<rdf:li xml:lang="' . esc_attr( $lang ) . '">' . htmlspecialchars( $text, ENT_XML1, 'UTF-8' ) . "</rdf:li>\n";
		}

		$content = "<x:xmpmeta xmlns:x=\"adobe:ns:meta/\">\n"
			. "\t<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\" xmlns:Iptc4xmpCore=\"http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/\">\n"
			. "\t\t<rdf:Description>\n"
			. "\t\t\t<Iptc4xmpCore:AltTextAccessibility>\n"
			. "\t\t\t\t<rdf:Alt>\n"
			. $li_tags
			. "\t\t\t\t</rdf:Alt>\n"
			. "\t\t\t</Iptc4xmpCore:AltTextAccessibility>\n"
			. "\t\t</rdf:Description>\n"
			. "\t</rdf:RDF>\n"
			. '</x:xmpmeta>';

		$file = wp_tempnam( 'alttext-xmp' );
		file_put_contents( $file, $content );
		$this->temp_files[] = $file;

		return $file;
	}

	/**
	 * Asserts that the alt text matches the expected value when the DOM extension is available,
	 * or is empty when the DOM extension is not installed.
	 *
	 * @param string $expected Expected alt text when DOM is available.
	 * @param string $actual   Actual alt text returned by wp_get_image_alttext().
	 * @param string $message  Optional failure message.
	 */
	private function assert_alttext_matches_or_empty_without_dom( $expected, $actual, $message = '' ) {
		if ( ! class_exists( 'DOMDocument', false ) ) {
			$this->assertSame( '', $actual, 'Expected empty string when DOM extension is not available.' );
			return;
		}

		$this->assertSame( $expected, $actual, $message );
	}

	/**
	 * Tests reading alt text with a valid image containing XMP metadata.
	 */
	public function test_wp_get_image_alttext_with_valid_image() {
		$alt = wp_get_image_alttext( DIR_TESTDATA . '/images/IPTC-PhotometadataRef-Std2025.1.jpg' );

		$this->assert_alttext_matches_or_empty_without_dom(
			'This is the Alt Text description to support accessibility in 2025.1',
			$alt
		);
	}

	/**
	 * Tests wp_get_image_alttext() returns empty string when image has no XMP metadata.
	 */
	public function test_wp_get_image_alttext_returns_empty_when_no_xmp_metadata() {
		$alt = wp_get_image_alttext( DIR_TESTDATA . '/images/test-image-upside-down.jpg' );

		$this->assertSame( '', $alt );
	}

	/**
	 * Tests wp_get_image_alttext() returns empty string when XMP has no AltTextAccessibility node.
	 */
	public function test_wp_get_image_alttext_returns_empty_when_no_alttext_node() {
		$file    = wp_tempnam( 'alttext-no-node' );
		$content = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description rdf:about="" /></rdf:RDF></x:xmpmeta>';
		file_put_contents( $file, $content );
		$this->temp_files[] = $file;

		$alt = wp_get_image_alttext( $file );

		$this->assertSame( '', $alt );
	}

	/**
	 * Tests possibility 1: exact match on site locale.
	 */
	public function test_wp_get_image_alttext_exact_locale_match() {
		switch_to_locale( 'de_DE' );

		$file = $this->create_temp_xmp_file(
			array(
				'de_DE'     => 'Deutscher Alt-Text (exakt)',
				'de'        => 'Deutscher Alt-Text (teilweise)',
				'x-default' => 'Default Alt Text',
			)
		);

		$alt = wp_get_image_alttext( $file );

		$this->assert_alttext_matches_or_empty_without_dom( 'Deutscher Alt-Text (exakt)', $alt );
	}

	/**
	 * Tests possibility 2: partial match on site locale (e.g. locale is es_ES and rdf:li has @xml:lang="es").
	 */
	public function test_wp_get_image_alttext_partial_locale_match() {
		switch_to_locale( 'es_ES' );

		$file = $this->create_temp_xmp_file(
			array(
				'es'        => 'Texto alternativo en espanol (parcial)',
				'x-default' => 'Default Alt Text',
			)
		);

		$alt = wp_get_image_alttext( $file );

		$this->assert_alttext_matches_or_empty_without_dom( 'Texto alternativo en espanol (parcial)', $alt );
	}

	/**
	 * Tests possibility 3: fallback to x-default when neither exact nor partial locale matches.
	 */
	public function test_wp_get_image_alttext_x_default_fallback() {
		switch_to_locale( 'ja_JP' );

		$file = $this->create_temp_xmp_file(
			array(
				'de_DE'     => 'Deutscher Alt-Text',
				'x-default' => 'Default Alt Text Fallback',
			)
		);

		$alt = wp_get_image_alttext( $file );

		$this->assert_alttext_matches_or_empty_without_dom( 'Default Alt Text Fallback', $alt );
	}

	/**
	 * Tests that wp_get_image_alttext() returns different alt text based on the site locale.
	 */
	public function test_wp_get_image_alttext_returns_different_alt_text_based_on_site_locale() {
		$file = $this->create_temp_xmp_file(
			array(
				'en_US'     => 'English (US) description',
				'en_GB'     => 'English (UK) description',
				'es_ES'     => 'Descripcion en espanol',
				'de_DE'     => 'Deutsche Beschreibung',
				'x-default' => 'Default description',
			)
		);

		switch_to_locale( 'en_US' );
		$this->assert_alttext_matches_or_empty_without_dom( 'English (US) description', wp_get_image_alttext( $file ) );

		switch_to_locale( 'en_GB' );
		$this->assert_alttext_matches_or_empty_without_dom( 'English (UK) description', wp_get_image_alttext( $file ) );

		switch_to_locale( 'es_ES' );
		$this->assert_alttext_matches_or_empty_without_dom( 'Descripcion en espanol', wp_get_image_alttext( $file ) );

		switch_to_locale( 'de_DE' );
		$this->assert_alttext_matches_or_empty_without_dom( 'Deutsche Beschreibung', wp_get_image_alttext( $file ) );

		// Unmatched locale should fall back to x-default.
		switch_to_locale( 'ja_JP' );
		$this->assert_alttext_matches_or_empty_without_dom( 'Default description', wp_get_image_alttext( $file ) );
	}
}
