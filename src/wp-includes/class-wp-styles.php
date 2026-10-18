<?php
/**
 * Dependencies API: WP_Styles class
 *
 * @since 2.6.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

/**
 * Core class used to register styles.
 *
 * @since 2.6.0
 *
 * @see WP_Dependencies
 *
 * @property string        $concat         Handles of the styles to concatenate. Deprecated in 7.2.0, as styles
 *                                         are no longer concatenated.
 * @property string        $concat_version Handles and versions of the styles to concatenate. Deprecated in 3.4.0.
 * @property bool          $do_concat      Whether to concatenate styles. Deprecated in 7.2.0, as styles are
 *                                         no longer concatenated.
 * @property string        $print_html     Markup of the styles held while concatenating. Deprecated in 7.2.0,
 *                                         as styles are no longer concatenated.
 * @property string        $print_code     Inline styles held while concatenating. Deprecated in 7.2.0, as styles
 *                                         are no longer concatenated.
 * @property string[]|null $default_dirs   Directories of the styles that could be concatenated. Deprecated in
 *                                         7.2.0, as styles are no longer concatenated.
 */
class WP_Styles extends WP_Dependencies {
	/**
	 * Base URL for styles.
	 *
	 * Full URL with trailing slash.
	 *
	 * @since 2.6.0
	 * @see wp_default_styles()
	 * @var string|null
	 */
	public $base_url;

	/**
	 * URL of the content directory.
	 *
	 * @since 2.8.0
	 * @see wp_default_styles()
	 * @var string|null
	 */
	public $content_url;

	/**
	 * Default version string for stylesheets.
	 *
	 * @since 2.6.0
	 * @see wp_default_styles()
	 * @var string|null
	 */
	public $default_version;

	/**
	 * The current text direction.
	 *
	 * @since 2.6.0
	 * @see wp_default_styles()
	 * @var string
	 */
	public $text_direction = 'ltr';

	/**
	 * Default values of the deprecated properties, which are now handled by the magic methods.
	 *
	 * @since 7.2.0
	 * @see WP_Styles::__get()
	 * @var array<string, string|bool|string[]>
	 */
	private const DEPRECATED_PROPERTIES = array(
		'concat'         => '',
		'concat_version' => '',
		'do_concat'      => false,
		'print_html'     => '',
		'print_code'     => '',
		'default_dirs'   => array( '/wp-admin/', '/wp-includes/css/' ),
	);

	/**
	 * Values of the deprecated properties which have been read or written.
	 *
	 * @since 7.2.0
	 * @see WP_Styles::DEPRECATED_PROPERTIES
	 * @var array<string, mixed>
	 * @phpstan-var array{ concat?: string, concat_version?: string, do_concat?: bool, print_html?: string, print_code?: string, default_dirs?: string[]|null }
	 */
	private $deprecated_property_values = array();

	/**
	 * Constructor.
	 *
	 * @since 2.6.0
	 */
	public function __construct() {
		/**
		 * Fires when the WP_Styles instance is initialized.
		 *
		 * @since 2.6.0
		 *
		 * @param WP_Styles $wp_styles WP_Styles instance (passed by reference).
		 */
		do_action_ref_array( 'wp_default_styles', array( &$this ) );
	}

	/**
	 * Gets a deprecated property.
	 *
	 * The value is returned by reference, so that plugins can still modify an
	 * array or string in place, as in `$wp_styles->default_dirs[] = $dir`.
	 *
	 * @since 7.2.0
	 *
	 * @see WP_Styles::DEPRECATED_PROPERTIES
	 *
	 * @param string $name Property name.
	 * @return mixed Property value, or null if the property is undefined.
	 *
	 * @throws Error If the property is not public.
	 */
	public function &__get( string $name ) {
		if ( ! array_key_exists( $name, self::DEPRECATED_PROPERTIES ) ) {
			$this->check_property_access( $name );
			wp_trigger_error(
				__METHOD__,
				sprintf( 'Undefined property: %s::$%s', static::class, $name ),
				E_USER_WARNING
			);
			$value = null;
			return $value;
		}

		$this->deprecated_property_used( __METHOD__, $name );

		if ( ! array_key_exists( $name, $this->deprecated_property_values ) ) {
			$this->deprecated_property_values[ $name ] = self::DEPRECATED_PROPERTIES[ $name ];
		}
		return $this->deprecated_property_values[ $name ];
	}

