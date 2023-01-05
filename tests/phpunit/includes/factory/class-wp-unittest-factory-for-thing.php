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
	 *
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
	 *
	 * @return int|WP_Error The object ID on success, WP_Error object on failure.
	 */
	abstract public function update_object( $object_id, $fields );

	/**
	 * Creates an object and returns its ID.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param array $args                   Optional. The arguments for the object to create.
	 *                                      Default empty array.
	 * @param null  $generation_definitions Optional. The default values for the object.
	 *                                      Default null.
	 *
	 * @return int|WP_Error The object ID on success, WP_Error object on failure.
	 */
	public function create( $args = array(), $generation_definitions = null ) {
		if ( is_null( $generation_definitions ) ) {
			$generation_definitions = $this->default_generation_definitions;
		}

		$generated_args = $this->generate_args( $args, $generation_definitions, $callbacks );
		$object_id      = $this->create_object( $generated_args );

		if ( ! $object_id || is_wp_error( $object_id ) ) {
			return $object_id;
		}

		if ( $callbacks ) {
			$updated_fields = $this->apply_callbacks( $callbacks, $object_id );
			$save_result    = $this->update_object( $object_id, $updated_fields );

			if ( ! $save_result || is_wp_error( $save_result ) ) {
				return $save_result;
			}
		}

		return $object_id;
	}

	/**
	 * Creates and returns an object.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param array $args                   Optional. The arguments for the object to create.
	 *                                      Default empty array.
	 * @param null  $generation_definitions Optional. The default values for the object.
	 *                                      Default null.
	 *
	 * @return mixed The created object. Can be anything. WP_Error object on failure.
	 */
	public function create_and_get( $args = array(), $generation_definitions = null ) {
		$object_id = $this->create( $args, $generation_definitions );

		if ( is_wp_error( $object_id ) ) {
			return $object_id;
		}

		return $this->get_object_by_id( $object_id );
	}

	/**
	 * Retrieves an object by ID.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int $object_id The object ID.
	 *
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
	 * @return array
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
	 *
	 * @param array       $args                   Optional. The arguments to combine with defaults.
	 *                                            Default empty array.
	 * @param array|null  $generation_definitions Optional. The defaults. Default null.
	 * @param array|null  $callbacks              Optional. Array with callbacks to apply on the fields.
	 *                                            Default null.
	 *
	 * @return array|WP_Error Combined array on success. WP_Error when default value is incorrent.
	 */
	public function generate_args( $args = array(), $generation_definitions = null, &$callbacks = null ) {
		$callbacks = array();
		if ( is_null( $generation_definitions ) ) {
			$generation_definitions = $this->default_generation_definitions;
		}

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
					return new WP_Error(
						'invalid_argument',
						'Factory default value should be either a scalar or an generator object.'
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
	 *
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
	 *
	 * @return WP_UnitTest_Factory_Callback_After_Create
	 */
	public function callback( $callback ) {
		return new WP_UnitTest_Factory_Callback_After_Create( $callback );
	}

	/**
	 * Adds slashes to the given value.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param array|object|string|mixed $value The value to add slashes to.
	 *
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
