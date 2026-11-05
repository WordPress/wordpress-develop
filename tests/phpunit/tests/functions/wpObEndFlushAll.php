<?php

/**
 * Tests for the wp_ob_end_flush_all function.
 *
 * @group functions
 *
 * @covers ::wp_ob_end_flush_all
 */
class Tests_Functions_wpObEndFlushAll extends WP_UnitTestCase {

	/**
	 * @ticket 60177
	 */
	public function test_wp_ob_end_flush_all() {

		$initial_buffer_level = ob_get_level();
		$output               = '';

		ob_start(
			static function ( $buffer, $phase ) use ( &$output ) {
				if ( ! ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) ) {
					$output .= $buffer;
				}
				return '';
			}
		);
		ob_start();

		try {
			echo 'output';
			wp_ob_end_flush_all();
			$final_buffer_level = ob_get_level();
		} finally {
			while ( ob_get_level() > $initial_buffer_level ) {
				ob_end_clean();
			}
			while ( ob_get_level() < $initial_buffer_level ) {
				ob_start();
			}
		}

		$this->assertSame( 0, $final_buffer_level, 'All output buffers should be closed.' );
		$this->assertSame( 'output', $output, 'Buffered output should be flushed, not discarded.' );
	}
}
