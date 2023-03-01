<?php
/**
 * This file is a port of the Lexer & Tokens_List classes from the PHPMyAdmin/sql-parser library.
 *
 * @package wp-sqlite-integration
 * @see https://github.com/phpmyadmin/sql-parser
 */

/**
 * Performs lexical analysis over a SQL statement and splits it in multiple tokens.
 */
class WP_SQLite_Lexer {

	/**
	 * The maximum length of a keyword.
	 */
	const KEYWORD_MAX_LENGTH = 30;

	/**
	 * The maximum length of a label.
	 *
	 * Ref: https://dev.mysql.com/doc/refman/5.7/en/statement-labels.html
	 */
	const LABEL_MAX_LENGTH = 16;

	/**
	 * The maximum length of an operator.
	 */
	const OPERATOR_MAX_LENGTH = 4;

	/**
	 * A list of methods that are used in lexing the SQL query.
	 *
	 * @var string[]
	 */
	public static $parser_methods = array(
		// It is best to put the parsers in order of their complexity
		// (ascending) and their occurrence rate (descending).
		//
		// Conflicts:
		//
		// 1. `parse_delimiter`, `parse_unknown`, `parse_keyword`, `parse_number`
		// They fight over delimiter. The delimiter may be a keyword, a
		// number or almost any character which makes the delimiter one of
		// the first tokens that must be parsed.
		//
		// 1. `parse_number` and `parse_operator`
		// They fight over `+` and `-`.
		//
		// 2. `parse_comment` and `parse_operator`
		// They fight over `/` (as in ```/*comment*/``` or ```a / b```)
		//
		// 3. `parse_bool` and `parse_keyword`
		// They fight over `TRUE` and `FALSE`.
		//
		// 4. `parse_keyword` and `parse_unknown`
		// They fight over words. `parse_unknown` does not know about
		// keywords.

		'parse_delimiter',
		'parse_whitespace',
		'parse_number',
		'parse_comment',
		'parse_operator',
		'parse_bool',
		'parse_string',
		'parse_symbol',
		'parse_keyword',
		'parse_label',
		'parse_unknown',
	);


	/**
	 * A list of keywords that indicate that the function keyword
	 * is not used as a function.
	 *
	 * @var string[]
	 */
	public $keyword_name_indicators = array(
		'FROM',
		'SET',
		'WHERE',
	);

	/**
	 * A list of operators that indicate that the function keyword
	 * is not used as a function.
	 *
	 * @var string[]
	 */
	public $operator_name_indicators = array(
		',',
		'.',
	);

	/**
	 * The string to be parsed.
	 *
	 * @var string
	 */
	public $str = '';

	/**
	 * The length of `$str`.
	 *
	 * By storing its length, a lot of time is saved, because parsing methods
	 * would call `strlen` everytime.
	 *
	 * @var int
	 */
	public $string_length = 0;

	/**
	 * The index of the last parsed character.
	 *
	 * @var int
	 */
	public $last = 0;

	/**
	 * The default delimiter. This is used, by default, in all new instances.
	 *
	 * @var string
	 */
	public static $default_delimiter = ';';

	/**
	 * Statements delimiter.
	 * This may change during lexing.
	 *
	 * @var string
	 */
	public $delimiter;

	/**
	 * The length of the delimiter.
	 *
	 * Because `parse_delimiter` can be called a lot, it would perform a lot of
	 * calls to `strlen`, which might affect performance when the delimiter is
	 * big.
	 *
	 * @var int
	 */
	public $delimiter_length;

	/**
	 * List of operators and their flags.
	 *
	 * @var array<string, int>
	 */
	public static $operators = array(

		/*
		 * Some operators (*, =) may have ambiguous flags, because they depend on
		 * the context they are being used in.
		 * For example: 1. SELECT * FROM table; # SQL specific (wildcard)
		 *                 SELECT 2 * 3;        # arithmetic
		 *              2. SELECT * FROM table WHERE foo = 'bar';
		 *                 SET @i = 0;
		 */

		// @see WP_SQLite_Token::FLAG_OPERATOR_ARITHMETIC
		'%'   => 1,
		'*'   => 1,
		'+'   => 1,
		'-'   => 1,
		'/'   => 1,

		// @see WP_SQLite_Token::FLAG_OPERATOR_LOGICAL
		'!'   => 2,
		'!='  => 2,
		'&&'  => 2,
		'<'   => 2,
		'<='  => 2,
		'<=>' => 2,
		'<>'  => 2,
		'='   => 2,
		'>'   => 2,
		'>='  => 2,
		'||'  => 2,

		// @see WP_SQLite_Token::FLAG_OPERATOR_BITWISE
		'&'   => 4,
		'<<'  => 4,
		'>>'  => 4,
		'^'   => 4,
		'|'   => 4,
		'~'   => 4,

		// @see WP_SQLite_Token::FLAG_OPERATOR_ASSIGNMENT
		':='  => 8,

		// @see WP_SQLite_Token::FLAG_OPERATOR_SQL
		'('   => 16,
		')'   => 16,
		'.'   => 16,
		','   => 16,
		';'   => 16,
	);

