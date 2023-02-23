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
class WP_SQLite_Translator extends PDO {


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
	 * The SQLite database.
	 *
	 * @var PDO
	 */
	private $sqlite;

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
	 * The table prefix.
	 *
	 * @var string
	 */
	private $table_prefix;

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
	 * The last found rows.
	 *
	 * @var int|string
	 */
	private $last_found_rows = 0;

	/**
	 * The number of rows found by the last SELECT query.
	 *
	 * @var int
	 */
	protected $last_select_found_rows;

	/**
	 * Class variable which is used for CALC_FOUND_ROW query.
	 *
	 * @var unsigned integer
	 */
	public $found_rows_result = null;

	/**
	 * Class variable used for query with ORDER BY FIELD()
	 *
	 * @var array of the object
	 */
	public $pre_ordered_results = null;

	/**
	 * Class variable to store the last query.
	 *
	 * @var string
	 */
	public $last_translation;

	/**
	 * The query rewriter.
	 *
	 * @var WP_SQLite_Query_Rewriter
	 */
	private $rewriter;

	/**
	 * Class variable to store the query strings.
	 *
	 * @var array
	 */
	public $queries = array();

	/**
	 * The query type.
	 *
	 * @var string
	 */
	private $query_type;

	/**
	 * Class variable to store the rewritten queries.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $rewritten_query;

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
	 * @var unsigned integer
	 * @access private
	 */
	private $last_insert_id;

