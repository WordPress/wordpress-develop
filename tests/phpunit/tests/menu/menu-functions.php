<?php

/**
 * @group menu
 */
class Tests_Menu_Functions extends WP_UnitTestCase {
	/**
	 * @dataProvider data_add_cssclass
	 *
	 * @covers add_cssclass
	 */
	public function test_add_cssclass( $input, $expected ) {
		$this->assertSame( $expected, add_cssclass( 'menu-top-first', $input ) );
	}

	public function data_add_cssclass() {
		return array(
			'class should not be added one'   => array(
				'input'    => 'menu-top menu-top-first menu-icon-dashboard',
				'expected' => 'menu-top menu-top-first menu-icon-dashboard',
			),
			'class should not be added two'   => array(
				'input'    => 'menu-top menu-top-first',
				'expected' => 'menu-top menu-top-first',
			),
			'class should not be added three' => array(
				'input'    => 'menu-top-first',
				'expected' => 'menu-top-first',
			),
			'class should not be added four'  => array(
				'input'    => 'menu-top-first menu-icon-dashboard',
				'expected' => 'menu-top-first menu-icon-dashboard',
			),
			'class should be added one'       => array(
				'input'    => 'menu-top menu-top-first-foo menu-icon-dashboard',
				'expected' => 'menu-top menu-top-first-foo menu-icon-dashboard menu-top-first',
			),
			'class should be added two'       => array(
				'input'    => 'menu-top foo-menu-top-first menu-icon-dashboard',
				'expected' => 'menu-top foo-menu-top-first menu-icon-dashboard menu-top-first',
			),
		);
	}
}
