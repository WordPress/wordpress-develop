<?php

/**
 * Peter is testing this PR.
 */
class Tests_Peter_Is_Testing_This_PR extends WP_UnitTestCase {

	/**
	 * @dataProvider data_github_environment_variable_defined
	 *
	 * @param mixed $variable
	 * @param mixed $expected_value
	 * @return void
	 */
	public function test_github_environment_variable_defined( $variable, $expected_value = null ) {
		$variable_value = getenv( $variable );
		$this->assertNotFalse( $variable_value, "Environment variable $variable is not defined." );

		if ( $expected_value !== null ) {
			$this->assertSame( $expected_value, $variable_value, "Environment variable $variable does not have the expected value." );
		}
	}

	/**
	 * Data provider for test_github_environment_variable_defined.
	 *
	 * @return array
	 */
	public function data_github_environment_variable_defined() {
		return array(
			array( 'GITHUB_ACTIONS', 'true' ),
			array( 'GITHUB_WORKFLOW' ),
			array( 'GITHUB_RUN_ID' ),
			array( 'GITHUB_RUN_NUMBER' ),
			array( 'GITHUB_JOB' ),
			array( 'GITHUB_ACTION' ),
			array( 'GITHUB_ACTOR' ),
			array( 'GITHUB_REPOSITORY' ),
			array( 'GITHUB_EVENT_NAME', 'pull_request' ),
			array( 'GITHUB_EVENT_PATH' ),
			array( 'GITHUB_WORKSPACE' ),
			array( 'GITHUB_SHA' ),
			array( 'GITHUB_REF' ),
			array( 'GITHUB_HEAD_REF' ),
			array( 'GITHUB_BASE_REF' ),
		);
	}
}
