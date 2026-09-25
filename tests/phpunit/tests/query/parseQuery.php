<?php

/**
 * @group query
 */
class Tests_Query_ParseQuery extends WP_UnitTestCase {
	/**
	 * Data provider for test_parse_query_s_type.
	 *
	 * @return array[]
	 */
	public function data_parse_query_s_types() {
		return array(
			'array input returns empty string' => array( array( 'foo' ), '' ),
			'string input returns string'      => array( 'foo', 'foo' ),
			'float input returns float'        => array( 3.5, 3.5 ),
			'int input returns int'            => array( 3, 3 ),
			'bool input returns bool'          => array( true, true ),
		);
	}

	/**
	 * Tests that WP_Query::parse_query() handles various types for the 's' parameter.
	 *
	 * @ticket 29736
	 *
	 * @dataProvider data_parse_query_s_types
	 *
	 * @param mixed $input    The value to pass as 's'.
	 * @param mixed $expected The expected value of query_vars['s'].
	 */
	public function test_parse_query_s_type( $input, $expected ) {
		$q = new WP_Query();
		$q->parse_query(
			array(
				's' => $input,
			)
		);

		$this->assertSame( $expected, $q->query_vars['s'] );
	}

	/**
	 * @ticket 33372
	 */
	public function test_parse_query_p_negative_int() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'p' => -3,
			)
		);

		$this->assertSame( '404', $q->query_vars['error'] );
	}

	/**
	 * @ticket 33372
	 */
	public function test_parse_query_p_array() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'p' => array(),
			)
		);

		$this->assertSame( '404', $q->query_vars['error'] );
	}

	/**
	 * @ticket 33372
	 */
	public function test_parse_query_p_object() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'p' => new stdClass(),
			)
		);

		$this->assertSame( '404', $q->query_vars['error'] );
	}

	/**
	 * Ensure an array of authors is rejected.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_author_array() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'author' => array( 1, 2, 3 ),
			)
		);

		$this->assertEmpty( $q->query_vars['author'] );
	}

	/**
	 * Ensure a non-scalar (non-numeric) author value is rejected.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_author_string() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'author' => 'admin',
			)
		);

		$this->assertEmpty( $q->query_vars['author'] );
	}

	/**
	 * Ensure nonscalar 'cat' array values are rejected.
	 *
	 * Note the returned 'cat' query_var value is a string.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_cat_array_mixed() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'cat' => array( 1, 'uncategorized', '-1' ),
			)
		);

		$this->assertSame( '-1,1', $q->query_vars['cat'] );
	}

	/**
	 * Ensure a nonscalar menu_order value is rejected.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_menu_order_nonscalar() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'menu_order' => array( 1 ),
			)
		);

		$this->assertEmpty( $q->query_vars['menu_order'] );
	}

	/**
	 * Ensure numeric 'subpost' gets assigned to 'attachment'.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_subpost_scalar() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'subpost' => 1,
			)
		);

		$this->assertSame( 1, $q->query_vars['attachment'] );
	}

	/**
	 * Ensure non-scalar 'subpost' does not get assigned to 'attachment'.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_subpost_nonscalar() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'subpost' => array( 1 ),
			)
		);

		$this->assertEmpty( $q->query_vars['attachment'] );
	}

	/**
	 * Ensure numeric 'attachment_id' value is assigned.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_attachment_id() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'attachment_id' => 1,
			)
		);

		$this->assertSame( 1, $q->query_vars['attachment_id'] );
	}

	/**
	 * Ensure non-scalar 'attachment_id' value is rejected.
	 *
	 * @ticket 17737
	 */
	public function test_parse_query_attachment_id_nonscalar() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				'attachment_id' => array( 1 ),
			)
		);

		$this->assertEmpty( $q->query_vars['attachment_id'] );
	}

	/**
	 * Tests that a fatal error is not thrown when a hierarchical taxonomy query var
	 * passed to wp_basename() in ::parse_tax_query() is an array instead of a string.
	 *
	 * The message that we should not see:
	 * `TypeError: urldecode(): Argument #1 ($string) must be of type string, array given`.
	 *
	 * @ticket 64870
	 */
	public function test_parse_query_hierarchical_taxonomy_query_var_array() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'query_var' => 'wptests_tax',
				'rewrite'   => array( 'hierarchical' => true ),
				'public'    => true,
			)
		);

		$q = new WP_Query(
			array(
				'wptests_tax' => array( 'term-a', 'term-b' ),
			)
		);

		unregister_taxonomy( 'wptests_tax' );

		$this->assertIsArray( $q->posts );
	}

	/**
	 * Data provider of string-only query vars and a representative valid value.
	 *
	 * @ticket 64507
	 *
	 * @return array[]
	 */
	public function data_string_query_vars() {
		return array(
			'author_name' => array( 'author_name', 'johndoe' ),
			'feed'        => array( 'feed', 'rss2' ),
			'attachment'  => array( 'attachment', 'my-image' ),
		);
	}

	/**
	 * Ensure that string query vars pass through parse_query() unchanged.
	 *
	 * @ticket 64507
	 * @dataProvider data_string_query_vars
	 *
	 * @param string $query_var Query var name.
	 * @param string $value     Valid string value.
	 */
	public function test_parse_query_string_var_string_value( $query_var, $value ) {
		$q = new WP_Query();
		$q->parse_query( array( $query_var => $value ) );

		$this->assertSame( $value, $q->query_vars[ $query_var ] );
	}

	/**
	 * Ensure that array values for string-only query vars are rejected (returns ''),
	 * preventing a TypeError fatal from str_contains() / str_replace() / wp_basename()
	 * on PHP 8+.
	 *
	 * @ticket 64507
	 * @dataProvider data_string_query_vars
	 *
	 * @param string $query_var Query var name.
	 */
	public function test_parse_query_string_var_array_value( $query_var ) {
		$q = new WP_Query();
		$q->parse_query( array( $query_var => array( 'unexpected', 'array' ) ) );

		$this->assertSame( '', $q->query_vars[ $query_var ] );
	}
}
