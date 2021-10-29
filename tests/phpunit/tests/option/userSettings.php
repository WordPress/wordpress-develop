<?php
class Tests_User_Settings extends WP_UnitTestCase {
	protected $user_id;

	function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $this->user_id );
	}

	function tear_down() {
		unset( $GLOBALS['_updated_user_settings'] );

		parent::tear_down();
	}

	function test_set_user_setting() {
		$foo = get_user_setting( 'foo' );

		$this->assertEmpty( $foo );

		$this->set_user_setting( 'foo', 'bar' );

		$this->assertSame( 'bar', get_user_setting( 'foo' ) );
	}

	function test_set_user_setting_dashes() {
		$foo = get_user_setting( 'foo' );

		$this->assertEmpty( $foo );

		$this->set_user_setting( 'foo', 'foo-bar-baz' );

		$this->assertSame( 'foo-bar-baz', get_user_setting( 'foo' ) );
	}

	function test_set_user_setting_strip_asterisks() {
		$foo = get_user_setting( 'foo' );

		$this->assertEmpty( $foo );

		$this->set_user_setting( 'foo', 'foo*bar*baz' );

		$this->assertSame( 'foobarbaz', get_user_setting( 'foo' ) );
	}

	// set_user_setting() bails if `headers_sent()` is true.
	function set_user_setting( $name, $value ) {
		$all_user_settings          = get_all_user_settings();
		$all_user_settings[ $name ] = $value;

		return wp_set_all_user_settings( $all_user_settings );
	}
}
