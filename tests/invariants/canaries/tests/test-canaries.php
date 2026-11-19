<?php
/**
 * Canaries: each test breaks a security invariant on purpose, through ../fixtures/canaries.php, and checks that it is reported.
 *
 * Each has a twin which does the same safely, and checks that nothing is
 * reported, so that an invariant which reports everything fails too.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once dirname( __DIR__ ) . '/fixtures/canaries.php';
require_once dirname( __DIR__ ) . '/fixtures/class-wp-invariants-canary-gadget.php';

/**
 * @coversNothing
 */
class Tests_Invariants_Canaries extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		if ( ! getenv( 'WP_INVARIANTS_CANARIES' ) ) {
			$this->markTestSkipped( 'The canaries need WP_INVARIANTS_CANARIES=1, so that their fixtures count as code under test.' );
		}
	}

	public function tear_down() {
		$_GET     = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * Asserts how often an invariant was violated in this test.
	 *
	 * @param string $id       Invariant id, such as `output/request-values-stay-text`.
	 * @param bool   $violated Whether it must have been violated.
	 */
	private function assertViolated( $id, $violated = true ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Named as PHPUnit's assertions are.
		if ( ! isset( WP_Invariant::all()[ $id ] ) ) {
			$this->markTestSkipped( "{$id} is not loaded, as with WP_INVARIANTS_SKIP." );
		}

		$violations = WP_Invariant_Violations::in_test( $id );
		if ( $violated ) {
			$this->assertNotEmpty( $violations, "{$id} was not reported." );
		} else {
			$this->assertSame( array(), array_column( $violations, 'message' ), "{$id} was reported." );
		}
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_output_request_values_stay_text( $safely ) {
		$_GET['s'] = '<img src=x onerror=alert(1)>';
		ob_start();
		wp_invariants_canary_print_search( $safely );
		ob_get_clean();

		$this->assertViolated( 'output/request-values-stay-text', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_wpdb_request_values_stay_literals( $safely ) {
		if ( ! method_exists( $GLOBALS['wpdb'], '_do_query' ) || defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			$this->markTestSkipped( 'This invariant needs MySQL.' );
		}

		$_GET['t'] = "x' OR '1'='1";
		wp_invariants_canary_query_title( $safely );

		$this->assertViolated( 'wpdb/request-values-stay-literals', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_request_writes_check_a_nonce_and_a_capability( $safely ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['nonce'] = wp_create_nonce( 'canary' );
		add_action(
			'wp_ajax_wp_invariants_canary',
			static function () use ( $safely ) {
				wp_invariants_canary_change_option( $safely );
			}
		);

		do_action( 'wp_ajax_wp_invariants_canary' );

		$this->assertViolated( 'request/writes-check-a-nonce', ! $safely );
		$this->assertViolated( 'request/writes-check-a-capability', ! $safely );
	}

	public function test_request_rest_writes_check_a_capability() {
		$this->add_route(
			'/change',
			array(
				'methods'             => 'POST',
				'callback'            => 'wp_invariants_canary_rest_change',
				'permission_callback' => '__return_true',
			)
		);

		rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/wp-invariants-canary/v1/change' ) );

		$this->assertViolated( 'request/writes-check-a-capability' );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_uploads_nothing_executable( $safely ) {
		$path = wp_invariants_canary_write_upload( $safely ? 'canary.jpg' : 'canary.php.jpg', 'GIF89a' );
		unlink( $path );

		$this->assertViolated( 'uploads/nothing-executable', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_archives_extract_inside_destination( $safely ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$to   = get_temp_dir() . uniqid( 'wp-invariants-canary-' ) . '/';
		$path = $safely ? "{$to}inside.txt" : get_temp_dir() . uniqid( 'wp-invariants-canary-outside-' ) . '.txt';
		mkdir( $to );
		add_filter(
			'pre_unzip_file',
			static function () use ( $path ) {
				return wp_invariants_canary_write_during_unzip( $path );
			}
		);

		unzip_file( DIR_TESTDATA . '/filesystem/archive-comment.zip', $to );
		unlink( $path );
		$GLOBALS['wp_filesystem']->delete( $to, true );

		$this->assertViolated( 'archives/extract-inside-destination', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_serialize_no_foreign_objects( $safely ) {
		$data = $safely ? serialize( new WP_Invariants_Canary_Gadget() ) : 'O:27:"WP_Invariants_Canary_Gadget":0:{}'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		wp_invariants_canary_unserialize( $data );

		$this->assertViolated( 'serialize/no-foreign-objects', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_kses_stored_html_is_filtered( $safely ) {
		wp_set_current_user( 0 );
		kses_init_filters();

		$content = '<p>Hello</p><script>alert(1)</script>';
		if ( $safely ) {
			wp_insert_post(
				array(
					'post_content' => $content,
					'post_title'   => 'canary',
				)
			);
		} else {
			wp_invariants_canary_store_content( $content );
		}

		$this->assertViolated( 'kses/stored-html-is-filtered', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_random_secrets_use_csprng( $safely ) {
		add_filter(
			'random_password',
			static function ( $password ) use ( $safely ) {
				return wp_invariants_canary_random( $safely, $password );
			}
		);

		wp_generate_password();

		$this->assertViolated( 'random/secrets-use-csprng', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_mail_no_header_injection( $safely ) {
		reset_phpmailer_instance();
		wp_invariants_canary_mail( $safely ? 'Hello' : "Hello\r\nBcc: victim@example.org" );

		$this->assertViolated( 'mail/no-header-injection', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_secrets_never_sent( $safely ) {
		update_option( 'wp_invariants_canary_user', self::factory()->user->create() );
		$this->add_route(
			'/user',
			array(
				'methods'             => 'GET',
				'callback'            => static function () use ( $safely ) {
					return wp_invariants_canary_rest_user( ! $safely );
				},
				'permission_callback' => '__return_true',
			)
		);

		rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp-invariants-canary/v1/user' ) );

		$this->assertViolated( 'secrets/never-sent', ! $safely );
	}

	/**
	 * @dataProvider data_safely
	 *
	 * @param bool $safely Whether to do it safely.
	 */
	public function test_kses_input_not_escaped( $safely ) {
		wp_invariants_canary_kses_escaped( ! $safely );

		$this->assertViolated( 'kses/input-not-escaped', ! $safely );
	}

	public function test_pcre_matches_no_html_syntax() {
		wp_invariants_canary_regex_html();

		$this->assertViolated( 'pcre/matches-no-html-syntax' );
	}

	public function test_pcre_matches_no_html_syntax_leaves_out_known_parsers() {
		wpautop( "<p>Hello</p>\n\n<div class=\"x\">World</div>" );
		wptexturize( '<p class="a">"Quoted" text</p>' );
		wp_html_split( '<p class="a">Hello</p><!-- c -->' );

		$this->assertViolated( 'pcre/matches-no-html-syntax', false );
	}

	/**
	 * Registers a REST route of the canaries, as REST routes are registered, on rest_api_init.
	 *
	 * @param string $route Route, such as `/user`.
	 * @param array  $args  Its arguments.
	 */
	private function add_route( $route, array $args ) {
		add_action(
			'rest_api_init',
			static function () use ( $route, $args ) {
				register_rest_route( 'wp-invariants-canary/v1', $route, $args );
			}
		);
		$GLOBALS['wp_rest_server'] = null;
	}

	/**
	 * @return array[]
	 */
	public static function data_safely() {
		return array(
			'unsafely' => array( false ),
			'safely'   => array( true ),
		);
	}
}
