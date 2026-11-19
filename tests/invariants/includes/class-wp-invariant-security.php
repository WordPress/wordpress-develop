<?php
/**
 * Checks of values which the security invariants share.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Reads addresses, serialized data, file names and file contents as an attacker would use them.
 *
 * None of these use PCRE, so that they leave preg_last_error() alone for the
 * WordPress code being called.
 */
final class WP_Invariant_Security {

	/**
	 * File extensions which a web server may run as code, wherever they appear among the extensions of a name.
	 */
	const EXECUTABLE_EXTENSIONS = array(
		'php'   => true,
		'php3'  => true,
		'php4'  => true,
		'php5'  => true,
		'php6'  => true,
		'php7'  => true,
		'php8'  => true,
		'phtml' => true,
		'pht'   => true,
		'phar'  => true,
		'phps'  => true,
		'shtml' => true,
		'cgi'   => true,
		'pl'    => true,
	);

	/**
	 * Names of files which configure a web server or PHP for the directory they are in.
	 */
	const CONFIGURATION_FILES = array(
		'.htaccess'  => true,
		'.user.ini'  => true,
		'web.config' => true,
	);

	/**
	 * Methods which unserialize() can lead to, through which serialized objects can run code.
	 */
	const GADGET_METHODS = array( '__wakeup', '__unserialize', '__destruct', '__toString', '__call', '__get' );

	/**
	 * Returns what kind of internal address an IP address is, or null when it is a public one.
	 *
	 * @param string $ip IPv4 or IPv6 address, without brackets.
	 * @return string|null Such as `loopback`, `private` or `link-local, the cloud metadata service`.
	 */
	public static function internal_address( $ip ) {
		$bytes = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- It warns on what is not an address.
		if ( false === $bytes ) {
			return null;
		}

		if ( 16 === strlen( $bytes ) ) {
			$mapped = substr( $bytes, 0, 12 );
			if ( str_repeat( "\0", 10 ) . "\xff\xff" === $mapped || "\x00\x64\xff\x9b" . str_repeat( "\0", 8 ) === $mapped ) {
				// An IPv4-mapped address, or the NAT64 prefix 64:ff9b::/96, reaches the IPv4 address in its last four bytes.
				$bytes = substr( $bytes, 12 );
			} elseif ( str_repeat( "\0", 15 ) . "\x01" === $bytes ) {
				return 'loopback';
			} elseif ( str_repeat( "\0", 16 ) === $bytes ) {
				return 'unspecified';
			} elseif ( 0xfc === ( ord( $bytes[0] ) & 0xfe ) ) {
				return 'unique local';
			} elseif ( 0xfe === ord( $bytes[0] ) && 0x80 === ( ord( $bytes[1] ) & 0xc0 ) ) {
				return 'link-local';
			} else {
				return null;
			}
		}

		$a = ord( $bytes[0] );
		$b = ord( $bytes[1] );
		if ( 0 === $a ) {
			return 'unspecified';
		}
		if ( 127 === $a ) {
			return 'loopback';
		}
		if ( 10 === $a || ( 172 === $a && $b >= 16 && $b <= 31 ) || ( 192 === $a && 168 === $b ) ) {
			return 'private';
		}
		if ( 169 === $a && 254 === $b ) {
			return "\xa9\xfe\xa9\xfe" === $bytes ? 'link-local, the cloud metadata service' : 'link-local';
		}
		if ( 100 === $a && $b >= 64 && $b <= 127 ) {
			return 'carrier-grade NAT';
		}

		return null;
	}

