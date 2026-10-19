<?php

/**
 * Tests for wp_finalize_scraping_edited_file_errors().
 *
 * @group load
 *
 * @covers ::wp_finalize_scraping_edited_file_errors
 */
class Tests_Load_WpFinalizeScrapingEditedFileErrors extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_outputs_true_when_there_is_no_fatal_error() {
		$output = get_echo( 'wp_finalize_scraping_edited_file_errors', array( 'abc123' ) );

		$this->assertSame(
			"\n###### wp_scraping_result_start:abc123 ######\n" .
			'true' .
			"\n###### wp_scraping_result_end:abc123 ######\n",
			$output
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ignores_non_fatal_errors() {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- Populate error_get_last() without raising a fatal.
		@trigger_error( 'Warning only', E_USER_WARNING );

		$output = get_echo( 'wp_finalize_scraping_edited_file_errors', array( 'warn-key' ) );

		$this->assertSame(
			"\n###### wp_scraping_result_start:warn-key ######\n" .
			'true' .
			"\n###### wp_scraping_result_end:warn-key ######\n",
			$output
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uses_the_provided_scrape_key_in_markers() {
		$output = get_echo( 'wp_finalize_scraping_edited_file_errors', array( 'custom-key-42' ) );

		$this->assertStringContainsString( '###### wp_scraping_result_start:custom-key-42 ######', $output );
		$this->assertStringContainsString( '###### wp_scraping_result_end:custom-key-42 ######', $output );
	}
}
