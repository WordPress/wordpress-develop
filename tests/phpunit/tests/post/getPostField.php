<?php
/**
 * Tests for get_post_field().
 *
 * @group post
 *
 * @covers ::get_post_field
 */
class Tests_Post_GetPostField extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title'   => 'Example Title',
				'post_content' => 'Example content',
				'post_excerpt' => 'Example excerpt',
			)
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_invalid_post() {
		$this->assertSame( '', get_post_field( 'post_title', 0 ) );
		$this->assertSame( '', get_post_field( 'post_title', PHP_INT_MAX ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_unknown_field() {
		$this->assertSame( '', get_post_field( 'not_a_real_field', self::$post_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_raw_post_field() {
		$this->assertSame( 'Example Title', get_post_field( 'post_title', self::$post_id, 'raw' ) );
		$this->assertSame( 'Example content', get_post_field( 'post_content', self::$post_id, 'raw' ) );
		$this->assertSame( 'Example excerpt', get_post_field( 'post_excerpt', self::$post_id, 'raw' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_forwards_context_to_sanitize_post_field() {
		add_filter(
			'post_title',
			static function ( $value ) {
				return $value . '|post_title';
			}
		);
		add_filter(
			'edit_post_title',
			static function ( $value ) {
				return $value . '|edit_post_title';
			}
		);

		$this->assertSame( 'Example Title', get_post_field( 'post_title', self::$post_id, 'raw' ) );
		$this->assertSame( 'Example Title|post_title', get_post_field( 'post_title', self::$post_id, 'display' ) );
		$this->assertSame( 'Example Title|post_title', get_post_field( 'post_title', self::$post_id, 'attribute' ) );
		$this->assertSame( 'Example Title|edit_post_title', get_post_field( 'post_title', self::$post_id, 'edit' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_attribute_context_escapes_markup_in_post_title() {
		$post           = get_post( self::$post_id );
		$original_title = $post->post_title;

		$post->post_title = 'Bold <b>Title</b> & Co';
		wp_cache_set( self::$post_id, $post, 'posts' );

		remove_all_filters( 'post_title' );

		$this->assertSame(
			'Bold &lt;b&gt;Title&lt;/b&gt; &amp; Co',
			get_post_field( 'post_title', self::$post_id, 'attribute' )
		);

		$post->post_title = $original_title;
		wp_cache_set( self::$post_id, $post, 'posts' );
	}
}
