<?php

/**
 * This class provides a function to rewrite CREATE query.
 *
 */
class WP_SQLite_Create_Query {

	/**
	 * The query string to be rewritten in this class.
	 *
	 * @var string
	 * @access private
	 */
	private $_query = '';
	/**
	 * The array to contain CREATE INDEX queries.
	 *
	 * @var array of strings
	 * @access private
	 */
	private $index_queries = array();
	/**
	 * The array to contain error messages.
	 *
	 * @var array of string
	 * @access private
	 */
	private $_errors = array();
	/**
	 * Variable to have the table name to be executed.
	 *
	 * @var string
	 * @access private
	 */
	private $table_name = '';
	/**
	 * Variable to check if the query has the primary key.
	 *
	 * @var boolean
	 * @access private
	 */
	private $has_primary_key = false;

	/**
	 * Function to rewrite query.
	 *
	 * @param string $query the query being processed
	 *
	 * @return string|array    the processed (rewritten) query
	 */
	public function rewrite_query( $query ) {
		$this->_query     = $query;
		$this->_errors [] = '';
		if ( preg_match( '/^CREATE\\s*(UNIQUE|FULLTEXT|)\\s*INDEX/ims', $this->_query, $match ) ) {
			// we manipulate CREATE INDEX query in WP_PDO_Engine.class.php
			// FULLTEXT index creation is simply ignored.
			if ( isset( $match[1] ) && stripos( $match[1], 'fulltext' ) !== false ) {
				return 'SELECT 1=1';
			}
			return $this->_query;
		}
		if ( preg_match( '/^CREATE\\s*(TEMP|TEMPORARY|)\\s*TRIGGER\\s*/im', $this->_query ) ) {
			// if WordPress comes to use foreign key constraint, trigger will be needed.
			// we don't use it for now.
			return $this->_query;
		}
		$this->strip_backticks();
		$this->quote_illegal_field();
		$this->get_table_name();
		$this->rewrite_comments();
		$this->rewrite_field_types();
		$this->rewrite_character_set();
		$this->rewrite_engine_info();
		$this->rewrite_unsigned();
		$this->rewrite_autoincrement();
		$this->rewrite_primary_key();
		$this->rewrite_foreign_key();
		$this->rewrite_unique_key();
		$this->rewrite_enum();
		$this->rewrite_set();
		$this->rewrite_key();
		$this->add_if_not_exists();

		return $this->post_process();
	}

	/**
	 * Method to get table name from the query string.
	 *
	 * 'IF NOT EXISTS' clause is removed for the easy regular expression usage.
	 * It will be added at the end of the process.
	 *
	 * @access private
	 */
	private function get_table_name() {
		// $pattern = '/^\\s*CREATE\\s*(TEMP|TEMPORARY)?\\s*TABLE\\s*(IF NOT EXISTS)?\\s*([^\(]*)/imsx';
		$pattern = '/^\\s*CREATE\\s*(?:TEMP|TEMPORARY)?\\s*TABLE\\s*(?:IF\\s*NOT\\s*EXISTS)?\\s*([^\(]*)/imsx';
		if ( preg_match( $pattern, $this->_query, $matches ) ) {
			$this->table_name = trim( $matches[1] );
		}
	}

