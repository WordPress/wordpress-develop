<?php
/**
 * Webfonts API: Provider for locally-hosted fonts.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      5.9.0
 */

/**
 * Webfonts API provider for locally-hosted fonts.
 */
class WP_Webfonts_Local_Provider extends WP_Webfonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	protected $id = 'local';

	/**
	 * Get validated params.
	 *
	 * @since 5.9.0
	 *
	 * @param array $params The webfont's parameters.
	 * @return array
	 */
	public function get_validated_params( $params ) {
		$params = parent::get_validated_params( $params );

		// Wrap font-family in quotes if it contains spaces.
		if ( false !== strpos( $params['font-family'], ' ' ) && false === strpos( $params['font-family'], '"' ) && false === strpos( $params['font-family'], "'" ) ) {
			$params['font-family'] = '"' . $params['font-family'] . '"';
		}
		return $params;
	}

	/**
	 * Get the CSS for a collection of fonts.
	 *
	 * @since 5.9.0
	 *
	 * @return string
	 */
	public function get_css() {
		$css = '';
		foreach ( $this->params as $font ) {

			// Validate font params.
			$font = $this->get_validated_params( $font );

			if ( empty( $font['font-family'] ) ) {
				continue;
			}

			$css .= '@font-face{';
			foreach ( $font as $key => $value ) {

				// Compile the "src" parameter.
				if ( 'src' === $key ) {
					$src = "local({$font['font-family']})";
					foreach ( $value as $item ) {

						// If the URL starts with "file:./" then it originated in a theme.json file.
						// Tweak the URL to be relative to the theme root.
						if ( 0 === strpos( $item['url'], 'file:./' ) ) {
							$item['url'] = wp_make_link_relative( get_theme_file_uri( str_replace( 'file:./', '', $item['url'] ) ) );
						}

						$src .= ( 'data' === $item['format'] )
							? ", url({$item['url']})"
							: ", url('{$item['url']}') format('{$item['format']}')";
					}
					$value = $src;
				}

				// If font-variation-settings is an array, convert it to a string.
				if ( 'font-variation-settings' === $key && is_array( $value ) ) {
					$variations = array();
					foreach ( $value as $key => $val ) {
						$variations[] = "$key $val";
					}
					$value = implode( ', ', $variations );
				}

				if ( ! empty( $value ) ) {
					$css .= "$key:$value;";
				}
			}
			$css .= '}';
		}

		return $css;
	}
}
