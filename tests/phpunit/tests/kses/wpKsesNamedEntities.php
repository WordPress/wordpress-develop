<?php

/**
 * Tests for wp_kses_named_entities().
 *
 * @group kses
 *
 * @covers ::wp_kses_named_entities
 */
class Tests_Kses_wpKsesNamedEntities extends WP_UnitTestCase {

	/**
	 * Tests that the callback returns the entity for an allowed entity name,
	 * and escapes the ampersand of any other name.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_entity_names
	 *
	 * @param string $name     Entity name captured by the pattern.
	 * @param string $expected Expected replacement.
	 */
	public function test_should_keep_allowed_entities_and_escape_others( $name, $expected ) {
		$this->assertSame( $expected, wp_kses_named_entities( array( "&$name;", $name ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_entity_names() {
		return array(
			'ampersand'                    => array( 'amp', '&amp;' ),
			'less than'                    => array( 'lt', '&lt;' ),
			'greater than'                 => array( 'gt', '&gt;' ),
			'quotation mark'               => array( 'quot', '&quot;' ),
			'copyright sign'               => array( 'copy', '&copy;' ),
			'non-breaking space'           => array( 'nbsp', '&nbsp;' ),
			'lowercase letter'             => array( 'eacute', '&eacute;' ),
			'uppercase letter'             => array( 'Eacute', '&Eacute;' ),
			'name that does not exist'     => array( 'notanentity', '&amp;notanentity;' ),
			'name with the wrong case'     => array( 'COPY', '&amp;COPY;' ),
			'name with the wrong case too' => array( 'Amp', '&amp;Amp;' ),
			'entity that is not allowed'   => array( 'colon', '&amp;colon;' ),
		);
	}

	/**
	 * Tests that an empty replacement is returned when no name was captured.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_matches_without_name
	 *
	 * @param array $matches Matches passed to the callback.
	 */
	public function test_should_return_empty_string_without_name( $matches ) {
		$this->assertSame( '', wp_kses_named_entities( $matches ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_matches_without_name() {
		return array(
			'no matches'      => array( array() ),
			'empty name'      => array( array( '&;', '' ) ),
			'only full match' => array( array( '&amp;' ) ),
			'null name'       => array( array( '&;', null ) ),
		);
	}

	/**
	 * Tests that the callback works with the pattern used by wp_kses_normalize_entities(),
	 * which is applied after the ampersands were escaped.
	 *
	 * @ticket 66492
	 */
	public function test_should_work_as_preg_replace_callback() {
		$result = preg_replace_callback(
			'/&amp;([A-Za-z]{2,8}[0-9]{0,2});/',
			'wp_kses_named_entities',
			'&amp;amp; &amp;copy; &amp;bogus; &amp;AMP; text'
		);

		$this->assertSame( '&amp; &copy; &amp;bogus; &amp;AMP; text', $result );
	}
}
