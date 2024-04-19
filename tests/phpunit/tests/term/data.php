<?php

/**
 * @group taxonomy
 */
class Tests_Term_Data extends WP_UnitTestCase {

	/**
	 * @var WP_Term
	 */
	private static $term;
	public static function wpSetUpBeforeClass() {
		register_taxonomy( 'wptests_tax', 'post' );

		static::$term = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
	}
	public function test_throws_error_on_accessing_dynamic_property() {

	}
}