	/**
	 * Method to change the MySQL field types to SQLite compatible types.
	 *
	 * If column name is the same as the key value, e.g. "date" or "timestamp",
	 * and the column is on the top of the line, we add a single quote and avoid
	 * to be replaced. But this doesn't work if that column name is in the middle
	 * of the line.
	 * Order of the key value is important. Don't change it.
	 *
	 * @access private
	 */
	private function rewrite_field_types() {
		$array_types = array(
			'bit'        => 'integer',
			'bool'       => 'integer',
			'boolean'    => 'integer',
			'tinyint'    => 'integer',
			'smallint'   => 'integer',
			'mediumint'  => 'integer',
			'int'        => 'integer',
			'integer'    => 'integer',
			'bigint'     => 'integer',
			'float'      => 'real',
			'double'     => 'real',
			'decimal'    => 'real',
			'dec'        => 'real',
			'numeric'    => 'real',
			'fixed'      => 'real',
			'date'       => 'text',
			'datetime'   => 'text',
			'timestamp'  => 'text',
			'time'       => 'text',
			'year'       => 'text',
			'char'       => 'text',
			'varchar'    => 'text',
			'binary'     => 'integer',
			'varbinary'  => 'blob',
			'tinyblob'   => 'blob',
			'tinytext'   => 'text',
			'blob'       => 'blob',
			'text'       => 'text',
			'mediumblob' => 'blob',
			'mediumtext' => 'text',
			'longblob'   => 'blob',
			'longtext'   => 'text',
		);
		foreach ( $array_types as $o => $r ) {
			if ( preg_match( "/^\\s*(?<!')$o\\s+(.+$)/im", $this->_query, $match ) ) {
				$ptrn         = "/$match[1]/im";
				$replaced     = str_ireplace( $ptrn, '#placeholder#', $this->_query );
				$replaced     = str_ireplace( $o, "'{$o}'", $replaced );
				$this->_query = str_replace( '#placeholder#', $ptrn, $replaced );
			}
			$pattern = "/\\b(?<!')$o\\b\\s*(\([^\)]*\)*)?\\s*/ims";
			if ( preg_match( "/^\\s*.*?\\s*\(.*?$o.*?\)/im", $this->_query ) ) {
				// ;
			} else {
				$this->_query = preg_replace( $pattern, " $r ", $this->_query );
			}
		}
	}

	/**
	 * Method for stripping the comments from the SQL statement.
	 *
	 * @access private
	 */
	private function rewrite_comments() {
		$this->_query = preg_replace(
			'/# --------------------------------------------------------/',
			'-- ******************************************************',
			$this->_query
		);
		$this->_query = preg_replace( '/#/', '--', $this->_query );
	}

	/**
	 * Method for stripping the engine and other stuffs.
	 *
	 * TYPE, ENGINE and AUTO_INCREMENT are removed here.
	 * @access private
	 */
	private function rewrite_engine_info() {
		$this->_query = preg_replace( '/\\s*(TYPE|ENGINE)\\s*=\\s*.*(?<!;)/ims', '', $this->_query );
		$this->_query = preg_replace( '/ AUTO_INCREMENT\\s*=\\s*[0-9]*/ims', '', $this->_query );
	}

	/**
	 * Method for stripping unsigned.
	 *
	 * SQLite doesn't have unsigned int data type. So UNSIGNED INT(EGER) is converted
	 * to INTEGER here.
	 *
	 * @access private
	 */
	private function rewrite_unsigned() {
		$this->_query = preg_replace( '/\\bunsigned\\b/ims', ' ', $this->_query );
	}

	/**
	 * Method for rewriting primary key auto_increment.
	 *
	 * If the field type is 'INTEGER PRIMARY KEY', it is automatically autoincremented
	 * by SQLite. There's a little difference between PRIMARY KEY and AUTOINCREMENT, so
	 * we may well convert to PRIMARY KEY only.
	 *
	 * @access private
	 */
	private function rewrite_autoincrement() {
		$this->_query = preg_replace(
			'/\\bauto_increment\\s*primary\\s*key\\s*(,)?/ims',
			' PRIMARY KEY AUTOINCREMENT \\1',
			$this->_query,
			-1,
			$count
		);
		$this->_query = preg_replace(
			'/\\bauto_increment\\b\\s*(,)?/ims',
			' PRIMARY KEY AUTOINCREMENT $1',
			$this->_query,
			-1,
			$count
		);
		if ( $count > 0 ) {
			$this->has_primary_key = true;
		}
	}

	/**
	 * Method for rewriting primary key.
	 *
	 * @access private
	 */
	private function rewrite_primary_key() {
		if ( $this->has_primary_key ) {
			$this->_query = preg_replace( '/\\s*primary key\\s*.*?\([^\)]*\)\\s*(,|)/i', ' ', $this->_query );
		} else {
			// If primary key has an index name, we remove that name.
			$this->_query = preg_replace( '/\\bprimary\\s*key\\s*.*?\\s*(\(.*?\))/im', 'PRIMARY KEY \\1', $this->_query );
		}
	}

