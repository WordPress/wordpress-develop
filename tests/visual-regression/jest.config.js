const config = require( '@wordpress/scripts/config/jest-e2e.config' );

const jestVisualRegressionConfig = {
	...config,
	setupFilesAfterEnv: [ '<rootDir>/config/bootstrap.js' ],
};

module.exports = jestVisualRegressionConfig;
