<?php

/**
 * Tests for the absint() function.
 *
 * @group functions
 *
 * @covers ::absint
 */
class Tests_Functions_Absint extends WP_UnitTestCase {

	/**
	 * @ticket 60101
	 *
	 * @dataProvider data_absint
	 */
	public function test_absint( $test_value, $expected_value ) {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters {
	 *     @type string $test_value Test value.
	 *     @type string $expected   Expected return value.
	 * }
	 */
	public function data_absint() {
		return array(
			'1 int'                 => array(
				'test_value'     => 1,
				'expected_value' => 1,
			),
			'1 string'              => array(
				'test_value'     => '1',
				'expected_value' => 1,
			),
			'-1 int'                => array(
				'test_value'     => -1,
				'expected_value' => 1,
			),
			'-1 string'             => array(
				'test_value'     => '-1',
				'expected_value' => 1,
			),
			'9.1 float'             => array(
				'test_value'     => 9.1,
				'expected_value' => 9,
			),
			'9.9 float'             => array(
				'test_value'     => 9.9,
				'expected_value' => 9,
			),
			'string'                => array(
				'test_value'     => 'string',
				'expected_value' => 0,
			),
			'string_1'              => array(
				'test_value'     => 'string_1',
				'expected_value' => 0,
			),
			'999_string'            => array(
				'test_value'     => '999_string',
				'expected_value' => 999,
			),
			'99 string with spaces' => array(
				'test_value'     => '99 string with spaces',
				'expected_value' => 99,
			),
			'99 array'              => array(
				'test_value'     => array( 99 ),
				'expected_value' => 1,
			),
			'99 string array'       => array(
				'test_value'     => array( '99' ),
				'expected_value' => 1,
			),
		);
	}

	/**
	 * Tests non-string scalar types, null, the empty array, and float edge values.
	 *
	 * @ticket 65826
	 *
	 * @dataProvider data_absint_other_types
	 *
	 * @param mixed $test_value     Test value.
	 * @param int   $expected_value Expected return value.
	 */
	public function test_absint_other_types( $test_value, int $expected_value ): void {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_absint_other_types(): array {
		return array(
			'null'                         => array(
				'test_value'     => null,
				'expected_value' => 0,
			),
			'true'                         => array(
				'test_value'     => true,
				'expected_value' => 1,
			),
			'false'                        => array(
				'test_value'     => false,
				'expected_value' => 0,
			),
			'empty array'                  => array(
				'test_value'     => array(),
				'expected_value' => 0,
			),
			'0 int'                        => array(
				'test_value'     => 0,
				'expected_value' => 0,
			),
			'0.0 float'                    => array(
				'test_value'     => 0.0,
				'expected_value' => 0,
			),
			'negative zero float'          => array(
				'test_value'     => -0.0,
				'expected_value' => 0,
			),
			'tiny positive float'          => array(
				'test_value'     => 1.0e-20,
				'expected_value' => 0,
			),
			'tiny negative float'          => array(
				'test_value'     => -1.0e-20,
				'expected_value' => 0,
			),
			'large in-range float (2^62)'  => array(
				'test_value'     => 4611686018427387904.0,
				'expected_value' => 4611686018427387904,
			),
			'large in-range float (-2^62)' => array(
				'test_value'     => -4611686018427387904.0,
				'expected_value' => 4611686018427387904,
			),
		);
	}

	/**
	 * Tests that an object is converted to `1`, with a notice (PHP 7) or
	 * warning (PHP 8) about the object to int conversion.
	 *
	 * @ticket 65826
	 */
	public function test_absint_object(): void {
		$error = null;

		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$error ): bool {
				$error = $errstr;
				return true;
			}
		);
		$actual = absint( new stdClass() );
		restore_error_handler();

		$this->assertSame( 1, $actual );
		$this->assertSame( 'Object of class stdClass could not be converted to int', $error );
	}

