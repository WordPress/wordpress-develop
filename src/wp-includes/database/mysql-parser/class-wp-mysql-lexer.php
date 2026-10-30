<?php

/**
 * MySQL lexer.
 *
 * An exhaustive lexer for the MySQL SQL dialect with multi-version support.
 * It scans the provided SQL input payload and generates MySQL token objects.
 * The lexer is intended to cover 100% of the MySQL SQL syntax for all MySQL
 * versions starting from 5.7. It's likely to support older versions as well.
 *
 * The lexer is implemented with zero dependencies, it doesn't require any PHP
 * extensions, and it doesn't use PCRE or any other regular expression engines.
 *
 * This lexer is based on the MySQL Workbench lexer grammar.
 * See:
 *   https://github.com/mysql/mysql-workbench/blob/8.0.38/library/parsers/grammars/MySQLLexer.g4
 *   https://github.com/mysql/mysql-workbench/blob/8.0.38/library/parsers/grammars/predefined.tokens
 *   https://github.com/mysql/mysql-workbench/blob/8.0.38/library/parsers/mysql/MySQLBaseLexer.cpp
 *
 * @access private
 */
class WP_MySQL_Lexer {
	/**
	 * The SQL modes that affect the lexer behavior.
	 *
	 * These values are intended to be used in a bitmask. See "$this->sql_modes".
	 * The list of the SQL modes is not exhaustive. Only the ones that influence
	 * the lexer behavior are included in this list.
	 *
	 * See:
	 *   https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html
	 */
	const SQL_MODE_HIGH_NOT_PRECEDENCE  = 1;
	const SQL_MODE_PIPES_AS_CONCAT      = 2;
	const SQL_MODE_IGNORE_SPACE         = 4;
	const SQL_MODE_NO_BACKSLASH_ESCAPES = 8;
	const SQL_MODE_ANSI_QUOTES          = 16;

	/**
	 * Character masks for frequently used character classes.
	 *
	 * These are intended to be used with "strspn()" and "strcspn()" functions
	 * for fast character class matching in the SQL payload.
	 */
	const WHITESPACE_MASK = " \t\n\r\f\v";
	const DIGIT_MASK      = '0123456789';
	const HEX_DIGIT_MASK  = '0123456789abcdefABCDEF';