	/**
	 * Method for rewriting foreign key.
	 *
	 * @access private
	 */
	private function rewrite_foreign_key() {
		$pattern = '/\\s*foreign\\s*key\\s*(|.*?)\([^\)]+?\)\\s*references\\s*.*/i';
		if ( preg_match_all( $pattern, $this->_query, $match ) ) {
			if ( isset( $match[1] ) ) {
				$this->_query = str_ireplace( $match[1], '', $this->_query );
			}
		}
	}

	/**
	 * Method for rewriting unique key.
	 *
	 * @access private
	 */
	private function rewrite_unique_key() {
		$this->_query = preg_replace_callback(
			'/\\bunique key\\b([^\(]*)(\(.*\))/im',
			array( $this, '_rewrite_unique_key' ),
			$this->_query
		);
	}

	/**
	 * Callback method for rewrite_unique_key.
	 *
	 * @param array $matches an array of matches from the Regex
	 *
	 * @access private
	 * @return string
	 */
	private function _rewrite_unique_key( $matches ) {
		$index_name = trim( $matches[1] );
		$col_name   = trim( $matches[2] );
		$tbl_name   = $this->table_name;
		if ( preg_match( '/\(\\d+?\)/', $col_name ) ) {
			$col_name = preg_replace( '/\(\\d+?\)/', '', $col_name );
		}
		$_wpdb   = new WP_SQLite_DB();
		$results = $_wpdb->get_results( "SELECT name FROM sqlite_master WHERE type='index'" );
		$_wpdb   = null;
		if ( $results ) {
			foreach ( $results as $result ) {
				if ( $result->name === $index_name ) {
					$r          = rand( 0, 50 );
					$index_name = $index_name . "_$r";
					break;
				}
			}
		}
		$index_name            = str_replace( ' ', '', $index_name );
		$this->index_queries[] = "CREATE UNIQUE INDEX $index_name ON " . $tbl_name . $col_name;

		return '';
	}

	/**
	 * Method for handling ENUM fields.
	 *
	 * SQLite doesn't support enum, so we change it to check constraint.
	 *
	 * @access private
	 */
	private function rewrite_enum() {
		$pattern      = '/(,|\))([^,]*)enum\((.*?)\)([^,\)]*)/ims';
		$this->_query = preg_replace_callback( $pattern, array( $this, '_rewrite_enum' ), $this->_query );
	}

	/**
	 * Call back method for rewrite_enum() and rewrite_set().
	 *
	 * @access private
	 *
	 * @param $matches
	 *
	 * @return string
	 */
	private function _rewrite_enum( $matches ) {
		$output = $matches[1] . ' ' . $matches[2] . ' TEXT ' . $matches[4] . ' CHECK (' . $matches[2] . ' IN (' . $matches[3] . ')) ';

		return $output;
	}

	/**
	 * Method for rewriting usage of set.
	 *
	 * It is similar but not identical to enum. SQLite does not support either.
	 *
	 * @access private
	 */
	private function rewrite_set() {
		$pattern      = '/\b(\w)*\bset\\s*\((.*?)\)\\s*(.*?)(,)*/ims';
		$this->_query = preg_replace_callback( $pattern, array( $this, '_rewrite_enum' ), $this->_query );
	}

	/**
	 * Method for rewriting usage of key to create an index.
	 *
	 * SQLite cannot create non-unique indices as part of the create query,
	 * so we need to create an index by hand and append it to the create query.
	 *
	 * @access private
	 */
	private function rewrite_key() {
		$this->_query = preg_replace_callback(
			'/,\\s*(KEY|INDEX)\\s*(\\w+)?\\s*(\(.+\))/im',
			array( $this, '_rewrite_key' ),
			$this->_query
		);
	}

