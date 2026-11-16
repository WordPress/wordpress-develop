<?php
/**
 * Tests for WP_Secrets_Option_Store.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsOptionStore extends WP_UnitTestCase {

	/**
	 * A well-formed single-slot record.
	 *
	 * @return array{v: int, current: array<string, int|string>}
	 */
	private function sample_record(): array {
		return array(
			'v'       => 1,
			'current' => array(
				'dk'          => 'x',
				'dk_nonce'    => 'x',
				'ct'          => 'x',
				'nonce'       => 'x',
				'fingerprint' => 'x',
				'created'     => 12345,
			),
		);
	}

	/**
	 * Ensure that WP_Secrets_Option_Store extends WP_Secrets_Store.
	 *
	 * @coversNothing
	 */
	public function test_extends_the_store_class(): void {
		$this->assertInstanceOf( WP_Secrets_Store::class, new WP_Secrets_Option_Store(), 'WP_Secrets_Option_Store should extend WP_Secrets_Store.' ); // @phpstan-ignore method.alreadyNarrowedType (Pins the class it extends, so removing it fails a test.)
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::get() returns null when no record is stored.
	 *
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_get_returns_null_for_an_absent_secret(): void {
		$store = new WP_Secrets_Option_Store();

		$this->assertNull( $store->get( 'myplugin/absent' ), 'An absent secret should be returned as null.' );
	}

	/**
	 * Ensure that a site-scope record is returned unchanged after being stored.
	 *
	 * @covers WP_Secrets_Option_Store::set
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_set_then_get_round_trips_a_record_site_scope(): void {
		$store  = new WP_Secrets_Option_Store();
		$record = $this->sample_record();

		$this->assertTrue( $store->set( 'myplugin/key', $record ), 'Storing a site-scope record should succeed.' );
		$this->assertSame( $record, $store->get( 'myplugin/key' ), 'The site-scope record should be returned unchanged.' );
	}

	/**
	 * Ensure that a network-scope record is returned unchanged after being stored.
	 *
	 * @covers WP_Secrets_Option_Store::set
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_set_then_get_round_trips_a_record_network_scope(): void {
		$store  = new WP_Secrets_Option_Store();
		$record = $this->sample_record();

		$this->assertTrue( $store->set( 'myplugin/key', $record, true ), 'Storing a network-scope record should succeed.' );
		$this->assertSame( $record, $store->get( 'myplugin/key', true ), 'The network-scope record should be returned unchanged.' );
	}

	/**
	 * Ensure that site and network records with the same name are stored independently.
	 *
	 * @covers WP_Secrets_Option_Store::set
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_site_and_network_scope_do_not_collide(): void {
		$store          = new WP_Secrets_Option_Store();
		$site_record    = $this->sample_record();
		$network_record = array_merge( $this->sample_record(), array( 'marker' => 'network' ) );

		$this->assertTrue( $store->set( 'myplugin/key', $site_record, false ), 'Storing the record for myplugin/key in site scope should succeed.' );
		$this->assertTrue( $store->set( 'myplugin/key', $network_record, true ), 'Storing the record for myplugin/key in network scope should succeed.' );

		$this->assertSame( $site_record, $store->get( 'myplugin/key', false ), 'The site-scope record should not be overwritten by the network-scope record.' );
		$this->assertSame( $network_record, $store->get( 'myplugin/key', true ), 'The network-scope record should be stored separately from the site-scope record.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::get() returns a malformed record error for a non-array value.
	 *
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_get_returns_an_error_when_the_stored_value_is_not_an_array(): void {
		update_option( '_wp_secret_myplugin/key', 'not an array', false );

		$store = new WP_Secrets_Option_Store();

		$result = $store->get( 'myplugin/key' );

		$this->assertWPError( $result, 'A non-array stored value should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code(), 'A non-array stored value should produce the malformed record error code.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::delete() removes a stored record.
	 *
	 * @covers WP_Secrets_Option_Store::delete
	 */
	public function test_delete_removes_an_existing_record(): void {
		$store = new WP_Secrets_Option_Store();
		$this->assertTrue( $store->set( 'myplugin/key', $this->sample_record() ), 'Storing the record for myplugin/key should succeed.' );

		$this->assertTrue( $store->delete( 'myplugin/key' ), 'Deleting an existing record should succeed.' );
		$this->assertNull( $store->get( 'myplugin/key' ), 'A deleted record should no longer be returned.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::delete() returns true when no record is stored.
	 *
	 * @covers WP_Secrets_Option_Store::delete
	 */
	public function test_delete_on_an_absent_record_is_not_an_error(): void {
		$store = new WP_Secrets_Option_Store();

		$this->assertTrue( $store->delete( 'myplugin/never-existed' ), 'Deleting an absent record should report success.' );
	}

	/**
	 * Ensure that site-scope secret records are stored with autoloading disabled.
	 *
	 * Autoloaded options are loaded into memory on every request, so keeping secrets
	 * out of the autoload set limits where their ciphertext travels.
	 *
	 * @covers WP_Secrets_Option_Store::set
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_site_scope_records_are_not_autoloaded(): void {
		global $wpdb;

		$store = new WP_Secrets_Option_Store();
		$this->assertTrue( $store->set( 'myplugin/key', $this->sample_record() ), 'Storing the record for myplugin/key should succeed.' );

		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				'_wp_secret_myplugin/key'
			)
		);

		$this->assertContains( $autoload, array( 'no', 'off' ), 'Site-scope secret records should not be autoloaded.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::list_names() returns names without the option prefix.
	 *
	 * @covers WP_Secrets_Option_Store::list_names
	 */
	public function test_list_names_returns_stored_names_without_the_prefix(): void {
		$store = new WP_Secrets_Option_Store();
		$this->assertTrue( $store->set( 'myplugin/one', $this->sample_record() ), 'Storing the record for myplugin/one should succeed.' );
		$this->assertTrue( $store->set( 'myplugin/two', $this->sample_record() ), 'Storing the record for myplugin/two should succeed.' );

		$names = $store->list_names();

		$this->assertIsArray( $names, 'list_names() should return an array.' );
		$this->assertContains( 'myplugin/one', $names, 'The first stored name should be listed without the prefix.' );
		$this->assertContains( 'myplugin/two', $names, 'The second stored name should be listed without the prefix.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::list_names() excludes options that are not secret records.
	 *
	 * @covers WP_Secrets_Option_Store::list_names
	 */
	public function test_list_names_does_not_include_unrelated_options(): void {
		update_option( 'completely_unrelated_option', 'value' );

		$store = new WP_Secrets_Option_Store();
		$this->assertTrue( $store->set( 'myplugin/one', $this->sample_record() ), 'Storing the record for myplugin/one should succeed.' );

		$names = $store->list_names();

		$this->assertIsArray( $names, 'list_names() should return an array.' );
		$this->assertNotContains( 'completely_unrelated_option', $names, 'Options that are not secret records should not be listed.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::list_names() returns only the names from the requested scope.
	 *
	 * @covers WP_Secrets_Option_Store::list_names
	 */
	public function test_list_names_returns_only_the_requested_scope(): void {
		$store = new WP_Secrets_Option_Store();
		$this->assertTrue( $store->set( 'myplugin/site-only', $this->sample_record(), false ), 'Storing the record for myplugin/site-only in site scope should succeed.' );
		$this->assertTrue( $store->set( 'myplugin/network-only', $this->sample_record(), true ), 'Storing the record for myplugin/network-only in network scope should succeed.' );

		$site_names    = $store->list_names( false );
		$network_names = $store->list_names( true );

		$this->assertIsArray( $site_names, 'list_names() should return an array for the site scope.' );
		$this->assertIsArray( $network_names, 'list_names() should return an array for the network scope.' );
		$this->assertContains( 'myplugin/site-only', $site_names, 'The site-scope name should be listed in the site scope.' );
		$this->assertNotContains( 'myplugin/network-only', $site_names, 'The network-scope name should not be listed in the site scope.' );
		$this->assertContains( 'myplugin/network-only', $network_names, 'The network-scope name should be listed in the network scope.' );
		$this->assertNotContains( 'myplugin/site-only', $network_names, 'The site-scope name should not be listed in the network scope.' );
	}

	/**
	 * '_' is a single-character wildcard in SQL LIKE. An option name with any other
	 * character where the prefix's underscore falls must not match unless the prefix
	 * is properly escaped before the query is built.
	 *
	 * @covers WP_Secrets_Option_Store::list_names
	 */
	public function test_list_names_escapes_the_underscore_wildcard(): void {
		/*
		 * Would match "_wp_secret_%" under an *unescaped* LIKE, since '_' matches
		 * any single character – proving esc_like() is actually applied.
		 */
		update_option( '_wpXsecret_decoy/name', 'irrelevant value', false );

		$store = new WP_Secrets_Option_Store();
		$this->assertTrue( $store->set( 'myplugin/real', $this->sample_record() ), 'Storing the record for myplugin/real should succeed.' );

		$names = $store->list_names();

		$this->assertIsArray( $names, 'list_names() should return an array.' );
		$this->assertContains( 'myplugin/real', $names, 'The real secret name should be listed.' );
		$this->assertNotContains( 'decoy/name', $names, 'An option matching only via an unescaped underscore wildcard should not be listed.' );
	}

	/**
	 * Ensure that one failed read of a stored record does not report the secret as absent, or leave it cached as missing.
	 *
	 * @ticket 66424
	 *
	 * @covers WP_Secrets_Option_Store::get
	 * @covers WP_Secrets_Option_Store::read_stored_value
	 *
	 * @dataProvider data_scopes
	 *
	 * @param bool $network Whether to use network scope.
	 */
	public function test_one_failed_read_does_not_hide_a_stored_secret( bool $network ): void {
		$this->assertTrue( $this->write_secret( 'test/key', 'one', $network ), 'Storing the secret should succeed.' );

		$filter = $this->fail_record_selects( 'test/key', $network, 1 );
		$value  = $this->read_secret( 'test/key', $network );

		remove_filter( 'query', $filter );

		$this->assertSame( 'one', $value, 'A secret should be readable when the Options API read of its record fails once.' );
		$this->assertSame( 'one', $this->read_secret( 'test/key', $network ), 'The secret should still be readable after the failed read.' );

		if ( $network ) {
			$stored = get_site_option( WP_Secrets_Option_Store::NETWORK_PREFIX . 'test/key' );
		} else {
			$stored = get_option( WP_Secrets_Option_Store::SITE_PREFIX . 'test/key' );
		}

		$this->assertIsArray( $stored, 'The Options API should not keep the record cached as missing after the failed read.' );
	}

	/**
	 * Ensure that a write after one failed read keeps the previous version, including in the stored row.
	 *
	 * @ticket 66424
	 *
	 * @covers WP_Secrets_Option_Store::get
	 * @covers WP_Secrets_Option_Store::read_stored_value
	 *
	 * @dataProvider data_scopes
	 *
	 * @param bool $network Whether to use network scope.
	 */
	public function test_one_failed_read_during_a_write_keeps_the_previous_version( bool $network ): void {
		$this->assertTrue( $this->write_secret( 'test/key', 'one', $network ), 'Storing the first value should succeed.' );
		$this->assertTrue( $this->write_secret( 'test/key', 'two', $network ), 'Storing the second value should succeed.' );

		$filter = $this->fail_record_selects( 'test/key', $network, 1 );
		$result = $this->write_secret( 'test/key', 'three', $network );

		remove_filter( 'query', $filter );

		$this->assertTrue( $result, 'A write should succeed when the Options API read of the record fails once.' );
		$this->assertSame( 'three', $this->read_secret( 'test/key', $network ), 'The new value should be the current version.' );
		$this->assertSame( 'two', $this->read_secret( 'test/key', $network, WP_Secret_Version::PREVIOUS ), 'The value before the write should be the previous version.' );

		// Later requests read from the database, so the write must have updated the stored row.
		wp_cache_flush();

		$this->assertSame( 'three', $this->read_secret( 'test/key', $network ), 'The stored row should hold the new value as the current version.' );
		$this->assertSame( 'two', $this->read_secret( 'test/key', $network, WP_Secret_Version::PREVIOUS ), 'The stored row should hold the value before the write as the previous version.' );
	}

	/**
	 * Ensure that a row that exists but holds an empty value is reported as malformed, not absent.
	 *
	 * @ticket 66424
	 *
	 * @covers WP_Secrets_Option_Store::get
	 * @covers WP_Secrets_Option_Store::read_stored_value
	 *
	 * @dataProvider data_scopes
	 *
	 * @param bool $network Whether to use network scope.
	 */
	public function test_a_failed_read_of_an_empty_row_reports_a_malformed_record( bool $network ): void {
		$store = new WP_Secrets_Option_Store();

		if ( $network ) {
			$this->assertTrue( update_site_option( WP_Secrets_Option_Store::NETWORK_PREFIX . 'test/key', '' ), 'Storing the empty network row should succeed.' );
		} else {
			$this->assertTrue( update_option( WP_Secrets_Option_Store::SITE_PREFIX . 'test/key', '', false ), 'Storing the empty site row should succeed.' );
		}

		$filter = $this->fail_record_selects( 'test/key', $network, 1 );
		$result = $this->without_db_errors( array( $store, 'get' ), 'test/key', $network );

		remove_filter( 'query', $filter );

		$this->assertWPError( $result, 'An empty row should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code(), 'An empty row should produce the malformed record error code.' );
	}

	/**
	 * Ensure that a network row with a NULL value is reported as malformed, not absent.
	 *
	 * Unlike wp_options.option_value, wp_sitemeta.meta_value is nullable.
	 *
	 * @ticket 66424
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Option_Store::get
	 * @covers WP_Secrets_Option_Store::read_stored_value
	 */
	public function test_a_network_row_with_a_null_value_reports_a_malformed_record(): void {
		global $wpdb;

		$this->skipWithoutMultisite();

		$inserted = $wpdb->insert(
			$wpdb->sitemeta,
			array(
				'site_id'    => get_current_network_id(),
				'meta_key'   => WP_Secrets_Option_Store::NETWORK_PREFIX . 'test/key',
				'meta_value' => null,
			)
		);

		$this->assertSame( 1, $inserted, 'Inserting the network row with a NULL value should succeed.' );

		$store  = new WP_Secrets_Option_Store();
		$result = $store->get( 'test/key', true );

		$this->assertWPError( $result, 'A network row with a NULL value should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code(), 'A network row with a NULL value should produce the malformed record error code.' );
	}

	/**
	 * Ensure that a secret whose record cannot be read is reported as unavailable, and is not overwritten by a write.
	 *
	 * @ticket 66424
	 *
	 * @covers WP_Secrets_Option_Store::get
	 * @covers WP_Secrets_Option_Store::read_stored_value
	 *
	 * @dataProvider data_scopes
	 *
	 * @param bool $network Whether to use network scope.
	 */
	public function test_an_unreadable_secret_is_reported_and_not_overwritten( bool $network ): void {
		$this->assertTrue( $this->write_secret( 'test/key', 'one', $network ), 'Storing the first value should succeed.' );
		$this->assertTrue( $this->write_secret( 'test/key', 'two', $network ), 'Storing the second value should succeed.' );

		$filter = $this->fail_record_selects( 'test/key', $network, 10 );
		$read   = $this->read_secret( 'test/key', $network );
		$write  = $this->write_secret( 'test/key', 'three', $network );

		remove_filter( 'query', $filter );

		$this->assertWPError( $read, 'A read should return a WP_Error when the record cannot be read.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $read->get_error_code(), 'A read should produce the store unavailable error code when the record cannot be read.' );
		$this->assertWPError( $write, 'A write should return a WP_Error when the record cannot be read.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $write->get_error_code(), 'A write should produce the store unavailable error code when the record cannot be read.' );

		// Once the database recovers, the failed reads must not leave the secret looking missing.
		$this->assertSame( 'two', $this->read_secret( 'test/key', $network ), 'The current version should be unchanged once the record can be read again.' );
		$this->assertSame( 'one', $this->read_secret( 'test/key', $network, WP_Secret_Version::PREVIOUS ), 'The previous version should be unchanged once the record can be read again.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::get() returns an error, not null, when the read of an absent record fails.
	 *
	 * @ticket 66424
	 *
	 * @covers WP_Secrets_Option_Store::get
	 * @covers WP_Secrets_Option_Store::read_stored_value
	 *
	 * @dataProvider data_scopes
	 *
	 * @param bool $network Whether to use network scope.
	 */
	public function test_get_does_not_report_a_secret_as_absent_when_its_read_fails( bool $network ): void {
		$store = new WP_Secrets_Option_Store();

		$filter = $this->fail_record_selects( 'test/absent', $network, 10 );
		$result = $this->without_db_errors( array( $store, 'get' ), 'test/absent', $network );

		remove_filter( 'query', $filter );

		$this->assertWPError( $result, 'A failed read should be reported as a WP_Error, not as an absent secret.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code(), 'A failed read should produce the store unavailable error code.' );
		$this->assertNull( $store->get( 'test/absent', $network ), 'An absent secret should be returned as null once it can be read.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::delete() does not report success when it cannot tell whether the record still exists.
	 *
	 * @ticket 66424
	 *
	 * @covers WP_Secrets_Option_Store::delete
	 *
	 * @dataProvider data_scopes
	 *
	 * @param bool $network Whether to use network scope.
	 */
	public function test_delete_does_not_report_success_when_it_cannot_read_the_record( bool $network ): void {
		$store  = new WP_Secrets_Option_Store();
		$record = $this->sample_record();

		$this->assertTrue( $store->set( 'test/key', $record, $network ), 'Storing the record should succeed.' );

		$filter = $this->fail_record_selects( 'test/key', $network, 10 );
		$result = $this->without_db_errors( array( $store, 'delete' ), 'test/key', $network );

		remove_filter( 'query', $filter );

		$this->assertWPError( $result, 'A delete should return a WP_Error when the record cannot be read.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code(), 'A delete should produce the store unavailable error code when the record cannot be read.' );
		$this->assertSame( $record, $store->get( 'test/key', $network ), 'The record should still be stored after the failed delete.' );
	}

	/**
	 * Ensure that WP_Secrets_Option_Store::list_names() returns an error, not an empty list, when its query fails.
	 *
	 * @ticket 66424
	 *
	 * @covers WP_Secrets_Option_Store::list_names
	 *
	 * @dataProvider data_scopes
	 *
	 * @param bool $network Whether to use network scope.
	 */
	public function test_list_names_returns_an_error_when_its_query_fails( bool $network ): void {
		$store = new WP_Secrets_Option_Store();

		$this->assertTrue( $store->set( 'test/key', $this->sample_record(), $network ), 'Storing the record should succeed.' );

		$filter = static function ( $query ) {
			if ( str_starts_with( ltrim( $query ), 'SELECT' ) && str_contains( $query, ' LIKE ' ) ) {
				return 'SELECT * FROM wptests_table_that_does_not_exist';
			}

			return $query;
		};

		add_filter( 'query', $filter );
		$result = $this->without_db_errors( array( $store, 'list_names' ), $network );
		remove_filter( 'query', $filter );

		$this->assertWPError( $result, 'A failed listing should be reported as a WP_Error, not as an empty list.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code(), 'A failed listing should produce the store unavailable error code.' );
		$this->assertSame( array( 'test/key' ), $store->list_names( $network ), 'The secret should be listed once the query succeeds.' );
	}

	/**
	 * @return array<string, array{0: bool}>
	 */
	public static function data_scopes(): array {
		return array(
			'site scope'    => array( false ),
			'network scope' => array( true ),
		);
	}

	/**
	 * Writes a secret through the public API.
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param string $value   The value to store.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @return true|WP_Error
	 */
	private function write_secret( string $name, string $value, bool $network ) {
		return $this->without_db_errors( $network ? 'wp_set_network_secret' : 'wp_set_secret', $name, $value );
	}

	/**
	 * Reads a secret through the public API.
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @param string $version The version slot to read.
	 * @return string|null|WP_Error The revealed value, null, or the error.
	 */
	private function read_secret( string $name, bool $network, string $version = WP_Secret_Version::CURRENT ) {
		$secret = $this->without_db_errors( $network ? 'wp_get_network_secret' : 'wp_get_secret', $name, $version );

		return $secret instanceof WP_Secret ? $secret->reveal() : $secret;
	}

	/**
	 * Makes the next database reads of a record fail, as a killed query or a lost
	 * connection would, after removing it from the object cache so it is read again.
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @param int    $count   Number of reads to fail.
	 * @return callable The 'query' filter that was added.
	 */
	private function fail_record_selects( string $name, bool $network, int $count ): callable {
		$option_name = ( $network ? WP_Secrets_Option_Store::NETWORK_PREFIX : WP_Secrets_Option_Store::SITE_PREFIX ) . $name;

		if ( $network && is_multisite() ) {
			wp_cache_delete( get_current_network_id() . ':' . $option_name, 'site-options' );
		} else {
			wp_cache_delete( $option_name, 'options' );
		}

		$filter = static function ( $query ) use ( &$count, $option_name ) {
			if ( $count > 0 && str_starts_with( ltrim( $query ), 'SELECT' ) && str_contains( $query, "'$option_name'" ) ) {
				--$count;

				return 'SELECT * FROM wptests_table_that_does_not_exist';
			}

			return $query;
		};

		add_filter( 'query', $filter );

		return $filter;
	}

	/**
	 * Calls a function with database errors suppressed.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param callable $callback Function to call.
	 * @param mixed    ...$args  Arguments to pass.
	 * @return mixed The function's return value.
	 */
	private function without_db_errors( callable $callback, ...$args ) {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );
		$result   = $callback( ...$args );
		$wpdb->suppress_errors( $suppress );

		return $result;
	}
}
