<?php

/**
 * @group functions
 *
 * @covers ::wp_is_json_media_type
 */
class Tests_Functions_WpIsJsonMediaType extends WP_UnitTestCase {
	/**
	 * @ticket 49404
	 * @dataProvider data_wp_is_json_media_type
	 */
	public function test_wp_is_json_media_type( $input, $expected ) {
		$this->assertSame( $expected, wp_is_json_media_type( $input ) );
	}


	public function data_wp_is_json_media_type() {
		return array(
			array( 'application/ld+json', true ),
			array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', true ),
			array( 'application/activity+json', true ),
			array( 'application/json+oembed', true ),
			array( 'application/json', true ),
			array( 'application/nojson', false ),
			array( 'application/no.json', false ),
			array( 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8', false ),
			array( 'application/activity+json, application/nojson', true ),
		);
	}
}
