<?php

/**
 * @group taxonomy
 */
class Tests_Term_WpTerm extends WP_UnitTestCase {
	protected static $term_id;

	/**
	 * @var string[]
	 */
	protected static $int_fields = array(
		'parent',
		'term_id',
		'count',
		'term_group',
		'term_taxonomy_id',
		'object_id',
	);

	public function set_up() {
		parent::set_up();
		register_taxonomy( 'wptests_tax', 'post' );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		register_taxonomy( 'wptests_tax', 'post' );

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

		self::$term_id = $factory->term->create( array( 'taxonomy' => 'wptests_tax' ) );
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
	 * @ticket 58329
	 */
	public function test_get_instance_no_sanitize() {
		$obj           = new stdClass();
		$obj->taxonomy = 'wptests_tax';
		foreach ( self::$int_fields as $int_field ) {
			$obj->$int_field = 'test';
		}
		wp_cache_add( 999, $obj, 'terms' );
		$term = WP_Term::get_instance( 999, 'wptests_tax', false );
		foreach ( self::$int_fields as $int_field ) {
			$this->assertIsInt( $term->$int_field );
		}
	}

	/**
	 * @ticket 58329
	 */
	public function test_get_instance_sanitize() {
		$obj           = new stdClass();
		$obj->taxonomy = 'wptests_tax';
		foreach ( self::$int_fields as $int_field ) {
			$obj->$int_field = 'test';
		}
		wp_cache_add( 999, $obj, 'terms' );
		$term = WP_Term::get_instance( 999, 'wptests_tax' );
		foreach ( self::$int_fields as $int_field ) {
			$this->assertIsInt( $term->$int_field );
		}
	}
}
