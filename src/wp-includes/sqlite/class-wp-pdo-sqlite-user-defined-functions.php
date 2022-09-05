<?php

/**
 * This class defines user defined functions(UDFs) for PDO library.
 *
 * These functions replace those used in the SQL statement with the PHP functions.
 *
 * Usage:
 *
 * <code>
 * new WP_PDO_SQLite_User_Defined_Functions(ref_to_pdo_obj);
 * </code>
 *
 * This automatically enables ref_to_pdo_obj to replace the function in the SQL statement
 * to the ones defined here.
 */
class WP_PDO_SQLite_User_Defined_Functions {

	/**
	 * The class constructor
	 *
	 * Initializes the use defined functions to PDO object with PDO::sqliteCreateFunction().
	 *
	 * @param PDO $pdo
	 */
	public function __construct( $pdo ) {
		if ( ! $pdo ) {
			wp_die( 'Database is not initialized.', 'Database Error' );
		}
		foreach ( $this->functions as $f => $t ) {
			$pdo->sqliteCreateFunction( $f, array( $this, $t ) );
		}
	}

	/**
	 * array to define MySQL function => function defined with PHP.
	 *
	 * Replaced functions must be public.
	 *
	 * @var array
	 */
	private $functions = array(
		'month'          => 'month',
		'year'           => 'year',
		'day'            => 'day',
		'unix_timestamp' => 'unix_timestamp',
		'now'            => 'now',
		'char_length'    => 'char_length',
		'md5'            => 'md5',
		'curdate'        => 'curdate',
		'rand'           => 'rand',
		'substring'      => 'substring',
		'dayofmonth'     => 'day',
		'second'         => 'second',
		'minute'         => 'minute',
		'hour'           => 'hour',
		'date_format'    => 'dateformat',
		'from_unixtime'  => 'from_unixtime',
		'date_add'       => 'date_add',
		'date_sub'       => 'date_sub',
		'adddate'        => 'date_add',
		'subdate'        => 'date_sub',
		'localtime'      => 'now',
		'localtimestamp' => 'now',
		'isnull'         => 'isnull',
		'if'             => '_if',
		'regexpp'        => 'regexp',
		'concat'         => 'concat',
		'field'          => 'field',
		'log'            => 'log',
		'least'          => 'least',
		'greatest'       => 'greatest',
		'get_lock'       => 'get_lock',
		'release_lock'   => 'release_lock',
		'ucase'          => 'ucase',
		'lcase'          => 'lcase',
		'inet_ntoa'      => 'inet_ntoa',
		'inet_aton'      => 'inet_aton',
		'datediff'       => 'datediff',
		'locate'         => 'locate',
		'utc_date'       => 'utc_date',
		'utc_time'       => 'utc_time',
		'utc_timestamp'  => 'utc_timestamp',
		'version'        => 'version',
	);

	/**
	 * Method to extract the month value from the date.
	 *
	 * @param string representing the date formatted as 0000-00-00.
	 *
	 * @return string representing the number of the month between 1 and 12.
	 */
	public function month( $field ) {
		return gmdate( 'n', strtotime( $field ) );
	}

	/**
	 * Method to extract the year value from the date.
	 *
	 * @param string representing the date formatted as 0000-00-00.
	 *
	 * @return string representing the number of the year.
	 */
	public function year( $field ) {
		return gmdate( 'Y', strtotime( $field ) );
	}

	/**
	 * Method to extract the day value from the date.
	 *
	 * @param string representing the date formatted as 0000-00-00.
	 *
	 * @return string representing the number of the day of the month from 1 and 31.
	 */
	public function day( $field ) {
		return gmdate( 'j', strtotime( $field ) );
	}

	/**
	 * Method to return the unix timestamp.
	 *
	 * Used without an argument, it returns PHP time() function (total seconds passed
	 * from '1970-01-01 00:00:00' GMT). Used with the argument, it changes the value
	 * to the timestamp.
	 *
	 * @param string representing the date formatted as '0000-00-00 00:00:00'.
	 *
	 * @return number of unsigned integer
	 */
	public function unix_timestamp( $field = null ) {
		return is_null( $field ) ? time() : strtotime( $field );
	}

