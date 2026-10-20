<?php
/**
 * Conformance suite for WP_Secrets_Provider implementations.
 *
 * Extend this, return a provider from provider(), and the contract every provider
 * is held to gets checked against it. The point is that "implements
 * WP_Secrets_Provider" is a claim PHP can verify about method names and nothing
 * else – it cannot tell you that absence is reported as null rather than false, or
 * that an unreachable backend fails closed instead of looking empty, and those are
 * the properties that actually matter.
 *
 * A host writing a KMS-, HSM-, or platform-backed provider can run this against it
 * before shipping. That is deliberate: this contract is currently shaped by three
 * hosts *describing* what they need rather than by three working implementations,
 * so an executable statement of it is worth more than another paragraph of prose.
 *
 * Where the contract legitimately varies, the suite adapts rather than insisting:
 * a read-only provider is not asked to round-trip a value, and a provider with no
 * version history is not asked to produce a previous one. What does not vary is
 * checked for everyone.
 *
 * @package SecretsAPI
 */
abstract class WP_Secrets_Provider_Conformance extends WP_UnitTestCase {

	/**
	 * The provider under test. Fresh per test.
	 *
	 * @return WP_Secrets_Provider
	 */
	abstract protected function provider();

	/**
	 * A name this provider will accept, unique per test run.
	 *
	 * Overridable because a platform may have its own naming rules – a provider
	 * backed by an AWS Parameter Store path, say, is entitled to want something
	 * that looks like a path.
	 *
	 * @return string
	 */
	protected function conformance_name() {
		return 'conformance/subject';
	}

	/**
	 * Skips the current test when the provider does not accept writes.
	 *
	 * Read-only is a legitimate shape – it is the whole reason is_writable()
	 * exists – so the round-trip checks below do not apply to it. They are
	 * skipped rather than quietly passing, so the report says what was not proven.
	 *
	 * @param WP_Secrets_Provider $provider The provider under test.
	 */
	private function require_writable( WP_Secrets_Provider $provider ): void {
		if ( ! $provider->is_writable() ) {
			$this->markTestSkipped( 'Provider declares itself read-only; write-path conformance does not apply.' );
		}
	}

	/**
	 * Every `wp_secret_changed` call seen since record_changes(), as its arguments.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private $recorded_changes = array();

	/**
	 * Starts recording every `wp_secret_changed` call for the rest of the test.
	 *
	 * Asks for all seven arguments, so a provider that passes fewer is caught by
	 * the count rather than by a missing-argument error.
	 */
	private function record_changes(): void {
		$this->recorded_changes = array();

		add_action(
			'wp_secret_changed',
			function ( ...$args ): void {
				$this->recorded_changes[] = array_values( $args );
			},
			10,
			7
		);
	}

	/**
	 * Whether the provider currently returns a previous version for a name.
	 *
	 * Asked before and after retiring, and the answer is expected to differ, so
	 * this is marked impure to stop static analysis assuming the two agree.
	 *
	 * @phpstan-impure
	 *
	 * @param WP_Secrets_Provider $provider The provider under test.
	 * @param string              $name     The secret's name.
	 * @return bool
	 */
	private function has_previous_version( WP_Secrets_Provider $provider, $name ) {
		return $provider->get( $name, WP_Secret_Version::PREVIOUS ) instanceof WP_Secret;
	}

	// – declarations must be coherent ----------------------------------------

	public function test_reports_a_non_empty_label(): void {
		$label = $this->provider()->get_label();

		$this->assertIsString( $label );
		$this->assertNotSame( '', trim( $label ) );
	}

	public function test_reports_a_known_protection_boundary(): void {
		$this->assertContains(
			$this->provider()->get_protection_boundary(),
			array( WP_Secrets_Provider::BOUNDARY_WORDPRESS, WP_Secrets_Provider::BOUNDARY_PROVIDER ),
			'get_protection_boundary() must return one of the BOUNDARY_* constants.'
		);
	}

	public function test_is_writable_returns_a_bool(): void {
		$this->assertIsBool( $this->provider()->is_writable() );
	}

	/**
	 * The declaration has to match the behaviour, or a settings screen that trusts
	 * is_writable() will offer a save control that cannot work.
	 */
	public function test_a_read_only_provider_actually_refuses_writes(): void {
		$provider = $this->provider();

		if ( $provider->is_writable() ) {
			$this->markTestSkipped( 'Provider accepts writes; this checks the read-only declaration.' );
		}

		$result = $provider->set( $this->conformance_name(), 'value' );

		$this->assertWPError( $result );
		$this->assertSame(
			WP_SECRETS_ERROR_PROVIDER_READ_ONLY,
			$result->get_error_code(),
			'A provider that declares itself read-only must refuse with secret_provider_read_only.'
		);
	}

