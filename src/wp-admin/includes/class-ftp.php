<?php
/**
 * PemFTP - An Ftp implementation in pure PHP
 *
 * @package PemFTP
 * @since 2.5.0
 *
 * @version 1.0
 * @copyright Alexey Dotsenko
 * @author Alexey Dotsenko
 * @link https://www.phpclasses.org/package/1743-PHP-FTP-client-in-pure-PHP.html
 * @license LGPL https://opensource.org/licenses/lgpl-license.html
 */

/**
 * Defines the newline characters, if not defined already.
 *
 * This can be redefined.
 *
 * @since 2.5.0
 * @var string
 */
if ( ! defined( 'CRLF' ) ) {
	define( 'CRLF', "\r\n" );
}

/**
 * Sets whatever to autodetect ASCII mode.
 *
 * This can be redefined.
 *
 * @since 2.5.0
 * @var int
 */
if ( ! defined( 'FTP_AUTOASCII' ) ) {
	define( 'FTP_AUTOASCII', -1 );
}

/**
 *
 * This can be redefined.
 *
 * @since 2.5.0
 * @var int
 */
if ( ! defined( 'FTP_BINARY' ) ) {
	define( 'FTP_BINARY', 1 );
}

/**
 *
 * This can be redefined.
 *
 * @since 2.5.0
 * @var int
 */
if ( ! defined( 'FTP_ASCII' ) ) {
	define( 'FTP_ASCII', 0 );
}

/**
 * Whether to force FTP.
 *
 * This can be redefined.
 *
 * @since 2.5.0
 * @var bool
 */
if ( ! defined( 'FTP_FORCE' ) ) {
	define( 'FTP_FORCE', true );
}

/**
 * @since 2.5.0
 * @var string
 */
define( 'FTP_OS_Unix', 'u' );

/**
 * @since 2.5.0
 * @var string
 */
define( 'FTP_OS_Windows', 'w' );

/**
 * @since 2.5.0
 * @var string
 */
define( 'FTP_OS_Mac', 'm' );

/**
 * PemFTP base class.
 */
class ftp_base {
	/* Public variables. */
	var $LocalEcho;
	var $Verbose;
	var $OS_local;
	var $OS_remote;

	/* Private variables. */
	var $_lastaction;
	var $_errors;
	var $_type;
	var $_umask;
	var $_timeout;
	var $_passive;
	var $_host;
	var $_fullhost;
	var $_port;
	var $_datahost;
	var $_dataport;
	var $_ftp_control_sock;
	var $_ftp_data_sock;
	var $_ftp_temp_sock;
	var $_ftp_buff_size;
	var $_login;
	var $_password;
	var $_connected;
	var $_ready;
	var $_code;
	var $_message;
	var $_can_restore;
	var $_port_available;
	var $_curtype;
	var $_features;

	var $_error_array;
	var $AuthorizedTransferMode;
	var $OS_FullName;
	var $_eol_code;
	var $AutoAsciiExt;

