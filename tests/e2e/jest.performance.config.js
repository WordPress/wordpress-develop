const config = require( '@wordpress/scripts/config/jest-e2e.config' );

const jestE2EConfig = {
	...config,
	testMatch: [ '**/performance/*.test.js' ],
	setupFilesAfterEnv: [
		'<rootDir>/config/bootstrap.performance.js',
	],
};

module.exports = jestE2EConfig;
