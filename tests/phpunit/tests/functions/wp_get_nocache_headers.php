<?php

/**
 * @group functions.php
 * @covers ::wp_get_nocache_headers
 */
class Tests_Functions_wp_get_nocache_headers extends WP_UnitTestCase {

	public function test_wp_get_nocache_headers() {
		$headers = wp_get_nocache_headers();

		$this->assertIsArray( $headers, 'array returned' );
		$this->assertContains( 'no-cache, must-revalidate, max-age=0', $headers, 'has no cache header' );
		$this->assertArrayHasKey( 'Expires', $headers, 'has Expires key' );
		$this->assertArrayHasKey( 'Cache-Control', $headers, 'has Expires key' );
		$this->assertArrayHasKey( 'Last-Modified', $headers, 'has Last-Modified key' );
		$this->assertFalse( $headers['Last-Modified'], 'Last-Modified key is false' );


		add_filter(
			'nocache_headers',
			function ( $header ) {

				return array( 'filter_name' => 'nocache_headers' );
			}
		);

		$headers = wp_get_nocache_headers();
		$this->assertEquals( 'nocache_headers', $headers['filter_name'], 'filter was applied' );
	}

}
