<?php
/**
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WpSecretsMemzero extends WP_UnitTestCase {

	/**
	 * Ensure that wp_secrets_memzero() replaces a string value with an empty string.
	 *
	 * @covers ::wp_secrets_memzero
	 */
	public function test_clears_a_plaintext_string(): void {
		$value = 'a plaintext value';

		wp_secrets_memzero( $value );

		$this->assertSame( '', $value, 'The variable should be an empty string after zeroing.' );
	}

	/**
	 * Ensure that wp_secrets_memzero() does not modify a non-string value.
	 *
	 * @covers ::wp_secrets_memzero
	 */
	public function test_leaves_non_string_values_untouched(): void {
		$value = 42;

		wp_secrets_memzero( $value ); // @phpstan-ignore argument.type (Intentionally passing a non-string value.)

		$this->assertSame( 42, $value, 'A non-string value should be left unchanged.' );
	}

	/**
	 * Ensure that wp_secrets_memzero() leaves an empty string as an empty string.
	 *
	 * @covers ::wp_secrets_memzero
	 */
	public function test_leaves_an_empty_string_untouched(): void {
		$value = '';

		wp_secrets_memzero( $value );

		$this->assertSame( '', $value, 'An empty string should remain an empty string.' );
	}

	/**
	 * Ensure that wp_secrets_memzero() clears the variable passed and leaves a shared copy untouched.
	 *
	 * @covers ::wp_secrets_memzero
	 */
	public function test_clears_the_variable_and_leaves_a_shared_copy_untouched(): void {
		$value = 'shared plaintext';
		$copy  = $value;

		wp_secrets_memzero( $value );

		$this->assertSame( '', $value, 'The zeroed variable should be an empty string.' );
		/*
		 * The shared copy is unaffected – see the docblock on wp_secrets_memzero():
		 * this is hygiene, not a guarantee, for exactly this reason.
		 */
		$this->assertSame( 'shared plaintext', $copy, 'The shared copy should keep its original value.' ); // @phpstan-ignore method.alreadyNarrowedType (Whether the copy survives the scrub is decided by the engine at runtime, not by the types.)
	}
}
