<?php
/**
 * Custom functions for the SQLite implementation.
 *
 * @package wp-sqlite-integration
 * @since 1.0.0
 */

/**
 * This class defines user defined functions(UDFs) for PDO library.
 *
 * These functions replace those used in the SQL statement with the PHP functions.
 *
 * Usage:
 *
 * <code>
 * new WP_SQLite_PDO_User_Defined_Functions(ref_to_pdo_obj);
 * </code>
 *
 * This automatically enables ref_to_pdo_obj to replace the function in the SQL statement
 * to the ones defined here.
 */
class WP_SQLite_PDO_User_Defined_Functions {

	/**
	 * The class constructor
	 *
	 * Initializes the use defined functions to PDO object with PDO::sqliteCreateFunction().
	 *
	 * @param PDO $pdo The PDO object.
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
	 * Array to define MySQL function => function defined with PHP.
	 *
	 * Replaced functions must be public.
	 *
	 * @var array
	 */
	private $functions = array(
		'month'          => 'month',
		'monthnum'       => 'month',
		'year'           => 'year',
		'day'            => 'day',
		'hour'           => 'hour',
		'minute'         => 'minute',
		'second'         => 'second',
		'week'           => 'week',
		'weekday'        => 'weekday',
		'dayofweek'      => 'dayofweek',
		'dayofmonth'     => 'dayofmonth',
		'unix_timestamp' => 'unix_timestamp',
		'now'            => 'now',
		'md5'            => 'md5',
		'curdate'        => 'curdate',
		'rand'           => 'rand',
		'from_unixtime'  => 'from_unixtime',
		'localtime'      => 'now',
		'localtimestamp' => 'now',
		'isnull'         => 'isnull',
		'if'             => '_if',
		'regexp'         => 'regexp',
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
	 * Method to return the unix timestamp.
	 *
	 * Used without an argument, it returns PHP time() function (total seconds passed
	 * from '1970-01-01 00:00:00' GMT). Used with the argument, it changes the value
	 * to the timestamp.
	 *
	 * @param string $field Representing the date formatted as '0000-00-00 00:00:00'.
	 *
	 * @return number of unsigned integer
	 */
	public function unix_timestamp( $field = null ) {
		return is_null( $field ) ? time() : strtotime( $field );
	}

	/**
	 * Method to emulate MySQL FROM_UNIXTIME() function.
	 *
	 * @param int    $field The unix timestamp.
	 * @param string $format Indicate the way of formatting(optional).
	 *
	 * @return string
	 */
	public function from_unixtime( $field, $format = null ) {
		// Convert to ISO time.
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
	 * Method to emulate MySQL MD5() function.
	 *
	 * @param string $field The string to be hashed.
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
	 * Method to emulate MySQL DATEFORMAT() function.
	 *
	 * @param string $date   Formatted as '0000-00-00' or datetime as '0000-00-00 00:00:00'.
	 * @param string $format The string format.
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

		$time   = strtotime( $date );
		$format = strtr( $format, $mysql_php_date_formats );

		return gmdate( $format, $time );
	}

	/**
	 * Method to extract the month value from the date.
	 *
	 * @param string $field Representing the date formatted as 0000-00-00.
	 *
	 * @return string Representing the number of the month between 1 and 12.
	 */
	public function month( $field ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * n - Numeric representation of a month, without leading zeros.
		 *     1 through 12
		 */
		return intval( gmdate( 'n', strtotime( $field ) ) );
	}

	/**
	 * Method to extract the year value from the date.
	 *
	 * @param string $field Representing the date formatted as 0000-00-00.
	 *
	 * @return string Representing the number of the year.
	 */
	public function year( $field ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * Y - A full numeric representation of a year, 4 digits.
		 */
		return intval( gmdate( 'Y', strtotime( $field ) ) );
	}

	/**
	 * Method to extract the day value from the date.
	 *
	 * @param string $field Representing the date formatted as 0000-00-00.
	 *
	 * @return string Representing the number of the day of the month from 1 and 31.
	 */
	public function day( $field ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * j - Day of the month without leading zeros.
		 *     1 to 31.
		 */
		return intval( gmdate( 'j', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL SECOND() function.
	 *
	 * @see https://www.php.net/manual/en/datetime.format.php
	 *
	 * @param string $field Representing the time formatted as '00:00:00'.
	 *
	 * @return number Unsigned integer
	 */
	public function second( $field ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * s - Seconds, with leading zeros (00 to 59)
		 */
		return intval( gmdate( 's', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL MINUTE() function.
	 *
	 * @param string $field Representing the time formatted as '00:00:00'.
	 *
	 * @return int
	 */
	public function minute( $field ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * i - Minutes with leading zeros.
		 *     00 to 59.
		 */
		return intval( gmdate( 'i', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL HOUR() function.
	 *
	 * Returns the hour for time, in 24-hour format, from 0 to 23.
	 * Importantly, midnight is 0, not 24.
	 *
	 * @param string $time Representing the time formatted, like '14:08:12'.
	 *
	 * @return int
	 */
	public function hour( $time ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * H   24-hour format of an hour with leading zeros.
		 *     00 through 23.
		 */
		return intval( gmdate( 'H', strtotime( $time ) ) );
	}

	/**
	 * Covers MySQL WEEK() function.
	 *
	 * Always assumes $mode = 1.
	 *
	 * @TODO: Support other modes.
	 *
	 * From https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_week:
	 *
	 * > Returns the week number for date. The two-argument form of WEEK()
	 * > enables you to specify whether the week starts on Sunday or Monday
	 * > and whether the return value should be in the range from 0 to 53
	 * > or from 1 to 53. If the mode argument is omitted, the value of the
	 * > default_week_format system variable is used.
	 * >
	 * > The following table describes how the mode argument works:
	 * >
	 * > Mode   First day of week   Range   Week 1 is the first week …
	 * > 0      Sunday              0-53    with a Sunday in this year
	 * > 1      Monday              0-53    with 4 or more days this year
	 * > 2      Sunday              1-53    with a Sunday in this year
	 * > 3      Monday              1-53    with 4 or more days this year
	 * > 4      Sunday              0-53    with 4 or more days this year
	 * > 5      Monday              0-53    with a Monday in this year
	 * > 6      Sunday              1-53    with 4 or more days this year
	 * > 7      Monday              1-53    with a Monday in this year
	 *
	 * @param string $field Representing the date.
	 * @param int    $mode  The mode argument.
	 */
	public function week( $field, $mode ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * W - ISO-8601 week number of year, weeks starting on Monday.
		 *     Example: 42 (the 42nd week in the year)
		 *
		 * Week 1 is the first week with a Thursday in it.
		 */
		return intval( gmdate( 'W', strtotime( $field ) ) );
	}

	/**
	 * Simulates WEEKDAY() function in MySQL.
	 *
	 * Returns the day of the week as an integer.
	 * The days of the week are numbered 0 to 6:
	 * * 0 for Monday
	 * * 1 for Tuesday
	 * * 2 for Wednesday
	 * * 3 for Thursday
	 * * 4 for Friday
	 * * 5 for Saturday
	 * * 6 for Sunday
	 *
	 * @param string $field Representing the date.
	 *
	 * @return int
	 */
	public function weekday( $field ) {
		/*
		 * date('N') returns 1 (for Monday) through 7 (for Sunday)
		 * That's one more than MySQL.
		 * Let's subtract one to make it compatible.
		 */
		return intval( gmdate( 'N', strtotime( $field ) ) ) - 1;
	}

	/**
	 * Method to emulate MySQL DAYOFMONTH() function.
	 *
	 * @see https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_dayofmonth
	 *
	 * @param string $field Representing the date.
	 *
	 * @return int Returns the day of the month for date as a number in the range 1 to 31.
	 */
	public function dayofmonth( $field ) {
		return intval( gmdate( 'j', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL DAYOFWEEK() function.
	 *
	 * > Returns the weekday index for date (1 = Sunday, 2 = Monday, …, 7 = Saturday).
	 * > These index values correspond to the ODBC standard. Returns NULL if date is NULL.
	 *
	 * @param string $field Representing the date.
	 *
	 * @return int Returns the weekday index for date (1 = Sunday, 2 = Monday, …, 7 = Saturday).
	 */
	public function dayofweek( $field ) {
		/**
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * `w` – Numeric representation of the day of the week
		 *     0 (for Sunday) through 6 (for Saturday)
		 */
		return intval( gmdate( 'w', strtotime( $field ) ) ) + 1;
	}

	/**
	 * Method to emulate MySQL DATE() function.
	 *
	 * @see https://www.php.net/manual/en/datetime.format.php
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
	 * @param mixed $field The field to be tested.
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
	 * @param mixed $expression The statement to be evaluated as true or false.
	 * @param mixed $truthy     Statement or value returned if $expression is true.
	 * @param mixed $falsy      Statement or value returned if $expression is false.
	 *
	 * @return mixed
	 */
	public function _if( $expression, $truthy, $falsy ) {
		return ( true === $expression ) ? $truthy : $falsy;
	}

	/**
	 * Method to emulate MySQL REGEXP() function.
	 *
	 * @param string $pattern Regular expression to match.
	 * @param string $field   Haystack.
	 *
	 * @return integer 1 if matched, 0 if not matched.
	 */
	public function regexp( $pattern, $field ) {
		/*
		 * If the original query says REGEXP BINARY
		 * the comparison is byte-by-byte and letter casing now
		 * matters since lower- and upper-case letters have different
		 * byte codes.
		 *
		 * The REGEXP function can't be easily made to accept two
		 * parameters, so we'll have to use a hack to get around this.
		 *
		 * If the first character of the pattern is a null byte, we'll
		 * remove it and make the comparison case-sensitive. This should
		 * be reasonably safe since PHP does not allow null bytes in
		 * regular expressions anyway.
		 */
		if ( "\x00" === $pattern[0] ) {
			$pattern = substr( $pattern, 1 );
			$flags   = '';
		} else {
			// Otherwise, the search is case-insensitive.
			$flags = 'i';
		}
		$pattern = str_replace( '/', '\/', $pattern );
		$pattern = '/' . $pattern . '/' . $flags;

		return preg_match( $pattern, $field );
	}

	/**
	 * Method to emulate MySQL FIELD() function.
	 *
	 * This function gets the list argument and compares the first item to all the others.
	 * If the same value is found, it returns the position of that value. If not, it
	 * returns 0.
	 *
	 * @return int
	 */
	public function field() {
		$num_args = func_num_args();
		if ( $num_args < 2 || is_null( func_get_arg( 0 ) ) ) {
			return 0;
		}
		$arg_list      = func_get_args();
		$search_string = strtolower( array_shift( $arg_list ) );

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
	 * @return double|null
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

		return min( $arg_list );
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

		return max( $arg_list );
	}

	/**
	 * Method to dummy out MySQL GET_LOCK() function.
	 *
	 * This function is meaningless in SQLite, so we do nothing.
	 *
	 * @param string  $name    Not used.
	 * @param integer $timeout Not used.
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
	 * @param string $name Not used.
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
	 * @param string $content String to be converted to uppercase.
	 *
	 * @return string SQLite compatible function name.
	 */
	public function ucase( $content ) {
		return "upper($content)";
	}

	/**
	 * Method to emulate MySQL LCASE() function.
	 *
	 * This is MySQL alias for lower() function. This function rewrites it
	 * to SQLite compatible name lower().
	 *
	 * @param string $content String to be converted to lowercase.
	 *
	 * @return string SQLite compatible function name.
	 */
	public function lcase( $content ) {
		return "lower($content)";
	}

	/**
	 * Method to emulate MySQL INET_NTOA() function.
	 *
	 * This function gets 4 or 8 bytes integer and turn it into the network address.
	 *
	 * @param integer $num Long integer.
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
	 * @param string $addr Network address.
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
	 * @param string $start Start date.
	 * @param string $end   End date.
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
	 * @param string  $substr Needle.
	 * @param string  $str    Haystack.
	 * @param integer $pos    Position.
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
	 * @return string formatted GMT date 'dddd-mm-dd'
	 */
	public function utc_date() {
		return gmdate( 'Y-m-d', time() );
	}

	/**
	 * Method to return GMT time in the string format.
	 *
	 * @return string formatted GMT time '00:00:00'
	 */
	public function utc_time() {
		return gmdate( 'H:i:s', time() );
	}

	/**
	 * Method to return GMT time stamp in the string format.
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
	 * @return string representing the version number: major_version.minor_version
	 */
	public function version() {
		return '5.5';
	}
}
