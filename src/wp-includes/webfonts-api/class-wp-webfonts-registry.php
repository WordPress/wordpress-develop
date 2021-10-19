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
 * This registry exists to handle all webfonts.
 *
 * It handles the following within the API:
 *  - loads the bundled provider files into memory;
 *  - registers each provider with the API by:
 *       1. creating an instance (object);
 *       2. storing it in-memory (by its unique provider ID) for use with the API;
 *  - handles generating the linked resources `<link>` for all providers.
 */
class WP_Webfonts_Registry {

	/**
	 * An in-memory storage container that holds all registered webfonts
	 * for use within the API.
	 *
	 * Keyed by font-family.font-style.font-weight:
	 *
	 *      @type string $key => @type array Webfont.
	 *
	 * @since 5.9.0
	 *
	 * @var array[]
	 */
	private $registered = array();

	/**
	 * Registration keys per provider.
	 *
	 * Provides a O(1) lookup when querying by provider.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	private $registry_by_provider = array();

	/**
	 * Registration keys per font-family.
	 *
	 * Provides a O(1) lookup when querying by provider.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	private $registry_by_font_family = array();

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
	 * Gets all registered webfonts.
	 *
	 * @since 5.9.0
	 *
	 * @return array[] Registered webfonts each keyed by font-family.font-style.font-weight.
	 */
	public function get_all_registered() {
		return $this->registered;
	}

	/**
	 * Gets the registered webfonts for the given provider.
	 *
	 * @since 5.9.0
	 *
	 * @param string $provider_id Provider ID to fetch.
	 * @return array[] Registered webfonts.
	 */
	public function get_by_provider( $provider_id ) {
		if ( ! isset( $this->registry_by_provider[ $provider_id ] ) ) {
			return array();
		}

		$webfonts = array();
		foreach ( $this->registry_by_provider[ $provider_id ] as $registration_key ) {
			// Skip if not registered.
			if ( ! isset( $this->registered[ $registration_key ] ) ) {
				continue;
			}

			$webfonts[ $registration_key ] = $this->registered[ $registration_key ];
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

		$font_family_key = $this->convert_font_family_into_key( $font_family );

		// If the font family is not registered, bail out.
		if ( ! isset( $this->registry_by_font_family[ $font_family_key ] ) ) {
			return array();
		}

		$webfonts = array();
		foreach ( $this->registry_by_font_family[ $font_family_key ] as $registration_key ) {
			// Safeguard. Skip if not in registry.
			if ( ! isset( $this->registered[ $registration_key ] ) ) {
				continue;
			}

			$webfonts[ $registration_key ] = $this->registered[ $registration_key ];
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
		if ( isset( $this->registered[ $registration_key ] ) ) {
			return $registration_key;
		}

		$this->registered[ $registration_key ] = $webfont;
		$this->store_in_query_by_containers( $webfont, $registration_key );

		return $registration_key;
	}

	/**
	 * Store the webfont into each query by container.
	 *
	 * These containers provide a performant way to quickly query webfonts by
	 * provider or font-family. The registration keys are stored in each for
	 * O(1) lookup.
	 *
	 * @since 5.9.0
	 *
	 * @param array  $webfont          Webfont definition.
	 * @param string $registration_key Webfont's registration key.
	 */
	private function store_in_query_by_containers( array $webfont, $registration_key ) {
		$font_family = $this->convert_font_family_into_key( $webfont['font-family'] );
		$provider    = $webfont['provider'];

		// Initialize the arrays if they do not exist.
		if ( ! isset( $this->registry_by_provider[ $provider ] ) ) {
			$this->registry_by_provider[ $provider ] = array();
		}
		if ( ! isset( $this->registry_by_font_family[ $font_family ] ) ) {
			$this->registry_by_font_family[ $font_family ] = array();
		}

		$this->registry_by_provider[ $provider ][]       = $registration_key;
		$this->registry_by_font_family[ $font_family ][] = $registration_key;
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
}
