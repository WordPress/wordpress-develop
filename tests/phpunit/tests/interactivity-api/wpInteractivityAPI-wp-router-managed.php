<?php
/**
 * Unit tests covering the `data-wp-router-managed` attribute functionality of
 * the WP_Interactivity_API class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @coversDefaultClass WP_Interactivity_API
 *
 * @group interactivity-api
 */
class Tests_WP_Interactivity_API_WP_Router_Managed extends WP_UnitTestCase {
	/**
	 * Instance of WP_Interactivity_API.
	 *
	 * @var WP_Interactivity_API
	 */
	protected $interactivity;

	/**
	 * Original WP_Hook instance associated to `wp_footer`.
	 *
	 * @var WP_Hook
	 */
	protected $original_wp_footer;

	/**
	 * Original instance associated to `wp_styles`.
	 *
	 * @var WP_Styles
	 */
	protected $original_wp_styles;

	/**
	 * Names of the block types registered by the tests.
	 *
	 * @var string[]
	 */
	protected $registered_block_types = array();

	/**
	 * Output buffering level at the beginning of the test.
	 *
	 * @var int
	 */
	protected $original_ob_level;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactivity     = new WP_Interactivity_API();
		$this->original_ob_level = ob_get_level();

		// Removes all hooks set for `wp_footer`.
		global $wp_filter;
		$this->original_wp_footer = $wp_filter['wp_footer'];
		$wp_filter['wp_footer']   = new WP_Hook();

		// Removes all registered styles.
		$this->original_wp_styles = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles']     = new WP_Styles();
		remove_action( 'wp_default_styles', 'wp_default_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		/*
		 * Discards the output buffers left behind by the tests, e.g. when an
		 * assertion between `ob_start()` and its clean up fails.
		 */
		while ( ob_get_level() > $this->original_ob_level ) {
			ob_end_clean();
		}

		foreach ( $this->registered_block_types as $block_name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
				unregister_block_type( $block_name );
			}
		}
		$this->registered_block_types = array();

		$this->remove_hooks();

		// Restores all previous hooks set for `wp_footer`.
		global $wp_filter;
		$wp_filter['wp_footer'] = $this->original_wp_footer;

		// Restores all previous registered styles.
		$GLOBALS['wp_styles'] = $this->original_wp_styles;
		add_action( 'wp_default_styles', 'wp_default_styles' );
		add_action( 'wp_print_styles', 'print_emoji_styles' );

