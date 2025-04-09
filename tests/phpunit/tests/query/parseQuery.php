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

	/**
	 * Test that WP_Query::get() returns the value as passed.
	 *
	 * @ticket 63255
	 * @dataProvider data_query_var_getter_returns_as_passed
	 *
	 * @param string $query_var      The query variable.
	 * @param mixed  $query_var_value The value to set for the query variable.
	 */
	public function test_query_var_getter_returns_as_passed( $query_var, $query_var_value ) {
		$query = new WP_Query();
		$query->parse_query(
			array(
				$query_var => $query_var_value,
			)
		);

		$this->assertSame( $query_var_value, $query->get( $query_var ), 'Query variable getter should type and order in which it was passed' );
	}

	/**
	 * Data provider for test_query_var_getter_returns_as_passed.
	 *
	 * @return array[] Data provider.
	 */
	public function data_query_var_getter_returns_as_passed() {
		return array(
			'post type, string'               => array( 'post_type', 'post' ),
			'post type, DESC array'           => array( 'post_type', array( 'post', 'page' ) ),
			'post type, ASC array'            => array( 'post_type', array( 'page', 'post' ) ),
			'post type, duplicate array'      => array( 'post_type', array( 'post', 'post' ) ),
			'post status, string'             => array( 'post_status', 'publish' ),
			'post status, DESC array'         => array( 'post_status', array( 'publish', 'draft' ) ),
			'post status, ASC array'          => array( 'post_status', array( 'draft', 'publish' ) ),
			'post status, duplicate array'    => array( 'post_status', array( 'draft', 'draft' ) ),

			'post_name__in, string'           => array( 'post_name__in', 'elphaba' ),
			'post_name__in, DESC array'       => array( 'post_name__in', array( 'the-wizard-of-oz', 'glinda', 'doctor-dillamond', 'elphaba' ) ),
			'post_name__in, ASC array'        => array( 'post_name__in', array( 'elphaba', 'doctor-dillamond', 'glinda', 'the-wizard-of-oz' ) ),
			'post_name__in, array dupes'      => array( 'post_name__in', array( 'elphaba', 'doctor-dillamond', 'elphaba', 'doctor-dillamond' ) ),

			'category__in, int[] ASC'         => array( 'category__in', array( 1, 2 ) ),
			'category__in, int[] DESC'        => array( 'category__in', array( 2, 1 ) ),

			'post id, int'                    => array( 'p', 1 ),
			'page_id, int'                    => array( 'page_id', 1 ),
			'attachment_id, int'              => array( 'page_id', 1 ),
			'offset, string'                  => array( 'offset', '5' ),
			'offset, int'                     => array( 'offset', 5 ),

			'post__in, string[] ASC'          => array( 'post__in', array( '1', '2' ) ),
			'post__in, string[] DESC'         => array( 'post__in', array( '2', '1' ) ),
			'post__in, int[] ASC'             => array( 'post__in', array( 1, 2 ) ),
			'post__in, int[] DESC'            => array( 'post__in', array( 2, 1 ) ),
			'post__in, int[] duplicate'       => array( 'post__in', array( 1, 1 ) ),

			'post__not_in, string[] ASC'      => array( 'post__not_in', array( '1', '2' ) ),
			'post__not_in, string[] DESC'     => array( 'post__not_in', array( '2', '1' ) ),
			'post__not_in, int[] ASC'         => array( 'post__not_in', array( 1, 2 ) ),
			'post__not_in, int[] DESC'        => array( 'post__not_in', array( 2, 1 ) ),
			'post__not_in, int[] duplicate'   => array( 'post__not_in', array( 1, 1 ) ),

			'author__in, string[] ASC'        => array( 'author__in', array( '1', '2' ) ),
			'author__in, string[] DESC'       => array( 'author__in', array( '2', '1' ) ),
			'author__in, int[] ASC'           => array( 'author__in', array( 1, 2 ) ),
			'author__in, int[] DESC'          => array( 'author__in', array( 2, 1 ) ),
			'author__in, int[] duplicate'     => array( 'author__in', array( 1, 1 ) ),

			'author__not_in, string[] ASC'    => array( 'author__not_in', array( '1', '2' ) ),
			'author__not_in, string[] DESC'   => array( 'author__not_in', array( '2', '1' ) ),
			'author__not_in, int[] ASC'       => array( 'author__not_in', array( 1, 2 ) ),
			'author__not_in, int[] DESC'      => array( 'author__not_in', array( 2, 1 ) ),
			'author__not_in, int[] duplicate' => array( 'author__not_in', array( 1, 1 ) ),

			'tag_slug__in, string[] ASC'      => array( 'tag_slug__in', array( 'bobby', 'hans', 'herman', 'victor' ) ),
			'tag_slug__in, string[] DESC'     => array( 'tag_slug__in', array( 'victor', 'herman', 'hans', 'bobby' ) ),

			'tag__in, int[] ASC'              => array( 'tag__in', array( 1, 2 ) ),
			'tag__in, int[] DESC'             => array( 'tag__in', array( 2, 1 ) ),

			'tag__not_in, int[] ASC'          => array( 'tag__not_in', array( 1, 2 ) ),
			'tag__not_in, int[] DESC'         => array( 'tag__not_in', array( 2, 1 ) ),
		);
	}
}