	/**
	 * List of keywords.
	 *
	 * The value associated to each keyword represents its flags.
	 *
	 * @see WP_SQLite_Token::FLAG_KEYWORD_RESERVED
	 *      WP_SQLite_Token::FLAG_KEYWORD_COMPOSED
	 *      WP_SQLite_Token::FLAG_KEYWORD_DATA_TYPE
	 *      ∂WP_SQLite_Token::FLAG_KEYWORD_KEY
	 *      WP_SQLite_Token::FLAG_KEYWORD_FUNCTION
	 *
	 * @var array<string,int>
	 */
	public static $keywords = array(
		'AT'                                => 1,
		'DO'                                => 1,
		'IO'                                => 1,
		'NO'                                => 1,
		'XA'                                => 1,
		'ANY'                               => 1,
		'CPU'                               => 1,
		'END'                               => 1,
		'IPC'                               => 1,
		'NDB'                               => 1,
		'NEW'                               => 1,
		'ONE'                               => 1,
		'ROW'                               => 1,
		'XID'                               => 1,
		'BOOL'                              => 1,
		'BYTE'                              => 1,
		'CODE'                              => 1,
		'CUBE'                              => 1,
		'DATA'                              => 1,
		'DISK'                              => 1,
		'ENDS'                              => 1,
		'FAST'                              => 1,
		'FILE'                              => 1,
		'FULL'                              => 1,
		'HASH'                              => 1,
		'HELP'                              => 1,
		'HOST'                              => 1,
		'LAST'                              => 1,
		'LESS'                              => 1,
		'LIST'                              => 1,
		'LOGS'                              => 1,
		'MODE'                              => 1,
		'NAME'                              => 1,
		'NEXT'                              => 1,
		'NONE'                              => 1,
		'ONLY'                              => 1,
		'OPEN'                              => 1,
		'PAGE'                              => 1,
		'PORT'                              => 1,
		'PREV'                              => 1,
		'ROWS'                              => 1,
		'SLOW'                              => 1,
		'SOME'                              => 1,
		'STOP'                              => 1,
		'THAN'                              => 1,
		'TYPE'                              => 1,
		'VIEW'                              => 1,
		'WAIT'                              => 1,
		'WORK'                              => 1,
		'X509'                              => 1,
		'AFTER'                             => 1,
		'BEGIN'                             => 1,
		'BLOCK'                             => 1,
		'BTREE'                             => 1,
		'CACHE'                             => 1,
		'CHAIN'                             => 1,
		'CLOSE'                             => 1,
		'ERROR'                             => 1,
		'EVENT'                             => 1,
		'EVERY'                             => 1,
		'FIRST'                             => 1,
		'FIXED'                             => 1,
		'FLUSH'                             => 1,
		'FOUND'                             => 1,
		'HOSTS'                             => 1,
		'LEVEL'                             => 1,
		'LOCAL'                             => 1,
		'LOCKS'                             => 1,
		'MERGE'                             => 1,
		'MUTEX'                             => 1,
		'NAMES'                             => 1,
		'NCHAR'                             => 1,
		'NEVER'                             => 1,
		'OWNER'                             => 1,
		'PHASE'                             => 1,
		'PROXY'                             => 1,
		'QUERY'                             => 1,
		'QUICK'                             => 1,
		'RELAY'                             => 1,
		'RESET'                             => 1,
		'RTREE'                             => 1,
		'SHARE'                             => 1,
		'SLAVE'                             => 1,
		'START'                             => 1,
		'SUPER'                             => 1,
		'SWAPS'                             => 1,
		'TYPES'                             => 1,
		'UNTIL'                             => 1,
		'VALUE'                             => 1,
		'ACTION'                            => 1,
		'ALWAYS'                            => 1,
		'BACKUP'                            => 1,
		'BINLOG'                            => 1,
		'CIPHER'                            => 1,
		'CLIENT'                            => 1,
		'COMMIT'                            => 1,
		'ENABLE'                            => 1,
		'ENGINE'                            => 1,
		'ERRORS'                            => 1,
		'ESCAPE'                            => 1,
		'EVENTS'                            => 1,
		'EXPIRE'                            => 1,
		'EXPORT'                            => 1,
		'FAULTS'                            => 1,
		'FIELDS'                            => 1,
		'FILTER'                            => 1,
		'GLOBAL'                            => 1,
		'GRANTS'                            => 1,
		'IMPORT'                            => 1,
		'ISSUER'                            => 1,
		'LEAVES'                            => 1,
		'MASTER'                            => 1,
		'MEDIUM'                            => 1,
		'MEMORY'                            => 1,
		'MODIFY'                            => 1,
		'NUMBER'                            => 1,
		'OFFSET'                            => 1,
		'PARSER'                            => 1,
		'PLUGIN'                            => 1,
		'RELOAD'                            => 1,
		'REMOVE'                            => 1,
		'REPAIR'                            => 1,
		'RESUME'                            => 1,
		'ROLLUP'                            => 1,
		'SERVER'                            => 1,
		'SIGNED'                            => 1,
		'SIMPLE'                            => 1,
		'SOCKET'                            => 1,
		'SONAME'                            => 1,
		'SOUNDS'                            => 1,
		'SOURCE'                            => 1,
		'STARTS'                            => 1,
		'STATUS'                            => 1,
		'STRING'                            => 1,
		'TABLES'                            => 1,
		'ACCOUNT'                           => 1,
		'ANALYSE'                           => 1,
		'CHANGED'                           => 1,
		'CHANNEL'                           => 1,
		'COLUMNS'                           => 1,
		'COMMENT'                           => 1,
		'COMPACT'                           => 1,
		'CONTEXT'                           => 1,
		'CURRENT'                           => 1,
		'DEFINER'                           => 1,
		'DISABLE'                           => 1,
		'DISCARD'                           => 1,
		'DYNAMIC'                           => 1,
		'ENGINES'                           => 1,
		'EXECUTE'                           => 1,
		'FOLLOWS'                           => 1,
		'GENERAL'                           => 1,
		'HANDLER'                           => 1,
		'INDEXES'                           => 1,
		'INSTALL'                           => 1,
		'INVOKER'                           => 1,
		'LOGFILE'                           => 1,
		'MIGRATE'                           => 1,
		'NO_WAIT'                           => 1,
		'OPTIONS'                           => 1,
		'PARTIAL'                           => 1,
		'PERSIST'                           => 1,
		'PLUGINS'                           => 1,
		'PREPARE'                           => 1,
		'PROFILE'                           => 1,
		'REBUILD'                           => 1,
		'RECOVER'                           => 1,
		'RESTORE'                           => 1,
		'RETURNS'                           => 1,
		'ROUTINE'                           => 1,
		'SESSION'                           => 1,
		'STACKED'                           => 1,
		'STORAGE'                           => 1,
		'SUBJECT'                           => 1,
		'SUSPEND'                           => 1,
		'UNICODE'                           => 1,
		'UNKNOWN'                           => 1,
		'UPGRADE'                           => 1,
		'USE_FRM'                           => 1,
		'WITHOUT'                           => 1,
		'WRAPPER'                           => 1,
		'CASCADED'                          => 1,
		'CHECKSUM'                          => 1,
		'DATAFILE'                          => 1,
		'DUMPFILE'                          => 1,
		'EXCHANGE'                          => 1,
		'EXTENDED'                          => 1,
		'FUNCTION'                          => 1,
		'LANGUAGE'                          => 1,
		'MAX_ROWS'                          => 1,
		'MAX_SIZE'                          => 1,
		'MIN_ROWS'                          => 1,
		'NATIONAL'                          => 1,
		'NVARCHAR'                          => 1,
		'PRECEDES'                          => 1,
		'PRESERVE'                          => 1,
		'PROFILES'                          => 1,
		'REDOFILE'                          => 1,
		'RELAYLOG'                          => 1,
		'ROLLBACK'                          => 1,
		'SCHEDULE'                          => 1,
		'SECURITY'                          => 1,
		'SEQUENCE'                          => 1,
		'SHUTDOWN'                          => 1,
		'SNAPSHOT'                          => 1,
		'SWITCHES'                          => 1,
		'TRIGGERS'                          => 1,
		'UNDOFILE'                          => 1,
		'WARNINGS'                          => 1,
		'AGGREGATE'                         => 1,
		'ALGORITHM'                         => 1,
		'COMMITTED'                         => 1,
		'DIRECTORY'                         => 1,
		'DUPLICATE'                         => 1,
		'EXPANSION'                         => 1,
		'INVISIBLE'                         => 1,
		'IO_THREAD'                         => 1,
		'ISOLATION'                         => 1,
		'NODEGROUP'                         => 1,
		'PACK_KEYS'                         => 1,
		'READ_ONLY'                         => 1,
		'REDUNDANT'                         => 1,
		'SAVEPOINT'                         => 1,
		'SQL_CACHE'                         => 1,
		'TEMPORARY'                         => 1,
		'TEMPTABLE'                         => 1,
		'UNDEFINED'                         => 1,
		'UNINSTALL'                         => 1,
		'VARIABLES'                         => 1,
		'COMPLETION'                        => 1,
		'COMPRESSED'                        => 1,
		'CONCURRENT'                        => 1,
		'CONNECTION'                        => 1,
		'CONSISTENT'                        => 1,
		'DEALLOCATE'                        => 1,
		'IDENTIFIED'                        => 1,
		'MASTER_SSL'                        => 1,
		'NDBCLUSTER'                        => 1,
		'PARTITIONS'                        => 1,
		'PERSISTENT'                        => 1,
		'PLUGIN_DIR'                        => 1,
		'PRIVILEGES'                        => 1,
		'REORGANIZE'                        => 1,
		'REPEATABLE'                        => 1,
		'ROW_FORMAT'                        => 1,
		'SQL_THREAD'                        => 1,
		'TABLESPACE'                        => 1,
		'TABLE_NAME'                        => 1,
		'VALIDATION'                        => 1,
		'COLUMN_NAME'                       => 1,
		'COMPRESSION'                       => 1,
		'CURSOR_NAME'                       => 1,
		'DIAGNOSTICS'                       => 1,
		'EXTENT_SIZE'                       => 1,
		'MASTER_HOST'                       => 1,
		'MASTER_PORT'                       => 1,
		'MASTER_USER'                       => 1,
		'MYSQL_ERRNO'                       => 1,
		'NONBLOCKING'                       => 1,
		'PROCESSLIST'                       => 1,
		'REPLICATION'                       => 1,
		'SCHEMA_NAME'                       => 1,
		'SQL_TSI_DAY'                       => 1,
		'TRANSACTION'                       => 1,
		'UNCOMMITTED'                       => 1,
		'CATALOG_NAME'                      => 1,
		'CLASS_ORIGIN'                      => 1,
		'DEFAULT_AUTH'                      => 1,
		'DES_KEY_FILE'                      => 1,
		'INITIAL_SIZE'                      => 1,
		'MASTER_DELAY'                      => 1,
		'MESSAGE_TEXT'                      => 1,
		'PARTITIONING'                      => 1,
		'PERSIST_ONLY'                      => 1,
		'RELAY_THREAD'                      => 1,
		'SERIALIZABLE'                      => 1,
		'SQL_NO_CACHE'                      => 1,
		'SQL_TSI_HOUR'                      => 1,
		'SQL_TSI_WEEK'                      => 1,
		'SQL_TSI_YEAR'                      => 1,
		'SUBPARTITION'                      => 1,
		'COLUMN_FORMAT'                     => 1,
		'INSERT_METHOD'                     => 1,
		'MASTER_SSL_CA'                     => 1,
		'RELAY_LOG_POS'                     => 1,
		'SQL_TSI_MONTH'                     => 1,
		'SUBPARTITIONS'                     => 1,
		'AUTO_INCREMENT'                    => 1,
		'AVG_ROW_LENGTH'                    => 1,
		'KEY_BLOCK_SIZE'                    => 1,
		'MASTER_LOG_POS'                    => 1,
		'MASTER_SSL_CRL'                    => 1,
		'MASTER_SSL_KEY'                    => 1,
		'RELAY_LOG_FILE'                    => 1,
		'SQL_TSI_MINUTE'                    => 1,
		'SQL_TSI_SECOND'                    => 1,
		'TABLE_CHECKSUM'                    => 1,
		'USER_RESOURCES'                    => 1,
		'AUTOEXTEND_SIZE'                   => 1,
		'CONSTRAINT_NAME'                   => 1,
		'DELAY_KEY_WRITE'                   => 1,
		'FILE_BLOCK_SIZE'                   => 1,
		'MASTER_LOG_FILE'                   => 1,
		'MASTER_PASSWORD'                   => 1,
		'MASTER_SSL_CERT'                   => 1,
		'PARSE_GCOL_EXPR'                   => 1,
		'REPLICATE_DO_DB'                   => 1,
		'SQL_AFTER_GTIDS'                   => 1,
		'SQL_TSI_QUARTER'                   => 1,
		'SUBCLASS_ORIGIN'                   => 1,
		'MASTER_SERVER_ID'                  => 1,
		'REDO_BUFFER_SIZE'                  => 1,
		'SQL_BEFORE_GTIDS'                  => 1,
		'STATS_PERSISTENT'                  => 1,
		'UNDO_BUFFER_SIZE'                  => 1,
		'CONSTRAINT_SCHEMA'                 => 1,
		'GROUP_REPLICATION'                 => 1,
		'IGNORE_SERVER_IDS'                 => 1,
		'MASTER_SSL_CAPATH'                 => 1,
		'MASTER_SSL_CIPHER'                 => 1,
		'RETURNED_SQLSTATE'                 => 1,
		'SQL_BUFFER_RESULT'                 => 1,
		'STATS_AUTO_RECALC'                 => 1,
		'CONSTRAINT_CATALOG'                => 1,
		'MASTER_RETRY_COUNT'                => 1,
		'MASTER_SSL_CRLPATH'                => 1,
		'MAX_STATEMENT_TIME'                => 1,
		'REPLICATE_DO_TABLE'                => 1,
		'SQL_AFTER_MTS_GAPS'                => 1,
		'STATS_SAMPLE_PAGES'                => 1,
		'REPLICATE_IGNORE_DB'               => 1,
		'MASTER_AUTO_POSITION'              => 1,
		'MASTER_CONNECT_RETRY'              => 1,
		'MAX_QUERIES_PER_HOUR'              => 1,
		'MAX_UPDATES_PER_HOUR'              => 1,
		'MAX_USER_CONNECTIONS'              => 1,
		'REPLICATE_REWRITE_DB'              => 1,
		'REPLICATE_IGNORE_TABLE'            => 1,
		'MASTER_HEARTBEAT_PERIOD'           => 1,
		'REPLICATE_WILD_DO_TABLE'           => 1,
		'MAX_CONNECTIONS_PER_HOUR'          => 1,
		'REPLICATE_WILD_IGNORE_TABLE'       => 1,

		'AS'                                => 3,
		'BY'                                => 3,
		'IS'                                => 3,
		'ON'                                => 3,
		'OR'                                => 3,
		'TO'                                => 3,
		'ADD'                               => 3,
		'ALL'                               => 3,
		'AND'                               => 3,
		'ASC'                               => 3,
		'DEC'                               => 3,
		'DIV'                               => 3,
		'FOR'                               => 3,
		'GET'                               => 3,
		'NOT'                               => 3,
		'OUT'                               => 3,
		'SQL'                               => 3,
		'SSL'                               => 3,
		'USE'                               => 3,
		'XOR'                               => 3,
		'BOTH'                              => 3,
		'CALL'                              => 3,
		'CASE'                              => 3,
		'DESC'                              => 3,
		'DROP'                              => 3,
		'DUAL'                              => 3,
		'EACH'                              => 3,
		'ELSE'                              => 3,
		'EXIT'                              => 3,
		'FROM'                              => 3,
		'INT1'                              => 3,
		'INT2'                              => 3,
		'INT3'                              => 3,
		'INT4'                              => 3,
		'INT8'                              => 3,
		'INTO'                              => 3,
		'JOIN'                              => 3,
		'KEYS'                              => 3,
		'KILL'                              => 3,
		'LIKE'                              => 3,
		'LOAD'                              => 3,
		'LOCK'                              => 3,
		'LONG'                              => 3,
		'LOOP'                              => 3,
		'NULL'                              => 3,
		'OVER'                              => 3,
		'READ'                              => 3,
		'SHOW'                              => 3,
		'THEN'                              => 3,
		'TRUE'                              => 3,
		'UNDO'                              => 3,
		'WHEN'                              => 3,
		'WITH'                              => 3,
		'ALTER'                             => 3,
		'CHECK'                             => 3,
		'CROSS'                             => 3,
		'FALSE'                             => 3,
		'FETCH'                             => 3,
		'FORCE'                             => 3,
		'GRANT'                             => 3,
		'GROUP'                             => 3,
		'INNER'                             => 3,
		'INOUT'                             => 3,
		'LEAVE'                             => 3,
		'LIMIT'                             => 3,
		'LINES'                             => 3,
		'ORDER'                             => 3,
		'OUTER'                             => 3,
		'PURGE'                             => 3,
		'RANGE'                             => 3,
		'READS'                             => 3,
		'RLIKE'                             => 3,
		'TABLE'                             => 3,
		'UNION'                             => 3,
		'USAGE'                             => 3,
		'USING'                             => 3,
		'WHERE'                             => 3,
		'WHILE'                             => 3,
		'WRITE'                             => 3,
		'BEFORE'                            => 3,
		'CHANGE'                            => 3,
		'COLUMN'                            => 3,
		'CREATE'                            => 3,
		'CURSOR'                            => 3,
		'DELETE'                            => 3,
		'ELSEIF'                            => 3,
		'EXCEPT'                            => 3,
		'FLOAT4'                            => 3,
		'FLOAT8'                            => 3,
		'HAVING'                            => 3,
		'IGNORE'                            => 3,
		'INFILE'                            => 3,
		'LINEAR'                            => 3,
		'OPTION'                            => 3,
		'REGEXP'                            => 3,
		'RENAME'                            => 3,
		'RETURN'                            => 3,
		'REVOKE'                            => 3,
		'SELECT'                            => 3,
		'SIGNAL'                            => 3,
		'STORED'                            => 3,
		'UNLOCK'                            => 3,
		'UPDATE'                            => 3,
		'ANALYZE'                           => 3,
		'BETWEEN'                           => 3,
		'CASCADE'                           => 3,
		'COLLATE'                           => 3,
		'DECLARE'                           => 3,
		'DELAYED'                           => 3,
		'ESCAPED'                           => 3,
		'EXPLAIN'                           => 3,
		'FOREIGN'                           => 3,
		'ITERATE'                           => 3,
		'LEADING'                           => 3,
		'NATURAL'                           => 3,
		'OUTFILE'                           => 3,
		'PRIMARY'                           => 3,
		'RELEASE'                           => 3,
		'REQUIRE'                           => 3,
		'SCHEMAS'                           => 3,
		'TRIGGER'                           => 3,
		'VARYING'                           => 3,
		'VIRTUAL'                           => 3,
		'CONTINUE'                          => 3,
		'DAY_HOUR'                          => 3,
		'DESCRIBE'                          => 3,
		'DISTINCT'                          => 3,
		'ENCLOSED'                          => 3,
		'MAXVALUE'                          => 3,
		'MODIFIES'                          => 3,
		'OPTIMIZE'                          => 3,
		'RESIGNAL'                          => 3,
		'RESTRICT'                          => 3,
		'SPECIFIC'                          => 3,
		'SQLSTATE'                          => 3,
		'STARTING'                          => 3,
		'TRAILING'                          => 3,
		'UNSIGNED'                          => 3,
		'ZEROFILL'                          => 3,
		'CONDITION'                         => 3,
		'DATABASES'                         => 3,
		'GENERATED'                         => 3,
		'INTERSECT'                         => 3,
		'MIDDLEINT'                         => 3,
		'PARTITION'                         => 3,
		'PRECISION'                         => 3,
		'PROCEDURE'                         => 3,
		'RECURSIVE'                         => 3,
		'SENSITIVE'                         => 3,
		'SEPARATOR'                         => 3,
		'ACCESSIBLE'                        => 3,
		'ASENSITIVE'                        => 3,
		'CONSTRAINT'                        => 3,
		'DAY_MINUTE'                        => 3,
		'DAY_SECOND'                        => 3,
		'OPTIONALLY'                        => 3,
		'READ_WRITE'                        => 3,
		'REFERENCES'                        => 3,
		'SQLWARNING'                        => 3,
		'TERMINATED'                        => 3,
		'YEAR_MONTH'                        => 3,
		'DISTINCTROW'                       => 3,
		'HOUR_MINUTE'                       => 3,
		'HOUR_SECOND'                       => 3,
		'INSENSITIVE'                       => 3,
		'MASTER_BIND'                       => 3,
		'LOW_PRIORITY'                      => 3,
		'SQLEXCEPTION'                      => 3,
		'VARCHARACTER'                      => 3,
		'DETERMINISTIC'                     => 3,
		'HIGH_PRIORITY'                     => 3,
		'MINUTE_SECOND'                     => 3,
		'STRAIGHT_JOIN'                     => 3,
		'IO_AFTER_GTIDS'                    => 3,
		'SQL_BIG_RESULT'                    => 3,
		'DAY_MICROSECOND'                   => 3,
		'IO_BEFORE_GTIDS'                   => 3,
		'OPTIMIZER_COSTS'                   => 3,
		'HOUR_MICROSECOND'                  => 3,
		'SQL_SMALL_RESULT'                  => 3,
		'MINUTE_MICROSECOND'                => 3,
		'NO_WRITE_TO_BINLOG'                => 3,
		'SECOND_MICROSECOND'                => 3,
		'SQL_CALC_FOUND_ROWS'               => 3,
		'MASTER_SSL_VERIFY_SERVER_CERT'     => 3,

		'NO SQL'                            => 7,
		'GROUP BY'                          => 7,
		'NOT NULL'                          => 7,
		'ORDER BY'                          => 7,
		'SET NULL'                          => 7,
		'AND CHAIN'                         => 7,
		'FULL JOIN'                         => 7,
		'IF EXISTS'                         => 7,
		'LEFT JOIN'                         => 7,
		'LESS THAN'                         => 7,
		'LOAD DATA'                         => 7,
		'NO ACTION'                         => 7,
		'ON DELETE'                         => 7,
		'ON UPDATE'                         => 7,
		'UNION ALL'                         => 7,
		'CROSS JOIN'                        => 7,
		'ESCAPED BY'                        => 7,
		'FOR UPDATE'                        => 7,
		'INNER JOIN'                        => 7,
		'LINEAR KEY'                        => 7,
		'NO RELEASE'                        => 7,
		'OR REPLACE'                        => 7,
		'RIGHT JOIN'                        => 7,
		'ENCLOSED BY'                       => 7,
		'LINEAR HASH'                       => 7,
		'ON SCHEDULE'                       => 7,
		'STARTING BY'                       => 7,
		'AND NO CHAIN'                      => 7,
		'CONTAINS SQL'                      => 7,
		'FOR EACH ROW'                      => 7,
		'NATURAL JOIN'                      => 7,
		'PARTITION BY'                      => 7,
		'SET PASSWORD'                      => 7,
		'SQL SECURITY'                      => 7,
		'CHARACTER SET'                     => 7,
		'IF NOT EXISTS'                     => 7,
		'TERMINATED BY'                     => 7,
		'DATA DIRECTORY'                    => 7,
		'READS SQL DATA'                    => 7,
		'UNION DISTINCT'                    => 7,
		'DEFAULT CHARSET'                   => 7,
		'DEFAULT COLLATE'                   => 7,
		'FULL OUTER JOIN'                   => 7,
		'INDEX DIRECTORY'                   => 7,
		'LEFT OUTER JOIN'                   => 7,
		'SUBPARTITION BY'                   => 7,
		'DISABLE ON SLAVE'                  => 7,
		'GENERATED ALWAYS'                  => 7,
		'RIGHT OUTER JOIN'                  => 7,
		'MODIFIES SQL DATA'                 => 7,
		'NATURAL LEFT JOIN'                 => 7,
		'START TRANSACTION'                 => 7,
		'LOCK IN SHARE MODE'                => 7,
		'NATURAL RIGHT JOIN'                => 7,
		'SELECT TRANSACTION'                => 7,
		'DEFAULT CHARACTER SET'             => 7,
		'ON COMPLETION PRESERVE'            => 7,
		'NATURAL LEFT OUTER JOIN'           => 7,
		'NATURAL RIGHT OUTER JOIN'          => 7,
		'WITH CONSISTENT SNAPSHOT'          => 7,
		'ON COMPLETION NOT PRESERVE'        => 7,

		'BIT'                               => 9,
		'XML'                               => 9,
		'ENUM'                              => 9,
		'JSON'                              => 9,
		'TEXT'                              => 9,
		'ARRAY'                             => 9,
		'SERIAL'                            => 9,
		'BOOLEAN'                           => 9,
		'DATETIME'                          => 9,
		'GEOMETRY'                          => 9,
		'MULTISET'                          => 9,
		'MULTILINEPOINT'                    => 9,
		'MULTILINEPOLYGON'                  => 9,

		'INT'                               => 11,
		'SET'                               => 11,
		'BLOB'                              => 11,
		'REAL'                              => 11,
		'FLOAT'                             => 11,
		'BIGINT'                            => 11,
		'DOUBLE'                            => 11,
		'DECIMAL'                           => 11,
		'INTEGER'                           => 11,
		'NUMERIC'                           => 11,
		'TINYINT'                           => 11,
		'VARCHAR'                           => 11,
		'LONGBLOB'                          => 11,
		'LONGTEXT'                          => 11,
		'SMALLINT'                          => 11,
		'TINYBLOB'                          => 11,
		'TINYTEXT'                          => 11,
		'CHARACTER'                         => 11,
		'MEDIUMINT'                         => 11,
		'VARBINARY'                         => 11,
		'MEDIUMBLOB'                        => 11,
		'MEDIUMTEXT'                        => 11,

		'BINARY VARYING'                    => 15,

		'KEY'                               => 19,
		'INDEX'                             => 19,
		'UNIQUE'                            => 19,
		'SPATIAL'                           => 19,
		'FULLTEXT'                          => 19,

		'INDEX KEY'                         => 23,
		'UNIQUE KEY'                        => 23,
		'FOREIGN KEY'                       => 23,
		'PRIMARY KEY'                       => 23,
		'SPATIAL KEY'                       => 23,
		'FULLTEXT KEY'                      => 23,
		'UNIQUE INDEX'                      => 23,
		'SPATIAL INDEX'                     => 23,
		'FULLTEXT INDEX'                    => 23,

		'X'                                 => 33,
		'Y'                                 => 33,
		'LN'                                => 33,
		'PI'                                => 33,
		'ABS'                               => 33,
		'AVG'                               => 33,
		'BIN'                               => 33,
		'COS'                               => 33,
		'COT'                               => 33,
		'DAY'                               => 33,
		'ELT'                               => 33,
		'EXP'                               => 33,
		'HEX'                               => 33,
		'LOG'                               => 33,
		'MAX'                               => 33,
		'MD5'                               => 33,
		'MID'                               => 33,
		'MIN'                               => 33,
		'NOW'                               => 33,
		'OCT'                               => 33,
		'ORD'                               => 33,
		'POW'                               => 33,
		'SHA'                               => 33,
		'SIN'                               => 33,
		'STD'                               => 33,
		'SUM'                               => 33,
		'TAN'                               => 33,
		'ACOS'                              => 33,
		'AREA'                              => 33,
		'ASIN'                              => 33,
		'ATAN'                              => 33,
		'CAST'                              => 33,
		'CEIL'                              => 33,
		'CONV'                              => 33,
		'HOUR'                              => 33,
		'LOG2'                              => 33,
		'LPAD'                              => 33,
		'RAND'                              => 33,
		'RPAD'                              => 33,
		'SHA1'                              => 33,
		'SHA2'                              => 33,
		'SIGN'                              => 33,
		'SQRT'                              => 33,
		'SRID'                              => 33,
		'ST_X'                              => 33,
		'ST_Y'                              => 33,
		'TRIM'                              => 33,
		'USER'                              => 33,
		'UUID'                              => 33,
		'WEEK'                              => 33,
		'ASCII'                             => 33,
		'ASWKB'                             => 33,
		'ASWKT'                             => 33,
		'ATAN2'                             => 33,
		'COUNT'                             => 33,
		'CRC32'                             => 33,
		'FIELD'                             => 33,
		'FLOOR'                             => 33,
		'INSTR'                             => 33,
		'LCASE'                             => 33,
		'LEAST'                             => 33,
		'LOG10'                             => 33,
		'LOWER'                             => 33,
		'LTRIM'                             => 33,
		'MONTH'                             => 33,
		'POWER'                             => 33,
		'QUOTE'                             => 33,
		'ROUND'                             => 33,
		'RTRIM'                             => 33,
		'SLEEP'                             => 33,
		'SPACE'                             => 33,
		'UCASE'                             => 33,
		'UNHEX'                             => 33,
		'UPPER'                             => 33,
		'ASTEXT'                            => 33,
		'BIT_OR'                            => 33,
		'BUFFER'                            => 33,
		'CONCAT'                            => 33,
		'DECODE'                            => 33,
		'ENCODE'                            => 33,
		'EQUALS'                            => 33,
		'FORMAT'                            => 33,
		'IFNULL'                            => 33,
		'ISNULL'                            => 33,
		'LENGTH'                            => 33,
		'LOCATE'                            => 33,
		'MINUTE'                            => 33,
		'NULLIF'                            => 33,
		'POINTN'                            => 33,
		'SECOND'                            => 33,
		'STDDEV'                            => 33,
		'STRCMP'                            => 33,
		'SUBSTR'                            => 33,
		'WITHIN'                            => 33,
		'ADDDATE'                           => 33,
		'ADDTIME'                           => 33,
		'AGAINST'                           => 33,
		'BIT_AND'                           => 33,
		'BIT_XOR'                           => 33,
		'CEILING'                           => 33,
		'CHARSET'                           => 33,
		'CROSSES'                           => 33,
		'CURDATE'                           => 33,
		'CURTIME'                           => 33,
		'DAYNAME'                           => 33,
		'DEGREES'                           => 33,
		'ENCRYPT'                           => 33,
		'EXTRACT'                           => 33,
		'GLENGTH'                           => 33,
		'ISEMPTY'                           => 33,
		'IS_IPV4'                           => 33,
		'IS_IPV6'                           => 33,
		'IS_UUID'                           => 33,
		'QUARTER'                           => 33,
		'RADIANS'                           => 33,
		'REVERSE'                           => 33,
		'SOUNDEX'                           => 33,
		'ST_AREA'                           => 33,
		'ST_SRID'                           => 33,
		'SUBDATE'                           => 33,
		'SUBTIME'                           => 33,
		'SYSDATE'                           => 33,
		'TOUCHES'                           => 33,
		'TO_DAYS'                           => 33,
		'VAR_POP'                           => 33,
		'VERSION'                           => 33,
		'WEEKDAY'                           => 33,
		'ASBINARY'                          => 33,
		'CENTROID'                          => 33,
		'COALESCE'                          => 33,
		'COMPRESS'                          => 33,
		'CONTAINS'                          => 33,
		'DATEDIFF'                          => 33,
		'DATE_ADD'                          => 33,
		'DATE_SUB'                          => 33,
		'DISJOINT'                          => 33,
		'DISTANCE'                          => 33,
		'ENDPOINT'                          => 33,
		'ENVELOPE'                          => 33,
		'GET_LOCK'                          => 33,
		'GREATEST'                          => 33,
		'ISCLOSED'                          => 33,
		'ISSIMPLE'                          => 33,
		'JSON_SET'                          => 33,
		'MAKEDATE'                          => 33,
		'MAKETIME'                          => 33,
		'MAKE_SET'                          => 33,
		'MBREQUAL'                          => 33,
		'OVERLAPS'                          => 33,
		'PASSWORD'                          => 33,
		'POSITION'                          => 33,
		'ST_ASWKB'                          => 33,
		'ST_ASWKT'                          => 33,
		'ST_UNION'                          => 33,
		'TIMEDIFF'                          => 33,
		'TRUNCATE'                          => 33,
		'VARIANCE'                          => 33,
		'VAR_SAMP'                          => 33,
		'YEARWEEK'                          => 33,
		'ANY_VALUE'                         => 33,
		'BENCHMARK'                         => 33,
		'BIT_COUNT'                         => 33,
		'COLLATION'                         => 33,
		'CONCAT_WS'                         => 33,
		'DAYOFWEEK'                         => 33,
		'DAYOFYEAR'                         => 33,
		'DIMENSION'                         => 33,
		'FROM_DAYS'                         => 33,
		'GEOMETRYN'                         => 33,
		'INET_ATON'                         => 33,
		'INET_NTOA'                         => 33,
		'JSON_KEYS'                         => 33,
		'JSON_TYPE'                         => 33,
		'LOAD_FILE'                         => 33,
		'MBRCOVERS'                         => 33,
		'MBREQUALS'                         => 33,
		'MBRWITHIN'                         => 33,
		'MONTHNAME'                         => 33,
		'NUMPOINTS'                         => 33,
		'ROW_COUNT'                         => 33,
		'ST_ASTEXT'                         => 33,
		'ST_BUFFER'                         => 33,
		'ST_EQUALS'                         => 33,
		'ST_LENGTH'                         => 33,
		'ST_POINTN'                         => 33,
		'ST_WITHIN'                         => 33,
		'SUBSTRING'                         => 33,
		'TO_BASE64'                         => 33,
		'UPDATEXML'                         => 33,
		'BIT_LENGTH'                        => 33,
		'CONVERT_TZ'                        => 33,
		'CONVEXHULL'                        => 33,
		'DAYOFMONTH'                        => 33,
		'EXPORT_SET'                        => 33,
		'FOUND_ROWS'                        => 33,
		'GET_FORMAT'                        => 33,
		'INET6_ATON'                        => 33,
		'INET6_NTOA'                        => 33,
		'INTERSECTS'                        => 33,
		'JSON_ARRAY'                        => 33,
		'JSON_DEPTH'                        => 33,
		'JSON_MERGE'                        => 33,
		'JSON_QUOTE'                        => 33,
		'JSON_VALID'                        => 33,
		'MBRTOUCHES'                        => 33,
		'NAME_CONST'                        => 33,
		'PERIOD_ADD'                        => 33,
		'STARTPOINT'                        => 33,
		'STDDEV_POP'                        => 33,
		'ST_CROSSES'                        => 33,
		'ST_GEOHASH'                        => 33,
		'ST_ISEMPTY'                        => 33,
		'ST_ISVALID'                        => 33,
		'ST_TOUCHES'                        => 33,
		'TO_SECONDS'                        => 33,
		'UNCOMPRESS'                        => 33,
		'UUID_SHORT'                        => 33,
		'WEEKOFYEAR'                        => 33,
		'AES_DECRYPT'                       => 33,
		'AES_ENCRYPT'                       => 33,
		'BIN_TO_UUID'                       => 33,
		'CHAR_LENGTH'                       => 33,
		'DATE_FORMAT'                       => 33,
		'DES_DECRYPT'                       => 33,
		'DES_ENCRYPT'                       => 33,
		'FIND_IN_SET'                       => 33,
		'FROM_BASE64'                       => 33,
		'GEOMFROMWKB'                       => 33,
		'GTID_SUBSET'                       => 33,
		'JSON_INSERT'                       => 33,
		'JSON_LENGTH'                       => 33,
		'JSON_OBJECT'                       => 33,
		'JSON_PRETTY'                       => 33,
		'JSON_REMOVE'                       => 33,
		'JSON_SEARCH'                       => 33,
		'LINEFROMWKB'                       => 33,
		'MBRCONTAINS'                       => 33,
		'MBRDISJOINT'                       => 33,
		'MBROVERLAPS'                       => 33,
		'MICROSECOND'                       => 33,
		'PERIOD_DIFF'                       => 33,
		'POLYFROMWKB'                       => 33,
		'SEC_TO_TIME'                       => 33,
		'STDDEV_SAMP'                       => 33,
		'STR_TO_DATE'                       => 33,
		'ST_ASBINARY'                       => 33,
		'ST_CENTROID'                       => 33,
		'ST_CONTAINS'                       => 33,
		'ST_DISJOINT'                       => 33,
		'ST_DISTANCE'                       => 33,
		'ST_ENDPOINT'                       => 33,
		'ST_ENVELOPE'                       => 33,
		'ST_ISCLOSED'                       => 33,
		'ST_ISSIMPLE'                       => 33,
		'ST_OVERLAPS'                       => 33,
		'ST_SIMPLIFY'                       => 33,
		'ST_VALIDATE'                       => 33,
		'SYSTEM_USER'                       => 33,
		'TIME_FORMAT'                       => 33,
		'TIME_TO_SEC'                       => 33,
		'UUID_TO_BIN'                       => 33,
		'COERCIBILITY'                      => 33,
		'EXTERIORRING'                      => 33,
		'EXTRACTVALUE'                      => 33,
		'GEOMETRYTYPE'                      => 33,
		'GEOMFROMTEXT'                      => 33,
		'GROUP_CONCAT'                      => 33,
		'IS_FREE_LOCK'                      => 33,
		'IS_USED_LOCK'                      => 33,
		'JSON_EXTRACT'                      => 33,
		'JSON_REPLACE'                      => 33,
		'JSON_UNQUOTE'                      => 33,
		'LINEFROMTEXT'                      => 33,
		'MBRCOVEREDBY'                      => 33,
		'MLINEFROMWKB'                      => 33,
		'MPOLYFROMWKB'                      => 33,
		'OCTET_LENGTH'                      => 33,
		'OLD_PASSWORD'                      => 33,
		'POINTFROMWKB'                      => 33,
		'POLYFROMTEXT'                      => 33,
		'RANDOM_BYTES'                      => 33,
		'RELEASE_LOCK'                      => 33,
		'SESSION_USER'                      => 33,
		'ST_ASGEOJSON'                      => 33,
		'ST_DIMENSION'                      => 33,
		'ST_GEOMETRYN'                      => 33,
		'ST_NUMPOINTS'                      => 33,
		'TIMESTAMPADD'                      => 33,
		'CONNECTION_ID'                     => 33,
		'FROM_UNIXTIME'                     => 33,
		'GTID_SUBTRACT'                     => 33,
		'INTERIORRINGN'                     => 33,
		'JSON_CONTAINS'                     => 33,
		'MBRINTERSECTS'                     => 33,
		'MLINEFROMTEXT'                     => 33,
		'MPOINTFROMWKB'                     => 33,
		'MPOLYFROMTEXT'                     => 33,
		'NUMGEOMETRIES'                     => 33,
		'POINTFROMTEXT'                     => 33,
		'ST_CONVEXHULL'                     => 33,
		'ST_DIFFERENCE'                     => 33,
		'ST_INTERSECTS'                     => 33,
		'ST_STARTPOINT'                     => 33,
		'TIMESTAMPDIFF'                     => 33,
		'WEIGHT_STRING'                     => 33,
		'IS_IPV4_COMPAT'                    => 33,
		'IS_IPV4_MAPPED'                    => 33,
		'LAST_INSERT_ID'                    => 33,
		'MPOINTFROMTEXT'                    => 33,
		'POLYGONFROMWKB'                    => 33,
		'ST_GEOMFROMWKB'                    => 33,
		'ST_LINEFROMWKB'                    => 33,
		'ST_POLYFROMWKB'                    => 33,
		'UNIX_TIMESTAMP'                    => 33,
		'GEOMCOLLFROMWKB'                   => 33,
		'MASTER_POS_WAIT'                   => 33,
		'POLYGONFROMTEXT'                   => 33,
		'ST_EXTERIORRING'                   => 33,
		'ST_GEOMETRYTYPE'                   => 33,
		'ST_GEOMFROMTEXT'                   => 33,
		'ST_INTERSECTION'                   => 33,
		'ST_LINEFROMTEXT'                   => 33,
		'ST_MAKEENVELOPE'                   => 33,
		'ST_MLINEFROMWKB'                   => 33,
		'ST_MPOLYFROMWKB'                   => 33,
		'ST_POINTFROMWKB'                   => 33,
		'ST_POLYFROMTEXT'                   => 33,
		'SUBSTRING_INDEX'                   => 33,
		'CHARACTER_LENGTH'                  => 33,
		'GEOMCOLLFROMTEXT'                  => 33,
		'GEOMETRYFROMTEXT'                  => 33,
		'JSON_MERGE_PATCH'                  => 33,
		'NUMINTERIORRINGS'                  => 33,
		'ST_INTERIORRINGN'                  => 33,
		'ST_MLINEFROMTEXT'                  => 33,
		'ST_MPOINTFROMWKB'                  => 33,
		'ST_MPOLYFROMTEXT'                  => 33,
		'ST_NUMGEOMETRIES'                  => 33,
		'ST_POINTFROMTEXT'                  => 33,
		'ST_SYMDIFFERENCE'                  => 33,
		'JSON_ARRAY_APPEND'                 => 33,
		'JSON_ARRAY_INSERT'                 => 33,
		'JSON_STORAGE_FREE'                 => 33,
		'JSON_STORAGE_SIZE'                 => 33,
		'LINESTRINGFROMWKB'                 => 33,
		'MULTIPOINTFROMWKB'                 => 33,
		'RELEASE_ALL_LOCKS'                 => 33,
		'ST_LATFROMGEOHASH'                 => 33,
		'ST_MPOINTFROMTEXT'                 => 33,
		'ST_POLYGONFROMWKB'                 => 33,
		'JSON_CONTAINS_PATH'                => 33,
		'MULTIPOINTFROMTEXT'                => 33,
		'ST_BUFFER_STRATEGY'                => 33,
		'ST_DISTANCE_SPHERE'                => 33,
		'ST_GEOMCOLLFROMTXT'                => 33,
		'ST_GEOMCOLLFROMWKB'                => 33,
		'ST_GEOMFROMGEOJSON'                => 33,
		'ST_LONGFROMGEOHASH'                => 33,
		'ST_POLYGONFROMTEXT'                => 33,
		'JSON_MERGE_PRESERVE'               => 33,
		'MULTIPOLYGONFROMWKB'               => 33,
		'ST_GEOMCOLLFROMTEXT'               => 33,
		'ST_GEOMETRYFROMTEXT'               => 33,
		'ST_NUMINTERIORRINGS'               => 33,
		'ST_POINTFROMGEOHASH'               => 33,
		'UNCOMPRESSED_LENGTH'               => 33,
		'MULTIPOLYGONFROMTEXT'              => 33,
		'ST_LINESTRINGFROMWKB'              => 33,
		'ST_MULTIPOINTFROMWKB'              => 33,
		'ST_MULTIPOINTFROMTEXT'             => 33,
		'MULTILINESTRINGFROMWKB'            => 33,
		'ST_MULTIPOLYGONFROMWKB'            => 33,
		'MULTILINESTRINGFROMTEXT'           => 33,
		'ST_MULTIPOLYGONFROMTEXT'           => 33,
		'GEOMETRYCOLLECTIONFROMWKB'         => 33,
		'ST_MULTILINESTRINGFROMWKB'         => 33,
		'GEOMETRYCOLLECTIONFROMTEXT'        => 33,
		'ST_MULTILINESTRINGFROMTEXT'        => 33,
		'VALIDATE_PASSWORD_STRENGTH'        => 33,
		'WAIT_FOR_EXECUTED_GTID_SET'        => 33,
		'ST_GEOMETRYCOLLECTIONFROMWKB'      => 33,
		'ST_GEOMETRYCOLLECTIONFROMTEXT'     => 33,
		'WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS' => 33,

		'IF'                                => 35,
		'IN'                                => 35,
		'MOD'                               => 35,
		'LEFT'                              => 35,
		'MATCH'                             => 35,
		'RIGHT'                             => 35,
		'EXISTS'                            => 35,
		'INSERT'                            => 35,
		'REPEAT'                            => 35,
		'SCHEMA'                            => 35,
		'VALUES'                            => 35,
		'CONVERT'                           => 35,
		'DEFAULT'                           => 35,
		'REPLACE'                           => 35,
		'DATABASE'                          => 35,
		'UTC_DATE'                          => 35,
		'UTC_TIME'                          => 35,
		'LOCALTIME'                         => 35,
		'CURRENT_DATE'                      => 35,
		'CURRENT_TIME'                      => 35,
		'CURRENT_USER'                      => 35,
		'UTC_TIMESTAMP'                     => 35,
		'LOCALTIMESTAMP'                    => 35,
		'CURRENT_TIMESTAMP'                 => 35,

		'NOT IN'                            => 39,

		'DATE'                              => 41,
		'TIME'                              => 41,
		'YEAR'                              => 41,
		'POINT'                             => 41,
		'POLYGON'                           => 41,
		'TIMESTAMP'                         => 41,
		'LINESTRING'                        => 41,
		'MULTIPOINT'                        => 41,
		'MULTIPOLYGON'                      => 41,
		'MULTILINESTRING'                   => 41,
		'GEOMETRYCOLLECTION'                => 41,

		'CHAR'                              => 43,
		'BINARY'                            => 43,
		'INTERVAL'                          => 43,
	);

