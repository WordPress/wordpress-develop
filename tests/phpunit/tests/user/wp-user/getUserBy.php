<?php

/**
 * @group  user
 * @covers WP_User::get_data_by
 */
class Tests_User_WpUser_GetDataBy extends WP_UnitTestCase {
	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_login'    => 'user1',
				'user_nicename' => 'userone',
				'user_pass'     => 'password',
				'first_name'    => 'John',
				'last_name'     => 'Doe',
				'display_name'  => 'John Doe',
				'user_email'    => 'jonny@battlefield3.com',
				'user_url'      => 'http://tacos.com',
				'role'          => 'contributor',
				'nickname'      => 'Johnny',
				'description'   => 'I am a WordPress user that cares about privacy.',
			)
		);
	}

	/**
	 * @dataProvider data_nonscalar_value
	 */
	public function test_nonscalar_value( $field, $value ) {
		$this->assertFalse( WP_User::get_data_by( $field, $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_nonscalar_value() {
		$obj = new stdClass();

		return array(
			// null value.
			'id: null'           => array(
				'field' => 'id',
				'value' => null,
			),
			'slug: null'         => array(
				'field' => 'slug',
				'value' => null,
			),
			'email: null'        => array(
				'field' => 'email',
				'value' => null,
			),
			'login: null'        => array(
				'field' => 'login',
				'value' => null,
			),
			// array value.
			'id: array type'     => array(
				'field' => 'id',
				'value' => array( 'array' ),
			),
			'slug: array type'   => array(
				'field' => 'slug',
				'value' => array( 'array' ),
			),
			'email: array type'  => array(
				'field' => 'email',
				'value' => array( 'array' ),
			),
			'login: array type'  => array(
				'field' => 'login',
				'value' => array( 'array' ),
			),
			// object value.
			'id: object type'    => array(
				'field' => 'id',
				'value' => $obj,
			),
			'slug: object type'  => array(
				'field' => 'slug',
				'value' => $obj,
			),
			'email: object type' => array(
				'field' => 'email',
				'value' => $obj,
			),
			'login: object type' => array(
				'field' => 'login',
				'value' => $obj,
			),
		);
	}
}