	/**
	 * Method to emulate MySQL SECOND() function.
	 *
	 * @param string representing the time formatted as '00:00:00'.
	 *
	 * @return number of unsigned integer
	 */
	public function second( $field ) {
		return intval( gmdate( 's', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL MINUTE() function.
	 *
	 * @param string representing the time formatted as '00:00:00'.
	 *
	 * @return number of unsigned integer
	 */
	public function minute( $field ) {
		return intval( gmdate( 'i', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL HOUR() function.
	 *
	 * @param string representing the time formatted as '00:00:00'.
	 *
	 * @return number
	 */
	public function hour( $time ) {
		list($hours) = explode( ':', $time );

		return intval( $hours );
	}

	/**
	 * Method to emulate MySQL FROM_UNIXTIME() function.
	 *
	 * @param integer of unix timestamp
	 * @param string to indicate the way of formatting(optional)
	 *
	 * @return string formatted as '0000-00-00 00:00:00'.
	 */
	public function from_unixtime( $field, $format = null ) {
		//convert to ISO time
		$date = gmdate( 'Y-m-d H:i:s', $field );

		return is_null( $format ) ? $date : $this->dateformat( $date, $format );
	}

	/**
	 * Method to emulate MySQL NOW() function.
	 *
	 * @return string representing current time formatted as '0000-00-00 00:00:00'.
	 */
	public function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Method to emulate MySQL CURDATE() function.
	 *
	 * @return string representing current time formatted as '0000-00-00'.
	 */
	public function curdate() {
		return gmdate( 'Y-m-d' );
	}

	/**
	 * Method to emulate MySQL CHAR_LENGTH() function.
	 *
	 * @param string
	 *
	 * @return int unsigned integer for the length of the argument.
	 */
	public function char_length( $field ) {
		return strlen( $field );
	}

	/**
	 * Method to emulate MySQL MD5() function.
	 *
	 * @param string
	 *
	 * @return string of the md5 hash value of the argument.
	 */
	public function md5( $field ) {
		return md5( $field );
	}

	/**
	 * Method to emulate MySQL RAND() function.
	 *
	 * SQLite does have a random generator, but it is called RANDOM() and returns random
	 * number between -9223372036854775808 and +9223372036854775807. So we substitute it
	 * with PHP random generator.
	 *
	 * This function uses mt_rand() which is four times faster than rand() and returns
	 * the random number between 0 and 1.
	 *
	 * @return int
	 */
	public function rand() {
		return mt_rand( 0, 1 );
	}

	/**
	 * Method to emulate MySQL SUBSTRING() function.
	 *
	 * This function rewrites the function name to SQLite compatible substr(),
	 * which can manipulate UTF-8 characters.
	 *
	 * @param string $text
	 * @param integer $pos representing the start point.
	 * @param integer $len representing the length of the substring(optional).
	 *
	 * @return string
	 */
	public function substring( $text, $pos, $len = null ) {
		return "substr($text, $pos, $len)";
	}

	/**
	 * Method to emulate MySQL DATEFORMAT() function.
	 *
	 * @param string date formatted as '0000-00-00' or datetime as '0000-00-00 00:00:00'.
	 * @param string $format
	 *
	 * @return string formatted according to $format
	 */
	public function dateformat( $date, $format ) {
		$mysql_php_date_formats = array(
			'%a' => 'D',
			'%b' => 'M',
			'%c' => 'n',
			'%D' => 'jS',
			'%d' => 'd',
			'%e' => 'j',
			'%H' => 'H',
			'%h' => 'h',
			'%I' => 'h',
			'%i' => 'i',
			'%j' => 'z',
			'%k' => 'G',
			'%l' => 'g',
			'%M' => 'F',
			'%m' => 'm',
			'%p' => 'A',
			'%r' => 'h:i:s A',
			'%S' => 's',
			'%s' => 's',
			'%T' => 'H:i:s',
			'%U' => 'W',
			'%u' => 'W',
			'%V' => 'W',
			'%v' => 'W',
			'%W' => 'l',
			'%w' => 'w',
			'%X' => 'Y',
			'%x' => 'o',
			'%Y' => 'Y',
			'%y' => 'y',
		);
		$t                      = strtotime( $date );
		$format                 = strtr( $format, $mysql_php_date_formats );
		$output                 = gmdate( $format, $t );

		return $output;
	}

	/**
	 * Method to emulate MySQL DATE_ADD() function.
	 *
	 * This function adds the time value of $interval expression to $date.
	 * $interval is a single quoted strings rewritten by SQLiteQueryDriver::rewrite_query().
	 * It is calculated in the private function derive_interval().
	 *
	 * @param string $date representing the start date.
	 * @param string $interval representing the expression of the time to add.
	 *
	 * @return string date formatted as '0000-00-00 00:00:00'.
	 * @throws Exception
	 */
	public function date_add( $date, $interval ) {
		$interval = $this->derive_interval( $interval );
		switch ( strtolower( $date ) ) {
			case 'curdate()':
				$date_object = new DateTime( $this->curdate() );
				$date_object->add( new DateInterval( $interval ) );
				return $date_object->format( 'Y-m-d' );

			case 'now()':
				$date_object = new DateTime( $this->now() );
				$date_object->add( new DateInterval( $interval ) );
				return $date_object->format( 'Y-m-d H:i:s' );

			default:
				$date_object = new DateTime( $date );
				$date_object->add( new DateInterval( $interval ) );
				return $date_object->format( 'Y-m-d H:i:s' );
		}
	}

	/**
	 * Method to emulate MySQL DATE_SUB() function.
	 *
	 * This function subtracts the time value of $interval expression from $date.
	 * $interval is a single quoted strings rewritten by SQLiteQueryDriver::rewrite_query().
	 * It is calculated in the private function derive_interval().
	 *
	 * @param string $date representing the start date.
	 * @param string $interval representing the expression of the time to subtract.
	 *
	 * @return string date formatted as '0000-00-00 00:00:00'.
	 * @throws Exception
	 */
	public function date_sub( $date, $interval ) {
		$interval = $this->derive_interval( $interval );
		switch ( strtolower( $date ) ) {
			case 'curdate()':
				$date_object = new DateTime( $this->curdate() );
				$date_object->sub( new DateInterval( $interval ) );
				return $date_object->format( 'Y-m-d' );

			case 'now()':
				$date_object = new DateTime( $this->now() );
				$date_object->sub( new DateInterval( $interval ) );
				return $date_object->format( 'Y-m-d H:i:s' );

			default:
				$date_object = new DateTime( $date );
				$date_object->sub( new DateInterval( $interval ) );
				return $date_object->format( 'Y-m-d H:i:s' );
		}
	}

	/**
	 * Method to calculate the interval time between two dates value.
	 *
	 * @access private
	 *
	 * @param string $interval white space separated expression.
	 *
	 * @return string representing the time to add or substract.
	 */
	private function derive_interval( $interval ) {
		$interval = trim( substr( trim( $interval ), 8 ) );
		$parts    = explode( ' ', $interval );
		foreach ( $parts as $part ) {
			if ( ! empty( $part ) ) {
				$_parts[] = $part;
			}
		}
		$type = strtolower( end( $_parts ) );
		switch ( $type ) {
			case 'second':
				return 'PT' . $_parts[0] . 'S';

			case 'minute':
				return 'PT' . $_parts[0] . 'M';

			case 'hour':
				return 'PT' . $_parts[0] . 'H';

			case 'day':
				return 'P' . $_parts[0] . 'D';

			case 'week':
				return 'P' . $_parts[0] . 'W';

			case 'month':
				return 'P' . $_parts[0] . 'M';

			case 'year':
				return 'P' . $_parts[0] . 'Y';

			case 'minute_second':
				list($minutes, $seconds) = explode( ':', $_parts[0] );
				return 'PT' . $minutes . 'M' . $seconds . 'S';

			case 'hour_second':
				list($hours, $minutes, $seconds) = explode( ':', $_parts[0] );
				return 'PT' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';

			case 'hour_minute':
				list($hours, $minutes) = explode( ':', $_parts[0] );
				return 'PT' . $hours . 'H' . $minutes . 'M';

			case 'day_second':
				$days                            = intval( $_parts[0] );
				list($hours, $minutes, $seconds) = explode( ':', $_parts[1] );
				return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';

			case 'day_minute':
				$days                  = intval( $_parts[0] );
				list($hours, $minutes) = explode( ':', $parts[1] );
				return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M';

			case 'day_hour':
				$days  = intval( $_parts[0] );
				$hours = intval( $_parts[1] );
				return 'P' . $days . 'D' . 'T' . $hours . 'H';

			case 'year_month':
				list($years, $months) = explode( '-', $_parts[0] );
				return 'P' . $years . 'Y' . $months . 'M';
		}
	}

	/**
	 * Method to emulate MySQL DATE() function.
	 *
	 * @param string $date formatted as unix time.
	 *
	 * @return string formatted as '0000-00-00'.
	 */
	public function date( $date ) {
		return gmdate( 'Y-m-d', strtotime( $date ) );
	}

	/**
	 * Method to emulate MySQL ISNULL() function.
	 *
	 * This function returns true if the argument is null, and true if not.
	 *
	 * @param various types $field
	 *
	 * @return boolean
	 */
	public function isnull( $field ) {
		return is_null( $field );
	}

	/**
	 * Method to emulate MySQL IF() function.
	 *
	 * As 'IF' is a reserved word for PHP, function name must be changed.
	 *
	 * @param unknonw $expression the statement to be evaluated as true or false.
	 * @param unknown $true statement or value returned if $expression is true.
	 * @param unknown $false statement or value returned if $expression is false.
	 *
	 * @return unknown
	 */
	public function _if( $expression, $true, $false ) {
		return ( true === $expression ) ? $true : $false;
	}

	/**
	 * Method to emulate MySQL REGEXP() function.
	 *
	 * @param string $field haystack
	 * @param string $pattern : regular expression to match.
	 *
	 * @return integer 1 if matched, 0 if not matched.
	 */
	public function regexp( $field, $pattern ) {
		$pattern = str_replace( '/', '\/', $pattern );
		$pattern = '/' . $pattern . '/i';

		return preg_match( $pattern, $field );
	}

	/**
	 * Method to emulate MySQL CONCAT() function.
	 *
	 * SQLite does have CONCAT() function, but it has a different syntax from MySQL.
	 * So this function must be manipulated here.
	 *
	 * @param string
	 *
	 * @return NULL if the argument is null | string conatenated if the argument is given.
	 */
	public function concat() {
		$return_value = '';
		$args_num     = func_num_args();
		$args_list    = func_get_args();
		for ( $i = 0; $i < $args_num; $i++ ) {
			if ( is_null( $args_list[ $i ] ) ) {
				return null;
			}
			$return_value .= $args_list[ $i ];
		}

		return $return_value;
	}

	/**
	 * Method to emulate MySQL FIELD() function.
	 *
	 * This function gets the list argument and compares the first item to all the others.
	 * If the same value is found, it returns the position of that value. If not, it
	 * returns 0.
	 *
	 * @param int...|float... variable number of string, integer or double
	 *
	 * @return int unsigned integer
	 */
	public function field() {
		global $wpdb;
		$num_args = func_num_args();
		if ( $num_args < 2 or is_null( func_get_arg( 0 ) ) ) {
			return 0;
		}
		$arg_list      = func_get_args();
		$search_string = array_shift( $arg_list );
		$str_to_check  = substr( $search_string, 0, strpos( $search_string, '.' ) );
		$str_to_check  = str_replace( $wpdb->prefix, '', $str_to_check );
		if ( $str_to_check && in_array( trim( $str_to_check ), $wpdb->tables, true ) ) {
			return 0;
		}
		for ( $i = 0; $i < $num_args - 1; $i++ ) {
			if ( strtolower( $arg_list[ $i ] ) === $search_string ) {
				return $i + 1;
			}
		}

		return 0;
	}

	/**
	 * Method to emulate MySQL LOG() function.
	 *
	 * Used with one argument, it returns the natural logarithm of X.
	 * <code>
	 * LOG(X)
	 * </code>
	 * Used with two arguments, it returns the natural logarithm of X base B.
	 * <code>
	 * LOG(B, X)
	 * </code>
	 * In this case, it returns the value of log(X) / log(B).
	 *
	 * Used without an argument, it returns false. This returned value will be
	 * rewritten to 0, because SQLite doesn't understand true/false value.
	 *
	 * @param integer representing the base of the logarithm, which is optional.
	 * @param double value to turn into logarithm.
	 *
	 * @return double | NULL
	 */
	public function log() {
		$num_args = func_num_args();
		if ( 1 === $num_args ) {
			$arg1 = func_get_arg( 0 );

			return log( $arg1 );
		}
		if ( 2 === $num_args ) {
			$arg1 = func_get_arg( 0 );
			$arg2 = func_get_arg( 1 );

			return log( $arg1 ) / log( $arg2 );
		}
		return null;
	}

	/**
	 * Method to emulate MySQL LEAST() function.
	 *
	 * This function rewrites the function name to SQLite compatible function name.
	 *
	 * @return mixed
	 */
	public function least() {
		$arg_list = func_get_args();

		return "min($arg_list)";
	}

	/**
	 * Method to emulate MySQL GREATEST() function.
	 *
	 * This function rewrites the function name to SQLite compatible function name.
	 *
	 * @return mixed
	 */
	public function greatest() {
		$arg_list = func_get_args();

		return "max($arg_list)";
	}

	/**
	 * Method to dummy out MySQL GET_LOCK() function.
	 *
	 * This function is meaningless in SQLite, so we do nothing.
	 *
	 * @param string $name
	 * @param integer $timeout
	 *
	 * @return string
	 */
	public function get_lock( $name, $timeout ) {
		return '1=1';
	}

	/**
	 * Method to dummy out MySQL RELEASE_LOCK() function.
	 *
	 * This function is meaningless in SQLite, so we do nothing.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function release_lock( $name ) {
		return '1=1';
	}

	/**
	 * Method to emulate MySQL UCASE() function.
	 *
	 * This is MySQL alias for upper() function. This function rewrites it
	 * to SQLite compatible name upper().
	 *
	 * @param string
	 *
	 * @return string SQLite compatible function name.
	 */
	public function ucase( $string ) {
		return "upper($string)";
	}

	/**
	 * Method to emulate MySQL LCASE() function.
	 *
	 *
	 * This is MySQL alias for lower() function. This function rewrites it
	 * to SQLite compatible name lower().
	 *
	 * @param string
	 *
	 * @return string SQLite compatible function name.
	 */
	public function lcase( $string ) {
		return "lower($string)";
	}

	/**
	 * Method to emulate MySQL INET_NTOA() function.
	 *
	 * This function gets 4 or 8 bytes integer and turn it into the network address.
	 *
	 * @param unsigned long integer
	 *
	 * @return string
	 */
	public function inet_ntoa( $num ) {
		return long2ip( $num );
	}

	/**
	 * Method to emulate MySQL INET_ATON() function.
	 *
	 * This function gets the network address and turns it into integer.
	 *
	 * @param string
	 *
	 * @return int long integer
	 */
	public function inet_aton( $addr ) {
		return absint( ip2long( $addr ) );
	}

	/**
	 * Method to emulate MySQL DATEDIFF() function.
	 *
	 * This function compares two dates value and returns the difference.
	 *
	 * @param string start
	 * @param string end
	 *
	 * @return string
	 */
	public function datediff( $start, $end ) {
		$start_date = new DateTime( $start );
		$end_date   = new DateTime( $end );
		$interval   = $end_date->diff( $start_date, false );

		return $interval->format( '%r%a' );
	}

	/**
	 * Method to emulate MySQL LOCATE() function.
	 *
	 * This function returns the position if $substr is found in $str. If not,
	 * it returns 0. If mbstring extension is loaded, mb_strpos() function is
	 * used.
	 *
	 * @param string needle
	 * @param string haystack
	 * @param integer position
	 *
	 * @return integer
	 */
	public function locate( $substr, $str, $pos = 0 ) {
		if ( ! extension_loaded( 'mbstring' ) ) {
			$val = strpos( $str, $substr, $pos );
			if ( false !== $val ) {
				return $val + 1;
			}
			return 0;
		}
		$val = mb_strpos( $str, $substr, $pos );
		if ( false !== $val ) {
			return $val + 1;
		}
		return 0;
	}

	/**
	 * Method to return GMT date in the string format.
	 *
	 * @param none
	 *
	 * @return string formatted GMT date 'dddd-mm-dd'
	 */
	public function utc_date() {
		return gmdate( 'Y-m-d', time() );
	}

	/**
	 * Method to return GMT time in the string format.
	 *
	 * @param none
	 *
	 * @return string formatted GMT time '00:00:00'
	 */
	public function utc_time() {
		return gmdate( 'H:i:s', time() );
	}

	/**
	 * Method to return GMT time stamp in the string format.
	 *
	 * @param none
	 *
	 * @return string formatted GMT timestamp 'yyyy-mm-dd 00:00:00'
	 */
	public function utc_timestamp() {
		return gmdate( 'Y-m-d H:i:s', time() );
	}

	/**
	 * Method to return MySQL version.
	 *
	 * This function only returns the current newest version number of MySQL,
	 * because it is meaningless for SQLite database.
	 *
	 * @param none
	 *
	 * @return string representing the version number: major_version.minor_version
	 */
	public function version() {
		//global $required_mysql_version;
		//return $required_mysql_version;
		return '5.5';
	}
}
