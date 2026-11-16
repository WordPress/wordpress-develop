<?php

/**
 * An abstract class that serves as a basis for all WordPress object-type factory classes.
 */
abstract class WP_UnitTest_Factory_For_Thing {

	/**
	 * Defines what default values the properties of a created object have.
	 *
	 * @var array<string, mixed>
	 */
	public $default_generation_definitions;

	/**
	 * Global factory that can be used to create other objects on the system.
	 *
	 * Held for the benefit of factories that need to create related fixtures. No core
	 * factory reads it, which is why every subclass defaults it to null: a factory
	 * constructed that way is fully functional.
	 *
	 * @var object|null
	 */
	public $factory;

	/**
	 * Creates a new factory, which will create objects of a specific Thing.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param object|null          $factory                        Global factory that can be used to create other objects on the system, or null when there is none.
	 * @param array<string, mixed> $default_generation_definitions Defines what default values should the properties of the object have. The default values can be generators -- an object with the next() method. There are some default generators:
	 *                                                              - {@link WP_UnitTest_Generator_Sequence}
	 *                                                              - {@link WP_UnitTest_Generator_Locale_Name}
	 *                                                              - {@link WP_UnitTest_Factory_Callback_After_Create}
	 */
	public function __construct( $factory, $default_generation_definitions = array() ) {
		$this->factory                        = $factory;
		$this->default_generation_definitions = $default_generation_definitions;
	}

	/**
	 * Creates an object and returns its ID.
	 *
	 * An implementation may still report a failure by returning a WP_Error object, as factories
	 * outside core written before 7.2.0 do, or may throw, as the core factories do. create()
	 * turns a WP_Error, or any value that is not a positive integer, into an exception either way.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 May throw an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed> $args The arguments.
	 * @return int|WP_Error The object ID on success, or a WP_Error object on failure.
	 * @throws WP_UnitTest_Factory_Exception When the object could not be created, if the implementation throws.
	 */
	abstract public function create_object( $args );

	/**
	 * Updates an existing object.
	 *
	 * As with create_object(), an implementation may report a failure by returning a WP_Error
	 * object or by throwing, and create() turns either into an exception.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 May throw an exception instead of returning a WP_Error object on failure.
	 *
	 * @param int                  $object_id The object ID.
	 * @param array<string, mixed> $fields    The values to update.
	 * @return int|WP_Error The object ID on success, or a WP_Error object on failure.
	 * @throws WP_UnitTest_Factory_Exception When the object could not be updated, if the implementation throws.
	 */
	abstract public function update_object( $object_id, $fields );

	/**
	 * Creates an object and returns its ID.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed>      $args                   Optional. The arguments for the object to create. Default empty array.
	 * @param array<string, mixed>|null $generation_definitions Optional. The default values for the object. Default null.
	 *
	 * @return positive-int The object ID.
	 * @throws WP_UnitTest_Factory_Exception When the object could not be created.
	 */
	public function create( $args = array(), $generation_definitions = null ) {
		$generation_definitions ??= $this->default_generation_definitions;

		$generated_args = $this->generate_args( $args, $generation_definitions, $callbacks );
		$object_id      = $this->create_object( $generated_args );

		// The core factories throw for themselves, but a factory defined elsewhere may still
		// return a WP_Error, as the contract allowed before 7.2.0.
		$this->assert_valid_object_id( $object_id, 'Unable to create the object' );

		if ( $callbacks ) {
			$updated_fields = $this->apply_callbacks( $callbacks, $object_id );

			$this->assert_valid_object_id(
				$this->update_object( $object_id, $updated_fields ),
				'Unable to update the object after creation'
			);
		}

		return $object_id;
	}

