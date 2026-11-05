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
	 * Ensure that WP_Secrets_Option_Store implements the WP_Secrets_Store interface.
	 *
	 * @coversNothing
	 */
	public function test_implements_the_store_interface(): void {
		$this->assertInstanceOf( WP_Secrets_Store::class, new WP_Secrets_Option_Store(), 'WP_Secrets_Option_Store should implement WP_Secrets_Store.' ); // @phpstan-ignore method.alreadyNarrowedType (Pins the interface the class declares, so removing it fails a test.)
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
}