	/**
	 * Tokens from the MySQL Workbench "predefined.tokens" list.
	 *
	 * This list preserves the token names and IDs from the MySQL Workbench
	 * "predefined.tokens" list, adding some tokens missing from the list.
	 *
	 * See:
	 *   https://github.com/mysql/mysql-workbench/blob/8.0.38/library/parsers/grammars/predefined.tokens
	 */
	const ACCESSIBLE_SYMBOL                      = 1;
	const ACCOUNT_SYMBOL                         = 2;
	const ACTION_SYMBOL                          = 3;
	const ADD_SYMBOL                             = 4;
	const ADDDATE_SYMBOL                         = 5;
	const AFTER_SYMBOL                           = 6;
	const AGAINST_SYMBOL                         = 7;
	const AGGREGATE_SYMBOL                       = 8;
	const ALGORITHM_SYMBOL                       = 9;
	const ALL_SYMBOL                             = 10;
	const ALTER_SYMBOL                           = 11;
	const ALWAYS_SYMBOL                          = 12;
	const ANALYSE_SYMBOL                         = 13;
	const ANALYZE_SYMBOL                         = 14;
	const AND_SYMBOL                             = 15;
	const ANY_SYMBOL                             = 16;
	const AS_SYMBOL                              = 17;
	const ASC_SYMBOL                             = 18;
	const ASCII_SYMBOL                           = 19;
	const ASENSITIVE_SYMBOL                      = 20;
	const AT_SYMBOL                              = 21;
	const AUTHORS_SYMBOL                         = 22;
	const AUTOEXTEND_SIZE_SYMBOL                 = 23;
	const AUTO_INCREMENT_SYMBOL                  = 24;
	const AVG_ROW_LENGTH_SYMBOL                  = 25;
	const AVG_SYMBOL                             = 26;
	const BACKUP_SYMBOL                          = 27;
	const BEFORE_SYMBOL                          = 28;
	const BEGIN_SYMBOL                           = 29;
	const BETWEEN_SYMBOL                         = 30;
	const BIGINT_SYMBOL                          = 31;
	const BINARY_SYMBOL                          = 32;
	const BINLOG_SYMBOL                          = 33;
	const BIN_NUM_SYMBOL                         = 34;
	const BIT_AND_SYMBOL                         = 35;
	const BIT_OR_SYMBOL                          = 36;
	const BIT_SYMBOL                             = 37;
	const BIT_XOR_SYMBOL                         = 38;
	const BLOB_SYMBOL                            = 39;
	const BLOCK_SYMBOL                           = 40;
	const BOOLEAN_SYMBOL                         = 41;
	const BOOL_SYMBOL                            = 42;
	const BOTH_SYMBOL                            = 43;
	const BTREE_SYMBOL                           = 44;
	const BY_SYMBOL                              = 45;
	const BYTE_SYMBOL                            = 46;
	const CACHE_SYMBOL                           = 47;
	const CALL_SYMBOL                            = 48;
	const CASCADE_SYMBOL                         = 49;
	const CASCADED_SYMBOL                        = 50;
	const CASE_SYMBOL                            = 51;
	const CAST_SYMBOL                            = 52;
	const CATALOG_NAME_SYMBOL                    = 53;
	const CHAIN_SYMBOL                           = 54;
	const CHANGE_SYMBOL                          = 55;
	const CHANGED_SYMBOL                         = 56;
	const CHANNEL_SYMBOL                         = 57;
	const CHARSET_SYMBOL                         = 58;
	const CHARACTER_SYMBOL                       = 59;
	const CHAR_SYMBOL                            = 60;
	const CHECKSUM_SYMBOL                        = 61;
	const CHECK_SYMBOL                           = 62;
	const CIPHER_SYMBOL                          = 63;
	const CLASS_ORIGIN_SYMBOL                    = 64;
	const CLIENT_SYMBOL                          = 65;
	const CLOSE_SYMBOL                           = 66;
	const COALESCE_SYMBOL                        = 67;
	const CODE_SYMBOL                            = 68;
	const COLLATE_SYMBOL                         = 69;
	const COLLATION_SYMBOL                       = 70;
	const COLUMNS_SYMBOL                         = 71;
	const COLUMN_SYMBOL                          = 72;
	const COLUMN_NAME_SYMBOL                     = 73;
	const COLUMN_FORMAT_SYMBOL                   = 74;
	const COMMENT_SYMBOL                         = 75;
	const COMMITTED_SYMBOL                       = 76;
	const COMMIT_SYMBOL                          = 77;
	const COMPACT_SYMBOL                         = 78;
	const COMPLETION_SYMBOL                      = 79;
	const COMPRESSED_SYMBOL                      = 80;
	const COMPRESSION_SYMBOL                     = 81;
	const CONCURRENT_SYMBOL                      = 82;
	const CONDITION_SYMBOL                       = 83;
	const CONNECTION_SYMBOL                      = 84;
	const CONSISTENT_SYMBOL                      = 85;
	const CONSTRAINT_SYMBOL                      = 86;
	const CONSTRAINT_CATALOG_SYMBOL              = 87;
	const CONSTRAINT_NAME_SYMBOL                 = 88;
	const CONSTRAINT_SCHEMA_SYMBOL               = 89;
	const CONTAINS_SYMBOL                        = 90;
	const CONTEXT_SYMBOL                         = 91;
	const CONTINUE_SYMBOL                        = 92;
	const CONTRIBUTORS_SYMBOL                    = 93;
	const CONVERT_SYMBOL                         = 94;
	const COUNT_SYMBOL                           = 95;
	const CPU_SYMBOL                             = 96;
	const CREATE_SYMBOL                          = 97;
	const CROSS_SYMBOL                           = 98;
	const CUBE_SYMBOL                            = 99;
	const CURDATE_SYMBOL                         = 100;
	const CURRENT_SYMBOL                         = 101;
	const CURRENT_DATE_SYMBOL                    = 102;
	const CURRENT_TIME_SYMBOL                    = 103;
	const CURRENT_TIMESTAMP_SYMBOL               = 104;
	const CURRENT_USER_SYMBOL                    = 105;
	const CURSOR_SYMBOL                          = 106;
	const CURSOR_NAME_SYMBOL                     = 107;
	const CURTIME_SYMBOL                         = 108;
	const DATABASE_SYMBOL                        = 109;
	const DATABASES_SYMBOL                       = 110;
	const DATAFILE_SYMBOL                        = 111;
	const DATA_SYMBOL                            = 112;
	const DATETIME_SYMBOL                        = 113;
	const DATE_ADD_SYMBOL                        = 114;
	const DATE_SUB_SYMBOL                        = 115;
	const DATE_SYMBOL                            = 116;
	const DAYOFMONTH_SYMBOL                      = 117;
	const DAY_HOUR_SYMBOL                        = 118;
	const DAY_MICROSECOND_SYMBOL                 = 119;
	const DAY_MINUTE_SYMBOL                      = 120;
	const DAY_SECOND_SYMBOL                      = 121;
	const DAY_SYMBOL                             = 122;
	const DEALLOCATE_SYMBOL                      = 123;
	const DEC_SYMBOL                             = 124;
	const DECIMAL_NUM_SYMBOL                     = 125;
	const DECIMAL_SYMBOL                         = 126;
	const DECLARE_SYMBOL                         = 127;
	const DEFAULT_SYMBOL                         = 128;
	const DEFAULT_AUTH_SYMBOL                    = 129;
	const DEFINER_SYMBOL                         = 130;
	const DELAYED_SYMBOL                         = 131;
	const DELAY_KEY_WRITE_SYMBOL                 = 132;
	const DELETE_SYMBOL                          = 133;
	const DESC_SYMBOL                            = 134;
	const DESCRIBE_SYMBOL                        = 135;
	const DES_KEY_FILE_SYMBOL                    = 136;
	const DETERMINISTIC_SYMBOL                   = 137;
	const DIAGNOSTICS_SYMBOL                     = 138;
	const DIRECTORY_SYMBOL                       = 139;
	const DISABLE_SYMBOL                         = 140;
	const DISCARD_SYMBOL                         = 141;
	const DISK_SYMBOL                            = 142;
	const DISTINCT_SYMBOL                        = 143;
	const DISTINCTROW_SYMBOL                     = 144;
	const DIV_SYMBOL                             = 145;
	const DOUBLE_SYMBOL                          = 146;
	const DO_SYMBOL                              = 147;
	const DROP_SYMBOL                            = 148;
	const DUAL_SYMBOL                            = 149;
	const DUMPFILE_SYMBOL                        = 150;
	const DUPLICATE_SYMBOL                       = 151;
	const DYNAMIC_SYMBOL                         = 152;
	const EACH_SYMBOL                            = 153;
	const ELSE_SYMBOL                            = 154;
	const ELSEIF_SYMBOL                          = 155;
	const ENABLE_SYMBOL                          = 156;
	const ENCLOSED_SYMBOL                        = 157;
	const ENCRYPTION_SYMBOL                      = 158;
	const END_SYMBOL                             = 159;
	const ENDS_SYMBOL                            = 160;
	const END_OF_INPUT_SYMBOL                    = 161; // defined in "predefined.tokens", but not used
	const ENGINES_SYMBOL                         = 162;
	const ENGINE_SYMBOL                          = 163;
	const ENUM_SYMBOL                            = 164;
	const ERROR_SYMBOL                           = 165;
	const ERRORS_SYMBOL                          = 166;
	const ESCAPED_SYMBOL                         = 167;
	const ESCAPE_SYMBOL                          = 168;
	const EVENTS_SYMBOL                          = 169;
	const EVENT_SYMBOL                           = 170;
	const EVERY_SYMBOL                           = 171;
	const EXCHANGE_SYMBOL                        = 172;
	const EXECUTE_SYMBOL                         = 173;
	const EXISTS_SYMBOL                          = 174;
	const EXIT_SYMBOL                            = 175;
	const EXPANSION_SYMBOL                       = 176;
	const EXPIRE_SYMBOL                          = 177;
	const EXPLAIN_SYMBOL                         = 178;
	const EXPORT_SYMBOL                          = 179;
	const EXTENDED_SYMBOL                        = 180;
	const EXTENT_SIZE_SYMBOL                     = 181;
	const EXTRACT_SYMBOL                         = 182;
	const FALSE_SYMBOL                           = 183;
	const FAST_SYMBOL                            = 184;
	const FAULTS_SYMBOL                          = 185;
	const FETCH_SYMBOL                           = 186;
	const FIELDS_SYMBOL                          = 187;
	const FILE_SYMBOL                            = 188;
	const FILE_BLOCK_SIZE_SYMBOL                 = 189;
	const FILTER_SYMBOL                          = 190;
	const FIRST_SYMBOL                           = 191;
	const FIXED_SYMBOL                           = 192;
	const FLOAT4_SYMBOL                          = 193;
	const FLOAT8_SYMBOL                          = 194;
	const FLOAT_SYMBOL                           = 195;
	const FLUSH_SYMBOL                           = 196;
	const FOLLOWS_SYMBOL                         = 197;
	const FORCE_SYMBOL                           = 198;
	const FOREIGN_SYMBOL                         = 199;
	const FOR_SYMBOL                             = 200;
	const FORMAT_SYMBOL                          = 201;
	const FOUND_SYMBOL                           = 202;
	const FROM_SYMBOL                            = 203;
	const FULL_SYMBOL                            = 204;
	const FULLTEXT_SYMBOL                        = 205;
	const FUNCTION_SYMBOL                        = 206;
	const GET_SYMBOL                             = 207;
	const GENERAL_SYMBOL                         = 208;
	const GENERATED_SYMBOL                       = 209;
	const GROUP_REPLICATION_SYMBOL               = 210;
	const GEOMETRYCOLLECTION_SYMBOL              = 211;
	const GEOMETRY_SYMBOL                        = 212;
	const GET_FORMAT_SYMBOL                      = 213;
	const GLOBAL_SYMBOL                          = 214;
	const GRANT_SYMBOL                           = 215;
	const GRANTS_SYMBOL                          = 216;
	const GROUP_SYMBOL                           = 217;
	const GROUP_CONCAT_SYMBOL                    = 218;
	const HANDLER_SYMBOL                         = 219;
	const HASH_SYMBOL                            = 220;
	const HAVING_SYMBOL                          = 221;
	const HELP_SYMBOL                            = 222;
	const HIGH_PRIORITY_SYMBOL                   = 223;
	const HOST_SYMBOL                            = 224;
	const HOSTS_SYMBOL                           = 225;
	const HOUR_MICROSECOND_SYMBOL                = 226;
	const HOUR_MINUTE_SYMBOL                     = 227;
	const HOUR_SECOND_SYMBOL                     = 228;
	const HOUR_SYMBOL                            = 229;
	const IDENTIFIED_SYMBOL                      = 230;
	const IF_SYMBOL                              = 231;
	const IGNORE_SYMBOL                          = 232;
	const IGNORE_SERVER_IDS_SYMBOL               = 233;
	const IMPORT_SYMBOL                          = 234;
	const INDEXES_SYMBOL                         = 235;
	const INDEX_SYMBOL                           = 236;
	const INFILE_SYMBOL                          = 237;
	const INITIAL_SIZE_SYMBOL                    = 238;
	const INNER_SYMBOL                           = 239;
	const INOUT_SYMBOL                           = 240;
	const INSENSITIVE_SYMBOL                     = 241;
	const INSERT_SYMBOL                          = 242;
	const INSERT_METHOD_SYMBOL                   = 243;
	const INSTANCE_SYMBOL                        = 244;
	const INSTALL_SYMBOL                         = 245;
	const INTEGER_SYMBOL                         = 246;
	const INTERVAL_SYMBOL                        = 247;
	const INTO_SYMBOL                            = 248;
	const INT_SYMBOL                             = 249;
	const INVOKER_SYMBOL                         = 250;
	const IN_SYMBOL                              = 251;
	const IO_AFTER_GTIDS_SYMBOL                  = 252;
	const IO_BEFORE_GTIDS_SYMBOL                 = 253;
	const IO_THREAD_SYMBOL                       = 254;
	const IO_SYMBOL                              = 255;
	const IPC_SYMBOL                             = 256;
	const IS_SYMBOL                              = 257;
	const ISOLATION_SYMBOL                       = 258;
	const ISSUER_SYMBOL                          = 259;
	const ITERATE_SYMBOL                         = 260;
	const JOIN_SYMBOL                            = 261;
	const JSON_SYMBOL                            = 262;
	const KEYS_SYMBOL                            = 263;
	const KEY_BLOCK_SIZE_SYMBOL                  = 264;
	const KEY_SYMBOL                             = 265;
	const KILL_SYMBOL                            = 266;
	const LANGUAGE_SYMBOL                        = 267;
	const LAST_SYMBOL                            = 268;
	const LEADING_SYMBOL                         = 269;
	const LEAVES_SYMBOL                          = 270;
	const LEAVE_SYMBOL                           = 271;
	const LEFT_SYMBOL                            = 272;
	const LESS_SYMBOL                            = 273;
	const LEVEL_SYMBOL                           = 274;
	const LIKE_SYMBOL                            = 275;
	const LIMIT_SYMBOL                           = 276;
	const LINEAR_SYMBOL                          = 277;
	const LINES_SYMBOL                           = 278;
	const LINESTRING_SYMBOL                      = 279;
	const LIST_SYMBOL                            = 280;
	const LOAD_SYMBOL                            = 281;
	const LOCALTIME_SYMBOL                       = 282;
	const LOCALTIMESTAMP_SYMBOL                  = 283;
	const LOCAL_SYMBOL                           = 284;
	const LOCATOR_SYMBOL                         = 285;
	const LOCKS_SYMBOL                           = 286;
	const LOCK_SYMBOL                            = 287;
	const LOGFILE_SYMBOL                         = 288;
	const LOGS_SYMBOL                            = 289;
	const LONGBLOB_SYMBOL                        = 290;
	const LONGTEXT_SYMBOL                        = 291;
	const LONG_NUM_SYMBOL                        = 292;
	const LONG_SYMBOL                            = 293;
	const LOOP_SYMBOL                            = 294;
	const LOW_PRIORITY_SYMBOL                    = 295;
	const MASTER_AUTO_POSITION_SYMBOL            = 296;
	const MASTER_BIND_SYMBOL                     = 297;
	const MASTER_CONNECT_RETRY_SYMBOL            = 298;
	const MASTER_DELAY_SYMBOL                    = 299;
	const MASTER_HOST_SYMBOL                     = 300;
	const MASTER_LOG_FILE_SYMBOL                 = 301;
	const MASTER_LOG_POS_SYMBOL                  = 302;
	const MASTER_PASSWORD_SYMBOL                 = 303;
	const MASTER_PORT_SYMBOL                     = 304;
	const MASTER_RETRY_COUNT_SYMBOL              = 305;
	const MASTER_SERVER_ID_SYMBOL                = 306;
	const MASTER_SSL_CAPATH_SYMBOL               = 307;
	const MASTER_SSL_CA_SYMBOL                   = 308;
	const MASTER_SSL_CERT_SYMBOL                 = 309;
	const MASTER_SSL_CIPHER_SYMBOL               = 310;
	const MASTER_SSL_CRL_SYMBOL                  = 311;
	const MASTER_SSL_CRLPATH_SYMBOL              = 312;
	const MASTER_SSL_KEY_SYMBOL                  = 313;
	const MASTER_SSL_SYMBOL                      = 314;
	const MASTER_SSL_VERIFY_SERVER_CERT_SYMBOL   = 315;
	const MASTER_SYMBOL                          = 316;
	const MASTER_TLS_VERSION_SYMBOL              = 317;
	const MASTER_USER_SYMBOL                     = 318;
	const MASTER_HEARTBEAT_PERIOD_SYMBOL         = 319;
	const MATCH_SYMBOL                           = 320;
	const MAX_CONNECTIONS_PER_HOUR_SYMBOL        = 321;
	const MAX_QUERIES_PER_HOUR_SYMBOL            = 322;
	const MAX_ROWS_SYMBOL                        = 323;
	const MAX_SIZE_SYMBOL                        = 324;
	const MAX_STATEMENT_TIME_SYMBOL              = 325;
	const MAX_SYMBOL                             = 326;
	const MAX_UPDATES_PER_HOUR_SYMBOL            = 327;
	const MAX_USER_CONNECTIONS_SYMBOL            = 328;
	const MAXVALUE_SYMBOL                        = 329;
	const MEDIUMBLOB_SYMBOL                      = 330;
	const MEDIUMINT_SYMBOL                       = 331;
	const MEDIUMTEXT_SYMBOL                      = 332;
	const MEDIUM_SYMBOL                          = 333;
	const MEMORY_SYMBOL                          = 334;
	const MERGE_SYMBOL                           = 335;
	const MESSAGE_TEXT_SYMBOL                    = 336;
	const MICROSECOND_SYMBOL                     = 337;
	const MID_SYMBOL                             = 338;
	const MIDDLEINT_SYMBOL                       = 339;
	const MIGRATE_SYMBOL                         = 340;
	const MINUTE_MICROSECOND_SYMBOL              = 341;
	const MINUTE_SECOND_SYMBOL                   = 342;
	const MINUTE_SYMBOL                          = 343;
	const MIN_ROWS_SYMBOL                        = 344;
	const MIN_SYMBOL                             = 345;
	const MODE_SYMBOL                            = 346;
	const MODIFIES_SYMBOL                        = 347;
	const MODIFY_SYMBOL                          = 348;
	const MOD_SYMBOL                             = 349;
	const MONTH_SYMBOL                           = 350;
	const MULTILINESTRING_SYMBOL                 = 351;
	const MULTIPOINT_SYMBOL                      = 352;
	const MULTIPOLYGON_SYMBOL                    = 353;
	const MUTEX_SYMBOL                           = 354;
	const MYSQL_ERRNO_SYMBOL                     = 355;
	const NAMES_SYMBOL                           = 356;
	const NAME_SYMBOL                            = 357;
	const NATIONAL_SYMBOL                        = 358;
	const NATURAL_SYMBOL                         = 359;
	const NCHAR_STRING_SYMBOL                    = 360;
	const NCHAR_SYMBOL                           = 361;
	const NDB_SYMBOL                             = 362;
	const NDBCLUSTER_SYMBOL                      = 363;
	const NEG_SYMBOL                             = 364;
	const NEVER_SYMBOL                           = 365;
	const NEW_SYMBOL                             = 366;
	const NEXT_SYMBOL                            = 367;
	const NODEGROUP_SYMBOL                       = 368;
	const NONE_SYMBOL                            = 369;
	const NONBLOCKING_SYMBOL                     = 370;
	const NOT_SYMBOL                             = 371;
	const NOW_SYMBOL                             = 372;
	const NO_SYMBOL                              = 373;
	const NO_WAIT_SYMBOL                         = 374;
	const NO_WRITE_TO_BINLOG_SYMBOL              = 375;
	const NULL_SYMBOL                            = 376;
	const NUMBER_SYMBOL                          = 377;
	const NUMERIC_SYMBOL                         = 378;
	const NVARCHAR_SYMBOL                        = 379;
	const OFFLINE_SYMBOL                         = 380;
	const OFFSET_SYMBOL                          = 381;
	const OLD_PASSWORD_SYMBOL                    = 382;
	const ON_SYMBOL                              = 383;
	const ONE_SYMBOL                             = 384;
	const ONLINE_SYMBOL                          = 385;
	const ONLY_SYMBOL                            = 386;
	const OPEN_SYMBOL                            = 387;
	const OPTIMIZE_SYMBOL                        = 388;
	const OPTIMIZER_COSTS_SYMBOL                 = 389;
	const OPTIONS_SYMBOL                         = 390;
	const OPTION_SYMBOL                          = 391;
	const OPTIONALLY_SYMBOL                      = 392;
	const ORDER_SYMBOL                           = 393;
	const OR_SYMBOL                              = 394;
	const OUTER_SYMBOL                           = 395;
	const OUTFILE_SYMBOL                         = 396;
	const OUT_SYMBOL                             = 397;
	const OWNER_SYMBOL                           = 398;
	const PACK_KEYS_SYMBOL                       = 399;
	const PAGE_SYMBOL                            = 400;
	const PARSER_SYMBOL                          = 401;
	const PARTIAL_SYMBOL                         = 402;
	const PARTITIONING_SYMBOL                    = 403;
	const PARTITIONS_SYMBOL                      = 404;
	const PARTITION_SYMBOL                       = 405;
	const PASSWORD_SYMBOL                        = 406;
	const PHASE_SYMBOL                           = 407;
	const PLUGINS_SYMBOL                         = 408;
	const PLUGIN_DIR_SYMBOL                      = 409;
	const PLUGIN_SYMBOL                          = 410;
	const POINT_SYMBOL                           = 411;
	const POLYGON_SYMBOL                         = 412;
	const PORT_SYMBOL                            = 413;
	const POSITION_SYMBOL                        = 414;
	const PRECEDES_SYMBOL                        = 415;
	const PRECISION_SYMBOL                       = 416;
	const PREPARE_SYMBOL                         = 417;
	const PRESERVE_SYMBOL                        = 418;
	const PREV_SYMBOL                            = 419;
	const PRIMARY_SYMBOL                         = 420;
	const PRIVILEGES_SYMBOL                      = 421;
	const PROCEDURE_SYMBOL                       = 422;
	const PROCESS_SYMBOL                         = 423;
	const PROCESSLIST_SYMBOL                     = 424;
	const PROFILE_SYMBOL                         = 425;
	const PROFILES_SYMBOL                        = 426;
	const PROXY_SYMBOL                           = 427;
	const PURGE_SYMBOL                           = 428;
	const QUARTER_SYMBOL                         = 429;
	const QUERY_SYMBOL                           = 430;
	const QUICK_SYMBOL                           = 431;
	const RANGE_SYMBOL                           = 432;
	const READS_SYMBOL                           = 433;
	const READ_ONLY_SYMBOL                       = 434;
	const READ_SYMBOL                            = 435;
	const READ_WRITE_SYMBOL                      = 436;
	const REAL_SYMBOL                            = 437;
	const REBUILD_SYMBOL                         = 438;
	const RECOVER_SYMBOL                         = 439;
	const REDOFILE_SYMBOL                        = 440;
	const REDO_BUFFER_SIZE_SYMBOL                = 441;
	const REDUNDANT_SYMBOL                       = 442;
	const REFERENCES_SYMBOL                      = 443;
	const REGEXP_SYMBOL                          = 444;
	const RELAY_SYMBOL                           = 445;
	const RELAYLOG_SYMBOL                        = 446;
	const RELAY_LOG_FILE_SYMBOL                  = 447;
	const RELAY_LOG_POS_SYMBOL                   = 448;
	const RELAY_THREAD_SYMBOL                    = 449;
	const RELEASE_SYMBOL                         = 450;
	const RELOAD_SYMBOL                          = 451;
	const REMOVE_SYMBOL                          = 452;
	const RENAME_SYMBOL                          = 453;
	const REORGANIZE_SYMBOL                      = 454;
	const REPAIR_SYMBOL                          = 455;
	const REPEATABLE_SYMBOL                      = 456;
	const REPEAT_SYMBOL                          = 457;
	const REPLACE_SYMBOL                         = 458;
	const REPLICATION_SYMBOL                     = 459;
	const REPLICATE_DO_DB_SYMBOL                 = 460;
	const REPLICATE_IGNORE_DB_SYMBOL             = 461;
	const REPLICATE_DO_TABLE_SYMBOL              = 462;
	const REPLICATE_IGNORE_TABLE_SYMBOL          = 463;
	const REPLICATE_WILD_DO_TABLE_SYMBOL         = 464;
	const REPLICATE_WILD_IGNORE_TABLE_SYMBOL     = 465;
	const REPLICATE_REWRITE_DB_SYMBOL            = 466;
	const REQUIRE_SYMBOL                         = 467;
	const RESET_SYMBOL                           = 468;
	const RESIGNAL_SYMBOL                        = 469;
	const RESTORE_SYMBOL                         = 470;
	const RESTRICT_SYMBOL                        = 471;
	const RESUME_SYMBOL                          = 472;
	const RETURNED_SQLSTATE_SYMBOL               = 473;
	const RETURNS_SYMBOL                         = 474;
	const RETURN_SYMBOL                          = 475;
	const REVERSE_SYMBOL                         = 476;
	const REVOKE_SYMBOL                          = 477;
	const RIGHT_SYMBOL                           = 478;
	const RLIKE_SYMBOL                           = 479;
	const ROLLBACK_SYMBOL                        = 480;
	const ROLLUP_SYMBOL                          = 481;
	const ROTATE_SYMBOL                          = 482;
	const ROUTINE_SYMBOL                         = 483;
	const ROWS_SYMBOL                            = 484;
	const ROW_COUNT_SYMBOL                       = 485;
	const ROW_FORMAT_SYMBOL                      = 486;
	const ROW_SYMBOL                             = 487;
	const RTREE_SYMBOL                           = 488;
	const SAVEPOINT_SYMBOL                       = 489;
	const SCHEDULE_SYMBOL                        = 490;
	const SCHEMA_SYMBOL                          = 491;
	const SCHEMA_NAME_SYMBOL                     = 492;
	const SCHEMAS_SYMBOL                         = 493;
	const SECOND_MICROSECOND_SYMBOL              = 494;
	const SECOND_SYMBOL                          = 495;
	const SECURITY_SYMBOL                        = 496;
	const SELECT_SYMBOL                          = 497;
	const SENSITIVE_SYMBOL                       = 498;
	const SEPARATOR_SYMBOL                       = 499;
	const SERIALIZABLE_SYMBOL                    = 500;
	const SERIAL_SYMBOL                          = 501;
	const SESSION_SYMBOL                         = 502;
	const SERVER_SYMBOL                          = 503;
	const SERVER_OPTIONS_SYMBOL                  = 504;
	const SESSION_USER_SYMBOL                    = 505;
	const SET_SYMBOL                             = 506;
	const SET_VAR_SYMBOL                         = 507;
	const SHARE_SYMBOL                           = 508;
	const SHOW_SYMBOL                            = 509;
	const SHUTDOWN_SYMBOL                        = 510;
	const SIGNAL_SYMBOL                          = 511;
	const SIGNED_SYMBOL                          = 512;
	const SIMPLE_SYMBOL                          = 513;
	const SLAVE_SYMBOL                           = 514;
	const SLOW_SYMBOL                            = 515;
	const SMALLINT_SYMBOL                        = 516;
	const SNAPSHOT_SYMBOL                        = 517;
	const SOME_SYMBOL                            = 518;
	const SOCKET_SYMBOL                          = 519;
	const SONAME_SYMBOL                          = 520;
	const SOUNDS_SYMBOL                          = 521;
	const SOURCE_SYMBOL                          = 522;
	const SPATIAL_SYMBOL                         = 523;
	const SPECIFIC_SYMBOL                        = 524;
	const SQLEXCEPTION_SYMBOL                    = 525;
	const SQLSTATE_SYMBOL                        = 526;
	const SQLWARNING_SYMBOL                      = 527;
	const SQL_AFTER_GTIDS_SYMBOL                 = 528;
	const SQL_AFTER_MTS_GAPS_SYMBOL              = 529;
	const SQL_BEFORE_GTIDS_SYMBOL                = 530;
	const SQL_BIG_RESULT_SYMBOL                  = 531;
	const SQL_BUFFER_RESULT_SYMBOL               = 532;
	const SQL_CACHE_SYMBOL                       = 533;
	const SQL_CALC_FOUND_ROWS_SYMBOL             = 534;
	const SQL_NO_CACHE_SYMBOL                    = 535;
	const SQL_SMALL_RESULT_SYMBOL                = 536;
	const SQL_SYMBOL                             = 537;
	const SQL_THREAD_SYMBOL                      = 538;
	const SSL_SYMBOL                             = 539;
	const STACKED_SYMBOL                         = 540;
	const STARTING_SYMBOL                        = 541;
	const STARTS_SYMBOL                          = 542;
	const START_SYMBOL                           = 543;
	const STATS_AUTO_RECALC_SYMBOL               = 544;
	const STATS_PERSISTENT_SYMBOL                = 545;
	const STATS_SAMPLE_PAGES_SYMBOL              = 546;
	const STATUS_SYMBOL                          = 547;
	const STDDEV_SAMP_SYMBOL                     = 548;
	const STDDEV_SYMBOL                          = 549;
	const STDDEV_POP_SYMBOL                      = 550;
	const STD_SYMBOL                             = 551;
	const STOP_SYMBOL                            = 552;
	const STORAGE_SYMBOL                         = 553;
	const STORED_SYMBOL                          = 554;
	const STRAIGHT_JOIN_SYMBOL                   = 555;
	const STRING_SYMBOL                          = 556;
	const SUBCLASS_ORIGIN_SYMBOL                 = 557;
	const SUBDATE_SYMBOL                         = 558;
	const SUBJECT_SYMBOL                         = 559;
	const SUBPARTITIONS_SYMBOL                   = 560;
	const SUBPARTITION_SYMBOL                    = 561;
	const SUBSTR_SYMBOL                          = 562;
	const SUBSTRING_SYMBOL                       = 563;
	const SUM_SYMBOL                             = 564;
	const SUPER_SYMBOL                           = 565;
	const SUSPEND_SYMBOL                         = 566;
	const SWAPS_SYMBOL                           = 567;
	const SWITCHES_SYMBOL                        = 568;
	const SYSDATE_SYMBOL                         = 569;
	const SYSTEM_USER_SYMBOL                     = 570;
	const TABLES_SYMBOL                          = 571;
	const TABLESPACE_SYMBOL                      = 572;
	const TABLE_REF_PRIORITY_SYMBOL              = 573;
	const TABLE_SYMBOL                           = 574;
	const TABLE_CHECKSUM_SYMBOL                  = 575;
	const TABLE_NAME_SYMBOL                      = 576;
	const TEMPORARY_SYMBOL                       = 577;
	const TEMPTABLE_SYMBOL                       = 578;
	const TERMINATED_SYMBOL                      = 579;
	const TEXT_SYMBOL                            = 580;
	const THAN_SYMBOL                            = 581;
	const THEN_SYMBOL                            = 582;
	const TIMESTAMP_SYMBOL                       = 583;
	const TIMESTAMP_ADD_SYMBOL                   = 584;
	const TIMESTAMP_DIFF_SYMBOL                  = 585;
	const TIME_SYMBOL                            = 586;
	const TINYBLOB_SYMBOL                        = 587;
	const TINYINT_SYMBOL                         = 588;
	const TINYTEXT_SYMBOL                        = 589;
	const TO_SYMBOL                              = 590;
	const TRAILING_SYMBOL                        = 591;
	const TRANSACTION_SYMBOL                     = 592;
	const TRIGGERS_SYMBOL                        = 593;
	const TRIGGER_SYMBOL                         = 594;
	const TRIM_SYMBOL                            = 595;
	const TRUE_SYMBOL                            = 596;
	const TRUNCATE_SYMBOL                        = 597;
	const TYPES_SYMBOL                           = 598;
	const TYPE_SYMBOL                            = 599;
	const UDF_RETURNS_SYMBOL                     = 600;
	const UNCOMMITTED_SYMBOL                     = 601;
	const UNDEFINED_SYMBOL                       = 602;
	const UNDOFILE_SYMBOL                        = 603;
	const UNDO_BUFFER_SIZE_SYMBOL                = 604;
	const UNDO_SYMBOL                            = 605;
	const UNICODE_SYMBOL                         = 606;
	const UNINSTALL_SYMBOL                       = 607;
	const UNION_SYMBOL                           = 608;
	const UNIQUE_SYMBOL                          = 609;
	const UNKNOWN_SYMBOL                         = 610;
	const UNLOCK_SYMBOL                          = 611;
	const UNSIGNED_SYMBOL                        = 612;
	const UNTIL_SYMBOL                           = 613;
	const UPDATE_SYMBOL                          = 614;
	const UPGRADE_SYMBOL                         = 615;
	const USAGE_SYMBOL                           = 616;
	const USER_RESOURCES_SYMBOL                  = 617;
	const USER_SYMBOL                            = 618;
	const USE_FRM_SYMBOL                         = 619;
	const USE_SYMBOL                             = 620;
	const USING_SYMBOL                           = 621;
	const UTC_DATE_SYMBOL                        = 622;
	const UTC_TIMESTAMP_SYMBOL                   = 623;
	const UTC_TIME_SYMBOL                        = 624;
	const VALIDATION_SYMBOL                      = 625;
	const VALUES_SYMBOL                          = 626;
	const VALUE_SYMBOL                           = 627;
	const VARBINARY_SYMBOL                       = 628;
	const VARCHAR_SYMBOL                         = 629;
	const VARCHARACTER_SYMBOL                    = 630;
	const VARIABLES_SYMBOL                       = 631;
	const VARIANCE_SYMBOL                        = 632;
	const VARYING_SYMBOL                         = 633;
	const VAR_POP_SYMBOL                         = 634;
	const VAR_SAMP_SYMBOL                        = 635;
	const VIEW_SYMBOL                            = 636;
	const VIRTUAL_SYMBOL                         = 637;
	const WAIT_SYMBOL                            = 638;
	const WARNINGS_SYMBOL                        = 639;
	const WEEK_SYMBOL                            = 640;
	const WEIGHT_STRING_SYMBOL                   = 641;
	const WHEN_SYMBOL                            = 642;
	const WHERE_SYMBOL                           = 643;
	const WHILE_SYMBOL                           = 644;
	const WITH_SYMBOL                            = 645;
	const WITHOUT_SYMBOL                         = 646;
	const WORK_SYMBOL                            = 647;
	const WRAPPER_SYMBOL                         = 648;
	const WRITE_SYMBOL                           = 649;
	const X509_SYMBOL                            = 650;
	const XA_SYMBOL                              = 651;
	const XID_SYMBOL                             = 652;
	const XML_SYMBOL                             = 653;
	const XOR_SYMBOL                             = 654;
	const YEAR_MONTH_SYMBOL                      = 655;
	const YEAR_SYMBOL                            = 656;
	const ZEROFILL_SYMBOL                        = 657;
	const PERSIST_SYMBOL                         = 658;
	const ROLE_SYMBOL                            = 659;
	const ADMIN_SYMBOL                           = 660;
	const INVISIBLE_SYMBOL                       = 661;
	const VISIBLE_SYMBOL                         = 662;
	const EXCEPT_SYMBOL                          = 663;
	const COMPONENT_SYMBOL                       = 664;
	const RECURSIVE_SYMBOL                       = 665;
	const JSON_OBJECTAGG_SYMBOL                  = 666;
	const JSON_ARRAYAGG_SYMBOL                   = 667;
	const OF_SYMBOL                              = 668;
	const SKIP_SYMBOL                            = 669;
	const LOCKED_SYMBOL                          = 670;
	const NOWAIT_SYMBOL                          = 671;
	const GROUPING_SYMBOL                        = 672;
	const PERSIST_ONLY_SYMBOL                    = 673;
	const HISTOGRAM_SYMBOL                       = 674;
	const BUCKETS_SYMBOL                         = 675;
	const REMOTE_SYMBOL                          = 676;
	const CLONE_SYMBOL                           = 677;
	const CUME_DIST_SYMBOL                       = 678;
	const DENSE_RANK_SYMBOL                      = 679;
	const EXCLUDE_SYMBOL                         = 680;
	const FIRST_VALUE_SYMBOL                     = 681;
	const FOLLOWING_SYMBOL                       = 682;
	const GROUPS_SYMBOL                          = 683;
	const LAG_SYMBOL                             = 684;
	const LAST_VALUE_SYMBOL                      = 685;
	const LEAD_SYMBOL                            = 686;
	const NTH_VALUE_SYMBOL                       = 687;
	const NTILE_SYMBOL                           = 688;
	const NULLS_SYMBOL                           = 689;
	const OTHERS_SYMBOL                          = 690;
	const OVER_SYMBOL                            = 691;
	const PERCENT_RANK_SYMBOL                    = 692;
	const PRECEDING_SYMBOL                       = 693;
	const RANK_SYMBOL                            = 694;
	const RESPECT_SYMBOL                         = 695;
	const ROW_NUMBER_SYMBOL                      = 696;
	const TIES_SYMBOL                            = 697;
	const UNBOUNDED_SYMBOL                       = 698;
	const WINDOW_SYMBOL                          = 699;
	const EMPTY_SYMBOL                           = 700;
	const JSON_TABLE_SYMBOL                      = 701;
	const NESTED_SYMBOL                          = 702;
	const ORDINALITY_SYMBOL                      = 703;
	const PATH_SYMBOL                            = 704;
	const HISTORY_SYMBOL                         = 705;
	const REUSE_SYMBOL                           = 706;
	const SRID_SYMBOL                            = 707;
	const THREAD_PRIORITY_SYMBOL                 = 708;
	const RESOURCE_SYMBOL                        = 709;
	const SYSTEM_SYMBOL                          = 710;
	const VCPU_SYMBOL                            = 711;
	const MASTER_PUBLIC_KEY_PATH_SYMBOL          = 712;
	const GET_MASTER_PUBLIC_KEY_SYMBOL           = 713;
	const RESTART_SYMBOL                         = 714;
	const DEFINITION_SYMBOL                      = 715;
	const DESCRIPTION_SYMBOL                     = 716;
	const ORGANIZATION_SYMBOL                    = 717;
	const REFERENCE_SYMBOL                       = 718;
	const OPTIONAL_SYMBOL                        = 719;
	const SECONDARY_SYMBOL                       = 720;
	const SECONDARY_ENGINE_SYMBOL                = 721;
	const SECONDARY_LOAD_SYMBOL                  = 722;
	const SECONDARY_UNLOAD_SYMBOL                = 723;
	const ACTIVE_SYMBOL                          = 724;
	const INACTIVE_SYMBOL                        = 725;
	const LATERAL_SYMBOL                         = 726;
	const RETAIN_SYMBOL                          = 727;
	const OLD_SYMBOL                             = 728;
	const NETWORK_NAMESPACE_SYMBOL               = 729;
	const ENFORCED_SYMBOL                        = 730;
	const ARRAY_SYMBOL                           = 731;
	const OJ_SYMBOL                              = 732;
	const MEMBER_SYMBOL                          = 733;
	const RANDOM_SYMBOL                          = 734;
	const MASTER_COMPRESSION_ALGORITHM_SYMBOL    = 735;
	const MASTER_ZSTD_COMPRESSION_LEVEL_SYMBOL   = 736;
	const PRIVILEGE_CHECKS_USER_SYMBOL           = 737;
	const MASTER_TLS_CIPHERSUITES_SYMBOL         = 738;
	const REQUIRE_ROW_FORMAT_SYMBOL              = 739;
	const PASSWORD_LOCK_TIME_SYMBOL              = 740;
	const FAILED_LOGIN_ATTEMPTS_SYMBOL           = 741;
	const REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYMBOL = 742;
	const STREAM_SYMBOL                          = 743;
	const OFF_SYMBOL                             = 744;

