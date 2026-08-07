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
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactivity = new WP_Interactivity_API();

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
		foreach ( $this->registered_block_types as $block_name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
				unregister_block_type( $block_name );
			}
		}
		$this->registered_block_types = array();

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
	 * Tests that the front-end filters are registered by `add_hooks()`.
	 *
	 * @covers ::add_hooks
	 */
	public function test_add_hooks_registers_front_end_filters() {
		$this->interactivity->add_hooks();

		$this->assertSame(
			20,
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);
		$this->assertNotFalse(
			has_filter( 'render_block_data', array( $this->interactivity, 'filter_render_block_data_client_navigation_support' ) )
		);

		$this->remove_hooks();
	}

	/**
	 * Tests that the front-end filters are not registered in admin requests.
	 *
	 * @covers ::add_hooks
	 */
	public function test_add_hooks_does_not_register_front_end_filters_in_admin() {
		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin() );

		$this->interactivity->add_hooks();

		$this->assertFalse(
			has_filter( 'wp_template_enhancement_output_buffer', array( $this->interactivity, 'filter_template_output_buffer_add_router_managed_attribute' ) )
		);
		$this->assertFalse(
			has_filter( 'render_block_data', array( $this->interactivity, 'filter_render_block_data_client_navigation_support' ) )
		);

		$this->remove_hooks();
		set_current_screen( 'front' );
	}

	/**
	 * Tests that the registered filter is invoked when the template enhancement
	 * output buffer is filtered.
	 *
	 * @covers ::add_hooks
	 * @covers ::filter_template_output_buffer_add_router_managed_attribute
	 */
	public function test_add_hooks_marks_style_assets_through_the_filter_chain() {
		$this->interactivity->add_hooks();
		$this->process_router_region();

		$buffer = $this->get_page_html();
		$html   = apply_filters( 'wp_template_enhancement_output_buffer', $buffer, $buffer );

		$this->assertSame( array( true, true ), $this->get_managed_attributes( $html, array( 'tag_name' => 'style' ) ) );
		$this->assertSame( array( true, null ), $this->get_managed_attributes( $html, array( 'tag_name' => 'link' ) ) );

		$this->remove_hooks();
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