	/**
	 * Sets a deprecated property.
	 *
	 * Other undefined properties are set as dynamic properties, as before.
	 *
	 * @since 7.2.0
	 *
	 * @see WP_Styles::DEPRECATED_PROPERTIES
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value.
	 *
	 * @throws Error If the property is not public.
	 */
	public function __set( string $name, $value ): void {
		if ( ! array_key_exists( $name, self::DEPRECATED_PROPERTIES ) ) {
			$this->check_property_access( $name );
			$this->$name = $value;
			return;
		}

		$this->deprecated_property_used( __METHOD__, $name );
		$this->deprecated_property_values[ $name ] = $value;
	}

	/**
	 * Checks whether a deprecated property is set.
	 *
	 * @since 7.2.0
	 *
	 * @see WP_Styles::DEPRECATED_PROPERTIES
	 *
	 * @param string $name Property name.
	 * @return bool Whether the property is set and not null.
	 */
	public function __isset( string $name ): bool {
		if ( ! array_key_exists( $name, self::DEPRECATED_PROPERTIES ) ) {
			return false;
		}

		$this->deprecated_property_used( __METHOD__, $name );

		if ( array_key_exists( $name, $this->deprecated_property_values ) ) {
			return isset( $this->deprecated_property_values[ $name ] );
		}
		return true;
	}

	/**
	 * Unsets a deprecated property, which restores its default value.
	 *
	 * @since 7.2.0
	 *
	 * @see WP_Styles::DEPRECATED_PROPERTIES
	 *
	 * @param string $name Property name.
	 *
	 * @throws Error If the property is not public.
	 */
	public function __unset( string $name ): void {
		if ( ! array_key_exists( $name, self::DEPRECATED_PROPERTIES ) ) {
			$this->check_property_access( $name );
			return;
		}

		$this->deprecated_property_used( __METHOD__, $name );
		unset( $this->deprecated_property_values[ $name ] );
	}

	/**
	 * Warns that a deprecated property was used.
	 *
	 * @since 7.2.0
	 *
	 * @param string $method Magic method through which the property was used.
	 * @param string $name   Property name.
	 */
	private function deprecated_property_used( string $method, string $name ): void {
		_doing_it_wrong(
			$method,
			sprintf(
				/* translators: %s: Property name. */
				__( 'The %s property is deprecated, as styles are no longer concatenated.' ),
				'<code>' . __CLASS__ . '::$' . $name . '</code>'
			),
			'7.2.0'
		);
	}

	/**
	 * Throws the error PHP would throw when a property which is not public is accessed.
	 *
	 * Without this check, the magic methods would expose the private and
	 * protected properties, since they run in the scope of the class.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name Property name.
	 *
	 * @throws Error If the property is not public.
	 */
	private function check_property_access( string $name ): void {
		if ( property_exists( $this, $name ) && ! ( new ReflectionProperty( $this, $name ) )->isPublic() ) {
			throw new Error( sprintf( 'Cannot access non-public property %s::$%s', static::class, $name ) );
		}
	}

