<?php

class TestFactoryFor extends WP_UnitTestCase {

	/**
	 * @var WP_UnitTest_Factory_For_Term
	 */
	private $category_factory;

	public function set_up() {
		parent::set_up();
		$this->category_factory = new WP_UnitTest_Factory_For_Term( null, 'category' );
	}

	public function test_create_creates_a_category() {
		$id = $this->category_factory->create();
		$this->assertInstanceOf( 'WP_Term', get_term_by( 'id', $id, 'category' ) );
	}

	public function test_get_object_by_id_gets_an_object() {
		$id = $this->category_factory->create();
		$this->assertInstanceOf( 'WP_Term', $this->category_factory->get_object_by_id( $id ) );
	}

	public function test_get_object_by_id_gets_an_object_with_the_same_name() {
		$id     = $this->category_factory->create( array( 'name' => 'Boo' ) );
		$object = $this->category_factory->get_object_by_id( $id );
		$this->assertSame( 'Boo', $object->name );
	}

	public function test_the_taxonomy_argument_overrules_the_factory_taxonomy() {
		$term_factory = new WP_UnitTest_Factory_For_term( null, 'category' );
		$id           = $term_factory->create( array( 'taxonomy' => 'post_tag' ) );
		$term         = get_term( $id, 'post_tag' );
		$this->assertSame( $id, $term->term_id );
	}

	/**
	 * @ticket 32536
	 */
	public function test_term_factory_create_and_get_should_return_term_object() {
		register_taxonomy( 'wptests_tax', 'post' );
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'wptests_tax' ) );
		$this->assertIsObject( $term );
		$this->assertNotEmpty( $term->term_id );
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_should_throw_an_exception_when_a_wp_error_is_returned() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );

		self::factory()->post->create( array( 'post_date' => '2020-12-41 14:15:27' ) );
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_should_throw_an_exception_when_a_falsy_value_is_returned() {
		$factory = new class( null ) extends WP_UnitTest_Factory_For_Thing {
			public function create_object( $args ) {
				return false;
			}

			public function update_object( $object_id, $fields ) {
				return $object_id;
			}

			public function get_object_by_id( $object_id ) {
				return null;
			}
		};

		$this->expectException( WP_UnitTest_Factory_Exception::class );

		$factory->create();
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_and_get_should_throw_an_exception_when_the_object_cannot_be_retrieved() {
		$factory = new class( null ) extends WP_UnitTest_Factory_For_Thing {
			public function create_object( $args ) {
				return 1;
			}

			public function update_object( $object_id, $fields ) {
				return $object_id;
			}

			public function get_object_by_id( $object_id ) {
				return new WP_Error( 'not_found', 'The object could not be found.' );
			}
		};

		$this->expectException( WP_UnitTest_Factory_Exception::class );

		$factory->create_and_get();
	}

	/**
	 * @ticket 66111
	 */
	public function test_generate_args_should_throw_an_exception_for_an_invalid_default_value() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );

		$this->category_factory->create( array(), array( 'name' => array( 'not' => 'a generator' ) ) );
	}
}
