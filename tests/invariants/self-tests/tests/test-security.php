<?php
/**
 * Self-tests of what the security invariants share: request values, addresses, serialized data, files and call scopes.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Tests_Invariants_Security extends TestCase {

	/**
	 * The superglobals before each test, which the tests change.
	 *
	 * @var array
	 */
	private $saved;

	public function setUp(): void {
		$this->saved = array( $_GET, $_POST, $_COOKIE, $_REQUEST, $_SERVER );
		WP_Invariant_Request::reset();
	}

	public function tearDown(): void {
		list( $_GET, $_POST, $_COOKIE, $_REQUEST, $_SERVER ) = $this->saved;
		WP_Invariant_Request::reset();
	}

	/**
	 * @dataProvider data_internal_addresses
	 *
	 * @param string      $ip       Address.
	 * @param string|null $expected Kind, or null for a public address.
	 */
	public function test_internal_address_knows_every_form_of_an_internal_address( $ip, $expected ) {
		$this->assertSame( $expected, WP_Invariant_Security::internal_address( $ip ) );
	}

	/**
	 * @return array[]
	 */
	public static function data_internal_addresses() {
		return array(
			'IPv4 loopback'          => array( '127.0.0.1', 'loopback' ),
			'IPv4 loopback range'    => array( '127.255.0.9', 'loopback' ),
			'IPv6 loopback'          => array( '::1', 'loopback' ),
			'IPv4-mapped loopback'   => array( '::ffff:127.0.0.1', 'loopback' ),
			'private 10/8'           => array( '10.1.2.3', 'private' ),
			'private 172.16/12'      => array( '172.31.255.255', 'private' ),
			'public 172.32'          => array( '172.32.0.1', null ),
			'private 192.168/16'     => array( '192.168.0.1', 'private' ),
			'metadata service'       => array( '169.254.169.254', 'link-local, the cloud metadata service' ),
			'metadata through NAT64' => array( '64:ff9b::a9fe:a9fe', 'link-local, the cloud metadata service' ),
			'carrier-grade NAT'      => array( '100.64.0.1', 'carrier-grade NAT' ),
			'unspecified'            => array( '0.0.0.0', 'unspecified' ),
			'IPv6 unspecified'       => array( '::', 'unspecified' ),
			'IPv6 unique local'      => array( 'fd12::1', 'unique local' ),
			'IPv6 link-local'        => array( 'fe80::1', 'link-local' ),
			'public IPv4'            => array( '93.184.216.34', null ),
			'public IPv6'            => array( '2606:4700::1', null ),
			'not an address'         => array( 'example.org', null ),
		);
	}

	public function test_serialized_classes_reads_objects_but_not_strings_which_look_like_them() {
		$data = serialize(
			array(
				new ArrayObject( array( 1 ) ),
				'O:8:"stdClass":0:{}',
				(object) array( 'inner' => new SplObjectStorage() ),
			)
		);

		$this->assertSame( array( 'ArrayObject', 'stdClass', 'SplObjectStorage' ), WP_Invariant_Security::serialized_classes( $data ) );
	}

	public function test_gadget_classes_are_loaded_classes_with_magic_methods_which_unserialize_would_create() {
		$data = serialize( array( new ArrayObject(), new stdClass(), new WP_Invariants_Fixture() ) );

		$this->assertSame( array( 'ArrayObject' ), WP_Invariant_Security::gadget_classes( $data ), 'ArrayObject has __unserialize(); stdClass and the fixture have no magic methods.' );
		$this->assertSame( array(), WP_Invariant_Security::gadget_classes( $data, false ), 'allowed_classes false creates no objects.' );
		$this->assertSame( array(), WP_Invariant_Security::gadget_classes( $data, array( 'stdClass' ) ), 'Classes left out of allowed_classes are not created.' );
		$this->assertSame( array(), WP_Invariant_Security::gadget_classes( 'O:22:"Not_Loaded_Gadget_Class":0:{}' ), 'A class which is not loaded is not loaded to find out.' );
	}

	/**
	 * @dataProvider data_executable_names
	 *
	 * @param string $name       File name.
	 * @param bool   $executable Whether it could run as code.
	 */
	public function test_executable_name_finds_code_by_any_extension( $name, $executable ) {
		$why = WP_Invariant_Security::executable_name( $name );
		$this->assertSame( $executable, is_string( $why ) );
	}

	/**
	 * @return array[]
	 */
	public static function data_executable_names() {
		return array(
			'PHP'                      => array( 'shell.php', true ),
			'PHP among the extensions' => array( 'photo.php.jpg', true ),
			'upper case'               => array( 'x.PHTML', true ),
			'trailing space'           => array( 'shell.php ', true ),
			'PHAR'                     => array( 'x.phar', true ),
			'a path'                   => array( '/uploads/2024/01/a.php5', true ),
			'.htaccess'                => array( '.htaccess', true ),
			'.user.ini in a directory' => array( 'dir/.user.ini', true ),
			'a name which is just php' => array( 'php.jpg', false ),
			'an image'                 => array( 'photo.jpg', false ),
			'php inside an extension'  => array( 'archive.phpx', false ),
		);
	}

	public function test_executable_contents_finds_php_and_phar_archives() {
		$this->assertNotNull( WP_Invariant_Security::executable_contents( "\xEF\xBB\xBF <?php echo 1;" ) );
		$this->assertNotNull( WP_Invariant_Security::executable_contents( '<?= 1 ?>' ) );
		$this->assertNotNull( WP_Invariant_Security::executable_contents( "GIF89a\0\0<?php __HALT_COMPILER(); ?>" ) );
		$this->assertNull( WP_Invariant_Security::executable_contents( "GIF89a\0\0" ) );
		$this->assertNull( WP_Invariant_Security::executable_contents( '<?xml version="1.0"?><svg/>' ) );
	}

	public function test_is_write_mode() {
		$this->assertFalse( WP_Invariant_Security::is_write_mode( 'rb' ) );
		foreach ( array( 'w', 'wb', 'a', 'x', 'c+', 'r+' ) as $mode ) {
			$this->assertTrue( WP_Invariant_Security::is_write_mode( $mode ), $mode );
		}
	}

	public function test_request_values_are_long_enough_strings_as_sent_and_unslashed() {
		$_GET     = array(
			'q'    => 'it\\\'s <b>',
			'n'    => '1',
			'list' => array( 'nested "value"' ),
		);
		$_POST    = array();
		$_COOKIE  = array();
		$_REQUEST = array();
		$_SERVER  = array(
			'REQUEST_URI'     => '/?q=x',
			'HTTP_USER_AGENT' => 'Agent "quoted"',
			'SCRIPT_NAME'     => '/not/a/request/value.php',
		);

		$values = WP_Invariant_Request::values();
		$this->assertContains( 'it\\\'s <b>', $values );
		$this->assertContains( 'it\'s <b>', $values, 'The superglobals are slashed, so values are followed unslashed too.' );
		$this->assertContains( 'nested "value"', $values );
		$this->assertContains( 'Agent "quoted"', $values );
		$this->assertContains( '/?q=x', $values );
		$this->assertNotContains( '1', $values, 'Values shorter than 4 bytes are not followed.' );
		$this->assertNotContains( '/not/a/request/value.php', $values );

		$this->assertSame( array( 'it\\\'s <b>', 'it\'s <b>' ), WP_Invariant_Request::values( '<' ) );
	}

	public function test_request_values_follow_changes_and_rest_requests() {
		$_GET = array( 'first' => 'first value' );
		$this->assertContains( 'first value', WP_Invariant_Request::values() );

		$_GET = array( 'second' => 'second value' );
		$this->assertContains( 'second value', WP_Invariant_Request::values() );
		$this->assertNotContains( 'first value', WP_Invariant_Request::values() );

		WP_Invariant_Request::start_rest_request( array( 'title' => 'a REST title' ) );
		$this->assertContains( 'a REST title', WP_Invariant_Request::values() );
		WP_Invariant_Request::end_rest_request();
		$this->assertNotContains( 'a REST title', WP_Invariant_Request::values() );
	}

	public function test_occurrences() {
		$this->assertSame( array( 0, 4, 8 ), WP_Invariant_Request::occurrences( 'abc abc abc', 'abc' ) );
		$this->assertSame( array( 0, 4 ), WP_Invariant_Request::occurrences( 'abc abc abc', 'abc', 2 ) );
		$this->assertSame( array(), WP_Invariant_Request::occurrences( 'abc', 'x' ) );
	}

	public function test_paths_resolve_inside_directories_through_dot_dot_and_symbolic_links() {
		$directory = sys_get_temp_dir() . '/wp-invariants-paths-' . getmypid();
		mkdir( "{$directory}/inside", 0777, true );
		symlink( "{$directory}/inside", "{$directory}/link" );

		$inside = WP_Invariant::directory_forms( "{$directory}/inside" );
		$this->assertTrue( WP_Invariant::path_resolves_inside( "{$directory}/inside/a/new/file.txt", $inside ) );
		$this->assertTrue( WP_Invariant::path_resolves_inside( "{$directory}/link/file.txt", $inside ), 'Through a symbolic link.' );
		$this->assertFalse( WP_Invariant::path_resolves_inside( "{$directory}/inside/../file.txt", $inside ), 'A .. climbs out.' );
		$this->assertFalse( WP_Invariant::path_resolves_inside( "{$directory}/inside-not/file.txt", $inside ), 'A directory whose name starts the same is another directory.' );
		$this->assertTrue( WP_Invariant::path_resolves_inside( "{$directory}/inside", $inside ), 'The directory itself.' );

		unlink( "{$directory}/link" );
		rmdir( "{$directory}/inside" );
		rmdir( $directory );
	}

	public function test_functions_calling_finds_the_functions_whose_own_code_calls_a_function() {
		$this->assertSame(
			array( 'WP_Invariants_Calls_Fixture::run', 'wp_invariants_calls_switches' ),
			WP_Invariant::functions_calling( array( dirname( __DIR__ ) . '/fixtures/calls' ), 'switch_to_blog' )
		);
	}

	public function test_a_frame_mark_tells_whether_its_call_is_still_running() {
		$mark = $this->mark_inside();
		$this->assertIsInt( $mark );
		$this->assertFalse( WP_Invariant::running( $mark, __CLASS__ . '::mark_inside' ), 'The call returned.' );

		$this->assertTrue( $this->check_inside(), 'The call is running.' );
		$this->assertNull( WP_Invariant::frame_mark( 'no_such_function' ) );
	}

	/**
	 * Returns a mark for itself.
	 *
	 * @return int|null
	 */
	private function mark_inside() {
		return WP_Invariant::frame_mark( __METHOD__ );
	}

	/**
	 * Returns whether a mark it makes for itself is running, from a call inside it.
	 *
	 * @return bool
	 */
	private function check_inside() {
		$mark = WP_Invariant::frame_mark( __METHOD__ );
		return ( static function () use ( $mark ) {
			return WP_Invariant::running( $mark, __CLASS__ . '::check_inside' );
		} )();
	}
}
