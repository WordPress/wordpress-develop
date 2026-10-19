<?php

/**
 * Tests for the_title_attribute().
 *
 * @group post
 * @group template
 *
 * @covers ::the_title_attribute
 */
class Tests_Post_TheTitleAttribute extends WP_UnitTestCase {

	/**
	 * Standard post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Post ID with HTML tags in title.
	 *
	 * @var int
	 */
	private static $html_post_id;

	/**
	 * Post ID with special characters and quotes in title.
	 *
	 * @var int
	 */
	private static $special_chars_post_id;

	/**
	 * Post ID with an empty title.
	 *
	 * @var int
	 */
	private static $empty_title_post_id;

	/**
	 * Creates shared post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Standard Post Title',
			)
		);

		self::$html_post_id = $factory->post->create(
			array(
				'post_title' => '<strong>Bold</strong> & <em>Italic</em> <code>Code</code> Title',
			)
		);

		self::$special_chars_post_id = $factory->post->create(
			array(
				'post_title' => 'Post "with" double & single \'quotes\'',
			)
		);

		self::$empty_title_post_id = $factory->post->create(
			array(
				'post_title' => '',
			)
		);
	}

	/**
	 * Tests that the_title_attribute() echoes the title by default.
	 *
	 * @ticket 65819
	 */
	public function test_echoes_title_by_default() {
		$output = get_echo(
			'the_title_attribute',
			array(
				array( 'post' => self::$post_id ),
			)
		);

		$this->assertSame( 'Standard Post Title', $output );
	}

	/**
	 * Tests that the_title_attribute() returns the title when echo is false.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_echo_false_arguments
	 *
	 * @param array|string $echo_arg Arguments disabling echo.
	 */
	public function test_returns_title_when_echo_is_false( $echo_arg ) {
		if ( is_array( $echo_arg ) ) {
			$args = array_merge( $echo_arg, array( 'post' => self::$post_id ) );
		} else {
			$args = $echo_arg . '&post=' . self::$post_id;
		}

		ob_start();
		$result = the_title_attribute( $args );
		$output = ob_get_clean();

		$this->assertSame( '', $output, 'Nothing should be echoed when echo is false.' );
		$this->assertSame( 'Standard Post Title', $result );
	}

	/**
	 * Data provider for test_returns_title_when_echo_is_false().
	 *
	 * @return array[]
	 */
	public static function data_echo_false_arguments() {
		return array(
			'array with boolean false' => array( array( 'echo' => false ) ),
			'array with integer 0'     => array( array( 'echo' => 0 ) ),
			'query string with echo=0' => array( 'echo=0' ),
		);
	}

	/**
	 * Tests that the_title_attribute() prepends before and appends after markup.
	 *
	 * @ticket 65819
	 */
	public function test_prepends_before_and_appends_after() {
		$result = the_title_attribute(
			array(
				'before' => 'Prefix: ',
				'after'  => ' (Suffix)',
				'echo'   => false,
				'post'   => self::$post_id,
			)
		);

		$this->assertSame( 'Prefix: Standard Post Title (Suffix)', $result );

		$query_string_result = the_title_attribute( 'before=Prefix: &after= (Suffix)&echo=0&post=' . self::$post_id );
		$this->assertSame( 'Prefix: Standard Post Title (Suffix)', $query_string_result );
	}

	/**
	 * Tests that the_title_attribute() strips HTML tags from title, before, and after.
	 *
	 * @ticket 65819
	 */
	public function test_strips_html_tags() {
		$result = the_title_attribute(
			array(
				'before' => '<span>[',
				'after'  => ']</span>',
				'echo'   => false,
				'post'   => self::$html_post_id,
			)
		);

		$this->assertSame( '[Bold &amp; Italic Code Title]', $result );
	}

	/**
	 * Tests that the_title_attribute() escapes special characters for attribute safety.
	 *
	 * @ticket 65819
	 */
	public function test_escapes_special_characters_for_attributes() {
		// Post title special characters texturized and escaped.
		$result = the_title_attribute(
			array(
				'echo' => false,
				'post' => self::$special_chars_post_id,
			)
		);

		$this->assertSame( 'Post &#8220;with&#8221; double &amp; single &#8216;quotes&#8217;', $result );

		// Raw quotes and ampersands in before/after are escaped via esc_attr().
		$with_before_quotes = the_title_attribute(
			array(
				'before' => 'Attribute "val" & <tag> ',
				'echo'   => false,
				'post'   => self::$post_id,
			)
		);

		$this->assertSame( 'Attribute &quot;val&quot; &amp;  Standard Post Title', $with_before_quotes );
	}

	/**
	 * Tests that the_title_attribute() returns null and outputs nothing when the title is empty.
	 *
	 * @ticket 65819
	 */
	public function test_returns_null_and_outputs_nothing_when_title_is_empty() {
		$result = the_title_attribute(
			array(
				'post' => self::$empty_title_post_id,
				'echo' => false,
			)
		);
		$this->assertNull( $result );

		$output = get_echo(
			'the_title_attribute',
			array(
				array(
					'post' => self::$empty_title_post_id,
					'echo' => true,
				),
			)
		);
		$this->assertSame( '', $output );
	}

	/**
	 * Tests that the_title_attribute() uses the global post when no post argument is provided.
	 *
	 * @ticket 65819
	 */
	public function test_uses_global_post_by_default() {
		$post            = get_post( self::$post_id );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$result = the_title_attribute( array( 'echo' => false ) );

		wp_reset_postdata();

		$this->assertSame( 'Standard Post Title', $result );
	}

	/**
	 * Tests that the_title_attribute() accepts both a post ID and a WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_post_id_and_post_object() {
		$by_id = the_title_attribute(
			array(
				'post' => self::$post_id,
				'echo' => false,
			)
		);

		$by_object = the_title_attribute(
			array(
				'post' => get_post( self::$post_id ),
				'echo' => false,
			)
		);

		$this->assertSame( 'Standard Post Title', $by_id );
		$this->assertSame( $by_id, $by_object );
	}

	/**
	 * Tests that the_title_attribute() respects the 'the_title' filter and strips any tags introduced by it.
	 *
	 * @ticket 65819
	 */
	public function test_respects_the_title_filter_and_strips_filter_tags() {
		$filter = new MockAction();
		add_filter( 'the_title', array( $filter, 'filter' ), 10, 2 );

		add_filter(
			'the_title',
			static function ( $title, $id ) {
				return '<em>Filtered:</em> ' . $title;
			},
			20,
			2
		);

		$result = the_title_attribute(
			array(
				'post' => self::$post_id,
				'echo' => false,
			)
		);

		$this->assertSame( 'Filtered: Standard Post Title', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( 'Standard Post Title', $filter->get_args()[0][0] );
		$this->assertSame( self::$post_id, $filter->get_args()[0][1] );
	}

	/**
	 * Tests that the_title_attribute() returns null for a non-existent post ID.
	 *
	 * @ticket 65819
	 */
	public function test_returns_null_for_non_existent_post() {
		$result = the_title_attribute(
			array(
				'post' => 999999,
				'echo' => false,
			)
		);

		$this->assertNull( $result );
	}
}
