<?php

/**
 * SQLite database configurator.
 *
 * This class initializes and configures the SQLite database, so that it can be
 * used by the SQLite driver to translate and emulate MySQL queries in SQLite.
 *
 * The configurator ensures that tables required for emulating MySQL behaviors
 * are created and populated with necessary data. It is also able to partially
 * repair and update these tables and metadata in case of database corruption.
 *
 * @access private
 */
class WP_SQLite_Configurator {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_MySQL_On_SQLite
	 */
	private $driver;

	/**
	 * A service for managing MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Builder
	 */
	private $schema_builder;

	/**
	 * A service for reconstructing the MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Reconstructor
	 */
	private $schema_reconstructor;

	/**
	 * Constructor.
	 *
	 * @param WP_MySQL_On_SQLite               $driver         The SQLite driver instance.
	 * @param WP_SQLite_Information_Schema_Builder $schema_builder The information schema builder instance.
	 */
	public function __construct(
		WP_MySQL_On_SQLite $driver,
		WP_SQLite_Information_Schema_Builder $schema_builder
	) {
		$this->driver               = $driver;
		$this->schema_builder       = $schema_builder;
		$this->schema_reconstructor = new WP_SQLite_Information_Schema_Reconstructor(
			$driver,
			$schema_builder
		);
	}

	/**
	 * Ensure that the SQLite database is configured.
	 *
	 * This method checks if the database is configured for the latest SQLite
	 * driver version, and if it is not, it will configure the database.
	 */
	public function ensure_database_configured(): void {
		$version    = SQLITE_DRIVER_VERSION;
		$db_version = $this->driver->get_saved_driver_version();
		if ( version_compare( $version, $db_version ) > 0 ) {
			$this->configure_database();
		}
	}

	/**
	 * Configure the SQLite database.
	 *
	 * This method creates tables used for emulating MySQL behaviors in SQLite,
	 * and populates them with necessary data. When it is used with an already
	 * configured database, it will update the configuration as per the current
	 * SQLite driver version and attempt to repair any configuration corruption.
	 */
	public function configure_database(): void {
		/*
		 * In SQLite, disabling foreign keys inside a transaction is a no-op.
		 * We need to disable them here, because some configuration operations
		 * may expect them disabled, e.g., to avoid triggering cascades.
		 *
		 * See:
		 *   https://www.sqlite.org/lang_altertable.html#making_other_kinds_of_table_schema_changes
		 */
		$foreign_keys = $this->driver->execute_sqlite_query( 'PRAGMA foreign_keys' )->fetchColumn();
		$this->driver->execute_sqlite_query( 'PRAGMA foreign_keys = OFF' );
		$transaction_started = false;
		try {
			// Use an EXCLUSIVE transaction to prevent multiple connections
			// from attempting to configure the database at the same time.
			$this->driver->execute_sqlite_query( 'BEGIN EXCLUSIVE TRANSACTION' );
			$transaction_started = true;
			$this->set_driver_in_transaction( true );

			// Read the saved version within the transaction, before it's updated.
			$saved_version = $this->driver->get_saved_driver_version();

			$this->ensure_global_variables_table();
			$this->schema_builder->ensure_information_schema_tables();
			$this->schema_reconstructor->ensure_correct_information_schema();
			$this->ensure_database_data();
			$this->run_migrations( $saved_version );
			$this->save_current_driver_version();
			$this->driver->execute_sqlite_query( 'COMMIT' );
		} catch ( Throwable $e ) {
			// Roll back only the transaction started here, unless a failed
			// MySQL query already rolled it back in the driver.
			if ( $transaction_started && $this->driver->inTransaction() ) {
				$this->driver->execute_sqlite_query( 'ROLLBACK' );
			}
			throw $e;
		} finally {
			if ( $transaction_started ) {
				$this->set_driver_in_transaction( false );
			}
			if ( 1 === (int) $foreign_keys ) {
				$this->driver->execute_sqlite_query( 'PRAGMA foreign_keys = ON' );
			}
		}
	}

