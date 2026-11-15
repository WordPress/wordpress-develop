<?php

/**
 * Tests for the `get_trackback_url()` and `trackback_url()` functions.
 *
 * @group comment
 * @group link
 */
class Tests_Comment_GetTrackbackUrl extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_name' => 'hello-trackback',
				'post_date' => '2024-03-15 10:00:00',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		$GLOBALS['post'] = get_post( self::$post_id );
	}

	/**
	 * Tests the trackback URL with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_trackback_url
	 */
	public function test_plain_permalinks() {
		$this->set_permalink_structure( '' );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/wp-trackback.php?p=' . self::$post_id, get_trackback_url() );
	}

	/**
	 * Tests the trackback URL with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_trackback_url
	 *
	 * @dataProvider data_pretty_permalinks
	 *
	 * @param string $structure Permalink structure.
	 * @param string $expected  Expected path relative to home_url().
	 */
	public function test_pretty_permalinks( $structure, $expected ) {
		$this->set_permalink_structure( $structure );

		$this->assertSame( home_url( $expected ), get_trackback_url() );
	}

	/**
	 * Data provider for test_pretty_permalinks().
	 *
	 * @return array[]
	 */
	public static function data_pretty_permalinks() {
		return array(
			'with trailing slash'    => array( '/%postname%/', '/hello-trackback/trackback/' ),
			'without trailing slash' => array( '/%postname%', '/hello-trackback/trackback' ),
			'date based'             => array( '/%year%/%postname%/', '/2024/hello-trackback/trackback/' ),
		);
	}

	/**
	 * Tests that plain permalinks use the site URL and pretty permalinks use the home URL.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_trackback_url
	 */
	public function test_uses_site_url_for_plain_and_home_url_for_pretty_permalinks() {
		update_option( 'siteurl', 'http://' . WP_TESTS_DOMAIN . '/wp' );

		$this->set_permalink_structure( '' );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/wp/wp-trackback.php?p=' . self::$post_id, get_trackback_url(), 'Plain permalinks should use the site URL.' );

		$this->set_permalink_structure( '/%postname%/' );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/hello-trackback/trackback/', get_trackback_url(), 'Pretty permalinks should use the home URL.' );
	}

	/**
	 * Tests the argument passed to the 'trackback_url' filter and that its return value is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_trackback_url
	 */
	public function test_trackback_url_filter() {
		$this->set_permalink_structure( '/%postname%/' );

		$filter = new MockAction();
		add_filter( 'trackback_url', array( $filter, 'filter' ) );
		add_filter(
			'trackback_url',
			static function ( $trackback_url ) {
				return $trackback_url . '?filtered=1';
			},
			20
		);

		$trackback_url = get_trackback_url();

		$this->assertSame( array( home_url( '/hello-trackback/trackback/' ) ), $filter->get_args()[0], 'The filter should receive the trackback URL.' );
		$this->assertSame( home_url( '/hello-trackback/trackback/?filtered=1' ), $trackback_url, 'The filtered URL should be returned.' );
	}

	/**
	 * Tests that trackback_url() displays the trackback URL by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::trackback_url
	 */
	public function test_trackback_url_displays_url() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->expectOutputString( home_url( '/hello-trackback/trackback/' ) );

		$this->assertNull( trackback_url() );
	}

	/**
	 * Tests that trackback_url() returns the URL with a deprecation notice when $deprecated_echo is false.
	 *
	 * @ticket 65819
	 *
	 * @covers ::trackback_url
	 *
	 * @expectedDeprecated trackback_url
	 */
	public function test_trackback_url_returns_url_when_echo_is_false() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->expectOutputString( '' );

		$this->assertSame( home_url( '/hello-trackback/trackback/' ), trackback_url( false ) );
	}
}
