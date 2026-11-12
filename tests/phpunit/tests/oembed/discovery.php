<?php

/**
 * @group oembed
 */
class Tests_oEmbed_Discovery extends WP_UnitTestCase {
	public function test_add_oembed_discovery_links_non_singular() {
		$this->assertSame( '', get_echo( 'wp_oembed_add_discovery_links' ) );
	}

	public function test_add_oembed_discovery_links_front_page() {
		$this->go_to( home_url() );
		$this->assertSame( '', get_echo( 'wp_oembed_add_discovery_links' ) );
		$this->assertSame( 0, url_to_postid( home_url() ) );
	}

	/**
	 * @ticket 34971
	 */
	public function test_add_oembed_discovery_links_static_front_page() {
		update_option( 'show_on_front', 'page' );
		update_option(
			'page_on_front',
			self::factory()->post->create(
				array(
					'post_title' => 'front-page',
					'post_type'  => 'page',
				)
			)
		);

		$this->go_to( home_url() );
		$this->assertQueryTrue( 'is_front_page', 'is_singular', 'is_page' );

		$expected  = '<link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink() ) ) . '" />' . "\n";
		$expected .= '<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink(), 'xml' ) ) . '" />' . "\n";

		$this->assertSame( $expected, get_echo( 'wp_oembed_add_discovery_links' ) );

		update_option( 'show_on_front', 'posts' );
	}

	public function test_add_oembed_discovery_links_to_post() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );

		$expected  = '<link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink() ) ) . '" />' . "\n";
		$expected .= '<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink(), 'xml' ) ) . '" />' . "\n";

		$this->assertSame( $expected, get_echo( 'wp_oembed_add_discovery_links' ) );

		add_filter( 'oembed_discovery_links', '__return_empty_string' );
		$this->assertSame( '', get_echo( 'wp_oembed_add_discovery_links' ), 'Expected filtering oembed_discovery_links to empty string to result in no wp_oembed_add_discovery_links() output.' );
	}

	public function test_add_oembed_discovery_links_to_page() {
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		$this->go_to( get_permalink( $post_id ) );
		$this->assertQueryTrue( 'is_page', 'is_singular' );

		$expected  = '<link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink() ) ) . '" />' . "\n";
		$expected .= '<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink(), 'xml' ) ) . '" />' . "\n";

		$this->assertSame( $expected, get_echo( 'wp_oembed_add_discovery_links' ) );
	}

	public function test_add_oembed_discovery_links_to_attachment() {
		$post_id       = self::factory()->post->create();
		$file          = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->go_to( get_permalink( $attachment_id ) );
		$this->assertQueryTrue( 'is_attachment', 'is_singular', 'is_single' );

		$expected  = '<link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink() ) ) . '" />' . "\n";
		$expected .= '<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink(), 'xml' ) ) . '" />' . "\n";

		$this->assertSame( $expected, get_echo( 'wp_oembed_add_discovery_links' ) );
	}

	/**
	 * @ticket 35567
	 */
	public function test_wp_oembed_add_discovery_links_non_embeddable_post_type_output_should_be_empty() {
		register_post_type( 'not_embeddable', array( 'embeddable' => false ) );

		$post = self::factory()->post->create_and_get(
			array(
				'post_type' => 'not_embeddable',
			)
		);

		$this->assertFalse( get_oembed_response_data( $post, 100 ) );
	}

	/**
	 * @ticket 64178
	 * @covers ::wp_oembed_add_discovery_links
	 */
	public function test_wp_oembed_add_discovery_links_back_compat() {
		$action       = 'wp_head';
		$old_priority = 10;
		$new_priority = 4;
		$callback     = 'wp_oembed_add_discovery_links';

		$this->assertTrue( has_action( $action, $callback, $old_priority ), 'Expected wp_oembed_add_discovery_links() to be hooked at wp_head with old priority.' );
		$this->assertTrue( has_action( $action, $callback, $new_priority ), 'Expected wp_oembed_add_discovery_links() to be hooked at wp_head with new priority.' );

		// Remove all wp_head actions and re-add just the one being tested.
		remove_all_actions( $action );
		add_action( $action, $callback, $old_priority );
		add_action( $action, $callback, $new_priority );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );

		$mock_action = new MockAction();
		add_filter( 'oembed_discovery_links', array( $mock_action, 'filter' ) );

		$wp_head_output = get_echo( 'wp_head' );
		$this->assertSame( 1, $mock_action->get_call_count() );

		$expected  = '<link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink() ) ) . '" />' . "\n";
		$expected .= '<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="' . esc_url( get_oembed_endpoint_url( get_permalink(), 'xml' ) ) . '" />' . "\n";

		$this->assertSame( $expected, $wp_head_output, 'Expected wp_head output to be the same as the wp_oembed_add_discovery_links() output.' );
		$this->assertSame( $expected, get_echo( $callback ), 'Expected wp_oembed_add_discovery_links() output to be the same as the wp_head output when called outside of wp_head.' );
	}
}
