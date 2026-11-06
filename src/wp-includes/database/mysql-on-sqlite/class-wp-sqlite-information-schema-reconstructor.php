<?php

/*
 * The SQLite driver uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 */

/**
 * SQLite information schema reconstructor for MySQL.
 *
 * This class checks and reconstructs the MySQL INFORMATION_SCHEMA data in SQLite
 * when it becomes out of sync with the actual SQLite database schema.
 *
 * Currently, it reconstructs schema information for missing tables, and removes
 * stale data for tables that no longer exist. When used with WordPress, it uses
 * the "wp_get_db_schema()" function to reconstruct WordPress table information.
 *
 * @access private
 */
class WP_SQLite_Information_Schema_Reconstructor {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_MySQL_On_SQLite
	 */
	private $driver;

	/**
	 * An instance of the SQLite connection.
	 *
	 * @var WP_SQLite_Connection
	 */
	private $connection;

	/**
	 * A service for managing MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Builder
	 */
	private $schema_builder;

	/**
	 * Constructor.
	 *
	 * @param WP_MySQL_On_SQLite               $driver         The SQLite driver instance.
	 * @param WP_SQLite_Information_Schema_Builder $schema_builder The information schema builder instance.
	 */
	public function __construct(
		$driver,
		WP_SQLite_Information_Schema_Builder $schema_builder
	) {
		$this->driver         = $driver;
		$this->connection     = $driver->get_connection();
		$this->schema_builder = $schema_builder;
	}

	/**
	 * Ensure that the MySQL INFORMATION_SCHEMA data in SQLite is correct.
	 *
	 * This method checks if the MySQL INFORMATION_SCHEMA data in SQLite is correct,
	 * and if it is not, it will reconstruct missing data and remove stale values.
	 */
	public function ensure_correct_information_schema(): void {
		$sqlite_tables             = $this->get_sqlite_table_names();
		$information_schema_tables = $this->get_information_schema_table_names();

		$tables_missing_from_information_schema = array_diff( $sqlite_tables, $information_schema_tables );
		$tables_missing_from_sqlite             = array_diff( $information_schema_tables, $sqlite_tables );

		// In WordPress, use "wp_get_db_schema()" to reconstruct WordPress tables.
		$wp_tables = count( $tables_missing_from_information_schema ) > 0
			? $this->get_wp_create_table_statements()
			: array();

		// Reconstruct information schema records for tables that don't have them.
		foreach ( $tables_missing_from_information_schema as $table ) {
			if ( isset( $wp_tables[ $table ] ) ) {
				// WordPress core table (as returned by "wp_get_db_schema()").
				$ast = $wp_tables[ $table ];
			} else {
				// Other table (a WordPress plugin or unrelated to WordPress).
				$sql = $this->generate_create_table_statement( $table );
				$ast = $this->driver->create_parser( $sql )->parse();
				if ( null === $ast ) {
					throw new WP_MySQL_On_SQLite_Exception( $this->driver, 'Failed to parse the MySQL query.' );
				}
			}

			/*
			 * First, let's make sure we clean up all related data. This fixes
			 * partial data corruption, such as when a table record is missing,
			 * but some related column, index, or constraint records are stored.
			 */
			$this->record_drop_table( $table );

			$this->schema_builder->record_create_table( $ast );
		}

		// Remove information schema records for tables that don't exist.
		foreach ( $tables_missing_from_sqlite as $table ) {
			$this->record_drop_table( $table );
		}
	}

	/**
	 * Record a DROP TABLE statement in the information schema.
	 *
	 * This removes a table record from the information schema, as well as all
	 * column, index, and constraint records that are related to the table.
	 *
	 * @param string $table_name The name of the table to drop.
	 */
	private function record_drop_table( string $table_name ): void {
		$sql = sprintf( 'DROP TABLE %s', $this->connection->quote_identifier( $table_name ) ); // TODO: mysql quote
		$ast = $this->driver->create_parser( $sql )->parse();
		if ( null === $ast ) {
			throw new WP_MySQL_On_SQLite_Exception( $this->driver, 'Failed to parse the MySQL query.' );
		}
		$this->schema_builder->record_drop_table(
			$ast->get_first_descendant_node( 'dropStatement' )
		);
	}

