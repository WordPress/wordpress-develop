<?php
/**
 * Pins the exact error-code strings so a later commit can't accidentally rename
 * one – these are part of the API's public error contract.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_ErrorCodes extends WP_UnitTestCase {

	/**
	 * Ensure that each error code constant holds its published string value.
	 *
	 * @coversNothing
	 *
	 * @dataProvider data_published_error_codes
	 *
	 * @param string $constant Name of the error-code constant.
	 * @param string $expected Value the published contract assigns to it.
	 */
	public function test_error_code_values_match_the_published_contract( $constant, $expected ): void {
		$this->assertSame( $expected, constant( $constant ), sprintf( '%s should hold its published error code string.', $constant ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_published_error_codes() {
		return array(
			'decryption failed'          => array( 'WP_SECRETS_ERROR_DECRYPTION_FAILED', 'secret_decryption_failed' ),
			'key unavailable'            => array( 'WP_SECRETS_ERROR_KEY_UNAVAILABLE', 'secret_key_unavailable' ),
			'store unavailable'          => array( 'WP_SECRETS_ERROR_STORE_UNAVAILABLE', 'secret_store_unavailable' ),
			'invalid name'               => array( 'WP_SECRETS_ERROR_INVALID_NAME', 'secret_invalid_name' ),
			'invalid value'              => array( 'WP_SECRETS_ERROR_INVALID_VALUE', 'secret_invalid_value' ),
			'invalid argument'           => array( 'WP_SECRETS_ERROR_INVALID_ARGUMENT', 'secret_invalid_argument' ),
			'crypto unavailable'         => array( 'WP_SECRETS_ERROR_CRYPTO_UNAVAILABLE', 'secret_crypto_unavailable' ),
			'record malformed'           => array( 'WP_SECRETS_ERROR_RECORD_MALFORMED', 'secret_record_malformed' ),
			'record unsupported version' => array( 'WP_SECRETS_ERROR_RECORD_UNSUPPORTED_VERSION', 'secret_record_unsupported_version' ),
			'provider read only'         => array( 'WP_SECRETS_ERROR_PROVIDER_READ_ONLY', 'secret_provider_read_only' ),
		);
	}

	/**
	 * Ensure that no two error code constants share a value.
	 *
	 * @coversNothing
	 */
	public function test_all_error_codes_are_distinct(): void {
		$codes = array_map( 'constant', array_column( self::data_published_error_codes(), 0 ) );

		$this->assertSame( count( $codes ), count( array_unique( $codes ) ), 'Every error code constant should have a distinct value.' );
	}

	/**
	 * Ensure that every WP_SECRETS_ERROR_* constant is pinned by the published contract.
	 *
	 * Fails when a new error code is added without being added to the data provider
	 * above, so the contract cannot silently fall behind the codes the API returns.
	 *
	 * @coversNothing
	 */
	public function test_every_error_code_constant_is_pinned(): void {
		$defined = array_filter(
			array_keys( get_defined_constants() ),
			static function ( $name ) {
				return str_starts_with( $name, 'WP_SECRETS_ERROR_' );
			}
		);

		$this->assertSameSets( array_column( self::data_published_error_codes(), 0 ), $defined, 'Every WP_SECRETS_ERROR_* constant should be listed in the published contract.' );
	}

	/**
	 * Ensure that WP_SECRETS_MAX_NAME_LENGTH fits the longest prefixed name in option_name.
	 *
	 * @coversNothing
	 */
	public function test_max_name_length_matches_the_options_column_budget(): void {
		/*
		 * wp_options.option_name is VARCHAR(191); '_wp_network_secret_' (19
		 * characters) is the longest prefix any store here uses.
		 */
		$this->assertSame( 191 - strlen( '_wp_network_secret_' ), WP_SECRETS_MAX_NAME_LENGTH, 'WP_SECRETS_MAX_NAME_LENGTH should fit the longest prefixed name in option_name.' );
	}
}
