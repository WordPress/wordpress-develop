<?php
/**
 * @group modules
 * @covers ::wp_register_module
 * @covers ::wp_enqueue_module
 * @covers ::wp_dequeue_module
 * @covers WP_Modules::print_enqueued_modules
 * @covers WP_Modules::print_import_map
 * @covers WP_Modules::print_module_preloads
 */
class Tests_Modules_Functions extends WP_UnitTestCase {
	/**
	 * Stores the original value of WP_Modules::$registered to restore it later.
	 *
	 * @var array
	 */
	protected $old_registered;

	/**
	 * Stores the original value of WP_Modules::$enqueued_before_registered to
	 * restore it later.
	 *
	 * @var array
	 */
	protected $old_enqueued_before_registered;

	public function set_up() {
		parent::set_up();

		$registered = new ReflectionProperty( 'WP_Modules', 'registered' );
		$registered->setAccessible( true );
		$this->old_registered = $registered->getValue();
		$registered->setValue( null, array() );

		$enqueued_before_registered = new ReflectionProperty( 'WP_Modules', 'enqueued_before_registered' );
		$enqueued_before_registered->setAccessible( true );
		$this->old_enqueued_before_registered = $enqueued_before_registered->getValue();
		$enqueued_before_registered->setValue( null, array() );
	}

	public function tear_down() {
		$registered = new ReflectionProperty( 'WP_Modules', 'registered' );
		$registered->setAccessible( true );
		$registered->setValue( null, $this->old_registered );

		$enqueued_before_registered = new ReflectionProperty( 'WP_Modules', 'enqueued_before_registered' );
		$enqueued_before_registered->setAccessible( true );
		$enqueued_before_registered->setValue( null, $this->old_enqueued_before_registered );

		parent::tear_down();
	}

	/**
	 * Gets a list of the enqueued modules.
	 *
	 * @return array Enqueued module URLs, keyed by module identifier.
	 */
	public function get_enqueued_modules() {
		$modules_markup   = get_echo( array( 'WP_Modules', 'print_enqueued_modules' ) );
		$p                = new WP_HTML_Tag_Processor( $modules_markup );
		$enqueued_modules = array();

		while ( $p->next_tag(
			array(
				'tag'  => 'SCRIPT',
				'type' => 'module',
			)
		) ) {
			$enqueued_modules[ $p->get_attribute( 'id' ) ] = $p->get_attribute( 'src' );
		}

		return $enqueued_modules;
	}

	/**
	 * Gets the modules listed in the import map.
	 *
	 * @return array Import map entry URLs, keyed by module identifier.
	 */
	public function get_import_map() {
		$import_map_markup = get_echo( array( 'WP_Modules', 'print_import_map' ) );
		preg_match( '/<script type="importmap">.*?(\{.*\}).*?<\/script>/s', $import_map_markup, $import_map_string );
		return json_decode( $import_map_string[1], true )['imports'];
	}

	/**
	 * Gets a list of preloaded modules.
	 *
	 * @return array Preloaded module URLs, keyed by module identifier.
	 */
	public function get_preloaded_modules() {
		$preloaded_markup  = get_echo( array( 'WP_Modules', 'print_module_preloads' ) );
		$p                 = new WP_HTML_Tag_Processor( $preloaded_markup );
		$preloaded_modules = array();

		while ( $p->next_tag(
			array(
				'tag' => 'LINK',
				'rel' => 'modulepreload',
			)
		) ) {
			$preloaded_modules[ $p->get_attribute( 'id' ) ] = $p->get_attribute( 'href' );
		}

		return $preloaded_modules;
	}

	/**
	 * Tests that a module gets enqueued correctly after being registered.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_enqueued_modules
	 */
	public function test_wp_enqueue_module() {
		wp_register_module( 'foo', '/foo.js' );
		wp_register_module( 'bar', '/bar.js' );
		wp_enqueue_module( 'foo' );
		wp_enqueue_module( 'bar' );

		$enqueued_modules = $this->get_enqueued_modules();

		$this->assertCount( 2, $enqueued_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_modules['foo'] );
		$this->assertStringStartsWith( '/bar.js', $enqueued_modules['bar'] );
	}

	/**
	* Tests that a module can be dequeued after being enqueued.
	*
	* @ticket 56313
	*
	* @covers ::wp_register_module
	* @covers ::wp_enqueue_module
	* @covers ::wp_dequeue_module
	 * @covers WP_Modules::print_enqueued_modules
	*/
	public function test_wp_dequeue_module() {
		wp_register_module( 'foo', '/foo.js' );
		wp_register_module( 'bar', '/bar.js' );
		wp_enqueue_module( 'foo' );
		wp_enqueue_module( 'bar' );
		wp_dequeue_module( 'foo' ); // Dequeued.

		$enqueued_modules = $this->get_enqueued_modules();

		$this->assertCount( 1, $enqueued_modules );
		$this->assertFalse( isset( $enqueued_modules['foo'] ) );
		$this->assertTrue( isset( $enqueued_modules['bar'] ) );
	}

