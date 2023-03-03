<?php
/**
 * @group option
 * @group user
 */
class Tests_Option_UserSettings extends WP_UnitTestCase {
	protected $user_id;

	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $this->user_id );
	}

	public function tear_down() {
		unset( $GLOBALS['_updated_user_settings'] );

		parent::tear_down();
	}

	/**
	 * @covers ::get_user_setting
	 * @covers ::get_all_user_settings
	 * @covers ::wp_set_all_user_settings
	 */
	public function test_set_user_setting() {
		$foo = get_user_setting( 'foo' );

		$this->assertEmpty( $foo );

		$this->set_user_setting( 'foo', 'bar' );

		$this->assertSame( 'bar', get_user_setting( 'foo' ) );
	}

	/**
	 * @covers ::get_user_setting
	 * @covers ::get_all_user_settings
	 * @covers ::wp_set_all_user_settings
	 */
	public function test_set_user_setting_dashes() {
		$foo = get_user_setting( 'foo' );

		$this->assertEmpty( $foo );

		$this->set_user_setting( 'foo', 'foo-bar-baz' );

		$this->assertSame( 'foo-bar-baz', get_user_setting( 'foo' ) );
	}

	/**
	 * @covers ::get_user_setting
	 * @covers ::get_all_user_settings
	 * @covers ::wp_set_all_user_settings
	 */
	public function test_set_user_setting_strip_asterisks() {
		$foo = get_user_setting( 'foo' );

		$this->assertEmpty( $foo );

		$this->set_user_setting( 'foo', 'foo*bar*baz' );

		$this->assertSame( 'foobarbaz', get_user_setting( 'foo' ) );
	}

	// set_user_setting() bails if `headers_sent()` is true.
	private function set_user_setting( $name, $value ) {
		$all_user_settings          = get_all_user_settings();
		$all_user_settings[ $name ] = $value;

		return wp_set_all_user_settings( $all_user_settings );
	}
}
