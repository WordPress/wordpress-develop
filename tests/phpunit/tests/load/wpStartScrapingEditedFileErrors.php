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
}
