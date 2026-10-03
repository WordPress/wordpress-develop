<?php
/**
 * Tests for the network-scope public functions.
 *
 * @group secrets
 */
class Tests_Secrets_NetworkScope extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	public function test_set_then_get_round_trips() {
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'value' ) );

		$secret = wp_get_network_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'value', $secret->reveal() );
	}

	public function test_get_returns_null_for_an_absent_secret() {
		$this->assertNull( wp_get_network_secret( 'myplugin/never-set' ) );
	}

	public function test_site_and_network_scope_are_independent_under_the_same_name() {
		wp_set_secret( 'myplugin/api-key', 'site-value' );
		wp_set_network_secret( 'myplugin/api-key', 'network-value' );

		$this->assertSame( 'site-value', wp_get_secret( 'myplugin/api-key' )->reveal() );
		$this->assertSame( 'network-value', wp_get_network_secret( 'myplugin/api-key' )->reveal() );
	}

	public function test_delete_removes_a_network_secret() {
		wp_set_network_secret( 'myplugin/api-key', 'value' );

		$this->assertTrue( wp_delete_network_secret( 'myplugin/api-key' ) );
		$this->assertNull( wp_get_network_secret( 'myplugin/api-key' ) );
	}

	public function test_delete_does_not_touch_the_site_scope_secret_of_the_same_name() {
		wp_set_secret( 'myplugin/api-key', 'site-value' );
		wp_set_network_secret( 'myplugin/api-key', 'network-value' );

		wp_delete_network_secret( 'myplugin/api-key' );

		$this->assertSame( 'site-value', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	public function test_previous_version_and_demotion_work_the_same_as_site_scope() {
		wp_set_network_secret( 'myplugin/api-key', 'first-value' );
		wp_set_network_secret( 'myplugin/api-key', 'second-value' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value', true );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value', true );
	}

	public function test_retire_clears_the_previous_slot() {
		wp_set_network_secret( 'myplugin/api-key', 'first-value' );
		wp_set_network_secret( 'myplugin/api-key', 'second-value' );

		$this->assertTrue( wp_retire_network_secret_version( 'myplugin/api-key' ) );
		$this->assertNull( wp_get_network_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	public function test_list_returns_network_secrets_only() {
		wp_set_secret( 'myplugin/site-only', 'value' );
		wp_set_network_secret( 'myplugin/network-only', 'value' );

		$names = wp_list_pluck( wp_list_network_secrets(), 'name' );

		$this->assertSame( array( 'myplugin/network-only' ), $names );
	}

	public function test_an_invalid_version_is_a_wp_error_same_as_site_scope() {
		$this->setExpectedIncorrectUsage( '_wp_secrets_get' );

		$result = wp_get_network_secret( 'myplugin/api-key', 'not-a-real-version' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	public function test_set_rejects_an_invalid_name() {
		$result = wp_set_network_secret( 'Not A Valid Name', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	/**
	 * The entire point of network scope: a secret written from one blog's context
	 * must read back identically from another blog's context. Requires multisite.
	 */
	public function test_network_secret_is_readable_from_any_blog() {
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
	 */
	public function test_site_secret_is_not_readable_from_a_different_blog() {
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
