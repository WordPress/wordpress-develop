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
	 * Name of the script handling the request before the test, which tests on the login screen change.
	 *
	 * @var mixed
	 */
	private $original_script_name;

	/**
	 * Gives each test fresh script and style registries, since several tests modify them, and turns
	 * concatenation off.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'wp_scripts', 'wp_styles', 'concatenate_scripts' ) as $name ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
			unset( $GLOBALS[ $name ] );
		}

		$this->original_script_name = $_SERVER['SCRIPT_NAME'] ?? null;

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

		if ( null === $this->original_script_name ) {
			unset( $_SERVER['SCRIPT_NAME'] );
		} else {
			$_SERVER['SCRIPT_NAME'] = $this->original_script_name;
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
			'Dashboard'                    => array( '/wp-admin/', false ),
			'Dashboard, no trailing slash' => array( '/wp-admin', false ),
			'new post'                     => array( '/wp-admin/post-new.php', true ),
			'new page'                     => array( '/wp-admin/post-new.php?post_type=page', true ),
			'editing a post'               => array( '/wp-admin/post.php?post=1&action=edit', true ),
			'trashing a post'              => array( '/wp-admin/post.php?post=1&action=trash', false ),
			'post list'                    => array( '/wp-admin/edit.php', false ),
			'relative, editing a post'     => array( 'wp-admin/post.php?post=1&action=edit', true ),
			'relative, new post'           => array( 'wp-admin/post-new.php', true ),
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
	 * Tests that a login leading to the editor of a post type that does not use the block editor
	 * prefetches only the admin's stylesheets, not the block editor's.
	 *
	 * The classic editor is turned off for pages only, so the post type is what decides. An edit
	 * link to post.php does not name its post type, so it is taken to be a post.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_editor_destinations_by_post_type
	 *
	 * @param non-falsy-string $redirect_to Where the login redirects to.
	 * @param bool             $is_editor   Whether that is expected to be the block editor.
	 */
	public function test_login_checks_block_editor_for_post_type( string $redirect_to, bool $is_editor ): void {
		add_filter(
			'use_block_editor_for_post_type',
			static function ( bool $use_block_editor, string $post_type ): bool {
				return 'page' === $post_type ? false : $use_block_editor;
			},
			10,
			2
		);

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => $redirect_to ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );

		if ( $is_editor ) {
			$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
		} else {
			$this->assertNotPrefetched( $links, '#/wp-includes/css/dist/edit-post/#' );
		}
	}

	/**
	 * Data provider for {@see self::test_login_checks_block_editor_for_post_type()}.
	 *
	 * @return array<non-falsy-string, array{ 0: non-falsy-string, 1: bool }>
	 */
	public function data_editor_destinations_by_post_type(): array {
		return array(
			'new post'               => array( '/wp-admin/post-new.php', true ),
			'new page'               => array( '/wp-admin/post-new.php?post_type=page', false ),
			'unregistered post type' => array( '/wp-admin/post-new.php?post_type=nonexistent', false ),
			'post type not a string' => array( '/wp-admin/post-new.php?post_type[]=post', false ),
			'editing a post'         => array( '/wp-admin/post.php?post=1&action=edit', true ),
		);
	}

	/**
	 * Tests that a login leading to the editor prefetches only the admin's stylesheets when the
	 * classic editor replaces the block editor for every post type.
	 *
	 * @ticket 57548
	 */
	public function test_login_prints_no_editor_assets_for_classic_editor(): void {
		add_filter( 'use_block_editor_for_post_type', '__return_false' );

		foreach ( array( '/wp-admin/post-new.php', '/wp-admin/post.php?post=1&action=edit' ) as $redirect_to ) {
			$links = $this->get_prefetched_on_login( array( 'redirect_to' => $redirect_to ) );

			$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
			$this->assertNotPrefetched( $links, '#/wp-includes/css/dist/edit-post/#' );
		}
	}

	/**
	 * Tests that every screen of the login page prefetches the admin's assets, not only the login
	 * form, since the others mostly lead to the admin as well.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_login_screens
	 *
	 * @param array<string, string> $request Request parameters of the login screen.
	 */
	public function test_login_prefetches_on_every_login_screen( array $request ): void {
		$links = $this->get_prefetched_on_login( $request );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Data provider for {@see self::test_login_prefetches_on_every_login_screen()}.
	 *
	 * @return array<non-falsy-string, array{ 0: array<string, string> }>
	 */
	public function data_login_screens(): array {
		return array(
			'login form'           => array( array() ),
			'lost password'        => array( array( 'action' => 'lostpassword' ) ),
			'registration'         => array( array( 'action' => 'register' ) ),
			'password reset key'   => array(
				array(
					'key'   => 'abc',
					'login' => 'admin',
				),
			),
			'check email'          => array( array( 'checkemail' => 'confirm' ) ),
			'interim login'        => array( array( 'interim-login' => '1' ) ),
			'unrecognized action'  => array( array( 'action' => 'unrecognized' ) ),
			'empty redirect'       => array( array( 'redirect_to' => '' ) ),
			'lost password error'  => array(
				array(
					'action'      => 'lostpassword',
					'redirect_to' => '',
				),
			),
			'admin email reminder' => array(
				array(
					'action'      => 'confirm_admin_email',
					'redirect_to' => '/wp-admin/',
				),
			),
		);
	}

	/**
	 * Tests that nothing is prefetched from login screens that do not lead to the admin.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_login_requests_not_leading_to_admin
	 *
	 * @param array<string, string> $request Request parameters of the login screen.
	 */
	public function test_login_prints_nothing_when_not_leading_to_admin( array $request ): void {
		$this->assertSame( array(), $this->get_prefetched_on_login( $request ) );
	}

	/**
	 * Data provider for {@see self::test_login_prints_nothing_when_not_leading_to_admin()}.
	 *
	 * On the lost password and registration screens, `redirect_to` is where submitting the form
	 * goes, which is typically back into wp-login.php.
	 *
	 * @return array<non-falsy-string, array{ 0: array<string, string> }>
	 */
	public function data_login_requests_not_leading_to_admin(): array {
		return array(
			'front end redirect'                 => array( array( 'redirect_to' => '/hello-world/' ) ),
			'lookalike admin path'               => array( array( 'redirect_to' => '/wp-admin-lookalike/' ) ),
			// A relative path, which the browser would resolve to /example.org/wp-admin/, not to this site's admin.
			'relative path resembling a host'    => array( array( 'redirect_to' => 'example.org/wp-admin/' ) ),
			'lost password redirecting to login' => array(
				array(
					'action'      => 'lostpassword',
					'redirect_to' => 'wp-login.php?checkemail=confirm',
				),
			),
			'registration redirecting to login'  => array(
				array(
					'action'      => 'register',
					'redirect_to' => 'wp-login.php?checkemail=registered',
				),
			),
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
	 * Tests that a `redirect_to` pointing off-site falls back to the admin, as wp_safe_redirect() does.
	 *
	 * @ticket 57548
	 */
	public function test_login_off_site_redirect_falls_back_to_admin(): void {
		$filter = new MockAction();
		add_filter( 'wp_prefetch_admin_assets', array( $filter, 'filter' ), 10, 2 );

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
	 * Tests that an explicit port that is the scheme's default counts as the admin's own, since
	 * `http://example.org:80/` is the same admin as `http://example.org/`.
	 *
	 * @ticket 57548
	 */
	public function test_login_prefetches_for_admin_with_explicit_default_port(): void {
		// Serve the site without a port of its own, as the test environment may not, so the redirect can name the default one.
		update_option( 'home', preg_replace( '#^(https?://[^/:]+):\d+#', '$1', home_url() ) );
		update_option( 'siteurl', preg_replace( '#^(https?://[^/:]+):\d+#', '$1', site_url() ) );

		$scheme = (string) wp_parse_url( admin_url(), PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( admin_url(), PHP_URL_HOST );
		$path   = (string) wp_parse_url( admin_url( 'post-new.php' ), PHP_URL_PATH );
		$this->assertNull( wp_parse_url( admin_url(), PHP_URL_PORT ), 'Expected the admin URL to have no port.' );

		$default_port = 'https' === $scheme ? 443 : 80;

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => "{$scheme}://{$host}:{$default_port}{$path}" ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
	}

	/**
	 * Tests that a protocol-relative `redirect_to` pointing at this site's admin prefetches when the
	 * admin is served over `http`, since wp_validate_redirect() gives such a value the `http` scheme.
	 *
	 * @ticket 57548
	 */
	public function test_login_prefetches_for_protocol_relative_admin_url(): void {
		$this->assertSame( 'http', wp_parse_url( admin_url(), PHP_URL_SCHEME ), 'Expected the admin to be served over http.' );

		$redirect_to = (string) preg_replace( '#^https?:#', '', admin_url( 'post-new.php' ) );
		$this->assertStringStartsWith( '//', $redirect_to );

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => $redirect_to ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
	}

	/**
	 * Tests that nothing is prefetched from an `https` login screen for a protocol-relative
	 * `redirect_to`, since wp_validate_redirect() gives it the `http` scheme, which is where
	 * wp_safe_redirect() then sends the browser.
	 *
	 * @ticket 57548
	 */
	public function test_login_prints_nothing_for_protocol_relative_admin_url_over_https(): void {
		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( 'https', wp_parse_url( admin_url(), PHP_URL_SCHEME ), 'Expected the admin to be served over https.' );

		$redirect_to = (string) preg_replace( '#^https?:#', '', admin_url( 'post-new.php' ) );

		$this->assertSame( array(), $this->get_prefetched_on_login( array( 'redirect_to' => $redirect_to ) ) );
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
		add_filter( 'wp_prefetch_admin_assets', array( $filter, 'filter' ), 10, 2 );

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
	 * Tests that nothing is prefetched for a post type that uses the classic editor, and that the
	 * filter is not applied either, since there is no destination to prefetch for.
	 *
	 * @ticket 57548
	 */
	public function test_admin_screen_prints_nothing_for_classic_editor(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'use_block_editor_for_post_type', '__return_false' );

		$filter = new MockAction();
		add_filter( 'wp_prefetch_admin_assets', array( $filter, 'filter' ) );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'edit' ) );
		$this->assertSame( 0, $filter->get_call_count(), 'Expected the filter not to be applied.' );
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
	 * Tests that assets the current screen has queued but not yet printed are not prefetched either,
	 * along with their dependencies, so the result does not depend on whether this runs before or
	 * after the screen's footer scripts.
	 *
	 * @ticket 57548
	 */
	public function test_skips_assets_queued_but_not_yet_printed(): void {
		wp_enqueue_script( 'user-profile' );
		wp_enqueue_style( 'forms' );

		$links = $this->get_prefetched_on_login();

		$this->assertSame( array(), wp_scripts()->done, 'Expected no scripts to have been printed.' );
		$this->assertNotPrefetched( $links, '#/wp-includes/js/jquery/jquery(\.min)?\.js#' );
		$this->assertNotPrefetched( $links, '#/wp-includes/js/jquery/jquery-migrate(\.min)?\.js#' );
		$this->assertNotPrefetched( $links, '#/wp-admin/css/forms(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'script', '#/wp-includes/js/utils(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that the login screen skips scripts it loads itself in the footer.
	 *
	 * wp-login.php enqueues `user-profile` after its header has printed, and that script brings in
	 * `jquery`, so the login screen loads jQuery in its footer. With the callbacks registered in
	 * default-filters.php, the prefetching runs in the footer, after the footer scripts, so it
	 * leaves jQuery out but still prefetches `utils`, which the login screen does not load.
	 *
	 * @ticket 57548
	 */
	public function test_login_skips_scripts_printed_in_footer(): void {
		$this->assertFalse( has_action( 'login_head', 'wp_prefetch_admin_assets' ), 'Expected no prefetching from the head of the login screen.' );
		$this->assertIsInt( has_action( 'login_footer', 'wp_prefetch_admin_assets' ), 'Expected prefetching from the footer of the login screen.' );

		$this->go_to_login_screen();
		wp_enqueue_script( 'user-profile' );

		$output = get_echo( 'do_action', array( 'login_footer' ) );
		$links  = $this->parse_prefetch_links( $output );

		// Find where jQuery's script tag and the first prefetch link are among the tags printed.
		$processor            = new WP_HTML_Tag_Processor( $output );
		$tag_index            = 0;
		$jquery_index         = null;
		$first_prefetch_index = null;
		while ( $processor->next_tag() ) {
			++$tag_index;

			if ( 'SCRIPT' === $processor->get_tag() ) {
				$src = $processor->get_attribute( 'src' );
				if ( is_string( $src ) && in_array( basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ), array( 'jquery.js', 'jquery.min.js' ), true ) ) {
					$jquery_index = $jquery_index ?? $tag_index;
				}
			} elseif ( 'LINK' === $processor->get_tag() && 'prefetch' === $processor->get_attribute( 'rel' ) ) {
				$first_prefetch_index = $first_prefetch_index ?? $tag_index;
			}
		}

		$this->assertIsInt( $jquery_index, 'Expected the login screen to load jQuery itself with a script tag.' );
		$this->assertIsInt( $first_prefetch_index, 'Expected prefetch links to be printed.' );
		$this->assertGreaterThan( $jquery_index, $first_prefetch_index, 'Expected the prefetch links to follow the footer scripts.' );

		$this->assertNotPrefetched( $links, '#/wp-includes/js/jquery/jquery(\.min)?\.js#' );
		$this->assertNotPrefetched( $links, '#/wp-includes/js/jquery/jquery-migrate(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'script', '#/wp-includes/js/utils(\.min)?\.js#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that the login screen is recognized by the request rather than by the hook, so that the
	 * prefetching still works when it is moved to another hook, such as back to the head.
	 *
	 * @ticket 57548
	 */
	public function test_login_prefetches_from_another_hook(): void {
		$this->go_to_login_screen();

		remove_all_actions( 'login_head' );
		add_action( 'login_head', 'wp_prefetch_admin_assets' );

		$links = $this->parse_prefetch_links( get_echo( 'do_action', array( 'login_head' ) ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that the login screen is recognized when a plugin serves it at a URL of its own, running
	 * wp-login.php from another script, in which case is_login() does not recognize it.
	 *
	 * @ticket 57548
	 */
	public function test_login_prefetches_at_custom_login_url(): void {
		add_filter(
			'login_url',
			static function (): string {
				return home_url( '/my-login/' );
			}
		);
		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$this->assertFalse( is_login(), 'Expected is_login() not to recognize the login screen.' );

		$links = $this->get_prefetched_on_login();

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that nothing is printed, and no error raised, on a request that is neither for the login
	 * screen nor for an admin screen, such as one for the front end, where get_current_screen() is
	 * not defined unless the admin includes have been loaded.
	 *
	 * @ticket 57548
	 */
	public function test_prints_nothing_on_front_end(): void {
		$this->assertSame( 0, did_action( 'login_init' ), 'Expected the request not to be for the login screen.' );
		$this->assertNull( $GLOBALS['current_screen'] ?? null, 'Expected no current screen.' );

		$this->assertSame( '', get_echo( 'wp_prefetch_admin_assets' ) );
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
	 * Tests that a handle with conditional data is not prefetched, since do_item() prints nothing
	 * for it.
	 *
	 * @ticket 57548
	 *
	 * @expectedDeprecated WP_Dependencies::add_data()
	 */
	public function test_skips_conditional_handles(): void {
		wp_styles()->add_data( 'forms', 'conditional', 'IE' );

		$links = $this->get_prefetched_on_login();

		$this->assertNotPrefetched( $links, '#/wp-admin/css/forms(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
	}

	/**
	 * Tests that a style whose own URL is filtered away is not prefetched in a right-to-left locale
	 * either, since WP_Styles::do_item() returns before printing its right-to-left stylesheet.
	 *
	 * @ticket 57548
	 */
	public function test_skips_rtl_stylesheet_of_style_filtered_away(): void {
		wp_styles()->text_direction = 'rtl';

		add_filter(
			'style_loader_src',
			static function ( $src, string $handle ) {
				return 'common' === $handle ? '' : $src;
			},
			10,
			2
		);

		$links = $this->get_prefetched_on_login();

		$this->assertNotPrefetched( $links, '#/wp-admin/css/common(-rtl)?(\.min)?\.css#' );
		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/forms-rtl(\.min)?\.css#' );
	}

	/**
	 * Tests that each prefetched URL is sanitized only once, so that the {@see 'clean_url'} filter
	 * runs once for it, as it does for the URL in the tag the next screen prints.
	 *
	 * @ticket 57548
	 */
	public function test_prefetched_urls_run_clean_url_filter_once(): void {
		// The contexts the filter ran in, keyed by the URL it was given, before it was escaped.
		$calls = array();
		add_filter(
			'clean_url',
			static function ( string $good_protocol_url, string $original_url, string $context ) use ( &$calls ): string {
				$calls[ $original_url ][] = $context;
				return $good_protocol_url;
			},
			10,
			3
		);

		$links = $this->get_prefetched_on_login();
		$this->assertNotSame( array(), $links );

		foreach ( $links as $link ) {
			$href = html_entity_decode( $link['href'], ENT_QUOTES );
			$this->assertSame( array( 'display' ), $calls[ $href ] ?? array(), "Expected the 'clean_url' filter to run once for {$href}." );
		}
	}

	/**
	 * Tests that stylesheet URLs reach the filter unescaped, like script URLs, and that a callback
	 * appending the escaped form of one, as built with esc_url(), is collapsed with it.
	 *
	 * @ticket 57548
	 */
	public function test_filter_receives_unescaped_stylesheet_urls(): void {
		// Give a stylesheet a query string of its own, so its URL has an `&` before the version.
		wp_styles()->registered['common']->src = '/wp-admin/css/common.css?color=blue';

		$common_href  = null;
		$escaped_href = null;
		add_filter(
			'wp_prefetch_admin_assets',
			static function ( array $resources ) use ( &$common_href, &$escaped_href ): array {
				foreach ( $resources as $resource ) {
					if (
						is_array( $resource ) &&
						isset( $resource['href'] ) &&
						is_string( $resource['href'] ) &&
						str_contains( $resource['href'], '/wp-admin/css/common.css' )
					) {
						$common_href  = $resource['href'];
						$escaped_href = esc_url( $resource['href'] );

						// Append the escaped form of the same URL, as a callback building it with esc_url() would.
						$resources[] = array(
							'href' => $escaped_href,
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

		$this->assertIsString( $escaped_href );
		$this->assertStringContainsString( '&#038;', $escaped_href, 'Expected the appended URL to differ from the one the filter received.' );

		$common_links = array_filter(
			$links,
			static function ( array $link ): bool {
				return str_contains( $link['href'], '/wp-admin/css/common.css' );
			}
		);
		$this->assertCount( 1, $common_links, 'The escaped form of the URL should be collapsed with it.' );
	}

	/**
	 * Tests that the filter can add, replace and remove resources, and that its result is sanitized.
	 *
	 * @ticket 57548
	 */
	public function test_filter_result_is_deduplicated_and_sanitized(): void {
		add_filter(
			'wp_prefetch_admin_assets',
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
					// Allowed by esc_url() by default, but not prefetchable.
					array(
						'href' => 'mailto:admin@example.com',
						'as'   => 'document',
					),
					array(
						'href' => 'ftp://example.com/file.js',
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
			'wp_prefetch_admin_assets',
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
	 * @param array<string, string> $request Request parameters of the login screen.
	 * @return list<array{ href: string, as: string }> Prefetch links in the order printed.
	 */
	private function get_prefetched_on_login( array $request = array() ): array {
		return $this->parse_prefetch_links( $this->get_login_footer_output( $request ) );
	}

	/**
	 * Runs the login screen's prefetching and returns everything it printed.
	 *
	 * @param array<string, string> $request Request parameters of the login screen.
	 * @return string Printed markup.
	 */
	private function get_login_footer_output( array $request = array() ): string {
		$_REQUEST = $request;

		$this->go_to_login_screen();

		remove_all_actions( 'login_footer' );
		add_action( 'login_footer', 'wp_prefetch_admin_assets' );

		return get_echo( 'do_action', array( 'login_footer' ) );
	}

	/**
	 * Makes the request one for the login screen, by firing 'login_init' as wp-login.php does.
	 *
	 * Its callbacks are removed first, since one of them sends headers. The request URI is that of
	 * wp-login.php, which a relative `redirect_to` is resolved against.
	 */
	private function go_to_login_screen(): void {
		$_SERVER['REQUEST_URI'] = (string) wp_parse_url( wp_login_url(), PHP_URL_PATH );

		remove_all_actions( 'login_init' );

		/** This action is documented in wp-login.php */
		do_action( 'login_init' );
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
	 * @param 'script'|'style'                        $as_value Value of the `as` attribute.
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
	 * @param 'script'|'style'                        $as_value Expected value of the `as` attribute.
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
