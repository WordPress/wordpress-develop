<?php

require_once __DIR__ . '/base.php';

/**
 * Tests for the registration, update, and reading of entity fields.
 *
 * @package WordPress
 * @subpackage Entity Fields API
 * @since 7.2.0
 *
 * @group entity-fields
 *
 * @covers WP_Entity_Fields_Registry
 * @covers ::wp_register_entity_fields
 * @covers ::wp_update_entity_fields
 * @covers ::wp_unregister_entity_fields
 * @covers ::wp_get_registered_entity_fields
 * @covers ::wp_get_registered_entity_field_modules
 * @covers ::wp_get_all_registered_entity_field_modules
 */
class Tests_Entity_Fields_WpEntityFieldsRegistry extends WP_Entity_Fields_UnitTestCase {

	/**
	 * Reading the registry fires `wp_entity_fields_api_init` once, whichever
	 * getter is read first and however many times it is read.
	 *
	 * @ticket 66229
	 */
	public function test_reading_the_registry_fires_the_action_once() {
		self::reset_registry();
		$registry = WP_Entity_Fields_Registry::get_instance();
		$fired    = did_action( 'wp_entity_fields_api_init' );

		wp_get_registered_entity_fields( 'postType', 'page' );
		$this->assertSame( $fired + 1, did_action( 'wp_entity_fields_api_init' ), 'The first read fires the action.' );

		wp_get_registered_entity_fields( 'postType', 'post' );
		wp_get_registered_entity_field_modules( 'postType', 'page' );
		wp_get_all_registered_entity_field_modules();
		$registry->get_all_registered();
		$this->assertSame( $fired + 1, did_action( 'wp_entity_fields_api_init' ), 'Further reads do not fire it again.' );
	}

