<?php

/**
 * @group post
 * @group formatting
 */
class Tests_Post_GetTheContent extends WP_UnitTestCase {
	/**
	 * @ticket 42814
	 */
	public function test_argument_back_compat_more_link_text() {
		$text = 'Foo<!--more-->Bar';
		$p    = self::factory()->post->create( array( 'post_content' => $text ) );

		$q = new WP_Query( array( 'p' => $p ) );
		while ( $q->have_posts() ) {
			$q->the_post();

			$found = get_the_content( 'Ping' );
		}

		$this->assertContains( '>Ping<', $found );
	}

	/**
	 * @ticket 42814
	 */
	public function test_argument_back_compat_strip_teaser() {
		$text = 'Foo<!--more-->Bar';
		$p    = self::factory()->post->create( array( 'post_content' => $text ) );

		$this->go_to( get_permalink( $p ) );

		$q = new WP_Query( array( 'p' => $p ) );
		while ( $q->have_posts() ) {
			$q->the_post();

			$found = get_the_content( null, true );
		}

		$this->assertNotContains( 'Foo', $found );
	}

	/**
	 * @ticket 42814
	 */
	public function test_content_other_post() {
		$text_1 = 'Foo<!--nextpage-->Bar<!--nextpage-->Baz';
		$post_1 = self::factory()->post->create_and_get( array( 'post_content' => $text_1 ) );

		$text_2 = 'Bing<!--nextpage-->Bang<!--nextpage-->Boom';
		$post_2 = self::factory()->post->create_and_get( array( 'post_content' => $text_2 ) );
		setup_postdata( $post_1 );
		$found = get_the_content( null, true, $post_2 );

		$this->assertSame( 'Bing', $found );
	}

	/**
	 * @ticket 42814
	 */
	public function test_should_respect_pagination_of_inner_post() {
		$text_1 = 'Foo<!--nextpage-->Bar<!--nextpage-->Baz';
		$post_1 = self::factory()->post->create_and_get( array( 'post_content' => $text_1 ) );

		$text_2 = 'Bing<!--nextpage-->Bang<!--nextpage-->Boom';
		$post_2 = self::factory()->post->create_and_get( array( 'post_content' => $text_2 ) );
		$go_to  = add_query_arg( 'page', '2', get_permalink( $post_1->ID ) );
		$this->go_to( $go_to );

		while ( have_posts() ) {
			the_post();
			$found = get_the_content( '', false, $post_2 );
		}

		$this->assertSame( 'Bang', $found );
	}

	/**
	 * @ticket 47824
	 */
	public function test_should_fall_back_to_post_global_outside_of_the_loop() {
		$GLOBALS['post'] = self::factory()->post->create( array( 'post_content' => 'Foo' ) );

		$this->assertSame( 'Foo', get_the_content() );
	}
}
