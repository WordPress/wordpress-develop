<?php
/**
 * Tests for get_wp_title_rss().
 *
 * @group feed
 *
 * @covers ::get_wp_title_rss
 */
class Tests_Feed_GetWpTitleRss extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_document_title_and_deprecated_arg() {
		add_filter(
			'pre_get_document_title',
			static function () {
				return 'Feed Title';
			}
		);

		$filter = new MockAction();
		add_filter( 'get_wp_title_rss', array( $filter, 'filter_append' ), 10, 2 );

		$result = get_wp_title_rss();

		$this->assertSame( 'Feed Title_append', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'Feed Title', '&#8211;' ), $filter->get_args()[0] );
	}
}
