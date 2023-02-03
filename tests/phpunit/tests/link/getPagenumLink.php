<?php

/**
 * @group  link
 * @covers ::get_pagenum_link
 */
class Tests_Link_GetPagenumLink extends WP_UnitTestCase {

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
	public function test_get_pagenum_link_firstpage_no_trailingslash() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%' );
		$_SERVER['REQUEST_URI'] = '/woohoo/page/2/';
		$paged                  = get_pagenum_link( 1 );

		$this->assertEquals( home_url( '/woohoo' ), $paged );

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	/**
	 * @ticket 2877
	 */
	public function test_get_pagenum_link_paged_no_trailingslash() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%' );
		$_SERVER['REQUEST_URI'] = '/woohoo';
		$paged                  = get_pagenum_link( 2 );

		$this->assertEquals( home_url( '/woohoo/page/2' ), $paged );

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	/**
	 * @ticket 2877
	 */
	public function test_get_pagenum_link_firstpage_no_trailingslash_queryargs() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%' );
		$_SERVER['REQUEST_URI'] = '/woohoo/page/2?test=1234';
		$paged                  = get_pagenum_link( 1 );

		$this->assertEquals( home_url( '/woohoo?test=1234' ), $paged );

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	/**
	 * @ticket 2877
	 */
	public function test_get_pagenum_link_paged_no_trailingslash_query_args() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%' );
		$_SERVER['REQUEST_URI'] = '/woohoo?test=1234';
		$paged                  = get_pagenum_link( 2 );

		$this->assertEquals( home_url( '/woohoo/page/2?test=1234' ), $paged );

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	public function get_pagenum_link_cb( $url ) {
		return $url . '/WooHoo';
	}
}
