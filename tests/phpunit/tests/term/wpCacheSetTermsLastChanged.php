<?php

/**
 * Tests for the wp_cache_set_terms_last_changed() function.
 *
 * @group taxonomy
 * @group cache
 *
 * @covers ::wp_cache_set_terms_last_changed
 */
class Tests_Term_WpCacheSetTermsLastChanged extends WP_UnitTestCase {

	/**
	 * A term used across the tests.
	 *
	 * @var int
	 */
	protected static $term_id;

	/**
	 * Creates a shared term before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$term_id = $factory->term->create( array( 'taxonomy' => 'category' ) );
	}

	/**
	 * Adding term meta should update the 'terms' and 'term-meta'
	 * last changed values but leave 'term-queries' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_term_meta_action_updates_terms_and_term_meta() {
		$terms_before        = wp_cache_get_last_changed( 'terms' );
		$term_meta_before    = wp_cache_get_last_changed( 'term-meta' );
		$term_queries_before = wp_cache_get_last_changed( 'term-queries' );

		add_term_meta( self::$term_id, 'test_key', 'test_value' );

		$this->assertNotSame(
			$terms_before,
			wp_cache_get_last_changed( 'terms' ),
			'The terms last changed value should be updated.'
		);
		$this->assertNotSame(
			$term_meta_before,
			wp_cache_get_last_changed( 'term-meta' ),
			'The term-meta last changed value should be updated.'
		);
		$this->assertSame(
			$term_queries_before,
			wp_cache_get_last_changed( 'term-queries' ),
			'The term-queries last changed value should not be updated.'
		);
	}

	/**
	 * Inserting a term should update the 'terms' and 'term-queries'
	 * last changed values but leave 'term-meta' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_term_insert_action_updates_terms_and_term_queries() {
		$terms_before        = wp_cache_get_last_changed( 'terms' );
		$term_meta_before    = wp_cache_get_last_changed( 'term-meta' );
		$term_queries_before = wp_cache_get_last_changed( 'term-queries' );

		wp_insert_term( 'Test term', 'category' );

		$this->assertNotSame(
			$terms_before,
			wp_cache_get_last_changed( 'terms' ),
			'The terms last changed value should be updated.'
		);
		$this->assertNotSame(
			$term_queries_before,
			wp_cache_get_last_changed( 'term-queries' ),
			'The term-queries last changed value should be updated.'
		);
		$this->assertSame(
			$term_meta_before,
			wp_cache_get_last_changed( 'term-meta' ),
			'The term-meta last changed value should not be updated.'
		);
	}
}