	/**
	 * A plugin hooking the action at the default priority sees the fields
	 * registered at an earlier priority: it can update a field, or remove it.
	 *
	 * @ticket 66229
	 */
	public function test_a_callback_at_the_default_priority_alters_the_defaults() {
		$this->on_entity_fields_api_init(
			static function () {
				wp_update_entity_fields(
					'test-plugin',
					'postType',
					'page',
					array(
						array(
							'id'            => 'date',
							'enableSorting' => true,
						),
					)
				);
				wp_unregister_entity_fields( 'postType', 'page', array( 'author' ) );
			}
		);

		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), null, 'id' );
		$this->assertArrayHasKey( 'date', $fields, 'The default field is kept.' );
		$this->assertTrue( $fields['date']['enableSorting'], 'The property is updated.' );
		$this->assertSame( 'datetime', $fields['date']['type'], 'The rest of the default definition is kept.' );
		$this->assertSame(
			array(
				'registeredBy' => 'core',
				'updatedBy'    => array( 'test-plugin' ),
			),
			$fields['date']['origin'],
			'Updating a field keeps who registered it and records who updated it.'
		);
		$this->assertArrayNotHasKey( 'author', $fields, 'The default field is removed.' );
	}

	/**
	 * The origin a field is registered with is stored on its definition,
	 * over any `origin` the definition sets.
	 *
	 * @ticket 66229
	 */
	public function test_fields_carry_the_origin_they_are_registered_with() {
		$this->register_fields( 'postType', 'page', array( $this->field( 'color' ) + array( 'origin' => 'spoofed' ) ) );

		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'origin', 'id' );
		$this->assertSame(
			array(
				'registeredBy' => 'test-plugin',
				'updatedBy'    => array(),
			),
			$fields['color']
		);
		$this->assertSame( 'core', $fields['author']['registeredBy'] );
	}

	/**
	 * A field can only be registered once: a field with the id of a
	 * registered field is skipped and leaves it untouched, and the rest of
	 * the fields of the call are registered.
	 *
	 * @ticket 66229
	 */
	public function test_registering_a_registered_field_is_skipped() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		$reported   = &$this->record_notices( 'WP_Entity_Fields_Registry::register' );
		$registered = $this->register_fields(
			'postType',
			'page',
			array(
				$this->field( 'color' ),
				array(
					'id'    => 'author',
					'label' => 'Writer',
				),
			),
			'plugin/fields'
		);

		$this->assertSame( array( 'color' ), $registered, 'The ids of the fields registered are returned.' );
		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), null, 'id' );
		$this->assertArrayHasKey( 'color', $fields, 'The rest of the fields of the call are registered.' );
		$this->assertSame( 'Author', $fields['author']['label'], 'The registered field is left untouched.' );
		$this->assertSame( 'core', $fields['author']['origin']['registeredBy'] );
		$this->assertSame( array( 'color' ), wp_get_registered_entity_field_modules( 'postType', 'page' )['plugin/fields'], 'The script module only applies to the fields registered.' );
		$this->assertCount( 1, $reported );
		$this->assertStringContainsString( 'postType "page"', $reported[0], 'The notice names the entity.' );
		$this->assertStringContainsString( 'already registered: author', $reported[0], 'The notice names the field that is already registered, not the others of the call.' );
	}

	/**
	 * An id that appears more than once in a call is registered once, with
	 * its first definition, and the rest of the fields of the call are
	 * registered.
	 *
	 * @ticket 66229
	 */
	public function test_registering_the_same_field_twice_keeps_the_first_definition() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		$reported   = &$this->record_notices( 'WP_Entity_Fields_Registry::register' );
		$registered = $this->register_fields(
			'postType',
			'page',
			array(
				$this->field( 'color' ),
				array(
					'id'    => 'color',
					'label' => 'Colour',
				),
				$this->field( 'size' ),
			)
		);

		$this->assertSame( array( 'color', 'size' ), $registered );
		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), null, 'id' );
		$this->assertSame( $this->field( 'color' )['label'], $fields['color']['label'], 'The first definition is kept.' );
		$this->assertArrayHasKey( 'size', $fields, 'The rest of the fields of the call are registered.' );
		$this->assertCount( 1, $reported );
		$this->assertStringContainsString( 'postType "page"', $reported[0], 'The notice names the entity.' );
		$this->assertStringContainsString( 'more than once in the same call: color', $reported[0], 'The notice names the duplicated field.' );
	}

	/**
	 * A field unregistered can be registered anew, with a new origin.
	 *
	 * @ticket 66229
	 */
	public function test_an_unregistered_field_can_be_registered_again() {
		$registered = array();
		$this->on_entity_fields_api_init(
			static function () use ( &$registered ) {
				wp_unregister_entity_fields( 'postType', 'page', array( 'author' ) );
				$registered = wp_register_entity_fields( 'test-plugin', 'postType', 'page', array( array( 'id' => 'author' ) ) );
			}
		);

		$this->assertSame( array( 'author' ), $registered );
		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), null, 'id' );
		$this->assertSame(
			array(
				'id'     => 'author',
				'origin' => array(
					'registeredBy' => 'test-plugin',
					'updatedBy'    => array(),
				),
			),
			$fields['author']
		);
	}

	/**
	 * Entities are told apart by their kind and name, not by a key joining
	 * them: `a/b` + `c` and `a` + `b/c` are two entities, whose fields and
	 * script modules do not mix.
	 *
	 * @ticket 66229
	 */
	public function test_entities_whose_kind_and_name_join_alike_are_kept_apart() {
		$result = array();
		$this->on_entity_fields_api_init(
			function () use ( &$result ) {
				$result['first']        = wp_register_entity_fields( 'test-plugin', 'a/b', 'c', array( $this->field( 'color' ) ), 'plugin/first' );
				$result['second']       = wp_register_entity_fields( 'test-plugin', 'a', 'b/c', array( $this->field( 'color' ), $this->field( 'size' ) ), 'plugin/second' );
				$result['unregistered'] = wp_unregister_entity_fields( 'a/b', 'c' );
			}
		);

		$this->assertSame( array( 'color' ), $result['first'], 'The first entity registers its field.' );
		$this->assertSame( array( 'color', 'size' ), $result['second'], 'The second entity registers a field with the same id.' );
		$this->assertSame( array( 'color' ), array_column( $result['unregistered'], 'id' ), 'Unregistering the first entity only removes its own field.' );
		$this->assertSame( array(), wp_get_registered_entity_fields( 'a/b', 'c' ) );
		$this->assertSame( array( 'color', 'size' ), array_column( wp_get_registered_entity_fields( 'a', 'b/c' ), 'id' ), 'The second entity keeps its fields.' );
		$this->assertSame( array( 'plugin/second' => array( 'color', 'size' ) ), wp_get_registered_entity_field_modules( 'a', 'b/c' ), 'The second entity keeps only its own module.' );
		$this->assertSame( array( 'b/c' => array( 'plugin/second' ) ), wp_get_all_registered_entity_field_modules()['a'], 'Only the second entity has modules under its kind.' );
		$this->assertArrayNotHasKey( 'a/b', wp_get_all_registered_entity_field_modules(), 'The kind of the first entity, emptied, is forgotten.' );
	}

	/**
	 * Unregistering returns the definitions it removes, in registration
	 * order and with their origin, whatever the order of the ids asked for;
	 * ids not registered are ignored, and nothing is returned when none is.
	 *
	 * @ticket 66229
	 */
	public function test_unregistering_returns_the_unregistered_fields() {
		$unregistered = array();
		$this->on_entity_fields_api_init(
			function () use ( &$unregistered ) {
				wp_register_entity_fields( 'test-plugin', 'test', 'entity', array( $this->field( 'a' ), $this->field( 'b' ), $this->field( 'c' ) ), 'plugin/fields' );
				$unregistered['by_id']   = wp_unregister_entity_fields( 'test', 'entity', array( 'c', 'missing', 'a' ) );
				$unregistered['missing'] = wp_unregister_entity_fields( 'test', 'entity', array( 'a' ) );
				$unregistered['every']   = wp_unregister_entity_fields( 'test', 'entity' );
				$unregistered['empty']   = wp_unregister_entity_fields( 'test', 'entity' );
			}
		);

		$origin = array(
			'registeredBy' => 'test-plugin',
			'updatedBy'    => array(),
		);
		$this->assertSame(
			array(
				array_merge( $this->field( 'a' ), array( 'origin' => $origin ) ),
				array_merge( $this->field( 'c' ), array( 'origin' => $origin ) ),
			),
			$unregistered['by_id'],
			'The fields unregistered by id are returned in registration order.'
		);
		$this->assertSame( array(), $unregistered['missing'], 'Nothing is returned when no field is registered.' );
		$this->assertSame(
			array( array_merge( $this->field( 'b' ), array( 'origin' => $origin ) ) ),
			$unregistered['every'],
			'Unregistering every field returns them all.'
		);
		$this->assertSame( array(), $unregistered['empty'], 'Nothing is returned for an entity without fields.' );
		$this->assertSame( array(), wp_get_registered_entity_fields( 'test', 'entity' ), 'The entity has no fields left.' );
		$this->assertArrayNotHasKey( 'test', wp_get_all_registered_entity_field_modules(), 'The entity has no script module left.' );
	}

	/**
	 * Updating merges each definition into the registered field, keeping
	 * its position; the origin cannot be updated, and each origin that
	 * updates the field is recorded once, in update order. The script module
	 * applies to the field on top of the modules it has.
	 *
	 * @ticket 66229
	 */
	public function test_updating_merges_into_the_registered_field() {
		$updated = array();
		$this->on_entity_fields_api_init(
			static function () use ( &$updated ) {
				$update    = array(
					array(
						'id'     => 'author',
						'label'  => 'Writer',
						'origin' => 'spoofed',
					),
				);
				$updated[] = wp_update_entity_fields( 'plugin-a', 'postType', 'page', $update, 'plugin/author' );
				$updated[] = wp_update_entity_fields(
					'plugin-b',
					'postType',
					'page',
					array(
						array(
							'id'       => 'author',
							'readOnly' => true,
						),
					)
				);
				$updated[] = wp_update_entity_fields(
					'plugin-a',
					'postType',
					'page',
					array(
						array(
							'id'    => 'author',
							'label' => 'Byline',
						),
					)
				);
			}
		);

		$this->assertSame( array( array( 'author' ), array( 'author' ), array( 'author' ) ), $updated );
		$fields = wp_get_registered_entity_fields( 'postType', 'page' );
		$this->assertSame( 'author', $fields[0]['id'], 'The field keeps its position.' );
		$this->assertSame( 'Byline', $fields[0]['label'], 'The last update wins.' );
		$this->assertTrue( $fields[0]['readOnly'], 'The updates add up.' );
		$this->assertSame( 'integer', $fields[0]['type'], 'The rest of the definition is kept.' );
		$this->assertSame(
			array(
				'registeredBy' => 'core',
				'updatedBy'    => array( 'plugin-a', 'plugin-b' ),
			),
			$fields[0]['origin']
		);
		$modules = wp_get_registered_entity_field_modules( 'postType', 'page' );
		$this->assertContains( 'author', $modules['test/defaults'], 'The field keeps its modules.' );
		$this->assertSame( array( 'author' ), $modules['plugin/author'], 'The module applies to the field.' );
	}

	/**
	 * A field whose `type` DataViews does not provide renders without a
	 * control, so it is reported, and registered anyway. A field without a
	 * `type` is not reported.
	 *
	 * @ticket 66229
	 */
	public function test_registering_a_field_with_an_unknown_type_is_reported() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		$reported   = &$this->record_notices( 'WP_Entity_Fields_Registry::register' );
		$registered = $this->register_fields(
			'postType',
			'page',
			array(
				$this->field( 'color' ),
				array(
					'id'    => 'size',
					'type'  => 'interger',
					'label' => 'Size',
				),
				array(
					'id'    => 'weight',
					'label' => 'Weight',
				),
			)
		);

		$this->assertSame( array( 'color', 'size', 'weight' ), $registered, 'The field is registered anyway.' );
		$this->assertCount( 1, $reported );
		$this->assertStringContainsString( 'postType "page"', $reported[0], 'The notice names the entity.' );
		$this->assertStringContainsString( ': size (type is not one of ', $reported[0], 'The notice names the field and the error of its type, not the others of the call.' );
	}

	/**
	 * Only registered fields can be updated: a field that is not is skipped,
	 * and the rest of the fields of the call are updated.
	 *
	 * @ticket 66229
	 */
	public function test_updating_an_unregistered_field_is_skipped() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::update' );
		$reported = &$this->record_notices( 'WP_Entity_Fields_Registry::update' );
		$updated  = array();
		$this->on_entity_fields_api_init(
			static function () use ( &$updated ) {
				$updated = wp_update_entity_fields(
					'test-plugin',
					'postType',
					'page',
					array(
						array(
							'id'    => 'author',
							'label' => 'Writer',
						),
						array(
							'id'    => 'color',
							'label' => 'Color',
						),
					),
					'plugin/fields'
				);
			}
		);

		$this->assertSame( array( 'author' ), $updated, 'The ids of the fields updated are returned.' );
		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), null, 'id' );
		$this->assertArrayNotHasKey( 'color', $fields, 'The field is not registered.' );
		$this->assertSame( 'Writer', $fields['author']['label'], 'The rest of the fields of the call are updated.' );
		$this->assertSame( array( 'test-plugin' ), $fields['author']['origin']['updatedBy'] );
		$this->assertSame( array( 'author' ), wp_get_registered_entity_field_modules( 'postType', 'page' )['plugin/fields'], 'The script module only applies to the fields updated.' );
		$this->assertCount( 1, $reported );
		$this->assertStringContainsString( 'postType "page"', $reported[0], 'The notice names the entity.' );
		$this->assertStringContainsString( 'not registered: color', $reported[0], 'The notice names only the field that is not registered.' );
	}

	/**
	 * A definition that is not an array with a non-empty string `id` is
	 * reported by its position and skipped, like a duplicated field: the
	 * rest of the fields of the call are registered, or updated.
	 *
	 * @ticket 66229
	 */
	public function test_an_invalid_definition_is_skipped() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::update' );
		$reported   = &$this->record_notices( 'WP_Entity_Fields_Registry::register' );
		$registered = null;
		$updated    = null;
		$this->on_entity_fields_api_init(
			function () use ( &$registered, &$updated ) {
				$registered = wp_register_entity_fields(
					'test-plugin',
					'postType',
					'page',
					array(
						$this->field( 'color' ),
						'size',
						array( 'label' => 'No id' ),
						array(
							'id'    => 7,
							'label' => 'Numeric id',
						),
						$this->field( 'weight' ),
					)
				);
				$updated    = wp_update_entity_fields(
					'test-plugin',
					'postType',
					'page',
					array(
						array( 'label' => 'No id' ),
						array(
							'id'    => 'color',
							'label' => 'Colour',
						),
					)
				);
			}
		);

		$this->assertSame( array( 'color', 'weight' ), $registered, 'The valid definitions are registered.' );
		$this->assertSame( array( 'color' ), $updated, 'The valid definitions are updated.' );
		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), null, 'id' );
		$this->assertSame( 'Colour', $fields['color']['label'] );
		$this->assertArrayHasKey( 'weight', $fields );
		$this->assertCount( 1, $reported );
		$this->assertStringContainsString( 'postType "page"', $reported[0], 'The notice names the entity.' );
		$this->assertStringContainsString( 'skipped: #2, #3, #4', $reported[0], 'The notice names the positions of the invalid definitions.' );
	}

	/**
	 * An origin that is not a non-empty string is refused.
	 *
	 * @ticket 66229
	 */
	public function test_registering_with_an_invalid_origin_is_refused() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		$registered = null;
		$this->on_entity_fields_api_init(
			static function () use ( &$registered ) {
				$registered = wp_register_entity_fields( '', 'postType', 'page', array( array( 'id' => 'color' ) ) );
			}
		);

		$this->assertSame( array(), $registered );
		$this->assertNotContains( 'color', array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' ) );
	}

	/**
	 * Fields that are not a list, such as fields keyed by id, are refused.
	 *
	 * @ticket 66229
	 */
	public function test_registering_fields_that_are_not_a_list_is_refused() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		$registered = null;
		$this->on_entity_fields_api_init(
			static function () use ( &$registered ) {
				$registered = wp_register_entity_fields( 'test-plugin', 'postType', 'page', array( 'color' => array( 'id' => 'color' ) ) );
			}
		);

		$this->assertSame( array(), $registered );
		$this->assertNotContains( 'color', array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' ) );
	}

	/**
	 * Unregistering with an entity kind or name that is not a non-empty
	 * string is refused with a notice rather than a fatal error, and the
	 * registry is left untouched.
	 *
	 * @ticket 66229
	 */
	public function test_unregistering_with_an_invalid_entity_is_refused() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::unregister' );
		$results = array();
		$this->on_entity_fields_api_init(
			static function () use ( &$results ) {
				$results['no name']    = wp_unregister_entity_fields( 'postType', array( 'author' ) );
				$results['empty kind'] = wp_unregister_entity_fields( '', 'page', array( 'author' ) );
			}
		);

		$this->assertSame(
			array(
				'no name'    => array(),
				'empty kind' => array(),
			),
			$results
		);
		$this->assertContains( 'author', array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' ), 'The default field is kept.' );
	}

	/**
	 * An id that is not a non-empty string is reported and skipped: the
	 * other ids of the call are unregistered.
	 *
	 * @ticket 66229
	 */
	public function test_unregistering_with_an_invalid_id_skips_it() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::unregister' );
		$unregistered = null;
		$this->on_entity_fields_api_init(
			static function () use ( &$unregistered ) {
				$unregistered = wp_unregister_entity_fields( 'postType', 'page', array( array( 'id' => 'author' ), '', 'date' ) );
			}
		);

		$this->assertSame( array( 'date' ), array_column( $unregistered, 'id' ) );
		$ids = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' );
		$this->assertContains( 'author', $ids, 'A definition in place of an id is skipped, not matched.' );
		$this->assertNotContains( 'date', $ids );
	}

	/**
	 * Registering only runs on the action: elsewhere it is refused and the
	 * registry is left untouched. Before the action a registration would
	 * keep the defaults from being registered; after it the fields have been
	 * read and the import map of the editor script built from them.
	 *
	 * @ticket 66229
	 */
	public function test_registering_outside_the_action_is_refused() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		self::reset_registry();
		$fired = did_action( 'wp_entity_fields_api_init' );

		$this->assertSame( array(), wp_register_entity_fields( 'test-plugin', 'postType', 'page', array( $this->field( 'color' ) ), 'plugin/color' ) );
		$this->assertSame( $fired, did_action( 'wp_entity_fields_api_init' ), 'Refusing does not fire the action.' );
		$this->assertNotContains( 'color', array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' ) );
		$this->assertArrayNotHasKey( 'plugin/color', wp_get_registered_entity_field_modules( 'postType', 'page' ) );
	}

	/**
	 * Updating only runs on the action, like registering: elsewhere it is
	 * refused and the registry is left untouched.
	 *
	 * @ticket 66229
	 */
	public function test_updating_outside_the_action_is_refused() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::update' );
		self::reset_registry();
		$fired = did_action( 'wp_entity_fields_api_init' );

		$this->assertSame(
			array(),
			wp_update_entity_fields(
				'test-plugin',
				'postType',
				'page',
				array(
					array(
						'id'    => 'author',
						'label' => 'Writer',
					),
				)
			)
		);
		$this->assertSame( $fired, did_action( 'wp_entity_fields_api_init' ), 'Refusing does not fire the action.' );
		$fields = array_column( wp_get_registered_entity_fields( 'postType', 'page' ), null, 'id' );
		$this->assertSame( 'Author', $fields['author']['label'] );
	}

	/**
	 * Unregistering only runs on the action: elsewhere it is refused before
	 * reading the registry, so a stray call, from an `init` callback say,
	 * neither drops a default field nor fires the action early against
	 * post types still being registered.
	 *
	 * @ticket 66229
	 */
	public function test_unregistering_outside_the_action_is_refused() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::unregister' );
		self::reset_registry();
		$fired = did_action( 'wp_entity_fields_api_init' );

		$this->assertSame( array(), wp_unregister_entity_fields( 'postType', 'page', array( 'author' ) ), 'Unregistering fields by id is refused.' );
		$this->assertSame( array(), wp_unregister_entity_fields( 'postType', 'page' ), 'Unregistering every field is refused.' );
		$this->assertSame( $fired, did_action( 'wp_entity_fields_api_init' ), 'Refusing does not fire the action.' );
		$this->assertContains( 'author', array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' ), 'The default field is kept.' );
	}

	/**
	 * A read before `init` is refused and returns nothing, without firing
	 * the action: the post types the defaults derive from are registered on
	 * `init`, and the action fires once. The next read after `init` fires it.
	 *
	 * @ticket 66229
	 */
	public function test_reading_before_init_does_not_fire_the_action() {
		global $wp_actions;

		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::initialize' );
		self::reset_registry();
		$fired = did_action( 'wp_entity_fields_api_init' );

		// did_action() counts the times a hook fired: forget `init` fired, as
		// before it runs.
		$did_init = $wp_actions['init'];
		unset( $wp_actions['init'] );
		try {
			$this->assertFalse( (bool) did_action( 'init' ) );
			$this->assertSame( array(), wp_get_registered_entity_fields( 'postType', 'page' ) );
			$this->assertSame( array(), wp_get_all_registered_entity_field_modules() );
			$this->assertSame( $fired, did_action( 'wp_entity_fields_api_init' ), 'A read before `init` does not fire the action.' );
		} finally {
			$wp_actions['init'] = $did_init;
		}

		$this->assertContains( 'author', array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' ), 'The next read fires the action and registers the defaults.' );
		$this->assertSame( $fired + 1, did_action( 'wp_entity_fields_api_init' ) );
	}

	/**
	 * A read during `init` is refused too: did_action() is true from the
	 * first `init` callback on, while the post types are still being
	 * registered. The next read after `init` fires the action.
	 *
	 * @ticket 66229
	 */
	public function test_reading_during_init_does_not_fire_the_action() {
		global $wp_current_filter;

		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::initialize' );
		self::reset_registry();
		$fired = did_action( 'wp_entity_fields_api_init' );

		// doing_action() reads the stack of hooks being run: put `init` on it,
		// as when an `init` callback runs.
		$wp_current_filter[] = 'init';
		try {
			$this->assertTrue( doing_action( 'init' ) );
			$this->assertSame( array(), wp_get_registered_entity_fields( 'postType', 'page' ) );
			$this->assertSame( array(), wp_get_all_registered_entity_field_modules() );
			$this->assertSame( $fired, did_action( 'wp_entity_fields_api_init' ), 'A read during `init` does not fire the action.' );
		} finally {
			array_pop( $wp_current_filter );
		}

		$this->assertContains( 'author', array_column( wp_get_registered_entity_fields( 'postType', 'page' ), 'id' ), 'The next read fires the action and registers the defaults.' );
		$this->assertSame( $fired + 1, did_action( 'wp_entity_fields_api_init' ) );
	}
}
