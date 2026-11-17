<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing the Ajax handler for querying themes.
 *
 * @group ajax
 * @group themes
 * @covers ::wp_ajax_query_themes
 */
class Tests_Ajax_wpAjaxQueryThemes extends WP_Ajax_UnitTestCase {

	/**
	 * @ticket 66713
	 *
	 * @dataProvider data_theme_names
	 *
	 * @param string $name     Theme or author name from the Themes API.
	 * @param string $expected Plain-text name for the installer.
	 */
	public function test_theme_names_are_returned_as_plain_text( $name, $expected ) {
		$this->_setRole( 'administrator' );
		$user_id = get_current_user_id();

		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}

		add_filter(
			'themes_api',
			static function () use ( $name ) {
				return (object) array(
					'themes' => array(
						(object) array(
							'slug'         => 'example-theme',
							'name'         => $name,
							'author'       => array( 'display_name' => $name ),
							'version'      => '1.0',
							'description'  => 'An example theme.',
							'rating'       => 0,
							'num_ratings'  => 0,
							'preview_url'  => 'https://example.org/',
							'requires'     => '',
							'requires_php' => '',
						),
					),
				);
			}
		);

		$_POST['request'] = array( 'search' => 'example-theme' );

		try {
			$response = json_decode( $this->get_ajax_response( 'query-themes' ), true );
		} finally {
			if ( is_multisite() ) {
				revoke_super_admin( $user_id );
			}
		}

		$this->assertTrue( $response['success'] );
		$this->assertSame( $expected, $response['data']['themes'][0]['name'] );
		$this->assertSame( $expected, $response['data']['themes'][0]['author'] );
	}

	/**
	 * @return array[]
	 */
	public static function data_theme_names() {
		return array(
			'encoded ampersand'          => array( 'Olive &amp; Ember', 'Olive & Ember' ),
			'plain ampersand'            => array( 'Olive & Ember', 'Olive & Ember' ),
			'numeric and named entities' => array( 'Café &#38; &quot;Co&quot; &mdash; &eacute;', 'Café & "Co" — é' ),
			'name with HTML'             => array( '<strong>Olive &amp; Ember</strong>', 'Olive & Ember' ),
			'literal angle brackets'     => array( 'Olive &lt;3', 'Olive <3' ),
			'plain text'                 => array( 'Twenty Twenty-Five', 'Twenty Twenty-Five' ),
		);
	}
}
