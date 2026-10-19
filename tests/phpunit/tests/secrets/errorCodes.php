<?php
/**
 * Pins the exact error-code strings so a later commit can't accidentally rename
 * one – these are part of the API's public error contract.
 *
 * @group secrets
 */
class Tests_Secrets_ErrorCodes extends WP_UnitTestCase {

	/**
	 * @dataProvider data_published_error_codes
	 *
	 * @param string $constant Name of the error-code constant.
	 * @param string $expected Value the published contract assigns to it.
	 */
	public function test_error_code_values_match_the_published_contract( $constant, $expected ): void {
		$this->assertSame( $expected, constant( $constant ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_published_error_codes() {
		return array(
			'decryption failed'  => array( 'WP_SECRETS_ERROR_DECRYPTION_FAILED', 'secret_decryption_failed' ),
			'key unavailable'    => array( 'WP_SECRETS_ERROR_KEY_UNAVAILABLE', 'secret_key_unavailable' ),
			'store unavailable'  => array( 'WP_SECRETS_ERROR_STORE_UNAVAILABLE', 'secret_store_unavailable' ),
			'invalid name'       => array( 'WP_SECRETS_ERROR_INVALID_NAME', 'secret_invalid_name' ),
			'invalid value'      => array( 'WP_SECRETS_ERROR_INVALID_VALUE', 'secret_invalid_value' ),
			'crypto unavailable' => array( 'WP_SECRETS_ERROR_CRYPTO_UNAVAILABLE', 'secret_crypto_unavailable' ),
			'record malformed'   => array( 'WP_SECRETS_ERROR_RECORD_MALFORMED', 'secret_record_malformed' ),
		);
	}

	public function test_all_error_codes_are_distinct(): void {
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

	public function test_max_name_length_matches_the_options_column_budget(): void {
		// wp_options.option_name is VARCHAR(191); '_wp_network_secret_' (19
		// characters) is the longest prefix any store here uses.
		$this->assertSame( 191 - strlen( '_wp_network_secret_' ), WP_SECRETS_MAX_NAME_LENGTH );
	}
}
