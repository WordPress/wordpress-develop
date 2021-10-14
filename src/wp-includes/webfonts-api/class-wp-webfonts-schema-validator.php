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
	protected $font_style = array( 'normal', 'italic', 'oblique', 'inherit', 'initial', 'revert', 'unset' );

	/**
	 * Valid font weight values.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	protected $font_weight = array( 'normal', 'bold', 'bolder', 'lighter', 'inherit' );

	/**
	 * Valid font display values.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	protected $font_display = array( 'auto', 'block', 'swap', 'fallback' );

	/**
	 * An array of valid CSS properties for @font-face.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	protected $font_face_properties = array(
		'ascend-override',
		'descend-override',
		'font-display',
		'font-family',
		'font-stretch',
		'font-style',
		'font-weight',
		'font-variant',
		'font-feature-settings',
		'font-variation-settings',
		'line-gap-override',
		'size-adjust',
		'src',
		'unicode-range',
	);

	/**
	 * Basic schema structure.
	 *
	 * @since 5.9.0
	 *
	 * @var array
	 */
	protected $basic_schema = array(
		'provider'     => '',
		'font-family'  => '',
		'font-style'   => 'normal',
		'font-weight'  => '400',
		'font-display' => 'fallback',
	);

	/**
	 * Webfont being validated.
	 *
	 * Set as a property for performance.
	 *
	 * @var array
	 */
	private $webfont = array();

	/**
	 * Checks if the given webfont schema is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont to validate.
	 * @return bool True when valid. False when invalid.
	 */
	public function is_valid_schema( array $webfont ) {
		return (
			$this->is_valid_provider( $webfont ) &&
			$this->is_valid_font_family( $webfont )
		);
	}

	/**
	 * Checks if the provider is validate.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont to valiate.
	 * @return bool True if valid. False if invalid.
	 */
	private function is_valid_provider( array $webfont ) {
		// @todo check if provider is registered.

		if (
			empty( $webfont['provider'] ) ||
			! is_string( $webfont['provider'] )
		) {
			trigger_error( __( 'Webfont provider must be a non-empty string.' ) );

			return false;
		}

		return true;
	}

	/**
	 * Checks if the font family is validate.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont to validate.
	 * @return bool True if valid. False if invalid.
	 */
	private function is_valid_font_family( array $webfont ) {
		if (
			empty( $webfont['font-family'] ) ||
			! is_string( $webfont['font-family'] )
		) {
			trigger_error( __( 'Webfont font family must be a non-empty string.' ) );

			return false;
		}

		return true;
	}

	/**
	 * Sets valid properties.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont definition.
	 * @return array Updated webfont.
	 */
	public function set_valid_properties( array $webfont ) {
		$this->webfont = array_merge( $this->basic_schema, $webfont );

		$this->set_valid_font_face_property();
		$this->set_valid_font_style();
		$this->set_valid_font_weight();
		$this->set_valid_font_display();

		$webfont       = $this->webfont;
		$this->webfont = array(); // Reset property.

		return $webfont;
	}

	/**
	 * Checks if the CSS property is valid for @font-face.
	 *
	 * @since 5.9.0
	 */
	private function set_valid_font_face_property() {
		foreach ( array_keys( $this->webfont ) as $property ) {
			/*
			 * Skip valid configuration parameters (these are configuring the webfont
			 * but are not @font-face properties.
			 */
			if ( 'provider' === $property ) {
				continue;
			}

			if ( ! in_array( $property, $this->font_face_properties, true ) ) {
				unset( $this->webfont[ $property ] );
			}
		}
	}

	/**
	 * Checks if the font style is validate.
	 *
	 * @since 5.9.0
	 */
	private function set_valid_font_style() {
		// If empty or not a string, trigger an error and then set the default value.
		if (
			empty( $this->webfont['font-style'] ) ||
			! is_string( $this->webfont['font-style'] )
		) {
			trigger_error( __( 'Webfont font style must be a non-empty string.' ) );

		} elseif ( // Bail out if the font-weight is a valid value.
			in_array( $this->webfont['font-style'], $this->font_style, true ) ||
			preg_match( '/^oblique\s+(\d+)%/', $this->webfont['font-style'] )
		) {
			return;
		}

		$this->webfont['font-style'] = 'normal';
	}

	/**
	 * Sets a default font weight if invalid.
	 *
	 * @since 5.9.0
	 */
	private function set_valid_font_weight() {
		// If empty or not a string, trigger an error and then set the default value.
		if (
			empty( $this->webfont['font-weight'] ) ||
			! is_string( $this->webfont['font-weight'] )
		) {
			trigger_error( __( 'Webfont font weight must be a non-empty string.' ) );

		} elseif ( // Bail out if the font-weight is a valid value.
			in_array( $this->webfont['font-weight'], $this->font_weight, true ) ||
			preg_match( '/^(\d+)$/', $this->webfont['font-weight'], $matches ) ||
			preg_match( '/^(\d+)\s+(\d+)$/', $this->webfont['font-weight'], $matches )
		) {
			return;
		}

		// Not valid. Set the default value.
		$this->webfont['font-weight'] = '400';
	}

	/**
	 * Sets a default font display if invalid.
	 *
	 * @since 5.9.0
	 */
	private function set_valid_font_display() {
		if (
			! empty( $this->webfont['font-display'] ) &&
			in_array( $this->webfont['font-display'], $this->font_display, true )
		) {
			return;
		}

		$this->webfont['font-display'] = 'fallback';
	}
}
