<?php
/**
 * Extend and replace the wpdb class.
 *
 * @package wp-sqlite-integration
 * @since 1.0.0
 */

/**
 * This class extends wpdb and replaces it.
 *
 * It also rewrites some methods that use mysql specific functions.
 */
class WP_SQLite_DB extends wpdb {

	/**
	 * Database Handle
	 *
	 * @access protected
	 *
	 * @var WP_SQLite_Translator
	 */
	protected $dbh;

	/**
	 * Constructor
	 *
	 * Unlike wpdb, no credentials are needed.
	 */
	public function __construct() {
		parent::__construct( '', '', '', '' );
		$this->charset = 'utf8mb4';
	}

	/**
	 * Method to set character set for the database.
	 *
	 * This overrides wpdb::set_charset(), only to dummy out the MySQL function.
	 *
	 * @see wpdb::set_charset()
	 *
	 * @param resource $dbh The resource given by mysql_connect.
	 * @param string   $charset Optional. The character set. Default null.
	 * @param string   $collate Optional. The collation. Default null.
	 */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
	}

	/**
	 * Method to get the character set for the database.
	 * Hardcoded to utf8mb4 for now.
	 *
	 * @param string $table  The table name.
	 * @param string $column The column name.
	 *
	 * @return string The character set.
	 */
	public function get_col_charset( $table, $column ) {
		// Hardcoded for now.
		return 'utf8mb4';
	}

	/**
	 * Method to dummy out wpdb::set_sql_mode()
	 *
	 * @see wpdb::set_sql_mode()
	 *
	 * @param array $modes Optional. A list of SQL modes to set.
	 */
	public function set_sql_mode( $modes = array() ) {
	}

	/**
	 * Closes the current database connection.
	 * Noop in SQLite.
	 *
	 * @return bool True to indicate the connection was successfully closed.
	 */
	public function close() {
		return true;
	}

	/**
	 * Method to select the database connection.
	 *
	 * This overrides wpdb::select(), only to dummy out the MySQL function.
	 *
	 * @see wpdb::select()
	 *
	 * @param string        $db  MySQL database name. Not used.
	 * @param resource|null $dbh Optional link identifier.
	 */
	public function select( $db, $dbh = null ) {
		$this->ready = true;
	}

	/**
	 * Method to escape characters.
	 *
	 * This overrides wpdb::_real_escape() to avoid using mysql_real_escape_string().
	 *
	 * @see wpdb::_real_escape()
	 *
	 * @param string $str The string to escape.
	 *
	 * @return string escaped
	 */
	function _real_escape( $str ) {
		return addslashes( $str );
	}

	/**
	 * Method to dummy out wpdb::esc_like() function.
	 *
	 * WordPress 4.0.0 introduced esc_like() function that adds backslashes to %,
	 * underscore and backslash, which is not interpreted as escape character
	 * by SQLite. So we override it and dummy out this function.
	 *
	 * @param string $text The raw text to be escaped. The input typed by the user should have no
	 *                     extra or deleted slashes.
	 *
	 * @return string Text in the form of a LIKE phrase. The output is not SQL safe. Call $wpdb::prepare()
	 *                or real_escape next.
	 */
	public function esc_like( $text ) {
		return $text;
	}

	/**
	 * Method to put out the error message.
	 *
	 * This overrides wpdb::print_error(), for we can't use the parent class method.
	 *
	 * @see wpdb::print_error()
	 *
	 * @global array $EZSQL_ERROR Stores error information of query and error string.
	 *
	 * @param string $str The error to display.
	 *
	 * @return bool|void False if the showing of errors is disabled.
	 */
	public function print_error( $str = '' ) {
		global $EZSQL_ERROR;

		if ( ! $str ) {
			$err = $this->dbh->get_error_message() ? $this->dbh->get_error_message() : '';
			$str = empty( $err ) ? '' : $err[2];
		}
		$EZSQL_ERROR[] = array(
			'query'     => $this->last_query,
			'error_str' => $str,
		);

		if ( $this->suppress_errors ) {
			return false;
		}

		wp_load_translations_early();

		$caller = $this->get_caller();
		$caller = $caller ? $caller : '(unknown)';

		$error_str = sprintf(
			'WordPress database error %1$s for query %2$s made by %3$s',
			$str,
			$this->last_query,
			$caller
		);

		error_log( $error_str );

		if ( ! $this->show_errors ) {
			return false;
		}

		if ( is_multisite() ) {
			$msg = "WordPress database error: [$str]\n{$this->last_query}\n";
			if ( defined( 'ERRORLOGFILE' ) ) {
				error_log( $msg, 3, ERRORLOGFILE );
			}
			if ( defined( 'DIEONDBERROR' ) ) {
				wp_die( $msg );
			}
		} else {
			$str   = htmlspecialchars( $str, ENT_QUOTES );
			$query = htmlspecialchars( $this->last_query, ENT_QUOTES );

			printf(
				'<div id="error"><p class="wpdberror">WordPress database error: [%1$s] %2$s</p></div>',
				$str,
				'<code>' . $query . '</code>'
			);
		}
	}

	/**
	 * Method to flush cached data.
	 *
	 * This overrides wpdb::flush(). This is not necessarily overridden, because
	 * $result will never be resource.
	 *
	 * @see wpdb::flush
	 */
	public function flush() {
		$this->last_result   = array();
		$this->col_info      = null;
		$this->last_query    = null;
		$this->rows_affected = 0;
		$this->num_rows      = 0;
		$this->last_error    = '';
		$this->result        = null;
	}

	/**
	 * Method to do the database connection.
	 *
	 * This overrides wpdb::db_connect() to avoid using MySQL function.
	 *
	 * @see wpdb::db_connect()
	 *
	 * @param bool $allow_bail Not used.
	 * @return void
	 */
	public function db_connect( $allow_bail = true ) {
		if ( $this->dbh ) {
			return;
		}
		$this->init_charset();

		$pdo = null;
		if ( isset( $GLOBALS['@pdo'] ) ) {
			$pdo = $GLOBALS['@pdo'];
		}
		$this->dbh        = new WP_SQLite_Translator( $pdo );
		$this->last_error = $this->dbh->get_error_message();
		if ( $this->last_error ) {
			return false;
		}
		$GLOBALS['@pdo'] = $this->dbh->get_pdo();
		$this->ready     = true;
	}

	/**
	 * Method to dummy out wpdb::check_connection()
	 *
	 * @param bool $allow_bail Not used.
	 *
	 * @return bool
	 */
	public function check_connection( $allow_bail = true ) {
		return true;
	}

	/**
	 * Method to execute the query.
	 *
	 * This overrides wpdb::query(). In fact, this method does all the database
	 * access jobs.
	 *
	 * @see wpdb::query()
	 *
	 * @param string $query Database query.
	 *
	 * @return int|false Number of rows affected/selected or false on error
	 */
	public function query( $query ) {
		if ( ! $this->ready ) {
			return false;
		}

		$query = apply_filters( 'query', $query );

		$this->flush();

		$this->func_call  = "\$db->query(\"$query\")";
		$this->last_query = $query;

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$this->timer_start();
		}

		$this->result = $this->dbh->query( $query );
		$this->num_queries++;

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );
		}

		$this->last_error = $this->dbh->get_error_message();
		if ( $this->last_error ) {
			$this->print_error( $this->last_error );
			return false;
		}

		if ( preg_match( '/^\\s*(set|create|alter|truncate|drop|optimize)\\s*/i', $query ) ) {
			return $this->dbh->get_return_value();
		}

		if ( preg_match( '/^\\s*(insert|delete|update|replace)\s/i', $query ) ) {
			$this->rows_affected = $this->dbh->get_affected_rows();
			if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
				$this->insert_id = $this->dbh->get_insert_id();
			}
			return $this->rows_affected;
		}

		$this->last_result = $this->dbh->get_query_results();
		$this->num_rows    = $this->dbh->get_num_rows();
		return $this->num_rows;
	}

	/**
	 * Method to set the class variable $col_info.
	 *
	 * This overrides wpdb::load_col_info(), which uses a mysql function.
	 *
	 * @see    wpdb::load_col_info()
	 * @access protected
	 */
	protected function load_col_info() {
		if ( $this->col_info ) {
			return;
		}
		$this->col_info = $this->dbh->get_columns();
	}

	/**
	 * Method to return what the database can do.
	 *
	 * This overrides wpdb::has_cap() to avoid using MySQL functions.
	 * SQLite supports subqueries, but not support collation, group_concat and set_charset.
	 *
	 * @see wpdb::has_cap()
	 *
	 * @param string $db_cap The feature to check for. Accepts 'collation',
	 *                       'group_concat', 'subqueries', 'set_charset',
	 *                       'utf8mb4', or 'utf8mb4_520'.
	 *
	 * @return bool Whether the database feature is supported, false otherwise.
	 */
	public function has_cap( $db_cap ) {
		return 'subqueries' === strtolower( $db_cap );
	}

	/**
	 * Method to return database version number.
	 *
	 * This overrides wpdb::db_version() to avoid using MySQL function.
	 * It returns mysql version number, but it means nothing for SQLite.
	 * So it return the newest mysql version.
	 *
	 * @see wpdb::db_version()
	 */
	public function db_version() {
		return '5.5';
	}

	/**
	 * Retrieves full database server information.
	 *
	 * @return string|false Server info on success, false on failure.
	 */
	public function db_server_info() {
		return SQLite3::version()['versionString'];
	}
}