	// – absence is null, and only null ---------------------------------------

	/**
	 * The single most important property in the whole API. A name that was never
	 * set is null – not false, not an empty WP_Secret, not a WP_Error. Reporting
	 * absence as an error makes every caller treat a missing optional credential
	 * as an outage; reporting an outage as absence makes them treat it as deleted.
	 */
	public function test_a_name_that_was_never_set_is_null(): void {
		$result = $this->provider()->get( 'conformance/never-set-' . uniqid(), WP_Secret_Version::CURRENT );

		$this->assertNull( $result );
	}

	/**
	 * A provider with no version history reports absence for PREVIOUS, because
	 * that is what it is: there is no previous value. It is not an error, and it
	 * is not an excuse to return the current one.
	 */
	public function test_previous_on_a_provider_or_secret_without_history_is_null_not_error(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertNotWPError( $provider->set( $name, 'only-ever-one-value' ) );

		$previous = $provider->get( $name, WP_Secret_Version::PREVIOUS );

		$this->assertNotWPError( $previous, 'PREVIOUS with no previous value is absence, not an error.' );
		$this->assertNull( $previous );
	}

	public function test_deleting_something_absent_is_success(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$this->assertNotWPError(
			$provider->delete( 'conformance/never-set-' . uniqid() ),
			'Deleting a secret that does not exist is the state the caller asked for.'
		);
	}

	public function test_retiring_with_no_previous_version_is_success(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'value' );

