<?php

/**
 * @group query
 */
class Tests_Query_ParseQuery extends WP_UnitTestCase {
	/**
	 * @ticket 29736
	 */
	public function test_parse_query_s_array() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				's' => array( 'foo' ),
			)
		);

		$this->assertSame( '', $q->query_vars['s'] );
	}

	public function test_parse_query_s_string() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				's' => 'foo',
			)
		);

		$this->assertSame( 'foo', $q->query_vars['s'] );
	}

	public function test_parse_query_s_float() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				's' => 3.5,
			)
		);

		$this->assertSame( 3.5, $q->query_vars['s'] );
	}

	public function test_parse_query_s_int() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				's' => 3,
			)
		);

		$this->assertSame( 3, $q->query_vars['s'] );
	}

	public function test_parse_query_s_bool() {
		$q = new WP_Query();
		$q->parse_query(
			array(
				's' => true,
			)
		);

		$this->assertTrue( $q->query_vars['s'] );
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

		$this->assertSame( '1,-1', $q->query_vars['cat'] );
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
}
