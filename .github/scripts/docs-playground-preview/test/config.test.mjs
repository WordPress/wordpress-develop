import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

import {
	isDeploymentEnabled,
	makeBaseCacheKey,
	resolveWordPressBeta,
	validateDependencies,
} from '../lib/config.mjs';

const dependenciesFile = new URL(
	'../../../docs-playground-preview/dependencies.json',
	import.meta.url
);
const packageFile = new URL(
	'../../../docs-playground-preview/package.json',
	import.meta.url
);
const nodeVersionFile = new URL(
	'../../../docs-playground-preview/.nvmrc',
	import.meta.url
);
const buildWorkflowFile = new URL(
	'../../../workflows/docs-playground-preview-build.yml',
	import.meta.url
);
const publishWorkflowFile = new URL(
	'../../../workflows/docs-playground-preview-publish.yml',
	import.meta.url
);
const lifecycleWorkflowFile = new URL(
	'../../../workflows/docs-playground-preview-lifecycle.yml',
	import.meta.url
);

async function manifest() {
	return JSON.parse( await readFile( dependenciesFile, 'utf8' ) );
}

test( 'the checked-in dependency manifest is valid', async () => {
	const dependencies = validateDependencies( await manifest() );
	const packageManifest = JSON.parse( await readFile( packageFile, 'utf8' ) );
	assert.equal( dependencies.schemaVersion, 1 );
	assert.equal(
		( await readFile( nodeVersionFile, 'utf8' ) ).trim(),
		dependencies.toolchain.nodeVersion
	);
	assert.equal(
		packageManifest.engines.node,
		dependencies.toolchain.nodeVersion
	);
	assert.equal(
		packageManifest.dependencies[ '@wp-playground/cli' ],
		dependencies.playground.cliVersion
	);
	assert.equal(
		packageManifest.dependencies.yarn,
		dependencies.toolchain.yarnVersion
	);
	assert.equal(
		packageManifest.dependencies[ '@wordpress/scripts' ],
		dependencies.toolchain.wpScriptsVersion
	);
	assert.equal(
		packageManifest.dependencies[ '@wordpress/i18n' ],
		dependencies.toolchain.wpI18nVersion
	);
} );

test( 'dependency repositories and commits are fixed', async () => {
	const dependencies = await manifest();
	dependencies.repositories.phpdocParser.repository = 'attacker/parser';
	assert.throws(
		() => validateDependencies( dependencies ),
		/must use WordPress\/phpdoc-parser/
	);

	const invalidCommit = await manifest();
	invalidCommit.repositories.wporgDeveloper.commit = 'trunk';
	assert.throws(
		() => validateDependencies( invalidCommit ),
		/must be a full commit hash/
	);
} );

test( 'toolchain versions are exact and required', async () => {
	for ( const name of [
		'nodeVersion',
		'npmVersion',
		'composerVersion',
		'yarnVersion',
		'wpScriptsVersion',
		'wpI18nVersion',
	] ) {
		const dependencies = await manifest();
		delete dependencies.toolchain[ name ];
		assert.throws(
			() => validateDependencies( dependencies ),
			new RegExp( `toolchain\\.${ name } must be an exact version` )
		);
	}
} );

test( 'the beta resolver records a concrete official build', async () => {
	const resolved = await resolveWordPressBeta( async ( url ) => {
		assert.equal(
			url,
			'https://api.wordpress.org/core/version-check/1.7/?channel=beta'
		);
		return {
			ok: true,
			json: async () => ( {
				offers: [
					{
						response: 'development',
						version: '7.2-beta1',
						download:
							'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
					},
				],
			} ),
		};
	} );
	assert.deepEqual( resolved, {
		channel: 'beta',
		version: '7.2-beta1',
		downloadUrl:
			'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
	} );
} );

test( 'the beta resolver rejects an unbound download URL', async () => {
	await assert.rejects(
		resolveWordPressBeta( async () => ( {
			ok: true,
			json: async () => ( {
				offers: [
					{
						response: 'autoupdate',
						version: '7.2-beta1',
						download: 'https://example.com/wordpress.zip',
					},
					{
						response: 'upgrade',
						version: '7.1.2',
						download: 'https://example.com/wordpress-stable.zip',
					},
				],
			} ),
		} ) ),
		/no concrete beta or stable build/
	);
} );

test( 'the beta resolver falls back to the latest stable build with a notice', async ( t ) => {
	const write = t.mock.method( process.stderr, 'write', () => true );
	const resolved = await resolveWordPressBeta( async () => ( {
		ok: true,
		json: async () => ( {
			offers: [
				{
					response: 'upgrade',
					version: '7.1.2',
					download:
						'https://downloads.wordpress.org/release/wordpress-7.1.2.zip',
				},
				{
					response: 'autoupdate',
					version: '7.0.3',
					download:
						'https://downloads.wordpress.org/release/wordpress-7.0.3.zip',
				},
			],
		} ),
	} ) );
	assert.deepEqual( resolved, {
		channel: 'stable',
		version: '7.1.2',
		downloadUrl:
			'https://downloads.wordpress.org/release/wordpress-7.1.2.zip',
	} );
	assert.equal( write.mock.callCount(), 1 );
	assert.match(
		String( write.mock.calls[ 0 ].arguments[ 0 ] ),
		/^::notice::.*tracking the stable release 7\.1\.2 until the next beta appears\./
	);
} );

