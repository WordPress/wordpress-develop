const path = require( 'path' );

const jestE2EConfig = {
	preset: 'jest-puppeteer',
	setupFilesAfterEnv: [
		'<rootDir>/config/bootstrap.js',
	],
	testMatch: [
		'<rootDir>/specs/**/__tests__/**/*.js',
		'<rootDir>/specs/**/?(*.)(spec|test).js',
		'<rootDir>/specs/**/test/*.js',
	],
	transform: {
		'^.+\\.[jt]sx?$': path.join( __dirname, 'babel-transform' ),
	},
	transformIgnorePatterns: [
		'node_modules',
		'scripts/config/puppeteer.config.js',
	],
};


module.exports = jestE2EConfig;
