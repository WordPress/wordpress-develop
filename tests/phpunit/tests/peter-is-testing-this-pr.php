<?php

/**
 * Peter is testing this PR.
 *
 * @group this-should-never-be-committed-to-trunk-but-some-of-it-might-be-helpful
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
		$this->markTestIncomplete( 'Need to make sure these are correct.' );
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
			array( 'CI', 'true' ),
			array( 'GITHUB_ACTION' ),
			array( 'GITHUB_ACTION_PATH' ),
			array( 'GITHUB_ACTION_REPOSITORY' ),
			array( 'GITHUB_ACTIONS' ),
			array( 'GITHUB_ACTOR' ),
			array( 'GITHUB_ACTOR_ID' ),
			array( 'GITHUB_API_URL' ),
			array( 'GITHUB_BASE_REF' ),
			array( 'GITHUB_ENV' ),
			array( 'GITHUB_EVENT_NAME', 'pull_request' ),
			array( 'GITHUB_EVENT_PATH' ),
			array( 'GITHUB_GRAPHQL_URL' ),
			array( 'GITHUB_HEAD_REF' ),
			array( 'GITHUB_JOB' ),
			array( 'GITHUB_OUTPUT' ),
			array( 'GITHUB_PATH' ),
			array( 'GITHUB_REF' ),
			array( 'GITHUB_REF_NAME' ),
			array( 'GITHUB_REF_PROTECTED' ),
			array( 'GITHUB_REF_TYPE' ),
			array( 'GITHUB_REPOSITORY' ),
			array( 'GITHUB_REPOSITORY_ID' ),
			array( 'GITHUB_REPOSITORY_OWNER' ),
			array( 'GITHUB_REPOSITORY_OWNER_ID' ),
			array( 'GITHUB_RETENTION_DAYS' ),
			array( 'GITHUB_RUN_ATTEMPT' ),
			array( 'GITHUB_RUN_ID' ),
			array( 'GITHUB_RUN_NUMBER' ),
			array( 'GITHUB_SERVER_URL' ),
			array( 'GITHUB_SHA' ),
			array( 'GITHUB_STEP_SUMMARY' ),
			array( 'GITHUB_TRIGGERING_ACTOR' ),
			array( 'GITHUB_WORKFLOW' ),
			array( 'GITHUB_WORKFLOW_REF' ),
			array( 'GITHUB_WORKFLOW_SHA' ),
			array( 'GITHUB_WORKSPACE' ),
			array( 'RUNNER_ARCH' ),
			array( 'RUNNER_DEBUG' ),
			array( 'RUNNER_ENVIRONMENT' ),
			array( 'RUNNER_NAME' ),
			array( 'RUNNER_OS' ),
			array( 'RUNNER_TEMP' ),
			array( 'RUNNER_TOOL_CACHE' ),
		);
	}
}
