<?php

class TestFactoryFor extends WP_UnitTestCase {

	/**
	 * @var WP_UnitTest_Factory_For_Term
	 */
	private $category_factory;

	public function set_up() {
		parent::set_up();
		$this->category_factory = new WP_UnitTest_Factory_For_Term( self::factory(), 'category' );
	}

	public function test_create_creates_a_category() {
		$id = $this->category_factory->create();
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'id', $id, 'category' ) );
	}

	public function test_get_object_by_id_gets_an_object() {
		$id = $this->category_factory->create();
		$this->assertInstanceOf( WP_Term::class, $this->category_factory->get_object_by_id( $id ) );
	}

	public function test_get_object_by_id_gets_an_object_with_the_same_name() {
		$id     = $this->category_factory->create( array( 'name' => 'Boo' ) );
		$object = $this->category_factory->get_object_by_id( $id );
		$this->assertInstanceOf( WP_Term::class, $object );
		$this->assertSame( 'Boo', $object->name );
	}

	public function test_the_taxonomy_argument_overrules_the_factory_taxonomy() {
		$term_factory = new WP_UnitTest_Factory_For_Term( self::factory(), 'category' );
		$id           = $term_factory->create( array( 'taxonomy' => 'post_tag' ) );
		$term         = get_term( $id, 'post_tag' );
		$this->assertInstanceOf( WP_Term::class, $term );
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
	public function test_create_should_return_the_id_of_the_created_object() {
		$factory = new Tests_Includes_Factory_Stub( self::factory(), 123 );

		$this->assertSame( 123, $factory->create() );
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_should_throw_an_exception_when_a_wp_error_is_returned() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to create the post: Invalid date.' );

		self::factory()->post->create( array( 'post_date' => '2020-12-41 14:15:27' ) );
	}

	/**
	 * A create_object() implementation is documented to return an object ID or a WP_Error
	 * object, but nothing stops it from returning an ID that is not usable as a fixture.
	 *
	 * @ticket 66111
	 *
	 * @dataProvider data_non_positive_object_ids
	 *
	 * @param int $object_id The invalid ID returned by create_object().
	 */
	public function test_create_should_throw_an_exception_when_a_non_positive_id_is_returned( $object_id ) {
		$factory = new Tests_Includes_Factory_Stub( self::factory(), $object_id );

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to create the object' );

		$factory->create();
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{int}>
	 */
	public function data_non_positive_object_ids() {
		return array(
			'zero'          => array( 0 ),
			'a negative ID' => array( -1 ),
		);
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_should_apply_callbacks_and_update_the_object() {
		$factory                                 = new Tests_Includes_Factory_Stub( self::factory(), 42 );
		$factory->default_generation_definitions = array(
			'name' => new WP_UnitTest_Factory_Callback_After_Create(
				static function ( int $object_id ) {
					return 'Object ' . $object_id;
				}
			),
		);

		$this->assertSame( 42, $factory->create() );
		$this->assertSame( array( 'name' => 'Object 42' ), $factory->updated_fields );
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_should_throw_an_exception_when_the_update_after_callbacks_returns_a_wp_error() {
		$factory = new Tests_Includes_Factory_Stub(
			self::factory(),
			42,
			new WP_Error( 'update_failed', 'The object could not be updated.' )
		);

		$factory->default_generation_definitions = array(
			'name' => new WP_UnitTest_Factory_Callback_After_Create( '__return_empty_string' ),
		);

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to update the object after creation: The object could not be updated.' );

		$factory->create();
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_should_throw_an_exception_when_the_update_after_callbacks_returns_a_non_positive_id() {
		$factory = new Tests_Includes_Factory_Stub( self::factory(), 42, 0 );

		$factory->default_generation_definitions = array(
			'name' => new WP_UnitTest_Factory_Callback_After_Create( '__return_empty_string' ),
		);

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to update the object after creation' );

		$factory->create();
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_and_get_should_return_the_created_object() {
		$object  = (object) array( 'ID' => 7 );
		$factory = new Tests_Includes_Factory_Stub( self::factory(), 7, 7, $object );

		$this->assertSame( $object, $factory->create_and_get() );
	}

	/**
	 * The WP_Error case is checked separately from the class check because a WP_Error is
	 * itself an object: an `instanceof` test alone would reject it with the less useful
	 * message, throwing away the error it carries.
	 *
	 * @ticket 66111
	 */
	public function test_create_and_get_should_throw_an_exception_when_a_wp_error_is_returned() {
		$factory = new Tests_Includes_Factory_Stub(
			self::factory(),
			1,
			1,
			new WP_Error( 'not_found', 'The object could not be found.' )
		);

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to retrieve the object with ID 1: The object could not be found.' );

		$factory->create_and_get();
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_and_get_should_throw_an_exception_when_the_object_cannot_be_retrieved() {
		$factory = new Tests_Includes_Factory_Stub( self::factory(), 1, 1, null );

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to retrieve the object with ID 1. Args: []' );

		$factory->create_and_get();
	}

	/**
	 * @ticket 66111
	 */
	public function test_get_object_by_id_should_return_the_object_type_of_the_factory() {
		$factory = self::factory();

		$this->assertInstanceOf( WP_Post::class, $factory->post->get_object_by_id( $factory->post->create() ) );
		$this->assertInstanceOf( WP_Comment::class, $factory->comment->get_object_by_id( $factory->comment->create() ) );
		$this->assertInstanceOf( WP_Term::class, $factory->term->get_object_by_id( $factory->term->create() ) );
		$this->assertInstanceOf( WP_User::class, $factory->user->get_object_by_id( $factory->user->create() ) );
		$this->assertInstanceOf( stdClass::class, $factory->bookmark->get_object_by_id( $factory->bookmark->create() ) );
	}

	/**
	 * @ticket 66111
	 */
	public function test_post_factory_get_object_by_id_should_throw_an_exception_for_an_unknown_id() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to retrieve the object with ID 987654321.' );

		self::factory()->post->get_object_by_id( 987654321 );
	}

	/**
	 * @ticket 66111
	 */
	public function test_comment_factory_get_object_by_id_should_throw_an_exception_for_an_unknown_id() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to retrieve the object with ID 987654321.' );

		self::factory()->comment->get_object_by_id( 987654321 );
	}

	/**
	 * @ticket 66111
	 */
	public function test_bookmark_factory_get_object_by_id_should_throw_an_exception_for_an_unknown_id() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to retrieve the object with ID 987654321.' );

		self::factory()->bookmark->get_object_by_id( 987654321 );
	}

	/**
	 * get_term() reports an unregistered taxonomy as a WP_Error, which is the only route
	 * a core factory has to the WP_Error branch of the retrieval guard.
	 *
	 * @ticket 66111
	 */
	public function test_term_factory_get_object_by_id_should_throw_an_exception_for_an_invalid_taxonomy() {
		$term_id = $this->category_factory->create();
		$factory = new WP_UnitTest_Factory_For_Term( self::factory(), 'wptests_unregistered_tax' );

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Invalid taxonomy' );

		$factory->get_object_by_id( $term_id );
	}

	/**
	 * The user factory is the exception: WP_User is constructed unconditionally, so an
	 * unknown ID yields an object that simply does not exist rather than a failure.
	 *
	 * @ticket 66111
	 */
	public function test_user_factory_get_object_by_id_should_not_throw_for_an_unknown_id() {
		$user = self::factory()->user->get_object_by_id( 987654321 );

		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertFalse( $user->exists() );
	}

	/**
	 * The term factory looks the term up in the taxonomy given in the args, which is not
	 * necessarily the one the factory was constructed with.
	 *
	 * @ticket 66111
	 */
	public function test_term_factory_create_and_get_should_honor_the_taxonomy_argument() {
		$term = $this->category_factory->create_and_get( array( 'taxonomy' => 'post_tag' ) );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'post_tag', $term->taxonomy );
	}

	/**
	 * create() passes update_object() an ID and only the after-create callback results, so the
	 * taxonomy has to be read from the term rather than assumed to be the factory's default.
	 *
	 * @ticket 66111
	 *
	 * @dataProvider data_term_taxonomy_sources
	 *
	 * @param non-falsy-string $taxonomy       The taxonomy the term is created in.
	 * @param bool             $in_definitions Whether the taxonomy is given in the generation definitions
	 *                                         rather than in the args.
	 */
	public function test_term_factory_should_apply_an_after_create_callback( string $taxonomy, bool $in_definitions ) {
		$args        = array( 'name' => 'Callback term' );
		$definitions = array(
			'description' => new WP_UnitTest_Factory_Callback_After_Create(
				static function ( int $created_id ) {
					return 'Description for ' . $created_id;
				}
			),
		);

		if ( $in_definitions ) {
			$definitions['taxonomy'] = $taxonomy;
		} else {
			$args['taxonomy'] = $taxonomy;
		}

		$term_id = self::factory()->term->create( $args, $definitions );
		$term    = get_term( $term_id, $taxonomy );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'Description for ' . $term_id, $term->description );
	}

	/**
	 * Data provider.
	 *
	 * The term factory's own taxonomy is post_tag, so category is the case the factory cannot
	 * know about without looking at the term.
	 *
	 * @return array<non-falsy-string, array{ non-falsy-string, bool }>
	 */
	public function data_term_taxonomy_sources(): array {
		return array(
			'factory taxonomy, in the args' => array( 'post_tag', false ),
			'another taxonomy, in the args' => array( 'category', false ),
			'another taxonomy, in the generation definitions' => array( 'category', true ),
		);
	}

	/**
	 * The taxonomy can come from the generation definitions rather than the args, so the term
	 * has to be looked up by ID rather than in the taxonomy the factory was constructed with.
	 *
	 * @ticket 66111
	 */
	public function test_term_factory_create_and_get_should_honor_a_generated_taxonomy() {
		register_taxonomy( 'wptests_tax', 'post' );

		$term = $this->category_factory->create_and_get(
			array(),
			array(
				'name'     => 'Generated taxonomy term',
				'taxonomy' => 'wptests_tax',
			)
		);

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'wptests_tax', $term->taxonomy );
	}

	/**
	 * wp_update_comment() reports the number of affected rows, which is zero when the callback
	 * writes back a value the row already holds. That is a successful no-op, not a failure.
	 *
	 * @ticket 66111
	 */
	public function test_comment_factory_should_apply_an_after_create_callback_that_changes_nothing() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::factory()->post->create(),
				'comment_author'  => 'Unchanged author',
			),
			array(
				'comment_content' => new WP_UnitTest_Factory_Callback_After_Create(
					static function ( int $created_id ) {
						$comment = get_comment( $created_id );

						return $comment instanceof WP_Comment ? $comment->comment_content : null;
					}
				),
			)
		);

		$this->assertGreaterThan( 0, $comment_id );
	}

	/**
	 * @ticket 66111
	 *
	 * @group ms-required
	 */
	public function test_blog_factory_update_object_should_throw_because_it_is_not_implemented() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Updating a site is not implemented' );

		self::factory()->blog->update_object( 1, array( 'domain' => 'example.org' ) );
	}

	/**
	 * @ticket 66111
	 *
	 * @group ms-required
	 */
	public function test_network_factory_update_object_should_throw_because_it_is_not_implemented() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Updating a network is not implemented' );

		self::factory()->network->update_object( 1, array( 'domain' => 'example.org' ) );
	}

	/**
	 * A factory defined outside core may still report a failure the way the contract allowed
	 * before 7.2.0, by returning a WP_Error. create() has to turn that into an exception, or
	 * the WP_Error would be handed back as though it were an object ID.
	 *
	 * @ticket 66111
	 */
	public function test_create_should_throw_an_exception_when_a_legacy_factory_returns_a_wp_error() {
		$factory = new Tests_Includes_Factory_Legacy_Stub(
			self::factory(),
			new WP_Error( 'insert_failed', 'The object could not be inserted.' )
		);

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to create the object: The object could not be inserted.' );

		$factory->create();
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_should_throw_an_exception_when_a_legacy_factory_update_returns_a_wp_error() {
		$factory = new Tests_Includes_Factory_Legacy_Stub(
			self::factory(),
			5,
			new WP_Error( 'update_failed', 'The object could not be updated.' )
		);

		$factory->default_generation_definitions = array(
			'name' => new WP_UnitTest_Factory_Callback_After_Create( '__return_empty_string' ),
		);

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to update the object after creation: The object could not be updated.' );

		$factory->create();
	}

	/**
	 * A factory defined outside core may still report that an object could not be retrieved
	 * the way the contract allowed before 7.2.0, by returning null, false or a WP_Error.
	 * create_and_get() has to turn that into an exception rather than hand it back.
	 *
	 * @ticket 66111
	 *
	 * @dataProvider data_legacy_retrieval_failures
	 *
	 * @param WP_Error|null|false $retrieved The value the legacy get_object_by_id() returns.
	 * @param string              $message   The expected exception message.
	 */
	public function test_create_and_get_should_throw_an_exception_when_a_legacy_factory_cannot_retrieve( $retrieved, string $message ) {
		$factory = new Tests_Includes_Factory_Legacy_Stub( self::factory(), 5, 1, $retrieved );

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( $message );

		$factory->create_and_get();
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{WP_Error|null|false, string}>
	 */
	public function data_legacy_retrieval_failures(): array {
		return array(
			'null'     => array( null, 'Unable to retrieve the object with ID 5. Args: []' ),
			'false'    => array( false, 'Unable to retrieve the object with ID 5. Args: []' ),
			'WP_Error' => array( new WP_Error( 'not_found', 'The object could not be found.' ), 'Unable to retrieve the object with ID 5: The object could not be found.' ),
		);
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_and_get_should_return_the_object_from_a_legacy_factory() {
		$object  = (object) array( 'ID' => 5 );
		$factory = new Tests_Includes_Factory_Legacy_Stub( self::factory(), 5, 1, $object );

		$this->assertSame( $object, $factory->create_and_get() );
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_many_should_return_an_array_of_ids() {
		$ids = $this->category_factory->create_many( 2 );

		$this->assertCount( 2, $ids );
		$this->assertContainsOnly( 'int', $ids );
		$this->assertGreaterThan( 0, min( $ids ) );
	}

	/**
	 * @ticket 66111
	 */
	public function test_create_many_should_throw_an_exception_when_an_object_cannot_be_created() {
		$factory = new Tests_Includes_Factory_Stub(
			self::factory(),
			new WP_Error( 'insert_failed', 'The object could not be inserted.' )
		);

		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Unable to create the object: The object could not be inserted.' );

		$factory->create_many( 2 );
	}

	/**
	 * @ticket 66111
	 */
	public function test_generate_args_should_throw_an_exception_for_an_invalid_default_value() {
		$this->expectException( WP_UnitTest_Factory_Exception::class );
		$this->expectExceptionMessage( 'Factory default value for the "name" field should be either a scalar or a generator object.' );

		$this->category_factory->create( array(), array( 'name' => array( 'not' => 'a generator' ) ) );
	}

	/**
	 * @ticket 66111
	 */
	public function test_generate_args_should_let_the_given_args_overrule_the_defaults() {
		$args = $this->category_factory->generate_args(
			array( 'name' => 'Boo' ),
			array(
				'name'        => 'Default name',
				'description' => 'Default description',
			),
			$callbacks
		);

		$this->assertSame(
			array(
				'name'        => 'Boo',
				'description' => 'Default description',
			),
			$args
		);
		$this->assertSame( array(), $callbacks );
	}

	/**
	 * @ticket 66111
	 */
	public function test_generate_args_should_expand_a_generator_object() {
		$args = $this->category_factory->generate_args(
			array(),
			array( 'name' => new WP_UnitTest_Generator_Sequence( 'Term %s' ) ),
			$callbacks
		);

		$this->assertIsString( $args['name'] );
		$this->assertMatchesRegularExpression( '/^Term \d{7}$/', $args['name'] );
		$this->assertSame( array(), $callbacks );
	}

	/**
	 * @ticket 66111
	 */
	public function test_generate_args_should_defer_a_callback_generator() {
		$callback = new WP_UnitTest_Factory_Callback_After_Create( '__return_empty_string' );

		$args = $this->category_factory->generate_args(
			array(),
			array( 'name' => $callback ),
			$callbacks
		);

		$this->assertSame( array(), $args );
		$this->assertSame( array( 'name' => $callback ), $callbacks );
	}
}

/**
 * Factory with stubbed out persistence, used to drive WP_UnitTest_Factory_For_Thing
 * down paths that a real factory only reaches when the database misbehaves.
 */
class Tests_Includes_Factory_Stub extends WP_UnitTest_Factory_For_Thing {

	/**
	 * Fields passed to the most recent update_object() call.
	 *
	 * @var array<string, mixed>|null
	 */
	public $updated_fields;

	/**
	 * @var int|WP_Error
	 */
	private $create_object_result;

	/**
	 * @var int|WP_Error
	 */
	private $update_object_result;

	/**
	 * @var mixed
	 */
	private $get_object_by_id_result;

	/**
	 * @param object       $factory                 Global factory that can be used to create
	 *                                              other objects on the system.
	 * @param int|WP_Error $create_object_result    Optional. The value create_object() returns.
	 *                                              Default 1.
	 * @param int|WP_Error $update_object_result    Optional. The value update_object() returns.
	 *                                              Default 1.
	 * @param mixed        $get_object_by_id_result Optional. The value get_object_by_id() returns.
	 *                                              Default null.
	 */
	public function __construct( $factory, $create_object_result = 1, $update_object_result = 1, $get_object_by_id_result = null ) {
		parent::__construct( $factory );

		$this->create_object_result    = $create_object_result;
		$this->update_object_result    = $update_object_result;
		$this->get_object_by_id_result = $get_object_by_id_result;
	}

	/**
	 * @param array<string, mixed> $args The arguments.
	 * @return positive-int The configured result.
	 * @throws WP_UnitTest_Factory_Exception When the configured result is not a positive integer.
	 */
	public function create_object( $args ) {
		$object_id = $this->create_object_result;

		$this->assert_valid_object_id( $object_id, 'Unable to create the object' );

		return $object_id;
	}

	/**
	 * @param int                  $object_id The object ID.
	 * @param array<string, mixed> $fields    The values to update.
	 * @return positive-int The configured result.
	 * @throws WP_UnitTest_Factory_Exception When the configured result is not a positive integer.
	 */
	public function update_object( $object_id, $fields ) {
		$this->updated_fields = $fields;

		$updated_id = $this->update_object_result;

		$this->assert_valid_object_id( $updated_id, 'Unable to update the object after creation' );

		return $updated_id;
	}

	/**
	 * Validates through the shared helper, the way a real factory does.
	 *
	 * @param int $object_id The object ID.
	 * @return stdClass The configured result.
	 * @throws WP_UnitTest_Factory_Exception When the configured result is not a stdClass.
	 */
	public function get_object_by_id( $object_id ): stdClass {
		$this->assert_valid_object( $this->get_object_by_id_result, $object_id, stdClass::class );

		return $this->get_object_by_id_result;
	}
}

/**
 * Factory written to the contract from before 7.2.0: create_object() and update_object()
 * report a failure by returning a WP_Error rather than by throwing, as factories defined
 * outside core may still do.
 */
class Tests_Includes_Factory_Legacy_Stub extends WP_UnitTest_Factory_For_Thing {

	/**
	 * @var int|WP_Error
	 */
	private $create_object_result;

	/**
	 * @var int|WP_Error
	 */
	private $update_object_result;

	/**
	 * @var object|WP_Error|null|false
	 */
	private $get_object_by_id_result;

	/**
	 * @param object                     $factory                 Global factory that can be used to create other objects on the system.
	 * @param int|WP_Error               $create_object_result    The value create_object() returns.
	 * @param int|WP_Error               $update_object_result    Optional. The value update_object() returns. Default 1.
	 * @param object|WP_Error|null|false $get_object_by_id_result Optional. The value get_object_by_id() returns. Default null.
	 */
	public function __construct( $factory, $create_object_result, $update_object_result = 1, $get_object_by_id_result = null ) {
		parent::__construct( $factory );

		$this->create_object_result    = $create_object_result;
		$this->update_object_result    = $update_object_result;
		$this->get_object_by_id_result = $get_object_by_id_result;
	}

	/**
	 * @param array<string, mixed> $args The arguments.
	 * @return int|WP_Error The configured result.
	 */
	public function create_object( $args ) {
		return $this->create_object_result;
	}

	/**
	 * @param int                  $object_id The object ID.
	 * @param array<string, mixed> $fields    The values to update.
	 * @return int|WP_Error The configured result.
	 */
	public function update_object( $object_id, $fields ) {
		return $this->update_object_result;
	}

	/**
	 * @param int $object_id The object ID.
	 * @return object|WP_Error|null|false The configured result.
	 */
	public function get_object_by_id( $object_id ) {
		return $this->get_object_by_id_result;
	}
}