	/**
	 * Class variable to store the number of rows affected.
	 *
	 * @var unsigned integer
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
	 * Variable to check if there is an active transaction.
	 *
	 * @var boolean
	 * @access protected
	 */
	protected $has_active_transaction = false;

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
					$dsn = 'sqlite:' . FQDB;
					$pdo = new PDO( $dsn, null, null, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) ); // phpcs:ignore WordPress.DB.RestrictedClasses
					new WP_SQLite_PDO_User_Defined_Functions( $pdo );
				} catch ( PDOException $ex ) {
					$status = $ex->getCode();
					if ( 5 === $status || 6 === $status ) {
						$locked = true;
					} else {
						$err_message = $ex->getMessage();
					}
				}
			} while ( $locked );

			if ( $status > 0 ) {
				$message          = sprintf(
					'<p>%s</p><p>%s</p><p>%s</p>',
					'Database initialization error!',
					"Code: $status",
					"Error Message: $err_message"
				);
				$this->is_error   = true;
				$this->last_error = $message;

				return false;
			}

			// MySQL data comes across stringified by default.
			$pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
			$pdo->query( WP_SQLite_Translator::CREATE_DATA_TYPES_CACHE_TABLE );
		}
		$this->pdo = $pdo;

		// Fixes a warning in the site-health screen.
		$this->client_info = SQLite3::version()['versionString'];

		register_shutdown_function( array( $this, '__destruct' ) );
		$this->init();

		$this->pdo->query( 'PRAGMA encoding="UTF-8";' );

		$this->table_prefix = $GLOBALS['table_prefix'];
	}

	/**
	 * Destructor
	 *
	 * If SQLITE_MEM_DEBUG constant is defined, append information about
	 * memory usage into database/mem_debug.txt.
	 *
	 * This definition is changed since version 1.7.
	 *
	 * @return boolean
	 */
	function __destruct() {
		if ( defined( 'SQLITE_MEM_DEBUG' ) && SQLITE_MEM_DEBUG ) {
			$max = ini_get( 'memory_limit' );
			if ( is_null( $max ) ) {
				$message = sprintf(
					'[%s] Memory_limit is not set in php.ini file.',
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] )
				);
				error_log( $message );
				return true;
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

		return true;
	}

	/**
	 * Method to initialize database, executed in the constructor.
	 *
	 * It checks if WordPress is in the installing process and does the required
	 * jobs. SQLite library version specific settings are also in this function.
	 *
	 * Some developers use WP_INSTALLING constant for other purposes, if so, this
	 * function will do no harms.
	 */
	private function init() {
		if ( version_compare( SQLite3::version()['versionString'], '3.7.11', '>=' ) ) {
			$this->can_insert_multiple_rows = true;
		}
		$statement = $this->pdo->query( 'PRAGMA foreign_keys' );
		if ( $statement->fetchColumn( 0 ) == '0' ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$this->pdo->query( 'PRAGMA foreign_keys = ON' );
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
		try {
			if (
				preg_match( '/^START TRANSACTION/i', $statement )
				|| preg_match( '/^BEGIN/i', $statement )
			) {
				return $this->beginTransaction();
			}
			if ( preg_match( '/^COMMIT/i', $statement ) ) {
				return $this->commit();
			}
			if ( preg_match( '/^ROLLBACK/i', $statement ) ) {
				return $this->rollBack();
			}

			do {
				$error = null;
				try {
					$translation = $this->translate(
						$statement,
						$this->found_rows_result
					);
				} catch ( PDOException $error ) {
					if ( $error->getCode() !== self::SQLITE_BUSY ) {
						return $this->handle_error( $error );
					}
				}
			} while ( $error );

			$stmt        = null;
			$last_retval = null;
			foreach ( $translation->queries as $query ) {
				$this->queries[] = "Executing: {$query->sql} | " . ( $query->params ? 'parameters: ' . implode( ', ', $query->params ) : '(no parameters)' );
				do {
					$error = null;
					try {
						$stmt        = $this->pdo->prepare( $query->sql );
						$last_retval = $stmt->execute( $query->params );
					} catch ( PDOException $error ) {
						if ( $error->getCode() !== self::SQLITE_BUSY ) {
							throw $error;
						}
					}
				} while ( $error );
			}

			if ( $translation->has_result ) {
				$this->results = $translation->result;
			} else {
				switch ( $translation->mysql_query_type ) {
					case 'DESCRIBE':
						$this->results = $stmt->fetchAll( $mode );
						if ( ! $this->results ) {
							$this->handle_error( new PDOException( 'Table not found' ) );
							return;
						}
						break;
					case 'SELECT':
					case 'SHOW':
						$this->results = $stmt->fetchAll( $mode );
						break;
					case 'TRUNCATE':
						$this->results      = true;
						$this->return_value = true;
						return $this->return_value;
					case 'SET':
						$this->results = 0;
						break;
					default:
						$this->results = $last_retval;
						break;
				}
			}

			if ( $translation->calc_found_rows ) {
				$this->found_rows_result = $translation->calc_found_rows;
			}

			if ( is_array( $this->results ) ) {
				$this->num_rows               = count( $this->results );
				$this->last_select_found_rows = count( $this->results );
			}

			switch ( $translation->sqlite_query_type ) {
				case 'DELETE':
				case 'UPDATE':
				case 'INSERT':
				case 'REPLACE':
					/*
					 * SELECT CHANGES() is a workaround for the fact that
					 * $stmt->rowCount() returns "0" (zero) with the
					 * SQLite driver at all times.
					 * Source: https://www.php.net/manual/en/pdostatement.rowcount.php
					 */
					$this->affected_rows  = (int) $this->pdo->query( 'select changes()' )->fetch()[0];
					$this->return_value   = $this->affected_rows;
					$this->num_rows       = $this->affected_rows;
					$this->last_insert_id = $this->pdo->lastInsertId();
					if ( is_numeric( $this->last_insert_id ) ) {
						$this->last_insert_id = (int) $this->last_insert_id;
					}
					break;
				default:
					$this->return_value = $this->results;
					break;
			}

			return $this->return_value;
		} catch ( Exception $err ) {
			if ( defined( 'PDO_DEBUG' ) && PDO_DEBUG === true ) {
				throw $err;
			}
			return $this->handle_error( $err );
		}
	}

	/**
	 * Gets the query object.
	 *
	 * @param string $sql    The SQL query.
	 * @param array  $params The parameters.
	 *
	 * @return stdClass
	 */
	public static function get_query_object( $sql = '', $params = array() ) {
		$sql_obj         = new stdClass();
		$sql_obj->sql    = trim( $sql );
		$sql_obj->params = $params;
		return $sql_obj;
	}

	/**
	 * Gets the translation result.
	 *
	 * @param array   $queries       The queries.
	 * @param boolean $has_result    Whether the query has a result.
	 * @param mixed   $custom_output The result.
	 *
	 * @return stdClass
	 */
	protected function get_translation_result( $queries, $has_result = false, $custom_output = null ) {
		$result                    = new stdClass();
		$result->queries           = $queries;
		$result->has_result        = $has_result;
		$result->result            = $custom_output;
		$result->calc_found_rows   = null;
		$result->sqlite_query_type = null;
		$result->mysql_query_type  = null;
		$result->rewriter          = null;
		$result->query_type        = null;

		return $result;
	}

	/**
	 * Method to return the queried column names.
	 *
	 * These data are meaningless for SQLite. So they are dummy emulating
	 * MySQL columns data.
	 *
	 * @return array of the object
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
				'unsigned'     => 0,  // 1 if column is unsigned integer.
				'zerofill'     => 0,  // 1 if column is zero-filled.
			);
			$table_name  = '';
			if ( preg_match( '/\s*FROM\s*(.*)?\s*/i', $this->rewritten_query, $match ) ) {
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
	 * Translates the query.
	 *
	 * @param string     $query           The query.
	 * @param int|string $last_found_rows The last found rows.
	 *
	 * @throws Exception If the query is not supported.
	 *
	 * @return stdClass
	 */
	public function translate( string $query, $last_found_rows = null ) {
		$this->last_found_rows = $last_found_rows;

		$tokens           = ( new WP_SQLite_Lexer( $query ) )->tokens;
		$this->rewriter   = new WP_SQLite_Query_Rewriter( $tokens );
		$this->query_type = $this->rewriter->peek()->value;

		switch ( $this->query_type ) {
			case 'ALTER':
				$result = $this->translate_alter();
				break;

			case 'CREATE':
				$result = $this->translate_create();
				break;

			case 'REPLACE':
			case 'SELECT':
			case 'INSERT':
			case 'UPDATE':
			case 'DELETE':
				$result = $this->translate_crud();
				break;

			case 'CALL':
			case 'SET':
				/*
				 * It would be lovely to support at least SET autocommit,
				 * but I don't think that is even possible with SQLite.
				 */
				$result = $this->get_translation_result( array( $this->noop() ) );
				break;

			case 'TRUNCATE':
				$this->rewriter->skip(); // TRUNCATE.
				$this->rewriter->skip(); // TABLE.
				$this->rewriter->add( new WP_SQLite_Token( 'DELETE', WP_SQLite_Token::TYPE_KEYWORD ) );
				$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
				$this->rewriter->add( new WP_SQLite_Token( 'FROM', WP_SQLite_Token::TYPE_KEYWORD ) );
				$this->rewriter->consume_all();
				$result = $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object( $this->rewriter->get_updated_query() ),
					)
				);
				break;

			case 'START TRANSACTION':
				$result = $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object( 'BEGIN' ),
					)
				);
				break;

			case 'BEGIN':
			case 'COMMIT':
			case 'ROLLBACK':
				$result = $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object( $query ),
					)
				);
				break;

			case 'DROP':
				$result = $this->translate_drop();
				break;

			case 'DESCRIBE':
				$this->rewriter->skip();
				$table_name = $this->rewriter->consume()->value;
				$result     = $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object(
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
							FROM pragma_table_info(\"$table_name\") p
							LEFT JOIN " . self::DATA_TYPES_CACHE_TABLE . " d
							ON d.`table` = \"$table_name\"
							AND d.`column_or_index` = p.`name`
							;
						"
						),
					)
				);
				break;

			case 'SHOW':
				$result = $this->translate_show();
				break;

			default:
				throw new Exception( 'Unknown query type: ' . $this->query_type );
		}
		// The query type could have changed – let's grab the new one.
		if ( count( $result->queries ) ) {
			$last_query                = $result->queries[ count( $result->queries ) - 1 ];
			$first_word                = preg_match( '/^\s*(\w+)/', $last_query->sql, $matches ) ? $matches[1] : '';
			$result->sqlite_query_type = strtoupper( $first_word );
		}
		$result->mysql_query_type = $this->query_type;
		return $result;
	}

	/**
	 * Translates the CREATE TABLE query.
	 *
	 * @throws Exception If the query is not supported.
	 *
	 * @return stdClass
	 */
	private function translate_create_table() {
		$table = $this->parse_create_table();

		$extra_queries = array();
		$definitions   = array();
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

			$definitions[]   = $this->make_sqlite_field_definition( $field );
			$extra_queries[] = $this->update_data_type_cache(
				$table->name,
				$field->name,
				$field->mysql_data_type,
			);
		}

		if ( count( $table->primary_key ) > 1 ) {
			$definitions[] = 'PRIMARY KEY ("' . implode( '", "', $table->primary_key ) . '")';
		}

		$create_table_query = WP_SQLite_Translator::get_query_object(
			$table->create_table .
			'"' . $table->name . '" (' . "\n" .
			implode( ",\n", $definitions ) .
			')'
		);

		foreach ( $table->constraints as $constraint ) {
			$index_type = $this->mysql_index_type_to_sqlite_type( $constraint->value );
			$unique     = '';
			if ( 'UNIQUE' === $constraint->value ) {
				$unique = 'UNIQUE ';
			}
			$index_name      = "{$table->name}__{$constraint->name}";
			$extra_queries[] = WP_SQLite_Translator::get_query_object(
				"CREATE $unique INDEX \"$index_name\" ON \"{$table->name}\" (\"" . implode( '", "', $constraint->columns ) . '")'
			);
			$extra_queries[] = $this->update_data_type_cache(
				$table->name,
				$index_name,
				$constraint->value,
			);
		}

		return $this->get_translation_result(
			array_merge(
				array(
					$create_table_query,
				),
				$extra_queries
			)
		);
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
				$result->constraints[] = $this->parse_mysql_create_table_constraint( $result->name );
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
				array( 'NOT NULL' ),
			) ) {
				$result->not_null = true;
				continue;
			}

			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_RESERVED,
				array( 'PRIMARY KEY' ),
			) ) {
				$result->primary_key = true;
				continue;
			}

			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				null,
				array( 'AUTO_INCREMENT' ),
			) ) {
				$result->primary_key    = true;
				$result->auto_increment = true;
				continue;
			}

			if ( $token->matches(
				WP_SQLite_Token::TYPE_KEYWORD,
				WP_SQLite_Token::FLAG_KEYWORD_FUNCTION,
				array( 'DEFAULT' ),
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
	 * Translator method.
	 *
	 * @throws Exception If the query type is unknown.
	 *
	 * @return stdClass
	 */
	private function translate_crud() {
		$query_type = $this->rewriter->consume()->value;

		$params                  = array();
		$is_in_duplicate_section = false;
		$table_name              = null;
		$has_sql_calc_found_rows = false;

		// Consume the query type.
		if ( 'INSERT' === $query_type && 'IGNORE' === $this->rewriter->peek()->value ) {
			$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
			$this->rewriter->add( new WP_SQLite_Token( 'OR', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_RESERVED ) );
			$this->rewriter->consume(); // IGNORE.
		}

		// Consume and record the table name.
		$this->insert_columns = array();
		if ( 'INSERT' === $query_type || 'REPLACE' === $query_type ) {
			$this->rewriter->consume(); // INTO.
			$table_name = $this->rewriter->consume()->value; // Table name.

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
		}

		$last_reserved_keyword = null;
		while ( true ) {
			$token = $this->rewriter->peek();
			if ( ! $token ) {
				break;
			}

			if ( WP_SQLite_Token::TYPE_KEYWORD === $token->type && $token->flags & WP_SQLite_Token::FLAG_KEYWORD_RESERVED ) {
				$last_reserved_keyword = $token->value;
				if ( 'FROM' === $last_reserved_keyword ) {
					$from_table = $this->rewriter->peek_nth( 2 )->value;
					if ( 'DUAL' === strtoupper( $from_table ) ) {
						// FROM DUAL is a MySQLism that means "no tables".
						$this->rewriter->skip();
						$this->rewriter->skip();
						continue;
					} elseif ( ! $table_name ) {
						$table_name = $from_table;
					}
				}
			}

			if ( 'SQL_CALC_FOUND_ROWS' === $token->value && WP_SQLite_Token::TYPE_KEYWORD === $token->type ) {
				$has_sql_calc_found_rows = true;
				$this->rewriter->skip();
				continue;
			}

			if ( 'AS' !== $last_reserved_keyword && WP_SQLite_Token::TYPE_STRING === $token->type && $token->flags & WP_SQLite_Token::FLAG_STRING_SINGLE_QUOTES ) {
				// Rewrite string values to bound parameters.
				$param_name            = ':param' . count( $params );
				$params[ $param_name ] = $this->preprocess_string_literal( $token->value );
				$this->rewriter->skip();
				$this->rewriter->add( new WP_SQLite_Token( $param_name, WP_SQLite_Token::TYPE_STRING, WP_SQLite_Token::FLAG_STRING_SINGLE_QUOTES ) );
				$this->rewriter->add( new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ) );
				continue;
			}

			if ( WP_SQLite_Token::TYPE_KEYWORD === $token->type ) {
				if (
					$this->translate_concat_function( $token )
					|| $this->translate_cast_as_binary( $token )
					|| $this->translate_date_add_sub( $token )
					|| $this->translate_values_function( $token, $is_in_duplicate_section )
					|| $this->translate_date_format( $token )
					|| $this->translate_interval( $token )
					|| $this->translate_regexp_functions( $token )
				) {
					continue;
				}

				if ( 'INSERT' === $query_type && 'DUPLICATE' === $token->keyword ) {
					$is_in_duplicate_section = true;
					$this->translate_on_duplicate_key( $table_name );
					continue;
				}
			}

			if ( $this->translate_concat_comma_to_pipes( $token ) ) {
				continue;
			}
			$this->rewriter->consume();
		}
		$this->rewriter->consume_all();

		$updated_query = $this->rewriter->get_updated_query();
		$result        = $this->get_translation_result( array() );

		if ( 'SELECT' === $query_type && $table_name && str_starts_with( strtolower( $table_name ), 'information_schema' ) ) {
			return $this->translate_information_schema_query(
				$updated_query
			);
		}

		/*
		 * If the query contains a function that is not supported by SQLite,
		 * return a dummy select. This check must be done after the query
		 * has been rewritten to use parameters to avoid false positives
		 * on queries such as `SELECT * FROM table WHERE field='CONVERT('`.
		 */
		if (
			strpos( $updated_query, '@@SESSION.sql_mode' ) !== false
			|| strpos( $updated_query, 'CONVERT( ' ) !== false
		) {
			$updated_query = 'SELECT 1=0';
			$params        = array();
		}

		// Emulate SQL_CALC_FOUND_ROWS for now.
		if ( $has_sql_calc_found_rows ) {
			$query = $updated_query;
			// We make the data for next SELECT FOUND_ROWS() statement.
			$unlimited_query = preg_replace( '/\\bLIMIT\\s\d+(?:\s*,\s*\d+)?$/imsx', '', $query );
			$stmt            = $this->pdo->prepare( $unlimited_query );
			$stmt->execute( $params );
			$result->calc_found_rows = count( $stmt->fetchAll() );
		}

		// Emulate FOUND_ROWS() by counting the rows in the result set.
		if ( strpos( $updated_query, 'FOUND_ROWS(' ) !== false ) {
			$last_found_rows   = ( $this->last_found_rows ? $this->last_found_rows : 0 ) . '';
			$result->queries[] = WP_SQLite_Translator::get_query_object(
				"SELECT {$last_found_rows} AS `FOUND_ROWS()`",
			);
			return $result;
		}

		/*
		 * Now that functions are rewritten to SQLite dialect,
		 * let's translate unsupported delete queries.
		 */
		if ( 'DELETE' === $query_type ) {
			$delete_result = $this->postprocess_double_delete( $params );
			if ( $delete_result ) {
				return $delete_result;
			}
		}

		$result->queries[] = WP_SQLite_Translator::get_query_object( $updated_query, $params );
		return $result;
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
			if ( false === strtotime( $value ) ) {
				$value = '0000-00-00 00:00:00';
			}
		}
		return $value;
	}

	/**
	 * Postprocesses a double delete query.
	 *
	 * @param array $rewritten_params The rewritten parameters.
	 *
	 * @throws Exception If the query is not a double delete query.
	 *
	 * @return WP_SQLite_Translation_Result|null The translation result or null if the query is not a double delete query.
	 */
	private function postprocess_double_delete( $rewritten_params ) {
		// Naive rewriting of DELETE JOIN query.
		// @TODO: Actually rewrite the query instead of using a hardcoded workaround.
		$updated_query = $this->rewriter->get_updated_query();
		if ( str_contains( $updated_query, ' JOIN ' ) ) {
			return $this->get_translation_result(
				array(
					WP_SQLite_Translator::get_query_object(
						"DELETE FROM {$this->table_prefix}options WHERE option_id IN (SELECT MIN(option_id) FROM {$this->table_prefix}options GROUP BY option_name HAVING COUNT(*) > 1)"
					),
				)
			);
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
		// It's a dual delete query if the comma comes before the FROM.
		if ( ! $comma || ! $from || $comma->position >= $from->position ) {
			return;
		}

		$table_name = $rewriter->skip()->value;
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
				$table_name = $rewriter->input_tokens[ $i ]->value;
				break;
			}
		}
		if ( ! $table_name ) {
			throw new Exception( 'Could not find table name for dual delete query.' );
		}

		/*
		 * Now, let's figure out the primary key name.
		 * This assumes that all listed table names are the same.
		 */
		$q       = $this->pdo->query( 'SELECT l.name FROM pragma_table_info("' . $table_name . '") as l WHERE l.pk = 1;' );
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
		$stmt   = $this->pdo->prepare( $select );
		$stmt->execute( $rewritten_params );
		$rows          = $stmt->fetchAll();
		$ids_to_delete = array();
		foreach ( $rows as $id ) {
			$ids_to_delete[] = $id['id_0'];
			$ids_to_delete[] = $id['id_1'];
		}

		$query = (
			count( $ids_to_delete )
				? "DELETE FROM {$table_name} WHERE {$pk_name} IN (" . implode( ',', $ids_to_delete ) . ')'
				: "DELETE FROM {$table_name} WHERE 0=1"
		);
		return $this->get_translation_result(
			array(
				WP_SQLite_Translator::get_query_object( $query ),
			),
			true,
			count( $ids_to_delete )
		);
	}

	/**
	 * Translate an information_schema query.
	 *
	 * @param string $query The query to translate.
	 *
	 * @return WP_SQLite_Translation_Result
	 */
	private function translate_information_schema_query( $query ) {
		// @TODO: Actually rewrite the columns.
		if ( str_contains( $query, 'bytes' ) ) {
			// Count rows per table.
			$tables = $this->pdo->query( "SELECT name as `table` FROM sqlite_master WHERE type='table' ORDER BY name" )->fetchAll();
			$rows   = '(CASE ';
			foreach ( $tables as $table ) {
				$table_name = $table['table'];
				$count      = $this->pdo->query( "SELECT COUNT(*) as `count` FROM $table_name" )->fetch();
				$rows      .= " WHEN name = '$table_name' THEN {$count['count']} ";
			}
			$rows .= 'ELSE 0 END) ';
			return $this->get_translation_result(
				array(
					WP_SQLite_Translator::get_query_object(
						"SELECT name as `table`, $rows as `rows`, 0 as `bytes` FROM sqlite_master WHERE type='table' ORDER BY name"
					),
				)
			);
		}
		return $this->get_translation_result(
			array(
				WP_SQLite_Translator::get_query_object(
					"SELECT name, 'myisam' as `engine`, 0 as `data`, 0 as `index` FROM sqlite_master WHERE type='table' ORDER BY name"
				),
			)
		);
	}

	/**
	 * Translate CAST() function when we want to cast to BINARY.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_cast_as_binary( $token ) {
		if ( $token->matches( WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_DATA_TYPE ) ) {
			$call_parent = $this->rewriter->last_call_stack_element();
			// Rewrite AS BINARY to AS BLOB inside CAST() calls.
			if (
				$call_parent
				&& 'CAST' === $call_parent['function']
				&& 'BINARY' === $token->value
			) {
				$this->rewriter->skip();
				$this->rewriter->add( new WP_SQLite_Token( 'BLOB', $token->type, $token->flags ) );
				return true;
			}
		}
		return false;
	}

	/**
	 * Translate CONCAT() function.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_concat_function( $token ) {
		/*
		 * Skip the CONCAT function but leave the parentheses.
		 * There is another code block below that replaces the
		 * , operators between the CONCAT arguments with ||.
		 */
		if (
			'CONCAT' === $token->keyword
			&& $token->flags & WP_SQLite_Token::FLAG_KEYWORD_FUNCTION
		) {
			$this->rewriter->skip();
			return true;
		}
		return false;
	}

	/**
	 * Translate CONCAT() function arguments.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_concat_comma_to_pipes( $token ) {
		if ( WP_SQLite_Token::TYPE_OPERATOR === $token->type ) {
			$call_parent = $this->rewriter->last_call_stack_element();
			// Rewrite commas to || in CONCAT() calls.
			if (
				$call_parent
				&& 'CONCAT' === $call_parent['function']
				&& ',' === $token->value
				&& $token->flags & WP_SQLite_Token::FLAG_OPERATOR_SQL
			) {
				$this->rewriter->skip();
				$this->rewriter->add( new WP_SQLite_Token( '||', WP_SQLite_Token::TYPE_OPERATOR ) );
				return true;
			}
		}
		return false;
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
			$token->flags & WP_SQLite_Token::FLAG_KEYWORD_FUNCTION && (
				'DATE_ADD' === $token->keyword ||
				'DATE_SUB' === $token->keyword
			)
		) {
			$this->rewriter->skip();
			$this->rewriter->add( new WP_SQLite_Token( 'DATETIME', WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_FUNCTION ) );
			return true;
		}
		return false;
	}

	/**
	 * Translate VALUES() function.
	 *
	 * @param WP_SQLite_Token $token                   The token to translate.
	 * @param bool            $is_in_duplicate_section Whether the VALUES() function is in a duplicate section.
	 *
	 * @return bool
	 */
	private function translate_values_function( $token, $is_in_duplicate_section ) {
		if (
			$token->flags & WP_SQLite_Token::FLAG_KEYWORD_FUNCTION &&
			'VALUES' === $token->keyword &&
			$is_in_duplicate_section
		) {
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
		return false;
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
			$token->flags & WP_SQLite_Token::FLAG_KEYWORD_FUNCTION &&
			'DATE_FORMAT' === $token->keyword
		) {
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
		return false;
	}

	/**
	 * Translate INTERVAL keyword with DATE_ADD() and DATE_SUB().
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_interval( $token ) {
		if ( 'INTERVAL' === $token->keyword ) {
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
		return false;
	}

	/**
	 * Translate REGEXP and RLIKE keywords.
	 *
	 * @param WP_SQLite_Token $token The token to translate.
	 *
	 * @return bool
	 */
	private function translate_regexp_functions( $token ) {
		if ( 'REGEXP' === $token->keyword || 'RLIKE' === $token->keyword ) {
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
		return false;
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
		$stmt = $this->pdo->prepare( 'SELECT * FROM pragma_table_info(:table_name) as l WHERE l.pk > 0;' );
		$stmt->execute( array( 'table_name' => $table_name ) );
		return $stmt->fetchAll();
	}

	/**
	 * Get the keys for a table.
	 *
	 * @param string $table_name  Table name.
	 * @param bool   $only_unique Only return unique keys.
	 *
	 * @return array
	 */
	private function get_keys( $table_name, $only_unique = false ) {
		$query   = $this->pdo->query( 'SELECT * FROM pragma_index_list("' . $table_name . '") as l;' );
		$indices = $query->fetchAll();
		$results = array();
		foreach ( $indices as $index ) {
			if ( ! $only_unique || '1' === $index['unique'] ) {
				$query     = $this->pdo->query( 'SELECT * FROM pragma_index_info("' . $index['name'] . '") as l;' );
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
		$stmt = $this->pdo->prepare( 'SELECT sql FROM sqlite_master WHERE type="table" AND name=:table' );
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
	 *
	 * @return stdClass
	 */
	private function translate_alter() {
		$this->rewriter->consume();
		$subject = strtolower( $this->rewriter->consume()->token );
		if ( 'table' !== $subject ) {
			throw new Exception( 'Unknown subject: ' . $subject );
		}

		$table_name = $this->normalize_column_name( $this->rewriter->consume()->token );
		$queries    = array();
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
					new WP_SQLite_Token( $table_name, WP_SQLite_Token::TYPE_KEYWORD ),
				)
			);
			$op_type          = strtoupper( $this->rewriter->consume()->token );
			$op_subject       = strtoupper( $this->rewriter->consume()->token );
			$mysql_index_type = $this->normalize_mysql_index_type( $op_subject );
			$is_index_op      = ! ! $mysql_index_type;

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
				$queries[] = $this->update_data_type_cache(
					$table_name,
					$column_name,
					$mysql_data_type
				);
			} elseif ( 'DROP' === $op_type && 'COLUMN' === $op_subject ) {
				$this->rewriter->consume_all();
			} elseif ( 'CHANGE' === $op_type && 'COLUMN' === $op_subject ) {
				if ( count( $queries ) ) {
					/*
					 * Mixing CHANGE COLUMN with other operations would require keeping track of the
					 * original table schema, and then applying the changes in order. This is not
					 * currently supported.
					 *
					 * Ideally, each ALTER TABLE operation would be flushed before the next one is
					 * processed, but that's not currently the case.
					 */
					throw new Exception(
						'Mixing CHANGE COLUMN with other operations in a single ALTER TABLE ' .
						'query is not supported yet.'
					);
				}
				// Parse the new column definition.
				$from_name        = $this->normalize_column_name( $this->rewriter->skip()->token );
				$new_field        = $this->parse_mysql_create_table_field();
				$alter_terminator = end( $this->rewriter->output_tokens );
				$queries[]        = $this->update_data_type_cache(
					$table_name,
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
				$old_schema  = $this->get_sqlite_create_table( $table_name );
				$old_indexes = $this->get_keys( $table_name, false );

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
				$cache_table_name = "_tmp__{$table_name}_" . rand( 10000000, 99999999 );
				$queries[]        = WP_SQLite_Translator::get_query_object(
					"CREATE TABLE `$cache_table_name` as SELECT * FROM `$table_name`"
				);

				// 4. Drop the old table to free up the indexes names
				$queries[] = WP_SQLite_Translator::get_query_object(
					"DROP TABLE `$table_name`"
				);

				// 5. Create a new table from the updated schema
				$queries[] = WP_SQLite_Translator::get_query_object(
					$create_table->get_updated_query()
				);

				// 6. Copy the data from step 3 to the new table
				$queries[] = WP_SQLite_Translator::get_query_object(
					"INSERT INTO {$table_name} SELECT * FROM $cache_table_name"
				);

				// 7. Drop the old table copy
				$queries[] = WP_SQLite_Translator::get_query_object(
					"DROP TABLE `$cache_table_name`"
				);

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
					$queries[] = WP_SQLite_Translator::get_query_object(
						"CREATE $unique INDEX IF NOT EXISTS `{$row['index']['name']}` ON $table_name (" . implode( ', ', $columns ) . ')'
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
				$sqlite_index_name = "{$table_name}__$key_name";
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
						new WP_SQLite_Token( '"' . $table_name . '"', WP_SQLite_Token::TYPE_STRING, WP_SQLite_Token::FLAG_STRING_DOUBLE_QUOTES ),
						new WP_SQLite_Token( ' ', WP_SQLite_Token::TYPE_WHITESPACE ),
						new WP_SQLite_Token( '(', WP_SQLite_Token::TYPE_OPERATOR ),
					)
				);
				$queries[] = $this->update_data_type_cache(
					$table_name,
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
						new WP_SQLite_Token( "\"{$table_name}__$key_name\"", WP_SQLite_Token::TYPE_KEYWORD, WP_SQLite_Token::FLAG_KEYWORD_KEY ),
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
			$queries[] = WP_SQLite_Translator::get_query_object(
				$this->rewriter->get_updated_query()
			);
		} while ( $comma );

		return $this->get_translation_result( $queries );
	}

	/**
	 * Translates a CREATE query.
	 *
	 * @throws Exception If the query is an unknown create type.
	 * @return stdClass The translation result.
	 */
	private function translate_create() {
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
				return $this->translate_create_table();

			case 'PROCEDURE':
			case 'DATABASE':
				return $this->get_translation_result( array( $this->noop() ) );

			default:
				throw new Exception( 'Unknown create type: ' . $what );
		}
	}

	/**
	 * Translates a DROP query.
	 *
	 * @throws Exception If the query is an unknown drop type.
	 *
	 * @return stdClass The translation result.
	 */
	private function translate_drop() {
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
				return $this->get_translation_result( array( WP_SQLite_Translator::get_query_object( $this->rewriter->get_updated_query() ) ) );

			case 'PROCEDURE':
			case 'DATABASE':
				return $this->get_translation_result( array( $this->noop() ) );

			default:
				throw new Exception( 'Unknown drop type: ' . $what );
		}
	}

	/**
	 * Translates a SHOW query.
	 *
	 * @throws Exception If the query is an unknown show type.
	 * @return stdClass The translation result.
	 */
	private function translate_show() {
		$this->rewriter->skip();
		$what1 = $this->rewriter->consume()->token;
		$what2 = $this->rewriter->consume()->token;
		$what  = $what1 . ' ' . $what2;
		switch ( $what ) {
			case 'CREATE PROCEDURE':
				return $this->get_translation_result( array( $this->noop() ) );

			case 'FULL COLUMNS':
				$this->rewriter->consume();
				$table_name = $this->rewriter->consume()->token;
				return $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object(
							"PRAGMA table_info($table_name);"
						),
					)
				);

			case 'COLUMNS FROM':
				$table_name = $this->rewriter->consume()->token;
				return $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object(
							"PRAGMA table_info(\"$table_name\");"
						),
					)
				);

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
				return $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object(
							'SELECT 1=1;'
						),
					),
					true,
					$results
				);

			case 'TABLES LIKE':
				$table_expression = $this->rewriter->skip();
				return $this->get_translation_result(
					array(
						WP_SQLite_Translator::get_query_object(
							"SELECT `name` as `Tables_in_db` FROM `sqlite_master` WHERE `type`='table' AND `name` LIKE :param;",
							array(
								':param' => $table_expression->value,
							)
						),
					)
				);

			default:
				switch ( $what1 ) {
					case 'TABLES':
						return $this->get_translation_result(
							array(
								WP_SQLite_Translator::get_query_object(
									"SELECT name FROM sqlite_master WHERE type='table'"
								),
							)
						);

					case 'VARIABLE':
					case 'VARIABLES':
						return $this->get_translation_result(
							array(
								$this->noop(),
							)
						);

					default:
						throw new Exception( 'Unknown show type: ' . $what );
				}
		}
	}

	/**
	 * Returns a dummy `SELECT 1=1` query object.
	 *
	 * @return stdClass The dummy query object.
	 */
	private function noop() {
		return WP_SQLite_Translator::get_query_object(
			'SELECT 1 WHERE 1=0;',
			array()
		);
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

		// Skip the unsigned keyword.
		$unsigned_maybe = $this->rewriter->peek();
		if ( $unsigned_maybe && $unsigned_maybe->matches(
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
	 * @return stdClass The query object.
	 */
	private function update_data_type_cache( $table, $column_or_index, $mysql_data_type ) {
		return WP_SQLite_Translator::get_query_object(
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
		$stmt = $this->pdo->prepare(
			'SELECT d.`mysql_type` FROM ' . self::DATA_TYPES_CACHE_TABLE . ' d
			WHERE `table`=:table
			AND `column_or_index` = :index',
		);
		$stmt->execute(
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
	 * Error handler.
	 *
	 * @param Exception $err Exception object.
	 *
	 * @return bool Always false.
	 */
	private function handle_error( Exception $err ) {
		$message     = $err->getMessage();
		$err_message = sprintf( 'Problem preparing the PDO SQL Statement. Error was: %s. trace: %s', $message, $err->getTraceAsString() );
		$this->set_error( __LINE__, __FUNCTION__, $err_message );
		$this->return_value = false;
		return false;
	}

	/**
	 * Method to format the error messages and put out to the file.
	 *
	 * When $wpdb::suppress_errors is set to true or $wpdb::show_errors is set to false,
	 * the error messages are ignored.
	 *
	 * @param string $line     Where the error occurred.
	 * @param string $function Indicate the function name where the error occurred.
	 * @param string $message  The message.
	 *
	 * @return boolean|void
	 */
	private function set_error( $line, $function, $message ) {
		$this->errors[]         = array(
			'line'     => $line,
			'function' => $function,
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
		$output .= '<div class="queries" style="clear:both;margin_bottom:2px;border:red dotted thin;">' . PHP_EOL;
		$output .= '<p>Queries made or created this session were:</p>' . PHP_EOL;
		$output .= '<ol>' . PHP_EOL;
		foreach ( $this->queries as $q ) {
			$output .= '<li>' . htmlspecialchars( $q ) . '</li>' . PHP_EOL;
		}
		$output .= '</ol>' . PHP_EOL;
		$output .= '</div>' . PHP_EOL;
		foreach ( $this->error_messages as $num => $m ) {
			$output .= '<div style="clear:both;margin_bottom:2px;border:red dotted thin;" class="error_message" style="border-bottom:dotted blue thin;">' . PHP_EOL;
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
			$output .= '<pre>' . htmlspecialchars( $e->getTraceAsString() ) . '</pre>' . PHP_EOL;
		}

		return $output;
	}

	/**
	 * Method to clear previous data.
	 */
	private function flush() {
		$this->rewritten_query = '';
		$this->results         = null;
		$this->last_insert_id  = null;
		$this->affected_rows   = null;
		$this->column_data     = array();
		$this->num_rows        = null;
		$this->return_value    = null;
		$this->error_messages  = array();
		$this->is_error        = false;
		$this->queries         = array();
		$this->param_num       = 0;
	}

	/**
	 * Method to call PDO::beginTransaction().
	 *
	 * @see PDO::beginTransaction()
	 * @return boolean
	 */
	public function beginTransaction() {
		if ( $this->has_active_transaction ) {
			return false;
		}
		$this->has_active_transaction = $this->pdo->beginTransaction();
		return $this->has_active_transaction;
	}

	/**
	 * Method to call PDO::commit().
	 *
	 * @see PDO::commit()
	 *
	 * @return void
	 */
	public function commit() {
		if ( $this->has_active_transaction ) {
			$this->pdo->commit();
			$this->has_active_transaction = false;
		}
	}

	/**
	 * Method to call PDO::rollBack().
	 *
	 * @see PDO::rollBack()
	 *
	 * @return void
	 */
	public function rollBack() {
		if ( $this->has_active_transaction ) {
			$this->pdo->rollBack();
			$this->has_active_transaction = false;
		}
	}
}
