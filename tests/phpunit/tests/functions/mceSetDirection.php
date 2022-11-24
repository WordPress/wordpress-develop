<?php
/**
 * Tests for _mce_set_direction function.
 *
 * @group functions.php
 *
 * @covers ::_mce_set_direction
 */#
class Tests_Functions_MceSetDirection extends WP_UnitTestCase {
	/**
	 * @var WP_Locale
	 */
	protected $locale;

	public function set_up() {
		parent::set_up();
		$this->locale = new WP_Locale();
	}

	/**
	 * @ticket 57192
	 */
	public function test__mce_set_direction() {
		global $wp_locale;
		$direction = $wp_locale->text_direction;

		$mce_init = array(
			'plugins'  => 'some_plugin_name',
			'toolbar1' => 'some_button_name',
		);
		$this->assertEquals( $mce_init, _mce_set_direction( $mce_init ), ' set to ltr' );

		$wp_locale->text_direction = 'rtl';

		$mce_init_expected = array(
			'plugins'        => 'some_plugin_name,directionality',
			'toolbar1'       => 'some_button_name,ltr',
			'directionality' => 'rtl',
			'rtl_ui'         => true,
		);
		$this->assertEquals( $mce_init_expected, _mce_set_direction( $mce_init ), 'set to rtl' );

		$mce_init = array(
			'plugins'  => 'some_plugin_name,directionality',
			'toolbar1' => 'some_button_name,ltr',
		);
		$this->assertEquals( $mce_init_expected, _mce_set_direction( $mce_init ), "checking we don't get to strings added" );

		$wp_locale->text_direction = $direction;
	}
}
