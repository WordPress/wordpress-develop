<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * @group functions
 * @group query
 *
 *
 * @covers ::wp
 */
class WpTest extends WP_UnitTestCase {

	public function test_wp_sets_global_vars() {
		global $wp, $wp_query, $wp_the_query;

		wp();

		$this->assertInstanceOf( 'WP', $wp );
		$this->assertInstanceOf( 'WP_Query', $wp_query );
		$this->assertInstanceOf( 'WP_Query', $wp_the_query );
	}
}