	/**
	 * Additional tokens, mostly mirroring the MySQL Workbench lexer grammar.
	 *
	 * These tokens are defined in the MySQL Workbench "MySQLLexer.g4" grammar.
	 *
	 * See:
	 *   https://github.com/mysql/mysql-workbench/blob/8.0.38/library/parsers/grammars/MySQLLexer.g4
	 */

	// Punctuators
	const AT_AT_SIGN_SYMBOL  = 745;
	const AT_SIGN_SYMBOL     = 746;
	const CLOSE_CURLY_SYMBOL = 747;
	const CLOSE_PAR_SYMBOL   = 748;
	const COLON_SYMBOL       = 749;
	const COMMA_SYMBOL       = 750;
	const DOT_SYMBOL         = 751;
	const OPEN_CURLY_SYMBOL  = 752;
	const OPEN_PAR_SYMBOL    = 753;
	const PARAM_MARKER       = 754;
	const SEMICOLON_SYMBOL   = 755;

	// Operators
	const ASSIGN_OPERATOR                = 756;
	const BITWISE_AND_OPERATOR           = 757;
	const BITWISE_NOT_OPERATOR           = 758;
	const BITWISE_OR_OPERATOR            = 759;
	const BITWISE_XOR_OPERATOR           = 760;
	const CONCAT_PIPES_SYMBOL            = 761;
	const DIV_OPERATOR                   = 762;
	const EQUAL_OPERATOR                 = 763;
	const GREATER_OR_EQUAL_OPERATOR      = 764;
	const GREATER_THAN_OPERATOR          = 765;
	const JSON_SEPARATOR_SYMBOL          = 766;
	const JSON_UNQUOTED_SEPARATOR_SYMBOL = 767;
	const LESS_OR_EQUAL_OPERATOR         = 768;
	const LESS_THAN_OPERATOR             = 769;
	const LOGICAL_AND_OPERATOR           = 770;
	const LOGICAL_NOT_OPERATOR           = 771;
	const LOGICAL_OR_OPERATOR            = 772;
	const MINUS_OPERATOR                 = 773;
	const MOD_OPERATOR                   = 774;
	const MULT_OPERATOR                  = 775;
	const NOT_EQUAL_OPERATOR             = 776;
	const NULL_SAFE_EQUAL_OPERATOR       = 777;
	const PLUS_OPERATOR                  = 778;
	const SHIFT_LEFT_OPERATOR            = 779;
	const SHIFT_RIGHT_OPERATOR           = 780;

	// Literals
	const BACK_TICK_QUOTED_ID = 781;
	const BIN_NUMBER          = 782;
	const DECIMAL_NUMBER      = 783;
	const DOUBLE_QUOTED_TEXT  = 784;
	const FLOAT_NUMBER        = 785;
	const HEX_NUMBER          = 786;
	const INT_NUMBER          = 787;
	const LONG_NUMBER         = 788;
	const NCHAR_TEXT          = 789;
	const SINGLE_QUOTED_TEXT  = 790;
	const ULONGLONG_NUMBER    = 791;

	// Identifier-like tokens
	const AT_TEXT_SUFFIX     = 792;
	const IDENTIFIER         = 793;
	const UNDERSCORE_CHARSET = 794;

	// Other tokens
	const INT1_SYMBOL                = 795;
	const INT2_SYMBOL                = 796;
	const INT3_SYMBOL                = 797;
	const INT4_SYMBOL                = 798;
	const INT8_SYMBOL                = 799;
	const NOT2_SYMBOL                = 800;
	const NULL2_SYMBOL               = 801;
	const SQL_TSI_DAY_SYMBOL         = 802;
	const SQL_TSI_HOUR_SYMBOL        = 803;
	const SQL_TSI_MICROSECOND_SYMBOL = 804;
	const SQL_TSI_MINUTE_SYMBOL      = 805;
	const SQL_TSI_MONTH_SYMBOL       = 806;
	const SQL_TSI_QUARTER_SYMBOL     = 807;
	const SQL_TSI_SECOND_SYMBOL      = 808;
	const SQL_TSI_WEEK_SYMBOL        = 809;
	const SQL_TSI_YEAR_SYMBOL        = 810;

	/**
	 * Other tokens, missing in the MySQL Workbench "MySQLLexer.g4" grammar.
	 *
	 * These tokens are missing in the "MySQLLexer.g4" grammar, because the MySQL
	 * Workbench lexer and parser don't cover 100% of the MySQL syntax.
	 */
	const INTERSECT_SYMBOL                              = 811;
	const ATTRIBUTE_SYMBOL                              = 812;
	const SOURCE_AUTO_POSITION_SYMBOL                   = 813;
	const SOURCE_BIND_SYMBOL                            = 814;
	const SOURCE_COMPRESSION_ALGORITHM_SYMBOL           = 815;
	const SOURCE_CONNECT_RETRY_SYMBOL                   = 816;
	const SOURCE_CONNECTION_AUTO_FAILOVER_SYMBOL        = 817;
	const SOURCE_DELAY_SYMBOL                           = 818;
	const SOURCE_HEARTBEAT_PERIOD_SYMBOL                = 819;
	const SOURCE_HOST_SYMBOL                            = 820;
	const SOURCE_LOG_FILE_SYMBOL                        = 821;
	const SOURCE_LOG_POS_SYMBOL                         = 822;
	const SOURCE_PASSWORD_SYMBOL                        = 823;
	const SOURCE_PORT_SYMBOL                            = 824;
	const SOURCE_PUBLIC_KEY_PATH_SYMBOL                 = 825;
	const SOURCE_RETRY_COUNT_SYMBOL                     = 826;
	const SOURCE_SSL_SYMBOL                             = 827;
	const SOURCE_SSL_CA_SYMBOL                          = 828;
	const SOURCE_SSL_CAPATH_SYMBOL                      = 829;
	const SOURCE_SSL_CERT_SYMBOL                        = 830;
	const SOURCE_SSL_CIPHER_SYMBOL                      = 831;
	const SOURCE_SSL_CRL_SYMBOL                         = 832;
	const SOURCE_SSL_CRLPATH_SYMBOL                     = 833;
	const SOURCE_SSL_KEY_SYMBOL                         = 834;
	const SOURCE_SSL_VERIFY_SERVER_CERT_SYMBOL          = 835;
	const SOURCE_TLS_CIPHERSUITES_SYMBOL                = 836;
	const SOURCE_TLS_VERSION_SYMBOL                     = 837;
	const SOURCE_USER_SYMBOL                            = 838;
	const SOURCE_ZSTD_COMPRESSION_LEVEL_SYMBOL          = 839;
	const GET_SOURCE_PUBLIC_KEY_SYMBOL                  = 840;
	const GTID_ONLY_SYMBOL                              = 841;
	const ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYMBOL = 842;
	const ZONE_SYMBOL                                   = 843;
	const INNODB_SYMBOL                                 = 844; // From 5.7.11 defined as is_identifier(..., "INNODB") in "sql_yacc.yy".
	const TLS_SYMBOL                                    = 845; // Added in 8.0.21. From 8.0.16 defined as is_identifier(..., "TLS") in "sql_yacc.yy".
	const REDO_LOG_SYMBOL                               = 846; // From 8.0.21 defined as is_identifier(..., "REDO_LOG") in "sql_yacc.yy".
	const KEYRING_SYMBOL                                = 847;
	const ENGINE_ATTRIBUTE_SYMBOL                       = 848;
	const SECONDARY_ENGINE_ATTRIBUTE_SYMBOL             = 849;
	const JSON_VALUE_SYMBOL                             = 850;
	const RETURNING_SYMBOL                              = 851;
	const GEOMCOLLECTION_SYMBOL                         = 852;

	// Comments
	const COMMENT             = 900;
	const MYSQL_COMMENT_START = 901;
	const MYSQL_COMMENT_END   = 902;

	// Special tokens
	const WHITESPACE = 0;
	const EOF        = -1;