	/**
	 * Constructor.
	 *
	 * @param boolean $port_mode Port Mode.
	 * @param boolean $verb      Verb.
	 * @param boolean $le        Le.
	 */
	public function __construct( $port_mode = false, $verb = false, $le = false ) {
		$this->LocalEcho              = $le;
		$this->Verbose                = $verb;
		$this->_lastaction            = null;
		$this->_error_array           = array();
		$this->_eol_code              = array(
			FTP_OS_Unix    => "\n",
			FTP_OS_Mac     => "\r",
			FTP_OS_Windows => "\r\n",
		);
		$this->AuthorizedTransferMode = array( FTP_AUTOASCII, FTP_ASCII, FTP_BINARY );
		$this->OS_FullName            = array(
			FTP_OS_Unix    => 'UNIX',
			FTP_OS_Windows => 'WINDOWS',
			FTP_OS_Mac     => 'MACOS',
		);
		$this->AutoAsciiExt           = array( 'ASP', 'BAT', 'C', 'CPP', 'CSS', 'CSV', 'JS', 'H', 'HTM', 'HTML', 'SHTML', 'INI', 'LOG', 'PHP3', 'PHTML', 'PL', 'PERL', 'SH', 'SQL', 'TXT' );
		$this->_port_available        = ( true == $port_mode );
		$this->SendMSG( 'Staring FTP client class' . ( $this->_port_available ? '' : ' without PORT mode support' ) );
		$this->_connected     = false;
		$this->_ready         = false;
		$this->_can_restore   = false;
		$this->_code          = 0;
		$this->_message       = '';
		$this->_ftp_buff_size = 4096;
		$this->_curtype       = null;
		$this->SetUmask( 0022 );
		$this->SetType( FTP_AUTOASCII );
		$this->SetTimeout( 30 );
		$this->Passive( ! $this->_port_available );
		$this->_login    = 'anonymous';
		$this->_password = 'anon@ftp.com';
		$this->_features = array();
		$this->OS_local  = FTP_OS_Unix;
		$this->OS_remote = FTP_OS_Unix;
		$this->features  = array();
		if ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) ) {
			$this->OS_local = FTP_OS_Windows;
		} elseif ( 'MAC' === strtoupper( substr( PHP_OS, 0, 3 ) ) ) {
			$this->OS_local = FTP_OS_Mac;
		}
	}

	/**
	 * FTP Base.
	 *
	 * @param  boolean $port_mode Port Mode.
	 */
	public function ftp_base( $port_mode = false ) {
		$this->__construct( $port_mode );
	}

	/**
	 * Parse Listing.
	 *
	 * @param  [type] $line Line.
	 */
	public function parselisting( $line ) {
		$is_windows = ( FTP_OS_Windows == $this->OS_remote );
		if ( $is_windows && preg_match( '/([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)/', $line, $lucifer ) ) {
			$b = array();
			if ( $lucifer[3] < 70 ) {
				$lucifer[3] += 2000;
			} else {
				$lucifer[3] += 1900; } // 4digit year fix.
			$b['isdir'] = ( '<DIR>' == $lucifer[7] );
			if ( $b['isdir'] ) {
				$b['type'] = 'd';
			} else {
				$b['type'] = 'f';
			}
			$b['size']   = $lucifer[7];
			$b['month']  = $lucifer[1];
			$b['day']    = $lucifer[2];
			$b['year']   = $lucifer[3];
			$b['hour']   = $lucifer[4];
			$b['minute'] = $lucifer[5];
			$b['time']   = @mktime( $lucifer[4] + ( strcasecmp( $lucifer[6], 'PM' ) == 0 ? 12 : 0 ), $lucifer[5], 0, $lucifer[1], $lucifer[2], $lucifer[3] );
			$b['am/pm']  = $lucifer[6];
			$b['name']   = $lucifer[8];
		} elseif ( ! $is_windows && $lucifer = preg_split( '/[ ]/', $line, 9, PREG_SPLIT_NO_EMPTY ) ) {
			$lcount = count( $lucifer );
			if ( $lcount < 8 ) {
				return '';
			}
			$b           = array();
			$b['isdir']  = 'd' === $lucifer[0][0];
			$b['islink'] = 'l' === $lucifer[0][0];
			if ( $b['isdir'] ) {
				$b['type'] = 'd';
			} elseif ( $b['islink'] ) {
				$b['type'] = 'l';
			} else {
				$b['type'] = 'f';
			}
			$b['perms']  = $lucifer[0];
			$b['number'] = $lucifer[1];
			$b['owner']  = $lucifer[2];
			$b['group']  = $lucifer[3];
			$b['size']   = $lucifer[4];
			if ( 8 == $lcount ) {
				sscanf( $lucifer[5], '%d-%d-%d', $b['year'], $b['month'], $b['day'] );
				sscanf( $lucifer[6], '%d:%d', $b['hour'], $b['minute'] );
				$b['time'] = @mktime( $b['hour'], $b['minute'], 0, $b['month'], $b['day'], $b['year'] );
				$b['name'] = $lucifer[7];
			} else {
				$b['month'] = $lucifer[5];
				$b['day']   = $lucifer[6];
				if ( preg_match( '/([0-9]{2}):([0-9]{2})/', $lucifer[7], $l2 ) ) {
					$b['year']   = gmdate( 'Y' );
					$b['hour']   = $l2[1];
					$b['minute'] = $l2[2];
				} else {
					$b['year']   = $lucifer[7];
					$b['hour']   = 0;
					$b['minute'] = 0;
				}
				$b['time'] = strtotime( sprintf( '%d %s %d %02d:%02d', $b['day'], $b['month'], $b['year'], $b['hour'], $b['minute'] ) );
				$b['name'] = $lucifer[8];
			}
		}

		return $b;
	}

	/**
	 * Send Message.
	 *
	 * @param string  $message Message.
	 * @param boolean $crlf    CRLF.
	 */
	public function SendMSG( $message = '', $crlf = true ) {
		if ( $this->Verbose ) {
			echo $message . ( $crlf ? CRLF : '' );
			flush();
		}
		return true;
	}

	/**
	 * SetType.
	 *
	 * @param [type] $mode Mode.
	 */
	public function SetType( $mode = FTP_AUTOASCII ) {
		if ( ! in_array( $mode, $this->AuthorizedTransferMode ) ) {
			$this->SendMSG( 'Wrong type' );
			return false;
		}
		$this->_type = $mode;
		$this->SendMSG( 'Transfer type: ' . ( $this->_type == FTP_BINARY ? 'binary' : ( $this->_type == FTP_ASCII ? 'ASCII' : 'auto ASCII' ) ) );
		return true;
	}

	/**
	 * Set Type.
	 *
	 * @param  [type] $mode Mode.
	 */
	public function _settype( $mode = FTP_ASCII ) {
		if ( $this->_ready ) {
			if ( FTP_BINARY == $mode ) {
				if ( FTP_BINARY != $this->_curtype ) {
					if ( ! $this->_exec( 'TYPE I', 'SetType' ) ) {
						return false;
					}
					$this->_curtype = FTP_BINARY;
				}
			} elseif ( FTP_ASCII != $this->_curtype ) {
				if ( ! $this->_exec( 'TYPE A', 'SetType' ) ) {
					return false;
				}
				$this->_curtype = FTP_ASCII;
			}
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Passive Mode.
	 *
	 * @param [type] $pasv Passive.
	 */
	public function Passive( $pasv = null ) {
		if ( is_null( $pasv ) ) {
			$this->_passive = ! $this->_passive;
		} else {
			$this->_passive = $pasv;
		}
		if ( ! $this->_port_available && ! $this->_passive ) {
			$this->SendMSG( 'Only passive connections available!' );
			$this->_passive = true;
			return false;
		}
		$this->SendMSG( 'Passive mode ' . ( $this->_passive ? 'on' : 'off' ) );
		return true;
	}

	/**
	 * Set Server.
	 *
	 * @param string  $host      Host.
	 * @param integer $port      Port.
	 * @param boolean $reconnect Reconnect.
	 */
	public function SetServer( $host, $port = 21, $reconnect = true ) {
		if ( ! is_long( $port ) ) {
			$this->verbose = true;
			$this->SendMSG( 'Incorrect port syntax' );
			return false;
		} else {
			$ip  = @gethostbyname( $host );
			$dns = @gethostbyaddr( $host );
			if ( ! $ip ) {
				$ip = $host;
			}
			if ( ! $dns ) {
				$dns = $host;
			}
			// Validate the IPAddress PHP4 returns -1 for invalid, PHP5 false.
			// -1 === "255.255.255.255" which is the broadcast address which is also going to be invalid.
			$ipaslong = ip2long( $ip );
			if ( ( false == $ipaslong ) || ( -1 === $ipaslong ) ) {
				$this->SendMSG( 'Wrong host name/address "' . $host . '"' );
				return false;
			}
			$this->_host     = $ip;
			$this->_fullhost = $dns;
			$this->_port     = $port;
			$this->_dataport = $port - 1;
		}
		$this->SendMSG( 'Host "' . $this->_fullhost . '(' . $this->_host . '):' . $this->_port . '"' );
		if ( $reconnect ) {
			if ( $this->_connected ) {
				$this->SendMSG( 'Reconnecting' );
				if ( ! $this->quit( FTP_FORCE ) ) {
					return false;
				}
				if ( ! $this->connect() ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Set Umask.
	 *
	 * @param integer $umask Umask, default 0022.
	 */
	public function SetUmask( $umask = 0022 ) {
		$this->_umask = $umask;
		umask( $this->_umask );
		$this->SendMSG( 'UMASK 0' . decoct( $this->_umask ) );
		return true;
	}

	/**
	 * Set Timeout.
	 *
	 * @param integer $timeout Timeout, default 30.
	 */
	public function SetTimeout( $timeout = 30 ) {
		$this->_timeout = $timeout;
		$this->SendMSG( 'Timeout ' . $this->_timeout );
		if ( $this->_connected ) {
			if ( ! $this->_settimeout( $this->_ftp_control_sock ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Connect.
	 *
	 * @param  [type] $server Server.
	 */
	public function connect( $server = null ) {
		if ( ! empty( $server ) ) {
			if ( ! $this->SetServer( $server ) ) {
				return false;
			}
		}
		if ( $this->_ready ) {
			return true;
		}
		$this->SendMsg( 'Local OS : ' . $this->OS_FullName[ $this->OS_local ] );
		if ( ! ( $this->_ftp_control_sock = $this->_connect( $this->_host, $this->_port ) ) ) {
			$this->SendMSG( 'Error : Cannot connect to remote host "' . $this->_fullhost . ' :' . $this->_port . '"' );
			return false;
		}
		$this->SendMSG( 'Connected to remote host "' . $this->_fullhost . ':' . $this->_port . '". Waiting for greeting.' );
		do {
			if ( ! $this->_readmsg() ) {
				return false;
			}
			if ( ! $this->_checkCode() ) {
				return false;
			}
			$this->_lastaction = time();
		} while ( $this->_code < 200 );
		$this->_ready = true;
		$syst         = $this->systype();
		if ( ! $syst ) {
			$this->SendMSG( "Can't detect remote OS" );
		} else {
			if ( preg_match( '/win|dos|novell/i', $syst[0] ) ) {
				$this->OS_remote = FTP_OS_Windows;
			} elseif ( preg_match( '/os/i', $syst[0] ) ) {
				$this->OS_remote = FTP_OS_Mac;
			} elseif ( preg_match( '/(li|u)nix/i', $syst[0] ) ) {
				$this->OS_remote = FTP_OS_Unix;
			} else {
				$this->OS_remote = FTP_OS_Mac;
			}
			$this->SendMSG( 'Remote OS: ' . $this->OS_FullName[ $this->OS_remote ] );
		}
		if ( ! $this->features() ) {
			$this->SendMSG( "Can't get features list. All supported - disabled" );
		} else {
			$this->SendMSG( 'Supported features: ' . implode( ', ', array_keys( $this->_features ) ) );
		}
		return true;
	}

	/**
	 * Quit.
	 *
	 * @param  boolean $force Force.
	 */
	public function quit( $force = false ) {
		if ( $this->_ready ) {
			if ( ! $this->_exec( 'QUIT' ) && ! $force ) {
				return false;
			}
			if ( ! $this->_checkCode() && ! $force ) {
				return false;
			}
			$this->_ready = false;
			$this->SendMSG( 'Session finished' );
		}
		$this->_quit();
		return true;
	}

	/**
	 * Login.
	 *
	 * @param  [type] $user User.
	 * @param  [type] $pass Password.
	 */
	public function login( $user = null, $pass = null ) {
		if ( ! is_null( $user ) ) {
			$this->_login = $user;
		} else {
			$this->_login = 'anonymous';
		}
		if ( ! is_null( $pass ) ) {
			$this->_password = $pass;
		} else {
			$this->_password = 'anon@anon.com';
		}
		if ( ! $this->_exec( 'USER ' . $this->_login, 'login' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		if ( 230 != $this->_code ) {
			if ( ! $this->_exec( ( ( 331 == $this->_code ) ? 'PASS ' : 'ACCT ' ) . $this->_password, 'login' ) ) {
				return false;
			}
			if ( ! $this->_checkCode() ) {
				return false;
			}
		}
		$this->SendMSG( 'Authentication succeeded' );
		if ( empty( $this->_features ) ) {
			if ( ! $this->features() ) {
				$this->SendMSG( "Can't get features list. All supported - disabled" );
			} else {
				$this->SendMSG( 'Supported features: ' . implode( ', ', array_keys( $this->_features ) ) );
			}
		}
		return true;
	}

	/**
	 * PWD.
	 */
	public function pwd() {
		if ( ! $this->_exec( 'PWD', 'pwd' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return preg_replace( '/^[0-9]{3} "(.+)".*$/s', "\\1", $this->_message );
	}

	/**
	 * Change Directory Up.
	 */
	public function cdup() {
		if ( ! $this->_exec( 'CDUP', 'cdup' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return true;
	}

	/**
	 * Change Directory.
	 *
	 * @param  [type] $pathname pathname.
	 */
	public function chdir( $pathname ) {
		if ( ! $this->_exec( 'CWD ' . $pathname, 'chdir' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return true;
	}
	/**
	 * Remove Directory.
	 *
	 * @param  [type] $pathname pathname.
	 */
	public function rmdir( $pathname ) {
		if ( ! $this->_exec( 'RMD ' . $pathname, 'rmdir' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return true;
	}

	/**
	 * Make Directory.
	 *
	 * @param  [type] $pathname pathname.
	 */
	public function mkdir( $pathname ) {
		if ( ! $this->_exec( 'MKD ' . $pathname, 'mkdir' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return true;
	}

	/**
	 * Rename.
	 *
	 * @param  [type] $from From.
	 * @param  [type] $to   To.
	 */
	public function rename( $from, $to ) {
		if ( ! $this->_exec( 'RNFR ' . $from, 'rename' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		if ( 350 == $this->_code ) {
			if ( ! $this->_exec( 'RNTO ' . $to, 'rename' ) ) {
				return false;
			}
			if ( ! $this->_checkCode() ) {
				return false;
			}
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Filesize.
	 *
	 * @param  [type] $pathname pathname.
	 */
	public function filesize( $pathname ) {
		if ( ! isset( $this->_features['SIZE'] ) ) {
			$this->PushError( 'filesize', 'not supported by server' );
			return false;
		}
		if ( ! $this->_exec( 'SIZE ' . $pathname, 'filesize' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return preg_replace( '/^[0-9]{3} ([0-9]+).*$/s', "\\1", $this->_message );
	}

	/**
	 * Abort.
	 */
	public function abort() {
		if ( ! $this->_exec( 'ABOR', 'abort' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			if ( 426 != $this->_code ) {
				return false;
			}
			if ( ! $this->_readmsg( 'abort' ) ) {
				return false;
			}
			if ( ! $this->_checkCode() ) {
				return false;
			}
		}
		return true;
	}
	/**
	 * MDTM.
	 *
	 * @param  [type] $pathname pathname.
	 */
	public function mdtm( $pathname ) {
		if ( ! isset( $this->_features['MDTM'] ) ) {
			$this->PushError( 'mdtm', 'not supported by server' );
			return false;
		}
		if ( ! $this->_exec( 'MDTM ' . $pathname, 'mdtm' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		$mdtm      = preg_replace( '/^[0-9]{3} ([0-9]+).*$/s', "\\1", $this->_message );
		$date      = sscanf( $mdtm, '%4d%2d%2d%2d%2d%2d' );
		$timestamp = mktime( $date[3], $date[4], $date[5], $date[1], $date[2], $date[0] );
		return $timestamp;
	}

	/**
	 * Systype.
	 */
	public function systype() {
		if ( ! $this->_exec( 'SYST', 'systype' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		$DATA = explode( ' ', $this->_message );
		return array( $DATA[1], $DATA[3] );
	}
	/**
	 * Delete.
	 *
	 * @param  [type] $pathname Pathname.
	 */
	public function delete( $pathname ) {
		if ( ! $this->_exec( 'DELE ' . $pathname, 'delete' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return true;
	}

	/**
	 * Site.
	 *
	 * @param  [type] $command Command.
	 * @param  string $fnction Function.
	 */
	public function site( $command, $fnction = 'site' ) {
		if ( ! $this->_exec( 'SITE ' . $command, $fnction ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return true;
	}

	/**
	 * CHMOD.
	 *
	 * @param  [type] $pathname Pathname.
	 * @param  [type] $mode     Mode.
	 */
	public function chmod( $pathname, $mode ) {
		if ( ! $this->site( sprintf( 'CHMOD %o %s', $mode, $pathname ), 'chmod' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Restore.
	 *
	 * @param  [type] $from From.
	 */
	public function restore( $from ) {
		if ( ! isset( $this->_features['REST'] ) ) {
			$this->PushError( 'restore', 'not supported by server' );
			return false;
		}
		if ( FTP_BINARY != $this->_curtype ) {
			$this->PushError( 'restore', "can't restore in ASCII mode" );
			return false;
		}
		if ( ! $this->_exec( 'REST ' . $from, 'resore' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return true;
	}

	/**
	 * Features.
	 */
	public function features() {
		if ( ! $this->_exec( 'FEAT', 'features' ) ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		$f               = preg_split( '/[' . CRLF . ']+/', preg_replace( '/[0-9]{3}[ -].*[' . CRLF . ']+/', '', $this->_message ), -1, PREG_SPLIT_NO_EMPTY );
		$this->_features = array();
		foreach ( $f as $k => $v ) {
			$v                                    = explode( ' ', trim( $v ) );
			$this->_features[ array_shift( $v ) ] = $v;
		}
		return true;
	}

	/**
	 * Raw List.
	 *
	 * @param  string $pathname Pathname.
	 * @param  string $arg      Arguments.
	 */
	public function rawlist( $pathname = '', $arg = '' ) {
		return $this->_list( ( $arg ? ' ' . $arg : '' ) . ( $pathname ? ' ' . $pathname : '' ), 'LIST', 'rawlist' );
	}
	/**
	 * NLIST.
	 *
	 * @param  string $pathname Pathname.
	 * @param  string $arg      Arguments.
	 */
	public function nlist( $pathname = '', $arg = '' ) {
		return $this->_list( ( $arg ? ' ' . $arg : '' ) . ( $pathname ? ' ' . $pathname : '' ), 'NLST', 'nlist' );
	}
	/**
	 * Is Exists.
	 *
	 * @param  [type] $pathname Pathname.
	 */
	public function is_exists( $pathname ) {
		return $this->file_exists( $pathname );
	}
	/**
	 * File Exists.
	 *
	 * @param  [type] $pathname Pathname.
	 */
	public function file_exists( $pathname ) {
		$exists = true;
		if ( ! $this->_exec( 'RNFR ' . $pathname, 'rename' ) ) {
			$exists = false;
		} else {
			if ( ! $this->_checkCode() ) {
				$exists = false;
			}
			$this->abort();
		}
		if ( $exists ) {
			$this->SendMSG( 'Remote file ' . $pathname . ' exists' );
		} else {
			$this->SendMSG( 'Remote file ' . $pathname . ' does not exist' );
		}
		return $exists;
	}

	/**
	 * FGET.
	 *
	 * @param  [type]  $fp         FP.
	 * @param  [type]  $remotefile Remote File.
	 * @param  integer $rest       Rest.
	 */
	public function fget( $fp, $remotefile, $rest = 0 ) {
		if ( $this->_can_restore && 0 != $rest ) {
			fseek( $fp, $rest );
		}
		$pi = pathinfo( $remotefile );
		if ( FTP_ASCII == $this->_type || ( FTP_AUTOASCII == $this->_type && in_array( strtoupper( $pi['extension'] ), $this->AutoAsciiExt ) ) ) {
			$mode = FTP_ASCII;
		} else {
			$mode = FTP_BINARY;
		}
		if ( ! $this->_data_prepare( $mode ) ) {
			return false;
		}
		if ( $this->_can_restore && 0 != $rest ) {
			$this->restore( $rest );
		}
		if ( ! $this->_exec( 'RETR ' . $remotefile, 'get' ) ) {
			$this->_data_close();
			return false;
		}
		if ( ! $this->_checkCode() ) {
			$this->_data_close();
			return false;
		}
		$out = $this->_data_read( $mode, $fp );
		$this->_data_close();
		if ( ! $this->_readmsg() ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return $out;
	}

	/**
	 * Get.
	 *
	 * @param  [type]  $remotefile Remote File.
	 * @param  [type]  $localfile  Local File.
	 * @param  integer $rest       Rest.
	 */
	public function get( $remotefile, $localfile = null, $rest = 0 ) {
		if ( is_null( $localfile ) ) {
			$localfile = $remotefile;
		}
		if ( @file_exists( $localfile ) ) {
			$this->SendMSG( 'Warning : local file will be overwritten' );
		}
		$fp = @fopen( $localfile, 'w' );
		if ( ! $fp ) {
			$this->PushError( 'get', "can't open local file", 'Cannot create "' . $localfile . '"' );
			return false;
		}
		if ( $this->_can_restore && 0 != $rest ) {
			fseek( $fp, $rest );
		}
		$pi = pathinfo( $remotefile );
		if ( FTP_ASCII == $this->_type || ( FTP_AUTOASCII == $this->_type && in_array( strtoupper( $pi['extension'] ), $this->AutoAsciiExt ) ) ) {
			$mode = FTP_ASCII;
		} else {
			$mode = FTP_BINARY;
		}
		if ( ! $this->_data_prepare( $mode ) ) {
			fclose( $fp );
			return false;
		}
		if ( $this->_can_restore && 0 != $rest ) {
			$this->restore( $rest );
		}
		if ( ! $this->_exec( 'RETR ' . $remotefile, 'get' ) ) {
			$this->_data_close();
			fclose( $fp );
			return false;
		}
		if ( ! $this->_checkCode() ) {
			$this->_data_close();
			fclose( $fp );
			return false;
		}
		$out = $this->_data_read( $mode, $fp );
		fclose( $fp );
		$this->_data_close();
		if ( ! $this->_readmsg() ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return $out;
	}

	/**
	 * FPUT.
	 *
	 * @param  [type]  $remotefile Remote File.
	 * @param  [type]  $fp         File Put.
	 * @param  integer $rest       Rest.
	 */
	public function fput( $remotefile, $fp, $rest = 0 ) {
		if ( $this->_can_restore && 0 != $rest ) {
			fseek( $fp, $rest );
		}
		$pi = pathinfo( $remotefile );
		if ( FTP_ASCII == $this->_type || ( FTP_AUTOASCII == $this->_type && in_array( strtoupper( $pi['extension'] ), $this->AutoAsciiExt ) ) ) {
			$mode = FTP_ASCII;
		} else {
			$mode = FTP_BINARY;
		}
		if ( ! $this->_data_prepare( $mode ) ) {
			return false;
		}
		if ( $this->_can_restore && 0 != $rest ) {
			$this->restore( $rest );
		}
		if ( ! $this->_exec( 'STOR ' . $remotefile, 'put' ) ) {
			$this->_data_close();
			return false;
		}
		if ( ! $this->_checkCode() ) {
			$this->_data_close();
			return false;
		}
		$ret = $this->_data_write( $mode, $fp );
		$this->_data_close();
		if ( ! $this->_readmsg() ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return $ret;
	}
	/**
	 * Put.
	 *
	 * @param  [type]  $localfile  Local File.
	 * @param  [type]  $remotefile Remote File.
	 * @param  integer $rest       Rest.
	 */
	public function put( $localfile, $remotefile = null, $rest = 0 ) {
		if ( is_null( $remotefile ) ) {
			$remotefile = $localfile;
		}
		if ( ! file_exists( $localfile ) ) {
			$this->PushError( 'put', "can't open local file", 'No such file or directory "' . $localfile . '"' );
			return false;
		}
		$fp = @fopen( $localfile, 'r' );

		if ( ! $fp ) {
			$this->PushError( 'put', "can't open local file", 'Cannot read file "' . $localfile . '"' );
			return false;
		}
		if ( $this->_can_restore && 0 != $rest ) {
			fseek( $fp, $rest );
		}
		$pi = pathinfo( $localfile );
		if ( FTP_ASCII == $this->_type || ( FTP_AUTOASCII == $this->_type && in_array( strtoupper( $pi['extension'] ), $this->AutoAsciiExt ) ) ) {
			$mode = FTP_ASCII;
		} else {
			$mode = FTP_BINARY;
		}
		if ( ! $this->_data_prepare( $mode ) ) {
			fclose( $fp );
			return false;
		}
		if ( $this->_can_restore && 0 != $rest ) {
			$this->restore( $rest );
		}
		if ( ! $this->_exec( 'STOR ' . $remotefile, 'put' ) ) {
			$this->_data_close();
			fclose( $fp );
			return false;
		}
		if ( ! $this->_checkCode() ) {
			$this->_data_close();
			fclose( $fp );
			return false;
		}
		$ret = $this->_data_write( $mode, $fp );
		fclose( $fp );
		$this->_data_close();
		if ( ! $this->_readmsg() ) {
			return false;
		}
		if ( ! $this->_checkCode() ) {
			return false;
		}
		return $ret;
	}

	/**
	 * MPut.
	 *
	 * @param  string  $local      Local.
	 * @param  [type]  $remote     Remote.
	 * @param  boolean $continious Continious.
	 */
	public function mput( $local = '.', $remote = null, $continious = false ) {
		$local = realpath( $local );
		if ( ! @file_exists( $local ) ) {
			$this->PushError( 'mput', "can't open local folder", 'Cannot stat folder "' . $local . '"' );
			return false;
		}
		if ( ! is_dir( $local ) ) {
			return $this->put( $local, $remote );
		}
		if ( empty( $remote ) ) {
			$remote = '.';
		} elseif ( ! $this->file_exists( $remote ) && ! $this->mkdir( $remote ) ) {
			return false;
		}
		if ( $handle = opendir( $local ) ) {
			$list = array();
			while ( false !== ( $file = readdir( $handle ) ) ) {
				if ( '.' != $file && '..' != $file ) {
					$list[] = $file;
				}
			}
			closedir( $handle );
		} else {
			$this->PushError( 'mput', "can't open local folder", 'Cannot read folder "' . $local . '"' );
			return false;
		}
		if ( empty( $list ) ) {
			return true;
		}
		$ret = true;
		foreach ( $list as $el ) {
			if ( is_dir( $local . '/' . $el ) ) {
				$t = $this->mput( $local . '/' . $el, $remote . '/' . $el );
			} else {
				$t = $this->put( $local . '/' . $el, $remote . '/' . $el );
			}
			if ( ! $t ) {
				$ret = false;
				if ( ! $continious ) {
					break;
				}
			}
		}
		return $ret;

	}

	/**
	 * MGET.
	 *
	 * @param  [type]  $remote     Remote.
	 * @param  string  $local      Local.
	 * @param  boolean $continious Continious.
	 */
	public function mget( $remote, $local = '.', $continious = false ) {
		$list = $this->rawlist( $remote, '-lA' );
		if ( false === $list ) {
			$this->PushError( 'mget', "can't read remote folder list", "Can't read remote folder \"" . $remote . '" contents' );
			return false;
		}
		if ( empty( $list ) ) {
			return true;
		}
		if ( ! @file_exists( $local ) ) {
			if ( ! @mkdir( $local ) ) {
				$this->PushError( 'mget', "can't create local folder", 'Cannot create folder "' . $local . '"' );
				return false;
			}
		}
		foreach ( $list as $k => $v ) {
			$list[ $k ] = $this->parselisting( $v );
			if ( ! $list[ $k ] || '.' == $list[ $k ]['name'] || '..' == $list[ $k ]['name'] ) {
				unset( $list[ $k ] );
			}
		}
		$ret = true;
		foreach ( $list as $el ) {
			if ( 'd' == $el['type'] ) {
				if ( ! $this->mget( $remote . '/' . $el['name'], $local . '/' . $el['name'], $continious ) ) {
					$this->PushError( 'mget', "can't copy folder", "Can't copy remote folder \"" . $remote . '/' . $el['name'] . '" to local "' . $local . '/' . $el['name'] . '"' );
					$ret = false;
					if ( ! $continious ) {
						break;
					}
				}
			} else {
				if ( ! $this->get( $remote . '/' . $el['name'], $local . '/' . $el['name'] ) ) {
					$this->PushError( 'mget', "can't copy file", "Can't copy remote file \"" . $remote . '/' . $el['name'] . '" to local "' . $local . '/' . $el['name'] . '"' );
					$ret = false;
					if ( ! $continious ) {
						break;
					}
				}
			}
			@chmod( $local . '/' . $el['name'], $el['perms'] );
			$t = strtotime( $el['date'] );
			if ( -1 !== $t && false !== $t ) {
				@touch( $local . '/' . $el['name'], $t );
			}
		}
		return $ret;
	}
	/**
	 * MDEL.
	 *
	 * @param  [type]  $remote    Remote.
	 * @param  boolean $continious Continious
	 */
	public function mdel( $remote, $continious = false ) {
		$list = $this->rawlist( $remote, '-la' );
		if ( false === $list ) {
			$this->PushError( 'mdel', "can't read remote folder list", "Can't read remote folder \"" . $remote . '" contents' );
			return false;
		}

		foreach ( $list as $k => $v ) {
			$list[ $k ] = $this->parselisting( $v );
			if ( ! $list[ $k ] || '.' == $list[ $k ]['name'] || '..' == $list[ $k ]['name'] ) {
				unset( $list[ $k ] );
			}
		}
		$ret = true;

		foreach ( $list as $el ) {
			if ( empty( $el ) ) {
				continue;
			}

			if ( 'd' == $el['type'] ) {
				if ( ! $this->mdel( $remote . '/' . $el['name'], $continious ) ) {
					$ret = false;
					if ( ! $continious ) {
						break;
					}
				}
			} else {
				if ( ! $this->delete( $remote . '/' . $el['name'] ) ) {
					$this->PushError( 'mdel', "can't delete file", "Can't delete remote file \"" . $remote . '/' . $el['name'] . '"' );
					$ret = false;
					if ( ! $continious ) {
						break;
					}
				}
			}
		}

		if ( ! $this->rmdir( $remote ) ) {
			$this->PushError( 'mdel', "can't delete folder", "Can't delete remote folder \"" . $remote . '/' . $el['name'] . '"' );
			$ret = false;
		}
		return $ret;
	}
	/**
	 * MMK Directory.
	 *
	 * @param  [type]  $dir  Directory.
	 * @param  integer $mode Mode.
	 */
	public function mmkdir( $dir, $mode = 0777 ) {
		if ( empty( $dir ) ) {
			return false;
		}
		if ( $this->is_exists( $dir ) || '/' == $dir ) {
			return true;
		}
		if ( ! $this->mmkdir( dirname( $dir ), $mode ) ) {
			return false;
		}
		$r = $this->mkdir( $dir, $mode );
		$this->chmod( $dir, $mode );
		return $r;
	}

	/**
	 * Glob.
	 *
	 * @param  [type] $pattern Pattern.
	 * @param  [type] $handle  Handle.
	 */
	public function glob( $pattern, $handle = null ) {
		$path = $output = null;
		if ( PHP_OS == 'WIN32' ) {
			$slash = '\\';
		} else {
			$slash = '/';
		}
		$lastpos = strrpos( $pattern, $slash );
		if ( ! ( false === $lastpos ) ) {
			$path    = substr( $pattern, 0, -$lastpos - 1 );
			$pattern = substr( $pattern, $lastpos );
		} else {
			$path = getcwd();
		}
		if ( is_array( $handle ) && ! empty( $handle ) ) {
			foreach ( $handle as $dir ) {
				if ( $this->glob_pattern_match( $pattern, $dir ) ) {
					$output[] = $dir;
				}
			}
		} else {
			$handle = @opendir( $path );
			if ( false === $handle ) {
				return false;
			}
			while ( $dir = readdir( $handle ) ) {
				if ( $this->glob_pattern_match( $pattern, $dir ) ) {
					$output[] = $dir;
				}
			}
			closedir( $handle );
		}
		if ( is_array( $output ) ) {
			return $output;
		}
		return false;
	}

	/**
	 * Glob Pattern Match.
	 *
	 * @param  [type] $pattern Pattern.
	 * @param  [type] $string  String.
	 */
	public function glob_pattern_match( $pattern, $string ) {
		$out    = null;
		$chunks = explode( ';', $pattern );
		foreach ( $chunks as $pattern ) {
			$escape = array( '$', '^', '.', '{', '}', '(', ')', '[', ']', '|' );
			while ( strpos( $pattern, '**' ) !== false ) {
				$pattern = str_replace( '**', '*', $pattern );
			}
			foreach ( $escape as $probe ) {
				$pattern = str_replace( $probe, "\\$probe", $pattern );
			}
			$pattern = str_replace(
				'?*',
				'*',
				str_replace(
					'*?',
					'*',
					str_replace(
						'*',
						'.*',
						str_replace( '?', '.{1,1}', $pattern )
					)
				)
			);
			$out[]   = $pattern;
		}
		if ( count( $out ) == 1 ) {
			return( $this->glob_regexp( "^$out[0]$", $string ) );
		} else {
			foreach ( $out as $tester ) {
				if ( $this->my_regexp( "^$tester$", $string ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Glob RegExpressions.
	 *
	 * @param  [type] $pattern Pattern.
	 * @param  [type] $probe   Probe.
	 */
	public function glob_regexp( $pattern, $probe ) {
		$sensitive = ( PHP_OS != 'WIN32' );
		return ( $sensitive ?
			preg_match( '/' . preg_quote( $pattern, '/' ) . '/', $probe ) :
			preg_match( '/' . preg_quote( $pattern, '/' ) . '/i', $probe )
		);
	}

	/**
	 * Directory List.
	 *
	 * @param  [type] $remote Remote.
	 */
	public function dirlist( $remote ) {
		$list = $this->rawlist( $remote, '-la' );
		if ( false === $list ) {
			$this->PushError( 'dirlist', "can't read remote folder list", "Can't read remote folder \"" . $remote . '" contents' );
			return false;
		}

		$dirlist = array();
		foreach ( $list as $k => $v ) {
			$entry = $this->parselisting( $v );
			if ( empty( $entry ) ) {
				continue;
			}

			if ( '.' == $entry['name'] || '..' == $entry['name'] ) {
				continue;
			}

			$dirlist[ $entry['name'] ] = $entry;
		}

		return $dirlist;
	}

	/**
	 * Check Code.
	 */
	private function _checkCode() {
		return ( $this->_code < 400 && $this->_code > 0 );
	}

	/**
	 * List.
	 *
	 * @param  string $arg     Arguments.
	 * @param  string $cmd     Command.
	 * @param  string $fnction Function.
	 */
	private function _list( $arg = '', $cmd = 'LIST', $fnction = '_list' ) {
		if ( ! $this->_data_prepare() ) {
			return false;
		}
		if ( ! $this->_exec( $cmd . $arg, $fnction ) ) {
			$this->_data_close();
			return false;
		}
		if ( ! $this->_checkCode() ) {
			$this->_data_close();
			return false;
		}
		$out = '';
		if ( $this->_code < 200 ) {
			$out = $this->_data_read();
			$this->_data_close();
			if ( ! $this->_readmsg() ) {
				return false;
			}
			if ( ! $this->_checkCode() ) {
				return false;
			}
			if ( false === $out ) {
				return false;
			}
			$out = preg_split( '/[' . CRLF . ']+/', $out, -1, PREG_SPLIT_NO_EMPTY );
		}
		return $out;
	}

	// <!-- --------------------------------------------------------------------------------------- -->
	// <!-- Part: error handling                                                      -->
	// <!-- --------------------------------------------------------------------------------------- -->

	/**
	 * Generate an error for external processing of the class.
	 *
	 * @param [type]  $fctname FCTNAME.
	 * @param [type]  $msg     Message.
	 * @param boolean $desc    Description.
	 */
	function PushError( $fctname, $msg, $desc = false ) {
		$error            = array();
		$error['time']    = time();
		$error['fctname'] = $fctname;
		$error['msg']     = $msg;
		$error['desc']    = $desc;
		if ( $desc ) {
			$tmp = ' (' . $desc . ')';
		} else {
			$tmp = '';
		}
		$this->SendMSG( $fctname . ': ' . $msg . $tmp );
		return( array_push( $this->_error_array, $error ) );
	}

	/**
	 * Recover from external error.
	 */
	function PopError() {
		if ( count( $this->_error_array ) ) {
			return( array_pop( $this->_error_array ) );
		} else {
			return( false );
		}
	}
}

$mod_sockets = extension_loaded( 'sockets' );
if ( ! $mod_sockets && function_exists( 'dl' ) && is_callable( 'dl' ) ) {
	$prefix = ( PHP_SHLIB_SUFFIX == 'dll' ) ? 'php_' : '';
	@dl( $prefix . 'sockets.' . PHP_SHLIB_SUFFIX );
	$mod_sockets = extension_loaded( 'sockets' );
}

require_once __DIR__ . '/class-ftp-' . ( $mod_sockets ? 'sockets' : 'pure' ) . '.php';

if ( $mod_sockets ) {
	/**
	 * FTP Class extends FTP Sockets.
	 */
	class ftp extends ftp_sockets {}
} else {
	/**
	 * FTP Class extends FTP Pure.
	 */
	class ftp extends ftp_pure {}
}
