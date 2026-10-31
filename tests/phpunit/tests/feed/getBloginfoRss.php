<?php
/**
 * Tests for get_bloginfo_rss().
 *
 * @group feed
 *
 * @covers ::get_bloginfo_rss
 */
class Tests_Feed_GetBloginfoRss extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_strips_tags_and_converts_ampersands() {
		add_filter(
			'pre_option_blogname',
			static function () {
				return 'Hello <em>World</em> & Co';
			}
		);

		$this->assertSame( 'Hello World &#038; Co', get_bloginfo_rss( 'name' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_converted_value_and_show() {
		$filter = new MockAction();
		add_filter( 'get_bloginfo_rss', array( $filter, 'filter_append' ), 10, 2 );

		add_filter(
			'pre_option_blogdescription',
			static function () {
				return 'Feed <b>Description</b> & Co';
			}
		);

		$result = get_bloginfo_rss( 'description' );

		$this->assertSame( 'Feed Description &#038; Co_append', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'Feed Description &#038; Co', 'description' ), $filter->get_args()[0] );
	}
}
