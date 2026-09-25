<?php
/**
 * Pins the exact error-code strings so a later commit can't accidentally rename
 * one -- these are part of the API's public error contract.
 *
 * @group secrets
 */
class Tests_Secrets_ErrorCodes extends WP_UnitTestCase {

	public function test_error_code_values_match_the_published_contract() {
		$this->assertSame( 'secret_decryption_failed', WP_SECRETS_ERROR_DECRYPTION_FAILED );
		$this->assertSame( 'secret_key_unavailable', WP_SECRETS_ERROR_KEY_UNAVAILABLE );
		$this->assertSame( 'secret_store_unavailable', WP_SECRETS_ERROR_STORE_UNAVAILABLE );
		$this->assertSame( 'secret_invalid_name', WP_SECRETS_ERROR_INVALID_NAME );
		$this->assertSame( 'secret_invalid_value', WP_SECRETS_ERROR_INVALID_VALUE );
		$this->assertSame( 'secret_crypto_unavailable', WP_SECRETS_ERROR_CRYPTO_UNAVAILABLE );
		$this->assertSame( 'secret_record_malformed', WP_SECRETS_ERROR_RECORD_MALFORMED );
	}

	public function test_all_error_codes_are_distinct() {
		$codes = array(
			WP_SECRETS_ERROR_DECRYPTION_FAILED,
			WP_SECRETS_ERROR_KEY_UNAVAILABLE,
			WP_SECRETS_ERROR_STORE_UNAVAILABLE,
			WP_SECRETS_ERROR_INVALID_NAME,
			WP_SECRETS_ERROR_INVALID_VALUE,
			WP_SECRETS_ERROR_CRYPTO_UNAVAILABLE,
			WP_SECRETS_ERROR_RECORD_MALFORMED,
		);

		$this->assertSame( count( $codes ), count( array_unique( $codes ) ) );
	}

	public function test_max_name_length_matches_the_options_column_budget() {
		// wp_options.option_name is VARCHAR(191); '_wp_network_secret_' (19
		// characters) is the longest prefix any store here uses.
		$this->assertSame( 191 - strlen( '_wp_network_secret_' ), WP_SECRETS_MAX_NAME_LENGTH );
	}
}
