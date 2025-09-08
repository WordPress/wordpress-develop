<?php
/**
 * Abilities API
 *
 * Defines WP_Ability class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 0.1.0
 */

declare( strict_types = 1 );

/**
 * Encapsulates the properties and methods related to a specific ability in the registry.
 *
 * @since 0.1.0
 *
 * @see WP_Abilities_Registry
 */
class WP_Ability {

	/**
	 * The name of the ability, with its namespace.
	 * Example: `my-plugin/my-ability`.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $name;

	/**
	 * The human-readable ability label.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $label;

	/**
	 * The detailed ability description.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $description;

	/**
	 * The optional ability input schema.
	 *
	 * @since 0.1.0
	 * @var array<string,mixed>
	 */
	protected $input_schema = array();

	/**
	 * The optional ability output schema.
	 *
	 * @since 0.1.0
	 * @var array<string,mixed>
	 */
	protected $output_schema = array();

	/**
	 * The ability execute callback.
	 *
	 * @since 0.1.0
	 * @var callable( array<string,mixed> $input): (mixed|\WP_Error)
	 */
	protected $execute_callback;

	/**
	 * The optional ability permission callback.
	 *
	 * @since 0.1.0
	 * @var ?callable( array<string,mixed> $input ): (bool|\WP_Error)
	 */
	protected $permission_callback = null;

	/**
	 * The optional ability metadata.
	 *
	 * @since 0.1.0
	 * @var array<string,mixed>
	 */
	protected $meta = array();

