<?php
/**
 * Webfonts API: Webfonts Registry
 *
 * @package WordPress
 * @subpackage Webfonts
 * @since 5.9.0
 */

/**
 * Webfonts Registry.
 *
 * Handles webfont registration and query of webfonts.
 */
class WP_Webfonts_Registry {

	/**
	 * Registered webfonts.
	 *
	 * @since 5.9.0
	 *
	 * @var string[][]
	 */
	private $registry = array();

	/**
	 * Registration keys per provider.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	private $registry_by_provider = array();

	/**
	 * Schema validator.
	 *
	 * @since 5.9.0
	 *
	 * @var WP_Webfonts_Schema_Validator
	 */
	private $validator;

	public function __construct( WP_Webfonts_Schema_Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Gets the webfont registry.
	 *
	 * @since 5.9.0
	 *
	 * @return string[][] Registered webfonts.
	 */
	public function get_registry() {
		return $this->registry;
	}

	/**
	 * Gets the registered webfonts for the given provider.
	 *
	 * @since 5.9.0
	 *
	 * @param string $provider_id Provider ID to fetch.
	 * @return string[][] Registered webfonts.
	 */
	public function get_by_provider( $provider_id ) {
		if ( ! isset( $this->registry_by_provider[ $provider_id ] ) ) {
			return array();
		}

		$webfonts = array();
		foreach ( $this->registry_by_provider[ $provider_id ] as $registration_key ) {
			// Safeguard. Skip if not in registry.
			if ( ! isset( $this->registry[ $registration_key ] ) ) {
				continue;
			}

			$webfonts[ $registration_key ] = $this->registry[ $registration_key ];
		}

		return $webfonts;
	}

	/**
	 * Gets the registered webfonts for the given font-family.
	 *
	 * @since 5.9.0
	 *
	 * @param string $font_family Family font to fetch.
	 * @return array[] Registered webfonts.
	 */
	public function get_by_font_family( $font_family ) {
		if ( ! is_string( $font_family ) || '' === $font_family ) {
			return array();
		}

		$webfonts        = array();
		$font_family_key = $this->convert_font_family_into_key( $font_family ) . '.';
		$last_char       = strlen( $font_family_key );

		foreach ( $this->registry as $registration_key => $webfont ) {
			// Skip if webfont's family font does not match.
			if ( substr( $registration_key, 0, $last_char ) !== $font_family_key ) {
				continue;
			}

			$webfonts[ $registration_key ] = $webfont;
		}

		return $webfonts;
	}

	/**
	 * Registers the given webfont if its schema is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont definition.
	 * @return string Registration key.
	 */
	public function register( array $webfont ) {
		$webfont = $this->convert_to_kabeb_case( $webfont );

		// Validate schema.
		if ( ! $this->validator->is_valid_schema( $webfont ) ) {
			return '';
		}

		$webfont = $this->validator->set_valid_properties( $webfont );

		// Add to registry.
		$registration_key = $this->generate_registration_key( $webfont );
		if ( ! isset( $this->registry[ $registration_key ] ) ) {
			$this->registry[ $registration_key ]                  = $webfont;
			$this->registry_by_provider[ $webfont['provider'] ][] = $registration_key;
		}

		return $registration_key;
	}

	/**
	 * Convert camelCase parameters into kabeb_case.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont definition.
	 * @return array Webfont with kabeb_case parameters (keys).
	 */
	private function convert_to_kabeb_case( array $webfont ) {
		$kebab_case = preg_replace( '/(?<!^)[A-Z]/', '-$0', array_keys( $webfont ) );
		$kebab_case = array_map( 'strtolower', $kebab_case );

		return array_combine( $kebab_case, array_values( $webfont ) );
	}

	/**
	 * Generates the registration key.
	 *
	 * Format: font-family.font-style.font-weight
	 * For example: `'open-sans.normal.400'`.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont definition.
	 * @return string Registration key.
	 */
	private function generate_registration_key( array $webfont ) {
		return sprintf(
			'%s.%s.%s',
			$this->convert_font_family_into_key( $webfont['font-family'] ),
			trim( $webfont['font-style'] ),
			trim( $webfont['font-weight'] )
		);
	}

	/**
	 * Converts the given font family into a key.
	 *
	 * For example: 'Open Sans' becomes 'open-sans'.
	 *
	 * @since 5.9.0
	 *
	 * @param string $font_family Font family to convert into a key.
	 * @return string
	 */
	private function convert_font_family_into_key( $font_family ) {
		if ( ! is_string( $font_family ) || '' === $font_family ) {
			return '';
		}

		return sanitize_title( $font_family );
	}

	/**
	 * Checks if the given webfont schema is validate.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont definition.
	 * @return bool True when valid. False when invalid.
	 */
	private function is_schema_valid( array $webfont ) {
		return $this->validator->is_schema_valid( $webfont );
	}
}
