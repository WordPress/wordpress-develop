<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_Robots extends WP_Canonical_UnitTestCase {

	function setUp() {
		parent::setUp();
	}

	function test_robots_url() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->assertCanonical( '/robots.txt', '/robots.txt' );
		$this->assertCanonical( '/robots.txt/', '/robots.txt' );
	}

}