	/**
	* Tests that a module can be enqueued before it is registered, and will be
	* handled correctly once registered.
	*
	* @ticket 56313
	*
	* @covers ::wp_register_module
	* @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_enqueued_modules
	*/
	public function test_wp_enqueue_module_works_before_register() {
		wp_enqueue_module( 'foo' );
		wp_register_module( 'foo', '/foo.js' );
		wp_enqueue_module( 'bar' ); // Not registered.

		$enqueued_modules = $this->get_enqueued_modules();

		$this->assertCount( 1, $enqueued_modules );
		$this->assertStringStartsWith( '/foo.js', $enqueued_modules['foo'] );
		$this->assertFalse( isset( $enqueued_modules['bar'] ) );
	}

	/**
	 * Tests that a module can be dequeued before it is registered and ensures
	 * that it is not enqueued after registration.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers ::wp_dequeue_module
	 * @covers WP_Modules::print_enqueued_modules
	 */
	public function test_wp_dequeue_module_works_before_register() {
		wp_enqueue_module( 'foo' );
		wp_enqueue_module( 'bar' );
		wp_dequeue_module( 'foo' );
		wp_register_module( 'foo', '/foo.js' );
		wp_register_module( 'bar', '/bar.js' );

		$enqueued_modules = $this->get_enqueued_modules();

		$this->assertCount( 1, $enqueued_modules );
		$this->assertFalse( isset( $enqueued_modules['foo'] ) );
		$this->assertTrue( isset( $enqueued_modules['bar'] ) );
	}

	/**
	 * Tests that dependencies for a registered module are added to the import map
	 * when the module is enqueued.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_import_map
	 */
	public function test_wp_import_map_dependencies() {
		wp_register_module( 'foo', '/foo.js', array( 'dep' ) );
		wp_register_module( 'dep', '/dep.js' );
		wp_register_module( 'no-dep', '/no-dep.js' );
		wp_enqueue_module( 'foo' );

		$import_map = $this->get_import_map();

		$this->assertCount( 1, $import_map );
		$this->assertStringStartsWith( '/dep.js', $import_map['dep'] );
		$this->assertFalse( isset( $import_map['no-dep'] ) );
	}

