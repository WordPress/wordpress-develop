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
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'value' ) );

		$secret = wp_get_network_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'value', $secret->reveal() );
	}

	/**
	 * Ensure that wp_get_network_secret() returns null for a network secret that was never set.
	 *
	 * @covers ::wp_get_network_secret
	 */
	public function test_get_returns_null_for_an_absent_secret(): void {
		$this->assertNull( wp_get_network_secret( 'myplugin/never-set' ) );
	}

	/**
	 * Ensure that a site secret and a network secret with the same name hold separate values.
	 *
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_get_network_secret
	 */
	public function test_site_and_network_scope_are_independent_under_the_same_name(): void {
		wp_set_secret( 'myplugin/api-key', 'site-value' );
		wp_set_network_secret( 'myplugin/api-key', 'network-value' );

		$site_secret    = wp_get_secret( 'myplugin/api-key' );
		$network_secret = wp_get_network_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $site_secret );
		$this->assertInstanceOf( WP_Secret::class, $network_secret );
		$this->assertSame( 'site-value', $site_secret->reveal() );
		$this->assertSame( 'network-value', $network_secret->reveal() );
	}

	/**
	 * Ensure that wp_delete_network_secret() removes a network secret so it can no longer be read.
	 *
	 * @covers ::wp_delete_network_secret
	 */
	public function test_delete_removes_a_network_secret(): void {
		wp_set_network_secret( 'myplugin/api-key', 'value' );

		$this->assertTrue( wp_delete_network_secret( 'myplugin/api-key' ) );
		$this->assertNull( wp_get_network_secret( 'myplugin/api-key' ) );
	}

	/**
	 * Ensure that wp_delete_network_secret() leaves a site secret of the same name in place.
	 *
	 * @covers ::wp_delete_network_secret
	 */
	public function test_delete_does_not_touch_the_site_scope_secret_of_the_same_name(): void {
		wp_set_secret( 'myplugin/api-key', 'site-value' );
		wp_set_network_secret( 'myplugin/api-key', 'network-value' );

		wp_delete_network_secret( 'myplugin/api-key' );

		$site_secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $site_secret );
		$this->assertSame( 'site-value', $site_secret->reveal() );
	}

	/**
	 * Ensure that overwriting a network secret demotes the old value to the previous slot.
	 *
	 * @covers ::wp_set_network_secret
	 */
	public function test_previous_version_and_demotion_work_the_same_as_site_scope(): void {
		wp_set_network_secret( 'myplugin/api-key', 'first-value' );
		wp_set_network_secret( 'myplugin/api-key', 'second-value' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value', true );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value', true );
	}

	/**
	 * Ensure that wp_retire_network_secret_version() clears the previous version of a network secret.
	 *
	 * @covers ::wp_retire_network_secret_version
	 */
	public function test_retire_clears_the_previous_slot(): void {
		wp_set_network_secret( 'myplugin/api-key', 'first-value' );
		wp_set_network_secret( 'myplugin/api-key', 'second-value' );

		$this->assertTrue( wp_retire_network_secret_version( 'myplugin/api-key' ) );
		$this->assertNull( wp_get_network_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
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

		wp_set_network_secret( 'myplugin/api-key', 'first-value' );
		wp_set_network_secret( 'myplugin/api-key', 'second-value' );
		wp_retire_network_secret_version( 'myplugin/api-key' );
		wp_delete_network_secret( 'myplugin/api-key' );
		wp_set_secret( 'myplugin/api-key', 'site-value' );

		$this->assertSame(
			array(
				array( 'created', true ),
				array( 'updated', true ),
				array( 'retired', true ),
				array( 'deleted', true ),
				array( 'created', false ),
			),
			$scopes
		);
	}

	/**
	 * Ensure that wp_list_network_secrets() lists network secrets and omits site secrets.
	 *
	 * @covers ::wp_list_network_secrets
	 */
	public function test_list_returns_network_secrets_only(): void {
		wp_set_secret( 'myplugin/site-only', 'value' );
		wp_set_network_secret( 'myplugin/network-only', 'value' );

		$entries = wp_list_network_secrets();

		$this->assertIsArray( $entries );
		$this->assertSame( array( 'myplugin/network-only' ), wp_list_pluck( $entries, 'name' ) );
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

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	/**
	 * Ensure that wp_set_network_secret() rejects a name that fails validation.
	 *
	 * @covers ::wp_set_network_secret
	 */
	public function test_set_rejects_an_invalid_name(): void {
		$result = wp_set_network_secret( 'Not A Valid Name', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	/**
	 * The entire point of network scope: a secret written from one blog's context
	 * must read back identically from another blog's context. Requires multisite.
	 *
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_get_network_secret
	 */
	public function test_network_secret_is_readable_from_any_blog(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: proves network secrets are not bound to the blog that wrote them.' );
		}

		wp_set_network_secret( 'myplugin/api-key', 'cross-blog-value' );

		$second_blog_id = self::factory()->blog->create();
		switch_to_blog( $second_blog_id );
		$secret = wp_get_network_secret( 'myplugin/api-key' );
		restore_current_blog();

		$this->assertIsSecret( $secret, 'cross-blog-value', 'Read from a second blog:' );
	}

	/**
	 * The mirror image: a site-scope secret must NOT be readable from another blog,
	 * proving there is no implicit fallback between scopes and no accidental sharing
	 * across blogs for site-scope secrets.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_site_secret_is_not_readable_from_a_different_blog(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: proves site secrets do not leak across blogs.' );
		}

		wp_set_secret( 'myplugin/api-key', 'blog-one-value' );

		$second_blog_id = self::factory()->blog->create();
		switch_to_blog( $second_blog_id );
		$result = wp_get_secret( 'myplugin/api-key' );
		restore_current_blog();

		$this->assertNull( $result );
	}
}
