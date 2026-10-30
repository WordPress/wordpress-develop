<?php

/*
 * The SQLite driver uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 *
 * PDO uses camel case naming, enable non-snake case:
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * PDO uses $string as a parameter name, enable it:
 * phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
 *
 * We conditionally define a trait for PHP-version-specific PDO methods:
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 */

/*
 * The "PDO::connect()" method in PHP 8.4 uses a "static" return type declaration,
 * which PHP 7 cannot parse. Therefore, a conditional file import is needed.
 */
if ( PHP_VERSION_ID >= 80400 ) {
	require_once __DIR__ . '/trait-wp-mysql-on-sqlite-pdo-compat-php-84.php';
} else {
	/**
	 * @access private
	 */
	trait WP_MySQL_On_SQLite_PDO_Compat {}
}

/**
 * SQLite driver for MySQL.
 *
 * This class emulates a MySQL database server on top of an SQLite database.
 * It translates queries written in MySQL SQL dialect to an SQLite SQL dialect,
 * maintains necessary metadata, and executes the translated queries in SQLite.
 *
 * The driver requires PDO with the SQLite driver, and the PCRE engine.
 */
class WP_MySQL_On_SQLite extends PDO {
	use WP_MySQL_On_SQLite_PDO_Compat;

	/**
	 * The path to the MySQL SQL grammar file.
	 */
	private const MYSQL_GRAMMAR_PATH = __DIR__ . '/../mysql-parser/mysql-grammar.php';

	/**
	 * The minimum supported MySQL version.
	 */
	private const MINIMUM_MYSQL_VERSION = 50700;

	/**
	 * The default MySQL version to emulate.
	 */
	const DEFAULT_MYSQL_VERSION = 80038;

	/**
	 * The minimum required version of SQLite.
	 *
	 * Currently, we require SQLite >= 3.37.0 due to the STRICT table support:
	 *   https://www.sqlite.org/stricttables.html
	 */
	const MINIMUM_SQLITE_VERSION = '3.37.0';

	/**
	 * An identifier prefix for internal database objects.
	 *
	 * @access private
	 *
	 * @TODO: Do not allow accessing objects with this prefix.
	 */
	const RESERVED_PREFIX = '_wp_sqlite_';

	/**
	 * The name of a global variables table.
	 *
	 * This special table is used to emulate MySQL global variables and to store
	 * some internal configuration values.
	 *
	 * @access private
	 */
	const GLOBAL_VARIABLES_TABLE_NAME = self::RESERVED_PREFIX . 'global_variables';

	/**
	 * The name of the SQLite driver version variable.
	 *
	 * This internal variable is used to store the latest version of the SQLite
	 * driver that was used to initialize and configure the SQLite database.
	 *
	 * @access private
	 */
	const DRIVER_VERSION_VARIABLE_NAME = self::RESERVED_PREFIX . 'driver_version';

	/**
	 * MySQL SQL modes mapped to their bitmask values.
	 *
	 * The modes are ordered by their bit position, matching MySQL's canonical
	 * serialization order.
	 *
	 * See:
	 *   https://github.com/mysql/mysql-server/blob/8.4/sql/system_variables.h
	 *   https://github.com/mysql/mysql-server/blob/5.7/sql/sys_vars.cc
	 */
	private const SQL_MODES = array(
		'REAL_AS_FLOAT'              => 1 << 0,
		'PIPES_AS_CONCAT'            => 1 << 1,
		'ANSI_QUOTES'                => 1 << 2,
		'IGNORE_SPACE'               => 1 << 3,
		'NOT_USED'                   => 1 << 4,
		'ONLY_FULL_GROUP_BY'         => 1 << 5,
		'NO_UNSIGNED_SUBTRACTION'    => 1 << 6,
		'NO_DIR_IN_CREATE'           => 1 << 7,
		'POSTGRESQL'                 => 1 << 8,
		'ORACLE'                     => 1 << 9,
		'MSSQL'                      => 1 << 10,
		'DB2'                        => 1 << 11,
		'MAXDB'                      => 1 << 12,
		'NO_KEY_OPTIONS'             => 1 << 13,
		'NO_TABLE_OPTIONS'           => 1 << 14,
		'NO_FIELD_OPTIONS'           => 1 << 15,
		'MYSQL323'                   => 1 << 16,
		'MYSQL40'                    => 1 << 17,
		'ANSI'                       => 1 << 18,
		'NO_AUTO_VALUE_ON_ZERO'      => 1 << 19,
		'NO_BACKSLASH_ESCAPES'       => 1 << 20,
		'STRICT_TRANS_TABLES'        => 1 << 21,
		'STRICT_ALL_TABLES'          => 1 << 22,
		'NO_ZERO_IN_DATE'            => 1 << 23,
		'NO_ZERO_DATE'               => 1 << 24,
		'ALLOW_INVALID_DATES'        => 1 << 25,
		'ERROR_FOR_DIVISION_BY_ZERO' => 1 << 26,
		'TRADITIONAL'                => 1 << 27,
		'NO_AUTO_CREATE_USER'        => 1 << 28,
		'HIGH_NOT_PRECEDENCE'        => 1 << 29,
		'NO_ENGINE_SUBSTITUTION'     => 1 << 30,
		'PAD_CHAR_TO_FULL_LENGTH'    => 1 << 31,

		// Modes below require 64-bit PHP.
		// TODO: Consider supporting these values on 32-bit PHP as well.
		'TIME_TRUNCATE_FRACTIONAL'   => 1 << 32,
	);

	/**
	 * The default SQL modes shared by MySQL 5.7 and 8.0.
	 *
	 * MySQL 5.7 additionally enables NO_AUTO_CREATE_USER.
	 */
	private const DEFAULT_SQL_MODES = array(
		'ERROR_FOR_DIVISION_BY_ZERO',
		'NO_ENGINE_SUBSTITUTION',
		'NO_ZERO_DATE',
		'NO_ZERO_IN_DATE',
		'ONLY_FULL_GROUP_BY',
		'STRICT_TRANS_TABLES',
	);

	/**
	 * A map of MySQL tokens to SQLite data types.
	 *
	 * This is used to translate a MySQL data type to an SQLite data type.
	 */
	private const DATA_TYPE_MAP = array(
		// Numeric data types:
		WP_MySQL_Lexer::BIT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::BOOL_SYMBOL               => 'INTEGER',
		WP_MySQL_Lexer::BOOLEAN_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::TINYINT_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::SMALLINT_SYMBOL           => 'INTEGER',
		WP_MySQL_Lexer::MEDIUMINT_SYMBOL          => 'INTEGER',
		WP_MySQL_Lexer::INT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::INTEGER_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::BIGINT_SYMBOL             => 'INTEGER',
		WP_MySQL_Lexer::FLOAT_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::DOUBLE_SYMBOL             => 'REAL',
		WP_MySQL_Lexer::REAL_SYMBOL               => 'REAL',
		WP_MySQL_Lexer::DECIMAL_SYMBOL            => 'REAL',
		WP_MySQL_Lexer::DEC_SYMBOL                => 'REAL',
		WP_MySQL_Lexer::FIXED_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::NUMERIC_SYMBOL            => 'REAL',

		// String data types:
		WP_MySQL_Lexer::CHAR_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::VARCHAR_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::NCHAR_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::NVARCHAR_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TINYTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TEXT_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::MEDIUMTEXT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::LONGTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::ENUM_SYMBOL               => 'TEXT',

		// Date and time data types:
		WP_MySQL_Lexer::DATE_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::TIME_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::DATETIME_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TIMESTAMP_SYMBOL          => 'TEXT',
		WP_MySQL_Lexer::YEAR_SYMBOL               => 'TEXT',

		// Binary data types:
		WP_MySQL_Lexer::BINARY_SYMBOL             => 'BLOB',
		WP_MySQL_Lexer::VARBINARY_SYMBOL          => 'BLOB',
		WP_MySQL_Lexer::TINYBLOB_SYMBOL           => 'BLOB',
		WP_MySQL_Lexer::BLOB_SYMBOL               => 'BLOB',
		WP_MySQL_Lexer::MEDIUMBLOB_SYMBOL         => 'BLOB',
		WP_MySQL_Lexer::LONGBLOB_SYMBOL           => 'BLOB',

		// Spatial data types:
		WP_MySQL_Lexer::GEOMETRY_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::POINT_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::LINESTRING_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::POLYGON_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::MULTIPOINT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::MULTILINESTRING_SYMBOL    => 'TEXT',
		WP_MySQL_Lexer::MULTIPOLYGON_SYMBOL       => 'TEXT',
		WP_MySQL_Lexer::GEOMCOLLECTION_SYMBOL     => 'TEXT',
		WP_MySQL_Lexer::GEOMETRYCOLLECTION_SYMBOL => 'TEXT',

		// SERIAL, SET, and JSON types are handled in the translation process.
	);

	/**
	 * A map of normalized MySQL data types to SQLite data types.
	 *
	 * This is used to generate SQLite CREATE TABLE statements from the MySQL
	 * INFORMATION_SCHEMA tables. They keys are MySQL data types normalized
	 * as they appear in the INFORMATION_SCHEMA. Values are SQLite data types.
	 */
	private const DATA_TYPE_STRING_MAP = array(
		// Numeric data types:
		'bit'                => 'INTEGER',
		'bool'               => 'INTEGER',
		'boolean'            => 'INTEGER',
		'tinyint'            => 'INTEGER',
		'smallint'           => 'INTEGER',
		'mediumint'          => 'INTEGER',
		'int'                => 'INTEGER',
		'integer'            => 'INTEGER',
		'bigint'             => 'INTEGER',
		'float'              => 'REAL',
		'double'             => 'REAL',
		'real'               => 'REAL',
		'decimal'            => 'REAL',
		'dec'                => 'REAL',
		'fixed'              => 'REAL',
		'numeric'            => 'REAL',

		// String data types:
		'char'               => 'TEXT',
		'varchar'            => 'TEXT',
		'nchar'              => 'TEXT',
		'nvarchar'           => 'TEXT',
		'tinytext'           => 'TEXT',
		'text'               => 'TEXT',
		'mediumtext'         => 'TEXT',
		'longtext'           => 'TEXT',
		'enum'               => 'TEXT',
		'set'                => 'TEXT',
		'json'               => 'TEXT',

		// Date and time data types:
		'date'               => 'TEXT',
		'time'               => 'TEXT',
		'datetime'           => 'TEXT',
		'timestamp'          => 'TEXT',
		'year'               => 'TEXT',

		// Binary data types:
		'binary'             => 'BLOB',
		'varbinary'          => 'BLOB',
		'tinyblob'           => 'BLOB',
		'blob'               => 'BLOB',
		'mediumblob'         => 'BLOB',
		'longblob'           => 'BLOB',

		// Spatial data types:
		'geometry'           => 'TEXT',
		'point'              => 'TEXT',
		'linestring'         => 'TEXT',
		'polygon'            => 'TEXT',
		'multipoint'         => 'TEXT',
		'multilinestring'    => 'TEXT',
		'multipolygon'       => 'TEXT',
		'geomcollection'     => 'TEXT',
		'geometrycollection' => 'TEXT',
	);

