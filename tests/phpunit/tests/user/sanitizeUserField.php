<?php

/**
 * Tests for the sanitize_user_field() function.
 *
 * @group user
 *
 * @covers ::sanitize_user_field
 */
class Tests_User_SanitizeUserField extends WP_UnitTestCase {

	/**
	 * Arbitrary user ID passed to the function, which never looks the user up.
	 *
	 * @var int
	 */
	const USER_ID = 123;

	/**
	 * Markup and entity fixture used to tell the escaping functions apart.
	 *
	 * @var string
	 */
	const MARKUP = '<b>Bold</b> & "double" \'single\'';

	/**
	 * Filter calls recorded by record_filters().
	 *
	 * @var array[]
	 */
	private $calls = array();

	/**
	 * Records every call to the hooks the function could fire for a field,
	 * and appends the hook name to the filtered value.
	 *
	 * @param string $field User field name.
	 */
	private function record_filters( $field ) {
		$field_no_prefix = str_replace( 'user_', '', $field );

		$hooks = array(
			"edit_{$field}",
			"edit_user_{$field_no_prefix}",
			"edit_user_{$field}",
			"pre_{$field}",
			"pre_user_{$field_no_prefix}",
			"pre_user_{$field}",
			$field,
			"user_{$field_no_prefix}",
			"user_{$field}",
		);

		foreach ( array_unique( $hooks ) as $hook ) {
			add_filter(
				$hook,
				function ( ...$args ) use ( $hook ) {
					$this->calls[] = array( $hook, $args );

					return $args[0] . '|' . $hook;
				},
				10,
				3
			);
		}
	}

