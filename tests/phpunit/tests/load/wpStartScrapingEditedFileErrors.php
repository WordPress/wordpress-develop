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

	/**
	 * Transient keys created during the test.
	 *
	 * @var string[]
	 */
	private $created_transients = array();

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

		foreach ( $this->created_transients as $transient ) {
			delete_transient( $transient );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_early_without_scrape_request_params() {
		wp_start_scraping_edited_file_errors();

		$this->assertFalse( defined( 'WP_SANDBOX_SCRAPING' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_early_when_scrape_params_are_empty() {
		$_REQUEST['wp_scrape_key']   = '';
		$_REQUEST['wp_scrape_nonce'] = '';

		wp_start_scraping_edited_file_errors();

		$this->assertFalse( defined( 'WP_SANDBOX_SCRAPING' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_early_when_scrape_transient_is_missing() {
		$_REQUEST['wp_scrape_key']   = 'missingkey1234567890123456789012';
		$_REQUEST['wp_scrape_nonce'] = 'nonce-value';

		wp_start_scraping_edited_file_errors();

		$this->assertFalse( defined( 'WP_SANDBOX_SCRAPING' ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_defines_sandbox_constant_when_scrape_nonce_matches() {
		$key   = 'validkey123456789012345678901234';
		$nonce = 'matching-nonce';

		$transient = 'scrape_key_' . $key;
		set_transient( $transient, $nonce, MINUTE_IN_SECONDS );
		$this->created_transients[] = $transient;

		$_REQUEST['wp_scrape_key']   = $key;
		$_REQUEST['wp_scrape_nonce'] = $nonce;

		// This registers wp_finalize_scraping_edited_file_errors() as a shutdown function.
		// It only prints scrape markers when the isolated child process exits, after
		// PHPUnit has reported the result, so it doesn't affect the test outcome.
		wp_start_scraping_edited_file_errors();

		$this->assertTrue( defined( 'WP_SANDBOX_SCRAPING' ) );
		$this->assertTrue( WP_SANDBOX_SCRAPING );
	}
}