	/**
	 * Callback method for rewrite_key.
	 *
	 * @param array $matches an array of matches from the Regex
	 *
	 * @access private
	 * @return string
	 */
	private function _rewrite_key( $matches ) {
		$index_name = trim( $matches[2] );
		$col_name   = trim( $matches[3] );
		if ( preg_match( '/\([0-9]+?\)/', $col_name, $match ) ) {
			$col_name = preg_replace_callback( '/\([0-9]+?\)/', array( $this, '_remove_length' ), $col_name );
		}
		$tbl_name = $this->table_name;
		$_wpdb    = new WP_SQLite_DB();
		$results  = $_wpdb->get_results( "SELECT name FROM sqlite_master WHERE type='index'" );
		$_wpdb    = null;
		if ( $results ) {
			foreach ( $results as $result ) {
				if ( $result->name === $index_name ) {
					$r          = rand( 0, 50 );
					$index_name = $index_name . "_$r";
					break;
				}
			}
		}
		$this->index_queries[] = 'CREATE INDEX ' . $index_name . ' ON ' . $tbl_name . $col_name;

		return '';
	}

	/**
	 * Call back method to remove unnecessary string.
	 *
	 * This method is deprecated.
	 *
	 * @param string $match
	 *
	 * @return string whose length is zero
	 * @access private
	 */
	private function _remove_length( $match ) {
		return '';
	}

	/**
	 * Method to assemble the main query and index queries into an array.
	 *
	 * It return the array of the queries to be executed separately.
	 *
	 * @return array
	 * @access private
	 */
	private function post_process() {
		$mainquery = $this->_query;
		do {
			$count     = 0;
			$mainquery = preg_replace( '/,\\s*\)/imsx', ')', $mainquery, -1, $count );
		} while ( $count > 0 );
		do {
			$count     = 0;
			$mainquery = preg_replace( '/\(\\s*?,/imsx', '(', $mainquery, -1, $count );
		} while ( $count > 0 );
		$return_val[] = $mainquery;
		$return_val   = array_merge( $return_val, $this->index_queries );

		return $return_val;
	}

	/**
	 * Method to add IF NOT EXISTS to query string.
	 *
	 * This adds IF NOT EXISTS to every query string, which prevent the exception
	 * from being thrown.
	 *
	 * @access private
	 */
	private function add_if_not_exists() {
		$pattern_table = '/^\\s*CREATE\\s*(TEMP|TEMPORARY)?\\s*TABLE\\s*(IF NOT EXISTS)?\\s*/ims';
		$this->_query  = preg_replace( $pattern_table, 'CREATE $1 TABLE IF NOT EXISTS ', $this->_query );
		$pattern_index = '/^\\s*CREATE\\s*(UNIQUE)?\\s*INDEX\\s*(IF NOT EXISTS)?\\s*/ims';
		for ( $i = 0; $i < count( $this->index_queries ); $i++ ) {
			$this->index_queries[ $i ] = preg_replace(
				$pattern_index,
				'CREATE $1 INDEX IF NOT EXISTS ',
				$this->index_queries[ $i ]
			);
		}
	}

	/**
	 * Method to strip back quotes.
	 *
	 * @access private
	 */
	private function strip_backticks() {
		$this->_query = str_replace( '`', '', $this->_query );
		foreach ( $this->index_queries as &$query ) {
			$query = str_replace( '`', '', $query );
		}
	}

	/**
	 * Method to remove the character set information from within mysql queries.
	 *
	 * This removes DEFAULT CHAR(ACTER) SET and COLLATE, which is meaningless for
	 * SQLite.
	 *
	 * @access private
	 */
	private function rewrite_character_set() {
		$pattern_charset  = '/\\b(default\\s*character\\s*set|default\\s*charset|character\\s*set)\\s*(?<!\()[^ ]*/im';
		$pattern_collate1 = '/\\s*collate\\s*[^ ]*(?=,)/im';
		$pattern_collate2 = '/\\s*collate\\s*[^ ]*(?<!;)/im';
		$patterns         = array( $pattern_charset, $pattern_collate1, $pattern_collate2 );
		$this->_query     = preg_replace( $patterns, '', $this->_query );
	}

	/**
	 * Method to quote illegal field name for SQLite
	 *
	 * @access private
	 */
	private function quote_illegal_field() {
		$this->_query = preg_replace( "/^\\s*(?<!')(default|values)/im", "'\\1'", $this->_query );
	}
}
