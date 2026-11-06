<?php

/**
 * Tests for WP_Rewrite::add_endpoint().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::add_endpoint
 */
class Tests_Rewrite_AddEndpoint extends WP_UnitTestCase {

	/**
	 * Public query variables before a test.
	 *
	 * @var string[]
	 */
	private $public_query_vars;

	public function set_up() {
		global $wp;

		parent::set_up();

		$this->public_query_vars = $wp->public_query_vars;
	}

	public function tear_down() {
		global $wp;

		$wp->public_query_vars = $this->public_query_vars;

		parent::tear_down();
	}

	/**
	 * Tests that the endpoint is added, with the query variable of the same name by default.
	 *
	 * @ticket 66496
	 */
	public function test_should_add_the_endpoint_with_the_name_as_the_query_variable() {
		global $wp;

		$wp_rewrite = new WP_Rewrite();

		$wp_rewrite->add_endpoint( 'an-endpoint', EP_PERMALINK );

		$this->assertSame( array( array( EP_PERMALINK, 'an-endpoint', 'an-endpoint' ) ), $wp_rewrite->endpoints );
		$this->assertContains( 'an-endpoint', $wp->public_query_vars );
	}

	/**
	 * Tests that a query variable that is null is the same as true.
	 *
	 * @ticket 66496
	 */
	public function test_should_use_the_name_when_the_query_variable_is_null() {
		global $wp;

		$wp_rewrite = new WP_Rewrite();

		$wp_rewrite->add_endpoint( 'an-endpoint', EP_PERMALINK, null );

		$this->assertSame( array( array( EP_PERMALINK, 'an-endpoint', 'an-endpoint' ) ), $wp_rewrite->endpoints );
		$this->assertContains( 'an-endpoint', $wp->public_query_vars );
	}

	/**
	 * Tests that another name can be used for the query variable.
	 *
	 * @ticket 66496
	 */
	public function test_should_use_another_name_for_the_query_variable() {
		global $wp;

		$wp_rewrite = new WP_Rewrite();

		$wp_rewrite->add_endpoint( 'an-endpoint', EP_PAGES, 'a_query_var' );

		$this->assertSame( array( array( EP_PAGES, 'an-endpoint', 'a_query_var' ) ), $wp_rewrite->endpoints );
		$this->assertContains( 'a_query_var', $wp->public_query_vars );
		$this->assertNotContains( 'an-endpoint', $wp->public_query_vars );
	}

	/**
	 * Tests that no query variable is added when it is false.
	 *
	 * @ticket 66496
	 */
	public function test_should_not_add_a_query_variable_when_it_is_false() {
		global $wp;

		$wp_rewrite = new WP_Rewrite();
		$before     = $wp->public_query_vars;

		$wp_rewrite->add_endpoint( 'an-endpoint', EP_ROOT, false );

		$this->assertSame( array( array( EP_ROOT, 'an-endpoint', false ) ), $wp_rewrite->endpoints );
		$this->assertSame( $before, $wp->public_query_vars );
	}

	/**
	 * Tests that the endpoints are added in order.
	 *
	 * @ticket 66496
	 */
	public function test_should_add_the_endpoints_in_order() {
		$wp_rewrite = new WP_Rewrite();

		$wp_rewrite->add_endpoint( 'first', EP_PERMALINK );
		$wp_rewrite->add_endpoint( 'second', EP_PAGES | EP_ROOT );

		$this->assertSame(
			array(
				array( EP_PERMALINK, 'first', 'first' ),
				array( EP_PAGES | EP_ROOT, 'second', 'second' ),
			),
			$wp_rewrite->endpoints
		);
	}

	/**
	 * Tests that the endpoints are removed when the object is initialized again.
	 *
	 * @ticket 66496
	 */
	public function test_should_be_removed_when_the_object_is_initialized_again() {
		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->add_endpoint( 'an-endpoint', EP_PERMALINK );

		$wp_rewrite->init();

		$this->assertSame( array(), $wp_rewrite->endpoints );
	}

	/**
	 * Tests that the endpoint is used when the function add_rewrite_endpoint() is called.
	 *
	 * @ticket 66496
	 */
	public function test_should_be_used_by_add_rewrite_endpoint() {
		global $wp_rewrite;

		add_rewrite_endpoint( 'an-endpoint', EP_PERMALINK );

		$this->assertContains( array( EP_PERMALINK, 'an-endpoint', 'an-endpoint' ), $wp_rewrite->endpoints );
	}
}
