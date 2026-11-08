<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_Robots extends WP_Canonical_UnitTestCase {

	public function test_remove_trailing_slashes_for_robots_requests() {
		$this->assertCanonical( '/robots.txt', '/robots.txt' );
		$this->assertCanonical( '/robots.txt/', '/robots.txt' );
	}
}
