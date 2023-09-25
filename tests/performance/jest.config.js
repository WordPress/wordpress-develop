const config = require( '@wordpress/scripts/config/jest-e2e.config' );

const jestE2EConfig = {
	...config,
	setupFilesAfterEnv: [
		'<rootDir>/config/bootstrap.js',
	],
	globals: {
		// Number of requests to run per test.
		TEST_RUNS: 20,
	}
};

module.exports = jestE2EConfig;
