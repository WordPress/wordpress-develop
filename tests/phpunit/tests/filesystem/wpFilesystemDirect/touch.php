<?php
/**
 * Tests for the WP_Filesystem_Direct::touch() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::touch
 */
class Tests_Filesystem_WpFilesystemDirect_Touch extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::touch()` creates a file.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_should_create_file
	 *
	 * @param string $file  The file path.
	 * @param int    $mtime The modified time to set.
	 * @param int    $atime The accessed time to set.
	 */
	public function test_should_create_file( $file, $mtime, $atime ) {
		$file = str_replace( 'TEST_DATA', self::$file_structure['test_dir']['path'], $file );

		if ( is_string( $mtime ) ) {
			$mtime = (int) str_replace(
				array( 'time plus one minute', time() + MINUTE_IN_SECONDS ),
				array( 'time', time() ),
				$mtime
			);
		}

		$expected_mtime = 0 === $mtime ? time() : $mtime;

		if ( is_string( $atime ) ) {
			$atime = (int) str_replace(
				array( 'time plus one minute', time() + MINUTE_IN_SECONDS ),
				array( 'time', time() ),
				$atime
			);
		}

		$expected_atime = 0 === $atime ? time() : $atime;

		$result = self::$filesystem->touch( $file, $mtime, $atime );

		$actual_atime  = fileatime( $file );
		$actual_exists = file_exists( $file );
		$actual_mtime  = filemtime( $file );

		if ( $actual_exists ) {
			unlink( $file );
		}

		$this->assertTrue( $result, 'WP_Filesystem_Direct::touch() did not return true.' );
		$this->assertTrue( $actual_exists, 'The file does not exist.' );
		$this->assertSame( $actual_atime, $expected_atime, 'The file does not have the expected atime.' );
		$this->assertSame( $actual_mtime, $expected_mtime, 'The file does not have the expected mtime.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_create_file() {
		return array(
			'default mtime or atime'      => array(
				'file'  => 'TEST_DATA/file-to-create.txt',
				'mtime' => 0,
				'atime' => 0,
			),
			'set mtime and default atime' => array(
				'file'  => 'TEST_DATA/file-to-create.txt',
				'mtime' => 'time plus one minute',
				'atime' => 'time',
			),
			'default mtime and set atime' => array(
				'file'  => 'TEST_DATA/file-to-create.txt',
				'mtime' => 'time',
				'atime' => 'time plus one minute',
			),
		);
	}
}
