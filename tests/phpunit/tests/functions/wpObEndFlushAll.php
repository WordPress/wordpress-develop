<?php

/**
 * @group functions
 * @group query
 *
 * @covers ::wp_ob_end_flush_all
 */
class Tests_Functions_wpObEndFlushAll extends WP_UnitTestCase {
	/**
	 * Output buffer callback.
	 *
	 * @param string $output output buffer
	 * @return string
	 */
	private function my_ob_cb( $output ) {
		return $output;
	}

	/**
	 * @ticket 22239
	 *
	 * Must run in a separate process since the output buffer is not endable and would spill to subsequent tests.
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_ob_end_flush_all_non_removable() {
		$this->markTestSkipped(
			'Non-removable output buffers cannot be tested at the moment, see https://github.com/sebastianbergmann/phpunit/issues/5851.'
		);

		// Clear any previous errors that could lead to false failures.
		error_clear_last();

		ob_start( array( $this, 'my_ob_cb' ), 0, 0 );

		wp_ob_end_flush_all();

		$this->assertNull( error_get_last() );
	}

	public function test_wp_ob_end_flush_all() {
		$this->expectOutputString( '' );

		// Clear any previous errors that could lead to false failures.
		error_clear_last();

		ob_start( array( $this, 'my_ob_cb' ) );

		// will not be in phpunit, since we end all buffers below
		// phpunit's behavior will change once the issue above is fixed though, in which case we would expect "hello" instead of empty string
		echo 'hello';

		wp_ob_end_flush_all();

		$this->assertNull( error_get_last() );

		// restart phpunit's output buffer
		ob_start();
	}
}
