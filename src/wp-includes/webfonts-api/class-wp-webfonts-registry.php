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
 *
 * @since 5.9.0
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
	 * Registers the given webfont if its schema is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont {
	 *     Webfont definition.
	 *
	 *    @type string       $provider              The provider ID (e.g. 'local', 'google').
	 *    @type string       $fontFamily            The @font-face font-family property.
	 *    @type string       $fontWeight            The @font-face font-weight property.
	 *                                              The font-weight can be a single value, or a range.
	 *                                              If a single value, then the font-weight can either be
	 *                                              a numeric value (400, 700, etc), or a word value (normal, bold, etc).
	 *                                              If a range, then the font-weight can be a numeric range
	 *                                              using 2 values, separated by a space ('100 700').
	 *    @type string       $fontStyle             The @font-face font-style property.
	 *                                              The font-style can be a valid CSS value (normal, italic etc).
	 *    @type string       $fontDisplay           The @font-face font-display property.
	 *                                              Accepted values: 'auto', 'block', 'fallback', 'swap'.
	 *    @type array|string $src                   The @font-face src property.
	 *                                              The src can be a single URL, or an array of URLs.
	 *    @type string       $fontStretch           The @font-face font-stretch property.
	 *    @type string       $fontVariant           The @font-face font-variant property.
	 *    @type string       $fontFeatureSettings   The @font-face font-feature-settings property.
	 *    @type string       $fontVariationSettings The @font-face font-variation-settings property.
	 *    @type string       $lineHeightOverride    The @font-face line-gap-override property.
	 *    @type string       $sizeAdjust            The @font-face size-adjust property.
	 *    @type string       $unicodeRange          The @font-face unicode-range property.
	 *    @type string       $ascendOverride        The @font-face ascend-override property.
	 *    @type string       $descendOverride       The @font-face descend-override property.
	 * }
	 *
	 * @return string Registration key.
	 */
	public function register( array $webfont ) {
		$webfont = $this->convert_to_kebab_case( $webfont );

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
	 * Convert camelCase parameters into kebab_case.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont definition.
	 * @return array Webfont with kebab_case parameters (keys).
	 */
	private function convert_to_kebab_case( array $webfont ) {
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