	/**
	 * Processes a style dependency.
	 *
	 * @since 2.6.0
	 * @since 5.5.0 Added the `$group` parameter.
	 *
	 * @see WP_Dependencies::do_item()
	 *
	 * @param string    $handle The style's registered handle.
	 * @param int|false $group  Optional. Group level: level (int), no groups (false).
	 *                          Default false.
	 * @return bool True on success, false on failure.
	 */
	public function do_item( $handle, $group = false ) {
		if ( ! parent::do_item( $handle ) ) {
			return false;
		}

		$obj = $this->registered[ $handle ];
		if ( $obj->extra['conditional'] ?? false ) {

			return false;
		}

		$src          = $obj->src;
		$inline_style = $this->print_inline_style( $handle, false );

		if ( $inline_style ) {
			$processor = new WP_HTML_Tag_Processor( '<style></style>' );
			$processor->next_tag();
			$processor->set_attribute( 'id', "{$handle}-inline-css" );
			$processor->set_modifiable_text( "\n{$inline_style}\n" );
			$inline_style_tag = "{$processor->get_updated_html()}\n";
		} else {
			$inline_style_tag = '';
		}

		$media = $obj->args ?? 'all';

		// A single item may alias a set of items, by having dependencies, but no source.
		if ( ! $src ) {
			if ( $inline_style_tag ) {
				echo $inline_style_tag;
			}

			return true;
		}

		$href = $this->_css_href( $src, $obj->ver, $handle );
		if ( ! $href ) {
			return true;
		}

		$rel   = isset( $obj->extra['alt'] ) && $obj->extra['alt'] ? 'alternate stylesheet' : 'stylesheet';
		$title = $obj->extra['title'] ?? '';

		$tag = sprintf(
			"<link rel='%s' id='%s-css'%s href='%s' media='%s' />\n",
			$rel,
			esc_attr( $handle ),
			$title ? sprintf( " title='%s'", esc_attr( $title ) ) : '',
			$href,
			esc_attr( $media )
		);

		/**
		 * Filters the HTML link tag of an enqueued style.
		 *
		 * @since 2.6.0
		 * @since 4.3.0 Introduced the `$href` parameter.
		 * @since 4.5.0 Introduced the `$media` parameter.
		 *
		 * @param string $tag    The link tag for the enqueued style.
		 * @param string $handle The style's registered handle.
		 * @param string $href   The stylesheet's source URL.
		 * @param string $media  The stylesheet's media attribute.
		 */
		$tag = apply_filters( 'style_loader_tag', $tag, $handle, $href, $media );

		$rtl_src = $this->get_rtl_src( $handle );

		if ( null !== $rtl_src ) {
			$rtl_href = esc_url( $rtl_src );
			$rtl_tag  = sprintf(
				"<link rel='%s' id='%s-rtl-css'%s href='%s' media='%s' />\n",
				$rel,
				esc_attr( $handle ),
				$title ? sprintf( " title='%s'", esc_attr( $title ) ) : '',
				$rtl_href,
				esc_attr( $media )
			);

			/** This filter is documented in wp-includes/class-wp-styles.php */
			$rtl_tag = apply_filters( 'style_loader_tag', $rtl_tag, $handle, $rtl_href, $media );

			if ( 'replace' === $obj->extra['rtl'] ) {
				$tag = $rtl_tag;
			} else {
				$tag .= $rtl_tag;
			}
		}

		echo $tag;
		echo $inline_style_tag;

		return true;
	}