	/**
	 * Get the names of all existing tables in the SQLite database.
	 *
	 * @return string[] The names of tables in the SQLite database.
	 */
	private function get_sqlite_table_names(): array {
		return $this->driver->execute_sqlite_query(
			"
				SELECT name
				FROM sqlite_master
				WHERE type = 'table'
				AND name != ?
				AND name NOT LIKE ? ESCAPE '\'
				AND name NOT LIKE ? ESCAPE '\'
				ORDER BY name
			",
			array(
				'_mysql_data_types_cache',
				'sqlite\_%',
				str_replace( '_', '\_', WP_MySQL_On_SQLite::RESERVED_PREFIX ) . '%',
			)
		)->fetchAll( PDO::FETCH_COLUMN );
	}

	/**
	 * Get the names of all tables recorded in the information schema.
	 *
	 * @return string[] The names of tables in the information schema.
	 */
	private function get_information_schema_table_names(): array {
		$tables_table = $this->schema_builder->get_table_name( false, 'tables' );
		return $this->driver->execute_sqlite_query(
			sprintf(
				'SELECT table_name FROM %s ORDER BY table_name',
				$this->connection->quote_identifier( $tables_table )
			)
		)->fetchAll( PDO::FETCH_COLUMN );
	}

	/**
	 * Get a map of parsed CREATE TABLE statements for WordPress tables.
	 *
	 * When reconstructing the information schema data for WordPress tables, we
	 * can use the "wp_get_db_schema()" function to get accurate CREATE TABLE
	 * statements. This method parses the result of "wp_get_db_schema()" into
	 * an array of parsed CREATE TABLE statements indexed by the table names.
	 *
	 * @return array<string, WP_Parser_Node> The WordPress CREATE TABLE statements.
	 */
	private function get_wp_create_table_statements(): array {
		// Bail out when not in a WordPress environment.
		if ( ! defined( 'ABSPATH' ) ) {
			return array();
		}

		/*
		 * In WP CLI, $wpdb may not be set. In that case, we can't load the schema.
		 * We need to bail out and use the standard non-WordPress-specific behavior.
		 */
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			// Outside of WP CLI, let's trigger a warning.
			if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
				trigger_error( 'The $wpdb global is not initialized.', E_USER_WARNING );
			}
			return array();
		}

		// Ensure the "wp_get_db_schema()" function is defined.
		if ( file_exists( ABSPATH . 'wp-admin/includes/schema.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/schema.php';
		}
		if ( ! function_exists( 'wp_get_db_schema' ) ) {
			throw new Exception( 'The "wp_get_db_schema()" function was not defined.' );
		}

		/*
		 * At this point, WPDB may not yet be initialized, as we're configuring
		 * the database connection. Let's only populate the table names using
		 * the "$table_prefix" global so we can get correct table names.
		 */
		global $table_prefix;
		$set_prefix_result = $wpdb->set_prefix( $table_prefix );
		if ( $set_prefix_result instanceof WP_Error ) {
			throw new Exception( $set_prefix_result->get_error_message() );
		}

		// Get schema for global tables.
		$schema = wp_get_db_schema( 'global' );

		// For multisite installs, get all blog IDs.
		$blog_ids = array();
		if ( is_multisite() ) {
			/*
			 * We need to use a database query over the "get_sites()" function,
			 * as WPDB may not yet be initialized. Moreover, we need to get the IDs
			 * of all existing blogs, independent of any filters and actions that
			 * could possibly alter the results of a "get_sites()" call.
			 */
			try {
				$blog_ids = $this->driver->execute_sqlite_query(
					sprintf(
						'SELECT blog_id FROM %s',
						$this->connection->quote_identifier( $wpdb->blogs )
					)
				)->fetchAll( PDO::FETCH_COLUMN );
			} catch ( PDOException $e ) {
				if ( ! str_contains( $e->getMessage(), 'no such table' ) ) {
					throw $e;
				}
			}
		}

		// Get schema for blog tables.
		if ( 0 === count( $blog_ids ) ) {
			// Single site or no blog IDs: Add schema for the main site.
			$schema .= wp_get_db_schema( 'blog' );
		} else {
			// Multisite: Add schema definitions for all sites.
			foreach ( $blog_ids as $blog_id ) {
				$schema .= wp_get_db_schema( 'blog', (int) $blog_id );
			}
		}

		// Parse the schema.
		$parser    = $this->driver->create_parser( $schema );
		$wp_tables = array();
		while ( $parser->next_query() ) {
			$ast = $parser->get_query_ast();
			if ( null === $ast ) {
				throw new WP_MySQL_On_SQLite_Exception( $this->driver, 'Failed to parse the MySQL query.' );
			}

			$create_node = $ast->get_first_descendant_node( 'createStatement' );
			if ( $create_node && $create_node->has_child_node( 'createTable' ) ) {
				$name_node = $create_node->get_first_descendant_node( 'tableName' );
				$name      = $this->unquote_mysql_identifier(
					substr( $schema, $name_node->get_start(), $name_node->get_length() )
				);

				$wp_tables[ $name ] = $create_node;
			}
		}
		return $wp_tables;
	}

	/**
	 * Generate a MySQL CREATE TABLE statement from an SQLite table definition.
	 *
	 * @param  string $table_name The name of the table.
	 * @return string             The CREATE TABLE statement.
	 */
	private function generate_create_table_statement( string $table_name ): string {
		// Columns.
		$columns = $this->driver->execute_sqlite_query(
			sprintf(
				'PRAGMA table_xinfo(%s)',
				$this->connection->quote_identifier( $table_name )
			)
		)->fetchAll( PDO::FETCH_ASSOC );

		$definitions  = array();
		$column_types = array();
		foreach ( $columns as $column ) {
			$mysql_type = $this->get_cached_mysql_data_type( $table_name, $column['name'] );
			if ( null === $mysql_type ) {
				$mysql_type = $this->get_mysql_column_type( $column['type'] );
			}
			$definitions[]                   = $this->generate_column_definition( $table_name, $column );
			$column_types[ $column['name'] ] = $mysql_type;
		}

		// Primary key.
		$pk_columns = array();
		foreach ( $columns as $column ) {
			// A position of the column in the primary key, starting from index 1.
			// A value of 0 means that the column is not part of the primary key.
			$pk_position = (int) $column['pk'];
			if ( 0 !== $pk_position ) {
				$pk_columns[ $pk_position ] = $column['name'];
			}
		}

		// Sort the columns by their position in the primary key.
		ksort( $pk_columns );

		if ( count( $pk_columns ) > 0 ) {
			$quoted_pk_columns = array();
			foreach ( $pk_columns as $pk_column ) {
				$quoted_pk_columns[] = $this->connection->quote_identifier( $pk_column );
			}
			$definitions[] = sprintf( 'PRIMARY KEY (%s)', implode( ', ', $quoted_pk_columns ) );
		}

		// Indexes and keys.
		$keys = $this->driver->execute_sqlite_query(
			sprintf(
				'PRAGMA index_list(%s)',
				$this->connection->quote_identifier( $table_name )
			)
		)->fetchAll( PDO::FETCH_ASSOC );

		foreach ( $keys as $key ) {
			// Skip the internal index that SQLite may create for a primary key.
			// In MySQL, no explicit index needs to be defined for a primary key.
			if ( 'pk' === $key['origin'] ) {
				continue;
			}
			$definitions[] = $this->generate_key_definition( $table_name, $key, $column_types );
		}

		return sprintf(
			"CREATE TABLE %s (\n  %s\n)",
			$this->connection->quote_identifier( $table_name ),
			implode( ",\n  ", $definitions )
		);
	}

	/**
	 * Generate a MySQL column definition from an SQLite column information.
	 *
	 * This method generates a MySQL column definition from SQLite column data.
	 *
	 * @param  string $table_name  The name of the table.
	 * @param  array  $column_info The SQLite column information.
	 * @return string              The MySQL column definition.
	 */
	private function generate_column_definition( string $table_name, array $column_info ): string {
		$definition   = array();
		$definition[] = $this->connection->quote_identifier( $column_info['name'] );

		// Data type.
		$mysql_type = $this->get_cached_mysql_data_type( $table_name, $column_info['name'] );
		if ( null === $mysql_type ) {
			$mysql_type = $this->get_mysql_column_type( $column_info['type'] );
		}

		/**
		 * Correct some column types based on their default values:
		 *   1. In MySQL, non-datetime columns can't have a timestamp default.
		 *      Let's use DATETIME when default is set to CURRENT_TIMESTAMP.
		 *   2. In MySQL, TEXT and BLOB columns can't have a default value.
		 *      Let's use VARCHAR(65535) and VARBINARY(65535) when default is set.
		 */
		$default = $this->generate_column_default( $mysql_type, $column_info['dflt_value'] );
		if ( 'CURRENT_TIMESTAMP' === $default ) {
			$mysql_type = 'datetime';
		} elseif ( 'text' === $mysql_type && null !== $default ) {
			$mysql_type = 'varchar(65535)';
		} elseif ( 'blob' === $mysql_type && null !== $default ) {
			$mysql_type = 'varbinary(65535)';
		}

		$definition[] = $mysql_type;

		// NULL/NOT NULL.
		if ( 1 === (int) $column_info['notnull'] ) {
			$definition[] = 'NOT NULL';
		}

		// Auto increment.
		$is_auto_increment = false;
		if ( 0 !== (int) $column_info['pk'] ) {
			$is_auto_increment = $this->driver->execute_sqlite_query(
				'SELECT 1 FROM sqlite_master WHERE tbl_name = ? AND sql LIKE ?',
				array( $table_name, '%AUTOINCREMENT%' )
			)->fetchColumn();

			if ( $is_auto_increment ) {
				$definition[] = 'AUTO_INCREMENT';
			}
		}

		// Default value.
		if ( null !== $default && ! $is_auto_increment ) {
			$definition[] = 'DEFAULT ' . $default;
		}

		return implode( ' ', $definition );
	}

	/**
	 * Generate a MySQL key definition from an SQLite key information.
	 *
	 * This method generates a MySQL key definition from SQLite key data.
	 *
	 * @param  string $table_name   The name of the table.
	 * @param  array  $key_info     The SQLite key information.
	 * @param  array  $column_types The MySQL data types of the columns.
	 * @return string               The MySQL key definition.
	 */
	private function generate_key_definition( string $table_name, array $key_info, array $column_types ): string {
		$definition = array();

		// Key type.
		$cached_type = $this->get_cached_mysql_data_type( $table_name, $key_info['name'] );
		if ( 'FULLTEXT' === $cached_type ) {
			$definition[] = 'FULLTEXT KEY';
		} elseif ( 'SPATIAL' === $cached_type ) {
			$definition[] = 'SPATIAL KEY';
		} elseif ( 'UNIQUE' === $cached_type || 1 === (int) $key_info['unique'] ) {
			$definition[] = 'UNIQUE KEY';
		} else {
			$definition[] = 'KEY';
		}

		// Key name.
		$name = $key_info['name'];

		/*
		 * The SQLite driver prefixes index names with "{$table_name}__" to avoid
		 * naming conflicts among tables in SQLite. We need to remove the prefix.
		 */
		if ( str_starts_with( $name, "{$table_name}__" ) ) {
			$name = substr( $name, strlen( "{$table_name}__" ) );
		}

		/**
		 * SQLite creates automatic internal indexes for primary and unique keys,
		 * naming them in format "sqlite_autoindex_{$table_name}_{$index_id}".
		 * For these internal indexes, we need to skip their name, so that in
		 * the generated MySQL definition, they follow implicit MySQL naming.
		 */
		if ( ! str_starts_with( $name, 'sqlite_autoindex_' ) ) {
			$definition[] = $this->connection->quote_identifier( $name );
		}

		// Key columns.
		$key_columns = $this->driver->execute_sqlite_query(
			sprintf(
				'PRAGMA index_info(%s)',
				$this->connection->quote_identifier( $key_info['name'] )
			)
		)->fetchAll( PDO::FETCH_ASSOC );
		$cols        = array();
		foreach ( $key_columns as $column ) {
			/*
			 * Extract type and length from column data type definition.
			 *
			 * This is required when the column data type is inferred from the
			 * '_mysql_data_types_cache' table, which stores the data type in
			 * the format "type(length)", such as "varchar(255)".
			 */
			$max_prefix_length = 100;
			$type              = strtolower( $column_types[ $column['name'] ] );
			$parts             = explode( '(', $type );
			$column_type       = $parts[0];
			$column_length     = isset( $parts[1] ) ? (int) $parts[1] : null;

			/*
			 * Add an index column prefix length, if needed.
			 *
			 * This is required for "text" and "blob" types for columns inferred
			 * directly from the SQLite schema, and for the following types for
			 * columns inferred from the '_mysql_data_types_cache' table:
			 *   char, varchar
			 *   text, tinytext, mediumtext, longtext
			 *   blob, tinyblob, mediumblob, longblob
			 *   varbinary
			 */
			if (
				str_ends_with( $column_type, 'char' )
				|| str_ends_with( $column_type, 'text' )
				|| str_ends_with( $column_type, 'blob' )
				|| str_starts_with( $column_type, 'var' )
			) {
				$cols[] = sprintf(
					'%s(%d)',
					$this->connection->quote_identifier( $column['name'] ),
					min( $column_length ?? $max_prefix_length, $max_prefix_length )
				);
			} else {
				$cols[] = $this->connection->quote_identifier( $column['name'] );
			}
		}

		$definition[] = '(' . implode( ', ', $cols ) . ')';
		return implode( ' ', $definition );
	}

	/**
	 * Generate a MySQL default value from an SQLite default value.
	 *
	 * @param  string      $mysql_type    The MySQL data type of the column.
	 * @param  string|null $default_value The default value of the SQLite column.
	 * @return string|null                The default value, or null if the column has no default value.
	 */
	private function generate_column_default( string $mysql_type, ?string $default_value ): ?string {
		if ( null === $default_value || '' === $default_value ) {
			return null;
		}
		$mysql_type = strtolower( $mysql_type );

		if ( str_starts_with( $mysql_type, 'bit' ) ) {
			// BIT columns are stored as INTEGER in SQLite.
			return "b'" . decbin( (int) $default_value ) . "'";
		}

		/*
		 * In MySQL, geometry columns can't have a default value.
		 *
		 * Geometry columns are saved as TEXT in SQLite, and in an older version
		 * of the SQLite driver, TEXT columns were assigned a default value of ''.
		 */
		if ( 'geomcollection' === $mysql_type || 'geometrycollection' === $mysql_type ) {
			return null;
		}

		/*
		 * In MySQL, date/time columns can't have a default value of ''.
		 *
		 * Date/time columns are saved as TEXT in SQLite, and in an older version
		 * of the SQLite driver, TEXT columns were assigned a default value of ''.
		 */
		if (
			"''" === $default_value
			&& in_array( $mysql_type, array( 'datetime', 'date', 'time', 'timestamp', 'year' ), true )
		) {
			return null;
		}

		/**
		 * Convert SQLite default values to MySQL default values.
		 *
		 * See:
		 *   - https://www.sqlite.org/syntax/column-constraint.html
		 *   - https://www.sqlite.org/syntax/literal-value.html
		 *   - https://www.sqlite.org/lang_expr.html#literal_values_constants_
		 */

		// Quoted string literal. E.g.: 'abc', "abc", `abc`
		$first_byte = $default_value[0] ?? null;
		if ( '"' === $first_byte || "'" === $first_byte || '`' === $first_byte ) {
			$value = substr( $default_value, 1, -1 );
			$value = str_replace( $first_byte . $first_byte, $first_byte, $value );
			return $this->quote_mysql_utf8_string_literal( $value );
		}

		// Normalize the default value for easier comparison.
		$uppercase_default_value = strtoupper( $default_value );

		// NULL, TRUE, FALSE.
		if ( 'NULL' === $uppercase_default_value ) {
			// DEFAULT NULL is the same as no default value.
			return null;
		} elseif ( 'TRUE' === $uppercase_default_value ) {
			return '1';
		} elseif ( 'FALSE' === $uppercase_default_value ) {
			return '0';
		}

		// Date/time values.
		if ( 'CURRENT_TIMESTAMP' === $uppercase_default_value ) {
			return 'CURRENT_TIMESTAMP';
		} elseif ( 'CURRENT_DATE' === $uppercase_default_value ) {
			return null; // Not supported in MySQL.
		} elseif ( 'CURRENT_TIME' === $uppercase_default_value ) {
			return null; // Not supported in MySQL.
		}

		// SQLite supports underscores in all numeric literals.
		$no_underscore_default_value = str_replace( '_', '', $default_value );

		// Numeric literals. E.g.: 123, 1.23, -1.23, 1e3, 1.2e-3
		if ( is_numeric( $no_underscore_default_value ) ) {
			return $no_underscore_default_value;
		}

		// HEX literals (numeric). E.g.: 0x1a2f, 0X1A2F
		if ( 1 === preg_match( '/^0[xX][0-9a-fA-F]+$/D', $no_underscore_default_value ) ) {
			// Convert to signed 64-bit decimal text in SQLite to avoid PHP integer size limits.
			return (string) $this->connection->query(
				'SELECT CAST(' . $no_underscore_default_value . ' AS TEXT)'
			)->fetchColumn();
		}

		// BLOB literals (string). E.g.: x'1a2f', X'1A2F'
		// Checking the prefix is enough as SQLite doesn't allow malformed values.
		if ( str_starts_with( $uppercase_default_value, "X'" ) ) {
			// Convert the hex string to ASCII bytes.
			return "'" . pack( 'H*', substr( $default_value, 2, -1 ) ) . "'";
		}

		// Unquoted string literal. E.g.: abc
		return $this->quote_mysql_utf8_string_literal( $default_value );
	}

	/**
	 * Get a MySQL column or index data type from legacy data types cache table.
	 *
	 * This method retrieves MySQL column or index data types from a special table
	 * that was used by an old version of the SQLite driver and that is otherwise
	 * no longer needed. This is more precise than direct inference from SQLite.
	 *
	 * For columns, it returns full column type, including prefix length, e.g.:
	 *   int(11), bigint(20) unsigned, varchar(255), longtext
	 *
	 * For indexes, it returns one of:
	 *   KEY, PRIMARY, UNIQUE, FULLTEXT, SPATIAL
	 *
	 * @param  string      $table_name           The table name.
	 * @param  string      $column_or_index_name The column or index name.
	 * @return string|null                       The MySQL definition, or null when not found.
	 */
	private function get_cached_mysql_data_type( string $table_name, string $column_or_index_name ): ?string {
		try {
			$mysql_type = $this->driver->execute_sqlite_query(
				'SELECT mysql_type FROM _mysql_data_types_cache
				WHERE `table` = ? COLLATE NOCASE
				AND (
					-- The old SQLite driver stored the MySQL data types in multiple
					-- formats - lowercase, uppercase, and, sometimes, with backticks.
					column_or_index = ? COLLATE NOCASE
					OR column_or_index = ? COLLATE NOCASE
				)',
				array( $table_name, $column_or_index_name, "`$column_or_index_name`" )
			)->fetchColumn();
		} catch ( PDOException $e ) {
			if ( str_contains( $e->getMessage(), 'no such table' ) ) {
				return null;
			}
			throw $e;
		}
		if ( false === $mysql_type ) {
			return null;
		}

		/**
		 * Check whether the stored type value is a valid MySQL column type.
		 *
		 * Some older versions of the legacy SQLite driver might have stored
		 * invalid MySQL column types in some scenarios:
		 *
		 *   1. Before https://github.com/WordPress/sqlite-database-integration/pull/126,
		 *      the legacy SQLite driver incorrectly stored MySQL column types
		 *      for columns with multiple type arguments.
		 *
		 *      E.g., a column definition like "col_name decimal(26, 8)" would
		 *      be stored with invalid type "decimal(26,".
		 *
		 *   2. Before https://github.com/WordPress/sqlite-database-integration/commit/b5a9fbaed4d0d843f792aaa959e3d00f193ff1ee
		 *      (see also https://github.com/Automattic/sqlite-database-integration/pull/2),
		 *      the legacy SQLite driver incorrectly recognized indexes on columns
		 *      with type keywords as additional table column definitions.
		 *
		 *      E.g., an index definition like "KEY timestamp (timestamp)" would
		 *      be stored as column "KEY" with invalid type "timestamp(timestamp)".
		 *
		 * To address these issues, we need to check whether the stored type looks
		 * like a valid MySQL column type definition.
		 */
		$open_par_index  = strpos( $mysql_type, '(' );
		$close_par_index = strpos( $mysql_type, ')' );
		if ( false !== $open_par_index ) {
			$end   = false !== $close_par_index ? $close_par_index : strlen( $mysql_type );
			$parts = explode( '(', substr( $mysql_type, 0, $end ) );
			$type  = strtolower( trim( $parts[0] ) );
			$args  = array();
			foreach ( explode( ',', $parts[1] ) as $arg ) {
				$args[] = strtolower( trim( $arg ) );
			}

			// WooCommerce uses decimal(26,8), decimal(19,4), and decimal(3,2)
			// column types, so we can can fix the invalid column definitions.
			$looks_like_wc_table = str_contains( $table_name, 'wc_' ) || str_contains( $table_name, 'woocommerce_' );
			$is_invalid_decimal  = 'decimal' === $type && count( $args ) === 2 && '' === $args[1];
			if ( $looks_like_wc_table && $is_invalid_decimal ) {
				if ( '26' === $args[0] ) {
					// Fix "decimal(26,".
					return 'decimal(26,8)';
				} elseif ( '19' === $args[0] ) {
					// Fix "decimal(19,".
					return 'decimal(19,4)';
				} elseif ( '3' === $args[0] ) {
					// Fix "decimal(3,".
					return 'decimal(3,2)';
				}
			}

			// Only numeric arguments are allowed for MySQL column types.
			// This handles the incorrectly stored index definition case.
			foreach ( $args as $arg ) {
				if ( ! is_numeric( $arg ) ) {
					return null;
				}
			}

			// If there is no closing parenthesis, the type is invalid.
			if ( false === $close_par_index ) {
				return null;
			}
		}

		// Normalize index type for backward compatibility. Some older versions
		// of the SQLite driver stored index types with a " KEY" suffix, e.g.,
		// "PRIMARY KEY" or "UNIQUE KEY". More recent versions omit the suffix.
		if ( str_ends_with( $mysql_type, ' KEY' ) ) {
			$mysql_type = substr( $mysql_type, 0, strlen( $mysql_type ) - strlen( ' KEY' ) );
		}
		return $mysql_type;
	}

	/**
	 * Get a MySQL column type from an SQLite column type.
	 *
	 * This method converts an SQLite column type to a MySQL column type as per
	 * the SQLite column type affinity rules:
	 *   https://sqlite.org/datatype3.html#determination_of_column_affinity
	 *
	 * @param  string $column_type The SQLite column type.
	 * @return string              The MySQL column type.
	 */
	private function get_mysql_column_type( string $column_type ): string {
		$type = strtoupper( $column_type );

		/*
		 * Following the rules of column affinity:
		 *   https://sqlite.org/datatype3.html#determination_of_column_affinity
		 */

		// 1. If the declared type contains the string "INT" then it is assigned
		//    INTEGER affinity.
		if ( str_contains( $type, 'INT' ) ) {
			return 'int';
		}

		// 2. If the declared type of the column contains any of the strings
		//    "CHAR", "CLOB", or "TEXT" then that column has TEXT affinity.
		if ( str_contains( $type, 'TEXT' ) || str_contains( $type, 'CHAR' ) || str_contains( $type, 'CLOB' ) ) {
			return 'text';
		}

		// 3. If the declared type for a column contains the string "BLOB" or
		//    if no type is specified then the column has affinity BLOB.
		if ( str_contains( $type, 'BLOB' ) || '' === $type ) {
			return 'blob';
		}

		// 4. If the declared type for a column contains any of the strings
		//    "REAL", "FLOA", or "DOUB" then the column has REAL affinity.
		if ( str_contains( $type, 'REAL' ) || str_contains( $type, 'FLOA' ) ) {
			return 'float';
		}
		if ( str_contains( $type, 'DOUB' ) ) {
			return 'double';
		}

		/**
		 * 5. Otherwise, the affinity is NUMERIC.
		 *
		 * While SQLite defaults to a NUMERIC column affinity, it's better to use
		 * TEXT in this case, because numeric SQLite columns in non-strict tables
		 * can contain any text data as well, when it is not a well-formed number.
		 *
		 * See: https://sqlite.org/datatype3.html#type_affinity
		 */
		return 'text';
	}

	/**
	 * Format a MySQL UTF-8 string literal for output in a CREATE TABLE statement.
	 *
	 * See WP_MySQL_On_SQLite::quote_mysql_utf8_string_literal().
	 *
	 * TODO: This is a copy of WP_MySQL_On_SQLite::quote_mysql_utf8_string_literal().
	 *       We may consider extracting it to reusable MySQL helpers.
	 *
	 * @param  string $utf8_literal The UTF-8 string literal to escape.
	 * @return string               The escaped string literal.
	 */
	private function quote_mysql_utf8_string_literal( string $utf8_literal ): string {
		$backslash    = chr( 92 );
		$replacements = array(
			"'"        => "''",                    // A single quote character (').
			$backslash => $backslash . $backslash, // A backslash character (\).
			chr( 0 )   => $backslash . '0',        // An ASCII NULL character (\0).
			chr( 10 )  => $backslash . 'n',        // A newline (linefeed) character (\n).
			chr( 13 )  => $backslash . 'r',        // A carriage return character (\r).
		);
		return "'" . strtr( $utf8_literal, $replacements ) . "'";
	}

	/**
	 * Unquote a quoted MySQL identifier.
	 *
	 * Remove bounding quotes and replace escaped quotes with their values.
	 *
	 * @param  string $quoted_identifier The quoted identifier value.
	 * @return string                    The unquoted identifier value.
	 */
	private function unquote_mysql_identifier( string $quoted_identifier ): string {
		$first_byte = $quoted_identifier[0] ?? null;
		if ( '"' === $first_byte || '`' === $first_byte ) {
			$unquoted = substr( $quoted_identifier, 1, -1 );
			return str_replace( $first_byte . $first_byte, $first_byte, $unquoted );
		}
		return $quoted_identifier;
	}
}
