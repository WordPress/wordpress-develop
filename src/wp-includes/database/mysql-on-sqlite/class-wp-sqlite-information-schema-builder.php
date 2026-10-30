<?php

/**
 * SQLite information schema builder for MySQL.
 *
 * This class builds and maintains MySQL INFORMATION_SCHEMA tables in SQLite.
 * It consumes the AST of MySQL DDL queries and records the schema information
 * in SQLite tables that emulate the MySQL INFORMATION_SCHEMA.
 *
 * @access private
 */
class WP_SQLite_Information_Schema_Builder {
	/**
	 * The name of the database that is saved in the information schema tables.
	 *
	 * The SQLite driver injects the configured database name dynamically,
	 * but we need to store some value in the information schema tables.
	 * This database name will also be visible in SQLite admin tools.
	 *
	 * @var string
	 */
	const SAVED_DATABASE_NAME = 'sqlite_database';

	/**
	 * SQL definitions for tables that emulate MySQL "information_schema".
	 *
	 * The full MySQL information schema comprises a large number of tables:
	 *   https://dev.mysql.com/doc/refman/8.4/en/information-schema-table-reference.html
	 *
	 * We only implement a limited subset that is necessary for a database schema
	 * introspection and representation, currently covering the following tables:
	 *
	 *  - SCHEMATA
	 *  - TABLES
	 *  - COLUMNS
	 *  - STATISTICS (indexes)
	 *  - TABLE_CONSTRAINTS
	 *  - CHECK_CONSTRAINTS
	 *
	 * TODO (not yet implemented):
	 *  - VIEWS
	 *  - TRIGGERS
	 */
	const INFORMATION_SCHEMA_TABLE_DEFINITIONS = array(
		// INFORMATION_SCHEMA.SCHEMATA
		'schemata'                => "
			CATALOG_NAME TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE,      -- always 'def'
			SCHEMA_NAME TEXT NOT NULL COLLATE NOCASE,                     -- database name
			DEFAULT_CHARACTER_SET_NAME TEXT NOT NULL COLLATE NOCASE,      -- default character set
			DEFAULT_COLLATION_NAME TEXT NOT NULL COLLATE NOCASE,          -- default collation
			SQL_PATH TEXT NULL COLLATE NOCASE,                            -- always NULL
			DEFAULT_ENCRYPTION TEXT NOT NULL DEFAULT 'NO' COLLATE NOCASE, -- not implemented
			PRIMARY KEY (SCHEMA_NAME)
		",

		// INFORMATION_SCHEMA.TABLES
		'tables'                  => "
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE, -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL COLLATE NOCASE,                -- database name
			TABLE_NAME TEXT NOT NULL COLLATE NOCASE,                  -- table name
			TABLE_TYPE TEXT NOT NULL COLLATE BINARY,                  -- 'BASE TABLE', 'VIEW', or 'SYSTEM VIEW'
			ENGINE TEXT NOT NULL COLLATE NOCASE,                      -- storage engine
			VERSION INTEGER NOT NULL DEFAULT 10,                      -- unused, in MySQL 8 hardcoded to 10
			ROW_FORMAT TEXT NOT NULL COLLATE BINARY,                  -- row storage format @TODO - implement
			TABLE_ROWS INTEGER NOT NULL DEFAULT 0,                    -- not implemented
			AVG_ROW_LENGTH INTEGER NOT NULL DEFAULT 0,                -- not implemented
			DATA_LENGTH INTEGER NOT NULL DEFAULT 0,                   -- not implemented
			MAX_DATA_LENGTH INTEGER NOT NULL DEFAULT 0,               -- not implemented
			INDEX_LENGTH INTEGER NOT NULL DEFAULT 0,                  -- not implemented
			DATA_FREE INTEGER NOT NULL DEFAULT 0,                     -- not implemented
			AUTO_INCREMENT INTEGER,                                   -- not implemented
			CREATE_TIME TEXT NOT NULL                                 -- table creation timestamp
				DEFAULT CURRENT_TIMESTAMP,
			UPDATE_TIME TEXT,                                         -- table update time
			CHECK_TIME TEXT,                                          -- not implemented
			TABLE_COLLATION TEXT NOT NULL COLLATE NOCASE,             -- table collation
			CHECKSUM INTEGER,                                         -- not implemented
			CREATE_OPTIONS TEXT NOT NULL DEFAULT '' COLLATE NOCASE,   -- extra CREATE TABLE options
			TABLE_COMMENT TEXT NOT NULL DEFAULT '' COLLATE NOCASE,    -- comment
			PRIMARY KEY (TABLE_SCHEMA, TABLE_NAME)
		",

		// INFORMATION_SCHEMA.COLUMNS
		'columns'                 => "
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE,      -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL COLLATE NOCASE,                     -- database name
			TABLE_NAME TEXT NOT NULL COLLATE NOCASE,                       -- table name
			COLUMN_NAME TEXT NOT NULL COLLATE NOCASE,                      -- column name
			ORDINAL_POSITION INTEGER NOT NULL,                             -- column position
			COLUMN_DEFAULT TEXT COLLATE BINARY,                            -- default value, NULL for both NULL and none
			IS_NULLABLE TEXT NOT NULL COLLATE NOCASE,                      -- 'YES' or 'NO'
			DATA_TYPE TEXT NOT NULL COLLATE BINARY,                        -- data type (without length, precision, etc.)
			CHARACTER_MAXIMUM_LENGTH INTEGER,                              -- max length for string columns in characters
			CHARACTER_OCTET_LENGTH INTEGER,                                -- max length for string columns in bytes
			NUMERIC_PRECISION INTEGER,                                     -- number precision for numeric columns
			NUMERIC_SCALE INTEGER,                                         -- number scale for numeric columns
			DATETIME_PRECISION INTEGER,                                    -- fractional seconds precision for temporal columns
			CHARACTER_SET_NAME TEXT COLLATE NOCASE,                        -- charset for string columns
			COLLATION_NAME TEXT COLLATE NOCASE,                            -- collation for string columns
			COLUMN_TYPE TEXT NOT NULL COLLATE BINARY,                      -- full data type (with length, precision, etc.)
			COLUMN_KEY TEXT NOT NULL DEFAULT '' COLLATE BINARY,            -- if column is indexed ('', 'PRI', 'UNI', 'MUL')
			EXTRA TEXT NOT NULL DEFAULT '' COLLATE NOCASE,                 -- AUTO_INCREMENT, VIRTUAL, STORED, etc.
			PRIVILEGES TEXT NOT NULL COLLATE NOCASE,                       -- not implemented
			COLUMN_COMMENT TEXT NOT NULL DEFAULT '' COLLATE BINARY,        -- comment
			GENERATION_EXPRESSION TEXT NOT NULL DEFAULT '' COLLATE BINARY, -- expression for generated columns
			SRS_ID INTEGER,                                                -- not implemented
			PRIMARY KEY (TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME)
		",

		// INFORMATION_SCHEMA.STATISTICS (indexes)
		'statistics'              => "
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE, -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL COLLATE NOCASE,                -- database name
			TABLE_NAME TEXT NOT NULL COLLATE NOCASE,                  -- table name
			NON_UNIQUE INTEGER NOT NULL,                              -- 0 for unique indexes, 1 otherwise
			INDEX_SCHEMA TEXT NOT NULL COLLATE NOCASE,                -- index database name
			INDEX_NAME TEXT NOT NULL COLLATE NOCASE,                  -- index name, for PKs always 'PRIMARY'
			SEQ_IN_INDEX INTEGER NOT NULL,                            -- column position in index (from 1)
			COLUMN_NAME TEXT COLLATE NOCASE,                          -- column name (NULL for functional indexes)
			COLLATION TEXT COLLATE NOCASE,                            -- column sort in the index ('A', 'D', or NULL)
			CARDINALITY INTEGER,                                      -- not implemented
			SUB_PART INTEGER,                                         -- number of indexed chars, NULL for full column
			PACKED TEXT,                                              -- not implemented
			NULLABLE TEXT NOT NULL COLLATE NOCASE,                    -- 'YES' if column can contain NULL, '' otherwise
			INDEX_TYPE TEXT NOT NULL COLLATE BINARY,                  -- 'BTREE', 'FULLTEXT', 'SPATIAL'
			COMMENT TEXT NOT NULL DEFAULT '' COLLATE NOCASE,          -- not implemented
			INDEX_COMMENT TEXT NOT NULL DEFAULT '' COLLATE BINARY,    -- index comment
			IS_VISIBLE TEXT NOT NULL DEFAULT 'YES' COLLATE NOCASE,    -- 'NO' if column is hidden, 'YES' otherwise
			EXPRESSION TEXT COLLATE BINARY,                           -- expression for functional indexes
			PRIMARY KEY (TABLE_SCHEMA, TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX),
			UNIQUE (INDEX_SCHEMA, TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX)
		",

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		'table_constraints'       => "
			CONSTRAINT_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE, -- always 'def'
			CONSTRAINT_SCHEMA TEXT NOT NULL COLLATE NOCASE,                -- constraint database name
			CONSTRAINT_NAME TEXT NOT NULL COLLATE NOCASE,                  -- constraint name
			TABLE_SCHEMA TEXT NOT NULL COLLATE NOCASE,                     -- table database name
			TABLE_NAME TEXT NOT NULL COLLATE NOCASE,                       -- table name
			CONSTRAINT_TYPE TEXT NOT NULL COLLATE BINARY,                  -- constraint type ('PRIMARY KEY', 'UNIQUE', 'FOREIGN KEY', 'CHECK')
			ENFORCED TEXT NOT NULL DEFAULT 'YES' COLLATE BINARY,           -- 'YES' if constraint is enforced, 'NO' otherwise

			-- Constraint names are unique per type in each table.
			-- A MySQL table can have a PRIMARY KEY, UNIQUE, FOREIGN KEY, and CHECK
			-- constraints with the same name, but the name must be unique per type.
			-- CHECK and FOREIGN KEY constraint names must also be unique per schema.
			PRIMARY KEY (TABLE_SCHEMA, TABLE_NAME, CONSTRAINT_TYPE, CONSTRAINT_NAME),
			UNIQUE (CONSTRAINT_SCHEMA, TABLE_NAME, CONSTRAINT_TYPE, CONSTRAINT_NAME)
		",

		// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
		'referential_constraints' => "
			CONSTRAINT_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE,        -- always 'def'
			CONSTRAINT_SCHEMA TEXT NOT NULL COLLATE NOCASE,                       -- constraint database name
			CONSTRAINT_NAME TEXT NOT NULL COLLATE NOCASE,                         -- constraint name
			UNIQUE_CONSTRAINT_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE, -- always 'def'
			UNIQUE_CONSTRAINT_SCHEMA TEXT NOT NULL COLLATE NOCASE,                -- referenced unique constraint database name
			UNIQUE_CONSTRAINT_NAME TEXT COLLATE NOCASE,                           -- referenced unique constraint name or NULL
			MATCH_OPTION TEXT NOT NULL COLLATE NOCASE DEFAULT 'NONE',             -- always 'NONE'
			UPDATE_RULE TEXT NOT NULL COLLATE NOCASE,                             -- 'CASCADE', 'SET NULL', 'SET DEFAULT', 'RESTRICT', 'NO ACTION'
			DELETE_RULE TEXT NOT NULL COLLATE NOCASE,                             -- 'CASCADE', 'SET NULL', 'SET DEFAULT', 'RESTRICT', 'NO ACTION'
			TABLE_NAME TEXT NOT NULL COLLATE NOCASE,                              -- table name
			REFERENCED_TABLE_NAME TEXT NOT NULL COLLATE NOCASE,                   -- referenced table name
			PRIMARY KEY (CONSTRAINT_SCHEMA, CONSTRAINT_NAME)
		",

		// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		'key_column_usage'        => "
			CONSTRAINT_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE, -- always 'def'
			CONSTRAINT_SCHEMA TEXT NOT NULL COLLATE NOCASE,                -- constraint database name
			CONSTRAINT_NAME TEXT NOT NULL COLLATE NOCASE,                  -- constraint name
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE,      -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL COLLATE NOCASE,                     -- table database name
			TABLE_NAME TEXT NOT NULL COLLATE NOCASE,                       -- table name
			COLUMN_NAME TEXT NOT NULL COLLATE NOCASE,                      -- column name
			ORDINAL_POSITION INTEGER NOT NULL,                             -- column position
			POSITION_IN_UNIQUE_CONSTRAINT INTEGER,                         -- column position in referenced unique constraint
			REFERENCED_TABLE_SCHEMA TEXT COLLATE NOCASE,                   -- referenced table database name
			REFERENCED_TABLE_NAME TEXT COLLATE NOCASE,                     -- referenced table name
			REFERENCED_COLUMN_NAME TEXT COLLATE NOCASE,                    -- referenced column name
			UNIQUE (CONSTRAINT_SCHEMA, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA)
		",

		// INFORMATION_SCHEMA.CHECK_CONSTRAINTS
		'check_constraints'       => "
			CONSTRAINT_CATALOG TEXT NOT NULL DEFAULT 'def' COLLATE NOCASE, -- always 'def'
			CONSTRAINT_SCHEMA TEXT NOT NULL COLLATE NOCASE,                -- constraint database name
			CONSTRAINT_NAME TEXT NOT NULL COLLATE NOCASE,                  -- constraint name
			CHECK_CLAUSE TEXT NOT NULL COLLATE BINARY,                     -- check clause
			PRIMARY KEY (CONSTRAINT_SCHEMA, CONSTRAINT_NAME)
		",
	);

	/**
	 * A mapping of MySQL tokens to normalized MySQL data types.
	 * This is used to store column data types in the information schema.
	 */
	const TOKEN_TO_TYPE_MAP = array(
		WP_MySQL_Lexer::INT_SYMBOL                => 'int',
		WP_MySQL_Lexer::TINYINT_SYMBOL            => 'tinyint',
		WP_MySQL_Lexer::SMALLINT_SYMBOL           => 'smallint',
		WP_MySQL_Lexer::MEDIUMINT_SYMBOL          => 'mediumint',
		WP_MySQL_Lexer::BIGINT_SYMBOL             => 'bigint',
		WP_MySQL_Lexer::REAL_SYMBOL               => 'double',
		WP_MySQL_Lexer::DOUBLE_SYMBOL             => 'double',
		WP_MySQL_Lexer::FLOAT_SYMBOL              => 'float',
		WP_MySQL_Lexer::DECIMAL_SYMBOL            => 'decimal',
		WP_MySQL_Lexer::NUMERIC_SYMBOL            => 'decimal',
		WP_MySQL_Lexer::FIXED_SYMBOL              => 'decimal',
		WP_MySQL_Lexer::BIT_SYMBOL                => 'bit',
		WP_MySQL_Lexer::BOOL_SYMBOL               => 'tinyint',
		WP_MySQL_Lexer::BOOLEAN_SYMBOL            => 'tinyint',
		WP_MySQL_Lexer::BINARY_SYMBOL             => 'binary',
		WP_MySQL_Lexer::VARBINARY_SYMBOL          => 'varbinary',
		WP_MySQL_Lexer::YEAR_SYMBOL               => 'year',
		WP_MySQL_Lexer::DATE_SYMBOL               => 'date',
		WP_MySQL_Lexer::TIME_SYMBOL               => 'time',
		WP_MySQL_Lexer::TIMESTAMP_SYMBOL          => 'timestamp',
		WP_MySQL_Lexer::DATETIME_SYMBOL           => 'datetime',
		WP_MySQL_Lexer::TINYBLOB_SYMBOL           => 'tinyblob',
		WP_MySQL_Lexer::BLOB_SYMBOL               => 'blob',
		WP_MySQL_Lexer::MEDIUMBLOB_SYMBOL         => 'mediumblob',
		WP_MySQL_Lexer::LONGBLOB_SYMBOL           => 'longblob',
		WP_MySQL_Lexer::TINYTEXT_SYMBOL           => 'tinytext',
		WP_MySQL_Lexer::TEXT_SYMBOL               => 'text',
		WP_MySQL_Lexer::MEDIUMTEXT_SYMBOL         => 'mediumtext',
		WP_MySQL_Lexer::LONGTEXT_SYMBOL           => 'longtext',
		WP_MySQL_Lexer::ENUM_SYMBOL               => 'enum',
		WP_MySQL_Lexer::SET_SYMBOL                => 'set',
		WP_MySQL_Lexer::SERIAL_SYMBOL             => 'bigint',
		WP_MySQL_Lexer::GEOMETRY_SYMBOL           => 'geometry',
		WP_MySQL_Lexer::GEOMETRYCOLLECTION_SYMBOL => 'geomcollection',
		WP_MySQL_Lexer::POINT_SYMBOL              => 'point',
		WP_MySQL_Lexer::MULTIPOINT_SYMBOL         => 'multipoint',
		WP_MySQL_Lexer::LINESTRING_SYMBOL         => 'linestring',
		WP_MySQL_Lexer::MULTILINESTRING_SYMBOL    => 'multilinestring',
		WP_MySQL_Lexer::POLYGON_SYMBOL            => 'polygon',
		WP_MySQL_Lexer::MULTIPOLYGON_SYMBOL       => 'multipolygon',
		WP_MySQL_Lexer::JSON_SYMBOL               => 'json',
	);