	/**
	 * Gets the URL of the right-to-left stylesheet for a registered style.
	 *
	 * On a right-to-left locale, a style registered with `rtl` data is served by a separate
	 * stylesheet, which either replaces its left-to-right one (when the data is `'replace'`)
	 * or loads alongside it.
	 *
	 * Like {@see WP_Styles::get_src()}, the URL is neither sanitized nor escaped, so a caller can
	 * pass it through esc_url() to print it, or esc_url_raw() otherwise, once.
	 *
	 * @since 7.2.0
	 *
	 * @param string $handle The style's registered handle.
	 * @return string|null URL of the right-to-left stylesheet, after the {@see 'style_loader_src'}
	 *                     filter. Null when the text direction is not right-to-left, or the style is
	 *                     not registered, has no source of its own, or has no right-to-left variant.
	 */
	public function get_rtl_src( string $handle ): ?string {
		if ( 'rtl' !== $this->text_direction || ! isset( $this->registered[ $handle ] ) ) {
			return null;
		}

		$obj = $this->registered[ $handle ];

		if ( ! $obj->src || ! isset( $obj->extra['rtl'] ) || ! $obj->extra['rtl'] ) {
			return null;
		}

		/*
		 * The version and the handle's added arguments are those of the left-to-right stylesheet,
		 * appended the same way, while the filter is passed the handle with `-rtl` appended, as it
		 * always has been.
		 */
		if ( is_bool( $obj->extra['rtl'] ) || 'replace' === $obj->extra['rtl'] ) {
			$suffix = isset( $obj->extra['suffix'] ) && is_string( $obj->extra['suffix'] ) ? $obj->extra['suffix'] : '';

			return str_replace( "{$suffix}.css", "-rtl{$suffix}.css", $this->build_src( $obj->src, $obj->ver, $handle, "$handle-rtl" ) );
		}

		// Any other value is the URL of the right-to-left stylesheet itself.
		if ( ! is_string( $obj->extra['rtl'] ) ) {
			return null;
		}

		return $this->build_src( $obj->extra['rtl'], $obj->ver, $handle, "$handle-rtl" );
	}

	/**
	 * Adds extra CSS styles to a registered stylesheet.
	 *
	 * @since 3.3.0
	 *
	 * @param string $handle The style's registered handle.
	 * @param string $code   String containing the CSS styles to be added.
	 * @return bool True on success, false on failure.
	 */
	public function add_inline_style( $handle, $code ) {
		if ( ! $code ) {
			return false;
		}

		$after = $this->get_data( $handle, 'after' );
		if ( ! $after ) {
			$after = array();
		}

		$after[] = $code;

		return $this->add_data( $handle, 'after', $after );
	}

	/**
	 * Prints extra CSS styles of a registered stylesheet.
	 *
	 * @since 3.3.0
	 *
	 * @param string $handle  The style's registered handle.
	 * @param bool   $display Optional. Whether to print the inline style
	 *                        instead of just returning it. Default true.
	 * @return string|bool False if no data exists, inline styles if `$display` is false,
	 *                     true otherwise.
	 * @phpstan-return ( $display is true ? bool : string|false )
	 */
	public function print_inline_style( $handle, $display = true ) {
		$output = $this->get_data( $handle, 'after' );

		if ( empty( $output ) || ! is_array( $output ) ) {
			return false;
		}

		// Obtain the original `src` for a stylesheet possibly inlined by wp_maybe_inline_styles().
		$inlined_src = $this->get_data( $handle, 'inlined_src' );

		// If there's only one `after` inline style, and that inline style had been inlined, then use the $inlined_src
		// as the sourceURL. Otherwise, if there is more than one inline `after` style associated with the handle,
		// then resort to using the handle to construct the sourceURL since there isn't a single source.
		if ( count( $output ) === 1 && is_string( $inlined_src ) && strlen( $inlined_src ) > 0 ) {
			$source_url = esc_url_raw( $inlined_src );
		} else {
			$source_url = rawurlencode( "{$handle}-inline-css" );
		}

		$output[] = sprintf(
			'/*# sourceURL=%s */',
			$source_url
		);

		$output = implode( "\n", $output );

		if ( ! $display ) {
			return $output;
		}

		$processor = new WP_HTML_Tag_Processor( '<style></style>' );
		$processor->next_tag();
		$processor->set_attribute( 'id', "{$handle}-inline-css" );
		$processor->set_modifiable_text( "\n{$output}\n" );
		echo "{$processor->get_updated_html()}\n";

		return true;
	}