	/**
	 * All data type options.
	 *
	 * @var array<string, int|array<int, int|string>>
	 */
	public static $data_type_options = array(
		'BINARY'        => 1,
		'CHARACTER SET' => array(
			2,
			'var',
		),
		'CHARSET'       => array(
			2,
			'var',
		),
		'COLLATE'       => array(
			3,
			'var',
		),
		'UNSIGNED'      => 4,
		'ZEROFILL'      => 5,
	);

	/**
	 * All field options.
	 *
	 * @var array<string, bool|int|array<int, int|string|array<string, bool>>>
	 */
	public static $field_options = array(

		/*
		 * Tells the `OptionsArray` to not sort the options.
		 * See the note below.
		 */
		'_UNSORTED'        => true,

		'NOT NULL'         => 1,
		'NULL'             => 1,
		'DEFAULT'          => array(
			2,
			'expr',
			array( 'breakOnAlias' => true ),
		),

		// Following are not according to grammar, but MySQL happily accepts these at any location.
		'CHARSET'          => array(
			2,
			'var',
		),
		'COLLATE'          => array(
			3,
			'var',
		),
		'AUTO_INCREMENT'   => 3,
		'PRIMARY'          => 4,
		'PRIMARY KEY'      => 4,
		'UNIQUE'           => 4,
		'UNIQUE KEY'       => 4,
		'COMMENT'          => array(
			5,
			'var',
		),
		'COLUMN_FORMAT'    => array(
			6,
			'var',
		),
		'ON UPDATE'        => array(
			7,
			'expr',
		),

		// Generated columns options.
		'GENERATED ALWAYS' => 8,
		'AS'               => array(
			9,
			'expr',
			array( 'parenthesesDelimited' => true ),
		),
		'VIRTUAL'          => 10,
		'PERSISTENT'       => 11,
		'STORED'           => 11,
		'CHECK'            => array(
			12,
			'expr',
			array( 'parenthesesDelimited' => true ),
		),
		'INVISIBLE'        => 13,
		'ENFORCED'         => 14,
		'NOT'              => 15,
		'COMPRESSED'       => 16,

		/*
		 * Common entries.
		 *
		 * NOTE: Some of the common options are not in the same order which
		 * causes troubles when checking if the options are in the right order.
		 * I should find a way to define multiple sets of options and make the
		 * parser select the right set.
		 *
		 * 'UNIQUE'                        => 4,
		 * 'UNIQUE KEY'                    => 4,
		 * 'COMMENT'                       => [5, 'var'],
		 * 'NOT NULL'                      => 1,
		 * 'NULL'                          => 1,
		 * 'PRIMARY'                       => 4,
		 * 'PRIMARY KEY'                   => 4,
		 */
	);

