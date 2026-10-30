<?php

/*
 * The SQLite driver uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 *
 * PDO uses camel case naming, enable non-snake case:
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
 */

/**
 * For backward compatibility with dependencies that use their own loader scripts
 * (e.g., WP CLI SQLite Command), ensure the PDO-based classes are loaded.
 */
require_once __DIR__ . '/class-wp-mysql-on-sqlite.php';
require_once __DIR__ . '/class-wp-mysql-on-sqlite-statement.php';

/**
 * Deprecated: A proxy of the WP_MySQL_On_SQLite class preserving the legacy API.
 *
 * This class temporarily preserves the legacy constructor and result API while
 * consumers transition to the PDO-based WP_MySQL_On_SQLite API.
 *
 * @deprecated 3.0.0 Use WP_MySQL_On_SQLite instead.
 */
class WP_SQLite_Driver {
	/**
	 * The emulated MySQL client library version.
	 *
	 * This is a mysqli-like property that is needed to avoid a PHP warning in
	 * the WordPress health info. The "WP_Debug_Data::get_wp_database()" method
	 * calls "$wpdb->dbh->client_info" - a mysqli-specific abstraction leak.
	 *
	 * @TODO: This should be fixed in WordPress core.
	 *
	 * See:
	 *   https://github.com/WordPress/wordpress-develop/blob/bcdca3f9925f1d3eca7b78d231837c0caf0c8c24/src/wp-admin/includes/class-wp-debug-data.php#L1579
	 *
	 * @var string
	 */
	public $client_info;

	/**
	 * The MySQL-on-SQLite driver instance.
	 *
	 * @var WP_MySQL_On_SQLite
	 */
	private $mysql_on_sqlite_driver;

	/**
	 * Statement returned for the last emulated query.
	 *
	 * @var WP_MySQL_On_SQLite_Statement|null
	 */
	private $last_statement;

	/**
	 * Results of the last emulated query.
	 *
	 * @var mixed
	 */
	private $last_result;

	/**
	 * Constructor.
	 *
	 * Set up an SQLite connection and the MySQL-on-SQLite driver.
	 *
	 * @param WP_SQLite_Connection $connection    A SQLite database connection.
	 * @param string               $database      The database name.
	 * @param int                  $mysql_version The emulated MySQL version as an integer.
	 *
	 * @throws WP_MySQL_On_SQLite_Exception When the driver initialization fails.
	 */
	public function __construct(
		WP_SQLite_Connection $connection,
		string $database,
		int $mysql_version = WP_MySQL_On_SQLite::DEFAULT_MYSQL_VERSION
	) {
		$this->mysql_on_sqlite_driver = new WP_MySQL_On_SQLite(
			sprintf( 'mysql-on-sqlite:dbname=%s', str_replace( ';', ';;', $database ) ),
			null,
			null,
			array(
				'mysql_version'       => $mysql_version,
				'sqlite_pdo'          => $connection->get_pdo(),
				'sqlite_journal_mode' => $connection->query( 'PRAGMA journal_mode' )->fetchColumn(),
			)
		);
		$this->client_info            = $this->mysql_on_sqlite_driver->client_info;

		$this->mysql_on_sqlite_driver->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
	}

	/**
	 * Get the SQLite connection instance.
	 *
	 * @return WP_SQLite_Connection
	 */
	public function get_connection(): WP_SQLite_Connection {
		return $this->mysql_on_sqlite_driver->get_connection();
	}

	/**
	 * Get the version of the SQLite engine.
	 *
	 * @return string SQLite engine version as a string.
	 */
	public function get_sqlite_version(): string {
		return $this->mysql_on_sqlite_driver->get_sqlite_version();
	}

	/**
	 * Get the SQLite driver version saved in the database.
	 *
	 * The saved driver version corresponds to the latest version of the SQLite
	 * driver that was used to initialize and configure the SQLite database.
	 *
	 * @return string       SQLite driver version as a string.
	 * @throws PDOException When the query execution fails.
	 */
	public function get_saved_driver_version(): string {
		return $this->mysql_on_sqlite_driver->get_saved_driver_version();
	}

	/**
	 * Check if a specific SQL mode is active.
	 *
	 * @param  string $mode The SQL mode to check.
	 * @return bool         True if the SQL mode is active, false otherwise.
	 */
	public function is_sql_mode_active( string $mode ): bool {
		return $this->mysql_on_sqlite_driver->is_sql_mode_active( $mode );
	}

