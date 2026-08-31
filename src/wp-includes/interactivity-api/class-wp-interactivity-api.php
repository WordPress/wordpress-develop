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
	 * @var array<string, string>
	 * @phpstan-var array{
	 *     'data-wp-interactive': 'data_wp_interactive_processor',
	 *     'data-wp-router-region': 'data_wp_router_region_processor',
	 *     'data-wp-context': 'data_wp_context_processor',
	 *     'data-wp-bind': 'data_wp_bind_processor',
	 *     'data-wp-class': 'data_wp_class_processor',
	 *     'data-wp-style': 'data_wp_style_processor',
	 *     'data-wp-text': 'data_wp_text_processor',
	 *     'data-wp-each': 'data_wp_each_processor',
	 * }
	 */
	private static array $directive_processors = array(
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
	 * Flag that indicates whether all the blocks rendered on the page support
	 * client-side navigation.
	 *
	 * It starts as `true` and it is set to `false` as soon as a block that does
	 * not declare support for client-side navigation is rendered. It is used to
	 * decide whether the server-generated style assets can be marked with the
	 * `data-wp-router-managed` attribute.
	 *
	 * @since 7.2.0
	 * @var bool
	 */
	private $all_blocks_support_client_navigation = true;

	/**
	 * Flag that indicates whether the template enhancement output buffer started
	 * by core is active for the current request.
	 *
	 * It is set from the {@see 'wp_template_enhancement_output_buffer_started'}
	 * action. When it is `true`, the `data-wp-router-managed` attribute is added
	 * through the {@see 'wp_template_enhancement_output_buffer'} filter and no
	 * additional output buffer is needed.
	 *
	 * @since 7.2.0
	 * @var bool
	 */
	private $template_output_buffer_started = false;

	/**
	 * Flag that indicates whether starting the fallback output buffer used to add
	 * the `data-wp-router-managed` attribute has already been considered.
	 *
	 * @since 7.2.0
	 * @var bool
	 */
	private $router_managed_output_buffer_attempted = false;

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
	 * An entry is the namespace the directive defined. It is `false` instead when
	 * the directive did not define a usable one — the attribute was empty, or its
	 * JSON held no `namespace`, or the namespace did not match the accepted
	 * characters — and no enclosing `data-wp-interactive` was in effect to inherit
	 * from. An entry is pushed either way, because one is popped for every closing
	 * tag regardless of what the directive contained, so `false` is what stands in
	 * for "no namespace here" and keeps the stack balanced.
	 *
	 * @since 6.6.0
	 * @var array<string|false>|null
	 * @phpstan-var list<string|false>|null
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
	 * @since 7.2.0 Tracks the page-wide client-side navigation support and registers the hooks that decide how the
	 *              `data-wp-router-managed` attribute is added to the server-generated style assets.
	 */
	public function add_hooks() {
		add_filter( 'script_module_data_@wordpress/interactivity', array( $this, 'filter_script_module_interactivity_data' ) );
		add_filter( 'script_module_data_@wordpress/interactivity-router', array( $this, 'filter_script_module_interactivity_router_data' ) );
		add_filter( 'wp_script_attributes', array( $this, 'add_load_on_client_navigation_attribute_to_script_modules' ) );

		if ( ! is_admin() ) {
			/*
			 * The tracked support is only read on the front end, so there is no
			 * need to inspect every rendered block in admin requests.
			 */
			add_filter( 'render_block_data', array( $this, 'filter_render_block_data_client_navigation_support' ) );

			/*
			 * The `wp_template_enhancement_output_buffer` filter that adds the
			 * `data-wp-router-managed` attribute is not registered here on purpose.
			 * Merely having that filter registered when the template is included
			 * makes core start the template enhancement output buffer for every
			 * front-end request, which disables response streaming even on pages
			 * that never get the attribute. See
			 * `wp_should_output_buffer_template_for_enhancement()`.
			 *
			 * Instead, the filter is added from
			 * `WP_Interactivity_API::data_wp_router_region_processor()`, i.e. only
			 * once a router region has actually been processed, and
			 * `WP_Interactivity_API::maybe_start_router_managed_output_buffer()`
			 * starts a dedicated output buffer when core's one is not running.
			 */
			add_action( 'wp_template_enhancement_output_buffer_started', array( $this, 'mark_template_output_buffer_started' ) );

			/*
			 * The lowest possible priority guarantees that nothing hooked to
			 * `wp_head` prints a style asset before the fallback output buffer
			 * starts. Marking only part of the style assets of a page would be
			 * worse than not marking any of them, because the router would treat
			 * the unmarked ones as client-injected and preserve them across every
			 * client-side navigation.
			 */
			add_action( 'wp_head', array( $this, 'maybe_start_router_managed_output_buffer' ), PHP_INT_MIN );
		}
	}

	/**
	 * Records that the template enhancement output buffer started by core is
	 * active for the current request.
	 *
	 * This method is a {@see 'wp_template_enhancement_output_buffer_started'}
	 * action callback.
	 *
	 * @since 7.2.0
	 */
	public function mark_template_output_buffer_started() {
		$this->template_output_buffer_started = true;
	}

	/**
	 * Tracks whether all the blocks rendered on the page support client-side
	 * navigation.
	 *
	 * This method is a `render_block_data` filter callback that only inspects the
	 * blocks being rendered; it always returns the parsed block unmodified. As
	 * soon as a block that does not declare support for client-side navigation is
	 * found, client-side navigation is considered unsupported for the whole page.
	 *
	 * The compatibility rules mirror the ones used by
	 * {@see block_core_query_disable_enhanced_pagination()}: blocks without a
	 * block name, i.e. freeform classic HTML, do not break compatibility, while
	 * named blocks require either `supports.interactivity` or
	 * `supports.interactivity.clientNavigation` to be `true`.
	 *
	 * @since 7.2.0
	 *
	 * @param array $parsed_block The block being rendered.
	 * @return array Returns the parsed block, unmodified.
	 */
	public function filter_render_block_data_client_navigation_support( $parsed_block ) {
		if ( ! $this->all_blocks_support_client_navigation ) {
			return $parsed_block;
		}

		if ( ! isset( $parsed_block['blockName'] ) ) {
			return $parsed_block;
		}

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $parsed_block['blockName'] );

		/*
		 * Client side navigation can be true in two states:
		 *  - supports.interactivity = true;
		 *  - supports.interactivity.clientNavigation = true;
		 */
		$supports_client_navigation = ( isset( $block_type->supports['interactivity']['clientNavigation'] ) && true === $block_type->supports['interactivity']['clientNavigation'] )
			|| ( isset( $block_type->supports['interactivity'] ) && true === $block_type->supports['interactivity'] );

		if ( ! $supports_client_navigation ) {
			$this->all_blocks_support_client_navigation = false;
		}

		return $parsed_block;
	}

	/**
	 * Adds the `data-wp-router-managed` attribute to all the style assets of the
	 * page.
	 *
	 * This method is a `wp_template_enhancement_output_buffer` filter callback, so
	 * it receives the complete server-generated markup. Working on the final
	 * buffer is what guarantees full-page coverage: every `<style>` element and
	 * every `<link rel="stylesheet">` element is marked regardless of how or when
	 * it was printed. The only exceptions are the style assets contained in
	 * `noscript` and `template` elements, which are not rendered as part of the
	 * document.
	 *
	 * The filter is not registered upfront. It is added from
	 * {@see WP_Interactivity_API::data_wp_router_region_processor()} when a router
	 * region is processed, because registering it earlier would force core to
	 * start the template enhancement output buffer on every front-end request. See
	 * {@see WP_Interactivity_API::add_hooks()}.
	 *
	 * The attribute lets the Interactivity API router tell server-rendered style
	 * assets apart from the ones injected later by JavaScript, so the latter can
	 * be preserved when the head is diffed during a client-side navigation.
	 *
	 * The attribute is only added when all the blocks rendered on the page support
	 * client-side navigation, at least one router region has been processed, and
	 * client-side navigation has not been disabled.
	 *
	 * @since 7.2.0
	 *
	 * @param string|mixed $buffer The template output buffer.
	 * @return string|mixed The template output buffer, with the attribute added to the style assets.
	 */
	public function filter_template_output_buffer_add_router_managed_attribute( $buffer ) {
		if ( ! is_string( $buffer ) ) {
			return $buffer;
		}

		if ( ! $this->should_add_router_managed_attribute() ) {
			return $buffer;
		}

		return $this->add_router_managed_attribute_to_style_assets( $buffer );
	}

	/**
	 * Starts an output buffer to add the `data-wp-router-managed` attribute to
	 * the style assets of the page when core's template enhancement output buffer
	 * is not running.
	 *
	 * This method is a {@see 'wp_head'} action callback registered with the lowest
	 * possible priority, so the buffer captures every style asset printed from
	 * that point on: the rest of the HEAD, the BODY and the footer.
	 *
	 * The buffer is only started when it is really needed. Core starts its own
	 * template enhancement output buffer whenever something requires it, and in
	 * that case the attribute is added through the
	 * {@see 'wp_template_enhancement_output_buffer'} filter instead.
	 *
	 * This fallback relies on the block template canvas, which renders the whole
	 * template, including the footer template parts, into a string before printing
	 * the doctype and firing `wp_head`. In that flow all the directives have
	 * already been processed by the time this method runs, so the conditions
	 * checked here are final and the markup printed before the buffer starts
	 * cannot contain style assets.
	 *
	 * In flows where blocks are rendered after `wp_head` instead, i.e. classic
	 * themes, block themes serving a PHP template, or a PHP template served
	 * through the {@see 'template_include'} filter by a plugin, no router region
	 * has been processed at this point, so this method intentionally does nothing.
	 * Those flows are covered by the
	 * {@see 'wp_template_enhancement_output_buffer'} filter as long as core's
	 * template enhancement output buffer is active, which classic themes enable by
	 * default since WordPress 6.9. When it is not active, e.g. a site opting out
	 * through the {@see 'wp_should_output_buffer_template_for_enhancement'} filter
	 * or a block theme serving a PHP template, the attribute is not emitted at
	 * all. That is a deliberate limitation: not marking any style asset is safe,
	 * whereas marking only some of them is not.
	 *
	 * @since 7.2.0
	 */
	public function maybe_start_router_managed_output_buffer() {
		if ( $this->router_managed_output_buffer_attempted || $this->template_output_buffer_started ) {
			return;
		}

		$this->router_managed_output_buffer_attempted = true;

		if ( ! $this->should_add_router_managed_attribute() ) {
			return;
		}

		/*
		 * The buffer is not closed explicitly. It is flushed through its callback
		 * by `wp_ob_end_flush_all()` on shutdown, like the one started by
		 * `wp_start_template_enhancement_output_buffer()`.
		 *
		 * As a side effect, third-party code that captures the output of `wp_head`
		 * with its own `ob_start()` and `ob_get_clean()` pair consumes this buffer
		 * instead of its own, because this one is started from within `wp_head`.
		 * The markup is not lost, but it is returned unmarked. This is an accepted
		 * tradeoff of covering the whole page from a single buffer.
		 */
		ob_start(
			array( $this, 'finalize_router_managed_output_buffer' ),
			/*
			 * An unlimited chunk size, so the entire output is passed to the
			 * callback at once and every style asset can be marked no matter where
			 * it was printed.
			 */
			0,
			/*
			 * The `PHP_OUTPUT_HANDLER_FLUSHABLE` flag is omitted so a `flush()`
			 * call cannot send a fragment of the page through the callback and
			 * leave the rest of the markup unprocessed. The buffer is still
			 * cleanable and removable, which is required because WordPress calls
			 * `wp_ob_end_flush_all()` before `wp_cache_close()`. Same rationale as
			 * `wp_start_template_enhancement_output_buffer()`.
			 */
			PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_FLUSHABLE
		);
	}

	/**
	 * Adds the `data-wp-router-managed` attribute to the style assets of the
	 * buffer started by {@see WP_Interactivity_API::maybe_start_router_managed_output_buffer()}.
	 *
	 * The conditions are checked again here because this callback runs when the
	 * buffer is finalized, i.e. at the very end of the request, and code running
	 * after `wp_head`, e.g. a `wp_footer` callback, may still have rendered a
	 * block without client-side navigation support or disabled client-side
	 * navigation.
	 *
	 * Unlike `wp_finalize_template_enhancement_output_buffer()`, this callback
	 * does not check the content type of the response. The buffer is only started
	 * from `wp_head`, so the response is an HTML document, and the HTML processor
	 * leaves markup without style assets untouched anyway.
	 *
	 * @since 7.2.0
	 *
	 * @param string $output The output buffer.
	 * @param int    $phase  The output buffer phase bitmask.
	 * @return string The output buffer, with the attribute added to the style assets.
	 */
	public function finalize_router_managed_output_buffer( string $output, int $phase ): string {
		// When the output is being cleaned, e.g. it is replaced with an error page, it must not be processed.
		if ( ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) !== 0 ) {
			return $output;
		}

		if ( ! $this->should_add_router_managed_attribute() ) {
			return $output;
		}

		try {
			return $this->add_router_managed_attribute_to_style_assets( $output );
		} catch ( Throwable $e ) {
			/*
			 * An exception thrown from an output buffer callback is fatal and
			 * discards the whole response, so the original output is returned
			 * unmodified instead. The page is served without the attribute, which
			 * only disables the optimization.
			 */
			return $output;
		}
	}

	/**
	 * Checks whether the style assets of the page can be marked with the
	 * `data-wp-router-managed` attribute.
	 *
	 * The attribute is only added when all the blocks rendered on the page support
	 * client-side navigation, at least one router region has been processed, and
	 * client-side navigation has not been disabled.
	 *
	 * @since 7.2.0
	 *
	 * @return bool Whether the attribute can be added to the style assets.
	 */
	private function should_add_router_managed_attribute(): bool {
		if ( ! $this->has_processed_router_region || ! $this->all_blocks_support_client_navigation ) {
			return false;
		}

		/*
		 * The configuration is read directly instead of through
		 * `WP_Interactivity_API::config()` because that method would create an
		 * empty `core/router` entry as a side effect of reading it.
		 */
		return empty( $this->config_data['core/router']['clientNavigationDisabled'] );
	}

	/**
	 * Adds the `data-wp-router-managed` attribute to every style asset found in
	 * the given markup.
	 *
	 * Every `<style>` element and every `<link rel="stylesheet">` element is
	 * marked. The only exceptions are the style assets contained in `noscript` and
	 * `template` elements, which are not rendered as part of the document.
	 *
	 * @since 7.2.0
	 *
	 * @param string $html The markup to process.
	 * @return string The markup, with the attribute added to the style assets.
	 */
	private function add_router_managed_attribute_to_style_assets( string $html ): string {
		$processor = new WP_HTML_Tag_Processor( $html );

		/*
		 * Depth of the `noscript` and `template` elements currently open. The
		 * contents of `template` elements are inert until they are cloned by
		 * JavaScript, and `noscript` elements are only rendered when scripting is
		 * disabled, so the style assets inside them must not be marked as
		 * server-generated ones.
		 */
		$inert_depth = 0;

		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag_name  = $processor->get_tag();
			$is_closer = $processor->is_tag_closer();

			if ( 'NOSCRIPT' === $tag_name || 'TEMPLATE' === $tag_name ) {
				if ( $is_closer ) {
					$inert_depth = max( 0, $inert_depth - 1 );
				} elseif ( ! $processor->has_self_closing_flag() ) {
					/*
					 * Self-closing tags are only meaningful in foreign content,
					 * i.e. inside SVG or MathML, where they have no closing tag.
					 * They are skipped so they do not leave the depth counter
					 * stuck for the rest of the document.
					 */
					++$inert_depth;
				}
				continue;
			}

			if ( $is_closer || $inert_depth > 0 ) {
				continue;
			}

			if ( 'STYLE' === $tag_name ) {
				$processor->set_attribute( 'data-wp-router-managed', true );
				continue;
			}

			if ( 'LINK' !== $tag_name ) {
				continue;
			}

			$rel = $processor->get_attribute( 'rel' );
			if ( ! is_string( $rel ) ) {
				continue;
			}

			$rel_tokens = preg_split( '/[\t\n\f\r ]+/', strtolower( $rel ), -1, PREG_SPLIT_NO_EMPTY );
			if ( is_array( $rel_tokens ) && in_array( 'stylesheet', $rel_tokens, true ) ) {
				$processor->set_attribute( 'data-wp-router-managed', true );
			}
		}

		return $processor->get_updated_html();
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

		// Checks if the reference path is preceded by a negation operator (!).
		$should_negate_value = '!' === $path[0];
		$path                = $should_negate_value ? substr( $path, 1 ) : $path;

		// Extracts the value from the store using the reference path.
		$path_segments = explode( '.', $path );
		$current       = $store;
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

			if ( $current instanceof Closure ) {
				/*
				 * This state getter's namespace is added to the stack so that
				 * `state()` or `get_config()` read that namespace when called
				 * without specifying one.
				 */
				array_push( $this->namespace_stack, $ns );
				try {
					$current = $current();

					/*
					 * Tracks derived state properties that are accessed during
					 * rendering.
					 *
					 * @since 6.9.0
					 */
					$this->derived_state_closures[ $ns ] = $this->derived_state_closures[ $ns ] ?? array();

					// Builds path for the current property and add it to tracking if not already present.
					$current_path = implode( '.', array_slice( $path_segments, 0, $index + 1 ) );
					if ( ! in_array( $current_path, $this->derived_state_closures[ $ns ], true ) ) {
						$this->derived_state_closures[ $ns ][] = $current_path;
					}
				} catch ( Throwable $e ) {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							/* translators: 1: Path pointing to an Interactivity API state property, 2: Namespace for an Interactivity API store. */
							__( 'Uncaught error executing a derived state callback with path "%1$s" and namespace "%2$s".' ),
							$path,
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

		// Returns the opposite if it contains a negation operator (!).
		return $should_negate_value ? ! $current : $current;
	}

	/**
	 * Parse the directive name to extract the following parts:
	 * - Prefix: The main directive name without "data-wp-". It cannot begin with a hyphen.
	 * - Suffix: An optional suffix used during directive processing, extracted after the first double hyphen "--".
	 * - Unique ID: An optional unique identifier, extracted after the first triple hyphen "---".
	 *
	 * This function has an equivalent version for the client side.
	 * See `parseDirectiveName` in https://github.com/WordPress/gutenberg/blob/trunk/packages/interactivity/src/vdom.ts:
	 *
	 * An empty suffix or unique ID is normalized to null, but the string "0" is preserved. The
	 * client's `|| null` discards only the empty string, since every non-empty string is truthy in
	 * JavaScript. Do not use empty() for these checks: it would discard "0" and diverge from the
	 * client.
	 *
	 * @see Tests_Interactivity_API_WpInteractivityAPI::test_parse_directive_name() for examples in the test inputs.
	 *
	 * @since 6.9.0
	 *
	 * @param string $directive_name The directive attribute name.
	 * @return array|null An array containing the directive prefix, optional suffix, and optional unique ID, or null if the directive name cannot be parsed.
	 * @phpstan-return array{
	 *     prefix: non-empty-string,
	 *     suffix: non-empty-string|null,
	 *     unique_id: non-empty-string|null,
	 * }|null
	 */
	private function parse_directive_name( string $directive_name ): ?array {
		// Remove the first 8 characters (assumes "data-wp-" prefix)
		$name = (string) substr( $directive_name, 8 );

		// Ensure the name only contains valid characters (anything a-z, A-Z, 0-9, -, or _).
		if ( 1 !== preg_match( '/^[a-zA-Z0-9\-_]+$/', $name ) ) {
			return null;
		}

		// Find the first occurrence of '--' to separate the prefix.
		$suffix_index = strpos( $name, '--' );

		/*
		 * A prefix cannot begin with a hyphen, so a name which does is not a directive at all. This
		 * covers both a lone leading hyphen, as in "data-wp--bind", and a leading double hyphen, as
		 * in "data-wp---foo", where treating the hyphens as a suffix separator would instead leave
		 * the prefix empty. It also covers "data-wp----unique-id", where only a unique ID is supplied
		 * without any prefix or suffix.
		 */
		if ( 0 === $suffix_index || '-' === $name[0] ) {
			return null;
		}

		// Without a '--' the whole name is the prefix. (This naturally also means there is no unique ID after '---'.)
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
		if ( 3 === strspn( $remaining, '-' ) ) {
			$unique_id = (string) substr( $remaining, 3 );
			return array(
				'prefix'    => $prefix,
				'suffix'    => null,
				'unique_id' => '' === $unique_id ? null : $unique_id,
			);
		}

		// Otherwise, remove the first two dashes for a potential suffix
		$suffix = (string) substr( $remaining, 2 );

		// Look for '---' in the suffix for a unique_id
		$unique_id_index = strpos( $suffix, '---' );

		if ( false !== $unique_id_index && '-' !== ( $suffix[ $unique_id_index + 3 ] ?? '' ) ) {
			$unique_id = (string) substr( $suffix, $unique_id_index + 3 );
			$suffix    = (string) substr( $suffix, 0, $unique_id_index );
			return array(
				'prefix'    => $prefix,
				'suffix'    => '' === $suffix ? null : $suffix,
				'unique_id' => '' === $unique_id ? null : $unique_id,
			);
		}

		return array(
			'prefix'    => $prefix,
			'suffix'    => '' === $suffix ? null : $suffix,
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
	 * @phpstan-return array{ 0: string|null, 1: mixed }
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
	 * @phpstan-return list<array{
	 *     namespace: string|null,
	 *     value: mixed,
	 *     suffix: string|null,
	 *     unique_id: string|null,
	 * }>
	 */
	private function get_directive_entries( WP_Interactivity_API_Directives_Processor $p, string $prefix ): array {
		$directive_attributes = $p->get_attribute_names_with_prefix( 'data-wp-' . $prefix );
		if ( null === $directive_attributes ) {
			return array();
		}

		$entries = array();
		foreach ( $directive_attributes as $attribute_name ) {
			$parsed_directive = $this->parse_directive_name( $attribute_name );
			if ( null === $parsed_directive ) {
				continue;
			}

			[ 'prefix' => $attr_prefix, 'suffix' => $suffix, 'unique_id' => $unique_id ] = $parsed_directive;
			// Ensure it is the desired directive.
			if ( $prefix !== $attr_prefix ) {
				continue;
			}
			$attribute_value = $p->get_attribute( $attribute_name );
			if ( null === $attribute_value ) {
				continue;
			}
			/*
			 * The namespace stack can hold false, which data_wp_interactive_processor() pushes for a
			 * `data-wp-interactive` whose namespace is invalid and which has no enclosing one to inherit. Only a
			 * string names a store, so anything else counts as no default namespace at all.
			 */
			$default_namespace = array_last( $this->namespace_stack ?? array() );
			if ( ! is_string( $default_namespace ) ) {
				$default_namespace = null;
			}

			list( $namespace, $value ) = $this->extract_directive_value( $attribute_value, $default_namespace );
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

			/*
			 * A context with no namespace has nothing to be stored under, so the inherited context is left as it
			 * is. Using the namespace as an array key regardless would coerce null to an empty string, which PHP
			 * 8.5 deprecates, and would store the context where no reference can address it anyway.
			 */
			if ( null === $entry['namespace'] ) {
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
	 * @since 7.1.0 An object is resolved to whatever it serializes to for the client, a number is formatted by the
	 *              JSON encoder, and a value which cannot be sent to the client is rejected rather than passed to
	 *              WP_HTML_Tag_Processor::set_attribute().
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p    The directives processor instance.
	 * @param string                                    $mode Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_bind_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ): void {
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

				/*
				 * An object is resolved to whatever it serializes to. When the reference points to a value stored
				 * in state or context, that is the value the client receives for it when the store is hydrated.
				 * A derived state closure is never serialized, so there the client value comes from the derived
				 * state's client-side implementation instead; the resolution is still applied so that both origins
				 * behave the same. Round-tripping through the JSON encoder rather than calling
				 * JsonSerializable::jsonSerialize() directly keeps this resolution identical to the client's,
				 * including for an object which serializes to another serializable object. When the encoding fails
				 * the object is left in place, to be reported as a usage error below. Note that it rarely does
				 * fail: wp_json_encode() retries through _wp_json_sanity_check(), which rebuilds the object from
				 * its public properties and so ignores jsonSerialize() altogether. An object whose serialized form
				 * JSON cannot represent therefore resolves to whatever that rebuild encodes to, which is what the
				 * client is sent for it as well.
				 *
				 * A throwing JsonSerializable::jsonSerialize() is caught for the same reason the value is checked
				 * at all: a binding must not be able to abort the render. An exception escaping here would leave
				 * `$context_stack` and `$namespace_stack` unrestored for every later `process_directives()` call
				 * on this instance, so the object is treated as one which failed to encode.
				 */
				if ( is_object( $result ) ) {
					try {
						$encoded = wp_json_encode( $result );
					} catch ( Throwable $e ) {
						$encoded = false;
					}
					if ( false !== $encoded ) {
						$result = json_decode( $encoded );
					}
				}

				/*
				 * Only a value which can be sent to the client may be stored in an attribute value. Strings and
				 * booleans are passed in as-is, numbers are formatted, and everything else is rejected as a usage
				 * error.
				 *
				 * An object which does not serialize to a scalar is rejected even when it defines `__toString()`,
				 * which PHP would otherwise coerce for the string parameters of the escaping functions. Its string
				 * representation is not what the client evaluates this reference to, whether that is the form
				 * serialized into the store or the return value of a derived state's client-side implementation,
				 * so the two could disagree once the directive is evaluated during hydration.
				 */
				if ( null !== $result ) {
					if ( ! is_scalar( $result ) ) {
						_doing_it_wrong(
							__METHOD__,
							sprintf(
								/* translators: %s: The attribute name. */
								__( 'Attempted to bind a non-scalar value to the "%s" attribute. Ensure the state/context property or the derived state closure resolves to a string, number, or boolean.' ),
								esc_html( $entry['suffix'] )
							),
							'7.1.0'
						);
						$result = null;
					} elseif ( is_int( $result ) || is_float( $result ) ) {
						/*
						 * A number is formatted by the JSON encoder rather than cast to string, so that the
						 * attribute value matches the number the client receives for this same reference. Casting
						 * a float is locale-dependent before PHP 8.0, and rounds to `precision` rather than to the
						 * encoder's `serialize_precision`.
						 *
						 * This closes the cases which differ in practice, not every one. A float written in
						 * exponent notation still disagrees, since PHP encodes 1e25 as `1.0e+25` where JavaScript
						 * renders it as `1e+25`, as does negative zero, and an integer above the range JavaScript
						 * can represent exactly is rounded once it reaches the client. Casting diverged on all
						 * three as well, so none is a regression.
						 */
						$encoded = wp_json_encode( $result );
						if ( JSON_ERROR_INF_OR_NAN === json_last_error() ) {
							/*
							 * The encoder only rejects INF and NAN, of which JSON can represent neither. When such
							 * a value is stored in state, the store itself also fails to encode in its entirety,
							 * and the client is sent an empty script tag in place of all of its state; only
							 * removing the value from the state resolves that. A derived state closure returning
							 * one never reaches the store, so there only the binding itself is affected.
							 */
							_doing_it_wrong(
								__METHOD__,
								sprintf(
									/* translators: %s: The attribute name. */
									__( 'Attempted to bind a non-finite number to the "%s" attribute. Ensure the state/context property or the derived state closure resolves to a finite number or a string.' ),
									esc_html( $entry['suffix'] )
								),
								'7.1.0'
							);
							$result = null;
						} else {
							$result = $encoded;
						}
					}
				}

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
			$entries = $this->get_directive_entries( $p, 'text' );

			// Get the first valid `data-wp-text` entry without suffix or unique ID.
			$valid_entry = array_find(
				$entries,
				fn( $entry ) => null === $entry['suffix'] && null === $entry['unique_id'] && ! empty( $entry['value'] )
			);

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
	 * Checks whether a `data-wp-router-region` directive has been processed.
	 *
	 * @since 7.2.0
	 *
	 * @return bool Whether a router region has been processed on the page.
	 */
	public function has_router_region(): bool {
		return $this->has_processed_router_region;
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
	 * @since 7.2.0 Registers the filter that adds the `data-wp-router-managed` attribute to the style assets.
	 *
	 * @param WP_Interactivity_API_Directives_Processor $p               The directives processor instance.
	 * @param string                                    $mode            Whether the processing is entering or exiting the tag.
	 */
	private function data_wp_router_region_processor( WP_Interactivity_API_Directives_Processor $p, string $mode ) {
		if ( 'enter' !== $mode ) {
			return;
		}

		if ( ! is_admin() ) {
			/*
			 * The filter is added here, and not in `add_hooks()`, because having
			 * it registered when the template is included is what makes core start
			 * the template enhancement output buffer, and that would disable
			 * response streaming on every front-end request. See
			 * `wp_should_output_buffer_template_for_enhancement()`.
			 *
			 * Adding it now cannot start a buffer that was not going to be
			 * started: directives are processed while the template renders, so the
			 * decision to buffer was already made at
			 * `wp_before_include_template`. The filter chain is only applied when
			 * the buffer is finalized, so attaching to an already started buffer
			 * works as expected.
			 *
			 * It is added for every processed router region, and not only for the
			 * first one, so the registration is restored if other code removed the
			 * filters of this hook in between, and so it happens once per request
			 * even though `$has_processed_router_region` is only reset when a new
			 * instance is created. Adding the same callback with the same priority
			 * more than once is a no-op.
			 *
			 * The priority is set to 20 so this filter runs after the one added by
			 * `wp_hoist_late_printed_styles()`, which uses the default priority and
			 * can move style tags into the HEAD. That way, every style asset
			 * present in the final markup gets the attribute.
			 */
			add_filter( 'wp_template_enhancement_output_buffer', array( $this, 'filter_template_output_buffer_add_router_managed_attribute' ), 20 );
		}

		if ( ! $this->has_processed_router_region ) {
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
