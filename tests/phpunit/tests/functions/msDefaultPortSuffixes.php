<?php

/**
 * Tests for the ms_default_port_suffixes() function.
 *
 * @group functions
 * @group multisite
 *
 * @covers ::ms_default_port_suffixes
 */
class Tests_Functions_MsDefaultPortSuffixes extends WP_UnitTestCase {

	/**
	 * @ticket 55488
	 */
	public function test_returns_standard_ports_by_default() {
		$this->assertSame( array( ':80', ':443' ), ms_default_port_suffixes() );
	}

	/**
	 * @ticket 55488
	 */
	public function test_result_is_filterable() {
		add_filter( 'ms_default_port_suffixes', array( $this, 'filter_port_suffixes' ) );

		$this->assertSame( array( ':8080' ), ms_default_port_suffixes() );
	}

	public function filter_port_suffixes() {
		return array( ':8080' );
	}
}