	/**
	 * Get the last executed MySQL query.
	 *
	 * @return string|null
	 */
	public function get_last_mysql_query(): ?string {
		return $this->mysql_on_sqlite_driver->get_last_mysql_query();
	}

	/**
	 * Get SQLite queries executed for the last MySQL query.
	 *
	 * @return array{ sql: string, params: array }[]
	 */
	public function get_last_sqlite_queries(): array {
		return $this->mysql_on_sqlite_driver->get_last_sqlite_queries();
	}

	/**
	 * Get the auto-increment value generated for the last query.
	 *
	 * @return int|string
	 */
	public function get_insert_id() {
		$last_insert_id = $this->mysql_on_sqlite_driver->lastInsertId();
		if ( is_numeric( $last_insert_id ) ) {
			$last_insert_id = (int) $last_insert_id;
		}
		return $last_insert_id;
	}

	/**
	 * @param string $query              Full SQL statement string.
	 * @param int    $fetch_mode         PDO fetch mode. Default is PDO::FETCH_OBJ.
	 * @param array  ...$fetch_mode_args Additional fetch mode arguments.
	 *
	 * @return mixed Return value, depending on the query type.
	 *
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	public function query( string $query, $fetch_mode = PDO::FETCH_OBJ, ...$fetch_mode_args ) {
		$this->last_statement = null;
		$stmt                 = $this->mysql_on_sqlite_driver->query( $query, $fetch_mode, ...$fetch_mode_args );
		$this->last_statement = $stmt;

		if ( $stmt->columnCount() > 0 ) {
			$this->last_result = $stmt->fetchAll( $fetch_mode );
		} else {
			$this->last_result = $stmt->rowCount();
		}
		return $this->last_result;
	}

	/**
	 * Tokenize a MySQL query and initialize a parser.
	 *
	 * @param  string          $query The MySQL query to parse.
	 * @return WP_MySQL_Parser        A parser initialized for the MySQL query.
	 */
	public function create_parser( string $query ): WP_MySQL_Parser {
		return $this->mysql_on_sqlite_driver->create_parser( $query );
	}

	/**
	 * Get results of the last query.
	 *
	 * @return mixed
	 */
	public function get_query_results() {
		return $this->last_result;
	}

	/**
	 * Get return value of the last query() function call.
	 *
	 * @return mixed
	 */
	public function get_last_return_value() {
		return $this->last_result;
	}

	/**
	 * Get the number of columns returned by the last emulated query.
	 *
	 * @return int
	 */
	public function get_last_column_count(): int {
		return null === $this->last_statement ? 0 : $this->last_statement->columnCount();
	}

	/**
	 * Get column metadata for results of the last emulated query.
	 *
	 * @return array
	 */
	public function get_last_column_meta(): array {
		if ( null === $this->last_statement ) {
			return array();
		}

		$column_meta  = array();
		$column_count = $this->last_statement->columnCount();
		for ( $i = 0; $i < $column_count; $i++ ) {
			$column_meta[] = $this->last_statement->getColumnMeta( $i );
		}
		return $column_meta;
	}

	/**
	 * Execute a query in SQLite.
	 *
	 * @param string $sql   The query to execute.
	 * @param array $params The query parameters.
	 * @throws PDOException When the query execution fails.
	 * @return PDOStatement The PDO statement object.
	 */
	public function execute_sqlite_query( string $sql, array $params = array() ): PDOStatement {
		return $this->mysql_on_sqlite_driver->execute_sqlite_query( $sql, $params );
	}

	/**
	 * Begin a new transaction or nested transaction.
	 */
	public function beginTransaction(): void {
		$this->mysql_on_sqlite_driver->beginTransaction();
	}

	/**
	 * A temporary alias for backward compatibility.
	 *
	 * @see self::beginTransaction()
	 */
	public function begin_transaction(): void {
		$this->beginTransaction();
	}

	/**
	 * Commit the current transaction or nested transaction.
	 */
	public function commit(): void {
		$this->mysql_on_sqlite_driver->commit();
	}

	/**
	 * Rollback the current transaction or nested transaction.
	 */
	public function rollback(): void {
		$this->mysql_on_sqlite_driver->rollback();
	}
}
