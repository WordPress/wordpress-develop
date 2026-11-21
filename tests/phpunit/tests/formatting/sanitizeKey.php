<?php

/**
 * @group formatting
 *
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
	public static function data_sanitize_key() {
		return array(
			'an empty string key'            => array(
				'key'      => '',
				'expected' => '',
			),
			'a lowercase key with commas'    => array(
				'key'      => 'howdy,admin',
				'expected' => 'howdyadmin',
			),
			'an uppercase key with commas'   => array(
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
	 * @dataProvider data_sanitize_key_non_string
	 *
	 * @param mixed  $key      The key to sanitize.
	 * @param string $expected The expected value.
	 */
	public function test_sanitize_key_non_string( $key, $expected ) {
		$this->assertSame( $expected, sanitize_key( $key ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_sanitize_key_non_string() {
		return array(
			'integer type'                 => array(
				'key'      => 0,
				'expected' => '0',
			),
			'boolean true'                 => array(
				'key'      => true,
				'expected' => '1',
			),
			'boolean false'                => array(
				'key'      => false,
				'expected' => '',
			),
			'float type'                   => array(
				'key'      => 0.123,
				'expected' => '0123',
			),
			'negative integer'             => array(
				'key'      => -1,
				'expected' => '-1',
			),
			'float in scientific notation' => array(
				'key'      => 1.0E+25,
				'expected' => '10e25',
			),
			'array type'                   => array(
				'key'      => array( 'key' ),
				'expected' => '',
			),
			'object type'                  => array(
				'key'      => new stdClass(),
				'expected' => '',
			),
			'float infinity'               => array(
				'key'      => INF,
				'expected' => 'inf',
			),
			'null'                         => array(
				'key'      => null,
				'expected' => '',
			),
			'stringable object'            => array(
				'key'      => new Exception( 'HOWDY' ),
				'expected' => '',
			),
			'closure'                      => array(
				'key'      => static function () {
					return 'howdy';
				},
				'expected' => '',
			),
		);
	}

	/**
	 * Tests that the filter receives the sanitized key and the original non-string key.
	 *
	 * @dataProvider data_sanitize_key_non_string
	 *
	 * @param mixed  $key      The key to sanitize.
	 * @param string $expected The expected value.
	 */
	public function test_sanitize_key_filter_with_non_string( $key, string $expected ): void {
		$filter = new MockAction();
		add_filter( 'sanitize_key', array( $filter, 'filter' ), 10, 2 );

		$this->assertSame( $expected, sanitize_key( $key ), 'Unexpected sanitized key.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The filter was not applied once.' );
		$this->assertSame( array( $expected, $key ), $filter->get_args()[0], 'The filter did not receive the sanitized key and the original key.' );
	}

	/**
	 * @ticket       54160
	 * @dataProvider data_sanitize_key_with_non_scalars
	 *
	 * @param mixed $nonscalar_key A non-scalar data type given as a key.
	 */
	public function test_sanitize_key_with_non_scalars( $nonscalar_key ) {
		add_filter(
			'sanitize_key',
			function ( $sanitized_key, $key ) use ( $nonscalar_key ) {
				$this->assertEmpty( $sanitized_key, 'Empty string not passed as first filtered argument' );
				$this->assertSame( $nonscalar_key, $key, 'Given unsanitized key not passed as second filtered argument' );
				return $sanitized_key;
			},
			10,
			2
		);
		$this->assertEmpty( sanitize_key( $nonscalar_key ), 'Non-scalar key did not return empty string' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_sanitize_key_with_non_scalars() {
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
}
