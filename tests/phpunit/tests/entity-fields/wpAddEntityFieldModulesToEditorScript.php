<?php

require_once __DIR__ . '/base.php';

/**
 * Tests for _wp_add_entity_field_modules_to_editor_script().
 *
 * @package WordPress
 * @subpackage Entity Fields API
 * @since 7.2.0
 *
 * @group entity-fields
 *
 * @covers ::_wp_add_entity_field_modules_to_editor_script
 * @covers ::_wp_add_entity_modules_to_editor_script
 */
class Tests_Entity_Fields_WpAddEntityFieldModulesToEditorScript extends WP_Entity_Fields_UnitTestCase {

	/**
	 * Unregisters the fields of every entity with a script module, so a
	 * test can start from a registry without modules. The registry is reset
	 * on tear down.
	 */
	private function unregister_all_field_modules() {
		$this->on_entity_fields_api_init(
			static function () {
				foreach ( wp_get_all_registered_entity_field_modules() as $kind => $entities ) {
					foreach ( array_keys( $entities ) as $name ) {
						wp_unregister_entity_fields( $kind, $name );
					}
				}
			}
		);
	}

	/**
	 * Returns the module ids among the module dependencies of a script.
	 *
	 * @param WP_Scripts $scripts The scripts registry.
	 * @param string     $handle  The script handle.
	 * @return string[] The module ids, in order.
	 */
	private function get_module_dependency_ids( WP_Scripts $scripts, $handle ) {
		$dependencies = $scripts->get_data( $handle, 'module_dependencies' );
		return array_map(
			static function ( $dependency ) {
				return is_array( $dependency ) ? $dependency['id'] : $dependency;
			},
			is_array( $dependencies ) ? $dependencies : array()
		);
	}

	/**
	 * The modules are added on `admin_footer`, which fires before the import
	 * map is printed, both in the admin template and in the pages rendered
	 * outside it.
	 *
	 * @ticket 66229
	 */
	public function test_the_modules_are_added_on_admin_footer() {
		$this->assertSame( 10, has_action( 'admin_footer', '_wp_add_entity_field_modules_to_editor_script' ) );
		$this->assertFalse( has_action( 'admin_init', '_wp_add_entity_field_modules_to_editor_script' ) );
	}

	/**
	 * The hook suffix `admin_footer` passes is not taken for the scripts
	 * registry: the global one is used.
	 *
	 * @ticket 66229
	 */
	public function test_the_admin_footer_action_uses_the_global_scripts() {
		global $wp_scripts;
		$original_scripts = $wp_scripts;
		$wp_scripts       = new WP_Scripts();

		try {
			$this->unregister_all_field_modules();
			$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/color' );
			$wp_scripts->add( 'wp-editor', '/editor.js' );
			$wp_scripts->enqueue( 'wp-editor' );
			$wp_scripts->add_data( 'wp-editor', 'module_dependencies', array() );

			// The core entity actions add their own modules to the editor script.
			remove_action( 'admin_footer', '_wp_add_entity_action_modules_to_editor_script', 10 );

			// Other callbacks of the action may print markup.
			get_echo( 'do_action', array( 'admin_footer', 'site-editor.php' ) );

			$ids = $this->get_module_dependency_ids( $wp_scripts, 'wp-editor' );
		} finally {
			$wp_scripts = $original_scripts;
		}

		$this->assertSame( array( 'plugin/color' ), $ids );
	}

	/**
	 * A page that registers the editor script without loading it is left
	 * untouched, and the registry is not read.
	 *
	 * @ticket 66229
	 */
	public function test_nothing_happens_when_the_editor_script_is_not_enqueued() {
		$scripts = new WP_Scripts();
		$scripts->add( 'wp-editor', '/editor.js' );
		$scripts->add_data( 'wp-editor', 'module_dependencies', array() );

		$fired = did_action( 'wp_entity_fields_api_init' );
		self::reset_registry();
		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$this->assertSame( array(), $scripts->get_data( 'wp-editor', 'module_dependencies' ) );
		$this->assertSame( $fired, did_action( 'wp_entity_fields_api_init' ), 'The registry is not read.' );
	}

	/**
	 * The modules are added when the editor script is only a dependency of
	 * the scripts the page enqueues, as on the pages booted by
	 * `@wordpress/boot`.
	 *
	 * @ticket 66229
	 */
	public function test_the_modules_are_added_when_the_editor_script_is_a_dependency() {
		$this->unregister_all_field_modules();
		$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/color' );

		$scripts = new WP_Scripts();
		$scripts->add( 'wp-editor', '/editor.js' );
		$scripts->add_data( 'wp-editor', 'module_dependencies', array() );
		$scripts->add( 'boot', '/boot.js', array( 'wp-editor' ) );
		$scripts->enqueue( 'boot' );
		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$this->assertSame( array( 'plugin/color' ), $this->get_module_dependency_ids( $scripts, 'wp-editor' ) );
	}

	/**
	 * A scripts registry without the editor script is left untouched, even
	 * when field script modules are registered.
	 *
	 * @ticket 66229
	 */
	public function test_nothing_happens_without_the_editor_script() {
		$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/color' );

		$scripts = new WP_Scripts();
		// The default scripts register the real editor script.
		$scripts->remove( 'wp-editor' );
		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$this->assertFalse( $scripts->query( 'wp-editor', 'registered' ) );
		$this->assertFalse( $scripts->get_data( 'wp-editor', 'module_dependencies' ) );
	}

