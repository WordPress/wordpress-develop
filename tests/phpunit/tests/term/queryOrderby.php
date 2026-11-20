<?php

/**
 * Tests for multiple 'orderby' fields in WP_Term_Query.
 *
 * @group taxonomy
 *
 * @covers WP_Term_Query::get_terms
 */
class Tests_Term_QueryOrderby extends WP_UnitTestCase {

	/**
	 * Term IDs, keyed by a short label.
	 *
	 * The terms are set up so that ordering by description alone leaves a tie
	 * that only a second field can break:
	 *
	 * - 'a': description 'x', name 'Bravo'.
	 * - 'b': description 'x', name 'Alpha'.
	 * - 'c': description 'w', name 'Charlie'.
	 *
	 * @var int[]
	 */
	private $terms = array();

	/**
	 * The SQL clauses passed to the 'terms_clauses' filter by the last query.
	 *
	 * @var array
	 */
	private $clauses = array();

	public function set_up() {
		parent::set_up();

		register_taxonomy( 'wptests_tax', 'post' );

		$this->terms['a'] = self::factory()->term->create(
			array(
				'taxonomy'    => 'wptests_tax',
				'name'        => 'Bravo',
				'description' => 'x',
			)
		);
		$this->terms['b'] = self::factory()->term->create(
			array(
				'taxonomy'    => 'wptests_tax',
				'name'        => 'Alpha',
				'description' => 'x',
			)
		);
		$this->terms['c'] = self::factory()->term->create(
			array(
				'taxonomy'    => 'wptests_tax',
				'name'        => 'Charlie',
				'description' => 'w',
			)
		);
	}

	/**
	 * Runs a term query for the test taxonomy and returns the found term IDs.
	 *
	 * @param array $args Query arguments.
	 * @return int[] Term IDs.
	 */
	private function get_term_ids( $args ) {
		$query = new WP_Term_Query();

		return $query->query(
			array_merge(
				array(
					'taxonomy'   => 'wptests_tax',
					'hide_empty' => false,
					'fields'     => 'ids',
				),
				$args
			)
		);
	}

	/**
	 * Callback for the 'terms_clauses' filter that stores the clauses.
	 *
	 * @param array $clauses Terms query SQL clauses.
	 * @return array Unchanged clauses.
	 */
	public function store_clauses( $clauses ) {
		$this->clauses = $clauses;
		return $clauses;
	}

	/**
	 * @ticket 45084
	 */
	public function test_space_separated_orderby_should_use_all_fields() {
		$found = $this->get_term_ids(
			array(
				'orderby' => 'description name',
				'order'   => 'ASC',
			)
		);

		$this->assertSame( array( $this->terms['c'], $this->terms['b'], $this->terms['a'] ), $found );
	}

	/**
	 * @ticket 45084
	 */
	public function test_space_separated_orderby_should_apply_order_to_all_fields() {
		$found = $this->get_term_ids(
			array(
				'orderby' => 'description name',
				'order'   => 'DESC',
			)
		);

		$this->assertSame( array( $this->terms['a'], $this->terms['b'], $this->terms['c'] ), $found );
	}

	/**
	 * @ticket 45084
	 */
	public function test_space_separated_orderby_should_ignore_extra_whitespace() {
		$found = $this->get_term_ids(
			array(
				'orderby' => '  description   name ',
				'order'   => 'ASC',
			)
		);

		$this->assertSame( array( $this->terms['c'], $this->terms['b'], $this->terms['a'] ), $found );
	}

