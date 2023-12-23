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
	 * Stores a reference to the ReflectionProperty instance of the
	 * WP_Modules::$registered property.
	 *
	 * @var ReflectionProperty
	 */
	protected $registered;

	/**
	 * Stores the original value of WP_Modules::$registered to restore it later.
	 *
	 * @var array
	 */
	protected $old_registered;

	/**
	 * Stores a reference to the ReflectionProperty instance of the
	 * WP_Modules::$enqueued_before_registered property.
	 *
	 * @var ReflectionProperty
	 */
	protected $enqueued_before_registered;

	/**
	 * Stores the original value of WP_Modules::$enqueued_before_registered to
	 * restore it later.
	 *
	 * @var array
	 */
	protected $old_enqueued_before_registered;

	public function set_up() {
		parent::set_up();

		$wp_modules = new ReflectionClass( 'WP_Modules' );

		$this->old_registered                 = $wp_modules->getStaticPropertyValue( 'registered' );
		$this->old_enqueued_before_registered = $wp_modules->getStaticPropertyValue( 'enqueued_before_registered' );

		$wp_modules->setStaticPropertyValue( 'registered', array() );
		$wp_modules->setStaticPropertyValue( 'enqueued_before_registered', array() );
	}

	public function tear_down() {
		$wp_modules = new ReflectionClass( 'WP_Modules' );

		$wp_modules->setStaticPropertyValue( 'registered', $this->old_registered );
		$wp_modules->setStaticPropertyValue( 'enqueued_before_registered', $this->old_enqueued_before_registered );

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

		$this->assertEquals( 2, count( $enqueued_modules ) );
		$this->assertEquals( true, str_starts_with( $enqueued_modules['foo'], '/foo.js' ) );
		$this->assertEquals( true, str_starts_with( $enqueued_modules['bar'], '/bar.js' ) );
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

		$this->assertEquals( 1, count( $enqueued_modules ) );
		$this->assertEquals( false, isset( $enqueued_modules['foo'] ) );
		$this->assertEquals( true, isset( $enqueued_modules['bar'] ) );
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

		$this->assertEquals( 1, count( $enqueued_modules ) );
		$this->assertEquals( true, str_starts_with( $enqueued_modules['foo'], '/foo.js' ) );
		$this->assertEquals( false, isset( $enqueued_modules['bar'] ) );
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

		$this->assertEquals( 1, count( $enqueued_modules ) );
		$this->assertEquals( false, isset( $enqueued_modules['foo'] ) );
		$this->assertEquals( true, isset( $enqueued_modules['bar'] ) );
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

		$this->assertEquals( 1, count( $import_map ) );
		$this->assertEquals( true, str_starts_with( $import_map['dep'], '/dep.js' ) );
		$this->assertEquals( false, isset( $import_map['no-dep'] ) );
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

		$this->assertEquals( 1, count( $import_map ) );
		$this->assertEquals( true, str_starts_with( $import_map['dep'], '/dep.js' ) );
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

		$this->assertEquals( true, str_starts_with( $import_map['static-dep'], '/static-dep.js' ) );
		$this->assertEquals( true, str_starts_with( $import_map['dynamic-dep'], '/dynamic-dep.js' ) );
		$this->assertEquals( true, str_starts_with( $import_map['nested-static-dep'], '/nested-static-dep.js' ) );
		$this->assertEquals( true, str_starts_with( $import_map['nested-dynamic-dep'], '/nested-dynamic-dep.js' ) );
		$this->assertEquals( false, isset( $import_map['no-dep'] ) );
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

		$this->assertEquals( 2, count( $preloaded_modules ) );
		$this->assertEquals( true, str_starts_with( $preloaded_modules['static-dep'], '/static-dep.js' ) );
		$this->assertEquals( true, str_starts_with( $preloaded_modules['nested-static-dep'], '/nested-static-dep.js' ) );
		$this->assertEquals( false, isset( $import_map['no-dep'] ) );
		$this->assertEquals( false, isset( $import_map['dynamic-dep'] ) );
		$this->assertEquals( false, isset( $import_map['nested-dynamic-dep'] ) );
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

		$this->assertEquals( 1, count( $preloaded_modules ) );
		$this->assertEquals( true, isset( $preloaded_modules['dep'] ) );
		$this->assertEquals( false, isset( $preloaded_modules['enqueued-dep'] ) );
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

		$this->assertEquals( 2, count( $import_map ) );
		$this->assertEquals( true, isset( $import_map['dep'] ) );
		$this->assertEquals( true, isset( $import_map['enqueued-dep'] ) );
	}
}
