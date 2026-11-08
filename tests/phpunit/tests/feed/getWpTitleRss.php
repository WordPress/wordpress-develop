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
	public function test_filter_can_customize_feed_title() {
		add_filter(
			'pre_get_document_title',
			static function () {
				return 'Feed Title';
			}
		);

		add_filter(
			'get_wp_title_rss',
			static function ( $title ) {
				return $title . ' Custom';
			}
		);

		$this->assertSame( 'Feed Title Custom', get_wp_title_rss() );
	}
}
