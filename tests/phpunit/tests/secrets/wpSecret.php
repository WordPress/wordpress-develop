<?php
/**
 * Tests for WP_Secret.
 *
 * @package WordPress
 * @subpackage Secrets
 */

/**
 * Masking, serialization refusal, and vault lifecycle for WP_Secret.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WPSecret extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	const PLAINTEXT   = 'sk_live_super_secret_value';
	const NAME        = 'myplugin/api-key';
	const FINGERPRINT = 'abc123def456';

	/**
	 * @return WP_Secret
	 */
	private function make_secret() {
		return new WP_Secret( self::NAME, self::PLAINTEXT, self::FINGERPRINT );
	}

	/**
	 * Ensure that WP_Secret cannot be extended.
	 *
	 * A subclass could declare a property or magic method that exposes the plaintext,
	 * bypassing the masking guarantees this class makes.
	 *
	 * @coversNothing
	 */
	public function test_class_is_final(): void {
		$reflection = new ReflectionClass( WP_Secret::class );

		$this->assertTrue( $reflection->isFinal(), 'WP_Secret should be declared final.' );
	}

	/**
	 * Ensure that WP_Secret implements JsonSerializable so json_encode() goes through the mask.
	 *
	 * @coversNothing
	 */
	public function test_implements_json_serializable(): void {
		$this->assertInstanceOf( JsonSerializable::class, $this->make_secret(), 'WP_Secret should implement JsonSerializable.' );
	}

	/**
	 * Ensure that WP_Secret::reveal() returns the plaintext the secret was constructed with.
	 *
	 * @covers WP_Secret::reveal
	 */
	public function test_reveal_returns_the_exact_plaintext(): void {
		$secret = $this->make_secret();

		$this->assertSame( self::PLAINTEXT, $secret->reveal(), 'reveal() should return the plaintext the secret was constructed with.' );
	}

	/**
	 * Ensure that WP_Secret::fingerprint() returns the fingerprint the secret was constructed with.
	 *
	 * @covers WP_Secret::fingerprint
	 */
	public function test_fingerprint_returns_the_exact_fingerprint(): void {
		$secret = $this->make_secret();

		$this->assertSame( self::FINGERPRINT, $secret->fingerprint(), 'fingerprint() should return the fingerprint the secret was constructed with.' );
	}

	/**
	 * Ensure that WP_Secret::get_name() returns the name the secret was constructed with.
	 *
	 * @covers WP_Secret::get_name
	 */
	public function test_get_name_returns_the_exact_name(): void {
		$secret = $this->make_secret();

		$this->assertSame( self::NAME, $secret->get_name(), 'get_name() should return the name the secret was constructed with.' );
	}

	/**
	 * Ensure that constructing a WP_Secret with a non-string value throws InvalidArgumentException.
	 *
	 * @covers WP_Secret::__construct
	 */
	public function test_constructor_rejects_a_non_string_value(): void {
		$this->expectException( InvalidArgumentException::class );

		new WP_Secret( self::NAME, 12345, self::FINGERPRINT ); // @phpstan-ignore argument.type (Intentionally passing a non-string value.)
	}

	/**
	 * Ensure that constructing a WP_Secret with an empty name throws InvalidArgumentException.
	 *
	 * @covers WP_Secret::__construct
	 */
	public function test_constructor_rejects_an_empty_name(): void {
		$this->expectException( InvalidArgumentException::class );

		new WP_Secret( '', self::PLAINTEXT, self::FINGERPRINT );
	}

	/**
	 * Ensure that constructing a WP_Secret with an empty fingerprint throws InvalidArgumentException.
	 *
	 * @covers WP_Secret::__construct
	 */
	public function test_constructor_rejects_an_empty_fingerprint(): void {
		$this->expectException( InvalidArgumentException::class );

		new WP_Secret( self::NAME, self::PLAINTEXT, '' );
	}

	/**
	 * Every representation short of reveal() must exclude the plaintext.
	 *
	 * @covers WP_Secret::__toString
	 * @covers WP_Secret::jsonSerialize
	 * @covers WP_Secret::__debugInfo
	 *
	 * @dataProvider data_masking_surfaces_never_contain_the_plaintext
	 *
	 * @param callable( WP_Secret ): ( string|false ) $callback Renders the secret through one surface.
	 */
	public function test_masking_surfaces_never_contain_the_plaintext( $callback ): void {
		$secret = $this->make_secret();

		$output = $callback( $secret );

		$this->assertIsString( $output, 'The masking surface should render the secret as a string.' );
		$this->assertStringNotContainsString( self::PLAINTEXT, $output, 'The masking surface output should not contain the plaintext.' );
	}

	/**
	 * @return array<string, array{0: callable( WP_Secret ): ( string|false )}>
	 */
	public static function data_masking_surfaces_never_contain_the_plaintext() {
		return array(
			'(string) cast'        => array(
				function ( WP_Secret $secret ) {
					return (string) $secret;
				},
			),
			'string interpolation' => array(
				function ( WP_Secret $secret ) {
					return "{$secret}";
				},
			),
			'json_encode'          => array(
				function ( WP_Secret $secret ) {
					return json_encode( $secret );
				},
			),
			'var_dump'             => array(
				function ( WP_Secret $secret ) {
					ob_start();
					var_dump( $secret );

					return ob_get_clean();
				},
			),
			'print_r'              => array(
				function ( WP_Secret $secret ) {
					return print_r( $secret, true );
				},
			),
			'var_export'           => array(
				function ( WP_Secret $secret ) {
					return var_export( $secret, true );
				},
			),
			'error_log'            => array(
				function ( WP_Secret $secret ) {
					$file = tempnam( sys_get_temp_dir(), 'wp-secret-test-' );
					error_log( $secret, 3, $file );
					$contents = file_get_contents( $file );
					unlink( $file );

					return $contents;
				},
			),
		);
	}

	/**
	 * Ensure that casting a WP_Secret to string yields the [secret:{name}] placeholder.
	 *
	 * @covers WP_Secret::__toString
	 */
	public function test_to_string_yields_the_masked_placeholder(): void {
		$secret = $this->make_secret();

		$this->assertSame( '[secret:' . self::NAME . ']', (string) $secret, 'Casting to string should yield the masked placeholder.' );
	}

	/**
	 * Ensure that json_encode() renders a WP_Secret as the [secret:{name}] placeholder.
	 *
	 * @covers WP_Secret::jsonSerialize
	 */
	public function test_json_encode_yields_the_masked_placeholder(): void {
		$secret = $this->make_secret();

		// json_encode() escapes '/' by default; decode rather than compare raw JSON.
		$json = json_encode( $secret );

		$this->assertIsString( $json, 'json_encode() should return a JSON string.' );
		$this->assertSame( '[secret:' . self::NAME . ']', json_decode( $json ), 'The decoded JSON should be the masked placeholder.' );
	}

	/**
	 * Ensure that var_dump() of a WP_Secret shows the [secret:{name}] placeholder.
	 *
	 * @covers WP_Secret::__debugInfo
	 */
	public function test_var_dump_yields_the_masked_placeholder(): void {
		$secret = $this->make_secret();

		ob_start();
		var_dump( $secret );
		$output = ob_get_clean();

		$this->assertStringContainsString( '[secret:' . self::NAME . ']', $output, 'var_dump() output should contain the masked placeholder.' );
	}

	/**
	 * Ensure that serializing a WP_Secret throws LogicException.
	 *
	 * @covers WP_Secret::__serialize
	 */
	public function test_serialize_throws(): void {
		$secret = $this->make_secret();

		$this->expectException( LogicException::class );

		serialize( $secret );
	}

	/**
	 * Ensure that cloning a WP_Secret throws LogicException.
	 *
	 * @covers WP_Secret::__clone
	 */
	public function test_clone_throws(): void {
		$secret = $this->make_secret();

		$this->expectException( LogicException::class );

		$clone = clone $secret;
	}

	/**
	 * Invoked directly via reflection, rather than through serialize()/unserialize(),
	 * because __serialize() alone shadows __sleep() and vice versa depending on which
	 * pair PHP's engine prefers – this proves each of the four refuses independently.
	 *
	 * @covers WP_Secret::__sleep
	 * @covers WP_Secret::__wakeup
	 * @covers WP_Secret::__serialize
	 * @covers WP_Secret::__unserialize
	 *
	 * @dataProvider data_serialization_magic_methods_throw_directly
	 *
	 * @param string            $method Magic method name.
	 * @param array<int, mixed> $args   Arguments to invoke it with.
	 */
	public function test_serialization_magic_methods_throw_directly( $method, $args ): void {
		$secret     = $this->make_secret();
		$reflection = new ReflectionMethod( $secret, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$this->expectException( LogicException::class );

		$reflection->invokeArgs( $secret, $args );
	}

	/**
	 * @return array<string, array{0: string, 1: array<int, mixed>}>
	 */
	public static function data_serialization_magic_methods_throw_directly() {
		return array(
			'__sleep'       => array( '__sleep', array() ),
			'__wakeup'      => array( '__wakeup', array() ),
			'__serialize'   => array( '__serialize', array() ),
			'__unserialize' => array( '__unserialize', array( array() ) ),
		);
	}

	/**
	 * Ensure that a secret's plaintext is removed from the vault once its last reference is released.
	 *
	 * @covers WP_Secret::__construct
	 * @covers WP_Secret::__destruct
	 */
	public function test_destructing_the_last_reference_removes_it_from_the_vault(): void {
		$secret = $this->make_secret();
		$id     = spl_object_id( $secret );

		$vault_property = new ReflectionProperty( WP_Secret::class, 'vault' );
		if ( PHP_VERSION_ID < 80100 ) {
			$vault_property->setAccessible( true );
		}

		$vault = $vault_property->getValue();

		$this->assertIsArray( $vault, 'The vault should be an array while the secret is referenced.' );
		$this->assertArrayHasKey( $id, $vault, 'The vault should hold the secret while it is referenced.' );

		unset( $secret );

		$vault = $vault_property->getValue();

		$this->assertIsArray( $vault, 'The vault should be an array after the secret is destroyed.' );
		$this->assertArrayNotHasKey( $id, $vault, 'The vault should not hold the secret after its last reference is released.' );
	}

	/**
	 * Nothing plaintext enters the object cache.
	 *
	 * The mechanism differs by backend and this asserts the outcome rather than the
	 * route: core's non-persistent cache clones on set, which __clone() refuses,
	 * while a persistent backend (Redis, Memcached) serializes instead, which
	 * __serialize() refuses. Both are LogicException, so both are covered here
	 * without the test having to know which cache is installed.
	 *
	 * @covers WP_Secret::__clone
	 * @covers WP_Secret::__serialize
	 */
	public function test_wp_cache_set_of_a_secret_is_refused(): void {
		$secret = $this->make_secret();

		$this->expectException( LogicException::class );

		wp_cache_set( 'secret', $secret, 'secrets-test' );
	}

	/**
	 * The independent second layer, not a restatement of the test above.
	 *
	 * Verified by neutering __clone() and __serialize() and re-running: the test
	 * above fails, this one still passes. That is the point – this holds because
	 * the plaintext is not a property of the object, so even a clone or a
	 * serialization that somehow got through would carry nothing to leak. It is a
	 * regression guard against a future change that moves the plaintext back into a
	 * declared property, which would defeat the masking design without breaking any
	 * of the magic-method tests.
	 *
	 * @covers WP_Secret::__construct
	 */
	public function test_nothing_plaintext_survives_a_refused_cache_write(): void {
		$secret = $this->make_secret();

		try {
			wp_cache_set( 'secret', $secret, 'secrets-test' );
		} catch ( LogicException $e ) {
			unset( $e );
		}

		$cached = wp_cache_get( 'secret', 'secrets-test' );

		$this->assertNeverContainsPlaintext( self::PLAINTEXT, $cached, 'The cache should never hold the plaintext after a refused write.' );
	}

	/**
	 * Ensure that each WP_Secret holds its own plaintext and destroying one leaves the other intact.
	 *
	 * @covers WP_Secret::__construct
	 * @covers WP_Secret::reveal
	 * @covers WP_Secret::__destruct
	 */
	public function test_two_instances_do_not_share_a_vault_slot(): void {
		$a = new WP_Secret( 'plugin/a', 'value-a', 'fp-a' );
		$b = new WP_Secret( 'plugin/b', 'value-b', 'fp-b' );

		$this->assertSame( 'value-a', $a->reveal(), 'The first secret should reveal its own plaintext.' );
		$this->assertSame( 'value-b', $b->reveal(), 'The second secret should reveal its own plaintext.' );

		unset( $a );

		$this->assertSame( 'value-b', $b->reveal(), 'The second secret should still reveal its plaintext after the first is destroyed.' );
	}

	// – withheld secrets (provider does not release the value) ---------------

	/**
	 * A secret a provider can name and fingerprint but will not hand to PHP: an
	 * HSM signing key, a brokered credential. Everything except reveal() behaves
	 * normally, which is the point – it still lists, still fingerprints, and
	 * still masks itself everywhere.
	 *
	 * @covers WP_Secret::withheld
	 * @covers WP_Secret::reveal
	 */
	public function test_withheld_reveal_returns_the_providers_reason(): void {
		$reason = new WP_Error( 'provider_withholds_value', 'This key never leaves the HSM.' );
		$secret = WP_Secret::withheld( 'myplugin/signing-key', 'abc123', $reason );

		$revealed = $secret->reveal();

		$this->assertWPError( $revealed, 'reveal() on a withheld secret should return a WP_Error.' );
		$this->assertSame( 'provider_withholds_value', $revealed->get_error_code(), 'reveal() should return the error code the provider gave.' );
	}

	/**
	 * Ensure that a withheld secret still reports its name and fingerprint.
	 *
	 * @covers WP_Secret::withheld
	 * @covers WP_Secret::get_name
	 * @covers WP_Secret::fingerprint
	 */
	public function test_withheld_still_reports_name_and_fingerprint(): void {
		$secret = WP_Secret::withheld(
			'myplugin/signing-key',
			'abc123',
			new WP_Error( 'provider_withholds_value', 'nope' )
		);

		$this->assertSame( 'myplugin/signing-key', $secret->get_name(), 'A withheld secret should still report its name.' );
		$this->assertSame( 'abc123', $secret->fingerprint(), 'A withheld secret should still report its fingerprint.' );
	}

	/**
	 * The masking guarantees are not weakened by the withheld path: there is no
	 * plaintext to leak, and the error reason must not leak either.
	 *
	 * @covers WP_Secret::withheld
	 * @covers WP_Secret::__debugInfo
	 * @covers WP_Secret::__toString
	 */
	public function test_withheld_masks_like_any_other_secret(): void {
		$secret = WP_Secret::withheld(
			'myplugin/signing-key',
			'abc123',
			new WP_Error( 'provider_withholds_value', 'UNIQUE-REASON-CANARY-4b1c' )
		);

		$printed = print_r( $secret, true );

		$this->assertStringNotContainsString( 'UNIQUE-REASON-CANARY-4b1c', $printed, 'print_r() output should not contain the withheld reason.' );
		$this->assertStringContainsString( '[secret:myplugin/signing-key]', (string) $secret, 'Casting a withheld secret to string should yield the masked placeholder.' );
	}
}
