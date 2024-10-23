<?php

// Misc help functions and utilities.

/**
 * Returns a string of the required length containing random characters. Note that
 * the maximum possible string length is 32.
 *
 * @param int $length Optional. The required length. Default 32.
 * @return string The string.
 */
function rand_str( $length = 32 ) {
	return substr( md5( uniqid( rand() ) ), 0, $length );
}

/**
 * Returns a string of the required length containing random characters.
 *
 * @param int $length The required length.
 * @return string The string.
 */
function rand_long_str( $length ) {
	$chars  = 'abcdefghijklmnopqrstuvwxyz';
	$string = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$rand    = rand( 0, strlen( $chars ) - 1 );
		$string .= substr( $chars, $rand, 1 );
	}

	return $string;
}

/**
 * Strips leading and trailing whitespace from each line in the string.
 *
 * @param string $txt The text.
 * @return string Text with line-leading and line-trailing whitespace stripped.
 */
function strip_ws( $txt ) {
	$lines  = explode( "\n", $txt );
	$result = array();
	foreach ( $lines as $line ) {
		if ( trim( $line ) ) {
			$result[] = trim( $line );
		}
	}

	return trim( implode( "\n", $result ) );
}

/**
 * Helper class for testing code that involves actions and filters.
 *
 * Typical use:
 *
 *     $ma = new MockAction();
 *     add_action( 'foo', array( &$ma, 'action' ) );
 *
 * @since UT (3.7.0)
 */
class MockAction {
	public $events;
	public $debug;

	/**
	 * PHP5 constructor.
	 *
	 * @since UT (3.7.0)
	 */
	public function __construct( $debug = 0 ) {
		$this->reset();
		$this->debug = $debug;
	}

	/**
	 * @since UT (3.7.0)
	 */
	public function reset() {
		$this->events = array();
	}

	/**
	 * @since UT (3.7.0)
	 */
	public function current_filter() {
		global $wp_actions;

		if ( is_callable( 'current_filter' ) ) {
			return current_filter();
		}

		return end( $wp_actions );
	}

	/**
	 * @since UT (3.7.0)
	 */
	public function action( $arg ) {
		$current_filter = $this->current_filter();

		if ( $this->debug ) {
			dmp( __FUNCTION__, $current_filter );
		}

		$this->events[] = array(
			'action'    => __FUNCTION__,
			'hook_name' => $current_filter,
			'tag'       => $current_filter, // Back compat.
			'args'      => func_get_args(),
		);

		return $arg;
	}

	/**
	 * @since UT (3.7.0)
	 */
	public function action2( $arg ) {
		$current_filter = $this->current_filter();

		if ( $this->debug ) {
			dmp( __FUNCTION__, $current_filter );
		}

		$this->events[] = array(
			'action'    => __FUNCTION__,
			'hook_name' => $current_filter,
			'tag'       => $current_filter, // Back compat.
			'args'      => func_get_args(),
		);

		return $arg;
	}

	/**
	 * @since 6.6.0
	 */
	public function action3( array $arg ) {
		$current_filter = $this->current_filter();

		if ( $this->debug ) {
			dmp( __FUNCTION__, $current_filter );
		}

		$this->events[] = array(
			'action'    => __FUNCTION__,
			'hook_name' => $current_filter,
			'tag'       => $current_filter, // Back compat.
			'args'      => func_get_args(),
		);

		return $arg;
	}

	/**
	 * @since UT (3.7.0)
	 */
	public function filter( $arg ) {
		$current_filter = $this->current_filter();

		if ( $this->debug ) {
			dmp( __FUNCTION__, $current_filter );
		}

		$this->events[] = array(
			'filter'    => __FUNCTION__,
			'hook_name' => $current_filter,
			'tag'       => $current_filter, // Back compat.
			'args'      => func_get_args(),
		);

		return $arg;
	}

	/**
	 * @since UT (3.7.0)
	 */
	public function filter2( $arg ) {
		$current_filter = $this->current_filter();

		if ( $this->debug ) {
			dmp( __FUNCTION__, $current_filter );
		}

		$this->events[] = array(
			'filter'    => __FUNCTION__,
			'hook_name' => $current_filter,
			'tag'       => $current_filter, // Back compat.
			'args'      => func_get_args(),
		);

		return $arg;
	}