	/**
	 * A map of SQL keyword string values to their corresponding token types.
	 *
	 * This is used for a fast lookup of MySQL keywords during tokenization.
	 */
	const TOKENS = array(
		// Tokens from MySQL 5.7:
		'ACCESSIBLE'                             => self::ACCESSIBLE_SYMBOL,
		'ACCOUNT'                                => self::ACCOUNT_SYMBOL,
		'ACTION'                                 => self::ACTION_SYMBOL,
		'ADD'                                    => self::ADD_SYMBOL,
		'ADDDATE'                                => self::ADDDATE_SYMBOL,
		'AFTER'                                  => self::AFTER_SYMBOL,
		'AGAINST'                                => self::AGAINST_SYMBOL,
		'AGGREGATE'                              => self::AGGREGATE_SYMBOL,
		'ALGORITHM'                              => self::ALGORITHM_SYMBOL,
		'ALL'                                    => self::ALL_SYMBOL,
		'ALTER'                                  => self::ALTER_SYMBOL,
		'ALWAYS'                                 => self::ALWAYS_SYMBOL,
		'ANALYSE'                                => self::ANALYSE_SYMBOL,
		'ANALYZE'                                => self::ANALYZE_SYMBOL,
		'AND'                                    => self::AND_SYMBOL,
		'ANY'                                    => self::ANY_SYMBOL,
		'AS'                                     => self::AS_SYMBOL,
		'ASC'                                    => self::ASC_SYMBOL,
		'ASCII'                                  => self::ASCII_SYMBOL,
		'ASENSITIVE'                             => self::ASENSITIVE_SYMBOL,
		'AT'                                     => self::AT_SYMBOL,
		'ATTRIBUTE'                              => self::ATTRIBUTE_SYMBOL,
		'AUTHORS'                                => self::AUTHORS_SYMBOL,
		'AUTO_INCREMENT'                         => self::AUTO_INCREMENT_SYMBOL,
		'AUTOEXTEND_SIZE'                        => self::AUTOEXTEND_SIZE_SYMBOL,
		'AVG'                                    => self::AVG_SYMBOL,
		'AVG_ROW_LENGTH'                         => self::AVG_ROW_LENGTH_SYMBOL,
		'BACKUP'                                 => self::BACKUP_SYMBOL,
		'BEFORE'                                 => self::BEFORE_SYMBOL,
		'BEGIN'                                  => self::BEGIN_SYMBOL,
		'BETWEEN'                                => self::BETWEEN_SYMBOL,
		'BIGINT'                                 => self::BIGINT_SYMBOL,
		'BIN_NUM'                                => self::BIN_NUM_SYMBOL,
		'BINARY'                                 => self::BINARY_SYMBOL,
		'BINLOG'                                 => self::BINLOG_SYMBOL,
		'BIT'                                    => self::BIT_SYMBOL,
		'BIT_AND'                                => self::BIT_AND_SYMBOL,
		'BIT_OR'                                 => self::BIT_OR_SYMBOL,
		'BIT_XOR'                                => self::BIT_XOR_SYMBOL,
		'BLOB'                                   => self::BLOB_SYMBOL,
		'BLOCK'                                  => self::BLOCK_SYMBOL,
		'BOOL'                                   => self::BOOL_SYMBOL,
		'BOOLEAN'                                => self::BOOLEAN_SYMBOL,
		'BOTH'                                   => self::BOTH_SYMBOL,
		'BTREE'                                  => self::BTREE_SYMBOL,
		'BY'                                     => self::BY_SYMBOL,
		'BYTE'                                   => self::BYTE_SYMBOL,
		'CACHE'                                  => self::CACHE_SYMBOL,
		'CALL'                                   => self::CALL_SYMBOL,
		'CASCADE'                                => self::CASCADE_SYMBOL,
		'CASCADED'                               => self::CASCADED_SYMBOL,
		'CASE'                                   => self::CASE_SYMBOL,
		'CAST'                                   => self::CAST_SYMBOL,
		'CATALOG_NAME'                           => self::CATALOG_NAME_SYMBOL,
		'CHAIN'                                  => self::CHAIN_SYMBOL,
		'CHANGE'                                 => self::CHANGE_SYMBOL,
		'CHANGED'                                => self::CHANGED_SYMBOL,
		'CHANNEL'                                => self::CHANNEL_SYMBOL,
		'CHAR'                                   => self::CHAR_SYMBOL,
		'CHARACTER'                              => self::CHARACTER_SYMBOL,
		'CHARSET'                                => self::CHARSET_SYMBOL,
		'CHECK'                                  => self::CHECK_SYMBOL,
		'CHECKSUM'                               => self::CHECKSUM_SYMBOL,
		'CIPHER'                                 => self::CIPHER_SYMBOL,
		'CLASS_ORIGIN'                           => self::CLASS_ORIGIN_SYMBOL,
		'CLIENT'                                 => self::CLIENT_SYMBOL,
		'CLOSE'                                  => self::CLOSE_SYMBOL,
		'COALESCE'                               => self::COALESCE_SYMBOL,
		'CODE'                                   => self::CODE_SYMBOL,
		'COLLATE'                                => self::COLLATE_SYMBOL,
		'COLLATION'                              => self::COLLATION_SYMBOL,
		'COLUMN'                                 => self::COLUMN_SYMBOL,
		'COLUMN_FORMAT'                          => self::COLUMN_FORMAT_SYMBOL,
		'COLUMN_NAME'                            => self::COLUMN_NAME_SYMBOL,
		'COLUMNS'                                => self::COLUMNS_SYMBOL,
		'COMMENT'                                => self::COMMENT_SYMBOL,
		'COMMIT'                                 => self::COMMIT_SYMBOL,
		'COMMITTED'                              => self::COMMITTED_SYMBOL,
		'COMPACT'                                => self::COMPACT_SYMBOL,
		'COMPLETION'                             => self::COMPLETION_SYMBOL,
		'COMPRESSED'                             => self::COMPRESSED_SYMBOL,
		'COMPRESSION'                            => self::COMPRESSION_SYMBOL,
		'CONCURRENT'                             => self::CONCURRENT_SYMBOL,
		'CONDITION'                              => self::CONDITION_SYMBOL,
		'CONNECTION'                             => self::CONNECTION_SYMBOL,
		'CONSISTENT'                             => self::CONSISTENT_SYMBOL,
		'CONSTRAINT'                             => self::CONSTRAINT_SYMBOL,
		'CONSTRAINT_CATALOG'                     => self::CONSTRAINT_CATALOG_SYMBOL,
		'CONSTRAINT_NAME'                        => self::CONSTRAINT_NAME_SYMBOL,
		'CONSTRAINT_SCHEMA'                      => self::CONSTRAINT_SCHEMA_SYMBOL,
		'CONTAINS'                               => self::CONTAINS_SYMBOL,
		'CONTEXT'                                => self::CONTEXT_SYMBOL,
		'CONTINUE'                               => self::CONTINUE_SYMBOL,
		'CONTRIBUTORS'                           => self::CONTRIBUTORS_SYMBOL,
		'CONVERT'                                => self::CONVERT_SYMBOL,
		'COUNT'                                  => self::COUNT_SYMBOL,
		'CPU'                                    => self::CPU_SYMBOL,
		'CREATE'                                 => self::CREATE_SYMBOL,
		'CROSS'                                  => self::CROSS_SYMBOL,
		'CUBE'                                   => self::CUBE_SYMBOL,
		'CURDATE'                                => self::CURDATE_SYMBOL,
		'CURRENT'                                => self::CURRENT_SYMBOL,
		'CURRENT_DATE'                           => self::CURRENT_DATE_SYMBOL,
		'CURRENT_TIME'                           => self::CURRENT_TIME_SYMBOL,
		'CURRENT_TIMESTAMP'                      => self::CURRENT_TIMESTAMP_SYMBOL,
		'CURRENT_USER'                           => self::CURRENT_USER_SYMBOL,
		'CURSOR'                                 => self::CURSOR_SYMBOL,
		'CURSOR_NAME'                            => self::CURSOR_NAME_SYMBOL,
		'CURTIME'                                => self::CURTIME_SYMBOL,
		'DATA'                                   => self::DATA_SYMBOL,
		'DATABASE'                               => self::DATABASE_SYMBOL,
		'DATABASES'                              => self::DATABASES_SYMBOL,
		'DATAFILE'                               => self::DATAFILE_SYMBOL,
		'DATE'                                   => self::DATE_SYMBOL,
		'DATE_ADD'                               => self::DATE_ADD_SYMBOL,
		'DATE_SUB'                               => self::DATE_SUB_SYMBOL,
		'DATETIME'                               => self::DATETIME_SYMBOL,
		'DAY'                                    => self::DAY_SYMBOL,
		'DAY_HOUR'                               => self::DAY_HOUR_SYMBOL,
		'DAY_MICROSECOND'                        => self::DAY_MICROSECOND_SYMBOL,
		'DAY_MINUTE'                             => self::DAY_MINUTE_SYMBOL,
		'DAY_SECOND'                             => self::DAY_SECOND_SYMBOL,
		'DAYOFMONTH'                             => self::DAYOFMONTH_SYMBOL,
		'DEALLOCATE'                             => self::DEALLOCATE_SYMBOL,
		'DEC'                                    => self::DEC_SYMBOL,
		'DECIMAL'                                => self::DECIMAL_SYMBOL,
		'DECIMAL_NUM'                            => self::DECIMAL_NUM_SYMBOL,
		'DECLARE'                                => self::DECLARE_SYMBOL,
		'DEFAULT'                                => self::DEFAULT_SYMBOL,
		'DEFAULT_AUTH'                           => self::DEFAULT_AUTH_SYMBOL,
		'DEFINER'                                => self::DEFINER_SYMBOL,
		'DELAY_KEY_WRITE'                        => self::DELAY_KEY_WRITE_SYMBOL,
		'DELAYED'                                => self::DELAYED_SYMBOL,
		'DELETE'                                 => self::DELETE_SYMBOL,
		'DES_KEY_FILE'                           => self::DES_KEY_FILE_SYMBOL,
		'DESC'                                   => self::DESC_SYMBOL,
		'DESCRIBE'                               => self::DESCRIBE_SYMBOL,
		'DETERMINISTIC'                          => self::DETERMINISTIC_SYMBOL,
		'DIAGNOSTICS'                            => self::DIAGNOSTICS_SYMBOL,
		'DIRECTORY'                              => self::DIRECTORY_SYMBOL,
		'DISABLE'                                => self::DISABLE_SYMBOL,
		'DISCARD'                                => self::DISCARD_SYMBOL,
		'DISK'                                   => self::DISK_SYMBOL,
		'DISTINCT'                               => self::DISTINCT_SYMBOL,
		'DISTINCTROW'                            => self::DISTINCTROW_SYMBOL,
		'DIV'                                    => self::DIV_SYMBOL,
		'DO'                                     => self::DO_SYMBOL,
		'DOUBLE'                                 => self::DOUBLE_SYMBOL,
		'DROP'                                   => self::DROP_SYMBOL,
		'DUAL'                                   => self::DUAL_SYMBOL,
		'DUMPFILE'                               => self::DUMPFILE_SYMBOL,
		'DUPLICATE'                              => self::DUPLICATE_SYMBOL,
		'DYNAMIC'                                => self::DYNAMIC_SYMBOL,
		'EACH'                                   => self::EACH_SYMBOL,
		'ELSE'                                   => self::ELSE_SYMBOL,
		'ELSEIF'                                 => self::ELSEIF_SYMBOL,
		'ENABLE'                                 => self::ENABLE_SYMBOL,
		'ENCLOSED'                               => self::ENCLOSED_SYMBOL,
		'ENCRYPTION'                             => self::ENCRYPTION_SYMBOL,
		'END'                                    => self::END_SYMBOL,
		'END_OF_INPUT'                           => self::EOF,
		'ENDS'                                   => self::ENDS_SYMBOL,
		'ENGINE'                                 => self::ENGINE_SYMBOL,
		'ENGINES'                                => self::ENGINES_SYMBOL,
		'ENUM'                                   => self::ENUM_SYMBOL,
		'ERROR'                                  => self::ERROR_SYMBOL,
		'ERRORS'                                 => self::ERRORS_SYMBOL,
		'ESCAPE'                                 => self::ESCAPE_SYMBOL,
		'ESCAPED'                                => self::ESCAPED_SYMBOL,
		'EVENT'                                  => self::EVENT_SYMBOL,
		'EVENTS'                                 => self::EVENTS_SYMBOL,
		'EVERY'                                  => self::EVERY_SYMBOL,
		'EXCHANGE'                               => self::EXCHANGE_SYMBOL,
		'EXECUTE'                                => self::EXECUTE_SYMBOL,
		'EXISTS'                                 => self::EXISTS_SYMBOL,
		'EXIT'                                   => self::EXIT_SYMBOL,
		'EXPANSION'                              => self::EXPANSION_SYMBOL,
		'EXPIRE'                                 => self::EXPIRE_SYMBOL,
		'EXPLAIN'                                => self::EXPLAIN_SYMBOL,
		'EXPORT'                                 => self::EXPORT_SYMBOL,
		'EXTENDED'                               => self::EXTENDED_SYMBOL,
		'EXTENT_SIZE'                            => self::EXTENT_SIZE_SYMBOL,
		'EXTRACT'                                => self::EXTRACT_SYMBOL,
		'FALSE'                                  => self::FALSE_SYMBOL,
		'FAST'                                   => self::FAST_SYMBOL,
		'FAULTS'                                 => self::FAULTS_SYMBOL,
		'FETCH'                                  => self::FETCH_SYMBOL,
		'FIELDS'                                 => self::FIELDS_SYMBOL,
		'FILE'                                   => self::FILE_SYMBOL,
		'FILE_BLOCK_SIZE'                        => self::FILE_BLOCK_SIZE_SYMBOL,
		'FILTER'                                 => self::FILTER_SYMBOL,
		'FIRST'                                  => self::FIRST_SYMBOL,
		'FIXED'                                  => self::FIXED_SYMBOL,
		'FLOAT'                                  => self::FLOAT_SYMBOL,
		'FLOAT4'                                 => self::FLOAT4_SYMBOL,
		'FLOAT8'                                 => self::FLOAT8_SYMBOL,
		'FLUSH'                                  => self::FLUSH_SYMBOL,
		'FOLLOWS'                                => self::FOLLOWS_SYMBOL,
		'FOR'                                    => self::FOR_SYMBOL,
		'FORCE'                                  => self::FORCE_SYMBOL,
		'FOREIGN'                                => self::FOREIGN_SYMBOL,
		'FORMAT'                                 => self::FORMAT_SYMBOL,
		'FOUND'                                  => self::FOUND_SYMBOL,
		'FROM'                                   => self::FROM_SYMBOL,
		'FULL'                                   => self::FULL_SYMBOL,
		'FULLTEXT'                               => self::FULLTEXT_SYMBOL,
		'FUNCTION'                               => self::FUNCTION_SYMBOL,
		'GENERAL'                                => self::GENERAL_SYMBOL,
		'GENERATED'                              => self::GENERATED_SYMBOL,
		'GEOMCOLLECTION'                         => self::GEOMCOLLECTION_SYMBOL,
		'GEOMETRY'                               => self::GEOMETRY_SYMBOL,
		'GEOMETRYCOLLECTION'                     => self::GEOMETRYCOLLECTION_SYMBOL,
		'GET'                                    => self::GET_SYMBOL,
		'GET_FORMAT'                             => self::GET_FORMAT_SYMBOL,
		'GLOBAL'                                 => self::GLOBAL_SYMBOL,
		'GRANT'                                  => self::GRANT_SYMBOL,
		'GRANTS'                                 => self::GRANTS_SYMBOL,
		'GROUP'                                  => self::GROUP_SYMBOL,
		'GROUP_CONCAT'                           => self::GROUP_CONCAT_SYMBOL,
		'GROUP_REPLICATION'                      => self::GROUP_REPLICATION_SYMBOL,
		'HANDLER'                                => self::HANDLER_SYMBOL,
		'HASH'                                   => self::HASH_SYMBOL,
		'HAVING'                                 => self::HAVING_SYMBOL,
		'HELP'                                   => self::HELP_SYMBOL,
		'HIGH_PRIORITY'                          => self::HIGH_PRIORITY_SYMBOL,
		'HOST'                                   => self::HOST_SYMBOL,
		'HOSTS'                                  => self::HOSTS_SYMBOL,
		'HOUR'                                   => self::HOUR_SYMBOL,
		'HOUR_MICROSECOND'                       => self::HOUR_MICROSECOND_SYMBOL,
		'HOUR_MINUTE'                            => self::HOUR_MINUTE_SYMBOL,
		'HOUR_SECOND'                            => self::HOUR_SECOND_SYMBOL,
		'IDENTIFIED'                             => self::IDENTIFIED_SYMBOL,
		'IF'                                     => self::IF_SYMBOL,
		'IGNORE'                                 => self::IGNORE_SYMBOL,
		'IGNORE_SERVER_IDS'                      => self::IGNORE_SERVER_IDS_SYMBOL,
		'IMPORT'                                 => self::IMPORT_SYMBOL,
		'IN'                                     => self::IN_SYMBOL,
		'INDEX'                                  => self::INDEX_SYMBOL,
		'INDEXES'                                => self::INDEXES_SYMBOL,
		'INFILE'                                 => self::INFILE_SYMBOL,
		'INITIAL_SIZE'                           => self::INITIAL_SIZE_SYMBOL,
		'INNER'                                  => self::INNER_SYMBOL,
		'INNODB'                                 => self::INNODB_SYMBOL,
		'INOUT'                                  => self::INOUT_SYMBOL,
		'INSENSITIVE'                            => self::INSENSITIVE_SYMBOL,
		'INSERT'                                 => self::INSERT_SYMBOL,
		'INSERT_METHOD'                          => self::INSERT_METHOD_SYMBOL,
		'INSTALL'                                => self::INSTALL_SYMBOL,
		'INSTANCE'                               => self::INSTANCE_SYMBOL,
		'INT'                                    => self::INT_SYMBOL,
		'INT1'                                   => self::INT1_SYMBOL,
		'INT2'                                   => self::INT2_SYMBOL,
		'INT3'                                   => self::INT3_SYMBOL,
		'INT4'                                   => self::INT4_SYMBOL,
		'INT8'                                   => self::INT8_SYMBOL,
		'INTEGER'                                => self::INTEGER_SYMBOL,
		'INTERVAL'                               => self::INTERVAL_SYMBOL,
		'INTO'                                   => self::INTO_SYMBOL,
		'INVOKER'                                => self::INVOKER_SYMBOL,
		'IO'                                     => self::IO_SYMBOL,
		'IO_AFTER_GTIDS'                         => self::IO_AFTER_GTIDS_SYMBOL,
		'IO_BEFORE_GTIDS'                        => self::IO_BEFORE_GTIDS_SYMBOL,
		'IO_THREAD'                              => self::IO_THREAD_SYMBOL,
		'IPC'                                    => self::IPC_SYMBOL,
		'IS'                                     => self::IS_SYMBOL,
		'ISOLATION'                              => self::ISOLATION_SYMBOL,
		'ISSUER'                                 => self::ISSUER_SYMBOL,
		'ITERATE'                                => self::ITERATE_SYMBOL,
		'JOIN'                                   => self::JOIN_SYMBOL,
		'JSON'                                   => self::JSON_SYMBOL,
		'KEY'                                    => self::KEY_SYMBOL,
		'KEY_BLOCK_SIZE'                         => self::KEY_BLOCK_SIZE_SYMBOL,
		'KEYS'                                   => self::KEYS_SYMBOL,
		'KILL'                                   => self::KILL_SYMBOL,
		'LANGUAGE'                               => self::LANGUAGE_SYMBOL,
		'LAST'                                   => self::LAST_SYMBOL,
		'LEADING'                                => self::LEADING_SYMBOL,
		'LEAVE'                                  => self::LEAVE_SYMBOL,
		'LEAVES'                                 => self::LEAVES_SYMBOL,
		'LEFT'                                   => self::LEFT_SYMBOL,
		'LESS'                                   => self::LESS_SYMBOL,
		'LEVEL'                                  => self::LEVEL_SYMBOL,
		'LIKE'                                   => self::LIKE_SYMBOL,
		'LIMIT'                                  => self::LIMIT_SYMBOL,
		'LINEAR'                                 => self::LINEAR_SYMBOL,
		'LINES'                                  => self::LINES_SYMBOL,
		'LINESTRING'                             => self::LINESTRING_SYMBOL,
		'LIST'                                   => self::LIST_SYMBOL,
		'LOAD'                                   => self::LOAD_SYMBOL,
		'LOCAL'                                  => self::LOCAL_SYMBOL,
		'LOCALTIME'                              => self::LOCALTIME_SYMBOL,
		'LOCALTIMESTAMP'                         => self::LOCALTIMESTAMP_SYMBOL,
		'LOCATOR'                                => self::LOCATOR_SYMBOL,
		'LOCK'                                   => self::LOCK_SYMBOL,
		'LOCKS'                                  => self::LOCKS_SYMBOL,
		'LOGFILE'                                => self::LOGFILE_SYMBOL,
		'LOGS'                                   => self::LOGS_SYMBOL,
		'LONG'                                   => self::LONG_SYMBOL,
		'LONG_NUM'                               => self::LONG_NUM_SYMBOL,
		'LONGBLOB'                               => self::LONGBLOB_SYMBOL,
		'LONGTEXT'                               => self::LONGTEXT_SYMBOL,
		'LOOP'                                   => self::LOOP_SYMBOL,
		'LOW_PRIORITY'                           => self::LOW_PRIORITY_SYMBOL,
		'MASTER'                                 => self::MASTER_SYMBOL,
		'MASTER_AUTO_POSITION'                   => self::MASTER_AUTO_POSITION_SYMBOL,
		'MASTER_BIND'                            => self::MASTER_BIND_SYMBOL,
		'MASTER_CONNECT_RETRY'                   => self::MASTER_CONNECT_RETRY_SYMBOL,
		'MASTER_DELAY'                           => self::MASTER_DELAY_SYMBOL,
		'MASTER_HEARTBEAT_PERIOD'                => self::MASTER_HEARTBEAT_PERIOD_SYMBOL,
		'MASTER_HOST'                            => self::MASTER_HOST_SYMBOL,
		'MASTER_LOG_FILE'                        => self::MASTER_LOG_FILE_SYMBOL,
		'MASTER_LOG_POS'                         => self::MASTER_LOG_POS_SYMBOL,
		'MASTER_PASSWORD'                        => self::MASTER_PASSWORD_SYMBOL,
		'MASTER_PORT'                            => self::MASTER_PORT_SYMBOL,
		'MASTER_RETRY_COUNT'                     => self::MASTER_RETRY_COUNT_SYMBOL,
		'MASTER_SERVER_ID'                       => self::MASTER_SERVER_ID_SYMBOL,
		'MASTER_SSL'                             => self::MASTER_SSL_SYMBOL,
		'MASTER_SSL_CA'                          => self::MASTER_SSL_CA_SYMBOL,
		'MASTER_SSL_CAPATH'                      => self::MASTER_SSL_CAPATH_SYMBOL,
		'MASTER_SSL_CERT'                        => self::MASTER_SSL_CERT_SYMBOL,
		'MASTER_SSL_CIPHER'                      => self::MASTER_SSL_CIPHER_SYMBOL,
		'MASTER_SSL_CRL'                         => self::MASTER_SSL_CRL_SYMBOL,
		'MASTER_SSL_CRLPATH'                     => self::MASTER_SSL_CRLPATH_SYMBOL,
		'MASTER_SSL_KEY'                         => self::MASTER_SSL_KEY_SYMBOL,
		'MASTER_SSL_VERIFY_SERVER_CERT'          => self::MASTER_SSL_VERIFY_SERVER_CERT_SYMBOL,
		'MASTER_TLS_VERSION'                     => self::MASTER_TLS_VERSION_SYMBOL,
		'MASTER_USER'                            => self::MASTER_USER_SYMBOL,
		'MATCH'                                  => self::MATCH_SYMBOL,
		'MAX'                                    => self::MAX_SYMBOL,
		'MAX_CONNECTIONS_PER_HOUR'               => self::MAX_CONNECTIONS_PER_HOUR_SYMBOL,
		'MAX_QUERIES_PER_HOUR'                   => self::MAX_QUERIES_PER_HOUR_SYMBOL,
		'MAX_ROWS'                               => self::MAX_ROWS_SYMBOL,
		'MAX_SIZE'                               => self::MAX_SIZE_SYMBOL,
		'MAX_STATEMENT_TIME'                     => self::MAX_STATEMENT_TIME_SYMBOL,
		'MAX_UPDATES_PER_HOUR'                   => self::MAX_UPDATES_PER_HOUR_SYMBOL,
		'MAX_USER_CONNECTIONS'                   => self::MAX_USER_CONNECTIONS_SYMBOL,
		'MAXVALUE'                               => self::MAXVALUE_SYMBOL,
		'MEDIUM'                                 => self::MEDIUM_SYMBOL,
		'MEDIUMBLOB'                             => self::MEDIUMBLOB_SYMBOL,
		'MEDIUMINT'                              => self::MEDIUMINT_SYMBOL,
		'MEDIUMTEXT'                             => self::MEDIUMTEXT_SYMBOL,
		'MEMORY'                                 => self::MEMORY_SYMBOL,
		'MERGE'                                  => self::MERGE_SYMBOL,
		'MESSAGE_TEXT'                           => self::MESSAGE_TEXT_SYMBOL,
		'MICROSECOND'                            => self::MICROSECOND_SYMBOL,
		'MID'                                    => self::MID_SYMBOL,
		'MIDDLEINT'                              => self::MIDDLEINT_SYMBOL,
		'MIGRATE'                                => self::MIGRATE_SYMBOL,
		'MIN'                                    => self::MIN_SYMBOL,
		'MIN_ROWS'                               => self::MIN_ROWS_SYMBOL,
		'MINUTE'                                 => self::MINUTE_SYMBOL,
		'MINUTE_MICROSECOND'                     => self::MINUTE_MICROSECOND_SYMBOL,
		'MINUTE_SECOND'                          => self::MINUTE_SECOND_SYMBOL,
		'MOD'                                    => self::MOD_SYMBOL,
		'MODE'                                   => self::MODE_SYMBOL,
		'MODIFIES'                               => self::MODIFIES_SYMBOL,
		'MODIFY'                                 => self::MODIFY_SYMBOL,
		'MONTH'                                  => self::MONTH_SYMBOL,
		'MULTILINESTRING'                        => self::MULTILINESTRING_SYMBOL,
		'MULTIPOINT'                             => self::MULTIPOINT_SYMBOL,
		'MULTIPOLYGON'                           => self::MULTIPOLYGON_SYMBOL,
		'MUTEX'                                  => self::MUTEX_SYMBOL,
		'MYSQL_ERRNO'                            => self::MYSQL_ERRNO_SYMBOL,
		'NAME'                                   => self::NAME_SYMBOL,
		'NAMES'                                  => self::NAMES_SYMBOL,
		'NATIONAL'                               => self::NATIONAL_SYMBOL,
		'NATURAL'                                => self::NATURAL_SYMBOL,
		'NCHAR'                                  => self::NCHAR_SYMBOL,
		'NCHAR_STRING'                           => self::NCHAR_STRING_SYMBOL,
		'NDB'                                    => self::NDB_SYMBOL,
		'NDBCLUSTER'                             => self::NDBCLUSTER_SYMBOL,
		'NEG'                                    => self::NEG_SYMBOL,
		'NEVER'                                  => self::NEVER_SYMBOL,
		'NEW'                                    => self::NEW_SYMBOL,
		'NEXT'                                   => self::NEXT_SYMBOL,
		'NO'                                     => self::NO_SYMBOL,
		'NO_WAIT'                                => self::NO_WAIT_SYMBOL,
		'NO_WRITE_TO_BINLOG'                     => self::NO_WRITE_TO_BINLOG_SYMBOL,
		'NODEGROUP'                              => self::NODEGROUP_SYMBOL,
		'NONBLOCKING'                            => self::NONBLOCKING_SYMBOL,
		'NONE'                                   => self::NONE_SYMBOL,
		'NOT'                                    => self::NOT_SYMBOL,
		'NOW'                                    => self::NOW_SYMBOL,
		'NULL'                                   => self::NULL_SYMBOL,
		'NUMBER'                                 => self::NUMBER_SYMBOL,
		'NUMERIC'                                => self::NUMERIC_SYMBOL,
		'NVARCHAR'                               => self::NVARCHAR_SYMBOL,
		'OFFLINE'                                => self::OFFLINE_SYMBOL,
		'OFFSET'                                 => self::OFFSET_SYMBOL,
		'OLD_PASSWORD'                           => self::OLD_PASSWORD_SYMBOL,
		'ON'                                     => self::ON_SYMBOL,
		'ONE'                                    => self::ONE_SYMBOL,
		'ONLINE'                                 => self::ONLINE_SYMBOL,
		'ONLY'                                   => self::ONLY_SYMBOL,
		'OPEN'                                   => self::OPEN_SYMBOL,
		'OPTIMIZE'                               => self::OPTIMIZE_SYMBOL,
		'OPTIMIZER_COSTS'                        => self::OPTIMIZER_COSTS_SYMBOL,
		'OPTION'                                 => self::OPTION_SYMBOL,
		'OPTIONALLY'                             => self::OPTIONALLY_SYMBOL,
		'OPTIONS'                                => self::OPTIONS_SYMBOL,
		'OR'                                     => self::OR_SYMBOL,
		'ORDER'                                  => self::ORDER_SYMBOL,
		'OUT'                                    => self::OUT_SYMBOL,
		'OUTER'                                  => self::OUTER_SYMBOL,
		'OUTFILE'                                => self::OUTFILE_SYMBOL,
		'OWNER'                                  => self::OWNER_SYMBOL,
		'PACK_KEYS'                              => self::PACK_KEYS_SYMBOL,
		'PAGE'                                   => self::PAGE_SYMBOL,
		'PARSER'                                 => self::PARSER_SYMBOL,
		'PARTIAL'                                => self::PARTIAL_SYMBOL,
		'PARTITION'                              => self::PARTITION_SYMBOL,
		'PARTITIONING'                           => self::PARTITIONING_SYMBOL,
		'PARTITIONS'                             => self::PARTITIONS_SYMBOL,
		'PASSWORD'                               => self::PASSWORD_SYMBOL,
		'PHASE'                                  => self::PHASE_SYMBOL,
		'PLUGIN'                                 => self::PLUGIN_SYMBOL,
		'PLUGIN_DIR'                             => self::PLUGIN_DIR_SYMBOL,
		'PLUGINS'                                => self::PLUGINS_SYMBOL,
		'POINT'                                  => self::POINT_SYMBOL,
		'POLYGON'                                => self::POLYGON_SYMBOL,
		'PORT'                                   => self::PORT_SYMBOL,
		'POSITION'                               => self::POSITION_SYMBOL,
		'PRECEDES'                               => self::PRECEDES_SYMBOL,
		'PRECISION'                              => self::PRECISION_SYMBOL,
		'PREPARE'                                => self::PREPARE_SYMBOL,
		'PRESERVE'                               => self::PRESERVE_SYMBOL,
		'PREV'                                   => self::PREV_SYMBOL,
		'PRIMARY'                                => self::PRIMARY_SYMBOL,
		'PRIVILEGES'                             => self::PRIVILEGES_SYMBOL,
		'PROCEDURE'                              => self::PROCEDURE_SYMBOL,
		'PROCESS'                                => self::PROCESS_SYMBOL,
		'PROCESSLIST'                            => self::PROCESSLIST_SYMBOL,
		'PROFILE'                                => self::PROFILE_SYMBOL,
		'PROFILES'                               => self::PROFILES_SYMBOL,
		'PROXY'                                  => self::PROXY_SYMBOL,
		'PURGE'                                  => self::PURGE_SYMBOL,
		'QUARTER'                                => self::QUARTER_SYMBOL,
		'QUERY'                                  => self::QUERY_SYMBOL,
		'QUICK'                                  => self::QUICK_SYMBOL,
		'RANGE'                                  => self::RANGE_SYMBOL,
		'READ'                                   => self::READ_SYMBOL,
		'READ_ONLY'                              => self::READ_ONLY_SYMBOL,
		'READ_WRITE'                             => self::READ_WRITE_SYMBOL,
		'READS'                                  => self::READS_SYMBOL,
		'REAL'                                   => self::REAL_SYMBOL,
		'REBUILD'                                => self::REBUILD_SYMBOL,
		'RECOVER'                                => self::RECOVER_SYMBOL,
		'REDO_BUFFER_SIZE'                       => self::REDO_BUFFER_SIZE_SYMBOL,
		'REDOFILE'                               => self::REDOFILE_SYMBOL,
		'REDUNDANT'                              => self::REDUNDANT_SYMBOL,
		'REFERENCES'                             => self::REFERENCES_SYMBOL,
		'REGEXP'                                 => self::REGEXP_SYMBOL,
		'RELAY'                                  => self::RELAY_SYMBOL,
		'RELAY_LOG_FILE'                         => self::RELAY_LOG_FILE_SYMBOL,
		'RELAY_LOG_POS'                          => self::RELAY_LOG_POS_SYMBOL,
		'RELAY_THREAD'                           => self::RELAY_THREAD_SYMBOL,
		'RELAYLOG'                               => self::RELAYLOG_SYMBOL,
		'RELEASE'                                => self::RELEASE_SYMBOL,
		'RELOAD'                                 => self::RELOAD_SYMBOL,
		'REMOVE'                                 => self::REMOVE_SYMBOL,
		'RENAME'                                 => self::RENAME_SYMBOL,
		'REORGANIZE'                             => self::REORGANIZE_SYMBOL,
		'REPAIR'                                 => self::REPAIR_SYMBOL,
		'REPEAT'                                 => self::REPEAT_SYMBOL,
		'REPEATABLE'                             => self::REPEATABLE_SYMBOL,
		'REPLACE'                                => self::REPLACE_SYMBOL,
		'REPLICATE_DO_DB'                        => self::REPLICATE_DO_DB_SYMBOL,
		'REPLICATE_DO_TABLE'                     => self::REPLICATE_DO_TABLE_SYMBOL,
		'REPLICATE_IGNORE_DB'                    => self::REPLICATE_IGNORE_DB_SYMBOL,
		'REPLICATE_IGNORE_TABLE'                 => self::REPLICATE_IGNORE_TABLE_SYMBOL,
		'REPLICATE_REWRITE_DB'                   => self::REPLICATE_REWRITE_DB_SYMBOL,
		'REPLICATE_WILD_DO_TABLE'                => self::REPLICATE_WILD_DO_TABLE_SYMBOL,
		'REPLICATE_WILD_IGNORE_TABLE'            => self::REPLICATE_WILD_IGNORE_TABLE_SYMBOL,
		'REPLICATION'                            => self::REPLICATION_SYMBOL,
		'REQUIRE'                                => self::REQUIRE_SYMBOL,
		'RESET'                                  => self::RESET_SYMBOL,
		'RESIGNAL'                               => self::RESIGNAL_SYMBOL,
		'RESTORE'                                => self::RESTORE_SYMBOL,
		'RESTRICT'                               => self::RESTRICT_SYMBOL,
		'RESUME'                                 => self::RESUME_SYMBOL,
		'RETURN'                                 => self::RETURN_SYMBOL,
		'RETURNED_SQLSTATE'                      => self::RETURNED_SQLSTATE_SYMBOL,
		'RETURNS'                                => self::RETURNS_SYMBOL,
		'REVERSE'                                => self::REVERSE_SYMBOL,
		'REVOKE'                                 => self::REVOKE_SYMBOL,
		'RIGHT'                                  => self::RIGHT_SYMBOL,
		'RLIKE'                                  => self::RLIKE_SYMBOL,
		'ROLLBACK'                               => self::ROLLBACK_SYMBOL,
		'ROLLUP'                                 => self::ROLLUP_SYMBOL,
		'ROTATE'                                 => self::ROTATE_SYMBOL,
		'ROUTINE'                                => self::ROUTINE_SYMBOL,
		'ROW'                                    => self::ROW_SYMBOL,
		'ROW_COUNT'                              => self::ROW_COUNT_SYMBOL,
		'ROW_FORMAT'                             => self::ROW_FORMAT_SYMBOL,
		'ROWS'                                   => self::ROWS_SYMBOL,
		'RTREE'                                  => self::RTREE_SYMBOL,
		'SAVEPOINT'                              => self::SAVEPOINT_SYMBOL,
		'SCHEDULE'                               => self::SCHEDULE_SYMBOL,
		'SCHEMA'                                 => self::SCHEMA_SYMBOL,
		'SCHEMA_NAME'                            => self::SCHEMA_NAME_SYMBOL,
		'SCHEMAS'                                => self::SCHEMAS_SYMBOL,
		'SECOND'                                 => self::SECOND_SYMBOL,
		'SECOND_MICROSECOND'                     => self::SECOND_MICROSECOND_SYMBOL,
		'SECURITY'                               => self::SECURITY_SYMBOL,
		'SELECT'                                 => self::SELECT_SYMBOL,
		'SENSITIVE'                              => self::SENSITIVE_SYMBOL,
		'SEPARATOR'                              => self::SEPARATOR_SYMBOL,
		'SERIAL'                                 => self::SERIAL_SYMBOL,
		'SERIALIZABLE'                           => self::SERIALIZABLE_SYMBOL,
		'SERVER'                                 => self::SERVER_SYMBOL,
		'SERVER_OPTIONS'                         => self::SERVER_OPTIONS_SYMBOL,
		'SESSION'                                => self::SESSION_SYMBOL,
		'SESSION_USER'                           => self::SESSION_USER_SYMBOL,
		'SET'                                    => self::SET_SYMBOL,
		'SET_VAR'                                => self::SET_VAR_SYMBOL,
		'SHARE'                                  => self::SHARE_SYMBOL,
		'SHOW'                                   => self::SHOW_SYMBOL,
		'SHUTDOWN'                               => self::SHUTDOWN_SYMBOL,
		'SIGNAL'                                 => self::SIGNAL_SYMBOL,
		'SIGNED'                                 => self::SIGNED_SYMBOL,
		'SIMPLE'                                 => self::SIMPLE_SYMBOL,
		'SLAVE'                                  => self::SLAVE_SYMBOL,
		'SLOW'                                   => self::SLOW_SYMBOL,
		'SMALLINT'                               => self::SMALLINT_SYMBOL,
		'SNAPSHOT'                               => self::SNAPSHOT_SYMBOL,
		'SOCKET'                                 => self::SOCKET_SYMBOL,
		'SOME'                                   => self::SOME_SYMBOL,
		'SONAME'                                 => self::SONAME_SYMBOL,
		'SOUNDS'                                 => self::SOUNDS_SYMBOL,
		'SOURCE'                                 => self::SOURCE_SYMBOL,
		'SPATIAL'                                => self::SPATIAL_SYMBOL,
		'SPECIFIC'                               => self::SPECIFIC_SYMBOL,
		'SQL'                                    => self::SQL_SYMBOL,
		'SQL_AFTER_GTIDS'                        => self::SQL_AFTER_GTIDS_SYMBOL,
		'SQL_AFTER_MTS_GAPS'                     => self::SQL_AFTER_MTS_GAPS_SYMBOL,
		'SQL_BEFORE_GTIDS'                       => self::SQL_BEFORE_GTIDS_SYMBOL,
		'SQL_BIG_RESULT'                         => self::SQL_BIG_RESULT_SYMBOL,
		'SQL_BUFFER_RESULT'                      => self::SQL_BUFFER_RESULT_SYMBOL,
		'SQL_CACHE'                              => self::SQL_CACHE_SYMBOL,
		'SQL_CALC_FOUND_ROWS'                    => self::SQL_CALC_FOUND_ROWS_SYMBOL,
		'SQL_NO_CACHE'                           => self::SQL_NO_CACHE_SYMBOL,
		'SQL_SMALL_RESULT'                       => self::SQL_SMALL_RESULT_SYMBOL,
		'SQL_THREAD'                             => self::SQL_THREAD_SYMBOL,
		'SQL_TSI_DAY'                            => self::SQL_TSI_DAY_SYMBOL,
		'SQL_TSI_HOUR'                           => self::SQL_TSI_HOUR_SYMBOL,
		'SQL_TSI_MICROSECOND'                    => self::SQL_TSI_MICROSECOND_SYMBOL,
		'SQL_TSI_MINUTE'                         => self::SQL_TSI_MINUTE_SYMBOL,
		'SQL_TSI_MONTH'                          => self::SQL_TSI_MONTH_SYMBOL,
		'SQL_TSI_QUARTER'                        => self::SQL_TSI_QUARTER_SYMBOL,
		'SQL_TSI_SECOND'                         => self::SQL_TSI_SECOND_SYMBOL,
		'SQL_TSI_WEEK'                           => self::SQL_TSI_WEEK_SYMBOL,
		'SQL_TSI_YEAR'                           => self::SQL_TSI_YEAR_SYMBOL,
		'SQLEXCEPTION'                           => self::SQLEXCEPTION_SYMBOL,
		'SQLSTATE'                               => self::SQLSTATE_SYMBOL,
		'SQLWARNING'                             => self::SQLWARNING_SYMBOL,
		'SSL'                                    => self::SSL_SYMBOL,
		'STACKED'                                => self::STACKED_SYMBOL,
		'START'                                  => self::START_SYMBOL,
		'STARTING'                               => self::STARTING_SYMBOL,
		'STARTS'                                 => self::STARTS_SYMBOL,
		'STATS_AUTO_RECALC'                      => self::STATS_AUTO_RECALC_SYMBOL,
		'STATS_PERSISTENT'                       => self::STATS_PERSISTENT_SYMBOL,
		'STATS_SAMPLE_PAGES'                     => self::STATS_SAMPLE_PAGES_SYMBOL,
		'STATUS'                                 => self::STATUS_SYMBOL,
		'STD'                                    => self::STD_SYMBOL,
		'STDDEV'                                 => self::STDDEV_SYMBOL,
		'STDDEV_POP'                             => self::STDDEV_POP_SYMBOL,
		'STDDEV_SAMP'                            => self::STDDEV_SAMP_SYMBOL,
		'STOP'                                   => self::STOP_SYMBOL,
		'STORAGE'                                => self::STORAGE_SYMBOL,
		'STORED'                                 => self::STORED_SYMBOL,
		'STRAIGHT_JOIN'                          => self::STRAIGHT_JOIN_SYMBOL,
		'STRING'                                 => self::STRING_SYMBOL,
		'SUBCLASS_ORIGIN'                        => self::SUBCLASS_ORIGIN_SYMBOL,
		'SUBDATE'                                => self::SUBDATE_SYMBOL,
		'SUBJECT'                                => self::SUBJECT_SYMBOL,
		'SUBPARTITION'                           => self::SUBPARTITION_SYMBOL,
		'SUBPARTITIONS'                          => self::SUBPARTITIONS_SYMBOL,
		'SUBSTR'                                 => self::SUBSTR_SYMBOL,
		'SUBSTRING'                              => self::SUBSTRING_SYMBOL,
		'SUM'                                    => self::SUM_SYMBOL,
		'SUPER'                                  => self::SUPER_SYMBOL,
		'SUSPEND'                                => self::SUSPEND_SYMBOL,
		'SWAPS'                                  => self::SWAPS_SYMBOL,
		'SWITCHES'                               => self::SWITCHES_SYMBOL,
		'SYSDATE'                                => self::SYSDATE_SYMBOL,
		'SYSTEM_USER'                            => self::SYSTEM_USER_SYMBOL,
		'TABLE'                                  => self::TABLE_SYMBOL,
		'TABLE_CHECKSUM'                         => self::TABLE_CHECKSUM_SYMBOL,
		'TABLE_NAME'                             => self::TABLE_NAME_SYMBOL,
		'TABLE_REF_PRIORITY'                     => self::TABLE_REF_PRIORITY_SYMBOL,
		'TABLES'                                 => self::TABLES_SYMBOL,
		'TABLESPACE'                             => self::TABLESPACE_SYMBOL,
		'TEMPORARY'                              => self::TEMPORARY_SYMBOL,
		'TEMPTABLE'                              => self::TEMPTABLE_SYMBOL,
		'TERMINATED'                             => self::TERMINATED_SYMBOL,
		'TEXT'                                   => self::TEXT_SYMBOL,
		'THAN'                                   => self::THAN_SYMBOL,
		'THEN'                                   => self::THEN_SYMBOL,
		'TIME'                                   => self::TIME_SYMBOL,
		'TIMESTAMP'                              => self::TIMESTAMP_SYMBOL,
		'TIMESTAMP_ADD'                          => self::TIMESTAMP_ADD_SYMBOL,
		'TIMESTAMP_DIFF'                         => self::TIMESTAMP_DIFF_SYMBOL,
		'TINYBLOB'                               => self::TINYBLOB_SYMBOL,
		'TINYINT'                                => self::TINYINT_SYMBOL,
		'TINYTEXT'                               => self::TINYTEXT_SYMBOL,
		'TO'                                     => self::TO_SYMBOL,
		'TRAILING'                               => self::TRAILING_SYMBOL,
		'TRANSACTION'                            => self::TRANSACTION_SYMBOL,
		'TRIGGER'                                => self::TRIGGER_SYMBOL,
		'TRIGGERS'                               => self::TRIGGERS_SYMBOL,
		'TRIM'                                   => self::TRIM_SYMBOL,
		'TRUE'                                   => self::TRUE_SYMBOL,
		'TRUNCATE'                               => self::TRUNCATE_SYMBOL,
		'TYPE'                                   => self::TYPE_SYMBOL,
		'TYPES'                                  => self::TYPES_SYMBOL,
		'UDF_RETURNS'                            => self::UDF_RETURNS_SYMBOL,
		'UNCOMMITTED'                            => self::UNCOMMITTED_SYMBOL,
		'UNDEFINED'                              => self::UNDEFINED_SYMBOL,
		'UNDO'                                   => self::UNDO_SYMBOL,
		'UNDO_BUFFER_SIZE'                       => self::UNDO_BUFFER_SIZE_SYMBOL,
		'UNDOFILE'                               => self::UNDOFILE_SYMBOL,
		'UNICODE'                                => self::UNICODE_SYMBOL,
		'UNINSTALL'                              => self::UNINSTALL_SYMBOL,
		'UNION'                                  => self::UNION_SYMBOL,
		'UNIQUE'                                 => self::UNIQUE_SYMBOL,
		'UNKNOWN'                                => self::UNKNOWN_SYMBOL,
		'UNLOCK'                                 => self::UNLOCK_SYMBOL,
		'UNSIGNED'                               => self::UNSIGNED_SYMBOL,
		'UNTIL'                                  => self::UNTIL_SYMBOL,
		'UPDATE'                                 => self::UPDATE_SYMBOL,
		'UPGRADE'                                => self::UPGRADE_SYMBOL,
		'USAGE'                                  => self::USAGE_SYMBOL,
		'USE'                                    => self::USE_SYMBOL,
		'USE_FRM'                                => self::USE_FRM_SYMBOL,
		'USER'                                   => self::USER_SYMBOL,
		'USER_RESOURCES'                         => self::USER_RESOURCES_SYMBOL,
		'USING'                                  => self::USING_SYMBOL,
		'UTC_DATE'                               => self::UTC_DATE_SYMBOL,
		'UTC_TIME'                               => self::UTC_TIME_SYMBOL,
		'UTC_TIMESTAMP'                          => self::UTC_TIMESTAMP_SYMBOL,
		'VALIDATION'                             => self::VALIDATION_SYMBOL,
		'VALUE'                                  => self::VALUE_SYMBOL,
		'VALUES'                                 => self::VALUES_SYMBOL,
		'VAR_POP'                                => self::VAR_POP_SYMBOL,
		'VAR_SAMP'                               => self::VAR_SAMP_SYMBOL,
		'VARBINARY'                              => self::VARBINARY_SYMBOL,
		'VARCHAR'                                => self::VARCHAR_SYMBOL,
		'VARCHARACTER'                           => self::VARCHARACTER_SYMBOL,
		'VARIABLES'                              => self::VARIABLES_SYMBOL,
		'VARIANCE'                               => self::VARIANCE_SYMBOL,
		'VARYING'                                => self::VARYING_SYMBOL,
		'VIEW'                                   => self::VIEW_SYMBOL,
		'VIRTUAL'                                => self::VIRTUAL_SYMBOL,
		'WAIT'                                   => self::WAIT_SYMBOL,
		'WARNINGS'                               => self::WARNINGS_SYMBOL,
		'WEEK'                                   => self::WEEK_SYMBOL,
		'WEIGHT_STRING'                          => self::WEIGHT_STRING_SYMBOL,
		'WHEN'                                   => self::WHEN_SYMBOL,
		'WHERE'                                  => self::WHERE_SYMBOL,
		'WHILE'                                  => self::WHILE_SYMBOL,
		'WITH'                                   => self::WITH_SYMBOL,
		'WITHOUT'                                => self::WITHOUT_SYMBOL,
		'WORK'                                   => self::WORK_SYMBOL,
		'WRAPPER'                                => self::WRAPPER_SYMBOL,
		'WRITE'                                  => self::WRITE_SYMBOL,
		'X509'                                   => self::X509_SYMBOL,
		'XA'                                     => self::XA_SYMBOL,
		'XID'                                    => self::XID_SYMBOL,
		'XML'                                    => self::XML_SYMBOL,
		'XOR'                                    => self::XOR_SYMBOL,
		'YEAR'                                   => self::YEAR_SYMBOL,
		'YEAR_MONTH'                             => self::YEAR_MONTH_SYMBOL,
		'ZEROFILL'                               => self::ZEROFILL_SYMBOL,

		// Tokens from MySQL 8.0:
		'ACTIVE'                                 => self::ACTIVE_SYMBOL,
		'ADMIN'                                  => self::ADMIN_SYMBOL,
		'ARRAY'                                  => self::ARRAY_SYMBOL,
		'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS' => self::ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYMBOL,
		'BUCKETS'                                => self::BUCKETS_SYMBOL,
		'CLONE'                                  => self::CLONE_SYMBOL,
		'COMPONENT'                              => self::COMPONENT_SYMBOL,
		'CUME_DIST'                              => self::CUME_DIST_SYMBOL,
		'DEFINITION'                             => self::DEFINITION_SYMBOL,
		'DENSE_RANK'                             => self::DENSE_RANK_SYMBOL,
		'DESCRIPTION'                            => self::DESCRIPTION_SYMBOL,
		'EMPTY'                                  => self::EMPTY_SYMBOL,
		'ENFORCED'                               => self::ENFORCED_SYMBOL,
		'ENGINE_ATTRIBUTE'                       => self::ENGINE_ATTRIBUTE_SYMBOL,
		'EXCEPT'                                 => self::EXCEPT_SYMBOL,
		'EXCLUDE'                                => self::EXCLUDE_SYMBOL,
		'FAILED_LOGIN_ATTEMPTS'                  => self::FAILED_LOGIN_ATTEMPTS_SYMBOL,
		'FIRST_VALUE'                            => self::FIRST_VALUE_SYMBOL,
		'FOLLOWING'                              => self::FOLLOWING_SYMBOL,
		'GET_MASTER_PUBLIC_KEY_SYM'              => self::GET_MASTER_PUBLIC_KEY_SYMBOL,
		'GET_SOURCE_PUBLIC_KEY'                  => self::GET_SOURCE_PUBLIC_KEY_SYMBOL,
		'GROUPING'                               => self::GROUPING_SYMBOL,
		'GROUPS'                                 => self::GROUPS_SYMBOL,
		'GTID_ONLY'                              => self::GTID_ONLY_SYMBOL,
		'HISTOGRAM'                              => self::HISTOGRAM_SYMBOL,
		'HISTORY'                                => self::HISTORY_SYMBOL,
		'INACTIVE'                               => self::INACTIVE_SYMBOL,
		'INTERSECT'                              => self::INTERSECT_SYMBOL,
		'INVISIBLE'                              => self::INVISIBLE_SYMBOL,
		'JSON_ARRAYAGG'                          => self::JSON_ARRAYAGG_SYMBOL,
		'JSON_OBJECTAGG'                         => self::JSON_OBJECTAGG_SYMBOL,
		'JSON_TABLE'                             => self::JSON_TABLE_SYMBOL,
		'JSON_VALUE'                             => self::JSON_VALUE_SYMBOL,
		'KEYRING'                                => self::KEYRING_SYMBOL,
		'LAG'                                    => self::LAG_SYMBOL,
		'LAST_VALUE'                             => self::LAST_VALUE_SYMBOL,
		'LATERAL'                                => self::LATERAL_SYMBOL,
		'LEAD'                                   => self::LEAD_SYMBOL,
		'LOCKED'                                 => self::LOCKED_SYMBOL,
		'MASTER_COMPRESSION_ALGORITHM'           => self::MASTER_COMPRESSION_ALGORITHM_SYMBOL,
		'MASTER_PUBLIC_KEY_PATH'                 => self::MASTER_PUBLIC_KEY_PATH_SYMBOL,
		'MASTER_TLS_CIPHERSUITES'                => self::MASTER_TLS_CIPHERSUITES_SYMBOL,
		'MASTER_ZSTD_COMPRESSION_LEVEL'          => self::MASTER_ZSTD_COMPRESSION_LEVEL_SYMBOL,
		'MEMBER'                                 => self::MEMBER_SYMBOL,
		'NESTED'                                 => self::NESTED_SYMBOL,
		'NETWORK_NAMESPACE'                      => self::NETWORK_NAMESPACE_SYMBOL,
		'NOWAIT'                                 => self::NOWAIT_SYMBOL,
		'NTH_VALUE'                              => self::NTH_VALUE_SYMBOL,
		'NTILE'                                  => self::NTILE_SYMBOL,
		'NULLS'                                  => self::NULLS_SYMBOL,
		'OF'                                     => self::OF_SYMBOL,
		'OFF'                                    => self::OFF_SYMBOL,
		'OJ'                                     => self::OJ_SYMBOL,
		'OLD'                                    => self::OLD_SYMBOL,
		'OPTIONAL'                               => self::OPTIONAL_SYMBOL,
		'ORDINALITY'                             => self::ORDINALITY_SYMBOL,
		'ORGANIZATION'                           => self::ORGANIZATION_SYMBOL,
		'OTHERS'                                 => self::OTHERS_SYMBOL,
		'OVER'                                   => self::OVER_SYMBOL,
		'PASSWORD_LOCK_TIME'                     => self::PASSWORD_LOCK_TIME_SYMBOL,
		'PATH'                                   => self::PATH_SYMBOL,
		'PERCENT_RANK'                           => self::PERCENT_RANK_SYMBOL,
		'PERSIST'                                => self::PERSIST_SYMBOL,
		'PERSIST_ONLY'                           => self::PERSIST_ONLY_SYMBOL,
		'PRECEDING'                              => self::PRECEDING_SYMBOL,
		'PRIVILEGE_CHECKS_USER'                  => self::PRIVILEGE_CHECKS_USER_SYMBOL,
		'RANDOM'                                 => self::RANDOM_SYMBOL,
		'RANK'                                   => self::RANK_SYMBOL,
		'RECURSIVE'                              => self::RECURSIVE_SYMBOL,
		'REDO_LOG'                               => self::REDO_LOG_SYMBOL,
		'REFERENCE'                              => self::REFERENCE_SYMBOL,
		'REMOTE'                                 => self::REMOTE_SYMBOL,
		'REQUIRE_ROW_FORMAT'                     => self::REQUIRE_ROW_FORMAT_SYMBOL,
		'REQUIRE_TABLE_PRIMARY_KEY_CHECK'        => self::REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYMBOL,
		'RESOURCE'                               => self::RESOURCE_SYMBOL,
		'RESPECT'                                => self::RESPECT_SYMBOL,
		'RESTART'                                => self::RESTART_SYMBOL,
		'RETAIN'                                 => self::RETAIN_SYMBOL,
		'RETURNING'                              => self::RETURNING_SYMBOL,
		'REUSE'                                  => self::REUSE_SYMBOL,
		'ROLE'                                   => self::ROLE_SYMBOL,
		'ROW_NUMBER'                             => self::ROW_NUMBER_SYMBOL,
		'SECONDARY'                              => self::SECONDARY_SYMBOL,
		'SECONDARY_ENGINE'                       => self::SECONDARY_ENGINE_SYMBOL,
		'SECONDARY_ENGINE_ATTRIBUTE'             => self::SECONDARY_ENGINE_ATTRIBUTE_SYMBOL,
		'SECONDARY_LOAD'                         => self::SECONDARY_LOAD_SYMBOL,
		'SECONDARY_UNLOAD'                       => self::SECONDARY_UNLOAD_SYMBOL,
		'SKIP'                                   => self::SKIP_SYMBOL,
		'SOURCE_AUTO_POSITION'                   => self::SOURCE_AUTO_POSITION_SYMBOL,
		'SOURCE_BIND'                            => self::SOURCE_BIND_SYMBOL,
		'SOURCE_COMPRESSION_ALGORITHM'           => self::SOURCE_COMPRESSION_ALGORITHM_SYMBOL,
		'SOURCE_CONNECT_RETRY'                   => self::SOURCE_CONNECT_RETRY_SYMBOL,
		'SOURCE_CONNECTION_AUTO_FAILOVER'        => self::SOURCE_CONNECTION_AUTO_FAILOVER_SYMBOL,
		'SOURCE_DELAY'                           => self::SOURCE_DELAY_SYMBOL,
		'SOURCE_HEARTBEAT_PERIOD'                => self::SOURCE_HEARTBEAT_PERIOD_SYMBOL,
		'SOURCE_HOST'                            => self::SOURCE_HOST_SYMBOL,
		'SOURCE_LOG_FILE'                        => self::SOURCE_LOG_FILE_SYMBOL,
		'SOURCE_LOG_POS'                         => self::SOURCE_LOG_POS_SYMBOL,
		'SOURCE_PASSWORD'                        => self::SOURCE_PASSWORD_SYMBOL,
		'SOURCE_PORT'                            => self::SOURCE_PORT_SYMBOL,
		'SOURCE_PUBLIC_KEY_PATH'                 => self::SOURCE_PUBLIC_KEY_PATH_SYMBOL,
		'SOURCE_RETRY_COUNT'                     => self::SOURCE_RETRY_COUNT_SYMBOL,
		'SOURCE_SSL'                             => self::SOURCE_SSL_SYMBOL,
		'SOURCE_SSL_CA'                          => self::SOURCE_SSL_CA_SYMBOL,
		'SOURCE_SSL_CAPATH'                      => self::SOURCE_SSL_CAPATH_SYMBOL,
		'SOURCE_SSL_CERT'                        => self::SOURCE_SSL_CERT_SYMBOL,
		'SOURCE_SSL_CIPHER'                      => self::SOURCE_SSL_CIPHER_SYMBOL,
		'SOURCE_SSL_CRL'                         => self::SOURCE_SSL_CRL_SYMBOL,
		'SOURCE_SSL_CRLPATH'                     => self::SOURCE_SSL_CRLPATH_SYMBOL,
		'SOURCE_SSL_KEY'                         => self::SOURCE_SSL_KEY_SYMBOL,
		'SOURCE_SSL_VERIFY_SERVER_CERT'          => self::SOURCE_SSL_VERIFY_SERVER_CERT_SYMBOL,
		'SOURCE_TLS_CIPHERSUITES'                => self::SOURCE_TLS_CIPHERSUITES_SYMBOL,
		'SOURCE_TLS_VERSION'                     => self::SOURCE_TLS_VERSION_SYMBOL,
		'SOURCE_USER'                            => self::SOURCE_USER_SYMBOL,
		'SOURCE_ZSTD_COMPRESSION_LEVEL'          => self::SOURCE_ZSTD_COMPRESSION_LEVEL_SYMBOL,
		'SRID'                                   => self::SRID_SYMBOL,
		'STREAM'                                 => self::STREAM_SYMBOL,
		'SYSTEM'                                 => self::SYSTEM_SYMBOL,
		'THREAD_PRIORITY'                        => self::THREAD_PRIORITY_SYMBOL,
		'TIES'                                   => self::TIES_SYMBOL,
		'TLS'                                    => self::TLS_SYMBOL,
		'UNBOUNDED'                              => self::UNBOUNDED_SYMBOL,
		'VCPU'                                   => self::VCPU_SYMBOL,
		'VISIBLE'                                => self::VISIBLE_SYMBOL,
		'WINDOW'                                 => self::WINDOW_SYMBOL,
		'ZONE'                                   => self::ZONE_SYMBOL,
	);

