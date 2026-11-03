<?php

/**
 * Tests for sanitize_bookmark_field() and sanitize_bookmark().
 *
 * @group bookmark
 */
class Tests_Bookmark_SanitizeBookmarkField extends WP_UnitTestCase {

	/**
	 * Arbitrary bookmark ID passed to the function, which never looks the bookmark up.
	 *
	 * @var int
	 */
	const BOOKMARK_ID = 123;

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
	 * @param string $field Bookmark field name.
	 */
	private function record_filters( $field ) {
		foreach ( array( "edit_{$field}", "pre_{$field}", $field ) as $hook ) {
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
	 * Tests that integer fields are cast to integers in every context.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 *
	 * @dataProvider data_casts_integer_fields
	 *
	 * @param string $field    Bookmark field name.
	 * @param mixed  $value    Field value.
	 * @param string $context  Sanitization context.
	 * @param int    $expected Expected value.
	 */
	public function test_casts_integer_fields( $field, $value, $context, $expected ) {
		$this->assertSame( $expected, sanitize_bookmark_field( $field, $value, self::BOOKMARK_ID, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_casts_integer_fields() {
		return array(
			'link_id, raw'       => array( 'link_id', '12abc', 'raw', 12 ),
			'link_id, attribute' => array( 'link_id', '7', 'attribute', 7 ),
			'link_rating, js'    => array( 'link_rating', '9', 'js', 9 ),
		);
	}

	/**
	 * Tests that link categories are cast to an array of absolute integers without being filtered.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 *
	 * @dataProvider data_link_category_values
	 *
	 * @param mixed  $value    Field value.
	 * @param string $context  Sanitization context.
	 * @param int[]  $expected Expected value.
	 */
	public function test_link_category_is_array_of_integers( $value, $context, $expected ) {
		$this->record_filters( 'link_category' );

		$this->assertSame( $expected, sanitize_bookmark_field( 'link_category', $value, self::BOOKMARK_ID, $context ) );
		$this->assertSame( array(), $this->calls, 'No filter should run for link categories.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_category_values() {
		return array(
			'array of mixed values, display' => array( array( '1', '-2', 'abc', 3 ), 'display', array( 1, 2, 0, 3 ) ),
			'single value, edit'             => array( '5', 'edit', array( 5 ) ),
			'empty string, raw'              => array( '', 'raw', array( 0 ) ),
		);
	}

	/**
	 * Tests that the visibility field only keeps Y and N characters, in every context.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 *
	 * @dataProvider data_link_visible_values
	 *
	 * @param string $value    Field value.
	 * @param string $expected Expected value.
	 */
	public function test_link_visible_keeps_only_y_and_n( $value, $expected ) {
		$this->assertSame( $expected, sanitize_bookmark_field( 'link_visible', $value, self::BOOKMARK_ID, 'raw' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_visible_values() {
		return array(
			'uppercase Y'     => array( 'Y', 'Y' ),
			'lowercase n'     => array( 'n', 'n' ),
			'word with a Y'   => array( 'Yes', 'Y' ),
			'markup'          => array( '<b>N</b>', 'N' ),
			'other character' => array( 'x', '' ),
		);
	}

	/**
	 * Tests that the target field only accepts _top and _blank, in every context.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 *
	 * @dataProvider data_link_target_values
	 *
	 * @param string $value    Field value.
	 * @param string $expected Expected value.
	 */
	public function test_link_target_only_accepts_known_targets( $value, $expected ) {
		$this->assertSame( $expected, sanitize_bookmark_field( 'link_target', $value, self::BOOKMARK_ID, 'raw' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_target_values() {
		return array(
			'_blank'    => array( '_blank', '_blank' ),
			'_top'      => array( '_top', '_top' ),
			'_self'     => array( '_self', '' ),
			'uppercase' => array( '_BLANK', '' ),
		);
	}

	/**
	 * Tests that the raw context returns the value without filtering or escaping it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 */
	public function test_raw_context_returns_value_unchanged() {
		$this->record_filters( 'link_foo' );

		$this->assertSame( self::MARKUP, sanitize_bookmark_field( 'link_foo', self::MARKUP, self::BOOKMARK_ID, 'raw' ) );
		$this->assertSame( array(), $this->calls, 'No filter should run.' );
	}

	/**
	 * Tests the filter that runs for each context, and the arguments it receives.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 *
	 * @dataProvider data_filters_for_context
	 *
	 * @param string $context       Sanitization context.
	 * @param string $hook          Expected hook name.
	 * @param array  $expected_args Expected filter arguments.
	 */
	public function test_applies_filter_for_context( $context, $hook, $expected_args ) {
		$this->record_filters( 'link_foo' );

		$this->assertSame( 'value|' . $hook, sanitize_bookmark_field( 'link_foo', 'value', self::BOOKMARK_ID, $context ) );
		$this->assertSame( array( array( $hook, $expected_args ) ), $this->calls, 'Only the expected filter should run.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_filters_for_context() {
		return array(
			'edit'                                 => array( 'edit', 'edit_link_foo', array( 'value', self::BOOKMARK_ID ) ),
			'db'                                   => array( 'db', 'pre_link_foo', array( 'value' ) ),
			'display'                              => array( 'display', 'link_foo', array( 'value', self::BOOKMARK_ID, 'display' ) ),
			'attribute uses display filters'       => array( 'attribute', 'link_foo', array( 'value', self::BOOKMARK_ID, 'attribute' ) ),
			'js uses display filters'              => array( 'js', 'link_foo', array( 'value', self::BOOKMARK_ID, 'js' ) ),
			'unknown context uses display filters' => array( 'other', 'link_foo', array( 'value', self::BOOKMARK_ID, 'other' ) ),
		);
	}

	/**
	 * Tests the escaping applied in each context.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 *
	 * @dataProvider data_escaping_for_context
	 *
	 * @param string $context  Sanitization context.
	 * @param string $expected Expected value.
	 */
	public function test_escapes_value_for_context( $context, $expected ) {
		$this->assertSame( $expected, sanitize_bookmark_field( 'link_foo', self::MARKUP, self::BOOKMARK_ID, $context ) );
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
	 * Tests that the notes field is escaped with esc_html() in the edit context,
	 * and other fields with esc_attr().
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark_field
	 *
	 * @dataProvider data_edit_escaping_function
	 *
	 * @param string $field    Bookmark field name.
	 * @param string $filter   Filter run by the expected escaping function.
	 * @param string $expected Expected escaped and filtered value.
	 */
	public function test_edit_context_escaping_function( $field, $filter, $expected ) {
		// Isolates the escaping from the default filters on the link_notes hooks.
		remove_all_filters( "edit_{$field}" );

		$escaping_filters = array(
			'esc_html'         => new MockAction(),
			'attribute_escape' => new MockAction(),
		);

		foreach ( $escaping_filters as $hook => $mock ) {
			add_filter( $hook, array( $mock, 'filter' ) );
		}
		add_filter(
			'esc_html',
			static function ( $escaped ) {
				return $escaped . '|html';
			},
			20
		);
		add_filter(
			'attribute_escape',
			static function ( $escaped ) {
				return $escaped . '|attribute';
			},
			20
		);

		$result = sanitize_bookmark_field( $field, self::MARKUP, self::BOOKMARK_ID, 'edit' );

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
			'link_notes'  => array( 'link_notes', 'esc_html', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;|html' ),
			'other field' => array( 'link_foo', 'attribute_escape', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;|attribute' ),
		);
	}

	/**
	 * Tests that sanitize_bookmark() sanitizes each known field of an object or array.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark
	 *
	 * @dataProvider data_bookmark_types
	 *
	 * @param bool $as_object Whether to pass the bookmark as an object.
	 */
	public function test_sanitize_bookmark_sanitizes_known_fields( $as_object ) {
		$bookmark = array(
			'link_id'       => '42',
			'link_target'   => '_self',
			'link_visible'  => 'Yes',
			'link_category' => array( '3', '-4' ),
			'link_rating'   => '7 stars',
			'unknown_field' => '<b>kept</b>',
		);

		$expected = array(
			'link_id'       => 42,
			'link_target'   => '',
			'link_visible'  => 'Y',
			'link_category' => array( 3, 4 ),
			'link_rating'   => 7,
			'unknown_field' => '<b>kept</b>',
		);

		if ( $as_object ) {
			$sanitized = sanitize_bookmark( (object) $bookmark, 'raw' );

			$this->assertIsObject( $sanitized, 'An object should be returned for an object.' );
			$this->assertSame( $expected, get_object_vars( $sanitized ) );
		} else {
			$this->assertSame( $expected, sanitize_bookmark( $bookmark, 'raw' ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_bookmark_types() {
		return array(
			'object' => array( true ),
			'array'  => array( false ),
		);
	}

	/**
	 * Tests that sanitize_bookmark() passes the bookmark ID and the context to the field filters.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark
	 *
	 * @dataProvider data_bookmark_types
	 *
	 * @param bool $as_object Whether to pass the bookmark as an object.
	 */
	public function test_sanitize_bookmark_passes_id_and_context_to_filters( $as_object ) {
		// Isolates the recorded call from the default filters on the link_rel hook.
		remove_all_filters( 'link_rel' );

		$filter = new MockAction();
		add_filter( 'link_rel', array( $filter, 'filter' ), 10, 3 );

		$bookmark = array(
			'link_id'  => 42,
			'link_rel' => 'friend',
		);

		sanitize_bookmark( $as_object ? (object) $bookmark : $bookmark, 'attribute' );

		$this->assertSame( array( array( 'friend', 42, 'attribute' ) ), $filter->get_args() );
	}

	/**
	 * Tests that sanitize_bookmark() does not add fields that are not set.
	 *
	 * @ticket 65819
	 *
	 * @covers ::sanitize_bookmark
	 */
	public function test_sanitize_bookmark_does_not_add_missing_fields() {
		$this->assertSame( array( 'link_id' => 5 ), sanitize_bookmark( array( 'link_id' => '5' ) ) );
	}
}