	/**
	 * Overrides the add_data method from WP_Dependencies, to allow unsetting dependencies for conditional styles.
	 *
	 * @since 6.9.0
	 *
	 * @param string $handle Name of the item. Should be unique.
	 * @param string $key    The data key.
	 * @param mixed  $value  The data value.
	 * @return bool True on success, false on failure.
	 */
	public function add_data( $handle, $key, $value ) {
		if ( ! isset( $this->registered[ $handle ] ) ) {
			return false;
		}

		if ( 'conditional' === $key ) {
			$this->registered[ $handle ]->deps = array();
		}

		return parent::add_data( $handle, $key, $value );
	}

	/**
	 * Determines style dependencies.
	 *
	 * @since 2.6.0
	 *
	 * @see WP_Dependencies::all_deps()
	 *
	 * @param string|string[] $handles   Item handle (string) or item handles (array of strings).
	 * @param bool            $recursion Optional. Internal flag that function is calling itself.
	 *                                   Default false.
	 * @param int|false       $group     Optional. Group level: level (int), no groups (false).
	 *                                   Default false.
	 * @return bool True on success, false on failure.
	 */
	public function all_deps( $handles, $recursion = false, $group = false ) {
		$result = parent::all_deps( $handles, $recursion, $group );
		if ( ! $recursion ) {
			/**
			 * Filters the array of enqueued styles before processing for output.
			 *
			 * @since 2.6.0
			 *
			 * @param string[] $to_do The list of enqueued style handles about to be processed.
			 */
			$this->to_do = apply_filters( 'print_styles_array', $this->to_do );
		}
		return $result;
	}

	/**
	 * Generates an enqueued style's fully-qualified URL.
	 *
	 * @since 2.6.0
	 *
	 * @param string            $src    The source of the enqueued style.
	 * @param string|false|null $ver    The version of the enqueued style.
	 * @param string            $handle The style's registered handle.
	 * @return string Style's fully-qualified URL, escaped for use in an HTML attribute.
	 */
	public function _css_href( $src, $ver, $handle ) {
		return esc_url( $this->build_src( $src, $ver, (string) $handle ) );
	}

	/**
	 * Gets the URL a registered style is loaded from.
	 *
	 * This is the URL printed in the stylesheet's `href` attribute, including the version query
	 * argument and any arguments added to the handle, after the {@see 'style_loader_src'} filter.
	 * Unlike {@see WP_Styles::_css_href()}, it is neither sanitized nor escaped, so a caller can
	 * pass it through esc_url() to print it, or esc_url_raw() otherwise, once. This is the same as
	 * {@see WP_Script_Modules::get_src()}.
	 *
	 * @since 7.2.0
	 *
	 * @param string $handle The style's registered handle.
	 * @return string Style URL, or an empty string when the style is not registered, has no
	 *                source of its own because it only aliases other styles, or was filtered away.
	 */
	public function get_src( string $handle ): string {
		if ( ! isset( $this->registered[ $handle ] ) ) {
			return '';
		}

		$obj = $this->registered[ $handle ];

		/*
		 * An alias, with no source of its own, has no URL. A source of `true`, like that of `colors`,
		 * gets past this, since its URL comes from the 'style_loader_src' filter in build_src(), as
		 * it does when WP_Styles::do_item() prints the stylesheet.
		 */
		if ( ! $obj->src ) {
			return '';
		}

		return $this->build_src( $obj->src, $obj->ver, $handle );
	}

