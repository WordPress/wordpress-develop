<?php

/**
 * @group wp
 *
 * @covers WP::remove_query_var
 */
class Tests_WP_RemoveQueryVar extends WP_UnitTestCase {

	/**
	 * @var WP
	 */
	protected $wp;

	public function set_up() {
		parent::set_up();
		$this->wp = new WP();
	}

	public function test_remove_query_var() {
		$public_qv_count = count( $this->wp->public_query_vars );

		$this->wp->add_query_var( 'test' );
		$this->assertContains( 'test', $this->wp->public_query_vars );
		$this->wp->remove_query_var( 'test' );

		$this->assertCount( $public_qv_count, $this->wp->public_query_vars );
	}
}
