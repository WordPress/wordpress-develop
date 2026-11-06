<?php

/**
 * SQLite information schema exception.
 *
 * This class is used to represent errors that may occur when building
 * the MySQL information schema for emulation in SQLite.
 *
 * @access private
 */
class WP_SQLite_Information_Schema_Exception extends Exception {
	// Information schema exception types.
	const TYPE_DUPLICATE_TABLE_NAME           = 'duplicate-table-name';
	const TYPE_DUPLICATE_COLUMN_NAME          = 'duplicate-column-name';
	const TYPE_COLUMN_NOT_FOUND               = 'column-not-found';
	const TYPE_DUPLICATE_KEY_NAME             = 'duplicate-key-name';
	const TYPE_KEY_COLUMN_NOT_FOUND           = 'key-column-not-found';
	const TYPE_CONSTRAINT_DOES_NOT_EXIST      = 'constraint-does-not-exist';
	const TYPE_MULTIPLE_CONSTRAINTS_WITH_NAME = 'multiple-constraints-with-name';
	const TYPE_INVALID_DEFAULT_VALUE          = 'invalid-default-value';

	/**
	 * The exception type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The data to be passed with the exception.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param string         $type     The exception type.
	 * @param string         $message  The exception message.
	 * @param array          $data     The data to be passed with the exception.
	 * @param Throwable|null $previous The previous throwable used for the exception chaining.
	 */
	public function __construct(
		string $type,
		string $message,
		array $data = array(),
		?Throwable $previous = null
	) {
		parent::__construct( $message, 0, $previous );
		$this->type = $type;
		$this->data = $data;
	}

	/**
	 * Get the type of the exception.
	 *
	 * @return string The type of the exception.
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get the data associated with the exception.
	 *
	 * @return array The data associated with the exception.
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Create a duplicate table name exception.
	 *
	 * @param  string $table_name The name of the affected table.
	 * @return self               The exception instance.
	 */
	public static function duplicate_table_name( string $table_name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_DUPLICATE_TABLE_NAME,
			sprintf( "Table '%s' already exists.", $table_name ),
			array( 'table_name' => $table_name )
		);
	}

	/**
	 * Create a duplicate column name exception.
	 *
	 * @param  string $column_name The name of the affected column.
	 * @return self                The exception instance.
	 */
	public static function duplicate_column_name( string $column_name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_DUPLICATE_COLUMN_NAME,
			sprintf( "Column '%s' already exists.", $column_name ),
			array( 'column_name' => $column_name )
		);
	}

	/**
	 * Create a column not found exception.
	 *
	 * @param  string $column_name The name of the affected column.
	 * @param  string $table_name  The name of the affected table.
	 * @return self                The exception instance.
	 */
	public static function column_not_found( string $column_name, string $table_name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_COLUMN_NOT_FOUND,
			sprintf( "Unknown column '%s' in '%s'.", $column_name, $table_name ),
			array(
				'column_name' => $column_name,
				'table_name'  => $table_name,
			)
		);
	}

	/**
	 * Create a duplicate key name exception.
	 *
	 * @param  string $key_name The name of the affected key.
	 * @return self             The exception instance.
	 */
	public static function duplicate_key_name( string $key_name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_DUPLICATE_KEY_NAME,
			sprintf( "Key '%s' already exists.", $key_name ),
			array( 'key_name' => $key_name )
		);
	}

	/**
	 * Create a key column not found exception.
	 *
	 * @param  string $column_name The name of the affected column.
	 * @return self                The exception instance.
	 */
	public static function key_column_not_found( string $column_name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_KEY_COLUMN_NOT_FOUND,
			sprintf( "Key column '%s' doesn't exist in table.", $column_name ),
			array( 'column_name' => $column_name )
		);
	}

	/**
	 * Create a constraint does not exist exception.
	 *
	 * @param  string $name The name of the affected constraint.
	 * @return self         The exception instance.
	 */
	public static function constraint_does_not_exist( string $name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_CONSTRAINT_DOES_NOT_EXIST,
			sprintf( "Constraint '%s' does not exist.", $name ),
			array( 'name' => $name )
		);
	}

	/**
	 * Create a multiple constraints with name exception.
	 *
	 * @param  string $name The name of the affected constraint.
	 * @return self         The exception instance.
	 */
	public static function multiple_constraints_with_name( string $name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_MULTIPLE_CONSTRAINTS_WITH_NAME,
			sprintf( "Table has multiple constraints with the name '%s'. Please use constraint specific 'DROP' clause.", $name ),
			array( 'name' => $name )
		);
	}

	/**
	 * Create an invalid default value exception.
	 *
	 * @param  string $column_name The name of the affected column.
	 * @return self                The exception instance.
	 */
	public static function invalid_default_value( string $column_name ): WP_SQLite_Information_Schema_Exception {
		return new self(
			self::TYPE_INVALID_DEFAULT_VALUE,
			sprintf( "Invalid default value for '%s'.", $column_name ),
			array( 'column_name' => $column_name )
		);
	}
}