	/**
	 * Creates and returns an object.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed>      $args                   Optional. The arguments for the object to create. Default empty array.
	 * @param array<string, mixed>|null $generation_definitions Optional. The default values for the object. Default null.
	 *
	 * @return object The created object. Can be anything.
	 * @throws WP_UnitTest_Factory_Exception When the object could not be created or retrieved.
	 */
	public function create_and_get( $args = array(), $generation_definitions = null ) {
		$object_id = $this->create( $args, $generation_definitions );
		$object    = $this->get_object_by_id( $object_id );

		// The core factories throw for themselves, but a factory defined elsewhere may still
		// return a WP_Error, null or false, as the contract allowed before 7.2.0.
		$this->assert_valid_object( $object, $object_id, null, $args );

		return $object;
	}

	/**
	 * Retrieves an object by ID.
	 *
	 * An implementation may still report that the object could not be retrieved by returning a
	 * WP_Error object, null or false, as factories outside core written before 7.2.0 do, or may
	 * throw, as the core factories do. create_and_get() turns any of those into an exception.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 May throw an exception instead of returning a WP_Error object, null or false when the object cannot be retrieved.
	 *
	 * @param int $object_id The object ID.
	 * @return object|WP_Error|null|false The object, or a WP_Error object, null or false on failure. Implementations narrow this to their own object type.
	 * @throws WP_UnitTest_Factory_Exception When the object could not be retrieved, if the implementation throws.
	 */
	abstract public function get_object_by_id( $object_id );

	/**
	 * Creates multiple objects.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of including a WP_Error object in the result.
	 *
	 * @param int                       $count                  Amount of objects to create.
	 * @param array<string, mixed>      $args                   Optional. The arguments for the object to create. Default empty array.
	 * @param array<string, mixed>|null $generation_definitions Optional. The default values for the object. Default null.
	 *
	 * @return positive-int[] An array of object IDs.
	 * @throws WP_UnitTest_Factory_Exception When one of the objects could not be created.
	 */
	public function create_many( $count, $args = array(), $generation_definitions = null ) {
		$results = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$results[] = $this->create( $args, $generation_definitions );
		}

