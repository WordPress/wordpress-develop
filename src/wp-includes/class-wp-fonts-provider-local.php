<?php
/**
 * Webfonts API provider abstract class.
 *
 * Individual webfonts providers should extend this class and implement.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      5.9.0
 */

/**
 * Abstract class for webfonts API providers.
 */
final class WP_Fonts_Provider_Local extends WP_Fonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @var string
	 * @since 5.9.0
	 * @access protected
	 */
	protected $id = 'local';

	/**
	 * Get the CSS for the font.
	 *
	 * @access public
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
