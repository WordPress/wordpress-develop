<?php
/**
 * Database type API
 *
 * @package WordPress
 * @subpackage Database type
 * @since n.e.x.t
 */

/**
 * Get supported types for metadata and option tables.
 *
 * @since n.e.x.t
 *
 * @return array List of supported database types.
 */
function wp_get_database_types() {
	return array(
		WP_TYPE_BOOLEAN,
		WP_TYPE_INTEGER,
		WP_TYPE_FLOAT,
		WP_TYPE_STRING,
		WP_TYPE_ARRAY,
		WP_TYPE_OBJECT,
		WP_TYPE_UNKNOWN,
	);
}

/**
 * Get the default data type to use for metadata or option value.
 *
 * @since n.e.x.t
 *
 * @return string The default database type.
 */
function wp_get_database_default_type() {

	/**
	 * Filter the default type use when saving value in metadata or option tables.
	 *
	 * @since n.e.x.t
	 *
	 * @param string the default type.
	 */
	return apply_filters( 'database_default_type', WP_DEFAULT_TYPE );
}

/**
 * Get the type of value.
 *
 * This function is not interchangeable with the PHP gettype function. It only supports a subset of existing PHP type
 * and the return value doesn't always match with PHP type.
 *
 * @since n.e.x.t
 *
 * @param mixed $value
 *
 * @return string
 */
function wp_get_database_type_for_value( $value ) {
	$original_value_type = gettype( $value );
	switch ( $original_value_type ) {
		case 'boolean':
			$value_type = WP_TYPE_BOOLEAN;
			break;
		case 'integer':
			$value_type = WP_TYPE_INTEGER;
			break;
		case 'string':
			$value_type = WP_TYPE_STRING;
			break;
		case 'array':
			$value_type = WP_TYPE_ARRAY;
			break;
		case 'object':
			$value_type = WP_TYPE_OBJECT;
			break;
		case 'double':
			$value_type = WP_TYPE_FLOAT;
			break;
		case 'resource':
		case 'resource (closed)':
		case 'NULL':
		case 'unknown type':
		default:
			$value_type = WP_TYPE_UNKNOWN;
	}

	/**
	 * Filter value's type.
	 *
	 * The value type should be part of the list of WordPress supported types.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $value_type database type
	 * @param string $original_value_type value's PHP type
	 * @param mixed $value current value
	 */
	return apply_filters( 'database_type_for_value', $value_type, $original_value_type, $value );
}

/**
 * Prepare a value before inserting it to the database.
 *
 * @since n.e.x.t
 *
 * @param mixed $value original value.
 *
 * @return mixed the prepared value for the database.
 */
function wp_prepare_value_for_db( $value ) {
	$type           = wp_get_database_type_for_value( $value );
	$prepared_value = $value;
	if ( in_array( $type, array( WP_TYPE_ARRAY, WP_TYPE_OBJECT ), true ) ) {
		$prepared_value = serialize( $value );
	} elseif ( WP_TYPE_UNKNOWN === $type ) {
		$prepared_value = maybe_serialize( $value );
	}

	/**
	 * Filter prepared value for the database.
	 *
	 * @since n.e.x.t
	 *
	 * @param mixed $prepared_value the prepared value.
	 * @param mixed $value the original value.
	 * @param string $type type infered from the original value.
	 */
	return apply_filters( 'prepare_value_for_db', $prepared_value, $value, $type );
}

/**
 * Format a value from the database.
 *
 * Convert the value from the database back to its original type.
 *
 * @since n.e.x.t
 *
 * @param string $type original value type.
 * @param mixed $value raw value from the database.
 *
 * @return mixed
 */
function wp_format_value_from_db( $type, $value ) {

	if ( WP_TYPE_ARRAY === $type ) {
		$formatted_value = $value;

		// sanity check to ensure value can be unserialized
		if ( is_serialized( $value ) ) {
			$formatted_value = unserialize( $value );
		}

		$formatted_value = (array) $formatted_value;
	} elseif ( WP_TYPE_OBJECT === $type ) {
		$formatted_value = $value;

		// sanity check to ensure value can be unserialized
		if ( is_serialized( $value ) ) {
			$formatted_value = unserialize( $value );
		}

		$formatted_value = (object) $formatted_value;
	} elseif ( WP_TYPE_UNKNOWN === $type ) {
		$formatted_value = maybe_unserialize( $value );
	} else {
		$formatted_value = $value;
		settype( $formatted_value, $type );
	}

	/**
	 * Filter formatted value from database.
	 *
	 * @since n.e.x.t
	 *
	 * @param mixed $formatted_value formatted value.
	 * @param mixed $value value from the database.
	 * @param string $type original value type.
	 */
	return apply_filters( 'format_value_from_db', $formatted_value, $value, $type );
}
