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
 */
class Tests_Script_Modules_WpScriptModules extends WP_UnitTestCase {

	/**
	 * @var WP_Script_Modules
	 */
	protected $original_script_modules;

	/**
	 * @var string
	 */
	protected $original_wp_version;

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
		global $wp_script_modules, $wp_version;
		parent::set_up();
		$this->original_script_modules = $wp_script_modules;
		$this->original_wp_version     = $wp_version;
		$wp_script_modules             = null;
		$this->script_modules          = wp_script_modules();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		global $wp_script_modules, $wp_version;
		parent::tear_down();
		$wp_script_modules = $this->original_script_modules;
		$wp_version        = $this->original_wp_version;
	}

	/**
	 * Gets a list of the enqueued script modules.
	 *
	 * @return array Enqueued script module URLs, keyed by script module identifier.
	 */
	public function get_enqueued_script_modules(): array {
		$modules = array();

		$p = new WP_HTML_Tag_Processor( get_echo( array( $this->script_modules, 'print_enqueued_script_modules' ) ) );
		while ( $p->next_tag( array( 'tag' => 'SCRIPT' ) ) ) {
			$this->assertSame( 'module', $p->get_attribute( 'type' ) );
			$this->assertIsString( $p->get_attribute( 'id' ) );
			$this->assertIsString( $p->get_attribute( 'src' ) );
			$this->assertStringEndsWith( '-js-module', $p->get_attribute( 'id' ) );

			$id             = preg_replace( '/-js-module$/', '', (string) $p->get_attribute( 'id' ) );
			$fetchpriority  = $p->get_attribute( 'fetchpriority' );
			$modules[ $id ] = array_merge(
				array(
					'url'           => $p->get_attribute( 'src' ),
					'fetchpriority' => is_string( $fetchpriority ) ? $fetchpriority : 'auto',
				),
				...array_map(
					static function ( $attribute_name ) use ( $p ) {
						return array( $attribute_name => $p->get_attribute( $attribute_name ) );
					},
					$p->get_attribute_names_with_prefix( 'data-' )
				)
			);
		}

		return $modules;
	}

	/**
	 * Gets the script modules listed in the import map.
	 *
	 * @return array<string, string> Import map entry URLs, keyed by script module identifier.
	 */
	public function get_import_map(): array {
		$p = new WP_HTML_Tag_Processor( get_echo( array( $this->script_modules, 'print_import_map' ) ) );
		if ( $p->next_tag( array( 'tag' => 'SCRIPT' ) ) ) {
			$this->assertSame( 'importmap', $p->get_attribute( 'type' ) );
			$this->assertSame( 'wp-importmap', $p->get_attribute( 'id' ) );
			$data = json_decode( $p->get_modifiable_text(), true );
			$this->assertIsArray( $data );
			$this->assertArrayHasKey( 'imports', $data );
			return $data['imports'];
		} else {
			return array();
		}
	}

	/**
	 * Gets a list of preloaded script modules.
	 *
	 * @return array Preloaded script module URLs, keyed by script module identifier.
	 */
	public function get_preloaded_script_modules(): array {
		$preloads = array();

		$p = new WP_HTML_Tag_Processor( get_echo( array( $this->script_modules, 'print_script_module_preloads' ) ) );
		while ( $p->next_tag( array( 'tag' => 'LINK' ) ) ) {
			$this->assertSame( 'modulepreload', $p->get_attribute( 'rel' ) );
			$this->assertIsString( $p->get_attribute( 'id' ) );
			$this->assertIsString( $p->get_attribute( 'href' ) );
			$this->assertStringEndsWith( '-js-modulepreload', $p->get_attribute( 'id' ) );

			$id              = preg_replace( '/-js-modulepreload$/', '', $p->get_attribute( 'id' ) );
			$fetchpriority   = $p->get_attribute( 'fetchpriority' );
			$preloads[ $id ] = array_merge(
				array(
					'url'           => $p->get_attribute( 'href' ),
					'fetchpriority' => is_string( $fetchpriority ) ? $fetchpriority : 'auto',
				),
				...array_map(
					static function ( $attribute_name ) use ( $p ) {
						return array( $attribute_name => $p->get_attribute( $attribute_name ) );
					},
					$p->get_attribute_names_with_prefix( 'data-' )
				)
			);
		}

		return $preloads;
	}

	/**
	 * Test wp_script_modules().
	 *
	 * @covers ::wp_script_modules()
	 */
	public function test_wp_script_modules() {
		$this->assertSame( $this->script_modules, wp_script_modules() );
	}

	/**
	 * Tests various ways of registering, enqueueing, dequeuing, and deregistering a script module.
	 *
	 * This ensures that the global function aliases pass all the same parameters as the class methods.
	 *
	 * @ticket 56313
	 *
	 * @dataProvider data_test_register_and_enqueue_script_module
	 *
	 * @covers ::wp_register_script_module()
	 * @covers WP_Script_Modules::register()
	 * @covers ::wp_enqueue_script_module()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers ::wp_dequeue_script_module()
	 * @covers WP_Script_Modules::dequeue()
	 * @covers ::wp_deregister_script_module()
	 * @covers WP_Script_Modules::deregister()
	 * @covers WP_Script_Modules::set_fetchpriority()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
	 * @covers WP_Script_Modules::print_import_map()
	 * @covers WP_Script_Modules::print_script_module_preloads()
	 */
	public function test_comprehensive_methods( bool $use_global_function, bool $only_enqueue ) {
		global $wp_version;
		$wp_version = '99.9.9';

		$register = static function ( ...$args ) use ( $use_global_function ) {
			if ( $use_global_function ) {
				wp_register_script_module( ...$args );
			} else {
				wp_script_modules()->register( ...$args );
			}
		};

		$register_and_enqueue = static function ( ...$args ) use ( $use_global_function, $only_enqueue ) {
			if ( $use_global_function ) {
				if ( $only_enqueue ) {
					wp_enqueue_script_module( ...$args );
				} else {
					wp_register_script_module( ...$args );
					wp_enqueue_script_module( $args[0] );
				}
			} else {
				if ( $only_enqueue ) {
					wp_script_modules()->enqueue( ...$args );
				} else {
					wp_script_modules()->register( ...$args );
					wp_script_modules()->enqueue( $args[0] );
				}
			}
		};

		// Minimal args.
		$register_and_enqueue( 'a', '/a.js' );

		// One Dependency.
		$register( 'b-dep', '/b-dep.js' );
		$register_and_enqueue( 'b', '/b.js', array( 'b-dep' ) );
		$this->assertTrue( wp_script_modules()->set_fetchpriority( 'b', 'low' ) );

		// Two dependencies with different formats and a false version.
		$register( 'c-dep', '/c-static.js', array(), false, array( 'fetchpriority' => 'low' ) );
		$register( 'c-static-dep', '/c-static-dep.js', array(), false, array( 'fetchpriority' => 'high' ) );
		$register_and_enqueue(
			'c',
			'/c.js',
			array(
				'c-dep',
				array(
					'id'     => 'c-static-dep',
					'import' => 'static',
				),
			),
			false
		);

		// Two dependencies, one imported statically and the other dynamically, with a null version.
		$register( 'd-static-dep', '/d-static-dep.js', array(), false, array( 'fetchpriority' => 'auto' ) );
		$register( 'd-dynamic-dep', '/d-dynamic-dep.js', array(), false, array( 'fetchpriority' => 'high' ) ); // Because this is a dynamic import dependency, the fetchpriority will not be reflected in the markup since no SCRIPT tag and no preload LINK are printed, and the importmap SCRIPT does not support designating a priority.
		$register_and_enqueue(
			'd',
			'/d.js',
			array(
				array(
					'id'     => 'd-static-dep',
					'import' => 'static',
				),
				array(
					'id'     => 'd-dynamic-dep',
					'import' => 'dynamic',
				),
			),
			null
		);

		// No dependencies, with a string version version.
		$register_and_enqueue(
			'e',
			'/e.js',
			array(),
			'1.0.0'
		);

		// No dependencies, with a string version and fetch priority.
		$register_and_enqueue(
			'f',
			'/f.js',
			array(),
			'2.0.0',
			array( 'fetchpriority' => 'auto' )
		);

		// No dependencies, with a string version and fetch priority of low.
		$register_and_enqueue(
			'g',
			'/g.js',
			array(),
			'2.0.0',
			array( 'fetchpriority' => 'low' )
		);

		// No dependencies, with a string version and fetch priority of high.
		$register_and_enqueue(
			'h',
			'/h.js',
			array(),
			'3.0.0',
			array( 'fetchpriority' => 'high' )
		);

		$actual = array(
			'preload_links' => $this->get_preloaded_script_modules(),
			'script_tags'   => $this->get_enqueued_script_modules(),
			'import_map'    => $this->get_import_map(),
		);

		$this->assertSame(
			array(
				'preload_links' => array(
					'b-dep'        => array(
						'url'           => '/b-dep.js?ver=99.9.9',
						'fetchpriority' => 'low', // Propagates from 'b'.
					),
					'c-dep'        => array(
						'url'                   => '/c-static.js?ver=99.9.9',
						'fetchpriority'         => 'auto', // Not 'low' because the dependent script 'c' has a fetchpriority of 'auto'.
						'data-wp-fetchpriority' => 'low',
					),
					'c-static-dep' => array(
						'url'                   => '/c-static-dep.js?ver=99.9.9',
						'fetchpriority'         => 'auto', // Propagated from 'c'.
						'data-wp-fetchpriority' => 'high',
					),
					'd-static-dep' => array(
						'url'           => '/d-static-dep.js?ver=99.9.9',
						'fetchpriority' => 'auto',
					),
				),
				'script_tags'   => array(
					'a' => array(
						'url'           => '/a.js?ver=99.9.9',
						'fetchpriority' => 'auto',
					),
					'b' => array(
						'url'           => '/b.js?ver=99.9.9',
						'fetchpriority' => 'low',
					),
					'c' => array(
						'url'           => '/c.js?ver=99.9.9',
						'fetchpriority' => 'auto',
					),
					'd' => array(
						'url'           => '/d.js',
						'fetchpriority' => 'auto',
					),
					'e' => array(
						'url'           => '/e.js?ver=1.0.0',
						'fetchpriority' => 'auto',
					),
					'f' => array(
						'url'           => '/f.js?ver=2.0.0',
						'fetchpriority' => 'auto',
					),
					'g' => array(
						'url'           => '/g.js?ver=2.0.0',
						'fetchpriority' => 'low',
					),
					'h' => array(
						'url'           => '/h.js?ver=3.0.0',
						'fetchpriority' => 'high',
					),
				),
				'import_map'    => array(
					'b-dep'         => '/b-dep.js?ver=99.9.9',
					'c-dep'         => '/c-static.js?ver=99.9.9',
					'c-static-dep'  => '/c-static-dep.js?ver=99.9.9',
					'd-static-dep'  => '/d-static-dep.js?ver=99.9.9',
					'd-dynamic-dep' => '/d-dynamic-dep.js?ver=99.9.9',
				),
			),
			$actual,
			"Snapshot:\n" . var_export( $actual, true )
		);

		// Dequeue the first half of the scripts.
		foreach ( array( 'a', 'b', 'c', 'd' ) as $id ) {
			if ( $use_global_function ) {
				wp_dequeue_script_module( $id );
			} else {
				wp_script_modules()->dequeue( $id );
			}
		}

		$actual = array(
			'preload_links' => $this->get_preloaded_script_modules(),
			'script_tags'   => $this->get_enqueued_script_modules(),
			'import_map'    => $this->get_import_map(),
		);
		$this->assertSame(
			array(
				'preload_links' => array(),
				'script_tags'   => array(
					'e' => array(
						'url'           => '/e.js?ver=1.0.0',
						'fetchpriority' => 'auto',
					),
					'f' => array(
						'url'           => '/f.js?ver=2.0.0',
						'fetchpriority' => 'auto',
					),
					'g' => array(
						'url'           => '/g.js?ver=2.0.0',
						'fetchpriority' => 'low',
					),
					'h' => array(
						'url'           => '/h.js?ver=3.0.0',
						'fetchpriority' => 'high',
					),
				),
				'import_map'    => array(),
			),
			$actual,
			"Snapshot:\n" . var_export( $actual, true )
		);

		// Unregister the remaining scripts.
		foreach ( array( 'e', 'f', 'g', 'h' ) as $id ) {
			if ( $use_global_function ) {
				wp_dequeue_script_module( $id );
			} else {
				wp_script_modules()->dequeue( $id );
			}
		}

		$actual = array(
			'preload_links' => $this->get_preloaded_script_modules(),
			'script_tags'   => $this->get_enqueued_script_modules(),
			'import_map'    => $this->get_import_map(),
		);
		$this->assertSame(
			array(
				'preload_links' => array(),
				'script_tags'   => array(),
				'import_map'    => array(),
			),
			$actual,
			"Snapshot:\n" . var_export( $actual, true )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ use_global_function: bool, only_enqueue: bool }>
	 */
	public function data_test_register_and_enqueue_script_module(): array {
		$data = array();

		foreach ( array( true, false ) as $use_global_function ) {
			foreach ( array( true, false ) as $only_enqueue ) {
				$test_case = compact( 'use_global_function', 'only_enqueue' );
				$key_parts = array();
				foreach ( $test_case as $param_name => $param_value ) {
					$key_parts[] = sprintf( '%s_%s', $param_name, json_encode( $param_value ) );
				}
				$data[ join( '_', $key_parts ) ] = $test_case;
			}
		}

		return $data;
	}

	/**
	 * Tests that a script module gets enqueued correctly after being registered.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
	 * @covers WP_Script_Modules::set_fetchpriority()
	 */
	public function test_wp_enqueue_script_module() {
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->register( 'bar', '/bar.js', array(), false, array( 'fetchpriority' => 'high' ) );
		$this->script_modules->register( 'baz', '/baz.js' );
		$this->assertTrue( $this->script_modules->set_fetchpriority( 'baz', 'low' ) );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'bar' );
		$this->script_modules->enqueue( 'baz' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 3, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo']['url'] );
		$this->assertSame( 'auto', $enqueued_script_modules['foo']['fetchpriority'] );
		$this->assertStringStartsWith( '/bar.js', $enqueued_script_modules['bar']['url'] );
		$this->assertSame( 'high', $enqueued_script_modules['bar']['fetchpriority'] );
		$this->assertStringStartsWith( '/baz.js', $enqueued_script_modules['baz']['url'] );
		$this->assertSame( 'low', $enqueued_script_modules['baz']['fetchpriority'] );
	}

	/**
	* Tests that a script module can be dequeued after being enqueued.
	*
	* @ticket 56313
	*
	* @covers WP_Script_Modules::register()
	* @covers WP_Script_Modules::enqueue()
	* @covers WP_Script_Modules::dequeue()
	* @covers WP_Script_Modules::print_enqueued_script_modules()
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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::deregister()
	 * @covers WP_Script_Modules::get_enqueued_script_modules()
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
	 * @covers WP_Script_Modules::deregister()
	 * @covers WP_Script_Modules::get_enqueued_script_modules()
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
	 * @covers WP_Script_Modules::get_enqueued_script_modules()
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::deregister()
	 * @covers WP_Script_Modules::enqueue()
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
	* @covers WP_Script_Modules::register()
	* @covers WP_Script_Modules::enqueue()
	* @covers WP_Script_Modules::print_enqueued_script_modules()
	*/
	public function test_wp_enqueue_script_module_works_before_register() {
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->enqueue( 'bar' ); // Not registered.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo']['url'] );
		$this->assertSame( 'auto', $enqueued_script_modules['foo']['fetchpriority'] );
		$this->assertArrayNotHasKey( 'bar', $enqueued_script_modules );
	}

	/**
	 * Tests that a script module can be dequeued before it is registered and
	 * ensures that it is not enqueued after registration.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::dequeue()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_import_map()
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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_import_map()
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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_import_map()
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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_import_map()
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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_script_module_preloads()
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
			// Note: The default fetchpriority=auto is upgraded to high because the dependent script module 'static-dep' has a high fetch priority.
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
			),
			false,
			array( 'fetchpriority' => 'high' )
		);
		$this->script_modules->register( 'dynamic-dep', '/dynamic-dep.js' );
		$this->script_modules->register( 'nested-static-dep', '/nested-static-dep.js' );
		$this->script_modules->register( 'nested-dynamic-dep', '/nested-dynamic-dep.js' );
		$this->script_modules->register( 'no-dep', '/no-dep.js' );
		$this->script_modules->enqueue( 'foo' );

		$preloaded_script_modules = $this->get_preloaded_script_modules();

		$this->assertCount( 2, $preloaded_script_modules );
		$this->assertStringStartsWith( '/static-dep.js', $preloaded_script_modules['static-dep']['url'] );
		$this->assertSame( 'auto', $preloaded_script_modules['static-dep']['fetchpriority'] ); // Not 'high'
		$this->assertStringStartsWith( '/nested-static-dep.js', $preloaded_script_modules['nested-static-dep']['url'] );
		$this->assertSame( 'auto', $preloaded_script_modules['nested-static-dep']['fetchpriority'] ); // Auto because the enqueued script foo has the fetchpriority of auto.
		$this->assertArrayNotHasKey( 'dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'nested-dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'no-dep', $preloaded_script_modules );
	}

	/**
	 * Tests that static dependencies of dynamic dependencies are not preloaded.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_script_module_preloads()
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
		$this->assertStringStartsWith( '/static-dep.js', $preloaded_script_modules['static-dep']['url'] );
		$this->assertSame( 'auto', $preloaded_script_modules['static-dep']['fetchpriority'] );
		$this->assertArrayNotHasKey( 'dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'nested-dynamic-dep', $preloaded_script_modules );
		$this->assertArrayNotHasKey( 'no-dep', $preloaded_script_modules );
	}

	/**
	 * Tests that preloaded dependencies don't include enqueued script modules.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_script_module_preloads()
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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_import_map()
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
	 * @covers WP_Script_Modules::get_src()
	 */
	public function test_get_src() {
		$get_src = new ReflectionMethod( $this->script_modules, 'get_src' );
		if ( PHP_VERSION_ID < 80100 ) {
			$get_src->setAccessible( true );
		}

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
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
	 * @covers WP_Script_Modules::print_import_map()
	 * @covers WP_Script_Modules::print_script_module_preloads()
	 * @covers WP_Script_Modules::get_version_query_string()
	 */
	public function test_version_is_propagated_correctly() {
		$this->script_modules->register(
			'foo',
			'/foo.js',
			array(
				'dep',
			),
			'1.0',
			array( 'fetchpriority' => 'auto' )
		);
		$this->script_modules->register( 'dep', '/dep.js', array(), '2.0', array( 'fetchpriority' => 'high' ) );
		$this->script_modules->enqueue( 'foo' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();
		$this->assertSame( '/foo.js?ver=1.0', $enqueued_script_modules['foo']['url'] );
		$this->assertSame( 'auto', $enqueued_script_modules['foo']['fetchpriority'] );

		$import_map = $this->get_import_map();
		$this->assertSame( '/dep.js?ver=2.0', $import_map['dep'] );

		$preloaded_script_modules = $this->get_preloaded_script_modules();
		$this->assertSame( '/dep.js?ver=2.0', $preloaded_script_modules['dep']['url'] );
		$this->assertSame( 'auto', $preloaded_script_modules['dep']['fetchpriority'] ); // Because 'foo' has a priority of 'auto'.
	}

	/**
	 * Tests that a script module is not registered when calling enqueue without a
	 * valid src.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
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
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
	 */
	public function test_wp_enqueue_script_module_registers_with_valid_src() {
		$this->script_modules->enqueue( 'foo', '/foo.js' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo']['url'] );
		$this->assertSame( 'auto', $enqueued_script_modules['foo']['fetchpriority'] );
	}

	/**
	 * Tests that a script module is registered when calling enqueue with a valid
	 * src the second time.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
	 */
	public function test_wp_enqueue_script_module_registers_with_valid_src_the_second_time() {
		$this->script_modules->enqueue( 'foo' ); // Not valid src.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 0, $enqueued_script_modules );
		$this->assertArrayNotHasKey( 'foo', $enqueued_script_modules );

		$this->script_modules->enqueue( 'foo', '/foo.js' ); // Valid src.

		$enqueued_script_modules = $this->get_enqueued_script_modules();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_script_modules['foo']['url'] );
		$this->assertSame( 'auto', $enqueued_script_modules['foo']['fetchpriority'] );
	}

	/**
	 * Tests that a script module is registered with all the params when calling
	 * enqueue.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Script_Modules::register()
	 * @covers WP_Script_Modules::enqueue()
	 * @covers WP_Script_Modules::print_enqueued_script_modules()
	 * @covers WP_Script_Modules::print_import_map()
	 */
	public function test_wp_enqueue_script_module_registers_all_params() {
		$this->script_modules->enqueue( 'foo', '/foo.js', array( 'dep' ), '1.0', array( 'fetchpriority' => 'low' ) );
		$this->script_modules->register( 'dep', '/dep.js' );

		$enqueued_script_modules = $this->get_enqueued_script_modules();
		$import_map              = $this->get_import_map();

		$this->assertCount( 1, $enqueued_script_modules );
		$this->assertSame( '/foo.js?ver=1.0', $enqueued_script_modules['foo']['url'] );
		$this->assertSame( 'low', $enqueued_script_modules['foo']['fetchpriority'] );
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
			'Solidus non-utf8'                       => array( '/', '/', 'iso-8859-1' ),
			'Less than non-utf8'                     => array( '<', '\u003C', 'iso-8859-1' ),
			'Greater than non-utf8'                  => array( '>', '\u003E', 'iso-8859-1' ),
			'Ampersand non-utf8'                     => array( '&', '&', 'iso-8859-1' ),
			'Newline non-utf8'                       => array( "\n", "\\n", 'iso-8859-1' ),
			'Tab non-utf8'                           => array( "\t", "\\t", 'iso-8859-1' ),
			'Form feed non-utf8'                     => array( "\f", "\\f", 'iso-8859-1' ),
			'Carriage return non-utf8'               => array( "\r", "\\r", 'iso-8859-1' ),
			'Line separator non-utf8'                => array( "\u{2028}", "\u2028", 'iso-8859-1' ),
			'Paragraph separator non-utf8'           => array( "\u{2029}", "\u2029", 'iso-8859-1' ),
			/*
			 * The following is the Flag of England emoji
			 * PHP: "\u{1F3F4}\u{E0067}\u{E0062}\u{E0065}\u{E006E}\u{E0067}\u{E007F}"
			 */
			'Flag of england non-utf8'               => array( '🏴󠁧󠁢󠁥󠁮󠁧󠁿', "\ud83c\udff4\udb40\udc67\udb40\udc62\udb40\udc65\udb40\udc6e\udb40\udc67\udb40\udc7f", 'iso-8859-1' ),
			'Malicious script closer non-utf8'       => array( '</script>', '\u003C/script\u003E', 'iso-8859-1' ),
			'Entity-encoded malicious script closer non-utf8' => array( '&lt;/script&gt;', '&lt;/script&gt;', 'iso-8859-1' ),

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
	 * Data provider for test_fetchpriority_values.
	 *
	 * @return array<string, array{fetchpriority: string}>
	 */
	public function data_provider_fetchpriority_values(): array {
		return array(
			'auto' => array( 'fetchpriority' => 'auto' ),
			'low'  => array( 'fetchpriority' => 'low' ),
			'high' => array( 'fetchpriority' => 'high' ),
		);
	}

	/**
	 * Tests that valid fetchpriority values are correctly added to the registered module.
	 *
	 * @ticket 61734
	 *
	 * @covers WP_Script_Modules::register
	 * @covers WP_Script_Modules::set_fetchpriority
	 *
	 * @dataProvider data_provider_fetchpriority_values
	 *
	 * @param string $fetchpriority The fetchpriority value to test.
	 */
	public function test_fetchpriority_values( string $fetchpriority ) {
		$this->script_modules->register( 'test-script', '/test-script.js', array(), null, array( 'fetchpriority' => $fetchpriority ) );
		$registered_modules = $this->get_registered_script_modules( $this->script_modules );
		$this->assertSame( $fetchpriority, $registered_modules['test-script']['fetchpriority'] );

		$this->script_modules->register( 'test-script-2', '/test-script-2.js' );
		$this->assertTrue( $this->script_modules->set_fetchpriority( 'test-script-2', $fetchpriority ) );
		$registered_modules = $this->get_registered_script_modules( $this->script_modules );
		$this->assertSame( $fetchpriority, $registered_modules['test-script-2']['fetchpriority'] );

		$this->assertTrue( $this->script_modules->set_fetchpriority( 'test-script-2', '' ) );
		$registered_modules = $this->get_registered_script_modules( $this->script_modules );
		$this->assertSame( 'auto', $registered_modules['test-script-2']['fetchpriority'] );
	}

	/**
	 * Tests that a script module with an invalid fetchpriority value gets a value of auto.
	 *
	 * @ticket 61734
	 *
	 * @covers WP_Script_Modules::register
	 * @expectedIncorrectUsage WP_Script_Modules::register
	 */
	public function test_register_script_module_having_fetchpriority_with_invalid_value() {
		$this->script_modules->register( 'foo', '/foo.js', array(), false, array( 'fetchpriority' => 'silly' ) );
		$registered_modules = $this->get_registered_script_modules( $this->script_modules );
		$this->assertSame( 'auto', $registered_modules['foo']['fetchpriority'] );
		$this->assertArrayHasKey( 'WP_Script_Modules::register', $this->caught_doing_it_wrong );
		$this->assertStringContainsString( 'Invalid fetchpriority `silly`', $this->caught_doing_it_wrong['WP_Script_Modules::register'] );
	}

	/**
	 * Tests that a script module with an invalid fetchpriority value type gets a value of auto.
	 *
	 * @ticket 61734
	 *
	 * @covers WP_Script_Modules::register
	 * @expectedIncorrectUsage WP_Script_Modules::register
	 */
	public function test_register_script_module_having_fetchpriority_with_invalid_value_type() {
		$this->script_modules->register( 'foo', '/foo.js', array(), false, array( 'fetchpriority' => array( 'WHY AM I NOT A STRING???' ) ) );
		$registered_modules = $this->get_registered_script_modules( $this->script_modules );
		$this->assertSame( 'auto', $registered_modules['foo']['fetchpriority'] );
		$this->assertArrayHasKey( 'WP_Script_Modules::register', $this->caught_doing_it_wrong );
		$this->assertStringContainsString( 'Invalid fetchpriority `array`', $this->caught_doing_it_wrong['WP_Script_Modules::register'] );
	}

	/**
	 * Tests that a setting the fetchpriority for script module with an invalid value is ignored so that it remains auto.
	 *
	 * @ticket 61734
	 *
	 * @covers WP_Script_Modules::register
	 * @covers WP_Script_Modules::set_fetchpriority
	 * @expectedIncorrectUsage WP_Script_Modules::set_fetchpriority
	 */
	public function test_set_fetchpriority_with_invalid_value() {
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->set_fetchpriority( 'foo', 'silly' );
		$registered_modules = $this->get_registered_script_modules( $this->script_modules );
		$this->assertSame( 'auto', $registered_modules['foo']['fetchpriority'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{enqueues: string[], expected: array}>
	 */
	public function data_provider_to_test_fetchpriority_bumping(): array {
		return array(
			'enqueue_bajo' => array(
				'enqueues' => array( 'bajo' ),
				'expected' => array(
					'preload_links' => array(),
					'script_tags'   => array(
						'bajo' => array(
							'url'                   => '/bajo.js',
							'fetchpriority'         => 'high',
							'data-wp-fetchpriority' => 'low',
						),
					),
					'import_map'    => array(
						'dyno' => '/dyno.js',
					),
				),
			),
			'enqueue_auto' => array(
				'enqueues' => array( 'auto' ),
				'expected' => array(
					'preload_links' => array(
						'bajo' => array(
							'url'                   => '/bajo.js',
							'fetchpriority'         => 'auto',
							'data-wp-fetchpriority' => 'low',
						),
					),
					'script_tags'   => array(
						'auto' => array(
							'url'                   => '/auto.js',
							'fetchpriority'         => 'high',
							'data-wp-fetchpriority' => 'auto',
						),
					),
					'import_map'    => array(
						'bajo' => '/bajo.js',
						'dyno' => '/dyno.js',
					),
				),
			),
			'enqueue_alto' => array(
				'enqueues' => array( 'alto' ),
				'expected' => array(
					'preload_links' => array(
						'auto' => array(
							'url'           => '/auto.js',
							'fetchpriority' => 'high',
						),
						'bajo' => array(
							'url'                   => '/bajo.js',
							'fetchpriority'         => 'high',
							'data-wp-fetchpriority' => 'low',
						),
					),
					'script_tags'   => array(
						'alto' => array(
							'url'           => '/alto.js',
							'fetchpriority' => 'high',
						),
					),
					'import_map'    => array(
						'auto' => '/auto.js',
						'bajo' => '/bajo.js',
						'dyno' => '/dyno.js',
					),
				),
			),
		);
	}

	/**
	 * Tests a higher fetchpriority on a dependent script module causes the fetchpriority of a dependency script module to be bumped.
	 *
	 * @ticket 61734
	 *
	 * @covers WP_Script_Modules::print_enqueued_script_modules
	 * @covers WP_Script_Modules::get_dependents
	 * @covers WP_Script_Modules::get_recursive_dependents
	 * @covers WP_Script_Modules::get_highest_fetchpriority
	 * @covers WP_Script_Modules::print_script_module_preloads
	 *
	 * @dataProvider data_provider_to_test_fetchpriority_bumping
	 */
	public function test_fetchpriority_bumping( array $enqueues, array $expected ) {
		$this->script_modules->register(
			'dyno',
			'/dyno.js',
			array(),
			null,
			array( 'fetchpriority' => 'low' ) // This won't show up anywhere since it is a dynamic import dependency.
		);

		$this->script_modules->register(
			'bajo',
			'/bajo.js',
			array(
				array(
					'id'     => 'dyno',
					'import' => 'dynamic',
				),
			),
			null,
			array( 'fetchpriority' => 'low' )
		);

		$this->script_modules->register(
			'auto',
			'/auto.js',
			array(
				array(
					'id'     => 'bajo',
					'import' => 'static',
				),
			),
			null,
			array( 'fetchpriority' => 'auto' )
		);
		$this->script_modules->register(
			'alto',
			'/alto.js',
			array( 'auto' ),
			null,
			array( 'fetchpriority' => 'high' )
		);

		foreach ( $enqueues as $enqueue ) {
			$this->script_modules->enqueue( $enqueue );
		}

		$actual = array(
			'preload_links' => $this->get_preloaded_script_modules(),
			'script_tags'   => $this->get_enqueued_script_modules(),
			'import_map'    => $this->get_import_map(),
		);
		$this->assertSame(
			$expected,
			$actual,
			"Snapshot:\n" . var_export( $actual, true )
		);
	}

	/**
	 * Tests bumping fetchpriority with complex dependency graph.
	 *
	 * @ticket 61734
	 * @link https://github.com/WordPress/wordpress-develop/pull/9770#issuecomment-3280065818
	 *
	 * @covers WP_Script_Modules::print_enqueued_script_modules
	 * @covers WP_Script_Modules::get_dependents
	 * @covers WP_Script_Modules::get_recursive_dependents
	 * @covers WP_Script_Modules::get_highest_fetchpriority
	 * @covers WP_Script_Modules::print_script_module_preloads
	 */
	public function test_fetchpriority_bumping_a_to_z() {
		wp_register_script_module( 'a', '/a.js', array( 'b' ), null, array( 'fetchpriority' => 'low' ) );
		wp_register_script_module( 'b', '/b.js', array( 'c' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script_module( 'c', '/c.js', array( 'd', 'e' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script_module( 'd', '/d.js', array( 'z' ), null, array( 'fetchpriority' => 'high' ) );
		wp_register_script_module( 'e', '/e.js', array(), null, array( 'fetchpriority' => 'auto' ) );

		wp_register_script_module( 'x', '/x.js', array( 'd', 'y' ), null, array( 'fetchpriority' => 'high' ) );
		wp_register_script_module( 'y', '/y.js', array( 'z' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script_module( 'z', '/z.js', array(), null, array( 'fetchpriority' => 'auto' ) );

		// The fetch priorities are derived from these enqueued dependents.
		wp_enqueue_script_module( 'a' );
		wp_enqueue_script_module( 'x' );

		$actual   = get_echo( array( wp_script_modules(), 'print_script_module_preloads' ) );
		$actual  .= get_echo( array( wp_script_modules(), 'print_enqueued_script_modules' ) );
		$expected = '
			<link rel="modulepreload" href="/b.js" id="b-js-modulepreload" fetchpriority="low">
			<link rel="modulepreload" href="/c.js" id="c-js-modulepreload" fetchpriority="low">
			<link rel="modulepreload" href="/d.js" id="d-js-modulepreload" fetchpriority="high">
			<link rel="modulepreload" href="/e.js" id="e-js-modulepreload" fetchpriority="low">
			<link rel="modulepreload" href="/z.js" id="z-js-modulepreload" fetchpriority="high">
			<link rel="modulepreload" href="/y.js" id="y-js-modulepreload" fetchpriority="high">
			<script type="module" src="/a.js" id="a-js-module" fetchpriority="low"></script>
			<script type="module" src="/x.js" id="x-js-module" fetchpriority="high"></script>
		';
		$this->assertEqualHTML( $expected, $actual, '<body>', "Snapshot:\n$actual" );
	}

	/**
	 * Tests bumping fetchpriority with complex dependency graph.
	 *
	 * @ticket 61734
	 * @link https://github.com/WordPress/wordpress-develop/pull/9770#issuecomment-3284266884
	 *
	 * @covers WP_Script_Modules::print_enqueued_script_modules
	 * @covers WP_Script_Modules::get_dependents
	 * @covers WP_Script_Modules::get_recursive_dependents
	 * @covers WP_Script_Modules::get_highest_fetchpriority
	 * @covers WP_Script_Modules::print_script_module_preloads
	 */
	public function test_fetchpriority_propagation() {
		// The high fetchpriority for this module will be disregarded because its enqueued dependent has a non-high priority.
		wp_register_script_module( 'a', '/a.js', array( 'd', 'e' ), null, array( 'fetchpriority' => 'high' ) );
		wp_register_script_module( 'b', '/b.js', array( 'e' ), null );
		wp_register_script_module( 'c', '/c.js', array( 'e', 'f' ), null );
		wp_register_script_module( 'd', '/d.js', array(), null );
		// The low fetchpriority for this module will be disregarded because its enqueued dependent has a non-low priority.
		wp_register_script_module( 'e', '/e.js', array(), null, array( 'fetchpriority' => 'low' ) );
		wp_register_script_module( 'f', '/f.js', array(), null );

		wp_register_script_module( 'x', '/x.js', array( 'a' ), null, array( 'fetchpriority' => 'low' ) );
		wp_register_script_module( 'y', '/y.js', array( 'b' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script_module( 'z', '/z.js', array( 'c' ), null, array( 'fetchpriority' => 'high' ) );

		wp_enqueue_script_module( 'x' );
		wp_enqueue_script_module( 'y' );
		wp_enqueue_script_module( 'z' );

		$actual   = get_echo( array( wp_script_modules(), 'print_script_module_preloads' ) );
		$actual  .= get_echo( array( wp_script_modules(), 'print_enqueued_script_modules' ) );
		$expected = '
			<link rel="modulepreload" href="/a.js" id="a-js-modulepreload" fetchpriority="low" data-wp-fetchpriority="high">
			<link rel="modulepreload" href="/d.js" id="d-js-modulepreload" fetchpriority="low">
			<link rel="modulepreload" href="/e.js" id="e-js-modulepreload" fetchpriority="high" data-wp-fetchpriority="low">
			<link rel="modulepreload" href="/b.js" id="b-js-modulepreload">
			<link rel="modulepreload" href="/c.js" id="c-js-modulepreload" fetchpriority="high">
			<link rel="modulepreload" href="/f.js" id="f-js-modulepreload" fetchpriority="high">
			<script type="module" src="/x.js" id="x-js-module" fetchpriority="low"></script>
			<script type="module" src="/y.js" id="y-js-module"></script>
			<script type="module" src="/z.js" id="z-js-module" fetchpriority="high"></script>
		';
		$this->assertEqualHTML( $expected, $actual, '<body>', "Snapshot:\n$actual" );
	}

	/**
	 * Tests that default script modules are printed as expected.
	 *
	 * @covers ::wp_default_script_modules
	 * @covers WP_Script_Modules::print_script_module_preloads
	 * @covers WP_Script_Modules::print_enqueued_script_modules
	 */
	public function test_default_script_modules() {
		wp_default_script_modules();
		wp_enqueue_script_module( '@wordpress/a11y' );
		wp_enqueue_script_module( '@wordpress/block-library/navigation/view' );

		$actual  = get_echo( array( wp_script_modules(), 'print_script_module_preloads' ) );
		$actual .= get_echo( array( wp_script_modules(), 'print_enqueued_script_modules' ) );

		$actual = $this->normalize_markup_for_snapshot( $actual );

		$expected = '
			<link rel="modulepreload" href="/wp-includes/js/dist/script-modules/interactivity/debug.min.js" id="@wordpress/interactivity-js-modulepreload" fetchpriority="low">
			<script type="module" src="/wp-includes/js/dist/script-modules/a11y/index.min.js" id="@wordpress/a11y-js-module" fetchpriority="low"></script>
			<script type="module" src="/wp-includes/js/dist/script-modules/block-library/navigation/view.min.js" id="@wordpress/block-library/navigation/view-js-module" fetchpriority="low"></script>
		';
		$this->assertEqualHTML( $expected, $actual, '<body>', "Snapshot:\n$actual" );
	}

	/**
	 * Tests that a dependent with high priority for default script modules with a low fetch priority are printed as expected.
	 *
	 * @covers ::wp_default_script_modules
	 * @covers WP_Script_Modules::print_script_module_preloads
	 * @covers WP_Script_Modules::print_enqueued_script_modules
	 */
	public function test_dependent_of_default_script_modules() {
		wp_default_script_modules();
		wp_enqueue_script_module(
			'super-important',
			'/super-important-module.js',
			array( '@wordpress/a11y', '@wordpress/block-library/navigation/view' ),
			null,
			array( 'fetchpriority' => 'high' )
		);

		$actual  = get_echo( array( wp_script_modules(), 'print_script_module_preloads' ) );
		$actual .= get_echo( array( wp_script_modules(), 'print_enqueued_script_modules' ) );

		$actual = $this->normalize_markup_for_snapshot( $actual );

		$expected = '
			<link rel="modulepreload" href="/wp-includes/js/dist/script-modules/a11y/index.min.js" id="@wordpress/a11y-js-modulepreload" fetchpriority="high" data-wp-fetchpriority="low">
			<link rel="modulepreload" href="/wp-includes/js/dist/script-modules/block-library/navigation/view.min.js" id="@wordpress/block-library/navigation/view-js-modulepreload" fetchpriority="high" data-wp-fetchpriority="low">
			<link rel="modulepreload" href="/wp-includes/js/dist/script-modules/interactivity/debug.min.js" id="@wordpress/interactivity-js-modulepreload" fetchpriority="high" data-wp-fetchpriority="low">
			<script type="module" src="/super-important-module.js" id="super-important-js-module" fetchpriority="high"></script>
		';
		$this->assertEqualHTML( $expected, $actual, '<body>', "Snapshot:\n$actual" );
	}

	/**
	 * Normalizes markup for snapshot.
	 *
	 * @param string $markup Markup.
	 * @return string Normalized markup.
	 */
	private function normalize_markup_for_snapshot( string $markup ): string {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$clean_url = static function ( string $url ): string {
			$url = preg_replace( '#^https?://[^/]+#', '', $url );
			return remove_query_arg( 'ver', $url );
		};
		while ( $processor->next_tag() ) {
			if ( 'LINK' === $processor->get_tag() && is_string( $processor->get_attribute( 'href' ) ) ) {
				$processor->set_attribute( 'href', $clean_url( $processor->get_attribute( 'href' ) ) );
			} elseif ( 'SCRIPT' === $processor->get_tag() && is_string( $processor->get_attribute( 'src' ) ) ) {
				$processor->set_attribute( 'src', $clean_url( $processor->get_attribute( 'src' ) ) );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * Tests that manipulating the queue works as expected.
	 *
	 * @ticket 63676
	 *
	 * @covers WP_Script_Modules::get_queue
	 * @covers WP_Script_Modules::queue
	 * @covers WP_Script_Modules::dequeue
	 */
	public function test_direct_queue_manipulation() {
		$this->script_modules->register( 'foo', '/foo.js' );
		$this->script_modules->register( 'bar', '/bar.js' );
		$this->script_modules->register( 'baz', '/baz.js' );
		$this->assertSame( array(), $this->script_modules->get_queue(), 'Expected queue to be empty.' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'foo' );
		$this->script_modules->enqueue( 'bar' );
		$this->assertSame( array( 'foo', 'bar' ), $this->script_modules->get_queue(), 'Expected two deduplicated queued items.' );
		$this->script_modules->dequeue( 'foo' );
		$this->script_modules->dequeue( 'foo' );
		$this->script_modules->enqueue( 'baz' );
		$this->assertSame( array( 'bar', 'baz' ), $this->script_modules->get_queue(), 'Expected items tup be updated after dequeue and enqueue.' );
		$this->script_modules->dequeue( 'baz' );
		$this->script_modules->dequeue( 'bar' );
		$this->assertSame( array(), $this->script_modules->get_queue(), 'Expected queue to be empty after dequeueing both items.' );
	}

	/**
	 * Gets registered script modules.
	 *
	 * @param WP_Script_Modules $script_modules
	 * @return array<string, array> Registered modules.
	 */
	private function get_registered_script_modules( WP_Script_Modules $script_modules ): array {
		$reflection_class    = new ReflectionClass( $script_modules );
		$registered_property = $reflection_class->getProperty( 'registered' );
		if ( PHP_VERSION_ID < 80100 ) {
			$registered_property->setAccessible( true );
		}
		return $registered_property->getValue( $script_modules );
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
