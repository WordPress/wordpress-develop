<?php

/**
 * @group formatting
 * @covers ::sanitize_key
 */
class Tests_Formatting_SanitizeKey extends WP_UnitTestCase {

	/**
	 * @ticket       54160
	 * @dataProvider data_sanitize_key
	 *
	 * @param string $key      The key to sanitize.
	 * @param string $expected The expected value.
	 */
	public function test_sanitize_key( $key, $expected ) {
		$this->assertSame( $expected, sanitize_key( $key ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_sanitize_key() {
		return array(
			'an empty string key'            => array(
				'key'      => '',
				'expected' => '',
			),
			'a lowercase key with commas'    => array(
				'key'      => 'howdy,admin',
				'expected' => 'howdyadmin',
			),
			'a lowercase key with commas'    => array(
				'key'      => 'HOWDY,ADMIN',
				'expected' => 'howdyadmin',
			),
			'a mixed case key with commas'   => array(
				'key'      => 'HoWdY,aDmIn',
				'expected' => 'howdyadmin',
			),
			'a key with dashes'              => array(
				'key'      => 'howdy-admin',
				'expected' => 'howdy-admin',
			),
			'a key with spaces'              => array(
				'key'      => 'howdy admin',
				'expected' => 'howdyadmin',
			),
			'a key with a HTML entity'       => array(
				'key'      => 'howdy&nbsp;admin',
				'expected' => 'howdynbspadmin',
			),
			'a key with a unicode character' => array(
				'key'      => 'howdy' . chr( 140 ) . 'admin',
				'expected' => 'howdyadmin',
			),
		);
	}

	/**
	 * @ticket       54160
	 * @dataProvider data_sanitize_key_nonstring_scalar
	 *
	 * @param mixed  $key      The key to sanitize.
	 * @param string $expected The expected value.
	 */
	public function test_sanitize_key_nonstring_scalar( $key, $expected ) {
		$this->assertSame( $expected, sanitize_key( $key ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_sanitize_key_nonstring_scalar() {
		return array(
			'integer type'  => array(
				'key'      => 0,
				'expected' => '0',
			),
			'boolean true'  => array(
				'key'      => true,
				'expected' => '1',
			),
			'boolean false' => array(
				'key'      => false,
				'expected' => '',
			),
			'float type'    => array(
				'key'      => 0.123,
				'expected' => '0123',
			),
		);
	}

	/**
	 * @ticket       54160
	 * @dataProvider data_sanitize_key_with_non_scalars
	 *
	 * @param mixed $key      The key to sanitize.
	 * @param mixed $expected The expected value.
	 */
	public function test_sanitize_key_with_non_scalars( $key, $expected ) {
		$this->assertSame( $expected, sanitize_key( $key ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_sanitize_key_with_non_scalars() {
		return array(
			'array type' => array(
				'key'      => array( 'key' ),
				'expected' => '',
			),
			'null'       => array(
				'key'      => null,
				'expected' => '',
			),
			'object'     => array(
				'key'      => new stdClass(),
				'expected' => '',
			),
		);
	}

	/**
	 * @ticket 54160
	 *
	 * @covers WP_Hook::sanitize_key
	 */
	public function test_filter_sanitize_key() {
		$key = 'Howdy, admin!';

		add_filter(
			'sanitize_key',
			static function ( $key ) {
				return 'Howdy, admin!';
			}
		);

		$this->assertSame( $key, sanitize_key( $key ) );
	}
}
