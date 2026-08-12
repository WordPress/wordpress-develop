<?php
/**
 * Test wp_pingback_link().
 *
 * @group general
 * @group template
 *
 * @covers ::wp_pingback_link
 */
class Tests_General_WpPingbackLink extends WP_UnitTestCase {
	/**
	 * @ticket 43791
	 */
	public function test_wp_pingback_link_is_hooked_to_wp_head() {
		$this->assertSame( 10, has_action( 'wp_head', 'wp_pingback_link' ) );
	}

	/**
	 * @ticket 43791
	 */
	public function test_wp_pingback_link_outputs_link_on_singular_when_pings_are_open() {
		$post_id = self::factory()->post->create(
			array(
				'ping_status' => 'open',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$expected = sprintf(
			'<link rel="pingback" href="%s" />' . "\n",
			esc_url( get_bloginfo( 'pingback_url', 'display' ) )
		);

		$this->assertSame( $expected, get_echo( 'wp_pingback_link' ) );
	}

	/**
	 * @ticket 43791
	 */
	public function test_wp_pingback_link_respects_filtered_pingback_url() {
		$post_id = self::factory()->post->create(
			array(
				'ping_status' => 'open',
			)
		);
		$filter  = static function ( $output, $show ) {
			if ( 'pingback_url' === $show ) {
				return 'https://example.com/pingback';
			}

			return $output;
		};

		add_filter( 'bloginfo_url', $filter, 10, 2 );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame(
			'<link rel="pingback" href="https://example.com/pingback" />' . "\n",
			get_echo( 'wp_pingback_link' )
		);
		remove_filter( 'bloginfo_url', $filter, 10 );
	}

	/**
	 * @ticket 43791
	 */
	public function test_wp_pingback_link_outputs_nothing_when_pings_are_closed() {
		$post_id = self::factory()->post->create(
			array(
				'ping_status' => 'closed',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertEmpty( get_echo( 'wp_pingback_link' ) );
	}

	/**
	 * @ticket 43791
	 */
	public function test_wp_pingback_link_outputs_nothing_when_not_singular() {
		self::factory()->post->create(
			array(
				'ping_status' => 'open',
			)
		);

		$this->go_to( home_url( '/' ) );

		$this->assertEmpty( get_echo( 'wp_pingback_link' ) );
	}
}