	/**
	 * Quotes mode.
	 *
	 * @link https://dev.mysql.com/doc/refman/en/sql-mode.html#sqlmode_ansi_quotes
	 * @link https://mariadb.com/kb/en/sql-mode/#ansi_quotes
	 */
	const SQL_MODE_ANSI_QUOTES = 2;

	/**
	 * The array of tokens.
	 *
	 * @var stdClass[]
	 */
	public $tokens = array();

	/**
	 * The count of tokens.
	 *
	 * @var int
	 */
	public $tokens_count = 0;

	/**
	 * The index of the next token to be returned.
	 *
	 * @var int
	 */
	public $tokens_index = 0;

	/**
	 * The object constructor.
	 *
	 * @param string $str       The query to be lexed.
	 * @param string $delimiter The delimiter to be used.
	 */
	public function __construct( $str, $delimiter = null ) {
		$this->str = $str;
		// `strlen` is used instead of `mb_strlen` because the lexer needs to parse each byte of the input.
		$this->string_length = strlen( $str );

		// Setting the delimiter.
		$this->set_delimiter( ! empty( $delimiter ) ? $delimiter : static::$default_delimiter );

		$this->lex();
	}

	/**
	 * Sets the delimiter.
	 *
	 * @param string $delimiter The new delimiter.
	 *
	 * @return void
	 */
	public function set_delimiter( $delimiter ) {
		$this->delimiter        = $delimiter;
		$this->delimiter_length = strlen( $delimiter );
	}

