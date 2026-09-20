<?php

/**
 * An abstract class that serves as a basis for all WordPress object-type factory classes.
 */
abstract class WP_UnitTest_Factory_For_Thing {

	public $default_generation_definitions;
	public $factory;

	/**
	 * Creates a new factory, which will create objects of a specific Thing.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param object $factory                       Global factory that can be used to create other objects
	 *                                              on the system.
	 * @param array $default_generation_definitions Defines what default values should the properties
	 *                                              of the object have. The default values can be generators --
	 *                                              an object with the next() method.
	 *                                              There are some default generators:
	 *                                               - {@link WP_UnitTest_Generator_Sequence}
	 *                                               - {@link WP_UnitTest_Generator_Locale_Name}
	 *                                               - {@link WP_UnitTest_Factory_Callback_After_Create}
	 */
	public function __construct( $factory, $default_generation_definitions = array() ) {
		$this->factory                        = $factory;
		$this->default_generation_definitions = $default_generation_definitions;
	}

	/**
	 * Creates an object and returns its ID.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param array $args The arguments.
	 * @return int|WP_Error The object ID on success, WP_Error object on failure.
	 */
	abstract public function create_object( $args );

	/**
	 * Updates an existing object.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int   $object_id The object ID.
	 * @param array $fields    The values to update.
	 * @return int|WP_Error The object ID on success, WP_Error object on failure.
	 */
	abstract public function update_object( $object_id, $fields );

	/**
	 * Creates an object and returns its ID.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array $args                   Optional. The arguments for the object to create.
	 *                                      Default empty array.
	 * @param null  $generation_definitions Optional. The default values for the object.
	 *                                      Default null.
	 *
	 * @return int The object ID.
	 * @throws WP_UnitTest_Factory_Exception When the object could not be created.
	 */
	public function create( $args = array(), $generation_definitions = null ) {
		$generation_definitions ??= $this->default_generation_definitions;

		$generated_args = $this->generate_args( $args, $generation_definitions, $callbacks );
		$object_id      = $this->create_object( $generated_args );

		$object_id = $this->get_object_id( $object_id, 'Unable to create the object' );

		if ( $callbacks ) {
			$updated_fields = $this->apply_callbacks( $callbacks, $object_id );
			$save_result    = $this->update_object( $object_id, $updated_fields );

			$this->get_object_id( $save_result, 'Unable to update the object after creation' );
		}

		return $object_id;
	}

	/**
	 * Creates and returns an object.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array $args                   Optional. The arguments for the object to create.
	 *                                      Default empty array.
	 * @param null  $generation_definitions Optional. The default values for the object.
	 *                                      Default null.
	 *
	 * @return mixed The created object. Can be anything.
	 * @throws WP_UnitTest_Factory_Exception When the object could not be created or retrieved.
	 */
	public function create_and_get( $args = array(), $generation_definitions = null ) {
		$object_id = $this->create( $args, $generation_definitions );
		$object    = $this->get_object_by_id( $object_id );

		if ( is_wp_error( $object ) ) {
			throw new WP_UnitTest_Factory_Exception(
				sprintf( 'Unable to retrieve the object with ID %d: %s', $object_id, $object->get_error_message() )
			);
		}

		return $object;
	}

	/**
	 * Retrieves an object by ID.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int $object_id The object ID.
	 * @return mixed The object. Can be anything.
	 */
	abstract public function get_object_by_id( $object_id );

	/**
	 * Creates multiple objects.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int   $count                  Amount of objects to create.
	 * @param array $args                   Optional. The arguments for the object to create.
	 *                                      Default empty array.
	 * @param null  $generation_definitions Optional. The default values for the object.
	 *                                      Default null.
	 *
	 * @return int[] An array of object IDs.
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
	 * @param array       $args                   Optional. The arguments to combine with defaults.
	 *                                            Default empty array.
	 * @param array|null  $generation_definitions Optional. The defaults. Default null.
	 * @param array|null  $callbacks              Optional. Array with callbacks to apply on the fields.
	 *                                            Default null.
	 *
	 * @return array The combined array.
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
	 * @param WP_UnitTest_Factory_Callback_After_Create[] $callbacks Array with callback functions.
	 * @param int                                         $object_id ID of the object to apply callbacks for.
	 * @return array The altered fields.
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
	 * Validates the result of a create or update operation and returns the object ID.
	 *
	 * A WP_Error or a falsy result means the object could not be created or updated,
	 * which is a fixture failure the test cannot recover from, so an exception is thrown
	 * instead of returning the value to the caller.
	 *
	 * @since 7.2.0
	 *
	 * @param int|WP_Error|false $object_id The value returned by create_object() or update_object().
	 * @param string             $message   The message to use when the value is falsy.
	 * @return int The object ID.
	 * @throws WP_UnitTest_Factory_Exception When the value is a WP_Error object or falsy.
	 */
	protected function get_object_id( $object_id, string $message ): int {
		if ( is_wp_error( $object_id ) ) {
			throw new WP_UnitTest_Factory_Exception(
				sprintf( '%s: %s', $message, $object_id->get_error_message() )
			);
		}

		if ( ! $object_id ) {
			throw new WP_UnitTest_Factory_Exception( $message );
		}

		return $object_id;
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
