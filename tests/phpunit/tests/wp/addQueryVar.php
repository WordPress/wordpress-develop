<?php

/**
 * @group wp
 *
 * @covers WP::add_query_var
 */
class Tests_WP_AddQueryVar extends WP_UnitTestCase {

	/**
	 * @var WP
	 */
	protected $wp;

	public function set_up() {
		parent::set_up();
		$this->wp = new WP();
	}

	public function test_add_query_var() {
		$public_qv_count = count( $this->wp->public_query_vars );

		$this->wp->add_query_var( 'test' );
		$this->wp->add_query_var( 'test2' );
		$this->wp->add_query_var( 'test' );

		$this->assertCount( $public_qv_count + 2, $this->wp->public_query_vars );
		$this->assertContains( 'test', $this->wp->public_query_vars );
		$this->assertContains( 'test2', $this->wp->public_query_vars );
	}
}