	/**
	 * @since UT (3.7.0)
	 */
	public function filter_append( $arg ) {
		$current_filter = $this->current_filter();

		if ( $this->debug ) {
			dmp( __FUNCTION__, $current_filter );
		}

		$this->events[] = array(
			'filter'    => __FUNCTION__,
			'hook_name' => $current_filter,
			'tag'       => $current_filter, // Back compat.
			'args'      => func_get_args(),
		);

		return $arg . '_append';
	}

	/**
	 * Does not return the result, so it's safe to use with the 'all' filter.
	 *
	 * @since UT (3.7.0)
	 */
	public function filterall( $hook_name, ...$args ) {
		$current_filter = $this->current_filter();

		if ( $this->debug ) {
			dmp( __FUNCTION__, $current_filter );
		}

		$this->events[] = array(
			'filter'    => __FUNCTION__,
			'hook_name' => $hook_name,
			'tag'       => $hook_name, // Back compat.
			'args'      => $args,
		);
	}

	/**
	 * Returns a list of all the actions, hook names and args.
	 *
	 * @since UT (3.7.0)
	 */
	public function get_events() {
		return $this->events;
	}

	/**
	 * Returns a count of the number of times the action was called since the last reset.
	 *
	 * @since UT (3.7.0)
	 */
	public function get_call_count( $hook_name = '' ) {
		if ( $hook_name ) {
			$count = 0;

			foreach ( $this->events as $e ) {
				if ( $e['action'] === $hook_name ) {
					++$count;
				}
			}

			return $count;
		}

		return count( $this->events );
	}

	/**
	 * Returns an array of the hook names that triggered calls to this action.
	 *
	 * @since 6.1.0
	 */
	public function get_hook_names() {
		$out = array();

		foreach ( $this->events as $e ) {
			$out[] = $e['hook_name'];
		}

		return $out;
	}

	/**
	 * Returns an array of the hook names that triggered calls to this action.
	 *
	 * @since UT (3.7.0)
	 * @since 6.1.0 Turned into an alias for ::get_hook_names().
	 */
	public function get_tags() {
		return $this->get_hook_names();
	}

	/**
	 * Returns an array of args passed in calls to this action.
	 *
	 * @since UT (3.7.0)
	 */
	public function get_args() {
		$out = array();

		foreach ( $this->events as $e ) {
			$out[] = $e['args'];
		}

		return $out;
	}
}

// Convert valid XML to an array tree structure.
// Kinda lame, but it works with a default PHP 4 installation.
class TestXMLParser {
	public $xml;
	public $data = array();

	/**
	 * PHP5 constructor.
	 */
	public function __construct( $in ) {
		$this->xml = xml_parser_create();
		xml_parser_set_option( $this->xml, XML_OPTION_CASE_FOLDING, 0 );
		xml_set_element_handler( $this->xml, array( $this, 'start_handler' ), array( $this, 'end_handler' ) );
		xml_set_character_data_handler( $this->xml, array( $this, 'data_handler' ) );
		$this->parse( $in );
	}

	public function parse( $in ) {
		$parse = xml_parse( $this->xml, $in, true );
		if ( ! $parse ) {
			throw new Exception(
				sprintf(
					'XML error: %s at line %d',
					xml_error_string( xml_get_error_code( $this->xml ) ),
					xml_get_current_line_number( $this->xml )
				)
			);
			xml_parser_free( $this->xml );
		}
		return true;
	}

	public function start_handler( $parser, $name, $attributes ) {
		$data['name'] = $name;
		if ( $attributes ) {
			$data['attributes'] = $attributes; }
		$this->data[] = $data;
	}

	public function data_handler( $parser, $data ) {
		$index = count( $this->data ) - 1;

		if ( ! isset( $this->data[ $index ]['content'] ) ) {
			$this->data[ $index ]['content'] = '';
		}
		$this->data[ $index ]['content'] .= $data;
	}

	public function end_handler( $parser, $name ) {
		if ( count( $this->data ) > 1 ) {
			$data                            = array_pop( $this->data );
			$index                           = count( $this->data ) - 1;
			$this->data[ $index ]['child'][] = $data;
		}
	}
}

/**
 * Converts an XML string into an array tree structure.
 *
 * The output of this function can be passed to xml_find() to find nodes by their path.
 *
 * @param string $in The XML string.
 * @return array XML as an array.
 */
function xml_to_array( $in ) {
	$p = new TestXMLParser( $in );
	return $p->data;
}