	/**
	 * A map of MySQL to SQLite date format translation.
	 *
	 * It maps MySQL DATE_FORMAT() formats to SQLite STRFTIME() formats.
	 *
	 * For MySQL formats, see:
	 *   https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-format
	 *
	 * For SQLite formats, see:
	 *   https://www.sqlite.org/lang_datefunc.html
	 *   https://strftime.org/
	 */
	private const MYSQL_DATE_FORMAT_TO_SQLITE_STRFTIME_MAP = array(
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
	 * Map MySQL INTERVAL units to SQLite units and value multipliers.
	 *
	 * MICROSECOND is excluded because DATETIME() drops fractional seconds.
	 * Composite units, such as HOUR_MINUTE, are not supported.
	 */
	private const INTERVAL_UNIT_MAP = array(
		WP_MySQL_Lexer::SECOND_SYMBOL  => array( 'SECOND', 1 ),
		WP_MySQL_Lexer::MINUTE_SYMBOL  => array( 'MINUTE', 1 ),
		WP_MySQL_Lexer::HOUR_SYMBOL    => array( 'HOUR', 1 ),
		WP_MySQL_Lexer::DAY_SYMBOL     => array( 'DAY', 1 ),
		WP_MySQL_Lexer::WEEK_SYMBOL    => array( 'DAY', 7 ),
		WP_MySQL_Lexer::MONTH_SYMBOL   => array( 'MONTH', 1 ),
		WP_MySQL_Lexer::QUARTER_SYMBOL => array( 'MONTH', 3 ),
		WP_MySQL_Lexer::YEAR_SYMBOL    => array( 'YEAR', 1 ),
	);

	/**
	 * A map of MySQL data types to implicit default values for non-strict mode.
	 *
	 * In MySQL, when STRICT_TRANS_TABLES and STRICT_ALL_TABLES modes are disabled,
	 * columns get IMPLICIT DEFAULT values that are used under some circumstances.
	 *
	 * See:
	 *   https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html#data-type-defaults-implicit
	 */
	private const DATA_TYPE_IMPLICIT_DEFAULT_MAP = array(
		// Numeric data types:
		'bit'                => '0',
		'bool'               => '0',
		'boolean'            => '0',
		'tinyint'            => '0',
		'smallint'           => '0',
		'mediumint'          => '0',
		'int'                => '0',
		'integer'            => '0',
		'bigint'             => '0',
		'float'              => '0',
		'double'             => '0',
		'real'               => '0',
		'decimal'            => '0',
		'dec'                => '0',
		'fixed'              => '0',
		'numeric'            => '0',

		// String data types:
		'char'               => '',
		'varchar'            => '',
		'nchar'              => '',
		'nvarchar'           => '',
		'tinytext'           => '',
		'text'               => '',
		'mediumtext'         => '',
		'longtext'           => '',
		'enum'               => '',     // TODO: Implement (first enum value).
		'set'                => '',
		'json'               => 'null', // String value 'null' (valid JSON)

		// Date and time data types:
		'date'               => '0000-00-00',
		'time'               => '00:00:00',
		'datetime'           => '0000-00-00 00:00:00',
		'timestamp'          => '0000-00-00 00:00:00',
		'year'               => '0000',

		// Binary data types:
		'binary'             => '',
		'varbinary'          => '',
		'tinyblob'           => '',
		'blob'               => '',
		'mediumblob'         => '',
		'longblob'           => '',

		// Spatial data types (no implicit defaults):
		'geometry'           => null,
		'point'              => null,
		'linestring'         => null,
		'polygon'            => null,
		'multipoint'         => null,
		'multilinestring'    => null,
		'multipolygon'       => null,
		'geomcollection'     => null,
		'geometrycollection' => null,
	);

	/**
	 * A map of MySQL column data types to native types in MySQL column meta.
	 *
	 * This maps normalized MySQL column data types (as per information schema)
	 * to MySQL "PDOStatement::getColumnMeta()" data types in the "native_type"
	 * field, as well as the "len" and "precision" fields, where applicable:
	 *
	 *     <mysql-column-type> => array( <native_type>, <mysqli_type>, <len>, <precision> )
	 *
	 * This is used to compute the column metadata from the information schema.
	 */
	private const COLUMN_INFO_MYSQL_TO_NATIVE_TYPES_MAP = array(
		// Numeric data types:
		'bit'             => array( 'BIT', 16, 1, 0 ),
		'tinyint'         => array( 'TINY', 1, 4, 0 ),
		'smallint'        => array( 'SHORT', 2, 6, 0 ),
		'mediumint'       => array( 'INT24', 9, 9, 0 ),
		'int'             => array( 'LONG', 3, 11, 0 ),
		'bigint'          => array( 'LONGLONG', 8, 20, 0 ),
		'float'           => array( 'FLOAT', 4, 12, 31 ),
		'double'          => array( 'DOUBLE', 5, 22, 31 ),
		'decimal'         => array( 'NEWDECIMAL', 246, null, null ),

		// String data types:
		'char'            => array( 'STRING', 254, null, 0 ),
		'varchar'         => array( 'VAR_STRING', 253, null, 0 ),
		'tinytext'        => array( 'BLOB', 252, null, 0 ),
		'text'            => array( 'BLOB', 252, null, 0 ),
		'mediumtext'      => array( 'BLOB', 252, null, 0 ),
		'longtext'        => array( 'BLOB', 252, null, 0 ),
		'enum'            => array( 'STRING', 254, null, 0 ),
		'set'             => array( 'STRING', 254, null, 0 ),
		'json'            => array( 'BLOB', 245, 4294967295, 0 ),

		// Date and time data types:
		'date'            => array( 'DATE', 10, 10, 0 ),
		'time'            => array( 'TIME', 11, 10, 0 ),
		'datetime'        => array( 'DATETIME', 12, 19, 0 ),
		'timestamp'       => array( 'TIMESTAMP', 7, 19, 0 ),
		'year'            => array( 'YEAR', 13, 4, 0 ),

		// Binary data types:
		'binary'          => array( 'BLOB', 254, null, 0 ),
		'varbinary'       => array( 'BLOB', 253, null, 0 ),
		'tinyblob'        => array( 'BLOB', 252, null, 0 ),
		'blob'            => array( 'BLOB', 252, null, 0 ),
		'mediumblob'      => array( 'BLOB', 252, null, 0 ),
		'longblob'        => array( 'BLOB', 252, null, 0 ),

		// Spatial data types:
		'geometry'        => array( 'GEOMETRY', 255, 4294967295, 0 ),
		'point'           => array( 'GEOMETRY', 255, 4294967295, 0 ),
		'linestring'      => array( 'GEOMETRY', 255, 4294967295, 0 ),
		'polygon'         => array( 'GEOMETRY', 255, 4294967295, 0 ),
		'multipoint'      => array( 'GEOMETRY', 255, 4294967295, 0 ),
		'multilinestring' => array( 'GEOMETRY', 255, 4294967295, 0 ),
		'multipolygon'    => array( 'GEOMETRY', 255, 4294967295, 0 ),
		'geomcollection'  => array( 'GEOMETRY', 255, 4294967295, 0 ),
	);

	/**
	 * A map of SQLite column definition data types and SQLite column meta data
	 * types to native types in MySQL column meta.
	 *
	 * This maps both SQLite column definition data types and SQLite column meta
	 * data types (as per "PDOStatement::getColumnMeta()") to MySQL column meta
	 * "native_type" field, as per "PDOStatement::getColumnMeta()", as well as
	 * the "len" and "precision" fields, where applicable:
	 *
	 *     <sqlite-column-definition-type> => array( <native_type>, <mysqli_type>, <len>, <precision> )
	 *     <sqlite-column-meta-type>       => array( <native_type>, <mysqli_type>, <len>, <precision> )
	 *
	 * This is used to compute the MySQL column metadata for non-column fields
	 * that have no records in the information schema (i.e., expressions).
	 */
	private const COLUMN_INFO_SQLITE_TO_NATIVE_TYPES_MAP = array(
		'NULL'    => array( 'NULL', 6, 0, 0 ),
		'INT'     => array( 'LONGLONG', 8, 21, 0 ),
		'INTEGER' => array( 'LONGLONG', 8, 21, 0 ),
		'STRING'  => array( 'VAR_STRING', 253, 65535, 31 ),
		'TEXT'    => array( 'BLOB', 252, null, 0 ),
		'REAL'    => array( 'DOUBLE', 5, 22, 31 ),
		'DOUBLE'  => array( 'DOUBLE', 5, 23, 31 ),
		'BLOB'    => array( 'BLOB', 252, null, 0 ),
	);

	/**
	 * The version of the MySQL server that the driver is configured for.
	 *
	 * @var int
	 */
	private $mysql_version;

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
	 * A MySQL query parser grammar.
	 *
	 * @var WP_Parser_Grammar
	 */
	private static $mysql_grammar;

	/**
	 * A reusable parser instance for MySQL queries.
	 *
	 * @var WP_MySQL_Parser|null
	 */
	private $mysql_parser = null;

	/**
	 * The main database name.
	 *
	 * The name of the main database that is used by the driver.
	 *
	 * @var string|null
	 */
	private $main_db_name;

	/**
	 * The name of the current database in use.
	 *
	 * This can be set with the USE statement. At the moment, we support only
	 * the main driver database and the INFORMATION_SCHEMA database.
	 *
	 * @var string
	 */
	private $db_name;

	/**
	 * An instance of the SQLite connection.
	 *
	 * @var WP_SQLite_Connection
	 */
	private $connection;

	/**
	 * Caller-visible PDO error mode.
	 *
	 * The underlying SQLite connection always uses exceptions so internal
	 * operations can handle errors reliably.
	 *
	 * @var int
	 */
	private $error_mode = PDO::ERRMODE_EXCEPTION;

	/**
	 * Whether fetched scalar values should be converted to strings.
	 *
	 * PDO SQLite cannot report PDO::ATTR_STRINGIFY_FETCHES on PHP 7.2–8.1,
	 * so the wrapper tracks its value on those versions.
	 *
	 * @var bool
	 */
	private $stringify_fetches = false;

	/**
	 * SQLSTATE associated with the last PDO operation.
	 *
	 * @var string|null
	 */
	private $error_code;

	/**
	 * Error information associated with the last PDO operation.
	 *
	 * @var array
	 */
	private $error_info = array( '', null, null );

	/**
	 * ID generated by the last user-issued INSERT or REPLACE statement.
	 *
	 * @var string
	 */
	private $last_insert_id = '0';

	/**
	 * User-defined functions registered on the SQLite connection.
	 *
	 * @var WP_SQLite_PDO_User_Defined_Functions
	 */
	private $user_defined_functions;

	/**
	 * A service for managing MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Builder
	 */
	private $information_schema_builder;

	/**
	 * Last executed MySQL query.
	 *
	 * @var string
	 */
	private $last_mysql_query;

	/**
	 * A list of SQLite queries executed for the last MySQL query.
	 *
	 * @var array{ sql: string, params: array }[]
	 */
	private $last_sqlite_queries = array();

	/**
	 * A PDO SQLite statement that represents the result of the last emulated query.
	 *
	 * @var PDOStatement|null
	 */
	private $last_result_statement;

	/**
	 * Override for the number of affected rows by the last emulated query.
	 *
	 * By default, the number of affected rows is carried by the row count value
	 * of "$this->last_result_statement". This property serves as an override for
	 * when the row count of the emulated query and statement don't match.
	 *
	 * @var int|null
	 */
	private $last_affected_rows;

	/**
	 * SQLite column metadata for the last emulated query.
	 *
	 * @var array
	 */
	private $last_column_meta = array();

	/**
	 * Data for emulating the "FOUND_ROWS()" function.
	 *
	 * When "SQL_CALC_FOUND_ROWS" is used, the appropriate value is stored here.
	 * Otherwise, it's used to store the last number of found rows, or a query
	 * that returns the rows that need to be counted for usage in "FOUND_ROWS()".
	 *
	 * From MySQL documentation:
	 *   In the absence of the SQL_CALC_FOUND_ROWS option in the most recent
	 *   successful SELECT statement, FOUND_ROWS() returns the number of rows
	 *   in the result set returned by that statement.
	 *
	 * In reality, this applies to SHOW and DESCRIBE statements as well.
	 *
	 * The value can be:
	 *   - integer: The number of rows to be directly returned by "FOUND_ROWS()".
	 *   - string:  A SQLite query whose result set rows need to be counted.
	 *   - array:   A tuple of a SQLite query and its parameters whose result
	 *              set rows need to be counted.
	 *
	 * @var int|string|array{0: string, 1: array}
	 */
	private $found_rows = 0;

	/**
	 * Whether the current MySQL query is read-only.
	 *
	 * @var bool
	 */
	private $is_readonly;

	/**
	 * Type of wrapper transaction that is active for the MySQL query emulation.
	 *
	 * Possible values:
	 *   - null:          No wrapper transaction is active.
	 *   - 'transaction': A top-level transaction is active.
	 *   - 'savepoint':   A nested savepoint is active.
	 *
	 * @var null|'transaction'|'savepoint'
	 */
	private $wrapper_transaction_type = null;

	/**
	 * Whether an SQLite transaction is active in the current session.
	 *
	 * This is a polyfill of the "PDO::inTransaction()" method for PHP < 8.4,
	 * where the "PDO::inTransaction()" method is not reliable with SQLite.
	 *
	 * @see https://bugs.php.net/bug.php?id=81227
	 * @see https://github.com/php/php-src/pull/14268
	 *
	 * @var bool
	 */
	private $in_transaction = false;

	/**
	 * Names of active user savepoints, outermost first.
	 *
	 * MySQL replaces a savepoint with the same name, while SQLite only shadows it.
	 * Tracking normalized names prevents shadowed SQLite savepoints from becoming
	 * visible again and supports case-insensitive lookup.
	 *
	 * @var string[]
	 */
	private $savepoint_names = array();

	/**
	 * Whether a MySQL table lock is active.
	 *
	 * Set to "true" when a lock is acquired using the MySQL LOCK statement.
	 * Set to "false" when locks are released using the MySQL UNLOCK statement.
	 *
	 * @var bool
	 */
	private $table_lock_active = false;

	/**
	 * The PDO fetch mode used for the emulated query.
	 *
	 * @var mixed
	 */
	private $pdo_fetch_mode;

	/**
	 * The currently active MySQL SQL modes.
	 *
	 * The default value reflects the default SQL modes for MySQL 8.0.
	 *
	 * TODO: This may be represented using a temporary table in the future,
	 *       together with GLOBAL SQL mode (a non-temporary table).
	 *
	 * @var int
	 */
	private $active_sql_modes;

	/**
	 * A name-to-value map of MySQL system variables for the current session.
	 *
	 * MySQL session system variables are session-specific, so we can store them
	 * in-memory. In SQL queries, they are combined with global system variables.
	 *
	 * See:
	 *   https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html
	 *
	 * @var array<string, string>
	 */
	private $session_system_variables = array();

	/**
	 * A name-to-value map of MySQL user variables.
	 *
	 * MySQL user variables are session-specific, so we can store them in-memory.
	 *
	 * See:
	 *   https://dev.mysql.com/doc/refman/8.4/en/user-variables.html
	 *
	 * @var array<string, string>
	 */
	private $user_variables = array();

	/**
	 * PDO API: Constructor.
	 *
	 * Set up an SQLite connection and the MySQL-on-SQLite driver.
	 *
	 * @param string      $dsn      MySQL-on-SQLite DSN containing the SQLite path and database name.
	 * @param string|null $username Optional. Ignored by this driver.
	 * @param string|null $password Optional. Ignored by this driver.
	 * @param array|null  $options  {
	 *     Optional PDO attributes and WP_MySQL_On_SQLite options.
	 *
	 *     Numeric keys are handled as standard PDO constructor options.
	 *     Driver-specific PDO options are not supported.
	 *
	 *     @type int             $mysql_version       Optional. MySQL version to emulate. Default 80038.
	 *     @type PDO|null        $sqlite_pdo          Optional. Existing PDO SQLite connection.
	 *     @type int|null        $sqlite_busy_timeout Optional. SQLite busy timeout in milliseconds.
	 *                                                Default 10000.
	 *     @type string|null     $sqlite_journal_mode Optional. SQLite journal mode. Default 'WAL'.
	 *     @type string|int|null $sqlite_synchronous  Optional. SQLite synchronous setting.
	 * }
	 *
	 * @throws InvalidArgumentException     When the MySQL version or an SQLite option is invalid.
	 * @throws WP_MySQL_On_SQLite_Exception When the driver initialization fails.
	 */
	public function __construct(
		string $dsn,
		?string $username = null,
		?string $password = null,
		?array $options = null
	) {
		$options = $options ?? array();

		// PDO DSN can't include "\0" bytes; parsing stops at the first one.
		$first_null_byte_index = strpos( $dsn, "\0" );
		if ( false !== $first_null_byte_index ) {
			$dsn = substr( $dsn, 0, $first_null_byte_index );
		}

		// Parse the DSN.
		$dsn_parts = explode( ':', $dsn, 2 );
		if ( count( $dsn_parts ) < 2 ) {
			throw new PDOException( 'invalid data source name' );
		}

		$driver = $dsn_parts[0];
		if ( 'mysql-on-sqlite' !== $driver ) {
			throw new PDOException( 'could not find driver' );
		}

		// PDO DSN supports semicolon escaping using double semicolon sequences.
		// Replace ";;" with "\0" to preserve escaped semicolons in "explode()".
		$args_string = str_replace( ';;', "\0", $dsn_parts[1] );
		$args        = array();
		foreach ( explode( ';', $args_string ) as $arg ) {
			// Restore escaped semicolons that were replaced with "\0".
			$arg = str_replace( "\0", ';', $arg );

			// PDO DSN allows whitespace before argument name. Trim characters
			// as per the "isspace()" C function (in the default "C" locale).
			$arg = ltrim( $arg, " \n\r\t\v\f" );

			if ( '' === $arg ) {
				continue;
			}
			$arg_parts             = explode( '=', $arg, 2 );
			$args[ $arg_parts[0] ] = $arg_parts[1] ?? null;
		}

		$path    = $args['path'] ?? ':memory:';
		$db_name = $args['dbname'] ?? 'sqlite_database';

		$mysql_version = $options['mysql_version'] ?? self::DEFAULT_MYSQL_VERSION;
		if ( ! is_int( $mysql_version ) || $mysql_version < self::MINIMUM_MYSQL_VERSION ) {
			throw new InvalidArgumentException(
				sprintf(
					'The "mysql_version" option must be an integer greater than or equal to %d.',
					self::MINIMUM_MYSQL_VERSION
				)
			);
		}

		// Create a new SQLite connection.
		$pdo_options = array_filter(
			$options,
			function ( $key ) {
				return is_int( $key );
			},
			ARRAY_FILTER_USE_KEY
		);

		$connection_pdo_options = array();
		if ( array_key_exists( PDO::ATTR_PERSISTENT, $pdo_options ) ) {
			// Persistence is connection-time-only. Do not apply it again after initialization.
			$connection_pdo_options[ PDO::ATTR_PERSISTENT ] = $pdo_options[ PDO::ATTR_PERSISTENT ];
			unset( $pdo_options[ PDO::ATTR_PERSISTENT ] );
		}

		$connection_options = array(
			'busy_timeout' => $options['sqlite_busy_timeout'] ?? null,
			'journal_mode' => $options['sqlite_journal_mode'] ?? null,
			'synchronous'  => $options['sqlite_synchronous'] ?? null,
			'pdo_options'  => $connection_pdo_options,
		);
		if ( isset( $options['sqlite_pdo'] ) ) {
			$connection_options['pdo'] = $options['sqlite_pdo'];
		} else {
			$connection_options['path'] = $path;
		}
		$this->connection = new WP_SQLite_Connection( $connection_options );

		$this->mysql_version = $mysql_version;
		$this->main_db_name  = $db_name;
		$this->db_name       = $db_name;
		$this->set_sql_modes( $this->get_default_sql_modes() );

		// Check the database name.
		if ( '' === $this->db_name ) {
			throw $this->new_driver_exception( 'The database name cannot be empty.' );
		}

		// Check the SQLite version.
		$sqlite_version = $this->get_sqlite_version();
		if ( version_compare( $sqlite_version, self::MINIMUM_SQLITE_VERSION, '<' ) ) {
			if ( defined( 'WP_SQLITE_UNSAFE_ENABLE_UNSUPPORTED_VERSIONS' ) && WP_SQLITE_UNSAFE_ENABLE_UNSUPPORTED_VERSIONS ) {
				// When "WP_SQLITE_UNSAFE_ENABLE_UNSUPPORTED_VERSIONS" is enabled,
				// allow using legacy SQLite versions, but not older than 3.27.0.
				if ( version_compare( $sqlite_version, '3.27.0', '<' ) ) {
					throw $this->new_driver_exception(
						sprintf(
							'The SQLite version %s is not supported. Minimum required version is %s.'
								. ' With "WP_SQLITE_UNSAFE_ENABLE_UNSUPPORTED_VERSIONS" enabled, you must use 3.27.0 or newer.',
							$sqlite_version,
							self::MINIMUM_SQLITE_VERSION
						)
					);
				}

				/*
				 * SQLite versions prior to 3.37.0 do not support STRICT tables.
				 *
				 * However, a database created with SQLite >= 3.37.0 can be used
				 * with SQLite versions < 3.37.0 when "PRAGMA writable_schema" is
				 * set to "ON", which also enables error-tolerant schema parsing.
				 *
				 * This is an unsafe opt-in feature for special backward compatibility
				 * use cases, as it can corrupt the database by allowing incorrect
				 * types into STRICT tables. Additionally, depending on the legacy
				 * SQLite version used, there is no guarantee that all features of
				 * the SQLite driver will work as expected. Use this with caution.
				 *
				 * See: https://www.sqlite.org/stricttables.html#accessing_strict_tables_in_earlier_versions_of_sqlite
				 *
				 * TODO: Remove this flag when we drop support for PHP 8.0.
				 *       From PHP 8.1, SQLite 3.46.1 is used by default.
				 */
				$this->execute_sqlite_query( 'PRAGMA writable_schema=ON' );
			} else {
				throw $this->new_driver_exception(
					sprintf(
						'The SQLite version %s is not supported. Minimum required version is %s.',
						$sqlite_version,
						self::MINIMUM_SQLITE_VERSION
					)
				);
			}
		}

		// Load MySQL client information to a property used by WordPress health info.
		$this->client_info = $this->getAttribute( PDO::ATTR_CLIENT_VERSION );

		// Enable foreign keys. By default, they are off.
		$this->connection->query( 'PRAGMA foreign_keys = ON' );

		// Register SQLite functions.
		$this->user_defined_functions = WP_SQLite_PDO_User_Defined_Functions::register_for( $this->connection->get_pdo() );

		// Load MySQL grammar.
		if ( null === self::$mysql_grammar ) {
			self::$mysql_grammar = new WP_Parser_Grammar( require self::MYSQL_GRAMMAR_PATH );
		}

		// Initialize information schema builder.
		$this->information_schema_builder = new WP_SQLite_Information_Schema_Builder(
			self::RESERVED_PREFIX,
			$this->connection
		);

		// Ensure that the database is configured.
		$migrator = new WP_SQLite_Configurator( $this, $this->information_schema_builder );
		$migrator->ensure_database_configured();

		$this->connection->set_query_logger(
			function ( string $sql, array $params ) {
				$this->last_sqlite_queries[] = array(
					'sql'    => $sql,
					'params' => $params,
				);
			}
		);

		foreach ( $pdo_options as $attribute => $value ) {
			$this->setAttribute( $attribute, $value );
		}
	}

	/**
	 * PDO API: Prepare a MySQL statement for execution.
	 *
	 * Prepared statements are not implemented yet. Report the standard PDO
	 * unsupported-function diagnostic instead of using uninitialized parent
	 * PDO state.
	 *
	 * @param  string     $query   The MySQL statement to prepare.
	 * @param  array|null $options Optional statement options.
	 * @return PDOStatement|false False when exceptions are disabled.
	 *
	 * @throws WP_MySQL_On_SQLite_Exception When exception mode is enabled.
	 */
	#[ReturnTypeWillChange]
	public function prepare( $query, $options = null ) {
		$driver_message = 'driver does not support prepared statements';
		$exception      = $this->new_driver_exception(
			'SQLSTATE[IM001]: Driver does not support this function: ' . $driver_message,
			'IM001',
			null,
			array( 'IM001', 0, $driver_message )
		);
		return $this->handle_pdo_error( $exception );
	}

	/**
	 * PDO API: Translate and execute a MySQL query in SQLite.
	 *
	 * A single MySQL query can be translated into zero or more SQLite queries.
	 *
	 * @param string   $query              Full SQL statement string.
	 * @param int|null $fetch_mode         Optional. PDO fetch mode. Defaults to the configured
	 *                                     PDO::ATTR_DEFAULT_FETCH_MODE.
	 * @param mixed    ...$fetch_mode_args Additional fetch mode arguments.
	 *
	 * @return PDOStatement|false PDO statement, or false when the fetch mode is invalid on PHP < 8.1.
	 *
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	#[ReturnTypeWillChange]
	public function query( string $query, ?int $fetch_mode = null, ...$fetch_mode_args ) {
		// Validate and parse the fetch mode and arguments.
		$arg_count            = func_num_args();
		$arg_colno            = 0;
		$arg_class            = null;
		$arg_constructor_args = array();
		$arg_into             = null;

		$get_type = function ( $value ) {
			$type = gettype( $value );
			if ( 'boolean' === $type ) {
				return 'bool';
			} elseif ( 'integer' === $type ) {
				return 'int';
			} elseif ( 'double' === $type ) {
				return 'float';
			}
			return $type;
		};

		if ( null === $fetch_mode ) {
			if ( PHP_VERSION_ID < 80100 && func_num_args() > 1 ) {
				trigger_error(
					'PDO::query(): SQLSTATE[HY000]: General error: mode must be an integer',
					E_USER_WARNING
				);
				return false;
			}
		} elseif ( PDO::FETCH_COLUMN === $fetch_mode ) {
			if ( 3 !== $arg_count ) {
				throw new ArgumentCountError(
					sprintf( 'PDO::query() expects exactly 3 arguments for the fetch mode provided, %d given', $arg_count )
				);
			}
			if ( ! is_int( $fetch_mode_args[0] ) ) {
				throw new TypeError(
					sprintf( 'PDO::query(): Argument #3 must be of type int, %s given', $get_type( $fetch_mode_args[0] ) )
				);
			}
			$arg_colno = $fetch_mode_args[0];
		} elseif ( PDO::FETCH_CLASS === $fetch_mode ) {
			if ( $arg_count < 3 ) {
				throw new ArgumentCountError(
					sprintf( 'PDO::query() expects at least 3 arguments for the fetch mode provided, %d given', $arg_count )
				);
			}
			if ( $arg_count > 4 ) {
				throw new ArgumentCountError(
					sprintf( 'PDO::query() expects at most 4 arguments for the fetch mode provided, %d given', $arg_count )
				);
			}
			if ( ! is_string( $fetch_mode_args[0] ) ) {
				throw new TypeError(
					sprintf( 'PDO::query(): Argument #3 must be of type string, %s given', $get_type( $fetch_mode_args[0] ) )
				);
			}
			if ( ! class_exists( $fetch_mode_args[0] ) ) {
				throw new TypeError( 'PDO::query(): Argument #3 must be a valid class' );
			}
			if ( 4 === $arg_count && ! is_array( $fetch_mode_args[1] ) ) {
				throw new TypeError(
					sprintf( 'PDO::query(): Argument #4 must be of type ?array, %s given', $get_type( $fetch_mode_args[1] ) )
				);
			}
			$arg_class            = $fetch_mode_args[0];
			$arg_constructor_args = $fetch_mode_args[1] ?? array();
		} elseif ( PDO::FETCH_INTO === $fetch_mode ) {
			if ( 3 !== $arg_count ) {
				throw new ArgumentCountError(
					sprintf( 'PDO::query() expects exactly 3 arguments for the fetch mode provided, %d given', $arg_count )
				);
			}
			if ( ! is_object( $fetch_mode_args[0] ) ) {
				throw new TypeError(
					sprintf( 'PDO::query(): Argument #3 must be of type object, %s given', $get_type( $fetch_mode_args[0] ) )
				);
			}
			$arg_into = $fetch_mode_args[0];
		} elseif ( $arg_count > 2 ) {
			throw new ArgumentCountError(
				sprintf( 'PDO::query() expects exactly 2 arguments for the fetch mode provided, %d given', $arg_count )
			);
		}

		$this->flush();
		$this->last_mysql_query = $query;

		try {
			// Parse the MySQL query.
			$parser = $this->create_parser( $query );
			$parser->next_query();
			$ast = $parser->get_query_ast();
			if ( null === $ast ) {
				throw $this->new_driver_exception( 'Failed to parse the MySQL query.' );
			}

			if ( $parser->next_query() ) {
				throw $this->new_driver_exception( 'Multi-query is not supported.' );
			}

			/*
			 * Determine if we need to wrap the translated queries in a transaction.
			 *
			 * [GRAMMAR]
			 * query:
			 *   EOF
			 *   | (simpleStatement | beginWork) (SEMICOLON_SYMBOL EOF? | EOF)
			 */
			$child_node = $ast->get_first_child_node();
			if (
				null === $child_node
				|| 'beginWork' === $child_node->rule_name
				|| $child_node->has_child_node( 'transactionOrLockingStatement' )
				|| $child_node->has_child_node( 'selectStatement' )
			) {
				$wrap_in_transaction = false;
			} else {
				$wrap_in_transaction = true;
			}

			/*
			 * Detect read-only statements before opening the wrapper transaction.
			 *
			 * [GRAMMAR]
			 * simpleStatement: selectStatement | showStatement | utilityStatement | ...
			 */
			$statement_node = null !== $child_node ? $child_node->get_first_child_node() : null;
			if ( null !== $statement_node ) {
				if (
					'selectStatement' === $statement_node->rule_name
					|| 'showStatement' === $statement_node->rule_name
					// Supported SET statements mutate only connection-local driver state
					// (sql_mode, user variables, etc.) or fail before touching SQLite.
					// They do not write to the SQLite database, so they must not take a
					// write lock. Use a deferred BEGIN instead of BEGIN IMMEDIATE.
					// This mirrors the SHOW/DESCRIBE handling.
					|| 'setStatement' === $statement_node->rule_name
				) {
					$this->is_readonly = true;
				} elseif ( 'utilityStatement' === $statement_node->rule_name ) {
					$utility_subnode = $statement_node->get_first_child_node();
					if ( null !== $utility_subnode && 'describeStatement' === $utility_subnode->rule_name ) {
						$this->is_readonly = true;
					}
				}
			}

			if ( $wrap_in_transaction ) {
				$this->begin_wrapper_transaction();
			}

			$this->execute_mysql_query( $ast );

			if ( $wrap_in_transaction ) {
				$this->commit_wrapper_transaction();
			}

			if ( null === $this->last_result_statement ) {
				$this->last_result_statement = $this->create_result_statement_from_data( array(), array() );
			}

			/*
			 * PDO SQLite reports affected rows only for INSERT, UPDATE, and DELETE.
			 * For other statements, it can report the row count of the last write,
			 * including the driver's internal writes. Use 0 for them instead.
			 *
			 * [GRAMMAR]
			 * simpleStatement: insertStatement | replaceStatement | updateStatement | deleteStatement | ...
			 */
			$reports_affected_rows = null !== $statement_node && in_array(
				$statement_node->rule_name,
				array( 'insertStatement', 'replaceStatement', 'updateStatement', 'deleteStatement' ),
				true
			);

			$stmt = new WP_MySQL_On_SQLite_Statement(
				$this->last_result_statement,
				$query,
				$this->create_column_meta_resolver( $this->last_column_meta ),
				$reports_affected_rows ? $this->last_affected_rows : 0
			);
			if ( null !== $fetch_mode ) {
				$stmt->setFetchMode( $fetch_mode, ...$fetch_mode_args );
			}
			$this->error_code = '00000';
			$this->error_info = array( '00000', null, null );
			return $stmt;
		} catch ( Throwable $e ) {
			/*
			 * Undo the failed statement. Like MySQL, keep an active user
			 * transaction open, so the caller can decide whether to commit
			 * or roll back the rest of it.
			 */
			try {
				$this->rollback_wrapper_transaction();
			} catch ( Throwable $rollback_exception ) {
				// Ignore rollback errors.
			}
			if ( ! $this->inTransaction() ) {
				$this->table_lock_active = false;
			}
			if ( $e instanceof WP_SQLite_Information_Schema_Exception ) {
				$e = $this->convert_information_schema_exception( $e );
			}
			if ( $e instanceof WP_MySQL_On_SQLite_Exception ) {
				$driver_exception = $e;
			} elseif ( $e instanceof PDOException ) {
				$driver_exception = $this->convert_sqlite_exception( $e );
			} else {
				$driver_exception = $this->new_driver_exception( $e->getMessage(), $e->getCode(), $e );
			}

			return $this->handle_pdo_error( $driver_exception );
		} finally {
			// A query that doesn't return any rows or fails sets found rows to 0.
			if ( ! $this->is_readonly || isset( $e ) ) {
				$this->found_rows = 0;
			}
		}
	}

	/**
	 * PDO API: Execute a MySQL statement and return the number of affected rows.
	 *
	 * @return int|false The number of affected rows or false on failure.
	 */
	#[ReturnTypeWillChange]
	public function exec( $query ) {
		$stmt = $this->query( $query );
		if ( false === $stmt ) {
			return false;
		}
		return $stmt->rowCount();
	}

	/**
	 * PDO API: Return the ID of the last inserted row.
	 *
	 * @param  string|null $name Optional sequence name. Ignored by SQLite.
	 * @return string|false      The last insert ID, or false on failure.
	 */
	#[ReturnTypeWillChange]
	public function lastInsertId( $name = null ) {
		if (
			is_array( $name )
			|| is_resource( $name )
			|| ( is_object( $name ) && ! method_exists( $name, '__toString' ) )
		) {
			if ( PHP_VERSION_ID >= 80000 ) {
				throw new TypeError(
					sprintf(
						'PDO::lastInsertId(): Argument #1 ($name) must be of type ?string, %s given',
						get_debug_type( $name )
					)
				);
			}
			trigger_error(
				sprintf( 'PDO::lastInsertId() expects parameter 1 to be string, %s given', strtolower( gettype( $name ) ) ),
				E_USER_WARNING
			);
			return false;
		}

		return $this->last_insert_id;
	}

	/**
	 * PDO API: Fetch the SQLSTATE associated with the last operation.
	 *
	 * @return string|null The SQLSTATE error code, or null when unavailable.
	 */
	public function errorCode(): ?string {
		return $this->error_code;
	}

	/**
	 * PDO API: Fetch error information associated with the last operation.
	 *
	 * @return array Error information from the last PDO operation.
	 */
	public function errorInfo(): array {
		return $this->error_info;
	}

	/**
	 * PDO API: Quote a string for use in a MySQL query.
	 *
	 * @param  string $string The string to quote.
	 * @param  int    $type   The PDO parameter type.
	 * @return string         The quoted string.
	 */
	#[ReturnTypeWillChange]
	public function quote( $string, $type = PDO::PARAM_STR ) {
		// Mirror value validation from the PDO MySQL driver.
		if (
			is_array( $string )
			|| is_resource( $string )
			|| ( is_object( $string ) && ! method_exists( $string, '__toString' ) )
		) {
			$given_type = is_object( $string ) ? get_class( $string ) : gettype( $string );
			throw new TypeError(
				sprintf(
					'WP_MySQL_On_SQLite::quote(): Argument #1 ($string) must be of type string, %s given',
					$given_type
				)
			);
		}
		$string = (string) $string;

		// Handle binary and national character prefixes.
		$prefix = '';
		if ( PDO::PARAM_LOB === ( $type & PDO::PARAM_LOB ) ) {
			$prefix = '_binary';
		} elseif (
			PDO::PARAM_STR_NATL === ( $type & PDO::PARAM_STR_NATL )
			&& PDO::PARAM_STR_CHAR !== ( $type & PDO::PARAM_STR_CHAR )
		) {
			$prefix = 'N';
		}

		/*
		 * PDO uses mysqlnd by default and can alternatively use libmysqlclient.
		 * This escaped character mapping matches the escaping of both drivers.
		 * Their malformed multibyte sequence handling is not needed for UTF-8.
		 *
		 * @see https://github.com/php/php-src/blob/dd6e76cce27aaa0ed9f7520648ed1081dfb6af36/ext/mysqlnd/mysqlnd_charset.c#L905
		 * @see https://github.com/mysql/mysql-server/blob/dc86e412f18b36ce271f791026714e8caa0ec919/mysys/charset.cc#L413
		 *
		 * We can't use "addcslashes()" here, because it has an unusual handling
		 * of the ASCII NULL and Control+Z characters, escaping them to "\000"
		 * and "\032" instead of "\0" and "\Z", respectively.
		 *
		 * It is important to use "strtr()" and not "str_replace()", because
		 * "str_replace()" applies replacements one after another, modifying
		 * intermediate changes rather than just the original string:
		 *
		 *   - str_replace( [ 'a', 'b' ], [ 'b', 'c' ], 'ab' ); // 'cc' (bad)
		 *   - strtr( 'ab', [ 'a' => 'b', 'b' => 'c' ] );       // 'bc' (good)
		 */
		$backslash    = chr( 92 );
		$replacements = array(
			chr( 0 )   => $backslash . '0',        // An ASCII NULL character (\0).
			chr( 10 )  => $backslash . 'n',        // A newline (linefeed) character (\n).
			chr( 13 )  => $backslash . 'r',        // A carriage return character (\r).
			$backslash => $backslash . $backslash, // A backslash character (\).
			"'"        => $backslash . "'",        // A single quote character (').
			'"'        => $backslash . '"',        // A double quote character (").
			chr( 26 )  => $backslash . 'Z',        // An ASCII 26 (Control+Z) character.
		);
		return $prefix . "'" . strtr( $string, $replacements ) . "'";
	}

	/**
	 * PDO API: Begin a transaction.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function beginTransaction(): bool {
		if ( $this->inTransaction() ) {
			throw $this->new_driver_exception( 'There is already an active transaction' );
		}
		$this->flush();
		$this->begin_user_transaction();
		return true;
	}

	/**
	 * PDO API: Commit a transaction.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function commit(): bool {
		if ( ! $this->inTransaction() ) {
			throw $this->new_driver_exception( 'There is no active transaction' );
		}
		$this->flush();

		// Unlike a COMMIT statement, PDO::commit() doesn't roll back on failure,
		// so the caller can still call PDO::rollBack(), as with PDO MySQL.
		$this->connection->query( 'COMMIT' );
		$this->in_transaction  = false;
		$this->savepoint_names = array();
		return true;
	}

	/**
	 * PDO API: Rollback a transaction.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function rollBack(): bool {
		if ( ! $this->inTransaction() ) {
			throw $this->new_driver_exception( 'There is no active transaction' );
		}
		$this->flush();
		$this->rollback_user_transaction();
		return true;
	}

	/**
	 * PDO API: Check if a transaction is active.
	 *
	 * @return bool True if a transaction is active, false otherwise.
	 */
	public function inTransaction(): bool {
		if ( PHP_VERSION_ID < 80400 ) {
			/*
			 * On PHP < 8.4, the "PDO::inTransaction()" method is not reliable.
			 *
			 * @see https://bugs.php.net/bug.php?id=81227
			 * @see https://github.com/php/php-src/pull/14268
			 */
			return $this->in_transaction;
		}
		return $this->connection->get_pdo()->inTransaction();
	}

	/**
	 * PDO API: Set a PDO attribute.
	 *
	 * TODO: Evaluate whether we should pass all PDO attributes to the underlying
	 *       PDO instance, or whether some of them require special handling.
	 *       See: https://github.com/php/php-src/blob/b391c28f903536e3bc6a0021ae0976ddbc2745f8/ext/pdo/php_pdo_driver.h#L103
	 *
	 * @param int   $attribute The attribute to set.
	 * @param mixed $value     The value of the attribute.
	 * @return bool            True on success, false on failure.
	 */
	public function setAttribute( $attribute, $value ): bool {
		// PDO reserves IDs starting at 1000 for driver-specific attributes.
		// These differ for MySQL and SQLite and are currently not supported.
		if ( is_int( $attribute ) && 1000 <= $attribute ) {
			return false;
		}

		// Track the caller's error mode while keeping internal SQLite operations in exception mode.
		if ( PDO::ATTR_ERRMODE === $attribute ) {
			$pdo    = $this->connection->get_pdo();
			$result = $pdo->setAttribute( $attribute, $value );
			if ( ! $result ) {
				return false;
			}
			$this->error_mode = $pdo->getAttribute( PDO::ATTR_ERRMODE );
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			return true;
		}

		$result = $this->connection->get_pdo()->setAttribute( $attribute, $value );
		if ( $result && PDO::ATTR_STRINGIFY_FETCHES === $attribute ) {
			$this->stringify_fetches = (bool) $value;
		}
		return $result;
	}

	/**
	 * PDO API: Get a PDO attribute.
	 *
	 * TODO: Evaluate whether we should get all PDO attributes from the underlying
	 *       PDO instance, or whether some of them require special handling.
	 *       See: https://github.com/php/php-src/blob/b391c28f903536e3bc6a0021ae0976ddbc2745f8/ext/pdo/php_pdo_driver.h#L103
	 *
	 * @param  int   $attribute The attribute to get.
	 * @return mixed            The value of the attribute.
	 */
	#[ReturnTypeWillChange]
	public function getAttribute( $attribute ) {
		if ( PDO::ATTR_DRIVER_NAME === $attribute ) {
			return 'mysql';
		} elseif ( PDO::ATTR_CLIENT_VERSION === $attribute ) {
			return 'mysqlnd ' . $this->get_mysql_server_version_string();
		} elseif ( PDO::ATTR_SERVER_VERSION === $attribute ) {
			return $this->get_mysql_server_version_string();
		} elseif ( PDO::ATTR_ERRMODE === $attribute ) {
			// Return the caller's error mode instead of the exception mode used internally.
			return $this->error_mode;
		} elseif ( PDO::ATTR_STRINGIFY_FETCHES === $attribute && PHP_VERSION_ID < 80200 ) {
			// PDO SQLite cannot report this attribute before PHP 8.2.
			return $this->stringify_fetches;
		}
		return $this->connection->get_pdo()->getAttribute( $attribute );
	}

	/**
	 * Get the underlying PDO SQLite instance.
	 *
	 * Exposes the PDO SQLite connection for advanced use, bypassing MySQL emulation.
	 * Do not retain it across reconnections or modify driver-owned state.
	 *
	 * @return PDO The underlying PDO SQLite instance.
	 */
	public function get_sqlite_pdo(): PDO {
		return $this->connection->get_pdo();
	}

	/**
	 * Get the SQLite connection instance.
	 *
	 * @access private
	 *
	 * @return WP_SQLite_Connection
	 */
	public function get_connection(): WP_SQLite_Connection {
		return $this->connection;
	}

	/**
	 * Get the version of the SQLite engine.
	 *
	 * @return string SQLite engine version as a string.
	 */
	public function get_sqlite_version(): string {
		return $this->connection->get_pdo()->getAttribute( PDO::ATTR_SERVER_VERSION );
	}

	/**
	 * Get the SQLite driver version saved in the database.
	 *
	 * The saved driver version corresponds to the latest version of the SQLite
	 * driver that was used to initialize and configure the SQLite database.
	 *
	 * @access private
	 *
	 * @return string       SQLite driver version as a string.
	 * @throws PDOException When the query execution fails.
	 */
	public function get_saved_driver_version(): string {
		$default_version = '0.0.0';
		try {
			$stmt = $this->execute_sqlite_query(
				sprintf(
					'SELECT value FROM %s WHERE name = ?',
					$this->quote_sqlite_identifier( self::GLOBAL_VARIABLES_TABLE_NAME )
				),
				array( self::DRIVER_VERSION_VARIABLE_NAME )
			);
			return $stmt->fetchColumn() ?? $default_version;
		} catch ( PDOException $e ) {
			if ( str_contains( $e->getMessage(), 'no such table' ) ) {
				return $default_version;
			}
			throw $e;
		}
	}

	/**
	 * Check if a specific SQL mode is active.
	 *
	 * @access private
	 *
	 * @param  string $mode The SQL mode to check.
	 * @return bool         True if the SQL mode is active, false otherwise.
	 */
	public function is_sql_mode_active( string $mode ): bool {
		$mode = strtoupper( $mode );
		if ( 'NOT_USED' === $mode && $this->mysql_version < 80000 ) {
			return false;
		}
		return isset( self::SQL_MODES[ $mode ] )
			&& ( $this->active_sql_modes & self::SQL_MODES[ $mode ] ) !== 0;
	}

	/**
	 * Get the last executed MySQL query.
	 *
	 * @access private
	 *
	 * @return string|null
	 */
	public function get_last_mysql_query(): ?string {
		return $this->last_mysql_query;
	}

	/**
	 * Get SQLite queries executed for the last MySQL query.
	 *
	 * @access private
	 *
	 * @return array{ sql: string, params: array }[]
	 */
	public function get_last_sqlite_queries(): array {
		return $this->last_sqlite_queries;
	}

	/**
	 * Tokenize a MySQL query and initialize a parser.
	 *
	 * @access private
	 *
	 * @param  string          $query The MySQL query to parse.
	 * @return WP_MySQL_Parser        A parser initialized for the MySQL query.
	 */
	public function create_parser( string $query ): WP_MySQL_Parser {
		$lexer  = new WP_MySQL_Lexer(
			$query,
			$this->mysql_version,
			$this->get_active_sql_mode_names()
		);
		$tokens = $lexer->remaining_tokens();

		// Reset the reusable parser with new tokens or create it on first use.
		if ( null === $this->mysql_parser ) {
			$this->mysql_parser = new WP_MySQL_Parser( self::$mysql_grammar, $tokens );
		} else {
			$this->mysql_parser->reset_tokens( $tokens );
		}

		return $this->mysql_parser;
	}

	/**
	 * Execute a query in SQLite.
	 *
	 * @access private
	 *
	 * @param string $sql   The query to execute.
	 * @param array $params The query parameters.
	 * @throws PDOException When the query execution fails.
	 * @return PDOStatement The PDO statement object.
	 */
	public function execute_sqlite_query( string $sql, array $params = array() ): PDOStatement {
		return $this->connection->query( $sql, $params );
	}

	/**
	 * Create a lazy MySQL-compatible column metadata resolver.
	 *
	 * Only raw SQLite metadata and database context are snapshotted here.
	 * The INFORMATION_SCHEMA metadata is resolved lazily and may reflect
	 * schema changes made after statement execution. This is a trade-off
	 * that avoids schema queries when column metadata is not requested.
	 *
	 * @param  array $raw_column_meta Raw SQLite result column metadata.
	 * @return callable               The column metadata resolver.
	 */
	private function create_column_meta_resolver( array $raw_column_meta ): callable {
		$db_name = $this->db_name;
		return function ( $column ) use ( $raw_column_meta, $db_name ) {
			if ( ! array_key_exists( $column, $raw_column_meta ) ) {
				return false;
			}

			$last_sqlite_queries = $this->last_sqlite_queries;
			try {
				return $this->resolve_column_meta( $raw_column_meta[ $column ], $db_name );
			} finally {
				$this->last_sqlite_queries = $last_sqlite_queries;
			}
		};
	}

	/**
	 * Resolve raw SQLite column metadata into MySQL-compatible metadata.
	 *
	 * @param  array  $meta    Raw SQLite column metadata.
	 * @param  string $db_name Database selected when the query was executed.
	 * @return array            MySQL-compatible column metadata.
	 */
	private function resolve_column_meta( array $meta, string $db_name ): array {
		$table = $meta['table'] ?? null;
		$name  = $meta['name'];
		$type  = strtoupper( $meta['sqlite:decl_type'] ?? $meta['native_type'] ?? '' );

		// When table is known, we can get data from the information schema.
		$column_info = null;
		if ( null !== $table ) {
			$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table );
			$columns_table      = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
			$column_info        = $this->execute_sqlite_query(
				sprintf(
					'
						SELECT
							IS_NULLABLE,
							DATA_TYPE,
							COLUMN_TYPE,
							COLUMN_KEY,
							CHARACTER_MAXIMUM_LENGTH,
							NUMERIC_PRECISION,
							NUMERIC_SCALE
						FROM %s
						WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
					',
					$this->quote_sqlite_identifier( $columns_table )
				),
				array( $this->get_saved_db_name( $db_name ), $table, $name )
			)->fetch( PDO::FETCH_ASSOC );

			if ( false === $column_info ) {
				$column_info = null;
			}
		}

		// If we have information schema data, we can use it.
		if ( null !== $column_info ) {
			$type_info = self::COLUMN_INFO_MYSQL_TO_NATIVE_TYPES_MAP[ $column_info['DATA_TYPE'] ] ?? null;
			if ( null === $type_info ) {
				$type_info = self::COLUMN_INFO_SQLITE_TO_NATIVE_TYPES_MAP[ $type ] ?? null;
			}
			$native_type = $type_info[0];
			$mysqli_type = $type_info[1];
			$len         = $type_info[2];
			$precision   = $type_info[3];

			if ( 'tinyint(1)' === $column_info['COLUMN_TYPE'] ) {
				$len = 1;
			}

			if ( 'decimal' === $column_info['DATA_TYPE'] ) {
				$len       = (int) $column_info['NUMERIC_PRECISION'] + (int) $column_info['NUMERIC_SCALE'];
				$precision = (int) $column_info['NUMERIC_SCALE'];
			}

			if (
				str_contains( $column_info['COLUMN_TYPE'], 'unsigned' )
				&& ! str_contains( $column_info['COLUMN_TYPE'], 'bigint' )
			) {
				$len -= 1;
			}

			// If set, length can be taken from the information schema.
			if ( isset( $column_info['CHARACTER_MAXIMUM_LENGTH'] ) ) {
				$len = (int) $column_info['CHARACTER_MAXIMUM_LENGTH'];
			}

			// For string types, the length is multiplied by the maximum number
			// of bytes per character for the used connection encoding. In our
			// case, it's always "utf8mb4" and therefore 4 bytes per character.
			if (
				str_contains( $column_info['DATA_TYPE'], 'text' )
				|| str_contains( $column_info['DATA_TYPE'], 'char' )
				|| 'enum' === $column_info['DATA_TYPE']
				|| 'set' === $column_info['DATA_TYPE']
			) {
				// Except for "longtext" - this might be a MySQL bug.
				if ( 'longtext' !== $column_info['DATA_TYPE'] ) {
					$len = 4 * $len;
				}
			}

			// Flags.
			$flags = array();
			if ( 'NO' === $column_info['IS_NULLABLE'] ) {
				$flags[] = 'not_null';
			}
			if ( 'PRI' === $column_info['COLUMN_KEY'] ) {
				$flags[] = 'primary_key';
			} elseif ( 'UNI' === $column_info['COLUMN_KEY'] ) {
				$flags[] = 'unique_key';
			} elseif ( 'MUL' === $column_info['COLUMN_KEY'] ) {
				$flags[] = 'multiple_key';
			}
		} else {
			$type_info   = self::COLUMN_INFO_SQLITE_TO_NATIVE_TYPES_MAP[ $type ];
			$native_type = $type_info[0];
			$mysqli_type = $type_info[1];
			$len         = $type_info[2] ?? 0;
			$precision   = $type_info[3];

			// Flags.
			$flags = array();
			if ( 'NULL' !== $type ) {
				$flags[] = 'not_null';
			}
		}

		if ( 'BLOB' === $native_type || 'GEOMETRY' === $native_type ) {
			$flags[] = 'blob';
		}

		// PDO type.
		if ( 'INT' === $type || 'INTEGER' === $type ) {
			$pdo_type = PDO::PARAM_INT;
		} else {
			$pdo_type = PDO::PARAM_STR;
		}

		// MySQLi charset number.
		$is_string   = 'STRING' === $type || 'TEXT' === $type;
		$is_binary   = 'BLOB' === $type || 'GEOMETRY' === $native_type;
		$is_datetime = str_contains( $native_type, 'DATE' ) || str_contains( $native_type, 'TIME' ) || 'YEAR' === $native_type;
		if ( $is_string && ! $is_binary && ! $is_datetime ) {
			$mysqli_charsetnr = 255; // utf8mb4_0900_ai_ci
		} else {
			$mysqli_charsetnr = 63;  // binary
		}

		return array(
			'native_type'      => $native_type,
			'pdo_type'         => $pdo_type,
			'flags'            => $flags,
			'table'            => $meta['table'] ?? '',
			'name'             => $meta['name'],
			'len'              => $len,
			'precision'        => $precision,
			'sqlite:decl_type' => $meta['sqlite:decl_type'] ?? '',

			/*
			 * The MySQLi PHP extension exposes more MySQL column metadata than PDO.
			 * We'll add the data here for use cases such as "wpdb::get_col_info()".
			 */
			'mysqli:orgname'   => $meta['name'],        // TODO: Use correct original name when alias is used.
			'mysqli:orgtable'  => $meta['table'] ?? '', // TODO: Use correct original name when table alias is used.
			'mysqli:db'        => $db_name,             // TODO: Use correct DB for queries to information schema.
			'mysqli:charsetnr' => $mysqli_charsetnr,
			'mysqli:flags'     => 0,                    // TODO: We can compute correct MySQL flags.
			'mysqli:type'      => $mysqli_type,
		);
	}

	/**
	 * Record and report a PDO operation error according to the configured mode.
	 *
	 * @param  WP_MySQL_On_SQLite_Exception $exception The operation error.
	 * @return false                                    Always false when the error is not thrown.
	 * @throws WP_MySQL_On_SQLite_Exception When exception mode is enabled.
	 */
	private function handle_pdo_error( WP_MySQL_On_SQLite_Exception $exception ): bool {
		$this->error_info = $exception->errorInfo;
		$this->error_code = $this->error_info[0];

		if ( PDO::ERRMODE_EXCEPTION === $this->error_mode ) {
			throw $exception;
		}
		if ( PDO::ERRMODE_WARNING === $this->error_mode ) {
			trigger_error( $exception->getMessage(), E_USER_WARNING );
		}
		return false;
	}

	/**
	 * Translate and execute a MySQL query in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "query" AST node with "simpleStatement" child.
	 * @throws WP_MySQL_On_SQLite_Exception When the query is not supported.
	 */
	private function execute_mysql_query( WP_Parser_Node $node ): void {
		if ( 'query' !== $node->rule_name ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected "query" node, got: "%s"', $node->rule_name )
			);
		}

		/*
		 * [GRAMMAR]
		 * query:
		 *   EOF
		 *   | (simpleStatement | beginWork) (SEMICOLON_SYMBOL EOF? | EOF)
		 */
		$children = $node->get_child_nodes();
		if ( count( $children ) !== 1 ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected 1 child node, got: %d', count( $children ) )
			);
		}

		if ( 'beginWork' === $children[0]->rule_name ) {
			$this->begin_user_transaction();
			return;
		}

		if ( 'simpleStatement' !== $children[0]->rule_name ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected "simpleStatement" node, got: "%s"', $children[0]->rule_name )
			);
		}

		// Process the "simpleStatement" AST node.
		$node = $children[0]->get_first_child_node();
		switch ( $node->rule_name ) {
			case 'transactionOrLockingStatement':
				$this->execute_transaction_or_locking_statement( $node );
				break;
			case 'selectStatement':
				$this->execute_select_statement( $node );
				break;
			case 'insertStatement':
			case 'replaceStatement':
				$this->execute_insert_or_replace_statement( $node );
				break;
			case 'updateStatement':
				$this->execute_update_statement( $node );
				break;
			case 'deleteStatement':
				$this->execute_delete_statement( $node );
				break;
			case 'createStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'createDatabase':
						/*
						 * TODO:
						 * We could support this by creating a new SQLite database
						 * file (e.g., $slugified_db_name.sqlite).
						 *
						 * Alternatively, it could be a no-op, in combination with
						 * DROP DATABASE deleting the data file and recreating it.
						 */
					case 'createTable':
						$this->execute_create_table_statement( $node );
						break;
					case 'createIndex':
						$this->execute_create_index_statement( $node );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'alterStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'alterTable':
						$this->execute_alter_table_statement( $node );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'dropStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'dropTable':
						$this->execute_drop_table_statement( $node );
						break;
					case 'dropIndex':
						$this->execute_drop_index_statement( $node );
						break;
					default:
						$query                       = $this->translate( $node );
						$this->last_result_statement = $this->execute_sqlite_query( $query );
				}
				break;
			case 'truncateTableStatement':
				$this->execute_truncate_table_statement( $node );
				break;
			case 'setStatement':
				$this->execute_set_statement( $node );
				break;
			case 'showStatement':
				$this->execute_show_statement( $node );
				break;
			case 'utilityStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'describeStatement':
						$this->execute_describe_statement( $subtree );
						break;
					case 'useCommand':
						$this->execute_use_statement( $subtree );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'tableAdministrationStatement':
				$this->execute_administration_statement( $node );
				break;
			default:
				throw $this->new_not_supported_exception(
					sprintf( 'statement type: "%s"', $node->rule_name )
				);
		}
	}

	/**
	 * Begin a wrapper transaction.
	 *
	 * A wrapper transaction is used to ensure consistency by encapsulating SQLite
	 * statements that are executed during a single MySQL query emulation process.
	 *
	 * TOP-LEVEL TRANSACTION vs. SAVEPOINT:
	 *
	 * When no transaction is active, we can use a top-level TRANSACTION to wrap
	 * the emulated MySQL statement. However, if a transaction is already active,
	 * we must use a SAVEPOINT, as SQLite doesn't support transaction nesting.
	 *
	 * BEGIN vs. BEGIN IMMEDIATE:
	 *
	 * When we're executing a statement that will need to write to the database,
	 * we must use "BEGIN IMMEDIATE" to immediately open a write transaction.
	 *
	 * This is needed to avoid the "database is locked" error (SQLITE_BUSY) when
	 * SQLite can't upgrade a read transaction to a write transaction, because
	 * another connection is already modifying the database.
	 *
	 * From the SQLite documentation:
	 *
	 *   ## Read transactions versus write transactions
	 *
	 *   If a write statement occurs while a read transaction is active,
	 *   then the read transaction is upgraded to a write transaction if
	 *   possible. If some other database connection has already modified
	 *   the database or is already in the process of modifying the database,
	 *   then upgrading to a write transaction is not possible and the write
	 *   statement will fail with SQLITE_BUSY.
	 *
	 *   ## DEFERRED, IMMEDIATE, and EXCLUSIVE transactions
	 *
	 *   Transactions can be DEFERRED, IMMEDIATE, or EXCLUSIVE. The default
	 *   transaction behavior is DEFERRED.
	 *
	 *   DEFERRED means that the transaction does not actually start until
	 *   the database is first accessed.
	 *
	 *   IMMEDIATE causes the database connection to start a new write
	 *   immediately, without waiting for a write statement. The BEGIN
	 *   IMMEDIATE might fail with SQLITE_BUSY if another write transaction
	 *   is already active on another database connection.
	 *
	 * See:
	 *   - https://www.sqlite.org/lang_transaction.html
	 *   - https://www.sqlite.org/rescode.html#busy
	 *
	 * For better performance, we could also consider opening the write
	 * transaction later in the session - just before the first write.
	 */
	private function begin_wrapper_transaction(): void {
		if ( null !== $this->wrapper_transaction_type ) {
			return;
		}

		$wrapper_transaction_type = $this->wrapper_transaction_type;
		if ( $this->inTransaction() ) {
			$savepoint_name           = $this->get_internal_savepoint_name( 'wrapper' );
			$stmt                     = $this->connection->prepare( sprintf( 'SAVEPOINT %s', $savepoint_name ) );
			$wrapper_transaction_type = 'savepoint';
		} else {
			// For write transactions, we must use "BEGIN IMMEDIATE".
			// @see self::begin_user_transaction() method comments.
			$stmt                     = $this->connection->prepare( $this->is_readonly ? 'BEGIN' : 'BEGIN IMMEDIATE' );
			$wrapper_transaction_type = 'transaction';
		}

		if ( ! $stmt->execute() ) {
			throw $this->new_driver_exception( 'Failed to begin wrapper transaction.' );
		}
		$this->wrapper_transaction_type = $wrapper_transaction_type;
		$this->in_transaction           = true;
	}

	/**
	 * Commit a wrapper transaction.
	 */
	private function commit_wrapper_transaction(): void {
		if ( null === $this->wrapper_transaction_type ) {
			return;
		}

		$in_transaction = $this->in_transaction;
		if ( 'savepoint' === $this->wrapper_transaction_type ) {
			$savepoint_name = $this->get_internal_savepoint_name( 'wrapper' );
			$stmt           = $this->connection->prepare( sprintf( 'RELEASE SAVEPOINT %s', $savepoint_name ) );
		} else {
			$stmt           = $this->connection->prepare( 'COMMIT' );
			$in_transaction = false;
		}

		if ( ! $stmt->execute() ) {
			throw $this->new_driver_exception( 'Failed to commit wrapper transaction.' );
		}
		$this->wrapper_transaction_type = null;
		$this->in_transaction           = $in_transaction;
	}

	/**
	 * Roll back a wrapper transaction.
	 *
	 * This undoes all changes of a failed statement. When the wrapper is
	 * a savepoint, the user transaction around it stays active.
	 */
	private function rollback_wrapper_transaction(): void {
		if ( 'transaction' === $this->wrapper_transaction_type ) {
			$this->rollback_user_transaction();
		} elseif ( 'savepoint' === $this->wrapper_transaction_type ) {
			$savepoint_name = $this->get_internal_savepoint_name( 'wrapper' );
			try {
				$this->connection->query( sprintf( 'ROLLBACK TO SAVEPOINT %s', $savepoint_name ) );
			} catch ( PDOException $e ) {
				/*
				 * Some SQLite errors roll back the whole transaction automatically,
				 * which also removes the savepoint. In that case, end the transaction.
				 *
				 * @see https://www.sqlite.org/lang_transaction.html#response_to_errors_within_a_transaction
				 */
				$this->rollback_user_transaction();
				return;
			}
			$this->connection->query( sprintf( 'RELEASE SAVEPOINT %s', $savepoint_name ) );
		}
	}

	/**
	 * Execute the "BEGIN" or "START TRANSACTION" MySQL statement in SQLite.
	 */
	private function begin_user_transaction(): void {
		// MySQL implicitly commits previous transaction when starting a new one.
		if ( $this->inTransaction() ) {
			$this->commit_user_transaction();
		}

		/*
		 * Since we don't know whether the user will write to the database, we
		 * must use "BEGIN IMMEDIATE" to immediately open a write transaction.
		 *
		 * This is needed to avoid the "database is locked" error (SQLITE_BUSY)
		 * when SQLite can't upgrade a read transaction to a write transaction,
		 * because another connection is already modifying the database.
		 *
		 * @see self::begin_wrapper_transaction()
		 */
		$this->connection->query( 'BEGIN IMMEDIATE' );
		$this->in_transaction  = true;
		$this->savepoint_names = array();
	}

	/**
	 * Execute the "COMMIT" MySQL statement in SQLite.
	 */
	private function commit_user_transaction(): void {
		// MySQL doesn't throw an error if there is no active transaction.
		if ( ! $this->inTransaction() ) {
			return;
		}
		try {
			$this->connection->query( 'COMMIT' );
		} catch ( PDOException $e ) {
			// Like MySQL, roll back a transaction that fails to commit.
			try {
				$this->rollback_user_transaction();
			} catch ( PDOException $rollback_exception ) {
				// SQLite has already rolled back the transaction.
			}
			throw $e;
		}
		$this->in_transaction  = false;
		$this->savepoint_names = array();
	}

	/**
	 * Execute the "ROLLBACK" MySQL statement in SQLite.
	 */
	private function rollback_user_transaction(): void {
		try {
			// MySQL doesn't throw an error if there is no active transaction.
			if ( $this->inTransaction() ) {
				$this->connection->query( 'ROLLBACK' );
			}
		} finally {
			// A ROLLBACK always ends the transaction, even when it fails.
			$this->in_transaction  = false;
			$this->savepoint_names = array();
		}
	}

	/**
	 * Execute a MySQL transaction or locking statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "transactionOrLockingStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_transaction_or_locking_statement( WP_Parser_Node $node ): void {
		$subnode = $node->get_first_child_node();
		$token   = $node->get_first_descendant_token();

		switch ( $subnode->rule_name ) {
			case 'transactionStatement':
				// START TRANSACTION.
				if ( WP_MySQL_Lexer::START_SYMBOL === $token->id ) {
					$this->begin_user_transaction();
					return;
				}

				// COMMIT.
				if ( WP_MySQL_Lexer::COMMIT_SYMBOL === $token->id ) {
					$this->commit_user_transaction();
					return;
				}

				break;
			case 'savepointStatement':
				$savepoint_name = $this->translate( $subnode->get_first_child_node( 'identifier' ) );
				$savepoint_key  = null === $savepoint_name
					? null
					: strtolower( $this->unquote_sqlite_identifier( $savepoint_name ) );

				// ROLLBACK/ROLLBACK TO SAVEPOINT <identifier>.
				if ( WP_MySQL_Lexer::ROLLBACK_SYMBOL === $token->id ) {
					if ( null === $savepoint_name ) {
						$this->rollback_user_transaction();
					} else {
						// ROLLBACK TO keeps the named savepoint and deletes those created after it.
						$index = array_search( $savepoint_key, $this->savepoint_names, true );
						if ( false === $index ) {
							throw $this->new_savepoint_does_not_exist_exception( $savepoint_name );
						}
						$this->execute_sqlite_query( sprintf( 'ROLLBACK TO SAVEPOINT %s', $savepoint_name ) );
						array_splice( $this->savepoint_names, $index + 1 );
					}
					return;
				}

				// SAVEPOINT.
				if ( WP_MySQL_Lexer::SAVEPOINT_SYMBOL === $token->id ) {
					// In MySQL with autocommit enabled, a standalone savepoint is discarded
					// immediately without starting a transaction.
					if ( ! $this->inTransaction() ) {
						return;
					}
					$this->execute_sqlite_query( sprintf( 'SAVEPOINT %s', $savepoint_name ) );

					/*
					 * MySQL deletes an existing savepoint when its name is reused, while
					 * SQLite keeps it on the stack, shadowed by the new one. Drop the old
					 * name so that it can no longer be referenced. The shadowed SQLite
					 * savepoint is harmless; it is discarded when the transaction ends.
					 */
					$index = array_search( $savepoint_key, $this->savepoint_names, true );
					if ( false !== $index ) {
						array_splice( $this->savepoint_names, $index, 1 );
					}
					$this->savepoint_names[] = $savepoint_key;
					return;
				}

				// RELEASE SAVEPOINT.
				if ( WP_MySQL_Lexer::RELEASE_SYMBOL === $token->id ) {
					// RELEASE deletes the named savepoint and those created after it.
					$index = array_search( $savepoint_key, $this->savepoint_names, true );
					if ( false === $index ) {
						throw $this->new_savepoint_does_not_exist_exception( $savepoint_name );
					}
					$this->execute_sqlite_query( sprintf( 'RELEASE SAVEPOINT %s', $savepoint_name ) );
					array_splice( $this->savepoint_names, $index );
					return;
				}

				break;
			case 'lockStatement':
				// LOCK TABLE/LOCK TABLES.
				if (
					WP_MySQL_Lexer::LOCK_SYMBOL === $token->id
					&& $subnode->has_child_node( 'lockItem' )
				) {
					// Check if the table(s) exists.
					$lock_items = $subnode->get_child_nodes( 'lockItem' );
					foreach ( $lock_items as $lock_item ) {
						$table_ref  = $lock_item->get_first_child_node( 'tableRef' );
						$database   = $this->get_database_name( $table_ref );
						$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
						if ( 'information_schema' === strtolower( $database ) ) {
							throw $this->new_access_denied_to_information_schema_exception();
						}

						try {
							/*
							* Attempt to query the table directly rather than checking
							* SQLite schema or information schema tables, so that we
							* can handle persistent and temporary tables in one query.
							*/
							$this->execute_sqlite_query(
								sprintf( 'SELECT 1 FROM %s LIMIT 0', $table_name )
							);
						} catch ( PDOException $e ) {
							throw $this->new_table_not_found_exception(
								sprintf( '%s.%s', $this->db_name, $table_name ),
								$e
							);
						}
					}

					$this->begin_user_transaction();
					$this->table_lock_active = true;
					return;
				}

				// UNLOCK TABLES/UNLOCK TABLE.
				if (
					WP_MySQL_Lexer::UNLOCK_SYMBOL === $token->id
					&& (
						$subnode->has_child_token( WP_MySQL_Lexer::TABLE_SYMBOL )
						|| $subnode->has_child_token( WP_MySQL_Lexer::TABLES_SYMBOL )
					)
				) {
					// Commit the transaction when created by the LOCK statement.
					if ( $this->table_lock_active && $this->inTransaction() ) {
						$this->commit_user_transaction();
						$this->table_lock_active = false;
					}
					return;
				}

				break;
		}

		throw $this->new_not_supported_exception(
			sprintf(
				'statement type: "%s" > "%s"',
				$node->rule_name,
				$subnode->rule_name
			)
		);
	}

	/**
	 * Translate and execute a MySQL SELECT statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "selectStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_select_statement( WP_Parser_Node $node ): void {
		/*
		 * [GRAMMAR]
		 * selectStatement:
		 *   queryExpression lockingClauseList?
		 *   | selectStatementWithInto
		 */

		// First, translate the query, before we modify last found rows count.
		$query = $this->translate( $node->get_first_child() );

		$has_sql_calc_found_rows = null !== $node->get_first_descendant_token(
			WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL
		);

		// Handle SQL_CALC_FOUND_ROWS.
		if ( true === $has_sql_calc_found_rows ) {
			// Recursively find a query expression with the first LIMIT or SELECT.
			$query_expr = $node->get_first_descendant_node( 'queryExpression' );
			while ( true ) {
				if ( $query_expr->has_child_node( 'limitClause' ) ) {
					break;
				}

				$query_expr_parens = $query_expr->get_first_child_node( 'queryExpressionParens' );
				if ( null !== $query_expr_parens ) {
					$query_expr = $query_expr_parens->get_first_child_node( 'queryExpression' );
					continue;
				}

				$query_expr_body = $query_expr->get_first_child_node( 'queryExpressionBody' );
				if ( count( $query_expr_body->get_children() ) > 1 ) {
					break;
				}

				$query_term = $query_expr_body->get_first_child_node( 'queryTerm' );
				if (
					count( $query_term->get_children() ) === 1
					&& $query_term->has_child_node( 'queryExpressionParens' )
				) {
					$query_expr = $query_term->get_first_child_node( 'queryExpressionParens' )->get_first_child_node( 'queryExpression' );
					continue;
				}

				break;
			}

			// Exclude the limit clause from the expression.
			$count_expr = new WP_Parser_Node( $query_expr->rule_id, $query_expr->rule_name );
			foreach ( $query_expr->get_children() as $child ) {
				if ( ! ( $child instanceof WP_Parser_Node && 'limitClause' === $child->rule_name ) ) {
					$count_expr->append_child( $child );
				}
			}

			// Get count of all the rows.
			$result = $this->execute_sqlite_query(
				'SELECT COUNT(*) AS cnt FROM (' . $this->translate( $count_expr ) . ')'
			);

			$this->found_rows = (int) $result->fetchColumn();
		} else {
			$this->found_rows = $query;
		}

		// Execute the query.
		$stmt = $this->execute_sqlite_query( $query );

		// Store column meta info. This must be done before fetching data, which
		// seems to erase type information for expressions in the SELECT clause.
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
	}

	/**
	 * Translate and execute a MySQL INSERT or REPLACE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "insertStatement" or "replaceStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_insert_or_replace_statement( WP_Parser_Node $node ): void {
		$parts                   = array();
		$on_conflict_update_list = null;
		foreach ( $node->get_children() as $child ) {
			$is_token = $child instanceof WP_MySQL_Token;
			$is_node  = $child instanceof WP_Parser_Node;

			if ( $child instanceof WP_Parser_Node && 'tableRef' === $child->rule_name ) {
				// MySQL supports INSERT without the INTO keyword; SQLite requires it.
				if ( ! $node->has_child_token( WP_MySQL_Lexer::INTO_SYMBOL ) ) {
					$parts[] = 'INTO';
				}

				$database = $this->get_database_name( $child );
				if ( 'information_schema' === strtolower( $database ) ) {
					throw $this->new_access_denied_to_information_schema_exception();
				}
			}

			// Skip the SET keyword in "INSERT INTO ... SET ..." syntax.
			if ( $is_token && WP_MySQL_Lexer::SET_SYMBOL === $child->id ) {
				continue;
			}

			if ( $is_token && WP_MySQL_Lexer::IGNORE_SYMBOL === $child->id ) {
				// Translate "UPDATE IGNORE" to "UPDATE OR IGNORE".
				$parts[] = 'OR IGNORE';
			} elseif (
				$is_node
				&& (
					'insertFromConstructor' === $child->rule_name
					|| 'insertQueryExpression' === $child->rule_name
					|| 'updateList' === $child->rule_name
				)
			) {
				$table_ref  = $node->get_first_child_node( 'tableRef' );
				$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
				$parts[]    = $this->translate_insert_or_replace_body( $table_name, $child );
			} elseif ( $is_node && 'insertUpdateList' === $child->rule_name ) {
				/*
				 * Translate "ON DUPLICATE KEY UPDATE" to "ON CONFLICT DO UPDATE SET".
				 *
				 * For SQLite versions older than 3.35.0, we need to handle the
				 * ON CONFLICT clause differently, and at this stage, we only
				 * save the translated update list to a variable.
				 *
				 * See below at "Handle ON CONFLICT clause for SQLite < 3.35.0".
				 */
				$sqlite_version = $this->get_sqlite_version();
				if ( version_compare( $sqlite_version, '3.35.0', '<' ) ) {
					$on_conflict_update_list = $this->translate_update_list( $table_name, $child );
				} else {
					$parts[] = 'ON CONFLICT DO UPDATE SET ';
					$parts[] = $this->translate_update_list( $table_name, $child );
				}
			} else {
				$parts[] = $this->translate( $child );
			}
		}

		$query = implode( ' ', $parts );

		/*
		 * Handle ON CONFLICT clause for SQLite < 3.35.0.
		 *
		 * If and "$on_conflict_update_list" was saved, we are on SQLite version
		 * older than 3.35.0 and an ON CONFLICT clause was used in the query.
		 *
		 * SQLite supports a generic ON CONFLICT clause without an explicit column
		 * list only from version 3.35.0.
		 *
		 * For older versions, we need to work around this limitation:
		 *   1. Save the ON CONFLICT update list to a variable.
		 *   2. Execute the query without the ON CONFLICT clause.
		 *   3. If a constraint violation error occurs, parse the names of the
		 *      columns that caused the violation from the error message.
		 *   4. Execute the query again, appending the ON CONFLICT clause with
		 *      the column names parsed from the error message.
		 */
		if ( null !== $on_conflict_update_list ) {
			try {
				$this->last_result_statement = $this->execute_sqlite_query( $query );
			} catch ( PDOException $e ) {
				$unique_key_violation_prefix = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: ';
				if ( '23000' === $e->getCode() && str_contains( $e->getMessage(), $unique_key_violation_prefix ) ) {
					/*
					 * Parse column names from the constraint violation error.
					 *
					 * The error message is in the following format:
					 *   <prefix>: <table>.<col1>, <table>.<col2>, ...
					 *
					 * The table and column names in the message are not quoted.
					 * To be on the safe side, we first strip the error message
					 * prefix and the "<table>." part for the first column, and
					 * then split the rest of the list by ", <table>." sequence.
					 */
					$column_list                 = substr( $e->getMessage(), strlen( $unique_key_violation_prefix ) + strlen( $table_name ) + 1 );
					$column_names                = explode( ", $table_name.", $column_list );
					$quoted_column_names         = array_map(
						function ( $column ) {
							return $this->quote_sqlite_identifier( $column );
						},
						$column_names
					);
					$this->last_result_statement = $this->execute_sqlite_query(
						$query . sprintf(
							' ON CONFLICT(%s) DO UPDATE SET %s',
							implode( ', ', $quoted_column_names ),
							$on_conflict_update_list
						)
					);
				} else {
					throw $e;
				}
			}
			$this->last_insert_id = $this->connection->get_last_insert_id();
			return;
		}

		$this->last_result_statement = $this->execute_sqlite_query( $query );
		$this->last_insert_id        = $this->connection->get_last_insert_id();
	}

	/**
	 * Translate and execute a MySQL UPDATE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "updateStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_update_statement( WP_Parser_Node $node ): void {
		// @TODO: Add support for UPDATE with multiple tables and JOINs.
		//        SQLite supports them in the FROM clause.

		$has_order = $node->has_child_node( 'orderClause' );
		$has_limit = $node->has_child_node( 'simpleLimitClause' );

		/*
		 * SQLite doesn't support UPDATE with ORDER BY/LIMIT.
		 * We need to use a subquery to emulate this behavior.
		 *
		 * For instance, the following query:
		 *   UPDATE t SET c = 1 WHERE c = 2 LIMIT 1;
		 * Will be rewritten to:
		 *   UPDATE t SET c = 1 WHERE rowid IN ( SELECT rowid FROM t WHERE c = 2 LIMIT 1 );
		 */
		$where_subquery = null;
		if ( $has_order || $has_limit ) {
			$where_subquery = 'SELECT rowid FROM ' . $this->translate_sequence(
				array(
					$node->get_first_child_node( 'tableReferenceList' ),
					$node->get_first_child_node( 'whereClause' ),
					$node->get_first_child_node( 'orderClause' ),
					$node->get_first_child_node( 'simpleLimitClause' ),
				)
			);
		}

		/*
		 * Translate the UPDATE statement parts.
		 *
		 * [GRAMMAR]
		 * updateStatement:
		 *   withClause? UPDATE_SYMBOL LOW_PRIORITY_SYMBOL? IGNORE_SYMBOL? tableReferenceList
		 *     SET_SYMBOL updateList whereClause? orderClause? simpleLimitClause?
		 */

		// Collect all tables used in the UPDATE clause (e.g, UPDATE t1, t2 JOIN t3).
		$table_alias_map = $this->create_table_reference_map(
			$node->get_first_child_node( 'tableReferenceList' )
		);

		/*
		 * Deny UPDATE for information schema tables.
		 *
		 * This basic approach is rather restrictive, as it blocks the usage
		 * of information schema tables anywhere in the UPDATE statement.
		 *
		 * TODO: Implement support for UPDATE statements like:
		 *         UPDATE t, information_schema.columns c SET t.column = c.column ...
		 */
		foreach ( $table_alias_map as $alias => $data ) {
			if ( 'information_schema' === strtolower( $data['database'] ?? '' ) ) {
				throw $this->new_access_denied_to_information_schema_exception();
			}
		}

		// Determine whether the UPDATE statement modifies multiple tables.
		$update_list_node        = $node->get_first_child_node( 'updateList' );
		$update_target           = null;
		$updates_multiple_tables = false;
		if ( count( $table_alias_map ) > 1 ) {
			foreach ( $update_list_node->get_child_nodes( 'updateElement' ) as $update_element ) {
				$column_ref       = $update_element->get_first_child_node( 'columnRef' );
				$column_ref_parts = $column_ref->get_descendant_nodes( 'identifier' );
				$table_or_alias   = count( $column_ref_parts ) > 1
					? $this->unquote_sqlite_identifier( $this->translate( $column_ref_parts[0] ) )
					: null;

				// When the SET column reference is not qualified, we need to
				// verify whether the column is used in multiple tables.
				if ( null === $table_or_alias ) {
					$persistent_table_names = array();
					$temporary_table_names  = array();
					foreach ( array_filter( array_column( $table_alias_map, 'table_name' ) ) as $table_name ) {
						$is_temporary      = $this->information_schema_builder->temporary_table_exists( $table_name );
						$quoted_table_name = $this->quote_sqlite_value( $table_name );
						if ( $is_temporary ) {
							$temporary_table_names[] = $quoted_table_name;
						} else {
							$persistent_table_names[] = $quoted_table_name;
						}
					}

					$column_name = $this->unquote_sqlite_identifier(
						$this->translate( end( $column_ref_parts ) )
					);

					$matched_temporary_tables = array();
					if ( count( $temporary_table_names ) > 0 ) {
						$matched_temporary_tables = $this->execute_sqlite_query(
							sprintf(
								'SELECT table_name FROM %s WHERE table_schema = ? AND table_name IN ( %s ) AND column_name = ?',
								$this->quote_sqlite_identifier(
									$this->information_schema_builder->get_table_name( true, 'columns' )
								),
								implode( ', ', $temporary_table_names )
							),
							array( $this->get_saved_db_name(), $column_name )
						)->fetchAll( PDO::FETCH_COLUMN );
					}

					$matched_persistent_tables = array();
					if ( count( $persistent_table_names ) > 0 ) {
						$matched_persistent_tables = $this->execute_sqlite_query(
							sprintf(
								'SELECT table_name FROM %s WHERE table_schema = ? AND table_name IN ( %s ) AND column_name = ?',
								$this->quote_sqlite_identifier(
									$this->information_schema_builder->get_table_name( false, 'columns' )
								),
								implode( ', ', $persistent_table_names )
							),
							array( $this->get_saved_db_name(), $column_name )
						)->fetchAll( PDO::FETCH_COLUMN );
					}

					$matched_tables  = array_merge( $matched_temporary_tables, $matched_persistent_tables );
					$matched_aliases = array();
					foreach ( $table_alias_map as $alias => $data ) {
						// Derived tables do not have a table name.
						if ( null === $data['table_name'] ) {
							continue;
						}

						foreach ( $matched_tables as $matched_table ) {
							if ( 0 === strcasecmp( $data['table_name'], $matched_table ) ) {
								$matched_aliases[] = $alias;
								break;
							}
						}
					}
					$updates_multiple_tables = count( $matched_aliases ) > 1;
					if ( 1 === count( $matched_aliases ) ) {
						$table_or_alias = $matched_aliases[0];
					} else {
						break;
					}
				}

				if ( null === $update_target ) {
					$update_target = $table_or_alias;
				}

				if ( $update_target !== $table_or_alias ) {
					$updates_multiple_tables = true;
					break;
				}
			}
		} else {
			$update_target = array_keys( $table_alias_map )[0];
		}

		// TODO: Support UPDATE that modifies multiple tables.
		//       This is non-trivial and likely requires temporary tables.
		//       E.g.: UPDATE t1, t2 SET t1.id = t2.id, t2.id = t1.id;
		if ( $updates_multiple_tables ) {
			throw $this->new_not_supported_exception( 'UPDATE statement modifying multiple tables' );
		}

		// Translate WITH clause.
		$with = $this->translate( $node->get_first_child_node( 'withClause' ) );

		// Translate "UPDATE IGNORE" to "UPDATE OR IGNORE".
		$or_ignore = $node->has_child_token( WP_MySQL_Lexer::IGNORE_SYMBOL )
			? 'OR IGNORE'
			: null;

		// Compose the update target clause.
		$update_target_table  = $table_alias_map[ $update_target ]['table_name'] ?? $update_target;
		$update_target_clause = $this->quote_sqlite_identifier( $update_target_table );
		if ( $update_target !== $update_target_table ) {
			$update_target_clause .= ' AS ' . $this->quote_sqlite_identifier( $update_target );
		}

		// Compose the FROM clause using all tables except the one being updated.
		// UPDATE with FROM in SQLite is equivalent to UPDATE with JOIN in MySQL.
		$from_items = array();
		foreach ( $table_alias_map as $alias => $data ) {
			if ( $alias === $update_target ) {
				continue;
			}

			$table_name = $data['table_name'];

			// Derived table.
			if ( null === $table_name ) {
				$from_item    = $data['table_expr'] . ' AS ' . $this->quote_sqlite_identifier( $alias );
				$from_items[] = $from_item;
				continue;
			}

			// Regular table.
			$from_item = $this->quote_sqlite_identifier( $table_name );
			if ( $alias !== $table_name ) {
				$from_item .= ' AS ' . $this->quote_sqlite_identifier( $alias );
			}
			$from_items[] = $from_item;
		}

		$from = null;
		if ( count( $from_items ) > 0 ) {
			$from = 'FROM ' . implode( ', ', $from_items );
		}

		// Translate UPDATE list, applying relevant type casting and IMPLICIT DEFAULT values.
		$update_list = $this->translate_update_list( $update_target_table, $node, $update_target );

		// Translate WHERE, ORDER BY, and LIMIT clauses.
		if ( $where_subquery ) {
			// When using a subquery, skip the original WHERE, ORDER BY, and LIMIT.
			$where_clause = ' WHERE rowid IN ( ' . $where_subquery . ' )';
			$order_clause = null;
			$limit_clause = null;
		} else {
			$where_clause = $this->translate( $node->get_first_child_node( 'whereClause' ) );
			$order_clause = $this->translate( $node->get_first_child_node( 'orderClause' ) );
			$limit_clause = $this->translate( $node->get_first_child_node( 'simpleLimitClause' ) );
		}

		// With JOINs, we need to use the JOIN expressions in the WHERE clause.
		$join_exprs = array_filter( array_column( $table_alias_map, 'join_expr' ) );
		if ( count( $join_exprs ) > 0 ) {
			$where_clause .= $where_clause ? ' AND ' : ' WHERE ';
			$where_clause .= implode( ' AND ', $join_exprs );
		}

		// Compose the UPDATE query.
		$parts = array(
			$with,
			'UPDATE',
			$or_ignore,
			$update_target_clause,
			'SET',
			$update_list,
			$from,
			$where_clause,
			$order_clause,
			$limit_clause,
		);
		$query = implode( ' ', array_filter( $parts ) );

		$this->last_result_statement = $this->execute_sqlite_query( $query );
	}

	/**
	 * Translate and execute a MySQL DELETE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "deleteStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_delete_statement( WP_Parser_Node $node ): void {
		/*
		 * Multi-table DELETE.
		 *
		 * MySQL supports multi-table DELETE statements that don't work in SQLite.
		 * These statements can have the following two flavours:
		 *  1. "DELETE t1, t2 FROM ... JOIN ... WHERE ..."
		 *  2. "DELETE FROM t1, t2 USING ... JOIN ... WHERE ..."
		 *
		 * We will rewrite such statements into a SELECT to fetch the ROWIDs of
		 * the rows to delete and then execute a DELETE statement for each table.
		 */
		$alias_ref_list = $node->get_first_child_node( 'tableAliasRefList' );
		if ( null !== $alias_ref_list ) {
			// 1. Get table aliases targeted by the DELETE statement.
			$table_aliases = array();
			foreach ( $alias_ref_list->get_child_nodes() as $alias_ref ) {
				$table_aliases[] = $this->unquote_sqlite_identifier(
					$this->translate( $alias_ref )
				);
			}

			// 2. Create an alias to table name map.
			$alias_map      = array();
			$table_ref_list = $node->get_first_child_node( 'tableReferenceList' );
			foreach ( $table_ref_list->get_descendant_nodes( 'singleTable' ) as $single_table ) {
				$table_ref  = $single_table->get_first_child_node( 'tableRef' );
				$alias_node = $single_table->get_first_child_node( 'tableAlias' );
				if ( $alias_node ) {
					$alias = $this->unquote_sqlite_identifier( $this->translate( $alias_node ) );
				} else {
					$alias = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
				}

				// For an information schema table, check if is a DELETE target.
				$database = $this->get_database_name( $table_ref );
				if (
					'information_schema' === strtolower( $database )
					&& in_array( $alias, $table_aliases, true )
				) {
					throw $this->new_access_denied_to_information_schema_exception();
				}

				$alias_map[ $alias ] = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
			}

			// 3. Compose the SELECT query to fetch ROWIDs to delete.
			$where_clause = $node->get_first_child_node( 'whereClause' );
			if ( null !== $where_clause ) {
				$where = $this->translate( $where_clause->get_first_child_node( 'expr' ) );
			}

			$select_list = array();
			foreach ( $table_aliases as $table ) {
				$select_list[] = sprintf(
					'%s.rowid AS %s',
					$this->quote_sqlite_identifier( $table ),
					$this->quote_sqlite_identifier( $table . '_rowid' )
				);
			}

			$ids = $this->execute_sqlite_query(
				sprintf(
					'SELECT %s FROM %s %s',
					implode( ', ', $select_list ),
					$this->translate( $table_ref_list ),
					isset( $where ) ? "WHERE $where" : ''
				)
			)->fetchAll( PDO::FETCH_ASSOC );

			// 4. Execute DELETE statements for each table.
			$affected_rows = 0;
			if ( count( $ids ) > 0 ) {
				foreach ( $table_aliases as $table ) {
					$stmt           = $this->execute_sqlite_query(
						sprintf(
							'DELETE FROM %s AS %s WHERE rowid IN ( %s )',
							$this->quote_sqlite_identifier( $alias_map[ $table ] ),
							$this->quote_sqlite_identifier( $table ),
							implode( ', ', array_column( $ids, "{$table}_rowid" ) )
						)
					);
					$affected_rows += $stmt->rowCount();
				}
			}

			$this->last_result_statement = $this->create_result_statement_from_data( array(), array() );
			$this->last_affected_rows    = $affected_rows;
			return;
		}

		// @TODO: Translate DELETE with JOIN to use a subquery.

		$table_ref = $node->get_first_child_node( 'tableRef' );
		$database  = $this->get_database_name( $table_ref );
		if ( 'information_schema' === strtolower( $database ) ) {
			throw $this->new_access_denied_to_information_schema_exception();
		}

		/*
		 * SQLite doesn't support DELETE with ORDER BY/LIMIT.
		 * We need to use a subquery to emulate this behavior.
		 *
		 * For instance, the following query:
		 *   DELETE FROM t WHERE c = 2 LIMIT 1;
		 * Will be rewritten to:
		 *   DELETE FROM t WHERE rowid IN ( SELECT rowid FROM t WHERE c = 2 LIMIT 1 );
		 */
		$has_order = $node->has_child_node( 'orderClause' );
		$has_limit = $node->has_child_node( 'simpleLimitClause' );
		if ( $has_order || $has_limit ) {
			$where_subquery = 'SELECT rowid FROM ' . $this->translate_sequence(
				array(
					$table_ref,
					$node->get_first_child_node( 'tableAlias' ),
					$node->get_first_child_node( 'whereClause' ),
					$node->get_first_child_node( 'orderClause' ),
					$node->get_first_child_node( 'simpleLimitClause' ),
				)
			);

			$query = sprintf(
				'DELETE FROM %s WHERE rowid IN ( %s )',
				$this->translate( $table_ref ),
				$where_subquery
			);

			$this->last_result_statement = $this->execute_sqlite_query( $query );
			return;
		}

		$query                       = $this->translate( $node );
		$this->last_result_statement = $this->execute_sqlite_query( $query );
	}

	/**
	 * Translate and execute a MySQL CREATE TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "createStatement" AST node with "createTable" child.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_create_table_statement( WP_Parser_Node $node ): void {
		$subnode = $node->get_first_child_node();

		// Handle TEMPORARY keyword.
		$table_is_temporary = $subnode->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );

		// Handle CREATE TABLE ... [AS] SELECT.
		$element_list = $subnode->get_first_child_node( 'tableElementList' );
		if ( null === $element_list ) {
			/*
			 * While SQLite supports CREATE TABLE ... AS SELECT statements,
			 * we need to somehow implement information schema support for
			 * the tables created in this way.
			 *
			 * TODO: Implement information schema support for CREATE TABLE ... AS SELECT.
			 */
			throw $this->new_not_supported_exception(
				'CREATE TABLE ... [AS] SELECT is currently not supported'
			);
		}

		// Get table name.
		$table_name_node = $subnode->get_first_child_node( 'tableName' );
		$database        = $this->get_database_name( $table_name_node );
		$table_name      = $this->unquote_sqlite_identifier( $this->translate( $table_name_node ) );

		if ( 'information_schema' === strtolower( $database ) ) {
			throw $this->new_access_denied_to_information_schema_exception();
		}

		// Handle IF NOT EXISTS.
		if ( $subnode->has_child_node( 'ifNotExists' ) ) {
			if ( $table_is_temporary ) {
				/*
				 * The temporary information schema tables are created with the
				 * first temporary table of a session, so they may not exist yet.
				 */
				$table_exists = $this->information_schema_builder->temporary_table_exists( $table_name );
			} else {
				$tables_table = $this->information_schema_builder->get_table_name( false, 'tables' );
				$table_exists = $this->execute_sqlite_query(
					sprintf(
						'SELECT 1 FROM %s WHERE table_schema = ? AND table_name = ?',
						$this->quote_sqlite_identifier( $tables_table )
					),
					array( $this->get_saved_db_name(), $table_name )
				)->fetchColumn();
			}

			if ( $table_exists ) {
				$this->last_result_statement = $this->create_result_statement_from_data( array(), array() );
				return;
			}
		}

		// Save information to information schema tables.
		$this->information_schema_builder->record_create_table( $node );

		// Generate CREATE TABLE statement from the information schema tables.
		$queries            = $this->get_sqlite_create_table_statement( $table_is_temporary, $table_name );
		$create_table_query = $queries[0];
		$constraint_queries = array_slice( $queries, 1 );

		$this->execute_sqlite_query( $create_table_query );

		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}

		// Apply AUTO_INCREMENT = N table option, if any.
		$this->apply_auto_increment_table_option( $table_is_temporary, $table_name, $node );
	}

	/**
	 * Translate and execute a MySQL ALTER TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "alterStatement" AST node with "alterTable" child.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_alter_table_statement( WP_Parser_Node $node ): void {
		$table_ref  = $node->get_first_descendant_node( 'tableRef' );
		$database   = $this->get_database_name( $table_ref );
		$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
		if ( 'information_schema' === strtolower( $database ) ) {
			throw $this->new_access_denied_to_information_schema_exception();
		}

		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		// Save all column names from the original table.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_names  = $this->execute_sqlite_query(
			sprintf(
				'SELECT
					COLUMN_NAME,
					LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE
				FROM %s WHERE table_schema = ? AND table_name = ?',
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $this->get_saved_db_name( $database ), $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		if ( 0 === count( $column_names ) ) {
			throw $this->new_table_not_found_exception( sprintf( '%s.%s', $database, $table_name ) );
		}

		// Track column renames and removals.
		$column_map = array_combine(
			array_column( $column_names, 'COLUMN_NAME_LOWERCASE' ),
			array_column( $column_names, 'COLUMN_NAME' )
		);
		foreach ( $node->get_descendant_nodes( 'alterListItem' ) as $action ) {
			$first_token = $action->get_first_child_token();

			switch ( $first_token->id ) {
				case WP_MySQL_Lexer::DROP_SYMBOL:
					$name = $this->translate( $action->get_first_child_node( 'fieldIdentifier' ) );
					if ( null !== $name ) {
						$name = $this->unquote_sqlite_identifier( $name );
						unset( $column_map[ strtolower( $name ) ] );
					}
					break;
				case WP_MySQL_Lexer::CHANGE_SYMBOL:
					$old_name = $this->unquote_sqlite_identifier(
						$this->translate( $action->get_first_child_node( 'fieldIdentifier' ) )
					);
					$new_name = $this->unquote_sqlite_identifier(
						$this->translate( $action->get_first_child_node( 'identifier' ) )
					);

					$column_map[ strtolower( $old_name ) ] = $new_name;
					break;
				case WP_MySQL_Lexer::RENAME_SYMBOL:
					$column_ref = $action->get_first_child_node( 'fieldIdentifier' );
					if ( null !== $column_ref ) {
						$old_name = $this->unquote_sqlite_identifier(
							$this->translate( $column_ref )
						);
						$new_name = $this->unquote_sqlite_identifier(
							$this->translate( $action->get_first_child_node( 'identifier' ) )
						);

						$column_map[ strtolower( $old_name ) ] = $new_name;
					}
					break;
			}
		}

		/*
		 * Skip the expensive table rebuild when the statement only carries
		 * table options (e.g. ALTER TABLE t AUTO_INCREMENT = N). These don't
		 * change the schema, so the recreate would be a pointless full copy.
		 */
		if ( count( $node->get_descendant_nodes( 'alterListItem' ) ) > 0 ) {
			$this->information_schema_builder->record_alter_table( $node );
			$this->recreate_table_from_information_schema( $table_is_temporary, $table_name, $column_map );
		}

		// Apply AUTO_INCREMENT = N table option, if any.
		$this->apply_auto_increment_table_option( $table_is_temporary, $table_name, $node );

		// @TODO: Consider using a "fast path" for ALTER TABLE statements that
		//        consist only of operations that SQLite's ALTER TABLE supports.
	}

	/**
	 * Translate and execute a MySQL DROP TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "dropStatement" AST node with "dropTable" child.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_drop_table_statement( WP_Parser_Node $node ): void {
		// Record the changes in the information schema.
		$this->information_schema_builder->record_drop_table( $node );

		// MySQL supports removing multiple tables in a single query DROP query.
		// In SQLite, we need to execute each DROP TABLE statement separately.
		$child_node         = $node->get_first_child_node();
		$table_refs         = $child_node->get_first_child_node( 'tableRefList' )->get_child_nodes();
		$table_is_temporary = $child_node->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );
		$queries            = array();
		foreach ( $table_refs as $table_ref ) {
			$database = $this->get_database_name( $table_ref );
			if ( 'information_schema' === strtolower( $database ) ) {
				throw $this->new_access_denied_to_information_schema_exception();
			}

			$parts = array();
			foreach ( $child_node->get_children() as $child ) {
				$is_token = $child instanceof WP_MySQL_Token;

				// Skip the TEMPORARY keyword.
				if ( $is_token && WP_MySQL_Lexer::TEMPORARY_SYMBOL === $child->id ) {
					continue;
				}

				// Replace table list with the current table reference.
				if ( ! $is_token && 'tableRefList' === $child->rule_name ) {
					// Add a "temp." schema prefix for temporary tables.
					$prefix = $table_is_temporary ? '`temp`.' : '';
					$part   = $prefix . $this->translate( $table_ref );
				} else {
					$part = $this->translate( $child );
				}

				if ( null !== $part ) {
					$parts[] = $part;
				}
			}
			$queries[] = 'DROP ' . implode( ' ', $parts );
		}

		foreach ( $queries as $query ) {
			$this->execute_sqlite_query( $query );
		}
	}

	/**
	 * Translate and execute a MySQL TRUNCATE TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "truncateTableStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_truncate_table_statement( WP_Parser_Node $node ): void {
		$table_ref  = $node->get_first_child_node( 'tableRef' );
		$database   = $this->get_database_name( $table_ref );
		$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
		if ( 'information_schema' === strtolower( $database ) ) {
			throw $this->new_access_denied_to_information_schema_exception();
		}

		$this->execute_sqlite_query(
			sprintf( 'DELETE FROM %s', $this->quote_sqlite_identifier( $table_name ) )
		);
		try {
			$this->last_result_statement = $this->execute_sqlite_query(
				'DELETE FROM sqlite_sequence WHERE name = ?',
				array( $table_name )
			);
		} catch ( PDOException $e ) {
			if ( str_contains( $e->getMessage(), 'no such table' ) ) {
				// The table might not exist if no sequences are used in the DB.
			} else {
				throw $e;
			}
		}
	}

	/**
	 * Translate and execute a MySQL CREATE INDEX statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "createStatement" AST node with "createIndex" child.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_create_index_statement( WP_Parser_Node $node ): void {
		$create_index = $node->get_first_child_node( 'createIndex' );
		$target       = $create_index->get_first_child_node( 'createIndexTarget' );
		$table_ref    = $target->get_first_child_node( 'tableRef' );
		$database     = $this->get_database_name( $table_ref );
		$table_name   = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );

		if ( 'information_schema' === strtolower( $database ) ) {
			throw $this->new_access_denied_to_information_schema_exception();
		}

		$this->information_schema_builder->record_create_index( $node );

		$index_name = $this->unquote_sqlite_identifier(
			$this->translate( $create_index->get_first_child_node( 'indexName' ) )
		);
		$is_unique  = $create_index->has_child_token( WP_MySQL_Lexer::UNIQUE_SYMBOL );

		// Get the key parts.
		$key_list_variants = $target->get_first_child_node( 'keyListVariants' );
		$key_list_nodes    = $key_list_variants->get_first_child_node()->get_child_nodes();
		foreach ( $key_list_nodes as $key_list_node ) {
			if ( 'keyPartOrExpression' === $key_list_node->rule_name ) {
				$key_part_node = $key_list_node->get_first_child();
			} else {
				$key_part_node = $key_list_node;
			}

			if ( 'keyPart' === $key_part_node->rule_name ) {
				$key_part  = $this->translate( $key_part_node->get_first_child_node( 'identifier' ) );
				$direction = $key_part_node->get_first_child_node( 'direction' );
				if ( null !== $direction ) {
					$key_part .= ' ' . $this->translate( $direction );
				}
			} else {
				$key_part = $this->translate( $key_part_node );
			}
			$key_parts[] = $key_part;
		}

		$sqlite_index_name = $this->get_sqlite_index_name( $table_name, $index_name );
		$this->execute_sqlite_query(
			sprintf(
				'CREATE %sINDEX %s ON %s (%s)',
				$is_unique ? 'UNIQUE ' : '',
				$this->quote_sqlite_identifier( $sqlite_index_name ),
				$this->translate( $target->get_first_child_node( 'tableRef' ) ),
				implode( ', ', $key_parts )
			)
		);
	}

	/**
	 * Translate and execute a MySQL DROP INDEX statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "dropStatement" AST node with "dropIndex" child.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_drop_index_statement( WP_Parser_Node $node ): void {
		$drop_index = $node->get_first_child_node( 'dropIndex' );
		$table_ref  = $drop_index->get_first_child_node( 'tableRef' );
		$database   = $this->get_database_name( $table_ref );
		if ( 'information_schema' === strtolower( $database ) ) {
			throw $this->new_access_denied_to_information_schema_exception();
		}

		$this->information_schema_builder->record_drop_index( $node );

		$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
		$index_name = $this->unquote_sqlite_identifier(
			$this->translate( $drop_index->get_first_child_node( 'indexRef' ) )
		);

		/*
		 * In MySQL, "DROP INDEX `PRIMARY` ON <table>" removes the PRIMARY KEY.
		 * This is not supported in SQLite, so in such cases, we need to recreate
		 * the table without the PRIMARY KEY using the updated information schema.
		 */
		if ( 'PRIMARY' === strtoupper( $index_name ) ) {
			$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );
			$this->recreate_table_from_information_schema( $table_is_temporary, $table_name );
			return;
		}

		$sqlite_index_name = $this->get_sqlite_index_name( $table_name, $index_name );
		$this->execute_sqlite_query(
			sprintf(
				'DROP INDEX %s',
				$this->quote_sqlite_identifier( $sqlite_index_name )
			)
		);
	}

	/**
	 * Translate and execute a MySQL SHOW statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_show_statement( WP_Parser_Node $node ): void {
		$tokens   = $node->get_child_tokens();
		$keyword1 = $tokens[1];
		$keyword2 = $tokens[2] ?? null;

		switch ( $keyword1->id ) {
			case WP_MySQL_Lexer::COLLATION_SYMBOL:
				$this->execute_show_collation_statement( $node );
				return;
			case WP_MySQL_Lexer::DATABASES_SYMBOL:
				$this->execute_show_databases_statement( $node );
				return;
			case WP_MySQL_Lexer::COLUMNS_SYMBOL:
			case WP_MySQL_Lexer::FIELDS_SYMBOL:
				$this->execute_show_columns_statement( $node );
				return;
			case WP_MySQL_Lexer::CREATE_SYMBOL:
				if ( WP_MySQL_Lexer::TABLE_SYMBOL === $keyword2->id ) {
					$table_ref  = $node->get_first_child_node( 'tableRef' );
					$database   = $this->get_database_name( $table_ref );
					$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );

					// Refuse SHOW CREATE TABLE for information schema tables,
					// as we don't have the table definitions at the moment.
					if ( 'information_schema' === strtolower( $database ) ) {
						throw $this->new_driver_exception(
							sprintf( "SHOW command denied to user 'sqlite'@'%%' for table '%s'", $table_name ),
							'42000'
						);
					}

					$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

					$sql = $this->get_mysql_create_table_statement( $table_is_temporary, $table_name );

					$this->last_column_meta = array(
						array(
							'native_type' => 'STRING',
							'pdo_type'    => PDO::PARAM_STR,
							'flags'       => array( 'not_null' ),
							'table'       => '',
							'name'        => 'Table',
							'len'         => 256,
							'precision'   => 31,
						),
						array(
							'native_type' => 'STRING',
							'pdo_type'    => PDO::PARAM_STR,
							'flags'       => array( 'not_null' ),
							'table'       => '',
							'name'        => 'Create Table',
							'len'         => strlen( $sql ?? '' ),
							'precision'   => 31,
						),
					);

					$this->last_result_statement = $this->create_result_statement_from_data(
						array_column( $this->last_column_meta, 'name' ),
						null === $sql ? array() : array( array( $table_name, $sql ) )
					);
					$this->found_rows            = null === $sql ? 0 : 1;
					return;
				}
				break;
			case WP_MySQL_Lexer::INDEX_SYMBOL:
			case WP_MySQL_Lexer::INDEXES_SYMBOL:
			case WP_MySQL_Lexer::KEYS_SYMBOL:
				$this->execute_show_index_statement( $node );
				return;
			case WP_MySQL_Lexer::GRANTS_SYMBOL:
				$this->last_result_statement = $this->create_result_statement_from_data(
					array( 'Grants for root@%' ),
					array( array( 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, SHUTDOWN, PROCESS, FILE, REFERENCES, INDEX, ALTER, SHOW DATABASES, SUPER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, REPLICATION SLAVE, REPLICATION CLIENT, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, CREATE USER, EVENT, TRIGGER, CREATE TABLESPACE, CREATE ROLE, DROP ROLE ON *.* TO `root`@`localhost` WITH GRANT OPTION' ) )
				);
				$this->last_column_meta      = array(
					array(
						'native_type' => 'STRING',
						'pdo_type'    => PDO::PARAM_STR,
						'flags'       => array( 'not_null' ),
						'table'       => '',
						'name'        => 'Grants for root@%',
						'len'         => 4096,
						'precision'   => 31,
					),
				);
				$this->found_rows            = 1;
				return;
			case WP_MySQL_Lexer::TABLE_SYMBOL:
				$this->execute_show_table_status_statement( $node );
				return;
			case WP_MySQL_Lexer::TABLES_SYMBOL:
				$this->execute_show_tables_statement( $node );
				return;
			case WP_MySQL_Lexer::VARIABLES_SYMBOL:
				$this->execute_show_variables_statement( $node );
				return;
		}

		throw $this->new_not_supported_exception(
			sprintf(
				'statement type: "%s" > "%s"',
				$node->rule_name,
				$keyword1->get_value()
			)
		);
	}

	/**
	 * Translate and execute a MySQL SHOW VARIABLES statement in SQLite.
	 *
	 * @param WP_Parser_Node $node The "showStatement" AST node.
	 */
	private function execute_show_variables_statement( WP_Parser_Node $node ): void {
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where, 'Variable_name' );
		}

		$query  = sprintf(
			"SELECT column1 AS `Variable_name`, column2 AS `Value`
			FROM (
				VALUES
					('version', ?),
					('version_comment', ?)
			)
			WHERE TRUE %s
			ORDER BY column1",
			$condition ?? ''
		);
		$params = array(
			$this->get_mysql_server_version_string(),
			$this->get_mysql_server_version_comment(),
		);

		$stmt = $this->execute_sqlite_query( $query, $params );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = array( $query, $params );
	}

	/**
	 * Translate and execute a MySQL SHOW COLLATION statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node The "showStatement" AST node.
	 */
	private function execute_show_collation_statement( WP_Parser_Node $node ): void {
		$definition = $this->information_schema_builder
			->get_computed_information_schema_table_definition( 'collations' );

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where, 'collation_name' );
		}

		$query = sprintf(
			'SELECT
				COLLATION_NAME AS `Collation`,
				CHARACTER_SET_NAME AS `Charset`,
				ID AS `Id`,
				IS_DEFAULT AS `Default`,
				IS_COMPILED AS `Compiled`,
				SORTLEN AS `Sortlen`,
				PAD_ATTRIBUTE AS `Pad_attribute`
			FROM (%s)
			WHERE TRUE %s',
			$definition,
			$condition ?? ''
		);
		$stmt  = $this->execute_sqlite_query( $query );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = $query;
	}

	/**
	 * Translate and execute a MySQL SHOW DATABASES statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node The "showStatement" AST node.
	 */
	private function execute_show_databases_statement( WP_Parser_Node $node ): void {
		$schemata_table = $this->information_schema_builder->get_table_name( false, 'schemata' );

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where, 'schema_name' );
		}
		$query  = sprintf(
			'SELECT SCHEMA_NAME AS Database
			FROM (
				SELECT CASE WHEN SCHEMA_NAME = ? THEN ? ELSE SCHEMA_NAME END AS SCHEMA_NAME
				FROM %s
				ORDER BY SCHEMA_NAME
			)%s',
			$this->quote_sqlite_identifier( $schemata_table ),
			isset( $condition ) ? ( ' WHERE TRUE ' . $condition ) : ''
		);
		$params = array(
			$this->get_saved_db_name(),
			$this->main_db_name,
		);

		$stmt = $this->execute_sqlite_query( $query, $params );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = array( $query, $params );
	}

	/**
	 * Translate and execute a MySQL SHOW INDEX statement in SQLite.
	 *
	 * @param WP_Parser_Node $node The "showStatement" AST node.
	 */
	private function execute_show_index_statement( WP_Parser_Node $node ): void {
		// Get database and table name.
		$table_ref = $node->get_first_child_node( 'tableRef' );
		$in_db     = $node->get_first_child_node( 'inDb' );
		if ( $in_db ) {
			// FROM/IN database.
			$database = $this->get_database_name( $in_db );
		} else {
			$database = $this->get_database_name( $table_ref );
		}
		$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );

		// WHERE clause.
		$where = $node->get_first_child_node( 'whereClause' );
		if ( null !== $where ) {
			$value     = $this->translate( $where->get_first_child_node( 'expr' ) );
			$condition = sprintf( 'AND %s', $value );
		} else {
			$condition = '';
		}

		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		/*
		 * TODO: Index naming.
		 *
		 * From the old driver:
		 *
		 * SQLite automatically assigns names to some indexes.
		 * However, dbDelta in WordPress expects the name to be
		 * the same as in the original CREATE TABLE. Let's
		 * translate the name back.
		 *
		 * The old driver does the two following conversions:
		 *   1)
		 *       $mysql_key_name = substr( $mysql_key_name, strlen( 'sqlite_autoindex_' ) );
		 *       $mysql_key_name = preg_replace( '/_[0-9]+$/', '', $mysql_key_name );
		 *   2)
		 *       $mysql_key_name = substr( $mysql_key_name, strlen( "{$table_name}__" ) );
		 */

		$statistics_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'statistics' );
		$query            = sprintf(
			"
				SELECT
					TABLE_NAME AS `Table`,
					NON_UNIQUE AS `Non_unique`,
					INDEX_NAME AS `Key_name`,
					SEQ_IN_INDEX AS `Seq_in_index`,
					COLUMN_NAME AS `Column_name`,
					COLLATION AS `Collation`,
					CARDINALITY AS `Cardinality`,
					SUB_PART AS `Sub_part`,
					PACKED AS `Packed`,
					NULLABLE AS `Null`,
					INDEX_TYPE AS `Index_type`,
					COMMENT AS `Comment`,
					INDEX_COMMENT AS `Index_comment`,
					IS_VISIBLE AS `Visible`,
					EXPRESSION AS `Expression`
				FROM %s
				WHERE table_schema = ?
				AND table_name = ?
				%s
				ORDER BY
					INDEX_NAME = 'PRIMARY' DESC,
					NON_UNIQUE = '0' DESC,
					INDEX_TYPE = 'SPATIAL' DESC,
					INDEX_TYPE = 'BTREE' DESC,
					INDEX_TYPE = 'FULLTEXT' DESC,
					ROWID,
					SEQ_IN_INDEX
			",
			$this->quote_sqlite_identifier( $statistics_table ),
			$condition
		);
		$params           = array(
			$this->get_saved_db_name( $database ),
			$table_name,
		);

		$stmt = $this->execute_sqlite_query( $query, $params );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = array( $query, $params );
	}

	/**
	 * Translate and execute a MySQL SHOW TABLE STATUS statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_show_table_status_statement( WP_Parser_Node $node ): void {
		// FROM/IN database.
		$in_db = $node->get_first_child_node( 'inDb' );
		if ( null === $in_db ) {
			$database = $this->db_name;
		} else {
			$database = $this->unquote_sqlite_identifier(
				$this->translate( $in_db->get_first_child_node( 'identifier' ) )
			);
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where, 'Name' );
		}

		// SHOW TABLE STATUS lists only non-temporary tables.
		$tables_table  = $this->information_schema_builder->get_table_name( false, 'tables' );
		$columns_table = $this->information_schema_builder->get_table_name( false, 'columns' );

		// Compose a subquery to compute auto-increment values.
		$has_sequence_table = (bool) $this->execute_sqlite_query(
			"SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'"
		)->fetchColumn();

		$auto_increment_subquery = sprintf(
			"(
				SELECT COALESCE(s.seq + 1, 1)
				FROM %s AS c
				%s
				WHERE c.extra = 'auto_increment'
				AND c.table_schema = t.table_schema
				AND c.table_name = t.table_name
			)",
			$this->quote_sqlite_identifier( $columns_table ),
			$has_sequence_table
				? 'LEFT JOIN main.sqlite_sequence AS s ON s.name = c.table_name'
				: 'LEFT JOIN (SELECT 0 AS seq) AS s'
		);

		$query  = sprintf(
			'SELECT * FROM (
				SELECT
					table_name AS `Name`,
					engine AS `Engine`,
					version AS `Version`,
					row_format AS `Row_format`,
					table_rows AS `Rows`,
					avg_row_length AS `Avg_row_length`,
					data_length AS `Data_length`,
					max_data_length AS `Max_data_length`,
					index_length AS `Index_length`,
					data_free AS `Data_free`,
					%s AS `Auto_increment`,
					create_time AS `Create_time`,
					update_time AS `Update_time`,
					check_time AS `Check_time`,
					table_collation AS `Collation`,
					checksum AS `Checksum`,
					create_options AS `Create_options`,
					table_comment AS `Comment`
				FROM %s AS t
				WHERE table_schema = ?
			)
			WHERE 1 %s
			ORDER BY `Name`',
			$auto_increment_subquery,
			$this->quote_sqlite_identifier( $tables_table ),
			$condition ?? ''
		);
		$params = array(
			$this->get_saved_db_name( $database ),
		);

		$stmt = $this->execute_sqlite_query( $query, $params );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = array( $query, $params );
	}

	/**
	 * Translate and execute a MySQL SHOW TABLES statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_show_tables_statement( WP_Parser_Node $node ): void {
		// FROM/IN database.
		$in_db = $node->get_first_child_node( 'inDb' );
		if ( null === $in_db ) {
			$database = $this->db_name;
		} else {
			$database = $this->unquote_sqlite_identifier(
				$this->translate( $in_db->get_first_child_node( 'identifier' ) )
			);
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where, 'table_name' );
		}

		// Handle the FULL keyword.
		$command_type = $node->get_first_child_node( 'showCommandType' );
		$is_full      = $command_type && $command_type->has_child_token( WP_MySQL_Lexer::FULL_SYMBOL );

		// Fetch table information.
		$table_tables = $this->information_schema_builder->get_table_name(
			false, // SHOW TABLES lists only non-temporary tables.
			'tables'
		);
		$query        = sprintf(
			'SELECT %s FROM %s WHERE table_schema = ? %s ORDER BY table_name',
			$is_full
				? sprintf( 'table_name AS `Tables_in_%s`, table_type AS `Table_type`', $database )
				: sprintf( 'table_name AS `Tables_in_%s`', $database ),
			$this->quote_sqlite_identifier( $table_tables ),
			$condition ?? ''
		);
		$params       = array(
			$this->get_saved_db_name( $database ),
		);

		$stmt = $this->execute_sqlite_query( $query, $params );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = array( $query, $params );
	}

	/**
	 * Translate and execute a MySQL SHOW COLUMNS statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 * @throws PDOException               When given table doesn't exist.
	 */
	private function execute_show_columns_statement( WP_Parser_Node $node ): void {
		// TODO: EXTENDED, FULL

		// Get database and table name.
		$table_ref = $node->get_first_child_node( 'tableRef' );
		$in_db     = $node->get_first_child_node( 'inDb' );
		if ( $in_db ) {
			// FROM/IN database.
			$database = $this->get_database_name( $in_db );
		} else {
			$database = $this->get_database_name( $table_ref );
		}
		$table_name         = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		// Check if the table exists.
		$tables_tables = $this->information_schema_builder->get_table_name( $table_is_temporary, 'tables' );
		$table_exists  = $this->execute_sqlite_query(
			sprintf(
				'SELECT 1 FROM %s WHERE table_schema = ? AND table_name = ?',
				$this->quote_sqlite_identifier( $tables_tables )
			),
			array( $this->get_saved_db_name( $database ), $table_name )
		)->fetchColumn();

		if ( ! $table_exists ) {
			throw $this->new_table_not_found_exception( sprintf( '%s.%s', $database, $table_name ) );
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where, 'column_name' );
		}

		// Handle the FULL keyword.
		$command_type = $node->get_first_child_node( 'showCommandType' );
		$is_full      = $command_type && $command_type->has_child_token( WP_MySQL_Lexer::FULL_SYMBOL );

		// Fetch column information.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );

		if ( $is_full ) {
			$fields = '
				column_name AS `Field`,
				column_type AS `Type`,
				collation_name AS `Collation`,
				is_nullable AS `Null`,
				column_key AS `Key`,
				column_default AS `Default`,
				extra AS `Extra`,
				privileges AS `Privileges`,
				column_comment AS `Comment`
			';
		} else {
			$fields = '
				column_name AS `Field`,
				column_type AS `Type`,
				is_nullable AS `Null`,
				column_key AS `Key`,
				column_default AS `Default`,
				extra AS `Extra`
			';
		}

		$query  = sprintf(
			'SELECT %s
			FROM %s
			WHERE table_schema = ? AND table_name = ? %s
			ORDER BY ordinal_position',
			$fields,
			$this->quote_sqlite_identifier( $columns_table ),
			$condition ?? ''
		);
		$params = array(
			$this->get_saved_db_name( $database ),
			$table_name,
		);

		$stmt = $this->execute_sqlite_query( $query, $params );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = array( $query, $params );
	}

	/**
	 * Translate and execute a MySQL DESCRIBE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "describeStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_describe_statement( WP_Parser_Node $node ): void {
		$table_ref  = $node->get_first_child_node( 'tableRef' );
		$database   = $this->get_database_name( $table_ref );
		$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );

		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$query         = sprintf(
			'SELECT
				column_name AS `Field`,
				column_type AS `Type`,
				is_nullable AS `Null`,
				column_key AS `Key`,
				column_default AS `Default`,
				extra AS `Extra`
			FROM %s
			WHERE table_schema = ?
			AND table_name = ?
			ORDER BY ordinal_position',
			$this->quote_sqlite_identifier( $columns_table )
		);
		$params        = array(
			$this->get_saved_db_name( $database ),
			$table_name,
		);

		$stmt = $this->execute_sqlite_query( $query, $params );
		$this->store_last_column_meta_from_statement( $stmt );
		$this->last_result_statement = $stmt;
		$this->found_rows            = array( $query, $params );
	}

	/**
	 * Translate and execute a MySQL USE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "useStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_use_statement( WP_Parser_Node $node ): void {
		$database_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_child_node( 'identifier' ) )
		);
		$database_name = strtolower( $database_name );

		if ( $this->main_db_name === $database_name || 'information_schema' === $database_name ) {
			$this->db_name = $database_name;
		} else {
			throw $this->new_not_supported_exception(
				sprintf(
					"can't use schema '%s', only '%s' and 'information_schema' are supported",
					$database_name,
					$this->db_name
				)
			);
		}
	}

	/**
	 * Translate and execute a MySQL SET statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "setStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_set_statement( WP_Parser_Node $node ): void {
		/*
		 * 1. Flatten the SET statement into a single array of definitions.
		 *
		 * The grammar is non-trivial, and supports multi-statements like:
		 *   SET @var = '...', SESSION sql_mode = '...', @@GLOBAL.time_zone = '...', @@debug = '...', ...
		 *
		 * This will be flattened into a single array of grammar node lists:
		 *   [
		 *     [ <userVariable>, <equal>, <expr> ],
		 *     [ <optionType>, <internalVariableName>, <equal>, <setExprOrDefault> ],
		 *     [ <setSystemVariable>, <equal>, <setExprOrDefault> ],
		 *     [ <setSystemVariable>, <equal>, <setExprOrDefault> ],
		 *   ]
		 */
		$subnode = $node->get_first_child_node();
		if ( $subnode->has_child_node( 'optionValueNoOptionType' ) ) {
			$start_node  = $subnode->get_first_child_node( 'optionValueNoOptionType' );
			$definitions = array( $start_node->get_children() );
		} elseif ( $subnode->has_child_node( 'startOptionValueListFollowingOptionType' ) ) {
			$start_node  = $subnode
				->get_first_child_node( 'startOptionValueListFollowingOptionType' )
				->get_first_child_node( 'optionValueFollowingOptionType' ) ?? $node;
			$definitions = array(
				array_merge(
					array( $subnode->get_first_child_node( 'optionType' ) ),
					$start_node->get_children()
				),
			);
		} else {
			$definitions = array( $subnode->get_children() );
		}

		$continue_node = $subnode->get_first_child_node( 'optionValueListContinued' );
		if ( $continue_node ) {
			foreach ( $continue_node->get_child_nodes( 'optionValue' ) as $child ) {
				$node          = $child->get_first_child_node( 'optionValueNoOptionType' ) ?? $child;
				$definitions[] = $node->get_child_nodes();
			}
		}

		/*
		 * 2. Iterate and process the SET definitions.
		 *
		 * When an "optionType" node is encountered (such as "SESSION var = ..."),
		 * it's value is used for all following system variable assignments that
		 * have no type keyword specified, until the next "optionType" is found.
		 *
		 * This doesn't apply to "@@" type prefixes (such as "@@SESSION.var_name"),
		 * which always impact only the immediately following system variable.
		 */
		$default_type = WP_MySQL_Lexer::SESSION_SYMBOL;
		foreach ( $definitions as $definition ) {
			// Check if the definition starts with an "optionType" node with
			// one of the SESSION, GLOBAL, PERSIST, or PERSIST_ONLY tokens.
			$part = array_shift( $definition );
			if ( $part instanceof WP_Parser_Node && 'optionType' === $part->rule_name ) {
				$default_type = $part->get_first_child_token()->id;
				$part         = array_shift( $definition );
			}

			if (
				$part instanceof WP_MySQL_Token
				&& WP_MySQL_Lexer::NAMES_SYMBOL === $part->id
			) {
				// "SET NAMES ..." is a no-op for now.
				// TODO: Validate charset compatibility with UTF-8.
				//       See: https://github.com/WordPress/sqlite-database-integration/issues/192
			} elseif (
				$part instanceof WP_Parser_Node
				&& 'charsetClause' === $part->rule_name
			) {
				// "SET CHARACTER SET ..." is a no-op for now.
				// TODO: Validate charset compatibility with UTF-8.
				//       See: https://github.com/WordPress/sqlite-database-integration/issues/192
			} elseif (
				$part instanceof WP_Parser_Node
				&& (
					'internalVariableName' === $part->rule_name
					|| 'setSystemVariable' === $part->rule_name
				)
			) {
				// Set a system variable.
				array_shift( $definition ); // Remove the '='.
				$value = array_shift( $definition );
				$this->execute_set_system_variable_statement( $part, $value, $default_type );
			} elseif (
				$part instanceof WP_Parser_Node
				&& 'userVariable' === $part->rule_name
			) {
				// Set a user variable.
				array_shift( $definition ); // Remove the '='.
				$value = array_shift( $definition );
				$this->execute_set_user_variable_statement( $part, $value );
			} else {
				throw $this->new_not_supported_exception(
					sprintf( 'SET statement: %s', $node->rule_name )
				);
			}
		}

		$this->last_result_statement = $this->create_result_statement_from_data( array(), array() );
	}

	/**
	 * Translate and execute a MySQL SET statement for system variables.
	 *
	 * @param  WP_Parser_Node $set_var_node  The "internalVariableName" or "setSystemVariable" AST node.
	 * @param  WP_Parser_Node $value_node    The "setExprOrDefault" AST node.
	 * @param  int            $default_type  The currently active default variable type.
	 *                                       One of the SESSION, GLOBAL, PERSIST, PERSIST_ONLY tokens.
	 * @throws WP_MySQL_On_SQLite_Exception  When the query execution fails.
	 */
	private function execute_set_system_variable_statement(
		WP_Parser_Node $set_var_node,
		WP_Parser_Node $value_node,
		int $default_type
	): void {
		// Get the variable name.
		$internal_variable_name = 'setSystemVariable' === $set_var_node->rule_name
			? $set_var_node->get_first_child_node( 'internalVariableName' )
			: $set_var_node;

		$name = strtolower(
			$this->unquote_sqlite_identifier(
				$this->translate( $internal_variable_name )
			)
		);

		// Get the type attribute (one of SESSION, GLOBAL, PERSIST, PERSIST_ONLY).
		$type = $default_type;
		if ( $set_var_node->has_child_node( 'setVarIdentType' ) ) {
			$var_ident_type = $set_var_node->get_first_child_node( 'setVarIdentType' );
			$type           = $var_ident_type->get_first_child_token()->id;
		}

		/*
		 * Some MySQL system variables values can be set using an unquoted pure
		 * identifier rather than a string literal. This includes non-reserved
		 * keywords. This is equivalent to using a corresponding string literal.
		 *
		 * For example, the following statement pairs are equivalent:
		 *
		 *   SET default_storage_engine = InnoDB
		 *   SET default_storage_engine = 'InnoDB'
		 *
		 *   SET default_collation_for_utf8mb4 = utf8mb4_0900_ai_ci
		 *   SET default_collation_for_utf8mb4 = 'utf8mb4_0900_ai_ci'
		 *
		 * In this cases, we need to use the value directly without attempting
		 * to evaluate the expression, as that would result in a query error.
		 * In the grammar, unquoted identifiers are captured by "columnRef".
		 */
		$identifier = $this->translate( $value_node->get_first_descendant_node( 'columnRef' ) );
		if ( $identifier && $identifier === $this->translate( $value_node ) ) {
			$value = $this->unquote_sqlite_identifier( $identifier );
		} elseif ( ! $value_node->has_child_node( 'expr' ) ) {
			$value = $this->unquote_sqlite_identifier( $this->translate( $value_node ) );
		} else {
			$value = $this->evaluate_expression( $value_node );
		}

		/*
		 * Handle ON/OFF values. They are accepted as both strings and keywords.
		 *
		 * @TODO: This is actually variable-specific and depends on its type.
		 *        For example:
		 *          SET autocommit = OFF;                   SELECT @@autocommit;                 -> 0
		 *          SET autocommit = false;                 SELECT @@autocommit;                 -> 0
		 *          SET session_track_gtids = OFF;          SELECT @@session_track_gtids;        -> OFF
		 *          SET session_track_gtids = false;        SELECT @@session_track_gtids;        -> OFF
		 *          SET updatable_views_with_limit = OFF;   ERROR 1231 (42000)
		 *          SET updatable_views_with_limit = false; SELECT @@updatable_views_with_limit; -> NO
		 */
		$lowercase_value = null === $value ? null : strtolower( $value );
		if ( 'sql_mode' !== $name && ( 'on' === $lowercase_value || 'off' === $lowercase_value ) ) {
			$value = 'on' === $lowercase_value ? 1 : 0;
		}

		if ( WP_MySQL_Lexer::SESSION_SYMBOL === $type ) {
			if ( 'sql_mode' === $name ) {
				if ( null !== $value_node->get_first_child_token( WP_MySQL_Lexer::DEFAULT_SYMBOL ) ) {
					$sql_modes = $this->get_default_sql_modes();
				} elseif ( is_string( $value ) ) {
					/*
					 * MySQL removes spaces only from the end of the complete value.
					 * Spaces within individual mode names remain invalid.
					 *
					 * See: https://github.com/mysql/mysql-server/blob/8.4/sql/strfunc.cc#L39-L64
					 */
					$sql_modes = explode( ',', rtrim( $value, ' ' ) );
				} else {
					$sql_modes = $value;
				}
				$this->set_sql_modes( $sql_modes );
			} else {
				$this->session_system_variables[ $name ] = $value;
			}
		} elseif ( WP_MySQL_Lexer::GLOBAL_SYMBOL === $type ) {
			throw $this->new_not_supported_exception( "SET statement type: 'GLOBAL'" );
		} elseif ( WP_MySQL_Lexer::PERSIST_SYMBOL === $type ) {
			throw $this->new_not_supported_exception( "SET statement type: 'PERSIST'" );
		} elseif ( WP_MySQL_Lexer::PERSIST_ONLY_SYMBOL === $type ) {
			throw $this->new_not_supported_exception( "SET statement type: 'PERSIST_ONLY'" );
		}

		// TODO: Handle GLOBAL, PERSIST, and PERSIST_ONLY types.
	}

	/**
	 * Translate and execute a MySQL SET statement for user variables.
	 *
	 * @param  WP_Parser_Node $user_variable The "userVariable" AST node.
	 * @param  WP_Parser_Node $expr          The "expr" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception  When the query execution fails.
	 */
	private function execute_set_user_variable_statement(
		WP_Parser_Node $user_variable,
		WP_Parser_Node $expr
	): void {
		$name  = $this->unquote_sqlite_identifier(
			$this->translate( $user_variable->get_first_child() )
		);
		$name  = strtolower( substr( $name, 1 ) ); // Remove '@', normalize case.
		$value = $this->evaluate_expression( $expr );

		$this->user_variables[ $name ] = $value;
	}

	/**
	 * Translate and execute a MySQL administration statement in SQLite.
	 *
	 * This emulates the following MySQL statements:
	 *  - ANALYZE TABLE
	 *  - CHECK TABLE
	 *  - OPTIMIZE TABLE
	 *  - REPAIR TABLE
	 *
	 * @param  WP_Parser_Node $node       A "tableAdministrationStatement" AST node.
	 * @throws WP_MySQL_On_SQLite_Exception When the query execution fails.
	 */
	private function execute_administration_statement( WP_Parser_Node $node ): void {
		$first_token    = $node->get_first_child_token();
		$table_ref_list = $node->get_first_child_node( 'tableRefList' );
		$savepoint_name = $this->get_internal_savepoint_name( 'table_administration' );
		$results        = array();
		foreach ( $table_ref_list->get_child_nodes( 'tableRef' ) as $table_ref ) {
			$database = $this->get_database_name( $table_ref );
			if ( 'information_schema' === strtolower( $database ) ) {
				throw $this->new_access_denied_to_information_schema_exception();
			}

			$table_name        = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
			$quoted_table_name = $this->quote_sqlite_identifier( $table_name );

			// Each table must be atomic because failures are reported as result rows.
			$this->execute_sqlite_query( sprintf( 'SAVEPOINT %s', $savepoint_name ) );
			try {
				switch ( $first_token->id ) {
					case WP_MySQL_Lexer::ANALYZE_SYMBOL:
						$stmt   = $this->execute_sqlite_query( sprintf( 'ANALYZE %s', $quoted_table_name ) );
						$errors = $stmt->fetchAll( PDO::FETCH_COLUMN );
						break;
					case WP_MySQL_Lexer::CHECK_SYMBOL:
						$stmt   = $this->execute_sqlite_query(
							sprintf( 'PRAGMA integrity_check(%s)', $quoted_table_name )
						);
						$errors = $stmt->fetchAll( PDO::FETCH_COLUMN );
						if ( 'ok' === $errors[0] ) {
							array_shift( $errors );
						}
						break;
					case WP_MySQL_Lexer::OPTIMIZE_SYMBOL:
					case WP_MySQL_Lexer::REPAIR_SYMBOL:
						/*
						 * SQLite doesn't support OPTIMIZE and REPAIR TABLE commands.
						 * We will recreate the table and copy the data instead.
						 * This corresponds to older MySQL OPTIMIZE TABLE behavior
						 * and still applies to some storage engines in some cases.
						 */
						$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );
						$this->recreate_table_from_information_schema( $table_is_temporary, $table_name );
						$errors = array();
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$first_token->get_value()
							)
						);
				}
			} catch ( PDOException $e ) {
				$this->execute_sqlite_query( sprintf( 'ROLLBACK TO SAVEPOINT %s', $savepoint_name ) );
				if ( '42S02' === $e->getCode() && isset( $e->errorInfo[2] ) ) {
					$errors = array( $e->errorInfo[2] );
				} elseif (
					// SQLite reports a missing table as a general error.
					'HY000' === $e->getCode()
					&& str_starts_with( $e->errorInfo[2] ?? '', 'no such table' )
				) {
					$errors = array( "Table '$table_name' doesn't exist" );
				} else {
					$errors = array( $e->getMessage() );
				}
			}
			$this->execute_sqlite_query( sprintf( 'RELEASE SAVEPOINT %s', $savepoint_name ) );

			$operation = strtolower( $first_token->get_value() );
			foreach ( $errors as $error ) {
				$results[] = array(
					'Table'    => $this->db_name . '.' . $table_name,
					'Op'       => $operation,
					'Msg_type' => 'Error',
					'Msg_text' => $error,
				);
			}
			$results[] = array(
				'Table'    => $this->db_name . '.' . $table_name,
				'Op'       => $operation,
				'Msg_type' => 'status',
				'Msg_text' => count( $errors ) > 0 ? 'Operation failed' : 'OK',
			);
		}

		$this->last_column_meta      = array(
			array(
				'native_type' => 'STRING',
				'pdo_type'    => PDO::PARAM_STR,
				'flags'       => array(),
				'table'       => '',
				'name'        => 'Table',
				'len'         => 512,
				'precision'   => 31,
			),
			array(
				'native_type' => 'STRING',
				'pdo_type'    => PDO::PARAM_STR,
				'flags'       => array(),
				'table'       => '',
				'name'        => 'Op',
				'len'         => 40,
				'precision'   => 31,
			),
			array(
				'native_type' => 'STRING',
				'pdo_type'    => PDO::PARAM_STR,
				'flags'       => array(),
				'table'       => '',
				'name'        => 'Msg_type',
				'len'         => 40,
				'precision'   => 31,
			),
			array(
				'native_type' => 'TEXT',
				'pdo_type'    => PDO::PARAM_STR,
				'flags'       => array(),
				'table'       => '',
				'name'        => 'Msg_text',
				'len'         => 1572864,
				'precision'   => 31,
			),
		);
		$this->last_result_statement = $this->create_result_statement_from_data(
			array_column( $this->last_column_meta, 'name' ),
			$results
		);
	}

	/**
	 * Evaluate an expression and return the value, preserving its type.
	 *
	 * This is used to support expressions in SET statements for MySQL variables.
	 *
	 * @param  WP_Parser_Node $node The "expr" AST node.
	 * @return mixed                The value of the expression.
	 */
	private function evaluate_expression( WP_Parser_Node $node ) {
		// To support expressions, we'll use a SQLite query.
		$stmt = $this->execute_sqlite_query(
			sprintf( 'SELECT %s', $this->translate( $node ) )
		);

		// MySQL variables are typed, so we need to preserve the value type.
		$value = $stmt->fetchColumn();
		$type  = $stmt->getColumnMeta( 0 )['native_type'];
		if ( 'null' === $type ) {
			return null;
		} elseif ( 'integer' === $type ) {
			return (int) $value;
		} elseif ( 'double' === $type ) {
			return (float) $value;
		}
		return $value;
	}

	/**
	 * Translate a MySQL AST node or token to an SQLite query fragment.
	 *
	 * @param  WP_Parser_Node|WP_MySQL_Token $node The AST node to translate.
	 * @return string|null                         The translated query fragment.
	 * @throws WP_MySQL_On_SQLite_Exception        When the translation fails.
	 */
	private function translate( $node ): ?string {
		if ( null === $node ) {
			return null;
		}

		if ( $node instanceof WP_MySQL_Token ) {
			return $this->translate_token( $node );
		}

		if ( ! $node instanceof WP_Parser_Node ) {
			throw $this->new_driver_exception(
				sprintf(
					'Expected a WP_Parser_Node or WP_MySQL_Token instance, got: %s',
					gettype( $node )
				)
			);
		}

		$rule_name = $node->rule_name;
		switch ( $rule_name ) {
			case 'queryExpression':
				return $this->translate_query_expression( $node );
			case 'querySpecification':
				return $this->translate_query_specification( $node );
			case 'tableRef':
				return $this->translate_table_ref( $node );
			case 'qualifiedIdentifier':
			case 'tableRefWithWildcard':
				$parts = $node->get_descendant_nodes( 'identifier' );
				if ( count( $parts ) === 2 ) {
					return $this->translate_qualified_identifier( $parts[0], $parts[1] );
				}
				return $this->translate_qualified_identifier( null, $parts[0] );
			case 'fieldIdentifier':
			case 'simpleIdentifier':
				$parts = $node->get_descendant_nodes( 'identifier' );
				if ( count( $parts ) === 3 ) {
					return $this->translate_qualified_identifier( $parts[0], $parts[1], $parts[2] );
				} elseif ( count( $parts ) === 2 ) {
					return $this->translate_qualified_identifier( null, $parts[0], $parts[1] );
				}
				return $this->translate_qualified_identifier( null, null, $parts[0] );
			case 'tableWild':
				$parts = $node->get_descendant_nodes( 'identifier' );
				if ( count( $parts ) === 2 ) {
					return $this->translate_qualified_identifier( $parts[0], $parts[1] ) . '.*';
				}
				return $this->translate_qualified_identifier( null, $parts[0] ) . '.*';
			case 'dotIdentifier':
				return $this->translate_sequence( $node->get_children(), '' );
			case 'identifierKeyword':
				return '`' . $this->translate( $node->get_first_child() ) . '`';
			case 'pureIdentifier':
				$value = $this->translate_pure_identifier( $node );

				/*
				 * At the moment, we only support ASCII bytes in all identifiers.
				 * This is because SQLite doesn't support case-insensitive Unicode
				 * character matching: https://sqlite.org/faq.html#q18
				 */
				for ( $i = 0; $i < strlen( $value ); $i++ ) {
					if ( ord( $value[ $i ] ) > 127 ) {
						throw $this->new_driver_exception(
							'The SQLite driver only supports ASCII characters in identifiers.'
						);
					}
				}
				return $value;
			case 'textStringLiteral':
				return $this->translate_string_literal( $node );
			case 'dataType':
			case 'nchar':
				$child = $node->get_first_child();
				if ( $child instanceof WP_Parser_Node ) {
					return $this->translate( $child );
				}

				// Handle optional prefixes (data type is the second token):
				//  1. LONG VARCHAR, LONG CHAR(ACTER) VARYING, LONG VARBINARY.
				//  2. NATIONAL CHAR, NATIONAL VARCHAR, NATIONAL CHAR(ACTER) VARYING.
				if ( WP_MySQL_Lexer::LONG_SYMBOL === $child->id ) {
					$child = $node->get_child_tokens()[1] ?? null;
				} elseif ( WP_MySQL_Lexer::NATIONAL_SYMBOL === $child->id ) {
					$child = $node->get_child_tokens()[1] ?? null;
				}

				if ( null === $child ) {
					throw $this->new_invalid_input_exception();
				}

				$type_token = self::DATA_TYPE_MAP[ $child->id ] ?? null;
				if ( null !== $type_token ) {
					return $type_token;
				}

				// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
				if ( WP_MySQL_Lexer::SERIAL_SYMBOL === $child->id ) {
					return 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE';
				}

				// @TODO: Handle SET and JSON.
				throw $this->new_not_supported_exception(
					sprintf( 'data type: %s', $child->get_value() )
				);
			case 'selectItem':
				return $this->translate_select_item( $node );
			case 'fromClause':
				// FROM DUAL is MySQL-specific syntax that means "FROM no tables"
				// and it is equivalent to omitting the FROM clause entirely.
				if ( $node->has_child_token( WP_MySQL_Lexer::DUAL_SYMBOL ) ) {
					return null;
				}
				return $this->translate_sequence( $node->get_children() );
			case 'bitExpr':
				return $this->translate_bit_expr( $node );
			case 'simpleExprBody':
				return $this->translate_simple_expr_body( $node );
			case 'predicateOperations':
				$token = $node->get_first_child_token();
				if ( WP_MySQL_Lexer::LIKE_SYMBOL === $token->id ) {
					return $this->translate_like( $node );
				} elseif ( WP_MySQL_Lexer::REGEXP_SYMBOL === $token->id ) {
					return $this->translate_regexp_functions( $node );
				}
				return $this->translate_sequence( $node->get_children() );
			case 'runtimeFunctionCall':
				return $this->translate_runtime_function_call( $node );
			case 'functionCall':
				return $this->translate_function_call( $node );
			case 'substringFunction':
				$nodes = $node->get_child_nodes();
				if ( count( $nodes ) === 2 ) {
					return sprintf(
						'SUBSTR(%s, %s)',
						$this->translate( $nodes[0] ),
						$this->translate( $nodes[1] )
					);
				} else {
					return sprintf(
						'SUBSTR(%s, %s, %s)',
						$this->translate( $nodes[0] ),
						$this->translate( $nodes[1] ),
						$this->translate( $nodes[2] )
					);
				}
			case 'systemVariable':
				$var_ident_type = $node->get_first_child_node( 'varIdentType' );
				$type_token     = $var_ident_type ? $var_ident_type->get_first_child_token() : null;
				$original_name  = $this->unquote_sqlite_identifier(
					$this->translate( $node->get_first_child_node( 'textOrIdentifier' ) )
				);

				$name = strtolower( $original_name );
				$type = $type_token ? $type_token->id : WP_MySQL_Lexer::SESSION_SYMBOL;
				if ( 'sql_mode' === $name ) {
					$value = implode( ',', $this->get_active_sql_mode_names() );
				} elseif ( 'version' === $name ) {
					$value = $this->get_mysql_server_version_string();
				} elseif ( 'version_comment' === $name ) {
					$value = $this->get_mysql_server_version_comment();
				} elseif ( WP_MySQL_Lexer::SESSION_SYMBOL === $type ) {
					$value = $this->session_system_variables[ $name ] ?? null;
				} else {
					// When we have no value, it's reasonable to use NULL.
					$value = null;
				}

				// @TODO: Emulate more system variables, or use reasonable defaults.
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variable-reference.html
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html
				if ( null === $value ) {
					return 'NULL';
				}
				if ( is_string( $value ) ) {
					return $this->quote_sqlite_value( $value );
				}
				return (string) $value;
			case 'userVariable':
				$name  = $this->unquote_sqlite_identifier( $this->translate( $node->get_first_child() ) );
				$name  = strtolower( substr( $name, 1 ) ); // Remove '@', normalize case.
				$value = $this->user_variables[ $name ] ?? null;
				if ( null === $value ) {
					return 'NULL';
				}
				if ( is_string( $value ) ) {
					return $this->quote_sqlite_value( $value );
				}
				return (string) $value;
			case 'castType':
				$first_child = $node->get_first_child();
				if ( $first_child instanceof WP_Parser_Node ) {
					$first_token = $first_child->get_first_child_token();
				} else {
					$first_token = $first_child;
				}
				switch ( $first_token->id ) {
					case WP_MySQL_Lexer::BINARY_SYMBOL:
						return 'BLOB';
					case WP_MySQL_Lexer::CHAR_SYMBOL:
					case WP_MySQL_Lexer::NCHAR_SYMBOL:
					case WP_MySQL_Lexer::NATIONAL_SYMBOL:
					case WP_MySQL_Lexer::DATE_SYMBOL:
					case WP_MySQL_Lexer::TIME_SYMBOL:
					case WP_MySQL_Lexer::DATETIME_SYMBOL:
					case WP_MySQL_Lexer::JSON_SYMBOL:
						return 'TEXT';
					case WP_MySQL_Lexer::SIGNED_SYMBOL:
					case WP_MySQL_Lexer::UNSIGNED_SYMBOL:
						// @TODO: Emulate UNSIGNED semantics. MySQL wraps negative
						//        values, but SQLite has no unsigned integer type.
						return 'INTEGER';
					case WP_MySQL_Lexer::DECIMAL_SYMBOL:
					case WP_MySQL_Lexer::FLOAT_SYMBOL:
					case WP_MySQL_Lexer::REAL_SYMBOL:
					case WP_MySQL_Lexer::DOUBLE_SYMBOL:
						return 'REAL';
					default:
						throw $this->new_not_supported_exception(
							sprintf( 'cast type: %s', $first_child->get_value() )
						);
				}
			case 'defaultCollation':
				// @TODO: Check and save in information schema.
				return null;
			case 'duplicateAsQueryExpression':
				// @TODO: How to handle IGNORE/REPLACE?

				// The "AS" keyword is optional in MySQL, but required in SQLite.
				return 'AS ' . $this->translate( $node->get_first_child_node() );
			case 'indexHint':
			case 'indexHintList':
				return null;
			case 'lockingClause':
				// SQLite doesn't support locking clauses (SELECT ... FOR UPDATE).
				// They are not needed in SQLite due to the database file locking.
				return null;
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate a MySQL token to SQLite.
	 *
	 * @param  WP_MySQL_Token $token The MySQL token to translate.
	 * @return string|null           The translated value.
	 */
	private function translate_token( WP_MySQL_Token $token ): ?string {
		switch ( $token->id ) {
			case WP_MySQL_Lexer::EOF:
				return null;
			case WP_MySQL_Lexer::BIN_NUMBER:
				/*
				 * There are no binary literals in SQLite. We need to convert all
				 * MySQL binary string values to HEX strings in SQLite (x'...').
				 */
				$value = $token->get_value();
				if ( '0' === $value[0] ) {
					// 0b...
					$value = substr( $value, 2 );
				} else {
					// b'...' or B'...'
					$value = substr( $value, 2, -1 );
				}

				// Convert the binary string to HEX.
				$hex = base_convert( $value, 2, 16 );

				/*
				 * The "base_convert()" function doesn't add or preserve padding.
				 * Let's compute how many bytes we expect and pad the HEX value
				 * to full bytes (SQLite requires HEX strings of even length).
				 */
				$byte_count = (int) ceil( strlen( $value ) / 8 );
				$hex        = str_pad( $hex, $byte_count * 2, '0', STR_PAD_LEFT );
				return sprintf( "x'%s'", $hex );
			case WP_MySQL_Lexer::HEX_NUMBER:
				/*
				 * In MySQL, "0x" prefixed values represent binary literal values,
				 * while in SQLite, that would be a hexadecimal number. Therefore,
				 * we need to convert the 0x... syntax to x'...'.
				 */
				$value = $token->get_value();
				if ( '0' === $value[0] && 'x' === $value[1] ) {
					$hex = substr( $value, 2 );

					// MySQL pads odd-length 0x literals with a leading zero.
					if ( 1 === strlen( $hex ) % 2 ) {
						$hex = '0' . $hex;
					}
					return sprintf( "x'%s'", $hex );
				}
				return $value;
			case WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL:
				return 'AUTOINCREMENT';
			case WP_MySQL_Lexer::BINARY_SYMBOL:
				/*
				 * "BINARY expr" is translated in "translate_simple_expr_body()".
				 * Returning null here is a safety net for any unhandled context
				 * where a bare BINARY token would otherwise leak into the output.
				 */
				return null;
			case WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL:
				/*
				 * The "SQL_CALC_FOUND_ROWS" keyword is implemented in the select
				 * statement translation and then removed from the output here.
				 */
				return null;
			default:
				return $token->get_value();
		}
	}

	/**
	 * Translate a sequence of MySQL AST nodes to SQLite.
	 *
	 * @param  array<WP_Parser_Node|WP_MySQL_Token> $nodes     The MySQL token to translate.
	 * @param  string                               $separator The separator to use between fragments.
	 * @return string|null                                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception                    When the translation fails.
	 */
	private function translate_sequence( array $nodes, string $separator = ' ' ): ?string {
		$parts = array();
		foreach ( $nodes as $node ) {
			if ( null === $node ) {
				continue;
			}

			$translated = $this->translate( $node );
			if ( null === $translated ) {
				continue;
			}
			$parts[] = $translated;
		}
		if ( 0 === count( $parts ) ) {
			return null;
		}
		return implode( $separator, $parts );
	}

	/**
	 * Translate a MySQL string literal to SQLite.
	 *
	 * @param  WP_Parser_Node $node The "textStringLiteral" AST node.
	 * @return string               The translated value.
	 */
	private function translate_string_literal( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();
		$value = $token->get_value();

		/*
		 * Translate datetime literals.
		 *
		 * Process only strings that could possibly represent a date or datetime
		 * literal ("YYYY-M-D", "YYYY-MM-DDTHH:MM:SSZ", etc.).
		 */
		if ( strlen( $value ) >= 8 && is_numeric( $value[0] ) ) {
			$value = $this->translate_datetime_literal( $value );
		}

		/*
		 * Handle null characters.
		 *
		 * SQLite doesn't fully support null characters (\u0000) in strings.
		 * However, it can store them and read them, with some limitations.
		 *
		 * In PHP, null bytes are often produced by the serialize() function.
		 * Removing them would damage the serialized data.
		 *
		 * There is no way to store null bytes using a string literal, so we
		 * need to pass the value as a HEX string and cast it back to TEXT.
		 * This will convert literals will null bytes to expressions.
		 *
		 * Alternatively, we could replace string literals with parameters and
		 * pass them using prepared statements. However, that's not universally
		 * applicable for all string literals (e.g., in default column values).
		 *
		 * We can't use the "part1 || CHAR(0) || part2 || ..." syntax, because
		 * with a large number of null bytes, SQLite throws the following error:
		 *
		 *   SQLSTATE[HY000]:
		 *   General error: 1 Expression tree is too large (maximum depth 1000)
		 *
		 * See:
		 *   https://www.sqlite.org/nulinstr.html
		 */
		if ( strpos( $value, "\0" ) !== false ) {
			return sprintf( "CAST(x'%s' AS TEXT)", bin2hex( $value ) );
		}
		return $this->quote_sqlite_value( $value );
	}

	/**
	 * Translate a MySQL pure identifier to SQLite.
	 *
	 * @param  WP_Parser_Node $node The "pureIdentifier" AST node.
	 * @return string               The translated value.
	 */
	private function translate_pure_identifier( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();
		$value = $token->get_value();

		if ( str_starts_with( $value, self::RESERVED_PREFIX ) ) {
			throw $this->new_driver_exception(
				sprintf(
					"Invalid identifier '%s', prefix '%s' is reserved",
					$value,
					self::RESERVED_PREFIX
				)
			);
		}

		return '`' . str_replace( '`', '``', $value ) . '`';
	}

	/**
	 * Translate a qualified MySQL identifier to SQLite.
	 *
	 * The identifier can be composed of 1 to 3 parts (schema, object, child).
	 *
	 * @param  WP_Parser_Node|null $schema_node An identifier node representing a schema name (database).
	 * @param  WP_Parser_Node|null $object_node An identifier node representing a database-level object name
	 *                                          (table, view, procedure, trigger, etc.).
	 * @param  WP_Parser_Node|null $child_node  An identifier node representing an object child name (column, index, etc.).
	 * @return string                           The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception     When the translation fails.
	 */
	private function translate_qualified_identifier(
		?WP_Parser_Node $schema_node,
		?WP_Parser_Node $object_node = null,
		?WP_Parser_Node $child_node = null
	): string {
		$parts = array();

		// Database name.
		$is_information_schema = 'information_schema' === $this->db_name;
		if ( null !== $schema_node ) {
			$schema_name = $this->unquote_sqlite_identifier(
				$this->translate_sequence( $schema_node->get_children() )
			);
			if ( 'information_schema' === strtolower( $schema_name ) ) {
				$is_information_schema = true;
			} elseif ( $this->main_db_name === $schema_name ) {
				$is_information_schema = false;
			} else {
				throw $this->new_not_supported_exception(
					sprintf(
						"can't use schema '%s', only '%s' and 'information_schema' are supported",
						$schema_name,
						$this->db_name
					)
				);
			}
		}

		// Database-level object name (table, view, procedure, trigger, etc.).
		if ( null !== $object_node ) {
			$parts[] = $this->translate( $object_node );
		}

		// Object child name (column, index, etc.).
		if ( null !== $child_node ) {
			$parts[] = $this->translate( $child_node );
		}

		return implode( '.', $parts );
	}

	/**
	 * Translate a MySQL query expression to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "queryExpression" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_query_expression( WP_Parser_Node $node ): string {
		// Get the query expression subnode under which we need to look for the
		// SELECT item list node. This prevents searching under "withClause".
		$query_expr_main = (
			$node->get_first_child_node( 'queryExpressionBody' )
			?? $node->get_first_child_node( 'queryExpressionParens' )
		);
		$query_term      = $query_expr_main->get_first_descendant_node( 'queryTerm' );
		$has_union       = $query_expr_main->has_child_token( WP_MySQL_Lexer::UNION_SYMBOL );
		$has_except      = $query_expr_main->has_child_token( WP_MySQL_Lexer::EXCEPT_SYMBOL );
		$has_intersect   = $query_term->has_child_token( WP_MySQL_Lexer::INTERSECT_SYMBOL );

		/*
		 * When the ORDER BY clause is present, we need to disambiguate the item
		 * list and make sure they don't cause an "ambiguous column name" error.
		 *
		 * @see WP_MySQL_On_SQLite::disambiguate_item()
		 */
		$disambiguated_order_list = array();
		$order_clause             = $node->get_first_child_node( 'orderClause' );
		if ( $order_clause && ! $has_union && ! $has_except && ! $has_intersect ) {
			/*
			 * [GRAMMAR]
			 * queryExpression: (withClause)? (
			 *   queryExpressionBody orderClause? limitClause?
			 *   | queryExpressionParens orderClause? limitClause?
			 * ) (procedureAnalyseClause)?
			 */

			// Create the SELECT item disambiguation map.
			$select_item_list   = $query_expr_main->get_first_descendant_node( 'selectItemList' );
			$disambiguation_map = $this->create_select_item_disambiguation_map( $select_item_list );

			// For each "orderList" item, search for a matching SELECT item.
			$disambiguated_order_list = array();
			$order_list               = $order_clause->get_first_child_node( 'orderList' );
			foreach ( $order_list->get_child_nodes() as $order_item ) {
				/*
				 * [GRAMMAR]
				 * orderExpression: expr direction?
				 */
				$order_expr         = $order_item->get_first_child_node( 'expr' );
				$order_direction    = $order_item->get_first_child_node( 'direction' );
				$disambiguated_item = $this->disambiguate_item( $disambiguation_map, $order_expr );

				$disambiguated_order_list[] = sprintf(
					'%s%s',
					$disambiguated_item ?? $this->translate( $order_expr ),
					null !== $order_direction ? ( ' ' . $this->translate( $order_direction ) ) : ''
				);
			}

			// Translate the query expression, replacing the ORDER BY list with
			// the one that was constructed using the disambiguation algorithm.
			$parts = array();
			foreach ( $node->get_children() as $child ) {
				if ( $child instanceof WP_Parser_Node && 'orderClause' === $child->rule_name ) {
					$parts[] = 'ORDER BY ' . implode( ', ', $disambiguated_order_list );
				} else {
					$parts[] = $this->translate( $child );
				}
			}
			return implode( ' ', $parts );
		}

		return $this->translate_sequence( $node->get_children() );
	}

	/**
	 * Translate a MySQL query specification node to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "querySpecification" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 * @return string|null
	 */
	private function translate_query_specification( WP_Parser_Node $node ): string {
		$group_by = $node->get_first_child_node( 'groupByClause' );
		$having   = $node->get_first_child_node( 'havingClause' );

		/*
		 * When the GROUP BY or HAVING clause is present, we need to disambiguate
		 * the items to ensure they don't cause an "ambiguous column name" error.
		 *
		 * @see WP_MySQL_On_SQLite::disambiguate_item()
		 */
		$group_by_clause = null;
		$having_clause   = null;
		if ( $group_by || $having ) {
			// Build a SELECT list disambiguation map for both GROUP BY and HAVING.
			$select_item_list   = $node->get_first_child_node( 'selectItemList' );
			$disambiguation_map = $this->create_select_item_disambiguation_map( $select_item_list );

			// Disambiguate the GROUP BY clause column references.
			$disambiguated_group_by_list = array();
			if ( $group_by ) {
				/*
				 * [GRAMMAR]
				 * groupByClause: GROUP_SYMBOL BY_SYMBOL orderList olapOption?
				 */
				$group_by_list = $group_by->get_first_child_node( 'orderList' );
				foreach ( $group_by_list->get_child_nodes() as $group_by_item ) {
					$group_by_expr                 = $group_by_item->get_first_child_node( 'expr' );
					$disambiguated_item            = $this->disambiguate_item( $disambiguation_map, $group_by_expr );
					$disambiguated_group_by_list[] = $disambiguated_item ?? $this->translate( $group_by_expr );
				}
				$group_by_clause = 'GROUP BY ' . implode( ', ', $disambiguated_group_by_list );
			}

			// Disambiguate the HAVING clause column references.
			$disambiguated_having_list = array();
			if ( $having ) {
				/*
				 * [GRAMMAR]
				 * havingClause: HAVING_SYMBOL expr
				 */
				$having_expr          = $having->get_first_child_node();
				$having_expr_children = $having_expr->get_children();
				foreach ( $having_expr_children as $having_item ) {
					if ( $having_item instanceof WP_Parser_Node ) {
						$disambiguated_item          = $this->disambiguate_item( $disambiguation_map, $having_item );
						$disambiguated_having_list[] = $disambiguated_item ?? $this->translate( $having_item );
					} else {
						$disambiguated_having_list[] = $this->translate( $having_item );
					}
				}
				$having_clause = 'HAVING ' . implode( ' ', $disambiguated_having_list );
			}

			// Translate the query specification, replacing the ORDER BY/HAVING
			// items with the ones that were disambiguated using the SELECT list.
			$parts = array();
			foreach ( $node->get_children() as $child ) {
				if ( $child instanceof WP_Parser_Node && 'groupByClause' === $child->rule_name ) {
					$parts[] = $group_by_clause;
				} elseif ( $child instanceof WP_Parser_Node && 'havingClause' === $child->rule_name ) {
					// SQLite doesn't allow using the "HAVING" clause without "GROUP BY".
					// In such cases, let's prefix the "HAVING" clause with "GROUP BY 1".
					if ( ! $group_by ) {
						$parts[] = 'GROUP BY 1';
					}
					$parts[] = $having_clause;
				} else {
					$part = $this->translate( $child );
					if ( null !== $part ) {
						$parts[] = $part;
					}
				}
			}
			return implode( ' ', $parts );
		}
		return $this->translate_sequence( $node->get_children() );
	}

	/**
	 * Translate a MySQL simple expression body to SQLite.
	 *
	 * @param WP_Parser_Node $node        The "simpleExprBody" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_simple_expr_body( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();

		// Translate "VALUES(col)" to "excluded.col" in ON DUPLICATE KEY UPDATE.
		if ( null !== $token && WP_MySQL_Lexer::VALUES_SYMBOL === $token->id ) {
			return sprintf(
				'`excluded`.%s',
				$this->translate( $node->get_first_child_node( 'simpleIdentifier' ) )
			);
		}

		/*
		 * Translate "BINARY expr" to "expr COLLATE BINARY".
		 *
		 * The MySQL BINARY operator enforces byte-by-byte string comparison.
		 * In SQLite, COLLATE BINARY is equivalent in comparison contexts.
		 */
		if ( null !== $token && WP_MySQL_Lexer::BINARY_SYMBOL === $token->id ) {
			$expr = $node->get_first_child_node( 'simpleExpr' );
			return sprintf( '%s COLLATE BINARY', $this->translate( $expr ) );
		}

		// Translate "CAST(expr AS type)" to its SQLite equivalent.
		if ( null !== $token && WP_MySQL_Lexer::CAST_SYMBOL === $token->id ) {
			$expr      = $node->get_first_child_node( 'expr' );
			$cast_type = $node->get_first_child_node( 'castType' );
			return $this->translate_cast_expr( $expr, $cast_type );
		}

		/**
		 * Translate MySQL CONVERT() expression.
		 *
		 * MySQL supports two forms of CONVERT():
		 *   1. CONVERT(expr, type):         Equivalent to CAST(expr AS type).
		 *   2. CONVERT(expr USING charset): Converts the character set.
		 */
		if ( null !== $token && WP_MySQL_Lexer::CONVERT_SYMBOL === $token->id ) {
			$expr      = $node->get_first_child_node( 'expr' );
			$cast_type = $node->get_first_child_node( 'castType' );

			if ( null !== $cast_type ) {
				// CONVERT(expr, type): Translate to cast expression.
				// TODO: Emulate UNSIGNED cast. SQLite has no unsigned integer type.
				return $this->translate_cast_expr( $expr, $cast_type );
			} else {
				// CONVERT(expr USING charset): Keep "expr" as is (no SQLite support).
				// TODO: Consider rejecting UTF-8-incompatible charsets.
				return $this->translate( $expr );
			}
		}

		/*
		 * Reject "INTERVAL n unit + expr": the parser includes following AND, OR,
		 * and XOR conditions in "expr", unlike MySQL. It also reads
		 * "expr + INTERVAL 1 DAY + x" as "expr + (INTERVAL 1 DAY + x)".
		 *
		 * TODO: Support prefix intervals after switching to the LALR(1) parser.
		 */
		if ( null !== $token && WP_MySQL_Lexer::INTERVAL_SYMBOL === $token->id ) {
			throw $this->new_not_supported_exception(
				'INTERVAL n unit followed by "+"; use "(expr + INTERVAL n unit)" instead'
			);
		}

		return $this->translate_sequence( $node->get_children() );
	}

	/**
	 * Translate a "bitExpr" AST node.
	 *
	 * Translate interval additions and subtractions to a single date function
	 * call, applying each interval from left to right, as MySQL does.
	 *
	 * TODO: After switching to LALR(1), use the tree's operator grouping
	 * and remove the loop over this parser's flat interval sequence.
	 *
	 * @param  WP_Parser_Node $node       The "bitExpr" AST node.
	 * @return string|null                The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_bit_expr( WP_Parser_Node $node ): ?string {
		/*
		 * The first child is an expression. Each interval adds four children:
		 * operator, INTERVAL, value, unit. A final "op bitExpr" may follow.
		 */
		$children  = $node->get_children();
		$count     = count( $children );
		$sql       = $this->translate( $children[0] );
		$intervals = array();
		$i         = 1;
		while (
			isset( $children[ $i + 1 ] )
			&& $children[ $i + 1 ] instanceof WP_Parser_Token
			&& WP_MySQL_Lexer::INTERVAL_SYMBOL === $children[ $i + 1 ]->id
		) {
			$intervals[] = array(
				WP_MySQL_Lexer::MINUS_OPERATOR === $children[ $i ]->id,
				$children[ $i + 2 ],
				$children[ $i + 3 ],
			);
			$i          += 4;
		}
		if ( count( $intervals ) > 0 ) {
			$sql = $this->translate_interval_arithmetic( $sql, $intervals );
		}

		if ( $i === $count ) {
			return $sql;
		}

		/*
		 * The parser groups "a + b + INTERVAL 1 DAY" as "a + (b + INTERVAL 1 DAY)".
		 * MySQL applies the interval to "a + b". Reject this form to avoid
		 * applying the interval to only part of the expression. For simplicity,
		 * also reject operators that bind less tightly than "+", such as "|".
		 *
		 * TODO: Remove this guard and support mixed arithmetic with LALR(1).
		 */
		$operator = $children[ $i ];
		$operand  = $children[ $i + 1 ];
		if ( $operand->has_child_token( WP_MySQL_Lexer::INTERVAL_SYMBOL ) ) {
			throw $this->new_not_supported_exception(
				sprintf(
					'INTERVAL after the "%s" operator; add parentheses to make the grouping explicit',
					$operator->get_value()
				)
			);
		}
		return sprintf( '%s %s %s', $sql, $this->translate( $operator ), $this->translate( $operand ) );
	}

	/**
	 * Translate a MySQL CAST expression to SQLite.
	 *
	 * Shared by the CAST(expr AS type) and CONVERT(expr, type) forms.
	 *
	 * @param  WP_Parser_Node $expr      The "expr" AST node.
	 * @param  WP_Parser_Node $cast_type The "castType" AST node.
	 * @return string                    The translated SQLite expression.
	 */
	private function translate_cast_expr( WP_Parser_Node $expr, WP_Parser_Node $cast_type ): string {
		/*
		 * Translate "CAST(expr AS BINARY)" to "CAST(expr AS TEXT) COLLATE BINARY".
		 * Emitting "CAST(expr AS BLOB)" would break equality against TEXT values
		 * due to SQLite's storage-class ordering (BLOB > TEXT).
		 */
		if ( $cast_type->has_child_token( WP_MySQL_Lexer::BINARY_SYMBOL ) ) {
			return sprintf( 'CAST(%s AS TEXT) COLLATE BINARY', $this->translate( $expr ) );
		}
		return sprintf( 'CAST(%s AS %s)', $this->translate( $expr ), $this->translate( $cast_type ) );
	}

	/**
	 * Translate a MySQL LIKE expression to SQLite.
	 *
	 * @param WP_Parser_Node $node        The "predicateOperations" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_like( WP_Parser_Node $node ): string {
		$tokens    = $node->get_descendant_tokens();
		$is_binary = isset( $tokens[1] ) && WP_MySQL_Lexer::BINARY_SYMBOL === $tokens[1]->id;

		if ( true === $is_binary ) {
			// TODO: GLOB can match different invalid UTF-8 bytes as equal.
			// Use byte-wise matching for full LIKE BINARY compatibility.
			$children = $node->get_children();
			return sprintf(
				'GLOB _helper_like_to_glob_pattern(%s)',
				$this->translate( $children[1] )
			);
		}

		/*
		 * @TODO: Implement the ESCAPE '...' clause.
		 */

		/*
		 * @TODO: Implement more correct LIKE behavior.
		 *
		 * While SQLite supports the LIKE operator, it seems to differ from the
		 * MySQL behavior in some ways:
		 *
		 *  1. In SQLite, LIKE is case-insensitive only for ASCII characters
		 *     ('a' LIKE 'A' is TRUE but 'æ' LIKE 'Æ' is FALSE)
		 *  2. In MySQL, LIKE interprets some escape sequences. See the contents
		 *     of the "_helper_like_to_glob_pattern" function.
		 *
		 * We'll probably need to overload the like() function:
		 *   https://www.sqlite.org/lang_corefunc.html#like
		 */
		$statement = $this->translate_sequence( $node->get_children() );
		if ( $this->is_sql_mode_active( 'NO_BACKSLASH_ESCAPES' ) ) {
			return $statement;
		}
		return $statement . " ESCAPE '\\'";
	}

	/**
	 * Translate MySQL REGEXP expression to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "predicateOperations" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_regexp_functions( WP_Parser_Node $node ): string {
		$tokens    = $node->get_descendant_tokens();
		$is_binary = isset( $tokens[1] ) && WP_MySQL_Lexer::BINARY_SYMBOL === $tokens[1]->id;

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
		if ( true === $is_binary ) {
			return 'REGEXP CHAR(0) || ' . $this->translate( $node->get_first_child_node() );
		}
		return 'REGEXP ' . $this->translate( $node->get_first_child_node() );
	}

	/**
	 * Translate a MySQL runtime function call to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "runtimeFunctionCall" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_runtime_function_call( WP_Parser_Node $node ): string {
		$child = $node->get_first_child();
		if ( $child instanceof WP_Parser_Node ) {
			return $this->translate( $child );
		}

		switch ( $child->id ) {
			case WP_MySQL_Lexer::IF_SYMBOL:
				// CASE also supports SQLite versions before IIF() was added in 3.32.0.
				$nodes = $node->get_child_nodes();
				return sprintf(
					'CASE WHEN %s THEN %s ELSE %s END',
					$this->translate( $nodes[0] ),
					$this->translate( $nodes[1] ),
					$this->translate( $nodes[2] )
				);
			case WP_MySQL_Lexer::DATABASE_SYMBOL:
				return $this->quote_sqlite_value( $this->db_name );
			case WP_MySQL_Lexer::CURRENT_TIMESTAMP_SYMBOL:
			case WP_MySQL_Lexer::NOW_SYMBOL:
				/*
				 * 1) SQLite doesn't support CURRENT_TIMESTAMP() with parentheses.
				 * 2) In MySQL, CURRENT_TIMESTAMP and CURRENT_TIMESTAMP() are an
				 *    alias of NOW(). In SQLite, there is no NOW() function.
				 */
				return 'CURRENT_TIMESTAMP';
			case WP_MySQL_Lexer::DATE_ADD_SYMBOL:
			case WP_MySQL_Lexer::DATE_SUB_SYMBOL:
				$nodes = $node->get_child_nodes();
				return $this->translate_interval_arithmetic(
					$this->translate( $nodes[0] ),
					array( array( WP_MySQL_Lexer::DATE_SUB_SYMBOL === $child->id, $nodes[1], $nodes[2] ) )
				);
			case WP_MySQL_Lexer::LEFT_SYMBOL:
				$nodes = $node->get_child_nodes();
				return sprintf(
					'SUBSTR(%s, 1, %s)',
					$this->translate( $nodes[0] ),
					$this->translate( $nodes[1] )
				);
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate MySQL date arithmetic to a SQLite DATE() or DATETIME() call.
	 *
	 * Each interval becomes a modifier, and SQLite applies them from left to right.
	 * Parenthesize the value before appending the unit: SQLite evaluates "||"
	 * before "*". Negate the value for subtraction so negative intervals work
	 * without invalid sign combinations such as "+-5 HOUR".
	 * Convert units where needed, e.g., WEEK to 7 days.
	 *
	 * MySQL returns a date when it adds only date units to a date. The driver
	 * stores dates as 10-character strings, so use DATE() for such inputs.
	 *
	 * @param  string $expr      The translated date expression.
	 * @param  array  $intervals List of intervals, each as an array of:
	 *                           - bool           Whether the interval is subtracted.
	 *                           - WP_Parser_Node The interval value expression node.
	 *                           - WP_Parser_Node The "interval" AST node holding the unit.
	 * @return string            The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_interval_arithmetic( string $expr, array $intervals ): string {
		$modifiers  = array();
		$date_units = true;
		foreach ( $intervals as list( $subtract, $value, $interval ) ) {
			// Token IDs also cover unit synonyms, e.g., SQL_TSI_HOUR.
			$unit_token = $interval->get_first_descendant_token();
			if ( ! isset( self::INTERVAL_UNIT_MAP[ $unit_token->id ] ) ) {
				throw $this->new_not_supported_exception(
					sprintf( 'INTERVAL unit: %s', $unit_token->get_value() )
				);
			}

			list( $unit, $multiplier ) = self::INTERVAL_UNIT_MAP[ $unit_token->id ];

			$value_sql = sprintf( '(%s)', $this->translate( $value ) );
			if ( 1 !== $multiplier ) {
				$value_sql = sprintf( '(%s * %d)', $value_sql, $multiplier );
			}
			if ( $subtract ) {
				$value_sql = '-' . $value_sql;
			}
			$modifiers[] = sprintf( "%s || ' %s'", $value_sql, $unit );
			$date_units  = $date_units && in_array( $unit, array( 'DAY', 'MONTH', 'YEAR' ), true );
		}

		$modifiers_sql = implode( ', ', $modifiers );
		if ( ! $date_units ) {
			return sprintf( 'DATETIME(%s, %s)', $expr, $modifiers_sql );
		}
		return sprintf(
			'CASE WHEN LENGTH(%1$s) = 10 THEN DATE(%1$s, %2$s) ELSE DATETIME(%1$s, %2$s) END',
			$expr,
			$modifiers_sql
		);
	}

	/**
	 * Translate a MySQL function call to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "functionCall" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_function_call( WP_Parser_Node $node ): string {
		$nodes = $node->get_child_nodes();
		$name  = strtoupper(
			$this->unquote_sqlite_identifier( $this->translate( $nodes[0] ) )
		);

		$args = array();
		if ( isset( $nodes[1] ) ) {
			foreach ( $nodes[1]->get_child_nodes() as $child ) {
				$args[] = $this->translate( $child );
			}
		}

		switch ( $name ) {
			case 'RAND':
				/*
				 * Unseeded RAND() compiles to a fast native SQLite expression.
				 * In MySQL, unseeded RAND() uses the thread-level random state,
				 * independent of any RAND(N) seeding, so we don't need PHP UDF.
				 *
				 * We map SQLite's RANDOM() to a float in [0, 1) by masking to
				 * 53 bits (IEEE 754 double mantissa width) and dividing by 2^53.
				 * This avoids the edge case where masking to 63 bits and dividing
				 * by 2^63 could round to 1.0 due to loss of precision in double.
				 *
				 * Seeded RAND(N) falls through to the UDF, which implements
				 * MySQL's deterministic LCG (Linear Congruential Generator).
				 * The UDF also handles RAND(NULL) as RAND(0), matching MySQL.
				 */
				if ( 0 === count( $args ) ) {
					return '((RANDOM() & ((1 << 53) - 1)) / ((1 << 53) * 1.0))';
				}
				return $this->translate_sequence( $node->get_children() );
			case 'DATE_FORMAT':
				list ( $date, $mysql_format ) = $args;

				$format = strtr( $mysql_format, self::MYSQL_DATE_FORMAT_TO_SQLITE_STRFTIME_MAP );
				if ( ! $format ) {
					throw $this->new_driver_exception(
						sprintf(
							'Could not translate a DATE_FORMAT() format to STRFTIME format (%s)',
							$mysql_format
						)
					);
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
				$cast_to_float = "'%H.%i'" === $mysql_format;
				if ( true === $cast_to_float ) {
					return sprintf( 'CAST(STRFTIME(%s, %s) AS FLOAT)', $format, $date );
				}
				return sprintf( 'STRFTIME(%s, %s)', $format, $date );
			case 'CHAR_LENGTH':
				// @TODO LENGTH and CHAR_LENGTH aren't always the same in MySQL for utf8 characters.
				return 'LENGTH(' . $args[0] . ')';
			case 'CONCAT':
				return '(' . implode( ' || ', $args ) . ')';
			case 'FOUND_ROWS':
				$found_rows = $this->found_rows;
				if ( is_int( $found_rows ) ) {
					return $found_rows;
				} elseif ( is_string( $found_rows ) ) {
					return (int) $this->execute_sqlite_query(
						sprintf( 'SELECT COUNT(*) FROM (%s)', $found_rows )
					)->fetchColumn();
				} elseif ( is_array( $found_rows ) && isset( $found_rows[0] ) ) {
					return (int) $this->execute_sqlite_query(
						sprintf( 'SELECT COUNT(*) FROM (%s)', $found_rows[0] ),
						$found_rows[1] ?? array()
					)->fetchColumn();
				} else {
					return 0;
				}
			case 'VERSION':
				return $this->quote_sqlite_value( $this->get_mysql_server_version_string() );
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Get the configured MySQL version in dotted notation.
	 *
	 * @return string The MySQL version.
	 */
	private function get_mysql_version_string(): string {
		return sprintf(
			'%d.%d.%d',
			intdiv( $this->mysql_version, 10000 ),
			intdiv( $this->mysql_version % 10000, 100 ),
			$this->mysql_version % 100
		);
	}

	/**
	 * Get the raw version string of the emulated MySQL server.
	 *
	 * @return string The MySQL server version.
	 */
	private function get_mysql_server_version_string(): string {
		return sprintf(
			'%s-mysql-on-sqlite-%s',
			$this->get_mysql_version_string(),
			SQLITE_DRIVER_VERSION
		);
	}

	/**
	 * Get the comment identifying the emulated MySQL server implementation.
	 *
	 * @return string The MySQL server version comment.
	 */
	private function get_mysql_server_version_comment(): string {
		return 'MySQL on SQLite';
	}

	/**
	 * Translate a MySQL datetime literal to SQLite.
	 *
	 * @param  string $value The MySQL datetime literal.
	 * @return string        The translated value.
	 */
	private function translate_datetime_literal( string $value ): string {
		/*
		 * Normalize MySQL date and datetime literals for SQLite.
		 *
		 * MySQL accepts date and time components without zero padding. SQLite
		 * stores temporal values as text, so they must be normalized for lexical
		 * comparisons and SQLite date functions to work correctly.
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
		if (
			1 === preg_match(
				'/^(\d{4})-(\d{1,2})-(\d{1,2})(?:([ T])(\d{1,2}):(\d{1,2}):(\d{1,2})(Z)?)?$/',
				$value,
				$matches
			)
		) {
			$value = sprintf( '%04d-%02d-%02d', $matches[1], $matches[2], $matches[3] );
			if ( isset( $matches[4] ) && '' !== $matches[4] ) {
				$is_iso_8601 = 'T' === $matches[4] && 'Z' === ( $matches[8] ?? '' );
				$value      .= sprintf(
					'%s%02d:%02d:%02d%s',
					$is_iso_8601 ? ' ' : $matches[4],
					$matches[5],
					$matches[6],
					$matches[7],
					$is_iso_8601 ? '' : ( $matches[8] ?? '' )
				);
			}
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
		if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2}:\d{2})$/', $value, $matches ) ) {
			/*
			 * Calling strtotime("0000-00-00 00:00:00") in 32-bit environments triggers
			 * an "out of integer range" warning – let's avoid that call for the popular
			 * case of "zero" dates.
			 */
			if ( '0000-00-00 00:00:00' !== $value && false === strtotime( $value ) ) {
				/*
				 * Check for dates with zero month/day parts (e.g. '2020-00-15 00:00:00').
				 *
				 * When the NO_ZERO_IN_DATE SQL mode is not active, MySQL accepts dates
				 * where the year is nonzero but the month or day is zero. We must
				 * preserve these values so that cast_value_for_saving() can handle
				 * them correctly at the column level.
				 *
				 * See: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_no_zero_in_date
				 */
				$has_zero_in_date = (
					( '00' === $matches[2] || '00' === $matches[3] ) &&
					'0000' !== $matches[1]
				);
				if ( ! $has_zero_in_date || $this->is_sql_mode_active( 'NO_ZERO_IN_DATE' ) ) {
					$value = '0000-00-00 00:00:00';
				}
			}
		}
		return $value;
	}

	/**
	 * Translate a select item to SQLite.
	 *
	 * In some cases, an explicit alias will be added to the select item, so that
	 * the returned column name is always the same as it would be in MySQL.
	 *
	 * @param  WP_Parser_Node $node       The "selectItem" AST node.
	 * @return string                     The translated expression.
	 */
	private function translate_select_item( WP_Parser_Node $node ): string {
		/*
		 * First, let's translate the select item subtree.
		 *
		 * [GRAMMAR]
		 * selectItem: tableWild | (expr selectAlias?)
		 */
		$item = $this->translate_sequence( $node->get_children() );

		// A table wildcard (e.g., "SELECT *, t.*, ...") never has an alias.
		if ( $node->has_child_node( 'tableWild' ) ) {
			return $item;
		}

		// When an explicit alias is provided, we can use it as is.
		$alias = $node->get_first_child_node( 'selectAlias' );
		if ( $alias ) {
			return $item;
		}

		/*
		 * When the select item contains only a column definition, we need to use
		 * it without change, so that the returned column name reflects the real
		 * column name in all cases, including when using a fully qualified name.
		 *
		 * For example, for "SELECT t.id", the column name in the result set will
		 * only be "id", not "t.id", as it may appear based on the original query.
		 *
		 * In this case, SQLite uses the same logic as MySQL, so using the value
		 * as is without adding an explicit alias will produce the correct result.
		 */
		$column_ref    = $node->get_first_descendant_node( 'columnRef' );
		$is_column_ref = $column_ref && $item === $this->translate( $column_ref );
		if ( $is_column_ref ) {
			return $item;
		}

		/*
		 * When the select item is a text string literal, we need to use an alias
		 * to ensure that the column name is the same as it would be in MySQL.
		 * In MySQL, the column name is the original text string literal value
		 * without quotes and escaping, but in SQLite, it is the quoted value.
		 *
		 * For example, for "SELECT 'abc'", the resulting column name is "abc"
		 * in MySQL, but would be "'abc'" in SQLite if an alias was not used.
		 *
		 * Descend the AST until we reach a textStringLiteral. If at any level
		 * we don't have a single child node, bail out; it's not a bare literal.
		 */
		$current = $node;
		while ( 'textStringLiteral' !== $current->rule_name ) {
			$children = $current->get_children();
			if ( 1 !== count( $children ) || ! $children[0] instanceof WP_Parser_Node ) {
				break;
			}
			$current = $children[0];
		}
		if ( 'textStringLiteral' === $current->rule_name ) {
			$alias = $current->get_first_child_token()->get_value();

			// When the literal value contains a NULL byte, MySQL truncates the
			// resulting identifier at the position of the first one of them.
			$fist_null_byte_pos = strpos( $alias, "\0" );
			if ( false !== $fist_null_byte_pos ) {
				$alias = substr( $alias, 0, $fist_null_byte_pos );
			}
			return sprintf( '%s AS %s', $item, $this->quote_sqlite_identifier( $alias ) );
		}

		/*
		 * When the select item has no explicit alias, we need to ensure that the
		 * returned column name is equivalent to what MySQL infers from the input.
		 *
		 * For example, if we translate "CONCAT('a', 'b')" to "('a' || 'b')", we
		 * need to use the original "CONCAT('a', 'b')" string as the column name.
		 * To achieve this, the select item will be translated as follows:
		 *
		 *   SELECT CONCAT('a', 'b') -> SELECT ('a' || 'b') AS `CONCAT('a', 'b')`
		 */
		$raw_alias = substr( $this->last_mysql_query, $node->get_start(), $node->get_length() );
		$alias     = $this->quote_sqlite_identifier( $raw_alias );
		if ( $alias === $item || $raw_alias === $item ) {
			// For the simple case of selecting only columns ("SELECT id FROM t"),
			// let's avoid unnecessary aliases ("SELECT `id` AS `id` FROM t").
			return $item;
		}
		return sprintf( '%s AS %s', $item, $alias );
	}

	/**
	 * Translate a MySQL table reference to SQLite.
	 *
	 * When the table reference targets an information schema table, we replace
	 * it with a subquery, injecting the configured database name dynamically.
	 *
	 * For example, the following query:
	 *
	 *   SELECT *, t.*, t.table_schema FROM information_schema.tables t
	 *
	 * Will be translated to:
	 *
	 *   SELECT *, `t`.*, `t`.`table_schema` FROM (
	 *     SELECT
	 *       `TABLE_CATALOG`,
	 *       CASE WHEN `TABLE_SCHEMA` = 'information_schema' THEN `TABLE_SCHEMA` ELSE 'database_name' END AS `TABLE_SCHEMA`,
	 *       `TABLE_NAME`,
	 *       ...
	 *     FROM `_wp_sqlite_mysql_information_schema_tables` AS `tables`
	 *   ) `t`
	 *
	 * The same logic will be applied to table references in JOIN clauses as well.
	 *
	 * @param  WP_Parser_Node $node       The "tableRef" AST node.
	 * @return string                     The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception When the translation fails.
	 */
	private function translate_table_ref( WP_Parser_Node $node ): string {
		// The table reference is in "<schema>.<table>" or "<table>" format.
		$parts  = $node->get_descendant_nodes( 'identifier' );
		$table  = array_pop( $parts );
		$schema = array_pop( $parts );

		$schema_name = $schema ? $this->unquote_sqlite_identifier( $this->translate( $schema ) ) : null;
		$table_name  = $this->unquote_sqlite_identifier( $this->translate( $table ) );

		// When the table reference targets an information schema table,
		// we need to inject some additional values dynamically.
		if (
			( null === $schema_name && 'information_schema' === $this->db_name )
			|| ( null !== $schema_name && 'information_schema' === strtolower( $schema_name ) )
		) {
			$table_name = strtolower( $table_name );

			// Some information schema tables can be computed on the fly.
			if ( 'character_sets' === $table_name || 'collations' === $table_name ) {
				$table_definition = $this->information_schema_builder
					->get_computed_information_schema_table_definition( $table_name );
				if ( null !== $table_definition ) {
					return sprintf( '(%s)', $table_definition );
				}
			}

			// Temporary tables are not listed in the information schema.
			$sqlite_table_name = $this->information_schema_builder->get_table_name( false, $table_name );

			// We need to fetch the SQLite column information, because the information
			// schema tables don't contain records for the information schema itself.
			$columns = $this->execute_sqlite_query(
				'SELECT name FROM pragma_table_info(?)',
				array( $sqlite_table_name )
			)->fetchAll( PDO::FETCH_COLUMN );

			if ( count( $columns ) === 0 ) {
				return $this->translate_sequence( $node->get_children() );
			}

			// List all columns in the table, replacing columns targeting database
			// name columns with the configured database name.
			static $information_schema_db_column_map = array(
				'SCHEMA_NAME'              => true,
				'TABLE_SCHEMA'             => true,
				'VIEW_SCHEMA'              => true,
				'INDEX_SCHEMA'             => true,
				'CONSTRAINT_SCHEMA'        => true,
				'UNIQUE_CONSTRAINT_SCHEMA' => true,
				'REFERENCED_TABLE_SCHEMA'  => true,
				'TRIGGER_SCHEMA'           => true,
			);

			$expanded_list = array();
			foreach ( $columns as $column ) {
				$quoted_column = $this->quote_sqlite_identifier( $column );
				if ( isset( $information_schema_db_column_map[ $column ] ) ) {
					// Replace the database name with the configured database name.
					$expanded_list[] = sprintf(
						"CASE WHEN %s = 'information_schema' THEN %s ELSE %s END AS %s",
						$quoted_column,
						$quoted_column,
						$this->quote_sqlite_value( $this->main_db_name ),
						$quoted_column
					);
				} elseif ( 'tables' === $table_name && 'AUTO_INCREMENT' === $column ) {
					// Inject the auto-increment values.
					$columns_table      = $this->information_schema_builder->get_table_name( false, 'columns' );
					$has_sequence_table = (bool) $this->execute_sqlite_query(
						"SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'"
					)->fetchColumn();

					$auto_increment_subquery = sprintf(
						"(
							SELECT COALESCE(s.seq + 1, 1)
							FROM %s AS c
							%s
							WHERE c.extra = 'auto_increment'
							AND c.table_schema = %s.table_schema
							AND c.table_name = %s.table_name
						)",
						$this->quote_sqlite_identifier( $columns_table ),
						$has_sequence_table
							? 'LEFT JOIN main.sqlite_sequence AS s ON s.name = c.table_name'
							: 'LEFT JOIN (SELECT 0 AS seq) AS s',
						$this->quote_sqlite_identifier( $table_name ),
						$this->quote_sqlite_identifier( $table_name )
					);

					$expanded_list[] = sprintf( '%s AS %s', $auto_increment_subquery, $quoted_column );
				} else {
					$expanded_list[] = $quoted_column;
				}
			}
			$column_list = implode( ', ', $expanded_list );

			// Compose information schema subquery.
			return sprintf(
				'(SELECT %s FROM %s AS %s)',
				$column_list,
				$this->quote_sqlite_identifier( $sqlite_table_name ),
				$this->quote_sqlite_identifier( $table_name )
			);
		}
		return $this->translate_sequence( $node->get_children() );
	}

	/**
	 * Recreate an existing table using data in the information schema.
	 *
	 * This is used for a generic support of ALTER TABLE queries, as well as
	 * for some other statements like OPTIMIZE TABLE and REPAIR TABLE.
	 *
	 * See:
	 *   https://www.sqlite.org/lang_altertable.html#making_other_kinds_of_table_schema_changes
	 *
	 * @param  bool   $table_is_temporary Whether the table is temporary.
	 * @param  string $table_name         The name of the table to recreate.
	 * @param  array  $column_map         Optional. A map of column names (old name -> new name)
	 *                                    to use when copying data from the original table.
	 *                                    When not provided, all columns are copied without renaming.
	 * @throws WP_MySQL_On_SQLite_Exception
	 */
	private function recreate_table_from_information_schema(
		bool $table_is_temporary,
		string $table_name,
		?array $column_map = null
	): void {
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_types  = $this->execute_sqlite_query(
			sprintf(
				'SELECT COLUMN_NAME, DATA_TYPE FROM %s WHERE table_schema = ? AND table_name = ?',
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $this->get_saved_db_name( $this->main_db_name ), $table_name )
		)->fetchAll( PDO::FETCH_KEY_PAIR );

		if ( null === $column_map ) {
			$column_names = array_keys( $column_types );
			$column_map   = array_combine( $column_names, $column_names );
		}
		$column_types = array_change_key_case( $column_types, CASE_LOWER );

		// Preserve ROWIDs.
		// This also addresses a special case when all original columns are dropped
		// and there is nothing to copy. We'll always have at least the ROWID column.
		$column_map = array( 'rowid' => 'rowid' ) + $column_map;

		$select_list = array();
		foreach ( $column_map as $old_name => $new_name ) {
			$value       = $this->quote_sqlite_identifier( $old_name );
			$mysql_type  = $column_types[ strtolower( $new_name ) ] ?? '';
			$sqlite_type = self::DATA_TYPE_STRING_MAP[ $mysql_type ] ?? null;
			// Legacy tables may store text and binary values with a different storage class.
			if ( 'TEXT' === $sqlite_type || 'BLOB' === $sqlite_type ) {
				$value = sprintf( 'CAST(%s AS %s)', $value, $sqlite_type );
			}
			$select_list[] = $value;
		}

		/*
		 * See:
		 *   https://www.sqlite.org/lang_altertable.html#making_other_kinds_of_table_schema_changes
		 */

		// 1. If foreign key constraints are enabled, disable them.
		$pragma_foreign_keys = $this->execute_sqlite_query( 'PRAGMA foreign_keys' )->fetchColumn();
		$this->execute_sqlite_query( 'PRAGMA foreign_keys = OFF' );

		// 2. Create a new table with the new schema.
		$tmp_table_name        = self::RESERVED_PREFIX . "tmp_{$table_name}_" . uniqid();
		$quoted_table_name     = $this->quote_sqlite_identifier( $table_name );
		$quoted_tmp_table_name = $this->quote_sqlite_identifier( $tmp_table_name );
		$queries               = $this->get_sqlite_create_table_statement( $table_is_temporary, $table_name, $tmp_table_name );
		$create_table_query    = $queries[0];
		$constraint_queries    = array_slice( $queries, 1 );
		$this->execute_sqlite_query( $create_table_query );

		// 3. Copy data from the original table to the new table.
		$this->execute_sqlite_query(
			sprintf(
				'INSERT INTO %s (%s) SELECT %s FROM %s',
				$quoted_tmp_table_name,
				implode(
					', ',
					array_map( array( $this, 'quote_sqlite_identifier' ), $column_map )
				),
				implode( ', ', $select_list ),
				$quoted_table_name
			)
		);

		// Preserve the AUTO_INCREMENT counter from the original table.
		$schema             = $table_is_temporary ? 'temp' : 'main';
		$has_sequence_table = (bool) $this->execute_sqlite_query(
			sprintf( "SELECT 1 FROM %s.sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'", $schema )
		)->fetchColumn();
		if ( $has_sequence_table ) {
			$this->execute_sqlite_query(
				sprintf(
					'UPDATE %s.sqlite_sequence
					SET seq = MAX(seq, COALESCE((
						SELECT seq FROM %s.sqlite_sequence
						WHERE name = ? COLLATE NOCASE -- $table_name: original table
					), 0))
					WHERE name = ? -- $tmp_table_name: update only the replacement\'s row.',
					$schema,
					$schema
				),
				array( $table_name, $tmp_table_name )
			);
		}

		// 4. Drop the original table.
		$this->execute_sqlite_query( sprintf( 'DROP TABLE %s', $quoted_table_name ) );

		// 5. Rename the new table to the original table name.
		$this->execute_sqlite_query(
			sprintf(
				'ALTER TABLE %s RENAME TO %s',
				$quoted_tmp_table_name,
				$quoted_table_name
			)
		);

		// 6. Reconstruct indexes, triggers, and views.
		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}

		// 7. If foreign key constraints were enabled, verify and enable them.
		if ( 1 === (int) $pragma_foreign_keys ) {
			$this->execute_sqlite_query( 'PRAGMA foreign_key_check' );
			$this->execute_sqlite_query( 'PRAGMA foreign_keys = ON' );
		}

		// @TODO: Triggers and views.
	}

	/**
	 * Apply the AUTO_INCREMENT table option from a CREATE TABLE or ALTER TABLE
	 * statement by adjusting the row in SQLite's "sqlite_sequence" table.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param WP_Parser_Node $node               The "createStatement" or "alterStatement" AST node.
	 */
	private function apply_auto_increment_table_option(
		bool $table_is_temporary,
		string $table_name,
		WP_Parser_Node $node
	): void {
		// Find the last AUTO_INCREMENT = N option (MySQL uses the last one).
		$value = null;
		foreach ( $node->get_descendant_nodes( 'createTableOption' ) as $option ) {
			if ( ! $option->has_child_token( WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL ) ) {
				continue;
			}
			$number_node = $option->get_first_child_node( 'ulonglong_number' );
			if ( null === $number_node ) {
				continue;
			}
			$value = (int) $number_node->get_first_descendant_token()->get_value();
		}
		if ( null === $value ) {
			return;
		}

		// Find the AUTO_INCREMENT column.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$auto_column   = $this->execute_sqlite_query(
			sprintf(
				"SELECT column_name FROM %s
				WHERE table_schema = ?
				AND table_name = ?
				AND extra = 'auto_increment'",
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $this->get_saved_db_name(), $table_name )
		)->fetchColumn();
		if ( false === $auto_column ) {
			return;
		}

		/*
		 * Prepare an expression for the sequence value.
		 *   1. Use N - 1. MySQL stores the next value, SQLite the last one.
		 *   2. Clamp to MAX(col) like MySQL (we can't go below existing values).
		 *
		 * The value is inlined as an integer literal because PDO binds PHP ints
		 * as TEXT, and SQLite's type affinity ranks TEXT above INTEGER in MAX().
		 */
		$schema   = $table_is_temporary ? 'temp' : 'main';
		$seq_expr = sprintf(
			'MAX(%d, COALESCE((SELECT MAX(%s) FROM %s), 0))',
			$value - 1,
			$this->quote_sqlite_identifier( $auto_column ),
			$this->quote_sqlite_identifier( $table_name )
		);

		// Update the value in the "sqlite_sequence" table.
		$updated = $this->execute_sqlite_query(
			sprintf( 'UPDATE %s.sqlite_sequence SET seq = %s WHERE name = ?', $schema, $seq_expr ),
			array( $table_name )
		)->rowCount();

		// If the sequence value does not exist yet, insert a new row.
		// SQLite reports matched (not affected) rows, so the 0 check is safe.
		if ( 0 === $updated ) {
			$this->execute_sqlite_query(
				sprintf( 'INSERT INTO %s.sqlite_sequence (name, seq) VALUES (?, %s)', $schema, $seq_expr ),
				array( $table_name )
			);
		}
	}

	/**
	 * Translate a MySQL SHOW LIKE ... or SHOW WHERE ... condition to SQLite.
	 *
	 * @param  WP_Parser_Node $like_or_where The "likeOrWhere" AST node.
	 * @param  string         $like_column   The column name to use in the LIKE clause ("table_name", "column_name", etc.).
	 * @return string                        The translated value.
	 * @throws WP_MySQL_On_SQLite_Exception  When the translation fails.
	 */
	private function translate_show_like_or_where_condition( WP_Parser_Node $like_or_where, string $like_column ): string {
		$like_clause = $like_or_where->get_first_child_node( 'likeClause' );
		if ( null !== $like_clause ) {
			$value = $this->translate(
				$like_clause->get_first_child_node( 'textStringLiteral' )
			);
			return sprintf(
				"AND %s LIKE %s ESCAPE '\\'",
				$this->quote_sqlite_identifier( $like_column ),
				$value
			);
		}

		$where_clause = $like_or_where->get_first_child_node( 'whereClause' );
		if ( null !== $where_clause ) {
			$value = $this->translate(
				$where_clause->get_first_child_node( 'expr' )
			);
			return sprintf( 'AND %s', $value );
		}

		return '';
	}

	/**
	 * Translate INSERT or REPLACE statement body to SQLite, while emulating
	 * MySQL column type casting and implicit default values when saving data.
	 *
	 * This method rewrites an INSERT or REPLACE statement body from:
	 *   INSERT INTO table (optionally some columns) <select-or-values>
	 * To a statement body with the following structure:
	 *   INSERT INTO table (table columns)
	 *   SELECT <adjusted-values> FROM (<select-or-values>) WHERE true
	 *
	 * In MySQL, the behavior of INSERT and UPDATE statements depends on whether
	 * the STRICT_TRANS_TABLES (InnoDB) or STRICT_ALL_TABLES SQL mode is enabled.
	 *
	 * This method applies relevant type casting and emulates IMPLICIT DEFAULT
	 * value behavior as follows:
	 *   1. In STRICT mode:
	 *      - Apply relevant type casting based on the column data type.
	 *   2. In non-STRICT mode:
	 *      - Apply relevant type casting based on the column data type.
	 *      - Replace invalid values with IMPLICIT DEFAULTs.
	 *      - Replace missing values without defaults with IMPLICIT DEFAULTs.
	 *
	 * The strict SQL modes can be set per session, and can be changed at runtime.
	 * In SQLite, we can emulate this using the knowledge of the table structure.
	 *
	 * -----
	 *
	 * Here's a summary of the strict vs. non-strict IMPLICIT DEFAULT behavior:
	 *
	 * When STRICT_TRANS_TABLES or STRICT_ALL_TABLES is enabled:
	 *   1. NULL + NO DEFAULT:     No value saves NULL, NULL saves NULL, DEFAULT saves NULL.
	 *   2. NULL + DEFAULT:        No value saves DEFAULT, NULL saves NULL, DEFAULT saves DEFAULT.
	 *   3. NOT NULL + NO DEFAULT: No value is rejected, NULL is rejected, DEFAULT is rejected.
	 *   4. NOT NULL + DEFAULT:    No value saves DEFAULT, NULL is rejected, DEFAULT saves DEFAULT.
	 *
	 * When STRICT_TRANS_TABLES and STRICT_ALL_TABLES are disabled:
	 *   1. NULL + NO DEFAULT:     No value saves NULL, NULL saves NULL, DEFAULT saves NULL.
	 *   2. NULL + DEFAULT:        No value saves DEFAULT, NULL saves NULL, DEFAULT saves DEFAULT.
	 *   3. NOT NULL + NO DEFAULT: No value saves IMPLICIT DEFAULT.
	 *                             NULL is rejected on INSERT, but saves IMPLICIT DEFAULT on UPDATE.
	 *                             DEFAULT saves IMPLICIT DEFAULT.
	 *   4. NOT NULL + DEFAULT:    No value saves DEFAULT.
	 *                             NULL is rejected on INSERT, but saves IMPLICIT DEFAULT on UPDATE.
	 *                             DEFAULT saves DEFAULT.
	 *
	 * For more information about STRICT mode in MySQL, see:
	 *   https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sql-mode-strict
	 *
	 * For more information about IMPLICIT DEFAULT values in MySQL, see:
	 *   https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html#data-type-defaults-implicit
	 *
	 * @param  string         $table_name The name of the target table.
	 * @param  WP_Parser_Node $node       The "insertQueryExpression" or "insertValues" AST node.
	 * @return string                     The translated INSERT query body.
	 */
	private function translate_insert_or_replace_body(
		string $table_name,
		WP_Parser_Node $node
	): string {
		// This method is always used with the main database.
		$database = $this->get_saved_db_name( $this->main_db_name );

		// Check if strict mode is enabled.
		$is_strict_mode = (
			$this->is_sql_mode_active( 'STRICT_TRANS_TABLES' )
			|| $this->is_sql_mode_active( 'STRICT_ALL_TABLES' )
		);

		// Get column metadata for the target table from the information schema.
		$is_temporary  = $this->information_schema_builder->temporary_table_exists( $table_name );
		$columns_table = $this->information_schema_builder->get_table_name( $is_temporary, 'columns' );
		$columns       = $this->execute_sqlite_query(
			'
				SELECT LOWER(column_name) AS COLUMN_NAME, is_nullable, column_default, data_type, extra
				FROM ' . $this->quote_sqlite_identifier( $columns_table ) . '
				WHERE table_schema = ?
				AND table_name = ?
				ORDER BY ordinal_position
			',
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// Check if the table exists.
		if ( 0 === count( $columns ) ) {
			throw $this->new_table_not_found_exception( $table_name );
		}

		// Get a list of columns that are targeted by the INSERT or REPLACE query.
		// This is either an explicit column list, or all columns of the table.
		$insert_list = array();
		$fields_node = $node->get_first_child_node( 'fields' );
		if ( $fields_node ) {
			// "INSERT INTO ... (column1, column2, ...)"
			foreach ( $fields_node->get_child_nodes() as $field ) {
				$column_name   = $this->unquote_sqlite_identifier( $this->translate( $field ) );
				$insert_list[] = strtolower( $column_name );
			}
		} elseif ( 'updateList' === $node->rule_name ) {
			// "INSERT INTO ... SET column1 = value1, column2 = value2, ..."
			foreach ( $node->get_child_nodes( 'updateElement' ) as $update_element ) {
				$column_ref    = $update_element->get_first_child_node( 'columnRef' );
				$column_name   = $this->unquote_sqlite_identifier( $this->translate( $column_ref ) );
				$insert_list[] = strtolower( $column_name );
			}
		} else {
			// "INSERT INTO ... VALUES(...)" or "INSERT INTO ... SELECT ..."
			// No explicit column list is provided; we need to list all columns.
			foreach ( array_column( $columns, 'COLUMN_NAME' ) as $column_name ) {
				$insert_list[] = strtolower( $column_name );
			}
		}

		// Check if all listed columns exist.
		$unknown_columns = array_diff( $insert_list, array_column( $columns, 'COLUMN_NAME' ) );
		if ( count( $unknown_columns ) > 0 ) {
			throw $this->new_driver_exception(
				sprintf(
					"SQLSTATE[42S22]: Column not found: 1054 Unknown column '%s' in 'field list'",
					$unknown_columns[0]
				),
				'42S22'
			);
		}

		// Prepare a helper map of columns that are included in the INSERT list.
		$insert_map = array_combine( $insert_list, $insert_list );

		/*
		 * Filter out columns that were omitted in the INSERT list:
		 *  1. In strict mode, filter out all omitted columns.
		 *  2. In non-strict mode, filter out omitted columns that will get a
		 *     value from the SQLite engine. That is, nullable columns, columns
		 *     with defaults, and generated columns.
		 */
		$columns = array_values(
			array_filter(
				$columns,
				function ( $column ) use ( $is_strict_mode, $insert_map ) {
					$is_omitted = ! isset( $insert_map[ $column['COLUMN_NAME'] ] );
					if ( ! $is_omitted ) {
						return true;
					}
					if ( $is_strict_mode ) {
						return false;
					}
					$is_nullable  = 'YES' === $column['IS_NULLABLE'];
					$has_default  = null !== $column['COLUMN_DEFAULT'];
					$is_generated = str_contains( $column['EXTRA'], 'auto_increment' );
					return ! ( $is_nullable || $has_default || $is_generated );
				}
			)
		);

		/*
		 * Get a list of column names for the INSERT or REPLACE values clause.
		 * These are the columns that will be used in a SELECT statement when
		 * the values clause is wrapped in a subquery:
		 *
		 *   INSERT INTO ... SELECT <select-list> FROM (<values-from-original-query>)
		 */
		$select_list = array();
		if ( 'insertQueryExpression' === $node->rule_name ) {
			// When inserting from a SELECT query, we don't know the column names.
			// Let's wrap the query with a "SELECT (...) LIMIT 0" to obtain them.
			$expr = $node->get_first_child_node( 'queryExpressionOrParens' );
			$stmt = $this->execute_sqlite_query(
				'SELECT * FROM (' . $this->translate( $expr ) . ') LIMIT 1'
			);
			$stmt->execute();

			for ( $i = 0; $i < $stmt->columnCount(); $i++ ) {
				/*
				 * Workaround for PHP PDO SQLite bug (#79664) in PHP < 7.3.
				 * See also: https://github.com/php/php-src/pull/5654
				 */
				if ( PHP_VERSION_ID < 70300 ) {
					try {
						$column_meta = $stmt->getColumnMeta( $i );
					} catch ( Throwable $e ) {
						$column_meta = false;
					}
					if ( false === $column_meta ) {
						// Due to a PDO bug in PHP < 7.3, we get no column metadata
						// when no rows are returned. In that case, no data will be
						// inserted, so we can bail out using a simple translation.
						return $this->translate( $node );
					}
				}
				$select_list[] = $stmt->getColumnMeta( $i )['name'];
			}
		} else {
			// When inserting from a VALUES list, SQLite uses a "columnN" naming.
			// This also applies to the SET syntax, which is converted to VALUES.
			foreach ( array_keys( $insert_list ) as $position ) {
				$select_list[] = 'column' . ( $position + 1 );
			}
		}

		// Compose a new INSERT column list with all columns from the table.
		$fragment = '(';
		foreach ( $columns as $i => $column ) {
			$fragment .= $i > 0 ? ', ' : '';
			$fragment .= $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );
		}
		$fragment .= ')';

		// Compose a wrapper SELECT statement emulating MySQL-like type casting,
		// and, in non-strict mode, IMPLICIT DEFAULT values for omitted columns.
		$fragment .= ' SELECT ';
		foreach ( $columns as $i => $column ) {
			$is_omitted = ! isset( $insert_map[ $column['COLUMN_NAME'] ] );
			$fragment  .= $i > 0 ? ', ' : '';
			if ( $is_omitted ) {
				/*
				 * This path only applies to non-strict mode. In strict mode,
				 * omitted columns get no IMPLICIT DEFAULT values, and they were
				 * previously filtered out from the columns list.
				 *
				 * When a column is omitted from the INSERT list, we need to use
				 * an IMPLICIT DEFAULT value. Note that at this point, all omitted
				 * columns that will not get an implicit default are filtered out.
				 * (That is, nullable, generated, and columns with true defaults.)
				 */
				$default   = self::DATA_TYPE_IMPLICIT_DEFAULT_MAP[ $column['DATA_TYPE'] ] ?? null;
				$fragment .= null === $default ? 'NULL' : $this->quote_sqlite_value( $default );
			} else {
				// When a column value is included, we need to apply type casting.
				$position          = array_search( $column['COLUMN_NAME'], $insert_list, true );
				$identifier        = $this->quote_sqlite_identifier( $select_list[ $position ] );
				$value             = $this->cast_value_for_saving( $column['DATA_TYPE'], $identifier );
				$is_auto_increment = str_contains( $column['EXTRA'], 'auto_increment' );

				/*
				 * In MySQL, inserting 0 into an AUTO_INCREMENT column increments
				 * the sequence, unless the NO_AUTO_VALUE_ON_ZERO SQL mode is set.
				 *
				 * In SQLite, we need to rewrite 0 to NULL to advance the sequence.
				 * The value is cast to INTEGER before the comparison, because
				 * SQLite treats values of different types as unequal (0 != '0').
				 *
				 * See: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_no_auto_value_on_zero
				 */
				if ( $is_auto_increment && ! $this->is_sql_mode_active( 'NO_AUTO_VALUE_ON_ZERO' ) ) {
					$value = sprintf( 'NULLIF(CAST(%s AS INTEGER), 0)', $value );
				}

				/*
				 * In MySQL non-STRICT mode, when inserting from a SELECT query:
				 *
				 * When a column is declared as NOT NULL, inserting a NULL value
				 * saves an IMPLICIT DEFAULT value instead. This behavior only
				 * applies to the INSERT ... SELECT syntax (not VALUES or SET).
				 *
				 * AUTO_INCREMENT columns are excluded. A NULL value advances
				 * the sequence regardless of the column's nullability.
				 */
				$is_insert_from_select = 'insertQueryExpression' === $node->rule_name;
				if (
					! $is_strict_mode
					&& ! $is_auto_increment
					&& $is_insert_from_select
					&& 'NO' === $column['IS_NULLABLE']
				) {
					$implicit_default = self::DATA_TYPE_IMPLICIT_DEFAULT_MAP[ $column['DATA_TYPE'] ] ?? null;
					if ( null !== $implicit_default ) {
						$value = sprintf( 'COALESCE(%s, %s)', $value, $this->quote_sqlite_value( $implicit_default ) );
					}
				}
				$fragment .= $value;
			}
		}

		// Wrap the original insert VALUES, SELECT, or SET list in a FROM clause.
		if ( 'insertFromConstructor' === $node->rule_name ) {
			// VALUES (...)
			$insert_values = $node->get_first_child_node( 'insertValues' );
			$from          = $this->translate( $insert_values );

			/**
			 * The automatic "columnN" naming for VALUES lists is supported only
			 * from SQLite 3.33.0. For older versions, we need to emulate it by
			 * prepending a dummy VALUES list header via the UNION ALL operator:
			 *
			 * SELECT
			 *   NULL AS `column1`, NULL AS `column2`, ... WHERE FALSE
			 *   UNION ALL
			 *   VALUES (value1, value2, ...)
			 */
			$is_values_naming_supported = version_compare( $this->get_sqlite_version(), '3.33.0', '>=' );
			if ( ! $is_values_naming_supported ) {
				$values_list = $insert_values->get_first_child_node( 'valueList' );
				$values      = $values_list->get_first_child_node( 'values' );
				$value_count = (
					count( $values->get_child_nodes( 'expr' ) )
					+ count( $values->get_child_nodes( WP_MySQL_Lexer::DEFAULT_SYMBOL ) )
				);

				$columns_list = '';
				for ( $i = 1; $i <= $value_count; $i++ ) {
					$columns_list .= $i > 1 ? ', ' : '';
					$columns_list .= 'NULL AS ' . $this->quote_sqlite_identifier( 'column' . $i );
				}
				$from = 'SELECT ' . $columns_list . ' WHERE FALSE UNION ALL ' . $from;
			}
		} elseif ( 'insertQueryExpression' === $node->rule_name ) {
			// SELECT ...
			$from = $this->translate(
				$node->get_first_child_node( 'queryExpressionOrParens' )
			);
		} else {
			// SET c1 = v1, c2 = v2, ...
			$values = array();
			foreach ( $node->get_child_nodes( 'updateElement' ) as $update_element ) {
				$values[] = $this->translate( $update_element->get_first_child_node( 'expr' ) );
			}
			$from = 'VALUES (' . implode( ', ', $values ) . ')';
		}

		/*
		 * The "WHERE true" suffix is used to avoid parsing ambiguity in SQLite.
		 * When an "ON CONFLICT" clause is used and there is no "WHERE", SQLite
		 * doesn't know if "ON" belongs to a "JOIN" or an "ON CONFLICT" clause.
		 *
		 * See: https://www.sqlite.org/lang_insert.html
		 */
		$fragment .= ' FROM (' . $from . ') WHERE true';

		return $fragment;
	}

	/**
	 * Translate UPDATE statement SET value list to SQLite, while emulating
	 * MySQL column type casting and implicit default values when saving data.
	 *
	 * Rewrites an UPDATE statement list in the following form:
	 *   UPDATE table SET <column> = <value>
	 * To a list with the following structure:
	 *   UPDATE table SET <column> = <adjusted-value>
	 *
	 * In MySQL, the behavior of INSERT and UPDATE statements depends on whether
	 * the STRICT_TRANS_TABLES (InnoDB) or STRICT_ALL_TABLES SQL mode is enabled.
	 *
	 * This method applies relevant type casting and emulates IMPLICIT DEFAULT
	 * value behavior as follows:
	 *   1. In STRICT mode:
	 *      - Apply relevant type casting based on the column data type.
	 *   2. In NON-STRICT mode:
	 *      - Apply relevant type casting based on the column data type.
	 *      - Replace invalid values with IMPLICIT DEFAULTs.
	 *      - Replace NULL values without defaults with IMPLICIT DEFAULTs.
	 *        (Updating a NOT NULL column to NULL saves as an IMPLICIT DEFAULT.)
	 *
	 * The strict SQL modes can be set per session, and can be changed at runtime.
	 * In SQLite, we can emulate this using the knowledge of the table structure.
	 *
	 * For more information about STRICT mode in MySQL, see:
	 *   https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sql-mode-strict
	 *
	 * For more information about IMPLICIT DEFAULT values in MySQL, see:
	 *   https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html#data-type-defaults-implicit
	 *
	 * Additionally, this method emulates "ON UPDATE CURRENT_TIMESTAMP" columns.
	 * In MySQL, such a column is set to the current timestamp when any other
	 * column in the row changes, unless the statement assigns it explicitly.
	 *
	 * For more information about ON UPDATE CURRENT_TIMESTAMP in MySQL, see:
	 *   https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html
	 *
	 * @param  string         $table_name  The name of the target table.
	 * @param  WP_Parser_Node $parent_node The "updateList" AST node parent node.
	 * @param  string|null    $table_alias Optional. The target table alias. Defaults to the table name.
	 * @return string                      The translated UPDATE list.
	 */
	private function translate_update_list( string $table_name, WP_Parser_Node $parent_node, ?string $table_alias = null ): string {
		$node = $parent_node->get_first_child_node( 'updateList' );

		// This method is always used with the main database.
		$database = $this->get_saved_db_name( $this->main_db_name );

		// Check if strict mode is enabled.
		$is_strict_mode = (
			$this->is_sql_mode_active( 'STRICT_TRANS_TABLES' )
			|| $this->is_sql_mode_active( 'STRICT_ALL_TABLES' )
		);

		// Get column metadata from the information schema.
		$is_temporary  = $this->information_schema_builder->temporary_table_exists( $table_name );
		$columns_table = $this->information_schema_builder->get_table_name( $is_temporary, 'columns' );
		$columns       = $this->execute_sqlite_query(
			'
				SELECT LOWER(column_name) AS COLUMN_NAME, is_nullable, data_type, column_default, extra
				FROM ' . $this->quote_sqlite_identifier( $columns_table ) . '
				WHERE table_schema = ?
				AND table_name = ?
			',
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// Check if the table exists.
		if ( 0 === count( $columns ) ) {
			throw $this->new_table_not_found_exception( $table_name );
		}

		$column_map = array_combine( array_column( $columns, 'COLUMN_NAME' ), $columns );

		// Translate the UPDATE list, emulating IMPLICIT DEFAULTs for NULL values.
		$fragment        = '';
		$assigned_values = array();
		foreach ( $node->get_child_nodes() as $i => $update_element ) {
			$column_ref       = $update_element->get_first_child_node( 'columnRef' );
			$column_ref_parts = $column_ref->get_descendant_nodes( 'identifier' );
			$expr             = $update_element->get_first_child_node( 'expr' );

			// Get column info.
			$column_name = $this->unquote_sqlite_identifier( $this->translate( end( $column_ref_parts ) ) );
			$column_info = $column_map[ strtolower( $column_name ) ] ?? null;
			if ( ! $column_info ) {
				throw $this->new_driver_exception(
					sprintf(
						"SQLSTATE[42S22]: Column not found: 1054 Unknown column '%s' in 'field list'",
						$column_name
					),
					'42S22'
				);
			}

			$data_type   = $column_info['DATA_TYPE'];
			$is_nullable = 'YES' === $column_info['IS_NULLABLE'];
			$default     = $column_info['COLUMN_DEFAULT'];

			// Get the UPDATE value. It's either an expression or a DEFAULT keyword.
			if ( null === $expr ) {
				// Emulate "column = DEFAULT".
				$value = null === $default ? 'NULL' : $this->format_sqlite_column_default( $column_info );
			} else {
				$value = $this->translate( $expr );
			}

			// Apply type casting.
			$value = $this->cast_value_for_saving( $data_type, $value );

			/*
			 * In MySQL non-STRICT mode, when a column is declared as NOT NULL,
			 * updating to a NULL value saves an IMPLICIT DEFAULT value instead.
			 * This behavior does not apply to ON DUPLICATE KEY UPDATE clauses.
			 */
			$is_on_duplicate_key_update = 'insertUpdateList' === $parent_node->rule_name;
			if ( ! $is_strict_mode && ! $is_nullable && ! $is_on_duplicate_key_update ) {
				$implicit_default = self::DATA_TYPE_IMPLICIT_DEFAULT_MAP[ $data_type ] ?? null;
				if ( null !== $implicit_default ) {
					$value = sprintf( 'COALESCE(%s, %s)', $value, $this->quote_sqlite_value( $implicit_default ) );
				}
			}

			// Compose the UPDATE list item.
			$fragment .= $i > 0 ? ', ' : '';
			$fragment .= $this->translate( end( $column_ref_parts ) );
			$fragment .= ' = ';
			$fragment .= $value;

			$assigned_values[ strtolower( $column_name ) ] = $value;
		}

		// Collect unassigned ON UPDATE CURRENT_TIMESTAMP columns for automatic updates.
		$on_update_columns = array();
		foreach ( $column_map as $column_name => $column_info ) {
			if (
				isset( $assigned_values[ $column_name ] )
				|| ! str_contains( $column_info['EXTRA'], 'on update CURRENT_TIMESTAMP' )
			) {
				continue;
			}

			$on_update_columns[] = $this->quote_sqlite_identifier( $column_name );
		}
		if ( 0 === count( $on_update_columns ) ) {
			return $fragment;
		}

		/*
		 * Emulate ON UPDATE CURRENT_TIMESTAMP for columns that are not assigned.
		 *
		 * Evaluate assigned expressions once per row and reuse their values to
		 * detect changes and determine whether to use the current timestamp.
		 *
		 * With "updated_at ... ON UPDATE CURRENT_TIMESTAMP", this MySQL query:
		 *
		 *   UPDATE items SET score = FLOOR(RAND(1) * 10);
		 *
		 * Becomes the following SQLite query (simplified):
		 *
		 *   UPDATE items SET (score, updated_at) = (
		 *     SELECT
		 *       score,
		 *       CASE WHEN items.score IS NOT score THEN CURRENT_TIMESTAMP ELSE items.updated_at END
		 *     FROM (SELECT FLOOR(RAND(1) * 10) AS score)
		 *   );
		 *
		 * Here, items.score is the stored value and score is the new value.
		 * The inner SELECT has no FROM clause, so SQLite cannot flatten it
		 * and repeat the assigned expressions when checking for changes.
		 * See: https://www.sqlite.org/optoverview.html#flattening
		 */
		$quoted_table      = $this->quote_sqlite_identifier( $table_alias ?? $table_name );
		$assigned_columns  = array();
		$value_expressions = array();
		$changes           = array();
		foreach ( $assigned_values as $column_name => $value ) {
			$quoted_column       = $this->quote_sqlite_identifier( $column_name );
			$assigned_columns[]  = $quoted_column;
			$value_expressions[] = sprintf( '(%s) COLLATE BINARY AS %s', $value, $quoted_column );

			// Compare row data using binary collation, qualifying the old values.
			$changes[] = sprintf( '%s.%s IS NOT %s COLLATE BINARY', $quoted_table, $quoted_column, $quoted_column );
		}
		$is_row_changed = implode( ' OR ', $changes );
		$row_values     = $assigned_columns;
		foreach ( $on_update_columns as $quoted_column ) {
			$assigned_columns[] = $quoted_column;
			$row_values[]       = sprintf(
				'CASE WHEN %s THEN CURRENT_TIMESTAMP ELSE %s.%s END',
				$is_row_changed,
				$quoted_table,
				$quoted_column
			);
		}

		return sprintf(
			'(%s) = (SELECT %s FROM (SELECT %s))',
			implode( ', ', $assigned_columns ),
			implode( ', ', $row_values ),
			implode( ', ', $value_expressions )
		);
	}

	/**
	 * Get the default SQL modes for the emulated MySQL version.
	 *
	 * @return string[] Default SQL mode names.
	 */
	private function get_default_sql_modes(): array {
		$sql_modes = self::DEFAULT_SQL_MODES;
		if ( $this->mysql_version < 80000 ) {
			$sql_modes[] = 'NO_AUTO_CREATE_USER';
		}

		return $sql_modes;
	}

	/**
	 * Set the active SQL modes from a name list or numeric bitmask.
	 *
	 * @param string[]|int $modes SQL mode names or a numeric bitmask.
	 */
	private function set_sql_modes( $modes ): void {
		if ( null === $modes ) {
			throw $this->new_invalid_sql_mode_exception( $modes );
		}

		$known_sql_modes_mask = array_sum( self::SQL_MODES );

		// TIME_TRUNCATE_FRACTIONAL was introduced in MySQL 8.0.1.
		if ( $this->mysql_version < 80001 ) {
			$known_sql_modes_mask &= ~self::SQL_MODES['TIME_TRUNCATE_FRACTIONAL'];
		}

		// Numeric SQL mode bitmap assignment (e.g., "SET sql_mode = 4" for ANSI_QUOTES).
		if ( is_int( $modes ) ) {
			// Ensure the bitmap contains only SQL mode bits known to the emulated MySQL version.
			if ( $modes < 0 || ( $modes & ~$known_sql_modes_mask ) !== 0 ) {
				throw $this->new_invalid_sql_mode_exception( $modes );
			}

			// Reject recognized but no longer supported SQL modes.
			$unsupported_sql_modes = 0;
			foreach ( self::SQL_MODES as $mode => $value ) {
				if ( ( $modes & $value ) !== 0 && $this->is_sql_mode_removed( $mode ) ) {
					$unsupported_sql_modes |= $value;
				}
			}

			if ( 0 !== $unsupported_sql_modes ) {
				throw $this->new_driver_exception(
					sprintf(
						'SQLSTATE[HY000]: General error: 3899 sql_mode=0x%08x is not supported.',
						$unsupported_sql_modes
					),
					'HY000'
				);
			}

			$sql_modes = $modes;
		} elseif ( is_array( $modes ) ) {
			// String SQL mode assignment (e.g., "SET sql_mode = 'ANSI_QUOTES,STRICT_TRANS_TABLES'").
			$sql_modes = 0;
			foreach ( $modes as $mode ) {
				if ( '' === $mode ) {
					continue;
				}

				$normalized_mode = strtoupper( $mode );
				$mode_value      = self::SQL_MODES[ $normalized_mode ] ?? 0;
				if (
					0 === ( $mode_value & $known_sql_modes_mask )
					|| ( 'NOT_USED' === $normalized_mode && $this->mysql_version < 80000 )
					|| $this->is_sql_mode_removed( $normalized_mode )
				) {
					throw $this->new_invalid_sql_mode_exception( $mode );
				}

				$sql_modes |= $mode_value;
			}
		} else {
			throw $this->new_driver_exception(
				"SQLSTATE[42000]: Syntax error or access violation: 1232 Incorrect argument type to variable 'sql_mode'",
				'42000'
			);
		}

		if ( ( $sql_modes & self::SQL_MODES['NO_BACKSLASH_ESCAPES'] ) !== 0 ) {
			throw $this->new_not_supported_exception( "SQL mode 'NO_BACKSLASH_ESCAPES'" );
		}

		/*
		 * MySQL retains composite SQL modes while enabling their component modes.
		 * Store both so "@@sql_mode" and individual mode checks match MySQL, even
		 * though not all resulting modes are respected by the emulation yet.
		 *
		 * See:
		 *   https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sql-mode-combo
		 *   https://github.com/mysql/mysql-server/blob/8.4/sql/sys_vars.cc
		 */
		if ( ( $sql_modes & self::SQL_MODES['ANSI'] ) !== 0 ) {
			$sql_modes |= self::SQL_MODES['REAL_AS_FLOAT']
				| self::SQL_MODES['PIPES_AS_CONCAT']
				| self::SQL_MODES['ANSI_QUOTES']
				| self::SQL_MODES['IGNORE_SPACE']
				| self::SQL_MODES['ONLY_FULL_GROUP_BY'];
		}

		if ( ( $sql_modes & self::SQL_MODES['TRADITIONAL'] ) !== 0 ) {
			$sql_modes |= self::SQL_MODES['STRICT_TRANS_TABLES']
				| self::SQL_MODES['STRICT_ALL_TABLES']
				| self::SQL_MODES['NO_ZERO_IN_DATE']
				| self::SQL_MODES['NO_ZERO_DATE']
				| self::SQL_MODES['ERROR_FOR_DIVISION_BY_ZERO']
				| self::SQL_MODES['NO_ENGINE_SUBSTITUTION'];

			// TRADITIONAL included NO_AUTO_CREATE_USER until its removal in MySQL 8.0.11.
			if ( ! $this->is_sql_mode_removed( 'NO_AUTO_CREATE_USER' ) ) {
				$sql_modes |= self::SQL_MODES['NO_AUTO_CREATE_USER'];
			}
		}

		$this->active_sql_modes = $sql_modes;
	}

	/**
	 * Check whether an SQL mode was removed from the emulated MySQL version.
	 *
	 * @param  string $mode Normalized SQL mode name.
	 * @return bool         Whether the SQL mode was removed.
	 */
	private function is_sql_mode_removed( string $mode ): bool {
		/*
		 * MySQL still recognizes the legacy bits for modes removed in 8.0.11
		 * so it can report them as unsupported, rather than unknown.
		 */
		return $this->mysql_version >= 80011
			&& in_array(
				$mode,
				array(
					'POSTGRESQL',
					'ORACLE',
					'MSSQL',
					'DB2',
					'MAXDB',
					'NO_KEY_OPTIONS',
					'NO_TABLE_OPTIONS',
					'NO_FIELD_OPTIONS',
					'MYSQL323',
					'MYSQL40',
					'NO_AUTO_CREATE_USER',
				),
				true
			);
	}

	/**
	 * Get the active SQL mode names in canonical bitmask order.
	 *
	 * @return string[] Active SQL mode names.
	 */
	private function get_active_sql_mode_names(): array {
		$active_modes = array();
		foreach ( self::SQL_MODES as $mode => $value ) {
			if ( ( $this->active_sql_modes & $value ) !== 0 ) {
				if ( 'NOT_USED' === $mode && $this->mysql_version < 80000 ) {
					/*
					 * MySQL 5.7 represents reserved bit 4 with a comma in its mode
					 * name table. Two empty components preserve that serialization
					 * when the final list is joined with commas.
					 */
					$active_modes[] = '';
					$active_modes[] = '';
				} else {
					$active_modes[] = $mode;
				}
			}
		}

		return $active_modes;
	}

	/**
	 * Store column metadata for the last SQLite statement.
	 *
	 * This function stores the original SQLite column metadata as-is, without
	 * converting it into MySQL column metadata. That is done only when needed.
	 *
	 * @param PDOStatement $stmt The PDOStatement object containing the SQLite column metadata.
	 */
	private function store_last_column_meta_from_statement( PDOStatement $stmt ): void {
		$this->last_column_meta = array();
		for ( $i = 0; $i < $stmt->columnCount(); $i++ ) {
			/*
			 * Workaround for PHP PDO SQLite bug (#79664) in PHP < 7.3.
			 * See also: https://github.com/php/php-src/pull/5654
			 */
			if ( PHP_VERSION_ID < 70300 ) {
				try {
					$this->last_column_meta[] = $stmt->getColumnMeta( $i );
				} catch ( Throwable $e ) {
					$this->last_column_meta[] = array(
						'native_type' => 'null',
						'pdo_type'    => PDO::PARAM_NULL,
						'flags'       => array(),
						'table'       => '',
						'name'        => '',
						'len'         => -1,
						'precision'   => 0,
					);
				}
				continue;
			}

			$this->last_column_meta[] = $stmt->getColumnMeta( $i );
		}
	}

	/**
	 * Unnest parenthesized MySQL expression node.
	 *
	 * In MySQL, extra parentheses around simple expressions are not considered.
	 *
	 * For example, the "SELECT (((id)))" clause is equivalent to "SELECT id".
	 * This means that the "(((id)))" part will behave as a column name rather
	 * than as an expression, and the resulting column name will be just "id".
	 *
	 * @param  WP_Parser_Node $node The expression AST node.
	 * @return WP_Parser_Node       The unnested expression.
	 */
	private function unnest_parenthesized_expression( WP_Parser_Node $node ): WP_Parser_Node {
		$children = $node->get_children();

		// Descend the "expr -> boolPri -> predicate -> bitExpr -> simpleExpr" -> "simpleExprBody"
		// tree, when on each level we have only a single child node (expression nesting).
		if (
			1 === count( $children )
			&& $children[0] instanceof WP_Parser_Node
			&& in_array( $children[0]->rule_name, array( 'expr', 'boolPri', 'predicate', 'bitExpr', 'simpleExpr', 'simpleExprBody' ), true )
		) {
			$unnested = $this->unnest_parenthesized_expression( $children[0] );
			return $unnested === $children[0] ? $node : $unnested;
		}

		// Unnest "OPEN_PAR_SYMBOL exprList CLOSE_PAR_SYMBOL" to "exprList".
		if (
			count( $children ) === 3
			&& $children[0] instanceof WP_MySQL_Token && WP_MySQL_Lexer::OPEN_PAR_SYMBOL === $children[0]->id
			&& $children[1] instanceof WP_Parser_Node && 'exprList' === $children[1]->rule_name
			&& $children[2] instanceof WP_MySQL_Token && WP_MySQL_Lexer::CLOSE_PAR_SYMBOL === $children[2]->id
			&& 1 === count( $children[1]->get_children() )
		) {
			return $this->unnest_parenthesized_expression( $children[1] );
		}

		return $node;
	}

	/**
	 * Disambiguate and translate an expression with a simple or parenthesized
	 * column reference for use within an ORDER BY, GROUP BY, or HAVING clause.
	 *
	 * In SQLite, columns that exist in multiple tables used within a query must
	 * be fully qualified when used in the ORDER BY, GROUP BY, or HAVING clause.
	 * In MySQL, these can be disambiguated using the SELECT item list.
	 *
	 * For example, when tables "t1" and "t2" both have a column called "name",
	 * the following query will cause an "ambiguous column name" error in SQLite,
	 * but it will succeed in MySQL, using the "t1.name" from the SELECT clause:
	 *
	 *   SELECT t1.name FROM t1 JOIN t2 ON t2.t1_id = t1.id ORDER BY name
	 *
	 * This is because MySQL primarily considers the "name" column that was used
	 * in the SELECT list - when it is unambiguous, it will be used in ORDER BY.
	 *
	 * To emulate this behavior in SQLite, we will search for unqualified column
	 * references in the ORDER BY, GROUP BY, or HAVING item expression, and try
	 * to qualify them using the SELECT item list.
	 *
	 * In other words, the above query will be rewritten as follows:
	 *
	 *   SELECT t1.name FROM t1 JOIN t2 ON t2.t1_id = t1.id ORDER BY t1.name
	 *
	 * Note that the ORDER BY column was rewritten from "name" to "t1.name".
	 *
	 * @TODO: When multi-database support is implemented, we'll also need to
	 *        consider column references in forms like "db.table.column".
	 *
	 * @param  array          $disambiguation_map The SELECT item disambiguation map (column name => array of select items).
	 *                                            @see WP_MySQL_On_SQLite::create_select_item_disambiguation_map()
	 * @param  WP_Parser_Node $expr               The expression AST node or subnode.
	 * @return string|null                        The disambiguated and translated expression;
	 *                                            null when the expression cannot be disambiguated.
	 */
	private function disambiguate_item( array $disambiguation_map, WP_Parser_Node $expr ) {
		// Skip when there is no column in the expression (no "columnRef" node),
		// or when the column is already qualified (has a "dotIdentifier" node).
		$column_ref = $expr->get_first_descendant_node( 'columnRef' );
		if ( ! $column_ref || $column_ref->get_first_descendant_node( 'dotIdentifier' ) ) {
			return null;
		}

		// Support also parenthesized column references (e.g. "(id)").
		$expr = $this->unnest_parenthesized_expression( $expr );

		// Consider only simple and parenthesized column references (as per MySQL).
		$expr_value   = $this->translate( $expr );
		$column_value = $this->translate( $column_ref );
		if ( $expr_value !== $column_value ) {
			return null;
		}

		// Look for SELECT items that match the column reference.
		$column_name         = $this->translate( $column_ref );
		$select_item_matches = $disambiguation_map[ $column_name ] ?? array();

		// When we find exactly one matching SELECT list item, we can disambiguate
		// the column reference. Otherwise, fall back to the original expression.
		if ( 1 === count( $select_item_matches ) ) {
			return $select_item_matches[0];
		}
		return null;
	}

	/**
	 * Create a SELECT item disambiguation map from a SELECT item list for use
	 * with the ORDER BY, GROUP BY, and HAVING clause disambiguation algorithm.
	 *
	 * @see WP_MySQL_On_SQLite::disambiguate_item()
	 *
	 * @param  WP_Parser_Node $select_item_list The "selectItemList" AST node.
	 * @return array                            The SELECT item disambiguation map (column name => array of select items).
	 */
	private function create_select_item_disambiguation_map( WP_Parser_Node $select_item_list ): array {
		// Create a map of SELECT item column names to their qualified values.
		$disambiguation_map = array();
		foreach ( $select_item_list->get_child_nodes() as $select_item ) {
			/*
			 * [GRAMMAR]
			 * selectItem: tableWild | (expr selectAlias?)
			 */

			// Skip when a "tableWild" node is used (no "expr" node).
			$select_item_expr = $select_item->get_first_child_node( 'expr' );
			if ( ! $select_item_expr ) {
				continue;
			}

			// A SELECT item alias always needs to be preserved as-is.
			$alias = $select_item->get_first_child_node( 'selectAlias' );
			if ( $alias ) {
				$alias_value                        = $this->translate( $alias->get_first_child_node() );
				$disambiguation_map[ $alias_value ] = array( $alias_value );
				continue;
			}

			// Skip when there is no column listed (no "columnRef" node).
			$select_column_ref = $select_item_expr->get_first_descendant_node( 'columnRef' );
			if ( ! $select_column_ref ) {
				continue;
			}

			// Skip when the column reference is not qualified (no "dotIdentifier" node).
			$dot_identifiers = $select_column_ref->get_descendant_nodes( 'dotIdentifier' );
			if ( 0 === count( $dot_identifiers ) ) {
				continue;
			}

			// Support also parenthesized column references (e.g. "(t.id)").
			$select_item_expr = $this->unnest_parenthesized_expression( $select_item_expr );

			// Consider only simple and parenthesized column references (as per MySQL).
			$expr_value   = $this->translate( $select_item_expr );
			$column_value = $this->translate( $select_column_ref );
			if ( $expr_value !== $column_value ) {
				continue;
			}

			// The column name is the last "dotIdentifier" node.
			$key = $this->translate( end( $dot_identifiers )->get_first_child_node() );

			$disambiguation_map[ $key ]   = $disambiguation_map[ $key ] ?? array();
			$disambiguation_map[ $key ][] = $column_value;
		}
		return $disambiguation_map;
	}

	/**
	 * Analyze a "tableReferenceList" AST node and extract table data.
	 *
	 * This method extracts table data for all tables that are used at the root
	 * level of a given query, including tables that are referenced using JOINs.
	 *
	 * The returned array maps table aliases to table names and additional data:
	 *   - key:   table alias, or name if no alias is used
	 *   - value: an array of table data
	 *       - database:   the database name of the table (null for derived tables)
	 *       - table_name: the real name of the table (null for derived tables)
	 *       - table_expr: the table expression for a derived table (null for regular tables)
	 *       - join_expr:  the join expression used for the table (null when no join is used)
	 *
	 * MySQL has a non-stand ardsyntax extension where a comma-separated list of
	 * table references is allowed as a table reference in itself, for instance:
	 *   SELECT * FROM (t1, t2) JOIN t3 ON 1
	 *
	 * Which is equivalent to:
	 *   SELECT * FROM (t1 CROSS JOIN t2) JOIN t3 ON 1
	 *
	 * @param  WP_Parser_Node $node The "tableReferenceList" AST node.
	 * @return array                The table reference map (table alias => array of table data).
	 */
	private function create_table_reference_map( WP_Parser_Node $node ): array {
		$table_map = array();

		// Collect all table references, including the ones used in JOINs.
		$table_refs = array();
		foreach ( $node->get_child_nodes( 'tableReference' ) as $table_ref ) {
			$table_refs[] = $table_ref;
			foreach ( $table_ref->get_child_nodes( 'joinedTable' ) as $joined_table ) {
				$table_refs[] = $joined_table;
			}
		}

		// Process each table reference, extracting table data.
		foreach ( $table_refs as $table_ref ) {
			$table_factor = $table_ref->get_first_descendant_node( 'tableFactor' );
			$join_expr    = $table_ref->get_first_child_node( 'expr' );
			$child        = $table_factor->get_first_child_node();

			// Descend all "singleTableParens" nodes to get the "singleTable" node.
			if ( 'singleTableParens' === $child->rule_name ) {
				$child = $child->get_first_descendant_node( 'singleTable' );
			}

			if ( 'singleTable' === $child->rule_name ) {
				// Extract data from the "singleTable" node.
				$table_ref  = $child->get_first_child_node( 'tableRef' );
				$name       = $this->translate( $table_ref );
				$alias_node = $child->get_first_child_node( 'tableAlias' );
				$alias      = $alias_node ? $this->translate( $alias_node->get_first_child_node( 'identifier' ) ) : null;

				$table_map[ $this->unquote_sqlite_identifier( $alias ?? $name ) ] = array(
					'database'   => $this->get_database_name( $table_ref ),
					'table_name' => $this->unquote_sqlite_identifier( $name ),
					'table_expr' => null,
					'join_expr'  => $this->translate( $join_expr ),
				);
			} elseif ( 'derivedTable' === $child->rule_name ) {
				// Extract data from the "derivedTable" node.
				$subquery   = $child->get_first_descendant_node( 'subquery' );
				$alias_node = $child->get_first_child_node( 'tableAlias' );
				$alias      = $alias_node ? $this->translate( $alias_node->get_first_child_node( 'identifier' ) ) : null;

				$table_map[ $this->unquote_sqlite_identifier( $alias ) ] = array(
					'database'   => null,
					'table_name' => null,
					'table_expr' => $this->translate( $subquery ),
					'join_expr'  => $this->translate( $join_expr ),
				);
			} elseif ( 'tableReferenceListParens' === $child->rule_name ) {
				// Recursively process the "tableReferenceListParens" node.
				$table_ref_list = $child->get_first_descendant_node( 'tableReferenceList' );
				$table_map      = array_merge( $table_map, $this->create_table_reference_map( $table_ref_list ) );
			}
		}
		return $table_map;
	}

	/**
	 * Emulate MySQL type casting for values to be saved to the database
	 * using INSERT, REPLACE, or UPDATE statements.
	 *
	 * @param  string $mysql_data_type  The MySQL data type.
	 * @param  string $translated_value The original translated value.
	 * @return string                   The translated value.
	 */
	private function cast_value_for_saving(
		string $mysql_data_type,
		string $translated_value
	): string {
		// TODO: This is also a good place to implement checks for maximum column
		//       lengths with truncating or bailing out depending on the SQL mode.

		// Check if strict mode is enabled.
		$is_strict_mode = (
			$this->is_sql_mode_active( 'STRICT_TRANS_TABLES' )
			|| $this->is_sql_mode_active( 'STRICT_ALL_TABLES' )
		);

		$mysql_data_type  = strtolower( $mysql_data_type );
		$sqlite_data_type = self::DATA_TYPE_STRING_MAP[ $mysql_data_type ];

		/*
		 * In MySQL, when saving a value via INSERT or UPDATE in non-strict mode,
		 *   1. MySQL attempts to cast the value to the target column data type.
		 *   2. When casting can't be done, MySQL saves an IMPLICIT DEFAULT.
		 */
		switch ( $mysql_data_type ) {
			case 'date':
			case 'time':
			case 'datetime':
			case 'timestamp':
			case 'year':
				/*
				 * MySQL supports date and time components without a zero padding,
				 * but that doesn't work with date and time functions in SQLite.
				 * E.g.: "2025-3-7 9:5:2" is a valid datetime/timestamp value in
				 * in MySQL, but SQLite requires it to be "2025-03-07 09:05:02".
				 *
				 * A solution to this would need to be done on the SQL level to
				 * address computed values, and it should be done for the strict
				 * mode as well. This may require a user-defined function.
				 *
				 * TODO: Handle zero padding for date and time functions, while
				 *       supporting both strict and non-strict modes.
				 */

				if ( 'date' === $mysql_data_type ) {
					$function_call = sprintf( 'DATE(%s)', $translated_value );
				} elseif ( 'time' === $mysql_data_type ) {
					$function_call = sprintf( 'TIME(%s)', $translated_value );
				} elseif ( 'datetime' === $mysql_data_type || 'timestamp' === $mysql_data_type ) {
					$function_call = sprintf( 'DATETIME(%s)', $translated_value );
				} elseif ( 'year' === $mysql_data_type ) {
					/*
					 * The YEAR type in MySQL only uses 1 byte and therefore
					 * covers only 256 values from 1901 to 2155 included.
					 * Additionally:
					 *   - Numbers from 0 to 69 correspond to years 2000 to 2069.
					 *   - Numbers from 70 to 99 correspond to years 1970 to 1999.
					 */
					return sprintf(
						"(
							SELECT CASE
								WHEN value IS NULL THEN NULL
								WHEN value = 0 THEN '0000'
								WHEN value BETWEEN 1901 AND 2155 THEN value
								WHEN value BETWEEN 1 AND 69 THEN 2000 + value
								WHEN value BETWEEN 70 AND 99 THEN 1900 + value
								ELSE %s
							END
							FROM (SELECT CAST(%s AS INTEGER) AS value)
						)",
						$is_strict_mode
							? sprintf( "THROW('Out of range value: ''' || %s || '''')", $translated_value )
							: "'0000'",
						$translated_value
					);
				}

				// In strict mode, invalid date/time values are rejected.
				// In non-strict mode, they get an IMPLICIT DEFAULT value.
				if ( $is_strict_mode ) {
					$fallback = sprintf(
						"THROW('Incorrect %s value: ''' || %s || '''')",
						$mysql_data_type,
						$translated_value
					);
				} else {
					$implicit_default = self::DATA_TYPE_IMPLICIT_DEFAULT_MAP[ $mysql_data_type ] ?? null;
					$fallback         = null === $implicit_default
						? 'NULL'
						: $this->quote_sqlite_value( $implicit_default );
				}

				/*
				 * Build the CASE expression for date/time validation.
				 *
				 * SQLite's DATE()/DATETIME() functions return NULL for zero
				 * dates, so the CASE includes explicit checks controlled by
				 * the NO_ZERO_DATE and NO_ZERO_IN_DATE SQL modes.
				 *
				 * In MySQL, the behavior of zero dates depends on these modes:
				 *
				 * NO_ZERO_DATE (see https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_no_zero_date):
				 *   - Disabled: '0000-00-00' is permitted and produces no warning.
				 *   - Enabled without strict mode: '0000-00-00' is permitted but produces a warning.
				 *   - Enabled with strict mode: '0000-00-00' is not permitted and produces an error.
				 *
				 * NO_ZERO_IN_DATE (see https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_no_zero_in_date):
				 *   - Disabled: dates with zero month/day parts (e.g. '2020-00-15') are permitted.
				 *   - Enabled without strict mode: zero-part dates produce a warning and are stored as '0000-00-00'.
				 *   - Enabled with strict mode: zero-part dates produce an error.
				 */
				return strtr(
					"CASE
						WHEN {value} IS NULL THEN NULL
						WHEN {value} IN ('0000-00-00', '0000-00-00 00:00:00') AND NOT {reject_zero_date} THEN {zero_date_value}
						WHEN SUBSTR({value}, 1, 4) != '0000' AND (SUBSTR({value}, 6, 2) = '00' OR SUBSTR({value}, 9, 2) = '00') AND NOT {reject_zero_in_date} THEN {value}
						WHEN {function_call} > '0' THEN {function_call}
						ELSE {fallback}
					END",
					array(
						'{value}'               => $translated_value,
						'{reject_zero_date}'    => (
							$this->is_sql_mode_active( 'NO_ZERO_DATE' ) && $is_strict_mode
						) ? 1 : 0,
						'{zero_date_value}'     => 'date' === $mysql_data_type
													? "'0000-00-00'"
													: "'0000-00-00 00:00:00'",
						'{reject_zero_in_date}' => $this->is_sql_mode_active( 'NO_ZERO_IN_DATE' ) ? 1 : 0,
						'{function_call}'       => $function_call,
						'{fallback}'            => $fallback,
					)
				);
			default:
				/*
				 * For all other data types, cast to the SQLite types as follows:
				 *   1. In strict mode, cast only values for TEXT and BLOB columns.
				 *      Numeric types accept string notation in SQLite as well.
				 *   2. In non-strict mode, cast all values.
				 *
				 * TODO: While close to MySQL behavior, this doesn't exactly match
				 *       all special cases. We may improve this further to accept
				 *       BLOBs for numeric types, and other special behaviors.
				 */
				if ( ! $is_strict_mode || 'TEXT' === $sqlite_data_type || 'BLOB' === $sqlite_data_type ) {
					return sprintf( 'CAST(%s AS %s)', $translated_value, $sqlite_data_type );
				}
				return $translated_value;
		}
	}

	/**
	 * Get the database name as it is saved in the information schema tables.
	 *
	 * @param  string|null $db_name Optional. The database name to use. Defaults to the current database name.
	 * @return string               The database name as it is saved in the information schema tables.
	 */
	private function get_saved_db_name( ?string $db_name = null ): string {
		if ( null === $db_name ) {
			$db_name = $this->db_name;
		}
		return $this->main_db_name === $db_name
			? WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME
			: $db_name;
	}

	/**
	 * Get the database name from one of fully-qualified name AST nodes.
	 *
	 * @param  WP_Parser_Node $node The AST node. One of "tableName", "tableRef", or "inDb".
	 * @return string               The database name.
	 */
	private function get_database_name( WP_Parser_Node $node ): string {
		if ( 'tableName' === $node->rule_name || 'tableRef' === $node->rule_name ) {
			$parts = $node->get_descendant_nodes( 'identifier' );
			if ( count( $parts ) > 1 ) {
				return $this->unquote_sqlite_identifier( $this->translate( $parts[0] ) );
			} else {
				return $this->db_name;
			}
		} elseif ( 'inDb' === $node->rule_name ) {
			return $this->unquote_sqlite_identifier(
				$this->translate( $node->get_first_child_node( 'identifier' ) )
			);
		}

		throw $this->new_driver_exception(
			sprintf( 'Could not get database name from node: %s', $node->rule_name )
		);
	}

	/**
	 * Generate a SQLite CREATE TABLE statement from information schema data.
	 *
	 * @param  bool        $table_is_temporary Whether the table is temporary.
	 * @param  string      $table_name         The name of the table to create.
	 * @param  string|null $new_table_name     Override the original table name for ALTER TABLE emulation.
	 * @return string[]                        Queries to create the table, indexes, and constraints.
	 * @throws WP_MySQL_On_SQLite_Exception    When the table information is missing.
	 */
	private function get_sqlite_create_table_statement(
		bool $table_is_temporary,
		string $table_name,
		?string $new_table_name = null
	): array {
		// This method is always used with the main database.
		$database = $this->get_saved_db_name( $this->main_db_name );

		// 1. Get table info.
		$tables_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'tables' );
		$table_info   = $this->execute_sqlite_query(
			'
				SELECT *
				FROM ' . $this->quote_sqlite_identifier( $tables_table ) . "
				WHERE table_type = 'BASE TABLE'
				AND table_schema = ?
				AND table_name = ?
			",
			array( $database, $table_name )
		)->fetch( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			throw $this->new_table_not_found_exception( $table_name );
		}

		// 2. Get column info.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_info   = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM %s WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position',
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 3. Get index info, grouped by index name.
		$statistics_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'statistics' );
		$constraint_info  = $this->execute_sqlite_query(
			sprintf(
				"
					SELECT *
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					ORDER BY
						INDEX_NAME = 'PRIMARY' DESC,
						NON_UNIQUE = '0' DESC,
						INDEX_TYPE = 'SPATIAL' DESC,
						INDEX_TYPE = 'BTREE' DESC,
						INDEX_TYPE = 'FULLTEXT' DESC,
						ROWID,
						SEQ_IN_INDEX
				",
				$this->quote_sqlite_identifier( $statistics_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$grouped_constraints = array();
		foreach ( $constraint_info as $constraint ) {
			$name                                 = $constraint['INDEX_NAME'];
			$seq                                  = $constraint['SEQ_IN_INDEX'];
			$grouped_constraints[ $name ][ $seq ] = $constraint;
		}

		// 4. Get foreign key info.
		$referential_constraints_table = $this->information_schema_builder
			->get_table_name( $table_is_temporary, 'referential_constraints' );
		$referential_constraints_info  = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM %s WHERE constraint_schema = ? AND table_name = ? ORDER BY constraint_name',
				$this->quote_sqlite_identifier( $referential_constraints_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$key_column_usage_map = array();
		if ( count( $referential_constraints_info ) > 0 ) {
			$key_column_usage_table = $this->information_schema_builder
				->get_table_name( $table_is_temporary, 'key_column_usage' );
			$key_column_usage_info  = $this->execute_sqlite_query(
				sprintf(
					'SELECT * FROM %s WHERE table_schema = ? AND table_name = ? AND referenced_column_name IS NOT NULL',
					$this->quote_sqlite_identifier( $key_column_usage_table )
				),
				array( $database, $table_name )
			)->fetchAll( PDO::FETCH_ASSOC );

			$key_column_usage_map = array();
			foreach ( $key_column_usage_info as $key_column_usage ) {
				$constraint_name = $key_column_usage['CONSTRAINT_NAME'];
				if ( ! isset( $key_column_usage_map[ $constraint_name ] ) ) {
					$key_column_usage_map[ $constraint_name ] = array();
				}
				$key_column_usage_map[ $constraint_name ][] = array(
					$key_column_usage['COLUMN_NAME'],
					$key_column_usage['REFERENCED_COLUMN_NAME'],
				);
			}
		}

		// 5. Get CHECK constraint info.
		$table_constraints_table = $this->information_schema_builder
			->get_table_name( $table_is_temporary, 'table_constraints' );
		$check_constraints_table = $this->information_schema_builder
			->get_table_name( $table_is_temporary, 'check_constraints' );
		$check_constraints_info  = $this->execute_sqlite_query(
			sprintf(
				'SELECT tc.*, cc.check_clause
				FROM %s tc
				JOIN %s cc ON cc.constraint_name = tc.constraint_name
				WHERE tc.constraint_schema = ?
				AND tc.table_name = ?
				ORDER BY tc.constraint_name',
				$this->quote_sqlite_identifier( $table_constraints_table ),
				$this->quote_sqlite_identifier( $check_constraints_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 6. Generate CREATE TABLE statement columns.
		$rows              = array();
		$has_autoincrement = false;
		foreach ( $column_info as $column ) {
			$query  = '  ';
			$query .= $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );

			$type = self::DATA_TYPE_STRING_MAP[ $column['DATA_TYPE'] ];

			/*
			 * In SQLite, there is a PRIMARY KEY quirk for backward compatibility.
			 * This applies to ROWID tables and single-column primary keys only:
			 *  1. "INTEGER PRIMARY KEY" creates an alias of ROWID.
			 *  2. "INT PRIMARY KEY" will not alias of ROWID.
			 *
			 * Therefore, we want to:
			 *  1. Use "INT PRIMARY KEY" when we have a single-column integer
			 *     PRIMARY KEY without AUTOINCREMENT (to avoid the ROWID alias).
			 *  2. Use "INTEGER PRIMARY KEY" otherwise.
			 *
			 * In SQLite, "AUTOINCREMENT" is only allowed on "INTEGER PRIMARY KEY",
			 * and setting it changes the automatic ROWID assignment algorithm to
			 * prevent the reuse of ROWIDs. Using "INT PRIMARY KEY" is not allowed.
			 *
			 * See:
			 *   - https://www.sqlite.org/autoinc.html
			 *   - https://www.sqlite.org/lang_createtable.html
			 */
			if (
				'INTEGER' === $type
				&& 'PRI' === $column['COLUMN_KEY']
				&& ! str_contains( $column['EXTRA'], 'auto_increment' )
				&& count( $grouped_constraints['PRIMARY'] ) === 1
			) {
				$type = 'INT';
			}

			$query .= ' ' . $type;

			// In MySQL, text fields are case-insensitive by default.
			// COLLATE NOCASE emulates the same behavior in SQLite.
			// @TODO: Respect the actual column and index collation.
			if ( 'TEXT' === $type ) {
				$query .= ' COLLATE NOCASE';
			}
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$query .= ' NOT NULL';
			}
			if ( str_contains( $column['EXTRA'], 'auto_increment' ) ) {
				$has_autoincrement = true;
				$query            .= ' PRIMARY KEY AUTOINCREMENT';
			}
			if ( null !== $column['COLUMN_DEFAULT'] ) {
				// Handle DEFAULT CURRENT_TIMESTAMP. This works only with timestamp
				// and datetime columns. For other column types, it's just a string.
				if (
					'CURRENT_TIMESTAMP' === $column['COLUMN_DEFAULT']
					&& ( 'timestamp' === $column['DATA_TYPE'] || 'datetime' === $column['DATA_TYPE'] )
				) {
					$query .= ' DEFAULT CURRENT_TIMESTAMP';
				} elseif ( str_contains( $column['EXTRA'], 'DEFAULT_GENERATED' ) ) {
					// Handle DEFAULT values with expressions (DEFAULT_GENERATED).
					// Translate the default clause from MySQL to SQLite.
					$ast            = $this->create_parser( 'SELECT ' . $column['COLUMN_DEFAULT'] )->parse();
					$expr           = $ast->get_first_descendant_node( 'selectItem' )->get_first_child_node();
					$default_clause = $this->translate( $expr );
					$query         .= sprintf( ' DEFAULT (%s)', $default_clause );
				} else {
					$query .= ' DEFAULT ' . $this->format_sqlite_column_default( $column );
				}
			}
			$rows[] = $query;
		}

		// 6. Generate CREATE TABLE statement constraints, collect indexes.
		$create_index_queries = array();
		foreach ( $grouped_constraints as $constraint ) {
			ksort( $constraint );
			$info = $constraint[1];

			$column_list = array_map(
				function ( $column ) {
					$fragment = $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );
					if ( 'D' === $column['COLLATION'] ) {
						$fragment .= ' DESC';
					}
					return $fragment;
				},
				$constraint
			);

			if ( 'PRIMARY' === $info['INDEX_NAME'] ) {
				if ( $has_autoincrement ) {
					/*
					 * In MySQL, a compound PRIMARY KEY can have an AUTO_INCREMENT
					 * column, when it is the first column in the key.
					 *
					 * SQLite doesn't support this, but we can emulate it as follows:
					 *   1. Keep only the first column as a PRIMARY KEY.
					 *      Since this is the column that also has AUTO_INCREMENT,
					 *      it reasonable to assume that its values are unique.
					 *   2. Create a UNIQUE key for all the PRIMARY KEY columns.
					 *      This is to preserve the index of the compound key.
					 */
					if ( count( $constraint ) > 1 ) {
						$sqlite_index_name      = $this->get_sqlite_index_name( $table_name, 'primary' );
						$create_index_queries[] = sprintf(
							'CREATE UNIQUE INDEX %s ON %s (%s)',
							self::RESERVED_PREFIX . $sqlite_index_name,
							$this->quote_sqlite_identifier( $table_name ),
							implode( ', ', $column_list )
						);
					}

					/*
					 * The PRIMARY KEY was already generated with AUTOINCREMENT,
					 * as required by SQLite column constraint syntax.
					 *
					 * @see https://www.sqlite.org/syntax/column-constraint.html
					 */
					continue;
				}
				$rows[] = sprintf( '  PRIMARY KEY (%s)', implode( ', ', $column_list ) );
			} else {
				$is_unique = 0 === (int) $info['NON_UNIQUE'];

				// Prefix the original index name with the table name.
				// This is to avoid conflicting index names in SQLite.
				$sqlite_index_name = $this->get_sqlite_index_name( $table_name, $info['INDEX_NAME'] );

				$create_index_queries[] = sprintf(
					'CREATE %sINDEX %s ON %s (%s)',
					$is_unique ? 'UNIQUE ' : '',
					$this->quote_sqlite_identifier( $sqlite_index_name ),
					$this->quote_sqlite_identifier( $table_name ),
					implode( ', ', $column_list )
				);
			}
		}

		// 8. Add foreign key constraints.
		foreach ( $referential_constraints_info as $referential_constraint ) {
			$column_names            = array();
			$referenced_column_names = array();
			foreach ( $key_column_usage_map[ $referential_constraint['CONSTRAINT_NAME'] ] as $info ) {
				$column_names[]            = $this->quote_sqlite_identifier( $info[0] );
				$referenced_column_names[] = $this->quote_sqlite_identifier( $info[1] );
			}
			$query = sprintf(
				'  CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
				$this->quote_sqlite_identifier( $referential_constraint['CONSTRAINT_NAME'] ),
				implode( ', ', $column_names ),
				$this->quote_sqlite_identifier( $referential_constraint['REFERENCED_TABLE_NAME'] ),
				implode( ', ', $referenced_column_names )
			);

			// ON DELETE
			$delete_rule = $referential_constraint['DELETE_RULE'];
			if ( 'NO ACTION' === $delete_rule ) {
				// In MySQL, NO ACTION is equivalent to RESTRICT with InnoDB.
				$delete_rule = 'RESTRICT';
			}
			$query .= sprintf( ' ON DELETE %s', $delete_rule );

			// ON UPDATE
			$update_rule = $referential_constraint['UPDATE_RULE'];
			if ( 'NO ACTION' === $update_rule ) {
				// In MySQL, NO ACTION is equivalent to RESTRICT with InnoDB.
				$update_rule = 'RESTRICT';
			}
			$query .= sprintf( ' ON UPDATE %s', $update_rule );

			$rows[] = $query;
		}

		// 9. Add CHECK constraints.
		foreach ( $check_constraints_info as $check_constraint ) {
			if ( 'NO' === $check_constraint['ENFORCED'] ) {
				continue;
			}

			// Translate the check clause from MySQL to SQLite.
			$ast          = $this->create_parser( 'SELECT ' . $check_constraint['CHECK_CLAUSE'] )->parse();
			$expr         = $ast->get_first_descendant_node( 'selectItem' )->get_first_child_node();
			$check_clause = $this->translate( $expr );

			$sql    = sprintf(
				'  CONSTRAINT %s CHECK (%s)',
				$this->quote_sqlite_identifier( $check_constraint['CONSTRAINT_NAME'] ),
				$check_clause
			);
			$rows[] = $sql;
		}

		// 10. Compose the CREATE TABLE statement.
		$create_table_query  = sprintf(
			"CREATE %sTABLE %s (\n",
			$table_is_temporary ? 'TEMPORARY ' : '',
			$this->quote_sqlite_identifier( $new_table_name ?? $table_name )
		);
		$create_table_query .= implode( ",\n", $rows );
		$create_table_query .= "\n)";

		if ( version_compare( $this->get_sqlite_version(), '3.37.0', '>=' ) ) {
			$create_table_query .= ' STRICT';
		}

		return array_merge( array( $create_table_query ), $create_index_queries );
	}

	/**
	 * Generate a MySQL CREATE TABLE statement from information schema data.
	 *
	 * @param  bool   $table_is_temporary Whether the table is temporary.
	 * @param  string $table_name         The name of the table to create.
	 * @return string                     The CREATE TABLE statement.
	 */
	private function get_mysql_create_table_statement( bool $table_is_temporary, string $table_name ): ?string {
		// This method is always used with the main database.
		$database = $this->get_saved_db_name( $this->main_db_name );

		// 1. Get table info.
		$tables_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'tables' );
		$table_info   = $this->execute_sqlite_query(
			'
				SELECT *
				FROM ' . $this->quote_sqlite_identifier( $tables_table ) . "
				WHERE table_type = 'BASE TABLE'
				AND table_schema = ?
				AND table_name = ?
			",
			array( $database, $table_name )
		)->fetch( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			return null;
		}

		// 2. Get column info.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_info   = $this->execute_sqlite_query(
			sprintf(
				'
					SELECT *
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					ORDER BY ordinal_position
				',
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 3. Get index info, grouped by index name.
		$statistics_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'statistics' );
		$constraint_info  = $this->execute_sqlite_query(
			sprintf(
				"
					SELECT *
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					ORDER BY
						INDEX_NAME = 'PRIMARY' DESC,
						NON_UNIQUE = '0' DESC,
						INDEX_TYPE = 'SPATIAL' DESC,
						INDEX_TYPE = 'BTREE' DESC,
						INDEX_TYPE = 'FULLTEXT' DESC,
						ROWID,
						SEQ_IN_INDEX
				",
				$this->quote_sqlite_identifier( $statistics_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$grouped_constraints = array();
		foreach ( $constraint_info as $constraint ) {
			$name                                 = $constraint['INDEX_NAME'];
			$seq                                  = $constraint['SEQ_IN_INDEX'];
			$grouped_constraints[ $name ][ $seq ] = $constraint;
		}

		// 4. Get foreign key info.
		$referential_constraints_table = $this->information_schema_builder
			->get_table_name( $table_is_temporary, 'referential_constraints' );
		$referential_constraints_info  = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM %s WHERE constraint_schema = ? AND table_name = ? ORDER BY constraint_name',
				$this->quote_sqlite_identifier( $referential_constraints_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$key_column_usage_map = array();
		if ( count( $referential_constraints_info ) > 0 ) {
			$key_column_usage_table = $this->information_schema_builder
				->get_table_name( $table_is_temporary, 'key_column_usage' );
			$key_column_usage_info  = $this->execute_sqlite_query(
				sprintf(
					'SELECT * FROM %s WHERE table_schema = ? AND table_name = ? AND referenced_column_name IS NOT NULL',
					$this->quote_sqlite_identifier( $key_column_usage_table )
				),
				array( $database, $table_name )
			)->fetchAll( PDO::FETCH_ASSOC );

			$key_column_usage_map = array();
			foreach ( $key_column_usage_info as $key_column_usage ) {
				$constraint_name = $key_column_usage['CONSTRAINT_NAME'];
				if ( ! isset( $key_column_usage_map[ $constraint_name ] ) ) {
					$key_column_usage_map[ $constraint_name ] = array();
				}
				$key_column_usage_map[ $constraint_name ][] = array(
					$key_column_usage['COLUMN_NAME'],
					$key_column_usage['REFERENCED_COLUMN_NAME'],
				);
			}
		}

		// 5. Get CHECK constraint info.
		$table_constraints_table = $this->information_schema_builder
			->get_table_name( $table_is_temporary, 'table_constraints' );
		$check_constraints_table = $this->information_schema_builder
			->get_table_name( $table_is_temporary, 'check_constraints' );
		$check_constraints_info  = $this->execute_sqlite_query(
			sprintf(
				'SELECT tc.*, cc.check_clause
				FROM %s tc
				JOIN %s cc ON cc.constraint_name = tc.constraint_name
				WHERE tc.constraint_schema = ?
				AND tc.table_name = ?
				ORDER BY tc.constraint_name',
				$this->quote_sqlite_identifier( $table_constraints_table ),
				$this->quote_sqlite_identifier( $check_constraints_table )
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 6. Generate CREATE TABLE statement columns.
		$rows               = array();
		$has_auto_increment = false;
		foreach ( $column_info as $column ) {
			$sql  = '  ';
			$sql .= $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
			$sql .= ' ' . $column['COLUMN_TYPE'];
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$sql .= ' NOT NULL';
			} elseif ( 'timestamp' === $column['COLUMN_TYPE'] ) {
				// Nullable "timestamp" columns dump NULL explicitly.
				$sql .= ' NULL';
			}
			if ( str_contains( $column['EXTRA'], 'auto_increment' ) ) {
				$has_auto_increment = true;
				$sql               .= ' AUTO_INCREMENT';
			}

			// Handle DEFAULT CURRENT_TIMESTAMP. This works only with timestamp
			// and datetime columns. For other column types, it's just a string.
			if (
				'CURRENT_TIMESTAMP' === $column['COLUMN_DEFAULT']
				&& ( 'timestamp' === $column['DATA_TYPE'] || 'datetime' === $column['DATA_TYPE'] )
			) {
				$sql .= ' DEFAULT CURRENT_TIMESTAMP';
			} elseif ( null !== $column['COLUMN_DEFAULT'] ) {
				if ( str_contains( $column['EXTRA'], 'DEFAULT_GENERATED' ) ) {
					$sql .= sprintf( ' DEFAULT (%s)', $column['COLUMN_DEFAULT'] );
				} else {
					$sql .= ' DEFAULT ' . $this->format_mysql_column_default( $column );
				}
			} elseif ( 'YES' === $column['IS_NULLABLE'] ) {
				$sql .= ' DEFAULT NULL';
			}

			// Handle ON UPDATE CURRENT_TIMESTAMP.
			if ( str_contains( $column['EXTRA'], 'on update CURRENT_TIMESTAMP' ) ) {
				$sql .= ' ON UPDATE CURRENT_TIMESTAMP';
			}

			if ( '' !== $column['COLUMN_COMMENT'] ) {
				$sql .= sprintf(
					' COMMENT %s',
					$this->quote_mysql_utf8_string_literal( $column['COLUMN_COMMENT'] )
				);
			}

			$rows[] = $sql;
		}

		// 7. Generate CREATE TABLE statement constraints, collect indexes.
		$format_index_column = function ( $column ) {
			$definition = $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
			if ( null !== $column['SUB_PART'] ) {
				$definition .= sprintf( '(%d)', $column['SUB_PART'] );
			}
			if ( 'D' === $column['COLLATION'] ) {
				$definition .= ' DESC';
			}
			return $definition;
		};

		foreach ( $grouped_constraints as $constraint ) {
			ksort( $constraint );
			$info = $constraint[1];

			if ( 'PRIMARY' === $info['INDEX_NAME'] ) {
				$sql  = '  PRIMARY KEY (';
				$sql .= implode( ', ', array_map( $format_index_column, $constraint ) );
				$sql .= ')';
			} else {
				$is_unique = 0 === (int) $info['NON_UNIQUE'];

				$sql  = sprintf(
					'  %s%s%sKEY ',
					$is_unique ? 'UNIQUE ' : '',
					'FULLTEXT' === $info['INDEX_TYPE'] ? 'FULLTEXT ' : '',
					'SPATIAL' === $info['INDEX_TYPE'] ? 'SPATIAL ' : ''
				);
				$sql .= $this->quote_mysql_identifier( $info['INDEX_NAME'] );
				$sql .= ' (';
				$sql .= implode( ', ', array_map( $format_index_column, $constraint ) );
				$sql .= ')';
			}

			if ( '' !== $info['INDEX_COMMENT'] ) {
				$sql .= sprintf(
					' COMMENT %s',
					$this->quote_mysql_utf8_string_literal( $info['INDEX_COMMENT'] )
				);
			}

			$rows[] = $sql;
		}

		// 8. Add foreign key constraints.
		foreach ( $referential_constraints_info as $referential_constraint ) {
			$column_names            = array();
			$referenced_column_names = array();
			foreach ( $key_column_usage_map[ $referential_constraint['CONSTRAINT_NAME'] ] as $info ) {
				$column_names[]            = $this->quote_mysql_identifier( $info[0] );
				$referenced_column_names[] = $this->quote_mysql_identifier( $info[1] );
			}
			$sql = sprintf(
				'  CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
				$this->quote_mysql_identifier( $referential_constraint['CONSTRAINT_NAME'] ),
				implode( ', ', $column_names ),
				$this->quote_mysql_identifier( $referential_constraint['REFERENCED_TABLE_NAME'] ),
				implode( ', ', $referenced_column_names )
			);
			if ( 'NO ACTION' !== $referential_constraint['DELETE_RULE'] ) {
				$sql .= sprintf( ' ON DELETE %s', $referential_constraint['DELETE_RULE'] );
			}
			if ( 'NO ACTION' !== $referential_constraint['UPDATE_RULE'] ) {
				$sql .= sprintf( ' ON UPDATE %s', $referential_constraint['UPDATE_RULE'] );
			}
			$rows[] = $sql;
		}

		// 9. Add CHECK constraints.
		foreach ( $check_constraints_info as $check_constraint ) {
			$sql    = sprintf(
				'  CONSTRAINT %s CHECK (%s)%s',
				$this->quote_mysql_identifier( $check_constraint['CONSTRAINT_NAME'] ),
				$check_constraint['CHECK_CLAUSE'],
				'NO' === $check_constraint['ENFORCED'] ? ' /*!80016 NOT ENFORCED */' : ''
			);
			$rows[] = $sql;
		}

		// 10. Compose the CREATE TABLE statement.
		$collation = $table_info['TABLE_COLLATION'];
		$charset   = substr( $collation, 0, strpos( $collation, '_' ) );

		$sql  = sprintf(
			"CREATE %sTABLE %s (\n",
			$table_is_temporary ? 'TEMPORARY ' : '',
			$this->quote_mysql_identifier( $table_name )
		);
		$sql .= implode( ",\n", $rows );
		$sql .= "\n)";
		$sql .= sprintf( ' ENGINE=%s', $table_info['ENGINE'] );

		// Add "AUTO_INCREMENT=N" if a sequence exists and has been advanced.
		if ( $has_auto_increment ) {
			try {
				$seq = (int) $this->execute_sqlite_query(
					sprintf(
						'SELECT seq FROM %s.sqlite_sequence WHERE name = ?',
						$table_is_temporary ? 'temp' : 'main'
					),
					array( $table_name )
				)->fetchColumn();
			} catch ( PDOException $e ) {
				if ( ! str_contains( $e->getMessage(), 'no such table' ) ) {
					throw $e;
				}
				$seq = 0;
			}
			if ( $seq > 0 ) {
				$sql .= sprintf( ' AUTO_INCREMENT=%d', $seq + 1 );
			}
		}

		$sql .= sprintf( ' DEFAULT CHARSET=%s', $charset );
		$sql .= sprintf( ' COLLATE=%s', $collation );
		if ( '' !== $table_info['TABLE_COMMENT'] ) {
			$sql .= sprintf(
				' COMMENT=%s',
				$this->quote_mysql_utf8_string_literal( $table_info['TABLE_COMMENT'] )
			);
		}
		return $sql;
	}

	/**
	 * Get an unique SQLite index name from a MySQL table name and index name.
	 *
	 * @param string $table_name The MySQL table name.
	 * @param string $index_name The MySQL index name.
	 * @return string            The SQLite index name.
	 */
	private function get_sqlite_index_name( string $mysql_table_name, string $mysql_index_name ): string {
		// Prefix the original index name with the table name.
		// This is to avoid conflicting index names in SQLite.
		return $mysql_table_name . '__' . $mysql_index_name;
	}

	/**
	 * Get an internal savepoint name.
	 *
	 * Internal savepoints are used to emulate MySQL transactions that are run
	 * inside a wrapping SQLite transaction, as transactions can't be nested.
	 *
	 * @param  string $name The name of the savepoint.
	 * @return string       The internal savepoint name.
	 */
	private function get_internal_savepoint_name( string $name ): string {
		return sprintf( '%ssavepoint_%s', self::RESERVED_PREFIX, $name );
	}

	/**
	 * Format a column default value as a literal for an SQLite CREATE TABLE statement.
	 *
	 * @param  array  $column The information schema column data.
	 * @return string         The default value as an SQLite literal.
	 */
	private function format_sqlite_column_default( array $column ): string {
		if ( 'bit' === $column['DATA_TYPE'] ) {
			// BIT defaults are stored as b'...' literals; reformat as an integer.
			return (string) bindec( substr( $column['COLUMN_DEFAULT'], 2, -1 ) );
		}

		return $this->quote_sqlite_value( $column['COLUMN_DEFAULT'] );
	}

	/**
	 * Format a column default value as a literal for a MySQL SHOW CREATE TABLE statement.
	 *
	 * @param  array  $column The information schema column data.
	 * @return string         The default value as a MySQL literal.
	 */
	private function format_mysql_column_default( array $column ): string {
		// BIT defaults are already stored as b'...' literals.
		if ( 'bit' === $column['DATA_TYPE'] ) {
			return $column['COLUMN_DEFAULT'];
		}

		return $this->quote_mysql_utf8_string_literal( $column['COLUMN_DEFAULT'] );
	}

	/**
	 * Unquote a quoted SQLite identifier.
	 *
	 * Remove bounding quotes and replace escaped quotes with their values.
	 *
	 * @param  string $quoted_identifier The quoted identifier value.
	 * @return string                    The unquoted identifier value.
	 */
	private function unquote_sqlite_identifier( string $quoted_identifier ): string {
		$first_byte = $quoted_identifier[0] ?? null;
		if ( '"' === $first_byte || '`' === $first_byte ) {
			$unquoted = substr( $quoted_identifier, 1, -1 );
			return str_replace( $first_byte . $first_byte, $first_byte, $unquoted );
		}
		return $quoted_identifier;
	}

	/**
	 * Quote an identifier for use in an SQLite query.
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	private function quote_sqlite_identifier( string $unquoted_identifier ): string {
		return $this->connection->quote_identifier( $unquoted_identifier );
	}

	/**
	 * Quote a value for use in an SQLite query.
	 *
	 * @param  mixed  $value The value to quote.
	 * @return string        The quoted value.
	 */
	private function quote_sqlite_value( $value ): string {
		return $this->connection->quote( $value );
	}

	/**
	 * Quote an identifier for use in a MySQL query.
	 *
	 * Wrap the identifier in backticks and escape backtick values within.
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	private function quote_mysql_identifier( string $unquoted_identifier ): string {
		return '`' . str_replace( '`', '``', $unquoted_identifier ) . '`';
	}

	/**
	 * Format a MySQL UTF-8 string literal for output in a CREATE TABLE statement.
	 *
	 * We expect UTF-8 strings coming from SQLite. The only characters that must
	 * be escaped in a single-quoted string for a UTF-8 MySQL dump are ' and \.
	 *
	 * MySQL SHOW CREATE TABLE command additionally escapes "\0", "\n", and "\r",
	 * for the mysql CLI, logs, and better readability. This applies to column
	 * default values, and table, column, and index comments. Other values, such
	 * as identifiers, don't have these extra characters escaped in the output.
	 *
	 * See:
	 *  - https://github.com/mysql/mysql-server/blob/ff05628a530696bc6851ba6540ac250c7a059aa7/sql/sql_show.cc#L1799
	 *  - https://github.com/mysql/mysql-server/blob/ff05628a530696bc6851ba6540ac250c7a059aa7/sql/table.cc#L3525
	 *
	 * Unfortunately, SQLite doesn't validate the UTF-8 encoding, so other byte
	 * sequences may come from SQLite as well: https://www.sqlite.org/invalidutf.html
	 *
	 * TODO: We may consider stripping invalid UTF-8 characters, but that's likely
	 *       to be a bigger project, as these can appear also in other contexts.
	 *
	 * @param  string $utf8_literal The UTF-8 string literal to escape.
	 * @return string               The escaped string literal.
	 */
	private function quote_mysql_utf8_string_literal( string $utf8_literal ): string {
		/*
		 * We can't use "addcslashes()" here, because it has an unusual handling
		 * of the ASCII NULL character, escaping it to "\000" instead of "\0".
		 *
		 * It is important to use "strtr()" and not "str_replace()", because
		 * "str_replace()" applies replacements one after another, modifying
		 * intermediate changes rather than just the original string:
		 *
		 *   - str_replace( [ 'a', 'b' ], [ 'b', 'c' ], 'ab' ); // 'cc' (bad)
		 *   - strtr( 'ab', [ 'a' => 'b', 'b' => 'c' ] );       // 'bc' (good)
		 */
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
	 * Clear the state of the driver.
	 */
	private function flush(): void {
		$this->last_mysql_query         = '';
		$this->last_sqlite_queries      = array();
		$this->last_result_statement    = null;
		$this->last_affected_rows       = null;
		$this->last_insert_id           = '0';
		$this->last_column_meta         = array();
		$this->is_readonly              = false;
		$this->wrapper_transaction_type = null;
		$this->user_defined_functions->flush();
	}

	/**
	 * Create a PDO SQLite statement from the specified columns and rows.
	 *
	 * Some emulated MySQL queries don't have an SQLite counterpart and their
	 * result data may be generated without a corresponding SQLite statement.
	 * In such cases, we can generate a simple SQLite SELECT query that will
	 * provide us with the PDOStatement API for the given column and row data.
	 *
	 * @param  array $columns The columns of the result set.
	 * @param  array $rows    The rows of the result set.
	 * @return PDOStatement   The corresponding PDO SQLite statement.
	 */
	private function create_result_statement_from_data( array $columns, array $rows ): PDOStatement {
		/*
		 * The statement writes nothing, but PDO SQLite can report the row count
		 * of the last write for it. Default the affected rows to 0.
		 */
		$this->last_affected_rows = $this->last_affected_rows ?? 0;

		$pdo = $this->connection->get_pdo();

		/*
		 * With 0 columns, we need a PDO statement with no columns and no rows.
		 *
		 * An unknown pragma has no columns, needs no table, takes no locks,
		 * and SQLite ignores it.
		 */
		if ( 0 === count( $columns ) ) {
			return $pdo->query( 'PRAGMA ' . self::RESERVED_PREFIX . 'empty_result' );
		}

		/*
		 * Create an SQLite statement that returns the specified columns and rows.
		 * This can be done using a SELECT statement in the following form:
		 *
		 *   -- A dummy header row to assign correct column names.
		 *   SELECT NULL AS `col1`, NULL AS `col2`, ... WHERE FALSE
		 *
		 *   UNION ALL
		 *
		 *   -- The actual data rows.
		 *   VALUES
		 *     (val11, val12, ...),
		 *     (val21, val22, ...),
		 *     ...
		 */

		// Construct column header row ("SELECT <column-list> WHERE FALSE").
		$query = 'SELECT ';
		foreach ( $columns as $i => $column ) {
			$query .= $i > 0 ? ', ' : '';
			$query .= 'NULL AS ' . $pdo->quote( $column );
		}
		$query .= ' WHERE FALSE';

		// UNION ALL
		if ( count( $rows ) > 0 ) {
			$query .= ' UNION ALL VALUES ';
		}

		// Construct data rows ("VALUES <row-list>").
		foreach ( $rows as $i => $row ) {
			$query .= $i > 0 ? ', ' : '';
			$query .= '(';
			foreach ( array_values( $row ) as $j => $value ) {
				$query .= $j > 0 ? ', ' : '';
				if ( null === $value ) {
					$query .= 'NULL';
				} elseif ( is_string( $value ) && strpos( $value, "\0" ) !== false ) {
					// Handle null characters; see self::translate_string_literal().
					$query .= sprintf( "CAST(x'%s' AS TEXT)", bin2hex( $value ) );
				} elseif ( is_string( $value ) ) {
					$query .= $pdo->quote( $value );
				} else {
					$query .= $value;
				}
			}
			$query .= ')';
		}
		return $pdo->query( $query );
	}

	/**
	 * Create a new MySQL-on-SQLite driver exception.
	 *
	 * @param string         $message    The exception message.
	 * @param int|string     $code       The exception code. For PDO errors, a string representing SQLSTATE.
	 * @param Throwable|null $previous   The previous exception.
	 * @param array|null     $error_info PDO-style error information.
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function new_driver_exception(
		string $message,
		$code = 0,
		?Throwable $previous = null,
		?array $error_info = null
	): WP_MySQL_On_SQLite_Exception {
		return new WP_MySQL_On_SQLite_Exception( $this, $message, $code, $previous, $error_info );
	}

	/**
	 * Create a new invalid input exception.
	 *
	 * This exception can be used to mark cases that should never occur according
	 * to the MySQL grammar. It may serve as an assertion that should never fail.
	 *
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function new_invalid_input_exception(): WP_MySQL_On_SQLite_Exception {
		return new WP_MySQL_On_SQLite_Exception( $this, 'MySQL query syntax error.' );
	}

	/**
	 * Create a new not supported exception.
	 *
	 * This exception can be used to mark MySQL constructs that are not supported.
	 *
	 * @param string $cause The cause, indicating which construct is not supported.
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function new_not_supported_exception( string $cause ): WP_MySQL_On_SQLite_Exception {
		return new WP_MySQL_On_SQLite_Exception(
			$this,
			sprintf( 'MySQL query not supported. Cause: %s', $cause )
		);
	}

	/**
	 * Create a MySQL-compatible table-not-found exception.
	 *
	 * @param  string         $table_name The missing table name.
	 * @param  Throwable|null $previous   The previous exception.
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function new_table_not_found_exception(
		string $table_name,
		?Throwable $previous = null
	): WP_MySQL_On_SQLite_Exception {
		$driver_message = sprintf( "Table '%s' doesn't exist", $table_name );
		return $this->new_driver_exception(
			'SQLSTATE[42S02]: Base table or view not found: 1146 ' . $driver_message,
			'42S02',
			$previous,
			array( '42S02', 1146, $driver_message )
		);
	}

	/**
	 * Create a MySQL-compatible savepoint-not-found exception.
	 *
	 * @param  string $savepoint_name The missing savepoint name, as an SQLite identifier.
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function new_savepoint_does_not_exist_exception( string $savepoint_name ): WP_MySQL_On_SQLite_Exception {
		$driver_message = sprintf(
			'SAVEPOINT %s does not exist',
			$this->unquote_sqlite_identifier( $savepoint_name )
		);
		return $this->new_driver_exception(
			'SQLSTATE[42000]: Syntax error or access violation: 1305 ' . $driver_message,
			'42000',
			null,
			array( '42000', 1305, $driver_message )
		);
	}

	/**
	 * Create a MySQL-compatible exception for an invalid SQL mode value.
	 *
	 * @param  mixed $value The invalid SQL mode value.
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function new_invalid_sql_mode_exception( $value ): WP_MySQL_On_SQLite_Exception {
		return $this->new_driver_exception(
			sprintf(
				"SQLSTATE[42000]: Syntax error or access violation: 1231 Variable 'sql_mode' can't be set to the value of '%s'",
				null === $value ? 'NULL' : (string) $value
			),
			'42000'
		);
	}

	/**
	 * Create a new access denied exception for the information schema database.
	 *
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function new_access_denied_to_information_schema_exception(): WP_MySQL_On_SQLite_Exception {
		return $this->new_driver_exception(
			"Access denied for user 'root'@'%' to database 'information_schema'",
			'42000'
		);
	}

	/**
	 * Convert a SQLite PDO exception to a MySQL-on-SQLite driver exception.
	 *
	 * @param  PDOException $e The SQLite PDO exception.
	 * @return WP_MySQL_On_SQLite_Exception
	 */
	private function convert_sqlite_exception( PDOException $e ): WP_MySQL_On_SQLite_Exception {
		$error_info    = is_array( $e->errorInfo ) ? $e->errorInfo : array();
		$sqlstate      = $error_info[0] ?? null;
		$error_message = $error_info[2] ?? null;

		if ( 'HY000' === $sqlstate && 0 === strpos( $error_message, 'no such table: ' ) ) {
			$table_name = substr( $error_message, strlen( 'no such table: ' ) );
			return $this->new_table_not_found_exception( $table_name, $e );
		}

		return $this->new_driver_exception( $e->getMessage(), $e->getCode(), $e );
	}

	/**
	 * Convert an information schema exception to a MySQL-like driver exception.
	 *
	 * This method is used to convert some information schema exceptions to the
	 * corresponding MySQL exceptions, as they would be generated by PDO MySQL.
	 * This conversion mirrors PDO's error messages and SQLSTATE codes.
	 *
	 * @param  WP_SQLite_Information_Schema_Exception $e The information schema exception.
	 * @return Throwable                                 The converted exception, or the original
	 *                                                   exception if no conversion was done.
	 */
	private function convert_information_schema_exception( WP_SQLite_Information_Schema_Exception $e ): Throwable {
		switch ( $e->get_type() ) {
			case WP_SQLite_Information_Schema_Exception::TYPE_DUPLICATE_TABLE_NAME:
				$driver_message = sprintf( "Table '%s' already exists", $e->get_data()['table_name'] );
				return $this->new_driver_exception(
					'SQLSTATE[42S01]: Base table or view already exists: 1050 ' . $driver_message,
					'42S01',
					null,
					array( '42S01', 1050, $driver_message )
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_DUPLICATE_COLUMN_NAME:
				$driver_message = sprintf( "Duplicate column name '%s'", $e->get_data()['column_name'] );
				return $this->new_driver_exception(
					'SQLSTATE[42S21]: Column already exists: 1060 ' . $driver_message,
					'42S21',
					null,
					array( '42S21', 1060, $driver_message )
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_COLUMN_NOT_FOUND:
				$driver_message = sprintf( "Unknown column '%s' in '%s'", $e->get_data()['column_name'], $e->get_data()['table_name'] );
				return $this->new_driver_exception(
					'SQLSTATE[42S22]: Column not found: 1054 ' . $driver_message,
					'42S22',
					null,
					array( '42S22', 1054, $driver_message )
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_DUPLICATE_KEY_NAME:
				$driver_message = sprintf( "Duplicate key name '%s'", $e->get_data()['key_name'] );
				return $this->new_driver_exception(
					'SQLSTATE[42000]: Syntax error or access violation: 1061 ' . $driver_message,
					'42000',
					null,
					array( '42000', 1061, $driver_message )
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_KEY_COLUMN_NOT_FOUND:
				$driver_message = sprintf( "Key column '%s' doesn't exist in table", $e->get_data()['column_name'] );
				return $this->new_driver_exception(
					'SQLSTATE[42000]: Syntax error or access violation: 1072 ' . $driver_message,
					'42000',
					null,
					array( '42000', 1072, $driver_message )
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_INVALID_DEFAULT_VALUE:
				$driver_message = sprintf( "Invalid default value for '%s'", $e->get_data()['column_name'] );
				return $this->new_driver_exception(
					'SQLSTATE[42000]: Syntax error or access violation: 1067 ' . $driver_message,
					'42000',
					null,
					array( '42000', 1067, $driver_message )
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_CONSTRAINT_DOES_NOT_EXIST:
				$driver_message = sprintf( "Constraint '%s' does not exist.", $e->get_data()['name'] );
				return $this->new_driver_exception(
					'SQLSTATE[HY000]: General error: 3940 ' . $driver_message,
					'HY000',
					null,
					array( 'HY000', 3940, $driver_message )
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_MULTIPLE_CONSTRAINTS_WITH_NAME:
				$driver_message = sprintf(
					"Table has multiple constraints with the name '%s'. Please use constraint specific 'DROP' clause.",
					$e->get_data()['name']
				);
				return $this->new_driver_exception(
					'SQLSTATE[HY000]: General error: 3939 ' . $driver_message,
					'HY000',
					null,
					array( 'HY000', 3939, $driver_message )
				);
			default:
				return $e;
		}
	}
}
