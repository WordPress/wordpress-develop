<?php

/**
 * Tests for WP_Rewrite::preg_index().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::preg_index
 */
class Tests_Rewrite_PregIndex extends WP_UnitTestCase {

	/**
	 * Tests that the index is a reference to a match when there is no prefix.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_numbers
	 *
	 * @param int|string $number   Number of the match.
	 * @param string     $expected Expected reference.
	 */
	public function test_should_return_a_reference_to_a_match_without_a_prefix( $number, $expected ) {
		$wp_rewrite = new WP_Rewrite();

		$this->assertSame( '', $wp_rewrite->matches );
		$this->assertSame( $expected, $wp_rewrite->preg_index( $number ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_numbers() {
		return array(
			'one'           => array( 1, '$1' ),
			'three'         => array( 3, '$3' ),
			'zero'          => array( 0, '$0' ),
			'two digits'    => array( 12, '$12' ),
			'string number' => array( '4', '$4' ),
		);
	}

	/**
	 * Tests that the index is a reference to a match of an array, when there is a prefix.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_prefixes
	 *
	 * @param string $matches  Name of the array of matches.
	 * @param int    $number   Number of the match.
	 * @param string $expected Expected reference.
	 */
	public function test_should_return_a_reference_to_an_item_of_the_matches( $matches, $number, $expected ) {
		$wp_rewrite          = new WP_Rewrite();
		$wp_rewrite->matches = $matches;

		$this->assertSame( $expected, $wp_rewrite->preg_index( $number ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_prefixes() {
		return array(
			'matches'    => array( 'matches', 2, '$matches[2]' ),
			'other name' => array( 'm', 11, '$m[11]' ),
			'zero'       => array( 'matches', 0, '$matches[0]' ),
		);
	}
}