	/**
	 * Builds a style's fully-qualified URL and passes it through the 'style_loader_src' filter.
	 *
	 * The result is not escaped, so callers can escape it for where it is used.
	 *
	 * @since 7.2.0
	 *
	 * @param string|true       $src    The source of the style, or true for one whose URL comes
	 *                                  from the {@see 'style_loader_src'} filter.
	 * @param string|false|null $ver           The version of the style.
	 * @param string            $handle        The style's registered handle, whose added arguments
	 *                                         are appended.
	 * @param string|null       $filter_handle Optional. The handle passed to the
	 *                                         {@see 'style_loader_src'} filter, such as
	 *                                         `{$handle}-rtl` for a right-to-left stylesheet.
	 *                                         Default `$handle`.
	 * @return string The filtered URL, or an empty string when the filter returns anything other
	 *                than a string.
	 */
	private function build_src( $src, $ver, string $handle, ?string $filter_handle = null ): string {
		if ( ! is_bool( $src ) && ! preg_match( '|^(https?:)?//|', $src ) && ! ( $this->content_url && str_starts_with( $src, $this->content_url ) ) ) {
			$src = $this->base_url . $src;
		}

		$ver_to_add = '';
		if ( empty( $ver ) && null !== $ver && is_string( $this->default_version ) ) {
			$ver_to_add = $this->default_version;
		} elseif ( is_scalar( $ver ) ) {
			$ver_to_add = (string) $ver;
		}

		$added_args = (string) ( $this->args[ $handle ] ?? '' );

		if ( '' !== $ver_to_add || '' !== $added_args ) {
			$fragment = strstr( $src, '#' );
			if ( false !== $fragment ) {
				$src = substr( $src, 0, -strlen( $fragment ) );
			}

			if ( '' !== $ver_to_add ) {
				$src .= ( str_contains( $src, '?' ) ? '&' : '?' ) . 'ver=' . rawurlencode( $ver_to_add );
			}
			if ( '' !== $added_args ) {
				$src .= ( str_contains( $src, '?' ) ? '&' : '?' ) . $added_args;
			}

			if ( false !== $fragment ) {
				$src .= $fragment;
			}
		}

		/**
		 * Filters an enqueued style's fully-qualified URL.
		 *
		 * @since 2.6.0
		 *
		 * @param string $src    The source URL of the enqueued style.
		 * @param string $handle The style's registered handle.
		 */
		$src = apply_filters( 'style_loader_src', $src, $filter_handle ?? $handle );

		return is_string( $src ) ? $src : '';
	}

	/**
	 * Whether a handle's source is in a default directory.
	 *
	 * @since 2.8.0
	 * @deprecated 7.2.0 Styles are no longer concatenated.
	 *
	 * @param string $src The source of the enqueued style.
	 * @return bool True if found, false if not.
	 */
	public function in_default_dir( $src ) {
		_deprecated_function( __METHOD__, '7.2.0' );

		$default_dirs = array_key_exists( 'default_dirs', $this->deprecated_property_values )
			? $this->deprecated_property_values['default_dirs']
			: self::DEPRECATED_PROPERTIES['default_dirs'];

		if ( ! $default_dirs ) {
			return true;
		}

		return array_any( (array) $default_dirs, fn( $test ) => str_starts_with( $src, $test ) );
	}

	/**
	 * Processes items and dependencies for the footer group.
	 *
	 * HTML 5 allows styles in the body, grab late enqueued items and output them in the footer.
	 *
	 * @since 3.3.0
	 *
	 * @see WP_Dependencies::do_items()
	 *
	 * @return string[] Handles of items that have been processed.
	 */
	public function do_footer_items() {
		$this->do_items( false, 1 );
		return $this->done;
	}

	/**
	 * Resets class properties.
	 *
	 * @since 3.3.0
	 */
	public function reset() {
		foreach ( array( 'do_concat', 'concat', 'concat_version', 'print_html' ) as $name ) {
			unset( $this->deprecated_property_values[ $name ] );
		}
	}

	/**
	 * Gets a style-specific dependency warning message.
	 *
	 * @since 6.9.1
	 *
	 * @param string   $handle                     Style handle with missing dependencies.
	 * @param string[] $missing_dependency_handles Missing dependency handles.
	 * @return string Formatted, localized warning message.
	 */
	protected function get_dependency_warning_message( $handle, $missing_dependency_handles ) {
		return sprintf(
			/* translators: 1: Style handle, 2: List of missing dependency handles. */
			__( 'The style with the handle "%1$s" was enqueued with dependencies that are not registered: %2$s.' ),
			$handle,
			implode( wp_get_list_item_separator(), $missing_dependency_handles )
		);
	}
}
