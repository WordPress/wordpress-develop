<?php

/**
 * @group formatting
 */
class Tests_Formatting_ConvertInvalidEntities extends WP_UnitTestCase {

	/**
	 * @dataProvider data_convert_invalid_entities_strings
	 * @dataProvider data_convert_invalid_entities_non_string
	 *
	 * @covers ::convert_invalid_entities
	 *
	 * @param mixed  $input    Supposedly a string with entities that need converting.
	 * @param string $expected Expected function output.
	 */
	public function test_convert_invalid_entities( $input, $expected ) {
		$this->assertSame( $expected, convert_invalid_entities( $input ) );
	}

	/**
	 * Data provider with test cases intended to be handled by the function.
	 *
	 * @return array
	 */
	public function data_convert_invalid_entities_strings() {
		return array(
			'empty string'                                => array(
				'input'    => '',
				'expected' => '',
			),
			'replaces windows1252 entities with unicode ones' => array(
				'input'    => '&#130;&#131;&#132;&#133;&#134;&#135;&#136;&#137;&#138;&#139;&#140;&#145;&#146;&#147;&#148;&#149;&#150;&#151;&#152;&#153;&#154;&#155;&#156;&#159;',
				'expected' => '&#8218;&#402;&#8222;&#8230;&#8224;&#8225;&#710;&#8240;&#352;&#8249;&#338;&#8216;&#8217;&#8220;&#8221;&#8226;&#8211;&#8212;&#732;&#8482;&#353;&#8250;&#339;&#376;',
			),
			// @ticket 20503
			'replaces latin letter z with caron'          => array(
				'input'    => '&#142;&#158;',
				'expected' => '&#381;&#382;',
			),
			'string without entity is returned unchanged' => array(
				'input'    => 'Hello! This is just text.',
				'expected' => 'Hello! This is just text.',
			),
			'string with unlisted entity is returned unchanged' => array(
				'input'    => 'English pound: &#163;.',
				'expected' => 'English pound: &#163;.',
			),
			'string with entity in thousand range (not win 1252) is returned unchanged' => array(
				'input'    => '&#1031;&#1315;',
				'expected' => '&#1031;&#1315;',
			),
		);
	}

	/**
	 * Data provider to safeguard consistent handling on unexpected data types.
	 *
	 * @return array
	 */
	public function data_convert_invalid_entities_non_string() {
		$std_class           = new stdClass();
		$std_class->property = 'value';

		return array(
			'null'  => array(
				'input'  => null,
				'output' => null,
			),
			'false' => array(
				'input'  => false,
				'output' => false,
			),
			'true'  => array(
				'input'  => true,
				'output' => true,
			),
			'int'   => array(
				'input'  => 7486,
				'output' => 7486,
			),
			'float' => array(
				'input'  => 25.689,
				'output' => 25.689,
			),
			/*
			'array'  => array(
				'input'  => array( 1, 2, 3 ),
				'output' => '',
			),
			'object' => array(
				'input'  => $std_class,
				'output' => '',
			),
			*/
		);
	}

	function test_escapes_lone_ampersands() {
		$this->assertSame( 'at&#038;t', convert_chars( 'at&t' ) );
	}
}
