<?php
/**
 * Webfonts API: Webfonts Schema Validator
 *
 * @package WordPress
 * @subpackage Webfonts
 * @since 5.9.0
 */

/**
 * Webfonts Schema Validator.
 *
 * Validates the webfont schema.
 */
class WP_Webfonts_Schema_Validator {

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
	 * Webfont being validated.
	 *
	 * @var string[]
	 */
	private $webfont = array();

	/**
	 * Checks if the given webfont schema is validate.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont definition.
	 * @return bool True when valid. False when invalid.
	 */
	public function is_schema_valid( array $webfont ) {
		$this->webfont = $webfont;

		$is_valid = (
			$this->is_provider_valid() &&
			$this->is_font_family_valid() &&
			$this->is_font_style_valid() &&
			$this->is_font_weight_valid()
		);

		$this->webfont = array();

		return $is_valid;
	}

	/**
	 * Checks if the provider is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_provider_valid() {
		// @todo check if provider is registered.

		if ( empty( $this->webfont['provider'] ) || ! is_string( $this->webfont['provider'] ) ) {
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
	 * Checks if the font family is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_family_valid() {
		if ( empty( $this->webfont['fontFamily'] ) || ! is_string( $this->webfont['fontFamily'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				__( 'Webfont must define a string "fontFamily".' ),
				'5.9.0'
			);

			return false;
		}

		return true;
	}

	/**
	 * Checks if the font style is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_style_valid() {
		if ( empty( $this->webfont['fontStyle'] ) || ! is_string( $this->webfont['fontStyle'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				__( 'Webfont must define a string "fontStyle".' ),
				'5.9.0'
			);

			return false;
		}

		if ( ! $this->is_font_style_value_valid( $this->webfont['fontStyle'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				sprintf(
				/* translators: 1: Slant angle, 2: Given font style. */
					__( 'Webfont font style must be normal, italic, oblique, or oblique %1$s. Given: %2$s.' ),
					'<angle>',
					$this->webfont['fontStyle']
				),
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
	private function is_font_style_value_valid( $font_style ) {
		if ( in_array( $font_style, $this->valid_font_style, true ) ) {
			return true;
		}

		// @todo Check for oblique <angle>.

		return false;
	}

	/**
	 * Checks if the font weight is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_weight_valid() {
		// @todo validate the value.
		if ( empty( $this->webfont['fontWeight'] ) || ! is_string( $this->webfont['fontWeight'] ) ) {
			_doing_it_wrong(
				'register_webfonts',
				__( 'Webfont must define a string "fontWeight".' ),
				'5.9.0'
			);

			return false;
		}

		return true;
	}
}