		parent::tear_down();
	}

	/**
	 * Processes directives while temporarily replacing the global
	 * WP_Interactivity_API instance so that global functions like
	 * `wp_interactivity_state` operate on the test instance.
	 *
	 * @param string $html The HTML to process.
	 * @return string The processed HTML.
	 */
	protected function process_directives( string $html ): string {
		global $wp_interactivity;
		$prev             = $wp_interactivity;
		$wp_interactivity = $this->interactivity;

		$result = $this->interactivity->process_directives( $html );

		$wp_interactivity = $prev;
		return $result;
	}

	/**
	 * Marks the test instance as having processed a router region.
	 */
	protected function process_router_region() {
		$this->process_directives( '<div data-wp-router-region="test">x</div>' );
	}

	/**
	 * Removes the hooks added by `WP_Interactivity_API::add_hooks()`.
	 */
	protected function remove_hooks() {
		remove_filter( 'script_module_data_@wordpress/interactivity', array( $this->interactivity, 'filter_script_module_interactivity_data' ) );
		remove_filter( 'script_module_data_@wordpress/interactivity-router', array( $this->interactivity, 'filter_script_module_interactivity_router_data' ) );
		remove_filter( 'wp_script_attributes', array( $this->interactivity, 'add_load_on_client_navigation_attribute_to_script_modules' ) );
		remove_filter( 'render_block_data', array( $this->interactivity, 'filter_render_block_data_client_navigation_support' ) );
		remove_action( 'wp_template_enhancement_output_buffer_started', array( $this->interactivity, 'mark_template_output_buffer_started' ) );
		remove_action( 'wp_head', array( $this->interactivity, 'maybe_start_router_managed_output_buffer' ), PHP_INT_MIN );
		remove_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ), 20 );
	}

	/**
	 * Registers a block type and schedules it for removal on tear down.
	 *
	 * @param string $block_name The block name.
	 * @param array  $args       Optional. The block type arguments.
	 */
	protected function register_test_block_type( string $block_name, array $args = array() ) {
		register_block_type( $block_name, $args );
		$this->registered_block_types[] = $block_name;
	}

	/**
	 * Returns a full page markup containing several style and non-style assets.
	 *
	 * @return string The page markup.
	 */
	protected function get_page_html(): string {
		return <<<HTML
<!DOCTYPE html>
<html>
<head>
<link rel='stylesheet' id='test-css' href='http://example.org/test.css' media='all' />
<style id='test-inline-css'>body{color:red}</style>
<link rel="preload" href="http://example.org/font.woff2" as="font" />
<script src="http://example.org/test.js"></script>
</head>
<body>
<style>p{color:blue}</style>
</body>
</html>
HTML;
	}

	/**
	 * Returns the value of the `data-wp-router-managed` attribute for every tag
	 * of the given markup, keyed by the order in which the tags are found.
	 *
	 * @param string $html  The markup to inspect.
	 * @param array  $query The tag query, as accepted by `WP_HTML_Tag_Processor::next_tag()`.
	 * @return array<int, mixed> The attribute values.
	 */
	protected function get_managed_attributes( string $html, array $query ): array {
		$values = array();
		$p      = new WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag( $query ) ) {
			$values[] = $p->get_attribute( 'data-wp-router-managed' );
		}
		return $values;
	}

	/**
	 * Tests that the attribute is added to all the style assets when all the
	 * conditions are met.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_added_to_style_assets() {
		$this->process_router_region();

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $this->get_page_html() );

		// Both style tags are marked.
		$this->assertSame( array( true, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );

		// Only the stylesheet link is marked.
		$this->assertSame( array( true, null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) ) );

		// The script tag is untouched.
		$this->assertSame( array( null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'script' ) ) );

		// The attribute is rendered in its empty, valueless form.
		$this->assertStringContainsString( 'data-wp-router-managed', $html );
		$this->assertStringNotContainsString( 'data-wp-router-managed=', $html );
		$this->assertSame( 3, substr_count( $html, 'data-wp-router-managed' ) );
	}

	/**
	 * Tests that the buffer is not modified when no router region has been
	 * processed.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_not_added_without_router_region() {
		$buffer = $this->get_page_html();

		$this->assertSame( $buffer, $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer ) );
	}

	/**
	 * Tests that the buffer is not modified when client-side navigation is
	 * disabled in the `core/router` config.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_not_added_when_client_navigation_is_disabled() {
		$this->process_router_region();
		$this->interactivity->config( 'core/router', array( 'clientNavigationDisabled' => true ) );

		$buffer = $this->get_page_html();

		$this->assertSame( $buffer, $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer ) );
	}

	/**
	 * Tests that the buffer is not modified when a block that does not support
	 * client-side navigation has been rendered.
	 *
	 * @covers ::filter_render_block_data_client_navigation_support
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_not_added_with_a_block_without_client_navigation_support() {
		$this->process_router_region();
		$this->register_test_block_type( 'test/no-client-nav' );

		$parsed_block = array(
			'blockName' => 'test/no-client-nav',
			'attrs'     => array(),
		);
		$this->assertSame( $parsed_block, $this->interactivity->filter_render_block_data_client_navigation_support( $parsed_block ) );

		$buffer = $this->get_page_html();

		$this->assertSame( $buffer, $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer ) );
	}

	/**
	 * Tests that the buffer is not modified when a block that is not registered
	 * has been rendered.
	 *
	 * @covers ::filter_render_block_data_client_navigation_support
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_not_added_with_an_unregistered_block() {
		$this->process_router_region();

		$parsed_block = array(
			'blockName' => 'test/unregistered',
			'attrs'     => array(),
		);
		$this->assertSame( $parsed_block, $this->interactivity->filter_render_block_data_client_navigation_support( $parsed_block ) );

		$buffer = $this->get_page_html();

		$this->assertSame( $buffer, $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer ) );
	}

	/**
	 * Tests that blocks without a block name, i.e. freeform classic HTML, do not
	 * break the client-side navigation compatibility.
	 *
	 * @covers ::filter_render_block_data_client_navigation_support
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_block_without_name_does_not_break_compatibility() {
		$this->process_router_region();

		$parsed_block = array(
			'blockName' => null,
			'attrs'     => array(),
		);
		$this->assertSame( $parsed_block, $this->interactivity->filter_render_block_data_client_navigation_support( $parsed_block ) );

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $this->get_page_html() );

		$this->assertSame( 3, substr_count( $html, 'data-wp-router-managed' ) );
	}

	/**
	 * Tests that blocks supporting client-side navigation do not break the
	 * client-side navigation compatibility.
	 *
	 * @covers ::filter_render_block_data_client_navigation_support
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_block_with_client_navigation_support_does_not_break_compatibility() {
		$this->process_router_region();
		$this->register_test_block_type(
			'test/client-nav',
			array(
				'supports' => array(
					'interactivity' => array( 'clientNavigation' => true ),
				),
			)
		);
		$this->register_test_block_type(
			'test/interactive',
			array(
				'supports' => array( 'interactivity' => true ),
			)
		);

		$this->interactivity->filter_render_block_data_client_navigation_support(
			array(
				'blockName' => 'test/client-nav',
				'attrs'     => array(),
			)
		);
		$this->interactivity->filter_render_block_data_client_navigation_support(
			array(
				'blockName' => 'test/interactive',
				'attrs'     => array(),
			)
		);

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $this->get_page_html() );

		$this->assertSame( 3, substr_count( $html, 'data-wp-router-managed' ) );
	}

	/**
	 * Tests that the `rel` attribute is handled as a case-insensitive,
	 * space-separated list of tokens.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_link_rel_token_list_handling() {
		$this->process_router_region();

		$buffer = '<!DOCTYPE html><html><head>' .
			'<link rel="alternate stylesheet" href="http://example.org/a.css">' .
			'<link rel="StyleSheet" href="http://example.org/b.css">' .
			'<link rel="preload stylesheets" href="http://example.org/c.css">' .
			'<link rel href="http://example.org/d.css">' .
			'<link href="http://example.org/e.css">' .
			'</head><body></body></html>';

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer );

		$this->assertSame(
			array( true, true, null, null, null ),
			$this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) )
		);
	}

	/**
	 * Tests that a non-string buffer is returned as is.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_non_string_buffer_is_returned_as_is() {
		$this->process_router_region();

		$this->assertNull( $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( null ) );
		$this->assertFalse( $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( false ) );
		$this->assertSame( array(), $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( array() ) );
	}

	/**
	 * Tests that style assets inside `noscript` elements are not marked.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_not_added_inside_noscript() {
		$this->process_router_region();

		$buffer = '<!DOCTYPE html><html><head>' .
			'<style id="before">a{}</style>' .
			'<noscript>' .
			'<style id="inside">b{}</style>' .
			'<link rel="stylesheet" id="inside-link" href="http://example.org/a.css">' .
			'</noscript>' .
			'<style id="after">c{}</style>' .
			'</head><body></body></html>';

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer );

		$this->assertSame( array( true, null, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
		$this->assertSame( array( null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) ) );
	}

	/**
	 * Tests that style assets inside `template` elements, including nested ones,
	 * are not marked.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_not_added_inside_template() {
		$this->process_router_region();

		$buffer = '<!DOCTYPE html><html><head>' .
			'<style id="before">a{}</style>' .
			'</head><body>' .
			'<template><template>' .
			'<style id="inside">b{}</style>' .
			'<link rel="stylesheet" id="inside-link" href="http://example.org/a.css">' .
			'</template></template>' .
			'<style id="after">c{}</style>' .
			'</body></html>';

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer );

		$this->assertSame( array( true, null, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
		$this->assertSame( array( null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) ) );
	}

	/**
	 * Tests that a self-closing `template` tag in foreign content, which has no
	 * closing tag, does not suppress the attribute for the rest of the document.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_self_closing_template_does_not_suppress_the_attribute() {
		$this->process_router_region();

		$buffer = '<!DOCTYPE html><html><head></head><body>' .
			'<svg><template/></svg>' .
			'<style id="trailing">x{}</style>' .
			'<template><style id="inert">y{}</style></template>' .
			'<style id="last">z{}</style>' .
			'</body></html>';

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer );

		$this->assertSame( array( true, null, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
	}

	/**
	 * Tests that `style` elements inside inline SVG are marked.
	 *
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_attribute_is_added_to_svg_style() {
		$this->process_router_region();

		$buffer = '<!DOCTYPE html><html><head></head><body><svg><style>circle{}</style></svg></body></html>';

		$html = $this->interactivity->filter_template_output_buffer_add_router_managed_attribute( $buffer );

		$this->assertSame( array( true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
	}

	/**
	 * Tests that the front-end hooks are registered by `add_hooks()`.
	 *
	 * The template enhancement output buffer filter must not be registered,
	 * because registering it is what makes core start the template enhancement
	 * output buffer on every front-end request.
	 *
	 * @covers ::add_hooks
	 */
	public function test_add_hooks_registers_front_end_hooks() {
		$this->interactivity->add_hooks();

		$this->assertFalse(
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);
		$this->assertNotFalse(
			has_filter( 'render_block_data', array( $this->interactivity, 'filter_render_block_data_client_navigation_support' ) )
		);
		$this->assertNotFalse(
			has_action( 'wp_template_enhancement_output_buffer_started', array( $this->interactivity, 'mark_template_output_buffer_started' ) )
		);
		$this->assertSame(
			PHP_INT_MIN,
			has_action( 'wp_head', array( $this->interactivity, 'maybe_start_router_managed_output_buffer' ) )
		);

		$this->remove_hooks();
	}

	/**
	 * Tests that the front-end hooks are not registered in admin requests.
	 *
	 * @covers ::add_hooks
	 */
	public function test_add_hooks_does_not_register_front_end_hooks_in_admin() {
		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin() );

		$this->interactivity->add_hooks();

		$this->assertFalse(
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);
		$this->assertFalse(
			has_filter( 'render_block_data', array( $this->interactivity, 'filter_render_block_data_client_navigation_support' ) )
		);
		$this->assertFalse(
			has_action( 'wp_template_enhancement_output_buffer_started', array( $this->interactivity, 'mark_template_output_buffer_started' ) )
		);
		$this->assertFalse(
			has_action( 'wp_head', array( $this->interactivity, 'maybe_start_router_managed_output_buffer' ) )
		);

		$this->remove_hooks();
		set_current_screen( 'front' );
	}

	/**
	 * Tests that processing a router region registers the template enhancement
	 * output buffer filter.
	 *
	 * @covers ::data_wp_router_region_processor
	 */
	public function test_processing_a_router_region_registers_the_output_buffer_filter() {
		$this->assertFalse(
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);

		$this->process_router_region();

		$this->assertSame(
			20,
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);

		$this->remove_hooks();
	}

	/**
	 * Tests that the template enhancement output buffer filter is registered
	 * again when another router region is processed, so it survives other code
	 * removing the filters of the hook.
	 *
	 * @covers ::data_wp_router_region_processor
	 */
	public function test_processing_another_router_region_registers_the_output_buffer_filter_again() {
		$this->process_router_region();

		remove_all_filters( 'wp_template_enhancement_output_buffer' );
		$this->assertFalse(
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);

		$this->process_router_region();

		$this->assertSame(
			20,
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);

		$this->remove_hooks();
	}

	/**
	 * Tests that processing markup without a router region does not register the
	 * template enhancement output buffer filter.
	 *
	 * @covers ::data_wp_router_region_processor
	 */
	public function test_processing_without_a_router_region_does_not_register_the_output_buffer_filter() {
		$this->process_directives( '<div data-wp-interactive="test"><p data-wp-text="state.text">x</p></div>' );

		$this->assertFalse(
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);
	}

	/**
	 * Tests that processing a router region does not register the template
	 * enhancement output buffer filter in admin requests.
	 *
	 * @covers ::data_wp_router_region_processor
	 */
	public function test_processing_a_router_region_does_not_register_the_output_buffer_filter_in_admin() {
		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin() );

		$this->process_router_region();

		$this->assertFalse(
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);

		$this->remove_hooks();
		set_current_screen( 'front' );
	}

	/**
	 * Tests that the registered filter is invoked when the template enhancement
	 * output buffer is filtered.
	 *
	 * @covers ::data_wp_router_region_processor
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_marks_style_assets_through_the_filter_chain() {
		$this->interactivity->add_hooks();
		$this->process_router_region();

		$buffer = $this->get_page_html();
		$html   = apply_filters( 'wp_template_enhancement_output_buffer', $buffer, $buffer );

		$this->assertSame( array( true, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
		$this->assertSame( array( true, null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) ) );

		$this->remove_hooks();
	}

	/**
	 * Tests that no output buffer is started when the conditions are not met.
	 *
	 * @covers ::maybe_start_router_managed_output_buffer
	 */
	public function test_output_buffer_is_not_started_without_router_region() {
		$level = ob_get_level();

		$this->interactivity->maybe_start_router_managed_output_buffer();

		$this->assertSame( $level, ob_get_level() );
	}

	/**
	 * Tests that no output buffer is started when client-side navigation is
	 * disabled in the `core/router` config.
	 *
	 * @covers ::maybe_start_router_managed_output_buffer
	 */
	public function test_output_buffer_is_not_started_when_client_navigation_is_disabled() {
		$this->process_router_region();
		$this->interactivity->config( 'core/router', array( 'clientNavigationDisabled' => true ) );

		$level = ob_get_level();

		$this->interactivity->maybe_start_router_managed_output_buffer();

		$this->assertSame( $level, ob_get_level() );
	}

	/**
	 * Tests that an output buffer handled by the class is started when the
	 * conditions are met and core's template output buffer is not running.
	 *
	 * @covers ::maybe_start_router_managed_output_buffer
	 */
	public function test_output_buffer_is_started_when_conditions_are_met() {
		$this->process_router_region();

		$level = ob_get_level();

		$this->interactivity->maybe_start_router_managed_output_buffer();

		$this->assertSame( $level + 1, ob_get_level() );

		$status = ob_get_status();
		ob_end_clean();

		$this->assertSame( $level, ob_get_level() );
		$this->assertSame( 'WP_Interactivity_API::finalize_router_managed_output_buffer', $status['name'] );
		$this->assertSame( 0, $status['chunk_size'] );
		$this->assertSame( 0, $status['flags'] & PHP_OUTPUT_HANDLER_FLUSHABLE );
		$this->assertNotSame( 0, $status['flags'] & PHP_OUTPUT_HANDLER_CLEANABLE );
		$this->assertNotSame( 0, $status['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE );
	}

	/**
	 * Tests that the output buffer is only started once.
	 *
	 * @covers ::maybe_start_router_managed_output_buffer
	 */
	public function test_output_buffer_is_started_only_once() {
		$this->process_router_region();

		$level = ob_get_level();

		$this->interactivity->maybe_start_router_managed_output_buffer();
		$this->interactivity->maybe_start_router_managed_output_buffer();

		$this->assertSame( $level + 1, ob_get_level() );

		ob_end_clean();
	}

	/**
	 * Tests that no output buffer is started when core's template enhancement
	 * output buffer is already running.
	 *
	 * @covers ::maybe_start_router_managed_output_buffer
	 * @covers ::mark_template_output_buffer_started
	 */
	public function test_output_buffer_is_not_started_when_template_output_buffer_started() {
		$this->interactivity->add_hooks();
		$this->process_router_region();

		/*
		 * The callback is invoked directly instead of through the action, because
		 * the other callbacks hooked to it, e.g. `wp_hoist_late_printed_styles()`,
		 * have side effects that are not relevant for this test.
		 */
		$this->interactivity->mark_template_output_buffer_started();

		$level = ob_get_level();

		$this->interactivity->maybe_start_router_managed_output_buffer();

		$this->assertSame( $level, ob_get_level() );

		$this->remove_hooks();
	}

	/**
	 * Tests that the output buffer callback marks the style assets when the
	 * buffer is finalized.
	 *
	 * @covers ::finalize_router_managed_output_buffer
	 */
	public function test_output_buffer_callback_marks_style_assets() {
		$this->process_router_region();

		$html = $this->interactivity->finalize_router_managed_output_buffer( $this->get_page_html(), PHP_OUTPUT_HANDLER_FINAL );

		$this->assertSame( array( true, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
		$this->assertSame( array( true, null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) ) );
	}

	/**
	 * Tests that the output buffer callback does not modify the output when the
	 * buffer is being cleaned.
	 *
	 * @covers ::finalize_router_managed_output_buffer
	 */
	public function test_output_buffer_callback_does_not_modify_cleaned_output() {
		$this->process_router_region();

		$buffer = $this->get_page_html();

		$this->assertSame(
			$buffer,
			$this->interactivity->finalize_router_managed_output_buffer( $buffer, PHP_OUTPUT_HANDLER_CLEAN )
		);
	}

	/**
	 * Tests that the output buffer callback rechecks the conditions when the
	 * buffer is finalized, so markup rendered after `wp_head` is taken into
	 * account.
	 *
	 * @covers ::finalize_router_managed_output_buffer
	 */
	public function test_output_buffer_callback_rechecks_the_conditions() {
		$this->process_router_region();

		$level = ob_get_level();
		$this->interactivity->maybe_start_router_managed_output_buffer();
		$this->assertSame( $level + 1, ob_get_level() );
		ob_end_clean();

		// Client-side navigation is disabled after the buffer has started.
		$this->interactivity->config( 'core/router', array( 'clientNavigationDisabled' => true ) );

		$buffer = $this->get_page_html();

		$this->assertSame(
			$buffer,
			$this->interactivity->finalize_router_managed_output_buffer( $buffer, PHP_OUTPUT_HANDLER_FINAL )
		);
	}

	/**
	 * Tests that the output buffer started by the class marks the style assets of
	 * everything printed after it started.
	 *
	 * @covers ::maybe_start_router_managed_output_buffer
	 * @covers ::finalize_router_managed_output_buffer
	 */
	public function test_output_buffer_marks_the_captured_output() {
		$this->process_router_region();

		/*
		 * The buffer started by the class is nested inside a plain one, so it can
		 * be finalized with `ob_end_flush()`, which runs the callback, without
		 * sending anything to the actual output.
		 */
		ob_start();
		$level = ob_get_level();
		$this->interactivity->maybe_start_router_managed_output_buffer();

		/*
		 * The buffer must have started before anything is printed. Otherwise, the
		 * clean up below would discard the buffers of the test runner.
		 */
		$this->assertSame( $level + 1, ob_get_level() );

		echo $this->get_page_html();
		ob_end_flush();
		$html = ob_get_clean();

		$this->assertSame( array( true, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
		$this->assertSame( array( true, null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) ) );
	}

	/**
	 * Tests the `has_router_region()` getter.
	 *
	 * @covers ::has_router_region
	 */
	public function test_has_router_region() {
		$this->assertFalse( $this->interactivity->has_router_region() );

		$this->process_router_region();

		$this->assertTrue( $this->interactivity->has_router_region() );
	}
}