	/**
	 * @ticket 45084
	 *
	 * @dataProvider data_array_orderby_should_use_order_per_field
	 *
	 * @param array    $orderby  The 'orderby' query var.
	 * @param string[] $expected Expected order of term labels.
	 */
	public function test_array_orderby_should_use_order_per_field( $orderby, $expected ) {
		$found = $this->get_term_ids(
			array(
				'orderby' => $orderby,
			)
		);

		$expected_ids = array();
		foreach ( $expected as $label ) {
			$expected_ids[] = $this->terms[ $label ];
		}

		$this->assertSame( $expected_ids, $found );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_orderby_should_use_order_per_field() {
		return array(
			'description ASC, name ASC'   => array(
				'orderby'  => array(
					'description' => 'ASC',
					'name'        => 'ASC',
				),
				'expected' => array( 'c', 'b', 'a' ),
			),
			'description ASC, name DESC'  => array(
				'orderby'  => array(
					'description' => 'ASC',
					'name'        => 'DESC',
				),
				'expected' => array( 'c', 'a', 'b' ),
			),
			'description DESC, name ASC'  => array(
				'orderby'  => array(
					'description' => 'DESC',
					'name'        => 'ASC',
				),
				'expected' => array( 'b', 'a', 'c' ),
			),
			'description DESC, name DESC' => array(
				'orderby'  => array(
					'description' => 'DESC',
					'name'        => 'DESC',
				),
				'expected' => array( 'a', 'b', 'c' ),
			),
			'lowercase directions'        => array(
				'orderby'  => array(
					'description' => 'desc',
					'name'        => 'asc',
				),
				'expected' => array( 'b', 'a', 'c' ),
			),
			'single field'                => array(
				'orderby'  => array(
					'name' => 'DESC',
				),
				'expected' => array( 'c', 'a', 'b' ),
			),
		);
	}

	/**
	 * @ticket 45084
	 */
	public function test_array_orderby_should_ignore_order_query_var() {
		$found = $this->get_term_ids(
			array(
				'orderby' => array(
					'description' => 'ASC',
					'name'        => 'DESC',
				),
				'order'   => 'DESC',
			)
		);

		$this->assertSame( array( $this->terms['c'], $this->terms['a'], $this->terms['b'] ), $found );
	}

	/**
	 * @ticket 45084
	 */
	public function test_list_of_fields_should_use_order_query_var() {
		$found = $this->get_term_ids(
			array(
				'orderby' => array( 'description', 'name' ),
				'order'   => 'DESC',
			)
		);

		$this->assertSame( array( $this->terms['a'], $this->terms['b'], $this->terms['c'] ), $found );
	}

	/**
	 * @ticket 45084
	 */
	public function test_empty_array_orderby_should_order_by_term_id() {
		$query = new WP_Term_Query(
			array(
				'taxonomy'   => 'wptests_tax',
				'hide_empty' => false,
				'fields'     => 'ids',
				'orderby'    => array(),
				'order'      => 'ASC',
			)
		);

		$this->assertStringContainsString( 'ORDER BY t.term_id ASC', $query->request );
	}

	/**
	 * @ticket 45084
	 */
	public function test_multiple_orderby_should_support_meta_value() {
		add_term_meta( $this->terms['a'], 'foo', '2' );
		add_term_meta( $this->terms['b'], 'foo', '1' );
		add_term_meta( $this->terms['c'], 'foo', '2' );

		$found = $this->get_term_ids(
			array(
				'meta_key' => 'foo',
				'orderby'  => array(
					'meta_value' => 'DESC',
					'name'       => 'ASC',
				),
			)
		);

		$this->assertSame( array( $this->terms['a'], $this->terms['c'], $this->terms['b'] ), $found );
	}

	/**
	 * @ticket 45084
	 */
	public function test_multiple_orderby_should_support_meta_query_clause_keys() {
		add_term_meta( $this->terms['a'], 'foo', 'aaa' );
		add_term_meta( $this->terms['a'], 'fee', 'zzz' );
		add_term_meta( $this->terms['b'], 'foo', 'aaa' );
		add_term_meta( $this->terms['b'], 'fee', 'aaa' );
		add_term_meta( $this->terms['c'], 'foo', 'jjj' );
		add_term_meta( $this->terms['c'], 'fee', 'jjj' );

		$found = $this->get_term_ids(
			array(
				'meta_query' => array(
					'relation' => 'AND',
					'foo_key'  => array(
						'key'     => 'foo',
						'compare' => 'EXISTS',
					),
					'fee_key'  => array(
						'key'     => 'fee',
						'compare' => 'EXISTS',
					),
				),
				'orderby'    => array(
					'foo_key' => 'ASC',
					'fee_key' => 'DESC',
				),
			)
		);

		$this->assertSame( array( $this->terms['a'], $this->terms['b'], $this->terms['c'] ), $found );
	}

	/**
	 * @ticket 45084
	 */
	public function test_multiple_orderby_should_support_include() {
		$include = array( $this->terms['b'], $this->terms['c'], $this->terms['a'] );

		$found = $this->get_term_ids(
			array(
				'include' => $include,
				'orderby' => 'include name',
			)
		);

		$this->assertSame( $include, $found );
	}

	/**
	 * @ticket 45084
	 */
	public function test_term_order_in_multiple_orderby_should_fall_back_to_term_id_without_object_ids() {
		$query = new WP_Term_Query(
			array(
				'taxonomy'   => 'wptests_tax',
				'hide_empty' => false,
				'fields'     => 'ids',
				'orderby'    => array(
					'term_order' => 'DESC',
					'name'       => 'ASC',
				),
			)
		);

		$this->assertStringNotContainsString( 'tr.term_order', $query->request );
		$this->assertStringContainsString( 'ORDER BY t.term_id DESC, t.name ASC', $query->request );
	}

	/**
	 * @ticket 45084
	 */
	public function test_term_order_in_multiple_orderby_should_be_used_with_object_ids() {
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array_values( $this->terms ), 'wptests_tax' );

		$query = new WP_Term_Query(
			array(
				'taxonomy'   => 'wptests_tax',
				'object_ids' => $post_id,
				'fields'     => 'ids',
				'orderby'    => 'term_order name',
				'order'      => 'ASC',
			)
		);

		$this->assertStringContainsString( 'ORDER BY tr.term_order ASC, t.name ASC', $query->request );
		$this->assertCount( 3, $query->terms );
	}

