<?php

/**
 * @group https-migration
 */
class Tests_HTTPS_Migration extends WP_UnitTestCase {

	/**
	 * @ticket 51437
	 */
	public function test_wp_should_replace_insecure_home_url() {
		// Should return false because site is not using HTTPS.
		$this->force_wp_is_using_https( false );
		$this->assertFalse( wp_should_replace_insecure_home_url() );

		// Should still return false because HTTPS migration flag is not set.
		$this->force_wp_is_using_https( true );
		$this->assertFalse( wp_should_replace_insecure_home_url() );

		// Should return false because HTTPS migration flag is marked as not required.
		update_option( 'https_migration_required', '0' );
		$this->assertFalse( wp_should_replace_insecure_home_url() );

		// Should return true because HTTPS migration flag is marked as required.
		update_option( 'https_migration_required', '1' );
		$this->assertTrue( wp_should_replace_insecure_home_url() );

		// Should be overridable via filter.
		add_filter( 'wp_should_replace_insecure_home_url', '__return_false' );
		$this->assertFalse( wp_should_replace_insecure_home_url() );
	}

	/**
	 * @ticket 51437
	 */
	public function test_wp_replace_insecure_home_url() {
		$http_url  = home_url( '', 'http' );
		$https_url = home_url( '', 'https' );

		$http_block_data  = array(
			'id'  => 3,
			'url' => $http_url . '/wp-content/uploads/2021/01/image.jpg',
		);
		$https_block_data = array(
			'id'  => 3,
			'url' => $https_url . '/wp-content/uploads/2021/01/image.jpg',
		);

		$content = '
			<!-- wp:paragraph -->
			<p><a href="%1$s">This is a link.</a></p>
			<!-- /wp:paragraph -->

			<!-- wp:custom-media %2$s -->
			<img src="%3$s" alt="">
			<!-- /wp:custom-media -->
			';

		$http_content  = sprintf( $content, $http_url, wp_json_encode( $http_block_data ), $http_block_data['url'] );
		$https_content = sprintf( $content, $https_url, wp_json_encode( $https_block_data ), $https_block_data['url'] );

		// Replaces URLs, including its encoded variant.
		add_filter( 'wp_should_replace_insecure_home_url', '__return_true' );
		$this->assertSame( $https_content, wp_replace_insecure_home_url( $http_content ) );

		// Does not replace anything if determined as unnecessary.
		add_filter( 'wp_should_replace_insecure_home_url', '__return_false' );
		$this->assertSame( $http_content, wp_replace_insecure_home_url( $http_content ) );
	}

	/**
	 * @ticket 51437
	 */
	public function test_wp_update_urls_to_https() {
		remove_all_filters( 'option_home' );
		remove_all_filters( 'option_siteurl' );
		remove_all_filters( 'home_url' );
		remove_all_filters( 'site_url' );

		$http_url  = 'http://example.org';
		$https_url = 'https://example.org';

		// Set up options to use HTTP URLs.
		update_option( 'home', $http_url );
		update_option( 'siteurl', $http_url );

		// Update URLs to HTTPS (successfully).
		$this->assertTrue( wp_update_urls_to_https() );
		$this->assertSame( $https_url, get_option( 'home' ) );
		$this->assertSame( $https_url, get_option( 'siteurl' ) );

		// Switch options back to use HTTP URLs, but now add filter to
		// force option value which will make the update irrelevant.
		update_option( 'home', $http_url );
		update_option( 'siteurl', $http_url );
		$this->force_option( 'home', $http_url );

		// Update URLs to HTTPS. While the update technically succeeds, it does not take effect due to the enforced
		// option. Therefore the change is expected to be reverted.
		$this->assertFalse( wp_update_urls_to_https() );
		$this->assertSame( $http_url, get_option( 'home' ) );
		$this->assertSame( $http_url, get_option( 'siteurl' ) );
	}

	/**
	 * @ticket 51437
	 */
	public function test_wp_update_https_migration_required() {
		// Changing HTTP to HTTPS on a site with content should result in flag being set, requiring migration.
		update_option( 'fresh_site', '0' );
		wp_update_https_migration_required( 'http://example.org', 'https://example.org' );
		$this->assertTrue( get_option( 'https_migration_required' ) );

		// Changing another part than the scheme should delete/reset the flag because changing those parts (e.g. the
		// domain) can have further implications.
		wp_update_https_migration_required( 'http://example.org', 'https://another-example.org' );
		$this->assertFalse( get_option( 'https_migration_required' ) );

		// Changing HTTP to HTTPS on a site without content should result in flag being set, but not requiring migration.
		update_option( 'fresh_site', '1' );
		wp_update_https_migration_required( 'http://example.org', 'https://example.org' );
		$this->assertFalse( get_option( 'https_migration_required' ) );

		// Changing (back) from HTTPS to HTTP should delete/reset the flag.
		wp_update_https_migration_required( 'https://example.org', 'http://example.org' );
		$this->assertFalse( get_option( 'https_migration_required' ) );
	}

	/**
	 * @ticket 51437
	 */
	public function test_wp_should_replace_insecure_home_url_integration() {
		// Setup (a site on HTTP, with existing content).
		remove_all_filters( 'option_home' );
		remove_all_filters( 'option_siteurl' );
		remove_all_filters( 'home_url' );
		remove_all_filters( 'site_url' );
		$http_url  = 'http://example.org';
		$https_url = 'https://example.org';
		update_option( 'home', $http_url );
		update_option( 'siteurl', $http_url );
		update_option( 'fresh_site', '0' );

		// Should return false when URLs are HTTP.
		$this->assertFalse( wp_should_replace_insecure_home_url() );

		// Should still return false because only one of the two URLs was updated to its HTTPS counterpart.
		update_option( 'home', $https_url );
		$this->assertFalse( wp_should_replace_insecure_home_url() );

		// Should return true because now both URLs are updated to their HTTPS counterpart.
		update_option( 'siteurl', $https_url );
		$this->assertTrue( wp_should_replace_insecure_home_url() );

		// Should return false because the domains of 'home' and 'siteurl' do not match, and we shouldn't make any
		// assumptions about such special cases.
		update_option( 'siteurl', 'https://wp.example.org' );
		$this->assertFalse( wp_should_replace_insecure_home_url() );
	}

	private function force_wp_is_using_https( $enabled ) {
		$scheme = $enabled ? 'https' : 'http';

		$replace_scheme = static function( $url ) use ( $scheme ) {
			return str_replace( array( 'http://', 'https://' ), $scheme . '://', $url );
		};

		add_filter( 'home_url', $replace_scheme, 99 );
		add_filter( 'site_url', $replace_scheme, 99 );
	}

	private function force_option( $option, $value ) {
		add_filter(
			"option_$option",
			static function() use ( $value ) {
				return $value;
			}
		);
	}
}
