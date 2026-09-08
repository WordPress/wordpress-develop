<?php

/**
 * @group formatting
 *
 * @covers ::esc_xml
 */
class Tests_Formatting_EscXml extends WP_UnitTestCase {
	/**
	 * Test basic escaping
	 *
	 * @dataProvider data_esc_xml_basics
	 *
	 * @param string $source   The source string to be escaped.
	 * @param string $expected The expected escaped value of `$source`.
	 */
	public function test_esc_xml_basics( $source, $expected ) {
		$actual = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_esc_xml_basics()`.
	 *
	 * @return array {
	 *     @type string $source   The source string to be escaped.
	 *     @type string $expected The expected escaped value of `$source`.
	 * }
	 */
	public function data_esc_xml_basics() {
		return array(
			// Simple string.
			array(
				'The quick brown fox.',
				'The quick brown fox.',
			),
			// URL with &.
			array(
				'http://localhost/trunk/wp-login.php?action=logout&_wpnonce=cd57d75985',
				'http://localhost/trunk/wp-login.php?action=logout&amp;_wpnonce=cd57d75985',
			),
			// SQL query w/ single quotes.
			array(
				"SELECT meta_key, meta_value FROM wp_trunk_sitemeta WHERE meta_key IN ('site_name', 'siteurl', 'active_sitewide_plugins', '_site_transient_timeout_theme_roots', '_site_transient_theme_roots', 'site_admins', 'can_compress_scripts', 'global_terms_enabled') AND site_id = 1",
				'SELECT meta_key, meta_value FROM wp_trunk_sitemeta WHERE meta_key IN (&apos;site_name&apos;, &apos;siteurl&apos;, &apos;active_sitewide_plugins&apos;, &apos;_site_transient_timeout_theme_roots&apos;, &apos;_site_transient_theme_roots&apos;, &apos;site_admins&apos;, &apos;can_compress_scripts&apos;, &apos;global_terms_enabled&apos;) AND site_id = 1',
			),
			// Zero string.
			array(
				'0',
				'0',
			),
		);
	}

	public function test_escapes_ampersands() {
		$source   = 'penn & teller & at&t';
		$expected = 'penn &amp; teller &amp; at&amp;t';
		$actual   = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	public function test_escapes_greater_and_less_than() {
		$source   = 'this > that < that <randomhtml />';
		$expected = 'this &gt; that &lt; that &lt;randomhtml /&gt;';
		$actual   = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	public function test_escapes_html_named_entities() {
		$source   = 'this &amp; is a &hellip; followed by &rsaquo; and more and a &nonexistent; entity';
		$expected = 'this &amp; is a … followed by › and more and a &amp;nonexistent; entity';
		$actual   = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	public function test_ignores_existing_entities() {
		$source = '&#038; &#x00A3; &#x22; &amp;';
		// note that _wp_specialchars() strips leading 0's from numeric character references.
		$expected = '&#038; &#xA3; &#x22; &amp;';
		$actual   = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test that CDATA Sections are not escaped.
	 *
	 * @dataProvider data_ignores_cdata_sections
	 *
	 * @param string $source   The source string to be escaped.
	 * @param string $expected The expected escaped value of `$source`.
	 */
	public function test_ignores_cdata_sections( $source, $expected ) {
		$actual = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_ignores_cdata_sections()`.
	 *
	 * @return array {
	 *     @type string $source   The source string to be escaped.
	 *     @type string $expected The expected escaped value of `$source`.
	 * }
	 */
	public function data_ignores_cdata_sections() {
		return array(
			// basic CDATA Section containing chars that would otherwise be escaped if not in a CDATA Section
			// not to mention the CDATA Section markup itself :-)
			// $source contains embedded newlines to test that the regex that ignores CDATA Sections
			// correctly handles that case.
			array(
				"This is\na<![CDATA[test of\nthe <emergency>]]>\nbroadcast system",
				"This is\na<![CDATA[test of\nthe <emergency>]]>\nbroadcast system",
			),
			// string with chars that should be escaped as well as a CDATA Section that should be not be.
			array(
				'This is &hellip; a <![CDATA[test of the <emergency>]]> broadcast <system />',
				'This is … a <![CDATA[test of the <emergency>]]> broadcast &lt;system /&gt;',
			),
			// Same as above, but with the CDATA Section at the start of the string.
			array(
				'<![CDATA[test of the <emergency>]]> This is &hellip; a broadcast <system />',
				'<![CDATA[test of the <emergency>]]> This is … a broadcast &lt;system /&gt;',
			),
			// Same as above, but with the CDATA Section at the end of the string.
			array(
				'This is &hellip; a broadcast <system /><![CDATA[test of the <emergency>]]>',
				'This is … a broadcast &lt;system /&gt;<![CDATA[test of the <emergency>]]>',
			),
			// Multiple CDATA Sections.
			array(
				'This is &hellip; a <![CDATA[test of the <emergency>]]> &broadcast; <![CDATA[<system />]]>',
				'This is … a <![CDATA[test of the <emergency>]]> &amp;broadcast; <![CDATA[<system />]]>',
			),
			// Ensure that ']]>' that does not mark the end of a CDATA Section is escaped.
			array(
				'<![CDATA[<&]]>]]>',
				'<![CDATA[<&]]>]]&gt;',
			),
		);
	}

	/**
	 * Test that invalid XML control characters are stripped.
	 *
	 * @dataProvider data_strips_invalid_xml_characters
	 *
	 * @param string $source   The source string containing invalid XML characters.
	 * @param string $expected The expected string with invalid characters removed.
	 */
	public function test_strips_invalid_xml_characters( $source, $expected ) {
		update_option( 'blog_charset', 'UTF-8' );
		$actual = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_strips_invalid_xml_characters()`.
	 *
	 * @return array {
	 *     @type string $source   The source string containing invalid XML characters.
	 *     @type string $expected The expected string with invalid characters removed.
	 * }
	 */
	public function data_strips_invalid_xml_characters() {
		return array(
			// Vertical tab (0x0B) - invalid in XML.
			array(
				"This contains a vertical tab\x0Bcharacter",
				'This contains a vertical tabcharacter',
			),
			// File separator (0x1C) - invalid in XML.
			array(
				"File separator\x1Ctest",
				'File separatortest',
			),
			// NULL byte (0x00) - invalid in XML.
			array(
				"Text with\x00null byte",
				'Text withnull byte',
			),
			// Bell character (0x07) - invalid in XML.
			array(
				"Bell\x07character",
				'Bellcharacter',
			),
			// Multiple invalid characters.
			array(
				"Multiple\x00invalid\x0B\x1Ccharacters\x07here",
				'Multipleinvalidcharactershere',
			),
			// Valid control characters should be preserved: tab (0x09), LF (0x0A), CR (0x0D).
			array(
				"Tab\tlinefeed\ncarriage return\rtest",
				"Tab\tlinefeed\ncarriage return\rtest",
			),
			// Mix of valid and invalid.
			array(
				"Valid\ttab but\x0Binvalid vertical tab",
				"Valid\ttab butinvalid vertical tab",
			),
			// Text without invalid characters should remain unchanged.
			array(
				'Normal text with spaces and punctuation!',
				'Normal text with spaces and punctuation!',
			),
			// Unicode characters in valid range should be preserved.
			array(
				'Unicode: café, naïve, 日本語',
				'Unicode: café, naïve, 日本語',
			),
		);
	}

	/**
	 * Test that invalid XML characters within CDATA sections are also stripped.
	 */
	public function test_strips_invalid_xml_characters_outside_cdata() {
		update_option( 'blog_charset', 'UTF-8' );
		$source   = "Text\x0Bwith<![CDATA[valid <content>]]>and\x1Cmore\x00invalid";
		$expected = 'Textwith<![CDATA[valid <content>]]>andmoreinvalid';
		$actual   = esc_xml( $source );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test that the function works correctly when charset is not UTF-8.
	 */
	public function test_non_utf8_charset_skips_invalid_character_stripping() {
		update_option( 'blog_charset', 'ISO-8859-1' );
		$source = "Test\x0Btext";
		$actual = esc_xml( $source );
		$this->assertIsString( $actual );
	}
}
