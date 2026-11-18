<?php
/**
 * Tests for get_the_title_rss().
 *
 * @group feed
 *
 * @covers ::get_the_title_rss
 */
class Tests_Feed_GetTheTitleRss extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_title() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Hello Feed Title',
			)
		);

		$filter = new MockAction();
		add_filter( 'the_title_rss', array( $filter, 'filter_append' ) );

		$result = get_the_title_rss( $post_id );

		$this->assertSame( 'Hello Feed Title_append', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'Hello Feed Title' ), $filter->get_args()[0] );
	}
}
