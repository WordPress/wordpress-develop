<?php
/**
 * Content Type API: WP_Content_Type class
 *
 * @package WordPress
 * @subpackage Post
 * @since 7.0.0
 */

/**
 * Core class used for interacting with content types.
 *
 * A content type is a higher-level abstraction that combines a post type
 * with its associated meta field definitions in a single, declarative API.
 *
 * @since 7.0.0
 *
 * @see register_content_type()
 */
#[AllowDynamicProperties]
final class WP_Content_Type {

	/**
	 * Content type key (same as post type key).
	 *
	 * @since 7.0.0
	 * @var string
	 */
	public $name;

	/**
	 * The registered WP_Post_Type object.
	 *
	 * @since 7.0.0
	 * @var WP_Post_Type|null
	 */
	public $post_type_object;

	/**
	 * Array of field definitions.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	public $fields = array();

	/**
	 * UI hints for editor/admin integrations.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	public $ui = array();

	/**
	 * Original arguments passed to register_content_type().
	 *
	 * @since 7.0.0
	 * @var array
	 */
	public $original_args = array();

	/**
	 * Supported field types for validation.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private static $supported_field_types = array(
		'string',
		'integer',
		'number',
		'boolean',
		'array',
		'object',
	);

	/**
	 * Supported control types for UI hints.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private static $supported_control_types = array(
		'text',
		'textarea',
		'number',
		'checkbox',
		'select',
		'radio',
		'date',
		'datetime',
		'email',
		'url',
		'color',
		'range',
	);

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $content_type Content type key.
	 * @param array  $args         Arguments for registering the content type.
	 */
	public function __construct( $content_type, $args = array() ) {
		$this->name          = $content_type;
		$this->original_args = $args;

		$this->set_props( $args );
	}

	/**
	 * Sets content type properties.
	 *
	 * @since 7.0.0
	 *
	 * @param array $args Array of arguments for registering a content type.
	 */
	public function set_props( $args ) {
		// Extract fields and UI from args.
		$this->fields = isset( $args['fields'] ) ? $args['fields'] : array();
		$this->ui     = isset( $args['ui'] ) ? $args['ui'] : array();

		// Normalize field definitions.
		$this->fields = $this->normalize_fields( $this->fields );
	}

	/**
	 * Normalizes field definitions to ensure consistent structure.
	 *
	 * @since 7.0.0
	 *
	 * @param array $fields Array of field definitions.
	 * @return array Normalized field definitions.
	 */
	private function normalize_fields( $fields ) {
		$normalized = array();

		foreach ( $fields as $key => $field ) {
			// Handle shorthand field reference (string instead of array).
			if ( is_string( $field ) ) {
				$key   = $field;
				$field = array();
			}

			$type = isset( $field['type'] ) ? $field['type'] : 'string';
			$enum = isset( $field['enum'] ) ? $field['enum'] : array();

			// Determine default value: use first enum value if enum is set and no default provided.
			$default = $this->get_default_for_type( $type );
			if ( ! empty( $enum ) && ! isset( $field['default'] ) ) {
				$default = $enum[0];
			}

			$normalized[ $key ] = wp_parse_args(
				$field,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'label'             => $this->generate_label( $key ),
					'description'       => '',
					'required'          => false,
					'default'           => $default,
					'sanitize_callback' => null,
					'auth_callback'     => null,
					'control'           => $this->get_default_control( $type ),
					'enum'              => array(),
					'revisions_enabled' => false,
				)
			);
		}