/**
 * Finds XML nodes by a given "path".
 *
 * Example usage:
 *
 *     $tree = xml_to_array( $rss );
 *     $items = xml_find( $tree, 'rss', 'channel', 'item' );
 *
 * @param array     $tree     An array tree structure of XML, typically from xml_to_array().
 * @param string ...$elements Names of XML nodes to create a "path" to find within the XML.
 * @return array Array of matching XML node information.
 */
function xml_find( $tree, ...$elements ) {
	$n   = count( $elements );
	$out = array();

	if ( $n < 1 ) {
		return $out;
	}

	for ( $i = 0; $i < count( $tree ); $i++ ) {
		#       echo "checking '{$tree[$i][name]}' == '{$elements[0]}'\n";
		#       var_dump( $tree[$i]['name'], $elements[0] );
		if ( $tree[ $i ]['name'] === $elements[0] ) {
			#           echo "n == {$n}\n";
			if ( 1 === $n ) {
				$out[] = $tree[ $i ];
			} else {
				$subtree =& $tree[ $i ]['child'];
				$out     = array_merge( $out, xml_find( $subtree, ...array_slice( $elements, 1 ) ) );
			}
		}
	}

	return $out;
}

function xml_join_atts( $atts ) {
	$a = array();
	foreach ( $atts as $k => $v ) {
		$a[] = $k . '="' . $v . '"';
	}
	return implode( ' ', $a );
}

function xml_array_dumbdown( &$data ) {
	$out = array();

	foreach ( array_keys( $data ) as $i ) {
		$name = $data[ $i ]['name'];
		if ( ! empty( $data[ $i ]['attributes'] ) ) {
			$name .= ' ' . xml_join_atts( $data[ $i ]['attributes'] );
		}

		if ( ! empty( $data[ $i ]['child'] ) ) {
			$out[ $name ][] = xml_array_dumbdown( $data[ $i ]['child'] );
		} else {
			$out[ $name ] = $data[ $i ]['content'];
		}
	}

	return $out;
}

function dmp( ...$args ) {
	foreach ( $args as $thing ) {
		echo ( is_scalar( $thing ) ? (string) $thing : var_export( $thing, true ) ), "\n";
	}
}

function dmp_filter( $a ) {
	dmp( $a );
	return $a;
}

function get_echo( $callback, $args = array() ) {
	ob_start();
	call_user_func_array( $callback, $args );
	return ob_get_clean();
}

// Recursively generate some quick assertEquals() tests based on an array.
function gen_tests_array( $name, $expected_data ) {
	$out = array();

	foreach ( $expected_data as $k => $v ) {
		if ( is_numeric( $k ) ) {
			$index = (string) $k;
		} else {
			$index = "'" . addcslashes( $k, "\n\r\t'\\" ) . "'";
		}

		if ( is_string( $v ) ) {
			$out[] = '$this->assertEquals( \'' . addcslashes( $v, "\n\r\t'\\" ) . '\', $' . $name . '[' . $index . '] );';
		} elseif ( is_numeric( $v ) ) {
			$out[] = '$this->assertEquals( ' . $v . ', $' . $name . '[' . $index . '] );';
		} elseif ( is_array( $v ) ) {
			$out[] = gen_tests_array( "{$name}[{$index}]", $v );
		}
	}

	return implode( "\n", $out ) . "\n";
}

/**
 * Use to create objects by yourself.
 */
class MockClass extends stdClass {}

/**
 * Drops all tables from the WordPress database.
 */
function drop_tables() {
	global $wpdb;
	$tables = $wpdb->get_col( 'SHOW TABLES;' );
	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}

function print_backtrace() {
	$bt = debug_backtrace();
	echo "Backtrace:\n";
	$i = 0;
	foreach ( $bt as $stack ) {
		echo ++$i, ': ';
		if ( isset( $stack['class'] ) ) {
			echo $stack['class'] . '::';
		}
		if ( isset( $stack['function'] ) ) {
			echo $stack['function'] . '() ';
		}
		echo "line {$stack[line]} in {$stack[file]}\n";
	}
	echo "\n";
}

// Mask out any input fields matching the given name.
function mask_input_value( $in, $name = '_wpnonce' ) {
	return preg_replace( '@<input([^>]*) name="' . preg_quote( $name ) . '"([^>]*) value="[^>]*" />@', '<input$1 name="' . preg_quote( $name ) . '"$2 value="***" />', $in );
}

/**
 * Removes the post type and its taxonomy associations.
 */
function _unregister_post_type( $cpt_name ) {
	unregister_post_type( $cpt_name );
}

