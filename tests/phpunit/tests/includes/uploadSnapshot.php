<?php

/**
 * @group phpunit
 *
 * Each process needs an uninitialized upload snapshot.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Tests_Includes_UploadSnapshot extends PHPUnit_Adapter_TestCase {
	/**
	 * @dataProvider data_initial_uploads
	 *
	 * @param string[] $initial_files Files present when uploads are first scanned.
	 */
	public function test_scan_user_uploads_preserves_initial_snapshot( $initial_files ) {
		$test_case = $this->getMockBuilder( WP_UnitTestCase::class )
			->setMethods( array( 'files_in_dir' ) )
			->getMock();

		$test_case->method( 'files_in_dir' )
			->willReturnOnConsecutiveCalls( $initial_files, array( 'added-during-test.jpg' ) );

		$this->assertSame( $initial_files, $test_case->scan_user_uploads() );
		$this->assertSame( $initial_files, $test_case->scan_user_uploads() );
	}

	/**
	 * @return array[]
	 */
	public function data_initial_uploads() {
		return array(
			'empty uploads'   => array( array() ),
			'existing upload' => array( array( 'existing.jpg' ) ),
		);
	}
}
