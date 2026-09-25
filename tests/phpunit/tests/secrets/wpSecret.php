<?php
/**
 * Tests for WP_Secret.
 *
 * @package SecretsAPI
 */

/**
 * Masking, serialization refusal, and vault lifecycle for WP_Secret.
 *
 * @group secrets
 */
class Tests_Secrets_WPSecret extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	const PLAINTEXT   = 'sk_live_super_secret_value';
	const NAME        = 'myplugin/api-key';
	const FINGERPRINT = 'abc123def456';

	private function make_secret() {
		return new WP_Secret( self::NAME, self::PLAINTEXT, self::FINGERPRINT );
	}

	public function test_class_is_final() {
		$reflection = new ReflectionClass( WP_Secret::class );

		$this->assertTrue( $reflection->isFinal() );
	}

	public function test_implements_json_serializable() {
		$this->assertInstanceOf( JsonSerializable::class, $this->make_secret() );
	}

	public function test_reveal_returns_the_exact_plaintext() {
		$secret = $this->make_secret();

		$this->assertSame( self::PLAINTEXT, $secret->reveal() );
	}

	public function test_fingerprint_returns_the_exact_fingerprint() {
		$secret = $this->make_secret();

		$this->assertSame( self::FINGERPRINT, $secret->fingerprint() );
	}

	public function test_get_name_returns_the_exact_name() {
		$secret = $this->make_secret();

		$this->assertSame( self::NAME, $secret->get_name() );
	}

	public function test_constructor_rejects_a_non_string_value() {
		$this->expectException( InvalidArgumentException::class );

		new WP_Secret( self::NAME, 12345, self::FINGERPRINT );
	}

	public function test_constructor_rejects_an_empty_name() {
		$this->expectException( InvalidArgumentException::class );

		new WP_Secret( '', self::PLAINTEXT, self::FINGERPRINT );
	}

	public function test_constructor_rejects_an_empty_fingerprint() {
		$this->expectException( InvalidArgumentException::class );

		new WP_Secret( self::NAME, self::PLAINTEXT, '' );
	}

	/**
	 * Every representation short of reveal() must exclude the plaintext.
	 *
	 * @dataProvider data_masking_surfaces
	 */
	public function test_masking_surfaces_never_contain_the_plaintext( $callback ) {
		$secret = $this->make_secret();

		$output = $callback( $secret );

		$this->assertStringNotContainsString( self::PLAINTEXT, $output );
	}

	public function data_masking_surfaces() {
		return array(
			'(string) cast'        => array(
				function ( $secret ) {
					return (string) $secret;
				},
			),
			'string interpolation' => array(
				function ( $secret ) {
					return "{$secret}";
				},
			),
			'json_encode'          => array(
				function ( $secret ) {
					return json_encode( $secret );
				},
			),
			'var_dump'             => array(
				function ( $secret ) {
					ob_start();
					var_dump( $secret );

					return ob_get_clean();
				},
			),
			'print_r'              => array(
				function ( $secret ) {
					return print_r( $secret, true );
				},
			),
			'var_export'           => array(
				function ( $secret ) {
					return var_export( $secret, true );
				},
			),
			'error_log'            => array(
				function ( $secret ) {
					$file = tempnam( sys_get_temp_dir(), 'wp-secret-test-' );
					error_log( $secret, 3, $file );
					$contents = file_get_contents( $file );
					unlink( $file );

					return $contents;
				},
			),
		);
	}

	public function test_to_string_yields_the_masked_placeholder() {
		$secret = $this->make_secret();

		$this->assertSame( '[secret:' . self::NAME . ']', (string) $secret );
	}

	public function test_json_encode_yields_the_masked_placeholder() {
		$secret = $this->make_secret();

		// json_encode() escapes '/' by default; decode rather than compare raw JSON.
		$this->assertSame( '[secret:' . self::NAME . ']', json_decode( json_encode( $secret ) ) );
	}

	public function test_var_dump_yields_the_masked_placeholder() {
		$secret = $this->make_secret();

		ob_start();
		var_dump( $secret );
		$output = ob_get_clean();

		$this->assertStringContainsString( '[secret:' . self::NAME . ']', $output );
	}

	public function test_serialize_throws() {
		$secret = $this->make_secret();

		$this->expectException( LogicException::class );

		serialize( $secret );
	}

	public function test_clone_throws() {
		$secret = $this->make_secret();

		$this->expectException( LogicException::class );

		clone $secret;
	}

	/**
	 * Invoked directly via reflection, rather than through serialize()/unserialize(),
	 * because __serialize() alone shadows __sleep() and vice versa depending on which
	 * pair PHP's engine prefers -- this proves each of the four refuses independently.
	 *
	 * @dataProvider data_refusing_magic_methods
	 */
	public function test_serialization_magic_methods_throw_directly( $method, $args ) {
		$secret     = $this->make_secret();
		$reflection = new ReflectionMethod( $secret, $method );
		$reflection->setAccessible( true );

		$this->expectException( LogicException::class );

		$reflection->invokeArgs( $secret, $args );
	}

	public function data_refusing_magic_methods() {
		return array(
			'__sleep'       => array( '__sleep', array() ),
			'__wakeup'      => array( '__wakeup', array() ),
			'__serialize'   => array( '__serialize', array() ),
			'__unserialize' => array( '__unserialize', array( array() ) ),
		);
	}

	public function test_destructing_the_last_reference_removes_it_from_the_vault() {
		$secret = $this->make_secret();
		$id     = spl_object_id( $secret );

		$vault_property = new ReflectionProperty( WP_Secret::class, 'vault' );
		$vault_property->setAccessible( true );

		$this->assertArrayHasKey( $id, $vault_property->getValue() );

		unset( $secret );

		$this->assertArrayNotHasKey( $id, $vault_property->getValue() );
	}

	/**
	 * Nothing plaintext enters the object cache.
	 *
	 * The mechanism differs by backend and this asserts the outcome rather than the
	 * route: core's non-persistent cache clones on set, which __clone() refuses,
	 * while a persistent backend (Redis, Memcached) serializes instead, which
	 * __serialize() refuses. Both are LogicException, so both are covered here
	 * without the test having to know which cache is installed.
	 */
	public function test_wp_cache_set_of_a_secret_is_refused() {
		$secret = $this->make_secret();

		$this->expectException( LogicException::class );

		wp_cache_set( 'secret', $secret, 'secrets-test' );
	}

	/**
	 * The independent second layer, not a restatement of the test above.
	 *
	 * Verified by neutering __clone() and __serialize() and re-running: the test
	 * above fails, this one still passes. That is the point -- this holds because
	 * the plaintext is not a property of the object, so even a clone or a
	 * serialization that somehow got through would carry nothing to leak. It is a
	 * regression guard against a future change that moves the plaintext back into a
	 * declared property, which would defeat the masking design without breaking any
	 * of the magic-method tests.
	 */
	public function test_nothing_plaintext_survives_a_refused_cache_write() {
		$secret = $this->make_secret();

		try {
			wp_cache_set( 'secret', $secret, 'secrets-test' );
		} catch ( LogicException $e ) {
			unset( $e );
		}

		$cached = wp_cache_get( 'secret', 'secrets-test' );

		$this->assertNeverContainsPlaintext( self::PLAINTEXT, $cached );
	}

	public function test_two_instances_do_not_share_a_vault_slot() {
		$a = new WP_Secret( 'plugin/a', 'value-a', 'fp-a' );
		$b = new WP_Secret( 'plugin/b', 'value-b', 'fp-b' );

		$this->assertSame( 'value-a', $a->reveal() );
		$this->assertSame( 'value-b', $b->reveal() );

		unset( $a );

		$this->assertSame( 'value-b', $b->reveal() );
	}

	// -- withheld secrets (provider does not release the value) ---------------

	/**
	 * A secret a provider can name and fingerprint but will not hand to PHP: an
	 * HSM signing key, a brokered credential. Everything except reveal() behaves
	 * normally, which is the point -- it still lists, still fingerprints, and
	 * still masks itself everywhere.
	 */
	public function test_withheld_reveal_returns_the_providers_reason() {
		$reason = new WP_Error( 'provider_withholds_value', 'This key never leaves the HSM.' );
		$secret = WP_Secret::withheld( 'myplugin/signing-key', 'abc123', $reason );

		$revealed = $secret->reveal();

		$this->assertWPError( $revealed );
		$this->assertSame( 'provider_withholds_value', $revealed->get_error_code() );
	}

	public function test_withheld_still_reports_name_and_fingerprint() {
		$secret = WP_Secret::withheld(
			'myplugin/signing-key',
			'abc123',
			new WP_Error( 'provider_withholds_value', 'nope' )
		);

		$this->assertSame( 'myplugin/signing-key', $secret->get_name() );
		$this->assertSame( 'abc123', $secret->fingerprint() );
	}

	/**
	 * The masking guarantees are not weakened by the withheld path: there is no
	 * plaintext to leak, and the error reason must not leak either.
	 */
	public function test_withheld_masks_like_any_other_secret() {
		$secret = WP_Secret::withheld(
			'myplugin/signing-key',
			'abc123',
			new WP_Error( 'provider_withholds_value', 'UNIQUE-REASON-CANARY-4b1c' )
		);

		$printed = print_r( $secret, true );

		$this->assertStringNotContainsString( 'UNIQUE-REASON-CANARY-4b1c', $printed );
		$this->assertStringContainsString( '[secret:myplugin/signing-key]', (string) $secret );
	}

	public function test_withheld_requires_a_wp_error_reason() {
		$this->expectException( InvalidArgumentException::class );

		WP_Secret::withheld( 'myplugin/signing-key', 'abc123', 'not an error' );
	}

	/**
	 * An ordinary secret is unaffected: reveal() still returns the plaintext, and
	 * the widened return type is not a behaviour change for the shipped provider.
	 */
	public function test_an_ordinary_secret_still_reveals_a_plain_string() {
		$secret = new WP_Secret( 'myplugin/api-key', 'sk_live_value', 'fingerprint' );

		$this->assertSame( 'sk_live_value', $secret->reveal() );
	}
}
