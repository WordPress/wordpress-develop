<?php
/**
 * @group secrets
 */
class Tests_Secrets_WpSecretsMemzero extends WP_UnitTestCase {

	public function test_clears_a_plaintext_string(): void {
		$value = 'a plaintext value';

		wp_secrets_memzero( $value );

		$this->assertNotSame( 'a plaintext value', $value );
		$this->assertSame( '', $value );
	}

	public function test_leaves_non_string_values_untouched(): void {
		$value = 42;

		wp_secrets_memzero( $value ); // @phpstan-ignore argument.type (Intentionally passing a non-string value.)

		$this->assertSame( 42, $value ); // @phpstan-ignore method.impossibleType (The by-reference parameter is documented as a string; this checks a non-string comes back untouched.)
	}

	public function test_leaves_an_empty_string_untouched(): void {
		$value = '';

		wp_secrets_memzero( $value );

		$this->assertSame( '', $value );
	}

	public function test_does_not_throw_when_the_value_is_shared(): void {
		$value = 'shared plaintext';
		$copy  = $value;

		wp_secrets_memzero( $value );

		$this->assertSame( '', $value );
		// The shared copy is unaffected – see the docblock on wp_secrets_memzero():
		// this is hygiene, not a guarantee, for exactly this reason.
		$this->assertSame( 'shared plaintext', $copy ); // @phpstan-ignore method.alreadyNarrowedType (Whether the copy survives the scrub is decided by the engine at runtime, not by the types.)
	}
}