	/**
	 * Tests that dependencies are not duplicated in the import map when multiple
	 * modules require the same dependency.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_import_map
	 */
	public function test_wp_import_map_no_duplicate_dependencies() {
		wp_register_module( 'foo', '/foo.js', array( 'dep' ) );
		wp_register_module( 'bar', '/bar.js', array( 'dep' ) );
		wp_register_module( 'dep', '/dep.js' );
		wp_enqueue_module( 'foo' );
		wp_enqueue_module( 'bar' );

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
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_import_map
	 */
	public function test_wp_import_map_recursive_dependencies() {
		wp_register_module(
			'foo',
			'/foo.js',
			array(
				'static-dep',
				array(
					'id'   => 'dynamic-dep',
					'type' => 'dynamic',
				),
			)
		);
		wp_register_module(
			'static-dep',
			'/static-dep.js',
			array(
				array(
					'id'   => 'nested-static-dep',
					'type' => 'static',
				),
				array(
					'id'   => 'nested-dynamic-dep',
					'type' => 'dynamic',
				),
			)
		);
		wp_register_module( 'dynamic-dep', '/dynamic-dep.js' );
		wp_register_module( 'nested-static-dep', '/nested-static-dep.js' );
		wp_register_module( 'nested-dynamic-dep', '/nested-dynamic-dep.js' );
		wp_register_module( 'no-dep', '/no-dep.js' );
		wp_enqueue_module( 'foo' );

		$import_map = $this->get_import_map();

		$this->assertStringStartsWith( '/static-dep.js', $import_map['static-dep'] );
		$this->assertStringStartsWith( '/dynamic-dep.js', $import_map['dynamic-dep'] );
		$this->assertStringStartsWith( '/nested-static-dep.js', $import_map['nested-static-dep'] );
		$this->assertStringStartsWith( '/nested-dynamic-dep.js', $import_map['nested-dynamic-dep'] );
		$this->assertFalse( isset( $import_map['no-dep'] ) );
	}

	/**
	 * Tests that only static dependencies are preloaded and dynamic ones are
	 * excluded.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_module_preloads
	 */
	public function test_wp_enqueue_preloaded_static_dependencies() {
		wp_register_module(
			'foo',
			'/foo.js',
			array(
				'static-dep',
				array(
					'id'   => 'dynamic-dep',
					'type' => 'dynamic',
				),
			)
		);
		wp_register_module(
			'static-dep',
			'/static-dep.js',
			array(
				array(
					'id'   => 'nested-static-dep',
					'type' => 'static',
				),
				array(
					'id'   => 'nested-dynamic-dep',
					'type' => 'dynamic',
				),
			)
		);
		wp_register_module( 'dynamic-dep', '/dynamic-dep.js' );
		wp_register_module( 'nested-static-dep', '/nested-static-dep.js' );
		wp_register_module( 'nested-dynamic-dep', '/nested-dynamic-dep.js' );
		wp_register_module( 'no-dep', '/no-dep.js' );
		wp_enqueue_module( 'foo' );

		$preloaded_modules = $this->get_preloaded_modules();

		$this->assertCount( 2, $preloaded_modules );
		$this->assertStringStartsWith( '/static-dep.js', $preloaded_modules['static-dep'] );
		$this->assertStringStartsWith( '/nested-static-dep.js', $preloaded_modules['nested-static-dep'] );
		$this->assertFalse( isset( $preloaded_modules['no-dep'] ) );
		$this->assertFalse( isset( $preloaded_modules['dynamic-dep'] ) );
		$this->assertFalse( isset( $preloaded_modules['nested-dynamic-dep'] ) );
	}

	/**
	 * Tests that static dependencies of dynamic depenendencies are not preloaded.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_module_preloads
	 */
	public function test_wp_dont_preload_static_dependencies_of_dynamic_dependencies() {
		wp_register_module(
			'foo',
			'/foo.js',
			array(
				'static-dep',
				array(
					'id'   => 'dynamic-dep',
					'type' => 'dynamic',
				),
			)
		);
		wp_register_module( 'static-dep', '/static-dep.js' );
		wp_register_module( 'dynamic-dep', '/dynamic-dep.js', array( 'nested-static-dep' ) );
		wp_register_module( 'nested-static-dep', '/nested-static-dep.js' );
		wp_register_module( 'no-dep', '/no-dep.js' );
		wp_enqueue_module( 'foo' );

		$preloaded_modules = $this->get_preloaded_modules();

		$this->assertCount( 1, $preloaded_modules );
		$this->assertStringStartsWith( '/static-dep.js', $preloaded_modules['static-dep'] );
		$this->assertFalse( isset( $preloaded_modules['dynamic-dep'] ) );
		$this->assertFalse( isset( $preloaded_modules['nested-static-dep'] ) );
		$this->assertFalse( isset( $preloaded_modules['no-dep'] ) );
	}

	/**
	 * Tests that preloaded dependencies don't include enqueued modules.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_module_preloads
	 */
	public function test_wp_preloaded_dependencies_filter_enqueued_modules() {
		wp_register_module(
			'foo',
			'/foo.js',
			array(
				'dep',
				'enqueued-dep',
			)
		);
		wp_register_module( 'dep', '/dep.js' );
		wp_register_module( 'enqueued-dep', '/enqueued-dep.js' );
		wp_enqueue_module( 'foo' );
		wp_enqueue_module( 'enqueued-dep' ); // Not preloaded.

		$preloaded_modules = $this->get_preloaded_modules();

		$this->assertCount( 1, $preloaded_modules );
		$this->assertTrue( isset( $preloaded_modules['dep'] ) );
		$this->assertFalse( isset( $preloaded_modules['enqueued-dep'] ) );
	}

	/**
	 * Tests that enqueued modules with dependants correctly add both the module
	 * and its dependencies to the import map.
	 *
	 * @ticket 56313
	 *
	 * @covers ::wp_register_module
	 * @covers ::wp_enqueue_module
	 * @covers WP_Modules::print_import_map
	 */
	public function test_wp_enqueued_modules_with_dependants_add_import_map() {
		wp_register_module(
			'foo',
			'/foo.js',
			array(
				'dep',
				'enqueued-dep',
			)
		);
		wp_register_module( 'dep', '/dep.js' );
		wp_register_module( 'enqueued-dep', '/enqueued-dep.js' );
		wp_enqueue_module( 'foo' );
		wp_enqueue_module( 'enqueued-dep' ); // Also in the import map.

		$import_map = $this->get_import_map();

		$this->assertCount( 2, $import_map );
		$this->assertTrue( isset( $import_map['dep'] ) );
		$this->assertTrue( isset( $import_map['enqueued-dep'] ) );
	}
}
