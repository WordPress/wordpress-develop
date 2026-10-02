<?php
/**
 * Interactivity API: Functions and hooks
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.5.0
 */

/**
 * Retrieves the main WP_Interactivity_API instance.
 *
 * It provides access to the WP_Interactivity_API instance, creating one if it
 * doesn't exist yet.
 *
 * @since 6.5.0
 *
 * @global WP_Interactivity_API $wp_interactivity
 *
 * @return WP_Interactivity_API The main WP_Interactivity_API instance.
 */
function wp_interactivity(): WP_Interactivity_API {
	global $wp_interactivity;
	if ( ! ( $wp_interactivity instanceof WP_Interactivity_API ) ) {
		$wp_interactivity = new WP_Interactivity_API();
	}
	return $wp_interactivity;
}

/**
 * Processes the interactivity directives contained within the HTML content
 * and updates the markup accordingly.
 *
 * To render trusted HTML with a `data-wp-html` directive, see
 * wp_interactivity_as_dangerous_html() for how to supply it safely and for what
 * the directive does when it declines to render.
 *
 * @since 6.5.0
 *
 * @see wp_interactivity_as_dangerous_html()
 *
 * @param string $html The HTML content to process.
 * @return string The processed HTML content. It returns the original content when the HTML contains unbalanced tags.
 */
function wp_interactivity_process_directives( string $html ): string {
	return wp_interactivity()->process_directives( $html );
}

/**
 * Gets and/or sets the initial state of an Interactivity API store for a
 * given namespace.
 *
 * If state for that store namespace already exists, it merges the new
 * provided state with the existing one.
 *
 * The namespace can be omitted inside derived state getters, using the
 * namespace where the getter is defined.
 *
 * @since 6.5.0
 * @since 6.6.0 The namespace can be omitted when called inside derived state getters.
 *
 * @param string|null $store_namespace The unique store namespace identifier.
 * @param array       $state           Optional. The array that will be merged with the existing state for the specified
 *                                     store namespace.
 * @return array The state for the specified store namespace. This will be the updated state if a $state argument was
 *               provided.
 */
function wp_interactivity_state( ?string $store_namespace = null, array $state = array() ): array {
	return wp_interactivity()->state( $store_namespace, $state );
}

/**
 * Gets and/or sets the configuration of the Interactivity API for a given
 * store namespace.
 *
 * If configuration for that store namespace exists, it merges the new
 * provided configuration with the existing one.
 *
 * @since 6.5.0
 *
 * @param string $store_namespace The unique store namespace identifier.
 * @param array  $config          Optional. The array that will be merged with the existing configuration for the
 *                                specified store namespace.
 * @return array The configuration for the specified store namespace. This will be the updated configuration if a
 *               $config argument was provided.
 */
function wp_interactivity_config( string $store_namespace, array $config = array() ): array {
	return wp_interactivity()->config( $store_namespace, $config );
}

/**
 * Generates a `data-wp-context` directive attribute by encoding a context
 * array.
 *
 * This helper function simplifies the creation of `data-wp-context` directives
 * by providing a way to pass an array of data, which encodes into a JSON string
 * safe for direct use as a HTML attribute value.
 *
 * Example:
 *
 *     <div <?php echo wp_interactivity_data_wp_context( array( 'isOpen' => true, 'count' => 0 ) ); ?>>
 *
 * @since 6.5.0
 *
 * @param array  $context         The array of context data to encode.
 * @param string $store_namespace Optional. The unique store namespace identifier.
 * @return string A complete `data-wp-context` directive with a JSON encoded value representing the context array and
 *                the store namespace if specified.
 */
function wp_interactivity_data_wp_context( array $context, string $store_namespace = '' ): string {
	return 'data-wp-context=\'' .
		( $store_namespace ? $store_namespace . '::' : '' ) .
		( empty( $context ) ? '{}' : wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) ) .
		'\'';
}

/**
 * Gets the current Interactivity API context for a given namespace.
 *
 * The function should be used only during directive processing. If the
 * `$store_namespace` parameter is omitted, it uses the current namespace value
 * on the internal namespace stack.
 *
 * It returns an empty array when the specified namespace is not defined.
 *
 * @since 6.6.0
 *
 * @param string|null $store_namespace Optional. The unique store namespace identifier.
 * @return array The context for the specified store namespace.
 */
function wp_interactivity_get_context( ?string $store_namespace = null ): array {
	return wp_interactivity()->get_context( $store_namespace );
}

/**
 * Returns an array representation of the current element being processed.
 *
 * The function should be used only during directive processing.
 *
 * @since 6.7.0
 *
 * @return array{attributes: array<string, string|bool>}|null Current element.
 */
function wp_interactivity_get_element(): ?array {
	return wp_interactivity()->get_element();
}

