<?php

/**
 * @group query
 */
class Tests_Query_InvalidQueries extends WP_UnitTestCase {
	public $last_posts_request;

	public function setUp() {
		parent::setUp();

		// Clean up variable before each test.
		$this->last_posts_request = '';
		// Store last query for tests.
		add_filter( 'posts_request', array( $this, '_set_last_posts_request' ) );
	}

	public function _set_last_posts_request( $request ) {
		$this->last_posts_request = $request;
		return $request;
	}

	function test_unregistered_post_type_wp_query() {
		global $wpdb;

		new WP_Query( array( 'post_type' => 'unregistered_cpt' ) );

		$this->assertContains( "{$wpdb->posts}.post_type = 'unregistered_cpt'", $this->last_posts_request );
		$this->assertContains( "{$wpdb->posts}.post_status = 'publish'", $this->last_posts_request );
	}

	function test_unregistered_post_type_goto() {
		global $wpdb;

		$this->go_to( home_url( '?post_type=unregistered_cpt' ) );

		$this->assertContains( "{$wpdb->posts}.post_type = 'unregistered_cpt'", $this->last_posts_request );
		$this->assertContains( "{$wpdb->posts}.post_status = 'publish'", $this->last_posts_request );
	}
}
