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
 * Handles schema validation, webfont registration, and query of webfonts.
 */
final class WP_Webfonts_Registry {

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
	 * @var string[]
	 */
	private $registry_by_provider = array();

	/**
	 * Valid font styles.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	private $valid_font_style = array(
		'normal',
		'italic',
		'oblique',
		// Global values.
		'inherit',
		'initial',
		'revert',
		'unset',
	);

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
	 * @return string[][] Registered webfonts.
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
	 * @param string[] $webfont Webfont definition.
	 * @return string Registration key.
	 */
	public function register( array $webfont ) {
		// Validate schema.
		if ( ! $this->is_schema_valid( $webfont ) ) {
			return '';
		}

		// Add to registry.
		$registration_key = $this->generate_registration_key( $webfont );
		if ( ! isset( $this->registry[ $registration_key ] ) ) {
			$this->registry[ $registration_key ]                  = $webfont;
			$this->registry_by_provider[ $webfont['provider'] ][] = $registration_key;
		}

		return $registration_key;
	}

	/**
	 * Generates the registration key.
	 *
	 * Format: fontFamily.fontStyle.fontWeight
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
			$this->convert_font_family_into_key( $webfont['fontFamily'] ),
			trim( $webfont['fontStyle'] ),
			trim( $webfont['fontWeight'] )
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
		if ( empty( $webfont['fontFamily'] ) || ! is_string( $webfont['fontFamily'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				__( 'Webfont must define a string "fontFamily".' ),
				'5.9.0'
			);

			return false;
		}

		if ( empty( $webfont['fontStyle'] ) || ! is_string( $webfont['fontStyle'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				__( 'Webfont must define a string "fontStyle".' ),
				'5.9.0'
			);

			return false;
		}

		if ( ! $this->is_valid_font_style( $webfont['fontStyle'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				sprintf(
				/* translators: 1: Slant angle, 2: Given font style. */
					__( 'Webfont font style must be normal, italic, oblique, or oblique %1$s. Given: %2$s.' ),
					'<angle>',
					$webfont['fontStyle']
				),
				'5.9.0'
			);

			return false;
		}

		// @todo validate the value.
		if ( empty( $webfont['fontWeight'] ) || ! is_string( $webfont['fontWeight'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				__( 'Webfont must define a string "fontWeight".' ),
				'5.9.0'
			);

			return false;
		}

		// @todo check if provider is registered.
		if ( empty( $webfont['provider'] ) || ! is_string( $webfont['provider'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				__( 'Webfont must define a string "provider".' ),
				'5.9.0'
			);

			return false;
		}

		return true;
	}

	/**
	 * Checks if the given font-style is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param string $font_style Font style to validate.
	 * @return bool True when font-style is valid.
	 */
	private function is_valid_font_style( $font_style ) {
		if ( in_array( $font_style, $this->valid_font_style, true ) ) {
			return true;
		}

		// @todo Check for oblique <angle>.

		return false;
	}
}
