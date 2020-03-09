<?php

require_once dirname( __DIR__ ) . '/canonical.php';

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_NoRewrite extends WP_Canonical_UnitTestCase {

	// These test cases are run against the test handler in WP_Canonical.

	public function setUp() {
		global $wp_rewrite;

		parent::setUp();

		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules();
	}

	/**
	 * @dataProvider data
	 */
	function test( $test_url, $expected, $ticket = 0, $expected_doing_it_wrong = array() ) {
		$this->assertCanonical( $test_url, $expected, $ticket, $expected_doing_it_wrong );
	}

	function data() {
		/*
		 * Test URL.
		 * [0]: Test URL.
		 * [1]: Expected results: Any of the following can be used.
		 *      array( 'url': expected redirection location, 'qv': expected query vars to be set via the rewrite AND $_GET );
		 *      array( expected query vars to be set, same as 'qv' above );
		 *      (string) expected redirect location.
		 * [3]: (optional) The ticket the test refers to. Can be skipped if unknown.
		 */
		return array(
			array( '/?p=123', '/?p=123' ),

			// This post_type arg should be stripped, because p=1 exists, and does not have post_type= in its query string.
			array( '/?post_type=fake-cpt&p=1', '/?p=1' ),

			// Strip an existing but incorrect post_type arg.
			array( '/?post_type=page&page_id=1', '/?p=1' ),

			// Trailing spaces and punctuation in query string args.
			array( // Space.
				'/?p=358 ',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded space.
				'/?p=358%20',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Exclamation mark.
				'/?p=358!',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded exclamation mark.
				'/?p=358%21',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Double quote.
				'/?p=358"',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded double quote.
				'/?p=358%22',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Single quote.
				'/?p=358\'',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded single quote.
				'/?p=358%27',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Opening bracket.
				'/?p=358(',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded opening bracket.
				'/?p=358%28',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Closing bracket.
				'/?p=358)',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded closing bracket.
				'/?p=358%29',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Comma.
				'/?p=358,',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded comma.
				'/?p=358%2C',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Period.
				'/?p=358.',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded period.
				'/?p=358%2E',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Semicolon.
				'/?p=358;',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded semicolon.
				'/?p=358%3B',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Opening curly bracket.
				'/?p=358{',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded opening curly bracket.
				'/?p=358%7B',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Closing curly bracket.
				'/?p=358}',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded closing curly bracket.
				'/?p=358%7D',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded opening curly quote.
				'/?p=358%E2%80%9C',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),
			array( // Encoded closing curly quote.
				'/?p=358%E2%80%9D',
				array(
					'url' => '/?p=358',
					'qv'  => array( 'p' => '358' ),
				),
				20383,
			),

			// Trailing spaces and punctuation in permalinks.
			array( '/page/2/ ', '/page/2/', 20383 ),   // Space.
			array( '/page/2/%20', '/page/2/', 20383 ), // Encoded space.
			array( '/page/2/!', '/page/2/', 20383 ),   // Exclamation mark.
			array( '/page/2/%21', '/page/2/', 20383 ), // Encoded exclamation mark.
			array( '/page/2/"', '/page/2/', 20383 ),   // Double quote.
			array( '/page/2/%22', '/page/2/', 20383 ), // Encoded double quote.
			array( '/page/2/\'', '/page/2/', 20383 ),  // Single quote.
			array( '/page/2/%27', '/page/2/', 20383 ), // Encoded single quote.
			array( '/page/2/(', '/page/2/', 20383 ),   // Opening bracket.
			array( '/page/2/%28', '/page/2/', 20383 ), // Encoded opening bracket.
			array( '/page/2/)', '/page/2/', 20383 ),   // Closing bracket.
			array( '/page/2/%29', '/page/2/', 20383 ), // Encoded closing bracket.
			array( '/page/2/,', '/page/2/', 20383 ),   // Comma.
			array( '/page/2/%2C', '/page/2/', 20383 ), // Encoded comma.
			array( '/page/2/.', '/page/2/', 20383 ),   // Period.
			array( '/page/2/%2E', '/page/2/', 20383 ), // Encoded period.
			array( '/page/2/;', '/page/2/', 20383 ),   // Semicolon.
			array( '/page/2/%3B', '/page/2/', 20383 ), // Encoded semicolon.
			array( '/page/2/{', '/page/2/', 20383 ),   // Opening curly bracket.
			array( '/page/2/%7B', '/page/2/', 20383 ), // Encoded opening curly bracket.
			array( '/page/2/}', '/page/2/', 20383 ),   // Closing curly bracket.
			array( '/page/2/%7D', '/page/2/', 20383 ), // Encoded closing curly bracket.
			array( '/page/2/%E2%80%9C', '/page/2/', 20383 ), // Encoded opening curly quote.
			array( '/page/2/%E2%80%9D', '/page/2/', 20383 ), // Encoded closing curly quote.

			array( '/?page_id=1', '/?p=1' ), // Redirect page_id to p (should cover page_id|p|attachment_id to one another).
			array( '/?page_id=1&post_type=revision', '/?p=1' ),

			array( '/?feed=rss2&p=1', '/?feed=rss2&p=1', 21841 ),
			array( '/?feed=rss&p=1', '/?feed=rss2&p=1', 24623 ),

			array( '/?comp=East+(North)', '/?comp=East+(North)', 49347 ),
		);
	}
}
