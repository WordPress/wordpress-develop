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
abstract class WP_Webfonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @since 5.9.0
	 * @var string
	 */
	protected $id;

	/**
	 * An array of URLs to preconnect to.
	 *
	 * @since 5.9.0
	 * @var array
	 */
	protected $preconnect_urls = array();

	/**
	 * The provider's root URL.
	 *
	 * @since 5.9.0
	 * @var string
	 */
	protected $root_url = '';

	/**
	 * Webfont parameters.
	 *
	 * @since 5.9.0
	 * @var array
	 */
	protected $params = array();

	/**
	 * An array of valid CSS properties for @font-face.
	 *
	 * @since 5.9.0
	 * @var array
	 */
	protected $valid_font_face_properties = array(
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
	 * An array of API parameters which will not be added to the @font-face.
	 *
	 * @since 5.9.0
	 * @var array
	 */
	protected $api_params = array();

	/**
	 * Get the provider's unique ID.
	 *
	 * @since 5.9.0
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the root URL for the provider.
	 *
	 * @since 5.9.0
	 *
	 * @return string
	 */
	public function get_root_url() {
		return $this->root_url;
	}

	/**
	 * Get the array of URLs to preconnect to.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function get_preconnect_urls() {
		return $this->preconnect_urls;
	}

	/**
	 * Set the object's params.
	 *
	 * @since 5.9.0
	 *
	 * @param array $params The webfont's parameters.
	 */
	public function set_params( $params ) {
		// Default values.
		$defaults = array(
			'font-weight'  => '400',
			'font-style'   => 'normal',
			'font-display' => 'fallback',
			'src'          => array(),
		);

		// Merge defaults with passed params.
		$params = wp_parse_args( $params, $defaults );

		// Whitelisted params.
		$whitelist = array_merge( $this->valid_font_face_properties, $this->api_params );

		// Only allow whitelisted properties.
		foreach ( $params as $key => $value ) {
			if ( ! in_array( $key, $whitelist, true ) ) {
				unset( $params[ $key ] );
			}
		}

		// Order $src items to optimize for browser support.
		if ( ! empty( $params['src'] ) ) {
			$params['src'] = (array) $params['src'];
			$src           = array();
			$src_ordered   = array();

			foreach ( $params['src'] as $url ) {
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
			$params['src'] = $src_ordered;
		}

		// Only allow valid font-display values.
		if (
			! empty( $params['font-display'] ) &&
			! in_array( $params['font-display'], array( 'auto', 'block', 'swap', 'fallback' ), true )
		) {
			$params['font-display'] = 'fallback';
		}

		// Only allow valid font-style values.
		if (
			! empty( $params['font-style'] ) &&
			! in_array( $params['font-style'], array( 'normal', 'italic', 'oblique' ), true ) &&
			! preg_match( '/^oblique\s+(\d+)%/', $params['font-style'], $matches )
		) {
			$params['font-style'] = 'normal';
		}

		// Only allow valid font-weight values.
		if (
			! empty( $params['font-weight'] ) &&
			! in_array( $params['font-weight'], array( 'normal', 'bold', 'bolder', 'lighter', 'inherit' ), true ) &&
			! preg_match( '/^(\d+)$/', $params['font-weight'], $matches ) &&
			! preg_match( '/^(\d+)\s+(\d+)$/', $params['font-weight'], $matches )
		) {
			$params['font-weight'] = 'normal';
		}

		$this->params = $params;
	}

	/**
	 * Get the object's params.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Get the CSS for the font.
	 *
	 * @since 5.9.0
	 *
	 * @return string
	 */
	abstract public function get_css();
}
