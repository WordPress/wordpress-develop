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
 * @package WordPress
 * @subpackage Secrets
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

	/**
	 * Ensure that the provider reports a label that is neither empty nor only whitespace.
	 */
	public function test_reports_a_non_empty_label(): void {
		$label = $this->provider()->get_label();

		$this->assertIsString( $label, 'The provider label should be a string.' );
		$this->assertNotSame( '', trim( $label ), 'The provider label should not be empty or only whitespace.' );
	}

	/**
	 * Ensure that the provider reports one of the BOUNDARY_* constants as its protection boundary.
	 */
	public function test_reports_a_known_protection_boundary(): void {
		$this->assertContains(
			$this->provider()->get_protection_boundary(),
			array( WP_Secrets_Provider::BOUNDARY_WORDPRESS, WP_Secrets_Provider::BOUNDARY_PROVIDER ),
			'get_protection_boundary() should return one of the BOUNDARY_* constants.'
		);
	}

	/**
	 * Ensure that the provider declares whether it accepts writes as a boolean.
	 */
	public function test_is_writable_returns_a_bool(): void {
		$this->assertIsBool( $this->provider()->is_writable(), 'The provider should report is_writable() as a bool.' );
	}

	/**
	 * The declaration has to match the behavior, or a settings screen that trusts
	 * is_writable() will offer a save control that cannot work.
	 */
	public function test_a_read_only_provider_actually_refuses_writes(): void {
		$provider = $this->provider();

		if ( $provider->is_writable() ) {
			$this->markTestSkipped( 'Provider accepts writes; this checks the read-only declaration.' );
		}

		$result = $provider->set( $this->conformance_name(), 'value' );

		$this->assertWPError( $result, 'The provider should refuse a write with a WP_Error when read-only.' );
		$this->assertSame(
			WP_SECRETS_ERROR_PROVIDER_READ_ONLY,
			$result->get_error_code(),
			'A provider that declares itself read-only should refuse with secret_provider_read_only.'
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

		$this->assertNull( $result, 'The provider should return null for a name that was never set.' );
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
		$this->assertNotWPError( $provider->set( $name, 'only-ever-one-value' ), 'The provider should accept the initial write.' );

		$previous = $provider->get( $name, WP_Secret_Version::PREVIOUS );

		$this->assertNotWPError( $previous, 'PREVIOUS with no previous value is absence, not an error.' );
		$this->assertNull( $previous, 'The provider should return null for a PREVIOUS version that does not exist.' );
	}

	/**
	 * Ensure that the provider treats deleting a secret that was never set as success.
	 */
	public function test_deleting_something_absent_is_success(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$this->assertNotWPError(
			$provider->delete( 'conformance/never-set-' . uniqid() ),
			'Deleting a secret that does not exist is the state the caller asked for.'
		);
	}

	/**
	 * Ensure that the provider treats retiring a secret that has no previous version as success.
	 */
	public function test_retiring_with_no_previous_version_is_success(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertTrue( $provider->set( $name, 'value' ), 'Setting the secret to value should succeed.' );

		$this->assertNotWPError( $provider->retire_previous( $name ), 'The provider should treat retiring with no previous version as success.' );
	}

	// – the round trip --------------------------------------------------------

	/**
	 * Ensure that the provider returns a stored value unchanged, under the name it was stored as.
	 */
	public function test_a_stored_value_comes_back_intact(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertNotWPError( $provider->set( $name, 'sk_live_conformance_value' ), 'The provider should accept the write.' );

		$secret = $provider->get( $name, WP_Secret_Version::CURRENT );

		$this->assertInstanceOf( 'WP_Secret', $secret, 'The provider should return the stored value as a WP_Secret.' );
		$this->assertSame( 'sk_live_conformance_value', $secret->reveal(), 'The provider should return the stored value unchanged.' );
		$this->assertSame( $name, $secret->get_name(), 'The provider should return the secret under the name it was stored as.' );
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
		$this->assertTrue( $provider->set( $name, 'stable-value' ), 'Setting the secret to stable-value should succeed.' );

		$first_read  = $provider->get( $name, WP_Secret_Version::CURRENT );
		$second_read = $provider->get( $name, WP_Secret_Version::CURRENT );

		$this->assertInstanceOf( 'WP_Secret', $first_read, 'The provider should return the first read as a WP_Secret.' );
		$this->assertInstanceOf( 'WP_Secret', $second_read, 'The provider should return the second read as a WP_Secret.' );

		$first  = $first_read->fingerprint();
		$second = $second_read->fingerprint();

		$this->assertIsString( $first, 'The provider should return the fingerprint as a string.' );
		$this->assertNotSame( '', $first, 'The provider should return a non-empty fingerprint.' );
		$this->assertSame( $first, $second, 'The provider should return the same fingerprint for the same value.' );
	}

	/**
	 * A settings form saves every field on every submit, so writing the value a
	 * secret already holds is routine. A provider that treats it as a rotation
	 * discards the previous version while consumers may still be using it.
	 *
	 * Compared by fingerprint rather than by value, so a provider that withholds
	 * values is checked too. Only asked of a provider that keeps a previous
	 * version; one with no history has nothing to lose.
	 *
	 * @ticket 66353
	 */
	public function test_writing_an_unchanged_value_keeps_the_previous_version(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertTrue( $provider->set( $name, 'first-value' ), 'Setting the secret to first-value should succeed.' );
		$this->assertTrue( $provider->set( $name, 'second-value' ), 'Setting the secret to second-value should succeed.' );

		$previous = $provider->get( $name, WP_Secret_Version::PREVIOUS );

		if ( ! $previous instanceof WP_Secret ) {
			$this->markTestSkipped( 'Provider keeps no previous version; there is nothing to keep.' );
		}

		$this->assertNotWPError( $provider->set( $name, 'second-value' ), 'The provider should accept a write of the unchanged value.' );

		$kept = $provider->get( $name, WP_Secret_Version::PREVIOUS );

		$this->assertInstanceOf( 'WP_Secret', $kept, 'Writing an unchanged value should not discard the previous version.' );
		$this->assertSame( $previous->fingerprint(), $kept->fingerprint(), 'Writing an unchanged value should not replace the previous version.' );
	}

	/**
	 * Ensure that the provider reports a deleted secret as absent.
	 */
	public function test_a_deleted_secret_reads_as_absent(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertTrue( $provider->set( $name, 'value' ), 'Setting the secret to value should succeed.' );
		$this->assertNotWPError( $provider->delete( $name ), 'The provider should accept the delete.' );

		$this->assertNull( $provider->get( $name, WP_Secret_Version::CURRENT ), 'The provider should report a deleted secret as null.' );
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

		$this->assertNotWPError( $provider->set( $name, 'UNIQUE-CONFORMANCE-CANARY-7c1d' ), 'The provider should accept the write.' );

		$this->assertCount( 1, $this->recorded_changes, 'set() should fire wp_secret_changed exactly once.' );

		$change = $this->recorded_changes[0];

		$this->assertCount( 7, $change, 'wp_secret_changed takes seven arguments, ending with $network.' );
		$this->assertSame( $name, $change[0], 'The provider should report the name of the written secret.' );
		$this->assertContains( $change[1], array( 'created', 'updated' ), 'The provider should report a write as created or updated.' );
		$this->assertIsInt( $change[2], 'The actor is a user id, or 0.' );
		$this->assertIsInt( $change[3], 'The timestamp is a Unix timestamp.' );
		$this->assertIsString( $change[4], "The old fingerprint is a string, '' when there is none." );
		$this->assertIsString( $change[5], "The new fingerprint is a string, '' when there is none." );
		$this->assertFalse( $change[6], 'A site-scope write should report $network as false.' );
		$this->assertNotContains( 'UNIQUE-CONFORMANCE-CANARY-7c1d', $change, 'wp_secret_changed should never be passed a value.' );
	}

	/**
	 * Ensure that the provider reports an action override passed to set() to wp_secret_changed unchanged.
	 */
	public function test_an_action_override_is_reported_as_given(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$this->record_changes();

		$this->assertNotWPError( $provider->set( $this->conformance_name(), 'value', false, false, 'imported' ), 'The provider should accept a write with an action override.' );

		$this->assertCount( 1, $this->recorded_changes, 'The provider should fire wp_secret_changed exactly once for the write.' );
		$this->assertSame( 'imported', $this->recorded_changes[0][1], 'The provider should report the action override as given.' );
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

		$this->assertNotWPError( $provider->set( $name, 'value', true ), 'The provider should accept a network-scope write.' );
		$this->assertNotWPError( $provider->delete( $name, true ), 'The provider should accept a network-scope delete.' );

		$this->assertCount( 2, $this->recorded_changes, 'The provider should fire wp_secret_changed once for each network-scope change.' );
		$this->assertTrue( $this->recorded_changes[0][6], 'A network-scope write should report $network as true.' );
		$this->assertTrue( $this->recorded_changes[1][6], 'A network-scope delete should report $network as true.' );
	}

	/**
	 * Ensure that the provider fires wp_secret_changed once, as deleted, when a secret is deleted.
	 */
	public function test_deleting_a_secret_reports_a_change(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertTrue( $provider->set( $name, 'value' ), 'Setting the secret to value should succeed.' );

		$this->record_changes();

		$this->assertNotWPError( $provider->delete( $name ), 'The provider should accept the delete.' );

		$this->assertCount( 1, $this->recorded_changes, 'delete() should fire wp_secret_changed exactly once.' );
		$this->assertSame( $name, $this->recorded_changes[0][0], 'The provider should report the name of the deleted secret.' );
		$this->assertSame( 'deleted', $this->recorded_changes[0][1], 'The provider should report a delete as deleted.' );
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
		$this->assertTrue( $provider->set( $name, 'first-value' ), 'Setting the secret to first-value should succeed.' );
		$this->assertTrue( $provider->set( $name, 'second-value' ), 'Setting the secret to second-value should succeed.' );

		if ( ! $this->has_previous_version( $provider, $name ) ) {
			$this->markTestSkipped( 'Provider keeps no previous version; there is nothing to retire.' );
		}

		$this->record_changes();

		$this->assertNotWPError( $provider->retire_previous( $name ), 'The provider should accept retiring the previous version.' );

		if ( $this->has_previous_version( $provider, $name ) ) {
			$this->markTestSkipped( 'Provider leaves version history to its backend; nothing was cleared.' );
		}

		$this->assertCount( 1, $this->recorded_changes, 'retire_previous() should fire wp_secret_changed exactly once.' );
		$this->assertSame( $name, $this->recorded_changes[0][0], 'The provider should report the name of the retired secret.' );
		$this->assertSame( 'retired', $this->recorded_changes[0][1], 'The provider should report a retire as retired.' );
	}

	/**
	 * Ensure that a read-only provider does not fire wp_secret_changed for a write it refused.
	 */
	public function test_a_refused_write_reports_no_change(): void {
		$provider = $this->provider();

		if ( $provider->is_writable() ) {
			$this->markTestSkipped( 'Provider accepts writes; this checks that a refused one stays silent.' );
		}

		$this->record_changes();

		$this->assertWPError( $provider->set( $this->conformance_name(), 'value' ), 'The provider should refuse a write with a WP_Error when read-only.' );
		$this->assertCount( 0, $this->recorded_changes, 'A write that did not happen should not be reported as a change.' );
	}

	// – listing never leaks ---------------------------------------------------

	/**
	 * Ensure that the provider's listing of secrets never contains a stored plaintext value.
	 *
	 * The whole listing is encoded and searched rather than checked field by field, so a
	 * value leaked under any key, including one the contract does not define, is caught.
	 */
	public function test_listing_never_contains_a_stored_value(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertTrue( $provider->set( $name, 'UNIQUE-CONFORMANCE-CANARY-3f9b' ), 'Setting the secret to UNIQUE-CONFORMANCE-CANARY-3f9b should succeed.' );

		$entries = $provider->list_secrets();

		$this->assertIsArray( $entries, 'The provider should return the listing as an array.' );

		$dump = wp_json_encode( $entries );

		$this->assertIsString( $dump, 'list_secrets() should return something that can be encoded and inspected.' );
		$this->assertStringNotContainsString(
			'UNIQUE-CONFORMANCE-CANARY-3f9b',
			$dump,
			'list_secrets() should never expose a plaintext.'
		);
	}

	/**
	 * Ensure that the provider includes the name of a stored secret in its listing.
	 */
	public function test_listing_includes_a_stored_secret(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertTrue( $provider->set( $name, 'value' ), 'Setting the secret to value should succeed.' );

		$entries = $provider->list_secrets();

		$this->assertNotWPError( $entries, 'The provider should return the listing without a WP_Error.' );
		$this->assertContains( $name, wp_list_pluck( $entries, 'name' ), 'The provider should include a stored secret in the listing.' );
	}

	/**
	 * A prefix filter that matched loosely would hand a caller names it did not
	 * ask about, which for a listing keyed by owner is exactly the wrong direction
	 * to be wrong in.
	 */
	public function test_listing_respects_a_name_prefix(): void {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$this->assertTrue( $provider->set( 'conformance-a/one', 'value' ), 'Setting conformance-a/one to value should succeed.' );
		$this->assertTrue( $provider->set( 'conformance-b/two', 'value' ), 'Setting conformance-b/two to value should succeed.' );

		$entries = $provider->list_secrets( 'conformance-a' );

		$this->assertNotWPError( $entries, 'The provider should return the prefixed listing without a WP_Error.' );

		$names = wp_list_pluck( $entries, 'name' );

		$this->assertContains( 'conformance-a/one', $names, 'The provider should include a secret matching the prefix.' );
		$this->assertNotContains( 'conformance-b/two', $names, 'The provider should omit a secret not matching the prefix.' );
	}
}
