<?php

require_once __DIR__ . '/base.php';

/**
 * Tests for the field collections of the Entity Fields API.
 *
 * @package WordPress
 * @subpackage Entity Fields API
 * @since 7.2.0
 *
 * @group entity-fields
 *
 * @covers ::wp_register_entity_field_collection
 * @covers ::wp_get_entity_field_collection_fields
 * @covers WP_Entity_Fields_Registry::register_collection
 * @covers WP_Entity_Fields_Registry::get_collection
 * @covers WP_Entity_Fields_Registry::get_collection_fields
 */
class Tests_Entity_Fields_WpRegisterEntityFieldCollection extends WP_Entity_Fields_UnitTestCase {

	/**
	 * Registers the fields of fixture collections at priority 0, for the
	 * duration of the test.
	 *
	 * @param string   $fixture     The folder of the collections, in
	 *                              data/entity-fields/collections.
	 * @param string[] $collections The collections to register, in order.
	 * @return bool[] What wp_register_entity_field_collection() returned for
	 *                each collection, keyed by collection.
	 */
	private function register_fixture_collections( $fixture, $collections ) {
		$directory = DIR_TESTDATA . '/entity-fields/collections/' . $fixture;
		$results   = array();
		$this->on_entity_fields_api_init(
			static function () use ( $directory, $collections, &$results ) {
				foreach ( $collections as $collection ) {
					$results[ $collection ] = wp_register_entity_field_collection( $directory . '/' . $collection );
				}
			},
			0
		);
		return $results;
	}

	/**
	 * A collection lists a folder per field: the id of a field is the name
	 * of its folder unless its `field.php` sets one, and the fields are in
	 * the alphabetical order of their folders, keyed by id.
	 *
	 * @ticket 66229
	 */
	public function test_a_collection_has_a_field_per_folder() {
		$fields = wp_get_entity_field_collection_fields( DIR_TESTDATA . '/entity-fields/fixture' );

		$this->assertSame( array( 'zeta', 'beta' ), array_keys( $fields ), 'The folder name is the id, unless the field sets one; the folders set the order.' );
		$this->assertSame( array( 'zeta', 'beta' ), array_column( $fields, 'id' ), 'Each field has its id.' );
		$this->assertSame( 'id', array_keys( $fields['beta'] )[0], 'The id comes first.' );
		$this->assertSame( 'Zeta', $fields['zeta']['label'] );
	}

	/**
	 * A missing collection has no fields.
	 *
	 * @ticket 66229
	 */
	public function test_a_missing_collection_has_no_fields() {
		$this->assertSame( array(), wp_get_entity_field_collection_fields( DIR_TESTDATA . '/entity-fields/missing' ) );
	}

	/**
	 * A collection registers its fields on its entity, after the fields
	 * registered on it before, with its origin and its module. Like the
	 * registry, it does not check that the entity exists or is exposed in
	 * the REST API.
	 *
	 * @ticket 66229
	 */
	public function test_a_collection_places_its_fields() {
		$this->register_post_types(
			array(
				'test_book'     => array( 'title' ),
				'test_magazine' => array( 'title' ),
				'test_hidden'   => array( 'title' ),
			),
			array( 'test_hidden' )
		);
		$this->register_fields( 'postType', 'test_book', array( $this->field( 'isbn' ) ) );
		$results = $this->register_fixture_collections( 'valid', array( 'book', 'magazine', 'hidden' ) );

		$this->assertSame(
			array(
				'book'     => true,
				'magazine' => true,
				'hidden'   => true,
			),
			$results
		);

		$book = wp_get_registered_entity_fields( 'postType', 'test_book' );
		$this->assertSame( array( 'subtitle', 'isbn' ), array_column( $book, 'id' ), 'The fields registered first come first: the fixture collections run at priority 0.' );
		$this->assertSame( 'fixture', $book[0]['origin']['registeredBy'], 'The fields carry the origin of their collection.' );
		$this->assertSame( array( 'fixture/book' => array( 'subtitle' ) ), wp_get_registered_entity_field_modules( 'postType', 'test_book' ), 'Each field is registered with the module of its collection.' );

		$this->assertSame( array( 'issue' ), array_column( wp_get_registered_entity_fields( 'postType', 'test_magazine' ), 'id' ) );
		$this->assertSame( array(), wp_get_registered_entity_field_modules( 'postType', 'test_magazine' ), 'A collection without module registers none.' );
		$this->assertSame( array( 'secret' ), array_column( wp_get_registered_entity_fields( 'postType', 'test_hidden' ), 'id' ), 'A post type not exposed in the REST API gets its fields too.' );
	}

	/**
	 * The files of a collection run in a scope of their own: an `index.php`
	 * or a `field.php` using the variables of the loader as locals, e.g.
	 * `$fields` to build its elements, changes nothing the loader read.
	 *
	 * @ticket 66229
	 */
	public function test_the_files_of_a_collection_have_their_own_scope() {
		$this->assertSame( array( 'scoped' => true ), $this->register_fixture_collections( 'valid', array( 'scoped' ) ) );

		$fields = wp_get_registered_entity_fields( 'postType', 'test_scoped' );
		$this->assertSame( array( 'plain', 'scoped' ), array_column( $fields, 'id' ), 'The index reusing `$directory` still reads its folder; the field reusing `$fields` and `$file` keeps the field read before it and the name of its folder.' );
		$this->assertSame( array( 'draft', 'publish' ), array_column( $fields[1]['elements'], 'value' ) );
	}

