<?php

/**
 * Tests for sanitize_category_field().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::sanitize_category_field
 */
class Tests_Category_SanitizeCategoryField extends WP_UnitTestCase {

	/**
	 * Arbitrary category ID passed to the function.
	 *
	 * @var int
	 */
	const CATEGORY_ID = 123;

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
	 * @param string $field Category field name.
	 */
	private function record_filters( $field ) {
		$hooks = array(
			"edit_term_{$field}",
			"edit_category_{$field}",
			"pre_term_{$field}",
			"pre_category_{$field}",
			'pre_category_nicename',
			"term_{$field}_rss",
			"category_{$field}_rss",
			"term_{$field}",
			"category_{$field}",
		);

		foreach ( $hooks as $hook ) {
			add_filter(
				$hook,
				function ( ...$args ) use ( $hook ) {
					$this->calls[] = array( $hook, $args );

					return $args[0] . '|' . $hook;
				},
				10,
				4
			);
		}
	}

	/**
	 * Tests that integer fields are cast to non-negative integers across contexts.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_casts_integer_fields
	 *
	 * @param string $field    Category field name.
	 * @param mixed  $value    Field value.
	 * @param string $context  Sanitization context.
	 * @param int    $expected Expected sanitized integer.
	 */
	public function test_casts_integer_fields( $field, $value, $context, $expected ) {
		$this->assertSame( $expected, sanitize_category_field( $field, $value, self::CATEGORY_ID, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_casts_integer_fields() {
		return array(
			'parent, raw'             => array( 'parent', '12abc', 'raw', 12 ),
			'parent, negative value'  => array( 'parent', -5, 'raw', 0 ),
			'term_id, display'        => array( 'term_id', '5', 'display', 5 ),
			'count, edit'             => array( 'count', '6', 'edit', 6 ),
			'count, negative string'  => array( 'count', '-10', 'display', 0 ),
			'term_group, attribute'   => array( 'term_group', '7', 'attribute', 7 ),
			'term_taxonomy_id, db'    => array( 'term_taxonomy_id', '8', 'db', 8 ),
			'object_id, js'           => array( 'object_id', '9', 'js', 9 ),
			'object_id, empty string' => array( 'object_id', '', 'display', 0 ),
		);
	}

	/**
	 * Tests that the raw context (case-insensitive) returns non-integer values unfiltered.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_raw_contexts
	 *
	 * @param string $context Raw context variation.
	 */
	public function test_raw_context_returns_value_unfiltered( $context ) {
		$this->record_filters( 'foo' );

		$this->assertSame( self::MARKUP, sanitize_category_field( 'foo', self::MARKUP, self::CATEGORY_ID, $context ) );
		$this->assertSame( array(), $this->calls, 'No filter should run in the raw context.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_raw_contexts() {
		return array(
			'lowercase raw' => array( 'raw' ),
			'uppercase RAW' => array( 'RAW' ),
			'mixed Raw'     => array( 'Raw' ),
		);
	}

	/**
	 * Tests the filters that run for each context and the arguments they receive.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_filters_for_context
	 *
	 * @param string  $context        Sanitization context.
	 * @param string  $expected_value Expected return value.
	 * @param array[] $expected_calls Expected filter calls.
	 */
	public function test_applies_filters_for_context( $context, $expected_value, $expected_calls ) {
		$this->record_filters( 'foo' );

		$this->assertSame( $expected_value, sanitize_category_field( 'foo', 'value', self::CATEGORY_ID, $context ) );
		$this->assertSame( $expected_calls, $this->calls );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_filters_for_context() {
		return array(
			'edit'                                 => array(
				'edit',
				'value|edit_term_foo|edit_category_foo',
				array(
					array( 'edit_term_foo', array( 'value', self::CATEGORY_ID, 'category' ) ),
					array( 'edit_category_foo', array( 'value|edit_term_foo', self::CATEGORY_ID ) ),
				),
			),
			'db'                                   => array(
				'db',
				'value|pre_term_foo|pre_category_foo',
				array(
					array( 'pre_term_foo', array( 'value', 'category' ) ),
					array( 'pre_category_foo', array( 'value|pre_term_foo' ) ),
				),
			),
			'rss'                                  => array(
				'rss',
				'value|term_foo_rss|category_foo_rss',
				array(
					array( 'term_foo_rss', array( 'value', 'category' ) ),
					array( 'category_foo_rss', array( 'value|term_foo_rss' ) ),
				),
			),
			'display'                              => array(
				'display',
				'value|term_foo|category_foo',
				array(
					array( 'term_foo', array( 'value', self::CATEGORY_ID, 'category', 'display' ) ),
					array( 'category_foo', array( 'value|term_foo', self::CATEGORY_ID, 'display' ) ),
				),
			),
			'attribute uses display filters'       => array(
				'attribute',
				'value|term_foo|category_foo',
				array(
					array( 'term_foo', array( 'value', self::CATEGORY_ID, 'category', 'attribute' ) ),
					array( 'category_foo', array( 'value|term_foo', self::CATEGORY_ID, 'attribute' ) ),
				),
			),
			'js uses display filters'              => array(
				'js',
				'value|term_foo|category_foo',
				array(
					array( 'term_foo', array( 'value', self::CATEGORY_ID, 'category', 'js' ) ),
					array( 'category_foo', array( 'value|term_foo', self::CATEGORY_ID, 'js' ) ),
				),
			),
			'unknown context uses display filters' => array(
				'other',
				'value|term_foo|category_foo',
				array(
					array( 'term_foo', array( 'value', self::CATEGORY_ID, 'category', 'other' ) ),
					array( 'category_foo', array( 'value|term_foo', self::CATEGORY_ID, 'other' ) ),
				),
			),
		);
	}

	/**
	 * Tests that sanitizing the 'slug' field in the 'db' context also applies the
	 * backwards-compatible 'pre_category_nicename' filter.
	 *
	 * @ticket 65819
	 */
	public function test_db_context_slug_applies_pre_category_nicename_filter() {
		remove_all_filters( 'pre_term_slug' );
		$this->record_filters( 'slug' );

		$result = sanitize_category_field( 'slug', 'my-slug', self::CATEGORY_ID, 'db' );

		$this->assertSame( 'my-slug|pre_term_slug|pre_category_slug|pre_category_nicename', $result );
		$this->assertSame(
			array(
				array( 'pre_term_slug', array( 'my-slug', 'category' ) ),
				array( 'pre_category_slug', array( 'my-slug|pre_term_slug' ) ),
				array( 'pre_category_nicename', array( 'my-slug|pre_term_slug|pre_category_slug' ) ),
			),
			$this->calls
		);
	}

	/**
	 * Tests the escaping applied in each context.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_escaping_for_context
	 *
	 * @param string $field    Category field name.
	 * @param string $context  Sanitization context.
	 * @param string $expected Expected escaped value.
	 */
	public function test_escapes_value_for_context( $field, $context, $expected ) {
		$this->assertSame( $expected, sanitize_category_field( $field, self::MARKUP, self::CATEGORY_ID, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_escaping_for_context() {
		return array(
			'edit non-description uses esc_attr' => array( 'foo', 'edit', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;' ),
			'edit description uses esc_html'     => array( 'description', 'edit', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;' ),
			'attribute uses esc_attr'            => array( 'foo', 'attribute', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; &#039;single&#039;' ),
			'js uses esc_js'                     => array( 'foo', 'js', '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;double&quot; \\\'single\\\'' ),
			'db does not escape'                 => array( 'foo', 'db', self::MARKUP ),
			'rss does not escape'                => array( 'foo', 'rss', self::MARKUP ),
			'display does not escape'            => array( 'foo', 'display', self::MARKUP ),
		);
	}

	/**
	 * Tests that the 'description' field is escaped with esc_html() in the edit context,
	 * and other fields with esc_attr().
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_edit_escaping_function
	 *
	 * @param string $field  Category field name.
	 * @param string $filter Filter run by the expected escaping function.
	 */
	public function test_edit_context_escaping_function( $field, $filter ) {
		$escaping_filters = array(
			'esc_html'         => new MockAction(),
			'attribute_escape' => new MockAction(),
		);

		foreach ( $escaping_filters as $hook => $mock ) {
			add_filter( $hook, array( $mock, 'filter' ) );
		}

		sanitize_category_field( $field, self::MARKUP, self::CATEGORY_ID, 'edit' );

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
			'description' => array( 'description', 'esc_html' ),
			'other field' => array( 'foo', 'attribute_escape' ),
		);
	}
}
