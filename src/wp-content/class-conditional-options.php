<?php

namespace xwp\conditional_options;

class conditional_options_cache {
	/**
	 * @var string
	 */
	private static $context;
	/**
	 * @var string
	 */
	private static $table_name;
	/*
	 * hold the current options
	 * @var array
	 */
	private static $options;

	/*
	 * do we have a cache miss?
	 * @var bool
	 */
	private static $has_miss = false;

	private static $running = false;


	public function __construct() {
		self::set_context();

		add_action( 'shutdown', array( __CLASS__, 'save_options_cache' ) );
	}


	/**
	 * @return void
	 */
	public static function set_context() {
		global $wpdb;

		if ( is_null( self::$context ) ) {
			self::$table_name = $wpdb->prefix . 'conditional_options_preload';
			self::maybe_create_table(); // TODO: move this a setup function
			self::$context = self::get_context();
			self::$options = self::get_cache();
		}
	}

	public static function conditional_options_preload() {

		return self::$options;
	}

	/**
	 * @param $option_name
	 * @param $default
	 *
	 * @return false|mixed|void
	 */
	public static function get_option( $pre, $option_name, $default = false ) {
		// shortcut function we are calling are self

		if ( self::$running ) {

			return $pre;
		}

		if ( array_key_exists( $option_name, self::$options ) ) {
			self::$running = false;

			return maybe_unserialize( self::$options[ $option_name ] );
		}

		self::$running = true;

		$option_value = get_option( $option_name, $default );
		if ( false !== $option_value ) {
			self::$options[ $option_name ] = $option_value;
			self::$has_miss                = true;
		}
		self::$running = false;

		return $option_value;

	}


	private static function get_cache() {
		global $wpdb;
		$keys = $wpdb->get_results(
			$wpdb->prepare(
				'select `keys` from ' . self::$table_name . ' where `url_hash` = %s ',
				self::$context
			),
			ARRAY_A
		);

		$key_array = explode( ',', $keys[0]['keys'] );

		$commaDelimitedPlaceholders = implode( ',', array_fill( 0, count( $key_array ), '%s' ) );
		$options                    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `option_name`, `option_value` FROM $wpdb->options WHERE `option_name` IN ( $commaDelimitedPlaceholders )",
				$key_array
			),
			ARRAY_A
		);

		if ( $options ) {
			foreach ( $options as $option ) {
				self::$options[ $option['option_name'] ] = $option['option_value'];
			}

			return self::$options;
		}

		return false;
	}

	/**
	 * @return void
	 */
	public static function save_options_cache() {
		global $wpdb;

		if ( self::$has_miss ) {
			//	self::maybe_create_table();

			$key_string = implode( ',', array_keys( self::$options ) );

			// TODO: add code to reset cache
			//          $db = $wpdb->query(
			//              $wpdb->prepare(
			//                  'DELETE FROM ' . self::$table_name . ' WHERE `url_hash` = %s',
			//                  self::$context
			//              )
			//          );

			$db = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ' . self::$table_name . ' ( `url_hash`, `keys` )  VALUES( %s, %s ) ON DUPLICATE KEY UPDATE `keys` = %s',
					self::$context,
					$key_string,
					$key_string
				)
			);
		}
	 self::stats();
	}

	public static function stats() {
		global $wpdb;

		$keys_count       = count( self::$options );
		$alloptions = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE autoload = 'yes'" );

		$options_keys = array();
		foreach ( $alloptions as $value ) {
			$options_keys[] = $value->option_name;
		}

		$diff_count = count( array_diff( array_keys( self::$options ), $options_keys ) );

		$options_count    = count( $alloptions );

		echo "<center>$keys_count options loaded/used instead of an all options count of $options_count</center>";
		echo "<center>Pluss the $keys_count included $diff_count options that were not set to be autoload</center>";
	}


	/**
	 * @return bool
	 */
	public static function running() {

		return self::$running;
	}
	/**
	 * lets work out the context
	 * TODO: expand as needed
	 *
	 * @return string
	 */
	public static function get_context() {
		global $wp_query;
		$queryied_name = '';
		if ( null !== $wp_query ) {
			$queryied_object = $wp_query->get_queried_object();
			if ( null !== $queryied_object ) {
				$queryied_name = get_class( $queryied_object );
			}
		} else {
			$queryied_name = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		}

		switch ( $queryied_name ) {
			case 'WP_Term':
				$context = ' term_' . $queryied_object->term_id;
				break;
			case 'WP_Post_Type':
				$context = ' post_type_' . $queryied_object->name;
				break;
			case 'WP_Post':
				$context = ' post_' . $queryied_object->ID;
				break;
			case 'WP_User':
				$context = ' user_' . $queryied_object->ID;
				break;
			case 'wp_admin':
				$context = 'wp_admin';
				break;
			default:
				if ( is_admin() ) {
					$context = ' wp_admin';
				} else {
					$context = ' wp_front';
				}
				break;
		}

		return md5( $context );
	}
	private static function maybe_create_table() {
		global $wpdb;

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}
		//  $wpdb->query( 'DROP table '. self::$table_name );
		$sql = 'CREATE TABLE ' . self::$table_name . " (
                    `url_hash` varchar(255) NOT NULL,
                    `keys`  text NOT NULL,
                    PRIMARY KEY (`url_hash`)
                ) ENGINE=InnoDB $charset_collate;";

		require_once ABSPATH . 'wp-admin/install-helper.php';
		\maybe_create_table( self::$table_name, $sql );
	}

}