	/**
	 * Tests that a `__toString()` method is ignored when converting an object
	 * to an integer, unlike when converting an object to a string.
	 *
	 * @ticket 65826
	 */
	public function test_absint_object_with_to_string(): void {
		$object = new class() {
			public function __toString(): string {
				return '42';
			}
		};

		$error = null;

		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$error ): bool {
				$error = $errstr;
				return true;
			}
		);
		$actual = absint( $object );
		restore_error_handler();

		$this->assertSame( 1, $actual );
		$this->assertStringEndsWith( 'could not be converted to int', (string) $error );
	}

	/**
	 * Tests that a closure is treated like any other object.
	 *
	 * @ticket 65826
	 */
	public function test_absint_closure(): void {
		$error = null;

		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$error ): bool {
				$error = $errstr;
				return true;
			}
		);
		$actual = absint(
			static function () {}
		);
		restore_error_handler();

		$this->assertSame( 1, $actual );
		$this->assertSame( 'Object of class Closure could not be converted to int', $error );
	}

	/**
	 * Tests that an enum case is treated like any other object.
	 *
	 * Note that a backed enum converts to `1` with a warning like any other
	 * object, not to its backing value.
	 *
	 * @ticket 65826
	 *
	 * @requires PHP 8.1
	 *
	 * @dataProvider data_absint_enums
	 *
	 * @param string $case_name Fully qualified enum case name.
	 */
	public function test_absint_enums( string $case_name ): void {
		require_once DIR_TESTDATA . '/functions/absint-enums.php';

		$enum_case = constant( $case_name );
		$error     = null;

		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$error ): bool {
				$error = $errstr;
				return true;
			}
		);
		$actual = absint( $enum_case );
		restore_error_handler();

		$this->assertSame( 1, $actual );
		$this->assertSame(
			'Object of class ' . get_class( $enum_case ) . ' could not be converted to int',
			$error
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_absint_enums(): array {
		return array(
			'pure enum case'   => array(
				'case_name' => 'Absint_Test_Pure_Enum::Hearts',
			),
			'backed enum case' => array(
				'case_name' => 'Absint_Test_Backed_Enum::Ace',
			),
		);
	}

	/**
	 * Tests that an object whose class registers an internal cast handler,
	 * such as `SimpleXMLElement`, is converted using that handler rather
	 * than to `1`.
	 *
	 * @ticket 65826
	 *
	 * @requires extension simplexml
	 */
	public function test_absint_object_with_cast_handler(): void {
		$this->assertSame( 42, absint( new SimpleXMLElement( '<value>42</value>' ) ) );
		$this->assertSame( 42, absint( new SimpleXMLElement( '<value>-42</value>' ) ) );
		$this->assertSame( 0, absint( new SimpleXMLElement( '<value>text</value>' ) ) );
	}

	/**
	 * Tests that a GMP number is converted via its internal cast handler.
	 *
	 * @ticket 65826
	 *
	 * @requires extension gmp
	 */
	public function test_absint_gmp(): void {
		$this->assertSame( 42, absint( gmp_init( '42' ) ) );
		$this->assertSame( 42, absint( gmp_init( '-42' ) ) );
	}

	/**
	 * Tests that a resource is converted to its resource ID.
	 *
	 * @ticket 65826
	 */
	public function test_absint_resource(): void {
		$stream = fopen( 'php://memory', 'r' );

		$this->assertSame( (int) $stream, absint( $stream ) );
		$this->assertGreaterThan( 0, absint( $stream ) );

		fclose( $stream );
	}

	/**
	 * Tests that a closed resource is still converted to its resource ID.
	 *
	 * @ticket 65826
	 */
	public function test_absint_closed_resource(): void {
		$stream      = fopen( 'php://memory', 'r' );
		$resource_id = (int) $stream;
		fclose( $stream );

		$this->assertSame( 'resource (closed)', gettype( $stream ) );
		$this->assertSame( $resource_id, absint( $stream ) );
	}

	/**
	 * Tests string values in the various formats PHP recognizes when
	 * casting a string to an integer.
	 *
	 * @ticket 65826
	 *
	 * @dataProvider data_absint_string_values
	 *
	 * @param string $test_value     Test value.
	 * @param int    $expected_value Expected return value.
	 */
	public function test_absint_string_values( string $test_value, int $expected_value ): void {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_absint_string_values(): array {
		return array(
			'empty string'                    => array(
				'test_value'     => '',
				'expected_value' => 0,
			),
			'whitespace only'                 => array(
				'test_value'     => ' ',
				'expected_value' => 0,
			),
			'zero'                            => array(
				'test_value'     => '0',
				'expected_value' => 0,
			),
			'integer'                         => array(
				'test_value'     => '42',
				'expected_value' => 42,
			),
			'minus sign only'                 => array(
				'test_value'     => '-',
				'expected_value' => 0,
			),
			'plus sign only'                  => array(
				'test_value'     => '+',
				'expected_value' => 0,
			),
			'decimal point only'              => array(
				'test_value'     => '.',
				'expected_value' => 0,
			),
			'exponent without a coefficient'  => array(
				'test_value'     => 'e5',
				'expected_value' => 0,
			),
			'uppercase exponent notation'     => array(
				'test_value'     => '1E3',
				'expected_value' => 1000,
			),
			'explicitly positive integer'     => array(
				'test_value'     => '+42',
				'expected_value' => 42,
			),
			'negative integer'                => array(
				'test_value'     => '-42',
				'expected_value' => 42,
			),
			'negative zero'                   => array(
				'test_value'     => '-0',
				'expected_value' => 0,
			),
			'surrounding whitespace'          => array(
				'test_value'     => ' 42 ',
				'expected_value' => 42,
			),
			'leading tab and newline'         => array(
				'test_value'     => "\t\n-7",
				'expected_value' => 7,
			),
			'float'                           => array(
				'test_value'     => '3.7',
				'expected_value' => 3,
			),
			'negative float'                  => array(
				'test_value'     => '-3.7',
				'expected_value' => 3,
			),
			'float without a leading digit'   => array(
				'test_value'     => '.5',
				'expected_value' => 0,
			),
			'exponent notation'               => array(
				'test_value'     => '1e3',
				'expected_value' => 1000,
			),
			'negative float with exponent'    => array(
				'test_value'     => '-2.5e2',
				'expected_value' => 250,
			),
			'float rounding up to the double' => array(
				'test_value'     => '1.9999999999999999',
				'expected_value' => 2,
			),
			'hexadecimal'                     => array(
				'test_value'     => '0x1A',
				'expected_value' => 0,
			),
			'leading zero'                    => array(
				'test_value'     => '012',
				'expected_value' => 12,
			),
			'binary'                          => array(
				'test_value'     => '0b101',
				'expected_value' => 0,
			),
			'trailing non-numeric characters' => array(
				'test_value'     => '42abc',
				'expected_value' => 42,
			),
			'leading non-numeric characters'  => array(
				'test_value'     => 'abc42',
				'expected_value' => 0,
			),
			'thousands separator'             => array(
				'test_value'     => '1,000',
				'expected_value' => 1,
			),
			'underscore separator'            => array(
				'test_value'     => '1_000',
				'expected_value' => 1,
			),
			'digits separated by a space'     => array(
				'test_value'     => '9 9',
				'expected_value' => 9,
			),
			'INF as a string'                 => array(
				'test_value'     => 'INF',
				'expected_value' => 0,
			),
			'NAN as a string'                 => array(
				'test_value'     => 'NAN',
				'expected_value' => 0,
			),
			'non-ASCII digits'                => array(
				'test_value'     => '٤٢',
				'expected_value' => 0,
			),
		);
	}

	/**
	 * Tests that values which used to make `abs()` overflow to a float
	 * return the closest possible integer instead of causing a fatal error.
	 *
	 * `(float) PHP_INT_MAX` is equal to the float previously returned for
	 * these values, so this is the most backward compatible integer result.
	 *
	 * Unlike out of range floats, whose conversion to int is platform-dependent,
	 * numeric strings which exceed the integer range are reliably saturated to
	 * `PHP_INT_MIN` / `PHP_INT_MAX` by the string to int conversion, and strings
	 * which exceed the float range become `INF`, which converts to `0`.
	 *
	 * @ticket 65826
	 *
	 * @dataProvider data_absint_extreme_values
	 *
	 * @param mixed $test_value     Test value.
	 * @param int   $expected_value Expected return value.
	 */
	public function test_absint_extreme_values( $test_value, int $expected_value ): void {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_absint_extreme_values(): array {
		return array(
			'PHP_INT_MAX'                               => array(
				'test_value'     => PHP_INT_MAX,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN'                               => array(
				'test_value'     => PHP_INT_MIN,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN as a float'                    => array(
				'test_value'     => (float) PHP_INT_MIN,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN + 1'                           => array(
				'test_value'     => PHP_INT_MIN + 1,
				'expected_value' => PHP_INT_MAX,
			),
			'string below the integer range'            => array(
				'test_value'     => '-99999999999999999999',
				'expected_value' => PHP_INT_MAX,
			),
			'string above the integer range'            => array(
				'test_value'     => '99999999999999999999',
				'expected_value' => PHP_INT_MAX,
			),
			'float string below the integer range'      => array(
				'test_value'     => '-99999999999999999999.9',
				'expected_value' => PHP_INT_MAX,
			),
			'float string above the integer range'      => array(
				'test_value'     => '99999999999999999999.9',
				'expected_value' => PHP_INT_MAX,
			),
			'float string just below the integer range' => array(
				'test_value'     => '-9223372036854775808.5',
				'expected_value' => PHP_INT_MAX,
			),
			'float string just above the integer range' => array(
				'test_value'     => '9223372036854775807.5',
				'expected_value' => PHP_INT_MAX,
			),
			'exponent string below the integer range'   => array(
				'test_value'     => '-1e20',
				'expected_value' => PHP_INT_MAX,
			),
			'exponent string above the integer range'   => array(
				'test_value'     => '1e20',
				'expected_value' => PHP_INT_MAX,
			),
			'float exponent string below the integer range' => array(
				'test_value'     => '-1.5e20',
				'expected_value' => PHP_INT_MAX,
			),
			'float exponent string above the integer range' => array(
				'test_value'     => '1.5e20',
				'expected_value' => PHP_INT_MAX,
			),
			'exponent string below the float range'     => array(
				'test_value'     => '-1e309',
				'expected_value' => 0,
			),
			'exponent string above the float range'     => array(
				'test_value'     => '1e309',
				'expected_value' => 0,
			),
		);
	}

	/**
	 * Tests that floats which cannot be represented as an integer are capped
	 * at `PHP_INT_MAX`, and that non-finite floats return `0`, without an
	 * out of range float to int cast, which warns as of PHP 8.5.
	 *
	 * @ticket 65826
	 *
	 * @dataProvider data_absint_unrepresentable_floats
	 *
	 * @param float $test_value     Test value.
	 * @param int   $expected_value Expected return value.
	 */
	public function test_absint_unrepresentable_floats( float $test_value, int $expected_value ): void {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_absint_unrepresentable_floats(): array {
		return array(
			'(float) PHP_INT_MAX (2^63)' => array(
				'test_value'     => (float) PHP_INT_MAX,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MAX + 1'            => array(
				'test_value'     => PHP_INT_MAX + 1,
				'expected_value' => PHP_INT_MAX,
			),
			'1.0e20'                     => array(
				'test_value'     => 1.0e20,
				'expected_value' => PHP_INT_MAX,
			),
			'-1.0e20'                    => array(
				'test_value'     => -1.0e20,
				'expected_value' => PHP_INT_MAX,
			),
			'INF'                        => array(
				'test_value'     => INF,
				'expected_value' => 0,
			),
			'-INF'                       => array(
				'test_value'     => -INF,
				'expected_value' => 0,
			),
			'NAN'                        => array(
				'test_value'     => NAN,
				'expected_value' => 0,
			),
		);
	}
}
