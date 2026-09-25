<?php
/**
 * Tests for WP_Secrets_Option_Store.
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsOptionStore extends WP_UnitTestCase {

	private function sample_record() {
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

	public function test_implements_the_store_interface() {
		$this->assertInstanceOf( WP_Secrets_Store::class, new WP_Secrets_Option_Store() );
	}

	public function test_get_returns_null_for_an_absent_secret() {
		$store = new WP_Secrets_Option_Store();

		$this->assertNull( $store->get( 'myplugin/absent' ) );
	}

	public function test_set_then_get_round_trips_a_record_site_scope() {
		$store  = new WP_Secrets_Option_Store();
		$record = $this->sample_record();

		$this->assertTrue( $store->set( 'myplugin/key', $record ) );
		$this->assertSame( $record, $store->get( 'myplugin/key' ) );
	}

	public function test_set_then_get_round_trips_a_record_network_scope() {
		$store  = new WP_Secrets_Option_Store();
		$record = $this->sample_record();

		$this->assertTrue( $store->set( 'myplugin/key', $record, true ) );
		$this->assertSame( $record, $store->get( 'myplugin/key', true ) );
	}

	public function test_site_and_network_scope_do_not_collide() {
		$store          = new WP_Secrets_Option_Store();
		$site_record    = $this->sample_record();
		$network_record = array_merge( $this->sample_record(), array( 'marker' => 'network' ) );

		$store->set( 'myplugin/key', $site_record, false );
		$store->set( 'myplugin/key', $network_record, true );

		$this->assertSame( $site_record, $store->get( 'myplugin/key', false ) );
		$this->assertSame( $network_record, $store->get( 'myplugin/key', true ) );
	}

	public function test_set_rejects_a_non_array_record() {
		$store = new WP_Secrets_Option_Store();

		$result = $store->set( 'myplugin/key', 'not an array' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code() );
	}

	public function test_get_returns_an_error_when_the_stored_value_is_not_an_array() {
		update_option( '_wp_secret_myplugin/key', 'not an array', false );

		$store = new WP_Secrets_Option_Store();

		$result = $store->get( 'myplugin/key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code() );
	}

	public function test_delete_removes_an_existing_record() {
		$store = new WP_Secrets_Option_Store();
		$store->set( 'myplugin/key', $this->sample_record() );

		$this->assertTrue( $store->delete( 'myplugin/key' ) );
		$this->assertNull( $store->get( 'myplugin/key' ) );
	}

	public function test_delete_on_an_absent_record_is_not_an_error() {
		$store = new WP_Secrets_Option_Store();

		$this->assertTrue( $store->delete( 'myplugin/never-existed' ) );
	}

	public function test_site_scope_records_are_not_autoloaded() {
		global $wpdb;

		$store = new WP_Secrets_Option_Store();
		$store->set( 'myplugin/key', $this->sample_record() );

		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				'_wp_secret_myplugin/key'
			)
		);

		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}

	public function test_list_names_returns_stored_names_without_the_prefix() {
		$store = new WP_Secrets_Option_Store();
		$store->set( 'myplugin/one', $this->sample_record() );
		$store->set( 'myplugin/two', $this->sample_record() );

		$names = $store->list_names();

		$this->assertContains( 'myplugin/one', $names );
		$this->assertContains( 'myplugin/two', $names );
	}

	public function test_list_names_does_not_include_unrelated_options() {
		update_option( 'completely_unrelated_option', 'value' );

		$store = new WP_Secrets_Option_Store();
		$store->set( 'myplugin/one', $this->sample_record() );

		$this->assertNotContains( 'completely_unrelated_option', $store->list_names() );
	}

	public function test_list_names_does_not_include_network_scope_names() {
		$store = new WP_Secrets_Option_Store();
		$store->set( 'myplugin/site-only', $this->sample_record(), false );
		$store->set( 'myplugin/network-only', $this->sample_record(), true );

		$this->assertContains( 'myplugin/site-only', $store->list_names( false ) );
		$this->assertNotContains( 'myplugin/network-only', $store->list_names( false ) );
		$this->assertContains( 'myplugin/network-only', $store->list_names( true ) );
		$this->assertNotContains( 'myplugin/site-only', $store->list_names( true ) );
	}

	/**
	 * '_' is a single-character wildcard in SQL LIKE. An option name with any other
	 * character where the prefix's underscore falls must not match unless the prefix
	 * is properly escaped before the query is built.
	 */
	public function test_list_names_escapes_the_underscore_wildcard() {
		// Would match "_wp_secret_%" under an *unescaped* LIKE, since '_' matches
		// any single character -- proving esc_like() is actually applied.
		update_option( '_wpXsecret_decoy/name', 'irrelevant value', false );

		$store = new WP_Secrets_Option_Store();
		$store->set( 'myplugin/real', $this->sample_record() );

		$names = $store->list_names();

		$this->assertContains( 'myplugin/real', $names );
		$this->assertNotContains( 'decoy/name', $names );
	}
}
