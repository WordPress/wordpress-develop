<?php
/**
 * Interactivity API: WP_Interactivity_API class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.5.0
 */

/**
 * Class used to process the Interactivity API on the server.
 *
 * @since 6.5.0
 */
final class WP_Interactivity_API {
	/**
	 * Holds the mapping of directive attribute names to their processor methods.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	private static $directive_processors = array(
		'data-wp-interactive'   => 'data_wp_interactive_processor',
		'data-wp-router-region' => 'data_wp_router_region_processor',
		'data-wp-context'       => 'data_wp_context_processor',
		'data-wp-bind'          => 'data_wp_bind_processor',
		'data-wp-class'         => 'data_wp_class_processor',
		'data-wp-style'         => 'data_wp_style_processor',
		'data-wp-text'          => 'data_wp_text_processor',
		/*
		 * `data-wp-each` needs to be processed in the last place because it moves
		 * the cursor to the end of the processed items to prevent them to be
		 * processed twice.
		 */
		'data-wp-each'          => 'data_wp_each_processor',
	);

	/**
	 * Holds the initial state of the different Interactivity API stores.
	 *
	 * This state is used during the server directive processing. Then, it is
	 * serialized and sent to the client as part of the interactivity data to be
	 * recovered during the hydration of the client interactivity stores.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	private $state_data = array();

	/**
	 * Holds the configuration required by the different Interactivity API stores.
	 *
	 * This configuration is serialized and sent to the client as part of the
	 * interactivity data and can be accessed by the client interactivity stores.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	private $config_data = array();

	/**
	 * Keeps track of all derived state closures accessed during server-side rendering.
	 *
	 * This data is serialized and sent to the client as part of the interactivity
	 * data, and is handled later in the client to support derived state props that
	 * are lazily hydrated.
	 *
	 * @since 6.9.0
	 * @var array
	 */
	private $derived_state_closures = array();

	/**
	 * Flag that indicates whether the `data-wp-router-region` directive has
	 * been found in the HTML and processed.
	 *
	 * The value is saved in a private property of the WP_Interactivity_API
	 * instance instead of using a static variable inside the processor
	 * function, which would hold the same value for all instances
	 * independently of whether they have processed any
	 * `data-wp-router-region` directive or not.
	 *
	 * @since 6.5.0
	 * @var bool
	 */
	private $has_processed_router_region = false;

	/**
	 * Set of script modules that can be loaded after client-side navigation.
	 *
	 * @since 6.9.0
	 * @var array<string, true>
	 */
	private $script_modules_that_can_load_on_client_navigation = array();

	/**
	 * Stack of namespaces defined by `data-wp-interactive` directives, in
	 * the order they are processed.
	 *
	 * This is only available during directive processing, otherwise it is `null`.
	 *
	 * @since 6.6.0
	 * @var array<string>|null
	 */
	private $namespace_stack = null;

	/**
	 * Stack of contexts defined by `data-wp-context` directives, in
	 * the order they are processed.
	 *
	 * This is only available during directive processing, otherwise it is `null`.
	 *
	 * @since 6.6.0
	 * @var array<array<mixed>>|null
	 */
	private $context_stack = null;

	/**
	 * Representation in array format of the element currently being processed.
	 *
	 * This is only available during directive processing, otherwise it is `null`.
	 *
	 * @since 6.7.0
	 * @var array{attributes: array<string, string|bool>}|null
	 */
	private $current_element = null;

	/**
	 * Expression-evaluation state: safe and server-evaluable.
	 *
	 * @since 6.9.0
	 */
	private const EXPRESSION_VALID = 1;

	/**
	 * Expression-evaluation state: valid JS, but evaluation is deferred to
	 * the browser hydration pass.
	 *
	 * @since 6.9.0
	 */
	private const EXPRESSION_DEFERRED = 0;

	/**
	 * Expression-evaluation state: invalid or dangerous input.
	 *
	 * @since 6.9.0
	 */
	private const EXPRESSION_INVALID = -1;

	/**
	 * Gets and/or sets the initial state of an Interactivity API store for a
	 * given namespace.
	 *
	 * If state for that store namespace already exists, it merges the new
	 * provided state with the existing one.
	 *
	 * When no namespace is specified, it returns the state defined for the
	 * current value in the internal namespace stack during a `process_directives` call.
	 *
	 * @since 6.5.0
	 * @since 6.6.0 The `$store_namespace` param is optional.
	 *
	 * @param string|null $store_namespace Optional. The unique store namespace identifier.
	 * @param array|null  $state           Optional. The array that will be merged with the existing state for the specified
	 *                                store namespace.
	 * @return array The current state for the specified store namespace. This will be the updated state if a $state
	 *               argument was provided.
	 */
	public function state( ?string $store_namespace = null, ?array $state = null ): array {
		if ( ! $store_namespace ) {
			if ( $state ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'The namespace is required when state data is passed.' ),
					'6.6.0'
				);
				return array();
			}
			if ( null !== $store_namespace ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'The namespace should be a non-empty string.' ),
					'6.6.0'
				);
				return array();
			}
			if ( null === $this->namespace_stack ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'The namespace can only be omitted during directive processing.' ),
					'6.6.0'
				);
				return array();
			}

			$store_namespace = end( $this->namespace_stack );
		}
		if ( ! isset( $this->state_data[ $store_namespace ] ) ) {
			$this->state_data[ $store_namespace ] = array();
		}
		if ( is_array( $state ) ) {
			$this->state_data[ $store_namespace ] = array_replace_recursive(
				$this->state_data[ $store_namespace ],
				$state
			);
		}
		return $this->state_data[ $store_namespace ];
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
	public function config( string $store_namespace, array $config = array() ): array {
		if ( ! isset( $this->config_data[ $store_namespace ] ) ) {
			$this->config_data[ $store_namespace ] = array();
		}
		if ( is_array( $config ) ) {
			$this->config_data[ $store_namespace ] = array_replace_recursive(
				$this->config_data[ $store_namespace ],
				$config
			);
		}
		return $this->config_data[ $store_namespace ];
	}

	/**
	 * Prints the serialized client-side interactivity data.
	 *
	 * Encodes the config and initial state into JSON and prints them inside a
	 * script tag of type "application/json". Once in the browser, the state will
	 * be parsed and used to hydrate the client-side interactivity stores and the
	 * configuration will be available using a `getConfig` utility.
	 *
	 * @since 6.5.0
	 *
	 * @deprecated 6.7.0 Client data passing is handled by the {@see "script_module_data_{$module_id}"} filter.
	 */
	public function print_client_interactivity_data() {
		_deprecated_function( __METHOD__, '6.7.0' );
	}

	/**
	 * Set client-side interactivity-router data.
	 *
	 * Once in the browser, the state will be parsed and used to hydrate the client-side
	 * interactivity stores and the configuration will be available using a `getConfig` utility.
	 *
	 * @since 6.7.0
	 *
	 * @param array $data Data to filter.
	 * @return array Data for the Interactivity Router script module.
	 */
	public function filter_script_module_interactivity_router_data( array $data ): array {
		if ( ! isset( $data['i18n'] ) ) {
			$data['i18n'] = array();
		}
		$data['i18n']['loading'] = __( 'Loading page, please wait.' );
		$data['i18n']['loaded']  = __( 'Page Loaded.' );
		return $data;
	}

	/**
	 * Set client-side interactivity data.
	 *
	 * Once in the browser, the state will be parsed and used to hydrate the client-side
	 * interactivity stores and the configuration will be available using a `getConfig` utility.
	 *
	 * @since 6.7.0
	 * @since 6.9.0 Serializes derived state props accessed during directive processing.
	 *
	 * @param array $data Data to filter.
	 * @return array Data for the Interactivity API script module.
	 */
	public function filter_script_module_interactivity_data( array $data ): array {
		if (
			empty( $this->state_data ) &&
			empty( $this->config_data ) &&
			empty( $this->derived_state_closures )
		) {
			return $data;
		}

		$config = array();
		foreach ( $this->config_data as $key => $value ) {
			if ( ! empty( $value ) ) {
				$config[ $key ] = $value;
			}
		}
		if ( ! empty( $config ) ) {
			$data['config'] = $config;
		}

		$state = array();
		foreach ( $this->state_data as $key => $value ) {
			if ( ! empty( $value ) ) {
				$state[ $key ] = $value;
			}
		}
		if ( ! empty( $state ) ) {
			$data['state'] = $state;
		}

		$derived_props = array();
		foreach ( $this->derived_state_closures as $key => $value ) {
			if ( ! empty( $value ) ) {
				$derived_props[ $key ] = $value;
			}
		}
		if ( ! empty( $derived_props ) ) {
			$data['derivedStateClosures'] = $derived_props;
		}

		return $data;
	}

	/**
	 * Returns the latest value on the context stack with the passed namespace.
	 *
	 * When the namespace is omitted, it uses the current namespace on the
	 * namespace stack during a `process_directives` call.
	 *
	 * @since 6.6.0
	 *
	 * @param string|null $store_namespace Optional. The unique store namespace identifier.
	 */
	public function get_context( ?string $store_namespace = null ): array {
		if ( null === $this->context_stack ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The context can only be read during directive processing.' ),
				'6.6.0'
			);
			return array();
		}

		if ( ! $store_namespace ) {
			if ( null !== $store_namespace ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'The namespace should be a non-empty string.' ),
					'6.6.0'
				);
				return array();
			}

			$store_namespace = end( $this->namespace_stack );
		}

		$context = end( $this->context_stack );

		return ( $store_namespace && $context && isset( $context[ $store_namespace ] ) )
			? $context[ $store_namespace ]
			: array();
	}

	/**
	 * Returns an array representation of the current element being processed.
	 *
	 * The returned array contains a copy of the element attributes.
	 *
	 * @since 6.7.0
	 *
	 * @return array{attributes: array<string, string|bool>}|null Current element.
	 */
	public function get_element(): ?array {
		if ( null === $this->current_element ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The element can only be read during directive processing.' ),
				'6.7.0'
			);
		}

		return $this->current_element;
	}

	/**
	 * Registers the `@wordpress/interactivity` script modules.
	 *
	 * @deprecated 6.7.0 Script Modules registration is handled by {@see wp_default_script_modules()}.
	 *
	 * @since 6.5.0
	 */
	public function register_script_modules() {
		_deprecated_function( __METHOD__, '6.7.0', 'wp_default_script_modules' );
	}

	/**
	 * Adds the necessary hooks for the Interactivity API.
	 *
	 * @since 6.5.0
	 * @since 6.9.0 Adds support for client-side navigation in script modules.
	 */
	public function add_hooks() {
		add_filter( 'script_module_data_@wordpress/interactivity', array( $this, 'filter_script_module_interactivity_data' ) );
		add_filter( 'script_module_data_@wordpress/interactivity-router', array( $this, 'filter_script_module_interactivity_router_data' ) );
		add_filter( 'wp_script_attributes', array( $this, 'add_load_on_client_navigation_attribute_to_script_modules' ) );
	}

	/**
	 * Adds the `data-wp-router-options` attribute to script modules that
	 * support client-side navigation.
	 *
	 * This method filters the script attributes to include loading instructions
	 * for the Interactivity API router, indicating which modules can be loaded
	 * during client-side navigation.
	 *
	 * @since 6.9.0
	 *
	 * @param array<string, string|true>|mixed $attributes The script tag attributes.
	 * @return array The modified script tag attributes.
	 */
	public function add_load_on_client_navigation_attribute_to_script_modules( $attributes ) {
		if (
			is_array( $attributes ) &&
			isset( $attributes['type'], $attributes['id'] ) &&
			'module' === $attributes['type'] &&
			array_key_exists(
				preg_replace( '/-js-module$/', '', $attributes['id'] ),
				$this->script_modules_that_can_load_on_client_navigation
			)
		) {
			$attributes['data-wp-router-options'] = wp_json_encode( array( 'loadOnClientNavigation' => true ) );
		}
		return $attributes;
	}

	/**
	 * Marks a script module as compatible with client-side navigation.
	 *
	 * This method registers a script module to be loaded during client-side
	 * navigation in the Interactivity API router. Script modules marked with
	 * this method will have the `loadOnClientNavigation` option enabled in the
	 * `data-wp-router-options` directive.
	 *
	 * @since 6.9.0
	 *
	 * @param string $script_module_id The script module identifier.
	 */
	public function add_client_navigation_support_to_script_module( string $script_module_id ) {
		$this->script_modules_that_can_load_on_client_navigation[ $script_module_id ] = true;
	}

	/**
	 * Processes the interactivity directives contained within the HTML content
	 * and updates the markup accordingly.
	 *
	 * @since 6.5.0
	 *
	 * @param string $html The HTML content to process.
	 * @return string The processed HTML content. It returns the original content when the HTML contains unbalanced tags.
	 */
	public function process_directives( string $html ): string {
		if ( ! str_contains( $html, 'data-wp-' ) ) {
			return $html;
		}

		$this->namespace_stack = array();
		$this->context_stack   = array();

		$result = $this->_process_directives( $html );

		$this->namespace_stack = null;
		$this->context_stack   = null;

		return $result ?? $html;
	}

	/**
	 * Processes the interactivity directives contained within the HTML content
	 * and updates the markup accordingly.
	 *
	 * It uses the WP_Interactivity_API instance's context and namespace stacks,
	 * which are shared between all calls.
	 *
	 * This method returns null if the HTML contains unbalanced tags.
	 *
	 * @since 6.6.0
	 *
	 * @param string $html The HTML content to process.
	 * @return string|null The processed HTML content. It returns null when the HTML contains unbalanced tags.
	 */
	private function _process_directives( string $html ) {
		$p          = new WP_Interactivity_API_Directives_Processor( $html );
		$tag_stack  = array();
		$unbalanced = false;

		$directive_processor_prefixes          = array_keys( self::$directive_processors );
		$directive_processor_prefixes_reversed = array_reverse( $directive_processor_prefixes );

		/*
		 * Save the current size for each stack to restore them in case
		 * the processing finds unbalanced tags.
		 */
		$namespace_stack_size = count( $this->namespace_stack );
		$context_stack_size   = count( $this->context_stack );

		while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag_name = $p->get_tag();

			/*
			 * Directives inside SVG and MATH tags are not processed,
			 * as they are not compatible with the Tag Processor yet.
			 * We still process the rest of the HTML.
			 */
			if ( 'SVG' === $tag_name || 'MATH' === $tag_name ) {
				if ( $p->get_attribute_names_with_prefix( 'data-wp-' ) ) {
					/* translators: 1: SVG or MATH HTML tag, 2: Namespace of the interactive block. */
					$message = sprintf( __( 'Interactivity directives were detected on an incompatible %1$s tag when processing "%2$s". These directives will be ignored in the server side render.' ), $tag_name, end( $this->namespace_stack ) );
					_doing_it_wrong( __METHOD__, $message, '6.6.0' );
				}
				$p->skip_to_tag_closer();
				continue;
			}

			if ( $p->is_tag_closer() ) {
				list( $opening_tag_name, $directives_prefixes ) = ! empty( $tag_stack ) ? end( $tag_stack ) : array( null, null );

				if ( 0 === count( $tag_stack ) || $opening_tag_name !== $tag_name ) {

					/*
					 * If the tag stack is empty or the matching opening tag is not the
					 * same than the closing tag, it means the HTML is unbalanced and it
					 * stops processing it.
					 */
					$unbalanced = true;
					break;
				} else {
					// Remove the last tag from the stack.
					array_pop( $tag_stack );
				}
			} else {
				$each_child_attrs = $p->get_attribute_names_with_prefix( 'data-wp-each-child' );
				if ( null === $each_child_attrs ) {
					continue;
				}

				if ( 0 !== count( $each_child_attrs ) ) {
					/*
					 * If the tag has a `data-wp-each-child` directive, jump to its closer
					 * tag because those tags have already been processed.
					 */
					$p->next_balanced_tag_closer_tag();
					continue;
				} else {
					$directives_prefixes = array();

					// Checks if there is a server directive processor registered for each directive.
					foreach ( $p->get_attribute_names_with_prefix( 'data-wp-' ) as $attribute_name ) {
						$parsed_directive = $this->parse_directive_name( $attribute_name );
						if ( empty( $parsed_directive ) ) {
							continue;
						}
						$directive_prefix = 'data-wp-' . $parsed_directive['prefix'];
						if ( array_key_exists( $directive_prefix, self::$directive_processors ) ) {
							$directives_prefixes[] = $directive_prefix;
						}
					}

					/*
					 * If this tag will visit its closer tag, it adds it to the tag stack
					 * so it can process its closing tag and check for unbalanced tags.
					 */
					if ( $p->has_and_visits_its_closer_tag() ) {
						$tag_stack[] = array( $tag_name, $directives_prefixes );
					}
				}
			}
			/*
			 * If the matching opener tag didn't have any directives, it can skip the
			 * processing.
			 */
			if ( 0 === count( $directives_prefixes ) ) {
				continue;
			}

			// Directive processing might be different depending on if it is entering the tag or exiting it.
			$modes = array(
				'enter' => ! $p->is_tag_closer(),
				'exit'  => $p->is_tag_closer() || ! $p->has_and_visits_its_closer_tag(),
			);

			// Get the element attributes to include them in the element representation.
			$element_attrs = array();
			$attr_names    = $p->get_attribute_names_with_prefix( '' ) ?? array();

			foreach ( $attr_names as $name ) {
				$element_attrs[ $name ] = $p->get_attribute( $name );
			}

			// Assign the current element right before running its directive processors.
			$this->current_element = array(
				'attributes' => $element_attrs,
			);

			foreach ( $modes as $mode => $should_run ) {
				if ( ! $should_run ) {
					continue;
				}

				/*
				 * Sorts the attributes by the order of the `directives_processor` array
				 * and checks what directives are present in this element.
				 */
				$existing_directives_prefixes = array_intersect(
					'enter' === $mode ? $directive_processor_prefixes : $directive_processor_prefixes_reversed,
					$directives_prefixes
				);
				foreach ( $existing_directives_prefixes as $directive_prefix ) {
					$func = is_array( self::$directive_processors[ $directive_prefix ] )
						? self::$directive_processors[ $directive_prefix ]
						: array( $this, self::$directive_processors[ $directive_prefix ] );

					call_user_func_array( $func, array( $p, $mode, &$tag_stack ) );
				}
			}

			// Clear the current element.
			$this->current_element = null;
		}

		if ( $unbalanced ) {
			// Reset the namespace and context stacks to their previous values.
			array_splice( $this->namespace_stack, $namespace_stack_size );
			array_splice( $this->context_stack, $context_stack_size );
		}

		/*
		 * It returns null if the HTML is unbalanced because unbalanced HTML is
		 * not safe to process. In that case, the Interactivity API runtime will
		 * update the HTML on the client side during the hydration. It will display
		 * a notice to the developer in the console to inform them about the issue.
		 */
		if ( $unbalanced || 0 < count( $tag_stack ) ) {
			return null;
		}

		return $p->get_updated_html();
	}

	/**
	 * Evaluates the reference path passed to a directive based on the current
	 * store namespace, state and context.
	 *
	 * @since 6.5.0
	 * @since 6.6.0 The function now adds a warning when the namespace is null, falsy, or the directive value is empty.
	 * @since 6.6.0 Removed `default_namespace` and `context` arguments.
	 * @since 6.6.0 Add support for derived state.
	 * @since 6.9.0 Receive $entry as an argument instead of the directive value string.
	 *
	 * @param array $entry An array containing a whole directive entry with its namespace, value, suffix, or unique ID.
	 * @return mixed|null The result of the evaluation. Null if the reference path doesn't exist or the namespace is falsy.
	 */
	private function evaluate( $entry ) {
		$context                               = end( $this->context_stack );
		['namespace' => $ns, 'value' => $path] = $entry;

		if ( ! $ns || ! $path ) {
			/* translators: %s: The directive value referenced. */
			$message = sprintf( __( 'Namespace or reference path cannot be empty. Directive value referenced: %s' ), json_encode( $entry ) );
			_doing_it_wrong( __METHOD__, $message, '6.6.0' );
			return null;
		}

		$store = array(
			'state'   => $this->state_data[ $ns ] ?? array(),
			'context' => $context[ $ns ] ?? array(),
		);

		// Preserve the long-standing dotted-path contract that malformed
		// directive values with leading/trailing whitespace are treated as
		// invalid and return null. Without this guard, the experimental
		// full-expression path would happily evaluate ` state.key` as
		// `$__st['key']`, changing the observable behavior covered by
		// `test_evaluate_non_existent_path`.
		if ( trim( $path ) !== $path ) {
			return null;
		}

		// Checks if the reference path is preceded by a negation operator (!).
		$should_negate_value = '!' === $path[0];
		$path                = $should_negate_value ? trim( substr( $path, 1 ) ) : $path;

		// Full-expression path: when the path is not a simple dotted path,
		// evaluate it as an expression. This mirrors the client-side
		// getEvaluate full-expression path (new Function() in JS) and supports
		// comparisons ( !==, ===, >, < ), logical operators, ternaries, and
		// other basic JS-like expressions.
		if ( ! preg_match( '/^(?:state|context)(?:\.[a-zA-Z_][a-zA-Z0-9_]*|\.[0-9]+)+$/', $path ) ) {
			$result = $this->evaluate_full_expression( $path, $store, $ns );
			return $should_negate_value ? ! $this->is_js_truthy( $result ) : $result;
		}

		// Extracts the value from the store using the reference path. The
		// shared helper also accounts for derived-state getters (Closures)
		// encountered along the path, invoking them on the server and
		// recording the path prefix in `$derived_state_closures` so the client
		// wiring for lazy hydration keeps working.
		$current = $this->resolve_path_with_closures( $store, explode( '.', $path ), $ns );
		if ( null === $current && '' === $path ) {
			// `resolve_path_with_closures()` returns null both for missing
			// paths and when a derived-state callback threw; the latter is
			// already reported inside the helper, so a null return here is
			// always "path does not exist".
		}

		// Returns the opposite if it contains a negation operator (!).
		return $should_negate_value ? ! $this->is_js_truthy( $current ) : $current;
	}

	/**
	 * Evaluates a full (non-dotted-path) expression and, while both engines
	 * exist, compares their results under WP_DEBUG.
	 *
	 * Keeping the dual-engine dispatch behind this one method makes later
	 * cleanup mechanical: whichever engine is not selected can be removed by
	 * simplifying this method and deleting the unused helper(s) below.
	 *
	 * @since 6.9.0
	 *
	 * @param string $path  Original JS expression.
	 * @param array  $store Store root with 'state' and 'context' keys.
	 * @param string $ns    Store namespace.
	 * @return mixed The expression result.
	 */
	private function evaluate_full_expression( string $path, array $store, string $ns ) {
		$result_a = $this->evaluate_full_expression_approach_a( $path, $store, $ns );
		$result_b = $this->evaluate_full_expression_approach_b( $path, $store, $ns );

		// WP_DEBUG-gated parity comparison. null+null is "both deferred or
		// unsupported" (not a mismatch); null+value IS a mismatch and is
		// logged — it signals a divergence in which expressions each engine
		// considers supported or how it computed the result.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$norm_a = is_bool( $result_a ) ? ( $result_a ? 'true' : 'false' ) : var_export( $result_a, true );
			$norm_b = is_bool( $result_b ) ? ( $result_b ? 'true' : 'false' ) : var_export( $result_b, true );
			if ( $norm_a !== $norm_b && ( null !== $result_a || null !== $result_b ) ) {
				wp_trigger_error(
					__METHOD__,
					sprintf(
						/* translators: 1: Directive expression, 2: Approach A result, 3: Approach B result. */
						__( 'Interactivity API expression evaluation mismatch for "%1$s": Approach A returned %2$s, Approach B returned %3$s.' ),
						$path,
						$norm_a,
						$norm_b
					),
					E_USER_WARNING
				);
			}
		}

		// Return Approach A's result while the dual-implementation
		// comparison is ongoing. Neither approach is canonical —
		// both are experiments and one will be removed before merge.
		return $result_a;
	}

	/**
	 * Splits a JS expression into `;`-delimited statements, respecting
	 * string literals, template literals, regex literals, and IIFEs.
	 *
	 * Mirrors the client-side `splitStatements()` helper in the Gutenberg
	 * Interactivity package, and Datastar's `genRx()` statement regex.
	 *
	 * @since 6.9.0
	 *
	 * @param string $expr JS expression possibly containing `;`.
	 * @return string[]|null Array of statements, or null when the
	 *                        expression contains no semicolons.
	 */
	private function split_expression_into_statements( string $expr ): ?array {
		if ( ! str_contains( $expr, ';' ) ) {
			return null;
		}

		// Matches: regex literals, double/single-quoted strings,
		// template literals, IIFEs, or any non-semicolon character.
		$re = '/(\/(?:\\\\\/|[^\/])*\/|"(?:\\\\"|[^"])*"|\'(?:\\\\\'|[^\'])*\'|`(?:\\\\`|[^`])*`|\(\s*((?:function)\s*\(\s*\)|(?:\(\s*\))\s*=>)\s*(?:\{[\s\S]*?\}|[^;){]*)\s*\)\s*\(\s*\)|[^;])+/';
		if ( preg_match_all( $re, trim( $expr ), $matches ) ) {
			return $matches[0];
		}

		return null;
	}

	/**
	 * Determines JS-style truthiness for the subset of value types directive
	 * expressions can observe on the server.
	 *
	 * Key divergences from PHP:
	 * - empty arrays are truthy in JS, falsy in PHP
	 * - the string '0' is truthy in JS, falsy in PHP
	 *
	 * @since 6.9.0
	 *
	 * @param mixed $value Value to test.
	 * @return bool Whether the value is truthy in JS terms.
	 */
	private function is_js_truthy( $value ): bool {
		if ( null === $value ) {
			return false;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			return 0 != $value;
		}
		if ( is_string( $value ) ) {
			return '' !== $value;
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return true;
		}
		return (bool) $value;
	}

	/**
	 * Evaluates a full (non-dotted-path) expression using Approach A:
	 * regex-transform state.X/context.X → PHP array access, validate the
	 * token stream, substitute derived-state closures with JSON literals,
	 * then eval() the resulting literal-only expression.
	 *
	 * Supports `;`-delimited multi-statement expressions: each statement
	 * is evaluated via the same pipeline, and the last statement's value
	 * is returned. References to `actions.*` and `callbacks.*` are
	 * regex-transformed to the PHP literal `null` (these are client-only
	 * JS function references with no server-side equivalent).
	 *
	 * This is the established behaviour and the result returned to the caller
	 * during the dual-implementation comparison phase.
	 *
	 * @since 6.9.0
	 *
	 * @param string $path  Directive expression (the original JS source).
	 * @param array  $store  Store root with 'state' and 'context' keys.
	 * @param string $ns     Store namespace (for derived-state recording).
	 * @return mixed Computed value, or null when unsupported/invalid.
	 */
	private function evaluate_full_expression_approach_a( string $path, array $store, string $ns ) {
		$__st  = $store['state'];
		$__ctx = $store['context'];

		// Split into statements on ';', respecting string literals,
		// template literals, regex literals, and IIFEs.
		$statements = array( $path );
		if ( str_contains( $path, ';' ) ) {
			$split = $this->split_expression_into_statements( $path );
			if ( null !== $split ) {
				$statements = $split;
			}
		}

		// Process each statement; return the last statement's value.
		$result = null;
		foreach ( $statements as $statement ) {
			// Transform state.X.Y.Z to $__st['X']['Y']['Z'].
			$php_expr = preg_replace_callback(
				'/state\.([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*)/',
				function ( $m ) {
					$parts = explode( '.', $m[1] );
					$r     = '$__st';
					foreach ( $parts as $p ) {
						$r .= "['{$p}']";
					}
					return $r;
				},
				$statement
			);

			// Transform context.X.Y.Z to $__ctx['X']['Y']['Z'].
			$php_expr = preg_replace_callback(
				'/context\.([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*)/',
				function ( $m ) {
					$parts = explode( '.', $m[1] );
					$r     = '$__ctx';
					foreach ( $parts as $p ) {
						$r .= "['{$p}']";
					}
					return $r;
				},
				$php_expr
			);

			// Transform actions.* and callbacks.* to the PHP literal
			// `null`. These are client-only JS function references
			// that have no server-side equivalent. Transforming them
			// to null allows expressions like `callbacks.x || context.x`
			// to evaluate correctly server-side while still deferring
			// to the client when they are the sole value.
			$php_expr = preg_replace(
				'/\b(?:actions|callbacks)\.[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*/',
				'null',
				$php_expr
			);

			// Validate the post-transform expression: VALID / UNSUPPORTED / INVALID.
			$safety = $this->evaluate_expression_safety( $php_expr );

			if ( self::EXPRESSION_INVALID === $safety ) {
				// INVALID — dangerous PHP constructs. Report and bail to the client.
				_doing_it_wrong(
					__METHOD__,
					sprintf(
					/* translators: %s: The directive expression. */
						__( 'Interactivity API directive contained an unsafe expression: "%s".' ),
						esc_html( $php_expr )
					),
					'6.9.0'
				);
				return null;
			}

			if ( self::EXPRESSION_DEFERRED === $safety ) {
				// DEFERRED — valid JS that PHP cannot evaluate server-side
				// (assignments, function/constant calls). Client handles it.
				return null;
			}

			// VALID — substitute derived-state closures with JSON literals so
			// eval() never sees a Closure object as an operand, then evaluate.
			$substituted = $this->substitute_closures( $php_expr, $store, $ns );
			if ( null === $substituted ) {
				return null;
			}

			try {
				// phpcs:ignore Squiz.PHP.Eval.Discouraged
				$result = eval( "return ( $substituted );" );
			} catch ( \Throwable $e ) {
				$result = null;
			}
		}

		return $result;
	}

	/**
	 * Evaluates a full (non-dotted-path) expression using Approach B:
	 * a custom lexer + recursive-descent parser + interpreter that consumes
	 * the ORIGINAL JS expression directly (no regex transforms, no eval()).
	 *
	 * This is the challenger approach during the dual-implementation
	 * comparison phase. It produces correct JS semantics for the supported
	 * expression subset.
	 *
	 * @since 6.9.0
	 *
	 * @param string $path  Directive expression (the original JS source).
	 * @param array  $store  Store root with 'state' and 'context' keys.
	 * @param string $ns     Store namespace (for derived-state recording).
	 * @return mixed Computed value, or null when unsupported/invalid.
	 */
	private function evaluate_full_expression_approach_b( string $path, array $store, string $ns ) {
		$evaluator = new WP_Interactivity_Expression_Evaluator(
			function ( string $resolved_path ) use ( $store, $ns ) {
				return $this->resolve_path_with_closures( $store, explode( '.', $resolved_path ), $ns );
			}
		);
		return $evaluator->evaluate( $path );
	}

	/**
	 * Records a derived-state closure path for a given namespace.
	 *
	 * The list of paths serialized to the client as `derivedStateClosures`
	 * tells the client which server-side state getters need reactive wrapping
	 * during hydration. Each path is the prefix up to and including the closure
	 * location, e.g. for `state.complex.value` where `complex` is a Closure,
	 * the recorded path is `state.complex`.
	 *
	 * Kept as a tiny helper so the dotted-path branch and the full-expression
	 * evaluators (Approach A's `substitute_closures()` and Approach B's
	 * `resolve()`) all record the same paths with the same semantics.
	 *
	 * @since 6.9.0
	 *
	 * @param string $ns   Store namespace.
	 * @param string $path Derived-state path prefix to record.
	 */
	private function record_derived_closure( string $ns, string $path ): void {
		$this->derived_state_closures[ $ns ] = $this->derived_state_closures[ $ns ] ?? array();
		if ( ! in_array( $path, $this->derived_state_closures[ $ns ], true ) ) {
			$this->derived_state_closures[ $ns ][] = $path;
		}
	}

	/**
	 * Resolves a dotted reference path against a store root, invoking any
	 * derived-state Closures encountered along the way.
	 *
	 * Shared by the simple dotted-path branch of {@see evaluate()} and by the
	 * full-expression evaluators (both Approach A and Approach B) so that
	 * derived-state getters behave identically across all server-side code
	 * paths: the closure is invoked on the server, its namespace is pushed onto
	 * the namespace stack for the duration of the call (so `state()` and
	 * `get_context()` inside the getter resolve correctly), the path prefix is
	 * recorded in `$derived_state_closures`, and resolution continues against
	 * the closure's return value. If a closure returns another closure, that
	 * subsequent closure is invoked on the next segment iteration — mirroring
	 * the existing plain-path behaviour.
	 *
	 * The `'length'` pseudo-property for list arrays and strings is honoured
	 * to mimic JavaScript's `.length` access, which directives rely on.
	 *
	 * @since 6.9.0
	 *
	 * @param array  $root           The store root, e.g. `['state' => …, 'context' => …]`
	 *                               for `evaluate()`, or a subtree during mid-path resolution.
	 * @param array  $path_segments  Dotted path already split on '.'.
	 * @param string $ns             Store namespace, used for derived-state recording.
	 * @return mixed The resolved value, or null if the path does not exist.
	 *                Note: null is ALSO returned if a derived-state callback throws;
	 *                that case is reported via `_doing_it_wrong()` before returning.
	 */
	private function resolve_path_with_closures( $root, array $path_segments, string $ns ) {
		$current = $root;
		foreach ( $path_segments as $index => $path_segment ) {
			/*
			 * Special case for numeric arrays and strings. Add length
			 * property mimicking JavaScript behavior.
			 *
			 * @since 6.8.0
			 */
			if ( 'length' === $path_segment ) {
				if ( is_array( $current ) && array_is_list( $current ) ) {
					$current = count( $current );
					break;
				}

				if ( is_string( $current ) ) {
					/*
					 * Differences in encoding between PHP strings and
					 * JavaScript mean that it's complicated to calculate
					 * the string length JavaScript would see from PHP.
					 * `strlen` is a reasonable approximation.
					 *
					 * Users that desire a more precise length likely have
					 * more precise needs than "bytelength" and should
					 * implement their own length calculation in derived
					 * state taking into account encoding and their desired
					 * output (codepoints, graphemes, bytes, etc.).
					 */
					$current = strlen( $current );
					break;
				}
			}

			if ( ( is_array( $current ) || $current instanceof ArrayAccess ) && isset( $current[ $path_segment ] ) ) {
				$current = $current[ $path_segment ];
			} elseif ( is_object( $current ) && isset( $current->$path_segment ) ) {
				$current = $current->$path_segment;
			} else {
				$current = null;
				break;
			}

			while ( $current instanceof Closure ) {
				/*
				 * This state getter's namespace is added to the stack so that
				 * `state()` or `get_config()` read that namespace when called
				 * without specifying one.
				 */
				array_push( $this->namespace_stack, $ns );
				try {
					$current = $current();

					// Tracks derived state properties accessed during rendering.
					$current_path = implode( '.', array_slice( $path_segments, 0, $index + 1 ) );
					$this->record_derived_closure( $ns, $current_path );
				} catch ( Throwable $e ) {
					_doing_it_wrong(
						// Attribute the notice to the public-facing
						// `evaluate` method (not this internal helper) so
						// the existing `@expectedIncorrectUsage` contract on
						// tests like `test_evaluate_derived_state_that_throws`
						// keeps matching.
						'WP_Interactivity_API::evaluate',
						sprintf(
							/* translators: 1: Path pointing to an Interactivity API state property, 2: Namespace for an Interactivity API store. */
							__( 'Uncaught error executing a derived state callback with path "%1$s" and namespace "%2$s".' ),
							implode( '.', $path_segments ),
							$ns
						),
						'6.6.0'
					);
					return null;
				} finally {
					// Remove the property's namespace from the stack.
					array_pop( $this->namespace_stack );
				}
			}
		}

		return $current;
	}

	/**
	 * Checks whether a PHP expression (the post-regex-transform form of a
	 * directive value) is safe to evaluate during SSR.
	 *
	 * Used by the full-expression path of {@see evaluate()} before invoking
	 * `eval()`. The expression's `state.X` / `context.X` references have
	 * already been rewritten to `$__st['X']` / `$__ctx['X']` by the regex
	 * transforms; this method inspects the resulting token stream and
	 * classifies the expression into one of three states.
	 *
	 * Possible return values:
	 *   1  = VALID       — safe, read-only expression → evaluate with eval().
	 *   0  = UNSUPPORTED — contains assignments or function/constant calls
	 *                      (these are valid JS but PHP cannot evaluate them
	 *                      server-side: actions live in view.js, and assigning
	 *                      to a state/context leaf has no persistence on the
	 *                      server). The client handles them at runtime.
	 *  -1  = INVALID     — contains dangerous PHP constructs (object/static
	 *                      access, namespace separators, code execution,
	 *                      file inclusion, nested eval, open tags, etc.) or
	 *                      characters that have no JS equivalent at all
	 *                      (`.`, `;`, backticks, `@`, `#`, `\`). Returns null
	 *                      and emits a `_doing_it_wrong()` notice.
	 *
	 * Variable names are restricted to `$__st` and `$__ctx` — anything else
	 * (e.g. `$_SERVER`, `$evil`) is INVALID. Bare identifiers (`T_STRING`)
	 * are restricted to `true`, `false`, `null`; any other identifier is
	 * treated as a function/constant reference and marks the expression as
	 * UNSUPPORTED (this is also what catches call syntax `foo(...)`, because
	 * `(` and `)` are individually safe characters but `foo` is a non-allowed
	 * `T_STRING`). See the implementation plan, decisions 4 and 5.
	 *
	 * @since 6.9.0
	 *
	 * @param string $php_expr The PHP expression to validate (after regex transform).
	 * @return int 1 (VALID), 0 (UNSUPPORTED), or -1 (INVALID).
	 */
	private function evaluate_expression_safety( string $php_expr ): int {
		// Single-character tokens that are individually safe. Note that `(`,
		// `)` and `,` are listed here; an unsanctioned function call is caught
		// via the `T_STRING` identifier not being `true/false/null`, not via
		// the parentheses themselves. `=` is also safe-as-a-character but
		// treated specially below as an assignment operator.
		$safe_chars = array(
			' ',
			'(',
			')',
			'[',
			']',
			'?',
			':',
			',',
			'+',
			'-',
			'*',
			'/',
			'%',
			'=',
			'!',
			'~',
			'|',
			'&',
			'^',
			'<',
			'>',
		);

		// Compound-assignment and mutation tokens. These make an expression
		// UNSUPPORTED (not INVALID): they would only modify local copies of
		// state/context that do not persist across requests, so they are not
		// dangerous; but PHP cannot faithfully model the JS mutation
		// server-side, so the client handles them.
		$assignment_tokens = array(
			T_PLUS_EQUAL,
			T_MINUS_EQUAL,
			T_MUL_EQUAL,
			T_DIV_EQUAL,
			T_MOD_EQUAL,
			T_POW_EQUAL,
			T_AND_EQUAL,
			T_OR_EQUAL,
			T_XOR_EQUAL,
			T_SL_EQUAL,
			T_SR_EQUAL,
			T_COALESCE_EQUAL,
			T_INC,
			T_DEC,
		);

		// Dangerous PHP constructs (reject-list). Touching any of these makes
		// the expression INVALID — they enable code execution, sandbox escape,
		// file access, or are PHP-specific operators with no JS equivalent
		// (allowing them would produce expressions that work server-side but
		// fail client-side, creating confusing SSR/hydration mismatches).
		//
		// Constants that do not exist on PHP 7.4 are define()-shimmed in
		// `interactivity-api-token-shims.php` with sentinel integer values.
		$dangerous = array(
			// Object/static/namespace access.
			T_OBJECT_OPERATOR,
			T_NULLSAFE_OBJECT_OPERATOR,
			T_DOUBLE_COLON,
			T_PAAMAYIM_NEKUDOTAYIM,
			T_NS_SEPARATOR,
			T_NAME_FULLY_QUALIFIED,
			T_NAME_QUALIFIED,
			T_NAME_RELATIVE,

			// Code execution / file inclusion.
			T_EVAL,
			T_EXIT,
			T_INCLUDE,
			T_INCLUDE_ONCE,
			T_REQUIRE,
			T_REQUIRE_ONCE,
			T_NEW,
			T_CLONE,

			// Function/closure definition.
			T_FUNCTION,
			T_FN,

			// Output / termination / scope manipulation.
			T_ECHO,
			T_PRINT,
			T_UNSET,
			T_THROW,
			T_GLOBAL,
			T_STATIC,
			T_GOTO,
			T_RETURN,
			T_YIELD,
			T_YIELD_FROM,
			T_HALT_COMPILER,
			T_ATTRIBUTE,
			T_NAMESPACE,

			// PHP open/close tags and inline HTML.
			T_OPEN_TAG,
			T_OPEN_TAG_WITH_ECHO,
			T_CLOSE_TAG,
			T_INLINE_HTML,

			// PHP 8.1+ tokenizes a bare `&` in expressions using the
			// ampersand token IDs, and the specific variant depends on the
			// following token rather than on whether the `&` is actually a
			// reference syntax construct. Both ampersand tokens are therefore
			// allow-listed below in expression context; they are not a
			// sandbox-escape vector on their own, and unsupported reference
			// forms still fail later during eval/parse rather than executing.

			// PHP-specific operators with no JS equivalent (kept out so SSR and
			// hydration agree on what is even expressible).
			T_SPACESHIP,
			T_LOGICAL_AND,
			T_LOGICAL_OR,
			T_LOGICAL_XOR,
			T_ARRAY,
			T_DOUBLE_ARROW,
			T_ARRAY_CAST,
			T_BOOL_CAST,
			T_DOUBLE_CAST,
			T_INT_CAST,
			T_OBJECT_CAST,
			T_STRING_CAST,
			T_UNSET_CAST,
			T_VOID_CAST,
			T_EMPTY,
			T_ISSET,
			T_CONCAT_EQUAL,
			T_CURLY_OPEN,
			T_DOLLAR_OPEN_CURLY_BRACES,
			T_STRING_VARNAME,
			T_START_HEREDOC,
			T_END_HEREDOC,
			T_NUM_STRING,
			T_ENCAPSED_AND_WHITESPACE,

			// Declarations / OOP keywords.
			T_ABSTRACT,
			T_FINAL,
			T_PRIVATE,
			T_PROTECTED,
			T_PUBLIC,
			T_PRIVATE_SET,
			T_PROTECTED_SET,
			T_PUBLIC_SET,
			T_CLASS,
			T_INTERFACE,
			T_TRAIT,
			T_ENUM,
			T_IMPLEMENTS,
			T_EXTENDS,
			T_INSTANCEOF,
			T_READONLY,
			T_DECLARE,
			T_CONST,
			T_VAR,
			T_CALLABLE,
			T_INSTEADOF,

			// Control flow (not expression-safe).
			T_IF,
			T_ELSE,
			T_ELSEIF,
			T_FOR,
			T_FOREACH,
			T_WHILE,
			T_DO,
			T_SWITCH,
			T_CASE,
			T_DEFAULT,
			T_BREAK,
			T_CONTINUE,
			T_TRY,
			T_CATCH,
			T_FINALLY,

			// Other PHP-specific constructs.
			T_MATCH,
			T_PIPE,
			T_ELLIPSIS,
			T_LIST,
			T_AS,
			T_USE,

			// Magic constants — no JS equivalent, leak server internals.
			T_CLASS_C,
			T_TRAIT_C,
			T_METHOD_C,
			T_FUNC_C,
			T_NS_C,
			T_FILE,
			T_DIR,
			T_LINE,
			T_PROPERTY_C,
		);

		try {
			// `token_get_all()` parses source as PHP *only between open/close
			// tags*; anything outside `<?php` is treated as T_INLINE_HTML and
			// surfaces as a single giant forbidden token. Prepend a PHP open
			// tag (with trailing whitespace) so the expression is parsed as
			// code, then skip the leading T_OPEN_TAG in the loop and exclude
			// its bytes from the round-trip byte-length check below.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$tokens = @token_get_all( '<?php ' . $php_expr );
		} catch ( \Throwable $e ) {
			return self::EXPRESSION_INVALID;
		}

		if ( ! is_array( $tokens ) || array() === $tokens ) {
			return self::EXPRESSION_INVALID;
		}

		/*
		 * Defense-in-depth: `token_get_all()` silently drops some byte
		 * sequences (notably T_INLINE_HTML and T_BAD_CHARACTER fragments can
		 * round-trip without preserving byte length). If the concatenated
		 * byte length of the returned tokens (excluding the prepended
		 * open-tag) does not match the input, the stream is unreliable and
		 * we reject rather than risk `eval()` seeing bytes the validator
		 * skipped.
		 */
		$byte_len = 0;
		foreach ( $tokens as $token ) {
			if ( is_string( $token ) ) {
				$byte_len += strlen( $token );
			} else {
				$byte_len += strlen( $token[1] );
			}
		}
		// Subtract the prepended "<?php " (6 bytes) from the token stream's
		// total. The leading T_OPEN_TAG itself encodes those 6 bytes.
		// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda
		if ( $byte_len !== strlen( $php_expr ) + 6 ) {
			return self::EXPRESSION_INVALID;
		}

		$has_assignment = false;
		$has_fn_call    = false;

		foreach ( $tokens as $token ) {
			// ── Single-character token ─────────────────────────────────
			if ( is_string( $token ) ) {
				$char = $token;

				// A bare `=` is the only single-character assignment operator.
				// `token_get_all()` returns `==`, `===`, `!=`, `!==`, `<=`,
				// `>=` as multi-character named tokens, so a bare `=` is
				// always assignment.
				if ( '=' === $char ) {
					$has_assignment = true;
					continue;
				}

				// Any other character not in the allow-list is INVALID. This
				// catches backticks (`cmd` — shell execution), `;`
				// (multi-statement), `.` (PHP string concat — no JS eq), `@`
				// (error suppression), `#` (PHP comment that could hide a
				// payload), and `\` (namespace separator; also a named token
				// T_NS_SEPARATOR when leading an identifier, handled below —
				// but standalone `\` is rejected here for clarity).
				if ( ! in_array( $char, $safe_chars, true ) ) {
					return self::EXPRESSION_INVALID;
				}
				continue;
			}

			// ── Named token ────────────────────────────────────────────
			$token_id   = $token[0];
			$token_text = $token[1];

			// The leading T_OPEN_TAG we prepended to make token_get_all parse
			// the expression as PHP code is intentionally present; skip it.
			// Any *additional* open/close tag from the user's expression is
			// still caught below via the $dangerous list (T_OPEN_TAG,
			// T_CLOSE_TAG, etc.).
			if ( T_OPEN_TAG === $token_id ) {
				continue;
			}

			// Reject dangerous PHP constructs outright.
			if ( in_array( $token_id, $dangerous, true ) ) {
				return self::EXPRESSION_INVALID;
			}

			// On PHP < 8.0, `#[Attr]` is tokenized as T_COMMENT (not
			// T_ATTRIBUTE), so it slips past the $dangerous check above.
			// Reject any T_COMMENT that starts with `#[`, which is a PHP
			// 8.0+ attribute on newer PHP but just a comment on older PHP.
			if ( T_COMMENT === $token_id && str_starts_with( $token_text, '#[' ) ) {
				return self::EXPRESSION_INVALID;
			}

			// Assignment / mutation tokens → UNSUPPORTED.
			if ( in_array( $token_id, $assignment_tokens, true ) ) {
				$has_assignment = true;
				continue;
			}

			// Literal operands, operators, whitespace, and comments are
			// allowed. `T_COMMENT` and `T_DOC_COMMENT` are harmless — PHP
			// ignores them during eval. Allow-list the explicitly permitted
			// token IDs; everything else falls through to the variable /
			// identifier checks below, which is safe because unknown tokens
			// are ignored rather than trusted.
			$allowed_named = array(
				T_LNUMBER,
				T_DNUMBER,
				T_CONSTANT_ENCAPSED_STRING,
				T_WHITESPACE,
				T_COMMENT,
				T_DOC_COMMENT,
				T_IS_EQUAL,
				T_IS_NOT_EQUAL,
				T_IS_IDENTICAL,
				T_IS_NOT_IDENTICAL,
				T_IS_SMALLER_OR_EQUAL,
				T_IS_GREATER_OR_EQUAL,
				T_BOOLEAN_AND,
				T_BOOLEAN_OR,
				T_SL,
				T_SR,
				T_POW,
				T_COALESCE,
				// PHP 8.1+ tokenization of a bare bitwise `&`.
				T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
				T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
			);
			if ( in_array( $token_id, $allowed_named, true ) ) {
				continue;
			}

			// Variable references — only $__st and $__ctx are permitted.
			if ( T_VARIABLE === $token_id ) {
				if ( ! in_array( $token_text, array( '$__st', '$__ctx' ), true ) ) {
					return self::EXPRESSION_INVALID;
				}
				continue;
			}

			// Bare identifiers — only true/false/null are permitted. Any other
			// T_STRING is a function or constant name and marks the
			// expression as UNSUPPORTED (the client evaluates it during
			// hydration). This is the mechanism that catches call syntax:
			// `foo(...)` tokenizes with `foo` as T_STRING and `(` `)` as
			// safe single-character tokens; `foo` failing this check sets
			// $has_fn_call and the expression returns null.
			if ( T_STRING === $token_id ) {
				if ( ! in_array( strtolower( $token_text ), array( 'true', 'false', 'null' ), true ) ) {
					$has_fn_call = true;
				}
				continue;
			}

			// Any other named token not covered above is unknown — reject to
			// be safe. New PHP tokens added in future versions will land here
			// until explicitly reviewed and allow-listed.
			return self::EXPRESSION_INVALID;
		}

		// Assignments or function/constant calls → DEFERRED to the client.
		if ( $has_assignment || $has_fn_call ) {
			return self::EXPRESSION_DEFERRED;
		}

		return self::EXPRESSION_VALID;
	}

	/**
	 * Substitutes derived-state Closures in a (post-regex-transform) PHP
	 * expression with JSON-encoded literals of their computed values.
	 *
	 * Approach A's `eval()` cannot invoke a Closure returned by an array
	 * access — `$__st['foo']` whose value is a Closure yields the Closure
	 * *object*, not its computed result, and `eval()` proceeds using the
	 * object as an operand (which throws for `===`/`&&`/`<` etc.). To preserve
	 * the existing SSR behaviour for compound expressions over derived-state
	 * getters (e.g. `data-wp-bind--hidden="state.below10 && state.someFlag"`),
	 * this pre-pass rewrites the expression so that every Closure-valued
	 * `$__st[...]` / `$__ctx[...]` reference is replaced by the JSON literal of
	 * its computed value. After substitution the expression contains only
	 * literals and operators — no Closure object ever reaches `eval()`.
	 *
	 * The substitution walks the same per-segment path as
	 * {@see resolve_path_with_closures()} so mid-path closures are invoked and
	 * recorded with the same prefix semantics (e.g. for
	 * `state.complex.value` where `complex` is a Closure, `state.complex` is
	 * recorded and the walk continues from the closure's return value).
	 *
	 * @since 6.9.0
	 *
	 * @param string $php_expr The post-regex-transform PHP expression,
	 *                         containing `$__st['X']['Y']` / `$__ctx['X']` references.
	 * @param array  $store    The store root: `['state' => …, 'context' => …]`.
	 * @param string $ns       Store namespace (for derived-state recording).
	 * @return string|null The rewritten expression with Closures substituted
	 *                     by literals, or null on any unexpected error or when
	 *                     a substituted value is not JSON-encodable.
	 */
	private function substitute_closures( string $php_expr, array $store, string $ns ) {
		// Match every $__st[...] / $__ctx[...] access, including chained
		// segments like $__st['a']['b']['c']. The capture group is the
		// literal source of the access so we can splice the replacement back
		// into the expression.
		$pattern = '/(\$__st|\$__ctx)((?:\[[\'"][a-zA-Z_][a-zA-Z0-9_]*[\'"]\])*)/';

		$had_failure = false;
		$callback    = function ( $m ) use ( $store, $ns, &$had_failure ) {
			// Determine the root ('state' or 'context').
			if ( '$__st' === $m[1] ) {
				$cur      = $store['state'] ?? array();
				$root_key = 'state';
			} else {
				$cur      = $store['context'] ?? array();
				$root_key = 'context';
			}

			// Parse the bracket-segment chain into segment strings.
			$segments = array( $root_key );
			if ( '' !== $m[2] ) {
				preg_match_all( "/\[(['\"])([a-zA-Z_][a-zA-Z0-9_]*)\\1\]/", $m[2], $seg_matches );
				foreach ( $seg_matches[2] as $seg ) {
					$segments[] = $seg;
				}
			}

			// Walk segments resolving the value, invoking Closures via the
			// shared helper (so derived-state recording works the same as the
			// dotted-path branch). resolve_path_with_closures() expects the
			// root to include the first segment's key as is — but the helper
			// starts by indexing into $cur, so pass a synthetic root that
			// contains the first segment under its key. Simpler: walk manually
			// here using the helper per-segment.
			$recorded_prefix = array( $root_key );
			foreach ( $segments as $i => $seg ) {
				if ( 0 === $i ) {
					// First segment selects state/context; $cur already set.
					continue;
				}

				if ( ( is_array( $cur ) || $cur instanceof ArrayAccess ) && isset( $cur[ $seg ] ) ) {
					$cur = $cur[ $seg ];
				} elseif ( is_object( $cur ) && isset( $cur->$seg ) ) {
					$cur = $cur->$seg;
				} else {
					$cur = null;
					break;
				}

				// The current segment is now part of the resolved path prefix.
				$recorded_prefix[] = $seg;

				// If the current value is a Closure, invoke it the same way
				// the dotted-path branch does: push the namespace, call,
				// record the path prefix, pop the namespace. Repeat while a
				// closure returns another closure so nested closure chains are
				// fully resolved before the next path segment is accessed.
				while ( $cur instanceof Closure ) {
					// The prefix up to and including the current segment,
					// matching the dotted-path branch's recording semantics
					// exactly (e.g. state.nested for state.nested.flag).
					$prefix_path = implode( '.', $recorded_prefix );

					array_push( $this->namespace_stack, $ns );
					try {
						$cur = $cur();
						$this->record_derived_closure( $ns, $prefix_path );
					} catch ( Throwable $e ) {
						$had_failure = true;
						_doing_it_wrong(
							'WP_Interactivity_API::substitute_closures',
							sprintf(
								/* translators: 1: Path pointing to an Interactivity API state property, 2: Namespace for an Interactivity API store. */
								__( 'Uncaught error executing a derived state callback with path "%1$s" and namespace "%2$s".' ),
								$prefix_path,
								$ns
							),
							'6.9.0'
						);
						return 'null';
					} finally {
						array_pop( $this->namespace_stack );
					}
				}
			}

			// Encode the resolved value as a JSON literal PHP can eval. PHP's
			// json_decode of the encoded output is used so the literal is a
			// valid PHP expression (arrays become array literals via the
			// decoded form wrapped in var_export-style). Actually json_encode
			// produces valid PHP for scalars/objects/arrays (true, false,
			// null, numbers, strings, arrays) — PHP's syntax matches JSON for
			// these. The only edge case is resources and unsupported types.
			$json = wp_json_encode( $cur );
			if ( false === $json ) {
				// Not JSON-encodable (resource, recursion, etc.). Bail the
				// entire expression so the client handles it rather than
				// partially evaluating the expression against a fabricated
				// null value.
				$had_failure = true;
				return 'null';
			}
			return $json;
		};

		try {
			$result = preg_replace_callback( $pattern, $callback, $php_expr );
		} catch ( \Throwable $e ) {
			return null; // Unsupported — caller falls back to client.
		}

		if ( null === $result ) {
			return null;
		}

		if ( $had_failure ) {
			return null;
		}

		return $result;
	}

	/**
	 * Parse the directive name to extract the following parts:
	 * - Prefix: The main directive name without "data-wp-".
	 * - Suffix: An optional suffix used during directive processing, extracted after the first double hyphen "--".
	 * - Unique ID: An optional unique identifier, extracted after the first triple hyphen "---".
	 *
	 * This function has an equivalent version for the client side.
	 * See `parseDirectiveName` in https://github.com/WordPress/gutenberg/blob/trunk/packages/interactivity/src/vdom.ts.:
	 *
	 * See examples in the function unit tests `test_parse_directive_name`.
	 *
	 * @since 6.9.0
	 *
	 * @param string $directive_name The directive attribute name.
	 * @return array An array containing the directive prefix, optional suffix, and optional unique ID.
	 */
	private function parse_directive_name( string $directive_name ): ?array {
		// Remove the first 8 characters (assumes "data-wp-" prefix)
		$name = substr( $directive_name, 8 );

		// Check for invalid characters (anything not a-z, 0-9, -, or _)
		if ( preg_match( '/[^a-z0-9\-_]/i', $name ) ) {
			return null;
		}

		// Find the first occurrence of '--' to separate the prefix
		$suffix_index = strpos( $name, '--' );

		if ( false === $suffix_index ) {
			return array(
				'prefix'    => $name,
				'suffix'    => null,
				'unique_id' => null,
			);
		}

		$prefix    = substr( $name, 0, $suffix_index );
		$remaining = substr( $name, $suffix_index );

		// If remaining starts with '---' but not '----', it's a unique_id
		if ( '---' === substr( $remaining, 0, 3 ) && '-' !== ( $remaining[3] ?? '' ) ) {
			return array(
				'prefix'    => $prefix,
				'suffix'    => null,
				'unique_id' => '---' !== $remaining ? substr( $remaining, 3 ) : null,
			);
		}

		// Otherwise, remove the first two dashes for a potential suffix
		$suffix = substr( $remaining, 2 );

		// Look for '---' in the suffix for a unique_id
		$unique_id_index = strpos( $suffix, '---' );

		if ( false !== $unique_id_index && '-' !== ( $suffix[ $unique_id_index + 3 ] ?? '' ) ) {
			$unique_id = substr( $suffix, $unique_id_index + 3 );
			$suffix    = substr( $suffix, 0, $unique_id_index );
			return array(
				'prefix'    => $prefix,
				'suffix'    => empty( $suffix ) ? null : $suffix,
				'unique_id' => empty( $unique_id ) ? null : $unique_id,
			);
		}

		return array(
			'prefix'    => $prefix,
			'suffix'    => empty( $suffix ) ? null : $suffix,
			'unique_id' => null,
		);
	}

	/**
	 * Parses and extracts the namespace and reference path from the given
	 * directive attribute value.
	 *
	 * If the value doesn't contain an explicit namespace, it returns the
	 * default one. If the value contains a JSON object instead of a reference
	 * path, the function tries to parse it and return the resulting array. If
	 * the value contains strings that represent booleans ("true" and "false"),
	 * numbers ("1" and "1.2") or "null", the function also transform them to
	 * regular booleans, numbers and `null`.
	 *
	 * Example:
	 *
	 *     extract_directive_value( 'actions.foo', 'myPlugin' )                      => array( 'myPlugin', 'actions.foo' )
	 *     extract_directive_value( 'otherPlugin::actions.foo', 'myPlugin' )         => array( 'otherPlugin', 'actions.foo' )
	 *     extract_directive_value( '{ "isOpen": false }', 'myPlugin' )              => array( 'myPlugin', array( 'isOpen' => false ) )
	 *     extract_directive_value( 'otherPlugin::{ "isOpen": false }', 'myPlugin' ) => array( 'otherPlugin', array( 'isOpen' => false ) )
	 *
	 * @since 6.5.0
	 *
	 * @param string|true $directive_value   The directive attribute value. It can be `true` when it's a boolean
	 *                                       attribute.
	 * @param string|null $default_namespace Optional. The default namespace if none is explicitly defined.
	 * @return array An array containing the namespace in the first item and the JSON, the reference path, or null on the
	 *               second item.
	 */
	private function extract_directive_value( $directive_value, $default_namespace = null ): array {
		if ( empty( $directive_value ) || is_bool( $directive_value ) ) {
			return array( $default_namespace, null );
		}

		// Replaces the value and namespace if there is a namespace in the value.
		if ( 1 === preg_match( '/^([\w\-_\/]+)::./', $directive_value ) ) {
			list($default_namespace, $directive_value) = explode( '::', $directive_value, 2 );
		}

		/*
		 * Tries to decode the value as a JSON object. If it fails and the value
		 * isn't `null`, it returns the value as it is. Otherwise, it returns the
		 * decoded JSON or null for the string `null`.
		 */
		$decoded_json = json_decode( $directive_value, true );
		if ( null !== $decoded_json || 'null' === $directive_value ) {
			$directive_value = $decoded_json;
		}

		return array( $default_namespace, $directive_value );
	}

	/**
	 * Parse the HTML element and get all the valid directives with the given prefix.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p      The directives processor instance.
	 * @param string                                    $prefix The directive prefix to filter by.
	 * @return array An array of entries containing the directive namespace, value, suffix, and unique ID.
	 */
	private function get_directive_entries( WP_Interactivity_API_Directives_Processor $p, string $prefix ) {
		$directive_attributes = $p->get_attribute_names_with_prefix( 'data-wp-' . $prefix );
		$entries              = array();
		foreach ( $directive_attributes as $attribute_name ) {
			[ 'prefix' => $attr_prefix, 'suffix' => $suffix, 'unique_id' => $unique_id] = $this->parse_directive_name( $attribute_name );
			// Ensure it is the desired directive.
			if ( $prefix !== $attr_prefix ) {
				continue;
			}
			list( $namespace, $value ) = $this->extract_directive_value( $p->get_attribute( $attribute_name ), end( $this->namespace_stack ) );
			$entries[]                 = array(
				'namespace' => $namespace,
				'value'     => $value,
				'suffix'    => $suffix,
				'unique_id' => $unique_id,
			);
		}
		// Sort directive entries to ensure stable ordering with the client.
		// Put nulls first, then sort by suffix and finally by uniqueIds.
		usort(
			$entries,
			function ( $a, $b ) {
				$a_suffix = $a['suffix'] ?? '';
				$b_suffix = $b['suffix'] ?? '';
				if ( $a_suffix !== $b_suffix ) {
					return $a_suffix <=> $b_suffix;
				}
				$a_id = $a['unique_id'] ?? '';
				$b_id = $b['unique_id'] ?? '';
				return $a_id <=> $b_id;
			}
		);
		return $entries;
	}

	/**
	 * Transforms a kebab-case string to camelCase.
	 *
	 * @since 6.5.0
	 *
	 * @param string $str The kebab-case string to transform to camelCase.
	 * @return string The transformed camelCase string.
	 */
	private function kebab_to_camel_case( string $str ): string {
		return lcfirst(
			preg_replace_callback(
				'/(-)([a-z])/',
				function ( $matches ) {
					return strtoupper( $matches[2] );
				},
				strtolower( rtrim( $str, '-' ) )
			)
		);
	}

	/**
	 * Processes the `data-wp-interactive` directive.
	 *
	 * It adds the default store namespace defined in the directive value to the
	 * stack so that it's available for the nested interactivity elements.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p    The directives processor instance.
	 * @param string                                    $mode Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_interactive_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		// When exiting tags, it removes the last namespace from the stack.
		if ( 'exit' === $mode ) {
			array_pop( $this->namespace_stack );
			return;
		}

		// Tries to decode the `data-wp-interactive` attribute value.
		$attribute_value = $p->get_attribute( 'data-wp-interactive' );

		/*
		 * Pushes the newly defined namespace or the current one if the
		 * `data-wp-interactive` definition was invalid or does not contain a
		 * namespace. It does so because the function pops out the current namespace
		 * from the stack whenever it finds a `data-wp-interactive`'s closing tag,
		 * independently of whether the previous `data-wp-interactive` definition
		 * contained a valid namespace.
		 */
		$new_namespace = null;
		if ( is_string( $attribute_value ) && ! empty( $attribute_value ) ) {
			$decoded_json = json_decode( $attribute_value, true );
			if ( is_array( $decoded_json ) ) {
				$new_namespace = $decoded_json['namespace'] ?? null;
			} else {
				$new_namespace = $attribute_value;
			}
		}
		$this->namespace_stack[] = ( $new_namespace && 1 === preg_match( '/^([\w\-_\/]+)/', $new_namespace ) )
			? $new_namespace
			: end( $this->namespace_stack );
	}

	/**
	 * Processes the `data-wp-context` directive.
	 *
	 * It adds the context defined in the directive value to the stack so that
	 * it's available for the nested interactivity elements.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_context_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		// When exiting tags, it removes the last context from the stack.
		if ( 'exit' === $mode ) {
			array_pop( $this->context_stack );
			return;
		}

		$entries = $this->get_directive_entries( $p, 'context' );
		$context = end( $this->context_stack ) !== false ? end( $this->context_stack ) : array();
		foreach ( $entries as $entry ) {
			if ( null !== $entry['suffix'] ) {
				continue;
			}

			$context = array_replace_recursive(
				$context,
				array( $entry['namespace'] => is_array( $entry['value'] ) ? $entry['value'] : array() )
			);
		}
		$this->context_stack[] = $context;
	}

	/**
	 * Processes the `data-wp-bind` directive.
	 *
	 * It updates or removes the bound attributes based on the evaluation of its
	 * associated reference.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_bind_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		if ( 'enter' === $mode ) {
			$entries = $this->get_directive_entries( $p, 'bind' );
			foreach ( $entries as $entry ) {
				if ( empty( $entry['suffix'] ) || null !== $entry['unique_id'] ) {
						continue;
				}

				// Skip if the suffix is an event handler.
				if ( str_starts_with( $entry['suffix'], 'on' ) ) {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							/* translators: %s: The directive, e.g. data-wp-on--click. */
							__( 'Binding event handler attributes is not supported. Please use "%s" instead.' ),
							esc_attr( 'data-wp-on--' . substr( $entry['suffix'], 2 ) )
						),
						'6.9.2'
					);
					continue;
				}

				$result = $this->evaluate( $entry );

				if (
					null !== $result &&
					(
						false !== $result ||
						( strlen( $entry['suffix'] ) > 5 && '-' === $entry['suffix'][4] )
					)
				) {
					/*
					 * If the result of the evaluation is a boolean and the attribute is
					 * `aria-` or `data-, convert it to a string "true" or "false". It
					 * follows the exact same logic as Preact because it needs to
					 * replicate what Preact will later do in the client:
					 * https://github.com/preactjs/preact/blob/ea49f7a0f9d1ff2c98c0bdd66aa0cbc583055246/src/diff/props.js#L131C24-L136
					 */
					if (
						is_bool( $result ) &&
						( strlen( $entry['suffix'] ) > 5 && '-' === $entry['suffix'][4] )
					) {
						$result = $result ? 'true' : 'false';
					}
					$p->set_attribute( $entry['suffix'], $result );
				} else {
					$p->remove_attribute( $entry['suffix'] );
				}
			}
		}
	}

	/**
	 * Processes the `data-wp-class` directive.
	 *
	 * It adds or removes CSS classes in the current HTML element based on the
	 * evaluation of its associated references.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_class_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		if ( 'enter' === $mode ) {
			$entries = $this->get_directive_entries( $p, 'class' );
			foreach ( $entries as $entry ) {
				if ( empty( $entry['suffix'] ) ) {
					continue;
				}
				$class_name = isset( $entry['unique_id'] ) && $entry['unique_id']
					? "{$entry['suffix']}---{$entry['unique_id']}"
					: $entry['suffix'];

				if ( empty( $class_name ) ) {
					return;
				}

				$result = $this->evaluate( $entry );

				if ( $result ) {
					$p->add_class( $class_name );
				} else {
					$p->remove_class( $class_name );
				}
			}
		}
	}

	/**
	 * Processes the `data-wp-style` directive.
	 *
	 * It updates the style attribute value of the current HTML element based on
	 * the evaluation of its associated references.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_style_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		if ( 'enter' === $mode ) {
			$entries = $this->get_directive_entries( $p, 'style' );
			foreach ( $entries as $entry ) {
				$style_property = $entry['suffix'];
				if ( empty( $style_property ) || null !== $entry['unique_id'] ) {
					continue;
				}

				$style_property_value  = $this->evaluate( $entry );
				$style_attribute_value = $p->get_attribute( 'style' );
				$style_attribute_value = ( $style_attribute_value && ! is_bool( $style_attribute_value ) ) ? $style_attribute_value : '';

				/*
				 * Checks first if the style property is not falsy and the style
				 * attribute value is not empty because if it is, it doesn't need to
				 * update the attribute value.
				 */
				if ( $style_property_value || $style_attribute_value ) {
					$style_attribute_value = $this->merge_style_property( $style_attribute_value, $style_property, $style_property_value );
					/*
					 * If the style attribute value is not empty, it sets it. Otherwise,
					 * it removes it.
					 */
					if ( ! empty( $style_attribute_value ) ) {
						$p->set_attribute( 'style', $style_attribute_value );
					} else {
						$p->remove_attribute( 'style' );
					}
				}
			}
		}
	}

	/**
	 * Merges an individual style property in the `style` attribute of an HTML
	 * element, updating or removing the property when necessary.
	 *
	 * If a property is modified, the old one is removed and the new one is added
	 * at the end of the list.
	 *
	 * @since 6.5.0
	 *
	 * Example:
	 *
	 *     merge_style_property( 'color:green;', 'color', 'red' )      => 'color:red;'
	 *     merge_style_property( 'background:green;', 'color', 'red' ) => 'background:green;color:red;'
	 *     merge_style_property( 'color:green;', 'color', null )       => ''
	 *
	 * @param string            $style_attribute_value The current style attribute value.
	 * @param string            $style_property_name   The style property name to set.
	 * @param string|false|null $style_property_value  The value to set for the style property. With false, null or an
	 *                                                 empty string, it removes the style property.
	 * @return string The new style attribute value after the specified property has been added, updated or removed.
	 */
	private function merge_style_property( string $style_attribute_value, string $style_property_name, $style_property_value ): string {
		$style_assignments    = explode( ';', $style_attribute_value );
		$result               = array();
		$style_property_value = ! empty( $style_property_value ) ? rtrim( trim( $style_property_value ), ';' ) : null;
		$new_style_property   = $style_property_value ? $style_property_name . ':' . $style_property_value . ';' : '';

		// Generates an array with all the properties but the modified one.
		foreach ( $style_assignments as $style_assignment ) {
			if ( empty( trim( $style_assignment ) ) ) {
				continue;
			}
			list( $name, $value ) = explode( ':', $style_assignment );
			if ( trim( $name ) !== $style_property_name ) {
				$result[] = trim( $name ) . ':' . trim( $value ) . ';';
			}
		}

		// Adds the new/modified property at the end of the list.
		$result[] = $new_style_property;

		return implode( '', $result );
	}

	/**
	 * Processes the `data-wp-text` directive.
	 *
	 * It updates the inner content of the current HTML element based on the
	 * evaluation of its associated reference.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_text_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		if ( 'enter' === $mode ) {
			$entries     = $this->get_directive_entries( $p, 'text' );
			$valid_entry = null;
			// Get the first valid `data-wp-text` entry without suffix or unique ID.
			foreach ( $entries as $entry ) {
				if ( null === $entry['suffix'] && null === $entry['unique_id'] && ! empty( $entry['value'] ) ) {
					$valid_entry = $entry;
					break;
				}
			}
			if ( null === $valid_entry ) {
				return;
			}
			$result = $this->evaluate( $valid_entry );

			/*
			 * Follows the same logic as Preact in the client and only changes the
			 * content if the value is a string or a number. Otherwise, it removes the
			 * content.
			 */
			if ( is_string( $result ) || is_numeric( $result ) ) {
				$p->set_content_between_balanced_tags( esc_html( $result ) );
			} else {
				$p->set_content_between_balanced_tags( '' );
			}
		}
	}

	/**
	 * Returns the CSS styles for animating the top loading bar in the router.
	 *
	 * @since 6.5.0
	 *
	 * @return string The CSS styles for the router's top loading bar animation.
	 */
	private function get_router_animation_styles(): string {
		return <<<CSS
			.wp-interactivity-router-loading-bar {
				position: fixed;
				top: 0;
				left: 0;
				margin: 0;
				padding: 0;
				width: 100vw;
				max-width: 100vw !important;
				height: 4px;
				background-color: #000;
				opacity: 0
			}
			.wp-interactivity-router-loading-bar.start-animation {
				animation: wp-interactivity-router-loading-bar-start-animation 30s cubic-bezier(0.03, 0.5, 0, 1) forwards
			}
			.wp-interactivity-router-loading-bar.finish-animation {
				animation: wp-interactivity-router-loading-bar-finish-animation 300ms ease-in
			}
			@keyframes wp-interactivity-router-loading-bar-start-animation {
				0% { transform: scaleX(0); transform-origin: 0 0; opacity: 1 }
				100% { transform: scaleX(1); transform-origin: 0 0; opacity: 1 }
			}
			@keyframes wp-interactivity-router-loading-bar-finish-animation {
				0% { opacity: 1 }
				50% { opacity: 1 }
				100% { opacity: 0 }
			}
CSS;
	}

	/**
	 * Deprecated.
	 *
	 * @since 6.5.0
	 * @deprecated 6.7.0 Use {@see WP_Interactivity_API::print_router_markup} instead.
	 */
	public function print_router_loading_and_screen_reader_markup() {
		_deprecated_function( __METHOD__, '6.7.0', 'WP_Interactivity_API::print_router_markup' );

		// Call the new method.
		$this->print_router_markup();
	}

	/**
	 * Outputs markup for the @wordpress/interactivity-router script module.
	 *
	 * This method prints a div element representing a loading bar visible during
	 * navigation.
	 *
	 * @since 6.7.0
	 */
	public function print_router_markup() {
		echo <<<HTML
			<div
				class="wp-interactivity-router-loading-bar"
				data-wp-interactive="core/router/private"
				data-wp-class--start-animation="state.navigation.hasStarted"
				data-wp-class--finish-animation="state.navigation.hasFinished"
			></div>
HTML;
	}

	/**
	 * Processes the `data-wp-router-region` directive.
	 *
	 * It renders in the footer a set of HTML elements to notify users about
	 * client-side navigations. More concretely, the elements added are 1) a
	 * top loading bar to visually inform that a navigation is in progress
	 * and 2) an `aria-live` region for accessible navigation announcements.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_router_region_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		if ( 'enter' === $mode && ! $this->has_processed_router_region ) {
			$this->has_processed_router_region = true;

			// Initializes the `state.url` property from the server.
			$this->state(
				'core/router',
				array(
					'url' => get_self_link(),
				)
			);

			// Enqueues as an inline style.
			wp_register_style( 'wp-interactivity-router-animations', false );
			wp_add_inline_style( 'wp-interactivity-router-animations', $this->get_router_animation_styles() );
			wp_enqueue_style( 'wp-interactivity-router-animations' );

			// Adds the necessary markup to the footer.
			add_action( 'wp_footer', array( $this, 'print_router_markup' ) );
		}
	}

	/**
	 * Processes the `data-wp-each` directive.
	 *
	 * This directive gets an array passed as reference and iterates over it
	 * generating new content for each item based on the inner markup of the
	 * `template` tag.
	 *
	 * @since 6.5.0
	 * @since 6.9.0 Include the list path in the rendered `data-wp-each-child` directives.
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 * @param array                                     $tag_stack       The reference to the tag stack.
	 */
	private function data_wp_each_processor( WP_Interactivity_API_Directives_Processor $p, string $mode, array &$tag_stack ) {
		if ( 'enter' === $mode && 'TEMPLATE' === $p->get_tag() ) {
			$entries = $this->get_directive_entries( $p, 'each' );
			if ( count( $entries ) > 1 || empty( $entries ) ) {
				// There should be only one `data-wp-each` directive per template tag.
				return;
			}
			$entry = $entries[0];
			if ( null !== $entry['unique_id'] ) {
				return;
			}
			$item_name = isset( $entry['suffix'] ) ? $this->kebab_to_camel_case( $entry['suffix'] ) : 'item';
			$result    = $this->evaluate( $entry );

			// Gets the content between the template tags and leaves the cursor in the closer tag.
			$inner_content = $p->get_content_between_balanced_template_tags();

			// Checks if there is a manual server-side directive processing.
			$template_end = 'data-wp-each: template end';
			$p->set_bookmark( $template_end );
			$p->next_tag();
			$manual_sdp = $p->get_attribute( 'data-wp-each-child' );
			$p->seek( $template_end ); // Rewinds to the template closer tag.
			$p->release_bookmark( $template_end );

			/*
			 * It doesn't process in these situations:
			 * - Manual server-side directive processing.
			 * - Empty or non-array values.
			 * - Associative arrays because those are deserialized as objects in JS.
			 * - Templates that contain top-level texts because those texts can't be
			 *   identified and removed in the client.
			 */
			if (
				$manual_sdp ||
				empty( $result ) ||
				! is_array( $result ) ||
				! array_is_list( $result ) ||
				! str_starts_with( trim( $inner_content ), '<' ) ||
				! str_ends_with( trim( $inner_content ), '>' )
			) {
				array_pop( $tag_stack );
				return;
			}

			// Processes the inner content for each item of the array.
			$processed_content = '';
			foreach ( $result as $item ) {
				// Creates a new context that includes the current item of the array.
				$this->context_stack[] = array_replace_recursive(
					end( $this->context_stack ) !== false ? end( $this->context_stack ) : array(),
					array( $entry['namespace'] => array( $item_name => $item ) )
				);

				// Processes the inner content with the new context.
				$processed_item = $this->_process_directives( $inner_content );

				if ( null === $processed_item ) {
					// If the HTML is unbalanced, stop processing it.
					array_pop( $this->context_stack );
					return;
				}

				/*
				 * Adds the `data-wp-each-child` directive to each top-level tag
				 * rendered by this `data-wp-each` directive. The value is the
				 * `data-wp-each` directive's namespace and path.
				 *
				 * Nested `data-wp-each` directives could render
				 * `data-wp-each-child` elements at the top level as well, and
				 * they should be overwritten.
				 *
				 * @since 6.9.0
				 */
				$i = new WP_Interactivity_API_Directives_Processor( $processed_item );
				while ( $i->next_tag() ) {
					$i->set_attribute( 'data-wp-each-child', $entry['namespace'] . '::' . $entry['value'] );
					$i->next_balanced_tag_closer_tag();
				}
				$processed_content .= $i->get_updated_html();

				// Removes the current context from the stack.
				array_pop( $this->context_stack );
			}

			// Appends the processed content after the tag closer of the template.
			$p->append_content_after_template_tag_closer( $processed_content );

			// Pops the last tag because it skipped the closing tag of the template tag.
			array_pop( $tag_stack );
		}
	}
}