test( 'the beta resolver prefers a beta build over a stable build', async () => {
	const resolved = await resolveWordPressBeta( async () => ( {
		ok: true,
		json: async () => ( {
			offers: [
				{
					response: 'upgrade',
					version: '7.1.2',
					download:
						'https://downloads.wordpress.org/release/wordpress-7.1.2.zip',
				},
				{
					response: 'development',
					version: '7.2-RC2',
					download:
						'https://downloads.wordpress.org/release/wordpress-7.2-RC2.zip',
				},
			],
		} ),
	} ) );
	assert.deepEqual( resolved, {
		channel: 'beta',
		version: '7.2-RC2',
		downloadUrl:
			'https://downloads.wordpress.org/release/wordpress-7.2-RC2.zip',
	} );
} );

test( 'the beta resolver rejects a response with no usable build', async () => {
	for ( const body of [
		{},
		{ offers: [] },
		{ offers: [ { response: 'upgrade', version: 'trunk' } ] },
	] ) {
		await assert.rejects(
			resolveWordPressBeta( async () => ( {
				ok: true,
				json: async () => body,
			} ) ),
			/no concrete beta or stable build/
		);
	}
} );

test( 'the cache key changes with every material base input', () => {
	const inputs = {
		cacheSchemaVersion: 1,
		platform: 'linux',
		architecture: 'x64',
		runnerImage: 'ubuntu-24.04',
		phpVersion: '8.4',
		wordpressVersion: '7.2-beta1',
		dependencyDigest: 'a'.repeat( 64 ),
		harnessDigest: 'b'.repeat( 64 ),
	};
	const baseline = makeBaseCacheKey( inputs );
	for ( const [ name, value ] of Object.entries( {
		cacheSchemaVersion: 2,
		platform: 'darwin',
		architecture: 'arm64',
		runnerImage: 'macos-15',
		phpVersion: '8.5',
		wordpressVersion: '7.2-beta2',
		dependencyDigest: 'c'.repeat( 64 ),
		harnessDigest: 'd'.repeat( 64 ),
	} ) ) {
		assert.notEqual(
			makeBaseCacheKey( { ...inputs, [ name ]: value } ),
			baseline
		);
	}
} );

test( 'deployment is inert outside the primary and opted-in staging repositories', () => {
	assert.equal( isDeploymentEnabled( 'WordPress/wordpress-develop' ), true );
	assert.equal( isDeploymentEnabled( 'sirreal/wordpress-develop' ), false );
	assert.equal(
		isDeploymentEnabled( 'sirreal/wordpress-develop', 'true' ),
		true
	);
	assert.equal(
		isDeploymentEnabled( 'somebody/wordpress-develop', 'true' ),
		false
	);
	assert.equal(
		isDeploymentEnabled( 'sirreal/wordpress-develop', 'TRUE' ),
		false
	);
} );

test( 'only the untrusted staging pull-request build bypasses staging activation', async () => {
	const buildWorkflow = await readFile( buildWorkflowFile, 'utf8' );
	const publishWorkflow = await readFile( publishWorkflowFile, 'utf8' );
	const lifecycleWorkflow = await readFile( lifecycleWorkflowFile, 'utf8' );
	const trunkJobOffset = buildWorkflow.indexOf( '\n  build-trunk:' );

	assert.notEqual( trunkJobOffset, -1 );
	assert.match(
		buildWorkflow.slice( 0, trunkJobOffset ),
		/github\.repository == 'sirreal\/wordpress-develop' /
	);
	assert.doesNotMatch(
		buildWorkflow.slice( 0, trunkJobOffset ),
		/vars\.DOCS_PREVIEW_STAGING/
	);

	for ( const trustedWorkflow of [
		buildWorkflow.slice( trunkJobOffset ),
		publishWorkflow,
		lifecycleWorkflow,
	] ) {
		assert.match(
			trustedWorkflow,
			/github\.repository == 'sirreal\/wordpress-develop' && vars\.DOCS_PREVIEW_STAGING == 'true'/
		);
	}
	assert.match(
		publishWorkflow,
		/DOCS_PREVIEW_ENFORCE: \$\{\{ vars\.DOCS_PREVIEW_ENFORCE \}\}/
	);
	const trunkPublisherOffset = publishWorkflow.indexOf(
		'\n    publish-trunk:'
	);
	assert.notEqual( trunkPublisherOffset, -1 );
	const pullRequestPublisher = publishWorkflow.slice(
		0,
		trunkPublisherOffset
	);
	assert.match(
		pullRequestPublisher,
		/publish:\n[\s\S]*?permissions:\n[\s\S]*?pull-requests: write/
	);
	assert.doesNotMatch( pullRequestPublisher, /issues: write/ );
	assert.equal(
		lifecycleWorkflow.match( /pull-requests: write/g )?.length,
		2
	);
	assert.doesNotMatch( lifecycleWorkflow, /issues: write/ );
} );