	/**
	 * Parses the string and extracts lexemes.
	 *
	 * @return void
	 */
	public function lex() {
		/*
		 * TODO: Sometimes, static::parse* functions make unnecessary calls to
		 * is* functions. For a better performance, some rules can be deduced
		 * from context.
		 * For example, in `parse_bool` there is no need to compare the token
		 * every time with `true` and `false`. The first step would be to
		 * compare with 'true' only and just after that add another letter from
		 * context and compare again with `false`.
		 * Another example is `parse_comment`.
		 */

		/**
		 * Last processed token.
		 *
		 * @var WP_SQLite_Token
		 */
		$last_token = null;

		for ( $this->last = 0, $last_idx = 0; $this->last < $this->string_length; $last_idx = ++$this->last ) {
			/**
			 * The new token.
			 *
			 * @var WP_SQLite_Token
			 */
			$token = null;

			foreach ( static::$parser_methods as $method ) {
				$token = $this->$method();

				if ( $token ) {
					break;
				}
			}

			if ( null === $token ) {
				$token = new WP_SQLite_Token( $this->str[ $this->last ] );
				$this->error( 'Unexpected character.', $this->str[ $this->last ], $this->last );
			} elseif (
				null !== $last_token
				&& WP_SQLite_Token::TYPE_SYMBOL === $token->type
				&& $token->flags & WP_SQLite_Token::FLAG_SYMBOL_VARIABLE
				&& (
					WP_SQLite_Token::TYPE_STRING === $last_token->type
					|| (
						WP_SQLite_Token::TYPE_SYMBOL === $last_token->type
						&& $last_token->flags & WP_SQLite_Token::FLAG_SYMBOL_BACKTICK
					)
				)
			) {
				// Handles ```... FROM 'user'@'%' ...```.
				$last_token->token .= $token->token;
				$last_token->type   = WP_SQLite_Token::TYPE_SYMBOL;
				$last_token->flags  = WP_SQLite_Token::FLAG_SYMBOL_USER;
				$last_token->value .= '@' . $token->value;
				continue;
			} elseif (
				null !== $last_token
				&& WP_SQLite_Token::TYPE_KEYWORD === $token->type
				&& WP_SQLite_Token::TYPE_OPERATOR === $last_token->type
				&& '.' === $last_token->value
			) {
				// Handles ```... tbl.FROM ...```. In this case, FROM is not a reserved word.
				$token->type  = WP_SQLite_Token::TYPE_NONE;
				$token->flags = 0;
				$token->value = $token->token;
			}

			$token->position = $last_idx;

			$this->tokens[ $this->tokens_count++ ] = $token;

			// Handling delimiters.
			if ( WP_SQLite_Token::TYPE_NONE === $token->type && 'DELIMITER' === $token->value ) {
				if ( $this->last + 1 >= $this->string_length ) {
					$this->error( 'Expected whitespace(s) before delimiter.', '', $this->last + 1 );
					continue;
				}

				/*
				 * Skipping last R (from `delimiteR`) and whitespaces between
				 * the keyword `DELIMITER` and the actual delimiter.
				 */
				$pos   = ++$this->last;
				$token = $this->parse_whitespace();

				if ( null !== $token ) {
					$token->position                       = $pos;
					$this->tokens[ $this->tokens_count++ ] = $token;
				}

				// Preparing the token that holds the new delimiter.
				if ( $this->last + 1 >= $this->string_length ) {
					$this->error( 'Expected delimiter.', '', $this->last + 1 );
					continue;
				}

				$pos = $this->last + 1;

				// Parsing the delimiter.
				$this->delimiter  = null;
				$delimiter_length = 0;
				while (
					++$this->last < $this->string_length
					&& ! static::is_whitespace( $this->str[ $this->last ] )
					&& $delimiter_length < 15
				) {
					$this->delimiter .= $this->str[ $this->last ];
					++$delimiter_length;
				}

				if ( empty( $this->delimiter ) ) {
					$this->error( 'Expected delimiter.', '', $this->last );
					$this->delimiter = ';';
				}

				--$this->last;

				// Saving the delimiter and its token.
				$this->delimiter_length                = strlen( $this->delimiter );
				$token                                 = new WP_SQLite_Token( $this->delimiter, WP_SQLite_Token::TYPE_DELIMITER );
				$token->position                       = $pos;
				$this->tokens[ $this->tokens_count++ ] = $token;
			}

			$last_token = $token;
		}

		// Adding a final delimiter to mark the ending.
		$this->tokens[ $this->tokens_count++ ] = new WP_SQLite_Token( null, WP_SQLite_Token::TYPE_DELIMITER );

		$this->solve_ambiguity_on_star_operator();
		$this->solve_ambiguity_on_function_keywords();
	}

