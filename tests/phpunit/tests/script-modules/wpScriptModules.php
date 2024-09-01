<?php
/**
 * Unit tests covering WP_Script_Modules functionality.
 *
 * @package WordPress
 * @subpackage Script Modules
 *
 * @since 6.5.0
 *
 * @group script-modules
 *
 * @coversDefaultClass WP_Script_Modules
 */
class Tests_Script_Modules_WpScriptModules extends WP_UnitTestCase {

	/**
	 * Instance of WP_Script_Modules.
	 *
	 * @var WP_Script_Modules
	 */
	protected $script_modules;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		// Set up the WP_Script_Modules instance.
		$this->script_modules = new WP_Script_Modules();
	}

	/**
	 * Gets a list of the enqueued script modules.
	 *
	 * @return array Enqueued script module URLs, keyed by script module identifier.
	 */
	public function get_enqueued_script_modules() {
		$script_modules_markup   = get_echo( array( $this->script_modules, 'print_enqueued_script_modules' ) );
		$p                       = new WP_HTML_Tag_Processor( $script_modules_markup );
		$enqueued_script_modules = array();

		while ( $p->next_tag( array( 'tag' => 'SCRIPT' ) ) ) {
			if ( 'module' === $p->get_attribute( 'type' ) ) {
				$id                             = preg_replace( '/-js-module$/', '', $p->get_attribute( 'id' ) );
				$enqueued_script_modules[ $id ] = $p->get_attribute( 'src' );
			}
		}

		return $enqueued_script_modules;
	}

	/**
	 * Gets the script modules listed in the import map.
	 *
	 * @return array Import map entry URLs, keyed by script module identifier.
	 */
	public function get_import_map() {
		$import_map_markup = get_echo( array( $this->script_modules, 'print_import_map' ) );
		preg_match( '/<script type="importmap" id="wp-importmap">.*?(\{.*\}).*?<\/script>/s', $import_map_markup, $import_map_string );
		return json_decode( $import_map_string[1], true )['imports'];
	}

	/**
	 * Gets a list of preloaded script modules.
	 *
	 * @return array Preloaded script module URLs, keyed by script module identifier.
	 */
	public function get_preloaded_script_modules() {
		$preloaded_markup         = get_echo( array( $this->script_modules, 'print_script_module_preloads' ) );
		$p                        = new WP_HTML_Tag_Processor( $preloaded_markup );
		$preloaded_script_modules = array();

		while ( $p->next_tag( array( 'tag' => 'LINK' ) ) ) {
			if ( 'modulepreload' === $p->get_attribute( 'rel' ) ) {
				$id                              = preg_replace( '/-js-modulepreload$/', '', $p->get_attribute( 'id' ) );
				$preloaded_script_modules[ $id ] = $p->get_attribute( 'href' );
			}
		}

		return $preloaded_script_modules;
	}

	/**
	 * Tests that a script module gets enqueued correctly after being registered.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_enqueued_script_modules()
	 */
	public function test_wp_enqueue_script_module() {
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->register( 'bar', '/bar.js' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'bar' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 2, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo'] );
		$this->assertStringStartsWith( '/bar.js', $enqueued_script_modules['bar'] );
	}

	/**
	* Tests that a script module can be dequeued after being enqueued.
	*
	* @ticket 56313
	*
	* @covers ::register()
	* @covers ::enqueue()
	* @covers ::dequeue()
	* @covers ::print_enqueued_script_modules()
	*/
	public function test_wp_dequeue_script_module() {
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->register( 'bar', '/bar.js' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'bar' );
		$this->script_modules->dequeue( 'foo' ); // Dequeued.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );
		$this->assertArrayHasKey( 'bar', $enqueued_script_modules );
	}


	/**
	 * Tests that a script module can be deregistered
	 * after being enqueued, and that will be removed
	 * from the enqueue list too.
	 *
	 * @ticket 60463
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::deregister()
	 * @covers ::get_enqueued_script_modules()
	 */
	public function test_wp_deregister_script_module() {
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->register( 'bar', '/bar.js' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'bar' );
		$this->script_modules->deregister( 'foo' ); // Dequeued.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );
		$this->assertArrayHasKey( 'bar', $enqueued_script_modules );
	}

	/**
	 * Tests that a script module is not deregistered
	 * if it has not been registered before, causing
	 * no errors.
	 *
	 * @ticket 60463
	 *
	 * @covers ::deregister()
	 * @covers ::get_enqueued_script_modules()
	 */
	public function test_wp_deregister_unexistent_script_module() {
		$this->script_modules->deregister( 'unexistent' );
		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 0, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'unexistent', $enqueued_script_modules );
	}

	/**
	 * Tests that a script module is not deregistered
	 * if it has been deregistered previously, causing
	 * no errors.
	 *
	 * @ticket 60463
	 *
	 * @covers ::get_enqueued_script_modules()
	 * @covers ::register()
	 * @covers ::deregister()
	 * @covers ::enqueue()
	 */
	public function test_wp_deregister_already_deregistered_script_module() {
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->deregister( 'foo' ); // Dequeued.
		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 0, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );

		$this->script_modules->deregister( 'foo' ); // Dequeued.
		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 0, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );
	}

	/**
	* Tests that a script module can be enqueued before it is registered, and will
	* be handled correctly once registered.
	*
	* @ticket 56313
	*
	* @covers ::register()
	* @covers ::enqueue()
	* @covers ::print_enqueued_script_modules()
	*/
	public function test_wp_enqueue_script_module_works_before_register() {
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->enqueue( 'bar' ); // Not registered.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo'] );
		$this->assertArrayNotHasKey( 'bar', $enqueued_script_modules );
	}

	/**
	 * Tests that a script module can be dequeued before it is registered and
	 * ensures that it is not enqueued after registration.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::dequeue()
	 * @covers ::print_enqueued_script_modules()
	 */
	public function test_wp_dequeue_script_module_works_before_register() {
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'bar' );
		$this->script_modules->dequeue( 'foo' );
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->register( 'bar', '/bar.js' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );
		$this->assertArrayHasKey( 'bar', $enqueued_script_modules );
	}

	/**
	 * Tests that dependencies for a registered module are added to the import map
	 * when the script module is enqueued.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_import_map()
	 */
	public function test_wp_import_map_dependencies() {
		$this->script_modules->register( 'foo', '/foo.js', array( 'dep' ) );
		$this->script_modules->register( 'dep', '/dep.js' );
		$this->script_modules->register( 'no-dep', '/no-dep.js' );
		$this->script_modules->enqueue( 'foo' );

		$import_map = $this->get_import_map();

		$this->assertCount( 1, $import_map );
		$this->assertStringStartsWith( '/dep.js', $import_map['dep'] );
		$this->assertArrayNotHasKey( 'no-dep', $import_map );
	}

	/**
	 * Tests that dependencies are not duplicated in the import map when multiple
	 * script modules require the same dependency.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_import_map()
	 */
	public function test_wp_import_map_no_duplicate_dependencies() {
		$this->script_modules->register( 'foo', '/foo.js', array( 'dep' ) );
		$this->script_modules->register( 'bar', '/bar.js', array( 'dep' ) );
		$this->script_modules->register( 'dep', '/dep.js' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'bar' );

		$import_map = $this->get_import_map();

		$this->assertCount( 1, $import_map );
		$this->assertStringStartsWith( '/dep.js', $import_map['dep'] );
	}

	/**
	 * Tests that all recursive dependencies (both static and dynamic) are
	 * included in the import map.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_import_map()
	 */
	public function test_wp_import_map_recursive_dependencies() {
		$this->script_modules->register(
			'foo',
			'/foo.js',
			array(
				'static-dep',
				array(
					'id'     => 'dynamic-dep',
					'import' => 'dynamic',
				),
			)
		);
		$this->script_modules->register(
			'static-dep',
			'/static-dep.js',
			array(
				array(
					'id'     => 'nested-static-dep',
					'import' => 'static',
				),
				array(
					'id'     => 'nested-dynamic-dep',
					'import' => 'dynamic',
				),
			)
		);
		$this->script_modules->register( 'dynamic-dep', '/dynamic-dep.js' );
		$this->script_modules->register( 'nested-static-dep', '/nested-static-dep.js' );
		$this->script_modules->register( 'nested-dynamic-dep', '/nested-dynamic-dep.js' );
		$this->script_modules->register( 'no-dep', '/no-dep.js' );
		$this->script_modules->enqueue( 'foo' );

		$import_map = $this->get_import_map();

		$this->assertStringStartsWith( '/static-dep.js', $import_map['static-dep'] );
		$this->assertStringStartsWith( '/dynamic-dep.js', $import_map['dynamic-dep'] );
		$this->assertStringStartsWith( '/nested-static-dep.js', $import_map['nested-static-dep'] );
		$this->assertStringStartsWith( '/nested-dynamic-dep.js', $import_map['nested-dynamic-dep'] );
		$this->assertArrayNotHasKey( 'no-dep', $import_map );
	}

	/**
	 * Tests that the import map is not printed at all if there are no
	 * dependencies.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_import_map()
	 */
	public function test_wp_import_map_doesnt_print_if_no_dependencies() {
		$this->script_modules->register( 'foo', '/foo.js' ); // No deps.
		$this->script_modules->enqueue( 'foo' );

		$import_map_markup = get_echo( array( $this->script_modules, 'print_import_map' ) );

		$this->assertEmpty( $import_map_markup );
	}

	/**
	 * Tests that only static dependencies are preloaded and dynamic ones are
	 * excluded.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_script_module_preloads()
	 */
	public function test_wp_enqueue_preloaded_static_dependencies() {
		$this->script_modules->register(
			'foo',
			'/foo.js',
			array(
				'static-dep',
				array(
					'id'     => 'dynamic-dep',
					'import' => 'dynamic',
				),
			)
		);
		$this->script_modules->register(
			'static-dep',
			'/static-dep.js',
			array(
				array(
					'id'     => 'nested-static-dep',
					'import' => 'static',
				),
				array(
					'id'     => 'nested-dynamic-dep',
					'import' => 'dynamic',
				),
			)
		);
		$this->script_modules->register( 'dynamic-dep', '/dynamic-dep.js' );
		$this->script_modules->register( 'nested-static-dep', '/nested-static-dep.js' );
		$this->script_modules->register( 'nested-dynamic-dep', '/nested-dynamic-dep.js' );
		$this->script_modules->register( 'no-dep', '/no-dep.js' );
		$this->script_modules->enqueue( 'foo' );

		$preloaded_script_modules = $this->get_preloaded_script_modules();

		$this->assertCount( 2, $preloaded_script_modules );
		$this->assertStringStartsWith( '/static-dep.js', $preloaded_script_modules['static-dep'] );
		$this->assertStringStartsWith( '/nested-static-dep.js', $preloaded_script_modules['nested-static-dep'] );
		$this->assertArrayNotHasKey( 'dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'nested-dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'no-dep', $preloaded_script_modules );
	}

	/**
	 * Tests that static dependencies of dynamic dependencies are not preloaded.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_script_module_preloads()
	 */
	public function test_wp_dont_preload_static_dependencies_of_dynamic_dependencies() {
		$this->script_modules->register(
			'foo',
			'/foo.js',
			array(
				'static-dep',
				array(
					'id'     => 'dynamic-dep',
					'import' => 'dynamic',
				),
			)
		);
		$this->script_modules->register( 'static-dep', '/static-dep.js' );
		$this->script_modules->register( 'dynamic-dep', '/dynamic-dep.js', array( 'nested-static-dep' ) );
		$this->script_modules->register( 'nested-static-dep', '/nested-static-dep.js' );
		$this->script_modules->register( 'no-dep', '/no-dep.js' );
		$this->script_modules->enqueue( 'foo' );

		$preloaded_script_modules = $this->get_preloaded_script_modules();

		$this->assertCount( 1, $preloaded_script_modules );
		$this->assertStringStartsWith( '/static-dep.js', $preloaded_script_modules['static-dep'] );
		$this->assertArrayNotHasKey( 'dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'nested-dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'no-dep', $preloaded_script_modules );
	}

	/**
	 * Tests that preloaded dependencies don't include enqueued script modules.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_script_module_preloads()
	 */
	public function test_wp_preloaded_dependencies_filter_enqueued_script_modules() {
		$this->script_modules->register(
			'foo',
			'/foo.js',
			array(
				'dep',
				'enqueued-dep',
			)
		);
		$this->script_modules->register( 'dep', '/dep.js' );
		$this->script_modules->register( 'enqueued-dep', '/enqueued-dep.js' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'enqueued-dep' ); // Not preloaded.

		$preloaded_script_modules = $this->get_preloaded_script_modules();

		$this->assertCount( 1, $preloaded_script_modules );
		$this->assertArrayHasKey( 'dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'enqueued-dep', $preloaded_script_modules );
	}

	/**
	 * Tests that enqueued script modules with dependants correctly add both the
	 * script module and its dependencies to the import map.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_import_map()
	 */
	public function test_wp_enqueued_script_modules_with_dependants_add_import_map() {
		$this->script_modules->register(
			'foo',
			'/foo.js',
			array(
				'dep',
				'enqueued-dep',
			)
		);
		$this->script_modules->register( 'dep', '/dep.js' );
		$this->script_modules->register( 'enqueued-dep', '/enqueued-dep.js' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'enqueued-dep' ); // Also in the import map.

		$import_map = $this->get_import_map();

		$this->assertCount( 2, $import_map );
		$this->assertArrayHasKey( 'dep', $import_map );
		$this->assertArrayHasKey( 'enqueued-dep', $import_map );
	}

	/**
	 * Tests the functionality of the `get_src` method to ensure
	 * proper URLs with version strings are returned.
	 *
	 * @ticket 56313
	 *
	 * @covers ::get_src()
	 */
	public function test_get_src() {
		$get_src = new ReflectionMethod( $this->script_modules, 'get_src' );
		$get_src->setAccessible( true );

		$this->script_modules->register(
			'module_with_version',
			'http://example.com/module.js',
			array(),
			'1.0'
		);

		$result = $get_src->invoke( $this->script_modules, 'module_with_version' );
		$this->assertSame( 'http://example.com/module.js?ver=1.0', $result );

		$this->script_modules->register(
			'module_without_version',
			'http://example.com/module.js',
			array(),
			null
		);

		$result = $get_src->invoke( $this->script_modules, 'module_without_version' );
		$this->assertSame( 'http://example.com/module.js', $result );

		$this->script_modules->register(
			'module_with_wp_version',
			'http://example.com/module.js',
			array(),
			false
		);

		$result = $get_src->invoke( $this->script_modules, 'module_with_wp_version' );
		$this->assertSame( 'http://example.com/module.js?ver=' . get_bloginfo( 'version' ), $result );

		$this->script_modules->register(
			'module_with_existing_query_string',
			'http://example.com/module.js?foo=bar',
			array(),
			'1.0'
		);

		$result = $get_src->invoke( $this->script_modules, 'module_with_existing_query_string' );
		$this->assertSame( 'http://example.com/module.js?foo=bar&ver=1.0', $result );

		// Filter the version to include the ID in the final URL, to test the filter, this should affect the tests below.
		add_filter(
			'script_module_loader_src',
			function ( $src, $id ) {
				return add_query_arg( 'script_module_id', urlencode( $id ), $src );
			},
			10,
			2
		);

		$result = $get_src->invoke( $this->script_modules, 'module_without_version' );
		$this->assertSame( 'http://example.com/module.js?script_module_id=module_without_version', $result );

		$result = $get_src->invoke( $this->script_modules, 'module_with_existing_query_string' );
		$this->assertSame( 'http://example.com/module.js?foo=bar&ver=1.0&script_module_id=module_with_existing_query_string', $result );
	}

	/**
	 * Tests that the correct version is propagated to the import map, enqueued
	 * script modules and preloaded script modules.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_enqueued_script_modules()
	 * @covers ::print_import_map()
	 * @covers ::print_script_module_preloads()
	 * @covers ::get_version_query_string()
	 */
	public function test_version_is_propagated_correctly() {
		$this->script_modules->register(
			'foo',
			'/foo.js',
			array(
				'dep',
			),
			'1.0'
		);
		$this->script_modules->register( 'dep', '/dep.js', array(), '2.0' );
		$this->script_modules->enqueue( 'foo' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();
		$this->assertSame( '/foo.js?ver=1.0', $enqueued_script_modules['foo'] );

		$import_map = $this->get_import_map();
		$this->assertSame( '/dep.js?ver=2.0', $import_map['dep'] );

		$preloaded_script_modules = $this->get_preloaded_script_modules();
		$this->assertSame( '/dep.js?ver=2.0', $preloaded_script_modules['dep'] );
	}

	/**
	 * Tests that a script module is not registered when calling enqueue without a
	 * valid src.
	 *
	 * @ticket 56313
	 *
	 * @covers ::enqueue()
	 * @covers ::print_enqueued_script_modules()
	 */
	public function test_wp_enqueue_script_module_doesnt_register_without_a_valid_src() {
		$this->script_modules->enqueue( 'foo' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 0, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );
	}

	/**
	 * Tests that a script module is registered when calling enqueue with a valid
	 * src.
	 *
	 * @ticket 56313
	 *
	 * @covers ::enqueue()
	 * @covers ::print_enqueued_script_modules()
	 */
	public function test_wp_enqueue_script_module_registers_with_valid_src() {
		$this->script_modules->enqueue( 'foo', '/foo.js' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo'] );
	}

	/**
	 * Tests that a script module is registered when calling enqueue with a valid
	 * src the second time.
	 *
	 * @ticket 56313
	 *
	 * @covers ::enqueue()
	 * @covers ::print_enqueued_script_modules()
	 */
	public function test_wp_enqueue_script_module_registers_with_valid_src_the_second_time() {
		$this->script_modules->enqueue( 'foo' ); // Not valid src.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 0, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );

		$this->script_modules->enqueue( 'foo', '/foo.js' ); // Valid src.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo'] );
	}

	/**
	 * Tests that a script module is registered with all the params when calling
	 * enqueue.
	 *
	 * @ticket 56313
	 *
	 * @covers ::register()
	 * @covers ::enqueue()
	 * @covers ::print_enqueued_script_modules()
	 * @covers ::print_import_map()
	 */
	public function test_wp_enqueue_script_module_registers_all_params() {
		$this->script_modules->enqueue( 'foo', '/foo.js', array( 'dep' ), '1.0' );
		$this->script_modules->register( 'dep', '/dep.js' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();
		$import_map              = $this->get_import_map();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertSame( '/foo.js?ver=1.0', $enqueued_script_modules['foo'] );
		$this->assertCount( 1, $import_map );
		$this->assertStringStartsWith( '/dep.js', $import_map['dep'] );
	}

	/**
	 * @ticket 61510
	 */
	public function test_print_script_module_data_prints_enqueued_module_data() {
		$this->script_modules->enqueue( '@test/module', '/example.js' );
		add_action(
			'script_module_data_@test/module',
			function ( $data ) {
				$data['foo'] = 'bar';
				return $data;
			}
		);

		$actual = get_echo( array( $this->script_modules, 'print_script_module_data' ) );

		$expected = <<<HTML
<script type="application/json" id="wp-script-module-data-@test/module">
{"foo":"bar"}
</script>

HTML;
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 61510
	 */
	public function test_print_script_module_data_prints_dependency_module_data() {
		$this->script_modules->register( '@test/dependency', '/dependency.js' );
		$this->script_modules->enqueue( '@test/module', '/example.js', array( '@test/dependency' ) );
		add_action(
			'script_module_data_@test/dependency',
			function ( $data ) {
				$data['foo'] = 'bar';
				return $data;
			}
		);

		$actual = get_echo( array( $this->script_modules, 'print_script_module_data' ) );

		$expected = <<<HTML
<script type="application/json" id="wp-script-module-data-@test/dependency">
{"foo":"bar"}
</script>

HTML;
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 61510
	 */
	public function test_print_script_module_data_does_not_print_nondependency_module_data() {
		$this->script_modules->register( '@test/other', '/dependency.js' );
		$this->script_modules->enqueue( '@test/module', '/example.js' );
		add_action(
			'script_module_data_@test/other',
			function ( $data ) {
				$data['foo'] = 'bar';
				return $data;
			}
		);

		$actual = get_echo( array( $this->script_modules, 'print_script_module_data' ) );

		$this->assertSame( '', $actual );
	}

	/**
	 * @ticket 61510
	 */
	public function test_print_script_module_data_does_not_print_empty_data() {
		$this->script_modules->enqueue( '@test/module', '/example.js' );
		add_action(
			'script_module_data_@test/module',
			function ( $data ) {
				return $data;
			}
		);

		$actual = get_echo( array( $this->script_modules, 'print_script_module_data' ) );

		$this->assertSame( '', $actual );
	}

	/**
	 * @ticket 61510
	 *
	 * @dataProvider data_special_chars_script_encoding
	 * @param string $input    Raw input string.
	 * @param string $expected Expected output string.
	 * @param string $charset  Blog charset option.
	 */
	public function test_print_script_module_data_encoding( $input, $expected, $charset ) {
		add_filter(
			'pre_option_blog_charset',
			function () use ( $charset ) {
				return $charset;
			}
		);

		$this->script_modules->enqueue( '@test/module', '/example.js' );
		add_action(
			'script_module_data_@test/module',
			function ( $data ) use ( $input ) {
				$data[''] = $input;
				return $data;
			}
		);

		$actual = get_echo( array( $this->script_modules, 'print_script_module_data' ) );

		$expected = <<<HTML
<script type="application/json" id="wp-script-module-data-@test/module">
{"":"{$expected}"}
</script>

HTML;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_special_chars_script_encoding(): array {
		return array(
			// UTF-8
			'Solidus'                                => array( '/', '/', 'UTF-8' ),
			'Double quote'                           => array( '"', '\\"', 'UTF-8' ),
			'Single quote'                           => array( '\'', '\'', 'UTF-8' ),
			'Less than'                              => array( '<', '\u003C', 'UTF-8' ),
			'Greater than'                           => array( '>', '\u003E', 'UTF-8' ),
			'Ampersand'                              => array( '&', '&', 'UTF-8' ),
			'Newline'                                => array( "\n", "\\n", 'UTF-8' ),
			'Tab'                                    => array( "\t", "\\t", 'UTF-8' ),
			'Form feed'                              => array( "\f", "\\f", 'UTF-8' ),
			'Carriage return'                        => array( "\r", "\\r", 'UTF-8' ),
			'Line separator'                         => array( "\u{2028}", "\u{2028}", 'UTF-8' ),
			'Paragraph separator'                    => array( "\u{2029}", "\u{2029}", 'UTF-8' ),

			/*
			 * The following is the Flag of England emoji
			 * PHP: "\u{1F3F4}\u{E0067}\u{E0062}\u{E0065}\u{E006E}\u{E0067}\u{E007F}"
			 */
			'Flag of england'                        => array( '🏴󠁧󠁢󠁥󠁮󠁧󠁿', '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'UTF-8' ),
			'Malicious script closer'                => array( '</script>', '\u003C/script\u003E', 'UTF-8' ),
			'Entity-encoded malicious script closer' => array( '&lt;/script&gt;', '&lt;/script&gt;', 'UTF-8' ),

			// Non UTF-8
			'Solidus'                                => array( '/', '/', 'iso-8859-1' ),
			'Less than'                              => array( '<', '\u003C', 'iso-8859-1' ),
			'Greater than'                           => array( '>', '\u003E', 'iso-8859-1' ),
			'Ampersand'                              => array( '&', '&', 'iso-8859-1' ),
			'Newline'                                => array( "\n", "\\n", 'iso-8859-1' ),
			'Tab'                                    => array( "\t", "\\t", 'iso-8859-1' ),
			'Form feed'                              => array( "\f", "\\f", 'iso-8859-1' ),
			'Carriage return'                        => array( "\r", "\\r", 'iso-8859-1' ),
			'Line separator'                         => array( "\u{2028}", "\u2028", 'iso-8859-1' ),
			'Paragraph separator'                    => array( "\u{2029}", "\u2029", 'iso-8859-1' ),
			/*
			 * The following is the Flag of England emoji
			 * PHP: "\u{1F3F4}\u{E0067}\u{E0062}\u{E0065}\u{E006E}\u{E0067}\u{E007F}"
			 */
			'Flag of england'                        => array( '🏴󠁧󠁢󠁥󠁮󠁧󠁿', "\ud83c\udff4\udb40\udc67\udb40\udc62\udb40\udc65\udb40\udc6e\udb40\udc67\udb40\udc7f", 'iso-8859-1' ),
			'Malicious script closer'                => array( '</script>', '\u003C/script\u003E', 'iso-8859-1' ),
			'Entity-encoded malicious script closer' => array( '&lt;/script&gt;', '&lt;/script&gt;', 'iso-8859-1' ),

		);
	}

	/**
	 * @ticket 61510
	 *
	 * @dataProvider data_invalid_script_module_data
	 * @param mixed $data Data to return in filter.
	 */
	public function test_print_script_module_data_does_not_print_invalid_data( $data ) {
		$this->script_modules->enqueue( '@test/module', '/example.js' );
		add_action(
			'script_module_data_@test/module',
			function ( $_ ) use ( $data ) {
				return $data;
			}
		);

		$actual = get_echo( array( $this->script_modules, 'print_script_module_data' ) );

		$this->assertSame( '', $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_invalid_script_module_data(): array {
		return array(
			'null'     => array( null ),
			'stdClass' => array( new stdClass() ),
			'number 1' => array( 1 ),
			'string'   => array( 'string' ),
		);
	}
}