		return $results;
	}

	/**
	 * Combines the given arguments with the generation_definitions (defaults) and applies
	 * possibly set callbacks on it.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed>      $args                   Optional. The arguments to combine with defaults. Default empty array.
	 * @param array<string, mixed>|null $generation_definitions Optional. The defaults. Default null.
	 * @param array|null                $callbacks              Optional. Array with callbacks to apply on the fields. Default null.
	 *
	 * @return array<string, mixed> The combined array.
	 * @throws WP_UnitTest_Factory_Exception When a default value is neither a scalar nor a generator object.
	 */
	public function generate_args( $args = array(), $generation_definitions = null, &$callbacks = null ) {
		$callbacks                = array();
		$generation_definitions ??= $this->default_generation_definitions;

		// Use the same incrementor for all fields belonging to this object.
		$gen = new WP_UnitTest_Generator_Sequence();
		// Add leading zeros to make sure MySQL sorting works as expected.
		$incr = zeroise( $gen->get_incr(), 7 );

		foreach ( array_keys( $generation_definitions ) as $field_name ) {
			if ( ! isset( $args[ $field_name ] ) ) {
				$generator = $generation_definitions[ $field_name ];
				if ( is_scalar( $generator ) ) {
					$args[ $field_name ] = $generator;
				} elseif ( is_object( $generator ) && method_exists( $generator, 'call' ) ) {
					$callbacks[ $field_name ] = $generator;
				} elseif ( is_object( $generator ) ) {
					$args[ $field_name ] = sprintf( $generator->get_template_string(), $incr );
				} else {
					throw new WP_UnitTest_Factory_Exception(
						sprintf(
							'Factory default value for the "%s" field should be either a scalar or a generator object.',
							$field_name
						)
					);
				}
			}
		}

		return $args;
	}


	/**
	 * Applies the callbacks on the created object.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param array<string, WP_UnitTest_Factory_Callback_After_Create> $callbacks Array with callback functions, keyed by field name.
	 * @param int                                                      $object_id ID of the object to apply callbacks for.
	 * @return array<string, mixed> The altered fields.
	 */
	public function apply_callbacks( $callbacks, $object_id ) {
		$updated_fields = array();

		foreach ( $callbacks as $field_name => $generator ) {
			$updated_fields[ $field_name ] = $generator->call( $object_id );
		}

		return $updated_fields;
	}

	/**
	 * Instantiates a callback object for the given function name.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param callable $callback The callback function.
	 * @return WP_UnitTest_Factory_Callback_After_Create
	 */
	public function callback( $callback ) {
		return new WP_UnitTest_Factory_Callback_After_Create( $callback );
	}

	/**
	 * Asserts that the result of a create or update operation is a valid object ID.
	 *
	 * A WP_Error or a falsy result means the object could not be created or updated,
	 * which is a fixture failure the test cannot recover from, so an exception is thrown
	 * rather than letting the invalid value pass to the caller.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed  $object_id The value returned by create_object() or update_object().
	 * @param string $message   The message to use when the value is not a valid ID.
	 * @return void
	 * @throws WP_UnitTest_Factory_Exception When the value is a WP_Error object or not a positive integer.
	 * @phpstan-assert int<1, max> $object_id
	 */
	protected function assert_valid_object_id( $object_id, string $message ): void {
		if ( is_wp_error( $object_id ) ) {
			throw new WP_UnitTest_Factory_Exception(
				sprintf( '%s: %s', $message, $object_id->get_error_message() )
			);
		}

		if ( ! is_int( $object_id ) || $object_id <= 0 ) {
			throw new WP_UnitTest_Factory_Exception( $message );
		}
	}

	/**
	 * Asserts that the result of a retrieval operation is an object, of the expected class if one is given.
	 *
	 * The WP_Error case is checked first, and separately. The class check below would reject
	 * a WP_Error too, but only with the generic message, discarding the reason the retrieval
	 * failed at the point where it is most useful.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed                $retrieved      The value returned by the retrieval function.
	 * @param int                  $object_id      The ID the object was retrieved by.
	 * @param string|null          $expected_class The class the object is expected to be an instance of, or null to accept any object.
	 * @param array<string, mixed> $args           Optional. The arguments the object was created with, reported in the message. Default empty array.
	 * @return void
	 * @throws WP_UnitTest_Factory_Exception When the value is a WP_Error object, is not an object, or is not of the expected class.
	 *
	 * @template T of object
	 * @phpstan-param class-string<T>|null $expected_class
	 * @phpstan-assert T $retrieved
	 */
	protected function assert_valid_object( $retrieved, int $object_id, ?string $expected_class, $args = array() ): void {
		if ( is_wp_error( $retrieved ) ) {
			throw new WP_UnitTest_Factory_Exception(
				sprintf( 'Unable to retrieve the object with ID %d: %s. Args: %s', $object_id, $retrieved->get_error_message(), wp_json_encode( $args ) )
			);
		}

		$is_valid = null === $expected_class ? is_object( $retrieved ) : $retrieved instanceof $expected_class;

		if ( ! $is_valid ) {
			throw new WP_UnitTest_Factory_Exception(
				sprintf( 'Unable to retrieve the object with ID %d. Args: %s', $object_id, wp_json_encode( $args ) )
			);
		}
	}

	/**
	 * Adds slashes to the given value.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param array|object|string|mixed $value The value to add slashes to.
	 * @return array|string The value with the possibly applied slashes.
	 */
	public function addslashes_deep( $value ) {
		if ( is_array( $value ) ) {
			$value = array_map( array( $this, 'addslashes_deep' ), $value );
		} elseif ( is_object( $value ) ) {
			$vars = get_object_vars( $value );
			foreach ( $vars as $key => $data ) {
				$value->{$key} = $this->addslashes_deep( $data );
			}
		} elseif ( is_string( $value ) ) {
			$value = addslashes( $value );
		}

		return $value;
	}
}