	/**
	 * The default collation for each MySQL charset.
	 * This is needed as collation is not always specified in a query.
	 */
	const CHARSET_DEFAULT_COLLATION_MAP = array(
		'armscii8' => 'armscii8_general_ci',
		'ascii'    => 'ascii_general_ci',
		'big5'     => 'big5_chinese_ci',
		'binary'   => 'binary',
		'cp1250'   => 'cp1250_general_ci',
		'cp1251'   => 'cp1251_general_ci',
		'cp1256'   => 'cp1256_general_ci',
		'cp1257'   => 'cp1257_general_ci',
		'cp850'    => 'cp850_general_ci',
		'cp852'    => 'cp852_general_ci',
		'cp866'    => 'cp866_general_ci',
		'cp932'    => 'cp932_japanese_ci',
		'dec8'     => 'dec8_swedish_ci',
		'eucjpms'  => 'eucjpms_japanese_ci',
		'euckr'    => 'euckr_korean_ci',
		'gb18030'  => 'gb18030_chinese_ci',
		'gb2312'   => 'gb2312_chinese_ci',
		'gbk'      => 'gbk_chinese_ci',
		'geostd8'  => 'geostd8_general_ci',
		'greek'    => 'greek_general_ci',
		'hebrew'   => 'hebrew_general_ci',
		'hp8'      => 'hp8_english_ci',
		'keybcs2'  => 'keybcs2_general_ci',
		'koi8r'    => 'koi8r_general_ci',
		'koi8u'    => 'koi8u_general_ci',
		'latin1'   => 'latin1_swedish_ci',
		'latin2'   => 'latin2_general_ci',
		'latin5'   => 'latin5_turkish_ci',
		'latin7'   => 'latin7_general_ci',
		'macce'    => 'macce_general_ci',
		'macroman' => 'macroman_general_ci',
		'sjis'     => 'sjis_japanese_ci',
		'swe7'     => 'swe7_swedish_ci',
		'tis620'   => 'tis620_thai_ci',
		'ucs2'     => 'ucs2_general_ci',
		'ujis'     => 'ujis_japanese_ci',
		'utf16'    => 'utf16_general_ci',
		'utf16le'  => 'utf16le_general_ci',
		'utf32'    => 'utf32_general_ci',
		'utf8'     => 'utf8_general_ci',
		'utf8mb4'  => 'utf8mb4_0900_ai_ci', // @TODO: This should probably be version-dependent.
											//        Before MySQL 8, the default was different.
	);

	/**
	 * Maximum number of bytes per character for each charset.
	 * The map contains only multi-byte charsets.
	 * Charsets that are not included are single-byte.
	 */
	const CHARSET_MAX_BYTES_MAP = array(
		'big5'    => 2,
		'cp932'   => 2,
		'eucjpms' => 3,
		'euckr'   => 2,
		'gb18030' => 4,
		'gb2312'  => 2,
		'gbk'     => 2,
		'sjis'    => 2,
		'ucs2'    => 2,
		'ujis'    => 3,
		'utf16'   => 4,
		'utf16le' => 4,
		'utf32'   => 4,
		'utf8'    => 3,
		'utf8mb4' => 4,
	);

	/**
	 * Zero values of temporal data types, without fractional seconds.
	 */
	const TEMPORAL_ZERO_VALUE_MAP = array(
		'date'      => '0000-00-00',
		'time'      => '00:00:00',
		'datetime'  => '0000-00-00 00:00:00',
		'timestamp' => '0000-00-00 00:00:00',
		'year'      => '0000',
	);

	/**
	 * A prefix for information schema table names.
	 *
	 * @var string
	 */
	private $table_prefix;

	/**
	 * A prefix for information schema table names for temporary tables.
	 *
	 * This is needed because for temporary tables, we store the information
	 * schema tables as temporary tables as well, and temporary tables with
	 * the same name as regular tables would override the regular tables.
	 *
	 * @var string
	 */
	private $temporary_table_prefix;

	/**
	 * Whether the information schema for temporary tables was already created.
	 *
	 * This is used to avoid trying to create a temporary information schema
	 * for each CREATE TEMPORARY TABLE statement during a single session.
	 *
	 * @var bool
	 */
	private $temporary_information_schema_exists = false;

	/**
	 * An instance of the SQLite connection.
	 *
	 * @var WP_SQLite_Connection
	 */
	private $connection;

	/**
	 * Constructor.
	 *
	 * @param string               $reserved_prefix An identifier prefix for internal database objects.
	 * @param WP_SQLite_Connection $connection      An instance of the SQLite connection.
	 */
	public function __construct( string $reserved_prefix, WP_SQLite_Connection $connection ) {
		$this->connection             = $connection;
		$this->table_prefix           = $reserved_prefix . 'mysql_information_schema_';
		$this->temporary_table_prefix = $reserved_prefix . 'mysql_information_schema_tmp_';
	}

	/**
	 * Get SQLite table name for the given MySQL information schema table name.
	 *
	 * @param  bool   $table_is_temporary            Whether a temporary table information schema is requested.
	 * @param  string $information_schema_table_name The MySQL information schema table name.
	 * @return string                                The SQLite table name.
	 */
	public function get_table_name( bool $table_is_temporary, string $information_schema_table_name ): string {
		$prefix = $table_is_temporary ? $this->temporary_table_prefix : $this->table_prefix;
		return $prefix . $information_schema_table_name;
	}

	/**
	 * Check if a temporary table exists in the SQLite database.
	 *
	 * @param  string $table_name The temporary table name.
	 * @return bool               True if the temporary table exists, false otherwise.
	 */
	public function temporary_table_exists( string $table_name ): bool {
		/*
		 * We could search in the "{$this->temporary_table_prefix}tables" table,
		 * but it may not exist yet, so using "sqlite_temp_master" is simpler.
		 *
		 * The name is matched case-insensitively, as it is in the information
		 * schema tables. The fetched value is not compared to a string, because
		 * PDO SQLite returns native types when fetches are not stringified.
		 */
		$stmt = $this->connection->query(
			"SELECT 1 FROM sqlite_temp_master WHERE type = 'table' AND name = ? COLLATE NOCASE",
			array( $table_name )
		);
		return false !== $stmt->fetchColumn();
	}

