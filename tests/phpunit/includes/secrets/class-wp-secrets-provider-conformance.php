<?php
/**
 * Conformance suite for WP_Secrets_Provider implementations.
 *
 * Extend this, return a provider from provider(), and the contract every provider
 * is held to gets checked against it. The point is that "implements
 * WP_Secrets_Provider" is a claim PHP can verify about method names and nothing
 * else -- it cannot tell you that absence is reported as null rather than false, or
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
	 * Overridable because a platform may have its own naming rules -- a provider
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
	 * Read-only is a legitimate shape -- it is the whole reason is_writable()
	 * exists -- so the round-trip checks below do not apply to it. They are
	 * skipped rather than quietly passing, so the report says what was not proven.
	 */
	private function require_writable( WP_Secrets_Provider $provider ) {
		if ( ! $provider->is_writable() ) {
			$this->markTestSkipped( 'Provider declares itself read-only; write-path conformance does not apply.' );
		}
	}

	// -- declarations must be coherent ----------------------------------------

	public function test_reports_a_non_empty_label() {
		$label = $this->provider()->get_label();

		$this->assertIsString( $label );
		$this->assertNotSame( '', trim( $label ) );
	}

	public function test_reports_a_known_protection_boundary() {
		$this->assertContains(
			$this->provider()->get_protection_boundary(),
			array( WP_Secrets_Provider::BOUNDARY_WORDPRESS, WP_Secrets_Provider::BOUNDARY_PROVIDER ),
			'get_protection_boundary() must return one of the BOUNDARY_* constants.'
		);
	}

	public function test_is_writable_returns_a_bool() {
		$this->assertIsBool( $this->provider()->is_writable() );
	}

	/**
	 * The declaration has to match the behaviour, or a settings screen that trusts
	 * is_writable() will offer a save control that cannot work.
	 */
	public function test_a_read_only_provider_actually_refuses_writes() {
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

	// -- absence is null, and only null ---------------------------------------

	/**
	 * The single most important property in the whole API. A name that was never
	 * set is null -- not false, not an empty WP_Secret, not a WP_Error. Reporting
	 * absence as an error makes every caller treat a missing optional credential
	 * as an outage; reporting an outage as absence makes them treat it as deleted.
	 */
	public function test_a_name_that_was_never_set_is_null() {
		$result = $this->provider()->get( 'conformance/never-set-' . uniqid(), WP_Secret_Version::CURRENT );

		$this->assertNull( $result );
	}

	/**
	 * A provider with no version history reports absence for PREVIOUS, because
	 * that is what it is: there is no previous value. It is not an error, and it
	 * is not an excuse to return the current one.
	 */
	public function test_previous_on_a_provider_or_secret_without_history_is_null_not_error() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$this->assertNotWPError( $provider->set( $name, 'only-ever-one-value' ) );

		$previous = $provider->get( $name, WP_Secret_Version::PREVIOUS );

		$this->assertNotWPError( $previous, 'PREVIOUS with no previous value is absence, not an error.' );
		$this->assertNull( $previous );
	}

	public function test_deleting_something_absent_is_success() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$this->assertNotWPError(
			$provider->delete( 'conformance/never-set-' . uniqid() ),
			'Deleting a secret that does not exist is the state the caller asked for.'
		);
	}

	public function test_retiring_with_no_previous_version_is_success() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'value' );

		$this->assertNotWPError( $provider->retire_previous( $name ) );
	}

	// -- the round trip --------------------------------------------------------

	public function test_a_stored_value_comes_back_intact() {
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
	public function test_a_fingerprint_is_stable_for_the_same_value() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'stable-value' );

		$first  = $provider->get( $name, WP_Secret_Version::CURRENT )->fingerprint();
		$second = $provider->get( $name, WP_Secret_Version::CURRENT )->fingerprint();

		$this->assertIsString( $first );
		$this->assertNotSame( '', $first );
		$this->assertSame( $first, $second );
	}

	public function test_a_deleted_secret_reads_as_absent() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'value' );
		$this->assertNotWPError( $provider->delete( $name ) );

		$this->assertNull( $provider->get( $name, WP_Secret_Version::CURRENT ) );
	}

	// -- listing never leaks ---------------------------------------------------

	public function test_listing_returns_an_array_or_an_error_and_never_a_value() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'UNIQUE-CONFORMANCE-CANARY-3f9b' );

		$entries = $provider->list_secrets();

		$this->assertIsArray( $entries );
		$this->assertStringNotContainsString(
			'UNIQUE-CONFORMANCE-CANARY-3f9b',
			wp_json_encode( $entries ),
			'list_secrets() must never expose a plaintext.'
		);
	}

	public function test_listing_includes_a_stored_secret() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$name = $this->conformance_name();
		$provider->set( $name, 'value' );

		$names = wp_list_pluck( $provider->list_secrets(), 'name' );

		$this->assertContains( $name, $names );
	}

	/**
	 * A prefix filter that matched loosely would hand a caller names it did not
	 * ask about, which for a listing keyed by owner is exactly the wrong direction
	 * to be wrong in.
	 */
	public function test_listing_respects_a_name_prefix() {
		$provider = $this->provider();
		$this->require_writable( $provider );

		$provider->set( 'conformance-a/one', 'value' );
		$provider->set( 'conformance-b/two', 'value' );

		$names = wp_list_pluck( $provider->list_secrets( 'conformance-a' ), 'name' );

		$this->assertContains( 'conformance-a/one', $names );
		$this->assertNotContains( 'conformance-b/two', $names );
	}
}
