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
	 *
	 * @dataProvider data_get_bloginfo_rss_show_values
	 *
	 * @param string $show Bloginfo show key.
	 */
	public function test_matches_stripped_and_converted_bloginfo( $show ) {
		$this->assertSame(
			convert_chars( strip_tags( get_bloginfo( $show ) ) ),
			get_bloginfo_rss( $show )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_bloginfo_rss_show_values() {
		return array(
			'name'        => array( 'name' ),
			'description' => array( 'description' ),
			'url'         => array( 'url' ),
			'charset'     => array( 'charset' ),
		);
	}

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
		add_filter( 'get_bloginfo_rss', array( $filter, 'filter' ), 10, 2 );

		add_filter(
			'pre_option_blogname',
			static function () {
				return 'Feed <b>Name</b>';
			}
		);

		$result = get_bloginfo_rss( 'name' );

		$this->assertSame( 'Feed Name', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'Feed Name', 'name' ), $filter->get_args()[0] );
	}
}