	/**
	 * Resolves the ambiguity when dealing with the "*" operator.
	 *
	 * In SQL statements, the "*" operator can be an arithmetic operator (like in 2*3) or an SQL wildcard (like in
	 * SELECT a.* FROM ...). To solve this ambiguity, the solution is to find the next token, excluding whitespaces and
	 * comments, right after the "*" position. The "*" is for sure an SQL wildcard if the next token found is any of:
	 * - "FROM" (the FROM keyword like in "SELECT * FROM...");
	 * - "USING" (the USING keyword like in "DELETE table_name.* USING...");
	 * - "," (a comma separator like in "SELECT *, field FROM...");
	 * - ")" (a closing parenthesis like in "COUNT(*)").
	 * This methods will change the flag of the "*" tokens when any of those condition above is true. Otherwise, the
	 * default flag (arithmetic) will be kept.
	 *
	 * @return void
	 */
	private function solve_ambiguity_on_star_operator() {
		$i_bak = $this->tokens_index;
		while ( true ) {
			$star_token = $this->tokens_get_next_of_type_and_value( WP_SQLite_Token::TYPE_OPERATOR, '*' );
			if ( null === $star_token ) {
				break;
			}
			// tokens_get_next() already gets rid of whitespaces and comments.
			$next = $this->tokens_get_next();

			if ( null === $next ) {
				continue;
			}

			if (
				( WP_SQLite_Token::TYPE_KEYWORD !== $next->type || ! in_array( $next->value, array( 'FROM', 'USING' ), true ) )
				&& ( WP_SQLite_Token::TYPE_OPERATOR !== $next->type || ! in_array( $next->value, array( ',', ')' ), true ) )
			) {
				continue;
			}

			$star_token->flags = WP_SQLite_Token::FLAG_OPERATOR_SQL;
		}

		$this->tokens_index = $i_bak;
	}

	/**
	 * Resolves the ambiguity when dealing with the functions keywords.
	 *
	 * In SQL statements, the function keywords might be used as table names or columns names.
	 * To solve this ambiguity, the solution is to find the next token, excluding whitespaces and
	 * comments, right after the function keyword position. The function keyword is for sure used
	 * as column name or table name if the next token found is any of:
	 *
	 * - "FROM" (the FROM keyword like in "SELECT Country x, AverageSalary avg FROM...");
	 * - "WHERE" (the WHERE keyword like in "DELETE FROM emp x WHERE x.salary = 20");
	 * - "SET" (the SET keyword like in "UPDATE Country x, City y set x.Name=x.Name");
	 * - "," (a comma separator like 'x,' in "UPDATE Country x, City y set x.Name=x.Name");
	 * - "." (a dot separator like in "x.asset_id FROM (SELECT evt.asset_id FROM evt)".
	 * - "NULL" (when used as a table alias like in "avg.col FROM (SELECT ev.col FROM ev) avg").
	 *
	 * This method will change the flag of the function keyword tokens when any of those
	 * condition above is true. Otherwise, the
	 * default flag (function keyword) will be kept.
	 *
	 * @return void
	 */
	private function solve_ambiguity_on_function_keywords() {
		$i_bak            = $this->tokens_index;
		$keyword_function = WP_SQLite_Token::TYPE_KEYWORD | WP_SQLite_Token::FLAG_KEYWORD_FUNCTION;
		while ( true ) {
			$keyword_token = $this->tokens_get_next_of_type_and_flag( WP_SQLite_Token::TYPE_KEYWORD, $keyword_function );
			if ( null === $keyword_token ) {
				break;
			}
			$next = $this->tokens_get_next();
			if (
				( WP_SQLite_Token::TYPE_KEYWORD !== $next->type
					|| ! in_array( $next->value, $this->keyword_name_indicators, true )
				)
				&& ( WP_SQLite_Token::TYPE_OPERATOR !== $next->type
					|| ! in_array( $next->value, $this->operator_name_indicators, true )
				)
				&& ( null !== $next->value )
			) {
				continue;
			}

			$keyword_token->type    = WP_SQLite_Token::TYPE_NONE;
			$keyword_token->flags   = WP_SQLite_Token::TYPE_NONE;
			$keyword_token->keyword = $keyword_token->value;
		}

		$this->tokens_index = $i_bak;
	}

	/**
	 * Creates a new error log.
	 *
	 * @param string $msg  The error message.
	 * @param string $str  The character that produced the error.
	 * @param int    $pos  The position of the character.
	 * @param int    $code The code of the error.
	 *
	 * @throws Exception The error log.
	 * @return void
	 */
	public function error( $msg, $str = '', $pos = 0, $code = 0 ) {
		throw new Exception(
			print_r(
				array(
					'query'    => $this->str,
					'message'  => $msg,
					'str'      => $str,
					'position' => $pos,
					'code'     => $code,
				),
				true
			)
		);
	}

	/**
	 * Parses a keyword.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_keyword() {
		$token = '';

		/**
		 * Value to be returned.
		 *
		 * @var WP_SQLite_Token
		 */
		$ret = null;

		// The value of `$this->last` where `$token` ends in `$this->str`.
		$i_end = $this->last;

		// Whether last parsed character is a whitespace.
		$last_space = false;

		for ( $j = 1; $j < static::KEYWORD_MAX_LENGTH && $this->last < $this->string_length; ++$j, ++$this->last ) {
			$last_space = false;
			// Composed keywords shouldn't have more than one whitespace between keywords.
			if ( static::is_whitespace( $this->str[ $this->last ] ) ) {
				if ( $last_space ) {
					--$j; // The size of the keyword didn't increase.
					continue;
				}

				$last_space = true;
			}

			$token .= $this->str[ $this->last ];
			$flags  = static::is_keyword( $token );

			if ( ( $this->last + 1 !== $this->string_length && ! static::is_separator( $this->str[ $this->last + 1 ] ) ) || ! $flags ) {
				continue;
			}

			$ret   = new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_KEYWORD, $flags );
			$i_end = $this->last;

