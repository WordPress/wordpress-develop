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
	 * Ensure that a site secret written after switching to a site on another network can be read in that site's own requests.
	 *
	 * @ticket 66688
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_site_secret_written_after_switching_to_another_network_reads_on_that_network(): void {
		$this->skipWithoutMultisite();

		list( $network_id, $blog_id ) = $this->create_site_on_another_network();

		switch_to_blog( $blog_id );
		$stored = wp_set_secret( 'myplugin/api-key', 'cross-network-value' );
		restore_current_blog();

		$this->assertTrue( $stored, 'Setting myplugin/api-key on a site of another network should succeed.' );

		$secret = $this->on_site_of_network(
			$network_id,
			$blog_id,
			static function () {
				return wp_get_secret( 'myplugin/api-key' );
			}
		);

		$this->assertIsSecret( $secret, 'cross-network-value', 'The site secret should be readable in a request for its own network.' );
	}

	/**
	 * Ensure that a site secret stored in a request for another network can be read after switch_to_blog().
	 *
	 * @ticket 66688
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_site_secret_stored_on_another_network_reads_after_switch_to_blog(): void {
		$this->skipWithoutMultisite();

		list( $network_id, $blog_id ) = $this->create_site_on_another_network();

		$stored = $this->on_site_of_network(
			$network_id,
			$blog_id,
			static function () {
				return wp_set_secret( 'myplugin/api-key', 'own-network-value' );
			}
		);

		$this->assertTrue( $stored, 'Setting myplugin/api-key in a request for the other network should succeed.' );

		switch_to_blog( $blog_id );
		$secret = wp_get_secret( 'myplugin/api-key' );
		restore_current_blog();

		$this->assertIsSecret( $secret, 'own-network-value', 'The site secret should be readable after switching to its site from another network.' );
	}

	/**
	 * Ensure that moving a site to another network keeps its secrets readable.
	 *
	 * @ticket 66689
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_site_secret_stays_readable_after_its_site_moves_to_another_network(): void {
		$this->skipWithoutMultisite();

		list( $network_id ) = $this->create_site_on_another_network();
		$blog_id            = self::factory()->blog->create();

		switch_to_blog( $blog_id );
		$stored = wp_set_secret( 'myplugin/api-key', 'before-move' );
		restore_current_blog();

		$this->assertTrue( $stored, 'Setting myplugin/api-key before the move should succeed.' );

		$moved = wp_update_site(
			$blog_id,
			array(
				'network_id' => $network_id,
				'domain'     => 'example.net',
				'path'       => '/moved/',
			)
		);

		$this->assertSame( $blog_id, $moved, 'Moving the site to the other network should succeed.' );

		$secret = $this->on_site_of_network(
			$network_id,
			$blog_id,
			static function () {
				return wp_get_secret( 'myplugin/api-key' );
			}
		);

		$this->assertIsSecret( $secret, 'before-move', 'The site secret should be readable after its site moved to another network.' );
	}

	/**
	 * Ensure that every network of an install uses the one root key stored on the main network.
	 *
	 * @ticket 66691
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_every_network_uses_the_root_key_of_the_main_network(): void {
		$this->skipWithoutMultisite();

		list( $network_id, $blog_id ) = $this->create_site_on_another_network();

		$main_root_key = ( new WP_Secrets_Key_Manager() )->get_root_key();

		$other_root_key = $this->on_site_of_network(
			$network_id,
			$blog_id,
			static function () {
				return ( new WP_Secrets_Key_Manager() )->get_root_key();
			}
		);

		$this->assertIsString( $main_root_key, 'The main network should have a root key.' );
		$this->assertSame( $main_root_key, $other_root_key, 'The other network should use the main network\'s root key.' );
		$this->assertNotFalse( get_network_option( get_main_network_id(), WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'The root key should be stored on the main network.' );
		$this->assertFalse( get_network_option( $network_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'No root key should be stored on the other network.' );
	}

	/**
	 * Creates a second network with a site on it.
	 *
	 * @return int[] The new network's ID and the ID of its site.
	 */
	private function create_site_on_another_network(): array {
		$network_id = self::factory()->network->create(
			array(
				'domain' => 'example.net',
				'path'   => '/',
			)
		);

		$blog_id = self::factory()->blog->create(
			array(
				'network_id' => $network_id,
				'domain'     => 'example.net',
				'path'       => '/site/',
			)
		);

		return array( $network_id, $blog_id );
	}

	/**
	 * Runs a callback as in a request for a site on another network.
	 *
	 * switch_to_blog() changes the current site but not the current network, so
	 * this also makes the site's network the current one.
	 *
	 * @param int      $network_id The network to make current.
	 * @param int      $blog_id    The site to switch to.
	 * @param callable $callback   The callback to run.
	 * @return mixed The callback's return value.
	 */
	private function on_site_of_network( int $network_id, int $blog_id, callable $callback ) {
		$current_site            = $GLOBALS['current_site'];
		$GLOBALS['current_site'] = get_network( $network_id );
		switch_to_blog( $blog_id );

		try {
			return $callback();
		} finally {
			restore_current_blog();
			$GLOBALS['current_site'] = $current_site;
		}
	}
}
