<?php
/**
 * Tests for bloginfo_rss().
 *
 * @group feed
 *
 * @covers ::bloginfo_rss
 */
class Tests_Feed_BloginfoRss extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_value_and_show() {
		add_filter(
			'pre_option_blogname',
			static function () {
				return 'Site Name';
			}
		);

		$filter = new MockAction();
		add_filter( 'bloginfo_rss', array( $filter, 'filter_append' ), 10, 2 );

		ob_start();
		bloginfo_rss( 'name' );
		$output = ob_get_clean();

		$this->assertSame( 'Site Name_append', $output );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'Site Name', 'name' ), $filter->get_args()[0] );
	}
}