	/**
	 * Tokens that represent function calls when followed by a parenthesis.
	 */
	const FUNCTIONS = array(
		self::ADDDATE_SYMBOL      => true,
		self::BIT_AND_SYMBOL      => true,
		self::BIT_OR_SYMBOL       => true,
		self::BIT_XOR_SYMBOL      => true,
		self::CAST_SYMBOL         => true,
		self::COUNT_SYMBOL        => true,
		self::CURDATE_SYMBOL      => true,
		self::CURRENT_DATE_SYMBOL => true,
		self::CURRENT_TIME_SYMBOL => true,
		self::CURTIME_SYMBOL      => true,
		self::DATE_ADD_SYMBOL     => true,
		self::DATE_SUB_SYMBOL     => true,
		self::EXTRACT_SYMBOL      => true,
		self::GROUP_CONCAT_SYMBOL => true,
		self::MAX_SYMBOL          => true,
		self::MID_SYMBOL          => true,
		self::MIN_SYMBOL          => true,
		self::NOW_SYMBOL          => true,
		self::POSITION_SYMBOL     => true,
		self::SESSION_USER_SYMBOL => true,
		self::STD_SYMBOL          => true,
		self::STDDEV_POP_SYMBOL   => true,
		self::STDDEV_SAMP_SYMBOL  => true,
		self::STDDEV_SYMBOL       => true,
		self::SUBDATE_SYMBOL      => true,
		self::SUBSTR_SYMBOL       => true,
		self::SUBSTRING_SYMBOL    => true,
		self::SUM_SYMBOL          => true,
		self::SYSDATE_SYMBOL      => true,
		self::SYSTEM_USER_SYMBOL  => true,
		self::TRIM_SYMBOL         => true,
		self::VAR_POP_SYMBOL      => true,
		self::VAR_SAMP_SYMBOL     => true,
		self::VARIANCE_SYMBOL     => true,
	);

