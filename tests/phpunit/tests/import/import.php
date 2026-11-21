<?php

/**
 * @group import
 */
class Tests_Import_Import extends WP_UnitTestCase {
	/**
	 * @covers ::get_importers
	 */
	public function test_ordering_of_importers() {
		global $wp_importers;
		$_wp_importers = $wp_importers; // Preserve global state.
		$wp_importers  = array(
			'xyz1' => array( 'xyz1' ),
			'XYZ2' => array( 'XYZ2' ),
			'abc2' => array( 'abc2' ),
			'ABC1' => array( 'ABC1' ),
			'def1' => array( 'def1' ),
		);
		$this->assertSame(
			array(
				'ABC1' => array( 'ABC1' ),
				'abc2' => array( 'abc2' ),
				'def1' => array( 'def1' ),
				'xyz1' => array( 'xyz1' ),
				'XYZ2' => array( 'XYZ2' ),
			),
			get_importers()
		);
		$wp_importers = $_wp_importers; // Restore global state.
	}
}