	/**
	 * There is no precedence between collections: a field of a collection
	 * that redefines a field registered before it is skipped by the registry,
	 * like a plugin registering it twice.
	 *
	 * @ticket 66229
	 */
	public function test_a_collection_redefining_a_registered_field_is_skipped() {
		$this->register_post_types( array( 'test_book' => array( 'title' ) ) );

		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register' );
		$directory = DIR_TESTDATA . '/entity-fields/collections/duplicate/book';
		$result    = null;
		$this->on_entity_fields_api_init(
			static function () use ( $directory, &$result ) {
				wp_register_entity_fields(
					'defaults',
					'postType',
					'test_book',
					array(
						array(
							'id'    => 'authorship',
							'type'  => 'text',
							'label' => 'Authorship',
						),
					)
				);
				$result = wp_register_entity_field_collection( $directory );
			},
			0
		);
		$fields = wp_get_registered_entity_fields( 'postType', 'test_book' );

		$this->assertFalse( $result, 'A collection that loses a field returns false.' );
		$this->assertSame( array( 'authorship' ), array_column( $fields, 'id' ) );
		$this->assertSame( 'Authorship', $fields[0]['label'], 'The field registered first is kept.' );
	}

	/**
	 * An invalid collection is reported and skipped; the valid ones are
	 * registered.
	 *
	 * @ticket 66229
	 */
	public function test_an_invalid_collection_is_skipped() {
		$this->register_post_types( array( 'test_book' => array( 'title' ) ) );

		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register_collection' );
		$reported = &$this->record_notices( 'WP_Entity_Fields_Registry::register_collection' );
		$results  = $this->register_fixture_collections( 'invalid', array( 'book', 'no_name', 'null_name', 'no_origin', 'missing' ) );
		$ids      = array_column( wp_get_registered_entity_fields( 'postType', 'test_book' ), 'id' );

		$this->assertSame( array( 'subtitle' ), $ids );
		$this->assertSame(
			array(
				'book'      => true,
				'no_name'   => false,
				'null_name' => false,
				'no_origin' => false,
				'missing'   => false,
			),
			$results,
			'An invalid configuration returns false.'
		);
		$this->assertCount(
			4,
			$reported,
			'Each invalid collection is reported: no name, a null name, no origin, and a missing `index.php`.'
		);
	}

	/**
	 * A `field.php` that does not return an array with a non-empty string
	 * id, or whose id an earlier folder took, is reported and skipped
	 * before its id is used as a key: an array id does not cause a fatal
	 * error, and a later folder does not replace an earlier one.
	 *
	 * @ticket 66229
	 */
	public function test_invalid_collection_fields_are_skipped() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::get_collection_fields' );
		$reported = &$this->record_notices( 'WP_Entity_Fields_Registry::get_collection_fields' );
		$fields   = wp_get_entity_field_collection_fields( DIR_TESTDATA . '/entity-fields/collections/invalid_fields/book' );

		$this->assertSame( array( 'valid' ), array_keys( $fields ), 'Only the valid field is read.' );
		$this->assertSame( 'Valid', $fields['valid']['label'], 'A later folder with the same id does not replace the earlier one.' );
		$this->assertCount( 2, $reported, 'The invalid fields and the duplicated ones are reported once each.' );
		$this->assertStringContainsString( 'array_id, empty_id, int_id, not_array, null_id', $reported[0], 'The invalid fields are named by folder.' );
		$this->assertStringContainsString( 'valid_again', $reported[1], 'The duplicated fields are named by folder.' );
	}

	/**
	 * A collection with invalid fields registers its valid ones, and returns
	 * false since not all of its fields are registered.
	 *
	 * @ticket 66229
	 */
	public function test_a_collection_with_invalid_fields_registers_the_valid_ones() {
		$this->register_post_types( array( 'test_book' => array( 'title' ) ) );

		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register_collection' );
		$reported = &$this->record_notices( 'WP_Entity_Fields_Registry::register_collection' );
		$results  = $this->register_fixture_collections( 'invalid_fields', array( 'book' ) );
		$fields   = wp_get_registered_entity_fields( 'postType', 'test_book' );

		$this->assertSame( array( 'book' => false ), $results, 'A collection that skips a field returns false.' );
		$this->assertSame( array( 'valid' ), array_column( $fields, 'id' ) );
		$this->assertSame( 'Valid', $fields[0]['label'] );
		$this->assertCount( 2, $reported, 'The notices name the method that registers the collection.' );
	}

	/**
	 * A collection is registered on the action, like fields are: elsewhere
	 * it is refused and the registry is left untouched.
	 *
	 * @ticket 66229
	 */
	public function test_registering_a_collection_outside_the_action_is_refused() {
		$this->setExpectedIncorrectUsage( 'WP_Entity_Fields_Registry::register_collection' );
		self::reset_registry();
		$fired = did_action( 'wp_entity_fields_api_init' );

		$this->assertFalse( wp_register_entity_field_collection( DIR_TESTDATA . '/entity-fields/collections/valid/book' ) );
		$this->assertSame( $fired, did_action( 'wp_entity_fields_api_init' ), 'Refusing does not fire the action.' );
		$this->assertSame( array(), wp_get_registered_entity_fields( 'postType', 'test_book' ) );
	}
}
