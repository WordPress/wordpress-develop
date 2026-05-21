<?php

/**
 * @group taxonomy
 */
class Tests_Term_WpTerm extends WP_UnitTestCase {
	protected static $term_id;
	protected static $shared_terms = array();

	public function set_up() {
		parent::set_up();
		register_taxonomy( 'wptests_tax', 'post' );
		register_taxonomy( 'wptests_tax_2', 'post' );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		register_taxonomy( 'wptests_tax', 'post' );
		register_taxonomy( 'wptests_tax_2', 'post' );

		// Ensure that there is a term with ID 1.
		if ( ! get_term( 1 ) ) {
			$wpdb->insert(
				$wpdb->terms,
				array(
					'term_id' => 1,
				)
			);

			$wpdb->insert(
				$wpdb->term_taxonomy,
				array(
					'term_id'  => 1,
					'taxonomy' => 'wptests_tax',
				)
			);

			clean_term_cache( 1, 'wptests_tax' );
		}

		self::$term_id      = $factory->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		self::$shared_terms = self::generate_shared_terms();
	}

	/**
	 * Utility function for generating two shared terms, in the 'wptests_tax' and 'wptests_tax_2' taxonomies.
	 *
	 * @return array Array of term_id/old_term_id/term_taxonomy_id triplets.
	 */
	protected static function generate_shared_terms() {
		global $wpdb;

		$term_1 = wp_insert_term( 'Foo', 'wptests_tax' );
		$term_2 = wp_insert_term( 'Foo', 'wptests_tax_2' );

		// Manually modify because shared terms shouldn't naturally occur.
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'term_id' => $term_1['term_id'] ),
			array( 'term_taxonomy_id' => $term_2['term_taxonomy_id'] ),
			array( '%d' ),
			array( '%d' )
		);

		clean_term_cache( $term_1['term_id'] );

		return array(
			array(
				'term_id'          => $term_1['term_id'],
				'old_term_id'      => $term_1['term_id'],
				'term_taxonomy_id' => $term_1['term_taxonomy_id'],
			),
			array(
				'term_id'          => $term_1['term_id'],
				'old_term_id'      => $term_2['term_id'],
				'term_taxonomy_id' => $term_2['term_taxonomy_id'],
			),
		);
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_work_for_numeric_string() {
		$found = WP_Term::get_instance( (string) self::$term_id );

		$this->assertSame( self::$term_id, $found->term_id );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_negative_number() {
		$found = WP_Term::get_instance( -self::$term_id );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_non_numeric_string() {
		$found = WP_Term::get_instance( 'abc' );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_succeed_for_float_that_is_equal_to_post_id() {
		$found = WP_Term::get_instance( 1.0 );

		$this->assertSame( 1, $found->term_id );
	}

	/**
	 * @ticket 40671
	 */
	public function test_get_instance_should_respect_taxonomy_when_term_id_is_found_in_cache() {
		global $wpdb;

		register_taxonomy( 'wptests_tax2', 'post' );

		// Ensure that cache is primed.
		WP_Term::get_instance( self::$term_id, 'wptests_tax' );

		$found = WP_Term::get_instance( self::$term_id, 'wptests_tax2' );
		$this->assertFalse( $found );
	}

	/**
	 * @ticket 50568
	 */
	public function test_filter_should_return_same_instance_when_context_matches() {
		$term = WP_Term::get_instance( self::$term_id );

		$this->assertSame( 'raw', $term->filter );
		$this->assertSame( $term, $term->filter( 'raw' ) );
	}

	/**
	 * @ticket 50568
	 */
	public function test_filter_raw_should_return_raw_instance_from_display_filtered_term() {
		$term         = WP_Term::get_instance( self::$term_id );
		$display_term = $term->filter( 'display' );

		$this->assertSame( 'display', $display_term->filter );

		$raw_term = $display_term->filter( 'raw' );

		$this->assertInstanceOf( 'WP_Term', $raw_term );
		$this->assertSame( 'raw', $raw_term->filter );
		$this->assertNotSame( $display_term, $raw_term );
	}

	/**
	 * @ticket 50568
	 */
	public function test_filter_raw_should_use_taxonomy_to_disambiguate_shared_terms() {
		$terms = self::$shared_terms;

		$display_term = get_term( $terms[0]['term_id'], 'wptests_tax', OBJECT, 'display' );

		$this->assertInstanceOf( 'WP_Term', $display_term );
		$this->assertSame( 'display', $display_term->filter );
		$this->assertSame( 'wptests_tax', $display_term->taxonomy );

		$raw_term = $display_term->filter( 'raw' );

		$this->assertInstanceOf( 'WP_Term', $raw_term );
		$this->assertSame( 'raw', $raw_term->filter );
		$this->assertSame( 'wptests_tax', $raw_term->taxonomy );
		$this->assertSame( $terms[0]['term_taxonomy_id'], $raw_term->term_taxonomy_id );
	}
}
