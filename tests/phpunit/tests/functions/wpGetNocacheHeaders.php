<?php

/**
 * @group functions.php
 * @covers ::wp_get_nocache_headers
 */
class Tests_Functions_wpGetNocacheHeaders extends WP_UnitTestCase {

	/**
	 * @ticket 54490
	 */
	public function test_wp_get_nocache_headers() {
		$this->assertSameSetsWithIndex(
			array(
				'Expires'       => 'Wed, 11 Jan 1984 05:00:00 GMT',
				'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
				'Last-Modified' => false,
			),
			wp_get_nocache_headers()
		);
	}

	/**
	 * @ticket 54490
	 */
	public function test_filter_nocache_headers() {
		add_filter(
			'nocache_headers',
			static function() {
				return array( 'filter_name' => 'nocache_headers' );
			}
		);

		$this->assertSameSetsWithIndex(
			array(
				'filter_name'   => 'nocache_headers',
				'Last-Modified' => false,
			),
			wp_get_nocache_headers()
		);
	}

}
