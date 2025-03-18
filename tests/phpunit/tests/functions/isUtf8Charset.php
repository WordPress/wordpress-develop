<?php
/**
 * Tests for the is_utf8_charset() function.
 *
 * @group functions
 * @group charset
 *
 * @covers ::is_utf8_charset
 */
class Tests_Functions_IsUtf8Charset extends WP_UnitTestCase {
    /**
     * Tests that is_utf8_charset correctly handles empty values.
     *
     * @ticket 25693
     *
     * @dataProvider data_empty_charset_values
     *
     * @param mixed $empty_charset Empty or null charset value.
     */
    public function test_handles_empty_values( $empty_charset ) {
        $this->assertTrue(
            is_utf8_charset( $empty_charset ),
            'Should return true when the charset is empty (defaulting to UTF-8)'
        );
    }

    /**
     * Tests that is_utf8_charset correctly identifies UTF-8 variants.
     *
     * @ticket 25693
     *
     * @dataProvider data_utf8_charset_variants
     *
     * @param string $utf8_charset A UTF-8 charset variant.
     */
    public function test_identifies_utf8_variants( $utf8_charset ) {
        $this->assertTrue(
            is_utf8_charset( $utf8_charset ),
            'Should identify valid UTF-8 charset variants'
        );
    }

    /**
     * Tests that is_utf8_charset correctly rejects non-UTF-8 charsets.
     *
     * @ticket 25693
     *
     * @dataProvider data_non_utf8_charset_values
     *
     * @param string $non_utf8_charset A non-UTF-8 charset.
     */
    public function test_rejects_non_utf8_charsets( $non_utf8_charset ) {
        $this->assertFalse(
            is_utf8_charset( $non_utf8_charset ),
            'Should reject non-UTF-8 charsets'
        );
    }

    /**
     * Data provider for empty charset values.
     *
     * @return array[].
     */
    public static function data_empty_charset_values() {
        return array(
            array( null ),
            array( '' ),
            array( false ),
            array( 0 ),
            array( '0' ),
        );
    }

    /**
     * Data provider for UTF-8 charset variants.
     *
     * @return array[].
     */
    public static function data_utf8_charset_variants() {
        return array(
            array( 'UTF-8' ),
            array( 'utf-8' ),
            array( 'UTF8' ),
            array( 'utf8' ),
        );
    }

    /**
     * Data provider for non-UTF-8 charset values.
     *
     * @return array[].
     */
    public static function data_non_utf8_charset_values() {
        return array(
            array( 'ISO-8859-1' ),
            array( 'ASCII' ),
            array( 'Windows-1252' ),
            array( 'EUC-JP' ),
            array( 'UTF-7' ),
        );
    }
}
