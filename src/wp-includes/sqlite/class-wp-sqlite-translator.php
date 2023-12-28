<?php
/**
 * The queries translator.
 *
 * @package wp-sqlite-integration
 * @see https://github.com/phpmyadmin/sql-parser
 */

/**
 * The queries translator class.
 */
class WP_SQLite_Translator {

	const SQLITE_BUSY   = 5;
	const SQLITE_LOCKED = 6;

	const DATA_TYPES_CACHE_TABLE = '_mysql_data_types_cache';

	const CREATE_DATA_TYPES_CACHE_TABLE = 'CREATE TABLE IF NOT EXISTS _mysql_data_types_cache (
		`table` TEXT NOT NULL,
		`column_or_index` TEXT NOT NULL,
		`mysql_type` TEXT NOT NULL,
		PRIMARY KEY(`table`, `column_or_index`)
	);';

	/**
	 * We use the ASCII SUB character to escape LIKE literal _ and %
	 */
	const LIKE_ESCAPE_CHAR = "\x1a";

	/**
	 * Class variable to reference to the PDO instance.
	 *
	 * @access private
	 *
	 * @var PDO object
	 */
	private $pdo;

	/**
	 * The database version.
	 *
	 * This is used here to avoid PHP warnings in the health screen.
	 *
	 * @var string
	 */
	public $client_info = '';

	/**
	 * How to translate field types from MySQL to SQLite.
	 *
	 * @var array
	 */
	private $field_types_translation = array(
		'bit'                => 'integer',
		'bool'               => 'integer',
		'boolean'            => 'integer',
		'tinyint'            => 'integer',
		'smallint'           => 'integer',
		'mediumint'          => 'integer',
		'int'                => 'integer',
		'integer'            => 'integer',
		'bigint'             => 'integer',
		'float'              => 'real',
		'double'             => 'real',
		'decimal'            => 'real',
		'dec'                => 'real',
		'numeric'            => 'real',
		'fixed'              => 'real',
		'date'               => 'text',
		'datetime'           => 'text',
		'timestamp'          => 'text',
		'time'               => 'text',
		'year'               => 'text',
		'char'               => 'text',
		'varchar'            => 'text',
		'binary'             => 'integer',
		'varbinary'          => 'blob',
		'tinyblob'           => 'blob',
		'tinytext'           => 'text',
		'blob'               => 'blob',
		'text'               => 'text',
		'mediumblob'         => 'blob',
		'mediumtext'         => 'text',
		'longblob'           => 'blob',
		'longtext'           => 'text',
		'geomcollection'     => 'text',
		'geometrycollection' => 'text',
	);

	/**
	 * The MySQL to SQLite date formats translation.
	 *
	 * Maps MySQL formats to SQLite strftime() formats.
	 *
	 * For MySQL formats, see:
	 * * https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-format
	 *
	 * For SQLite formats, see:
	 * * https://www.sqlite.org/lang_datefunc.html
	 * * https://strftime.org/
	 *
	 * @var array
	 */
	private $mysql_date_format_to_sqlite_strftime = array(
		'%a' => '%D',
		'%b' => '%M',
		'%c' => '%n',
		'%D' => '%jS',
		'%d' => '%d',
		'%e' => '%j',
		'%H' => '%H',
		'%h' => '%h',
		'%I' => '%h',
		'%i' => '%M',
		'%j' => '%z',
		'%k' => '%G',
		'%l' => '%g',
		'%M' => '%F',
		'%m' => '%m',
		'%p' => '%A',
		'%r' => '%h:%i:%s %A',
		'%S' => '%s',
		'%s' => '%s',
		'%T' => '%H:%i:%s',
		'%U' => '%W',
		'%u' => '%W',
		'%V' => '%W',
		'%v' => '%W',
		'%W' => '%l',
		'%w' => '%w',
		'%X' => '%Y',
		'%x' => '%o',
		'%Y' => '%Y',
		'%y' => '%y',
	);

	/**
	 * Number of rows found by the last SELECT query.
	 *
	 * @var int
	 */
	private $last_select_found_rows;

	/**
	 * Number of rows found by the last SQL_CALC_FOUND_ROW query.
	 *
	 * @var int integer
	 */
	private $last_sql_calc_found_rows = null;

	/**
	 * The query rewriter.
	 *
	 * @var WP_SQLite_Query_Rewriter
	 */
	private $rewriter;

	/**
	 * Last executed MySQL query.
	 *
	 * @var string
	 */
	public $mysql_query;

	/**
	 * A list of executed SQLite queries.
	 *
	 * @var array
	 */
	public $executed_sqlite_queries = array();

	/**
	 * The affected table name.
	 *
	 * @var array
	 */
	private $table_name = array();

	/**
	 * The type of the executed query (SELECT, INSERT, etc).
	 *
	 * @var array
	 */
	private $query_type = array();

	/**
	 * The columns to insert.
	 *
	 * @var array
	 */
	private $insert_columns = array();

	/**
	 * Class variable to store the result of the query.
	 *
	 * @access private
	 *
	 * @var array reference to the PHP object
	 */
	private $results = null;

	/**
	 * Class variable to check if there is an error.
	 *
	 * @var boolean
	 */
	public $is_error = false;

	/**
	 * Class variable to store the file name and function to cause error.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $errors;

	/**
	 * Class variable to store the error messages.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $error_messages = array();

	/**
	 * Class variable to store the affected row id.
	 *
	 * @var int integer
	 * @access private
	 */
	private $last_insert_id;

	/**
	 * Class variable to store the number of rows affected.
	 *
	 * @var int integer
	 */
	private $affected_rows;

	/**
	 * Class variable to store the queried column info.
	 *
	 * @var array
	 */
	private $column_data;

	/**
	 * Variable to emulate MySQL affected row.
	 *
	 * @var integer
	 */
	private $num_rows;

	/**
	 * Return value from query().
	 *
	 * Each query has its own return value.
	 *
	 * @var mixed
	 */
	private $return_value;

	/**
	 * Variable to keep track of nested transactions level.
	 *
	 * @var int
	 */
	private $transaction_level = 0;

	/**
	 * Value returned by the last exec().
	 *
	 * @var mixed
	 */
	private $last_exec_returned;

	/**
	 * The PDO fetch mode passed to query().
	 *
	 * @var mixed
	 */
	private $pdo_fetch_mode;

	/**
	 * The last reserved keyword seen in an SQL query.
	 *
	 * @var mixed
	 */
	private $last_reserved_keyword;

	/**
	 * True if a VACUUM operation should be done on shutdown,
	 * to handle OPTIMIZE TABLE and similar operations.
	 *
	 * @var bool
	 */
	private $vacuum_requested = false;

	/**
	 * True if the present query is metadata
	 *
	 * @var bool
	 */
	private $is_information_schema_query = false;

	/**
	 * True if a GROUP BY clause is detected.
	 *
	 * @var bool
	 */
	private $has_group_by = false;

	/**
	 * 0 if no LIKE is in progress, otherwise counts nested parentheses.
	 *
	 * @todo A generic stack of expression would scale better. There's already a call_stack in WP_SQLite_Query_Rewriter.
	 * @var int
	 */
	private $like_expression_nesting = 0;

	/**
	 * 0 if no LIKE is in progress, otherwise counts nested parentheses.
	 *
	 * @var int
	 */
	private $like_escape_count = 0;

	/**
	 * Associative array with list of system (non-WordPress) tables.
	 *
	 * @var array  [tablename => tablename]
	 */
	private $sqlite_system_tables = array();

	/**
	 * The last error message from SQLite.
	 *
	 * @var string
	 */
	private $last_sqlite_error;

	/**
	 * Constructor.
	 *
	 * Create PDO object, set user defined functions and initialize other settings.
	 * Don't use parent::__construct() because this class does not only returns
	 * PDO instance but many others jobs.
	 *
	 * @param PDO $pdo The PDO object.
	 */
	public function __construct( $pdo = null ) {
		if ( ! $pdo ) {
			if ( ! is_file( FQDB ) ) {
				$this->prepare_directory();
			}

			$locked      = false;
			$status      = 0;
			$err_message = '';
			do {
				try {
					$options = array(
						PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
						PDO::ATTR_STRINGIFY_FETCHES => true,
						PDO::ATTR_TIMEOUT           => 5,
					);

					$dsn = 'sqlite:' . FQDB;
					$pdo = new PDO( $dsn, null, null, $options ); // phpcs:ignore WordPress.DB.RestrictedClasses
				} catch ( PDOException $ex ) {
					$status = $ex->getCode();
					if ( self::SQLITE_BUSY === $status || self::SQLITE_LOCKED === $status ) {
						$locked = true;
					} else {
						$err_message = $ex->getMessage();
					}
				}
			} while ( $locked );

			if ( $status > 0 ) {
				$message                = sprintf(
					'<p>%s</p><p>%s</p><p>%s</p>',
					'Database initialization error!',
					"Code: $status",
					"Error Message: $err_message"
				);
				$this->is_error         = true;
				$this->error_messages[] = $message;
				return;
			}
		}

		new WP_SQLite_PDO_User_Defined_Functions( $pdo );

		// MySQL data comes across stringified by default.
		$pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		$pdo->query( WP_SQLite_Translator::CREATE_DATA_TYPES_CACHE_TABLE );

		/*
		 * A list of system tables lets us emulate information_schema
		 * queries without returning extra tables.
		 */
		$this->sqlite_system_tables ['sqlite_sequence']              = 'sqlite_sequence';
		$this->sqlite_system_tables [ self::DATA_TYPES_CACHE_TABLE ] = self::DATA_TYPES_CACHE_TABLE;

		$this->pdo = $pdo;

		// Fixes a warning in the site-health screen.
		$this->client_info = SQLite3::version()['versionString'];

		register_shutdown_function( array( $this, '__destruct' ) );

		// WordPress happens to use no foreign keys.
		$statement = $this->pdo->query( 'PRAGMA foreign_keys' );
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		if ( $statement->fetchColumn( 0 ) == '0' ) {
			$this->pdo->query( 'PRAGMA foreign_keys = ON' );
		}
		$this->pdo->query( 'PRAGMA encoding="UTF-8";' );
	}

	/**
	 * Destructor
	 *
	 * If SQLITE_MEM_DEBUG constant is defined, append information about
	 * memory usage into database/mem_debug.txt.
	 *
	 * This definition is changed since version 1.7.
	 */
	public function __destruct() {
		if ( defined( 'SQLITE_MEM_DEBUG' ) && SQLITE_MEM_DEBUG ) {
			$max = ini_get( 'memory_limit' );
			if ( is_null( $max ) ) {
				$message = sprintf(
					'[%s] Memory_limit is not set in php.ini file.',
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] )
				);
				error_log( $message );
				return;
			}
			if ( stripos( $max, 'M' ) !== false ) {
				$max = (int) $max * MB_IN_BYTES;
			}
			$peak = memory_get_peak_usage( true );
			$used = round( (int) $peak / (int) $max * 100, 2 );
			if ( $used > 90 ) {
				$message = sprintf(
					"[%s] Memory peak usage warning: %s %% used. (max: %sM, now: %sM)\n",
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ),
					$used,
					$max,
					$peak
				);
				error_log( $message );
			}
		}
	}

	/**
	 * Get the PDO object.
	 *
	 * @return PDO
	 */
	public function get_pdo() {
		return $this->pdo;
	}

	/**
	 * Method to return inserted row id.
	 */
	public function get_insert_id() {
		return $this->last_insert_id;
	}

	/**
	 * Method to return the number of rows affected.
	 */
	public function get_affected_rows() {
		return $this->affected_rows;
	}

	/**
	 * This method makes database directory and .htaccess file.
	 *
	 * It is executed only once when the installation begins.
	 */
	private function prepare_directory() {
		global $wpdb;
		$u = umask( 0000 );
		if ( ! is_dir( FQDBDIR ) ) {
			if ( ! @mkdir( FQDBDIR, 0704, true ) ) {
				umask( $u );
				wp_die( 'Unable to create the required directory! Please check your server settings.', 'Error!' );
			}
		}
		if ( ! is_writable( FQDBDIR ) ) {
			umask( $u );
			$message = 'Unable to create a file in the directory! Please check your server settings.';
			wp_die( $message, 'Error!' );
		}
		if ( ! is_file( FQDBDIR . '.htaccess' ) ) {
			$fh = fopen( FQDBDIR . '.htaccess', 'w' );
			if ( ! $fh ) {
				umask( $u );
				echo 'Unable to create a file in the directory! Please check your server settings.';

				return false;
			}
			fwrite( $fh, 'DENY FROM ALL' );
			fclose( $fh );
		}
		if ( ! is_file( FQDBDIR . 'index.php' ) ) {
			$fh = fopen( FQDBDIR . 'index.php', 'w' );
			if ( ! $fh ) {
				umask( $u );
				echo 'Unable to create a file in the directory! Please check your server settings.';

				return false;
			}
			fwrite( $fh, '<?php // Silence is gold. ?>' );
			fclose( $fh );
		}
		umask( $u );

		return true;
	}

	/**
	 * Method to execute query().
	 *
	 * Divide the query types into seven different ones. That is to say:
	 *
	 * 1. SELECT SQL_CALC_FOUND_ROWS
	 * 2. INSERT
	 * 3. CREATE TABLE(INDEX)
	 * 4. ALTER TABLE
	 * 5. SHOW VARIABLES
	 * 6. DROP INDEX
	 * 7. THE OTHERS
	 *
	 * #1 is just a tricky play. See the private function handle_sql_count() in query.class.php.
	 * From #2 through #5 call different functions respectively.
	 * #6 call the ALTER TABLE query.
	 * #7 is a normal process: sequentially call prepare_query() and execute_query().
	 *
	 * #1 process has been changed since version 1.5.1.
	 *
	 * @param string $statement          Full SQL statement string.
	 * @param int    $mode               Not used.
	 * @param array  ...$fetch_mode_args Not used.
	 *
	 * @see PDO::query()
	 *
	 * @throws Exception    If the query could not run.
	 * @throws PDOException If the translated query could not run.
	 *
	 * @return mixed according to the query type
	 */
	public function query( $statement, $mode = PDO::FETCH_OBJ, ...$fetch_mode_args ) { // phpcs:ignore WordPress.DB.RestrictedClasses
		$this->flush();
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters queries before they are translated and run.
			 *
			 * Return a non-null value to cause query() to return early with that result.
			 * Use this filter to intercept queries that don't work correctly in SQLite.
			 *
			 * From within the filter you can do
			 *  function filter_sql ($result, $translator, $statement, $mode, $fetch_mode_args) {
			 *     if ( intercepting this query  ) {
			 *       return $translator->execute_sqlite_query( $statement );
			 *     }
			 *     return $result;
			 *   }
			 *
			 * @param null|array $result Default null to continue with the query.
			 * @param object     $translator The translator object. You can call $translator->execute_sqlite_query().
			 * @param string     $statement The statement passed.
			 * @param int        $mode Fetch mode: PDO::FETCH_OBJ, PDO::FETCH_CLASS, etc.
			 * @param array      $fetch_mode_args Variable arguments passed to query.
			 *
			 * @returns null|array Null to proceed, or an array containing a resultset.
			 * @since 2.1.0
			 */
			$pre = apply_filters( 'pre_query_sqlite_db', null, $this, $statement, $mode, $fetch_mode_args );
			if ( null !== $pre ) {
				return $pre;
			}
		}
		$this->pdo_fetch_mode = $mode;
		$this->mysql_query    = $statement;
		if (
			preg_match( '/^\s*START TRANSACTION/i', $statement )
			|| preg_match( '/^\s*BEGIN/i', $statement )
		) {
			return $this->begin_transaction();
		}
		if ( preg_match( '/^\s*COMMIT/i', $statement ) ) {
			return $this->commit();
		}
		if ( preg_match( '/^\s*ROLLBACK/i', $statement ) ) {
			return $this->rollback();
		}

		try {
			// Perform all the queries in a nested transaction.
			$this->begin_transaction();

			do {
				$error = null;
				try {
					$this->execute_mysql_query(
						$statement
					);
				} catch ( PDOException $error ) {
					if ( $error->getCode() !== self::SQLITE_BUSY ) {
						throw $error;
					}
				}
			} while ( $error );

			/**
			 * Notifies that a query has been translated and executed.
			 *
			 * @param string $query          The executed SQL query.
			 * @param string $query_type     The type of the SQL query (e.g. SELECT, INSERT, UPDATE, DELETE).
			 * @param string $table_name     The name of the table affected by the SQL query.
			 * @param array  $insert_columns The columns affected by the INSERT query (if applicable).
			 * @param int    $last_insert_id The ID of the last inserted row (if applicable).
			 * @param int    $affected_rows  The number of affected rows (if applicable).
			 *
			 * @since 0.1.0
			 */
			do_action(
				'sqlite_translated_query_executed',
				$this->mysql_query,
				$this->query_type,
				$this->table_name,
				$this->insert_columns,
				$this->last_insert_id,
				$this->affected_rows
			);

			// Commit the nested transaction.
			$this->commit();

			return $this->return_value;
		} catch ( Exception $err ) {
			// Rollback the nested transaction.
			$this->rollback();
			if ( defined( 'PDO_DEBUG' ) && PDO_DEBUG === true ) {
				throw $err;
			}
			return $this->handle_error( $err );
		}
	}

	/**
	 * Method to return the queried column names.
	 *
	 * These data are meaningless for SQLite. So they are dummy emulating
	 * MySQL columns data.
	 *
	 * @return array|null of the object
	 */
	public function get_columns() {
		if ( ! empty( $this->results ) ) {
			$primary_key = array(
				'meta_id',
				'comment_ID',
				'link_ID',
				'option_id',
				'blog_id',
				'option_name',
				'ID',
				'term_id',
				'object_id',
				'term_taxonomy_id',
				'umeta_id',
				'id',
			);
			$unique_key  = array( 'term_id', 'taxonomy', 'slug' );
			$data        = array(
				'name'         => '', // Column name.
				'table'        => '', // Table name.
				'max_length'   => 0,  // Max length of the column.
				'not_null'     => 1,  // 1 if not null.
				'primary_key'  => 0,  // 1 if column has primary key.
				'unique_key'   => 0,  // 1 if column has unique key.
				'multiple_key' => 0,  // 1 if column doesn't have unique key.
				'numeric'      => 0,  // 1 if column has numeric value.
				'blob'         => 0,  // 1 if column is blob.
				'type'         => '', // Type of the column.
				'int'          => 0,  // 1 if column is int integer.
				'zerofill'     => 0,  // 1 if column is zero-filled.
			);
			$table_name  = '';
			$sql         = '';
			$query       = end( $this->executed_sqlite_queries );
			if ( $query ) {
				$sql = $query['sql'];
			}
			if ( preg_match( '/\s*FROM\s*(.*)?\s*/i', $sql, $match ) ) {
				$table_name = trim( $match[1] );
			}
			foreach ( $this->results[0] as $key => $value ) {
				$data['name']  = $key;
				$data['table'] = $table_name;
				if ( in_array( $key, $primary_key, true ) ) {
					$data['primary_key'] = 1;
				} elseif ( in_array( $key, $unique_key, true ) ) {
					$data['unique_key'] = 1;
				} else {
					$data['multiple_key'] = 1;
				}
				$this->column_data[] = json_decode( json_encode( $data ) );

				// Reset data for next iteration.
				$data['name']         = '';
				$data['table']        = '';
				$data['primary_key']  = 0;
				$data['unique_key']   = 0;
				$data['multiple_key'] = 0;
			}

			return $this->column_data;
		}
		return null;
	}

	/**
	 * Method to return the queried result data.
	 *
	 * @return mixed
	 */
	public function get_query_results() {
		return $this->results;
	}

	/**
	 * Method to return the number of rows from the queried result.
	 */
	public function get_num_rows() {
		return $this->num_rows;
	}

	/**
	 * Method to return the queried results according to the query types.
	 *
	 * @return mixed
	 */
	public function get_return_value() {
		return $this->return_value;
	}

	/**
	 * Executes a MySQL query in SQLite.
	 *
	 * @param string $query The query.
	 *
	 * @throws Exception If the query is not supported.
	 */
	private function execute_mysql_query( $query ) {
		$tokens           = ( new WP_SQLite_Lexer( $query ) )->tokens;
		$this->rewriter   = new WP_SQLite_Query_Rewriter( $tokens );
		$this->query_type = $this->rewriter->peek()->value;

		switch ( $this->query_type ) {
			case 'ALTER':
				$this->execute_alter();
				break;

			case 'CREATE':
				$this->execute_create();
				break;

			case 'SELECT':
				$this->execute_select();
				break;

			case 'INSERT':
			case 'REPLACE':
				$this->execute_insert_or_replace();
				break;

			case 'UPDATE':
				$this->execute_update();
				break;

			case 'DELETE':
				$this->execute_delete();
				break;

			case 'CALL':
			case 'SET':
				/*
				 * It would be lovely to support at least SET autocommit,
				 * but I don't think that is even possible with SQLite.
				 */
				$this->results = 0;
				break;

			case 'TRUNCATE':
				$this->execute_truncate();
				break;

			case 'BEGIN':
			case 'START TRANSACTION':
				$this->results = $this->begin_transaction();
				break;

			case 'COMMIT':
				$this->results = $this->commit();
				break;

			case 'ROLLBACK':
				$this->results = $this->rollback();
				break;

			case 'DROP':
				$this->execute_drop();
				break;

			case 'SHOW':
				$this->execute_show();
				break;

			case 'DESCRIBE':
				$this->execute_describe();
				break;

			case 'CHECK':
				$this->execute_check();
				break;

			case 'OPTIMIZE':
			case 'REPAIR':
			case 'ANALYZE':
				$this->execute_optimize( $this->query_type );
				break;

			default:
				throw new Exception( 'Unknown query type: ' . $this->query_type );
		}
	}

	/**
	 * Executes a MySQL CREATE TABLE query in SQLite.
	 *
	 * @throws Exception If the query is not supported.
	 */
	private function execute_create_table() {
		$table = $this->parse_create_table();

		$definitions = array();
		foreach ( $table->fields as $field ) {
			/*
			 * Do not include the inline PRIMARY KEY definition
			 * if there is more than one primary key.
			 */
			if ( $field->primary_key && count( $table->primary_key ) > 1 ) {
				$field->primary_key = false;
			}
			if ( $field->auto_increment && count( $table->primary_key ) > 1 ) {
				throw new Exception( 'Cannot combine AUTOINCREMENT and multiple primary keys in SQLite' );
			}

			$definitions[] = $this->make_sqlite_field_definition( $field );
			$this->update_data_type_cache(
				$table->name,
				$field->name,
				$field->mysql_data_type
			);
		}

		if ( count( $table->primary_key ) > 1 ) {
			$definitions[] = 'PRIMARY KEY ("' . implode( '", "', $table->primary_key ) . '")';
		}

		$create_query = (
			$table->create_table .
			'"' . $table->name . '" (' . "\n" .
			implode( ",\n", $definitions ) .
			')'
		);
		$this->execute_sqlite_query( $create_query );
		$this->results      = $this->last_exec_returned;
		$this->return_value = $this->results;

		foreach ( $table->constraints as $constraint ) {
			$index_type = $this->mysql_index_type_to_sqlite_type( $constraint->value );
			$unique     = '';
			if ( 'UNIQUE INDEX' === $index_type ) {
				$unique = 'UNIQUE ';
			}
			$index_name = "{$table->name}__{$constraint->name}";
			$this->execute_sqlite_query(
				"CREATE $unique INDEX \"$index_name\" ON \"{$table->name}\" (\"" . implode( '", "', $constraint->columns ) . '")'
			);
			$this->update_data_type_cache(
				$table->name,
				$index_name,
				$constraint->value
			);
		}
	}

	/**
	 * Parse the CREATE TABLE query.
	 *
	 * @return stdClass Structured data.
	 */
	private function parse_create_table() {
		$this->rewriter       = clone $this->rewriter;
		$result               = new stdClass();
		$result->create_table = null;
		$result->name         = null;
		$result->fields       = array();
		$result->constraints  = array();
		$result->primary_key  = array();

		/*
		 * The query starts with CREATE TABLE [IF NOT EXISTS].
		 * Consume everything until the table name.
		 */
		while ( true ) {
			$token = $this->rewriter->consume();
			if ( ! $token ) {
				break;
			}
			// The table name is the first non-keyword token.
			if ( WP_SQLite_Token::TYPE_KEYWORD !== $token->type ) {
				// Store the table name for later.
				$result->name = $this->normalize_column_name( $token->value );

				// Drop the table name and store the CREATE TABLE command.
				$this->rewriter->drop_last();
				$result->create_table = $this->rewriter->get_updated_query();
				break;
			}
		}

		/*
		 * Move to the opening parenthesis:
		 * CREATE TABLE wp_options (
		 *   ^ here.
		 */
		$this->rewriter->skip(
			array(
				'type'  => WP_SQLite_Token::TYPE_OPERATOR,
				'value' => '(',
			)
		);

		/*
		 * We're in the table definition now.
		 * Read everything until the closing parenthesis.
		 */
		$declarations_depth = $this->rewriter->depth;
		do {
			/*
			 * We want to capture a rewritten line of the query.
			 * Let's clear any data we might have captured so far.
			 */
			$this->rewriter->replace_all( array() );

			/*
			 * Decide how to parse the current line. We expect either:
			 *
			 * Field definition, e.g.:
			 *     `my_field` varchar(255) NOT NULL DEFAULT 'foo'
			 * Constraint definition, e.g.:
			 *      PRIMARY KEY (`my_field`)
			 *
			 * Lexer does not seem to reliably understand whether the
			 * first token is a field name or a reserved keyword, so
			 * instead we'll check whether the second non-whitespace
			 * token is a data type.
			 */
			$second_token = $this->rewriter->peek_nth( 2 );

			if ( $second_token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_DATA_TYPE
			) ) {
				$result->fields[] = $this->parse_mysql_create_table_field();
			} else {
				$result->constraints[] = $this->parse_mysql_create_table_constraint();
			}

			/*
			 * If we're back at the initial depth, we're done.
			 * Also, MySQL supports a trailing comma – if we see one,
			 * then we're also done.
			 */
		} while (
			$token
			&& $this->rewriter->depth >= $declarations_depth
			&& $this->rewriter->peek()->token !== ')'
		);

		// Merge all the definitions of the primary key.
		foreach ( $result->constraints as $k => $constraint ) {
			if ( 'PRIMARY' === $constraint->value ) {
				$result->primary_key = array_merge(
					$result->primary_key,
					$constraint->columns
				);
				unset( $result->constraints[ $k ] );
			}
		}

		// Inline primary key in a field definition.
		foreach ( $result->fields as $k => $field ) {
			if ( $field->primary_key ) {
				$result->primary_key[] = $field->name;
			} elseif ( in_array( $field->name, $result->primary_key, true ) ) {
				$field->primary_key = true;
			}
		}

		// Remove duplicates.
		$result->primary_key = array_unique( $result->primary_key );

		return $result;
	}

	/**
	 * Parses a CREATE TABLE query.
	 *
	 * @throws Exception If the query is not supported.
	 *
	 * @return stdClass
	 */
	private function parse_mysql_create_table_field() {
		$result                   = new stdClass();
		$result->name             = '';
		$result->sqlite_data_type = '';
		$result->not_null         = false;
		$result->default          = null;
		$result->auto_increment   = false;
		$result->primary_key      = false;

		$field_name_token = $this->rewriter->skip(); // Field name.
		$this->rewriter->add( new WP_SQLite_Token( "\n", WP_SQLite_Token::TYPE_WHITESPACE ) );
		$result->name = $this->normalize_column_name( $field_name_token->value );

		$definition_depth = $this->rewriter->depth;

		$skip_mysql_data_type_parts = $this->skip_mysql_data_type();
		$result->sqlite_data_type   = $skip_mysql_data_type_parts[0];
		$result->mysql_data_type    = $skip_mysql_data_type_parts[1];

		// Look for the NOT NULL and AUTO_INCREMENT flags.
		while ( true ) {
			$token = $this->rewriter->skip();
			if ( ! $token ) {
				break;
			}
			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED,
				array( 'NOT NULL' )
			) ) {
				$result->not_null = true;
				continue;
			}

			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED,
				array( 'PRIMARY KEY' )
			) ) {
				$result->primary_key = true;
				continue;
			}

			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				null,
				array( 'AUTO_INCREMENT' )
			) ) {
				$result->primary_key    = true;
				$result->auto_increment = true;
				continue;
			}

			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_FUNCTION,
				array( 'DEFAULT' )
			) ) {
				$result->default = $this->rewriter->consume()->token;
				continue;
			}

			if ( $this->is_create_table_field_terminator( $token, $definition_depth ) ) {
				$this->rewriter->add( $token );
				break;
			}
		}

		return $result;
	}

	/**
	 * Translate field definitions.
	 *
	 * @param stdClass $field Field definition.
	 *
	 * @return string
	 */
	private function make_sqlite_field_definition( $field ) {
		$definition = '"' . $field->name . '" ' . $field->sqlite_data_type;
		if ( $field->auto_increment ) {
			$definition .= ' PRIMARY KEY AUTOINCREMENT';
		} elseif ( $field->primary_key ) {
			$definition .= ' PRIMARY KEY ';
		}
		if ( $field->not_null ) {
			$definition .= ' NOT NULL';
		}
		if ( null !== $field->default ) {
			$definition .= ' DEFAULT ' . $field->default;
		}

		/*
		 * In MySQL, text fields are case-insensitive by default.
		 * COLLATE NOCASE emulates the same behavior in SQLite.
		 */
		if ( 'text' === $field->sqlite_data_type ) {
			$definition .= ' COLLATE NOCASE';
		}
		return $definition;
	}

	/**
	 * Parses a CREATE TABLE constraint.
	 *
	 * @throws Exception If the query is not supported.
	 *
	 * @return stdClass
	 */
	private function parse_mysql_create_table_constraint() {
		$result          = new stdClass();
		$result->name    = '';
		$result->value   = '';
		$result->columns = array();

		$definition_depth = $this->rewriter->depth;
		$constraint       = $this->rewriter->peek();
		if ( ! $constraint->matches( WP_SQLite_Token::TYPE_KEYWORD ) ) {
			/*
			 * Not a constraint declaration, but we're not finished
			 * with the table declaration yet.
			 */
			throw new Exception( 'Unexpected token in MySQL query: ' . $this->rewriter->peek()->value );
		}

		$result->value = $this->normalize_mysql_index_type( $constraint->value );
		if ( $result->value ) {
			$this->rewriter->skip(); // Constraint type.
			if ( 'PRIMARY' !== $result->value ) {
				$result->name = $this->rewriter->skip()->value;
			}

			$constraint_depth = $this->rewriter->depth;
			$this->rewriter->skip(); // `(`
			do {
				$result->columns[] = $this->normalize_column_name( $this->rewriter->skip()->value );
				$paren_maybe       = $this->rewriter->peek();
				if ( $paren_maybe && '(' === $paren_maybe->token ) {
					$this->rewriter->skip();
					$this->rewriter->skip();
					$this->rewriter->skip();
				}
				$this->rewriter->skip(); // `,` or `)`
			} while ( $this->rewriter->depth > $constraint_depth );
		}

		do {
			$token = $this->rewriter->skip();
		} while ( ! $this->is_create_table_field_terminator( $token, $definition_depth ) );

		return $result;
	}

	/**
	 * Checks if the current token is the terminator of a CREATE TABLE field.
	 *
	 * @param WP_SQLite_Token $token            The current token.
	 * @param int             $definition_depth The initial depth.
	 * @param int|null        $current_depth    The current depth.
	 *
	 * @return bool
	 */
	private function is_create_table_field_terminator( $token, $definition_depth, $current_depth = null ) {
		if ( null === $current_depth ) {
			$current_depth = $this->rewriter->depth;
		}
		return (
			// Reached the end of the query.
			null === $token

			// The field-terminating ",".
			|| (
				$current_depth === $definition_depth &&
				WP_SQLite_Token::TYPE_OPERATOR === $token->type &&
				',' === $token->value
			)

			// The definitions-terminating ")".
			|| $current_depth === $definition_depth - 1

			// The query-terminating ";".
			|| (
				WP_SQLite_Token::TYPE_DELIMITER === $token->type &&
				';' === $token->value
			)
		);
	}

	/**
	 * Executes a DELETE statement.
	 *
	 * @throws Exception If the table could not be found.
	 */
	private function execute_delete() {
		$this->rewriter->consume(); // DELETE.

		// Process expressions and extract bound parameters.
		$params = array();
		while ( true ) {
			$token = $this->rewriter->peek();
			if ( ! $token ) {
				break;
			}

			$this->remember_last_reserved_keyword( $token );

			if (
				$this->extract_bound_parameter( $token, $params )
				|| $this->translate_expression( $token )
			) {
				continue;
			}

			$this->rewriter->consume();
		}
		$this->rewriter->consume_all();

		$updated_query = $this->rewriter->get_updated_query();

		// Perform DELETE-specific translations.

		// Naive rewriting of DELETE JOIN query.
		// @TODO: Actually rewrite the query instead of using a hardcoded workaround.
		if ( str_contains( $updated_query, ' JOIN ' ) ) {
			$table_prefix = isset( $GLOBALS['table_prefix'] ) ? $GLOBALS['table_prefix'] : 'wp_';
			$this->execute_sqlite_query(
				"DELETE FROM {$table_prefix}options WHERE option_id IN (SELECT MIN(option_id) FROM {$table_prefix}options GROUP BY option_name HAVING COUNT(*) > 1)"
			);
			$this->set_result_from_affected_rows();
			return;
		}

		$rewriter = new WP_SQLite_Query_Rewriter( $this->rewriter->output_tokens );

		$comma = $rewriter->peek(
			array(
				'type'  => WP_SQLite_Token::TYPE_OPERATOR,
				'value' => ',',
			)
		);
		$from  = $rewriter->peek(
			array(
				'type'  => WP_SQLite_Token::TYPE_KEYWORD,
				'value' => 'FROM',
			)
		);
		// The DELETE query targets a single table if there's no comma before the FROM.
		if ( ! $comma || ! $from || $comma->position >= $from->position ) {
			$this->execute_sqlite_query(
				$updated_query,
				$params
			);
			$this->set_result_from_affected_rows();
			return;
		}

		// The DELETE query targets multiple tables – rewrite it into a
		// SELECT to fetch the IDs of the rows to delete, then delete them
		// using a separate DELETE query.

		$this->table_name = $rewriter->skip()->value;
		$rewriter->add( new WP_SQLite_Token( 'SELECT', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ) );

		/*
		 * Get table name.
		 */
		$from  = $rewriter->peek(
			array(
				'type'  => WP_SQLite_Token::TYPE_KEYWORD,
				'value' => 'FROM',
			)
		);
		$index = array_search( $from, $rewriter->input_tokens, true );
		for ( $i = $index + 1; $i < $rewriter->max; $i++ ) {
			// Assume the table name is the first token after FROM.
			if ( ! $rewriter->input_tokens[ $i ]->is_semantically_void() ) {
				$this->table_name = $rewriter->input_tokens[ $i ]->value;
				break;
			}
		}
		if ( ! $this->table_name ) {
			throw new Exception( 'Could not find table name for dual delete query.' );
		}

		/*
		 * Now, let's figure out the primary key name.
		 * This assumes that all listed table names are the same.
		 */
		$q       = $this->execute_sqlite_query( 'SELECT l.name FROM pragma_table_info("' . $this->table_name . '") as l WHERE l.pk = 1;' );
		$pk_name = $q->fetch()['name'];

		/*
		 * Good, we can finally create the SELECT query.
		 * Let's rewrite DELETE a, b FROM ... to SELECT a.id, b.id FROM ...
		 */
		$alias_nb = 0;
		while ( true ) {
			$token = $rewriter->consume();
			if ( WP_SQLite_Token::TYPE_KEYWORD === $token->type && 'FROM' === $token->value ) {
				break;
			}

			/*
			 * Between DELETE and FROM we only expect commas and table aliases.
			 * If it's not a comma, it must be a table alias.
			 */
			if ( ',' !== $token->value ) {
				// Insert .id AS id_1 after the table alias.
				$rewriter->add_many(
					array(
						new WP_SQLite_Token( '.', WP_SQLite_Token::TYPE_OPERATOR, WP_SQLite_Token::FLAG_OPERATOR_SQL ),
						new WP_SQLite_Token( $pk_name, WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_KEY ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( 'AS', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( 'id_' . $alias_nb, WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_KEY ),
					)
				);
				++$alias_nb;
			}
		}
		$rewriter->consume_all();

		// Select the IDs to delete.
		$select = $rewriter->get_updated_query();
		$stmt   = $this->execute_sqlite_query( $select );
		$stmt->execute( $params );
		$rows          = $stmt->fetchAll();
		$ids_to_delete = array();
		foreach ( $rows as $id ) {
			$ids_to_delete[] = $id['id_0'];
			$ids_to_delete[] = $id['id_1'];
		}

		$query = (
		count( $ids_to_delete )
			? "DELETE FROM {$this->table_name} WHERE {$pk_name} IN (" . implode( ',', $ids_to_delete ) . ')'
			: "DELETE FROM {$this->table_name} WHERE 0=1"
		);
		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows(
			count( $ids_to_delete )
		);
	}

	/**
	 * Executes a SELECT statement.
	 */
	private function execute_select() {
		$this->rewriter->consume(); // SELECT.

		$params                  = array();
		$table_name              = null;
		$has_sql_calc_found_rows = false;

		// Consume and record the table name.
		while ( true ) {
			$token = $this->rewriter->peek();
			if ( ! $token ) {
				break;
			}

			$this->remember_last_reserved_keyword( $token );

			if ( ! $table_name ) {
				$this->table_name = $this->peek_table_name( $token );
				$table_name       = $this->peek_table_name( $token );
			}

			if ( $this->skip_sql_calc_found_rows( $token ) ) {
				$has_sql_calc_found_rows = true;
				continue;
			}

			if (
				$this->extract_bound_parameter( $token, $params )
				|| $this->translate_expression( $token )
			) {
				continue;
			}

			$this->rewriter->consume();
		}
		$this->rewriter->consume_all();

		$updated_query = $this->rewriter->get_updated_query();

		if ( $table_name && str_starts_with( strtolower( $table_name ), 'information_schema' ) ) {
			$this->is_information_schema_query = true;
			$updated_query                     = $this->get_information_schema_query( $updated_query );
			$params                            = array();
		} elseif (
			strpos( $updated_query, '@@SESSION.sql_mode' ) !== false
			|| strpos( $updated_query, 'CONVERT( ' ) !== false
		) {
			/*
			 * If the query contains a function that is not supported by SQLite,
			 * return a dummy select. This check must be done after the query
			 * has been rewritten to use parameters to avoid false positives
			 * on queries such as `SELECT * FROM table WHERE field='CONVERT('`.
			 */
			$updated_query = 'SELECT 1=0';
			$params        = array();
		} elseif ( $has_sql_calc_found_rows ) {
			// Emulate SQL_CALC_FOUND_ROWS for now.
			$query = $updated_query;
			// We make the data for next SELECT FOUND_ROWS() statement.
			$unlimited_query = preg_replace( '/\\bLIMIT\\s\d+(?:\s*,\s*\d+)?$/imsx', '', $query );
			$stmt            = $this->execute_sqlite_query( $unlimited_query );
			$stmt->execute( $params );
			$this->last_sql_calc_found_rows = count( $stmt->fetchAll() );
		}

		// Emulate FOUND_ROWS() by counting the rows in the result set.
		if ( strpos( $updated_query, 'FOUND_ROWS(' ) !== false ) {
			$last_found_rows = ( $this->last_sql_calc_found_rows ? $this->last_sql_calc_found_rows : 0 ) . '';
			$updated_query   = "SELECT {$last_found_rows} AS `FOUND_ROWS()`";
		}

		$stmt = $this->execute_sqlite_query( $updated_query, $params );
		if ( $this->is_information_schema_query ) {
			$this->set_results_from_fetched_data(
				$this->strip_sqlite_system_tables(
					$stmt->fetchAll( $this->pdo_fetch_mode )
				)
			);
		} else {
			$this->set_results_from_fetched_data(
				$stmt->fetchAll( $this->pdo_fetch_mode )
			);
		}
	}

	/**
	 * Executes a TRUNCATE statement.
	 */
	private function execute_truncate() {
		$this->rewriter->skip(); // TRUNCATE.
		if ( 'TABLE' === strtoupper( $this->rewriter->peek()->value ) ) {
			$this->rewriter->skip(); // TABLE.
		}
		$this->rewriter->add( new WP_SQLite_Token( 'DELETE', WP_SQLite_Token::TYPE_KEYWORD ) );
		$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
		$this->rewriter->add( new WP_SQLite_Token( 'FROM', WP_SQLite_Token::TYPE_KEYWORD ) );
		$this->rewriter->consume_all();
		$this->execute_sqlite_query( $this->rewriter->get_updated_query() );
		$this->results      = true;
		$this->return_value = true;
	}

	/**
	 * Executes a DESCRIBE statement.
	 *
	 * @throws PDOException When the table is not found.
	 */
	private function execute_describe() {
		$this->rewriter->skip();
		$this->table_name = $this->rewriter->consume()->value;
		$stmt             = $this->execute_sqlite_query(
			"SELECT
				`name` as `Field`,
				(
					CASE `notnull`
					WHEN 0 THEN 'YES'
					WHEN 1 THEN 'NO'
					END
				) as `Null`,
				IFNULL(
					d.`mysql_type`,
					(
						CASE `type`
						WHEN 'INTEGER' THEN 'int'
						WHEN 'TEXT' THEN 'text'
						WHEN 'BLOB' THEN 'blob'
						WHEN 'REAL' THEN 'real'
						ELSE `type`
						END
					)
				) as `Type`,
				TRIM(`dflt_value`, \"'\") as `Default`,
				'' as Extra,
				(
					CASE `pk`
					WHEN 0 THEN ''
					ELSE 'PRI'
					END
				) as `Key`
				FROM pragma_table_info(\"$this->table_name\") p
				LEFT JOIN " . self::DATA_TYPES_CACHE_TABLE . " d
				ON d.`table` = \"$this->table_name\"
				AND d.`column_or_index` = p.`name`
				;
			"
		);
		$this->set_results_from_fetched_data(
			$stmt->fetchAll( $this->pdo_fetch_mode )
		);
		if ( ! $this->results ) {
			throw new PDOException( 'Table not found' );
		}
	}

	/**
	 * Executes an UPDATE statement.
	 */
	private function execute_update() {
		$this->rewriter->consume(); // Update.

		$params = array();
		while ( true ) {
			$token = $this->rewriter->peek();
			if ( ! $token ) {
				break;
			}

			// Record the table name.
			if (
				! $this->table_name &&
				! $token->matches(
					WP_SQLite_Token::TYPE_KEYWORD,
					WP_SQLite_Token::FLAG_KEYWORD_RESERVED
				)
			) {
				$this->table_name = $token->value;
			}

			$this->remember_last_reserved_keyword( $token );

			if (
				$this->extract_bound_parameter( $token, $params )
				|| $this->translate_expression( $token )
			) {
				continue;
			}

			$this->rewriter->consume();
		}
		$this->rewriter->consume_all();

		$updated_query = $this->rewriter->get_updated_query();
		$this->execute_sqlite_query( $updated_query, $params );
		$this->set_result_from_affected_rows();
	}

	/**
	 * Executes a INSERT or REPLACE statement.
	 */
	private function execute_insert_or_replace() {
		$params                  = array();
		$is_in_duplicate_section = false;

		$this->rewriter->consume(); // INSERT or REPLACE.

		// Consume the query type.
		if ( 'IGNORE' === $this->rewriter->peek()->value ) {
			$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
			$this->rewriter->add( new WP_SQLite_Token( 'OR', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ) );
			$this->rewriter->consume(); // IGNORE.
		}

		// Consume and record the table name.
		$this->insert_columns = array();
		$this->rewriter->consume(); // INTO.
		$this->table_name = $this->rewriter->consume()->value; // Table name.

		/*
		 * A list of columns is given if the opening parenthesis
		 * is earlier than the VALUES keyword.
		 */
		$paren  = $this->rewriter->peek(
			array(
				'type'  => WP_SQLite_Token::TYPE_OPERATOR,
				'value' => '(',
			)
		);
		$values = $this->rewriter->peek(
			array(
				'type'  => WP_SQLite_Token::TYPE_KEYWORD,
				'value' => 'VALUES',
			)
		);
		if ( $paren && $values && $paren->position <= $values->position ) {
			$this->rewriter->consume(
				array(
					'type'  => WP_SQLite_Token::TYPE_OPERATOR,
					'value' => '(',
				)
			);
			while ( true ) {
				$token = $this->rewriter->consume();
				if ( $token->matches( WP_SQLite_Token::TYPE_OPERATOR, null, array( ')' ) ) ) {
					break;
				}
				if ( ! $token->matches( WP_SQLite_Token::TYPE_OPERATOR ) ) {
					$this->insert_columns[] = $token->value;
				}
			}
		}

		while ( true ) {
			$token = $this->rewriter->peek();
			if ( ! $token ) {
				break;
			}

			$this->remember_last_reserved_keyword( $token );

			if (
				( $is_in_duplicate_section && $this->translate_values_function( $token ) )
				|| $this->extract_bound_parameter( $token, $params )
				|| $this->translate_expression( $token )
			) {
				continue;
			}

			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				null,
				array( 'DUPLICATE' )
			)
			) {
				$is_in_duplicate_section = true;
				$this->translate_on_duplicate_key( $this->table_name );
				continue;
			}

			$this->rewriter->consume();
		}

		$this->rewriter->consume_all();

		$updated_query = $this->rewriter->get_updated_query();
		$this->execute_sqlite_query( $updated_query, $params );
		$this->set_result_from_affected_rows();
		$this->last_insert_id = $this->pdo->lastInsertId();
		if ( is_numeric( $this->last_insert_id ) ) {
			$this->last_insert_id = (int) $this->last_insert_id;
		}
		$this->last_insert_id = apply_filters( 'sqlite_last_insert_id', $this->last_insert_id, $this->table_name );
	}

	/**
	 * Preprocesses a string literal.
	 *
	 * @param string $value The string literal.
	 *
	 * @return string The preprocessed string literal.
	 */
	private function preprocess_string_literal( $value ) {
		/*
		 * The code below converts the date format to one preferred by SQLite.
		 *
		 * MySQL accepts ISO 8601 date strings:        'YYYY-MM-DDTHH:MM:SSZ'
		 * SQLite prefers a slightly different format: 'YYYY-MM-DD HH:MM:SS'
		 *
		 * SQLite date and time functions can understand the ISO 8601 notation, but
		 * lookups don't. To keep the lookups working, we need to store all dates
		 * in UTC without the "T" and "Z" characters.
		 *
		 * Caveat: It will adjust every string that matches the pattern, not just dates.
		 *
		 * In theory, we could only adjust semantic dates, e.g. the data inserted
		 * to a date column or compared against a date column.
		 *
		 * In practice, this is hard because dates are just text – SQLite has no separate
		 * datetime field. We'd need to cache the MySQL data type from the original
		 * CREATE TABLE query and then keep refreshing the cache after each ALTER TABLE query.
		 *
		 * That's a lot of complexity that's perhaps not worth it. Let's just convert
		 * everything for now. The regexp assumes "Z" is always at the end of the string,
		 * which is true in the unit test suite, but there could also be a timezone offset
		 * like "+00:00" or "+01:00". We could add support for that later if needed.
		 */
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z$/', $value, $matches ) ) {
			$value = $matches[1] . ' ' . $matches[2];
		}

		/*
		 * Mimic MySQL's behavior and truncate invalid dates.
		 *
		 * "2020-12-41 14:15:27" becomes "0000-00-00 00:00:00"
		 *
		 * WARNING: We have no idea whether the truncated value should
		 * be treated as a date in the first place.
		 * In SQLite dates are just strings. This could be a perfectly
		 * valid string that just happens to contain a date-like value.
		 *
		 * At the same time, WordPress seems to rely on MySQL's behavior
		 * and even tests for it in Tests_Post_wpInsertPost::test_insert_empty_post_date.
		 * Let's truncate the dates for now.
		 *
		 * In the future, let's update WordPress to do its own date validation
		 * and stop relying on this MySQL feature,
		 */
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})$/', $value, $matches ) ) {
			/*
			 * Calling strtotime("0000-00-00 00:00:00") in 32-bit environments triggers
			 * an "out of integer range" warning – let's avoid that call for the popular
			 * case of "zero" dates.
			 */
			if ( '0000-00-00 00:00:00' !== $value && false === strtotime( $value ) ) {
				$value = '0000-00-00 00:00:00';
			}
		}
		return $value;
	}

	/**
	 * Preprocesses a LIKE expression.
	 *
	 * @param WP_SQLite_Token $token The token to preprocess.
	 * @return string
	 */
	private function preprocess_like_expr( &$token ) {
		/*
		 * This code handles escaped wildcards in LIKE clauses.
		 * If we are within a LIKE experession, we look for \_ and \%, the
		 * escaped LIKE wildcards, the ones where we want a literal, not a
		 * wildcard match. We change the \ escape for an ASCII \x1a (SUB) character,
		 * so the \ characters won't get munged.
		 * These \_ and \% escape sequences are in the token name, because
		 * the lexer has already done stripcslashes on the value.
		 */
		if ( $this->like_expression_nesting > 0 ) {
			/* Remove the quotes around the name. */
			$unescaped_value = mb_substr( $token->token, 1, -1, 'UTF-8' );
			if ( str_contains( $unescaped_value, '\_' ) || str_contains( $unescaped_value, '\%' ) ) {
				++$this->like_escape_count;
				return str_replace(
					array( '\_', '\%' ),
					array( self::LIKE_ESCAPE_CHAR . '_', self::LIKE_ESCAPE_CHAR . '%' ),
					$unescaped_value
				);
			}
		}
		return $token->value;
	}
	/**
	 * Translate CAST() function when we want to cast to BINARY.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_cast_as_binary( $token ) {
		if ( ! $token->matches(
			WP_SQLite_Token::TYPE_KEYWORD,
			WP_SQLite_Token::FLAG_KEYWORD_DATA_TYPE,
			array( 'BINARY' )
		)
		) {
			return false;
		}

		$call_parent = $this->rewriter->last_call_stack_element();
		if (
			! $call_parent
			|| 'CAST' !== $call_parent['function']
		) {
			return false;
		}

		// Rewrite AS BINARY to AS BLOB inside CAST() calls.
		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( 'BLOB', $token->type, $token->flags ) );
		return true;
	}

	/**
	 * Translates an expression in an SQL statement if the token is the start of an expression.
	 *
	 * @param WP_SQLite_Token $token The first token of an expression.
	 *
	 * @return bool True if the expression was translated successfully, false otherwise.
	 */
	private function translate_expression( $token ) {
		return (
			$this->skip_from_dual( $token )
			|| $this->translate_concat_function( $token )
			|| $this->translate_concat_comma_to_pipes( $token )
			|| $this->translate_function_aliases( $token )
			|| $this->translate_cast_as_binary( $token )
			|| $this->translate_date_add_sub( $token )
			|| $this->translate_date_format( $token )
			|| $this->translate_interval( $token )
			|| $this->translate_regexp_functions( $token )
			|| $this->capture_group_by( $token )
			|| $this->translate_ungrouped_having( $token )
			|| $this->translate_like_escape( $token )
		);
	}

	/**
	 * Skips the `FROM DUAL` clause in the SQL statement.
	 *
	 * @param WP_SQLite_Token $token The token to check for the `FROM DUAL` clause.
	 *
	 * @return bool True if the `FROM DUAL` clause was skipped, false otherwise.
	 */
	private function skip_from_dual( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED,
				array( 'FROM' )
			)
		) {
			return false;
		}
		$from_table = $this->rewriter->peek_nth( 2 )->value;
		if ( 'DUAL' !== strtoupper( $from_table ) ) {
			return false;
		}

		// FROM DUAL is a MySQLism that means "no tables".
		$this->rewriter->skip();
		$this->rewriter->skip();
		return true;
	}

	/**
	 * Peeks at the table name in the SQL statement.
	 *
	 * @param WP_SQLite_Token $token The token to check for the table name.
	 *
	 * @return string|bool The table name if it was found, false otherwise.
	 */
	private function peek_table_name( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED,
				array( 'FROM' )
			)
		) {
			return false;
		}
		$table_name = $this->rewriter->peek_nth( 2 )->value;
		if ( 'dual' === strtolower( $table_name ) ) {
			return false;
		}
		return $table_name;
	}

	/**
	 * Skips the `SQL_CALC_FOUND_ROWS` keyword in the SQL statement.
	 *
	 * @param WP_SQLite_Token $token The token to check for the `SQL_CALC_FOUND_ROWS` keyword.
	 *
	 * @return bool True if the `SQL_CALC_FOUND_ROWS` keyword was skipped, false otherwise.
	 */
	private function skip_sql_calc_found_rows( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				null,
				array( 'SQL_CALC_FOUND_ROWS' )
			)
		) {
			return false;
		}
		$this->rewriter->skip();
		return true;
	}

	/**
	 * Remembers the last reserved keyword encountered in the SQL statement.
	 *
	 * @param WP_SQLite_Token $token The token to check for the reserved keyword.
	 */
	private function remember_last_reserved_keyword( $token ) {
		if (
			$token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED
			)
		) {
			$this->last_reserved_keyword = $token->value;
		}
	}

	/**
	 * Extracts the bound parameter from the given token and adds it to the `$params` array.
	 *
	 * @param WP_SQLite_Token $token The token to extract the bound parameter from.
	 * @param array           $params An array of parameters to be bound to the SQL statement.
	 *
	 * @return bool True if the parameter was extracted successfully, false otherwise.
	 */
	private function extract_bound_parameter( $token, &$params ) {
		if ( ! $token->matches(
			WP_SQLite_Token::TYPE_STRING,
			WP_SQLite_Token::FLAG_STRING_SINGLE_QUOTES
		)
			|| 'AS' === $this->last_reserved_keyword
		) {
			return false;
		}

		$param_name            = ':param' . count( $params );
		$value                 = $this->preprocess_like_expr( $token );
		$value                 = $this->preprocess_string_literal( $value );
		$params[ $param_name ] = $value;
		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( $param_name, WP_SQLite_Token::TYPE_STRING, WP_SQLite_Token::FLAG_STRING_SINGLE_QUOTES ) );
		$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
		return true;
	}

	/**
	 * Translate CONCAT() function.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_concat_function( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_FUNCTION,
				array( 'CONCAT' )
			)
		) {
			return false;
		}

		/*
		 * Skip the CONCAT function but leave the parentheses.
		 * There is another code block below that replaces the
		 * , operators between the CONCAT arguments with ||.
		 */
		$this->rewriter->skip();
		return true;
	}

	/**
	 * Translate CONCAT() function arguments.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_concat_comma_to_pipes( $token ) {
		if ( ! $token->matches(
			WP_SQLite_Token::TYPE_OPERATOR,
			WP_SQLite_Token::FLAG_OPERATOR_SQL,
			array( ',' )
		)
		) {
			return false;
		}

		$call_parent = $this->rewriter->last_call_stack_element();
		if (
			! $call_parent
			|| 'CONCAT' !== $call_parent['function']
		) {
			return false;
		}

		// Rewrite commas to || in CONCAT() calls.
		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( '||', WP_SQLite_Token::TYPE_OPERATOR ) );
		return true;
	}

	/**
	 * Translate DATE_ADD() and DATE_SUB() functions.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_date_add_sub( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_FUNCTION,
				array( 'DATE_ADD', 'DATE_SUB' )
			)
		) {
			return false;
		}

		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( 'DATETIME', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_FUNCTION ) );
		return true;
	}

	/**
	 * Convert function aliases.
	 *
	 * @param object $token The current token.
	 *
	 * @return bool False when no match, true when this function consumes the token.
	 *
	 * @todo LENGTH and CHAR_LENGTH aren't always the same in MySQL for utf8 characters. They are in SQLite.
	 */
	private function translate_function_aliases( $token ) {
		if ( ! $token->matches(
			WP_SQLite_Token::TYPE_KEYWORD,
			WP_SQLite_Token::FLAG_KEYWORD_FUNCTION,
			array( 'SUBSTRING', 'CHAR_LENGTH' )
		)
		) {
			return false;
		}
		switch ( $token->value ) {
			case 'SUBSTRING':
				$name = 'SUBSTR';
				break;
			case 'CHAR_LENGTH':
				$name = 'LENGTH';
				break;
			default:
				$name = $token->value;
				break;
		}
		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( $name, $token->type, $token->flags ) );

		return true;
	}

	/**
	 * Translate VALUES() function.
	 *
	 * @param WP_SQLite_Token $token                   The token to translate.
	 *
	 * @return bool
	 */
	private function translate_values_function( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_FUNCTION,
				array( 'VALUES' )
			)
		) {
			return false;
		}

		/*
		 * Rewrite:  VALUES(`option_name`)
		 * to:       excluded.option_name
		 */
		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( 'excluded', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_KEY ) );
		$this->rewriter->add( new WP_SQLite_Token( '.', WP_SQLite_Token::TYPE_OPERATOR ) );

		$this->rewriter->skip(); // Skip the opening `(`.
		// Consume the column name.
		$this->rewriter->consume(
			array(
				'type'  => WP_SQLite_Token::TYPE_OPERATOR,
				'value' => ')',
			)
		);
		// Drop the consumed ')' token.
		$this->rewriter->drop_last();
		return true;
	}

	/**
	 * Translate DATE_FORMAT() function.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @throws Exception If the token is not a DATE_FORMAT() function.
	 *
	 * @return bool
	 */
	private function translate_date_format( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_FUNCTION,
				array( 'DATE_FORMAT' )
			)
		) {
			return false;
		}

		// Rewrite DATE_FORMAT( `post_date`, '%Y-%m-%d' ) to STRFTIME( '%Y-%m-%d', `post_date` ).

		// Skip the DATE_FORMAT function name.
		$this->rewriter->skip();
		// Skip the opening `(`.
		$this->rewriter->skip();

		// Skip the first argument so we can read the second one.
		$first_arg = $this->rewriter->skip_and_return_all(
			array(
				'type'  => WP_SQLite_Token::TYPE_OPERATOR,
				'value' => ',',
			)
		);

		// Make sure we actually found the comma.
		$comma = array_pop( $first_arg );
		if ( ',' !== $comma->value ) {
			throw new Exception( 'Could not parse the DATE_FORMAT() call' );
		}

		// Skip the second argument but capture the token.
		$format     = $this->rewriter->skip()->value;
		$new_format = strtr( $format, $this->mysql_date_format_to_sqlite_strftime );
		if ( ! $new_format ) {
			throw new Exception( "Could not translate a DATE_FORMAT() format to STRFTIME format ($format)" );
		}

		/*
		 * MySQL supports comparing strings and floats, e.g.
		 *
		 * > SELECT '00.42' = 0.4200
		 * 1
		 *
		 * SQLite does not support that. At the same time,
		 * WordPress likes to filter dates by comparing numeric
		 * outputs of DATE_FORMAT() to floats, e.g.:
		 *
		 *     -- Filter by hour and minutes
		 *     DATE_FORMAT(
		 *         STR_TO_DATE('2014-10-21 00:42:29', '%Y-%m-%d %H:%i:%s'),
		 *         '%H.%i'
		 *     ) = 0.4200;
		 *
		 * Let's cast the STRFTIME() output to a float if
		 * the date format is typically used for string
		 * to float comparisons.
		 *
		 * In the future, let's update WordPress to avoid comparing
		 * strings and floats.
		 */
		$cast_to_float = '%H.%i' === $format;
		if ( $cast_to_float ) {
			$this->rewriter->add( new WP_SQLite_Token( 'CAST', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_FUNCTION ) );
			$this->rewriter->add( new WP_SQLite_Token( '(', WP_SQLite_Token::TYPE_OPERATOR ) );
		}

		$this->rewriter->add( new WP_SQLite_Token( 'STRFTIME', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_FUNCTION ) );
		$this->rewriter->add( new WP_SQLite_Token( '(', WP_SQLite_Token::TYPE_OPERATOR ) );
		$this->rewriter->add( new WP_SQLite_Token( "'$new_format'", WP_SQLite_Token::TYPE_STRING ) );
		$this->rewriter->add( new WP_SQLite_Token( ',', WP_SQLite_Token::TYPE_OPERATOR ) );

		// Add the buffered tokens back to the stream.
		$this->rewriter->add_many( $first_arg );

		// Consume the closing ')'.
		$this->rewriter->consume(
			array(
				'type'  => WP_SQLite_Token::TYPE_OPERATOR,
				'value' => ')',
			)
		);

		if ( $cast_to_float ) {
			$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
			$this->rewriter->add( new WP_SQLite_Token( 'as', WP_SQLite_Token::TYPE_OPERATOR ) );
			$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
			$this->rewriter->add( new WP_SQLite_Token( 'FLOAT', WP_SQLite_Token::TYPE_KEYWORD ) );
			$this->rewriter->add( new WP_SQLite_Token( ')', WP_SQLite_Token::TYPE_OPERATOR ) );
		}

		return true;
	}

	/**
	 * Translate INTERVAL keyword with DATE_ADD() and DATE_SUB().
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_interval( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				null,
				array( 'INTERVAL' )
			)
		) {
			return false;
		}
		// Skip the INTERVAL keyword from the output stream.
		$this->rewriter->skip();

		$num  = $this->rewriter->skip()->value;
		$unit = $this->rewriter->skip()->value;

		/*
			* In MySQL, we say:
			*     DATE_ADD(d, INTERVAL 1 YEAR)
			*     DATE_SUB(d, INTERVAL 1 YEAR)
			*
			* In SQLite, we say:
			*     DATE(d, '+1 YEAR')
			*     DATE(d, '-1 YEAR')
			*
			* The sign of the interval is determined by the date_* function
			* that is closest in the call stack.
			*
			* Let's find it.
			*/
		$interval_op = '+'; // Default to adding.
		for ( $j = count( $this->rewriter->call_stack ) - 1; $j >= 0; $j-- ) {
			$call = $this->rewriter->call_stack[ $j ];
			if ( 'DATE_ADD' === $call['function'] ) {
				$interval_op = '+';
				break;
			}
			if ( 'DATE_SUB' === $call['function'] ) {
				$interval_op = '-';
				break;
			}
		}

		$this->rewriter->add( new WP_SQLite_Token( "'{$interval_op}$num $unit'", WP_SQLite_Token::TYPE_STRING ) );
		return true;
	}

	/**
	 * Translate REGEXP and RLIKE keywords.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_regexp_functions( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				null,
				array( 'REGEXP', 'RLIKE' )
			)
		) {
			return false;
		}
		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( 'REGEXP', WP_SQLite_Token::TYPE_KEYWORD ) );

		$next = $this->rewriter->peek();

		/*
		 * If the query says REGEXP BINARY, the comparison is byte-by-byte
		 * and letter casing matters – lowercase and uppercase letters are
		 * represented using different byte codes.
		 *
		 * The REGEXP function can't be easily made to accept two
		 * parameters, so we'll have to use a hack to get around this.
		 *
		 * If the first character of the pattern is a null byte, we'll
		 * remove it and make the comparison case-sensitive. This should
		 * be reasonably safe since PHP does not allow null bytes in
		 * regular expressions anyway.
		 */
		if ( $next->matches( WP_SQLite_Token::TYPE_KEYWORD, null, array( 'BINARY' ) ) ) {
			// Skip the "BINARY" keyword.
			$this->rewriter->skip();
			// Prepend a null byte to the pattern.
			$this->rewriter->add_many(
				array(
					new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
					new WP_SQLite_Token( 'char', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_FUNCTION ),
					new WP_SQLite_Token( '(', WP_SQLite_Token::TYPE_OPERATOR ),
					new WP_SQLite_Token( '0', WP_SQLite_Token::TYPE_NUMBER ),
					new WP_SQLite_Token( ')', WP_SQLite_Token::TYPE_OPERATOR ),
					new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
					new WP_SQLite_Token( '||', WP_SQLite_Token::TYPE_OPERATOR ),
					new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
				)
			);
		}
		return true;
	}

	/**
	 * Detect GROUP BY.
	 *
	 * @todo edgecase Fails on a statement with GROUP BY nested in an outer HAVING without GROUP BY.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function capture_group_by( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED,
				array( 'GROUP' )
			)
		) {
			return false;
		}
		$next = $this->rewriter->peek_nth( 2 )->value;
		if ( 'BY' !== strtoupper( $next ) ) {
			return false;
		}

		$this->has_group_by = true;

		return false;
	}

	/**
	 * Translate WHERE something HAVING something to WHERE something AND something.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_ungrouped_having( $token ) {
		if (
			! $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED,
				array( 'HAVING' )
			)
		) {
			return false;
		}
		if ( $this->has_group_by ) {
			return false;
		}
		$this->rewriter->skip();
		$this->rewriter->add( new WP_SQLite_Token( 'AND', WP_SQLite_Token::TYPE_KEYWORD ) );

		return true;
	}

	/**
	 * Rewrite LIKE '\_whatever' as LIKE '\_whatever' ESCAPE '\' .
	 *
	 * We look for keyword LIKE. On seeing it we set a flag.
	 * If the flag is set, we emit ESCAPE '\' before the next keyword.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_like_escape( $token ) {

		if ( 0 === $this->like_expression_nesting ) {
			$is_like = $token->matches( WP_SQLite_Token::TYPE_KEYWORD, null, array( 'LIKE' ) );
			/* is this the LIKE keyword? If so set the flag. */
			if ( $is_like ) {
				$this->like_expression_nesting = 1;
			}
		} else {
			/* open parenthesis during LIKE parameter, count it. */
			if ( $token->matches( WP_SQLite_Token::TYPE_OPERATOR, null, array( '(' ) ) ) {
				++$this->like_expression_nesting;

				return false;
			}

			/* close parenthesis matching open parenthesis during LIKE parameter, count it. */
			if ( $this->like_expression_nesting > 1 && $token->matches( WP_SQLite_Token::TYPE_OPERATOR, null, array( ')' ) ) ) {
				--$this->like_expression_nesting;

				return false;
			}

			/* a keyword, a commo, a semicolon, the end of the statement, or a close parenthesis */
			$is_like_finished = $token->matches( WP_SQLite_Token::TYPE_KEYWORD )
					|| $token->matches( WP_SQLite_Token::TYPE_DELIMITER, null, array( ';' ) ) || ( WP_SQLite_Token::TYPE_DELIMITER === $token->type && null === $token->value )
					|| $token->matches( WP_SQLite_Token::TYPE_OPERATOR, null, array( ')', ',' ) );

			if ( $is_like_finished ) {
				/*
				 * Here we have another keyword encountered with the LIKE in progress.
				 * Emit the ESCAPE clause.
				 */
				if ( $this->like_escape_count > 0 ) {
					/* If we need the ESCAPE clause emit it. */
					$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_DELIMITER ) );
					$this->rewriter->add( new WP_SQLite_Token( 'ESCAPE', WP_SQLite_Token::TYPE_KEYWORD ) );
					$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_DELIMITER ) );
					$this->rewriter->add( new WP_SQLite_Token( "'" . self::LIKE_ESCAPE_CHAR . "'", WP_SQLite_Token::TYPE_STRING ) );
					$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_DELIMITER ) );
				}
				$this->like_escape_count       = 0;
				$this->like_expression_nesting = 0;
			}
		}

		return false;
	}

	/**
	 * Rewrite a query from the MySQL information_schema.
	 *
	 * @param string $updated_query The query to rewrite.
	 *
	 * @return string The query for use by SQLite
	 */
	private function get_information_schema_query( $updated_query ) {
		// @TODO: Actually rewrite the columns.
		$normalized_query = preg_replace( '/\s+/', ' ', strtolower( $updated_query ) );
		if ( str_contains( $normalized_query, 'bytes' ) ) {
			// Count rows per table.
			$tables =
				$this->execute_sqlite_query( "SELECT name as `table_name` FROM sqlite_master WHERE type='table' ORDER BY name" )->fetchAll();
			$tables = $this->strip_sqlite_system_tables( $tables );

			$rows = '(CASE ';
			foreach ( $tables as $table ) {
				$table_name = $table['table_name'];
				$count      = $this->execute_sqlite_query( "SELECT COUNT(1) as `count` FROM $table_name" )->fetch();
				$rows      .= " WHEN name = '$table_name' THEN {$count['count']} ";
			}
			$rows         .= 'ELSE 0 END) ';
			$updated_query =
				"SELECT name as `table_name`, $rows as `rows`, 0 as `bytes` FROM sqlite_master WHERE type='table' ORDER BY name";
		} elseif ( str_contains( $normalized_query, 'count(*)' ) && ! str_contains( $normalized_query, 'table_name =' ) ) {
			// @TODO This is a guess that the caller wants a count of tables.
			$list = array();
			foreach ( $this->sqlite_system_tables as $system_table => $name ) {
				$list [] = "'" . $system_table . "'";
			}
			$list          = implode( ', ', $list );
			$sql           = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT IN ($list)";
			$table_count   = $this->execute_sqlite_query( $sql )->fetch();
			$updated_query = 'SELECT ' . $table_count[0] . ' AS num';

			$this->is_information_schema_query = false;
		} else {
			$updated_query =
				"SELECT name as `table_name`, 'myisam' as `engine`, 0 as `data_length`, 0 as `index_length`, 0 as `data_free` FROM sqlite_master WHERE type='table' ORDER BY name";
		}

		return $updated_query;
	}

	/**
	 * Remove system table rows from resultsets of information_schema tables.
	 *
	 * @param array $tables The result set.
	 *
	 * @return array The filtered result set.
	 */
	private function strip_sqlite_system_tables( $tables ) {
		return array_values(
			array_filter(
				$tables,
				function ( $table ) {
					$table_name = false;
					if ( is_array( $table ) ) {
						if ( isset( $table['Name'] ) ) {
							$table_name = $table['Name'];
						} elseif ( isset( $table['table_name'] ) ) {
							$table_name = $table['table_name'];
						}
					} elseif ( is_object( $table ) ) {
						$table_name = property_exists( $table, 'Name' )
							? $table->Name // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							: $table->table_name;
					}

					return $table_name && ! array_key_exists( $table_name, $this->sqlite_system_tables );
				},
				ARRAY_FILTER_USE_BOTH
			)
		);
	}

	/**
	 * Translate the ON DUPLICATE KEY UPDATE clause.
	 *
	 * @param string $table_name The table name.
	 *
	 * @return void
	 */
	private function translate_on_duplicate_key( $table_name ) {
		/*
		 * Rewrite:
		 *     ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`)
		 * to:
		 *     ON CONFLICT(ip) DO UPDATE SET option_name = excluded.option_name
		 */

		// Find the conflicting column.
		$pk_columns = array();
		foreach ( $this->get_primary_keys( $table_name ) as $row ) {
			$pk_columns[] = $row['name'];
		}

		$unique_columns = array();
		foreach ( $this->get_keys( $table_name, true ) as $row ) {
			foreach ( $row['columns'] as $column ) {
				$unique_columns[] = $column['name'];
			}
		}

		// Guess the conflict column based on the query details.

		// 1. Listed INSERT columns that are either PK or UNIQUE.
		$conflict_columns = array_intersect(
			$this->insert_columns,
			array_merge( $pk_columns, $unique_columns )
		);
		// 2. Composite Primary Key columns.
		if ( ! $conflict_columns && count( $pk_columns ) > 1 ) {
			$conflict_columns = $pk_columns;
		}
		// 3. The first unique column.
		if ( ! $conflict_columns && count( $unique_columns ) > 0 ) {
			$conflict_columns = array( $unique_columns[0] );
		}
		// 4. Regular Primary Key column.
		if ( ! $conflict_columns ) {
			$conflict_columns = $pk_columns;
		}

		/*
		 * If we still haven't found any conflict column, we
		 * can't rewrite the ON DUPLICATE KEY statement.
		 * Let's default to a regular INSERT to mimic MySQL
		 * which would still insert the row without throwing
		 * an error.
		 */
		if ( ! $conflict_columns ) {
			// Drop the consumed "ON".
			$this->rewriter->drop_last();
			// Skip over "DUPLICATE", "KEY", and "UPDATE".
			$this->rewriter->skip();
			$this->rewriter->skip();
			$this->rewriter->skip();
			while ( $this->rewriter->skip() ) {
				// Skip over the rest of the query.
			}
			return;
		}

		// Skip over "DUPLICATE", "KEY", and "UPDATE".
		$this->rewriter->skip();
		$this->rewriter->skip();
		$this->rewriter->skip();

		// Add the CONFLICT keyword.
		$this->rewriter->add( new WP_SQLite_Token( 'CONFLICT', WP_SQLite_Token::TYPE_KEYWORD ) );

		// Add "( <columns list> ) DO UPDATE SET ".
		$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
		$this->rewriter->add( new WP_SQLite_Token( '(', WP_SQLite_Token::TYPE_OPERATOR ) );

		$max = count( $conflict_columns );
		foreach ( $conflict_columns as $i => $conflict_column ) {
			$this->rewriter->add( new WP_SQLite_Token( '"' . $conflict_column . '"', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_KEY ) );
			if ( $i !== $max - 1 ) {
				$this->rewriter->add( new WP_SQLite_Token( ',', WP_SQLite_Token::TYPE_OPERATOR ) );
				$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
			}
		}
		$this->rewriter->add( new WP_SQLite_Token( ')', WP_SQLite_Token::TYPE_OPERATOR ) );
		$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
		$this->rewriter->add( new WP_SQLite_Token( 'DO', WP_SQLite_Token::TYPE_KEYWORD ) );
		$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
		$this->rewriter->add( new WP_SQLite_Token( 'UPDATE', WP_SQLite_Token::TYPE_KEYWORD ) );
		$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
		$this->rewriter->add( new WP_SQLite_Token( 'SET', WP_SQLite_Token::TYPE_KEYWORD ) );
		$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
	}

	/**
	 * Get the primary keys for a table.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return array
	 */
	private function get_primary_keys( $table_name ) {
		$stmt = $this->execute_sqlite_query( 'SELECT * FROM pragma_table_info(:table_name) as l WHERE l.pk > 0;' );
		$stmt->execute( array( 'table_name' => $table_name ) );
		return $stmt->fetchAll();
	}

	/**
	 * Get the keys for a table.
	 *
	 * @param string $table_name Table name.
	 * @param bool   $only_unique Only return unique keys.
	 *
	 * @return array
	 */
	private function get_keys( $table_name, $only_unique = false ) {
		$query   = $this->execute_sqlite_query( 'SELECT * FROM pragma_index_list("' . $table_name . '") as l;' );
		$indices = $query->fetchAll();
		$results = array();
		foreach ( $indices as $index ) {
			if ( ! $only_unique || '1' === $index['unique'] ) {
				$query     = $this->execute_sqlite_query( 'SELECT * FROM pragma_index_info("' . $index['name'] . '") as l;' );
				$results[] = array(
					'index'   => $index,
					'columns' => $query->fetchAll(),
				);
			}
		}
		return $results;
	}

	/**
	 * Get the CREATE TABLE statement for a table.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return string
	 */
	private function get_sqlite_create_table( $table_name ) {
		$stmt = $this->execute_sqlite_query( 'SELECT sql FROM sqlite_master WHERE type="table" AND name=:table' );
		$stmt->execute( array( ':table' => $table_name ) );
		$create_table = '';
		foreach ( $stmt->fetchAll() as $row ) {
			$create_table .= $row['sql'] . "\n";
		}
		return $create_table;
	}

	/**
	 * Translate ALTER query.
	 *
	 * @throws Exception If the subject is not 'table', or we're performing an unknown operation.
	 */
	private function execute_alter() {
		$this->rewriter->consume();
		$subject = strtolower( $this->rewriter->consume()->token );
		if ( 'table' !== $subject ) {
			throw new Exception( 'Unknown subject: ' . $subject );
		}

		$this->table_name = $this->normalize_column_name( $this->rewriter->consume()->token );
		do {
			/*
			 * This loop may be executed multiple times if there are multiple operations in the ALTER query.
			 * Let's reset the initial state on each pass.
			 */
			$this->rewriter->replace_all(
				array(
					new WP_SQLite_Token( 'ALTER', WP_SQLite_Token::TYPE_KEYWORD ),
					new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
					new WP_SQLite_Token( 'TABLE', WP_SQLite_Token::TYPE_KEYWORD ),
					new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
					new WP_SQLite_Token( $this->table_name, WP_SQLite_Token::TYPE_KEYWORD ),
				)
			);
			$op_type          = strtoupper( $this->rewriter->consume()->token );
			$op_subject       = strtoupper( $this->rewriter->consume()->token );
			$mysql_index_type = $this->normalize_mysql_index_type( $op_subject );
			$is_index_op      = (bool) $mysql_index_type;

			if ( 'ADD' === $op_type && 'COLUMN' === $op_subject ) {
				$column_name = $this->rewriter->consume()->value;

				$skip_mysql_data_type_parts = $this->skip_mysql_data_type();
				$sqlite_data_type           = $skip_mysql_data_type_parts[0];
				$mysql_data_type            = $skip_mysql_data_type_parts[1];

				$this->rewriter->add(
					new WP_SQLite_Token(
						$sqlite_data_type,
						WP_SQLite_Token::TYPE_KEYWORD,
						WP_SQLite_Token::FLAG_KEYWORD_DATA_TYPE
					)
				);
				$this->update_data_type_cache(
					$this->table_name,
					$column_name,
					$mysql_data_type
				);
			} elseif ( 'DROP' === $op_type && 'COLUMN' === $op_subject ) {
				$this->rewriter->consume_all();
			} elseif ( 'CHANGE' === $op_type && 'COLUMN' === $op_subject ) {
				// Parse the new column definition.
				$from_name        = $this->normalize_column_name( $this->rewriter->skip()->token );
				$new_field        = $this->parse_mysql_create_table_field();
				$alter_terminator = end( $this->rewriter->output_tokens );
				$this->update_data_type_cache(
					$this->table_name,
					$new_field->name,
					$new_field->mysql_data_type
				);

				/*
				 * In SQLite, there is no direct equivalent to the CHANGE COLUMN
				 * statement from MySQL. We need to do a bit of work to emulate it.
				 *
				 * The idea is to:
				 * 1. Get the existing table schema.
				 * 2. Adjust the column definition.
				 * 3. Copy the data out of the old table.
				 * 4. Drop the old table to free up the indexes names.
				 * 5. Create a new table from the updated schema.
				 * 6. Copy the data from step 3 to the new table.
				 * 7. Drop the old table copy.
				 * 8. Restore any indexes that were dropped in step 4.
				 */

				// 1. Get the existing table schema.
				$old_schema  = $this->get_sqlite_create_table( $this->table_name );
				$old_indexes = $this->get_keys( $this->table_name, false );

				// 2. Adjust the column definition.

				// First, tokenize the old schema.
				$tokens       = ( new WP_SQLite_Lexer( $old_schema ) )->tokens;
				$create_table = new WP_SQLite_Query_Rewriter( $tokens );

				// Now, replace every reference to the old column name with the new column name.
				while ( true ) {
					$token = $create_table->consume();
					if ( ! $token ) {
						break;
					}
					if ( WP_SQLite_Token::TYPE_STRING !== $token->type
						|| $from_name !== $this->normalize_column_name( $token->value ) ) {
						continue;
					}

					// We found the old column name, let's remove it.
					$create_table->drop_last();

					// If the next token is a data type, we're dealing with a column definition.
					$is_column_definition = $create_table->peek()->matches(
						WP_SQLite_Token::TYPE_KEYWORD,
						WP_SQLite_Token::FLAG_KEYWORD_DATA_TYPE
					);
					if ( $is_column_definition ) {
						// Skip the old field definition.
						$field_depth = $create_table->depth;
						do {
							$field_terminator = $create_table->skip();
						} while (
							! $this->is_create_table_field_terminator(
								$field_terminator,
								$field_depth,
								$create_table->depth
							)
						);

						// Add an updated field definition.
						$definition = $this->make_sqlite_field_definition( $new_field );
						// Technically it's not a token, but it's fine to cheat a little bit.
						$create_table->add( new WP_SQLite_Token( $definition, WP_SQLite_Token::TYPE_KEYWORD ) );
						// Restore the terminating "," or ")" token.
						$create_table->add( $field_terminator );
					} else {
						// Otherwise, just add the new name in place of the old name we dropped.
						$create_table->add(
							new WP_SQLite_Token(
								"`$new_field->name`",
								WP_SQLite_Token::TYPE_KEYWORD
							)
						);
					}
				}

				// 3. Copy the data out of the old table
				$cache_table_name = "_tmp__{$this->table_name}_" . rand( 10000000, 99999999 );
				$this->execute_sqlite_query(
					"CREATE TABLE `$cache_table_name` as SELECT * FROM `$this->table_name`"
				);

				// 4. Drop the old table to free up the indexes names
				$this->execute_sqlite_query( "DROP TABLE `$this->table_name`" );

				// 5. Create a new table from the updated schema
				$this->execute_sqlite_query( $create_table->get_updated_query() );

				// 6. Copy the data from step 3 to the new table
				$this->execute_sqlite_query( "INSERT INTO {$this->table_name} SELECT * FROM $cache_table_name" );

				// 7. Drop the old table copy
				$this->execute_sqlite_query( "DROP TABLE `$cache_table_name`" );

				// 8. Restore any indexes that were dropped in step 4
				foreach ( $old_indexes as $row ) {
					/*
					 * Skip indexes prefixed with sqlite_autoindex_
					 * (these are automatically created by SQLite).
					 */
					if ( str_starts_with( $row['index']['name'], 'sqlite_autoindex_' ) ) {
						continue;
					}

					$columns = array();
					foreach ( $row['columns'] as $column ) {
						$columns[] = ( $column['name'] === $from_name )
							? '`' . $new_field->name . '`'
							: '`' . $column['name'] . '`';
					}

					$unique = '1' === $row['index']['unique'] ? 'UNIQUE' : '';

					/*
					 * Use IF NOT EXISTS to avoid collisions with indexes that were
					 * a part of the CREATE TABLE statement
					 */
					$this->execute_sqlite_query(
						"CREATE $unique INDEX IF NOT EXISTS `{$row['index']['name']}` ON $this->table_name (" . implode( ', ', $columns ) . ')'
					);
				}

				if ( ',' === $alter_terminator->token ) {
					/*
					 * If the terminator was a comma,
					 * we need to continue processing the rest of the ALTER query.
					 */
					$comma = true;
					continue;
				}
				// We're done.
				break;
			} elseif ( 'ADD' === $op_type && $is_index_op ) {
				$key_name          = $this->rewriter->consume()->value;
				$sqlite_index_type = $this->mysql_index_type_to_sqlite_type( $mysql_index_type );
				$sqlite_index_name = "{$this->table_name}__$key_name";
				$this->rewriter->replace_all(
					array(
						new WP_SQLite_Token( 'CREATE', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( $sqlite_index_type, WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( "\"$sqlite_index_name\"", WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_KEY ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( 'ON', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( '"' . $this->table_name . '"', WP_SQLite_Token::TYPE_STRING, WP_SQLite_Token::FLAG_STRING_DOUBLE_QUOTES ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( '(', WP_SQLite_Token::TYPE_OPERATOR ),
					)
				);
				$this->update_data_type_cache(
					$this->table_name,
					$sqlite_index_name,
					$mysql_index_type
				);

				$token = $this->rewriter->consume(
					array(
						WP_SQLite_Token::TYPE_OPERATOR,
						null,
						'(',
					)
				);
				$this->rewriter->drop_last();

				// Consume all the fields, skip the sizes like `(20)` in `varchar(20)`.
				while ( true ) {
					$token = $this->rewriter->consume();
					if ( ! $token ) {
						break;
					}
					// $token is field name.
					if ( ! $token->matches( WP_SQLite_Token::TYPE_OPERATOR ) ) {
						$token->token = '`' . $this->normalize_column_name( $token->token ) . '`';
						$token->value = '`' . $this->normalize_column_name( $token->token ) . '`';
					}

					/*
					 * Optionally, it may be followed by a size like `(20)`.
					 * Let's skip it.
					 */
					$paren_maybe = $this->rewriter->peek();
					if ( $paren_maybe && '(' === $paren_maybe->token ) {
						$this->rewriter->skip();
						$this->rewriter->skip();
						$this->rewriter->skip();
					}
					if ( ')' === $token->value ) {
						break;
					}
				}
			} elseif ( 'DROP' === $op_type && $is_index_op ) {
				$key_name = $this->rewriter->consume()->value;
				$this->rewriter->replace_all(
					array(
						new WP_SQLite_Token( 'DROP', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( 'INDEX', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( "\"{$this->table_name}__$key_name\"", WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_KEY ),
					)
				);
			} else {
				throw new Exception( 'Unknown operation: ' . $op_type );
			}
			$comma = $this->rewriter->consume(
				array(
					'type'  => WP_SQLite_Token::TYPE_OPERATOR,
					'value' => ',',
				)
			);
			$this->rewriter->drop_last();
			$this->execute_sqlite_query(
				$this->rewriter->get_updated_query()
			);
		} while ( $comma );

		$this->results      = 1;
		$this->return_value = $this->results;
	}

	/**
	 * Translates a CREATE query.
	 *
	 * @throws Exception If the query is an unknown create type.
	 */
	private function execute_create() {
		$this->rewriter->consume();
		$what = $this->rewriter->consume()->token;

		/**
		 * Technically it is possible to support temporary tables as follows:
		 *    ATTACH '' AS 'tempschema';
		 *    CREATE TABLE tempschema.<name>(...)...;
		 * However, for now, let's just ignore the TEMPORARY keyword.
		 */
		if ( 'TEMPORARY' === $what ) {
			$this->rewriter->drop_last();
			$what = $this->rewriter->consume()->token;
		}

		switch ( $what ) {
			case 'TABLE':
				$this->execute_create_table();
				break;

			case 'PROCEDURE':
			case 'DATABASE':
				$this->results = true;
				break;

			default:
				throw new Exception( 'Unknown create type: ' . $what );
		}
	}

	/**
	 * Translates a DROP query.
	 *
	 * @throws Exception If the query is an unknown drop type.
	 */
	private function execute_drop() {
		$this->rewriter->consume();
		$what = $this->rewriter->consume()->token;

		/*
		 * Technically it is possible to support temporary tables as follows:
		 *   ATTACH '' AS 'tempschema';
		 *   CREATE TABLE tempschema.<name>(...)...;
		 * However, for now, let's just ignore the TEMPORARY keyword.
		 */
		if ( 'TEMPORARY' === $what ) {
			$this->rewriter->drop_last();
			$what = $this->rewriter->consume()->token;
		}

		switch ( $what ) {
			case 'TABLE':
				$this->rewriter->consume_all();
				$this->execute_sqlite_query( $this->rewriter->get_updated_query() );
				$this->results = $this->last_exec_returned;
				break;

			case 'PROCEDURE':
			case 'DATABASE':
				$this->results = true;
				return;

			default:
				throw new Exception( 'Unknown drop type: ' . $what );
		}
	}

	/**
	 * Translates a SHOW query.
	 *
	 * @throws Exception If the query is an unknown show type.
	 */
	private function execute_show() {
		$this->rewriter->skip();
		$what1 = $this->rewriter->consume()->token;
		$what2 = $this->rewriter->consume()->token;
		$what  = $what1 . ' ' . $what2;
		switch ( $what ) {
			case 'CREATE PROCEDURE':
				$this->results = true;
				return;

			case 'FULL COLUMNS':
				$this->rewriter->consume();
				// Fall through.
			case 'COLUMNS FROM':
				$table_name = $this->rewriter->consume()->token;
				$stmt       = $this->execute_sqlite_query(
					"PRAGMA table_info(\"$table_name\");"
				);
				/* @todo we may need to add the Extra column if anybdy needs it. 'auto_increment' is the value */
				$name_map = array(
					'name'       => 'Field',
					'type'       => 'Type',
					'dflt_value' => 'Default',
					'cid'        => null,
					'notnull'    => null,
					'pk'         => null,
				);
				$columns  = $stmt->fetchAll( $this->pdo_fetch_mode );
				$columns  = array_map(
					function ( $row ) use ( $name_map ) {
						$new       = array();
						$is_object = is_object( $row );
						$row       = $is_object ? (array) $row : $row;
						foreach ( $row as $k => $v ) {
							$k = array_key_exists( $k, $name_map ) ? $name_map [ $k ] : $k;
							if ( $k ) {
								$new[ $k ] = $v;
							}
						}
						if ( array_key_exists( 'notnull', $row ) ) {
							$new['Null'] = ( '1' === $row ['notnull'] ) ? 'NO' : 'YES';
						}
						if ( array_key_exists( 'pk', $row ) ) {
							$new['Key'] = ( '1' === $row ['pk'] ) ? 'PRI' : '';
						}
						return $is_object ? (object) $new : $new;
					},
					$columns
				);
				$this->set_results_from_fetched_data( $columns );
				return;

			case 'INDEX FROM':
				$table_name = $this->rewriter->consume()->token;
				$results    = array();

				foreach ( $this->get_primary_keys( $table_name ) as $row ) {
					$results[] = array(
						'Table'       => $table_name,
						'Non_unique'  => '0',
						'Key_name'    => 'PRIMARY',
						'Column_name' => $row['name'],
					);
				}
				foreach ( $this->get_keys( $table_name ) as $row ) {
					foreach ( $row['columns'] as $k => $column ) {
						$results[] = array(
							'Table'       => $table_name,
							'Non_unique'  => '1' === $row['index']['unique'] ? '0' : '1',
							'Key_name'    => $row['index']['name'],
							'Column_name' => $column['name'],
						);
					}
				}
				for ( $i = 0;$i < count( $results );$i++ ) {
					$sqlite_key_name = $results[ $i ]['Key_name'];
					$mysql_key_name  = $sqlite_key_name;

					/*
					 * SQLite automatically assigns names to some indexes.
					 * However, dbDelta in WordPress expects the name to be
					 * the same as in the original CREATE TABLE. Let's
					 * translate the name back.
					 */
					if ( str_starts_with( $mysql_key_name, 'sqlite_autoindex_' ) ) {
						$mysql_key_name = substr( $mysql_key_name, strlen( 'sqlite_autoindex_' ) );
						$mysql_key_name = preg_replace( '/_[0-9]+$/', '', $mysql_key_name );
					}
					if ( str_starts_with( $mysql_key_name, "{$table_name}__" ) ) {
						$mysql_key_name = substr( $mysql_key_name, strlen( "{$table_name}__" ) );
					}

					$mysql_type = $this->get_cached_mysql_data_type( $table_name, $sqlite_key_name );
					if ( 'FULLTEXT' !== $mysql_type && 'SPATIAL' !== $mysql_type ) {
						$mysql_type = 'BTREE';
					}

					$results[ $i ] = (object) array_merge(
						$results[ $i ],
						array(
							'Seq_in_index'  => 0,
							'Key_name'      => $mysql_key_name,
							'Index_type'    => $mysql_type,

							/*
							 * Many of these details are not available in SQLite,
							 * so we just shim them with dummy values.
							 */
							'Collation'     => 'A',
							'Cardinality'   => '0',
							'Sub_part'      => null,
							'Packed'        => null,
							'Null'          => '',
							'Comment'       => '',
							'Index_comment' => '',
						)
					);
				}
				$this->set_results_from_fetched_data(
					$results
				);

				return;

			case 'TABLE STATUS':  // FROM `database`.
				$this->rewriter->skip();
				$database_expression = $this->rewriter->skip();
				$stmt                = $this->execute_sqlite_query(
					"SELECT name as `Name`, 'myisam' as `Engine`, 0 as `Data_length`, 0 as `Index_length`, 0 as `Data_free` FROM sqlite_master WHERE type='table' ORDER BY name"
				);

				$tables = $this->strip_sqlite_system_tables( $stmt->fetchAll( $this->pdo_fetch_mode ) );
				foreach ( $tables as $table ) {
					$table_name  = $table->Name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$stmt        = $this->execute_sqlite_query( "SELECT COUNT(1) as `Rows` FROM $table_name" );
					$rows        = $stmt->fetchall( $this->pdo_fetch_mode );
					$table->Rows = $rows[0]->Rows; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				}

				$this->set_results_from_fetched_data(
					$this->strip_sqlite_system_tables( $tables )
				);

				return;

			case 'TABLES LIKE':
				$table_expression = $this->rewriter->skip();
				$stmt             = $this->execute_sqlite_query(
					"SELECT `name` as `Tables_in_db` FROM `sqlite_master` WHERE `type`='table' AND `name` LIKE :param;",
					array(
						':param' => $table_expression->value,
					)
				);
				$this->set_results_from_fetched_data(
					$stmt->fetchAll( $this->pdo_fetch_mode )
				);
				return;

			default:
				switch ( $what1 ) {
					case 'TABLES':
						$stmt = $this->execute_sqlite_query(
							"SELECT name FROM sqlite_master WHERE type='table'"
						);
						$this->set_results_from_fetched_data(
							$stmt->fetchAll( $this->pdo_fetch_mode )
						);
						return;

					case 'VARIABLE':
					case 'VARIABLES':
						$this->results = true;
						return;

					default:
						throw new Exception( 'Unknown show type: ' . $what );
				}
		}
	}

	/**
	 * Consumes data types from the query.
	 *
	 * @throws Exception If the data type cannot be translated.
	 *
	 * @return array The data types.
	 */
	private function skip_mysql_data_type() {
		$type = $this->rewriter->skip();
		if ( ! $type->matches(
			WP_SQLite_Token::TYPE_KEYWORD,
			WP_SQLite_Token::FLAG_KEYWORD_DATA_TYPE
		) ) {
			throw new Exception( 'Data type expected in MySQL query, unknown token received: ' . $type->value );
		}

		$mysql_data_type = strtolower( $type->value );
		if ( ! isset( $this->field_types_translation[ $mysql_data_type ] ) ) {
			throw new Exception( 'MySQL field type cannot be translated to SQLite: ' . $mysql_data_type );
		}

		$sqlite_data_type = $this->field_types_translation[ $mysql_data_type ];

		// Skip the length, e.g. (10) in VARCHAR(10).
		$paren_maybe = $this->rewriter->peek();
		if ( $paren_maybe && '(' === $paren_maybe->token ) {
			$mysql_data_type .= $this->rewriter->skip()->token;
			$mysql_data_type .= $this->rewriter->skip()->token;
			$mysql_data_type .= $this->rewriter->skip()->token;
		}

		// Skip the int keyword.
		$int_maybe = $this->rewriter->peek();
		if ( $int_maybe && $int_maybe->matches(
			WP_SQLite_Token::TYPE_KEYWORD,
			null,
			array( 'UNSIGNED' )
		)
		) {
			$mysql_data_type .= ' ' . $this->rewriter->skip()->token;
		}
		return array(
			$sqlite_data_type,
			$mysql_data_type,
		);
	}

	/**
	 * Updates the data type cache.
	 *
	 * @param string $table           The table name.
	 * @param string $column_or_index The column or index name.
	 * @param string $mysql_data_type The MySQL data type.
	 *
	 * @return void
	 */
	private function update_data_type_cache( $table, $column_or_index, $mysql_data_type ) {
		$this->execute_sqlite_query(
			'INSERT INTO ' . self::DATA_TYPES_CACHE_TABLE . ' (`table`, `column_or_index`, `mysql_type`)
				VALUES (:table, :column, :datatype)
				ON CONFLICT(`table`, `column_or_index`) DO UPDATE SET `mysql_type` = :datatype
			',
			array(
				':table'    => $table,
				':column'   => $column_or_index,
				':datatype' => $mysql_data_type,
			)
		);
	}

	/**
	 * Gets the cached MySQL data type.
	 *
	 * @param string $table           The table name.
	 * @param string $column_or_index The column or index name.
	 *
	 * @return string The MySQL data type.
	 */
	private function get_cached_mysql_data_type( $table, $column_or_index ) {
		$stmt       = $this->execute_sqlite_query(
			'SELECT d.`mysql_type` FROM ' . self::DATA_TYPES_CACHE_TABLE . ' d
			WHERE `table`=:table
			AND `column_or_index` = :index',
			array(
				':table' => $table,
				':index' => $column_or_index,
			)
		);
		$mysql_type = $stmt->fetchColumn( 0 );
		if ( str_ends_with( $mysql_type, ' KEY' ) ) {
			$mysql_type = substr( $mysql_type, 0, strlen( $mysql_type ) - strlen( ' KEY' ) );
		}
		return $mysql_type;
	}

	/**
	 * Normalizes a column name.
	 *
	 * @param string $column_name The column name.
	 *
	 * @return string The normalized column name.
	 */
	private function normalize_column_name( $column_name ) {
		return trim( $column_name, '`\'"' );
	}

	/**
	 * Normalizes an index type.
	 *
	 * @param string $index_type The index type.
	 *
	 * @return string|null The normalized index type, or null if the index type is not supported.
	 */
	private function normalize_mysql_index_type( $index_type ) {
		$index_type = strtoupper( $index_type );
		$index_type = preg_replace( '/INDEX$/', 'KEY', $index_type );
		$index_type = preg_replace( '/ KEY$/', '', $index_type );
		if (
			'KEY' === $index_type
			|| 'PRIMARY' === $index_type
			|| 'UNIQUE' === $index_type
			|| 'FULLTEXT' === $index_type
			|| 'SPATIAL' === $index_type
		) {
			return $index_type;
		}
		return null;
	}

	/**
	 * Converts an index type to a SQLite index type.
	 *
	 * @param string|null $normalized_mysql_index_type The normalized index type.
	 *
	 * @return string|null The SQLite index type, or null if the index type is not supported.
	 */
	private function mysql_index_type_to_sqlite_type( $normalized_mysql_index_type ) {
		if ( null === $normalized_mysql_index_type ) {
			return null;
		}
		if ( 'PRIMARY' === $normalized_mysql_index_type ) {
			return 'PRIMARY KEY';
		}
		if ( 'UNIQUE' === $normalized_mysql_index_type ) {
			return 'UNIQUE INDEX';
		}
		return 'INDEX';
	}

	/**
	 * Executes a CHECK statement.
	 */
	private function execute_check() {
		$this->rewriter->skip();  // CHECK.
		$this->rewriter->skip();  // TABLE.
		$table_name = $this->rewriter->consume()->value;  // Τable_name.

		$tables =
			$this->execute_sqlite_query(
				"SELECT name as `table_name` FROM sqlite_master WHERE type='table' AND name = :table_name ORDER BY name",
				array( $table_name )
			)->fetchAll();

		if ( is_array( $tables ) && 1 === count( $tables ) && $table_name === $tables[0]['table_name'] ) {

			$this->set_results_from_fetched_data(
				array(
					(object) array(
						'Table'    => $table_name,
						'Op'       => 'check',
						'Msg_type' => 'status',
						'Msg_text' => 'OK',
					),
				)
			);
		} else {

			$this->set_results_from_fetched_data(
				array(
					(object) array(
						'Table'    => $table_name,
						'Op'       => 'check',
						'Msg_type' => 'Error',
						'Msg_text' => "Table '$table_name' doesn't exist",
					),
					(object) array(
						'Table'    => $table_name,
						'Op'       => 'check',
						'Msg_type' => 'status',
						'Msg_text' => 'Operation failed',
					),
				)
			);
		}
	}

	/**
	 * Handle an OPTIMIZE / REPAIR / ANALYZE TABLE statement, by using VACUUM just once, at shutdown.
	 *
	 * @param string $query_type The query type.
	 */
	private function execute_optimize( $query_type ) {
		// OPTIMIZE TABLE tablename.
		$this->rewriter->skip();
		$this->rewriter->skip();
		$table_name = $this->rewriter->skip()->value;
		$status     = '';

		if ( ! $this->vacuum_requested ) {
			$this->vacuum_requested = true;
			if ( function_exists( 'add_action' ) ) {
				$status = "SQLite does not support $query_type, doing VACUUM instead";
				add_action(
					'shutdown',
					function () {
						$this->execute_sqlite_query( 'VACUUM' );
					}
				);
			} else {
				/* add_action isn't available in the unit test environment, and we're deep in a transaction. */
				$status = "SQLite unit testing does not support $query_type.";
			}
		}
		$resultset = array(
			(object) array(
				'Table'    => $table_name,
				'Op'       => strtolower( $query_type ),
				'Msg_type' => 'note',
				'Msg_text' => $status,
			),
			(object) array(
				'Table'    => $table_name,
				'Op'       => strtolower( $query_type ),
				'Msg_type' => 'status',
				'Msg_text' => 'OK',
			),
		);

		$this->set_results_from_fetched_data( $resultset );
	}

	/**
	 * Error handler.
	 *
	 * @param Exception $err Exception object.
	 *
	 * @return bool Always false.
	 */
	private function handle_error( Exception $err ) {
		$message = $err->getMessage();
		$this->set_error( __LINE__, __FUNCTION__, $message );
		$this->return_value = false;
		return false;
	}

	/**
	 * Method to format the error messages and put out to the file.
	 *
	 * When $wpdb::suppress_errors is set to true or $wpdb::show_errors is set to false,
	 * the error messages are ignored.
	 *
	 * @param string $line          Where the error occurred.
	 * @param string $function_name Indicate the function name where the error occurred.
	 * @param string $message       The message.
	 *
	 * @return boolean|void
	 */
	private function set_error( $line, $function_name, $message ) {
		$this->errors[]         = array(
			'line'     => $line,
			'function' => $function_name,
		);
		$this->error_messages[] = $message;
		$this->is_error         = true;
	}

	/**
	 * PDO has no explicit close() method.
	 *
	 * This is because PHP may choose to reuse the same
	 * connection for the next request. The PHP manual
	 * states the PDO object can only be unset:
	 *
	 * https://www.php.net/manual/en/pdo.connections.php#114822
	 */
	public function close() {
		$this->pdo = null;
	}

	/**
	 * Method to return error messages.
	 *
	 * @throws Exception If error is found.
	 *
	 * @return string
	 */
	public function get_error_message() {
		if ( count( $this->error_messages ) === 0 ) {
			$this->is_error       = false;
			$this->error_messages = array();
			return '';
		}

		if ( false === $this->is_error ) {
			return '';
		}

		$output  = '<div style="clear:both">&nbsp;</div>' . PHP_EOL;
		$output .= '<div class="queries" style="clear:both;margin-bottom:2px;border:red dotted thin;">' . PHP_EOL;
		$output .= '<p>MySQL query:</p>' . PHP_EOL;
		$output .= '<p>' . $this->mysql_query . '</p>' . PHP_EOL;
		$output .= '<p>Queries made or created this session were:</p>' . PHP_EOL;
		$output .= '<ol>' . PHP_EOL;
		foreach ( $this->executed_sqlite_queries as $q ) {
			$message = "Executing: {$q['sql']} | " . ( $q['params'] ? 'parameters: ' . implode( ', ', $q['params'] ) : '(no parameters)' );

			$output .= '<li>' . htmlspecialchars( $message ) . '</li>' . PHP_EOL;
		}
		$output .= '</ol>' . PHP_EOL;
		$output .= '</div>' . PHP_EOL;
		foreach ( $this->error_messages as $num => $m ) {
			$output .= '<div style="clear:both;margin-bottom:2px;border:red dotted thin;" class="error_message" style="border-bottom:dotted blue thin;">' . PHP_EOL;
			$output .= sprintf(
				'Error occurred at line %1$d in Function %2$s. Error message was: %3$s.',
				(int) $this->errors[ $num ]['line'],
				'<code>' . htmlspecialchars( $this->errors[ $num ]['function'] ) . '</code>',
				$m
			) . PHP_EOL;
			$output .= '</div>' . PHP_EOL;
		}

		try {
			throw new Exception();
		} catch ( Exception $e ) {
			$output .= '<p>Backtrace:</p>' . PHP_EOL;
			$output .= '<pre>' . $e->getTraceAsString() . '</pre>' . PHP_EOL;
		}

		return $output;
	}

	/**
	 * Executes a query in SQLite.
	 *
	 * @param mixed $sql The query to execute.
	 * @param mixed $params The parameters to bind to the query.
	 * @throws PDOException If the query could not be executed.
	 * @return object {
	 *     The result of the query.
	 *
	 *     @type PDOStatement $stmt The executed statement
	 *     @type * $result The value returned by $stmt.
	 * }
	 */
	public function execute_sqlite_query( $sql, $params = array() ) {
		$this->executed_sqlite_queries[] = array(
			'sql'    => $sql,
			'params' => $params,
		);

		$stmt = $this->pdo->prepare( $sql );
		if ( false === $stmt || null === $stmt ) {
			$this->last_exec_returned = null;
			$info                     = $this->pdo->errorInfo();
			$this->last_sqlite_error  = $info[0] . ' ' . $info[2];
			throw new PDOException( implode( ' ', array( 'Error:', $info[0], $info[2], 'SQLite:', $sql ) ), $info[1] );
		}
		$returned                 = $stmt->execute( $params );
		$this->last_exec_returned = $returned;
		if ( ! $returned ) {
			$info                    = $stmt->errorInfo();
			$this->last_sqlite_error = $info[0] . ' ' . $info[2];
			throw new PDOException( implode( ' ', array( 'Error:', $info[0], $info[2], 'SQLite:', $sql ) ), $info[1] );
		}

		return $stmt;
	}

	/**
	 * Method to set the results from the fetched data.
	 *
	 * @param array $data The data to set.
	 */
	private function set_results_from_fetched_data( $data ) {
		if ( null === $this->results ) {
			$this->results = $data;
		}
		if ( is_array( $this->results ) ) {
			$this->num_rows               = count( $this->results );
			$this->last_select_found_rows = count( $this->results );
		}
		$this->return_value = $this->results;
	}

	/**
	 * Method to set the results from the affected rows.
	 *
	 * @param int|null $override Override the affected rows.
	 */
	private function set_result_from_affected_rows( $override = null ) {
		/*
		 * SELECT CHANGES() is a workaround for the fact that
		 * $stmt->rowCount() returns "0" (zero) with the
		 * SQLite driver at all times.
		 * Source: https://www.php.net/manual/en/pdostatement.rowcount.php
		 */
		if ( null === $override ) {
			$this->affected_rows = (int) $this->execute_sqlite_query( 'select changes()' )->fetch()[0];
		} else {
			$this->affected_rows = $override;
		}
		$this->return_value = $this->affected_rows;
		$this->num_rows     = $this->affected_rows;
		$this->results      = $this->affected_rows;
	}

	/**
	 * Method to clear previous data.
	 */
	private function flush() {
		$this->mysql_query                 = '';
		$this->results                     = null;
		$this->last_exec_returned          = null;
		$this->table_name                  = null;
		$this->last_insert_id              = null;
		$this->affected_rows               = null;
		$this->insert_columns              = array();
		$this->column_data                 = array();
		$this->num_rows                    = null;
		$this->return_value                = null;
		$this->error_messages              = array();
		$this->is_error                    = false;
		$this->executed_sqlite_queries     = array();
		$this->like_expression_nesting     = 0;
		$this->like_escape_count           = 0;
		$this->is_information_schema_query = false;
		$this->has_group_by                = false;
	}

	/**
	 * Begin a new transaction or nested transaction.
	 *
	 * @return boolean
	 */
	public function begin_transaction() {
		$success = false;
		try {
			if ( 0 === $this->transaction_level ) {
				$this->execute_sqlite_query( 'BEGIN' );
			} else {
				$this->execute_sqlite_query( 'SAVEPOINT LEVEL' . $this->transaction_level );
			}
			$success = $this->last_exec_returned;
		} finally {
			if ( $success ) {
				++$this->transaction_level;
				/**
				 * Notifies that a transaction-related query has been translated and executed.
				 *
				 * @param string $command       The SQL statement (one of "START TRANSACTION", "COMMIT", "ROLLBACK").
				 * @param bool   $success       Whether the SQL statement was successful or not.
				 * @param int    $nesting_level The nesting level of the transaction.
				 *
				 * @since 0.1.0
				 */
				do_action( 'sqlite_transaction_query_executed', 'START TRANSACTION', (bool) $this->last_exec_returned, $this->transaction_level - 1 );
			}
		}
		return $success;
	}

	/**
	 * Commit the current transaction or nested transaction.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function commit() {
		if ( 0 === $this->transaction_level ) {
			return false;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'COMMIT' );
		} else {
			$this->execute_sqlite_query( 'RELEASE SAVEPOINT LEVEL' . $this->transaction_level );
		}

		do_action( 'sqlite_transaction_query_executed', 'COMMIT', (bool) $this->last_exec_returned, $this->transaction_level );
		return $this->last_exec_returned;
	}

	/**
	 * Rollback the current transaction or nested transaction.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function rollback() {
		if ( 0 === $this->transaction_level ) {
			return false;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'ROLLBACK' );
		} else {
			$this->execute_sqlite_query( 'ROLLBACK TO SAVEPOINT LEVEL' . $this->transaction_level );
		}
		do_action( 'sqlite_transaction_query_executed', 'ROLLBACK', (bool) $this->last_exec_returned, $this->transaction_level );
		return $this->last_exec_returned;
	}
}
