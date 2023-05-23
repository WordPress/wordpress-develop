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
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$this->assertSame( 'Post 2 Page 1', wp_trim_excerpt() );
			}
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
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$this->assertSame( 'Post 2 Page 1', wp_trim_excerpt() );
			}
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
			static function( $content ) use ( &$has_filter ) {
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

		$this->assertSame( 10, has_filter( 'the_content', 'wp_filter_content_tags' ), 'wp_filter_content_tags() was not restored in wp_trim_excerpt()' );
	}

	/**
	 * Tests that `wp_trim_excerpt()` doesn't restore `wp_filter_content_tags()` if it was previously unhooked.
	 *
	 * @ticket 56588
	 */
	public function test_wp_trim_excerpt_does_not_restore_wp_filter_content_tags_if_previously_unhooked() {
		$post = self::factory()->post->create();

		// Remove wp_filter_content_tags() from 'the_content' filter generally.
		remove_filter( 'the_content', 'wp_filter_content_tags' );

		wp_trim_excerpt( '', $post );

		// Assert that the filter callback was not restored after running 'the_content'.
		$this->assertFalse( has_filter( 'the_content', 'wp_filter_content_tags' ) );
	}
}
