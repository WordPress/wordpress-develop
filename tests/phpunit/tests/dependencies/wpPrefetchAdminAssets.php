<?php
/**
 * Tests for {@see wp_prefetch_admin_assets()}.
 *
 * Prefetching only happens when concatenation is off. Each test turns it off with the
 * {@see 'wp_should_concatenate_admin_scripts'} filter, so that it does not depend on the
 * `CONCATENATE_SCRIPTS` and `SCRIPT_DEBUG` constants, which cannot be changed once defined.
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
	 * Globals each test replaces, with the values they had before it.
	 *
	 * @var array<string, mixed>
	 */
	private $original_globals = array();

	/**
	 * Gives each test fresh script and style registries, since several tests modify them, and turns
	 * concatenation off.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'wp_scripts', 'wp_styles', 'concatenate_scripts', 'action', 'interim_login' ) as $name ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
			unset( $GLOBALS[ $name ] );
		}

		add_filter( 'wp_should_concatenate_admin_scripts', '__return_false' );
	}

	/**
	 * Restores the globals the test replaced.
	 */
	public function tear_down(): void {
		foreach ( $this->original_globals as $name => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $name ] );
			} else {
				$GLOBALS[ $name ] = $value;
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that nothing is prefetched from the login screen when the admin concatenates its assets.
	 *
	 * @ticket 57548
	 */
	public function test_login_prints_nothing_when_admin_concatenates(): void {
		add_filter( 'wp_should_concatenate_admin_scripts', '__return_true' );

		$this->assertSame( array(), $this->get_prefetched_on_login() );
	}

	/**
	 * Tests that the login screen predicts what the admin will do rather than reading the
	 * `$concatenate_scripts` global, which script_concat_settings() may have settled on false before
	 * 'login_init' fired.
	 *
	 * @ticket 57548
	 */
	public function test_login_ignores_concatenate_scripts_global(): void {
		$GLOBALS['concatenate_scripts'] = true;

		$this->assertNotSame( array(), $this->get_prefetched_on_login(), 'Expected prefetching when the admin will not concatenate.' );

		$GLOBALS['concatenate_scripts'] = false;
		add_filter( 'wp_should_concatenate_admin_scripts', '__return_true' );

		$this->assertSame( array(), $this->get_prefetched_on_login(), 'Expected no prefetching when the admin will concatenate.' );
	}

	/**
	 * Tests that an admin screen goes by the `$concatenate_scripts` global, which has settled by the
	 * time its head is printed, so a plugin setting the global is respected.
	 *
	 * @ticket 57548
	 */
	public function test_admin_screen_uses_concatenate_scripts_global(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$GLOBALS['concatenate_scripts'] = true;

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'dashboard' ), 'Expected no prefetching when the global says to concatenate.' );

		$GLOBALS['concatenate_scripts'] = false;
		add_filter( 'wp_should_concatenate_admin_scripts', '__return_true' );

		$this->assertNotSame( array(), $this->get_prefetched_on_admin_screen( 'dashboard' ), 'Expected prefetching when the global says not to concatenate.' );
	}

	/**
	 * Tests that an admin screen settles the `$concatenate_scripts` global with script_concat_settings()
	 * when nothing has done so yet.
	 *
	 * @ticket 57548
	 */
	public function test_admin_screen_settles_concatenate_scripts_global(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'wp_should_concatenate_admin_scripts', '__return_true' );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'dashboard' ) );
		$this->assertTrue( $GLOBALS['concatenate_scripts'] );
	}

	/**
	 * Tests what the login screen prefetches for the admin: the render-blocking head scripts and the
	 * admin-wide stylesheets, but not the editor's stylesheets or the per-user color scheme.
	 *
	 * @ticket 57548
	 */
	public function test_login_prefetches_render_blocking_admin_assets(): void {
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
	 * @dataProvider data_editor_destinations
	 *
	 * @param string $redirect_to Where the login redirects to.
	 * @param bool   $is_editor   Whether that is the block editor.
	 */
	public function test_login_prefetches_editor_assets_for_editor_destination( string $redirect_to, bool $is_editor ): void {
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
	 */
	public function test_login_prefetches_editor_assets_for_absolute_editor_url(): void {
		$links = $this->get_prefetched_on_login( array( 'redirect_to' => admin_url( 'post-new.php' ) ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
	}

	/**
	 * Tests that nothing is prefetched from login screens that do not lead to the admin.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_login_requests_not_leading_to_admin
	 *
	 * @param array<string, string> $request       Request parameters of the login screen.
	 * @param string|null           $action        Action as resolved by wp-login.php.
	 * @param bool                  $interim_login Whether the interim login modal is displayed.
	 */
	public function test_login_prints_nothing_when_not_leading_to_admin( array $request, ?string $action, bool $interim_login ): void {
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
			'lost password'                => array( array( 'action' => 'lostpassword' ), 'lostpassword', false ),
			'registration'                 => array( array( 'action' => 'register' ), 'register', false ),
			'logout'                       => array( array( 'action' => 'logout' ), 'logout', false ),
			'password reset key'           => array(
				array(
					'key'   => 'abc',
					'login' => 'admin',
				),
				'resetpass',
				false,
			),
			'check email'                  => array( array( 'checkemail' => 'confirm' ), 'checkemail', false ),
			'check email after register'   => array( array( 'checkemail' => 'registered' ), 'checkemail', false ),
			'interim login'                => array( array( 'interim-login' => '1' ), 'login', true ),
			'login_footer fired by plugin' => array( array(), null, false ),
			'front end redirect'           => array( array( 'redirect_to' => '/hello-world/' ), 'login', false ),
			'lookalike admin path'         => array( array( 'redirect_to' => '/wp-admin-lookalike/' ), 'login', false ),
		);
	}

	/**
	 * Tests that nothing is prefetched when an absolute `redirect_to` points at this site's front end.
	 *
	 * @ticket 57548
	 */
	public function test_login_prints_nothing_for_absolute_front_end_redirect(): void {
		$this->assertSame( array(), $this->get_prefetched_on_login( array( 'redirect_to' => home_url( '/hello-world/' ) ) ) );
	}

	/**
	 * Tests that the action wp-login.php resolved is used rather than the request parameter, so an
	 * action wp-login.php does not recognize, which it treats as the login form, still prefetches.
	 *
	 * @ticket 57548
	 */
	public function test_login_uses_action_resolved_by_wp_login(): void {
		$links = $this->get_prefetched_on_login( array( 'action' => 'unrecognized' ), 'login' );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that a `redirect_to` pointing off-site falls back to the admin, as wp_safe_redirect() does.
	 *
	 * @ticket 57548
	 */
	public function test_login_off_site_redirect_falls_back_to_admin(): void {
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
	 */
	public function test_login_prints_nothing_for_admin_on_another_allowed_host(): void {
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
	 */
	public function test_login_prints_nothing_for_admin_on_another_port(): void {
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
	 * Tests that nothing is prefetched when the login lands in this site's admin under another scheme,
	 * since the URLs prefetched take the scheme of the login screen and that admin would request
	 * different ones.
	 *
	 * @ticket 57548
	 */
	public function test_login_prints_nothing_for_admin_on_another_scheme(): void {
		$scheme       = (string) wp_parse_url( admin_url(), PHP_URL_SCHEME );
		$other_scheme = 'https' === $scheme ? 'http' : 'https';

		$this->assertSame(
			array(),
			$this->get_prefetched_on_login( array( 'redirect_to' => set_url_scheme( admin_url( 'post-new.php' ), $other_scheme ) ) )
		);
	}

	/**
	 * Tests that a protocol-relative `redirect_to` pointing at this site's admin prefetches, since it
	 * keeps the scheme of the login screen.
	 *
	 * @ticket 57548
	 */
	public function test_login_prefetches_for_protocol_relative_admin_url(): void {
		$redirect_to = (string) preg_replace( '#^https?:#', '', admin_url( 'post-new.php' ) );
		$this->assertStringStartsWith( '//', $redirect_to );

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => $redirect_to ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
	}

	/**
	 * Tests that the Dashboard and the post list tables prefetch only the editor's stylesheets.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_admin_screens_leading_to_editor
	 *
	 * @param string $screen    Screen ID.
	 * @param string $post_type Post type of the editor expected to be prefetched for.
	 */
	public function test_admin_screen_prefetches_editor_assets( string $screen, string $post_type ): void {
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
	 * @dataProvider data_other_admin_screens
	 *
	 * @param string $screen Screen ID.
	 */
	public function test_other_admin_screen_prints_nothing( string $screen ): void {
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
	 * Tests that nothing is prefetched for a user who cannot edit posts of the type listed.
	 *
	 * @ticket 57548
	 */
	public function test_admin_screen_prints_nothing_for_user_who_cannot_edit_posts(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'dashboard' ) );
	}

	/**
	 * Tests that the editor's stylesheets are prefetched for a user who can edit posts of the type
	 * listed but not create them, since opening an existing post leads to the editor as well.
	 *
	 * A Contributor can edit patterns but not create them, which requires `publish_posts`.
	 *
	 * @ticket 57548
	 */
	public function test_admin_screen_prefetches_for_user_who_can_edit_but_not_create_posts(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$post_type_object = get_post_type_object( 'wp_block' );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type_object );
		$this->assertTrue( current_user_can( $post_type_object->cap->edit_posts ), 'Expected the user to be able to edit patterns.' );
		$this->assertFalse( current_user_can( $post_type_object->cap->create_posts ), 'Expected the user not to be able to create patterns.' );

		$links = $this->get_prefetched_on_admin_screen( 'edit-wp_block' );

		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
	}

	/**
	 * Tests that nothing is prefetched for a post type that uses the classic editor.
	 *
	 * @ticket 57548
	 */
	public function test_admin_screen_prints_nothing_for_classic_editor(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'use_block_editor_for_post_type', '__return_false' );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'edit' ) );
	}

	/**
	 * Tests that assets the current screen has already printed are not prefetched again.
	 *
	 * @ticket 57548
	 */
	public function test_skips_assets_already_printed(): void {
		wp_styles()->done[]  = 'common';
		wp_scripts()->done[] = 'utils';

		$links = $this->get_prefetched_on_login();

		$this->assertNotPrefetched( $links, '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertNotPrefetched( $links, '#/wp-includes/js/utils(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/forms(\.min)?\.css#' );
	}

	/**
	 * Tests that the login screen skips scripts it loads itself in the footer.
	 *
	 * wp-login.php enqueues `user-profile` after its header has printed, and that script brings in
	 * `jquery`, so the login screen loads jQuery in its footer. The prefetching runs after the
	 * footer scripts, as registered in default-filters.php, so it leaves jQuery out but still
	 * prefetches `utils`, which the login screen does not load.
	 *
	 * @ticket 57548
	 */
	public function test_login_skips_scripts_printed_in_footer(): void {
		$GLOBALS['action']        = 'login';
		$GLOBALS['interim_login'] = false;

		remove_all_actions( 'login_footer' );
		add_action( 'login_footer', 'wp_print_footer_scripts', 20 );
		add_action( 'login_footer', 'wp_prefetch_admin_assets', 21 );

		wp_enqueue_script( 'user-profile' );

		$output = get_echo( 'do_action', array( 'login_footer' ) );
		$links  = $this->parse_prefetch_links( $output );

		$this->assertMatchesRegularExpression( '#<script[^>]+src=[\'"][^\'"]*/wp-includes/js/jquery/jquery(\.min)?\.js#', $output, 'Expected the login screen to load jQuery itself.' );
		$this->assertNotPrefetched( $links, '#/wp-includes/js/jquery/jquery(\.min)?\.js#' );
		$this->assertNotPrefetched( $links, '#/wp-includes/js/jquery/jquery-migrate(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'script', '#/wp-includes/js/utils(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that the prefetching from the login screen is hooked to run after its footer scripts.
	 *
	 * @ticket 57548
	 *
	 * @coversNothing
	 */
	public function test_login_hook_follows_footer_scripts(): void {
		$prefetch_priority = has_action( 'login_footer', 'wp_prefetch_admin_assets' );
		$scripts_priority  = has_action( 'login_footer', 'wp_print_footer_scripts' );

		$this->assertIsInt( $prefetch_priority );
		$this->assertIsInt( $scripts_priority );
		$this->assertGreaterThan( $scripts_priority, $prefetch_priority );
		$this->assertFalse( has_action( 'login_head', 'wp_prefetch_admin_assets' ) );
	}

	/**
	 * Tests that a right-to-left locale prefetches the right-to-left stylesheets.
	 *
	 * @ticket 57548
	 */
	public function test_prefetches_rtl_stylesheets(): void {
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
	 */
	public function test_filter_receives_unescaped_stylesheet_urls(): void {
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
	 */
	public function test_filter_result_is_deduplicated_and_sanitized(): void {
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
					// Rejected by esc_url(), so it would otherwise be printed with an empty `href`.
					array(
						'href' => 'javascript:alert(1)',
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
	 * Tests that the filter can turn prefetching off, in which case neither the prefetch links nor
	 * the script prefetching them in browsers that lack `rel="prefetch"` are printed.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_filter_turning_off
	 *
	 * @param mixed $filtered Value the filter returns.
	 */
	public function test_filter_can_turn_off_prefetching( $filtered ): void {
		add_filter(
			'prefetch_admin_assets',
			static function () use ( $filtered ) {
				return $filtered;
			}
		);

		$this->assertSame( '', $this->get_login_footer_output() );
	}

	/**
	 * Tests that the script prefetching the links in browsers that lack `rel="prefetch"` is printed
	 * once, after all of the prefetch links it reads from the page.
	 *
	 * @ticket 57548
	 */
	public function test_prints_polyfill_after_prefetch_links(): void {
		$processor      = new WP_HTML_Tag_Processor( $this->get_login_footer_output() );
		$links          = 0;
		$scripts        = array();
		$links_after_js = 0;
		while ( $processor->next_tag() ) {
			if ( 'LINK' === $processor->get_tag() && 'prefetch' === $processor->get_attribute( 'rel' ) ) {
				++$links;
				if ( $scripts ) {
					++$links_after_js;
				}
			} elseif ( 'SCRIPT' === $processor->get_tag() ) {
				$scripts[] = $processor->get_modifiable_text();
			}
		}

		$this->assertGreaterThan( 0, $links, 'Expected prefetch links to be printed.' );
		$this->assertCount( 1, $scripts, 'Expected one script to be printed.' );
		$this->assertSame( 0, $links_after_js, 'Expected the script to follow all of the prefetch links.' );
		$this->assertStringContainsString( 'link[rel~="prefetch"]', $scripts[0] );
		$this->assertStringContainsString( '//# sourceURL=wp_prefetch_admin_assets', $scripts[0] );
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
	 *                                             `login_footer` fired by a plugin outside of it.
	 * @param bool                  $interim_login Whether wp-login.php is displaying the interim
	 *                                             login modal.
	 * @return list<array{ href: string, as: string }> Prefetch links in the order printed.
	 */
	private function get_prefetched_on_login( array $request = array(), ?string $action = 'login', bool $interim_login = false ): array {
		return $this->parse_prefetch_links( $this->get_login_footer_output( $request, $action, $interim_login ) );
	}

	/**
	 * Runs the login screen's prefetching and returns everything it printed.
	 *
	 * @param array<string, string> $request       Request parameters of the login screen.
	 * @param string|null           $action        Action as resolved by wp-login.php, or null for
	 *                                             `login_footer` fired by a plugin outside of it.
	 * @param bool                  $interim_login Whether wp-login.php is displaying the interim
	 *                                             login modal.
	 * @return string Printed markup.
	 */
	private function get_login_footer_output( array $request = array(), ?string $action = 'login', bool $interim_login = false ): string {
		$_REQUEST = $request;

		$GLOBALS['action']        = $action;
		$GLOBALS['interim_login'] = $interim_login;

		remove_all_actions( 'login_footer' );
		add_action( 'login_footer', 'wp_prefetch_admin_assets' );

		return get_echo( 'do_action', array( 'login_footer' ) );
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
