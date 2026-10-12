<?php
/**
 * Tests for robots template functions and filters.
 *
 * @package WordPress
 *
 * @group robots
 */
class Tests_Robots extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		remove_all_filters( 'wp_robots' );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_renders_when_relevant() {
		// Do not render robots meta tag when there are no directives.
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );

		// Render robots meta tag with noindex.
		add_filter( 'wp_robots', array( $this, 'add_noindex_directive' ) );
		$output = get_echo( 'wp_robots' );
		$this->assertSame( "<meta name='robots' content='noindex' />\n", $output );

		// Do not render robots meta tag when there are only false-y directives.
		add_filter( 'wp_robots', array( $this, 'remove_noindex_directive' ), 11 );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_parses_directives_correctly() {
		add_filter(
			'wp_robots',
			static function ( array $robots ) {
				// Directives that should have values must use strings.
				$robots['directive-with-value']         = 'yes';
				$robots['directive-with-numeric-value'] = '1';
				// Any non-string value will be evaluated as boolean.
				// False-y directives will not be included.
				$robots['directive-active-boolean']   = true;
				$robots['directive-inactive-boolean'] = false;
				$robots['directive-active-integer']   = 1;
				$robots['directive-inactive-integer'] = 0;
				return $robots;
			}
		);

		$expected_directives_string = implode(
			', ',
			array(
				'directive-with-value:yes',
				'directive-with-numeric-value:1',
				'directive-active-boolean',
				'directive-active-integer',
			)
		);

		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'{$expected_directives_string}'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_noindex() {
		add_filter( 'wp_robots', 'wp_robots_noindex' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, nofollow'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_no_robots() {
		add_filter( 'wp_robots', 'wp_robots_no_robots' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, follow'", $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, nofollow'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_sensitive_page() {
		add_filter( 'wp_robots', 'wp_robots_sensitive_page' );

		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, noarchive'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_max_image_preview_large() {
		add_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'max-image-preview:large'", $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );
	}

	/**
	 * @ticket 52457
	 */
	public function test_wp_robots_search_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_search' );
		$this->go_to( home_url( '?s=ticket+52457+core.trac.wordpress.org' ) );

		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( 'noindex', $output );
	}

	/**
	 * @ticket 52457
	 */
	public function test_wp_robots_non_search_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_search' );
		$this->go_to( home_url() );

		$output = get_echo( 'wp_robots' );
		$this->assertStringNotContainsString( 'noindex', $output );
	}

	/**
	 * @ticket 63467
	 */
	public function test_do_robots_uses_filtered_admin_url_paths(): void {
		add_filter(
			'admin_url',
			static function ( string $url, string $path, ?int $blog_id, string $scheme ): string {
				return home_url( "/control/$path", $scheme );
			},
			10,
			4
		);

		$output = get_echo( 'do_robots' );

		$this->assertStringNotContainsString( 'wp-admin', $output );
		$this->assertStringContainsString( "Disallow: /control/\n", $output );
		$this->assertStringContainsString( "Allow: /control/admin-ajax.php\n", $output );
	}

	public function add_noindex_directive( array $robots ) {
		$robots['noindex'] = true;
		return $robots;
	}

	public function remove_noindex_directive( array $robots ) {
		$robots['noindex'] = false;
		return $robots;
	}
}