/**
 * Creates an opaque token for verbatim server-rendered HTML.
 *
 * During server directive processing, a `data-wp-html` directive whose
 * reference resolves to the returned token replaces the content of its element
 * with the registered HTML, unchanged. This function is part of Core; no plugin
 * is needed to use it.
 *
 * Token identity:
 *
 * The token is the only value `data-wp-html` renders, and it is recognized by
 * its original object identity within the current request. The token holds no
 * HTML and has no declared public methods, and writing properties to it does
 * not replace the HTML registered for it. A string, even a sanitized one, is
 * not a token. Neither is a clone, an object created with `new` or by
 * unserializing, or a value decoded from context or JSON: none of them acquire
 * trust. A token stored in state, or returned by a derived state getter, can be
 * rendered any number of times.
 *
 * Safety:
 *
 * Neither this function nor the rendering of `data-wp-html` sanitizes or
 * validates the HTML. Passing untrusted input creates a cross-site scripting
 * (XSS) vulnerability, and passing malformed HTML can break the page. HTML that
 * contains tags such as `body` can change the page's own root elements.
 * Sanitize the HTML where the string is produced, for example with
 * wp_kses_post().
 *
 * Never pass a value read from context to this function, because any ancestor
 * block can override context. Use context only to select, by ID, which value
 * held in state to show, and take the HTML itself from state. This is guidance
 * for the caller: nothing checks at runtime where the string came from.
 *
 * Rendering and declines:
 *
 * The reference is evaluated with the namespace and context of the element that
 * carries `data-wp-html`. When it resolves to a token, only the content of that
 * element is replaced. The element and its attributes, including
 * `data-wp-html`, are kept, its other directives are processed as usual, and
 * processing continues with the elements that follow. Directives inside the
 * inserted HTML are not processed, and the inserted HTML is not checked for
 * balanced tags. Inside a `template` with `data-wp-each`, the directive renders
 * once per item, with that item in context, and the rendered items keep their
 * usual `data-wp-each-child` markings.
 *
 * When the directive declines to render, the element keeps its content as a
 * fallback and is processed as it would be without `data-wp-html`. Only a
 * non-empty `data-wp-html` entry without a suffix or a unique ID acts; any other
 * `data-wp-html` entry is ignored without a notice. A `null` value, such as for
 * a reference that is not defined, also keeps the fallback without a notice,
 * unless the element is ineligible or combined as described below. These
 * declines raise a `_doing_it_wrong()` notice, each for a distinct reason:
 *
 * - The value is not a token created by this function.
 * - The element cannot hold content: a void element, or `script`, `style`,
 *   `textarea`, `title`, `iframe`, `noembed`, `noframes` or `xmp`.
 * - The element also has any `data-wp-text` entry, or is a `template` with any
 *   `data-wp-each` entry. This takes precedence over the other reasons and
 *   applies even if the `data-wp-html` entry is empty or has a suffix or a
 *   unique ID. `data-wp-text` and `data-wp-each` then run as usual.
 *
 * A notice names the reason, the element's tag and, where available, the
 * directive's reference. It never includes the evaluated data. Errors raised
 * while evaluating the reference, such as in a derived state getter, have their
 * own notices. Directives inside `svg` and `math` elements are still not
 * processed.
 *
 * The client boundary:
 *
 * The directive does not write tokens or HTML to the data the Interactivity API
 * prints, or to context attributes. Strings held in state are still printed,
 * so HTML kept in state, as in the example below, is visible to the client. A
 * token placed in state serializes as an empty object: it carries neither its
 * registered HTML nor its trust. Evaluating a derived state getter can record
 * the path of that getter in the printed data, even if the directive then
 * declines to render.
 *
 * The server never gives the client trust: the client has to create its own
 * token, with `asDangerousHTML()` from `@wordpress/interactivity`, in a client
 * that supports `data-wp-html`. The server rendering and that client support are
 * released in coordination. On such a client, the server-rendered content is
 * expected to stay in place until the client value resolves to a client token,
 * and each item of a `data-wp-each` list is expected to be reused only when the
 * client list has the same items in the same number and order. Items that the
 * client mounts afresh start from the template's content. These behaviors
 * belong to the client and are not guaranteed by the server processing.
 *
 * Example:
 *
 *     wp_interactivity_state(
 *         'myplugin',
 *         array(
 *             // The HTML is sanitized where it is produced, and kept in state by ID.
 *             'htmlById'    => array(
 *                 'intro' => wp_kses_post( '<p>Welcome to <em>my</em> site.</p>' ),
 *                 'outro' => wp_kses_post( '<p>Thanks for reading.</p>' ),
 *             ),
 *             'sectionIds'  => array( 'intro', 'outro' ),
 *             // The context only selects the ID. The token is created here, from state.
 *             'sectionHtml' => static function () {
 *                 $id    = wp_interactivity_get_context()['sectionId'] ?? null;
 *                 $state = wp_interactivity_state();
 *                 $html  = is_string( $id ) ? ( $state['htmlById'][ $id ] ?? null ) : null;
 *                 return is_string( $html ) ? wp_interactivity_as_dangerous_html( $html ) : null;
 *             },
 *         )
 *     );
 *
 *     <div data-wp-interactive="myplugin">
 *         <template data-wp-each--section-id="state.sectionIds">
 *             <section data-wp-html="state.sectionHtml">Fallback content</section>
 *         </template>
 *     </div>
 *
 * Each rendered `section` shows the HTML for its ID. An ID that has no HTML
 * returns `null`, so its `section` keeps "Fallback content" without a notice.
 *
 * @since 7.2.0
 *
 * @see WP_Interactivity_Dangerous_HTML
 * @see wp_interactivity_state()
 * @see wp_interactivity_get_context()
 * @see wp_interactivity_process_directives()
 * @see wp_kses_post()
 *
 * @param string $html HTML to register.
 * @return WP_Interactivity_Dangerous_HTML The registered token.
 */
function wp_interactivity_as_dangerous_html( string $html ): WP_Interactivity_Dangerous_HTML {
	static $register = null;
	if ( null === $register ) {
		$register = Closure::bind(
			static function ( WP_Interactivity_Dangerous_HTML $token, string $html ): void {
				WP_Interactivity_API::$dangerous_html[ spl_object_id( $token ) ] = array( WeakReference::create( $token ), $html );
			},
			null,
			WP_Interactivity_API::class
		);
	}
	$token = new WP_Interactivity_Dangerous_HTML();
	$register( $token, $html );
	return $token;
}
