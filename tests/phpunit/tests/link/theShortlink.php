<?php

/**
 * Tests for the_shortlink().
 *
 * @group link
 * @group post
 *
 * @covers ::the_shortlink
 */
class Tests_Link_TheShortlink extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Test Post for Shortlink',
			)
		);
	}

	/**
	 * Sets up global post before each test.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['post'] = get_post( self::$post_id );
	}

	/**
	 * Tears down global post after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Tests that the_shortlink() outputs default text when no text is provided.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_default_text() {
		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<a rel="shortlink" href="' . esc_url( $shortlink ) . '">This is the short link.</a>';

		$this->expectOutputString( $expected );
		the_shortlink();
	}

	/**
	 * Tests that the_shortlink() respects custom link text.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_custom_text
	 *
	 * @param string $text Custom link text.
	 */
	public function test_the_shortlink_custom_text( $text ) {
		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<a rel="shortlink" href="' . esc_url( $shortlink ) . '">' . $text . '</a>';

		$this->expectOutputString( $expected );
		the_shortlink( $text );
	}

	/**
	 * Tests that the_shortlink() outputs before and after markup around the anchor.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_with_before_and_after() {
		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<li><a rel="shortlink" href="' . esc_url( $shortlink ) . '">Short URL</a></li>';

		$this->expectOutputString( $expected );
		the_shortlink( 'Short URL', '', '<li>', '</li>' );
	}

	/**
	 * Tests that the_shortlink() outputs before markup without after markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_with_only_before() {
		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<p><a rel="shortlink" href="' . esc_url( $shortlink ) . '">Short URL</a>';

		$this->expectOutputString( $expected );
		the_shortlink( 'Short URL', '', '<p>' );
	}

	/**
	 * Tests that the_shortlink() outputs after markup without before markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_with_only_after() {
		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<a rel="shortlink" href="' . esc_url( $shortlink ) . '">Short URL</a></p>';

		$this->expectOutputString( $expected );
		the_shortlink( 'Short URL', '', '', '</p>' );
	}

	/**
	 * Tests that the_shortlink() passes expected arguments to the 'the_shortlink' filter.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_filter_receives_arguments() {
		$filter = new MockAction();
		add_filter( 'the_shortlink', array( $filter, 'filter' ), 10, 4 );

		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<a rel="shortlink" href="' . esc_url( $shortlink ) . '">Click Here</a>';

		$this->expectOutputString( $expected );
		the_shortlink( 'Click Here', 'Custom Title' );

		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( $expected, $filter_args[0][0] );
		$this->assertSame( $shortlink, $filter_args[0][1] );
		$this->assertSame( 'Click Here', $filter_args[0][2] );
		$this->assertSame( 'Custom Title', $filter_args[0][3] );
	}

	/**
	 * Tests that the 'the_shortlink' filter can modify the echoed output.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_filter_modifies_output() {
		$custom_filter = static function ( $link, $shortlink, $text, $title ) {
			return '<span class="custom-shortlink">' . $link . '</span>';
		};

		add_filter( 'the_shortlink', $custom_filter, 10, 4 );

		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<span class="custom-shortlink"><a rel="shortlink" href="' . esc_url( $shortlink ) . '">Link</a></span>';

		$this->expectOutputString( $expected );
		the_shortlink( 'Link' );

		remove_filter( 'the_shortlink', $custom_filter, 10 );
	}

	/**
	 * Tests that the_shortlink() respects custom shortlinks from 'pre_get_shortlink'.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_respects_pre_get_shortlink_filter() {
		$custom_url = 'https://wp.me/p12345';
		$filter     = static function () use ( $custom_url ) {
			return $custom_url;
		};

		add_filter( 'pre_get_shortlink', $filter );

		$expected = '<a rel="shortlink" href="' . $custom_url . '">Shortlink</a>';

		$this->expectOutputString( $expected );
		the_shortlink( 'Shortlink' );

		remove_filter( 'pre_get_shortlink', $filter );
	}

	/**
	 * Tests that the_shortlink() outputs nothing when the shortlink is empty.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_outputs_nothing_when_shortlink_is_empty() {
		$filter = static function () {
			return '';
		};

		add_filter( 'pre_get_shortlink', $filter );

		$this->expectOutputString( '' );
		the_shortlink( 'Shortlink', '', '<p>', '</p>' );

		remove_filter( 'pre_get_shortlink', $filter );
	}

	/**
	 * Tests that the_shortlink() works when pretty permalinks are enabled.
	 *
	 * @ticket 65819
	 */
	public function test_the_shortlink_with_pretty_permalinks() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = '<a rel="shortlink" href="' . esc_url( $shortlink ) . '">Get Shortlink</a>';

		$this->expectOutputString( $expected );
		the_shortlink( 'Get Shortlink' );
	}

	/**
	 * Data provider of custom text values.
	 *
	 * @return array[]
	 */
	public static function data_custom_text() {
		return array(
			'simple text'           => array( 'Short URL' ),
			'unicode text'          => array( 'সংক্ষিপ্ত লিংক' ),
			'text with html entity' => array( 'Link &amp; Share' ),
			'html text'             => array( '<span>Shortlink</span>' ),
		);
	}
}
