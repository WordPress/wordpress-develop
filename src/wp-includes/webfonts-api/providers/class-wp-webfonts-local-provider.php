<?php
/**
 * Webfonts API provider for locally-hosted fonts.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      5.9.0
 */

/**
 * Webfonts API provider for locally-hosted fonts.
 */
final class WP_Webfonts_Local_Provider extends WP_Fonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @since 5.9.0
	 * @var string
	 */
	protected $id = 'local';

	/**
	 * Get validated params.
	 *
	 * @access public
	 * @since 5.9.0
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
	 * Get the CSS for the font.
	 *
	 * @since 5.9.0
	 * @return string
	 */
	public function get_css() {
		if ( empty( $this->params['font-family'] ) ) {
			return '';
		}

		$css = '@font-face{';
		foreach ( $this->params as $key => $value ) {

			// Skip the "preload" parameter.
			if ( 'preload' === $key ) {
				continue;
			}

			// Compile the "src" parameter.
			if ( 'src' === $key ) {
				$src = "local({$this->params['font-family']})";
				foreach ( $value as $item ) {
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

		return $css;
	}
}
