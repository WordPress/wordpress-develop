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
			'Dashboard'           => array( '/wp-admin/', false ),
			'new post'            => array( '/wp-admin/post-new.php', true ),
			'new page'            => array( '/wp-admin/post-new.php?post_type=page', true ),
			'editing a post'      => array( '/wp-admin/post.php?post=1&action=edit', true ),
			'trashing a post'     => array( '/wp-admin/post.php?post=1&action=trash', false ),
			'post list'           => array( '/wp-admin/edit.php', false ),
			'absolute editor URL' => array( 'http://example.org/wp-admin/post-new.php', true ),
		);
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
	 * @param array<string, string> $request Request parameters of the login screen.
	 */
	public function test_login_prints_nothing_when_not_leading_to_admin( array $request ): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$this->assertSame( array(), $this->get_prefetched_on_login( $request ) );
	}

	/**
	 * Data provider for {@see self::test_login_prints_nothing_when_not_leading_to_admin()}.
	 *
	 * @return array<non-falsy-string, array{ 0: array<string, string> }>
	 */
	public function data_login_requests_not_leading_to_admin(): array {
		return array(
			'lost password'        => array( array( 'action' => 'lostpassword' ) ),
			'registration'         => array( array( 'action' => 'register' ) ),
			'logout'               => array( array( 'action' => 'logout' ) ),
			'interim login'        => array( array( 'interim-login' => '1' ) ),
			'front end redirect'   => array( array( 'redirect_to' => '/hello-world/' ) ),
			'absolute front end'   => array( array( 'redirect_to' => 'http://example.org/hello-world/' ) ),
			'lookalike admin path' => array( array( 'redirect_to' => '/wp-admin-lookalike/' ) ),
		);
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

		$links = $this->get_prefetched_on_login( array( 'redirect_to' => 'https://attacker.example.com/wp-admin/post-new.php' ) );

		$this->assertPrefetched( $links, 'style', '#/wp-admin/css/common(\.min)?\.css#' );
		$this->assertNotPrefetched( $links, '#/wp-includes/css/dist/edit-post/#' );
		$this->assertSame( admin_url(), $filter->get_args()[0][1] );
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
	 * @param string $screen      Screen ID.
	 * @param string $next_screen Expected URL of the editor being prefetched for.
	 */
	public function test_admin_screen_prefetches_editor_assets( string $screen, string $next_screen ): void {
		define( 'CONCATENATE_SCRIPTS', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$filter = new MockAction();
		add_filter( 'prefetch_admin_assets', array( $filter, 'filter' ), 10, 2 );

		$links = $this->get_prefetched_on_admin_screen( $screen );

		$this->assertPrefetched( $links, 'style', '#/wp-includes/css/dist/edit-post/style(\.min)?\.css#' );
		$this->assertSame( array(), $this->get_hrefs( $links, 'script' ), 'No scripts should be prefetched for the editor.' );
		$this->assertSame( $next_screen, $filter->get_args()[0][1] );
	}

	/**
	 * Data provider for {@see self::test_admin_screen_prefetches_editor_assets()}.
	 *
	 * @return array<non-falsy-string, array{ 0: non-falsy-string, 1: non-falsy-string }>
	 */
	public function data_admin_screens_leading_to_editor(): array {
		return array(
			'Dashboard'  => array( 'dashboard', 'http://example.org/wp-admin/post-new.php?post_type=post' ),
			'Posts list' => array( 'edit', 'http://example.org/wp-admin/post-new.php?post_type=post' ),
			'Pages list' => array( 'edit-page', 'http://example.org/wp-admin/post-new.php?post_type=page' ),
		);
	}

	/**
	 * Tests that admin screens other than the Dashboard and the post list tables prefetch nothing.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_other_admin_screen_prints_nothing(): void {
		define( 'CONCATENATE_SCRIPTS', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( array(), $this->get_prefetched_on_admin_screen( 'plugins' ) );
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
	 * @param array<string, string> $request Request parameters of the login screen.
	 * @return list<array{ href: string, as: string }> Prefetch links in the order printed.
	 */
	private function get_prefetched_on_login( array $request = array() ): array {
		$_REQUEST = $request;

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
