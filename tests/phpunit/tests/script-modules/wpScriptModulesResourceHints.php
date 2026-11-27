<?php
/**
 * Unit tests covering WP_Script_Modules::filter_resource_hints().
 *
 * @package WordPress
 * @subpackage Script Modules
 *
 * @since 7.1.0
 *
 * @group script-modules
 * @ticket 62709
 * @covers WP_Script_Modules::filter_resource_hints
 */
class Tests_Script_Modules_WpScriptModules_ResourceHints extends WP_UnitTestCase {

	protected WP_Script_Modules $original_script_modules;
	protected WP_Script_Modules $script_modules;

	public function set_up() {
		global $wp_script_modules;
		parent::set_up();
		$this->original_script_modules = $wp_script_modules;
		$wp_script_modules             = null;
		$this->script_modules          = wp_script_modules();
	}

	public function tear_down() {
		global $wp_script_modules;
		$wp_script_modules = $this->original_script_modules;
		parent::tear_down();
	}

	/**
	 * Tests that a DNS prefetch hint is added for an enqueued module with an external src.
	 */
	public function test_dns_prefetch_for_enqueued_script_module() {
		$this->script_modules->enqueue( 'my-module', 'https://cdn.example.com/module.js' );

		$hints = $this->script_modules->filter_resource_hints( array(), 'dns-prefetch' );
		$hosts = array_map( fn( $url ) => wp_parse_url( $url, PHP_URL_HOST ), $hints );

		$this->assertContains( 'cdn.example.com', $hosts );
	}

	/**
	 * Tests that a DNS prefetch hint is added for a static external dependency.
	 */
	public function test_dns_prefetch_for_static_dependency() {
		$this->script_modules->register( 'dep-module', 'https://cdn.example.com/dep.js' );
		$this->script_modules->enqueue( 'my-module', '/local/module.js', array( 'dep-module' ) );

		$hints = $this->script_modules->filter_resource_hints( array(), 'dns-prefetch' );
		$hosts = array_map( fn( $url ) => wp_parse_url( $url, PHP_URL_HOST ), $hints );

		$this->assertContains( 'cdn.example.com', $hosts );
	}

	/**
	 * Tests that a DNS prefetch hint is added for a dynamic external dependency.
	 *
	 * Dynamic dependencies never receive modulepreload hints, so dns-prefetch is
	 * particularly valuable for them.
	 */
	public function test_dns_prefetch_for_dynamic_dependency() {
		$this->script_modules->register( 'dep-module', 'https://cdn.example.com/dep.js' );
		$this->script_modules->enqueue(
			'my-module',
			'/local/module.js',
			array(
				array(
					'id'     => 'dep-module',
					'import' => 'dynamic',
				),
			)
		);

		$hints = $this->script_modules->filter_resource_hints( array(), 'dns-prefetch' );
		$hosts = array_map( fn( $url ) => wp_parse_url( $url, PHP_URL_HOST ), $hints );

		$this->assertContains( 'cdn.example.com', $hosts );
	}

	/**
	 * Tests that modules hosted on the same site are excluded from DNS prefetch hints.
	 */
	public function test_dns_prefetch_excludes_same_site_modules() {
		$server_name = $_SERVER['SERVER_NAME'];
		$this->script_modules->enqueue( 'local-module', "https://{$server_name}/module.js" );

		$hints = $this->script_modules->filter_resource_hints( array(), 'dns-prefetch' );

		$this->assertEmpty( $hints );
	}

	/**
	 * Tests that modules with relative (root-relative) src paths are excluded from DNS prefetch hints.
	 */
	public function test_dns_prefetch_excludes_relative_src_modules() {
		$this->script_modules->enqueue( 'local-module', '/wp-includes/js/my-module.js' );

		$hints = $this->script_modules->filter_resource_hints( array(), 'dns-prefetch' );

		$this->assertEmpty( $hints );
	}

	/**
	 * Tests that registered-but-not-enqueued modules do not produce DNS prefetch hints.
	 */
	public function test_dns_prefetch_excludes_registered_only_modules() {
		$this->script_modules->register( 'my-module', 'https://cdn.example.com/module.js' );
		// Intentionally not enqueued.

		$hints = $this->script_modules->filter_resource_hints( array(), 'dns-prefetch' );

		$this->assertEmpty( $hints );
	}

	/**
	 * Tests that non-dns-prefetch relation types are not modified.
	 */
	public function test_other_relation_types_are_not_modified() {
		$this->script_modules->enqueue( 'my-module', 'https://cdn.example.com/module.js' );

		$original = array( 'https://other.example.com' );

		foreach ( array( 'preconnect', 'prefetch', 'prerender' ) as $relation_type ) {
			$hints = $this->script_modules->filter_resource_hints( $original, $relation_type );
			$this->assertSame( $original, $hints, "Relation type '{$relation_type}' should not be modified." );
		}
	}

	/**
	 * Tests that duplicate external hosts only produce a single DNS prefetch hint entry.
	 */
	public function test_dns_prefetch_deduplicates_hosts_via_wp_resource_hints() {
		// Both modules share the same CDN host.
		$this->script_modules->enqueue( 'module-a', 'https://cdn.example.com/module-a.js' );
		$this->script_modules->enqueue( 'module-b', 'https://cdn.example.com/module-b.js' );

		// filter_resource_hints itself may include both raw URLs; deduplication by host
		// is handled by wp_resource_hints(). Verify via the full pipeline.
		add_filter( 'wp_resource_hints', array( $this->script_modules, 'filter_resource_hints' ), 10, 2 );
		$output = get_echo( 'wp_resource_hints' );
		remove_filter( 'wp_resource_hints', array( $this->script_modules, 'filter_resource_hints' ) );

		$this->assertSame(
			1,
			substr_count( $output, '//cdn.example.com' ),
			'The same CDN host should only appear once in the output.'
		);
	}

	/**
	 * Tests that the filter_resource_hints method integrates correctly with wp_resource_hints().
	 */
	public function test_integration_with_wp_resource_hints() {
		$this->script_modules->enqueue( 'my-module', 'https://cdn.example.com/module.js' );

		add_filter( 'wp_resource_hints', array( $this->script_modules, 'filter_resource_hints' ), 10, 2 );
		$output = get_echo( 'wp_resource_hints' );
		remove_filter( 'wp_resource_hints', array( $this->script_modules, 'filter_resource_hints' ) );

		$this->assertStringContainsString( "<link rel='dns-prefetch' href='//cdn.example.com' />", $output );
	}
}
