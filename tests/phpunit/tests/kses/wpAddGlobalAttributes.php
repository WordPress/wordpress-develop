<?php

/**
 * Tests for _wp_add_global_attributes().
 *
 * @group kses
 *
 * @covers ::_wp_add_global_attributes
 */
class Tests_Kses_wpAddGlobalAttributes extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_merges_global_attributes_into_array() {
		$result = _wp_add_global_attributes(
			array(
				'href' => true,
			)
		);

		$this->assertTrue( $result['href'] );
		$this->assertTrue( $result['class'] );
		$this->assertTrue( $result['id'] );
		$this->assertTrue( $result['style'] );
		$this->assertTrue( $result['data-*'] );
		$this->assertTrue( $result['aria-label'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_true_value_becomes_globals_only() {
		$result = _wp_add_global_attributes( true );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['class'] );
		$this->assertArrayNotHasKey( 'href', $result );
	}

	/**
	 * @ticket 65819
	 */
	public function test_non_array_non_true_value_is_returned_unchanged() {
		$this->assertFalse( _wp_add_global_attributes( false ) );
		$this->assertSame( 'keep', _wp_add_global_attributes( 'keep' ) );
	}
}
