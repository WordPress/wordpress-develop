<?php

/**
 * @group formatting
 *
 * @covers ::wp_trim_excerpt
 */
class Tests_Formatting_wpTrimExcerpt extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'core/post-content' => 'last_child',
				),
			)
		);
	}

	public function tear_down() {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'tests/hooked-block' ) ) {
			unregister_block_type( 'tests/hooked-block' );
		}

		parent::tear_down();
	}

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

		$hooked_block_types_count = 0;
		$spy                      = static function ( $hooked_block_types ) use ( &$hooked_block_types_count ) {
			++$hooked_block_types_count;
			return $hooked_block_types;
		};
		add_filter( 'hooked_block_types', $spy );

		wp_trim_excerpt( '', $post );

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

		// Give the hooked block actual markup, as the 'hooked_block' filter allows.
		$filter = static function ( array $parsed_hooked_block ): array {
			$parsed_hooked_block['innerHTML']    = '<p>LEAKED HOOKED TEXT</p>';
			$parsed_hooked_block['innerContent'] = array( '<p>LEAKED HOOKED TEXT</p>' );
			return $parsed_hooked_block;
		};
		add_filter( 'hooked_block', $filter );

		$excerpt = wp_trim_excerpt( '', $post );

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

		$filter = static function ( array $parsed_hooked_block ): array {
			$parsed_hooked_block['innerHTML']    = '<p>HOOKED TEXT</p>';
			$parsed_hooked_block['innerContent'] = array( '<p>HOOKED TEXT</p>' );
			return $parsed_hooked_block;
		};
		add_filter( 'hooked_block', $filter );

		// Generate an excerpt, which unhooks and restores the callback.
		wp_trim_excerpt( '', $post );

		// A subsequent front-end run of 'the_content' must still apply Block Hooks.
		/** This filter is documented in wp-includes/post-template.php */
		$content = apply_filters( 'the_content', get_the_content( '', false, $post ) );

		$this->assertStringContainsString( 'HOOKED TEXT', $content, 'Block Hooks did not run on the_content after wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` unhooks and restores 'the_content' filter
	 * callbacks that were moved to a non-default priority.
	 *
	 * @ticket 66110
	 *
	 * @dataProvider data_wp_trim_excerpt_unhooks_callbacks_at_custom_priority
	 *
	 * @param string $callback         The filter callback.
	 * @param int    $default_priority The callback's default priority.
	 * @param int    $custom_priority  The priority the callback is moved to.
	 */
	public function test_wp_trim_excerpt_unhooks_callbacks_at_custom_priority( $callback, $default_priority, $custom_priority ) {
		$post = self::factory()->post->create();

		remove_filter( 'the_content', $callback, $default_priority );
		add_filter( 'the_content', $callback, $custom_priority );

		$has_filter = true;
		add_filter(
			'the_content',
			static function ( $content ) use ( &$has_filter, $callback ) {
				$has_filter = has_filter( 'the_content', $callback );
				return $content;
			},
			PHP_INT_MAX
		);

		wp_trim_excerpt( '', $post );

		$this->assertFalse( $has_filter, "{$callback}() was not unhooked in wp_trim_excerpt()" );
		$this->assertSame( $custom_priority, has_filter( 'the_content', $callback ), "{$callback}() was not restored at its original priority" );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_trim_excerpt_unhooks_callbacks_at_custom_priority() {
		return array(
			'content tags' => array( 'wp_filter_content_tags', 12, 13 ),
			'do_blocks'    => array( 'do_blocks', 9, 7 ),
			'block hooks'  => array( 'apply_block_hooks_to_content_from_post_object', 8, 7 ),
		);
	}

	/**
	 * Tests that `wp_trim_excerpt()` unhooks and restores a callback registered at multiple priorities.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_unhooks_do_blocks_registered_at_multiple_priorities() {
		global $wp_filter;

		$post = self::factory()->post->create();

		remove_filter( 'the_content', 'do_blocks', 9 );
		add_filter( 'the_content', 'do_blocks', 7 );
		add_filter( 'the_content', 'do_blocks', 11 );

		$has_filter = true;
		add_filter(
			'the_content',
			static function ( $content ) use ( &$has_filter ) {
				$has_filter = has_filter( 'the_content', 'do_blocks' );
				return $content;
			},
			PHP_INT_MAX
		);

		wp_trim_excerpt( '', $post );

		$this->assertFalse( $has_filter, 'do_blocks() was not unhooked at every priority in wp_trim_excerpt()' );
		$this->assertArrayHasKey( 'do_blocks', $wp_filter['the_content']->callbacks[7], 'do_blocks() was not restored at priority 7' );
		$this->assertArrayHasKey( 'do_blocks', $wp_filter['the_content']->callbacks[11], 'do_blocks() was not restored at priority 11' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` restores unhooked callbacks sharing a priority in their original order.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_preserves_order_of_callbacks_sharing_a_priority() {
		global $wp_filter;

		$post = self::factory()->post->create();

		// Move do_blocks() onto the priority of the Block Hooks callback, after it.
		remove_filter( 'the_content', 'do_blocks', 9 );
		add_filter( 'the_content', 'do_blocks', 8 );

		$group = array( 'apply_block_hooks_to_content_from_post_object', 'do_blocks' );
		$this->assertSame( $group, array_values( array_intersect( array_keys( $wp_filter['the_content']->callbacks[8] ), $group ) ), 'Unexpected initial order' );

		wp_trim_excerpt( '', $post );

		$this->assertSame( $group, array_values( array_intersect( array_keys( $wp_filter['the_content']->callbacks[8] ), $group ) ), 'Block Hooks no longer run before do_blocks() after wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` unhooks and restores every registration of a callback.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_unhooks_all_registrations_of_a_callback() {
		$post = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:paragraph --><p>Visible text.</p><!-- /wp:paragraph -->',
			)
		);

		$GLOBALS['post'] = get_post( $post );
		setup_postdata( $GLOBALS['post'] );

		// Register the Block Hooks callback a second time, at priority 0.
		add_filter( 'the_content', 'apply_block_hooks_to_content_from_post_object', 0 );

		add_filter(
			'hooked_block',
			static function ( array $parsed_hooked_block ): array {
				$parsed_hooked_block['innerHTML']    = '<p>LEAKED HOOKED TEXT</p>';
				$parsed_hooked_block['innerContent'] = array( '<p>LEAKED HOOKED TEXT</p>' );
				return $parsed_hooked_block;
			}
		);

		$this->assertSame( 'Visible text.', wp_trim_excerpt( '', $post ), 'A second registration of the Block Hooks callback leaked markup into the excerpt' );

		global $wp_filter;
		$this->assertArrayHasKey( 'apply_block_hooks_to_content_from_post_object', $wp_filter['the_content']->callbacks[0], 'The priority 0 registration was not restored' );
		$this->assertArrayHasKey( 'apply_block_hooks_to_content_from_post_object', $wp_filter['the_content']->callbacks[8], 'The default registration was not restored' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` restores the accepted arguments of an unhooked callback.
	 *
	 * @ticket 66110
	 */
	public function test_wp_trim_excerpt_restores_accepted_args() {
		global $wp_filter;

		$post = self::factory()->post->create();

		remove_filter( 'the_content', 'wp_filter_content_tags', 12 );
		add_filter( 'the_content', 'wp_filter_content_tags', 12, 2 );

		wp_trim_excerpt( '', $post );

		$this->assertSame( 2, $wp_filter['the_content']->callbacks[12]['wp_filter_content_tags']['accepted_args'] );
	}
}
