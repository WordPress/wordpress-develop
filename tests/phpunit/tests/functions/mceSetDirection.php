<?php

/**
 * Tests for the _mce_set_direction function.
 *
 * @group functions
 *
 * @covers ::_mce_set_direction
 */
class Tests_functions_mceSetDirection extends WP_UnitTestCase {

	/**
	 * @ticket 60219
	 */
	public function test__mce_set_direction() {
		global $wp_locale;

		$mce_init = array(
			'directionality' => 'ltr',
			'rtl_ui'         => false,
			'plugins'        => 'plugins',
			'toolbar1'       => 'toolbar1',
		);

		$expected = array(
			'directionality' => 'rtl',
			'rtl_ui'         => true,
			'plugins'        => 'plugins,directionality',
			'toolbar1'       => 'toolbar1,ltr',
		);

		$actual = _mce_set_direction( $mce_init );
		$this->assertSameSets( $mce_init, $actual );

		$orig_text_dir             = $wp_locale->text_direction;
		$wp_locale->text_direction = 'rtl';
		$actual                    = _mce_set_direction( $mce_init );
		$wp_locale->text_direction = $orig_text_dir;

		$this->assertSameSets( $expected, $actual );
	}
}
