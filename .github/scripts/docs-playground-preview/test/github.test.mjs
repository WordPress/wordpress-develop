import assert from 'node:assert/strict';
import { test } from 'node:test';

import { GitHubApi } from '../lib/github.mjs';
import { COMMENT_MARKER, RELEASE_TAG } from '../lib/publication.mjs';

function response( value, status = 200 ) {
	return {
		ok: status >= 200 && status < 300,
		status,
		json: async () => value,
		text: async () => JSON.stringify( value ),
	};
}

function run( overrides = {} ) {
	return {
		id: 456,
		run_attempt: 1,
		head_sha: 'a'.repeat( 40 ),
		head_branch: 'feature',
		head_repository: {
			full_name: 'contributor/wordpress-develop',
			owner: { login: 'contributor' },
		},
		...overrides,
	};
}

function pullRequest( overrides = {} ) {
	return {
		number: 123,
		base: { ref: 'trunk' },
		head: {
			sha: 'a'.repeat( 40 ),
			ref: 'feature',
			repo: { full_name: 'contributor/wordpress-develop' },
		},
		...overrides,
	};
}

test( 'API requests use the repository token and report failures', async () => {
	const calls = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			calls.push( { url, options } );
			return response( { id: 456 } );
		}
	);
	assert.equal( ( await api.getRun( 456 ) ).id, 456 );
	assert.match( calls[ 0 ].url, /actions\/runs\/456$/ );
	assert.equal( calls[ 0 ].options.headers.Authorization, 'Bearer token' );

	const failing = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async () => response( { message: 'no' }, 403 )
	);
	await assert.rejects( failing.getRun( 456 ), /HTTP 403/ );
} );

test( 'a fork run resolves its PR without workflow pull_requests data', async () => {
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url ) => {
			assert.match( url, /state=open/ );
			assert.match( url, /head=contributor%3Afeature/ );
			return response( [ pullRequest() ] );
		}
	);
	assert.equal( ( await api.findPullRequestForRun( run() ) ).number, 123 );
	const obsolete = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async () => response( [] )
	);
	assert.equal( await obsolete.findPullRequestForRun( run() ), null );

	const ambiguous = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async () =>
			response( [ pullRequest(), pullRequest( { number: 124 } ) ] )
	);
	await assert.rejects(
		ambiguous.findPullRequestForRun( run() ),
		/Expected one pull request/
	);
} );

test( 'latest run lookup binds SHA, branch, repository, and current attempt', async () => {
	const calls = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url ) => {
			calls.push( url );
			if ( url.includes( '/actions/workflows/' ) ) {
				return response( {
					workflow_runs: [
						run( { id: 789, head_branch: 'other' } ),
						run( { id: 456 } ),
					],
				} );
			}
			if ( url.includes( '/jobs?' ) ) {
				return response( {
					jobs: [
						{
							name: 'Build Code Reference snapshot',
							conclusion: 'success',
						},
					],
				} );
			}
			return response( run( { id: 456, run_attempt: 2 } ) );
		}
	);
	const latest = await api.latestPreviewRun( run() );
	assert.equal( latest.run_attempt, 2 );
	assert.match( calls[ 0 ], /head_sha=/ );
	assert.match( calls[ 1 ], /actions\/runs\/456$/ );
	assert.match( calls[ 2 ], /runs\/456\/attempts\/2\/jobs/ );
} );

