<?php

/**
 * @group formatting
 *
 * @covers ::wp_trim_excerpt
 */
class Tests_Formatting_wpTrimExcerpt extends WP_UnitTestCase {
	/**
	 * @ticket 25349
	 */
	public function test_secondary_loop_respect_more() {
		$post1 = self::factory()->post->create(
			array(
				'post_content' => 'Post 1 Page 1<!--more-->Post 1 Page 2',
			)
		);
		$post2 = self::factory()->post->create(
			array(
				'post_content' => 'Post 2 Page 1<!--more-->Post 2 Page 2',
			)
		);

		$this->go_to( '/?p=' . $post1 );
		setup_postdata( get_post( $post1 ) );

		$q = new WP_Query(
			array(
				'post__in' => array( $post2 ),
			)
		);

		$this->assertTrue( $q->have_posts() );

		while ( $q->have_posts() ) {
			$q->the_post();
			$this->assertSame( 'Post 2 Page 1', wp_trim_excerpt() );
		}
	}

	/**
	 * @ticket 25349
	 */
	public function test_secondary_loop_respect_nextpage() {
		$post1 = self::factory()->post->create(
			array(
				'post_content' => 'Post 1 Page 1<!--nextpage-->Post 1 Page 2',
			)
		);
		$post2 = self::factory()->post->create(
			array(
				'post_content' => 'Post 2 Page 1<!--nextpage-->Post 2 Page 2',
			)
		);

		$this->go_to( '/?p=' . $post1 );
		setup_postdata( get_post( $post1 ) );

		$q = new WP_Query(
			array(
				'post__in' => array( $post2 ),
			)
		);

		$this->assertTrue( $q->have_posts() );

		while ( $q->have_posts() ) {
			$q->the_post();
			$this->assertSame( 'Post 2 Page 1', wp_trim_excerpt() );
		}
	}

	/**
	 * @ticket 51042
	 */
	public function test_should_generate_excerpt_for_empty_values() {
		if ( PHP_VERSION_ID >= 80100 ) {
			/*
			 * For the time being, ignoring PHP 8.1 "null to non-nullable" deprecations coming in
			 * via hooked in filter functions until a more structural solution to the
			 * "missing input validation" conundrum has been architected and implemented.
			 */
			$this->expectDeprecation();
			$this->expectDeprecationMessageMatches( '`Passing null to parameter \#[0-9]+ \(\$[^\)]+\) of type [^ ]+ is deprecated`' );
		}

		$post = self::factory()->post->create(
			array(
				'post_content' => 'Post content',
			)
		);

		$this->assertSame( 'Post content', wp_trim_excerpt( '', $post ) );
		$this->assertSame( 'Post content', wp_trim_excerpt( null, $post ) );
		$this->assertSame( 'Post content', wp_trim_excerpt( false, $post ) );
	}

