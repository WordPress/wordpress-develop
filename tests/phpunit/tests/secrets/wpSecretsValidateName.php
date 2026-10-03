<?php
/**
 * @group secrets
 */
class Tests_Secrets_WpSecretsValidateName extends WP_UnitTestCase {

	/**
	 * @dataProvider data_valid_names
	 */
	public function test_accepts_valid_names( $name ) {
		$this->assertTrue( wp_secrets_validate_name( $name ) );
	}

	public function data_valid_names() {
		return array(
			'simple'                      => array( 'myplugin/api-key' ),
			'single character segments'   => array( 'a/b' ),
			'hyphens'                     => array( 'my-plugin/my-secret-key' ),
			'underscores'                 => array( 'my_plugin/my_secret_key' ),
			'digits'                      => array( 'plugin123/key456' ),
			'mixed hyphen and underscore' => array( 'a1-b2_c3/d4-e5_f6' ),
			'consecutive separators'      => array( 'a--b/c__d' ),
			'exactly the max length'      => array( str_repeat( 'a', 85 ) . '/' . str_repeat( 'b', 86 ) ),
		);
	}

	/**
	 * @dataProvider data_invalid_names
	 */
	public function test_rejects_invalid_names( $name ) {
		$result = wp_secrets_validate_name( $name );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	/**
	 * An unnamespaced name is accepted, but reports through _doing_it_wrong().
	 * It exists so code written against the Displace prototype -- whose keyspace
	 * was flat -- can be ported one call site at a time rather than all at once.
	 * See docs/decisions/0005-namespaces-are-not-access-control.md for the consequence.
	 *
	 * @dataProvider data_unnamespaced_names
	 */
	public function test_accepts_an_unnamespaced_name_but_reports_it( $name ) {
		$this->setExpectedIncorrectUsage( 'wp_secrets_validate_name' );

		$this->assertTrue( wp_secrets_validate_name( $name ) );
	}

	public function data_unnamespaced_names() {
		return array(
			'simple'      => array( 'api_key' ),
			'with hyphen' => array( 'api-key' ),
			'digits'      => array( 'key2' ),
			'at the max'  => array( str_repeat( 'a', WP_SECRETS_MAX_NAME_LENGTH ) ),
		);
	}

	/**
	 * The unnamespaced form relaxes the "exactly one slash" rule, not the
	 * character rules -- an unnamespaced name is still held to the same segment
	 * pattern as a namespaced one.
	 */
	public function test_an_unnamespaced_name_still_obeys_the_character_rules() {
		$result = wp_secrets_validate_name( 'Not_A_Valid_Name' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function data_invalid_names() {
		return array(
			'empty string'                 => array( '' ),
			'two slashes'                  => array( 'too/many/slashes' ),
			'uppercase, unnamespaced'      => array( 'NotAValidName' ),
			'space, unnamespaced'          => array( 'not a valid name' ),
			'empty namespace'              => array( '/key' ),
			'empty key'                    => array( 'namespace/' ),
			'uppercase namespace'          => array( 'MyPlugin/key' ),
			'uppercase key'                => array( 'plugin/MyKey' ),
			'leading hyphen in namespace'  => array( '-plugin/key' ),
			'trailing hyphen in namespace' => array( 'plugin-/key' ),
			'leading underscore in key'    => array( 'plugin/_key' ),
			'trailing underscore in key'   => array( 'plugin/key_' ),
			'space in key'                 => array( 'plugin/key with space' ),
			'dot in key'                   => array( 'plugin/key.name' ),
			'non-ascii'                    => array( 'plügin/key' ),
			'not a string: null'           => array( null ),
			'not a string: int'            => array( 12345 ),
			'not a string: array'          => array( array( 'plugin/key' ) ),
			'not a string: bool'           => array( true ),
			'one character over the max'   => array( str_repeat( 'a', 85 ) . '/' . str_repeat( 'b', 87 ) ),
		);
	}
}