	/**
	 * Tokens that are functionally equivalent and can be used interchangeably.
	 *
	 * Some of the synonyms may have a different keyword or function status and
	 * version constraints, hence the synonym conversion needs to be applied
	 * at the end of the tokenization process, after all other transformations.
	 *
	 * E.g.: NOW is a non-reserved keyword that needs to be used with "()" while
	 *       CURRENT_TIMESTAMP is a reserved keyword that can be used without "()".
	 */
	const SYNONYMS = array(
		self::CHARACTER_SYMBOL           => self::CHAR_SYMBOL,
		self::CURRENT_DATE_SYMBOL        => self::CURDATE_SYMBOL,
		self::CURRENT_TIME_SYMBOL        => self::CURTIME_SYMBOL,
		self::CURRENT_TIMESTAMP_SYMBOL   => self::NOW_SYMBOL,
		self::DAYOFMONTH_SYMBOL          => self::DAY_SYMBOL,
		self::DEC_SYMBOL                 => self::DECIMAL_SYMBOL,
		self::DISTINCTROW_SYMBOL         => self::DISTINCT_SYMBOL,
		self::FIELDS_SYMBOL              => self::COLUMNS_SYMBOL,
		self::FLOAT4_SYMBOL              => self::FLOAT_SYMBOL,
		self::FLOAT8_SYMBOL              => self::DOUBLE_SYMBOL,
		self::GEOMCOLLECTION_SYMBOL      => self::GEOMETRYCOLLECTION_SYMBOL,
		self::INT1_SYMBOL                => self::TINYINT_SYMBOL,
		self::INT2_SYMBOL                => self::SMALLINT_SYMBOL,
		self::INT3_SYMBOL                => self::MEDIUMINT_SYMBOL,
		self::INT4_SYMBOL                => self::INT_SYMBOL,
		self::INT8_SYMBOL                => self::BIGINT_SYMBOL,
		self::INTEGER_SYMBOL             => self::INT_SYMBOL,
		self::IO_THREAD_SYMBOL           => self::RELAY_THREAD_SYMBOL,
		self::LOCALTIME_SYMBOL           => self::NOW_SYMBOL,
		self::LOCALTIMESTAMP_SYMBOL      => self::NOW_SYMBOL,
		self::MID_SYMBOL                 => self::SUBSTRING_SYMBOL,
		self::MIDDLEINT_SYMBOL           => self::MEDIUMINT_SYMBOL,
		self::NDB_SYMBOL                 => self::NDBCLUSTER_SYMBOL,
		self::RLIKE_SYMBOL               => self::REGEXP_SYMBOL,
		self::SCHEMA_SYMBOL              => self::DATABASE_SYMBOL,
		self::SCHEMAS_SYMBOL             => self::DATABASES_SYMBOL,
		self::SESSION_USER_SYMBOL        => self::USER_SYMBOL,
		self::SOME_SYMBOL                => self::ANY_SYMBOL,
		self::SQL_TSI_DAY_SYMBOL         => self::DAY_SYMBOL,
		self::SQL_TSI_HOUR_SYMBOL        => self::HOUR_SYMBOL,
		self::SQL_TSI_MICROSECOND_SYMBOL => self::MICROSECOND_SYMBOL,
		self::SQL_TSI_MINUTE_SYMBOL      => self::MINUTE_SYMBOL,
		self::SQL_TSI_MONTH_SYMBOL       => self::MONTH_SYMBOL,
		self::SQL_TSI_QUARTER_SYMBOL     => self::QUARTER_SYMBOL,
		self::SQL_TSI_SECOND_SYMBOL      => self::SECOND_SYMBOL,
		self::SQL_TSI_WEEK_SYMBOL        => self::WEEK_SYMBOL,
		self::SQL_TSI_YEAR_SYMBOL        => self::YEAR_SYMBOL,
		self::STDDEV_POP_SYMBOL          => self::STD_SYMBOL,
		self::STDDEV_SYMBOL              => self::STD_SYMBOL,
		self::SUBSTR_SYMBOL              => self::SUBSTRING_SYMBOL,
		self::SYSTEM_USER_SYMBOL         => self::USER_SYMBOL,
		self::VAR_POP_SYMBOL             => self::VARIANCE_SYMBOL,
		self::VARCHARACTER_SYMBOL        => self::VARCHAR_SYMBOL,
	);

	/**
	 * Version constraints for version-specific tokens.
	 *
	 * This is a map of tokens to the MySQL server versions in which they were
	 * introduced (positive number) or removed (negative number). Tokens that
	 * were both introduced and later removed are not included in this list
	 * and are handled by manual version checks in the tokenization process.
	 *
	 * See:
	 *   https://dev.mysql.com/doc/mysqld-version-reference/en/keywords.html
	 *
	 * @TODO Verify the version specifiers and ranges against the list above.
	 *
	 * Positive number: >= <version> (introduced in <version>)
	 * Negative number: <  <version> (removed in <version>)
	 */
	const VERSIONS = array(
		// MySQL 5
		self::ACCOUNT_SYMBOL                         => 50707,
		self::ALWAYS_SYMBOL                          => 50707,
		self::ANALYSE_SYMBOL                         => -80000,
		self::AUTHORS_SYMBOL                         => -50700,
		self::CHANNEL_SYMBOL                         => 50706,
		self::COMPRESSION_SYMBOL                     => 50707,
		self::CONTRIBUTORS_SYMBOL                    => -50700,
		self::CURRENT_SYMBOL                         => 50604,
		self::DEFAULT_AUTH_SYMBOL                    => 50604,
		self::DES_KEY_FILE_SYMBOL                    => -80003,
		self::ENCRYPTION_SYMBOL                      => 50711,
		self::EXPIRE_SYMBOL                          => 50606,
		self::EXPORT_SYMBOL                          => 50606,
		self::FILE_BLOCK_SIZE_SYMBOL                 => 50707,
		self::FILTER_SYMBOL                          => 50700,
		self::FOLLOWS_SYMBOL                         => 50700,
		self::GENERATED_SYMBOL                       => 50707,
		self::GET_SYMBOL                             => 50604,
		self::GROUP_REPLICATION_SYMBOL               => 50707,
		self::INNODB_SYMBOL                          => 50711,
		self::INSTANCE_SYMBOL                        => 50713,
		self::JSON_SYMBOL                            => 50708,
		self::MASTER_AUTO_POSITION_SYMBOL            => 50605,
		self::MASTER_BIND_SYMBOL                     => 50602,
		self::MASTER_RETRY_COUNT_SYMBOL              => 50601,
		self::MASTER_SSL_CRL_SYMBOL                  => 50603,
		self::MASTER_SSL_CRLPATH_SYMBOL              => 50603,
		self::MASTER_TLS_VERSION_SYMBOL              => 50713,
		self::NEVER_SYMBOL                           => 50704,
		self::NUMBER_SYMBOL                          => 50606,
		self::OLD_PASSWORD_SYMBOL                    => -50706,
		self::ONLY_SYMBOL                            => 50605,
		self::OPTIMIZER_COSTS_SYMBOL                 => 50706,
		self::PLUGIN_DIR_SYMBOL                      => 50604,
		self::PRECEDES_SYMBOL                        => 50700,
		self::REDOFILE_SYMBOL                        => -80000,
		self::REPLICATE_DO_DB_SYMBOL                 => 50700,
		self::REPLICATE_DO_TABLE_SYMBOL              => 50700,
		self::REPLICATE_IGNORE_DB_SYMBOL             => 50700,
		self::REPLICATE_IGNORE_TABLE_SYMBOL          => 50700,
		self::REPLICATE_REWRITE_DB_SYMBOL            => 50700,
		self::REPLICATE_WILD_DO_TABLE_SYMBOL         => 50700,
		self::REPLICATE_WILD_IGNORE_TABLE_SYMBOL     => 50700,
		self::ROTATE_SYMBOL                          => 50713,
		self::SQL_AFTER_MTS_GAPS_SYMBOL              => 50606,
		self::SQL_CACHE_SYMBOL                       => -80000,
		self::STACKED_SYMBOL                         => 50700,
		self::STORED_SYMBOL                          => 50707,
		self::TABLE_REF_PRIORITY_SYMBOL              => -80000,
		self::VALIDATION_SYMBOL                      => 50706,
		self::VIRTUAL_SYMBOL                         => 50707,
		self::XID_SYMBOL                             => 50704,

		// MySQL 8
		self::ACTIVE_SYMBOL                          => 80014,
		self::ADMIN_SYMBOL                           => 80000,
		self::ARRAY_SYMBOL                           => 80017,
		self::ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYMBOL => 80000,
		self::ATTRIBUTE_SYMBOL                       => 80021,
		self::BUCKETS_SYMBOL                         => 80000,
		self::CLONE_SYMBOL                           => 80000,
		self::COMPONENT_SYMBOL                       => 80000,
		self::CUME_DIST_SYMBOL                       => 80000,
		self::DEFINITION_SYMBOL                      => 80011,
		self::DENSE_RANK_SYMBOL                      => 80000,
		self::DESCRIPTION_SYMBOL                     => 80011,
		self::EMPTY_SYMBOL                           => 80000,
		self::ENFORCED_SYMBOL                        => 80017,
		self::ENGINE_ATTRIBUTE_SYMBOL                => 80021,
		self::EXCEPT_SYMBOL                          => 80000,
		self::EXCLUDE_SYMBOL                         => 80000,
		self::FAILED_LOGIN_ATTEMPTS_SYMBOL           => 80019,
		self::FIRST_VALUE_SYMBOL                     => 80000,
		self::FOLLOWING_SYMBOL                       => 80000,
		self::GEOMCOLLECTION_SYMBOL                  => 80000,
		self::GET_MASTER_PUBLIC_KEY_SYMBOL           => 80000,
		self::GET_SOURCE_PUBLIC_KEY_SYMBOL           => 80000,
		self::GROUPING_SYMBOL                        => 80000,
		self::GROUPS_SYMBOL                          => 80000,
		self::GTID_ONLY_SYMBOL                       => 80000,
		self::HISTOGRAM_SYMBOL                       => 80000,
		self::HISTORY_SYMBOL                         => 80000,
		self::INACTIVE_SYMBOL                        => 80014,
		self::INTERSECT_SYMBOL                       => 80031,
		self::INVISIBLE_SYMBOL                       => 80000,
		self::JSON_ARRAYAGG_SYMBOL                   => 80000,
		self::JSON_OBJECTAGG_SYMBOL                  => 80000,
		self::JSON_TABLE_SYMBOL                      => 80000,
		self::JSON_VALUE_SYMBOL                      => 80021,
		self::KEYRING_SYMBOL                         => 80024,
		self::LAG_SYMBOL                             => 80000,
		self::LAST_VALUE_SYMBOL                      => 80000,
		self::LATERAL_SYMBOL                         => 80014,
		self::LEAD_SYMBOL                            => 80000,
		self::LOCKED_SYMBOL                          => 80000,
		self::MASTER_COMPRESSION_ALGORITHM_SYMBOL    => 80018,
		self::MASTER_PUBLIC_KEY_PATH_SYMBOL          => 80000,
		self::MASTER_TLS_CIPHERSUITES_SYMBOL         => 80018,
		self::MASTER_ZSTD_COMPRESSION_LEVEL_SYMBOL   => 80018,
		self::MEMBER_SYMBOL                          => 80017,
		self::NESTED_SYMBOL                          => 80000,
		self::NETWORK_NAMESPACE_SYMBOL               => 80017,
		self::NOWAIT_SYMBOL                          => 80000,
		self::NTH_VALUE_SYMBOL                       => 80000,
		self::NTILE_SYMBOL                           => 80000,
		self::NULLS_SYMBOL                           => 80000,
		self::OF_SYMBOL                              => 80000,
		self::OFF_SYMBOL                             => 80019,
		self::OJ_SYMBOL                              => 80017,
		self::OLD_SYMBOL                             => 80014,
		self::OPTIONAL_SYMBOL                        => 80013,
		self::ORDINALITY_SYMBOL                      => 80000,
		self::ORGANIZATION_SYMBOL                    => 80011,
		self::OTHERS_SYMBOL                          => 80000,
		self::OVER_SYMBOL                            => 80000,
		self::PASSWORD_LOCK_TIME_SYMBOL              => 80019,
		self::PATH_SYMBOL                            => 80000,
		self::PERCENT_RANK_SYMBOL                    => 80000,
		self::PERSIST_ONLY_SYMBOL                    => 80000,
		self::PERSIST_SYMBOL                         => 80000,
		self::PRECEDING_SYMBOL                       => 80000,
		self::PRIVILEGE_CHECKS_USER_SYMBOL           => 80018,
		self::RANDOM_SYMBOL                          => 80018,
		self::RANK_SYMBOL                            => 80000,
		self::RECURSIVE_SYMBOL                       => 80000,
		self::REDO_LOG_SYMBOL                        => 80021,
		self::REFERENCE_SYMBOL                       => 80011,
		self::REQUIRE_ROW_FORMAT_SYMBOL              => 80019,
		self::REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYMBOL => 80019,
		self::RESOURCE_SYMBOL                        => 80000,
		self::RESPECT_SYMBOL                         => 80000,
		self::RESTART_SYMBOL                         => 80011,
		self::RETAIN_SYMBOL                          => 80014,
		self::REUSE_SYMBOL                           => 80000,
		self::RETURNING_SYMBOL                       => 80021,
		self::ROLE_SYMBOL                            => 80000,
		self::ROW_NUMBER_SYMBOL                      => 80000,
		self::SECONDARY_ENGINE_ATTRIBUTE_SYMBOL      => 80021,
		self::SECONDARY_ENGINE_SYMBOL                => 80013,
		self::SECONDARY_LOAD_SYMBOL                  => 80013,
		self::SECONDARY_SYMBOL                       => 80013,
		self::SECONDARY_UNLOAD_SYMBOL                => 80013,
		self::SKIP_SYMBOL                            => 80000,
		self::SOURCE_AUTO_POSITION_SYMBOL            => 80000,
		self::SOURCE_BIND_SYMBOL                     => 80000,
		self::SOURCE_COMPRESSION_ALGORITHM_SYMBOL    => 80000,
		self::SOURCE_CONNECT_RETRY_SYMBOL            => 80000,
		self::SOURCE_CONNECTION_AUTO_FAILOVER_SYMBOL => 80000,
		self::SOURCE_DELAY_SYMBOL                    => 80000,
		self::SOURCE_HEARTBEAT_PERIOD_SYMBOL         => 80000,
		self::SOURCE_HOST_SYMBOL                     => 80000,
		self::SOURCE_LOG_FILE_SYMBOL                 => 80000,
		self::SOURCE_LOG_POS_SYMBOL                  => 80000,
		self::SOURCE_PASSWORD_SYMBOL                 => 80000,
		self::SOURCE_PORT_SYMBOL                     => 80000,
		self::SOURCE_PUBLIC_KEY_PATH_SYMBOL          => 80000,
		self::SOURCE_RETRY_COUNT_SYMBOL              => 80000,
		self::SOURCE_SSL_CA_SYMBOL                   => 80000,
		self::SOURCE_SSL_CAPATH_SYMBOL               => 80000,
		self::SOURCE_SSL_CERT_SYMBOL                 => 80000,
		self::SOURCE_SSL_CIPHER_SYMBOL               => 80000,
		self::SOURCE_SSL_CRL_SYMBOL                  => 80000,
		self::SOURCE_SSL_CRLPATH_SYMBOL              => 80000,
		self::SOURCE_SSL_KEY_SYMBOL                  => 80000,
		self::SOURCE_SSL_SYMBOL                      => 80000,
		self::SOURCE_SSL_VERIFY_SERVER_CERT_SYMBOL   => 80000,
		self::SOURCE_TLS_CIPHERSUITES_SYMBOL         => 80000,
		self::SOURCE_TLS_VERSION_SYMBOL              => 80000,
		self::SOURCE_USER_SYMBOL                     => 80000,
		self::SOURCE_ZSTD_COMPRESSION_LEVEL_SYMBOL   => 80000,
		self::SRID_SYMBOL                            => 80000,
		self::STREAM_SYMBOL                          => 80019,
		self::SYSTEM_SYMBOL                          => 80000,
		self::THREAD_PRIORITY_SYMBOL                 => 80000,
		self::TIES_SYMBOL                            => 80000,
		self::TLS_SYMBOL                             => 80016,
		self::UNBOUNDED_SYMBOL                       => 80000,
		self::VCPU_SYMBOL                            => 80000,
		self::VISIBLE_SYMBOL                         => 80000,
		self::WINDOW_SYMBOL                          => 80000,
		self::ZONE_SYMBOL                            => 80022,
	);

