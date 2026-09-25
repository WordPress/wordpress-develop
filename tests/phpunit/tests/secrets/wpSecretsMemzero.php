<?php
/**
 * @group secrets
 */
class Tests_Secrets_WpSecretsMemzero extends WP_UnitTestCase {

	public function test_clears_a_plaintext_string() {
		$value = 'a plaintext value';

		wp_secrets_memzero( $value );

		$this->assertNotSame( 'a plaintext value', $value );
		$this->assertSame( '', $value );
	}

	public function test_leaves_non_string_values_untouched() {
		$value = 42;

		wp_secrets_memzero( $value );

		$this->assertSame( 42, $value );
	}

	public function test_leaves_an_empty_string_untouched() {
		$value = '';

		wp_secrets_memzero( $value );

		$this->assertSame( '', $value );
	}

	public function test_does_not_throw_when_the_value_is_shared() {
		$value = 'shared plaintext';
		$copy  = $value;

		wp_secrets_memzero( $value );

		$this->assertSame( '', $value );
		// The shared copy is unaffected -- see the docblock on wp_secrets_memzero():
		// this is hygiene, not a guarantee, for exactly this reason.
		$this->assertSame( 'shared plaintext', $copy );
	}
}
