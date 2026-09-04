<?php

/**
 * @group ms-required
 * @group multisite
 *
 * @covers ::wpmu_validate_blog_signup
 */
class Tests_Multisite_wpmuValidateBlogSignup extends WP_UnitTestCase {

	protected static $super_admin_id;

	protected static $existing_user_login = 'existinguserfoo';
	protected static $existing_user_id;

	protected static $existing_blog_name = 'existingsitefoo';
	protected static $existing_blog_id;

	protected $minimum_site_name_length = 4;

	private $original_home = null;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$super_admin_id = $factory->user->create();
		grant_super_admin( self::$super_admin_id );

		self::$existing_user_id = $factory->user->create( array( 'user_login' => self::$existing_user_login ) );

		$network = get_network();

		if ( is_subdomain_install() ) {
			$domain = self::$existing_blog_name . '.' . preg_replace( '|^www\.|', '', $network->domain );
			$path   = $network->path;
		} else {
			$domain = $network->domain;
			$path   = $network->path . self::$existing_blog_name . '/';
		}

		self::$existing_blog_id = $factory->blog->create(
			array(
				'domain'     => $domain,
				'path'       => $path,
				'network_id' => $network->id,
			)
		);
	}

	public static function wpTearDownAfterClass() {
		revoke_super_admin( self::$super_admin_id );
		wpmu_delete_user( self::$super_admin_id );

		wpmu_delete_user( self::$existing_user_id );

		wp_delete_site( self::$existing_blog_id );
	}

	public function tear_down() {
		if ( null !== $this->original_home ) {
			update_blog_option( get_network()->site_id, 'home', $this->original_home );
			$this->original_home = null;
		}

		remove_filter( 'wpmu_signup_blog_url', array( $this, 'filter_signup_blog_url' ) );

		parent::tear_down();
	}

	/**
	 * @dataProvider data_validate_blogname
	 */
	public function test_validate_blogname( $blog_name, $error_message ) {
		$result = wpmu_validate_blog_signup( $blog_name, 'Foo Site Title', get_userdata( self::$super_admin_id ) );
		$this->assertContains( 'blogname', $result['errors']->get_error_codes(), $error_message );
	}

	public function data_validate_blogname() {
		$data = array(
			array( '', 'Site names must not be empty.' ),
			array( 'foo-hello', 'Site names must not contain hyphens.' ),
			array( 'foo_hello', 'Site names must not contain underscores.' ),
			array( 'foo hello', 'Site names must not contain spaces.' ),
			array( 'FooHello', 'Site names must not contain uppercase letters.' ),
			array( '12345678', 'Site names must not consist of numbers only.' ),
			array( self::$existing_blog_name, 'Site names must not collide with an existing site name.' ),
			array( self::$existing_user_login, 'Site names must not collide with an existing user login.' ),
			array( 'foo', 'Site names must at least contain 4 characters.' ),
		);

		$illegal_names = get_site_option( 'illegal_names' );
		if ( ! empty( $illegal_names ) ) {
			$data[] = array( array_shift( $illegal_names ), 'Illegal site names are not allowed.' );
		} else {
			$data[] = array( 'www', 'Illegal site names are not allowed.' );
		}

		return $data;
	}

	public function test_validate_empty_blog_title() {
		$result = wpmu_validate_blog_signup( 'uniqueblogname1234', '', get_userdata( self::$super_admin_id ) );
		$this->assertContains( 'blog_title', $result['errors']->get_error_codes(), 'Site titles must not be empty.' );
	}

	public function test_validate_blogname_from_same_existing_user() {
		$result = wpmu_validate_blog_signup( self::$existing_user_login, 'Foo Site Title', get_userdata( self::$existing_user_id ) );
		$this->assertEmpty( $result['errors']->get_error_codes() );
	}

	/**
	 * @ticket 39676
	 *
	 * @dataProvider data_filter_minimum_site_name_length
	 */
	public function test_filter_minimum_site_name_length( $site_name, $minimum_length, $expect_error ) {
		$this->minimum_site_name_length = $minimum_length;
		add_filter( 'minimum_site_name_length', array( $this, 'filter_minimum_site_name_length' ) );

		$result = wpmu_validate_blog_signup( $site_name, 'Site Title', get_userdata( self::$super_admin_id ) );

		remove_filter( 'minimum_site_name_length', array( $this, 'filter_minimum_site_name_length' ) );
		$this->minimum_site_name_length = 4;

		if ( $expect_error ) {
			$this->assertContains( 'blogname', $result['errors']->get_error_codes() );
		} else {
			$this->assertEmpty( $result['errors']->get_error_codes() );
		}
	}

	public function data_filter_minimum_site_name_length() {
		return array(
			array( 'fooo', 5, true ),
			array( 'foooo', 5, false ),
			array( 'foo', 4, true ),
			array( 'fooo', 4, false ),
			array( 'fo', 3, true ),
			array( 'foo', 3, false ),
		);
	}

	public function filter_minimum_site_name_length() {
		return $this->minimum_site_name_length;
	}

	/**
	 * @ticket 43667
	 */
	public function test_signup_nonce_check() {
		$original_php_self       = $_SERVER['PHP_SELF'];
		$_SERVER['PHP_SELF']     = '/wp-signup.php';
		$_POST['signup_form_id'] = 'blog-signup-form';
		$_POST['_signup_form']   = wp_create_nonce( 'signup_form_' . $_POST['signup_form_id'] );

		$valid               = wpmu_validate_blog_signup( 'my-nonce-site', 'Site Title', get_userdata( self::$super_admin_id ) );
		$_SERVER['PHP_SELF'] = $original_php_self;

		$this->assertNotContains( 'invalid_nonce', $valid['errors']->get_error_codes() );
	}

	/**
	 * @ticket 43667
	 */
	public function test_signup_nonce_check_invalid() {
		$original_php_self       = $_SERVER['PHP_SELF'];
		$_SERVER['PHP_SELF']     = '/wp-signup.php';
		$_POST['signup_form_id'] = 'blog-signup-form';
		$_POST['_signup_form']   = wp_create_nonce( 'invalid' );

		$valid               = wpmu_validate_blog_signup( 'my-nonce-site', 'Site Title', get_userdata( self::$super_admin_id ) );
		$_SERVER['PHP_SELF'] = $original_php_self;

		$this->assertContains( 'invalid_nonce', $valid['errors']->get_error_codes() );
	}

	/**
	 * @ticket 31076
	 * @covers ::wpmu_get_signup_blog_url
	 */
	public function test_uses_main_site_scheme_for_subdirectory_installs() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'This test applies to subdirectory installs only.' );
		}

		$network             = get_network();
		$this->original_home = get_blog_option( $network->site_id, 'home' );

		update_blog_option( $network->site_id, 'home', set_url_scheme( $this->original_home, 'https' ) );

		$this->assertSame(
			'https://' . $network->domain . $network->path . 'newsite/',
			wpmu_get_signup_blog_url( $network->domain, $network->path . 'newsite/' )
		);
	}

	/**
	 * @ticket 31076
	 * @covers ::wpmu_get_signup_blog_url
	 */
	public function test_defaults_to_http_for_subdomain_installs() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'This test applies to subdomain installs only.' );
		}

		$this->assertSame(
			'http://newsite.example.org/',
			wpmu_get_signup_blog_url( 'newsite.example.org', '/' )
		);
	}


	/**
	 * @ticket 31076
	 * @covers ::wpmu_signup_blog_notification
	 */
	public function test_signup_blog_notification_uses_signup_blog_url() {
		reset_phpmailer_instance();
		add_filter( 'wpmu_signup_blog_url', array( $this, 'filter_signup_blog_url' ) );

		wpmu_signup_blog_notification(
			'newsite.example.org',
			'/',
			'New Site',
			'newuser',
			'newuser@example.org',
			'activation-key'
		);

		$mailer = tests_retrieve_phpmailer_instance();
		$email  = $mailer->get_sent();

		$this->assertStringContainsString( 'wp-activate.php?key=activation-key', $email->body );
		$this->assertStringContainsString( 'https://newsite.example.org/', $email->body );
		$this->assertStringContainsString( 'https://newsite.example.org/', $email->subject );

		reset_phpmailer_instance();
	}

	public function filter_signup_blog_url( $url ) {
		return set_url_scheme( $url, 'https' );
	}
}
