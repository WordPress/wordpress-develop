<?php
/**
 * Tests for {@see wp_prefetch_admin_assets()}.
 *
 * Prefetching only happens when concatenation is off, which is decided by the `CONCATENATE_SCRIPTS`
 * and `SCRIPT_DEBUG` constants. A constant cannot be undefined again, so every test defines
 * `CONCATENATE_SCRIPTS` in a separate process. `SCRIPT_DEBUG` is already defined by the time a test
 * runs, as on when running from `src/`, so tests whose outcome depends on it are skipped when it
 * has the other value.
 *
 * @todo Once concatenation is retired in https://core.trac.wordpress.org/ticket/57548, remove the
 *       `CONCATENATE_SCRIPTS` constant and the tests that depend on it, and run the rest in the
 *       main process rather than in separate ones, which are slow.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 *
 * @covers ::wp_prefetch_admin_assets
 */
class Tests_Dependencies_WpPrefetchAdminAssets extends WP_UnitTestCase {

	/**
	 * Tests that nothing is prefetched when the admin concatenates its assets.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_prints_nothing_when_concatenating(): void {
		define( 'CONCATENATE_SCRIPTS', true );

		if ( SCRIPT_DEBUG ) {
			$this->markTestSkipped( 'SCRIPT_DEBUG is on, which turns off concatenation.' );
		}

		$this->assertSame( array(), $this->get_prefetched_on_login() );
	}

	/**
	 * Tests that `SCRIPT_DEBUG` turns off concatenation, and so turns on prefetching, even when
	 * `CONCATENATE_SCRIPTS` is on.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_script_debug_overrides_concatenation(): void {
		define( 'CONCATENATE_SCRIPTS', true );

		if ( ! SCRIPT_DEBUG ) {
			$this->markTestSkipped( 'SCRIPT_DEBUG is off.' );
		}

		$this->assertNotSame( array(), $this->get_prefetched_on_login() );
	}

	/**
	 * Tests what the login screen prefetches for the admin: the render-blocking head scripts and the
	 * admin-wide stylesheets, but not the editor's stylesheets or the per-user color scheme.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_login_prefetches_render_blocking_admin_assets(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$links = $this->get_prefetched_on_login();

		$this->assertPrefetched( $links, 'script', '#/wp-includes/js/jquery/jquery(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'script', '#/wp-includes/js/jquery/jquery-migrate(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'script', '#/wp-includes/js/utils(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dashicons(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/buttons(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/admin-bar(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/commands/style(\.min)?\.css#' );

		// Footer scripts are not prefetched.
		$this->assertNotPrefetched( $links, '#/wp-admin/js/common(\.min)?\.js#' );
		$this->assertNotPrefetched( $links, '#/hoverIntent(\.min)?\.js#' );

		// Neither is the color scheme, which depends on the user.
		$this->assertNotPrefetched( $links, '#/wp-admin/css/colors/#' );

		// Nor the editor, which the login is not leading to.
		$this->assertNotPrefetched( $links, '#/wp-includes/css/dist/edit-post/#' );
	}

	/**
	 * Tests that a login leading to the block editor also prefetches the editor's stylesheets.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_editor_destinations
	 *
	 * @param string $redirect_to Where the login redirects to.
	 * @param bool   $is_editor   Whether that is the block editor.
	 */
	public function test_login_prefetches_editor_assets_for_editor_destination( string $redirect_to, bool $is_editor ): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => $redirect_to ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );

		if ( $is_editor ) {
			$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
			$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/block-editor/content(\.min)?\.css#' );
			$this->assertPrefetched( $links, 'style', '#/wp-includes/css/media-views(\.min)?\.css#' );
		} else {
			$this->assertNotPrefetched( $links, '#/wp-includes/css/dist/edit-post/#' );
		}
	}

	/**
	 * Data provider for {@see self::test_login_prefetches_editor_assets_for_editor_destination()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: bool }>
	 */
	public function data_editor_destinations(): array {
		return array(
			'Dashboard'       => array( '/wp-admin/', false ),
			'new post'        => array( '/wp-admin/post-new.php', true ),
			'new page'        => array( '/wp-admin/post-new.php?post_type=page', true ),
			'editing a post'  => array( '/wp-admin/post.php?post=1&action=edit', true ),
			'trashing a post' => array( '/wp-admin/post.php?post=1&action=trash', false ),
			'post list'       => array( '/wp-admin/edit.php', false ),
		);
	}

	/**
	 * Tests that an absolute `redirect_to` pointing at this site's editor also prefetches the
	 * editor's stylesheets.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_login_prefetches_editor_assets_for_absolute_editor_url(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => admin_url( 'post-new.php' ) ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
	}

	/**
	 * Tests that nothing is prefetched from login screens that do not lead to the admin.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_login_requests_not_leading_to_admin
	 *
	 * @param array<string, string> $request       Request parameters of the login screen.
	 * @param string|null           $action        Action as resolved by wp-login.php.
	 * @param bool                  $interim_login Whether the interim login modal is displayed.
	 */
	public function test_login_prints_nothing_when_not_leading_to_admin( array $request, ?string $action, bool $interim_login ): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$this->assertSame( array(), $this->get_prefetched_on_login( $request, $action, $interim_login ) );
	}

	/**
	 * Data provider for {@see self::test_login_prints_nothing_when_not_leading_to_admin()}.
	 *
	 * The action is the one wp-login.php resolves for the request.
	 *
	 * @return array<non-falsy-string, array{ 0: array<string, string>, 1: string|null, 2: bool }>
	 */
	public function data_login_requests_not_leading_to_admin(): array {
		return array(
			'lost password'              => array( array( 'action' => 'lostpassword' ), 'lostpassword', false ),
			'registration'               => array( array( 'action' => 'register' ), 'register', false ),
			'logout'                     => array( array( 'action' => 'logout' ), 'logout', false ),
			'password reset key'         => array(
				array(
					'key'   => 'abc',
					'login' => 'admin',
				),
				'resetpass',
				false,
			),
			'check email'                => array( array( 'checkemail' => 'confirm' ), 'checkemail', false ),
			'check email after register' => array( array( 'checkemail' => 'registered' ), 'checkemail', false ),
			'interim login'              => array( array( 'interim-login' => '1' ), 'login', true ),
			'login_head fired by plugin' => array( array(), null, false ),
			'front end redirect'         => array( array( 'redirect_to' => '/hello-world/' ), 'login', false ),
			'lookalike admin path'       => array( array( 'redirect_to' => '/wp-admin-lookalike/' ), 'login', false ),
		);
	}

	/**
	 * Tests that nothing is prefetched when an absolute `redirect_to` points at this site's front end.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_login_prints_nothing_for_absolute_front_end_redirect(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$this->assertSame( array(), $this->get_prefetched_on_login( array( 'redirect_to' => home_url( '/hello-world/' ) ) ) );
	}

	/**
	 * Tests that the action wp-login.php resolved is used rather than the request parameter, so an
	 * action wp-login.php does not recognize, which it treats as the login form, still prefetches.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_login_uses_action_resolved_by_wp_login(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$links = $this->get_prefetched_on_login( array( 'action' => 'unrecognized' ), 'login' );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that a `redirect_to` pointing off-site falls back to the admin, as wp_safe_redirect() does.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_login_off_site_redirect_falls_back_to_admin(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$filter = new MockAction();
		add_filter( 'prefetch_admin_assets', array( $filter, 'filter' ), 10, 2 );

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => 'https://elsewhere.example.com/wp-admin/post-new.php' ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertNotPrefetched( $links, '#/wp-includes/css/dist/edit-post/#' );
		$this->assertSame( admin_url(), $filter->get_args()[0][1] );
	}

	/**
	 * Tests that nothing is prefetched when the login lands in the admin of another allowed host,
	 * such as another site on a multisite network, which would request its assets from its own host.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_login_prints_nothing_for_admin_on_another_allowed_host(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		add_filter(
			'allowed_redirect_hosts',
			static function ( array $hosts ): array {
				$hosts[] = 'another.example.net';
				return $hosts;
			}
		);

		$this->assertSame(
			array(),
			$this->get_prefetched_on_login( array( 'redirect_to' => 'http://another.example.net/wp-admin/post-new.php' ) )
		);
	}

	/**
	 * Tests that nothing is prefetched when the login lands in an admin on this host but another port,
	 * which wp_validate_redirect() allows since it compares only the host.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_login_prints_nothing_for_admin_on_another_port(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$scheme = (string) wp_parse_url( admin_url(), PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( admin_url(), PHP_URL_HOST );
		$port   = (int) wp_parse_url( admin_url(), PHP_URL_PORT );
		$path   = (string) wp_parse_url( admin_url(), PHP_URL_PATH );

		// Any port other than the admin's own, which is the scheme's default when none is given.
		$other_port = ( $port ? $port : ( 'https' === $scheme ? 443 : 80 ) ) + 1;

		$this->assertSame(
			array(),
			$this->get_prefetched_on_login( array( 'redirect_to' => "{$scheme}://{$host}:{$other_port}{$path}" ) )
		);
	}

	/**
	 * Tests that the Dashboard and the post list tables prefetch only the editor's stylesheets.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_admin_screens_leading_to_editor
	 *
	 * @param string $screen    Screen ID.
	 * @param string $post_type Post type of the editor expected to be prefetched for.
	 */
	public function test_admin_screen_prefetches_editor_assets( string $screen, string $post_type ): void {
		define( 'CONCATENATE_SCRIPTS', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$filter = new MockAction();
		add_filter( 'prefetch_admin_assets', array( $filter, 'filter' ), 10, 2 );

		$links = $this->get_prefetched_on_admin_screen( $screen );

		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
		$this->assertSame( array(), $this->get_hrefs( $links, 'script' ), 'No scripts should be prefetched for the editor.' );
		$this->assertSame( add_query_arg( 'post_type', $post_type, admin_url( 'post-new.php' ) ), $filter->get_args()[0][1] );
	}

	/**
	 * Data provider for {@see self::test_admin_screen_prefetches_editor_assets()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: string }>
	 */
	public function data_admin_screens_leading_to_editor(): array {
		return array(
			'Dashboard'  => array( 'dashboard', 'post' ),
			'Posts list' => array( 'edit', 'post' ),
			'Pages list' => array( 'edit-page', 'page' ),
		);
	}

	/**
	 * Tests that admin screens other than the site's Dashboard and post list tables prefetch nothing.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_other_admin_screens
	 *
	 * @param string $screen Screen ID.
	 */
	public function test_other_admin_screen_prints_nothing( string $screen ): void {
		define( 'CONCATENATE_SCRIPTS', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( $screen ) );
	}

	/**
	 * Data provider for {@see self::test_other_admin_screen_prints_nothing()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public function data_other_admin_screens(): array {
		return array(
			'Plugins'                 => array( 'plugins' ),
			'Network Admin Dashboard' => array( 'dashboard-network' ),
			'User Admin Dashboard'    => array( 'dashboard-user' ),
		);
	}

	/**
	 * Tests that nothing is prefetched for a user who cannot create posts of the type listed.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_admin_screen_prints_nothing_for_user_who_cannot_create_posts(): void {
		define( 'CONCATENATE_SCRIPTS', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'dashboard' ) );
	}

	/**
	 * Tests that nothing is prefetched for a post type that uses the classic editor.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_admin_screen_prints_nothing_for_classic_editor(): void {
		define( 'CONCATENATE_SCRIPTS', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'use_block_editor_for_post_type', '__return_false' );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'edit' ) );
	}

	/**
	 * Tests that assets the current screen has already printed are not prefetched again.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_skips_assets_already_printed(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		wp_styles()->done[]  = 'common';
		wp_scripts()->done[] = 'utils';

		$links = $this->get_prefetched_on_login();

		$this->assertNotPrefetched( $links, '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertNotPrefetched( $links, '#/wp-includes/js/utils(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/forms(\.min)?\.css#' );
	}

	/**
	 * Tests that a right-to-left locale prefetches the right-to-left stylesheets.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_prefetches_rtl_stylesheets(): void {
		define( 'CONCATENATE_SCRIPTS', false );
		wp_styles()->text_direction = 'rtl';

		$links = $this->get_prefetched_on_login();

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common-rtl(\.min)?\.css#' );
		$this->assertNotPrefetched( $links, '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that stylesheet URLs reach the filter unescaped, like script URLs, so that a callback
	 * appending the plain form of one is collapsed with it.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_receives_unescaped_stylesheet_urls(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		// Give a stylesheet a query string of its own, so its URL has an `&` before the version.
		wp_styles()->registered['common']->src = '/wp-admin/css/common.css?color=blue';

		$common_href = null;
		add_filter(
			'prefetch_admin_assets',
			static function ( array $resources ) use ( &$common_href ): array {
				foreach ( $resources as $resource ) {
					if (
						is_array( $resource ) &&
						isset( $resource['href'] ) &&
						is_string( $resource['href'] ) &&
						str_contains( $resource['href'], '/wp-admin/css/common.css' )
					) {
						$common_href = $resource['href'];

						// Append the plain form of the same URL, as a callback building it itself would.
						$resources[] = array(
							'href' => str_replace( '&#038;', '&', $resource['href'] ),
							'as'   => 'style',
						);
					}
				}
				return $resources;
			}
		);

		$links = $this->get_prefetched_on_login();

		$this->assertIsString( $common_href );
		$this->assertStringContainsString( '/wp-admin/css/common.css?color=blue&ver=', $common_href );
		$this->assertStringNotContainsString( '&#038;', $common_href );

		$common_links = array_filter(
			$links,
			static function ( array $link ): bool {
				return str_contains( $link['href'], '/wp-admin/css/common.css' );
			}
		);
		$this->assertCount( 1, $common_links, 'The plain form of the URL should be collapsed with it.' );
	}

	/**
	 * Tests that the filter can add, replace and remove resources, and that its result is sanitized.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_result_is_deduplicated_and_sanitized(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		add_filter(
			'prefetch_admin_assets',
			static function (): array {
				return array(
					array(
						'href' => 'https://example.com/first.js',
						'as'   => 'script',
					),
					array(
						'href' => 'https://example.com/first.js',
						'as'   => 'style',
					),
					array(
						'href' => 'https://example.com/image.png',
						'as'   => 'image',
					),
					array( 'href' => 'https://example.com/no-destination.js' ),
					array( 'as' => 'script' ),
					array(
						'href' => '',
						'as'   => 'script',
					),
					'not an array',
				);
			}
		);

		$this->assertSame(
			array(
				array(
					'href' => 'https://example.com/first.js',
					'as'   => 'script',
				),
				array(
					'href' => 'https://example.com/image.png',
					'as'   => 'image',
				),
			),
			$this->get_prefetched_on_login()
		);
	}

	/**
	 * Tests that the filter can turn prefetching off.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_filter_turning_off
	 *
	 * @param mixed $filtered Value the filter returns.
	 */
	public function test_filter_can_turn_off_prefetching( $filtered ): void {
		define( 'CONCATENATE_SCRIPTS', false );

		add_filter(
			'prefetch_admin_assets',
			static function () use ( $filtered ) {
				return $filtered;
			}
		);

		$this->assertSame( array(), $this->get_prefetched_on_login() );
	}

	/**
	 * Data provider for {@see self::test_filter_can_turn_off_prefetching()}.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed }>
	 */
	public function data_filter_turning_off(): array {
		return array(
			'empty array'  => array( array() ),
			'not an array' => array( null ),
		);
	}

	/**
	 * Runs the login screen's prefetching and returns the links it printed.
	 *
	 * @param array<string, string> $request       Request parameters of the login screen.
	 * @param string|null           $action        Action as resolved by wp-login.php, or null for
	 *                                             `login_head` fired by a plugin outside of it.
	 * @param bool                  $interim_login Whether wp-login.php is displaying the interim
	 *                                             login modal.
	 * @return list<array{ href: string, as: string }> Prefetch links in the order printed.
	 */
	private function get_prefetched_on_login( array $request = array(), ?string $action = 'login', bool $interim_login = false ): array {
		$_REQUEST = $request;

		$GLOBALS['action']        = $action;
		$GLOBALS['interim_login'] = $interim_login;

		remove_all_actions( 'login_head' );
		add_action( 'login_head', 'wp_prefetch_admin_assets' );

		return $this->parse_prefetch_links( get_echo( 'do_action', array( 'login_head' ) ) );
	}

	/**
	 * Runs an admin screen's prefetching and returns the links it printed.
	 *
	 * @param string $screen Screen ID.
	 * @return list<array{ href: string, as: string }> Prefetch links in the order printed.
	 */
	private function get_prefetched_on_admin_screen( string $screen ): array {
		set_current_screen( $screen );

		remove_all_actions( 'admin_head' );
		add_action( 'admin_head', 'wp_prefetch_admin_assets' );

		return $this->parse_prefetch_links( get_echo( 'do_action', array( 'admin_head' ) ) );
	}

	/**
	 * Parses the prefetch links out of printed markup.
	 *
	 * @param string $html Printed markup.
	 * @return list<array{ href: string, as: string }> Prefetch links in document order.
	 */
	private function parse_prefetch_links( string $html ): array {
		$links     = array();
		$processor = new WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag( 'LINK' ) ) {
			if ( 'prefetch' !== $processor->get_attribute( 'rel' ) ) {
				continue;
			}

			$links[] = array(
				'href' => (string) $processor->get_attribute( 'href' ),
				'as'   => (string) $processor->get_attribute( 'as' ),
			);
		}

		return $links;
	}

	/**
	 * Gets the URLs prefetched with the given `as` value.
	 *
	 * @param list<array{ href: string, as: string }> $links    Prefetch links.
	 * @param string                                  $as_value Value of the `as` attribute.
	 * @return list<string> URLs.
	 */
	private function get_hrefs( array $links, string $as_value ): array {
		$hrefs = array();

		foreach ( $links as $link ) {
			if ( $as_value === $link['as'] ) {
				$hrefs[] = $link['href'];
			}
		}

		return $hrefs;
	}

	/**
	 * Asserts that a URL matching the pattern is prefetched with the given `as` value.
	 *
	 * @param list<array{ href: string, as: string }> $links    Prefetch links.
	 * @param string                                  $as_value Expected value of the `as` attribute.
	 * @param non-empty-string                        $pattern  Regular expression the URL must match.
	 */
	private function assertPrefetched( array $links, string $as_value, string $pattern ): void {
		$this->assertNotEmpty(
			preg_grep( $pattern, $this->get_hrefs( $links, $as_value ) ),
			"Expected a prefetch link with as='{$as_value}' matching {$pattern}."
		);
	}

	/**
	 * Asserts that no URL matching the pattern is prefetched.
	 *
	 * @param list<array{ href: string, as: string }> $links   Prefetch links.
	 * @param non-empty-string                        $pattern Regular expression the URL must not match.
	 */
	private function assertNotPrefetched( array $links, string $pattern ): void {
		$this->assertEmpty(
			preg_grep( $pattern, array_column( $links, 'href' ) ),
			"Expected no prefetch link matching {$pattern}."
		);
	}
}
