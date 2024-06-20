<?php
/**
 * REST API: WP_REST_URL_Details_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 */

class WP_HTML_Head_Scanner extends WP_HTML_Tag_Processor {
	public function get_title_content_and_advance() {
		if ( 'TITLE' !== $this->get_tag() || $this->is_tag_closer() ) {
			return null;
		}

		$this->set_bookmark( 'title-opener' );
		$from = $this->bookmarks['title-opener']->end + 1;

		if ( ! $this->next_tag( array( 'tag_closers' => 'visit' ) ) || 'TITLE' !== $this->get_tag() ) {
			// When missing a closing TITLE tag, the title becomes the full document after the opener.
			return html_entity_decode(
				substr( $this->html, $from ),
				ENT_QUOTES,
				get_bloginfo( 'charset' )
			);
		}

		$this->set_bookmark( 'title-closer' );
		$to   = $this->bookmarks['title-closer']->start;

		$this->release_bookmark( 'title-opener' );
		$this->release_bookmark( 'title-closer' );

		return html_entity_decode(
			substr( $this->html, $from, $to - $from ),
			ENT_QUOTES,
			get_bloginfo( 'charset' )
		);
	}
}

/**
 * Controller which provides REST endpoint for retrieving information
 * from a remote site's HTML response.
 *
 * @since 5.9.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_URL_Details_Controller extends WP_REST_Controller {

	/**
	 * Constructs the controller.
	 *
	 * @since 5.9.0
	 */
	public function __construct() {
		$this->namespace = 'wp-block-editor/v1';
		$this->rest_base = 'url-details';
	}

	/**
	 * Registers the necessary REST API routes.
	 *
	 * @since 5.9.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'parse_url_details' ),
					'args'                => array(
						'url' => array(
							'required'          => true,
							'description'       => __( 'The URL to process.' ),
							'validate_callback' => 'wp_http_validate_url',
							'sanitize_callback' => 'sanitize_url',
							'type'              => 'string',
							'format'            => 'uri',
						),
					),
					'permission_callback' => array( $this, 'permissions_check' ),
					'schema'              => array( $this, 'get_public_item_schema' ),
				),
			)
		);
	}

	/**
	 * Retrieves the item's schema, conforming to JSON Schema.
	 *
	 * @since 5.9.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'url-details',
			'type'       => 'object',
			'properties' => array(
				'title'       => array(
					'description' => sprintf(
						/* translators: %s: HTML title tag. */
						__( 'The contents of the %s element from the URL.' ),
						'<title>'
					),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'icon'        => array(
					'description' => sprintf(
						/* translators: %s: HTML link tag. */
						__( 'The favicon image link of the %s element from the URL.' ),
						'<link rel="icon">'
					),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'description' => array(
					'description' => sprintf(
						/* translators: %s: HTML meta tag. */
						__( 'The content of the %s element from the URL.' ),
						'<meta name="description">'
					),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'image'       => array(
					'description' => sprintf(
						/* translators: 1: HTML meta tag, 2: HTML meta tag. */
						__( 'The Open Graph image link of the %1$s or %2$s element from the URL.' ),
						'<meta property="og:image">',
						'<meta property="og:image:url">'
					),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Fetches relevant information from the relevant HEAD elements in the response HTML.
	 *
	 * @since 6.5.0
	 *
	 * @param string $response_html HTML content in the response potentially containing meta information.
	 * @param string $url           Used to build absolute URLs from relative URLs in the response data.
	 *
	 * @return array {
	 *     Attributes extracted from the response HTML, each of which will be an empty string if not found in the HTML.
	 *
	 *     @type string $title       HTML page title.
	 *     @type string $icon        URL to icon image specified by LINK element.
	 *     @type string $description Open-graph description from META element.
	 *     @type string $image       URL for open-graph image from META element.
	 * }
	 */
	public function find_head_information( $response_html, $url ) {
		$title       = null;
		$icon        = null;
		$description = null;
		$image       = null;

		$processor = new WP_HTML_Head_Scanner( $response_html );

		while ( $processor->next_tag() && ! isset( $title, $icon, $description, $image ) ) {
			$tag_name = $processor->get_tag();

			if ( 'TITLE' === $tag_name && ! isset( $title ) ) {
				$title_value = $processor->get_title_content_and_advance();
				if ( is_string( $title_value ) ) {
					$title = $this->prepare_metadata_for_output( trim( $title_value ) );
				}
				continue;
			}

			if ( 'LINK' === $tag_name && ! isset( $icon ) ) {
				$rel = $processor->get_attribute( 'rel' );
				if ( ! is_string( $rel ) ) {
					continue;
				}

				$rel = trim( $rel );
				if ( 'icon' !== $rel && 'icon shortcut' !== $rel && 'shortcut icon' !== $rel ) {
					continue;
				}

				$href = $processor->get_attribute( 'href' );
				if ( is_string( $href ) && ! empty( $href ) ) {
					$icon = $this->get_icon( trim( $href ), $url );
				}
				continue;
			}

			if ( 'META' === $tag_name && ! isset( $description ) ) {
				$name = $processor->get_attribute( 'name' );
				if ( is_string( $name ) ) {
					$name = trim( $name );
				}

				if ( 'og:description' === $name || 'description' === $name ) {
					$content = $processor->get_attribute( 'content' );
					if ( is_string( $content ) && ! empty( $content ) ) {
						$description = $this->prepare_metadata_for_output( trim( $content ) );
						continue;
					}
				}
			}

			if ( 'META' === $tag_name && ! isset( $image ) ) {
				$property = $processor->get_attribute( 'property' );
				if ( is_string( $property ) ) {
					$property = trim( $property );
				}

				if ( 'og:image' === $property || 'og:image:url' === $property ) {
					$content = $processor->get_attribute( 'content' );
					if ( is_string( $content ) && ! empty( $content ) ) {
						$image = $this->get_image( trim( $content ), $url );
					}
				}
			}

			/*
			 * Stop looking for meta information once reaching the BODY.
			 * It's possible that more LINK, META, or TITLE elements may
			 * appear later on, and they would still apply in a browser,
			 * but for the scraping here it's a pragmatic choice to stop
			 * processing and give up. Those kinds of valid-yet-invalid
			 * HTML constructions aren't supported here.
			 *
			 * It would be valid here to continue past this tag, but that
			 * would likely incur an average performance penalty and only
			 * recover a set of rare cases where the markup is malformed
			 * in this specific manner.
			 */
			if ( 'BODY' === $tag_name ) {
				break;
			}
		}

		return array(
			'title'       => $title ?? '',
			'icon'        => $icon ?? '',
			'description' => $description ?? '',
			'image'       => $image ?? '',
		);
	}

	/**
	 * Retrieves the contents of the title tag from the HTML response.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error The parsed details as a response object. WP_Error if there are errors.
	 */
	public function parse_url_details( $request ) {
		$url = untrailingslashit( $request['url'] );

		if ( empty( $url ) ) {
			return new WP_Error( 'rest_invalid_url', __( 'Invalid URL' ), array( 'status' => 404 ) );
		}

		// Transient per URL.
		$cache_key = $this->build_cache_key_for_url( $url );

		// Attempt to retrieve cached response.
		$cached_response = $this->get_cache( $cache_key );

		if ( ! empty( $cached_response ) ) {
			$remote_url_response = $cached_response;
		} else {
			$remote_url_response = $this->get_remote_url( $url );

			// Exit if we don't have a valid body or it's empty.
			if ( is_wp_error( $remote_url_response ) || empty( $remote_url_response ) ) {
				return $remote_url_response;
			}

			// Cache the valid response.
			$this->set_cache( $cache_key, $remote_url_response );
		}

		$data = $this->add_additional_fields_to_object(
			$this->find_head_information( $remote_url_response, $url ),
			$request
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters the URL data for the response.
		 *
		 * @since 5.9.0
		 *
		 * @param WP_REST_Response $response            The response object.
		 * @param string           $url                 The requested URL.
		 * @param WP_REST_Request  $request             Request object.
		 * @param string           $remote_url_response HTTP response body from the remote URL.
		 */
		return apply_filters( 'rest_prepare_url_details', $response, $url, $request, $remote_url_response );
	}

	/**
	 * Checks whether a given request has permission to read remote URLs.
	 *
	 * @since 5.9.0
	 *
	 * @return WP_Error|bool True if the request has permission, else WP_Error.
	 */
	public function permissions_check() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( current_user_can( $post_type->cap->edit_posts ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_cannot_view_url_details',
			__( 'Sorry, you are not allowed to process remote URLs.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Retrieves the document title from a remote URL.
	 *
	 * @since 5.9.0
	 *
	 * @param string $url The website URL whose HTML to access.
	 * @return string|WP_Error The HTTP response from the remote URL on success.
	 *                         WP_Error if no response or no content.
	 */
	private function get_remote_url( $url ) {

		/*
		 * Provide a modified UA string to workaround web properties which block WordPress "Pingbacks".
		 * Why? The UA string used for pingback requests contains `WordPress/` which is very similar
		 * to that used as the default UA string by the WP HTTP API. Therefore requests from this
		 * REST endpoint are being unintentionally blocked as they are misidentified as pingback requests.
		 * By slightly modifying the UA string, but still retaining the "WordPress" identification (via "WP")
		 * we are able to work around this issue.
		 * Example UA string: `WP-URLDetails/5.9-alpha-51389 (+http://localhost:8888)`.
		*/
		$modified_user_agent = 'WP-URLDetails/' . get_bloginfo( 'version' ) . ' (+' . get_bloginfo( 'url' ) . ')';

		$args = array(
			'limit_response_size' => 150 * KB_IN_BYTES,
			'user-agent'          => $modified_user_agent,
		);

		/**
		 * Filters the HTTP request args for URL data retrieval.
		 *
		 * Can be used to adjust response size limit and other WP_Http::request() args.
		 *
		 * @since 5.9.0
		 *
		 * @param array  $args Arguments used for the HTTP request.
		 * @param string $url  The attempted URL.
		 */
		$args = apply_filters( 'rest_url_details_http_request_args', $args, $url );

		$response = wp_safe_remote_get( $url, $args );

		if ( WP_Http::OK !== wp_remote_retrieve_response_code( $response ) ) {
			// Not saving the error response to cache since the error might be temporary.
			return new WP_Error(
				'no_response',
				__( 'URL not found. Response returned a non-200 status code for this URL.' ),
				array( 'status' => WP_Http::NOT_FOUND )
			);
		}

		$remote_body = wp_remote_retrieve_body( $response );

		if ( empty( $remote_body ) ) {
			return new WP_Error(
				'no_content',
				__( 'Unable to retrieve body from response at this URL.' ),
				array( 'status' => WP_Http::NOT_FOUND )
			);
		}

		return $remote_body;
	}

	/**
	 * Parses the site icon from the provided HTML.
	 *
	 * @since 5.9.0
	 * @since 6.5.0 Expects `href` value as input instead of LINK tag HTML.
	 *
	 * @param string $icon_href The value of the `href` attribute for the icon LINK element.
	 * @param string $url  The target website URL.
	 * @return string The icon URI on success. Empty string if not found.
	 */
	private function get_icon( $icon_href, $url ) {
		// If the icon is a data URL, return it.
		$parsed_icon = wp_parse_url( $icon_href );
		if ( isset( $parsed_icon['scheme'] ) && 'data' === $parsed_icon['scheme'] ) {
			return $icon_href;
		}

		// Attempt to convert relative URLs to absolute.
		if ( ! is_string( $url ) || '' === $url ) {
			return $icon_href;
		}
		$parsed_url = wp_parse_url( $url );
		if ( isset( $parsed_url['scheme'] ) && isset( $parsed_url['host'] ) ) {
			$root_url  = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/';
			$icon_href = WP_Http::make_absolute_url( $icon_href, $root_url );
		}

		return $icon_href;
	}

	/**
	 * Parses the Open Graph (OG) Image from the provided HTML.
	 *
	 * See: https://ogp.me/.
	 *
	 * @since 5.9.0
	 * @since 6.5.0 Expects image URL input as value of `content` attribute instead of META tag HTML.
	 *
	 * @param string $image_url Value of the `content` attribute from the image META tag, supposedly containing an image URL.
	 * @param string $url       The target website URL.
	 * @return string The OG image on success. Empty string if not found.
	 */
	private function get_image( $image_url, $url ) {
		// Bail out if image not found.
		if ( '' === $image_url ) {
			return '';
		}

		// Attempt to convert relative URLs to absolute.
		$parsed_url = wp_parse_url( $url );
		if ( isset( $parsed_url['scheme'] ) && isset( $parsed_url['host'] ) ) {
			$root_url  = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/';
			$image_url = WP_Http::make_absolute_url( $image_url, $root_url );
		}

		return $image_url;
	}

	/**
	 * Prepares the metadata by:
	 *    - stripping all HTML tags and tag entities.
	 *    - converting non-tag entities into characters.
	 *
	 * @since 5.9.0
	 *
	 * @param string $metadata The metadata content to prepare.
	 * @return string The prepared metadata.
	 */
	private function prepare_metadata_for_output( $metadata ) {
		$metadata = html_entity_decode( $metadata, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$metadata = wp_strip_all_tags( $metadata );
		return $metadata;
	}

	/**
	 * Utility function to build cache key for a given URL.
	 *
	 * @since 5.9.0
	 *
	 * @param string $url The URL for which to build a cache key.
	 * @return string The cache key.
	 */
	private function build_cache_key_for_url( $url ) {
		return 'g_url_details_response_' . md5( $url );
	}

	/**
	 * Utility function to retrieve a value from the cache at a given key.
	 *
	 * @since 5.9.0
	 *
	 * @param string $key The cache key.
	 * @return mixed The value from the cache.
	 */
	private function get_cache( $key ) {
		return get_site_transient( $key );
	}

	/**
	 * Utility function to cache a given data set at a given cache key.
	 *
	 * @since 5.9.0
	 *
	 * @param string $key  The cache key under which to store the value.
	 * @param string $data The data to be stored at the given cache key.
	 * @return bool True when transient set. False if not set.
	 */
	private function set_cache( $key, $data = '' ) {
		$ttl = HOUR_IN_SECONDS;

		/**
		 * Filters the cache expiration.
		 *
		 * Can be used to adjust the time until expiration in seconds for the cache
		 * of the data retrieved for the given URL.
		 *
		 * @since 5.9.0
		 *
		 * @param int $ttl The time until cache expiration in seconds.
		 */
		$cache_expiration = apply_filters( 'rest_url_details_cache_expiration', $ttl );

		return set_site_transient( $key, $data, $cache_expiration );
	}
}
