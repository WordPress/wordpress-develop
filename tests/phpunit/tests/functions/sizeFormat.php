<?php

/**
 * Tests for size_format()
 *
 * @ticket 22405
 * @ticket 36635
 * @ticket 40875
 *
 * @group functions.php
 * @covers ::size_format
 */
class Tests_Functions_SizeFormat extends WP_UnitTestCase {

	public function data_size_format() {
		return array(
			// Invalid values.
			array( array(), 0, false ),
			array( 'baba', 0, false ),
			array( '', 0, false ),
			array( '-1', 0, false ),
			array( -1, 0, false ),
			// Bytes.
			array( 0, 0, '0 B' ),
			array( 1, 0, '1 B' ),
			array( 1023, 0, '1,023 B' ),
			// Kilobytes.
			array( KB_IN_BYTES, 0, '1 KB' ),
			array( KB_IN_BYTES, 2, '1.00 KB' ),
			array( 2.5 * KB_IN_BYTES, 0, '3 KB' ),
			array( 2.5 * KB_IN_BYTES, 2, '2.50 KB' ),
			array( 10 * KB_IN_BYTES, 0, '10 KB' ),
			// Megabytes.
			array( (string) 1024 * KB_IN_BYTES, 2, '1.00 MB' ),
			array( MB_IN_BYTES, 0, '1 MB' ),
			array( 2.5 * MB_IN_BYTES, 0, '3 MB' ),
			array( 2.5 * MB_IN_BYTES, 2, '2.50 MB' ),
			// Gigabytes.
			array( (string) 1024 * MB_IN_BYTES, 2, '1.00 GB' ),
			array( GB_IN_BYTES, 0, '1 GB' ),
			array( 2.5 * GB_IN_BYTES, 0, '3 GB' ),
			array( 2.5 * GB_IN_BYTES, 2, '2.50 GB' ),
			// Terabytes.
			array( (string) 1024 * GB_IN_BYTES, 2, '1.00 TB' ),
			array( TB_IN_BYTES, 0, '1 TB' ),
			array( 2.5 * TB_IN_BYTES, 0, '3 TB' ),
			array( 2.5 * TB_IN_BYTES, 2, '2.50 TB' ),
			// Petabytes.
			array( (string) 1024 * TB_IN_BYTES, 2, '1.00 PB' ),
			array( PB_IN_BYTES, 0, '1 PB' ),
			array( 2.5 * PB_IN_BYTES, 0, '3 PB' ),
			array( 2.5 * PB_IN_BYTES, 2, '2.50 PB' ),
			// Exabytes.
			array( (string) 1024 * PB_IN_BYTES, 2, '1.00 EB' ),
			array( EB_IN_BYTES, 0, '1 EB' ),
			array( 2.5 * EB_IN_BYTES, 0, '3 EB' ),
			array( 2.5 * EB_IN_BYTES, 2, '2.50 EB' ),
			// Zettabytes.
			array( (string) 1024 * EB_IN_BYTES, 2, '1.00 ZB' ),
			array( ZB_IN_BYTES, 0, '1 ZB' ),
			array( 2.5 * ZB_IN_BYTES, 0, '3 ZB' ),
			array( 2.5 * ZB_IN_BYTES, 2, '2.50 ZB' ),
			// Yottabytes.
			array( (string) 1024 * ZB_IN_BYTES, 2, '1.00 YB' ),
			array( YB_IN_BYTES, 0, '1 YB' ),
			array( 2.5 * YB_IN_BYTES, 0, '3 YB' ),
			array( 2.5 * YB_IN_BYTES, 2, '2.50 YB' ),
			// Edge values.
			array( TB_IN_BYTES + ( TB_IN_BYTES / 2 ) + MB_IN_BYTES, 1, '1.5 TB' ),
			array( TB_IN_BYTES - MB_IN_BYTES - KB_IN_BYTES, 3, '1,023.999 GB' ),
		);
	}

	/**
	 * @dataProvider data_size_format
	 *
	 * @param $bytes
	 * @param $decimals
	 * @param $expected
	 */
	public function test_size_format( $bytes, $decimals, $expected ) {
		$this->assertSame( $expected, size_format( $bytes, $decimals ) );
	}
}
