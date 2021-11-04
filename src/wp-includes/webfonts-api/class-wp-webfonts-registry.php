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
	 * @var array[]
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

	/**
	 * Creates the registry.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Webfonts_Schema_Validator $validator Instance of the validator.
	 */
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
	 *    @type string       $provider                The provider ID (e.g. 'local', 'google').
	 *    @type string       $font-family             The @font-face font-family property.
	 *    @type string       $font-weight             The @font-face font-weight property.
	 *                                                The font-weight can be a single value, or a range.
	 *                                                If a single value, then the font-weight can either be
	 *                                                a numeric value (400, 700, etc), or a word value
	 *                                                (normal, bold, etc).
	 *                                                If a range, then the font-weight can be a numeric range
	 *                                                using 2 values, separated by a space ('100 700').
	 *    @type string       $font-style              The @font-face font-style property.
	 *                                                The font-style can be a valid CSS value (normal, italic etc).
	 *    @type string       $font-display            The @font-face font-display property.
	 *                                                Accepted values: 'auto', 'block', 'fallback', 'swap'.
	 *    @type array|string $src                     The @font-face src property.
	 *                                                The src can be a single URL, or an array of URLs.
	 *    @type string       $font-stretch            The @font-face font-stretch property.
	 *    @type string       $font-variant            The @font-face font-variant property.
	 *    @type string       $font-feature-settings   The @font-face font-feature-settings property.
	 *    @type string       $font-variation-settings The @font-face font-variation-settings property.
	 *    @type string       $line-gap-override       The @font-face line-gap-override property.
	 *    @type string       $size-adjust             The @font-face size-adjust property.
	 *    @type string       $unicode-range           The @font-face unicode-range property.
	 *    @type string       $ascend-override         The @font-face ascend-override property.
	 *    @type string       $descend-override        The @font-face descend-override property.
	 * }
	 * @return string Registration key.
	 */
	public function register( array $webfont ) {
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
		$this->store_for_query_by( $webfont, $registration_key );

		return $registration_key;
	}

	/**
	 * Store the webfont for query by request.
	 *
	 * This container provides a performant way to quickly query webfonts by
	 * provider. The registration keys are stored for O(1) lookup.
	 *
	 * @since 5.9.0
	 *
	 * @param array  $webfont          Webfont definition.
	 * @param string $registration_key Webfont's registration key.
	 */
	private function store_for_query_by( array $webfont, $registration_key ) {
		$provider = $webfont['provider'];

		// Initialize the array if it does not exist.
		if ( ! isset( $this->registry_by_provider[ $provider ] ) ) {
			$this->registry_by_provider[ $provider ] = array();
		}

		$this->registry_by_provider[ $provider ][] = $registration_key;
	}

	/**
	 * Generates the registration key.
	 *
	 * Format: font-family.font-style.font-weight
	 * For example: `'open-sans.normal.400'`.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont definition.
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
	 * @return string Font-family as a key.
	 */
	private function convert_font_family_into_key( $font_family ) {
		if ( ! is_string( $font_family ) || '' === $font_family ) {
			return '';
		}

		return sanitize_title( $font_family );
	}
}
