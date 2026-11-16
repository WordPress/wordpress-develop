<?php
/**
 * Tests for the network-scope public functions.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_NetworkScope extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	/**
	 * Ensure that a value stored by wp_set_network_secret() is returned by wp_get_network_secret().
	 *
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_get_network_secret
	 */
	public function test_set_then_get_round_trips(): void {
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'value' ), 'Setting a network secret should succeed.' );

		$secret = wp_get_network_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The network secret should be readable as a WP_Secret.' );
		$this->assertSame( 'value', $secret->reveal(), 'The network secret should return the value it was set to.' );
	}

	/**
	 * Ensure that wp_get_network_secret() returns null for a network secret that was never set.
	 *
	 * @covers ::wp_get_network_secret
	 */
	public function test_get_returns_null_for_an_absent_secret(): void {
		$this->assertNull( wp_get_network_secret( 'myplugin/never-set' ), 'A network secret that was never set should be null.' );
	}

	/**
	 * Ensure that a site secret and a network secret with the same name hold separate values.
	 *
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_get_network_secret
	 */
	public function test_site_and_network_scope_are_independent_under_the_same_name(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'site-value' ), 'Setting myplugin/api-key to site-value should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'network-value' ), 'Setting network secret myplugin/api-key to network-value should succeed.' );

		$site_secret    = wp_get_secret( 'myplugin/api-key' );
		$network_secret = wp_get_network_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $site_secret, 'The site secret should be readable as a WP_Secret.' );
		$this->assertInstanceOf( WP_Secret::class, $network_secret, 'The network secret should be readable as a WP_Secret.' );
		$this->assertSame( 'site-value', $site_secret->reveal(), 'The site secret should keep its own value.' );
		$this->assertSame( 'network-value', $network_secret->reveal(), 'The network secret should keep its own value.' );
	}

	/**
	 * Ensure that wp_delete_network_secret() removes a network secret so it can no longer be read.
	 *
	 * @covers ::wp_delete_network_secret
	 */
	public function test_delete_removes_a_network_secret(): void {
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'value' ), 'Setting network secret myplugin/api-key to value should succeed.' );

		$this->assertTrue( wp_delete_network_secret( 'myplugin/api-key' ), 'Deleting a network secret should succeed.' );
		$this->assertNull( wp_get_network_secret( 'myplugin/api-key' ), 'A deleted network secret should be null.' );
	}

	/**
	 * Ensure that wp_delete_network_secret() leaves a site secret of the same name in place.
	 *
	 * @covers ::wp_delete_network_secret
	 */
	public function test_delete_does_not_touch_the_site_scope_secret_of_the_same_name(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'site-value' ), 'Setting myplugin/api-key to site-value should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'network-value' ), 'Setting network secret myplugin/api-key to network-value should succeed.' );

		$this->assertTrue( wp_delete_network_secret( 'myplugin/api-key' ), 'Deleting network secret myplugin/api-key should succeed.' );

		$site_secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $site_secret, 'The site secret should still be readable as a WP_Secret.' );
		$this->assertSame( 'site-value', $site_secret->reveal(), 'The site secret should keep its value after the network secret is deleted.' );
	}

	/**
	 * Ensure that overwriting a network secret demotes the old value to the previous slot.
	 *
	 * @covers ::wp_set_network_secret
	 */
	public function test_overwriting_a_network_secret_demotes_the_old_value(): void {
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'first-value' ), 'Setting network secret myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'second-value' ), 'Setting network secret myplugin/api-key to second-value should succeed.' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value', true, 'The previous slot should decrypt to the first network value.' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value', true, 'The current slot should decrypt to the second network value.' );
	}

	/**
	 * Ensure that wp_retire_network_secret_version() clears the previous version of a network secret.
	 *
	 * @covers ::wp_retire_network_secret_version
	 */
	public function test_retire_clears_the_previous_slot(): void {
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'first-value' ), 'Setting network secret myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'second-value' ), 'Setting network secret myplugin/api-key to second-value should succeed.' );

		$this->assertTrue( wp_retire_network_secret_version( 'myplugin/api-key' ), 'Retiring the previous network version should succeed.' );
		$this->assertNull( wp_get_network_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ), 'The previous network version should be gone after retiring it.' );
	}

	/**
	 * A listener is told which scope changed. Without it, a network secret and a
	 * site secret of the same name are indistinguishable in an audit log.
	 *
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_retire_network_secret_version
	 * @covers ::wp_delete_network_secret
	 * @covers ::wp_set_secret
	 */
	public function test_change_hook_reports_network_scope(): void {
		$scopes = array();
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$scopes ) {
				$scopes[] = array( $args[1], $args[6] );
			},
			10,
			7
		);

		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'first-value' ), 'Setting network secret myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'second-value' ), 'Setting network secret myplugin/api-key to second-value should succeed.' );
		$this->assertTrue( wp_retire_network_secret_version( 'myplugin/api-key' ), 'Retiring the previous version of network secret myplugin/api-key should succeed.' );
		$this->assertTrue( wp_delete_network_secret( 'myplugin/api-key' ), 'Deleting network secret myplugin/api-key should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'site-value' ), 'Setting myplugin/api-key to site-value should succeed.' );

		$this->assertSame(
			array(
				array( 'created', true ),
				array( 'updated', true ),
				array( 'retired', true ),
				array( 'deleted', true ),
				array( 'created', false ),
			),
			$scopes,
			'wp_secret_changed should report each action with its network scope flag.'
		);
	}

	/**
	 * Ensure that wp_list_network_secrets() lists network secrets and omits site secrets.
	 *
	 * @covers ::wp_list_network_secrets
	 */
	public function test_list_returns_network_secrets_only(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/site-only', 'value' ), 'Setting myplugin/site-only to value should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/network-only', 'value' ), 'Setting network secret myplugin/network-only to value should succeed.' );

		$entries = wp_list_network_secrets();

		$this->assertIsArray( $entries, 'wp_list_network_secrets() should return an array.' );
		$this->assertSame( array( 'myplugin/network-only' ), wp_list_pluck( $entries, 'name' ), 'The network listing should contain only the network secret.' );
	}

	/**
	 * Ensure that wp_get_network_secret() returns a WP_Error for an unrecognized version.
	 *
	 * @covers ::wp_get_network_secret
	 * @covers ::_wp_secrets_get
	 */
	public function test_an_invalid_version_is_a_wp_error_same_as_site_scope(): void {
		$this->setExpectedIncorrectUsage( '_wp_secrets_get' );

		$result = wp_get_network_secret( 'myplugin/api-key', 'not-a-real-version' ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result, 'An invalid version should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code(), 'An invalid version should be rejected with WP_SECRETS_ERROR_INVALID_ARGUMENT.' );
	}

	/**
	 * Ensure that wp_set_network_secret() rejects a name that fails validation.
	 *
	 * @covers ::wp_set_network_secret
	 */
	public function test_set_rejects_an_invalid_name(): void {
		$result = wp_set_network_secret( 'Not A Valid Name', 'value' );

		$this->assertWPError( $result, 'An invalid name should be rejected with a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code(), 'An invalid name should be rejected with WP_SECRETS_ERROR_INVALID_NAME.' );
	}

	/**
	 * The entire point of network scope: a secret written from one blog's context
	 * must read back identically from another blog's context. Requires multisite.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_get_network_secret
	 */
	public function test_network_secret_is_readable_from_any_blog(): void {
		$this->skipWithoutMultisite();

		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'cross-blog-value' ), 'Setting network secret myplugin/api-key to cross-blog-value should succeed.' );

		$second_blog_id = self::factory()->blog->create();
		switch_to_blog( $second_blog_id );
		$secret = wp_get_network_secret( 'myplugin/api-key' );
		restore_current_blog();

		$this->assertIsSecret( $secret, 'cross-blog-value', 'The network secret should be readable from a second blog.' );
	}

	/**
	 * The mirror image: a site-scope secret must NOT be readable from another blog,
	 * proving there is no implicit fallback between scopes and no accidental sharing
	 * across blogs for site-scope secrets.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_site_secret_is_not_readable_from_a_different_blog(): void {
		$this->skipWithoutMultisite();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'blog-one-value' ), 'Setting myplugin/api-key to blog-one-value should succeed.' );

		$second_blog_id = self::factory()->blog->create();
		switch_to_blog( $second_blog_id );
		$result = wp_get_secret( 'myplugin/api-key' );
		restore_current_blog();

		$this->assertNull( $result, 'A site secret should not be readable from a different blog.' );
	}

	/**
	 * Ensure that a network secret stored before a conversion to multisite can still be read.
	 *
	 * @ticket 66686
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_get_network_secret
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_network_secret_stored_before_conversion_reads_after_conversion(): void {
		$this->skipWithoutMultisite();

		$this->store_network_secret_before_conversion( 'myplugin/api-key', 'pre-conversion-value' );

		$this->assertIsSecret( wp_get_network_secret( 'myplugin/api-key' ), 'pre-conversion-value', 'A network secret stored before the conversion should still be readable.' );
	}

	/**
	 * Ensure that reading a network secret stored before a conversion to multisite does not move it.
	 *
	 * @ticket 66686
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_reading_a_network_secret_stored_before_conversion_does_not_move_it(): void {
		$this->skipWithoutMultisite();

		$this->store_network_secret_before_conversion( 'myplugin/api-key', 'pre-conversion-value' );

		$this->assertInstanceOf( WP_Secret::class, wp_get_network_secret( 'myplugin/api-key' ), 'The network secret should be readable.' );
		$this->assertIsArray( get_blog_option( get_main_site_id(), '_wp_network_secret_myplugin/api-key' ), 'Reading the network secret should leave its record in the main site\'s options.' );
		$this->assertFalse( get_site_option( '_wp_network_secret_myplugin/api-key' ), 'Reading the network secret should not copy its record to sitemeta.' );
	}

	/**
	 * Ensure that a network secret stored before a conversion to multisite is listed once.
	 *
	 * @ticket 66686
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_list_network_secrets
	 * @covers WP_Secrets_Option_Store::list_names
	 */
	public function test_network_secret_stored_before_conversion_is_listed(): void {
		$this->skipWithoutMultisite();

		$this->store_network_secret_before_conversion( 'myplugin/api-key', 'pre-conversion-value' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/other-key', 'post-conversion-value' ), 'Setting network secret myplugin/other-key after the conversion should succeed.' );

		$names = wp_list_pluck( wp_list_network_secrets(), 'name' );

		sort( $names );

		$this->assertSame( array( 'myplugin/api-key', 'myplugin/other-key' ), $names, 'Network secrets from before and after the conversion should each be listed once.' );
	}

	/**
	 * Ensure that writing a network secret stored before a conversion to multisite moves it to sitemeta and keeps its old value.
	 *
	 * @ticket 66686
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_network_secret
	 * @covers WP_Secrets_Option_Store::set
	 */
	public function test_writing_a_network_secret_stored_before_conversion_moves_it(): void {
		$this->skipWithoutMultisite();

		$this->store_network_secret_before_conversion( 'myplugin/api-key', 'pre-conversion-value' );

		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'post-conversion-value' ), 'Overwriting the network secret after the conversion should succeed.' );

		$this->assertIsSecret( wp_get_network_secret( 'myplugin/api-key' ), 'post-conversion-value', 'The network secret should read its new value.' );
		$this->assertIsSecret( wp_get_network_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ), 'pre-conversion-value', 'The value stored before the conversion should be kept as the previous version.' );
		$this->assertIsArray( get_site_option( '_wp_network_secret_myplugin/api-key' ), 'The record should now be stored in sitemeta.' );
		$this->assertFalse( get_blog_option( get_main_site_id(), '_wp_network_secret_myplugin/api-key' ), 'The record should be removed from the main site\'s options.' );
	}

	/**
	 * Ensure that deleting a network secret stored before a conversion to multisite removes it.
	 *
	 * @ticket 66686
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_delete_network_secret
	 * @covers WP_Secrets_Option_Store::delete
	 */
	public function test_deleting_a_network_secret_stored_before_conversion_removes_it(): void {
		$this->skipWithoutMultisite();

		$this->store_network_secret_before_conversion( 'myplugin/api-key', 'pre-conversion-value' );

		$this->assertTrue( wp_delete_network_secret( 'myplugin/api-key' ), 'Deleting the network secret should succeed.' );

		$this->assertNull( wp_get_network_secret( 'myplugin/api-key' ), 'The deleted network secret should no longer exist.' );
		$this->assertFalse( get_blog_option( get_main_site_id(), '_wp_network_secret_myplugin/api-key' ), 'The record should be removed from the main site\'s options.' );
	}

	/**
	 * Ensure that deleting a network secret also removes a copy left from before a conversion to multisite.
	 *
	 * @ticket 66686
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_delete_network_secret
	 * @covers WP_Secrets_Option_Store::delete
	 */
	public function test_deleting_a_network_secret_does_not_bring_back_its_pre_conversion_copy(): void {
		$this->skipWithoutMultisite();

		$this->store_network_secret_before_conversion( 'myplugin/api-key', 'pre-conversion-value' );
		$stale = get_blog_option( get_main_site_id(), '_wp_network_secret_myplugin/api-key' );

		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'post-conversion-value' ), 'Overwriting the network secret after the conversion should succeed.' );

		// A copy that was not removed when the record moved, for example because that delete failed.
		$this->assertTrue( update_blog_option( get_main_site_id(), '_wp_network_secret_myplugin/api-key', $stale ), 'Restoring the pre-conversion copy should succeed.' );

		$this->assertTrue( wp_delete_network_secret( 'myplugin/api-key' ), 'Deleting the network secret should succeed.' );
		$this->assertNull( wp_get_network_secret( 'myplugin/api-key' ), 'The pre-conversion copy should not be read once the network secret is deleted.' );
	}

	/**
	 * Ensure that a network secret stored before a conversion to multisite is not read on another network.
	 *
	 * @ticket 66686
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Option_Store::get
	 */
	public function test_network_secret_stored_before_conversion_is_not_read_on_another_network(): void {
		$this->skipWithoutMultisite();

		$this->store_network_secret_before_conversion( 'myplugin/api-key', 'pre-conversion-value' );

		$network_id = self::factory()->network->create(
			array(
				'domain' => 'example.net',
				'path'   => '/',
			)
		);

		$current_site            = $GLOBALS['current_site'];
		$GLOBALS['current_site'] = get_network( $network_id );

		try {
			$secret = wp_get_network_secret( 'myplugin/api-key' );
		} finally {
			$GLOBALS['current_site'] = $current_site;
		}

		$this->assertNull( $secret, 'Another network should not read the main network\'s pre-conversion network secrets.' );
	}

	/**
	 * Stores a network secret where a single site keeps it, in its options table, as
	 * if the install was converted to multisite afterwards.
	 *
	 * @param string $name  The secret's namespaced name.
	 * @param string $value The secret's value.
	 */
	private function store_network_secret_before_conversion( string $name, string $value ): void {
		$option_name = "_wp_network_secret_{$name}";

		$this->assertTrue( wp_set_network_secret( $name, $value ), "Setting network secret {$name} should succeed." );

		$record = get_site_option( $option_name );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertTrue( delete_site_option( $option_name ), 'Removing the record from sitemeta should succeed.' );
		$this->assertTrue( update_blog_option( get_main_site_id(), $option_name, $record ), 'Storing the record in the main site\'s options should succeed.' );
	}
}