		return $normalized;
	}

	/**
	 * Gets the default value for a field type.
	 *
	 * @since 7.0.0
	 *
	 * @param string $type Field type.
	 * @return mixed Default value appropriate for the type.
	 */
	private function get_default_for_type( $type ) {
		$defaults = array(
			'string'  => '',
			'integer' => 0,
			'number'  => 0,
			'boolean' => false,
			'array'   => array(),
			'object'  => array(),
		);

		return isset( $defaults[ $type ] ) ? $defaults[ $type ] : '';
	}

	/**
	 * Generates a human-readable label from a field key.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key Field key.
	 * @return string Generated label.
	 */
	private function generate_label( $key ) {
		return ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
	}

	/**
	 * Gets the default control type for a field type.
	 *
	 * @since 7.0.0
	 *
	 * @param string $type Field type.
	 * @return string Default control type.
	 */
	private function get_default_control( $type ) {
		$control_map = array(
			'string'  => 'text',
			'integer' => 'number',
			'number'  => 'number',
			'boolean' => 'checkbox',
			'array'   => 'textarea',
			'object'  => 'textarea',
		);

		return isset( $control_map[ $type ] ) ? $control_map[ $type ] : 'text';
	}

	/**
	 * Validates field definitions.
	 *
	 * @since 7.0.0
	 *
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_fields() {
		foreach ( $this->fields as $key => $field ) {
			// Validate field key.
			if ( empty( $key ) || ! is_string( $key ) ) {
				return new WP_Error(
					'invalid_field_key',
					__( 'Field keys must be non-empty strings.' )
				);
			}

			// Validate field type.
			if ( ! in_array( $field['type'], self::$supported_field_types, true ) ) {
				return new WP_Error(
					'invalid_field_type',
					sprintf(
						/* translators: 1: Field key, 2: Invalid type, 3: Supported types. */
						__( 'Invalid field type "%2$s" for field "%1$s". Supported types: %3$s.' ),
						$key,
						$field['type'],
						implode( ', ', self::$supported_field_types )
					)
				);
			}

			// Validate control type if specified.
			if ( ! empty( $field['control'] ) && ! in_array( $field['control'], self::$supported_control_types, true ) ) {
				/**
				 * Filters the list of supported control types for content type fields.
				 *
				 * @since 7.0.0
				 *
				 * @param array  $supported_control_types List of supported control types.
				 * @param string $content_type            Content type key.
				 */
				$custom_controls = apply_filters( 'content_type_supported_controls', self::$supported_control_types, $this->name );

				if ( ! in_array( $field['control'], $custom_controls, true ) ) {
					return new WP_Error(
						'invalid_control_type',
						sprintf(
							/* translators: 1: Field key, 2: Invalid control type. */
							__( 'Invalid control type "%2$s" for field "%1$s".' ),
							$key,
							$field['control']
						)
					);
				}
			}

			// Validate enum values if type supports it.
			if ( ! empty( $field['enum'] ) && ! is_array( $field['enum'] ) ) {
				return new WP_Error(
					'invalid_enum_values',
					sprintf(
						/* translators: %s: Field key. */
						__( 'Enum values for field "%s" must be an array.' ),
						$key
					)
				);
			}
		}

		return true;
	}

	/**
	 * Registers the post type.
	 *
	 * @since 7.0.0
	 *
	 * @param array $post_type_args Arguments for register_post_type().
	 * @return WP_Post_Type|WP_Error The registered post type object, or WP_Error on failure.
	 */
	public function register_post_type( $post_type_args ) {
		$this->post_type_object = register_post_type( $this->name, $post_type_args );

		return $this->post_type_object;
	}

	/**
	 * Registers all meta fields for this content type.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True on success.
	 */
	public function register_meta_fields() {
		foreach ( $this->fields as $key => $field ) {
			$meta_args = $this->build_meta_args( $field );
			register_post_meta( $this->name, $key, $meta_args );
		}

		return true;
	}

	/**
	 * Builds arguments for register_post_meta() from a field definition.
	 *
	 * @since 7.0.0
	 *
	 * @param array $field Field definition.
	 * @return array Arguments for register_post_meta().
	 */
	private function build_meta_args( $field ) {
		$meta_args = array(
			'type'              => $field['type'],
			'single'            => $field['single'],
			'show_in_rest'      => $this->build_rest_schema( $field ),
			'description'       => $field['description'],
			'default'           => $field['default'],
			'revisions_enabled' => $field['revisions_enabled'],
		);

		// Add label for REST API documentation.
		if ( ! empty( $field['label'] ) ) {
			$meta_args['label'] = $field['label'];
		}

		// Add sanitize callback if provided, or generate one based on type and constraints.
		if ( ! empty( $field['sanitize_callback'] ) ) {
			$meta_args['sanitize_callback'] = $field['sanitize_callback'];
		} else {
			$meta_args['sanitize_callback'] = $this->get_sanitize_callback( $field );
		}

		// Add auth callback if provided.
		if ( ! empty( $field['auth_callback'] ) ) {
			$meta_args['auth_callback'] = $field['auth_callback'];
		}

		return $meta_args;
	}

	/**
	 * Builds REST API schema from a field definition.
	 *
	 * @since 7.0.0
	 *
	 * @param array $field Field definition.
	 * @return bool|array REST schema or boolean.
	 */
	private function build_rest_schema( $field ) {
		if ( empty( $field['show_in_rest'] ) ) {
			return false;
		}

		// If show_in_rest is already an array (custom schema), use it.
		if ( is_array( $field['show_in_rest'] ) ) {
			return $field['show_in_rest'];
		}

		// Build schema from field definition.
		$schema = array(
			'type' => $field['type'],
		);

		// Add description.
		if ( ! empty( $field['description'] ) ) {
			$schema['description'] = $field['description'];
		}

		// Add default value only if explicitly set and different from type default.
		if ( array_key_exists( 'default', $field ) ) {
			$type_default = $this->get_default_for_type( $field['type'] );
			if ( $field['default'] !== $type_default ) {
				$schema['default'] = $field['default'];
			}
		}

		// Add enum constraint.
		if ( ! empty( $field['enum'] ) ) {
			$schema['enum'] = $field['enum'];
		}

		// Handle required fields.
		if ( ! empty( $field['required'] ) ) {
			$schema['required'] = true;
		}

		// Handle array type - must specify items schema for REST API.
		if ( 'array' === $field['type'] ) {
			$schema['items'] = isset( $field['items'] ) ? $field['items'] : array( 'type' => 'string' );
		}

		// Handle object type - add additionalProperties for flexibility.
		if ( 'object' === $field['type'] ) {
			$schema['additionalProperties'] = true;
		}

		// Wrap in REST API expected format.
		return array(
			'schema' => $schema,
		);
	}

	/**
	 * Gets a sanitize callback based on field type and constraints.
	 *
	 * @since 7.0.0
	 *
	 * @param array $field Field definition.
	 * @return callable|null Sanitize callback or null.
	 */
	private function get_sanitize_callback( $field ) {
		$type = $field['type'];

		// If enum is set, validate against allowed values.
		if ( ! empty( $field['enum'] ) ) {
			$enum_values = $field['enum'];
			return function ( $value ) use ( $enum_values, $type ) {
				// First sanitize by type.
				$value = WP_Content_Type::sanitize_by_type( $value, $type );

				// Then validate against enum.
				if ( in_array( $value, $enum_values, true ) ) {
					return $value;
				}

				return isset( $enum_values[0] ) ? $enum_values[0] : '';
			};
		}

		// Default sanitization by type.
		return function ( $value ) use ( $type ) {
			return WP_Content_Type::sanitize_by_type( $value, $type );
		};
	}

	/**
	 * Sanitizes a value based on its type.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed  $value Value to sanitize.
	 * @param string $type  Field type.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize_by_type( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return sanitize_text_field( $value );

			case 'integer':
				return (int) $value;

			case 'number':
				return (float) $value;

			case 'boolean':
				return (bool) $value;

			case 'array':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return array();

			case 'object':
				if ( is_array( $value ) || is_object( $value ) ) {
					return (array) $value;
				}
				return array();

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Gets the field definition for a specific field.
	 *
	 * @since 7.0.0
	 *
	 * @param string $field_key Field key.
	 * @return array|null Field definition or null if not found.
	 */
	public function get_field( $field_key ) {
		return isset( $this->fields[ $field_key ] ) ? $this->fields[ $field_key ] : null;
	}

	/**
	 * Gets all field definitions.
	 *
	 * @since 7.0.0
	 *
	 * @return array Array of field definitions.
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Gets required fields.
	 *
	 * @since 7.0.0
	 *
	 * @return array Array of required field keys.
	 */
	public function get_required_fields() {
		$required = array();

		foreach ( $this->fields as $key => $field ) {
			if ( ! empty( $field['required'] ) ) {
				$required[] = $key;
			}
		}

		return $required;
	}

	/**
	 * Gets UI hints.
	 *
	 * @since 7.0.0
	 *
	 * @return array UI hints array.
	 */
	public function get_ui() {
		return $this->ui;
	}

	/**
	 * Gets the REST API schema for all fields.
	 *
	 * @since 7.0.0
	 *
	 * @return array REST API schema.
	 */
	public function get_rest_schema() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(),
		);

		foreach ( $this->fields as $key => $field ) {
			if ( empty( $field['show_in_rest'] ) ) {
				continue;
			}

			$schema['properties'][ $key ] = array(
				'type'        => $field['type'],
				'description' => $field['description'],
			);

			if ( ! empty( $field['enum'] ) ) {
				$schema['properties'][ $key ]['enum'] = $field['enum'];
			}

			if ( ! empty( $field['required'] ) ) {
				if ( ! isset( $schema['required'] ) ) {
					$schema['required'] = array();
				}
				$schema['required'][] = $key;
			}
		}

		return $schema;
	}

	/**
	 * Validates field values against the content type schema.
	 *
	 * @since 7.0.0
	 *
	 * @param array $values Array of field key => value pairs.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_values( $values ) {
		$errors = new WP_Error();

		// Check required fields.
		foreach ( $this->get_required_fields() as $required_key ) {
			if ( ! isset( $values[ $required_key ] ) || '' === $values[ $required_key ] ) {
				$field = $this->get_field( $required_key );
				$errors->add(
					'missing_required_field',
					sprintf(
						/* translators: %s: Field label. */
						__( 'The field "%s" is required.' ),
						$field['label']
					),
					array( 'field' => $required_key )
				);
			}
		}

		// Validate each provided value.
		foreach ( $values as $key => $value ) {
			$field = $this->get_field( $key );

			if ( ! $field ) {
				continue; // Skip unknown fields.
			}

			// Validate type.
			if ( ! $this->validate_type( $value, $field['type'] ) ) {
				$errors->add(
					'invalid_field_type',
					sprintf(
						/* translators: 1: Field label, 2: Expected type. */
						__( 'The field "%1$s" must be of type %2$s.' ),
						$field['label'],
						$field['type']
					),
					array( 'field' => $key )
				);
			}

			// Validate enum.
			if ( ! empty( $field['enum'] ) && ! in_array( $value, $field['enum'], true ) ) {
				$errors->add(
					'invalid_enum_value',
					sprintf(
						/* translators: 1: Field label, 2: Allowed values. */
						__( 'The field "%1$s" must be one of: %2$s.' ),
						$field['label'],
						implode( ', ', $field['enum'] )
					),
					array( 'field' => $key )
				);
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}

	/**
	 * Validates a value against an expected type.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $type  Expected type.
	 * @return bool True if valid.
	 */
	private function validate_type( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return is_string( $value );

			case 'integer':
				return is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) );

			case 'number':
				return is_numeric( $value );

			case 'boolean':
				return is_bool( $value ) || in_array( $value, array( 0, 1, '0', '1', 'true', 'false' ), true );

			case 'array':
				return is_array( $value );

			case 'object':
				return is_array( $value ) || is_object( $value );

			default:
				return true;
		}
	}

	/**
	 * Converts the content type to an array.
	 *
	 * @since 7.0.0
	 *
	 * @return array Content type data as array.
	 */
	public function to_array() {
		return array(
			'name'   => $this->name,
			'fields' => $this->fields,
			'ui'     => $this->ui,
		);
	}
}
