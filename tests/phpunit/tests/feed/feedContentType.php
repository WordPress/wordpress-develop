<?php
/**
 * Tests for feed_content_type().
 *
 * @group feed
 *
 * @covers ::feed_content_type
 */
class Tests_Feed_FeedContentType extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_known_feed_content_types
	 *
	 * @param string $type     Feed type.
	 * @param string $expected Expected content type.
	 */
	public function test_returns_content_type_for_known_feed_types( $type, $expected ) {
		$this->assertSame( $expected, feed_content_type( $type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_known_feed_content_types() {
		return array(
			'rss'      => array( 'rss', 'application/rss+xml' ),
			'rss2'     => array( 'rss2', 'application/rss+xml' ),
			'rss-http' => array( 'rss-http', 'text/xml' ),
			'atom'     => array( 'atom', 'application/atom+xml' ),
			'rdf'      => array( 'rdf', 'application/rdf+xml' ),
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_octet_stream_for_unknown_feed_type() {
		$this->assertSame( 'application/octet-stream', feed_content_type( 'foo' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_empty_type_uses_default_feed() {
		$this->assertSame( 'application/rss+xml', feed_content_type() );
		$this->assertSame( 'application/rss+xml', feed_content_type( '' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_empty_type_respects_default_feed_filter() {
		add_filter(
			'default_feed',
			static function () {
				return 'atom';
			}
		);

		$this->assertSame( 'application/atom+xml', feed_content_type() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_content_type_and_type() {
		$filter = new MockAction();
		add_filter( 'feed_content_type', array( $filter, 'filter_append' ), 10, 2 );

		$result = feed_content_type( 'atom' );

		$this->assertSame( 'application/atom+xml_append', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'application/atom+xml', 'atom' ), $filter->get_args()[0] );
	}
}
