<?php
/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_Paged extends WP_Canonical_UnitTestCase {

	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$para = 'This is a paragraph.
			This is a paragraph.
			This is a paragraph.';
		$next = '<!--nextpage-->';

		self::$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => "{$para}{$next}{$para}{$next}{$para}",
			)
		);
	}

	/**
	 * @dataProvider data_redirect_canonical_with_nextpage_pagination
	 */
	public function test_redirect_canonical_with_nextpage_pagination( $page, $expected_page, $ticket = 0 ) {
		$link = parse_url( get_permalink( self::$post_id ), PHP_URL_PATH );

		$this->assertCanonical( $link . $page, $link . $expected_page, $ticket );
	}

	/**
	 * Data provider for test_redirect_canonical_with_nextpage_pagination().
	 *
	 * @return array[] Test parameters {
	 *     @type string $page          Page number to test, with trailing slash.
	 *     @type string $expected_page Expected page number to be redirected to.
	 *     @type int    $ticket        The ticket the test refers to. Can be skipped if unknown.
	 * }
	 */
	public function data_redirect_canonical_with_nextpage_pagination() {
		return array(
			'page 0'            => array( '0/', '', 53362 ),
			'page 1'            => array( '1/', '', 53362 ),
			'existing page'     => array( '3/', '3/', 45337 ),
			'non-existing page' => array( '4/', '', 45337 ),
		);
	}
}
