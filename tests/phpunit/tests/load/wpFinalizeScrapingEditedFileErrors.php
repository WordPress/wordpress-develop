<?php

/**
 * Tests for wp_finalize_scraping_edited_file_errors().
 *
 * @group load
 *
 * @covers ::wp_finalize_scraping_edited_file_errors
 */
class Tests_Load_WpFinalizeScrapingEditedFileErrors extends WP_UnitTestCase {

	public function tear_down() {
		// Don't leave the error from test_ignores_non_fatal_errors() for later tests.
		error_clear_last();

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_outputs_true_when_there_is_no_fatal_error() {
		error_clear_last();

		$output = get_echo( 'wp_finalize_scraping_edited_file_errors', array( 'custom-key-42' ) );

		$this->assertSame(
			"\n###### wp_scraping_result_start:custom-key-42 ######\n" .
			'true' .
			"\n###### wp_scraping_result_end:custom-key-42 ######\n",
			$output
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_ignores_non_fatal_errors() {
		error_clear_last();

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
}
