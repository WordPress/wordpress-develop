<?php

namespace WordPress\Tests\WP_Includes\Functions;

use DateTime;
use DateTimeImmutable;
use WP_UnitTestCase;

/**
 * @group date
 * @group datetime
 *
 *
 * @covers ::current_datetime
 */
class CurrentDatetimeTest extends WP_UnitTestCase {

	/**
	 * @ticket 53484
	 */
	public function test_current_datetime_return_type() {
		$this->assertInstanceOf( 'DateTimeImmutable', current_datetime() );
	}
}