	/**
	 * Tests that `wp_trim_excerpt()` unhooks `wp_filter_content_tags()` from 'the_content' filter.
	 *
	 * @ticket 56588
	 */
	public function test_wp_trim_excerpt_unhooks_wp_filter_content_tags() {
		$post = self::factory()->post->create();

		/*
		 * Record that during 'the_content' filter run by wp_trim_excerpt() the
		 * wp_filter_content_tags() callback is not used.
		 */
		$has_filter = true;
		add_filter(
			'the_content',
			static function ( $content ) use ( &$has_filter ) {
				$has_filter = has_filter( 'the_content', 'wp_filter_content_tags' );
				return $content;
			}
		);

		wp_trim_excerpt( '', $post );

		$this->assertFalse( $has_filter, 'wp_filter_content_tags() was not unhooked in wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` doesn't permanently unhook `wp_filter_content_tags()` from 'the_content' filter.
	 *
	 * @ticket 56588
	 */
	public function test_wp_trim_excerpt_should_not_permanently_unhook_wp_filter_content_tags() {
		$post = self::factory()->post->create();

		wp_trim_excerpt( '', $post );

		$this->assertSame( 12, has_filter( 'the_content', 'wp_filter_content_tags' ), 'wp_filter_content_tags() was not restored in wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` doesn't restore `wp_filter_content_tags()` if it was previously unhooked.
	 *
	 * @ticket 56588
	 */
	public function test_wp_trim_excerpt_does_not_restore_wp_filter_content_tags_if_previously_unhooked() {
		$post = self::factory()->post->create();

		// Remove wp_filter_content_tags() from 'the_content' filter generally.
		remove_filter( 'the_content', 'wp_filter_content_tags', 12 );

		wp_trim_excerpt( '', $post );

		// Assert that the filter callback was not restored after running 'the_content'.
		$this->assertFalse( has_filter( 'the_content', 'wp_filter_content_tags' ) );
	}

	/**
	 * Tests that `wp_trim_excerpt()` does process valid blocks.
	 *
	 * @ticket 58682
	 */
	public function test_wp_trim_excerpt_check_if_block_renders() {
		$post = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:paragraph --> <p>A test paragraph</p> <!-- /wp:paragraph -->',
			)
		);

		$output_text = wp_trim_excerpt( '', $post );

		$this->assertSame( 'A test paragraph', $output_text, 'wp_trim_excerpt() did not process paragraph block.' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` unhooks `do_blocks()` from 'the_content' filter.
	 *
	 * @ticket 58682
	 */
	public function test_wp_trim_excerpt_unhooks_do_blocks() {
		$post = self::factory()->post->create();

		/*
		 * Record that during 'the_content' filter run by wp_trim_excerpt() the
		 * do_blocks() callback is not used.
		 */
		$has_filter = true;
		add_filter(
			'the_content',
			static function ( $content ) use ( &$has_filter ) {
				$has_filter = has_filter( 'the_content', 'do_blocks' );
				return $content;
			}
		);

		wp_trim_excerpt( '', $post );

		$this->assertFalse( $has_filter, 'do_blocks() was not unhooked in wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` doesn't permanently unhook `do_blocks()` from 'the_content' filter.
	 *
	 * @ticket 58682
	 */
	public function test_wp_trim_excerpt_should_not_permanently_unhook_do_blocks() {
		$post = self::factory()->post->create();

		wp_trim_excerpt( '', $post );

		$this->assertSame( 9, has_filter( 'the_content', 'do_blocks' ), 'do_blocks() was not restored in wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` doesn't restore `do_blocks()` if it was previously unhooked.
	 *
	 * @ticket 58682
	 */
	public function test_wp_trim_excerpt_does_not_restore_do_blocks_if_previously_unhooked() {
		$post = self::factory()->post->create();

		// Remove do_blocks() from 'the_content' filter generally.
		remove_filter( 'the_content', 'do_blocks', 9 );

		wp_trim_excerpt( '', $post );

		// Assert that the filter callback was not restored after running 'the_content'.
		$this->assertFalse( has_filter( 'the_content', 'do_blocks' ) );
	}

	/**
	 * Tests that `wp_trim_excerpt()` unhooks `apply_block_hooks_to_content_from_post_object()` from 'the_content' filter.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_unhooks_apply_block_hooks_to_content_from_post_object() {
		$post = self::factory()->post->create();

		/*
		 * Record that during 'the_content' filter run by wp_trim_excerpt() the
		 * apply_block_hooks_to_content_from_post_object() callback is not used.
		 */
		$has_filter = true;
		add_filter(
			'the_content',
			static function ( $content ) use ( &$has_filter ) {
				$has_filter = has_filter( 'the_content', 'apply_block_hooks_to_content_from_post_object' );
				return $content;
			}
		);

		wp_trim_excerpt( '', $post );

		$this->assertFalse( $has_filter, 'apply_block_hooks_to_content_from_post_object() was not unhooked in wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` doesn't permanently unhook `apply_block_hooks_to_content_from_post_object()` from 'the_content' filter.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_should_not_permanently_unhook_apply_block_hooks_to_content_from_post_object() {
		$post = self::factory()->post->create();

		wp_trim_excerpt( '', $post );

		$this->assertSame( 8, has_filter( 'the_content', 'apply_block_hooks_to_content_from_post_object' ), 'apply_block_hooks_to_content_from_post_object() was not restored in wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` doesn't restore `apply_block_hooks_to_content_from_post_object()` if it was previously unhooked.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_does_not_restore_apply_block_hooks_to_content_from_post_object_if_previously_unhooked() {
		$post = self::factory()->post->create();

		// Remove apply_block_hooks_to_content_from_post_object() from 'the_content' filter generally.
		remove_filter( 'the_content', 'apply_block_hooks_to_content_from_post_object', 8 );

		wp_trim_excerpt( '', $post );

		// Assert that the filter callback was not restored after running 'the_content'.
		$this->assertFalse( has_filter( 'the_content', 'apply_block_hooks_to_content_from_post_object' ) );
	}

	/**
	 * Tests that the Block Hooks algorithm is not run while `wp_trim_excerpt()`
	 * generates an excerpt.
	 *
	 * The 'hooked_block_types' filter only fires inside the Block Hooks
	 * algorithm, so counting its invocations detects whether the algorithm ran.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_does_not_run_block_hooks_algorithm() {
		$post = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:paragraph --><p>A test paragraph.</p><!-- /wp:paragraph -->',
			)
		);

		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'core/post-content' => 'last_child',
				),
			)
		);

		$hooked_block_types_count = 0;
		$spy                      = static function ( $hooked_block_types ) use ( &$hooked_block_types_count ) {
			++$hooked_block_types_count;
			return $hooked_block_types;
		};
		add_filter( 'hooked_block_types', $spy );

		wp_trim_excerpt( '', $post );

		remove_filter( 'hooked_block_types', $spy );
		unregister_block_type( 'tests/hooked-block' );

		$this->assertSame( 0, $hooked_block_types_count, 'Block Hooks algorithm ran during wp_trim_excerpt()' );
	}

	/**
	 * Tests that markup of a hooked block does not leak into the generated excerpt.
	 *
	 * apply_block_hooks_to_content_from_post_object() wraps delimiter-free
	 * content in a temporary core/post-content wrapper block, which acts as an
	 * anchor for hooked blocks. Since do_blocks() is unhooked while the excerpt
	 * is generated, an inserted hooked block is never rendered, and any markup
	 * provided via the 'hooked_block' filter would leak into the excerpt as
	 * plain text once the block delimiters are stripped.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_does_not_leak_hooked_block_markup() {
		$post = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:paragraph --><p>Visible text.</p><!-- /wp:paragraph -->',
			)
		);

		// Emulate a front-end loop, as the callback resolves context via get_post().
		$GLOBALS['post'] = get_post( $post );
		setup_postdata( $GLOBALS['post'] );

		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'core/post-content' => 'last_child',
				),
			)
		);

		// Give the hooked block actual markup, as the 'hooked_block' filter allows.
		$filter = static function ( $parsed_hooked_block ) {
			$parsed_hooked_block['innerHTML']    = '<p>LEAKED HOOKED TEXT</p>';
			$parsed_hooked_block['innerContent'] = array( '<p>LEAKED HOOKED TEXT</p>' );
			return $parsed_hooked_block;
		};
		add_filter( 'hooked_block', $filter );

		$excerpt = wp_trim_excerpt( '', $post );

		remove_filter( 'hooked_block', $filter );
		unregister_block_type( 'tests/hooked-block' );

		$this->assertSame( 'Visible text.', $excerpt, 'Hooked block markup leaked into the excerpt generated by wp_trim_excerpt()' );
	}

	/**
	 * Tests that Block Hooks still run on 'the_content' after `wp_trim_excerpt()`
	 * has generated an excerpt.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_restores_block_hooks_for_subsequent_the_content() {
		$post = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:paragraph --><p>Visible text.</p><!-- /wp:paragraph -->',
			)
		);

		// Emulate a front-end loop, as the callback resolves context via get_post().
		$GLOBALS['post'] = get_post( $post );
		setup_postdata( $GLOBALS['post'] );

		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'core/post-content' => 'last_child',
				),
			)
		);

		$filter = static function ( $parsed_hooked_block ) {
			$parsed_hooked_block['innerHTML']    = '<p>HOOKED TEXT</p>';
			$parsed_hooked_block['innerContent'] = array( '<p>HOOKED TEXT</p>' );
			return $parsed_hooked_block;
		};
		add_filter( 'hooked_block', $filter );

		// Generate an excerpt, which unhooks and restores the callback.
		wp_trim_excerpt( '', $post );

		// A subsequent front-end run of 'the_content' must still apply Block Hooks.
		$content = apply_filters( 'the_content', get_the_content( '', false, $post ) );

		remove_filter( 'hooked_block', $filter );
		unregister_block_type( 'tests/hooked-block' );

		$this->assertStringContainsString( 'HOOKED TEXT', $content, 'Block Hooks did not run on the_content after wp_trim_excerpt()' );
	}
}