	/**
	 * Ensure that the information schema tables exist in the SQLite
	 * database. Tables that are missing will be created.
	 */
	public function ensure_information_schema_tables(): void {
		$sqlite_version         = $this->connection->get_pdo()->getAttribute( PDO::ATTR_SERVER_VERSION ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		$supports_strict_tables = version_compare( $sqlite_version, '3.37.0', '>=' );
		foreach ( self::INFORMATION_SCHEMA_TABLE_DEFINITIONS as $table_name => $table_body ) {
			$this->connection->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS %s%s (%s)%s',
					$this->table_prefix,
					$table_name,
					$table_body,
					$supports_strict_tables ? ' STRICT' : ''
				)
			);
		}
	}

	/**
	 * Get the definition and data of a computed information schema table.
	 *
	 * Some information schema tables can be computed on the fly when they are
	 * referenced in a query. This method provides their definitions and data.
	 *
	 * @param  string $table_name The table name.
	 * @return string|null        The table definition and data, or null if
	 *                            the table is not a computed table.
	 */
	public function get_computed_information_schema_table_definition( string $table_name ): ?string {
		switch ( strtolower( $table_name ) ) {
			case 'character_sets':
				return "SELECT
						column1 AS CHARACTER_SET_NAME,
						column2 AS DEFAULT_COLLATE_NAME,
						column3 AS DESCRIPTION,
						column4 AS MAXLEN
					FROM (
					VALUES
						('binary', 'binary', 'Binary pseudo charset', 1),
						('utf8', 'utf8_general_ci', 'UTF-8 Unicode', 3),
						('utf8mb4', 'utf8mb4_0900_ai_ci', 'UTF-8 Unicode', 4)
					)";
			case 'collations':
				return "SELECT
					column1 AS COLLATION_NAME,
					column2 AS CHARACTER_SET_NAME,
					column3 AS ID,
					column4 AS IS_DEFAULT,
					column5 AS IS_COMPILED,
					column6 AS SORTLEN,
					column7 AS PAD_ATTRIBUTE
				FROM (
				VALUES
					('binary', 'binary', 63, 'Yes', 'Yes', 1, 'NO PAD'),
					('utf8_bin', 'utf8', 83, '', 'Yes', 1, 'PAD SPACE'),
					('utf8_general_ci', 'utf8', 33, 'Yes', 'Yes', 1, 'PAD SPACE'),
					('utf8_unicode_ci', 'utf8', 192, '', 'Yes', 8, 'PAD SPACE'),
					('utf8mb4_bin', 'utf8mb4', 46, '', 'Yes', 1, 'PAD SPACE'),
					('utf8mb4_unicode_ci', 'utf8mb4', 224, '', 'Yes', 8, 'PAD SPACE'),
					('utf8mb4_unicode_520_ci', 'utf8mb4', 246, '', 'Yes', 8, 'PAD SPACE'),
					('utf8mb4_0900_ai_ci', 'utf8mb4', 255, 'Yes', 'Yes', 0, 'NO PAD')
				)";
			default:
				return null;
		}
	}

	/**
	 * Ensure that the temporary information schema tables exist in
	 * the SQLite database. Tables that are missing will be created.
	 */
	public function ensure_temporary_information_schema_tables(): void {
		$sqlite_version         = $this->connection->get_pdo()->getAttribute( PDO::ATTR_SERVER_VERSION ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		$supports_strict_tables = version_compare( $sqlite_version, '3.37.0', '>=' );
		foreach ( self::INFORMATION_SCHEMA_TABLE_DEFINITIONS as $table_name => $table_body ) {
			// Skip the "schemata" table; MySQL doesn't support temporary databases.
			if ( 'schemata' === $table_name ) {
				continue;
			}

			$this->connection->query(
				sprintf(
					'CREATE TEMPORARY TABLE IF NOT EXISTS %s%s (%s)%s',
					$this->temporary_table_prefix,
					$table_name,
					$table_body,
					$supports_strict_tables ? ' STRICT' : ''
				)
			);
		}
		$this->temporary_information_schema_exists = true;
	}

	/**
	 * Analyze CREATE TABLE statement and record data in the information schema.
	 *
	 * @param WP_Parser_Node $node The "createStatement" AST node with "createTable" child.
	 */
	public function record_create_table( WP_Parser_Node $node ): void {
		$table_name_node  = $node->get_first_descendant_node( 'tableName' );
		$table_name       = $this->get_table_name_from_node( $table_name_node );
		$table_engine     = $this->get_table_engine( $node );
		$table_row_format = 'MyISAM' === $table_engine ? 'Fixed' : 'Dynamic';
		$table_collation  = $this->get_table_collation( $node );
		$table_comment    = $this->get_table_comment( $node );

		/*
		 * When creating a temporary table:
		 *   1. Track that we're processing a temporary table.
		 *   2. Ensure that the temporary information schema tables exist.
		 */
		$subnode            = $node->get_first_child_node();
		$table_is_temporary = $subnode->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );
		if ( $table_is_temporary && ! $this->temporary_information_schema_exists ) {
			$this->ensure_temporary_information_schema_tables();
		}

		// 1. Table.
		$tables_table_name = $this->get_table_name( $table_is_temporary, 'tables' );
		$table_data        = array(
			'table_schema'    => self::SAVED_DATABASE_NAME,
			'table_name'      => $table_name,
			'table_type'      => 'BASE TABLE',
			'engine'          => $table_engine,
			'row_format'      => $table_row_format,
			'table_collation' => $table_collation,
			'table_comment'   => $table_comment,
		);

		try {
			$this->insert_values( $tables_table_name, $table_data );
		} catch ( PDOException $e ) {
			/*
			 * Even though we keep track of whether the temporary information
			 * schema tables already exist, there is a special case in which
			 * the tracked information may be incorrect.
			 *
			 * This can happen when the query is in a transaction that is later
			 * rolled back. In that case, let's ensure the schema, and try again.
			 */
			if ( $table_is_temporary && str_contains( $e->getMessage(), 'no such table' ) ) {
				$this->ensure_temporary_information_schema_tables();
				try {
					$e = null;
					$this->insert_values( $tables_table_name, $table_data );
				} catch ( PDOException $retry_exception ) {
					$e = $retry_exception;
				}
			}

			if ( $e ) {
				if ( '23000' === $e->getCode() ) {
					throw WP_SQLite_Information_Schema_Exception::duplicate_table_name( $table_name );
				} else {
					throw $e;
				}
			}
		}

		// 2. Columns.
		$column_position = 1;
		foreach ( $node->get_descendant_nodes( 'columnDefinition' ) as $column_node ) {
			$column_name = $this->get_value( $column_node->get_first_child_node( 'fieldIdentifier' ) );

			// Column definition.
			$column_data = $this->extract_column_data(
				$table_name,
				$column_name,
				$column_node,
				$column_position
			);

			try {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'columns' ),
					$column_data
				);
			} catch ( PDOException $e ) {
				if ( '23000' === $e->getCode() ) {
					throw WP_SQLite_Information_Schema_Exception::duplicate_column_name( $column_name );
				}
				throw $e;
			}

			// Extract inline column constraints and indexes.
			$index_data                  = $this->extract_column_statistics_data(
				$table_name,
				$column_name,
				$column_node,
				'YES' === $column_data['is_nullable']
			);
			$constraint_data             = $this->extract_table_constraint_data(
				$column_node,
				$table_name,
				$index_data['index_name'] ?? null
			);
			$referential_constraint_data = $this->extract_referential_constraint_data(
				$column_node,
				$table_name
			);
			$key_column_usage_data       = $this->extract_key_column_usage_data(
				$column_node,
				$table_name,
				$index_data['index_name'] ?? null
			);
			$check_constraint_data       = $this->extract_check_constraint_data(
				$column_node,
				$table_name
			);

			// Save inline column constraints and indexes.
			if ( null !== $index_data ) {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'statistics' ),
					$index_data
				);
			}
			if ( null !== $constraint_data ) {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'table_constraints' ),
					$constraint_data
				);
			}
			if ( null !== $referential_constraint_data ) {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'referential_constraints' ),
					$referential_constraint_data
				);
			}
			foreach ( $key_column_usage_data as $key_column_usage_item ) {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'key_column_usage' ),
					$key_column_usage_item
				);
			}
			if ( null !== $check_constraint_data ) {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'check_constraints' ),
					$check_constraint_data
				);
			}

			$column_position += 1;
		}

		// 3. Constraints and indexes.
		foreach ( $node->get_descendant_nodes( 'tableConstraintDef' ) as $constraint_node ) {
			$this->record_add_constraint_or_index( $table_is_temporary, $table_name, $constraint_node );
		}
	}

	/**
	 * Analyze ALTER TABLE statement and record data in the information schema.
	 *
	 * @param WP_Parser_Node $node The "alterStatement" AST node with "alterTable" child.
	 */
	public function record_alter_table( WP_Parser_Node $node ): void {
		$table_ref  = $node->get_first_descendant_node( 'tableRef' );
		$table_name = $this->get_table_name_from_node( $table_ref );
		$actions    = $node->get_descendant_nodes( 'alterListItem' );

		// Check if a temporary table with the given name exists.
		$table_is_temporary = $this->temporary_table_exists( $table_name );

		foreach ( $actions as $action ) {
			$first_token = $action->get_first_child_token();

			// ADD
			if ( WP_MySQL_Lexer::ADD_SYMBOL === $first_token->id ) {
				// ADD [COLUMN] (...[, ...])
				$table_element_list = $action->get_first_child_node( 'tableElementList' );
				if ( null !== $table_element_list ) {
					foreach ( $table_element_list->get_descendant_nodes( 'columnDefinition' ) as $column_definition ) {
						$name = $this->get_value( $column_definition->get_first_child_node( 'fieldIdentifier' ) );
						$this->record_add_column( $table_is_temporary, $table_name, $name, $column_definition );
					}
					foreach ( $table_element_list->get_descendant_nodes( 'tableConstraintDef' ) as $constraint ) {
						$this->record_add_constraint_or_index( $table_is_temporary, $table_name, $constraint );
					}
					continue;
				}

				// ADD [COLUMN] ...
				$field_definition = $action->get_first_descendant_node( 'fieldDefinition' );
				if ( null !== $field_definition ) {
					$name = $this->get_value( $action->get_first_child_node( 'identifier' ) );
					$this->record_add_column( $table_is_temporary, $table_name, $name, $field_definition );
					// @TODO: Handle FIRST/AFTER.
					continue;
				}

				// ADD constraint or index.
				$constraint = $action->get_first_descendant_node( 'tableConstraintDef' );
				if ( null !== $constraint ) {
					$this->record_add_constraint_or_index( $table_is_temporary, $table_name, $constraint );
					continue;
				}

				throw new \Exception( sprintf( 'Unsupported ALTER TABLE ADD action: %s', $first_token->get_value() ) );
			}

			// CHANGE [COLUMN]
			if ( WP_MySQL_Lexer::CHANGE_SYMBOL === $first_token->id ) {
				$old_name = $this->get_value( $action->get_first_child_node( 'fieldIdentifier' ) );
				$new_name = $this->get_value( $action->get_first_child_node( 'identifier' ) );
				$this->record_change_column(
					$table_is_temporary,
					$table_name,
					$old_name,
					$new_name,
					$action->get_first_descendant_node( 'fieldDefinition' )
				);
				continue;
			}

			// MODIFY [COLUMN]
			if ( WP_MySQL_Lexer::MODIFY_SYMBOL === $first_token->id ) {
				$name = $this->get_value( $action->get_first_child_node( 'fieldIdentifier' ) );
				$this->record_modify_column(
					$table_is_temporary,
					$table_name,
					$name,
					$action->get_first_descendant_node( 'fieldDefinition' )
				);
				continue;
			}

			// DROP
			if ( WP_MySQL_Lexer::DROP_SYMBOL === $first_token->id ) {
				// DROP CONSTRAINT
				if ( $action->has_child_token( WP_MySQL_Lexer::CONSTRAINT_SYMBOL ) ) {
					$name = $this->get_value( $action->get_first_child_node( 'identifier' ) );
					$this->record_drop_constraint( $table_is_temporary, $table_name, $name );
					continue;
				}

				// DROP PRIMARY KEY
				if ( $action->has_child_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
					$this->record_drop_key( $table_is_temporary, $table_name, 'PRIMARY' );
					continue;
				}

				// DROP FOREIGN KEY
				if ( $action->has_child_token( WP_MySQL_Lexer::FOREIGN_SYMBOL ) ) {
					$field_identifier = $action->get_first_child_node( 'fieldIdentifier' );
					$identifiers      = $field_identifier->get_descendant_nodes( 'identifier' );
					$name             = $this->get_value( end( $identifiers ) );
					$this->record_drop_foreign_key( $table_is_temporary, $table_name, $name );
					continue;
				}

				// DROP CHECK
				if ( $action->has_child_token( WP_MySQL_Lexer::CHECK_SYMBOL ) ) {
					$name = $this->get_value( $action->get_first_child_node( 'identifier' ) );
					$this->record_drop_check_constraint( $table_is_temporary, $table_name, $name );
					continue;
				}

				// DROP [COLUMN]
				$column_ref = $action->get_first_child_node( 'fieldIdentifier' );
				if ( null !== $column_ref ) {
					$name = $this->get_value( $column_ref );
					$this->record_drop_column( $table_is_temporary, $table_name, $name );
					continue;
				}

				// DROP INDEX
				if ( $action->has_child_node( 'keyOrIndex' ) ) {
					$name = $this->get_value( $action->get_first_child_node( 'indexRef' ) );
					$this->record_drop_index_data( $table_is_temporary, $table_name, $name );
					continue;
				}
			}

			// ALTER [COLUMN]
			if ( WP_MySQL_Lexer::ALTER_SYMBOL === $first_token->id ) {
				// SET DEFAULT and DROP DEFAULT
				$column_ref = $action->get_first_child_node( 'fieldIdentifier' );
				if ( null !== $column_ref && $action->has_child_token( WP_MySQL_Lexer::DEFAULT_SYMBOL ) ) {
					$name = $this->get_value( $column_ref );
					$this->record_alter_column_default( $table_is_temporary, $table_name, $name, $action );
				}
			}
		}
	}

	/**
	 * Analyze DROP TABLE statement and record data in the information schema.
	 *
	 * @param WP_Parser_Node $node The "dropStatement" AST node with "dropTable" child.
	 */
	public function record_drop_table( WP_Parser_Node $node ): void {
		$child_node = $node->get_first_child_node();

		$has_temporary_keyword = $child_node->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );

		$table_refs = $child_node->get_first_child_node( 'tableRefList' )->get_child_nodes();
		foreach ( $table_refs as $table_ref ) {
			$table_name         = $this->get_table_name_from_node( $table_ref );
			$table_is_temporary = $this->temporary_table_exists( $table_name );

			/*
			 * DROP TEMPORARY TABLE drops only temporary tables. When there is no
			 * such table, there is nothing to record. The temporary information
			 * schema tables may not even exist yet.
			 */
			if ( $has_temporary_keyword && ! $table_is_temporary ) {
				continue;
			}

			$this->delete_values(
				$this->get_table_name( $table_is_temporary, 'tables' ),
				array(
					'table_schema' => self::SAVED_DATABASE_NAME,
					'table_name'   => $table_name,
				)
			);
			$this->delete_values(
				$this->get_table_name( $table_is_temporary, 'columns' ),
				array(
					'table_schema' => self::SAVED_DATABASE_NAME,
					'table_name'   => $table_name,
				)
			);
			$this->delete_values(
				$this->get_table_name( $table_is_temporary, 'statistics' ),
				array(
					'table_schema' => self::SAVED_DATABASE_NAME,
					'table_name'   => $table_name,
				)
			);
			$this->delete_values(
				$this->get_table_name( $table_is_temporary, 'table_constraints' ),
				array(
					'table_schema' => self::SAVED_DATABASE_NAME,
					'table_name'   => $table_name,
				)
			);
		}

		// @TODO: RESTRICT vs. CASCADE
	}

	/**
	 * Analyze CREATE INDEX definition and record data in the information schema.
	 *
	 * @param WP_Parser_Node $node The "createStatement" AST node with "createIndex" child.
	 */
	public function record_create_index( WP_Parser_Node $node ): void {
		$create_index = $node->get_first_child_node( 'createIndex' );
		$target       = $create_index->get_first_child_node( 'createIndexTarget' );
		$table_ref    = $target->get_first_child_node( 'tableRef' );
		$table_name   = $this->get_table_name_from_node( $table_ref );

		$table_is_temporary = $this->temporary_table_exists( $table_name );
		$this->record_add_index( $table_is_temporary, $table_name, $create_index );
	}

	/**
	 * Analyze DROP INDEX definition and record data in the information schema.
	 *
	 * @param WP_Parser_Node $node The "dropStatement" AST node with "dropIndex" child.
	 */
	public function record_drop_index( WP_Parser_Node $node ): void {
		$drop_index         = $node->get_first_child_node( 'dropIndex' );
		$table_ref          = $drop_index->get_first_child_node( 'tableRef' );
		$table_name         = $this->get_table_name_from_node( $table_ref );
		$index_name         = $this->get_value( $drop_index->get_first_child_node( 'indexRef' ) );
		$table_is_temporary = $this->temporary_table_exists( $table_name );
		$this->record_drop_index_data( $table_is_temporary, $table_name, $index_name );
	}

	/**
	 * Analyze ADD COLUMN definition and record data in the information schema.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param string         $column_name        The column name.
	 * @param WP_Parser_Node $node               The "columnDefinition" or "fieldDefinition" AST node.
	 */
	private function record_add_column(
		bool $table_is_temporary,
		string $table_name,
		string $column_name,
		WP_Parser_Node $node
	): void {
		$columns_table_name = $this->get_table_name( $table_is_temporary, 'columns' );
		$position           = $this->connection->query(
			'
				SELECT MAX(ordinal_position)
				FROM ' . $this->connection->quote_identifier( $columns_table_name ) . '
				WHERE table_schema = ?
				AND table_name = ?
			',
			array( self::SAVED_DATABASE_NAME, $table_name )
		)->fetchColumn();

		$column_data = $this->extract_column_data( $table_name, $column_name, $node, (int) $position + 1 );
		try {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'columns' ),
				$column_data
			);
		} catch ( PDOException $e ) {
			if ( '23000' === $e->getCode() ) {
				throw WP_SQLite_Information_Schema_Exception::duplicate_column_name( $column_name );
			}
			throw $e;
		}

		$index_data = $this->extract_column_statistics_data( $table_name, $column_name, $node, true );
		if ( null !== $index_data ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'statistics' ),
				$index_data
			);
		}

		$constraint_data = $this->extract_table_constraint_data(
			$node,
			$table_name,
			$index_data['index_name'] ?? null
		);
		if ( null !== $constraint_data ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'table_constraints' ),
				$constraint_data
			);
		}
	}

	/**
	 * Analyze CHANGE COLUMN definition and record data in the information schema.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param string         $column_name        The column name.
	 * @param string         $new_column_name    The new column name when the column is renamed.
	 * @param WP_Parser_Node $node               The "fieldDefinition" AST node.
	 */
	private function record_change_column(
		bool $table_is_temporary,
		string $table_name,
		string $column_name,
		string $new_column_name,
		WP_Parser_Node $node
	): void {
		$column_data = $this->extract_column_data( $table_name, $new_column_name, $node, 0 );
		unset( $column_data['ordinal_position'] );
		$this->update_values(
			$this->get_table_name( $table_is_temporary, 'columns' ),
			$column_data,
			array(
				'table_schema' => self::SAVED_DATABASE_NAME,
				'table_name'   => $table_name,
				'column_name'  => $column_name,
			)
		);

		// Update column name in statistics, if it has changed.
		if ( $new_column_name !== $column_name ) {
			$this->update_values(
				$this->get_table_name( $table_is_temporary, 'statistics' ),
				array(
					'column_name' => $new_column_name,
				),
				array(
					'table_schema' => self::SAVED_DATABASE_NAME,
					'table_name'   => $table_name,
					'column_name'  => $column_name,
				)
			);
		}

		// Handle inline constraints. When inline constraint is defined, MySQL
		// always adds a new constraint rather than replacing an existing one.
		$index_data = $this->extract_column_statistics_data(
			$table_name,
			$new_column_name,
			$node,
			'YES' === $column_data['is_nullable']
		);
		if ( null !== $index_data ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'statistics' ),
				$index_data
			);
			$this->sync_column_key_info( $table_is_temporary, $table_name );
		}

		$constraint_data = $this->extract_table_constraint_data(
			$node,
			$table_name,
			$index_data['index_name'] ?? null
		);
		if ( null !== $constraint_data ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'table_constraints' ),
				$constraint_data
			);
		}
	}

	/**
	 * Analyze MODIFY COLUMN definition and record data in the information schema.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param string         $column_name        The column name.
	 * @param WP_Parser_Node $node               The "fieldDefinition" AST node.
	 */
	private function record_modify_column(
		bool $table_is_temporary,
		string $table_name,
		string $column_name,
		WP_Parser_Node $node
	): void {
		$this->record_change_column( $table_is_temporary, $table_name, $column_name, $column_name, $node );
	}

	/**
	 * Record SET DEFAULT or DROP DEFAULT data in the information schema.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param string         $column_name        The column name.
	 * @param WP_Parser_Node $node               The "alterListItem" AST node.
	 */
	private function record_alter_column_default(
		bool $table_is_temporary,
		string $table_name,
		string $column_name,
		WP_Parser_Node $node
	): void {
		$columns_table = $this->get_table_name( $table_is_temporary, 'columns' );
		$column        = $this->connection->query(
			'SELECT DATA_TYPE, DATETIME_PRECISION, IS_NULLABLE, EXTRA FROM ' . $this->connection->quote_identifier( $columns_table ) . '
			WHERE table_schema = ? AND table_name = ? AND column_name = ?',
			array( self::SAVED_DATABASE_NAME, $table_name, $column_name )
		)->fetch( PDO::FETCH_ASSOC );
		if ( false === $column ) {
			throw WP_SQLite_Information_Schema_Exception::column_not_found( $column_name, $table_name );
		}

		/*
		 * MySQL accepts these changes but hides them from its schema.
		 * These are known MySQL bugs that are not worth replicating.
		 *
		 * @see https://bugs.mysql.com/bug.php?id=111702
		 * @see https://bugs.mysql.com/bug.php?id=80222
		 */
		if ( str_contains( $column['EXTRA'], 'auto_increment' ) ) {
			throw new Exception( 'SET DEFAULT and DROP DEFAULT on AUTO_INCREMENT columns are not supported.' );
		}

		$default = null;
		$extra   = trim( str_replace( 'DEFAULT_GENERATED', '', $column['EXTRA'] ) );
		if ( $node->has_child_token( WP_MySQL_Lexer::SET_SYMBOL ) ) {
			$expression         = $node->get_first_child_node( 'exprWithParentheses' );
			$literal            = $expression ? $this->get_parenthesized_default_literal( $expression ) : null;
			$datetime_precision = null === $column['DATETIME_PRECISION'] ? null : (int) $column['DATETIME_PRECISION'];
			$default            = $literal
				? $this->get_literal_default( $literal, $column['DATA_TYPE'], $column_name, $datetime_precision )
				: $this->get_default_value( $node, $column['DATA_TYPE'], $column_name, $datetime_precision );
			if ( null === $default && 'NO' === $column['IS_NULLABLE'] ) {
				throw WP_SQLite_Information_Schema_Exception::invalid_default_value( $column_name );
			}
			if ( $expression && ! $literal ) {
				$extra = trim( 'DEFAULT_GENERATED ' . $extra );
			}
		}

		$this->update_values(
			$columns_table,
			array(
				'column_default' => $default,
				'extra'          => $extra,
			),
			array(
				'table_schema' => self::SAVED_DATABASE_NAME,
				'table_name'   => $table_name,
				'column_name'  => $column_name,
			)
		);
	}

	/**
	 * Find a literal wrapped only in parentheses or unary plus operators.
	 *
	 * @param  WP_Parser_Node $node The default expression.
	 * @return WP_Parser_Node|null  The literal, or null for other expressions.
	 */
	private function get_parenthesized_default_literal( WP_Parser_Node $node ): ?WP_Parser_Node {
		while ( 'literal' !== $node->rule_name ) {
			$children = $node->get_children();
			if (
				3 === count( $children )
				&& $children[0] instanceof WP_Parser_Token
				&& WP_MySQL_Lexer::OPEN_PAR_SYMBOL === $children[0]->id
				&& $children[2] instanceof WP_Parser_Token
				&& WP_MySQL_Lexer::CLOSE_PAR_SYMBOL === $children[2]->id
			) {
				$children = array( $children[1] );
			} elseif (
				2 === count( $children )
				&& $children[0] instanceof WP_Parser_Token
				&& WP_MySQL_Lexer::PLUS_OPERATOR === $children[0]->id
			) {
				$children = array( $children[1] );
			}
			if ( 1 !== count( $children ) || ! $children[0] instanceof WP_Parser_Node ) {
				return null;
			}
			$node = $children[0];
		}
		return $node;
	}

	/**
	 * Record DROP COLUMN data in the information schema.
	 *
	 * @param bool   $table_is_temporary Whether the table is temporary.
	 * @param string $table_name         The table name.
	 * @param string $column_name        The column name.
	 */
	private function record_drop_column(
		bool $table_is_temporary,
		string $table_name,
		string $column_name
	): void {
		// Delete the column record from the columns table.
		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'columns' ),
			array(
				'table_schema' => self::SAVED_DATABASE_NAME,
				'table_name'   => $table_name,
				'column_name'  => $column_name,
			)
		);

		/*
		 * When a column is dropped, we need to reflect the effects of the change
		 * on the existing indexes and constraints that the column was part of.
		 *
		 * This means:
		 *
		 *   1. Remove the column records from the statistics table.
		 *   2. Renumber SEQ_IN_INDEX values in the statistics table so that
		 *      there are no sequence gaps caused by the removed column.
		 *   3. Recompute column key information in the statistics table.
		 *   4. Delete the table constraint records for no longer existing indexes.
		 *
		 * From MySQL documentation:
		 *
		 *   If columns are dropped from a table, the columns are also removed
		 *   from any index of which they are a part. If all columns that make up
		 *   an index are dropped, the index is dropped as well.
		 *
		 * This means we need to remove the records from the STATISTICS table,
		 * renumber the SEQ_IN_INDEX values, and resync the column key info.
		 *
		 * See:
		 *   - https://dev.mysql.com/doc/refman/8.4/en/alter-table.html
		 */
		$statistics_table  = $this->get_table_name( $table_is_temporary, 'statistics' );
		$constraints_table = $this->get_table_name( $table_is_temporary, 'table_constraints' );

		/*
		 * 1. Delete the column records from the statistics table.
		 *
		 * In MySQL, when a column is dropped, it is removed from all indexes
		 * that it was part of. An index is dropped when it has no more columns.
		 */
		$this->delete_values(
			$statistics_table,
			array(
				'table_schema' => self::SAVED_DATABASE_NAME,
				'table_name'   => $table_name,
				'column_name'  => $column_name,
			)
		);

		/*
		 * 2. Renumber SEQ_IN_INDEX values in the statistics table.
		 *
		 * When a column is removed from a multi-column index, it can leave a gap
		 * in the numeric sequence of SEQ_IN_INDEX values in the statistics table.
		 */
		$this->connection->query(
			sprintf(
				'WITH renumbered AS (
					SELECT
						rowid,
						row_number() OVER (PARTITION BY index_name ORDER BY seq_in_index) AS seq_in_index
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
				)
				UPDATE %s AS statistics
				SET seq_in_index = (SELECT seq_in_index FROM renumbered WHERE rowid = statistics.rowid)
				WHERE statistics.rowid IN (SELECT rowid FROM renumbered)',
				$this->connection->quote_identifier( $statistics_table ),
				$this->connection->quote_identifier( $statistics_table )
			),
			array( self::SAVED_DATABASE_NAME, $table_name )
		);

		/*
		 * 3. Recompute column key data in the statistics table.
		 *
		 * When a column is removed from a multi-column index, it can cause the
		 * value of COLUMN_KEY in the statistics for other columns to change.
		 */
		$this->sync_column_key_info( $table_is_temporary, $table_name );

		/*
		 * 4. Delete the table constraint records for no longer existing indexes.
		 *
		 * If there are no more columns left in an index the column was part of,
		 * we need to make sure that the associated table constraint records are
		 * deleted as well. Therefore, remove all index-specific table constraint
		 * records that have no index data associated with them for a given table.
		 */
		$this->connection->query(
			sprintf(
				"DELETE FROM %s
				WHERE table_schema = ?
				AND table_name = ?
				AND constraint_type IN ('PRIMARY KEY', 'UNIQUE')
				AND constraint_name NOT IN (
					SELECT DISTINCT index_name FROM %s WHERE table_schema = ? AND table_name = ?
				)",
				$this->connection->quote_identifier( $constraints_table ),
				$this->connection->quote_identifier( $statistics_table )
			),
			array( self::SAVED_DATABASE_NAME, $table_name, self::SAVED_DATABASE_NAME, $table_name )
		);
	}

	/**
	 * Analyze ADD "tableConstraintDef" and record data in the information schema.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param WP_Parser_Node $node               The "tableConstraintDef" AST node.
	 */
	private function record_add_constraint_or_index(
		bool $table_is_temporary,
		string $table_name,
		WP_Parser_Node $node
	): void {
		$child                = $node->get_first_child();
		$first_child_token_id = $child instanceof WP_MySQL_Token ? $child->id : null;
		if (
			WP_MySQL_Lexer::KEY_SYMBOL === $first_child_token_id
			|| WP_MySQL_Lexer::INDEX_SYMBOL === $first_child_token_id
			|| WP_MySQL_Lexer::FULLTEXT_SYMBOL === $first_child_token_id
			|| WP_MySQL_Lexer::SPATIAL_SYMBOL === $first_child_token_id
		) {
			$this->record_add_index( $table_is_temporary, $table_name, $node );
		} else {
			$this->record_add_constraint( $table_is_temporary, $table_name, $node );
		}
	}

	/**
	 * Analyze index definition and record data in the information schema.
	 *
	 * This serves both "ALTER TABLE ... ADD ..." and "CREATE INDEX" statements.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param WP_Parser_Node $node               The "tableConstraintDef" or "createIndex" AST node.
	 */
	private function record_add_index(
		bool $table_is_temporary,
		string $table_name,
		WP_Parser_Node $node
	): void {
		$statistics_data = $this->extract_index_statistics_data( $table_is_temporary, $table_name, $node );
		$index_name      = $statistics_data[0]['index_name'];
		foreach ( $statistics_data as $index_data ) {
			try {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'statistics' ),
					$index_data
				);
			} catch ( PDOException $e ) {
				if ( '23000' === $e->getCode() ) {
					throw WP_SQLite_Information_Schema_Exception::duplicate_key_name( $index_name );
				}
				throw $e;
			}
		}

		// Sync column info from index data.
		$this->sync_column_key_info( $table_is_temporary, $table_name );

		// For UNIQUE index, save also constraint data.
		if ( $node->has_child_token( WP_MySQL_Lexer::UNIQUE_SYMBOL ) ) {
			$constraint_data = $this->extract_table_constraint_data(
				$node,
				$table_name,
				$index_name
			);

			if ( null !== $constraint_data ) {
				$this->insert_values(
					$this->get_table_name( $table_is_temporary, 'table_constraints' ),
					$constraint_data
				);
			}
		}
	}

	/**
	 * Record DROP INDEX data in the information schema.
	 *
	 * @param bool   $table_is_temporary Whether the table is temporary.
	 * @param string $table_name         The table name.
	 * @param string $index_name         The index name.
	 */
	private function record_drop_index_data(
		bool $table_is_temporary,
		string $table_name,
		string $index_name
	): void {
		// Delete index data.
		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'statistics' ),
			array(
				'table_schema' => self::SAVED_DATABASE_NAME,
				'table_name'   => $table_name,
				'index_name'   => $index_name,
			)
		);

		/*
		 * Delete associated table constraint data.
		 *
		 * A table constraint record is saved for PRIMARY KEY and UNIQUE indexes.
		 * We don't need to read the schema to get the constraint type, because:
		 *
		 *   1. In MySQL, all primary keys are named "PRIMARY", and no other
		 *      indexes can be named so. This way we can identify primary keys.
		 *   2. In MySQL, all indexes in a table must have distinct names, no
		 *      matter the index type. Therefore, if a table constraint record
		 *      exists for a given index name, we know it is a unique index.
		 */
		$constraint_type =
			strtoupper( $index_name ) === 'PRIMARY' ? 'PRIMARY KEY' : 'UNIQUE';

		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'table_constraints' ),
			array(
				'table_schema'    => self::SAVED_DATABASE_NAME,
				'table_name'      => $table_name,
				'constraint_name' => $index_name,
				'constraint_type' => $constraint_type,
			)
		);

		// Sync column info from constraint data.
		$this->sync_column_key_info( $table_is_temporary, $table_name );
	}

	/**
	 * Analyze ADD CONSTRAINT definition and record data in the information schema.
	 *
	 * @param bool           $table_is_temporary Whether the table is temporary.
	 * @param string         $table_name         The table name.
	 * @param WP_Parser_Node $node               The "tableConstraintDef" AST node.
	 */
	private function record_add_constraint(
		bool $table_is_temporary,
		string $table_name,
		WP_Parser_Node $node
	): void {
		// Get first constraint keyword.
		$children = $node->get_children();
		if ( $children[0] instanceof WP_Parser_Node && 'constraintName' === $children[0]->rule_name ) {
			$keyword = $children[1];
		} else {
			$keyword = $children[0];
		}
		if ( ! $keyword instanceof WP_MySQL_Token ) {
			$keyword = $keyword->get_first_child_token();
		}

		// PRIMARY KEY and UNIQUE require an index.
		if (
			WP_MySQL_Lexer::PRIMARY_SYMBOL === $keyword->id
			|| WP_MySQL_Lexer::UNIQUE_SYMBOL === $keyword->id
		) {
			$statistics_data = $this->extract_index_statistics_data( $table_is_temporary, $table_name, $node );
			$index_name      = $statistics_data[0]['index_name'];
			foreach ( $statistics_data as $index_data ) {
				try {
					$this->insert_values(
						$this->get_table_name( $table_is_temporary, 'statistics' ),
						$index_data
					);
				} catch ( PDOException $e ) {
					if ( '23000' === $e->getCode() ) {
						throw WP_SQLite_Information_Schema_Exception::duplicate_key_name( $index_name );
					}
					throw $e;
				}
			}

			// Sync column info from index data.
			$this->sync_column_key_info( $table_is_temporary, $table_name );
		} else {
			$index_name = null;
		}

		// Extract constraint data.
		$constraint_data             = $this->extract_table_constraint_data( $node, $table_name, $index_name );
		$referential_constraint_data = $this->extract_referential_constraint_data( $node, $table_name );
		$key_column_usage_data       = $this->extract_key_column_usage_data( $node, $table_name, $index_name );
		$check_constraint_data       = $this->extract_check_constraint_data( $node, $table_name );

		// Save constraint data.
		if ( null !== $constraint_data ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'table_constraints' ),
				$constraint_data
			);
		}

		if ( null !== $referential_constraint_data ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'referential_constraints' ),
				$referential_constraint_data
			);
		}

		foreach ( $key_column_usage_data as $key_column_usage_item ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'key_column_usage' ),
				$key_column_usage_item
			);
		}

		if ( null !== $check_constraint_data ) {
			$this->insert_values(
				$this->get_table_name( $table_is_temporary, 'check_constraints' ),
				$check_constraint_data
			);
		}
	}

	/**
	 * Analyze DROP CONSTRAINT statement and record data in the information schema.
	 *
	 * @param bool   $table_is_temporary Whether the table is temporary.
	 * @param string $table_name         The table name.
	 * @param string $name               The constraint name.
	 */
	private function record_drop_constraint(
		bool $table_is_temporary,
		string $table_name,
		string $name
	): void {
		$constraint_types = $this->connection->query(
			sprintf(
				'SELECT constraint_type FROM %s WHERE table_schema = ? AND table_name = ? AND constraint_name = ?',
				$this->connection->quote_identifier( $this->get_table_name( $table_is_temporary, 'table_constraints' ) )
			),
			array(
				self::SAVED_DATABASE_NAME,
				$table_name,
				$name,
			)
		)->fetchAll(
			PDO::FETCH_COLUMN // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		);

		if ( 0 === count( $constraint_types ) ) {
			throw WP_SQLite_Information_Schema_Exception::constraint_does_not_exist( $name );
		}

		// MySQL doesn't allow a generic DELETE CONSTRAINT clause when the target
		// is ambiguous, i.e., when multiple constraints with the same name exist.
		if ( count( $constraint_types ) > 1 ) {
			throw WP_SQLite_Information_Schema_Exception::multiple_constraints_with_name( $name );
		}

		$constraint_type = $constraint_types[0];
		if ( 'PRIMARY KEY' === $constraint_type ) {
			$this->record_drop_key( $table_is_temporary, $table_name, 'PRIMARY' );
		} elseif ( 'UNIQUE' === $constraint_type ) {
			$this->record_drop_key( $table_is_temporary, $table_name, $name );
		} elseif ( 'FOREIGN KEY' === $constraint_type ) {
			$this->record_drop_foreign_key( $table_is_temporary, $table_name, $name );
		} elseif ( 'CHECK' === $constraint_type ) {
			$this->record_drop_check_constraint( $table_is_temporary, $table_name, $name );
		} else {
			throw new \Exception(
				"DROP CONSTRAINT for constraint type '$constraint_type' is not supported."
			);
		}
	}

	/**
	 * Analyze DROP PRIMARY KEY or DROP UNIQUE statement and record data
	 * in the information schema.
	 *
	 * @param bool   $table_is_temporary Whether the table is temporary.
	 * @param string $table_name         The table name.
	 * @param mixed  $name               The constraint name.
	 */
	private function record_drop_key(
		bool $table_is_temporary,
		string $table_name,
		string $name
	): void {
		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'table_constraints' ),
			array(
				'TABLE_SCHEMA'    => self::SAVED_DATABASE_NAME,
				'TABLE_NAME'      => $table_name,
				'CONSTRAINT_NAME' => $name,
			)
		);

		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'statistics' ),
			array(
				'TABLE_SCHEMA' => self::SAVED_DATABASE_NAME,
				'TABLE_NAME'   => $table_name,
				'INDEX_NAME'   => $name,
			)
		);

		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'key_column_usage' ),
			array(
				'TABLE_SCHEMA'            => self::SAVED_DATABASE_NAME,
				'TABLE_NAME'              => $table_name,
				'CONSTRAINT_NAME'         => $name,

				// Remove only PRIMARY/UNIQUE key records; not FOREIGN KEY data.
				'REFERENCED_TABLE_SCHEMA' => null,
			)
		);

		// Sync column info from constraint data.
		$this->sync_column_key_info( $table_is_temporary, $table_name );
	}

	/**
	 * Analyze DROP FOREIGN KEY statement and record data in the information schema.
	 *
	 * @param bool   $table_is_temporary Whether the table is temporary.
	 * @param string $table_name         The table name.
	 * @param string $name               The foreign key name.
	 */
	private function record_drop_foreign_key(
		bool $table_is_temporary,
		string $table_name,
		string $name
	): void {
		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'table_constraints' ),
			array(
				'TABLE_SCHEMA'    => self::SAVED_DATABASE_NAME,
				'TABLE_NAME'      => $table_name,
				'CONSTRAINT_NAME' => $name,
			)
		);

		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'referential_constraints' ),
			array(
				'CONSTRAINT_SCHEMA' => self::SAVED_DATABASE_NAME,
				'TABLE_NAME'        => $table_name,
				'CONSTRAINT_NAME'   => $name,
			)
		);

		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'key_column_usage' ),
			array(
				'TABLE_SCHEMA'            => self::SAVED_DATABASE_NAME,
				'TABLE_NAME'              => $table_name,
				'CONSTRAINT_NAME'         => $name,

				// Remove only FOREIGN KEY records; not PRIMARY/UNIQUE KEY data.
				'REFERENCED_TABLE_SCHEMA' => self::SAVED_DATABASE_NAME,
			)
		);
	}

	/**
	 * Analyze DROP CHECK statement and record data in the information schema.
	 *
	 * @param bool   $table_is_temporary Whether the table is temporary.
	 * @param string $table_name         The table name.
	 * @param string $name               The check constraint name.
	 */
	private function record_drop_check_constraint(
		bool $table_is_temporary,
		string $table_name,
		string $name
	): void {
		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'table_constraints' ),
			array(
				'CONSTRAINT_SCHEMA' => self::SAVED_DATABASE_NAME,
				'TABLE_NAME'        => $table_name,
				'CONSTRAINT_TYPE'   => 'CHECK',
				'CONSTRAINT_NAME'   => $name,
			)
		);

		$this->delete_values(
			$this->get_table_name( $table_is_temporary, 'check_constraints' ),
			array(
				'CONSTRAINT_SCHEMA' => self::SAVED_DATABASE_NAME,
				'CONSTRAINT_NAME'   => $name,
			)
		);
	}

	/**
	 * Analyze "columnDefinition" or "fieldDefinition" AST node and extract column data.
	 *
	 * @param  string         $table_name  The table name.
	 * @param  string         $column_name The column name.
	 * @param  WP_Parser_Node $node        The "columnDefinition" or "fieldDefinition" AST node.
	 * @param  int            $position    The ordinal position of the column in the table.
	 * @return array                       Column data for the information schema.
	 */
	private function extract_column_data( string $table_name, string $column_name, WP_Parser_Node $node, int $position ): array {
		list ( $data_type, $column_type ) = $this->get_column_data_types( $node );

		$default  = $this->get_column_default( $node, $data_type, $column_name );
		$nullable = $this->get_column_nullable( $node );
		$key      = $this->get_column_key( $node );
		$extra    = $this->get_column_extra( $node );
		$comment  = $this->get_column_comment( $node );

		list ( $charset, $collation )        = $this->get_column_charset_and_collation( $node, $data_type );
		list ( $char_length, $octet_length ) = $this->get_column_lengths( $node, $data_type, $charset );
		list ( $precision, $scale )          = $this->get_column_numeric_attributes( $node, $data_type );
		$datetime_precision                  = $this->get_column_datetime_precision( $node, $data_type );
		$generation_expression               = $this->get_column_generation_expression( $node );

		return array(
			'table_schema'             => self::SAVED_DATABASE_NAME,
			'table_name'               => $table_name,
			'column_name'              => $column_name,
			'ordinal_position'         => $position,
			'column_default'           => $default,
			'is_nullable'              => $nullable,
			'data_type'                => $data_type,
			'character_maximum_length' => $char_length,
			'character_octet_length'   => $octet_length,
			'numeric_precision'        => $precision,
			'numeric_scale'            => $scale,
			'datetime_precision'       => $datetime_precision,
			'character_set_name'       => $charset,
			'collation_name'           => $collation,
			'column_type'              => $column_type,
			'column_key'               => $key,
			'extra'                    => $extra,
			'privileges'               => 'select,insert,update,references',
			'column_comment'           => $comment,
			'generation_expression'    => $generation_expression,
			'srs_id'                   => null, // not implemented
		);
	}

	/**
	 * Analyze "columnDefinition" or "fieldDefinition" AST node and extract constraint data.
	 *
	 * @param  string         $table_name  The table name.
	 * @param  string         $column_name The column name.
	 * @param  WP_Parser_Node $node        The "columnDefinition" or "fieldDefinition" AST node.
	 * @param  bool           $nullable    Whether the column is nullable.
	 * @return array|null                  Column statistics data for the information schema.
	 */
	private function extract_column_statistics_data(
		string $table_name,
		string $column_name,
		WP_Parser_Node $node,
		bool $nullable
	): ?array {
		// Handle inline PRIMARY KEY and UNIQUE constraints.
		$has_inline_primary_key = null !== $node->get_first_descendant_token( WP_MySQL_Lexer::KEY_SYMBOL );
		$has_inline_unique_key  = null !== $node->get_first_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL );
		if ( $has_inline_primary_key || $has_inline_unique_key ) {
			$index_name = $has_inline_primary_key ? 'PRIMARY' : $column_name;
			return array(
				'table_schema'  => self::SAVED_DATABASE_NAME,
				'table_name'    => $table_name,
				'non_unique'    => 0,
				'index_schema'  => self::SAVED_DATABASE_NAME,
				'index_name'    => $index_name,
				'seq_in_index'  => 1,
				'column_name'   => $column_name,
				'collation'     => 'A',
				'cardinality'   => 0, // not implemented
				'sub_part'      => null,
				'packed'        => null, // not implemented
				'nullable'      => true === $nullable ? 'YES' : '',
				'index_type'    => 'BTREE',
				'comment'       => '', // not implemented
				'index_comment' => '', // @TODO
				'is_visible'    => 'YES', // @TODO: Save actual visibility value.
				'expression'    => null, // @TODO
			);
		}
		return null;
	}

	/**
	 * Analyze "tableConstraintDef" or "createIndex" AST node and extract index data.
	 *
	 * @param  bool           $table_is_temporary Whether the table is temporary.
	 * @param  string         $table_name         The table name.
	 * @param  WP_Parser_Node $node               The "tableConstraintDef" or "createIndex" AST node.
	 * @return array                              Index statistics data for the information schema.
	 */
	private function extract_index_statistics_data(
		bool $table_is_temporary,
		string $table_name,
		WP_Parser_Node $node
	): array {
		// Get first keyword.
		$children = $node->get_children();
		$keyword  = $children[0] instanceof WP_MySQL_Token ? $children[0] : $children[1];
		if ( ! $keyword instanceof WP_MySQL_Token ) {
			$keyword = $keyword->get_first_child_token();
		}

		// Get key parts.
		$key_list = $node->get_first_descendant_node( 'keyListVariants' )->get_first_child();
		if ( 'keyListWithExpression' === $key_list->rule_name ) {
			$key_parts = array();
			foreach ( $key_list->get_descendant_nodes( 'keyPartOrExpression' ) as $key_part ) {
				$key_parts[] = $key_part->get_first_child();
			}
		} else {
			$key_parts = $key_list->get_descendant_nodes( 'keyPart' );
		}

		// Get index column names.
		$key_part_column_names = array();
		foreach ( $key_parts as $key_part ) {
			$key_part_column_names[] = $this->get_index_column_name( $key_part );
		}

		// Fetch column info.
		$column_names = array_filter( $key_part_column_names );
		if ( count( $column_names ) > 0 ) {
			$columns_table_name = $this->get_table_name( $table_is_temporary, 'columns' );
			$column_info        = $this->connection->query(
				'
					SELECT column_name, data_type, is_nullable, character_maximum_length
					FROM ' . $this->connection->quote_identifier( $columns_table_name ) . '
					WHERE table_schema = ?
					AND table_name = ?
					AND column_name IN (' . implode( ',', array_fill( 0, count( $column_names ), '?' ) ) . ')
				',
				array_merge( array( self::SAVED_DATABASE_NAME, $table_name ), $column_names )
			)->fetchAll(
				PDO::FETCH_ASSOC // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
			);
		} else {
			$column_info = array();
		}

		$column_info_map = array_combine(
			array_column( $column_info, 'COLUMN_NAME' ),
			$column_info
		);

		// Get first index column data type (needed for index type).
		$first_column_name  = $this->get_index_column_name( $key_parts[0] );
		$first_column_type  = $column_info_map[ $first_column_name ]['DATA_TYPE'] ?? null;
		$has_spatial_column = null !== $first_column_type && $this->is_spatial_data_type( $first_column_type );

		$non_unique      = $this->get_index_non_unique( $keyword );
		$index_name      = $this->get_index_name( $node, $table_name );
		$index_type      = $this->get_index_type( $node, $keyword, $has_spatial_column );
		$index_comment   = $this->get_index_comment( $node );
		$seq_in_index    = 1;
		$statistics_data = array();
		foreach ( $key_parts as $i => $key_part ) {
			$column_name = $key_part_column_names[ $i ];
			$collation   = $this->get_index_column_collation( $key_part, $index_type );
			$column_info = $column_info_map[ $column_name ] ?? null;

			if ( null === $column_info ) {
				throw WP_SQLite_Information_Schema_Exception::key_column_not_found( $column_name );
			}

			if (
				'PRIMARY' === $index_name
				|| 'NO' === $column_info_map[ $column_name ]['IS_NULLABLE']
			) {
				$nullable = '';
			} else {
				$nullable = 'YES';
			}

			$sub_part = $this->get_index_column_sub_part(
				$key_part,
				$column_info_map[ $column_name ]['CHARACTER_MAXIMUM_LENGTH'],
				$has_spatial_column
			);

			$statistics_data[] = array(
				'table_schema'  => self::SAVED_DATABASE_NAME,
				'table_name'    => $table_name,
				'non_unique'    => $non_unique,
				'index_schema'  => self::SAVED_DATABASE_NAME,
				'index_name'    => $index_name,
				'seq_in_index'  => $seq_in_index,
				'column_name'   => $column_name,
				'collation'     => $collation,
				'cardinality'   => 0, // not implemented
				'sub_part'      => $sub_part,
				'packed'        => null, // not implemented
				'nullable'      => $nullable,
				'index_type'    => $index_type,
				'comment'       => '', // not implemented
				'index_comment' => $index_comment,
				'is_visible'    => 'YES', // @TODO: Save actual visibility value.
				'expression'    => null, // @TODO
			);

			$seq_in_index += 1;
		}
		return $statistics_data;
	}

	/**
	 * Extract table constraint data from the "tableConstraintDef" or "columnDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node        The "tableConstraintDef" or "columnDefinition" AST node.
	 * @param  string         $table_name  The table name.
	 * @param  string         $column_name The column name.
	 * @return array|null                  Table constraint data for the information schema.
	 */
	public function extract_table_constraint_data(
		WP_Parser_Node $node,
		string $table_name,
		?string $index_name = null
	): ?array {
		$type = $this->get_table_constraint_type( $node );
		if ( null === $type ) {
			return null;
		}

		// Index name always takes precedence over constraint name.
		$name = $index_name ?? $this->get_table_constraint_name( $node, $table_name );

		// Constraint enforcement.
		$constraint_enforcement = $node->get_first_descendant_node( 'constraintEnforcement' );
		if ( $constraint_enforcement && $constraint_enforcement->has_child_token( WP_MySQL_Lexer::NOT_SYMBOL ) ) {
			$enforced = 'NO';
		} else {
			$enforced = 'YES';
		}

		return array(
			'table_schema'      => self::SAVED_DATABASE_NAME,
			'table_name'        => $table_name,
			'constraint_schema' => self::SAVED_DATABASE_NAME,
			'constraint_name'   => $name,
			'constraint_type'   => $type,
			'enforced'          => $enforced,
		);
	}

	/**
	 * Extract referential constraint data from the "tableConstraintDef" AST node.
	 *
	 * @param  WP_Parser_Node $node       The "tableConstraintDef" AST node.
	 * @param  string         $table_name The table name.
	 * @return array|null                 The referential constraint data as stored in information schema.
	 */
	private function extract_referential_constraint_data( WP_Parser_Node $node, string $table_name ): ?array {
		$references = $node->get_first_descendant_node( 'references' );
		if ( null === $references ) {
			return null;
		}

		// Referenced table name.
		$referenced_table      = $references->get_first_child_node( 'tableRef' );
		$referenced_table_name = $this->get_table_name_from_node( $referenced_table );

		// Referenced column names.
		$reference_parts = $references->get_first_child_node( 'identifierListWithParentheses' )
			->get_first_child_node( 'identifierList' )
			->get_child_nodes( 'identifier' );

		// ON UPDATE and ON DELETE both use the "deleteOption" node.
		$actions   = $this->get_foreign_key_actions( $references );
		$on_update = $actions['on_update'];
		$on_delete = $actions['on_delete'];

		// Find PRIMARY and UNIQUE constraints in the referenced table.
		$table_is_temporary    = false;
		$statistics_table_name = $this->get_table_name( $table_is_temporary, 'statistics' );
		$statistics            = $this->connection->query(
			'
				SELECT index_name, column_name
				FROM ' . $this->connection->quote_identifier( $statistics_table_name ) . "
				WHERE table_schema = ?
				AND table_name = ?
				AND non_unique = 0
				ORDER BY index_name = 'PRIMARY' DESC, index_name, seq_in_index
			",
			array( self::SAVED_DATABASE_NAME, $referenced_table_name )
		)->fetchAll(
			PDO::FETCH_ASSOC // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		);

		// Group index columns to a map.
		$index_columns_map = array();
		foreach ( $statistics as $statistics_item ) {
			$index_columns_map[ $statistics_item['INDEX_NAME'] ][] = $statistics_item['COLUMN_NAME'];
		}

		// Find which index includes referenced column names as a prefix.
		$unique_constraint_name = null;
		foreach ( $index_columns_map as $index_name => $index_columns ) {
			// An index with fewer columns than the reference can't have them as a prefix.
			if ( count( $index_columns ) < count( $reference_parts ) ) {
				continue;
			}

			// Column names are case-insensitive in MySQL.
			$is_prefix = true;
			foreach ( $reference_parts as $i => $reference_part ) {
				if ( 0 !== strcasecmp( $index_columns[ $i ], $this->get_value( $reference_part ) ) ) {
					$is_prefix = false;
					break;
				}
			}
			if ( $is_prefix ) {
				$unique_constraint_name = $index_name;
				break;
			}
		}

		$name = $this->get_table_constraint_name( $node, $table_name );
		return array(
			'constraint_schema'        => self::SAVED_DATABASE_NAME,
			'constraint_name'          => $name,
			'unique_constraint_schema' => self::SAVED_DATABASE_NAME,
			'unique_constraint_name'   => $unique_constraint_name,
			'update_rule'              => $on_update,
			'delete_rule'              => $on_delete,
			'table_name'               => $table_name,
			'referenced_table_name'    => $referenced_table_name,
		);
	}

	/**
	 * Extract key column usage data from the "tableConstraintDef" AST node.
	 *
	 * @param  WP_Parser_Node $node        The "tableConstraintDef" AST node.
	 * @param  string         $table_name  The table name.
	 * @param  string         $index_name  The index name, when the constraint uses an index.
	 * @return array                       The key column usage data as stored in information schema.
	 */
	private function extract_key_column_usage_data(
		WP_Parser_Node $node,
		string $table_name,
		?string $index_name = null
	): array {
		$is_primary = $node->get_first_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL );
		$is_unique  = $node->get_first_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL );
		$references = $node->get_first_descendant_node( 'references' );
		if ( null === $references && ! $is_primary && ! $is_unique ) {
			return array();
		}

		// Referenced table name and column names.
		if ( $references ) {
			$referenced_table        = $references->get_first_child_node( 'tableRef' );
			$referenced_identifiers  = $referenced_table->get_descendant_nodes( 'identifier' );
			$referenced_table_schema = count( $referenced_identifiers ) > 1
				? $this->get_value( $referenced_identifiers[0] )
				: self::SAVED_DATABASE_NAME;
			$referenced_table_name   = $this->get_table_name_from_node( $referenced_table );
			$referenced_columns      = $references->get_first_child_node( 'identifierListWithParentheses' )
				->get_first_child_node( 'identifierList' )
				->get_child_nodes( 'identifier' );
		} else {
			$referenced_table_schema = null;
			$referenced_table_name   = null;
			$referenced_columns      = array();
		}

		// Constraint name.
		$name = $index_name ?? $this->get_table_constraint_name( $node, $table_name );

		// Key parts.
		if ( 'columnDefinition' === $node->rule_name ) {
			$identifiers = $node
				->get_first_descendant_node( 'fieldIdentifier' )
				->get_descendant_nodes( 'identifier' );
			$key_parts   = array( end( $identifiers ) );
		} else {
			$key_parts = array();
			foreach ( $node->get_descendant_nodes( 'keyPart' ) as $key_part ) {
				$key_parts[] = $key_part->get_first_child_node( 'identifier' );
			}
		}

		$rows = array();
		foreach ( $key_parts as $i => $key_part ) {
			$column_name = $this->get_value( $key_part );
			$position    = $i + 1;

			$rows[] = array(
				'constraint_schema'             => self::SAVED_DATABASE_NAME,
				'constraint_name'               => $name,
				'table_schema'                  => self::SAVED_DATABASE_NAME,
				'table_name'                    => $table_name,
				'column_name'                   => $column_name,
				'ordinal_position'              => $position,
				'position_in_unique_constraint' => $references ? $position : null,
				'referenced_table_schema'       => $referenced_table_schema,
				'referenced_table_name'         => $referenced_table_name,
				'referenced_column_name'        => $referenced_columns ? $this->get_value( $referenced_columns[ $i ] ) : null,
			);
		}
		return $rows;
	}

	/**
	 * Extract check constraint data from the "tableConstraintDef" AST node.
	 *
	 * @param  WP_Parser_Node $node       The "tableConstraintDef" AST node.
	 * @param  string         $table_name The table name.
	 * @return array|null                 The check constraint data as stored in information schema.
	 */
	private function extract_check_constraint_data( WP_Parser_Node $node, string $table_name ): ?array {
		$check_constraint = $node->get_first_descendant_node( 'checkConstraint' );
		if ( null === $check_constraint ) {
			return null;
		}

		$expr         = $check_constraint->get_first_child_node( 'exprWithParentheses' );
		$check_clause = $this->serialize_mysql_expression( $expr );

		return array(
			'constraint_schema' => self::SAVED_DATABASE_NAME,
			'constraint_name'   => $this->get_table_constraint_name( $node, $table_name ),
			'check_clause'      => $check_clause,
		);
	}

	/**
	 * Update column info from constraint data in the statistics table.
	 *
	 * When constraints are added or removed, we need to reflect the changes
	 * in the "COLUMN_KEY" and "IS_NULLABLE" columns of the "COLUMNS" table.
	 *
	 *   A) COLUMN_KEY (priority from 1 to 4):
	 *     1. "PRI": Column is any component of a PRIMARY KEY.
	 *     2. "UNI": Column is the first column of a UNIQUE KEY.
	 *     3. "MUL": Column is the first column of a non-unique index.
	 *     4. "":    Column is not indexed.
	 *
	 *   B) IS_NULLABLE: In COLUMNS, "YES"/"NO". In STATISTICS, "YES"/"".
	 *
	 * @param bool   $table_is_temporary Whether the table is temporary.
	 * @param string $table_name         The table name.
	 */
	private function sync_column_key_info( bool $table_is_temporary, string $table_name ): void {
		// @TODO: Consider listing only affected columns.
		$columns_table_name    = $this->get_table_name( $table_is_temporary, 'columns' );
		$statistics_table_name = $this->get_table_name( $table_is_temporary, 'statistics' );
		$this->connection->query(
			'
				UPDATE ' . $this->connection->quote_identifier( $columns_table_name ) . " AS c
				SET (column_key, is_nullable) = (
					SELECT
						CASE
							WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI'
							WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI'
							WHEN MAX(s.seq_in_index = 1) THEN 'MUL'
							ELSE ''
						END,
						CASE
							WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO'
							ELSE c.is_nullable
						END
					FROM " . $this->connection->quote_identifier( $statistics_table_name ) . ' AS s
					WHERE s.table_schema = c.table_schema
					AND s.table_name = c.table_name
					AND s.column_name = c.column_name
				)
			    WHERE c.table_schema = ?
			    AND c.table_name = ?
			',
			array( self::SAVED_DATABASE_NAME, $table_name )
		);
	}

	/**
	 * Extract table name from one of fully-qualified name AST nodes.
	 *
	 * @param  WP_Parser_Node $node The AST node. One of "tableName" or "tableRef".
	 * @return string               The table name.
	 */
	private function get_table_name_from_node( WP_Parser_Node $node ): string {
		if ( 'tableRef' === $node->rule_name || 'tableName' === $node->rule_name ) {
			$parts = $node->get_descendant_nodes( 'identifier' );
			return $this->get_value( end( $parts ) );
		}

		throw new Exception(
			sprintf( 'Could not get table name from node: %s', $node->rule_name )
		);
	}

	/**
	 * Extract table engine value from the "createStatement" AST node.
	 *
	 * @param  WP_Parser_Node $node The "createStatement" AST node with "createTable" child.
	 * @return string               The table engine as stored in information schema.
	 */
	private function get_table_engine( WP_Parser_Node $node ): string {
		$engine_node = $node->get_first_descendant_node( 'engineRef' );
		if ( null === $engine_node ) {
			return 'InnoDB';
		}

		$engine = strtoupper( $this->get_value( $engine_node ) );
		if ( 'INNODB' === $engine ) {
			return 'InnoDB';
		} elseif ( 'MYISAM' === $engine ) {
			return 'MyISAM';
		}
		return $engine;
	}

	/**
	 * Extract table collation value from the "createStatement" AST node.
	 *
	 * @param  WP_Parser_Node $node The "createStatement" AST node with "createTable" child.
	 * @return string               The table collation as stored in information schema.
	 */
	private function get_table_collation( WP_Parser_Node $node ): string {
		$collate_node = $node->get_first_descendant_node( 'collationName' );
		if ( null === $collate_node ) {
			// @TODO: Use default DB collation or DB_CHARSET & DB_COLLATE.
			return 'utf8mb4_0900_ai_ci';
		}
		return strtolower( $this->get_value( $collate_node ) );
	}

	/**
	 * Extract table comment from the "createStatement" AST node.
	 *
	 * @param  WP_Parser_Node $node The "createStatement" AST node with "createTable" child.
	 * @return string               The table comment as stored in information schema.
	 */
	private function get_table_comment( WP_Parser_Node $node ): string {
		foreach ( $node->get_descendant_nodes( 'createTableOption' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::COMMENT_SYMBOL ) ) {
				return $this->get_value( $attr->get_first_child_node( 'textStringLiteral' ) );
			}
		}
		return '';
	}

	/**
	 * Extract column default value from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node        The "columnDefinition" or "fieldDefinition" AST node.
	 * @param  string         $data_type   The column data type as stored in information schema.
	 * @param  string         $column_name The column name.
	 * @return string|null                 The column default as stored in information schema.
	 */
	private function get_column_default( WP_Parser_Node $node, string $data_type, string $column_name ): ?string {
		$default_attr = null;
		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::DEFAULT_SYMBOL ) ) {
				$default_attr = $attr;
			}
		}

		if ( null === $default_attr ) {
			return null;
		}
		$datetime_precision = $this->get_column_datetime_precision( $node, $data_type );
		return $this->get_default_value( $default_attr, $data_type, $column_name, $datetime_precision );
	}

	/**
	 * Extract a default value from a column attribute or ALTER COLUMN action.
	 *
	 * @param  WP_Parser_Node $node               The "columnAttribute" or "alterListItem" AST node.
	 * @param  string         $data_type          The column data type as stored in information schema.
	 * @param  string         $column_name        The column name.
	 * @param  int|null       $datetime_precision The column fractional seconds precision.
	 * @return string|null                        The default value as stored in information schema.
	 */
	private function get_default_value( WP_Parser_Node $node, string $data_type, string $column_name, ?int $datetime_precision ): ?string {
		/*
		 * [GRAMMAR]
		 * columnAttribute:
		 *   DEFAULT_SYMBOL (
		 *      signedLiteral
		 *      | NOW_SYMBOL timeFunctionParameters?
		 *      | {serverVersion >= 80013}? exprWithParentheses
		 *   )
		 *
		 * alterListItem:
		 *   ALTER_SYMBOL COLUMN_SYMBOL? fieldIdentifier SET_SYMBOL DEFAULT_SYMBOL (
		 *      {serverVersion >= 80014}? exprWithParentheses
		 *      | signedLiteral
		 *   )
		 */

		// DEFAULT NOW()
		if ( $node->has_child_token( WP_MySQL_Lexer::NOW_SYMBOL ) ) {
			return 'CURRENT_TIMESTAMP';
		}

		// DEFAULT signedLiteral
		$signed_literal = $node->get_first_child_node( 'signedLiteral' );
		if ( $signed_literal ) {
			$literal = $signed_literal->get_first_child_node( 'literal' );
			if ( null === $literal ) {
				// A signed number, such as "-5", has no "literal" child node.
				// Like MySQL, drop the unary plus, e.g., "+5" becomes "5".
				$number  = ltrim( $this->get_value( $signed_literal ), '+' );
				$default = $this->normalize_literal_default( $number, $data_type, $column_name );
				return $this->get_temporal_zero_value( $data_type, $default, $datetime_precision ) ?? $default;
			}
			return $this->get_literal_default( $literal, $data_type, $column_name, $datetime_precision );
		}

		// DEFAULT (expression) - MySQL 8.0.13+ supports exprWithParentheses
		$expr_with_parens = $node->get_first_child_node( 'exprWithParentheses' );
		if ( $expr_with_parens ) {
			return $this->serialize_mysql_expression( $expr_with_parens );
		}

		throw new Exception( 'DEFAULT value of this type is not supported.' );
	}

	/**
	 * Extract and normalize a literal default value.
	 *
	 * @param  WP_Parser_Node $literal            The "literal" AST node.
	 * @param  string         $data_type          The column data type as stored in information schema.
	 * @param  string         $column_name        The column name.
	 * @param  int|null       $datetime_precision The column fractional seconds precision.
	 * @return string|null                        The default value as stored in information schema.
	 */
	private function get_literal_default( WP_Parser_Node $literal, string $data_type, string $column_name, ?int $datetime_precision ): ?string {
		// DEFAULT NULL
		if ( $literal->has_child_node( 'nullLiteral' ) ) {
			return null;
		}

		// DEFAULT TRUE or DEFAULT FALSE
		if ( $literal->has_child_node( 'boolLiteral' ) ) {
			$bool_literal = $literal->get_first_child_node( 'boolLiteral' );
			$bool_value   = $bool_literal->has_child_token( WP_MySQL_Lexer::TRUE_SYMBOL ) ? '1' : '0';
			$default      = 'bit' === $data_type ? "b'{$bool_value}'" : $bool_value;
		} else {
			$default = $this->normalize_literal_default( $this->get_value( $literal ), $data_type, $column_name );
		}

		/*
		 * Normalize numeric zero defaults to MySQL's temporal zero values.
		 * Also normalize string zeros, even where strict MySQL rejects them.
		 * Preserve YEAR strings '0' and '00', which MySQL interprets as 2000.
		 */
		if ( 'year' === $data_type && $literal->has_child_node( 'textLiteral' ) ) {
			return $default;
		}
		return $this->get_temporal_zero_value( $data_type, $default, $datetime_precision ) ?? $default;
	}

	/**
	 * Normalize a literal default value for the column data type.
	 *
	 * @param  string $default_value The literal default value.
	 * @param  string $data_type     The column data type as stored in information schema.
	 * @param  string $column_name   The column name.
	 * @return string                The default value as stored in information schema.
	 */
	private function normalize_literal_default( string $default_value, string $data_type, string $column_name ): string {
		if ( 'bit' === $data_type ) {
			/*
			 * @TODO: Validate and normalize defaults from their AST and the full
			 * column definition before storing them in the information schema.
			 */
			$bit_default = $this->get_bit_default( $default_value );
			if ( null === $bit_default ) {
				throw WP_SQLite_Information_Schema_Exception::invalid_default_value( $column_name );
			}
			return $bit_default;
		}

		/*
		 * @TODO: Non-BIT literal defaults, except for temporal zero values, are
		 * currently stored verbatim. To match MySQL, they should be normalized.
		 * For example:
		 *  - BINARY/VARBINARY defaults stored as 0x hex literals.
		 *  - Hex and bit literals coerced to raw bytes for text columns and to
		 *    their decimal value for numeric columns.
		 *  - The charset introducer dropped from text-literal defaults.
		 *  - Numeric defaults normalized, e.g. 1.0 to 1 or 1e3 to 1000.
		 */
		return $default_value;
	}

	/**
	 * Get the zero value of a temporal column when its default is zero.
	 *
	 * For example, a 0, '0', -0.0, or 0e0 default of a DATETIME(3) column has
	 * the zero value of "0000-00-00 00:00:00.000".
	 *
	 * @param  string   $data_type          The column data type as stored in information schema.
	 * @param  string   $default_value      The column default value of a number or string literal.
	 * @param  int|null $datetime_precision The column fractional seconds precision.
	 * @return string|null                  The zero value, or null when the column is not temporal
	 *                                      or the default is not zero.
	 */
	private function get_temporal_zero_value( string $data_type, string $default_value, ?int $datetime_precision ): ?string {
		$zero_value = self::TEMPORAL_ZERO_VALUE_MAP[ $data_type ] ?? null;

		// A signed number can be a hex literal, such as "-0x0", which is not numeric.
		if ( null === $zero_value || ! is_numeric( $default_value ) || 0.0 !== (float) $default_value ) {
			return null;
		}

		if ( $datetime_precision > 0 ) {
			$zero_value .= '.' . str_repeat( '0', $datetime_precision );
		}
		return $zero_value;
	}

	/**
	 * Normalize a BIT default value to a MySQL bit literal.
	 *
	 * The value may be a bit literal, a hex literal, or a non-negative decimal.
	 *
	 * @param  string      $default_value The BIT default value.
	 * @return string|null                The default as a bit literal, e.g. "b'101'", or null when not a bit value.
	 */
	private function get_bit_default( string $default_value ): ?string {
		$value = strtolower( $default_value );

		// An empty string coerces to zero.
		if ( '' === $value ) {
			return "b'0'";
		}

		// Bit literal, e.g. b'101' or 0b101.
		if (
			preg_match( "/\Ab'([01]*)'\z/", $value, $matches )
			|| preg_match( '/\A0b([01]+)\z/', $value, $matches )
		) {
			$bits = ltrim( $matches[1], '0' );
			return "b'" . ( '' === $bits ? '0' : $bits ) . "'";
		}

		// Hex literal, e.g. x'05' or 0x05.
		if (
			preg_match( "/\Ax'([0-9a-f]*)'\z/", $value, $matches )
			|| preg_match( '/\A0x([0-9a-f]+)\z/', $value, $matches )
		) {
			return "b'" . decbin( hexdec( $matches[1] ) ) . "'";
		}

		// Non-negative decimal, e.g. 5, or a numeric string literal such as '0'.
		if ( is_numeric( $value ) && $value >= 0 ) {
			return "b'" . decbin( (int) $value ) . "'";
		}

		return null;
	}

	/**
	 * Extract column nullability from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node The "columnDefinition" or "fieldDefinition" AST node.
	 * @return string               The column nullability as stored in information schema.
	 */
	private function get_column_nullable( WP_Parser_Node $node ): string {
		// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
		$data_type = $node->get_first_descendant_node( 'dataType' );
		if ( null !== $data_type->get_first_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
			return 'NO';
		}

		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			// PRIMARY KEY columns are always NOT NULL.
			if ( $attr->has_child_token( WP_MySQL_Lexer::KEY_SYMBOL ) ) {
				return 'NO';
			}

			// Check for NOT NULL attribute.
			if (
				$attr->has_child_token( WP_MySQL_Lexer::NOT_SYMBOL )
				&& $attr->has_child_node( 'nullLiteral' )
			) {
				return 'NO';
			}
		}
		return 'YES';
	}

	/**
	 * Extract column key info from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node The "columnDefinition" or "fieldDefinition" AST node.
	 * @return string               The column key info as stored in information schema.
	 */
	private function get_column_key( WP_Parser_Node $node ): string {
		// 1. PRI: Column is a primary key or its any component.
		if (
			null !== $node->get_first_descendant_token( WP_MySQL_Lexer::KEY_SYMBOL )
		) {
			return 'PRI';
		}

		// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
		$data_type = $node->get_first_descendant_node( 'dataType' );
		if ( null !== $data_type->get_first_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
			return 'PRI';
		}

		// 2. UNI: Column has UNIQUE constraint.
		if ( null !== $node->get_first_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL ) ) {
			return 'UNI';
		}

		// 3. MUL: Column has INDEX.
		if ( null !== $node->get_first_descendant_token( WP_MySQL_Lexer::INDEX_SYMBOL ) ) {
			return 'MUL';
		}

		return '';
	}

	/**
	 * Extract column extra from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node The "columnDefinition" or "fieldDefinition" AST node.
	 * @return string               The column extra as stored in information schema.
	 */
	private function get_column_extra( WP_Parser_Node $node ): string {
		$extras     = array();
		$attributes = $node->get_descendant_nodes( 'columnAttribute' );

		// SERIAL
		$data_type = $node->get_first_descendant_node( 'dataType' );
		if ( null !== $data_type->get_first_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
			return 'auto_increment';
		}

		// AUTO_INCREMENT columns can't have a DEFAULT value.
		foreach ( $attributes as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL ) ) {
				return 'auto_increment';
			}
		}

		// Check whether DEFAULT value is generated.
		foreach ( $attributes as $attr ) {
			if (
				$attr->has_child_token( WP_MySQL_Lexer::DEFAULT_SYMBOL )
				&& (
					$attr->has_child_node( 'exprWithParentheses' )
					|| $attr->has_child_token( WP_MySQL_Lexer::NOW_SYMBOL )
				)
			) {
				$extras[] = 'DEFAULT_GENERATED';
			}
		}

		// Check for ON UPDATE CURRENT_TIMESTAMP.
		foreach ( $attributes as $attr ) {
			if (
				$attr->has_child_token( WP_MySQL_Lexer::ON_SYMBOL )
				&& $attr->has_child_token( WP_MySQL_Lexer::UPDATE_SYMBOL )
			) {
				$extras[] = 'on update CURRENT_TIMESTAMP';
			}
		}

		// Check for generated columns.
		if ( $node->get_first_descendant_token( WP_MySQL_Lexer::VIRTUAL_SYMBOL ) ) {
			$extras[] = 'VIRTUAL GENERATED';
		} elseif ( $node->get_first_descendant_token( WP_MySQL_Lexer::STORED_SYMBOL ) ) {
			$extras[] = 'STORED GENERATED';
		}
		return implode( ' ', $extras );
	}

	/**
	 * Extract column comment from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node The "columnDefinition" or "fieldDefinition" AST node.
	 * @return string               The column comment as stored in information schema.
	 */
	private function get_column_comment( WP_Parser_Node $node ): string {
		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::COMMENT_SYMBOL ) ) {
				return $this->get_value( $attr->get_first_child_node( 'textLiteral' ) );
			}
		}
		return '';
	}

	/**
	 * Extract column data type from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node    The "columnDefinition" or "fieldDefinition" AST node.
	 * @return array{ string, string } The data type and column type as stored in information schema.
	 */
	private function get_column_data_types( WP_Parser_Node $node ): array {
		$type_node = $node->get_first_descendant_node( 'dataType' );
		$type      = $type_node->get_descendant_tokens();
		$token     = $type[0];

		// Normalize types.
		if ( isset( self::TOKEN_TO_TYPE_MAP[ $token->id ] ) ) {
			$type = self::TOKEN_TO_TYPE_MAP[ $token->id ];
		} elseif (
			// VARCHAR/NVARCHAR
			// NCHAR/NATIONAL VARCHAR
			// CHAR/CHARACTER/NCHAR VARYING
			// NATIONAL CHAR/CHARACTER VARYING
			WP_MySQL_Lexer::VARCHAR_SYMBOL === $token->id
			|| WP_MySQL_Lexer::NVARCHAR_SYMBOL === $token->id
			|| ( isset( $type[1] ) && WP_MySQL_Lexer::VARCHAR_SYMBOL === $type[1]->id )
			|| ( isset( $type[1] ) && WP_MySQL_Lexer::VARYING_SYMBOL === $type[1]->id )
			|| ( isset( $type[2] ) && WP_MySQL_Lexer::VARYING_SYMBOL === $type[2]->id )
		) {
			$type = 'varchar';
		} elseif (
			// CHAR, NCHAR, NATIONAL CHAR
			WP_MySQL_Lexer::CHAR_SYMBOL === $token->id
			|| WP_MySQL_Lexer::NCHAR_SYMBOL === $token->id
			|| isset( $type[1] ) && WP_MySQL_Lexer::CHAR_SYMBOL === $type[1]->id
		) {
			$type = 'char';
		} elseif (
			// LONG VARBINARY
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::VARBINARY_SYMBOL === $type[1]->id
		) {
			$type = 'mediumblob';
		} elseif (
			// LONG CHAR/CHARACTER, LONG CHAR/CHARACTER VARYING
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::CHAR_SYMBOL === $type[1]->id
		) {
			$type = 'mediumtext';
		} elseif (
			// LONG VARCHAR
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::VARCHAR_SYMBOL === $type[1]->id
		) {
			$type = 'mediumtext';
		} else {
			throw new \RuntimeException( 'Unknown data type: ' . $token->get_value() );
		}

		// Get full type.
		$full_type = $type;
		if ( 'enum' === $type || 'set' === $type ) {
			$string_list = $type_node->get_first_descendant_node( 'stringList' );
			$values      = $string_list->get_child_nodes( 'textString' );
			foreach ( $values as $i => $value ) {
				$values[ $i ] = "'" . str_replace( "'", "''", $this->get_value( $value ) ) . "'";
			}
			$full_type .= '(' . implode( ',', $values ) . ')';
		}

		$field_length = $type_node->get_first_descendant_node( 'fieldLength' );
		if ( null !== $field_length ) {
			if ( 'decimal' === $type || 'float' === $type || 'double' === $type ) {
				$full_type .= rtrim( $this->get_value( $field_length ), ')' ) . ',0)';
			} else {
				$full_type .= $this->get_value( $field_length );
			}
			/*
			 * As of MySQL 8.0.17, the display width attribute is deprecated for
			 * integer types (tinyint, smallint, mediumint, int/integer, bigint)
			 * and is not stored anymore. However, it may be important for older
			 * versions and WP's dbDelta, so it is safer to keep it at the moment.
			 * @TODO: Investigate if it is important to keep this.
			 */
		}

		$precision = $type_node->get_first_descendant_node( 'precision' );
		if ( null !== $precision ) {
			$full_type .= $this->get_value( $precision );
		}

		$datetime_precision = $type_node->get_first_descendant_node( 'typeDatetimePrecision' );
		if ( null !== $datetime_precision ) {
			$full_type .= $this->get_value( $datetime_precision );
		}

		if (
			WP_MySQL_Lexer::BOOL_SYMBOL === $token->id
			|| WP_MySQL_Lexer::BOOLEAN_SYMBOL === $token->id
		) {
			$full_type .= '(1)'; // Add length for booleans.
		}

		if ( null === $field_length && null === $precision ) {
			if ( 'decimal' === $type ) {
				$full_type .= '(10,0)'; // Add default precision for decimals.
			} elseif ( 'char' === $type || 'bit' === $type || 'binary' === $type ) {
				$full_type .= '(1)';    // Add default length for char, bit, binary.
			}
		}

		// UNSIGNED.
		// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
		if (
			$type_node->get_first_descendant_token( WP_MySQL_Lexer::UNSIGNED_SYMBOL )
			|| $type_node->get_first_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL )
		) {
			$full_type .= ' unsigned';
		}

		// ZEROFILL.
		if ( $type_node->get_first_descendant_token( WP_MySQL_Lexer::ZEROFILL_SYMBOL ) ) {
			$full_type .= ' zerofill';
		}

		return array( $type, $full_type );
	}

	/**
	 * Extract column charset and collation from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node              The "columnDefinition" or "fieldDefinition" AST node.
	 * @param  string         $data_type         The column data type as stored in information schema.
	 * @return array{ string|null, string|null } The column charset and collation as stored in information schema.
	 */
	private function get_column_charset_and_collation( WP_Parser_Node $node, string $data_type ): array {
		if ( ! (
			'char' === $data_type
			|| 'varchar' === $data_type
			|| 'tinytext' === $data_type
			|| 'text' === $data_type
			|| 'mediumtext' === $data_type
			|| 'longtext' === $data_type
			|| 'enum' === $data_type
			|| 'set' === $data_type
		) ) {
			return array( null, null );
		}

		$charset   = null;
		$collation = null;
		$is_binary = false;

		// Charset.
		$charset_node = $node->get_first_descendant_node( 'charsetWithOptBinary' );
		if ( null !== $charset_node ) {
			$charset_name_node = $charset_node->get_first_child_node( 'charsetName' );
			if ( null !== $charset_name_node ) {
				$charset = strtolower( $this->get_value( $charset_name_node ) );
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::ASCII_SYMBOL ) ) {
				$charset = 'latin1';
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::UNICODE_SYMBOL ) ) {
				$charset = 'ucs2';
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::BYTE_SYMBOL ) ) {
				// @TODO: This changes varchar to varbinary.
			}

			// @TODO: "DEFAULT"

			if ( $charset_node->has_child_token( WP_MySQL_Lexer::BINARY_SYMBOL ) ) {
				$is_binary = true;
			}
		} else {
			// National charsets (in MySQL, it's "utf8").
			$data_type_node = $node->get_first_descendant_node( 'dataType' );
			if (
				$data_type_node->has_child_node( 'nchar' )
				|| $data_type_node->has_child_token( WP_MySQL_Lexer::NCHAR_SYMBOL )
				|| $data_type_node->has_child_token( WP_MySQL_Lexer::NATIONAL_SYMBOL )
				|| $data_type_node->has_child_token( WP_MySQL_Lexer::NVARCHAR_SYMBOL )
			) {
				$charset = 'utf8';
			}
		}

		// Normalize charset.
		if ( 'utf8mb3' === $charset ) {
			$charset = 'utf8';
		}

		// Collation.
		$collation_node = $node->get_first_descendant_node( 'collationName' );
		if ( null !== $collation_node ) {
			$collation = strtolower( $this->get_value( $collation_node ) );
		}

		// Defaults.
		// @TODO: These are hardcoded now. We should get them from table/DB.
		if ( null === $charset && null === $collation ) {
			$charset = 'utf8mb4';
			// @TODO: "BINARY" (seems to change varchar to varbinary).
			// @TODO: "DEFAULT"
		}

		// If only one of charset/collation is set, the other one is derived.
		if ( null === $collation ) {
			if ( $is_binary ) {
				$collation = $charset . '_bin';
			} elseif ( isset( self::CHARSET_DEFAULT_COLLATION_MAP[ $charset ] ) ) {
				$collation = self::CHARSET_DEFAULT_COLLATION_MAP[ $charset ];
			} else {
				$collation = $charset . '_general_ci';
			}
		} elseif ( null === $charset ) {
			$charset = substr( $collation, 0, strpos( $collation, '_' ) );
		}

		return array( $charset, $collation );
	}

	/**
	 * Extract column length info from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node        The "columnDefinition" or "fieldDefinition" AST node.
	 * @param  string         $data_type   The column data type as stored in information schema.
	 * @param  string|null    $charset     The column charset as stored in information schema.
	 * @return array{ int|null, int|null } The column char length and octet length as stored in information schema.
	 */
	private function get_column_lengths( WP_Parser_Node $node, string $data_type, ?string $charset ): array {
		// Text and blob types.
		if ( 'tinytext' === $data_type || 'tinyblob' === $data_type ) {
			return array( 255, 255 );
		} elseif ( 'text' === $data_type || 'blob' === $data_type ) {
			return array( 65535, 65535 );
		} elseif ( 'mediumtext' === $data_type || 'mediumblob' === $data_type ) {
			return array( 16777215, 16777215 );
		} elseif ( 'longtext' === $data_type || 'longblob' === $data_type ) {
			return array( 4294967295, 4294967295 );
		}

		// For CHAR, VARCHAR, BINARY, VARBINARY, we need to check the field length.
		if (
			'char' === $data_type
			|| 'binary' === $data_type
			|| 'varchar' === $data_type
			|| 'varbinary' === $data_type
		) {
			$field_length = $node->get_first_descendant_node( 'fieldLength' );
			if ( null === $field_length ) {
				$length = 1;
			} else {
				$length = (int) trim( $this->get_value( $field_length ), '()' );
			}

			if ( 'char' === $data_type || 'varchar' === $data_type ) {
				$max_bytes_per_char = self::CHARSET_MAX_BYTES_MAP[ $charset ] ?? 1;
				return array( $length, $max_bytes_per_char * $length );
			} else {
				return array( $length, $length );
			}
		}

		// For ENUM and SET, we need to check the longest value.
		if ( 'enum' === $data_type || 'set' === $data_type ) {
			$string_list = $node->get_first_descendant_node( 'stringList' );
			$values      = $string_list->get_child_nodes( 'textString' );
			$length      = 0;
			foreach ( $values as $value ) {
				if ( 'enum' === $data_type ) {
					$length = max( $length, strlen( $this->get_value( $value ) ) );
				} else {
					$length += strlen( $this->get_value( $value ) );
				}
			}
			if ( 'set' === $data_type ) {
				if ( 2 === count( $values ) ) {
					$length += 1;
				} elseif ( count( $values ) > 2 ) {
					$length += 2;
				}
			}
			$max_bytes_per_char = self::CHARSET_MAX_BYTES_MAP[ $charset ] ?? 1;
			return array( $length, $max_bytes_per_char * $length );
		}

		return array( null, null );
	}

	/**
	 * Extract column precision and scale from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node        The "columnDefinition" or "fieldDefinition" AST node.
	 * @param  string         $data_type   The column data type as stored in information schema.
	 * @return array{ int|null, int|null } The column precision and scale as stored in information schema.
	 */
	private function get_column_numeric_attributes( WP_Parser_Node $node, string $data_type ): array {
		if ( 'tinyint' === $data_type ) {
			return array( 3, 0 );
		} elseif ( 'smallint' === $data_type ) {
			return array( 5, 0 );
		} elseif ( 'mediumint' === $data_type ) {
			return array( 7, 0 );
		} elseif ( 'int' === $data_type ) {
			return array( 10, 0 );
		} elseif ( 'bigint' === $data_type ) {
			if ( null !== $node->get_first_descendant_token( WP_MySQL_Lexer::UNSIGNED_SYMBOL ) ) {
				return array( 20, 0 );
			}

			// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
			$data_type = $node->get_first_descendant_node( 'dataType' );
			if ( null !== $data_type->get_first_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
				return array( 20, 0 );
			}

			return array( 19, 0 );
		}

		// For bit columns, we need to check the precision.
		if ( 'bit' === $data_type ) {
			$field_length = $node->get_first_descendant_node( 'fieldLength' );
			if ( null === $field_length ) {
				return array( 1, null );
			}
			return array( (int) trim( $this->get_value( $field_length ), '()' ), null );
		}

		// For floating point numbers, we need to check the precision and scale.
		$precision      = null;
		$scale          = null;
		$precision_node = $node->get_first_descendant_node( 'precision' );
		if ( null !== $precision_node ) {
			$values    = $precision_node->get_descendant_tokens( WP_MySQL_Lexer::INT_NUMBER );
			$precision = (int) $values[0]->get_value();
			$scale     = (int) $values[1]->get_value();
		}

		if ( 'float' === $data_type ) {
			return array( $precision ?? 12, $scale );
		} elseif ( 'double' === $data_type ) {
			return array( $precision ?? 22, $scale );
		} elseif ( 'decimal' === $data_type ) {
			if ( null === $precision ) {
				// Only precision can be specified ("fieldLength" in the grammar).
				$field_length = $node->get_first_descendant_node( 'fieldLength' );
				if ( null !== $field_length ) {
					$precision = (int) trim( $this->get_value( $field_length ), '()' );
				}
			}
			return array( $precision ?? 10, $scale ?? 0 );
		}

		return array( null, null );
	}

	/**
	 * Extract column date/time precision from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node      The "columnDefinition" or "fieldDefinition" AST node.
	 * @param  string         $data_type The column data type as stored in information schema.
	 * @return int|null                  The date/time precision as stored in information schema.
	 */
	private function get_column_datetime_precision( WP_Parser_Node $node, string $data_type ): ?int {
		if ( 'time' === $data_type || 'datetime' === $data_type || 'timestamp' === $data_type ) {
			// Expressions in the column definition may have their own precision,
			// e.g., "CAST(... AS DATETIME(6))", so only the data type is checked.
			$type_node = $node->get_first_descendant_node( 'dataType' );
			$precision = $type_node->get_first_descendant_node( 'typeDatetimePrecision' );
			if ( null === $precision ) {
				return 0;
			} else {
				return (int) $precision->get_first_child_token( WP_MySQL_Lexer::INT_NUMBER )->get_value();
			}
		}
		return null;
	}

	/**
	 * Extract column generation expression from the "columnDefinition" or "fieldDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node The "columnDefinition" or "fieldDefinition" AST node.
	 * @return string               The column generation expression as stored in information schema.
	 */
	private function get_column_generation_expression( WP_Parser_Node $node ): string {
		if ( null !== $node->get_first_descendant_token( WP_MySQL_Lexer::GENERATED_SYMBOL ) ) {
			$expr = $node->get_first_descendant_node( 'exprWithParentheses' );
			return $this->get_value( $expr );
		}
		return '';
	}

	/**
	 * Extract table constraint name from the "tableConstraintDef" or "columnDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node       The "tableConstraintDef" or "columnDefinition" AST node.
	 * @param  string         $table_name The table name.
	 * @return string|null                The table constraint name.
	 */
	public function get_table_constraint_name( WP_Parser_Node $node, string $table_name ): ?string {
		$name_node = $node->get_first_child_node( 'constraintName' );
		if ( null !== $name_node ) {
			return $this->get_value( $name_node->get_first_child_node( 'identifier' ) );
		}

		$foreign_key      = $node->get_first_descendant_node( 'references' );
		$check_constraint = $node->get_first_descendant_node( 'checkConstraint' );

		// FOREIGN KEY and CHECK constraints without a name get a generated name.
		if ( $foreign_key || $check_constraint ) {
			$type = $check_constraint ? 'chk' : 'ibfk';

			// Get the highest existing name in format "<table_name>_<type>_<number>".
			$existing_names = $this->connection->query(
				sprintf(
					"SELECT DISTINCT constraint_name
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					AND (constraint_name LIKE ? ESCAPE '\\')",
					$this->connection->quote_identifier(
						$this->get_table_name(
							$this->temporary_table_exists( $table_name ),
							'table_constraints'
						)
					)
				),
				array(
					self::SAVED_DATABASE_NAME,
					$table_name,
					str_replace( array( '_', '%' ), array( '\\_', '\\%' ), $table_name ) . "\\_{$type}\\_%",
				)
			)->fetchAll(
				PDO::FETCH_COLUMN // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
			);

			$last_name_index = 0;
			foreach ( $existing_names as $existing_name ) {
				$parts     = explode( '_', $existing_name );
				$last_part = end( $parts );
				if ( strlen( $last_part ) === strspn( $last_part, '0123456789' ) ) {
					$last_name_index = (int) max( $last_name_index, (int) $last_part );
				}
			}
			return $table_name . "_{$type}_" . ( $last_name_index + 1 );
		}

		return null;
	}

	/**
	 * Extract table constraint type from the "tableConstraintDef" or "columnDefinition" AST node.
	 *
	 * @param  WP_Parser_Node $node The "tableConstraintDef" or "columnDefinition" AST node.
	 * @return string|null          The table constraint type as stored in information schema.
	 */
	private function get_table_constraint_type( WP_Parser_Node $node ): ?string {
		if ( $node->get_first_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
			return 'PRIMARY KEY';
		}
		if ( $node->get_first_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL ) ) {
			return 'UNIQUE';
		}
		if ( $node->get_first_descendant_node( 'references' ) ) {
			return 'FOREIGN KEY';
		}
		if ( $node->get_first_descendant_node( 'checkConstraint' ) ) {
			return 'CHECK';
		}
		return null;
	}

	/**
	 * Extract index name from the "tableConstraintDef" AST node.
	 *
	 * @param  WP_Parser_Node $node       The "tableConstraintDef" or "createIndex" AST node.
	 * @param  string         $table_name The table name.
	 * @return string                     The index name as stored in information schema.
	 */
	private function get_index_name( WP_Parser_Node $node, string $table_name ): string {
		if ( $node->get_first_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
			return 'PRIMARY';
		}

		/*
		 * Get index name.
		 *
		 * When both index and constraint name are defined, the index name will
		 * be used. E.g., in "CONSTRAINT c UNIQUE u (id)", the name will be "u".
		 */
		$name_node = $node->get_first_descendant_node( 'indexName' );
		if ( null === $name_node && $node->has_child_node( 'constraintName' ) ) {
			$name_node = $node
				->get_first_child_node( 'constraintName' )
				->get_first_child_node( 'identifier' );
		}

		if ( null === $name_node ) {
			/*
			 * In MySQL, the default index name equals the first column name.
			 * If any part is an expression, the name will be "functional_index".
			 * If the name is already used, we need to append a number.
			 */
			$subnode = $node->get_first_child_node( 'keyListVariants' )->get_first_child_node();
			if ( null !== $subnode->get_first_descendant_node( 'exprWithParentheses' ) ) {
				$name = 'functional_index';
			} else {
				$name = $this->get_value( $subnode->get_first_descendant_node( 'identifier' ) );
			}

			// Check if the name is already used.
			$existing_indices = $this->connection->query(
				sprintf(
					"SELECT DISTINCT index_name
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					AND (index_name = ? OR index_name LIKE ? ESCAPE '\\')",
					$this->connection->quote_identifier(
						$this->get_table_name(
							$this->temporary_table_exists( $table_name ),
							'statistics'
						)
					)
				),
				array(
					self::SAVED_DATABASE_NAME,
					$table_name,
					$name,
					str_replace( array( '_', '%' ), array( '\\_', '\\%' ), $name ) . '\\_%',
				)
			)->fetchAll(
				PDO::FETCH_COLUMN // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
			);

			// The name is not used - we can use it as-is.
			if ( count( $existing_indices ) === 0 ) {
				return $name;
			}

			// The name is used - find the first unused name.
			$new_name = $name;
			$suffix   = 2;
			while ( in_array( $new_name, $existing_indices, true ) ) {
				$new_name = $name . '_' . $suffix;
				$suffix  += 1;
			}
			return $new_name;
		}
		return $this->get_value( $name_node );
	}

	/**
	 * Extract index non-unique value from the "tableConstraintDef" AST node.
	 *
	 * @param  WP_MySQL_Token $token The first constraint keyword.
	 * @return int                   The value of non-unique as stored in information schema.
	 */
	private function get_index_non_unique( WP_MySQL_Token $token ): int {
		if (
			WP_MySQL_Lexer::PRIMARY_SYMBOL === $token->id
			|| WP_MySQL_Lexer::UNIQUE_SYMBOL === $token->id
		) {
			return 0;
		}
		return 1;
	}

	/**
	 * Extract index type from the "tableConstraintDef" AST node.
	 *
	 * @param  WP_Parser_Node $node               The "tableConstraintDef" or "createIndex" AST node.
	 * @param  WP_MySQL_Token $token              The first constraint keyword.
	 * @param  bool           $has_spatial_column Whether the index contains a spatial column.
	 * @return string                             The index type as stored in information schema.
	 */
	private function get_index_type(
		WP_Parser_Node $node,
		WP_MySQL_Token $token,
		bool $has_spatial_column
	): string {
		// Handle "USING ..." clause.
		$index_type_node = $node->get_first_descendant_node( 'indexType' );
		if ( null !== $index_type_node ) {
			$index_type = strtoupper( $this->get_value( $index_type_node ) );
			if ( 'RTREE' === $index_type ) {
				return 'SPATIAL';
			} elseif ( 'HASH' === $index_type ) {
				// InnoDB uses BTREE even when HASH is specified.
				return 'BTREE';
			}
			return $index_type;
		}

		// Derive index type from its definition.
		if ( WP_MySQL_Lexer::FULLTEXT_SYMBOL === $token->id ) {
			return 'FULLTEXT';
		} elseif ( WP_MySQL_Lexer::SPATIAL_SYMBOL === $token->id ) {
			return 'SPATIAL';
		}

		// Spatial indexes are also derived from column data type.
		if ( $has_spatial_column ) {
			return 'SPATIAL';
		}

		return 'BTREE';
	}

	/**
	 * Extract index comment from the "tableConstraintDef" AST node.
	 *
	 * @param  WP_Parser_Node $node The "tableConstraintDef" or "createIndex" AST node.
	 * @return string               The index comment as stored in information schema.
	 */
	public function get_index_comment( WP_Parser_Node $node ): string {
		foreach ( $node->get_descendant_nodes( 'commonIndexOption' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::COMMENT_SYMBOL ) ) {
				return $this->get_value( $attr->get_first_child_node( 'textLiteral' ) );
			}
		}
		return '';
	}

	/**
	 * Extract index column name from the "keyPart" AST node.
	 *
	 * @param  WP_Parser_Node $node The "keyPart" AST node.
	 * @return string               The index column name as stored in information schema.
	 */
	private function get_index_column_name( WP_Parser_Node $node ): ?string {
		if ( 'keyPart' !== $node->rule_name ) {
			return null;
		}
		return $this->get_value( $node->get_first_descendant_node( 'identifier' ) );
	}

	/**
	 * Extract index column name from the "keyPart" AST node.
	 *
	 * @param  WP_Parser_Node $node       The "keyPart" AST node.
	 * @param  string         $index_type The index type as stored in information schema.
	 * @return string                     The index column name as stored in information schema.
	 */
	private function get_index_column_collation( WP_Parser_Node $node, string $index_type ): ?string {
		if ( 'FULLTEXT' === $index_type ) {
			return null;
		}

		$collate_node = $node->get_first_descendant_node( 'direction' );
		if ( null === $collate_node ) {
			return 'A';
		}
		$collate = strtoupper( $this->get_value( $collate_node ) );
		return 'DESC' === $collate ? 'D' : 'A';
	}

	/**
	 * Extract index column sub-part value from the "keyPart" AST node.
	 *
	 * @param  WP_Parser_Node $node       The "keyPart" AST node.
	 * @param  int|null       $max_length The maximum character length of the index column.
	 * @param  bool           $is_spatial Whether the index column is a spatial column.
	 * @return int|null                   The index column sub-part value as stored in information schema.
	 */
	private function get_index_column_sub_part(
		WP_Parser_Node $node,
		?int $max_length,
		bool $is_spatial
	): ?int {
		$field_length = $node->get_first_descendant_node( 'fieldLength' );
		if ( null === $field_length ) {
			if ( $is_spatial ) {
				return 32;
			}
			return null;
		}

		$value = (int) trim( $this->get_value( $field_length ), '()' );
		if ( null !== $max_length && $value >= $max_length ) {
			return $max_length;
		}
		return $value;
	}

	/**
	 * Extract foreign key UPDATE and DELETE actions from the "references" AST node.
	 *
	 * @param  WP_Parser_Node $node  The "references" AST node.
	 * @return array<string, string> The foreign key actions as stored in information schema.
	 */
	private function get_foreign_key_actions( WP_Parser_Node $node ): array {
		$children = $node->get_children();

		// ON UPDATE and ON DELETE both use the "deleteOption" node.
		$update_option = null;
		$delete_option = null;
		foreach ( $children as $i => $child ) {
			if ( $child instanceof WP_MySQL_Token && WP_MySQL_Lexer::UPDATE_SYMBOL === $child->id ) {
				$update_option = $children[ $i + 1 ];
			} elseif ( $child instanceof WP_MySQL_Token && WP_MySQL_Lexer::DELETE_SYMBOL === $child->id ) {
				$delete_option = $children[ $i + 1 ];
			}
		}

		$result = array(
			'on_update' => 'NO ACTION',
			'on_delete' => 'NO ACTION',
		);
		foreach ( array( 'on_update', 'on_delete' ) as $action ) {
			$option = 'on_update' === $action ? $update_option : $delete_option;
			if ( null === $option ) {
				continue;
			}

			$tokens    = $option->get_descendant_tokens();
			$token1_id = isset( $tokens[0] ) ? $tokens[0]->id : null;
			$token2_id = isset( $tokens[1] ) ? $tokens[1]->id : null;
			if ( WP_MySQL_Lexer::NO_SYMBOL === $token1_id ) {
				$result[ $action ] = 'NO ACTION';
			} elseif ( WP_MySQL_Lexer::RESTRICT_SYMBOL === $token1_id ) {
				$result[ $action ] = 'RESTRICT';
			} elseif ( WP_MySQL_Lexer::CASCADE_SYMBOL === $token1_id ) {
				$result[ $action ] = 'CASCADE';
			} elseif ( WP_MySQL_Lexer::SET_SYMBOL === $token1_id && WP_MySQL_Lexer::NULL_SYMBOL === $token2_id ) {
				$result[ $action ] = 'SET NULL';
			} elseif ( WP_MySQL_Lexer::SET_SYMBOL === $token1_id && WP_MySQL_Lexer::DEFAULT_SYMBOL === $token2_id ) {
				$result[ $action ] = 'SET DEFAULT';
			} else {
				throw new \Exception( sprintf( 'Unsupported foreign key action: %s', $option->get_value() ) );
			}
		}
		return $result;
	}

	/**
	 * Determine whether the column data type is a spatial data type.
	 *
	 * @param  string $data_type The column data type as stored in information schema.
	 * @return bool              Whether the column data type is a spatial data type.
	 */
	private function is_spatial_data_type( string $data_type ): bool {
		return 'geometry' === $data_type
			|| 'geomcollection' === $data_type
			|| 'point' === $data_type
			|| 'multipoint' === $data_type
			|| 'linestring' === $data_type
			|| 'multilinestring' === $data_type
			|| 'polygon' === $data_type
			|| 'multipolygon' === $data_type;
	}

	/**
	 * This is a helper function to get the full unescaped value of a node.
	 *
	 * @TODO: This should be done in a more correct way, for names maybe allowing
	 *        descending only a single-child hierarchy, such as these:
	 *          identifier -> pureIdentifier -> IDENTIFIER
	 *          identifier -> pureIdentifier -> BACKTICK_QUOTED_ID
	 *          identifier -> pureIdentifier -> DOUBLE_QUOTED_TEXT
	 *          etc.
	 *
	 *        For saving "DEFAULT ..." in column definitions, we actually need to
	 *        serialize the whole node, in the case of expressions. This may mean
	 *        implementing an MySQL AST -> string printer.
	 *
	 * @param  WP_Parser_Node $node The AST node that needs to be serialized.
	 * @return string               The serialized value of the node.
	 */
	private function get_value( WP_Parser_Node $node ): string {
		$full_value = '';
		foreach ( $node->get_children() as $child ) {
			if ( $child instanceof WP_Parser_Node ) {
				$value = $this->get_value( $child );

				/*
				 * At the moment, we only support ASCII bytes in all identifiers.
				 * This is because SQLite doesn't support case-insensitive Unicode
				 * character matching: https://sqlite.org/faq.html#q18
				 */
				if ( 'pureIdentifier' === $child->rule_name ) {
					for ( $i = 0; $i < strlen( $value ); $i++ ) {
						if ( ord( $value[ $i ] ) > 127 ) {
							throw new Exception( 'The SQLite driver only supports ASCII characters in identifiers.' );
						}
					}
				}
			} else {
				$value = $child->get_value();
			}
			$full_value .= $value;
		}
		return $full_value;
	}

	/**
	 * Serialize a MySQL expression for storing in the information schema.
	 *
	 * This is used for storing DEFAULT and CHECK expressions in the database.
	 *
	 * The current implementation is using a naive approach based on directly
	 * joining the original expression token bytes. This is safe, beacuase the
	 * original tokens must comprise a valid expression. While functionally
	 * equivalent, it is not strictly identical to what MySQL stores, because
	 * MySQL normalizes and prints the expression in a specific format.
	 *
	 * TODO: Consider implementing a MySQL expression node -> string formatter
	 *       that would produce results that are identical to MySQL formatting.
	 *       This gets tricky from MySQL 8, where a double-escaping regression
	 *       was introduced, storing strings like "_utf8mb4\'abc\'" instead of
	 *       "_utf8mb4'abc'", but displaying them correctly in SHOW statements.
	 *       @see https://bugs.mysql.com/bug.php?id=100607
	 *
	 * @param  WP_Parser_Node $node The AST node that needs to be serialized.
	 * @return string               The serialized value of the node.
	 */
	private function serialize_mysql_expression( WP_Parser_Node $node ): string {
		// The wrapping parentheses are generally not stored, although in MySQL,
		// this varies by expression type as per the expression formatter logic.
		if ( 'exprWithParentheses' === $node->rule_name ) {
			return $this->serialize_mysql_expression( $node->get_first_child_node( 'expr' ) );
		}

		$value         = '';
		$last_token_id = null;
		foreach ( $node->get_descendant_tokens() as $i => $token ) {
			// Do not insert whitespace around parentheses. This is primarily to
			// avoid inserting whitespace before '(', which may break function
			// calls, depending on the value of the "IGNORE_SPACE" SQL mode.
			if (
				0 === $i
				|| WP_MySQL_Lexer::OPEN_PAR_SYMBOL === $token->id
				|| WP_MySQL_Lexer::CLOSE_PAR_SYMBOL === $token->id
				|| WP_MySQL_Lexer::OPEN_PAR_SYMBOL === $last_token_id
				|| WP_MySQL_Lexer::CLOSE_PAR_SYMBOL === $last_token_id
			) {
				$value .= $token->get_bytes();
			} else {
				$value .= ' ' . $token->get_bytes();
			}
			$last_token_id = $token->id;
		}
		return $value;
	}

	/**
	 * Insert values into an SQLite table.
	 *
	 * @param string                $table_name The name of the table.
	 * @param array<string, string> $data       The data to insert (key is column name, value is column value).
	 */
	private function insert_values( string $table_name, array $data ): void {
		$insert_columns = array();
		foreach ( $data as $column => $value ) {
			$insert_columns[] = $this->connection->quote_identifier( $column );
		}

		$this->connection->query(
			sprintf(
				'INSERT INTO %s (%s) VALUES (%s)',
				$this->connection->quote_identifier( $table_name ),
				implode( ', ', $insert_columns ),
				implode( ', ', array_fill( 0, count( $data ), '?' ) )
			),
			array_values( $data )
		);
	}

	/**
	 * Update values in an SQLite table.
	 *
	 * @param string                $table_name The name of the table.
	 * @param array<string, string> $data       The data to update (key is column name, value is column value).
	 * @param array<string, string> $where      The WHERE clause conditions (key is column name, value is column value).
	 */
	private function update_values( string $table_name, array $data, array $where ): void {
		$set_statements = array();
		foreach ( $data as $column => $value ) {
			$set_statements[] = $this->connection->quote_identifier( $column ) . ' = ?';
		}

		$where_statements = array();
		foreach ( $where as $column => $value ) {
			$where_statements[] = $this->connection->quote_identifier( $column ) . ' = ?';
		}

		$this->connection->query(
			sprintf(
				'UPDATE %s SET %s WHERE %s',
				$this->connection->quote_identifier( $table_name ),
				implode( ', ', $set_statements ),
				implode( ' AND ', $where_statements )
			),
			array_merge( array_values( $data ), array_values( $where ) )
		);
	}

	/**
	 * Delete values from an SQLite table.
	 *
	 * @param string                $table_name The name of the table.
	 * @param array<string, string> $where      The WHERE clause conditions (key is column name, value is column value).
	 */
	private function delete_values( string $table_name, array $where ): void {
		$where_statements = array();
		foreach ( $where as $column => $value ) {
			if ( null === $value ) {
				$where_statements[] = $this->connection->quote_identifier( $column ) . ' IS NULL';
				unset( $where[ $column ] );
			} else {
				$where_statements[] = $this->connection->quote_identifier( $column ) . ' = ?';
			}
		}

		$this->connection->query(
			sprintf(
				'DELETE FROM %s WHERE %s',
				$this->connection->quote_identifier( $table_name ),
				implode( ' AND ', $where_statements )
			),
			array_values( $where )
		);
	}
}