	/**
	 * Constructor.
	 *
	 * Do not use this constructor directly. Instead, use the `wp_register_ability()` function.
	 *
	 * @access private
	 *
	 * @since 0.1.0
	 *
	 * @see wp_register_ability()
	 *
	 * @param string              $name The name of the ability, with its namespace.
	 * @param array<string,mixed> $args An associative array of arguments for the ability. This should
	 *                                  include `label`, `description`, `input_schema`, `output_schema`,
	 *                                  `execute_callback`, `permission_callback`, and `meta`.
	 */
	public function __construct( string $name, array $args ) {
		$this->name = $name;

		$properties = $this->prepare_properties( $args );

		foreach ( $properties as $property_name => $property_value ) {
			if ( ! property_exists( $this, $property_name ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: Property name. */
						esc_html__( 'Property "%1$s" is not a valid property for ability "%2$s". Please check the %3$s class for allowed properties.' ),
						'<code>' . esc_html( $property_name ) . '</code>',
						'<code>' . esc_html( $this->name ) . '</code>',
						'<code>' . esc_html( self::class ) . '</code>'
					),
					'0.1.0'
				);
				continue;
			}

			$this->$property_name = $property_value;
		}
	}

	/**
	 * Prepares and validates the properties used to instantiate the ability.
	 *
	 * Errors are thrown as exceptions instead of \WP_Errors to allow for simpler handling and overloading. They are then
	 * caught and converted to a WP_Error when by WP_Abilities_Registry::register().
	 *
	 * @since 0.2.0
	 *
	 * @see WP_Abilities_Registry::register()
	 *
	 * @param array<string,mixed> $args An associative array of arguments used to instantiate the class.
	 * @return array<string,mixed> The validated and prepared properties.
	 * @throws \InvalidArgumentException if an argument is invalid.
	 *
	 * @phpstan-return array{
	 *   label: string,
	 *   description: string,
	 *   input_schema?: array<string,mixed>,
	 *   output_schema?: array<string,mixed>,
	 *   execute_callback: callable( array<string,mixed> $input): (mixed|\WP_Error),
	 *   permission_callback?: ?callable( array<string,mixed> $input ): (bool|\WP_Error),
	 *   meta?: array<string,mixed>,
	 *   ...<string, mixed>,
	 * } $args
	 */
	protected function prepare_properties( array $args ): array {
		if ( empty( $args['label'] ) || ! is_string( $args['label'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The ability properties must contain a `label` string.' )
			);
		}

		if ( empty( $args['description'] ) || ! is_string( $args['description'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The ability properties must contain a `description` string.' )
			);
		}

		if ( isset( $args['input_schema'] ) && ! is_array( $args['input_schema'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The ability properties should provide a valid `input_schema` definition.' )
			);
		}

		if ( isset( $args['output_schema'] ) && ! is_array( $args['output_schema'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The ability properties should provide a valid `output_schema` definition.' )
			);
		}

		if ( empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The ability properties must contain a valid `execute_callback` function.' )
			);
		}

		if ( isset( $args['permission_callback'] ) && ! is_callable( $args['permission_callback'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The ability properties should provide a valid `permission_callback` function.' )
			);
		}

		if ( isset( $args['meta'] ) && ! is_array( $args['meta'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The ability properties should provide a valid `meta` array.' )
			);
		}

		return $args;
	}

	/**
	 * Retrieves the name of the ability, with its namespace.
	 * Example: `my-plugin/my-ability`.
	 *
	 * @since 0.1.0
	 *
	 * @return string The ability name, with its namespace.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Retrieves the human-readable label for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The human-readable ability label.
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Retrieves the detailed description for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The detailed description for the ability.
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Retrieves the input schema for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string,mixed> The input schema for the ability.
	 */
	public function get_input_schema(): array {
		return $this->input_schema;
	}

	/**
	 * Retrieves the output schema for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string,mixed> The output schema for the ability.
	 */
	public function get_output_schema(): array {
		return $this->output_schema;
	}

	/**
	 * Retrieves the metadata for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string,mixed> The metadata for the ability.
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	/**
	 * Validates input data against the input schema.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $input Optional. The input data to validate.
	 * @return true|\WP_Error Returns true if valid or the WP_Error object if validation fails.
	 */
	protected function validate_input( array $input = array() ) {
		$input_schema = $this->get_input_schema();
		if ( empty( $input_schema ) ) {
			return true;
		}

		$valid_input = rest_validate_value_from_schema( $input, $input_schema, 'input' );
		if ( is_wp_error( $valid_input ) ) {
			return new \WP_Error(
				'ability_invalid_input',
				sprintf(
					/* translators: %1$s ability name, %2$s error message. */
					__( 'Ability "%1$s" has invalid input. Reason: %2$s' ),
					$this->name,
					$valid_input->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Checks whether the ability has the necessary permissions.
	 * If the permission callback is not set, the default behavior is to allow access
	 * when the input provided passes validation.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $input Optional. The input data for permission checking.
	 * @return bool|\WP_Error Whether the ability has the necessary permission.
	 */
	public function has_permission( array $input = array() ) {
		$is_valid = $this->validate_input( $input );
		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}

		if ( ! is_callable( $this->permission_callback ) ) {
			return true;
		}

		return call_user_func( $this->permission_callback, $input );
	}

	/**
	 * Executes the ability callback.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $input The input data for the ability.
	 * @return mixed|\WP_Error The result of the ability execution, or WP_Error on failure.
	 */
	protected function do_execute( array $input ) {
		if ( ! is_callable( $this->execute_callback ) ) {
			return new \WP_Error(
				'ability_invalid_execute_callback',
				/* translators: %s ability name. */
				sprintf( __( 'Ability "%s" does not have a valid execute callback.' ), $this->name )
			);
		}

		return call_user_func( $this->execute_callback, $input );
	}

	/**
	 * Validates output data against the output schema.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $output The output data to validate.
	 * @return true|\WP_Error Returns true if valid, or a WP_Error object if validation fails.
	 */
	protected function validate_output( $output ) {
		$output_schema = $this->get_output_schema();
		if ( empty( $output_schema ) ) {
			return true;
		}

		$valid_output = rest_validate_value_from_schema( $output, $output_schema, 'output' );
		if ( is_wp_error( $valid_output ) ) {
			return new \WP_Error(
				'ability_invalid_output',
				sprintf(
					/* translators: %1$s ability name, %2$s error message. */
					__( 'Ability "%1$s" has invalid output. Reason: %2$s' ),
					$this->name,
					$valid_output->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Executes the ability after input validation and running a permission check.
	 * Before returning the return value, it also validates the output.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $input Optional. The input data for the ability.
	 * @return mixed|\WP_Error The result of the ability execution, or WP_Error on failure.
	 */
	public function execute( array $input = array() ) {
		$has_permissions = $this->has_permission( $input );
		if ( true !== $has_permissions ) {
			if ( is_wp_error( $has_permissions ) ) {
				if ( 'ability_invalid_input' === $has_permissions->get_error_code() ) {
					return $has_permissions;
				}
				// Don't leak the permission check error to someone without the correct perms.
				_doing_it_wrong(
					__METHOD__,
					esc_html( $has_permissions->get_error_message() ),
					'0.1.0'
				);
			}

			return new \WP_Error(
				'ability_invalid_permissions',
				/* translators: %s ability name. */
				sprintf( __( 'Ability "%s" does not have necessary permission.' ), $this->name )
			);
		}

		$result = $this->do_execute( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$is_valid = $this->validate_output( $result );

		return is_wp_error( $is_valid ) ? $is_valid : $result;
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 0.1.0
	 */
	public function __wakeup(): void {
		throw new \LogicException( self::class . ' should never be unserialized.' );
	}
}
