<?php

/**
 * @group formatting
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
	 * @ticket 52820
	 *
	 * @covers ::wp_trim_excerpt
	 */
	public function test_raw_excerpt_should_return_untrimmed() {
		$words_60 = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ' .
		'Aenean vehicula nibh eget ligula sodales, id maximus erat semper. ' .
		'Donec elementum lobortis est in elementum. Etiam tempor mauris felis, ' .
		'non accumsan urna dignissim et. Donec sed tortor hendrerit, fermentum lacus non, ' .
		'scelerisque ante. Integer nunc lacus, varius quis maximus sed, ornare eu nisl. ' .
		'Vivamus egestas ipsum eget urna sollicitudin, feugiat placerat.';

		$words_55 = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ' .
		'Aenean vehicula nibh eget ligula sodales, id maximus erat semper. ' .
		'Donec elementum lobortis est in elementum. Etiam tempor mauris felis, ' .
		'non accumsan urna dignissim et. Donec sed tortor hendrerit, fermentum lacus non, ' .
		'scelerisque ante. Integer nunc lacus, varius quis maximus sed, ornare eu nisl. ' .
		'Vivamus egestas ipsum';

		$post = self::factory()->post->create(
			array(
				'post_content' => $words_60,
			)
		);

		// Default behavior
		add_filter( 'excerpt_more', array( $this, 'remove_excerpt_more' ) );
		$this->assertSame(
			$words_55,
			wp_trim_excerpt( '', $post ),
			'The trimmed excerpt does not have 55 words'
		);

		$this->assertSame(
			'Overwrite',
			wp_trim_excerpt( 'Overwrite', $post ),
			'The trimmed excerpt is not the word "Overwrite"'
		);

		// This filter will make use of `$raw_excerpt` as the excerpt.
		add_filter( 'wp_trim_excerpt', array( $this, 'return_raw_excerpt' ), 10, 2 );
		remove_filter( 'the_content', 'wpautop' );

		$this->assertSame(
			$words_60,
			wp_trim_excerpt( '', $post ),
			'The trimmed excerpt does not have 60 words'
		);
	}

	/**
	 * This callback removes the excerpt more.
	 *
	 * @return string An empty string.
	 */
	public function remove_excerpt_more() {
		return '';
	}

	/**
	 * This callback returns the raw excerpt.
	 *
	 * @see {wp_trim_excerpt}
	 *
	 * @param string $trimmed The trimmed text.
	 * @param string $raw     The text prior to trimming.
	 *
	 * @return string The raw excerpt.
	 */
	public function return_raw_excerpt( $trimmed, $raw ) {
		return $raw;
	}
}