	/**
	 * Identifier-like strings that may represent underscore-prefixed charset names.
	 *
	 * Includes charsets from both MySQL 5 and 8; via "SHOW CHARACTER SET"/docs:
	 *   https://dev.mysql.com/doc/refman/5.7/en/charset-charsets.html
	 *   https://dev.mysql.com/doc/refman/8.4/en/charset-charsets.html
	 *
	 * @TODO: Make the list respect the MySQL version. The _utf8 underscore charset
	 *        exists only on MySQL 5, and maybe some others are version-dependant too.
	 *        We can check this using SHOW CHARACTER SET on different MySQL versions.
	 */
	const UNDERSCORE_CHARSETS = array(
		'_armscii8' => true,
		'_ascii'    => true,
		'_big5'     => true,
		'_binary'   => true,
		'_cp1250'   => true,
		'_cp1251'   => true,
		'_cp1256'   => true,
		'_cp1257'   => true,
		'_cp850'    => true,
		'_cp852'    => true,
		'_cp866'    => true,
		'_cp932'    => true,
		'_dec8'     => true,
		'_eucjpms'  => true,
		'_euckr'    => true,
		'_gb18030'  => true,
		'_gb2312'   => true,
		'_gbk'      => true,
		'_geostd8'  => true,
		'_greek'    => true,
		'_hebrew'   => true,
		'_hp8'      => true,
		'_keybcs2'  => true,
		'_koi8r'    => true,
		'_koi8u'    => true,
		'_latin1'   => true,
		'_latin2'   => true,
		'_latin5'   => true,
		'_latin7'   => true,
		'_macce'    => true,
		'_macroman' => true,
		'_sjis'     => true,
		'_swe7'     => true,
		'_tis620'   => true,
		'_ucs2'     => true,
		'_ujis'     => true,
		'_utf16'    => true,
		'_utf16le'  => true,
		'_utf32'    => true,
		'_utf8'     => true,
		'_utf8mb3'  => true,
		'_utf8mb4'  => true,
	);

	/**
	 * The SQL payload to tokenize.
	 *
	 * @var string
	 */
	private $sql;

	/**
	 * Byte length of the SQL payload.
	 *
	 * @var int
	 */
	private $sql_length;

	/**
	 * The version of the MySQL server that the SQL payload is intended for.
	 *
	 * This is used to determine which tokens are valid for the given MySQL
	 * version, and how some tokens should be interpreted.
	 *
	 * @var int
	 */
	private $mysql_version;

	/**
	 * The SQL modes that should be considered active during tokenization.
	 *
	 * This is an integer that represents currently active SQL modes as a bitmask.
	 * The SQL modes are defined as "SQL_MODE_"-prefixed constants in this class.
	 * The list of the SQL modes isn't exhaustive, as only some affect tokenization.
	 *
	 * @var int
	 */
	private $sql_modes = 0;

	/**
	 * How many bytes from the original SQL payload have been read and tokenized.
	 *
	 * This is an internal cursor that is used to track the current position in
	 * the SQL payload during tokenization. When used as an index in the SQL
	 * payload, it points to the next byte to read.
	 *
	 * @var int
	 */
	private $bytes_already_read = 0;

	/**
	 * Byte offset in the SQL payload where current token starts.
	 *
	 * This is used to extract the token bytes after the token is processed.
	 * The bytes of the current token are represented by "$this->sql" in range
	 * from "$this->token_starts_at" to "$this->bytes_already_read - 1".
	 *
	 * @var int
	 */
	private $token_starts_at = 0;

	/**
	 * The type of the current token.
	 *
	 * When a token is successfully recognized and read, this value is set to the
	 * constant representing the token type. When no token was read yet, or the
	 * end of the SQL payload or an invalid token is reached, this value is null.
	 *
	 * @var int|null
	 */
	private $token_type;

	/**
	 * Whether the tokenizer is inside an active MySQL-specific comment.
	 *
	 * MySQL supports a special comment syntax whose content is recognized as
	 * a comment by most database engines, but can be treated as SQL by MySQL:
	 *
	 *  1. /*! ...  - The content is treated as SQL.
	 *  2. /*!12345 - The content is treated as SQL when "MySQL version >= 12345".
	 *
	 * @var bool
	 */
	private $in_mysql_comment = false;

	/**
	 * @param string $sql The SQL payload to tokenize.
	 * @param int $mysql_version The version of the MySQL server that the SQL payload is intended for.
	 * @param string[] $sql_modes The SQL modes that should be considered active during tokenization.
	 */
	public function __construct(
		string $sql,
		int $mysql_version = 80038,
		array $sql_modes = array()
	) {
		$this->sql           = $sql;
		$this->sql_length    = strlen( $sql );
		$this->mysql_version = $mysql_version;

		foreach ( $sql_modes as $sql_mode ) {
			$sql_mode = strtoupper( $sql_mode );
			if ( 'HIGH_NOT_PRECEDENCE' === $sql_mode ) {
				$this->sql_modes |= self::SQL_MODE_HIGH_NOT_PRECEDENCE;
			} elseif ( 'PIPES_AS_CONCAT' === $sql_mode ) {
				$this->sql_modes |= self::SQL_MODE_PIPES_AS_CONCAT;
			} elseif ( 'IGNORE_SPACE' === $sql_mode ) {
				$this->sql_modes |= self::SQL_MODE_IGNORE_SPACE;
			} elseif ( 'NO_BACKSLASH_ESCAPES' === $sql_mode ) {
				$this->sql_modes |= self::SQL_MODE_NO_BACKSLASH_ESCAPES;
			} elseif ( 'ANSI_QUOTES' === $sql_mode ) {
				$this->sql_modes |= self::SQL_MODE_ANSI_QUOTES;
			} elseif ( 'ANSI' === $sql_mode ) {
				/*
				 * Expand the composite ANSI mode into its lexer-relevant components.
				 * The ANSI mode also implies REAL_AS_FLOAT and ONLY_FULL_GROUP_BY,
				 * which do not affect the lexer.
				 *
				 * See: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_ansi
				 */
				$this->sql_modes |= self::SQL_MODE_PIPES_AS_CONCAT
					| self::SQL_MODE_IGNORE_SPACE
					| self::SQL_MODE_ANSI_QUOTES;
			}
		}
	}

	/**
	 * Read the next token from the SQL payload and return it as a token object.
	 *
	 * This method reads bytes from the SQL payload until a token is recognized.
	 * It starts from "$this->sql[ $this->bytes_already_read ]", advances the
	 * number of bytes read, and returns a boolean indicating whether a token
	 * was successfully recognized and read. When the end of the SQL payload
	 * or an invalid token is reached, the method returns false.
	 *
	 * @return bool Whether a token was successfully recognized and read.
	 */
	public function next_token(): bool {
		// We already reached the end of the SQL payload or an invalid token.
		// Don't attempt to read any more bytes, and bail out immediately.
		if (
			self::EOF === $this->token_type
			|| ( null === $this->token_type && $this->bytes_already_read > 0 )
		) {
			$this->token_type = null;
			return false;
		}

		// Skip leading whitespace inline for optimal performance.
		$this->bytes_already_read += strspn( $this->sql, self::WHITESPACE_MASK, $this->bytes_already_read );

		do {
			$this->token_starts_at = $this->bytes_already_read;
			$this->token_type      = $this->read_next_token();
		} while (
			self::WHITESPACE === $this->token_type
			|| self::COMMENT === $this->token_type
			|| self::MYSQL_COMMENT_START === $this->token_type
			|| self::MYSQL_COMMENT_END === $this->token_type
		);

		// Invalid input.
		if ( null === $this->token_type ) {
			return false;
		}
		return true;
	}

	/**
	 * Return the current token represented as a WP_MySQL_Token object.
	 *
	 * When no token was read yet, or the end of the SQL payload or an invalid
	 * token is reached, the method returns null.
	 *
	 * @TODO: Consider referential stability ($lexer->get_token() === $lexer->get_token()),
	 *        or separate getters for the token type and token bytes (no token objects).
	 *
	 * @return WP_MySQL_Token|null An object representing the next recognized token or null.
	 */
	public function get_token(): ?WP_MySQL_Token {
		if ( null === $this->token_type ) {
			return null;
		}
		return new WP_MySQL_Token(
			$this->token_type,
			$this->token_starts_at,
			$this->bytes_already_read - $this->token_starts_at,
			$this->sql,
			$this->is_sql_mode_active( self::SQL_MODE_NO_BACKSLASH_ESCAPES )
		);
	}

	/**
	 * Read all remaining tokens from the SQL payload and return them as an array.
	 *
	 * This method starts from the current position in the SQL payload, as marked
	 * by "$this->sql[ $this->bytes_already_read ]", and reads all tokens until
	 * the end of the SQL payload is reached, returning an array of token objects.
	 *
	 * When an invalid token is reached, the method stops and returns the partial
	 * sequence of valid tokens. In this case, the EOF token will not be included.
	 *
	 * This method can be used to tokenize the whole SQL payload at once, at the
	 * expense of storing all token objects in memory at the same time.
	 *
	 * @return WP_MySQL_Token[] An array of token objects representing the remaining tokens.
	 */
	public function remaining_tokens(): array {
		$tokens                            = array();
		$no_backslash_escapes_sql_mode_set = $this->is_sql_mode_active(
			self::SQL_MODE_NO_BACKSLASH_ESCAPES
		);

		while ( true ) {
			// Bail on EOF, or on a null token type once at least one byte has
			// been consumed (read_next_token() hit invalid input mid-stream).
			if (
				self::EOF === $this->token_type
				|| ( null === $this->token_type && $this->bytes_already_read > 0 )
			) {
				$this->token_type = null;
				break;
			}

			// Skip leading whitespace inline for optimal performance.
			$this->bytes_already_read += strspn( $this->sql, self::WHITESPACE_MASK, $this->bytes_already_read );

			do {
				$this->token_starts_at = $this->bytes_already_read;
				$this->token_type      = $this->read_next_token();
			} while (
				self::WHITESPACE === $this->token_type
				|| self::COMMENT === $this->token_type
				|| self::MYSQL_COMMENT_START === $this->token_type
				|| self::MYSQL_COMMENT_END === $this->token_type
			);

			if ( null === $this->token_type ) {
				break;
			}

			$tokens[] = new WP_MySQL_Token(
				$this->token_type,
				$this->token_starts_at,
				$this->bytes_already_read - $this->token_starts_at,
				$this->sql,
				$no_backslash_escapes_sql_mode_set
			);

			if ( self::EOF === $this->token_type ) {
				$this->token_type = null;
				break;
			}
		}
		return $tokens;
	}

	/**
	 * The version of the MySQL server that the SQL payload is intended for.
	 *
	 * This represents the MySQL server version that the lexer is set up to
	 * consider when tokenizing the SQL payload.
	 *
	 * @return int The MySQL server version that the lexer is set up to consider.
	 */
	public function get_mysql_version(): int {
		return $this->mysql_version;
	}

	/**
	 * Whether an SQL mode is set to be considered as active during tokenization.
	 * The SQL modes are defined as "SQL_MODE_"-prefixed constants in this class.
	 *
	 * @param int $mode The SQL mode to check, an "SQL_MODE_"-prefixed constant.
	 * @return bool Whether the given SQL mode is active.
	 */
	public function is_sql_mode_active( int $mode ): bool {
		return ( $this->sql_modes & $mode ) !== 0;
	}

	/**
	 * Get the numeric token ID for a given token name.
	 *
	 * @param string $token_name The name of the token.
	 * @return int|null The token ID for the given token name; null when not found.
	 */
	public static function get_token_id( string $token_name ): ?int {
		$constant_name = self::class . '::' . $token_name;
		if ( ! defined( $constant_name ) ) {
			return null;
		}
		return constant( $constant_name );
	}

	/**
	 * Get the name of a token for a given token ID.
	 *
	 * This method is intended to be used only for testing and debugging purposes,
	 * when tokens need to be presented by their names in a human-readable form.
	 * It should not be used in production code, as it's not performance-optimized.
	 *
	 * @param int $token_id The numeric token ID.
	 * @return string The token name for the given token ID; null when not found.
	 */
	public static function get_token_name( int $token_id ): ?string {
		$reflection = new ReflectionClass( self::class );
		// Reverse the array, as some constant values in the class can conflict,
		// and tokens are defined at the end of the class constant definitions.
		// @TODO: Consider are more robust way to determine the token name.
		//        E.g., prefix all token constant names with a common prefix.
		$constants  = array_reverse( $reflection->getConstants() );
		$token_name = array_search( $token_id, $constants, true );
		return $token_name ? $token_name : null;
	}

