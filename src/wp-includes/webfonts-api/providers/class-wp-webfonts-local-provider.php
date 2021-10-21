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
	 * Prepares the given webfont.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont to validate.
	 * @return array
	 */
	protected function prepare( array $webfont ) {
		$webfont = $this->order_src( $webfont );

		// Wrap font-family in quotes if it contains spaces.
		if (
			false !== strpos( $webfont['font-family'], ' ' ) &&
			false === strpos( $webfont['font-family'], '"' ) &&
			false === strpos( $webfont['font-family'], "'" )
		) {
			$webfont['font-family'] = '"' . $webfont['font-family'] . '"';
		}

		return $webfont;
	}

	/**
	 * Order `src` items to optimize for browser support.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont to process.
	 * @return string[]
	 */
	private function order_src( array $webfont ) {
		if ( ! is_array( $webfont['src'] ) ) {
			$webfont['src'] = (array) $webfont['src'];
		}

		$src         = array();
		$src_ordered = array();

		foreach ( $webfont['src'] as $url ) {
			// Add data URIs first.
			if ( 0 === strpos( trim( $url ), 'data:' ) ) {
				$src_ordered[] = array(
					'url'    => $url,
					'format' => 'data',
				);
				continue;
			}
			$format         = pathinfo( $url, PATHINFO_EXTENSION );
			$src[ $format ] = $url;
		}

		// Add woff2.
		if ( ! empty( $src['woff2'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['woff2'],
				'format' => 'woff2',
			);
		}

		// Add woff.
		if ( ! empty( $src['woff'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['woff'],
				'format' => 'woff',
			);
		}

		// Add ttf.
		if ( ! empty( $src['ttf'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['ttf'],
				'format' => 'truetype',
			);
		}

		// Add eot.
		if ( ! empty( $src['eot'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['eot'],
				'format' => 'embedded-opentype',
			);
		}

		// Add otf.
		if ( ! empty( $src['otf'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['otf'],
				'format' => 'opentype',
			);
		}
		$webfont['src'] = $src_ordered;

		return $webfont;
	}

	/**
	 * Get the CSS for a collection of webfonts.
	 *
	 * @since 5.9.0
	 *
	 * @return string The CSS.
	 */
	public function get_css() {
		$css = '';

		foreach ( $this->webfonts as $webfont ) {
			$css .= "@font-face{\n" . $this->build_font_css( $webfont ) . "}\n";
		}

		return $css;
	}

	/**
	 * Builds the font-family's CSS.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont to process.
	 * @return string This font-family's CSS.
	 */
	private function build_font_css( array $webfont ) {
		$css = '';
		foreach ( $webfont as $key => $value ) {

			// Compile the "src" parameter.
			if ( 'src' === $key ) {
				$value = $this->compile_src( $webfont['font-family'], $value );
			}

			// If font-variation-settings is an array, convert it to a string.
			if ( 'font-variation-settings' === $key && is_array( $value ) ) {
				$value = $this->compile_variations( $value );
			}

			if ( ! empty( $value ) ) {
				$css .= "\t$key:$value;\n";
			}
		}

		return $css;
	}

	/**
	 * Compiles the `src` into valid CSS.
	 *
	 * @since 5.9.0
	 *
	 * @param string $font_family Font family.
	 * @param array  $value       Value to process.
	 * @return string The CSS.
	 */
	private function compile_src( $font_family, array $value ) {
		$src = "local($font_family)";

		foreach ( $value as $item ) {

			if ( 0 === strpos( $item['url'], get_site_url() ) ) {
				$item['url'] = wp_make_link_relative( $item['url'] );
			}

			$src .= ( 'data' === $item['format'] )
				? ", url({$item['url']})"
				: ", url('{$item['url']}') format('{$item['format']}')";
		}
		return $src;
	}

	/**
	 * Compiles the font variation settings.
	 *
	 * @since 5.9.0
	 *
	 * @param array $font_variation_settings Array of font variation settings.
	 * @return string The CSS.
	 */
	private function compile_variations( array $font_variation_settings ) {
		$variations = '';

		foreach ( $font_variation_settings as $key => $value ) {
			$variations .= "$key $value";
		}

		return $variations;
	}
}