			/*
			 * We don't break so we find longest keyword.
			 * For example, `OR` and `ORDER` have a common prefix `OR`.
			 * If we stopped at `OR`, the parsing would be invalid.
			 */
		}

		$this->last = $i_end;

		return $ret;
	}

	/**
	 * Parses a label.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_label() {
		$token = '';

		/**
		 * Value to be returned.
		 *
		 * @var WP_SQLite_Token
		 */
		$ret = null;

		// The value of `$this->last` where `$token` ends in `$this->str`.
		$i_end = $this->last;
		for ( $j = 1; $j < static::LABEL_MAX_LENGTH && $this->last < $this->string_length; ++$j, ++$this->last ) {
			if ( ':' === $this->str[ $this->last ] && $j > 1 ) {
				// End of label.
				$token .= $this->str[ $this->last ];
				$ret    = new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_LABEL );
				$i_end  = $this->last;
				break;
			}

			if ( static::is_whitespace( $this->str[ $this->last ] ) && $j > 1 ) {
				/*
				 * Whitespace between label and `:`.
				 * The size of the keyword didn't increase.
				 */
				--$j;
			} elseif ( static::is_separator( $this->str[ $this->last ] ) ) {
				// Any other separator.
				break;
			}

			$token .= $this->str[ $this->last ];
		}

		$this->last = $i_end;

		return $ret;
	}

	/**
	 * Parses an operator.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_operator() {
		$token = '';

		/**
		 * Value to be returned.
		 *
		 * @var WP_SQLite_Token
		 */
		$ret = null;

		// The value of `$this->last` where `$token` ends in `$this->str`.
		$i_end = $this->last;

		for ( $j = 1; $j < static::OPERATOR_MAX_LENGTH && $this->last < $this->string_length; ++$j, ++$this->last ) {
			$token .= $this->str[ $this->last ];
			$flags  = static::is_operator( $token );

			if ( ! $flags ) {
				continue;
			}

			$ret   = new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_OPERATOR, $flags );
			$i_end = $this->last;
		}

		$this->last = $i_end;

		return $ret;
	}

	/**
	 * Parses a whitespace.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_whitespace() {
		$token = $this->str[ $this->last ];

		if ( ! static::is_whitespace( $token ) ) {
			return null;
		}

		while ( ++$this->last < $this->string_length && static::is_whitespace( $this->str[ $this->last ] ) ) {
			$token .= $this->str[ $this->last ];
		}

		--$this->last;

		return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_WHITESPACE );
	}

	/**
	 * Parses a comment.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_comment() {
		$i_bak = $this->last;
		$token = $this->str[ $this->last ];

		// Bash style comments (#comment\n).
		if ( static::is_comment( $token ) ) {
			while ( ++$this->last < $this->string_length && "\n" !== $this->str[ $this->last ] ) {
				$token .= $this->str[ $this->last ];
			}

			// Include trailing \n as whitespace token.
			if ( $this->last < $this->string_length ) {
				--$this->last;
			}

			return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_COMMENT, WP_SQLite_Token::FLAG_COMMENT_BASH );
		}

		// C style comments (/*comment*\/).
		if ( ++$this->last < $this->string_length ) {
			$token .= $this->str[ $this->last ];
			if ( static::is_comment( $token ) ) {
				// There might be a conflict with "*" operator here, when string is "*/*".
				// This can occurs in the following statements:
				// - "SELECT */* comment */ FROM ..."
				// - "SELECT 2*/* comment */3 AS `six`;".
				$next = $this->last + 1;
				if ( ( $next < $this->string_length ) && '*' === $this->str[ $next ] ) {
					// Conflict in "*/*": first "*" was not for ending a comment.
					// Stop here and let other parsing method define the true behavior of that first star.
					$this->last = $i_bak;

					return null;
				}

				$flags = WP_SQLite_Token::FLAG_COMMENT_C;

				// This comment already ended. It may be a part of a previous MySQL specific command.
				if ( '*/' === $token ) {
					return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_COMMENT, $flags );
				}

				// Checking if this is a MySQL-specific command.
				if ( $this->last + 1 < $this->string_length && '!' === $this->str[ $this->last + 1 ] ) {
					$flags |= WP_SQLite_Token::FLAG_COMMENT_MYSQL_CMD;
					$token .= $this->str[ ++$this->last ];

					while (
						++$this->last < $this->string_length
						&& $this->str[ $this->last ] >= '0'
						&& $this->str[ $this->last ] <= '9'
					) {
						$token .= $this->str[ $this->last ];
					}

					--$this->last;

					// We split this comment and parse only its beginning here.
					return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_COMMENT, $flags );
				}

				// Parsing the comment.
				while (
					++$this->last < $this->string_length
					&& ( '*' !== $this->str[ $this->last - 1 ] || '/' !== $this->str[ $this->last ] )
				) {
					$token .= $this->str[ $this->last ];
				}

				// Adding the ending.
				if ( $this->last < $this->string_length ) {
					$token .= $this->str[ $this->last ];
				}

				return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_COMMENT, $flags );
			}
		}

		// SQL style comments (-- comment\n).
		if ( ++$this->last < $this->string_length ) {
			$token .= $this->str[ $this->last ];
			$end    = false;
		} else {
			--$this->last;
			$end = true;
		}

		if ( static::is_comment( $token, $end ) ) {
			// Checking if this comment did not end already (```--\n```).
			if ( "\n" !== $this->str[ $this->last ] ) {
				while ( ++$this->last < $this->string_length && "\n" !== $this->str[ $this->last ] ) {
					$token .= $this->str[ $this->last ];
				}
			}

			// Include trailing \n as whitespace token.
			if ( $this->last < $this->string_length ) {
				--$this->last;
			}

			return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_COMMENT, WP_SQLite_Token::FLAG_COMMENT_SQL );
		}

		$this->last = $i_bak;

		return null;
	}

	/**
	 * Parses a boolean.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_bool() {
		if ( $this->last + 3 >= $this->string_length ) {
			// At least `min(strlen('TRUE'), strlen('FALSE'))` characters are required.
			return null;
		}

		$i_bak = $this->last;
		$token = $this->str[ $this->last ] . $this->str[ ++$this->last ]
		. $this->str[ ++$this->last ] . $this->str[ ++$this->last ]; // _TRUE_ or _FALS_e.

		if ( static::is_bool( $token ) ) {
			return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_BOOL );
		}

		if ( ++$this->last < $this->string_length ) {
			$token .= $this->str[ $this->last ]; // fals_E_.
			if ( static::is_bool( $token ) ) {
				return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_BOOL, 1 );
			}
		}

		$this->last = $i_bak;

		return null;
	}

	/**
	 * Parses a number.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_number() {
		/*
		 * A rudimentary state machine is being used to parse numbers due to
		 * the various forms of their notation.
		 *
		 * Below are the states of the machines and the conditions to change
		 * the state.
		 *
		 *      1 --------------------[ + or - ]-------------------> 1
		 *      1 -------------------[ 0x or 0X ]------------------> 2
		 *      1 --------------------[ 0 to 9 ]-------------------> 3
		 *      1 -----------------------[ . ]---------------------> 4
		 *      1 -----------------------[ b ]---------------------> 7
		 *
		 *      2 --------------------[ 0 to F ]-------------------> 2
		 *
		 *      3 --------------------[ 0 to 9 ]-------------------> 3
		 *      3 -----------------------[ . ]---------------------> 4
		 *      3 --------------------[ e or E ]-------------------> 5
		 *
		 *      4 --------------------[ 0 to 9 ]-------------------> 4
		 *      4 --------------------[ e or E ]-------------------> 5
		 *
		 *      5 ---------------[ + or - or 0 to 9 ]--------------> 6
		 *
		 *      7 -----------------------[ ' ]---------------------> 8
		 *
		 *      8 --------------------[ 0 or 1 ]-------------------> 8
		 *      8 -----------------------[ ' ]---------------------> 9
		 *
		 * State 1 may be reached by negative numbers.
		 * State 2 is reached only by hex numbers.
		 * State 4 is reached only by float numbers.
		 * State 5 is reached only by numbers in approximate form.
		 * State 7 is reached only by numbers in bit representation.
		 *
		 * Valid final states are: 2, 3, 4 and 6. Any parsing that finished in a
		 * state other than these is invalid.
		 * Also, negative states are invalid states.
		 */
		$i_bak = $this->last;
		$token = '';
		$flags = 0;
		$state = 1;
		for ( ; $this->last < $this->string_length; ++$this->last ) {
			if ( 1 === $state ) {
				if ( '-' === $this->str[ $this->last ] ) {
					$flags |= WP_SQLite_Token::FLAG_NUMBER_NEGATIVE;
				} elseif (
					$this->last + 1 < $this->string_length
					&& '0' === $this->str[ $this->last ]
					&& ( 'x' === $this->str[ $this->last + 1 ] || 'X' === $this->str[ $this->last + 1 ] )
				) {
					$token .= $this->str[ $this->last++ ];
					$state  = 2;
				} elseif ( $this->str[ $this->last ] >= '0' && $this->str[ $this->last ] <= '9' ) {
					$state = 3;
				} elseif ( '.' === $this->str[ $this->last ] ) {
					$state = 4;
				} elseif ( 'b' === $this->str[ $this->last ] ) {
					$state = 7;
				} elseif ( '+' !== $this->str[ $this->last ] ) {
					// `+` is a valid character in a number.
					break;
				}
			} elseif ( 2 === $state ) {
				$flags |= WP_SQLite_Token::FLAG_NUMBER_HEX;
				if (
					! (
						( $this->str[ $this->last ] >= '0' && $this->str[ $this->last ] <= '9' )
						|| ( $this->str[ $this->last ] >= 'A' && $this->str[ $this->last ] <= 'F' )
						|| ( $this->str[ $this->last ] >= 'a' && $this->str[ $this->last ] <= 'f' )
					)
				) {
					break;
				}
			} elseif ( 3 === $state ) {
				if ( '.' === $this->str[ $this->last ] ) {
					$state = 4;
				} elseif ( 'e' === $this->str[ $this->last ] || 'E' === $this->str[ $this->last ] ) {
					$state = 5;
				} elseif (
					( $this->str[ $this->last ] >= 'a' && $this->str[ $this->last ] <= 'z' )
					|| ( $this->str[ $this->last ] >= 'A' && $this->str[ $this->last ] <= 'Z' )
				) {
					// A number can't be directly followed by a letter.
					$state = -$state;
				} elseif ( $this->str[ $this->last ] < '0' || $this->str[ $this->last ] > '9' ) {
					// Just digits and `.`, `e` and `E` are valid characters.
					break;
				}
			} elseif ( 4 === $state ) {
				$flags |= WP_SQLite_Token::FLAG_NUMBER_FLOAT;
				if ( 'e' === $this->str[ $this->last ] || 'E' === $this->str[ $this->last ] ) {
					$state = 5;
				} elseif (
					( $this->str[ $this->last ] >= 'a' && $this->str[ $this->last ] <= 'z' )
					|| ( $this->str[ $this->last ] >= 'A' && $this->str[ $this->last ] <= 'Z' )
				) {
					// A number can't be directly followed by a letter.
					$state = -$state;
				} elseif ( $this->str[ $this->last ] < '0' || $this->str[ $this->last ] > '9' ) {
					// Just digits, `e` and `E` are valid characters.
					break;
				}
			} elseif ( 5 === $state ) {
				$flags |= WP_SQLite_Token::FLAG_NUMBER_APPROXIMATE;
				if (
					'+' === $this->str[ $this->last ] || '-' === $this->str[ $this->last ]
					|| ( $this->str[ $this->last ] >= '0' && $this->str[ $this->last ] <= '9' )
				) {
					$state = 6;
				} elseif (
					( $this->str[ $this->last ] >= 'a' && $this->str[ $this->last ] <= 'z' )
					|| ( $this->str[ $this->last ] >= 'A' && $this->str[ $this->last ] <= 'Z' )
				) {
					// A number can't be directly followed by a letter.
					$state = -$state;
				} else {
					break;
				}
			} elseif ( 6 === $state ) {
				if ( $this->str[ $this->last ] < '0' || $this->str[ $this->last ] > '9' ) {
					// Just digits are valid characters.
					break;
				}
			} elseif ( 7 === $state ) {
				$flags |= WP_SQLite_Token::FLAG_NUMBER_BINARY;
				if ( '\'' !== $this->str[ $this->last ] ) {
					break;
				}

				$state = 8;
			} elseif ( 8 === $state ) {
				if ( '\'' === $this->str[ $this->last ] ) {
					$state = 9;
				} elseif ( '0' !== $this->str[ $this->last ] && '1' !== $this->str[ $this->last ] ) {
					break;
				}
			} elseif ( 9 === $state ) {
				break;
			}

			$token .= $this->str[ $this->last ];
		}

		if ( 2 === $state || 3 === $state || ( '.' !== $token && 4 === $state ) || 6 === $state || 9 === $state ) {
			--$this->last;

			return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_NUMBER, $flags );
		}

		$this->last = $i_bak;

		return null;
	}

	/**
	 * Parses a string.
	 *
	 * @param string $quote Additional starting symbol.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_string( $quote = '' ) {
		$token = $this->str[ $this->last ];
		$flags = static::is_string( $token );

		if ( ! $flags && $token !== $quote ) {
			return null;
		}

		$quote = $token;

		while ( ++$this->last < $this->string_length ) {
			if (
				$this->last + 1 < $this->string_length
				&& (
					( $this->str[ $this->last ] === $quote && $this->str[ $this->last + 1 ] === $quote )
					|| ( '\\' === $this->str[ $this->last ] && '`' !== $quote )
				)
			) {
				$token .= $this->str[ $this->last ] . $this->str[ ++$this->last ];
			} else {
				if ( $this->str[ $this->last ] === $quote ) {
					break;
				}

				$token .= $this->str[ $this->last ];
			}
		}

		if ( $this->last >= $this->string_length || $this->str[ $this->last ] !== $quote ) {
			$this->error(
				sprintf(
					'Ending quote %1$s was expected.',
					$quote
				),
				'',
				$this->last
			);
		} else {
			$token .= $this->str[ $this->last ];
		}

		return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_STRING, $flags );
	}

	/**
	 * Parses a symbol.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_symbol() {
		$token = $this->str[ $this->last ];
		$flags = static::is_symbol( $token );

		if ( ! $flags ) {
			return null;
		}

		if ( $flags & WP_SQLite_Token::FLAG_SYMBOL_VARIABLE ) {
			if ( $this->last + 1 < $this->string_length && '@' === $this->str[ ++$this->last ] ) {
				// This is a system variable (e.g. `@@hostname`).
				$token .= $this->str[ $this->last++ ];
				$flags |= WP_SQLite_Token::FLAG_SYMBOL_SYSTEM;
			}
		} elseif ( $flags & WP_SQLite_Token::FLAG_SYMBOL_PARAMETER ) {
			if ( '?' !== $token && $this->last + 1 < $this->string_length ) {
				++$this->last;
			}
		} else {
			$token = '';
		}

		$str = null;

		if ( $this->last < $this->string_length ) {
			$str = $this->parse_string( '`' );

			if ( null === $str ) {
				$str = $this->parse_unknown();

				if ( null === $str ) {
					$this->error( 'Variable name was expected.', $this->str[ $this->last ], $this->last );
				}
			}
		}

		if ( null !== $str ) {
			$token .= $str->token;
		}

		return new WP_SQLite_Token( $token, WP_SQLite_Token::TYPE_SYMBOL, $flags );
	}

	/**
	 * Parses unknown parts of the query.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_unknown() {
		$token = $this->str[ $this->last ];
		if ( static::is_separator( $token ) ) {
			return null;
		}

		while ( ++$this->last < $this->string_length && ! static::is_separator( $this->str[ $this->last ] ) ) {
			$token .= $this->str[ $this->last ];

			// Test if end of token equals the current delimiter. If so, remove it from the token.
			if ( str_ends_with( $token, $this->delimiter ) ) {
				$token       = substr( $token, 0, -$this->delimiter_length );
				$this->last -= $this->delimiter_length - 1;
				break;
			}
		}

		--$this->last;

		return new WP_SQLite_Token( $token );
	}

	/**
	 * Parses the delimiter of the query.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function parse_delimiter() {
		$index = 0;

		while ( $index < $this->delimiter_length && $this->last + $index < $this->string_length ) {
			if ( $this->delimiter[ $index ] !== $this->str[ $this->last + $index ] ) {
				return null;
			}

			++$index;
		}

		$this->last += $this->delimiter_length - 1;

		return new WP_SQLite_Token( $this->delimiter, WP_SQLite_Token::TYPE_DELIMITER );
	}

	/**
	 * Checks if the given string is a keyword.
	 *
	 * @param string $str         String to be checked.
	 * @param bool   $is_reserved Checks if the keyword is reserved.
	 *
	 * @return int|null
	 */
	public static function is_keyword( $str, $is_reserved = false ) {
		$str = strtoupper( $str );

		if ( isset( static::$keywords[ $str ] ) ) {
			if ( $is_reserved && ! ( static::$keywords[ $str ] & WP_SQLite_Token::FLAG_KEYWORD_RESERVED ) ) {
				return null;
			}

			return static::$keywords[ $str ];
		}

		return null;
	}

	/**
	 * Checks if the given string is an operator.
	 *
	 * @param string $str String to be checked.
	 *
	 * @return int|null The appropriate flag for the operator.
	 */
	public static function is_operator( $str ) {
		if ( ! isset( static::$operators[ $str ] ) ) {
			return null;
		}

		return static::$operators[ $str ];
	}

	/**
	 * Checks if the given character is a whitespace.
	 *
	 * @param string $str String to be checked.
	 *
	 * @return bool
	 */
	public static function is_whitespace( $str ) {
		return ( ' ' === $str ) || ( "\r" === $str ) || ( "\n" === $str ) || ( "\t" === $str );
	}

	/**
	 * Checks if the given string is the beginning of a whitespace.
	 *
	 * @param string $str String to be checked.
	 * @param mixed  $end Whether this is the end of the string.
	 *
	 * @return int|null The appropriate flag for the comment type.
	 */
	public static function is_comment( $str, $end = false ) {
		$string_length = strlen( $str );
		if ( 0 === $string_length ) {
			return null;
		}

		// If comment is Bash style (#).
		if ( '#' === $str[0] ) {
			return WP_SQLite_Token::FLAG_COMMENT_BASH;
		}

		// If comment is opening C style (/*), warning, it could be a MySQL command (/*!).
		if ( ( $string_length > 1 ) && ( '/' === $str[0] ) && ( '*' === $str[1] ) ) {
			return ( $string_length > 2 ) && ( '!' === $str[2] ) ?
				WP_SQLite_Token::FLAG_COMMENT_MYSQL_CMD : WP_SQLite_Token::FLAG_COMMENT_C;
		}

		// If comment is closing C style (*/), warning, it could conflicts with wildcard and a real opening C style.
		// It would looks like the following valid SQL statement: "SELECT */* comment */ FROM...".
		if ( ( $string_length > 1 ) && ( '*' === $str[0] ) && ( '/' === $str[1] ) ) {
			return WP_SQLite_Token::FLAG_COMMENT_C;
		}

		// If comment is SQL style (--\s?).
		if ( ( $string_length > 2 ) && ( '-' === $str[0] ) && ( '-' === $str[1] ) && static::is_whitespace( $str[2] ) ) {
			return WP_SQLite_Token::FLAG_COMMENT_SQL;
		}

		if ( ( 2 === $string_length ) && $end && ( '-' === $str[0] ) && ( '-' === $str[1] ) ) {
			return WP_SQLite_Token::FLAG_COMMENT_SQL;
		}

		return null;
	}

	/**
	 * Checks if the given string is a boolean value.
	 * This actually checks only for `TRUE` and `FALSE` because `1` or `0` are
	 * numbers and are parsed by specific methods.
	 *
	 * @param string $str String to be checked.
	 *
	 * @return bool
	 */
	public static function is_bool( $str ) {
		$str = strtoupper( $str );

		return ( 'TRUE' === $str ) || ( 'FALSE' === $str );
	}

	/**
	 * Checks if the given character can be a part of a number.
	 *
	 * @param string $str String to be checked.
	 *
	 * @return bool
	 */
	public static function is_number( $str ) {
		return ( $str >= '0' ) && ( $str <= '9' ) || ( '.' === $str )
			|| ( '-' === $str ) || ( '+' === $str ) || ( 'e' === $str ) || ( 'E' === $str );
	}

	/**
	 * Checks if the given character is the beginning of a symbol. A symbol
	 * can be either a variable or a field name.
	 *
	 * @param string $str String to be checked.
	 *
	 * @return int|null The appropriate flag for the symbol type.
	 */
	public static function is_symbol( $str ) {
		if ( 0 === strlen( $str ) ) {
			return null;
		}

		if ( '@' === $str[0] ) {
			return WP_SQLite_Token::FLAG_SYMBOL_VARIABLE;
		}

		if ( '`' === $str[0] ) {
			return WP_SQLite_Token::FLAG_SYMBOL_BACKTICK;
		}

		if ( ':' === $str[0] || '?' === $str[0] ) {
			return WP_SQLite_Token::FLAG_SYMBOL_PARAMETER;
		}

		return null;
	}

	/**
	 * Checks if the given character is the beginning of a string.
	 *
	 * @param string $str String to be checked.
	 *
	 * @return int|null The appropriate flag for the string type.
	 */
	public static function is_string( $str ) {
		if ( strlen( $str ) === 0 ) {
			return null;
		}

		if ( '\'' === $str[0] ) {
			return WP_SQLite_Token::FLAG_STRING_SINGLE_QUOTES;
		}

		if ( '"' === $str[0] ) {
			return WP_SQLite_Token::FLAG_STRING_DOUBLE_QUOTES;
		}

		return null;
	}

	/**
	 * Checks if the given character can be a separator for two lexeme.
	 *
	 * @param string $str String to be checked.
	 *
	 * @return bool
	 */
	public static function is_separator( $str ) {
		/*
		 * NOTES:   Only non alphanumeric ASCII characters may be separators.
		 * `~` is the last printable ASCII character.
		 */
		return ( $str <= '~' )
			&& ( '_' !== $str )
			&& ( '$' !== $str )
			&& ( ( $str < '0' ) || ( $str > '9' ) )
			&& ( ( $str < 'a' ) || ( $str > 'z' ) )
			&& ( ( $str < 'A' ) || ( $str > 'Z' ) );
	}

	/**
	 * Constructor.
	 *
	 * @param stdClass[] $tokens The initial array of tokens.
	 * @param int        $count  The count of tokens in the initial array.
	 */
	public function tokens( array $tokens = array(), $count = -1 ) {
		if ( empty( $tokens ) ) {
			return;
		}

		$this->tokens       = $tokens;
		$this->tokens_count = -1 === $count ? count( $tokens ) : $count;
	}

	/**
	 * Gets the next token.
	 *
	 * @param int $type The type of the token.
	 * @param int $flag The flag of the token.
	 */
	public function tokens_get_next_of_type_and_flag( $type, $flag ) {
		for ( ; $this->tokens_index < $this->tokens_count; ++$this->tokens_index ) {
			if ( ( $this->tokens[ $this->tokens_index ]->type === $type ) && ( $this->tokens[ $this->tokens_index ]->flags === $flag ) ) {
				return $this->tokens[ $this->tokens_index++ ];
			}
		}

		return null;
	}

	/**
	 * Gets the next token.
	 *
	 * @param int    $type  The type of the token.
	 * @param string $value The value of the token.
	 *
	 * @return stdClass|null
	 */
	public function tokens_get_next_of_type_and_value( $type, $value ) {
		for ( ; $this->tokens_index < $this->tokens_count; ++$this->tokens_index ) {
			if ( ( $this->tokens[ $this->tokens_index ]->type === $type ) && ( $this->tokens[ $this->tokens_index ]->value === $value ) ) {
				return $this->tokens[ $this->tokens_index++ ];
			}
		}

		return null;
	}

	/**
	 * Gets the next token. Skips any irrelevant token (whitespaces and
	 * comments).
	 *
	 * @return stdClass|null
	 */
	public function tokens_get_next() {
		for ( ; $this->tokens_index < $this->tokens_count; ++$this->tokens_index ) {
			if (
				( WP_SQLite_Token::TYPE_WHITESPACE !== $this->tokens[ $this->tokens_index ]->type )
				&& ( WP_SQLite_Token::TYPE_COMMENT !== $this->tokens[ $this->tokens_index ]->type )
			) {
				return $this->tokens[ $this->tokens_index++ ];
			}
		}

		return null;
	}
}
