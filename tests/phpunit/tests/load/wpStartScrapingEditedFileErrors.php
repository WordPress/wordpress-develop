<?php

/**
 * Tests for wp_start_scraping_edited_file_errors().
 *
 * @group load
 *
 * @covers ::wp_start_scraping_edited_file_errors
 */
class Tests_Load_WpStartScrapingEditedFileErrors extends WP_UnitTestCase {

	/**
	 * Original $_REQUEST scrape values.
	 *
	 * @var array
	 */
	private $original_request = array();

	public function set_up() {
		parent::set_up();

		foreach ( array( 'wp_scrape_key', 'wp_scrape_nonce' ) as $key ) {
			$this->original_request[ $key ] = array_key_exists( $key, $_REQUEST ) ? $_REQUEST[ $key ] : null;
			unset( $_REQUEST[ $key ] );
		}
	}

	public function tear_down() {
		foreach ( $this->original_request as $key => $value ) {
			if ( null !== $value ) {
				$_REQUEST[ $key ] = $value;
			} else {
				unset( $_REQUEST[ $key ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_early_without_scrape_request_params() {
		// Other tests in the same run may already have defined the constant,
		// so check that this call does not change its state.
		$was_defined = defined( 'WP_SANDBOX_SCRAPING' );

		$this->expectOutputString( '' );

		wp_start_scraping_edited_file_errors();

		$this->assertSame( $was_defined, defined( 'WP_SANDBOX_SCRAPING' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_early_when_scrape_params_are_empty() {
		$_REQUEST['wp_scrape_key']   = '';
		$_REQUEST['wp_scrape_nonce'] = '';

		$was_defined = defined( 'WP_SANDBOX_SCRAPING' );

		$this->expectOutputString( '' );

		wp_start_scraping_edited_file_errors();

		$this->assertSame( $was_defined, defined( 'WP_SANDBOX_SCRAPING' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_early_when_scrape_transient_is_missing() {
		$_REQUEST['wp_scrape_key']   = 'missingkey1234567890123456789012';
		$_REQUEST['wp_scrape_nonce'] = 'nonce-value';

		$was_defined = defined( 'WP_SANDBOX_SCRAPING' );

		// A nonce mismatch would print an error and die, so returning quietly
		// proves the missing transient path was taken.
		$this->expectOutputString( '' );

		wp_start_scraping_edited_file_errors();

		$this->assertSame( $was_defined, defined( 'WP_SANDBOX_SCRAPING' ) );
	}

	/**
	 * Only this test defines WP_SANDBOX_SCRAPING, so it is the only one that
	 * needs its own process.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_defines_sandbox_scraping_when_nonce_matches() {
		$key   = 'matchingkey12345678901234567890';
		$nonce = 'nonce-value';

		set_transient( 'scrape_key_' . $key, $nonce, 60 );

		$_REQUEST['wp_scrape_key']   = $key;
		$_REQUEST['wp_scrape_nonce'] = $nonce;

		// A matching nonce registers wp_finalize_scraping_edited_file_errors() as a
		// shutdown function. It prints the scrape markers when this child process
		// exits, after PHPUnit has already recorded the test result. This is safe
		// because the test runs in an isolated process, so the output does not
		// affect the result or any other test.
		wp_start_scraping_edited_file_errors();

		$this->assertTrue( defined( 'WP_SANDBOX_SCRAPING' ) );
		$this->assertTrue( WP_SANDBOX_SCRAPING );

		delete_transient( 'scrape_key_' . $key );
	}
}