		$this->assertNotWPError( $provider->retire_previous( $name ) );
	}

	// – the round trip --------------------------------------------------------

	public function test_a_stored_value_comes_back_intact(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertNotWPError( $provider->set( $name, 'sk_live_conformance_value' ) );

		$secret = $provider->get( $name, WP_Secret_Version::CURRENT );

		$this->assertInstanceOf( 'WP_Secret', $secret );
		$this->assertSame( 'sk_live_conformance_value', $secret->reveal() );
		$this->assertSame( $name, $secret->get_name() );
	}

	/**
	 * Fingerprints are how callers compare secrets without revealing them, so the
	 * same value must fingerprint the same way twice. A provider that returns a
	 * random or timestamped fingerprint breaks every comparison built on it.
	 */
	public function test_a_fingerprint_is_stable_for_the_same_value(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'stable-value' );

		$first_read  = $provider->get( $name, WP_Secret_Version::CURRENT );
		$second_read = $provider->get( $name, WP_Secret_Version::CURRENT );

		$this->assertInstanceOf( 'WP_Secret', $first_read );
		$this->assertInstanceOf( 'WP_Secret', $second_read );

		$first  = $first_read->fingerprint();
		$second = $second_read->fingerprint();

		$this->assertIsString( $first );
		$this->assertNotSame( '', $first );
		$this->assertSame( $first, $second );
	}

	public function test_a_deleted_secret_reads_as_absent(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'value' );
		$this->assertNotWPError( $provider->delete( $name ) );

		$this->assertNull( $provider->get( $name, WP_Secret_Version::CURRENT ) );
	}

	// – every change is reported ----------------------------------------------

	/**
	 * The API does not fire `wp_secret_changed` on a provider's behalf, so a
	 * provider that forgets to is a site whose audit log went quiet the day the
	 * host installed it. Nothing else would notice.
	 */
	public function test_a_successful_write_reports_a_change(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->record_changes();

		$this->assertNotWPError( $provider->set( $name, 'UNIQUE-CONFORMANCE-CANARY-7c1d' ) );

		$this->assertCount( 1, $this->recorded_changes, 'set() must fire wp_secret_changed exactly once.' );

		$change = $this->recorded_changes[0];

		$this->assertCount( 7, $change, 'wp_secret_changed takes seven arguments, ending with $network.' );
		$this->assertSame( $name, $change[0] );
		$this->assertContains( $change[1], array( 'created', 'updated' ) );
		$this->assertIsInt( $change[2], 'The actor is a user id, or 0.' );
		$this->assertIsInt( $change[3], 'The timestamp is a Unix timestamp.' );
		$this->assertIsString( $change[4], "The old fingerprint is a string, '' when there is none." );
		$this->assertIsString( $change[5], "The new fingerprint is a string, '' when there is none." );
		$this->assertFalse( $change[6], 'A site-scope write must report $network as false.' );
		$this->assertNotContains( 'UNIQUE-CONFORMANCE-CANARY-7c1d', $change, 'wp_secret_changed must never be passed a value.' );
	}

	public function test_an_action_override_is_reported_as_given(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$this->record_changes();

		$this->assertNotWPError( $provider->set( $this->conformance_name(), 'value', false, false, 'imported' ) );

		$this->assertCount( 1, $this->recorded_changes );
		$this->assertSame( 'imported', $this->recorded_changes[0][1] );
	}

	/**
	 * A listener cannot otherwise tell a network secret from a site secret of the
	 * same name, and on a network those are different credentials.
	 */
	public function test_a_network_scope_change_reports_its_scope(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->record_changes();

		$this->assertNotWPError( $provider->set( $name, 'value', true ) );
		$this->assertNotWPError( $provider->delete( $name, true ) );

		$this->assertCount( 2, $this->recorded_changes );
		$this->assertTrue( $this->recorded_changes[0][6], 'A network-scope write must report $network as true.' );
		$this->assertTrue( $this->recorded_changes[1][6], 'A network-scope delete must report $network as true.' );
	}

	public function test_deleting_a_secret_reports_a_change(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'value' );

		$this->record_changes();

		$this->assertNotWPError( $provider->delete( $name ) );

		$this->assertCount( 1, $this->recorded_changes, 'delete() must fire wp_secret_changed exactly once.' );
		$this->assertSame( $name, $this->recorded_changes[0][0] );
		$this->assertSame( 'deleted', $this->recorded_changes[0][1] );
	}

	/**
	 * Only asked of a provider that keeps a previous version and clears it on
	 * request. One with no history, or whose backend manages its own, has nothing
	 * to report.
	 */
	public function test_retiring_a_previous_version_reports_a_change(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'first-value' );
		$provider->set( $name, 'second-value' );

		if ( ! $this->has_previous_version( $provider, $name ) ) {
			$this->markTestSkipped( 'Provider keeps no previous version; there is nothing to retire.' );
		}

		$this->record_changes();

		$this->assertNotWPError( $provider->retire_previous( $name ) );

		if ( $this->has_previous_version( $provider, $name ) ) {
			$this->markTestSkipped( 'Provider leaves version history to its backend; nothing was cleared.' );
		}

		$this->assertCount( 1, $this->recorded_changes, 'retire_previous() must fire wp_secret_changed exactly once.' );
		$this->assertSame( $name, $this->recorded_changes[0][0] );
		$this->assertSame( 'retired', $this->recorded_changes[0][1] );
	}

	public function test_a_refused_write_reports_no_change(): void {
		$provider = $this->provider();

		if ( $provider->is_writable() ) {
			$this->markTestSkipped( 'Provider accepts writes; this checks that a refused one stays silent.' );
		}

		$this->record_changes();

		$this->assertWPError( $provider->set( $this->conformance_name(), 'value' ) );
		$this->assertCount( 0, $this->recorded_changes, 'A write that did not happen must not be reported as a change.' );
	}

	// – listing never leaks ---------------------------------------------------

	public function test_listing_returns_an_array_or_an_error_and_never_a_value(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'UNIQUE-CONFORMANCE-CANARY-3f9b' );

		$entries = $provider->list_secrets();

		$this->assertIsArray( $entries );

		$dump = wp_json_encode( $entries );

		$this->assertIsString( $dump, 'list_secrets() must return something that can be encoded and inspected.' );
		$this->assertStringNotContainsString(
			'UNIQUE-CONFORMANCE-CANARY-3f9b',
			$dump,
			'list_secrets() must never expose a plaintext.'
		);
	}

	public function test_listing_includes_a_stored_secret(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'value' );

		$entries = $provider->list_secrets();

		$this->assertNotWPError( $entries );
		$this->assertContains( $name, wp_list_pluck( $entries, 'name' ) );
	}

	/**
	 * A prefix filter that matched loosely would hand a caller names it did not
	 * ask about, which for a listing keyed by owner is exactly the wrong direction
	 * to be wrong in.
	 */
	public function test_listing_respects_a_name_prefix(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$provider->set( 'conformance-a/one', 'value' );
		$provider->set( 'conformance-b/two', 'value' );

		$entries = $provider->list_secrets( 'conformance-a' );

		$this->assertNotWPError( $entries );

		$names = wp_list_pluck( $entries, 'name' );

		$this->assertContains( 'conformance-a/one', $names );
		$this->assertNotContains( 'conformance-b/two', $names );
	}
}
