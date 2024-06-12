<?php

/**
 * @group oembed
 */
class Tests_Embed_Template extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		global $wp_scripts;
		$wp_scripts = null;
	}

	public function tear_down() {
		parent::tear_down();

		global $wp_scripts;
		$wp_scripts = null;
	}

	public function test_oembed_output_post() {
		$user = self::factory()->user->create_and_get(
			array(
				'display_name' => 'John Doe',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $user->ID,
				'post_title'   => 'Hello World',
				'post_content' => 'Foo Bar',
				'post_excerpt' => 'Bar Baz',
			)
		);
		$this->go_to( get_post_embed_url( $post_id ) );

		$this->assertQueryTrue( 'is_single', 'is_singular', 'is_embed' );

		// `print_embed_scripts()` assumes `wp-includes/js/wp-embed-template.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-embed-template.js' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringNotContainsString( 'That embed cannot be found.', $actual );
		$this->assertStringContainsString( 'Hello World', $actual );
	}

	public function test_oembed_output_post_with_thumbnail() {
		$post_id       = self::factory()->post->create(
			array(
				'post_title'   => 'Hello World',
				'post_content' => 'Foo Bar',
				'post_excerpt' => 'Bar Baz',
			)
		);
		$file          = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		set_post_thumbnail( $post_id, $attachment_id );

		$this->go_to( get_post_embed_url( $post_id ) );

		$this->assertQueryTrue( 'is_single', 'is_singular', 'is_embed' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringNotContainsString( 'That embed cannot be found.', $actual );
		$this->assertStringContainsString( 'Hello World', $actual );
		$this->assertStringContainsString( 'canola.jpg', $actual );
	}

	public function test_oembed_output_404() {
		$this->go_to( home_url( '/?p=123&embed=true' ) );
		$GLOBALS['wp_query']->query_vars['embed'] = true;

		$this->assertQueryTrue( 'is_404', 'is_embed' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringContainsString( 'That embed cannot be found.', $actual );
	}

	public function test_oembed_output_attachment() {
		$post          = self::factory()->post->create_and_get();
		$file          = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = self::factory()->attachment->create_object(
			$file,
			$post->ID,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Hello World',
				'post_content'   => 'Foo Bar',
				'post_excerpt'   => 'Bar Baz',
			)
		);

		$this->go_to( get_post_embed_url( $attachment_id ) );

		$this->assertQueryTrue( 'is_single', 'is_singular', 'is_attachment', 'is_embed' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringNotContainsString( 'That embed cannot be found.', $actual );
		$this->assertStringContainsString( 'Hello World', $actual );
		$this->assertStringContainsString( 'canola.jpg', $actual );
	}

	public function test_oembed_output_draft_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello World',
				'post_content' => 'Foo Bar',
				'post_excerpt' => 'Bar Baz',
				'post_status'  => 'draft',
			)
		);

		$this->go_to( get_post_embed_url( $post_id ) );

		$this->assertQueryTrue( 'is_404', 'is_embed' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringContainsString( 'That embed cannot be found.', $actual );
	}

	public function test_oembed_output_scheduled_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello World',
				'post_content' => 'Foo Bar',
				'post_excerpt' => 'Bar Baz',
				'post_status'  => 'future',
				'post_date'    => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);

		$this->go_to( get_post_embed_url( $post_id ) );

		$this->assertQueryTrue( 'is_404', 'is_embed' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringContainsString( 'That embed cannot be found.', $actual );
	}

	public function test_oembed_output_private_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello World',
				'post_content' => 'Foo Bar',
				'post_excerpt' => 'Bar Baz',
				'post_status'  => 'private',
			)
		);

		$this->go_to( get_post_embed_url( $post_id ) );

		$this->assertQueryTrue( 'is_404', 'is_embed' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringContainsString( 'That embed cannot be found.', $actual );
	}

	public function test_oembed_output_private_post_with_permissions() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello World',
				'post_content' => 'Foo Bar',
				'post_excerpt' => 'Bar Baz',
				'post_status'  => 'private',
				'post_author'  => $user_id,
			)
		);

		$this->go_to( get_post_embed_url( $post_id ) );

		$this->assertQueryTrue( 'is_single', 'is_singular', 'is_embed' );

		ob_start();
		require ABSPATH . WPINC . '/theme-compat/embed.php';
		$actual = ob_get_clean();

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML( $actual ) );
		$this->assertStringNotContainsString( 'That embed cannot be found.', $actual );
		$this->assertStringContainsString( 'Hello World', $actual );
	}

	public function test_wp_embed_excerpt_more_no_embed() {
		$GLOBALS['wp_query'] = new WP_Query();

		$this->assertSame( 'foo bar', wp_embed_excerpt_more( 'foo bar' ) );
	}

	public function test_wp_embed_excerpt_more() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Foo Bar',
				'post_content' => 'Bar Baz',
			)
		);

		$this->assertSame( '', wp_embed_excerpt_more( '' ) );

		$this->go_to( get_post_embed_url( $post_id ) );

		$actual = wp_embed_excerpt_more( '' );

		$expected = sprintf(
			' &hellip; <a href="%s" class="wp-embed-more" target="_top">Continue reading <span class="screen-reader-text">Foo Bar</span></a>',
			get_the_permalink()
		);

		$this->assertSame( $expected, $actual );
	}

	public function test_is_embed_post() {
		$this->assertFalse( is_embed() );

		$post_id = self::factory()->post->create();
		$this->go_to( get_post_embed_url( $post_id ) );
		$this->assertTrue( is_embed() );
	}

	public function test_is_embed_attachment() {
		$post_id       = self::factory()->post->create();
		$file          = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->go_to( get_post_embed_url( $attachment_id ) );
		$this->assertTrue( is_embed() );
	}

	public function test_is_embed_404() {
		$this->go_to( home_url( '/?p=12345&embed=true' ) );
		$this->assertTrue( is_embed() );
	}

	public function test_get_post_embed_html_non_existent_post() {
		$this->assertFalse( get_post_embed_html( 200, 200, 0 ) );
		$this->assertFalse( get_post_embed_html( 200, 200 ) );
	}

	public function test_get_post_embed_html() {
		$post_id = self::factory()->post->create();
		$title   = esc_attr(
			sprintf(
				__( '&#8220;%1$s&#8221; &#8212; %2$s' ),
				get_the_title( $post_id ),
				get_bloginfo( 'name' )
			)
		);

		$expected = '<iframe sandbox="allow-scripts" security="restricted" src="' . esc_url( get_post_embed_url( $post_id ) ) . '#?secret=__SECRET__" width="200" height="200" title="' . $title . '" data-secret=__SECRET__ frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content"></iframe>';
		$actual   = get_post_embed_html( 200, 200, $post_id );
		$actual   = preg_replace( '/secret=("?)\w+\1/', 'secret=__SECRET__', $actual );

		$this->assertStringStartsWith( '<blockquote class="wp-embedded-content" data-secret=__SECRET__>', $actual );
		$this->assertStringContainsString( $expected, $actual );
		$this->assertStringEndsWith( '</script>', trim( $actual ) );
	}

	/** @covers ::wp_oembed_add_host_js() */
	public function test_add_host_js() {
		remove_all_filters( 'embed_oembed_html' );

		// This function is now a no-op.
		wp_oembed_add_host_js();

		$this->assertFalse( has_filter( 'embed_oembed_html', 'wp_maybe_enqueue_oembed_host_js' ) );
	}

	/** @covers ::wp_maybe_enqueue_oembed_host_js() */
	public function test_wp_maybe_enqueue_oembed_host_js() {
		$scripts = wp_scripts();

		$this->assertFalse( $scripts->query( 'wp-embed', 'enqueued' ) );

		$post_embed     = '<blockquote class="wp-embedded-content" data-secret="S24AQCJW9i"><a href="https://make.wordpress.org/core/2016/03/11/embeds-changes-in-wordpress-4-5/">Embeds Changes in WordPress 4.5</a></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);" title="&#8220;Embeds Changes in WordPress 4.5&#8221; &#8212; Make WordPress Core" src="https://make.wordpress.org/core/2016/03/11/embeds-changes-in-wordpress-4-5/embed/#?secret=S24AQCJW9i" data-secret="S24AQCJW9i" width="600" height="338" frameborder="0" marginwidth="0" marginheight="0" scrolling="no"></iframe>';
		$non_post_embed = '<iframe title="Zoo Cares For 23 Tiny Pond Turtles" width="750" height="422" src="https://www.youtube.com/embed/6ZXHqUjL6f8?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';

		wp_maybe_enqueue_oembed_host_js( $non_post_embed );
		$this->assertFalse( $scripts->query( 'wp-embed', 'enqueued' ) );

		wp_maybe_enqueue_oembed_host_js( $post_embed );
		$this->assertTrue( $scripts->query( 'wp-embed', 'enqueued' ) );
	}

	/** @covers ::wp_maybe_enqueue_oembed_host_js() */
	public function test_wp_maybe_enqueue_oembed_host_js_without_wp_head_action() {
		$scripts = wp_scripts();

		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		$this->assertFalse( $scripts->query( 'wp-embed', 'enqueued' ) );

		$post_embed = '<blockquote class="wp-embedded-content" data-secret="S24AQCJW9i"><a href="https://make.wordpress.org/core/2016/03/11/embeds-changes-in-wordpress-4-5/">Embeds Changes in WordPress 4.5</a></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);" title="&#8220;Embeds Changes in WordPress 4.5&#8221; &#8212; Make WordPress Core" src="https://make.wordpress.org/core/2016/03/11/embeds-changes-in-wordpress-4-5/embed/#?secret=S24AQCJW9i" data-secret="S24AQCJW9i" width="600" height="338" frameborder="0" marginwidth="0" marginheight="0" scrolling="no"></iframe>';

		wp_maybe_enqueue_oembed_host_js( $post_embed );
		$this->assertFalse( $scripts->query( 'wp-embed', 'enqueued' ) );
	}

	/**
	 * Confirms that no ampersands exist in src/wp-includes/js/wp-embed.js.
	 *
	 * See also the `verify:wp-embed` Grunt task for verifying the built file.
	 *
	 * @ticket 34698
	 */
	public function test_js_no_ampersands() {
		$this->assertStringNotContainsString( '&', file_get_contents( ABSPATH . WPINC . '/js/wp-embed.js' ) );
	}
}
