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
	 * Original current network object, preserved across multisite tests that switch networks.
	 *
	 * @var WP_Network|null
	 */
	private $original_current_site;

	public function set_up(): void {
		parent::set_up();

		if ( is_multisite() ) {
			global $current_site;
			$this->original_current_site = $current_site;
		}
	}

	public function tear_down(): void {
		if ( is_multisite() ) {
			global $current_site;
			$current_site = $this->original_current_site;
		}

		parent::tear_down();
	}

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
	 * Ensure that writing a site secret after switching to a site on another network
	 * uses that network's root key so native requests on that site can decrypt it.
	 *
	 * @ticket 66688
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_site_secret_written_after_switching_to_a_site_on_another_network_uses_that_networks_root_key(): void {
		$this->skipWithoutMultisite();

		$this->assertTrue( wp_set_network_secret( 'myplugin/net1-key', 'net1-value' ), 'Initializing network 1 should succeed.' );

		$network_2         = self::factory()->network->create();
		$site_on_network_2 = self::factory()->blog->create(
			array(
				'network_id' => $network_2,
			)
		);

		$this->assertFalse(
			get_network_option( $network_2, WP_Secrets_Key_Manager::ROOT_KEY_OPTION ),
			'Network 2 should not have a root key before a secret is written on its site.'
		);

		switch_to_blog( $site_on_network_2 );
		$written = wp_set_secret( 'myplugin/cross-net', 'secret-for-net2' );
		restore_current_blog();

		$this->assertTrue( $written, 'Writing a site secret after switching to a site on network 2 should succeed.' );

		$network_2_root_key = get_network_option( $network_2, WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		$this->assertIsString( $network_2_root_key, 'Writing on a network 2 site should create network 2 root key in sitemeta.' );
		$this->assertNotSame(
			get_network_option( get_current_network_id(), WP_Secrets_Key_Manager::ROOT_KEY_OPTION ),
			$network_2_root_key,
			'Network 2 root key should be distinct from network 1 root key.'
		);

		global $current_site;
		$current_site = get_network( $network_2 );

		switch_to_blog( $site_on_network_2 );
		$secret = wp_get_secret( 'myplugin/cross-net' );
		restore_current_blog();

		$this->assertIsSecret( $secret, 'secret-for-net2', 'A native request on network 2 should decrypt the site secret written via cross-network switch.' );
	}

	/**
	 * Ensure that a site secret stored natively on a site in another network
	 * is readable after switch_to_blog() from the main network.
	 *
	 * @ticket 66688
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_site_secret_on_another_network_is_readable_after_switch_to_blog(): void {
		$this->skipWithoutMultisite();

		$this->assertTrue( wp_set_network_secret( 'myplugin/net1-key', 'net1-value' ), 'Initializing network 1 should succeed.' );

		$network_2         = self::factory()->network->create();
		$site_on_network_2 = self::factory()->blog->create(
			array(
				'network_id' => $network_2,
			)
		);

		global $current_site;
		$original_network = $current_site;
		$current_site     = get_network( $network_2 );

		switch_to_blog( $site_on_network_2 );
		$native_written = wp_set_secret( 'myplugin/native-net2', 'native-secret' );
		restore_current_blog();

		$current_site = $original_network;

		$this->assertTrue( $native_written, 'Writing a secret natively on network 2 should succeed.' );
		$this->assertNull( wp_get_secret( 'myplugin/native-net2' ), 'The network 2 site secret should not exist on the network 1 site.' );

		switch_to_blog( $site_on_network_2 );
		$switched_secret = wp_get_secret( 'myplugin/native-net2' );
		restore_current_blog();

		$this->assertIsSecret( $switched_secret, 'native-secret', 'Switching from network 1 to a network 2 site should decrypt that site secret.' );
	}

	/**
	 * Ensure that network-scope secrets stay on the current network after switch_to_blog().
	 *
	 * @ticket 66688
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_get_network_secret
	 */
	public function test_network_secret_after_switching_to_a_site_on_another_network_stays_on_the_current_network(): void {
		$this->skipWithoutMultisite();

		$this->assertTrue( wp_set_network_secret( 'myplugin/net-secret', 'network-1-value' ), 'Setting network 1 secret should succeed.' );

		$network_2         = self::factory()->network->create();
		$site_on_network_2 = self::factory()->blog->create(
			array(
				'network_id' => $network_2,
			)
		);

		switch_to_blog( $site_on_network_2 );
		$read_while_switched  = wp_get_network_secret( 'myplugin/net-secret' );
		$wrote_while_switched = wp_set_network_secret( 'myplugin/from-switched', 'still-network-1' );
		restore_current_blog();

		$this->assertIsSecret( $read_while_switched, 'network-1-value', 'Reading a network secret after switching to a site on another network should still read the current network secret.' );
		$this->assertTrue( $wrote_while_switched, 'Writing a network secret after switching to a site on another network should succeed.' );
		$this->assertIsSecret( wp_get_network_secret( 'myplugin/from-switched' ), 'still-network-1', 'A network secret written while switched should be stored on the current network.' );
		$this->assertFalse(
			get_network_option( $network_2, WP_Secrets_Option_Store::NETWORK_PREFIX . 'myplugin/from-switched' ),
			'Switching to a site on network 2 should not write network-scope secrets to network 2.'
		);
	}
}
