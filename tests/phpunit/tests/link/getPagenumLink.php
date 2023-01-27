<?php

/**
 * @group  link
 * @covers ::get_pagenum_link
 */
class Tests_Link_GetPagenumLink extends WP_UnitTestCase {

	public function get_pagenum_link_cb( $url ) {
		return $url . '/WooHoo';
	}

	/**
	 * @ticket 8847
	 */
	public function test_get_pagenum_link_case_insensitivity() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		add_filter( 'home_url', array( $this, 'get_pagenum_link_cb' ) );
		$_SERVER['REQUEST_URI'] = '/woohoo';
		$paged                  = get_pagenum_link( 2 );

		remove_filter( 'home_url', array( $this, 'get_pagenum_link_cb' ) );
		$this->assertSame( $paged, home_url( '/WooHoo/page/2/' ) );

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	/**
	 * @ticket 2877
	 */
	function test_get_pagenum_link_firstpage_trailingslash() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%' );

		add_filter( 'home_url', array( $this, 'get_pagenum_link_cb' ) );
		$_SERVER['REQUEST_URI'] = '/woohoo';
		$paged                  = get_pagenum_link( 1 );

		remove_filter( 'home_url', array( $this, 'get_pagenum_link_cb' ) );
		$this->assertEquals( home_url( '/WooHoo' ), $paged );

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}
}
