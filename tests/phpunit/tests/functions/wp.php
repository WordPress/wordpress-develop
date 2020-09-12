<?php

/**
 * @group functions.php
 * @group query
 */
class Tests_Functions_WP extends WP_UnitTestCase {

	public function test_wp_sets_global_vars() {
		global $wp, $wp_query, $wp_the_query;

		wp();

		$this->assertInstanceOf( 'WP', $wp );
		$this->assertInstanceOf( 'WP_Query', $wp_query );
		$this->assertInstanceOf( 'WP_Query', $wp_the_query );
	}

}