	/**
	 * The dependencies of the editor script stay untouched when no entity has
	 * a script module registered.
	 *
	 * @ticket 66229
	 */
	public function test_nothing_happens_without_registered_field_modules() {
		$this->unregister_all_field_modules();
		$this->assertSame( array(), wp_get_all_registered_entity_field_modules(), 'No field script module is registered.' );

		$scripts = new WP_Scripts();
		$scripts->add( 'wp-editor', '/editor.js' );
		$scripts->enqueue( 'wp-editor' );
		$scripts->add_data( 'wp-editor', 'module_dependencies', array() );
		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$this->assertSame( array(), $scripts->get_data( 'wp-editor', 'module_dependencies' ) );
	}

	/**
	 * The script module of a field becomes a dynamic dependency of the editor
	 * script.
	 *
	 * @ticket 66229
	 */
	public function test_field_script_modules_become_dynamic_dependencies_of_the_editor_script() {
		$this->unregister_all_field_modules();
		$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/color' );

		$scripts = new WP_Scripts();
		$scripts->add( 'wp-editor', '/editor.js' );
		$scripts->enqueue( 'wp-editor' );
		// Only the dependencies added by the function are of interest.
		$scripts->add_data( 'wp-editor', 'module_dependencies', array() );
		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$this->assertSame(
			array(
				array(
					'id'     => 'plugin/color',
					'import' => 'dynamic',
				),
			),
			$scripts->get_data( 'wp-editor', 'module_dependencies' )
		);
	}

	/**
	 * The dependencies already declared are kept, and the modules are added
	 * once: a second run adds nothing.
	 *
	 * @ticket 66229
	 */
	public function test_declared_dependencies_are_kept_and_modules_are_added_once() {
		$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/color' );

		$scripts = new WP_Scripts();
		$scripts->add( 'wp-editor', '/editor.js' );
		$scripts->enqueue( 'wp-editor' );
		$scripts->add_data( 'wp-editor', 'module_dependencies', array( '@wordpress/existing' ) );

		_wp_add_entity_field_modules_to_editor_script( $scripts );
		$dependencies = $scripts->get_data( 'wp-editor', 'module_dependencies' );
		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$this->assertSame( $dependencies, $scripts->get_data( 'wp-editor', 'module_dependencies' ), 'A second run adds nothing.' );
		$ids = $this->get_module_dependency_ids( $scripts, 'wp-editor' );
		$this->assertSame( '@wordpress/existing', $ids[0], 'The declared dependency is kept first.' );
		$this->assertSame( 1, count( array_keys( $ids, 'plugin/color', true ) ), 'The module is declared once.' );
		$this->assertContains(
			array(
				'id'     => 'plugin/color',
				'import' => 'dynamic',
			),
			$dependencies
		);
	}

	/**
	 * A module registered for several entities is declared once.
	 *
	 * @ticket 66229
	 */
	public function test_a_module_shared_by_several_entities_is_declared_once() {
		$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/fields' );
		$this->register_fields( 'taxonomy', 'category', array( $this->field( 'color' ) ), 'plugin/fields' );

		$scripts = new WP_Scripts();
		$scripts->add( 'wp-editor', '/editor.js' );
		$scripts->enqueue( 'wp-editor' );
		// Only the dependencies added by the function are of interest.
		$scripts->add_data( 'wp-editor', 'module_dependencies', array() );
		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$ids = $this->get_module_dependency_ids( $scripts, 'wp-editor' );
		$this->assertSame( array( 'plugin/fields' ), array_values( array_intersect( $ids, array( 'plugin/fields' ) ) ) );
	}

	/**
	 * The script modules of the fields reach the import map of a page that
	 * loads the editor script.
	 *
	 * @ticket 66229
	 */
	public function test_field_script_modules_reach_the_import_map() {
		global $wp_scripts;
		$original_scripts = $wp_scripts;
		$wp_scripts       = new WP_Scripts();

		try {
			// Only the test module is of interest: the fixture defaults
			// module is not registered as a script module.
			$this->unregister_all_field_modules();
			$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/color' );
			$wp_scripts->add( 'wp-editor', '/editor.js' );
			wp_register_script_module( 'plugin/color', '/color.js' );

			$wp_scripts->enqueue( 'wp-editor' );
			_wp_add_entity_field_modules_to_editor_script( $wp_scripts );

			$processor = new WP_HTML_Tag_Processor( get_echo( array( wp_script_modules(), 'print_import_map' ) ) );
			$this->assertTrue( $processor->next_tag( 'SCRIPT' ), 'An import map is printed.' );
			$this->assertSame( 'importmap', $processor->get_attribute( 'type' ) );
			$import_map = json_decode( $processor->get_modifiable_text(), true );
		} finally {
			$wp_scripts = $original_scripts;
			wp_deregister_script_module( 'plugin/color' );
		}

		$this->assertArrayHasKey( 'plugin/color', $import_map['imports'] );
	}
}