test( 'trunk lookup binds the workflow, branch, repository, and current attempt', async () => {
	const calls = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url ) => {
			calls.push( url );
			if ( url.includes( '/git/ref/heads/trunk' ) ) {
				return response( { object: { sha: 'a'.repeat( 40 ) } } );
			}
			if ( url.includes( '/actions/workflows/' ) ) {
				return response( {
					workflow_runs: [
						run( {
							id: 789,
							event: 'push',
							head_branch: 'trunk',
							head_repository: {
								full_name: 'other/wordpress-develop',
							},
						} ),
						run( {
							id: 456,
							event: 'push',
							head_branch: 'trunk',
							head_repository: {
								full_name: 'WordPress/wordpress-develop',
							},
						} ),
					],
				} );
			}
			return response(
				run( {
					id: 456,
					run_attempt: 2,
					event: 'push',
					head_branch: 'trunk',
					head_repository: {
						full_name: 'WordPress/wordpress-develop',
					},
				} )
			);
		}
	);
	assert.equal( await api.getTrunkHeadSha(), 'a'.repeat( 40 ) );
	assert.equal( ( await api.latestTrunkPreviewRun() ).run_attempt, 2 );
	assert.match( calls[ 1 ], /event=push/ );
	assert.match( calls[ 1 ], /branch=trunk/ );
	assert.match( calls[ 2 ], /actions\/runs\/456$/ );
} );

test( 'skipped trigger runs neither publish nor supersede a build', async () => {
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url ) => {
			if ( url.includes( '/actions/workflows/' ) ) {
				return response( {
					workflow_runs: [ run( { id: 789 } ), run() ],
				} );
			}
			if ( url.includes( '/jobs?' ) ) {
				return response( {
					jobs: [
						{
							name: 'Build Code Reference snapshot',
							conclusion: url.includes( '/789/' )
								? 'skipped'
								: 'success',
						},
					],
				} );
			}
			return response(
				url.endsWith( '/789' ) ? run( { id: 789 } ) : run()
			);
		}
	);
	assert.equal( ( await api.latestPreviewRun( run() ) ).id, 456 );
	assert.equal( await api.isSkippedPreviewBuild( run( { id: 789 } ) ), true );
} );

test( 'release operations keep immutable names and bytes', async () => {
	const calls = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			calls.push( { url, options } );
			if ( options.method === 'DELETE' ) {
				return response( null, 204 );
			}
			if ( url.includes( '/actions/caches?' ) ) {
				return response( { actions_caches: [] } );
			}
			return response( { id: 9, name: 'snapshot.zip' } );
		}
	);
	await api.createRelease();
	await api.uploadReleaseAsset(
		9,
		'snapshot.zip',
		Buffer.from( 'snapshot' ),
		'application/zip'
	);
	await api.updateReleaseAsset( 10, 'renamed.json' );
	await api.deleteReleaseAsset( 10 );
	await api.listActionCaches( 'refs/pull/123/merge' );
	await api.deleteActionCache( 11 );
	assert.equal( calls[ 0 ].options.json.tag_name, RELEASE_TAG );
	assert.match( calls[ 1 ].url, /^https:\/\/uploads\.github\.com/ );
	assert.equal( calls[ 1 ].options.body.toString(), 'snapshot' );
	assert.equal( calls[ 2 ].options.method, 'PATCH' );
	assert.deepEqual( calls[ 2 ].options.json, { name: 'renamed.json' } );
	assert.equal( calls[ 3 ].options.method, 'DELETE' );
	assert.match( calls[ 4 ].url, /ref=refs%2Fpull%2F123%2Fmerge/ );
	assert.equal( calls[ 5 ].options.method, 'DELETE' );
} );

test( 'only the marked bot comment is updated', async () => {
	const calls = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			calls.push( { url, options } );
			if ( url.includes( '/comments?per_page' ) ) {
				return response( [
					{ id: 1, user: { type: 'User' }, body: COMMENT_MARKER },
					{ id: 2, user: { type: 'Bot' }, body: COMMENT_MARKER },
				] );
			}
			if ( options.method === 'DELETE' ) {
				return response( null, 204 );
			}
			return response( { id: 2 } );
		}
	);
	assert.equal( ( await api.findPreviewComment( 123 ) ).id, 2 );
	await api.updateComment( 2, 'updated' );
	await api.removeLabel( 123 );
	assert.equal( calls[ 1 ].options.method, 'PATCH' );
	assert.deepEqual( calls[ 1 ].options.json, { body: 'updated' } );
	assert.match( calls[ 2 ].url, /labels\/docs-preview$/ );
} );
