<?php

/**
 * @group post
 */
class Tests_Post_GetTheTitleAttribute extends WP_UnitTestCase {

	/**
	 * @ticket 37461
	 */
	public function test_returns_sanitized_title_attribute() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'A simple title' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( 'A simple title', get_the_title_attribute() );
	}

	/**
	 * @ticket 37461
	 */
	public function test_applies_before_and_after() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'Title' ) );

		$GLOBALS['post'] = $post;

		$actual = get_the_title_attribute(
			array(
				'before' => 'Read: ',
				'after'  => '!',
			)
		);

		$this->assertSame( 'Read: Title!', $actual );
	}

	/**
	 * @ticket 37461
	 */
	public function test_strips_tags_and_escapes_quotes() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'A "quoted" <b>bold</b> title' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( 'A &quot;quoted&quot; bold title', get_the_title_attribute() );
	}

	/**
	 * @ticket 37461
	 */
	public function test_empty_title_returns_empty_string() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => '' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( '', get_the_title_attribute() );
	}

	/**
	 * @ticket 37461
	 */
	public function test_accepts_post_object_and_post_id() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'Object title' ) );

		$this->assertSame( 'Object title', get_the_title_attribute( array( 'post' => $post ) ) );
		$this->assertSame( 'Object title', get_the_title_attribute( array( 'post' => $post->ID ) ) );
	}

	/**
	 * @ticket 37461
	 */
	public function test_accepts_string_style_args() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'String args' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( '<em>String args</em>', get_the_title_attribute( 'before=<em>&after=</em>' ) );
	}

	/**
	 * @ticket 37461
	 */
	public function test_echo_argument_is_ignored() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'Ignored echo' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( 'Ignored echo', get_the_title_attribute( array( 'echo' => true ) ) );
	}

	/**
	 * @ticket 37461
	 */
	public function test_the_title_attribute_echoes_by_default() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'Echoed title' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( 'Echoed title', get_echo( 'the_title_attribute' ) );
	}

	/**
	 * @ticket 37461
	 */
	public function test_the_title_attribute_with_echo_false_returns() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => 'Returned title' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( 'Returned title', the_title_attribute( 'echo=0' ) );
	}

	/**
	 * @ticket 37461
	 */
	public function test_the_title_attribute_empty_title_echoes_nothing() {
		$post = self::factory()->post->create_and_get( array( 'post_title' => '' ) );

		$GLOBALS['post'] = $post;

		$this->assertSame( '', get_echo( 'the_title_attribute' ) );
	}
}
