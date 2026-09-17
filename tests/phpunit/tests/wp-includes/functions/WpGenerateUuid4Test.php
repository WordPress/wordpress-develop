<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * @group functions
 *
 *
 * @covers ::wp_generate_uuid4
 */
class WpGenerateUuid4Test extends WP_UnitTestCase {

	/**
	 * Tests wp_generate_uuid4().
	 *
	 * @ticket 38164
	 */
	public function test_wp_generate_uuid4() {
		$uuids = array();
		for ( $i = 0; $i < 20; $i += 1 ) {
			$uuid = wp_generate_uuid4();
			$this->assertTrue( wp_is_uuid( $uuid, 4 ) );
			$uuids[] = $uuid;
		}

		$unique_uuids = array_unique( $uuids );
		$this->assertSame( $uuids, $unique_uuids );
	}
}
