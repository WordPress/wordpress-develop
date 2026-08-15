<?php

/**
 * @ticket 65526
 *
 * @group date
 * @group functions
 *
 * @covers ::human_readable_duration
 */
class Tests_Functions_HumanReadableDuration extends WP_UnitTestCase {

	/**
	 * Test human_readable_duration().
	 *
	 * @ticket 39667
	 * @dataProvider data_human_readable_duration
	 *
	 * @param string $input    Duration.
	 * @param string $expected Expected human readable duration.
	 */
	public function test_human_readable_duration( $input, $expected ) {
		$this->assertSame( $expected, human_readable_duration( $input ) );
	}

	/**
	 * Data provider for test_human_readable_duration().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input  Duration.
	 *         @type string $expect Expected human readable duration.
	 *     }
	 * }
	 */
	public function data_human_readable_duration() {
		return array(
			// Valid ii:ss cases.
			array( '0:0', '0 minutes, 0 seconds' ),
			array( '00:00', '0 minutes, 0 seconds' ),
			array( '0:5', '0 minutes, 5 seconds' ),
			array( '0:05', '0 minutes, 5 seconds' ),
			array( '01:01', '1 minute, 1 second' ),
			array( '30:00', '30 minutes, 0 seconds' ),
			array( ' 30:00 ', '30 minutes, 0 seconds' ),
			// Valid HH:ii:ss cases.
			array( '0:0:0', '0 hours, 0 minutes, 0 seconds' ),
			array( '00:00:00', '0 hours, 0 minutes, 0 seconds' ),
			array( '00:30:34', '0 hours, 30 minutes, 34 seconds' ),
			array( '01:01:01', '1 hour, 1 minute, 1 second' ),
			array( '1:02:00', '1 hour, 2 minutes, 0 seconds' ),
			array( '10:30:34', '10 hours, 30 minutes, 34 seconds' ),
			array( '1234567890:59:59', '1234567890 hours, 59 minutes, 59 seconds' ),
			// Valid ii:ss cases with negative sign.
			array( '-00:00', '0 minutes, 0 seconds' ),
			array( '-3:00', '3 minutes, 0 seconds' ),
			array( '-03:00', '3 minutes, 0 seconds' ),
			array( '-30:00', '30 minutes, 0 seconds' ),
			// Valid HH:ii:ss cases with negative sign.
			array( '-00:00:00', '0 hours, 0 minutes, 0 seconds' ),
			array( '-1:02:00', '1 hour, 2 minutes, 0 seconds' ),
			// Invalid cases.
			array( null, false ),
			array( '', false ),
			array( ':', false ),
			array( '::', false ),
			array( array(), false ),
			array( 'Batman Begins !', false ),
			array( '', false ),
			array( '-1', false ),
			array( -1, false ),
			array( 0, false ),
			array( 1, false ),
			array( '00', false ),
			array( '30:-10', false ),
			array( ':30:00', false ),   // Missing HH.
			array( 'MM:30:00', false ), // Invalid HH.
			array( '30:MM:00', false ), // Invalid ii.
			array( '30:30:MM', false ), // Invalid ss.
			array( '30:MM', false ),    // Invalid ss.
			array( 'MM:00', false ),    // Invalid ii.
			array( 'MM:MM', false ),    // Invalid ii and ss.
			array( '10 :30', false ),   // Containing a space.
			array( '59:61', false ),    // Out of bound.
			array( '61:59', false ),    // Out of bound.
			array( '3:59:61', false ),  // Out of bound.
			array( '03:61:59', false ), // Out of bound.
		);
	}
}
