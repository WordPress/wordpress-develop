<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_Robots extends WP_Canonical_UnitTestCase {

	public function test_remove_trailing_slashes_for_robots_requests() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->assertCanonical( '/robots.txt', '/robots.txt' );
		$this->assertCanonical( '/robots.txt/', '/robots.txt' );
	}

	public function test_remove_trailing_slashes_for_atproto_did_requests() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->assertCanonical( '/.well-known/atproto-did', '/.well-known/atproto-did' );
		$this->assertCanonical( '/.well-known/atproto-did/', '/.well-known/atproto-did' );
	}
}