	/**
	 * Tests that the ID field is cast to an integer in every context.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_casts_id_to_integer
	 *
	 * @param mixed  $value    Field value.
	 * @param string $context  Sanitization context.
	 * @param int    $expected Expected value.
	 */
	public function test_casts_id_to_integer( $value, $context, $expected ) {
		$this->assertSame( $expected, sanitize_user_field( 'ID', $value, self::USER_ID, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_casts_id_to_integer() {
		return array(
			'raw'       => array( '12abc', 'raw', 12 ),
			'display'   => array( '5', 'display', 5 ),
			'edit'      => array( '6', 'edit', 6 ),
			'attribute' => array( '8', 'attribute', 8 ),
			'js'        => array( '9', 'js', 9 ),
		);
	}

	/**
	 * Tests that the raw context returns the value without filtering or escaping it.
	 *
	 * @ticket 65819
	 */
	public function test_raw_context_returns_value_unchanged() {
		$this->record_filters( 'user_foo' );

		$this->assertSame( self::MARKUP, sanitize_user_field( 'user_foo', self::MARKUP, self::USER_ID, 'raw' ) );
		$this->assertSame( array(), $this->calls, 'No filter should run.' );
	}

	/**
	 * Tests that values that are neither strings nor numbers are returned unchanged.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_non_scalar_values
	 *
	 * @param mixed $value Field value.
	 */
	public function test_returns_non_string_values_unchanged( $value ) {
		$this->record_filters( 'user_foo' );

		$this->assertSame( $value, sanitize_user_field( 'user_foo', $value, self::USER_ID, 'display' ) );
		$this->assertSame( array(), $this->calls, 'No filter should run.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_non_scalar_values() {
		return array(
			'array' => array( array( 'a', 'b' ) ),
			'null'  => array( null ),
			'false' => array( false ),
		);
	}

	/**
	 * Tests the filter that runs for each context, and the arguments it receives.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_filters_for_context
	 *
	 * @param string $field         User field name.
	 * @param string $context       Sanitization context.
	 * @param string $hook          Expected hook name.
	 * @param array  $expected_args Expected filter arguments.
	 */
	public function test_applies_filter_for_context( $field, $context, $hook, $expected_args ) {
		$this->record_filters( $field );

		$this->assertSame( 'value|' . $hook, sanitize_user_field( $field, 'value', self::USER_ID, $context ) );
		$this->assertSame( array( array( $hook, $expected_args ) ), $this->calls, 'Only the expected filter should run.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_filters_for_context() {
		return array(
			'edit, prefixed field'                 => array( 'user_foo', 'edit', 'edit_user_foo', array( 'value', self::USER_ID ) ),
			'edit, unprefixed field'               => array( 'foo', 'edit', 'edit_user_foo', array( 'value', self::USER_ID ) ),
			'db, prefixed field'                   => array( 'user_foo', 'db', 'pre_user_foo', array( 'value' ) ),
			'db, unprefixed field'                 => array( 'foo', 'db', 'pre_user_foo', array( 'value' ) ),
			'display, prefixed field'              => array( 'user_foo', 'display', 'user_foo', array( 'value', self::USER_ID, 'display' ) ),
			'display, unprefixed field'            => array( 'foo', 'display', 'user_foo', array( 'value', self::USER_ID, 'display' ) ),
			'attribute uses display filters'       => array( 'user_foo', 'attribute', 'user_foo', array( 'value', self::USER_ID, 'attribute' ) ),
			'js uses display filters'              => array( 'user_foo', 'js', 'user_foo', array( 'value', self::USER_ID, 'js' ) ),
			'unknown context uses display filters' => array( 'user_foo', 'other', 'user_foo', array( 'value', self::USER_ID, 'other' ) ),
		);
	}

	/**
	 * Tests the escaping applied in each context.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_escaping_for_context
	 *
	 * @param string $context  Sanitization context.
	 * @param string $expected Expected value.
	 */
	public function test_escapes_value_for_context( $context, $expected ) {
		$this->assertSame( $expected, sanitize_user_field( 'user_foo', self::MARKUP, self::USER_ID, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_escaping_for_context() {
		return array(
			'edit'      => array( 'edit', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;' ),
			'attribute' => array( 'attribute', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;' ),
			'js'        => array( 'js', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; \\\'single\\\'' ),
			'db'        => array( 'db', self::MARKUP ),
			'display'   => array( 'display', self::MARKUP ),
		);
	}

	/**
	 * Tests that the description field is escaped with esc_html() in the edit context,
	 * and other fields with esc_attr().
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_edit_escaping_function
	 *
	 * @param string $field    User field name.
	 * @param string $filter   Filter run by the expected escaping function.
	 * @param string $expected Expected escaped and filtered value.
	 */
	public function test_edit_context_escaping_function( $field, $filter, $expected ) {
		$escaping_filters = array(
			'esc_html'         => new MockAction(),
			'attribute_escape' => new MockAction(),
		);

		foreach ( $escaping_filters as $hook => $mock ) {
			add_filter( $hook, array( $mock, 'filter' ) );
			add_filter(
				$hook,
				static function ( $value ) use ( $hook ) {
					return $value . '|' . $hook;
				},
				20
			);
		}

		$result = sanitize_user_field( $field, self::MARKUP, self::USER_ID, 'edit' );

		$this->assertSame( $expected, $result );

		foreach ( $escaping_filters as $hook => $mock ) {
			$this->assertSame( $hook === $filter ? 1 : 0, $mock->get_call_count(), "The {$hook} filter call count did not match." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_edit_escaping_function() {
		return array(
			'description' => array( 'description', 'esc_html', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;|esc_html' ),
			'other field' => array( 'user_foo', 'attribute_escape', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;|attribute_escape' ),
		);
	}

	/**
	 * Tests that the user_url field is passed through esc_url() outside the raw context.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_escapes_user_url
	 *
	 * @param string $value    Field value.
	 * @param string $context  Sanitization context.
	 * @param string $expected Expected value.
	 */
	public function test_escapes_user_url( $value, $context, $expected ) {
		// Isolates esc_url() from the default filters on the user_url hooks.
		remove_all_filters( 'user_url' );
		remove_all_filters( 'pre_user_url' );
		remove_all_filters( 'edit_user_url' );

		$this->assertSame( $expected, sanitize_user_field( 'user_url', $value, self::USER_ID, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_escapes_user_url() {
		return array(
			'display, adds the scheme'          => array( 'example.org/path', 'display', 'http://example.org/path' ),
			'display, encodes ampersands'       => array( 'https://example.org/?a=1&b=2', 'display', 'https://example.org/?a=1&#038;b=2' ),
			'display, rejects a javascript URL' => array( 'javascript:alert(1)', 'display', '' ),
			'db'                                => array( 'javascript:alert(1)', 'db', '' ),
			'raw is not escaped'                => array( 'javascript:alert(1)', 'raw', 'javascript:alert(1)' ),
		);
	}
}