	private function read_next_token(): ?int {
		$byte      = $this->sql[ $this->bytes_already_read ] ?? null;
		$next_byte = $this->sql[ $this->bytes_already_read + 1 ] ?? null;

		// A map for a single-byte symbol fast path.
		static $single_byte_ops = array(
			'(' => self::OPEN_PAR_SYMBOL,
			')' => self::CLOSE_PAR_SYMBOL,
			',' => self::COMMA_SYMBOL,
			';' => self::SEMICOLON_SYMBOL,
			'+' => self::PLUS_OPERATOR,
			'~' => self::BITWISE_NOT_OPERATOR,
			'%' => self::MOD_OPERATOR,
			'^' => self::BITWISE_XOR_OPERATOR,
			'?' => self::PARAM_MARKER,
			'{' => self::OPEN_CURLY_SYMBOL,
			'}' => self::CLOSE_CURLY_SYMBOL,
			'=' => self::EQUAL_OPERATOR,
		);

		// Fast path for keywords and identifiers.
		// `$byte > "\x7F"` catches any non-ASCII byte (0x80-0xFF); read_identifier()
		// restricts the accepted identifier codepoints to U+0080-U+FFFF.
		// `"'" !== $next_byte` defers x'..', n'..' and similar special
		// literals to their dedicated branches below; only single quotes
		// form those, regardless of SQL mode.
		if (
			(
				( $byte >= 'a' && $byte <= 'z' )
				|| ( $byte >= 'A' && $byte <= 'Z' )
				|| $byte > "\x7F"
			)
			&& "'" !== $next_byte
		) {
			$started_at = $this->bytes_already_read;
			$type       = $this->read_identifier();
			if (
				self::IDENTIFIER === $type
				// When preceded by a dot, it is always an identifier.
				&& ! ( $started_at > 0 && '.' === $this->sql[ $started_at - 1 ] )
			) {
				// Inline the keyword lookup on the hot identifier path: most
				// identifiers are not keywords, so this avoids two method calls
				// (token-bytes extraction + keyword determination) per token.
				$keyword = self::TOKENS[ strtoupper(
					substr( $this->sql, $started_at, $this->bytes_already_read - $started_at )
				) ] ?? self::IDENTIFIER;
				if ( self::IDENTIFIER !== $keyword ) {
					$type = $this->resolve_keyword_type( $keyword );
				}
			}
		} elseif ( null !== $byte && isset( $single_byte_ops[ $byte ] ) ) {
			// Fast path for single-byte symbols.
			$this->bytes_already_read += 1;
			$type                      = $single_byte_ops[ $byte ];
		} elseif ( "'" === $byte || '"' === $byte || '`' === $byte ) {
			$type = $this->read_quoted_text();
		} elseif ( null !== $byte && strspn( $byte, self::DIGIT_MASK ) > 0 ) {
			$type = $this->read_number();
		} elseif ( '.' === $byte ) {
			if ( null !== $next_byte && strspn( $next_byte, self::DIGIT_MASK ) > 0 ) {
				$type = $this->read_number();
			} else {
				$this->bytes_already_read += 1;
				$type                      = self::DOT_SYMBOL;
			}
		} elseif ( ':' === $byte ) {
			$this->bytes_already_read += 1; // Consume the ':'.
			if ( '=' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '='.
				$type                      = self::ASSIGN_OPERATOR;
			} else {
				$type = self::COLON_SYMBOL;
			}
		} elseif ( '<' === $byte ) {
			$this->bytes_already_read += 1; // Consume the '<'.
			if ( '=' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '='.
				if ( '>' === ( $this->sql[ $this->bytes_already_read ] ?? null ) ) {
					$this->bytes_already_read += 1; // Consume the '>'.
					$type                      = self::NULL_SAFE_EQUAL_OPERATOR;
				} else {
					$type = self::LESS_OR_EQUAL_OPERATOR;
				}
			} elseif ( '>' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '>'.
				$type                      = self::NOT_EQUAL_OPERATOR;
			} elseif ( '<' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '<'.
				$type                      = self::SHIFT_LEFT_OPERATOR;
			} else {
				$type = self::LESS_THAN_OPERATOR;
			}
		} elseif ( '>' === $byte ) {
			$this->bytes_already_read += 1; // Consume the '>'.
			if ( '=' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '='.
				$type                      = self::GREATER_OR_EQUAL_OPERATOR;
			} elseif ( '>' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '>'.
				$type                      = self::SHIFT_RIGHT_OPERATOR;
			} else {
				$type = self::GREATER_THAN_OPERATOR;
			}
		} elseif ( '!' === $byte ) {
			$this->bytes_already_read += 1; // Consume the '!'.
			if ( '=' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '='.
				$type                      = self::NOT_EQUAL_OPERATOR;
			} else {
				$type = self::LOGICAL_NOT_OPERATOR;
			}
		} elseif ( '-' === $byte ) {
			if (
				'-' === $next_byte
				&& $this->bytes_already_read + 2 < $this->sql_length
				&& strspn( $this->sql[ $this->bytes_already_read + 2 ], self::WHITESPACE_MASK ) > 0
			) {
				$type = $this->read_line_comment();
			} elseif ( '>' === $next_byte ) {
				$this->bytes_already_read += 2; // Consume the '->'.
				if ( '>' === ( $this->sql[ $this->bytes_already_read ] ?? null ) ) {
					$this->bytes_already_read += 1; // Consume the '>'.
					if ( $this->mysql_version >= 50713 ) {
						$type = self::JSON_UNQUOTED_SEPARATOR_SYMBOL;
					} else {
						return null; // Invalid input.
					}
				} else {
					if ( $this->mysql_version >= 50708 ) {
						$type = self::JSON_SEPARATOR_SYMBOL;
					} else {
						return null; // Invalid input.
					}
				}
			} else {
				$this->bytes_already_read += 1; // Consume the '-'.
				$type                      = self::MINUS_OPERATOR;
			}
		} elseif ( '*' === $byte ) {
			$this->bytes_already_read += 1;
			if ( '/' === $next_byte && $this->in_mysql_comment ) {
				$this->bytes_already_read += 1; // Consume the '/'.
				$type                      = self::MYSQL_COMMENT_END;
				$this->in_mysql_comment    = false;
			} else {
				$type = self::MULT_OPERATOR;
			}
		} elseif ( '/' === $byte ) {
			if ( '*' === $next_byte ) {
				if ( '!' === ( $this->sql[ $this->bytes_already_read + 2 ] ?? null ) ) {
					$type = $this->read_mysql_comment();
				} else {
					$this->bytes_already_read += 2; // Consume the '/*'.
					$this->read_comment_content();
					$type = self::COMMENT;
				}
			} else {
				$this->bytes_already_read += 1;
				$type                      = self::DIV_OPERATOR;
			}
		} elseif ( '&' === $byte ) {
			$this->bytes_already_read += 1; // Consume the '&'.
			if ( '&' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '&'.
				$type                      = self::LOGICAL_AND_OPERATOR;
			} else {
				$type = self::BITWISE_AND_OPERATOR;
			}
		} elseif ( '|' === $byte ) {
			$this->bytes_already_read += 1; // Consume the '|'.
			if ( '|' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the '|'.
				$type                      = $this->is_sql_mode_active( self::SQL_MODE_PIPES_AS_CONCAT )
					? self::CONCAT_PIPES_SYMBOL
					: self::LOGICAL_OR_OPERATOR;
			} else {
				$type = self::BITWISE_OR_OPERATOR;
			}
		} elseif ( '@' === $byte ) {
			$this->bytes_already_read += 1; // Consume the '@'.

			if ( '@' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the second '@'.
				$type                      = self::AT_AT_SIGN_SYMBOL;
			} else {
				/**
				 * Check whether the '@' marks an unquoted user-defined variable:
				 *   https://dev.mysql.com/doc/refman/8.4/en/user-variables.html
				 *
				 * Rules:
				 *   1. Starts with a '@'.
				 *   2. Allowed following characters are ASCII a-z, A-Z, 0-9, _, ., $.
				 */
				$length = strspn( $this->sql, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.$', $this->bytes_already_read );
				if ( $length > 0 ) {
					$this->bytes_already_read += $length;
					$type                      = self::AT_TEXT_SUFFIX;
				} else {
					$type = self::AT_SIGN_SYMBOL;
				}
			}
		} elseif ( '\\' === $byte ) {
			$this->bytes_already_read += 1; // Consume the '\'.
			if ( 'N' === $next_byte ) {
				$this->bytes_already_read += 1; // Consume the 'N'.
				$type                      = self::NULL2_SYMBOL;
			} else {
				return null; // Invalid input.
			}
		} elseif ( '#' === $byte ) {
			$type = $this->read_line_comment();
		} elseif ( null !== $byte && strspn( $byte, self::WHITESPACE_MASK ) > 0 ) {
			$this->bytes_already_read += strspn( $this->sql, self::WHITESPACE_MASK, $this->bytes_already_read );
			$type                      = self::WHITESPACE;
		} elseif ( ( 'x' === $byte || 'X' === $byte || 'b' === $byte || 'B' === $byte ) && "'" === $next_byte ) {
			$type = $this->read_number();
		} elseif ( ( 'n' === $byte || 'N' === $byte ) && "'" === $next_byte ) {
			$this->bytes_already_read += 1; // n/N
			$type                      = $this->read_quoted_text( "'" );
			if ( self::SINGLE_QUOTED_TEXT === $type ) {
				$type = self::NCHAR_TEXT;
			}
		} elseif ( null === $byte ) {
			$type = self::EOF;
		} else {
			$started_at = $this->bytes_already_read;
			$type       = $this->read_identifier();
			if ( self::IDENTIFIER === $type ) {
				// When preceded by a dot, it is always an identifier.
				if ( $started_at > 0 && '.' === $this->sql[ $started_at - 1 ] ) {
					$type = self::IDENTIFIER;
				} elseif ( '_' === $byte && isset( self::UNDERSCORE_CHARSETS[ strtolower( $this->get_current_token_bytes() ) ] ) ) {
					$type = self::UNDERSCORE_CHARSET;
				} else {
					$type = $this->determine_identifier_or_keyword_type( $this->get_current_token_bytes() );
				}
			}
		}
		return $type;
	}

	private function get_current_token_bytes(): string {
		return substr(
			$this->sql,
			$this->token_starts_at,
			$this->bytes_already_read - $this->token_starts_at
		);
	}

	/**
	 * Read an unquoted identifier.
	 *
	 * This function reads characters that are allowed in an unquoted identifier.
	 * An identifier cannot consist solely of digits, but this function doesn't
	 * ensure that explicitly, as numbers are processed before identifiers in
	 * the tokenization process, recognizing all digit-only sequences as numbers.
	 *
	 * Rules:
	 *   1. Allowed characters are ASCII a-z, A-Z, 0-9, _, $, and Unicode U+0080-U+FFFF.
	 *   2. Unquoted identifiers may begin with a digit but may not consist solely of digits.
	 *
	 *  See:
	 *    https://dev.mysql.com/doc/refman/8.4/en/identifiers.html
	 */
	private function read_identifier(): ?int {
		$started_at = $this->bytes_already_read;
		while ( true ) {
			// First, let's try to parse an ASCII sequence.
			$this->bytes_already_read += strspn(
				$this->sql,
				'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$',
				$this->bytes_already_read
			);

			// Check if the following byte can be part of a multibyte character
			// in the range of U+0080 to U+FFFF before looking at further bytes.
			// If it can't, bail out early to avoid unnecessary UTF-8 decoding.
			// Identifiers are usually ASCII-only, so we can optimize for that.
			$byte_1 = ord(
				$this->sql[ $this->bytes_already_read ] ?? "\0"
			);
			if ( $byte_1 < 0xC2 || $byte_1 > 0xEF ) {
				break;
			}

			// Look for a valid 2-byte UTF-8 symbol. Covers range U+0080 - U+07FF.
			$byte_2 = ord(
				$this->sql[ $this->bytes_already_read + 1 ] ?? "\0"
			);
			if (
				$byte_1 <= 0xDF
				&& $byte_2 >= 0x80 && $byte_2 <= 0xBF
			) {
				$this->bytes_already_read += 2;
				continue;
			}

			// Look for a valid 3-byte UTF-8 symbol in range U+0800 - U+FFFF.
			$byte_3 = ord(
				$this->sql[ $this->bytes_already_read + 2 ] ?? "\0"
			);
			if (
				$byte_1 <= 0xEF
				&& $byte_2 >= 0x80 && $byte_2 <= 0xBF
				&& $byte_3 >= 0x80 && $byte_3 <= 0xBF
				// Exclude surrogate range U+D800 to U+DFFF:
				&& ! ( 0xED === $byte_1 && $byte_2 >= 0xA0 )
				// Exclude overlong encodings:
				&& ! ( 0xE0 === $byte_1 && $byte_2 < 0xA0 )
			) {
				$this->bytes_already_read += 3;
				continue;
			}

			// Not a valid identifier character.
			break;
		}

		// An identifier cannot consist solely of digits, but we don't need to
		// ensure that explicitly, as numbers are processed before identifiers.

		return $this->bytes_already_read - $started_at > 0
			? self::IDENTIFIER
			: null; // Invalid input.
	}

	private function read_number(): ?int {
		// @TODO: Support numeric-only identifier parts after "." (e.g., 1ea10.1).

		$byte       = $this->sql[ $this->bytes_already_read ] ?? null;
		$next_byte  = $this->sql[ $this->bytes_already_read + 1 ] ?? null;
		$third_byte = $this->sql[ $this->bytes_already_read + 2 ] ?? null;

		if (
			// HEX number in the form of 0xN.
			(
				'0' === $byte
				&& 'x' === $next_byte
				&& null !== $third_byte
				&& strspn( $third_byte, self::HEX_DIGIT_MASK ) > 0
			)
			// HEX number in the form of x'N' or X'N'.
			|| ( ( 'x' === $byte || 'X' === $byte ) && "'" === $next_byte )
		) {
			$is_quoted                 = "'" === $next_byte;
			$this->bytes_already_read += 2; // Consume "0x" or "x'".
			$this->bytes_already_read += strspn( $this->sql, self::HEX_DIGIT_MASK, $this->bytes_already_read );
			if ( $is_quoted ) {
				if (
					$this->bytes_already_read >= $this->sql_length
					|| "'" !== $this->sql[ $this->bytes_already_read ]
				) {
					return null; // Invalid input.
				}
				$this->bytes_already_read += 1; // Consume the "'".
			}
			$type = self::HEX_NUMBER;
		} elseif (
			// BIN number in the form of 0bN.
			(
				'0' === $byte
				&& 'b' === $next_byte
				&& ( '0' === $third_byte || '1' === $third_byte )
			)
			// BIN number in the form of b'N' or B'N'.
			|| ( ( 'b' === $byte || 'B' === $byte ) && "'" === $next_byte )
		) {
			$is_quoted                 = "'" === $next_byte;
			$this->bytes_already_read += 2; // Consume "0b" or "b'".
			$this->bytes_already_read += strspn( $this->sql, '01', $this->bytes_already_read );
			if ( $is_quoted ) {
				if (
					$this->bytes_already_read >= $this->sql_length
					|| "'" !== $this->sql[ $this->bytes_already_read ]
				) {
					return null; // Invalid input.
				}
				$this->bytes_already_read += 1; // Consume the "'".
			}
			$type = self::BIN_NUMBER;
		} else {
			// Here, we have a sequence starting with N or .N, where N is a digit.

			// 1. Try integer first.
			$this->bytes_already_read += strspn( $this->sql, self::DIGIT_MASK, $this->bytes_already_read );
			$type                      = self::INT_NUMBER;

			// 2. In case of N. or .N, it's a decimal or float number.
			if ( '.' === ( $this->sql[ $this->bytes_already_read ] ?? null ) ) {
				$this->bytes_already_read += 1;
				$type                      = self::DECIMAL_NUMBER;
				$this->bytes_already_read += strspn( $this->sql, self::DIGIT_MASK, $this->bytes_already_read );
			}

			// 3. When exponent is present, it's a float number.
			$byte         = $this->sql[ $this->bytes_already_read ] ?? null;
			$next_byte    = $this->sql[ $this->bytes_already_read + 1 ] ?? null;
			$has_exponent =
				( 'e' === $byte || 'E' === $byte )
				&& null !== $next_byte
				&& (
					strspn( $next_byte, self::DIGIT_MASK ) > 0
					|| (
						( '+' === $next_byte || '-' === $next_byte )
						&& $this->bytes_already_read + 2 < $this->sql_length
						&& strspn( $this->sql[ $this->bytes_already_read + 2 ], self::DIGIT_MASK ) > 0
					)
				);
			if ( $has_exponent ) {
				$this->bytes_already_read += 1; // Consume the 'e' or 'E'.
				$this->bytes_already_read += 1; // Consume the '+', '-', or digit.
				$this->bytes_already_read += strspn( $this->sql, self::DIGIT_MASK, $this->bytes_already_read );
				$type                      = self::FLOAT_NUMBER;
			}
		}

		/*
		 * In MySQL, when an input matches both a number and an identifier, the
		 * number always wins. However, if the number is followed by a non-numeric
		 * identifier-like character, then it is recognized as an identifier...
		 * Unless it's a float number, which ignores any subsequent input.
		 *
		 * Examples:
		 *  - "1234" (integer) vs. "1234a" (identifier)
		 *  - "0b01" (bin)     vs. "0b012" (identifier)
		 *  - "0xa1" (hex)     vs. "0xa1x" (identifier)
		 *  - "12.3" (decimal) vs. "12.3a" (identifier)
		 *  - "1e10" (float)   vs. "1e10a" (float, followed by identifier)
		 */
		$text                       = $this->get_current_token_bytes();
		$possible_identifier_prefix =
			self::INT_NUMBER === $type
			|| ( '0' === $text[0] && ( 'b' === $text[1] || 'x' === $text[1] ) );

		/*
		 * When we match some subsequent identifier bytes, it's an identifier.
		 * Note that the "$this->read_identifier()" method doesn't check that
		 * the identifier doesn't consist solely of digits. This is an advantage
		 * here, as we can look only at subsequent bytes instead of backtracking
		 * to the beginning of the number (for valid identifiers like 0b019).
		 */
		if ( $possible_identifier_prefix && self::IDENTIFIER === $this->read_identifier() ) {
			$type = self::IDENTIFIER;
		}

		// Determine integer type.
		if ( self::INT_NUMBER === $type ) {
			// Fast path for most integers.
			$bytes = $this->get_current_token_bytes();
			if ( strlen( $bytes ) < 10 ) {
				return self::INT_NUMBER;
			}

			// Remove leading zeros.
			$bytes  = substr( $bytes, strspn( $bytes, '0' ) );
			$length = strlen( $bytes );

			// Determine integer type based on its length and value.
			if ( $length < 10 ) {
				return self::INT_NUMBER;
			} elseif ( 10 === $length ) {
				return strcmp( $bytes, '2147483647' ) > 0
					? self::LONG_NUMBER
					: self::INT_NUMBER;
			} elseif ( $length < 19 ) {
				return self::LONG_NUMBER;
			} elseif ( 19 === $length ) {
				return strcmp( $bytes, '9223372036854775807' ) > 0
					? self::ULONGLONG_NUMBER
					: self::LONG_NUMBER;
			} elseif ( 20 === $length ) {
				return strcmp( $bytes, '18446744073709551615' ) > 0
					? self::DECIMAL_NUMBER
					: self::ULONGLONG_NUMBER;
			} else {
				return self::DECIMAL_NUMBER;
			}
		}
		return $type;
	}

	/**
	 * Quoted literals and identifiers:
	 *   https://dev.mysql.com/doc/refman/8.4/en/string-literals.html
	 *   https://dev.mysql.com/doc/refman/8.4/en/identifiers.html
	 *
	 * Rules:
	 *   1. Quotes can be escaped by doubling them ('', "", ``).
	 *   2. In string literals, backslashes escape the next character,
	 *      unless the NO_BACKSLASH_ESCAPES SQL mode is set.
	 *   3. In identifiers, backslashes are always literal and never escape.
	 */
	private function read_quoted_text(): ?int {
		$quote                     = $this->sql[ $this->bytes_already_read ];
		$this->bytes_already_read += 1; // Consume the quote.

		/*
		 * Determine whether the quote opens an identifier or a string literal.
		 * An identifier is quoted with a backtick or a double quote when the
		 * ANSI_QUOTES SQL mode is active. Otherwise, it is a string literal.
		 *
		 * See: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_ansi_quotes
		 */
		$is_identifier_quote = '`' === $quote
			|| ( '"' === $quote && $this->is_sql_mode_active( self::SQL_MODE_ANSI_QUOTES ) );

		/*
		 * Backslash escapes apply only to string literals, and only when the
		 * NO_BACKSLASH_ESCAPES SQL mode is not set.
		 */
		$backslash_is_escape = ! $is_identifier_quote
			&& ! $this->is_sql_mode_active( self::SQL_MODE_NO_BACKSLASH_ESCAPES );

		// We need to look for the closing quote in a loop, as it can be escaped,
		// in which case the escape sequence is consumed and the loop continues.
		$at = $this->bytes_already_read;
		while ( true ) {
			$quote_at = strpos( $this->sql, $quote, $at );
			if ( false === $quote_at ) {
				return null; // Invalid input.
			}
			$at = $quote_at;

			/*
			 * In string literals, quotes can be escaped with a backslash. When
			 * NO_BACKSLASH_ESCAPES SQL mode is active, the backslash is treated
			 * as a regular character. Identifiers never use backslash escaping.
			 *
			 * The quote is escaped only when the number of preceding backslashes
			 * is odd - "\" is an escape sequence, "\\" is an escaped backslash,
			 * "\\\" is an escaped backslash and an escape sequence, and so on.
			 *
			 * The `($at - $i - 1) >= 0` guard prevents PHP's negative-string-
			 * offset wraparound (PHP 7.1+) when the closing-quote candidate
			 * sits at the very start of the input. The `?? null` covers
			 * positive out-of-range indexes belt-and-suspenders.
			 */
			if ( $backslash_is_escape ) {
				$i = 0;
				while ( ( $at - $i - 1 ) >= 0 && '\\' === ( $this->sql[ $at - $i - 1 ] ?? null ) ) {
					$i += 1;
				}
				if ( 1 === $i % 2 ) {
					$at += 1;
					continue;
				}
			}

			// Check if the quote is doubled.
			if ( ( $this->sql[ $at + 1 ] ?? null ) === $quote ) {
				$at += 2;
				continue;
			}

			break;
		}
		$at += 1;

		$this->bytes_already_read = $at;

		if ( $is_identifier_quote ) {
			return self::BACK_TICK_QUOTED_ID;
		}
		return '"' === $quote ? self::DOUBLE_QUOTED_TEXT : self::SINGLE_QUOTED_TEXT;
	}

	private function read_line_comment(): int {
		$this->bytes_already_read += strcspn( $this->sql, "\r\n", $this->bytes_already_read );
		return self::COMMENT;
	}

	private function read_mysql_comment(): int {
		// @TODO: Consider supporting optimizer hints (/*+ ... */) or document
		//        that they are not supported.

		// MySQL-specific comments in the following forms:
		//   1. /*! ... */       - The content is treated as SQL.
		//   2. /*!80400 ... */  - SQL when the server version is at least 8.4.0.
		//   3. /*!080400 ... */ - The same version in six-digit form (MySQL 8.1+).
		//
		// See: https://dev.mysql.com/doc/refman/8.4/en/comments.html
		$this->bytes_already_read += 3; // Consume the '/*!'.

		// Version has 5 digits (Mmmrr) or 6 digits (MMmmrr + whitespace, MySQL 8.1+).
		$digit_count    = strspn( $this->sql, self::DIGIT_MASK, $this->bytes_already_read, 6 );
		$version_length = $digit_count < 5 ? 0 : 5;
		if (
			6 === $digit_count
			&& $this->mysql_version >= 80100
			&& 1 === strspn( $this->sql, self::WHITESPACE_MASK, $this->bytes_already_read + 6, 1 )
		) {
			$version_length = 6;
		}
		$version = (int) substr( $this->sql, $this->bytes_already_read, $version_length );

		if ( $this->mysql_version < $version ) {
			// Version not satisfied. Treat the content as a regular comment.
			$this->read_comment_content();
			return self::COMMENT;
		} else {
			// Version satisfied or not specified. Treat the content as SQL code.
			$this->bytes_already_read += $version_length; // Skip the version number.
			$this->in_mysql_comment    = true;
			return self::MYSQL_COMMENT_START;
		}
	}

	private function read_comment_content(): void {
		$comment_end = strpos( $this->sql, '*/', $this->bytes_already_read );
		if ( false === $comment_end ) {
			$this->bytes_already_read = $this->sql_length;
		} else {
			$this->bytes_already_read = $comment_end + 2;
		}
	}

	private function determine_identifier_or_keyword_type( string $value ): int {
		$type = self::TOKENS[ strtoupper( $value ) ] ?? self::IDENTIFIER;
		if ( self::IDENTIFIER === $type ) {
			return self::IDENTIFIER;
		}
		return $this->resolve_keyword_type( $type );
	}

	/**
	 * Resolve a keyword token id matched in self::TOKENS, applying version gating,
	 * function-call lookahead, the SQL_MODE_HIGH_NOT_PRECEDENCE rule, and synonyms.
	 *
	 * @param int $type A token id already matched in self::TOKENS (never IDENTIFIER).
	 */
	private function resolve_keyword_type( int $type ): int {

		// Apply MySQL version specifics (positive number: >= <version>, negative number: < <version>).
		if ( isset( self::VERSIONS[ $type ] ) ) {
			$version = self::VERSIONS[ $type ];
			if ( $this->mysql_version < $version || -$version >= $this->mysql_version ) {
				return self::IDENTIFIER;
			}
		}

		// Apply MySQL version ranges manually.
		if (
			self::MAX_STATEMENT_TIME_SYMBOL === $type
			&& ! ( $this->mysql_version >= 50704 && $this->mysql_version < 50708 )
		) {
			return self::IDENTIFIER;
		}

		if (
			self::NONBLOCKING_SYMBOL === $type
			&& ! ( $this->mysql_version >= 50700 && $this->mysql_version < 50706 )
		) {
			return self::IDENTIFIER;
		}

		if (
			self::REMOTE_SYMBOL === $type
			&& ( $this->mysql_version >= 80003 && $this->mysql_version < 80014 )
		) {
			return self::IDENTIFIER;
		}

		// Determine function calls.
		if ( isset( self::FUNCTIONS[ $type ] ) ) {
			// Keep ignored whitespace outside the current token range.
			$peek = $this->bytes_already_read;
			if ( $this->is_sql_mode_active( self::SQL_MODE_IGNORE_SPACE ) ) {
				$peek += strspn( $this->sql, self::WHITESPACE_MASK, $peek );
			}
			if ( '(' !== ( $this->sql[ $peek ] ?? null ) ) {
				return self::IDENTIFIER;
			}
		}

		// With "SQL_MODE_HIGH_NOT_PRECEDENCE" enabled, "NOT" needs to be emitted as a higher priority NOT2 symbol.
		if ( self::NOT_SYMBOL === $type && $this->is_sql_mode_active( self::SQL_MODE_HIGH_NOT_PRECEDENCE ) ) {
			$type = self::NOT2_SYMBOL;
		}

		// Apply synonyms.
		return self::SYNONYMS[ $type ] ?? $type;
	}
}