	/**
	 * Ensure that the global variables table exists.
	 *
	 * This method configures a database table to store MySQL global variables
	 * and other internal configuration values.
	 */
	private function ensure_global_variables_table(): void {
		$this->driver->execute_sqlite_query(
			sprintf(
				'CREATE TABLE IF NOT EXISTS %s (name TEXT PRIMARY KEY, value TEXT)',
				$this->driver->get_connection()->quote_identifier(
					WP_MySQL_On_SQLite::GLOBAL_VARIABLES_TABLE_NAME
				)
			)
		);
	}

	/**
	 * Ensure that the database data is correctly populated.
	 *
	 * This method ensures that the "INFORMATION_SCHEMA.SCHEMATA" table contains
	 * records for both the "INFORMATION_SCHEMA" database and the user database.
	 * At the moment, only a single user database is supported.
	 *
	 * Additionally, this method ensures that the user database name is stored
	 * correctly in all the information schema tables.
	 */
	public function ensure_database_data(): void {
		// Get all databases from the "SCHEMATA" table.
		$schemata_table = $this->schema_builder->get_table_name( false, 'schemata' );
		$databases      = $this->driver->execute_sqlite_query(
			sprintf(
				'SELECT SCHEMA_NAME FROM %s',
				$this->driver->get_connection()->quote_identifier( $schemata_table )
			)
		)->fetchAll( PDO::FETCH_COLUMN ); // phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO

		// Ensure that the "INFORMATION_SCHEMA" database record exists.
		if ( ! in_array( 'information_schema', $databases, true ) ) {
			$this->driver->execute_sqlite_query(
				sprintf(
					'INSERT INTO %s (SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME) VALUES (?, ?, ?)',
					$this->driver->get_connection()->quote_identifier( $schemata_table )
				),
				// The "INFORMATION_SCHEMA" database stays on "utf8mb3" even in MySQL 8 and 9.
				array( 'information_schema', 'utf8mb3', 'utf8mb3_general_ci' )
			);
		}

		// Get the existing user database name.
		$existing_user_db_name = null;
		foreach ( $databases as $database ) {
			if ( 'information_schema' !== strtolower( $database ) ) {
				$existing_user_db_name = $database;
				break;
			}
		}

		// Ensure that the user database record exists.
		if ( null === $existing_user_db_name ) {
			$existing_user_db_name = WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME;
			$this->driver->execute_sqlite_query(
				sprintf(
					'INSERT INTO %s (SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME) VALUES (?, ?, ?)',
					$this->driver->get_connection()->quote_identifier( $schemata_table )
				),
				// @TODO: This should probably be version-dependent.
				//        Before MySQL 8, the default was different.
				array( $existing_user_db_name, 'utf8mb4', 'utf8mb4_0900_ai_ci' )
			);
		}

		// Migrate from older versions without dynamic database names.
		$saved_database_name = WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME;
		if ( $saved_database_name !== $existing_user_db_name ) {
			// INFORMATION_SCHEMA.SCHEMATA
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET SCHEMA_NAME = ? WHERE SCHEMA_NAME != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $schemata_table )
				),
				array( $saved_database_name )
			);

			// INFORMATION_SCHEMA.TABLES
			$tables_table = $this->schema_builder->get_table_name( false, 'tables' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $tables_table )
				),
				array( $saved_database_name )
			);

			// INFORMATION_SCHEMA.COLUMNS
			$columns_table = $this->schema_builder->get_table_name( false, 'columns' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $columns_table )
				),
				array( $saved_database_name )
			);

			// INFORMATION_SCHEMA.STATISTICS
			$statistics_table = $this->schema_builder->get_table_name( false, 'statistics' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ?, INDEX_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $statistics_table )
				),
				array( $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
			$table_constraints_table = $this->schema_builder->get_table_name( false, 'table_constraints' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ?, CONSTRAINT_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $table_constraints_table )
				),
				array( $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
			$referential_constraints_table = $this->schema_builder->get_table_name( false, 'referential_constraints' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET CONSTRAINT_SCHEMA = ?, UNIQUE_CONSTRAINT_SCHEMA = ? WHERE CONSTRAINT_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $referential_constraints_table )
				),
				array( $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
			$key_column_usage_table = $this->schema_builder->get_table_name( false, 'key_column_usage' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s
					SET
					  TABLE_SCHEMA = ?,
					  CONSTRAINT_SCHEMA = ?,
					  REFERENCED_TABLE_SCHEMA = CASE WHEN REFERENCED_TABLE_SCHEMA IS NULL THEN NULL ELSE ? END
					WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $key_column_usage_table )
				),
				array( $saved_database_name, $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.CHECK_CONSTRAINTS
			$check_constraints_table = $this->schema_builder->get_table_name( false, 'check_constraints' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET CONSTRAINT_SCHEMA = ? WHERE CONSTRAINT_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $check_constraints_table )
				),
				array( $saved_database_name )
			);
		}
	}

	/**
	 * Ensure that the driver knows that we started or ended an SQLite transaction.
	 *
	 * This is a workaround for PHP < 8.4, where PDO::inTransaction() doesn't
	 * reflect transaction state started via a query, such as "BEGIN EXCLUSIVE".
	 *
	 * @see WP_MySQL_On_SQLite::inTransaction()
	 *
	 * @param bool $in_transaction Whether the driver is in a transaction.
	 */
	private function set_driver_in_transaction( bool $in_transaction ): void {
		if ( PHP_VERSION_ID >= 80400 ) {
			return;
		}

		// Bind the closure to the driver class, so that it can access the private
		// property even when the driver is an instance of a subclass.
		$set_in_transaction = function () use ( $in_transaction ): void {
			$this->in_transaction = $in_transaction;
		};
		Closure::bind( $set_in_transaction, $this->driver, WP_MySQL_On_SQLite::class )();
	}

	/**
	 * Save the current SQLite driver version.
	 *
	 * This method saves the current SQLite driver version to the database.
	 */
	private function save_current_driver_version(): void {
		$this->driver->execute_sqlite_query(
			sprintf(
				'INSERT INTO %s (name, value) VALUES (?, ?) ON CONFLICT(name) DO UPDATE SET value = ?',
				$this->driver->get_connection()->quote_identifier(
					WP_MySQL_On_SQLite::GLOBAL_VARIABLES_TABLE_NAME
				)
			),
			array(
				WP_MySQL_On_SQLite::DRIVER_VERSION_VARIABLE_NAME,
				SQLITE_DRIVER_VERSION,
				SQLITE_DRIVER_VERSION,
			)
		);
	}

	/**
	 * Migrate data stored by older driver versions.
	 *
	 * This is used when a new driver version needs to change some existing data.
	 * Migrations can be version-gated so that they don't rerun on every update.
	 *
	 * @param string $saved_version The driver version that last configured the database.
	 */
	private function run_migrations( string $saved_version ): void {
		// Drop ON UPDATE CURRENT_TIMESTAMP triggers created by versions <= 3.1.1.
		// Run this before other migrations would drop them by table rebuilds.
		if ( version_compare( $saved_version, '3.1.1', '<=' ) ) {
			$this->migrate_on_update_triggers();
		}

		// Fix temporal precision and zero defaults stored by versions <= 3.1.1.
		if ( version_compare( $saved_version, '3.1.1', '<=' ) ) {
			$this->migrate_datetime_precision();
			$this->migrate_temporal_zero_defaults();
		}
	}

	/**
	 * Migrate ON UPDATE CURRENT_TIMESTAMP triggers to column metadata.
	 *
	 * Handles old ON UPDATE triggers with the following name formats:
	 *
	 *   1. Versions 3.0.0 to 3.1.1: "_wp_sqlite_{table}_{column}_on_update"
	 *   2. Versions before 3.0.0: "__{table}_{column}_on_update__"
	 */
	private function migrate_on_update_triggers(): void {
		$connection    = $this->driver->get_connection();
		$columns_table = $connection->quote_identifier( $this->schema_builder->get_table_name( false, 'columns' ) );
		$columns       = $this->driver->execute_sqlite_query(
			sprintf(
				"SELECT c.TABLE_NAME, c.COLUMN_NAME, c.EXTRA, t.name AS TRIGGER_NAME
				FROM %s AS c
				JOIN main.sqlite_master AS t ON t.type = 'trigger'
				AND t.tbl_name = c.TABLE_NAME COLLATE NOCASE
				AND (
					t.name = '__' || c.TABLE_NAME || '_' || c.COLUMN_NAME || '_on_update__' COLLATE NOCASE
					OR t.name = ? || c.TABLE_NAME || '_' || c.COLUMN_NAME || '_on_update' COLLATE NOCASE
				)
				WHERE c.TABLE_SCHEMA = ?",
				$columns_table
			),
			array( WP_MySQL_On_SQLite::RESERVED_PREFIX, WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME )
		)->fetchAll( PDO::FETCH_ASSOC );

		foreach ( $columns as $column ) {
			if ( ! str_contains( $column['EXTRA'], 'on update CURRENT_TIMESTAMP' ) ) {
				$this->driver->execute_sqlite_query(
					sprintf( 'UPDATE %s SET EXTRA = ? WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', $columns_table ),
					array(
						trim( $column['EXTRA'] . ' on update CURRENT_TIMESTAMP' ),
						WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME,
						$column['TABLE_NAME'],
						$column['COLUMN_NAME'],
					)
				);
			}
			$this->driver->execute_sqlite_query(
				sprintf( 'DROP TRIGGER main.%s', $connection->quote_identifier( $column['TRIGGER_NAME'] ) )
			);
		}
	}

	/**
	 * Migrate DATETIME_PRECISION values stored by older driver versions.
	 *
	 * Older versions stored 0 regardless of fractional seconds precision.
	 * The correct value is read from the column type, such as "datetime(3)".
	 */
	private function migrate_datetime_precision(): void {
		// CAST() reads the leading integer, e.g., "3)" as 3, or returns 0 when there is none.
		$this->driver->execute_sqlite_query(
			sprintf(
				"UPDATE %s
				SET DATETIME_PRECISION = CAST(SUBSTR(COLUMN_TYPE, INSTR(COLUMN_TYPE, '(') + 1) AS INTEGER)
				WHERE DATA_TYPE IN ('time', 'datetime', 'timestamp')",
				$this->driver->get_connection()->quote_identifier(
					$this->schema_builder->get_table_name( false, 'columns' )
				)
			)
		);
	}

	/**
	 * Migrate zero defaults of temporal columns stored by older driver versions.
	 *
	 * Older driver versions stored defaults such as "DEFAULT 0" of a TIMESTAMP
	 * column verbatim, while MySQL stores them as a zero date/time value. Rows
	 * that got such a default store it as well, so they get the zero value too.
	 */
	private function migrate_temporal_zero_defaults(): void {
		$connection    = $this->driver->get_connection();
		$columns_table = $connection->quote_identifier(
			$this->schema_builder->get_table_name( false, 'columns' )
		);

		// Read SQLite defaults; reconstructed metadata may already be normalized.
		$columns = $this->driver->execute_sqlite_query(
			sprintf(
				"SELECT c.TABLE_NAME, c.COLUMN_NAME, c.DATA_TYPE, c.DATETIME_PRECISION, p.dflt_value
				FROM %s AS c
				JOIN pragma_table_info(c.TABLE_NAME, 'main') AS p ON c.COLUMN_NAME = p.name
				WHERE c.TABLE_SCHEMA = ?
				AND c.DATA_TYPE IN ('date', 'time', 'datetime', 'timestamp', 'year')
				AND c.EXTRA NOT LIKE '%%DEFAULT_GENERATED%%'
				AND p.dflt_value IS NOT NULL",
				$columns_table
			),
			array( WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME )
		)->fetchAll( PDO::FETCH_ASSOC );

		// Normalize zeros, such as 0, '0', -0.0, or 0e0.
		$table_columns = array();
		foreach ( $columns as $column ) {
			// SQLite returns the default as an SQL literal, e.g., 0, '0', or "0".
			$default   = $column['dflt_value'];
			$is_string = in_array( $default[0], array( "'", '"', '`' ), true );
			if ( $is_string ) {
				$default = substr( $default, 1, -1 );
			}
			if ( ! is_numeric( $default ) || 0.0 !== (float) $default ) {
				continue;
			}

			// A string zero of a YEAR column, such as '0', can be the year 2000.
			if ( $is_string && 'year' === $column['DATA_TYPE'] ) {
				continue;
			}

			$zero_value = WP_SQLite_Information_Schema_Builder::TEMPORAL_ZERO_VALUE_MAP[ $column['DATA_TYPE'] ];
			$precision  = (int) $column['DATETIME_PRECISION'];
			if ( $precision > 0 ) {
				$zero_value .= '.' . str_repeat( '0', $precision );
			}

			$table_columns[ $column['TABLE_NAME'] ][] = array(
				'name'        => $column['COLUMN_NAME'],
				'zero_value'  => $zero_value,
				'old_default' => $default,
				'is_string'   => $is_string,
			);
		}

		foreach ( $table_columns as $table_name => $repaired_columns ) {
			// Drop triggers, so they don't run during row repair. The table rebuild drops them anyway.
			$triggers = $this->driver->execute_sqlite_query(
				"SELECT name FROM sqlite_master WHERE type = 'trigger' AND tbl_name = ? COLLATE NOCASE",
				array( $table_name )
			)->fetchAll( PDO::FETCH_COLUMN );
			foreach ( $triggers as $trigger ) {
				$this->driver->execute_sqlite_query(
					sprintf( 'DROP TRIGGER %s', $connection->quote_identifier( $trigger ) )
				);
			}

			$alter_list = array();
			foreach ( $repaired_columns as $column ) {
				// Preserve numeric and string types so comparisons apply the same
				// SQLite conversion as when the defaults were stored.
				$quoted_column = $connection->quote_identifier( $column['name'] );
				$this->driver->execute_sqlite_query(
					sprintf(
						'UPDATE %s SET %s = ? WHERE %s = %s',
						$connection->quote_identifier( $table_name ),
						$quoted_column,
						$quoted_column,
						$column['is_string'] ? '?' : $column['old_default']
					),
					$column['is_string']
						? array( $column['zero_value'], $column['old_default'] )
						: array( $column['zero_value'] )
				);

				$alter_list[] = sprintf(
					'ALTER COLUMN `%s` SET DEFAULT %s',
					str_replace( '`', '``', $column['name'] ),
					$this->driver->quote( $column['zero_value'] )
				);
			}

			// ALTER TABLE stores the new defaults, and it recreates the table to apply them.
			try {
				$this->driver->query(
					sprintf( 'ALTER TABLE `%s` %s', str_replace( '`', '``', $table_name ), implode( ', ', $alter_list ) )
				);
			} catch ( WP_MySQL_On_SQLite_Exception $e ) {
				throw new WP_MySQL_On_SQLite_Exception(
					$this->driver,
					sprintf( 'Failed to recreate table "%s": %s', $table_name, $e->getMessage() ),
					$e->getCode(),
					$e
				);
			}
		}
	}
}