function _unregister_taxonomy( $taxonomy_name ) {
	unregister_taxonomy( $taxonomy_name );
}

/**
 * Unregister a post status.
 *
 * @since 4.2.0
 *
 * @param string $status
 */
function _unregister_post_status( $status ) {
	unset( $GLOBALS['wp_post_statuses'][ $status ] );
}

function _cleanup_query_vars() {
	// Clean out globals to stop them polluting wp and wp_query.
	foreach ( $GLOBALS['wp']->public_query_vars as $v ) {
		unset( $GLOBALS[ $v ] );
	}

	foreach ( $GLOBALS['wp']->private_query_vars as $v ) {
		unset( $GLOBALS[ $v ] );
	}

	foreach ( get_taxonomies( array(), 'objects' ) as $t ) {
		if ( $t->publicly_queryable && ! empty( $t->query_var ) ) {
			$GLOBALS['wp']->add_query_var( $t->query_var );
		}
	}

	foreach ( get_post_types( array(), 'objects' ) as $t ) {
		if ( is_post_type_viewable( $t ) && ! empty( $t->query_var ) ) {
			$GLOBALS['wp']->add_query_var( $t->query_var );
		}
	}
}

function _clean_term_filters() {
	remove_filter( 'get_terms', array( 'Featured_Content', 'hide_featured_term' ), 10, 2 );
	remove_filter( 'get_the_terms', array( 'Featured_Content', 'hide_the_featured_term' ), 10, 3 );
}

/**
 * Special class for exposing protected wpdb methods we need to access
 */
class WpdbExposedMethodsForTesting extends wpdb {
	public function __construct() {
		global $wpdb;
		$this->dbh         = $wpdb->dbh;
		$this->is_mysql    = $wpdb->is_mysql;
		$this->ready       = true;
		$this->field_types = $wpdb->field_types;
		$this->charset     = $wpdb->charset;

		$this->dbuser     = $wpdb->dbuser;
		$this->dbpassword = $wpdb->dbpassword;
		$this->dbname     = $wpdb->dbname;
		$this->dbhost     = $wpdb->dbhost;
	}

	public function __call( $name, $arguments ) {
		return call_user_func_array( array( $this, $name ), $arguments );
	}
}

/**
 * Determine approximate backtrack count when running PCRE.
 *
 * @return int The backtrack count.
 */
function benchmark_pcre_backtracking( $pattern, $subject, $strategy ) {
	$saved_config = ini_get( 'pcre.backtrack_limit' );

	// Attempt to prevent PHP crashes. Adjust lower when needed.
	$limit = 1000000;

	// Start with small numbers, so if a crash is encountered at higher numbers we can still debug the problem.
	for ( $i = 4; $i <= $limit; $i *= 2 ) {

		ini_set( 'pcre.backtrack_limit', $i );

		switch ( $strategy ) {
			case 'split':
				preg_split( $pattern, $subject );
				break;
			case 'match':
				preg_match( $pattern, $subject );
				break;
			case 'match_all':
				$matches = array();
				preg_match_all( $pattern, $subject, $matches );
				break;
		}

		ini_set( 'pcre.backtrack_limit', $saved_config );

		switch ( preg_last_error() ) {
			case PREG_NO_ERROR:
				return $i;
			case PREG_BACKTRACK_LIMIT_ERROR:
				break;
			case PREG_RECURSION_LIMIT_ERROR:
				trigger_error( 'PCRE recursion limit encountered before backtrack limit.' );
				return;
			case PREG_BAD_UTF8_ERROR:
				trigger_error( 'UTF-8 error during PCRE benchmark.' );
				return;
			case PREG_INTERNAL_ERROR:
				trigger_error( 'Internal error during PCRE benchmark.' );
				return;
			default:
				trigger_error( 'Unexpected error during PCRE benchmark.' );
				return;
		}
	}

	return $i;
}

function test_rest_expand_compact_links( $links ) {
	if ( empty( $links['curies'] ) ) {
		return $links;
	}
	foreach ( $links as $rel => $links_array ) {
		if ( ! strpos( $rel, ':' ) ) {
			continue;
		}

		$name = explode( ':', $rel );

		$curie              = wp_list_filter( $links['curies'], array( 'name' => $name[0] ) );
		$full_uri           = str_replace( '{rel}', $name[1], $curie[0]['href'] );
		$links[ $full_uri ] = $links_array;
		unset( $links[ $rel ] );
	}
	return $links;
}