	/**
	 * Returns the names of the classes of the objects in serialized data.
	 *
	 * It reads `O:<length>:"<name>"` and `C:<length>:"<name>"` wherever they
	 * appear outside serialized strings.
	 *
	 * @param string $data Serialized data.
	 * @return string[] Distinct class names.
	 */
	public static function serialized_classes( $data ) {
		$classes = array();
		$length  = strlen( $data );
		$at      = 0;
		while ( $at < $length ) {
			$type = $data[ $at ];
			if ( 's' === $type && ':' === ( $data[ $at + 1 ] ?? '' ) ) {
				// Skip a string, whose contents can look like anything: s:<length>:"…";.
				$digits = strspn( $data, '0123456789', $at + 2 );
				$at    += 2 + $digits + 2 + (int) substr( $data, $at + 2, $digits ) + 2;
				continue;
			}
			if ( ( 'O' === $type || 'C' === $type ) && ':' === ( $data[ $at + 1 ] ?? '' ) ) {
				$digits = strspn( $data, '0123456789', $at + 2 );
				$name   = substr( $data, $at + 2 + $digits + 2, (int) substr( $data, $at + 2, $digits ) );
				if ( '' !== $name ) {
					$classes[ $name ] = true;
				}
			}
			++$at;
		}

		return array_keys( $classes );
	}

	/**
	 * Returns the classes in serialized data through which unserialize() could run code.
	 *
	 * Those are classes which are loaded, which unserialize() would create, and
	 * which have any of GADGET_METHODS. A class which has not been loaded is not
	 * loaded to find out.
	 *
	 * @param string     $data            Serialized data.
	 * @param bool|array $allowed_classes The `allowed_classes` option given to unserialize(), true when none was.
	 * @return string[]
	 */
	public static function gadget_classes( $data, $allowed_classes = true ) {
		if ( false === $allowed_classes || ( false === strpos( $data, 'O:' ) && false === strpos( $data, 'C:' ) ) ) {
			return array();
		}

		$allowed = is_array( $allowed_classes ) ? array_change_key_case( array_flip( $allowed_classes ) ) : null;
		$gadgets = array();
		foreach ( self::serialized_classes( $data ) as $class ) {
			if ( ( null !== $allowed && ! isset( $allowed[ strtolower( $class ) ] ) ) || ! class_exists( $class, false ) ) {
				continue;
			}
			foreach ( self::GADGET_METHODS as $method ) {
				if ( method_exists( $class, $method ) ) {
					$gadgets[] = $class;
					break;
				}
			}
		}

		return $gadgets;
	}

	/**
	 * Returns why a file name could run as code on a web server, or null when it could not.
	 *
	 * Apache runs a file by any of its extensions, so `photo.php.jpg` counts as PHP.
	 *
	 * @param string $name A file name, or a path.
	 * @return string|null Such as `the extension .php` or `.htaccess, which configures the server`.
	 */
	public static function executable_name( $name ) {
		$name = strtolower( basename( str_replace( '\\', '/', $name ) ) );
		if ( isset( self::CONFIGURATION_FILES[ $name ] ) ) {
			return "{$name}, which configures the server";
		}

		$extensions = explode( '.', $name );
		array_shift( $extensions );
		foreach ( $extensions as $extension ) {
			$extension = rtrim( $extension, " \t" );
			if ( isset( self::EXECUTABLE_EXTENSIONS[ $extension ] ) ) {
				return "the extension .{$extension}";
			}
		}

		return null;
	}

	/**
	 * Returns why the contents of a file are PHP code, or null when they are not.
	 *
	 * @param string $contents Contents of a file.
	 * @return string|null Such as `it starts with <?php`.
	 */
	public static function executable_contents( $contents ) {
		$start = ltrim( substr( $contents, 0, 64 ), "\xEF\xBB\xBF \t\r\n" );
		if ( 0 === strncasecmp( $start, '<?php', 5 ) || 0 === strncmp( $start, '<?=', 3 ) ) {
			$short = 0 === strncmp( $start, '<?=', 3 );
			return 'it starts with ' . ( $short ? '<?=' : substr( $start, 0, 5 ) );
		}
		if ( false !== stripos( $contents, '__HALT_COMPILER' ) ) {
			return 'it holds __HALT_COMPILER, as a PHAR archive does';
		}

		return null;
	}

	/**
	 * Whether a mode for fopen() writes or creates the file.
	 *
	 * @param string $mode Mode, such as `rb` or `w+`.
	 * @return bool
	 */
	public static function is_write_mode( $mode ) {
		return ( '' !== $mode && false !== strpos( 'waxc', $mode[0] ) ) || false !== strpos( $mode, '+' );
	}
}
