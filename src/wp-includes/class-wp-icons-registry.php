<?php
/**
 * Icons API: WP_Icons_Registry class
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Core class used for interacting with registered icons.
 *
 * @since 7.0.0
 */
class WP_Icons_Registry {
	/**
	 * Registered icons array.
	 *
	 * @since 7.0.0
	 * @var array[]
	 */
	protected $registered_icons = array();

	/**
	 * Container for the main instance of the class.
	 *
	 * @since 7.0.0
	 * @var WP_Icons_Registry|null
	 */
	protected static $instance = null;

	/**
	 * Constructor.
	 *
	 * WP_Icons_Registry is a singleton class, so keep this protected.
	 *
	 * Icons are populated via `_wp_register_default_icons()` during the
	 * `init` action. Third-party icons can be registered via
	 * {@see wp_register_icon()} once their collection is registered.
	 *
	 * @since 7.0.0
	 */
	protected function __construct() {}

	/**
	 * Registers an icon.
	 *
	 * @since 7.0.0
	 * @since 7.1.0 The icon name must be namespaced in the form "collection/icon-name".
	 *
	 * @param string $icon_name       Namespaced icon name in the form "collection/icon-name"
	 *                                (e.g. "core/arrow-left").
	 * @param array  $icon_properties {
	 *     List of properties for the icon.
	 *
	 *     @type string $label     Required. A human-readable label for the icon.
	 *     @type string $content   Optional. SVG markup for the icon.
	 *                             If not provided, the content will be retrieved from the `file_path` if set.
	 *                             If both `content` and `file_path` are not set, the icon will not be registered.
	 *     @type string $file_path Optional. The full path to the file containing the icon content.
	 * }
	 * @return bool True if the icon was registered with success and false otherwise.
	 */
	public function register( $icon_name, $icon_properties ) {
		if ( ! isset( $icon_name ) || ! is_string( $icon_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon name must be a string.' ),
				'7.0.0'
			);
			return false;
		}

		// Require a namespaced name in the form "collection/icon-name".
		if ( ! str_contains( $icon_name, '/' ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon name must be namespaced in the form "collection/icon-name".' ),
				'7.1.0'
			);
			return false;
		}

		// Split the namespaced name into a collection slug and an unqualified icon name.
		list( $collection, $unqualified_name ) = explode( '/', $icon_name, 2 );

		if ( preg_match( '/[A-Z]/', $unqualified_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon names must not contain uppercase characters.' ),
				'7.1.0'
			);
			return false;
		}

		if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/', $unqualified_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon names must start and end with a lowercase letter or digit and contain only lowercase letters, digits, hyphens, and underscores.' ),
				'7.1.0'
			);
			return false;
		}

		$allowed_keys = array_fill_keys( array( 'label', 'content', 'file_path' ), 1 );
		foreach ( array_keys( $icon_properties ) as $key ) {
			if ( ! array_key_exists( $key, $allowed_keys ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: The name of a user-provided key. */
						__( 'Invalid icon property: "%s".' ),
						$key
					),
					'7.0.0'
				);
				return false;
			}
		}

		if ( ! WP_Icon_Collections_Registry::get_instance()->is_registered( $collection ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: Icon collection slug. */
					__( 'Icon collection "%s" is not registered.' ),
					$collection
				),
				'7.1.0'
			);
			return false;
		}

		if ( ! isset( $icon_properties['label'] ) || ! is_string( $icon_properties['label'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon label must be a string.' ),
				'7.0.0'
			);
			return false;
		}

		if (
			( ! isset( $icon_properties['content'] ) && ! isset( $icon_properties['file_path'] ) ) ||
			( isset( $icon_properties['content'] ) && isset( $icon_properties['file_path'] ) )
		) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icons must provide either `content` or `file_path`.' ),
				'7.0.0'
			);
			return false;
		}

		if ( isset( $icon_properties['content'] ) ) {
			if ( ! is_string( $icon_properties['content'] ) ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'Icon content must be a string.' ),
					'7.0.0'
				);
				return false;
			}

			$sanitized_icon_content = $this->sanitize_inline_svg( $icon_properties['content'] );
			if ( empty( $sanitized_icon_content ) ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'Icon content does not contain valid SVG markup.' ),
					'7.0.0'
				);
				return false;
			}

			$icon_properties['content'] = $sanitized_icon_content;
		}

		$qualified_name = $collection . '/' . $unqualified_name;

		if ( $this->is_registered( $qualified_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon is already registered.' ),
				'7.1.0'
			);
			return false;
		}

		$icon = array_merge(
			$icon_properties,
			array(
				'name'       => $qualified_name,
				'collection' => $collection,
			)
		);

		$this->registered_icons[ $qualified_name ] = $icon;

		return true;
	}

	/**
	 * Unregisters an icon.
	 *
	 * @since 7.1.0
	 *
	 * @param string $icon_name Namespaced icon name in the form "collection/icon-name"
	 *                          (e.g. "core/arrow-left").
	 * @return bool True if the icon was unregistered successfully, false otherwise.
	 */
	public function unregister( $icon_name ) {
		if ( ! $this->is_registered( $icon_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: Icon name. */
					__( 'Icon "%s" is not registered.' ),
					$icon_name
				),
				'7.1.0'
			);
			return false;
		}

		unset( $this->registered_icons[ $icon_name ] );
		return true;
	}

	/**
	 * Builds the allowed attribute list for wp_kses() from attribute names.
	 *
	 * @since 7.1.0
	 *
	 * @param string ...$attribute_names Attribute names to allow.
	 * @return array Attribute names mapped to true.
	 */
	private function get_allowed_attribute_list( ...$attribute_names ) {
		return array_fill_keys( $attribute_names, true );
	}

	/**
	 * Sanitizes an SVG embedded in an HTML fragment.
	 *
	 * The input SVG must have been extracted as HTML from a broader HTML
	 * document, NOT as an entire XML document from an external file or JSON
	 * value. Parsed as HTML, XML-only constructs (CDATA, `<foreignObject>`
	 * integration points) are mis-parsed. WP_HTML_Processor extracts the whole
	 * SVG element before wp_kses runs, so inner HTML tags like `<p>` do not
	 * terminate the SVG and self-closing tags are handled correctly.
	 *
	 * @since 7.0.0
	 * @since 7.1.0 Renamed from `sanitize_icon_content()`.
	 *
	 * @param string $html_containing_svg HTML fragment containing the SVG to sanitize.
	 * @return string The sanitized SVG, or an empty string when no valid SVG is found.
	 */
	private function sanitize_inline_svg( $html_containing_svg ) {
		// Core attributes applicable to most elements. `data-*` is a wildcard
		// supported by wp_kses() and matches any data attribute.
		$core_attributes = $this->get_allowed_attribute_list( 'class', 'data-*', 'id', 'style' );

		/*
		 * ARIA and accessibility attributes. wp_kses() does not support an
		 * `aria-*` wildcard, so every ARIA state and property is listed
		 * explicitly. The list mirrors the WAI-ARIA states and properties.
		 *
		 * @link https://www.w3.org/TR/wai-aria-1.2/#state_prop_def
		 */
		$aria_attributes = $this->get_allowed_attribute_list(
			'aria-activedescendant',
			'aria-atomic',
			'aria-autocomplete',
			'aria-busy',
			'aria-checked',
			'aria-colcount',
			'aria-colindex',
			'aria-colspan',
			'aria-controls',
			'aria-current',
			'aria-describedby',
			'aria-description',
			'aria-details',
			'aria-disabled',
			'aria-dropeffect',
			'aria-errormessage',
			'aria-expanded',
			'aria-flowto',
			'aria-grabbed',
			'aria-haspopup',
			'aria-hidden',
			'aria-invalid',
			'aria-keyshortcuts',
			'aria-label',
			'aria-labelledby',
			'aria-level',
			'aria-live',
			'aria-modal',
			'aria-multiline',
			'aria-multiselectable',
			'aria-orientation',
			'aria-owns',
			'aria-placeholder',
			'aria-posinset',
			'aria-pressed',
			'aria-readonly',
			'aria-relevant',
			'aria-required',
			'aria-roledescription',
			'aria-rowcount',
			'aria-rowindex',
			'aria-rowspan',
			'aria-selected',
			'aria-setsize',
			'aria-sort',
			'aria-valuemax',
			'aria-valuemin',
			'aria-valuenow',
			'aria-valuetext',
			'focusable',
			'role',
			'tabindex',
		);

		// Presentation attributes for graphics elements (shapes, text, use, image).
		$presentation_attributes = $this->get_allowed_attribute_list(
			'clip-path',
			'clip-rule',
			'color',
			'color-interpolation',
			'color-rendering',
			'display',
			'fill',
			'fill-opacity',
			'fill-rule',
			'filter',
			'mask',
			'opacity',
			'paint-order',
			'stroke',
			'stroke-dasharray',
			'stroke-dashoffset',
			'stroke-linecap',
			'stroke-linejoin',
			'stroke-miterlimit',
			'stroke-opacity',
			'stroke-width',
			'transform',
			'vector-effect',
			'visibility',
		);

		// Marker attributes (only for shape elements).
		$marker_attributes = $this->get_allowed_attribute_list( 'marker-end', 'marker-mid', 'marker-start' );

		// Container attributes for grouping elements.
		$container_attributes = $this->get_allowed_attribute_list(
			'clip-path',
			'display',
			'filter',
			'mask',
			'opacity',
			'transform',
			'visibility',
		);

		/*
		 * Allowed tags for wp_kses(). WP_HTML_Processor::normalize() with
		 * constraints (similar structure to this array) is proposed to improve
		 * HTML/SVG sanitization in the future.
		 *
		 * @link https://github.com/dmsnell/wordpress-develop/pull/20
		 */
		$allowed_tags = array(
			// Root SVG element.
			'svg'                 => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$this->get_allowed_attribute_list(
					'height',
					'preserveaspectratio',
					'viewbox',
					'width',
					'x',
					'xmlns',
					'xmlns:xlink',
					'y',
				)
			),
			// Basic shape elements (with markers).
			'path'                => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$marker_attributes,
				$this->get_allowed_attribute_list(
					'd',
					'pathlength',
				)
			),
			'circle'              => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$marker_attributes,
				$this->get_allowed_attribute_list(
					'cx',
					'cy',
					'r',
				)
			),
			'ellipse'             => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$marker_attributes,
				$this->get_allowed_attribute_list(
					'cx',
					'cy',
					'rx',
					'ry',
				)
			),
			'line'                => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$marker_attributes,
				$this->get_allowed_attribute_list(
					'x1',
					'x2',
					'y1',
					'y2',
				)
			),
			'polygon'             => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$marker_attributes,
				$this->get_allowed_attribute_list(
					'points',
				)
			),
			'polyline'            => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$marker_attributes,
				$this->get_allowed_attribute_list(
					'points',
				)
			),
			'rect'                => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$marker_attributes,
				$this->get_allowed_attribute_list(
					'height',
					'rx',
					'ry',
					'width',
					'x',
					'y',
				)
			),
			// Grouping and structural elements.
			'g'                   => array_merge(
				$core_attributes,
				$aria_attributes,
				$container_attributes
			),
			'defs'                => $core_attributes,
			'view'                => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'preserveaspectratio',
					'viewbox',
					'viewtarget',
					'zoomandpan',
				)
			),
			'symbol'              => array_merge(
				$core_attributes,
				$aria_attributes,
				$container_attributes,
				$this->get_allowed_attribute_list(
					'height',
					'preserveaspectratio',
					'viewbox',
					'width',
					'x',
					'y',
				)
			),
			'use'                 => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$this->get_allowed_attribute_list(
					'height',
					'href',
					'width',
					'x',
					'xlink:href',
					'y',
				)
			),
			'switch'              => array_merge(
				$core_attributes,
				$aria_attributes,
				$container_attributes
			),
			// Linking element.
			'a'                   => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$container_attributes,
				$this->get_allowed_attribute_list(
					'href',
					'rel',
					'target',
					'type',
					'xlink:href',
				)
			),
			'clippath'            => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'clippathunits',
					'transform',
				)
			),
			'mask'                => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'height',
					'maskcontentunits',
					'maskunits',
					'width',
					'x',
					'y',
				)
			),
			// Gradient elements.
			'lineargradient'      => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'gradienttransform',
					'gradientunits',
					'href',
					'spreadmethod',
					'x1',
					'x2',
					'xlink:href',
					'y1',
					'y2',
				)
			),
			'radialgradient'      => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'cx',
					'cy',
					'fr',
					'fx',
					'fy',
					'gradienttransform',
					'gradientunits',
					'href',
					'r',
					'spreadmethod',
					'xlink:href',
				)
			),
			'stop'                => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'offset',
					'stop-color',
					'stop-opacity',
				)
			),
			// Pattern element.
			'pattern'             => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'height',
					'href',
					'patterncontentunits',
					'patterntransform',
					'patternunits',
					'preserveaspectratio',
					'viewbox',
					'width',
					'x',
					'xlink:href',
					'y',
				)
			),
			// Filter elements.
			'filter'              => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'filterunits',
					'height',
					'primitiveunits',
					'width',
					'x',
					'y',
				)
			),
			'feblend'             => $this->get_allowed_attribute_list(
				'in',
				'in2',
				'mode',
				'result',
			),
			'fecolormatrix'       => $this->get_allowed_attribute_list(
				'in',
				'result',
				'type',
				'values',
			),
			'fecomponenttransfer' => $this->get_allowed_attribute_list(
				'in',
				'result',
			),
			'fecomposite'         => $this->get_allowed_attribute_list(
				'in',
				'in2',
				'k1',
				'k2',
				'k3',
				'k4',
				'operator',
				'result',
			),
			'feconvolvematrix'    => $this->get_allowed_attribute_list(
				'bias',
				'divisor',
				'edgemode',
				'in',
				'kernelmatrix',
				'order',
				'preservealpha',
				'result',
				'targetx',
				'targety',
			),
			'fediffuselighting'   => $this->get_allowed_attribute_list(
				'diffuseconstant',
				'in',
				'result',
				'surfacescale',
			),
			'fedisplacementmap'   => $this->get_allowed_attribute_list(
				'in',
				'in2',
				'result',
				'scale',
				'xchannelselector',
				'ychannelselector',
			),
			'fedistantlight'      => $this->get_allowed_attribute_list(
				'azimuth',
				'elevation',
			),
			'feflood'             => $this->get_allowed_attribute_list(
				'flood-color',
				'flood-opacity',
				'result',
			),
			'fegaussianblur'      => $this->get_allowed_attribute_list(
				'edgemode',
				'in',
				'result',
				'stddeviation',
			),
			'feimage'             => $this->get_allowed_attribute_list(
				'href',
				'preserveaspectratio',
				'result',
				'xlink:href',
			),
			'femerge'             => $this->get_allowed_attribute_list(
				'result',
			),
			'femergenode'         => $this->get_allowed_attribute_list(
				'in',
			),
			'femorphology'        => $this->get_allowed_attribute_list(
				'in',
				'operator',
				'radius',
				'result',
			),
			'feoffset'            => $this->get_allowed_attribute_list(
				'dx',
				'dy',
				'in',
				'result',
			),
			'fepointlight'        => $this->get_allowed_attribute_list(
				'x',
				'y',
				'z',
			),
			'fespecularlighting'  => $this->get_allowed_attribute_list(
				'in',
				'result',
				'specularconstant',
				'specularexponent',
				'surfacescale',
			),
			'fespotlight'         => $this->get_allowed_attribute_list(
				'limitingconeangle',
				'pointsatx',
				'pointsaty',
				'pointsatz',
				'specularexponent',
				'x',
				'y',
				'z',
			),
			'fetile'              => $this->get_allowed_attribute_list(
				'in',
				'result',
			),
			'feturbulence'        => $this->get_allowed_attribute_list(
				'basefrequency',
				'numoctaves',
				'result',
				'seed',
				'stitchtiles',
				'type',
			),
			'fefunca'             => $this->get_allowed_attribute_list(
				'amplitude',
				'exponent',
				'intercept',
				'offset',
				'slope',
				'tablevalues',
				'type',
			),
			'fefuncb'             => $this->get_allowed_attribute_list(
				'amplitude',
				'exponent',
				'intercept',
				'offset',
				'slope',
				'tablevalues',
				'type',
			),
			'fefuncg'             => $this->get_allowed_attribute_list(
				'amplitude',
				'exponent',
				'intercept',
				'offset',
				'slope',
				'tablevalues',
				'type',
			),
			'fefuncr'             => $this->get_allowed_attribute_list(
				'amplitude',
				'exponent',
				'intercept',
				'offset',
				'slope',
				'tablevalues',
				'type',
			),
			// Text elements.
			'text'                => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$this->get_allowed_attribute_list(
					'alignment-baseline',
					'baseline-shift',
					'dominant-baseline',
					'dx',
					'dy',
					'font-family',
					'font-size',
					'font-style',
					'font-variant',
					'font-weight',
					'lengthadjust',
					'letter-spacing',
					'rotate',
					'text-anchor',
					'text-decoration',
					'textlength',
					'word-spacing',
					'writing-mode',
					'x',
					'y',
				)
			),
			'tspan'               => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$this->get_allowed_attribute_list(
					'dx',
					'dy',
					'font-family',
					'font-size',
					'font-style',
					'font-weight',
					'lengthadjust',
					'rotate',
					'text-anchor',
					'text-decoration',
					'textlength',
					'x',
					'y',
				)
			),
			'textpath'            => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$this->get_allowed_attribute_list(
					'href',
					'method',
					'spacing',
					'startoffset',
					'text-anchor',
					'xlink:href',
				)
			),
			// Descriptive elements.
			'title'               => array(),
			'desc'                => array(),
			'metadata'            => array(),
			// Image element.
			'image'               => array_merge(
				$core_attributes,
				$aria_attributes,
				$presentation_attributes,
				$this->get_allowed_attribute_list(
					'height',
					'href',
					'preserveaspectratio',
					'width',
					'x',
					'xlink:href',
					'y',
				)
			),
			// Marker element.
			'marker'              => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'markerheight',
					'markerunits',
					'markerwidth',
					'orient',
					'preserveaspectratio',
					'refx',
					'refy',
					'viewbox',
				)
			),
			// Animation elements.
			'animate'             => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'accumulate',
					'additive',
					'attributename',
					'begin',
					'calcmode',
					'dur',
					'end',
					'from',
					'keysplines',
					'keytimes',
					'repeatcount',
					'to',
					'values',
				)
			),
			'animatemotion'       => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'accumulate',
					'additive',
					'begin',
					'calcmode',
					'dur',
					'end',
					'from',
					'keypoints',
					'keysplines',
					'keytimes',
					'path',
					'repeatcount',
					'rotate',
					'to',
					'values',
				)
			),
			'animatetransform'    => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'accumulate',
					'additive',
					'attributename',
					'begin',
					'calcmode',
					'dur',
					'end',
					'from',
					'keysplines',
					'keytimes',
					'repeatcount',
					'to',
					'type',
					'values',
				)
			),
			'set'                 => array_merge(
				$core_attributes,
				$this->get_allowed_attribute_list(
					'attributename',
					'begin',
					'dur',
					'end',
					'repeatcount',
					'to',
				)
			),
		);

		$processor = WP_HTML_Processor::create_fragment( $html_containing_svg );
		if ( ! $processor ) {
			return '';
		}

		/*
		 * Find the first SVG root, ignoring surrounding content. The namespace
		 * check rejects a foreign-namespaced `<svg>`, such as in `<math><svg>`.
		 */
		if ( ! $processor->next_tag( 'SVG' ) || 'svg' !== $processor->get_namespace() ) {
			return '';
		}

		$svg   = $processor->serialize_token();
		$depth = $processor->get_current_depth();
		while ( $processor->next_token() && $processor->get_current_depth() >= $depth ) {
			$svg .= $processor->serialize_token();
		}

		/*
		 * An early stop inside an SVG means truncated input, not unsupported
		 * markup. Reject it: the parser can synthesize closing tags that were
		 * never written, so no valid document remains to trust.
		 */
		if (
			null !== $processor->get_last_error()
			|| $processor->paused_at_incomplete_token()
		) {
			return '';
		}
		$svg .= '</svg>';

		/*
		 * Reject more than one top-level SVG. Nested SVGs were extracted above,
		 * so only sibling roots remain to be found.
		 */
		while ( $processor->next_tag( 'SVG' ) ) {
			if ( 'svg' === $processor->get_namespace() ) {
				return '';
			}
		}

		return wp_kses( $svg, $allowed_tags );
	}

	/**
	 * Retrieves the content of a registered icon.
	 *
	 * @since 7.0.0
	 *
	 * @param string $icon_name Icon name including namespace.
	 * @return string|null The content of the icon, if found.
	 */
	protected function get_content( $icon_name ) {
		if ( ! isset( $this->registered_icons[ $icon_name ]['content'] ) ) {
			$file_path  = $this->registered_icons[ $icon_name ]['file_path'] ?? '';
			$is_stringy = is_string( $file_path ) || ( is_object( $file_path ) && method_exists( $file_path, '__toString' ) );
			$icon_path  = $is_stringy ? realpath( (string) $file_path ) : false;

			if (
				! is_string( $icon_path ) ||
				! str_ends_with( $icon_path, '.svg' ) ||
				! is_file( $icon_path ) ||
				! is_readable( $icon_path )
			) {
				wp_trigger_error(
					__METHOD__,
					__( 'Icon file is missing or unreadable.' )
				);
				return null;
			}

			/*
			 * An external `.svg` file is XML, but sanitize_inline_svg() expects an
			 * inline HTML fragment. A dedicated XML sanitizer should handle this
			 * in the future.
			 */
			$content = $this->sanitize_inline_svg( file_get_contents( $icon_path ) );

			if ( empty( $content ) ) {
				wp_trigger_error(
					__METHOD__,
					__( 'Icon content does not contain valid SVG markup.' )
				);
				return null;
			}

			$this->registered_icons[ $icon_name ]['content'] = $content;
		}
		return $this->registered_icons[ $icon_name ]['content'];
	}

	/**
	 * Retrieves an array containing the properties of a registered icon.
	 *
	 * @since 7.0.0
	 *
	 * @param string $icon_name Icon name including namespace.
	 * @return array|null Registered icon properties or `null` if the icon is not registered.
	 */
	public function get_registered_icon( $icon_name ) {
		if ( ! $this->is_registered( $icon_name ) ) {
			return null;
		}

		$icon            = $this->registered_icons[ $icon_name ];
		$icon['content'] = $icon['content'] ?? $this->get_content( $icon_name );

		return $icon;
	}

	/**
	 * Retrieves all registered icons.
	 *
	 * @since 7.0.0
	 * @since 7.1.0 Search also matches icon labels.
	 *
	 * @param string $search Optional. Search term by which to filter the icons.
	 * @return array[] Array of arrays containing the registered icon properties.
	 */
	public function get_registered_icons( $search = '' ) {
		$icons = array();

		foreach ( $this->registered_icons as $icon ) {
			if ( ! empty( $search )
				&& false === stripos( $icon['name'], $search )
				&& false === stripos( $icon['label'] ?? '', $search )
			) {
				continue;
			}

			$icon['content'] = $icon['content'] ?? $this->get_content( $icon['name'] );
			$icons[]         = $icon;
		}

		return $icons;
	}

	/**
	 * Checks if an icon is registered.
	 *
	 * @since 7.0.0
	 *
	 * @param string $icon_name Icon name including namespace.
	 * @return bool True if the icon is registered, false otherwise.
	 */
	public function is_registered( $icon_name ) {
		return isset( $this->registered_icons[ $icon_name ] );
	}

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 7.0.0
	 *
	 * @return WP_Icons_Registry The main instance.
	 */
	public static function get_instance() {
		self::$instance ??= new self();

		return self::$instance;
	}
}
