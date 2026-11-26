<?php
/**
 * Tests for get_default_feed().
 *
 * @group feed
 *
 * @covers ::get_default_feed
 */
class Tests_Feed_GetDefaultFeed extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_rss2_by_default() {
		$this->assertSame( 'rss2', get_default_feed() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_maps_legacy_rss_alias_to_rss2() {
		add_filter(
			'default_feed',
			static function () {
				return 'rss';
			}
		);

		$this->assertSame( 'rss2', get_default_feed() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_unfiltered_default() {
		$filter = new MockAction();
		add_filter( 'default_feed', array( $filter, 'filter_append' ) );

		$result = get_default_feed();

		$this->assertSame( 'rss2_append', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'rss2' ), $filter->get_args()[0] );
	}
}