	/**
	 * @ticket 45084
	 */
	public function test_none_should_be_skipped_in_multiple_orderby() {
		$query = new WP_Term_Query(
			array(
				'taxonomy'   => 'wptests_tax',
				'hide_empty' => false,
				'fields'     => 'ids',
				'orderby'    => 'none name',
				'order'      => 'ASC',
			)
		);

		$this->assertStringContainsString( 'ORDER BY t.name ASC', $query->request );
	}

	/**
	 * @ticket 45084
	 */
	public function test_fields_resolving_to_the_same_column_should_only_be_used_once() {
		$query = new WP_Term_Query(
			array(
				'taxonomy'   => 'wptests_tax',
				'hide_empty' => false,
				'fields'     => 'ids',
				'orderby'    => array(
					'id'      => 'DESC',
					'term_id' => 'ASC',
				),
			)
		);

		$this->assertStringContainsString( 'ORDER BY t.term_id DESC', $query->request );
		$this->assertStringNotContainsString( 't.term_id ASC', $query->request );
	}

	/**
	 * @ticket 45084
	 */
	public function test_count_query_should_not_use_multiple_orderby() {
		$query = new WP_Term_Query();
		$count = $query->query(
			array(
				'taxonomy'   => 'wptests_tax',
				'hide_empty' => false,
				'fields'     => 'count',
				'orderby'    => 'description name',
			)
		);

		$this->assertSame( '3', $count );
		$this->assertStringNotContainsString( 'ORDER BY', $query->request );
	}

	/**
	 * A single field must produce the same 'orderby' and 'order' clauses as
	 * before multiple fields were supported, so 'terms_clauses' callbacks
	 * that modify them keep working.
	 *
	 * @ticket 45084
	 */
	public function test_single_orderby_should_keep_order_in_separate_clause() {
		add_filter( 'terms_clauses', array( $this, 'store_clauses' ) );

		$this->get_term_ids(
			array(
				'orderby' => 'name',
				'order'   => 'DESC',
			)
		);

		$this->assertSame( 'ORDER BY t.name', $this->clauses['orderby'], 'The orderby clause should not contain a direction.' );
		$this->assertSame( 'DESC', $this->clauses['order'], 'The order clause should hold the direction.' );
	}

	/**
	 * @ticket 45084
	 */
	public function test_multiple_orderby_should_keep_last_direction_in_separate_clause() {
		add_filter( 'terms_clauses', array( $this, 'store_clauses' ) );

		$this->get_term_ids(
			array(
				'orderby' => array(
					'description' => 'ASC',
					'name'        => 'DESC',
				),
			)
		);

		$this->assertSame( 'ORDER BY tt.description ASC, t.name', $this->clauses['orderby'], 'The orderby clause should hold all fields but the last direction.' );
		$this->assertSame( 'DESC', $this->clauses['order'], 'The order clause should hold the last direction.' );
	}

	/**
	 * @ticket 45084
	 */
	public function test_changing_order_in_terms_clauses_should_affect_last_field() {
		add_filter( 'terms_clauses', array( $this, 'store_clauses' ) );
		add_filter(
			'terms_clauses',
			static function ( $clauses ) {
				$clauses['order'] = 'ASC';
				return $clauses;
			},
			20
		);

		$found = $this->get_term_ids(
			array(
				'orderby' => array(
					'description' => 'ASC',
					'name'        => 'DESC',
				),
			)
		);

		$this->assertSame( array( $this->terms['c'], $this->terms['b'], $this->terms['a'] ), $found );
	}
}
