import assert from 'node:assert/strict';
import { test } from 'node:test';

import { GitHubApi } from '../lib/github.mjs';
import { COMMENT_MARKER, RELEASE_TAG } from '../lib/publication.mjs';

/**
 * @param {unknown} value
 */
function response( value, status = 200 ) {
	return {
		ok: status >= 200 && status < 300,
		status,
		json: async () => value,
		text: async () => JSON.stringify( value ) ?? '',
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
	/** @type {any[]} */
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
	assert.ok( calls[ 0 ].options.signal instanceof AbortSignal );
	assert.equal( calls[ 0 ].options.signal.aborted, false );

	const failing = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async () => response( { message: 'no' }, 403 )
	);
	await assert.rejects( failing.getRun( 456 ), /HTTP 403/ );
} );

test( 'transient read failures are retried and mutations are not', async () => {
	/** @type {any[]} */
	const attempts = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			attempts.push( options.method || 'GET' );
			if ( attempts.length === 1 ) {
				throw new Error( 'socket hang up' );
			}
			if ( attempts.length === 2 ) {
				return response( { message: 'busy' }, 503 );
			}
			return response( { id: 456 } );
		}
	);
	api.backoff = async () => {};
	assert.equal( ( await api.getRun( 456 ) ).id, 456 );
	assert.deepEqual( attempts, [ 'GET', 'GET', 'GET' ] );

	const exhausted = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async () => response( { message: 'busy' }, 503 )
	);
	let reads = 0;
	exhausted.backoff = async () => {
		reads++;
	};
	await assert.rejects( exhausted.getRun( 456 ), /HTTP 503/ );
	assert.equal( reads, 2 );

	/** @type {any[]} */
	const mutations = [];
	const mutating = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			mutations.push( options.method );
			return response( { message: 'busy' }, 503 );
		}
	);
	mutating.backoff = async () => assert.fail( 'A mutation must not repeat.' );
	await assert.rejects( mutating.createComment( 123, 'body' ), /HTTP 503/ );
	assert.deepEqual( mutations, [ 'POST' ] );
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
	/** @type {any[]} */
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
	/** @type {any[]} */
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

test( 'the trunk build lookup stops at the first page holding a match', async () => {
	/** @type {any[]} */
	const requested = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url ) => {
			if ( url.includes( '/actions/workflows/' ) ) {
				requested.push( url );
				return response( {
					workflow_runs: Array.from( { length: 100 }, ( _, index ) =>
						run( {
							id: 1000 - index,
							event: 'push',
							head_branch: 'trunk',
							head_repository: {
								full_name: 'WordPress/wordpress-develop',
							},
						} )
					),
				} );
			}
			return response( run( { id: 1000 } ) );
		}
	);
	assert.equal( ( await api.latestTrunkPreviewRun() ).id, 1000 );
	assert.equal( requested.length, 1 );
	assert.match( requested[ 0 ], /page=1$/ );
} );

test( 'the trunk build lookup pages past unrelated runs', async () => {
	/** @type {any[]} */
	const requested = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url ) => {
			if ( ! url.includes( '/actions/workflows/' ) ) {
				return response( run( { id: 321 } ) );
			}
			requested.push( url );
			if ( url.endsWith( 'page=1' ) ) {
				return response( {
					workflow_runs: Array.from( { length: 100 }, () =>
						run( {
							event: 'push',
							head_branch: 'trunk',
							head_repository: {
								full_name: 'other/wordpress-develop',
							},
						} )
					),
				} );
			}
			return response( {
				workflow_runs: [
					run( {
						id: 321,
						event: 'push',
						head_branch: 'trunk',
						head_repository: {
							full_name: 'WordPress/wordpress-develop',
						},
					} ),
				],
			} );
		}
	);
	assert.equal( ( await api.latestTrunkPreviewRun() ).id, 321 );
	assert.equal( requested.length, 2 );
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
	/** @type {any[]} */
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
	await api.deleteReleaseAsset( 10 );
	await api.listActionCaches( 'refs/pull/123/merge' );
	await api.deleteActionCache( 11 );
	assert.equal( calls[ 0 ].options.json.tag_name, RELEASE_TAG );
	assert.match( calls[ 1 ].url, /^https:\/\/uploads\.github\.com/ );
	assert.equal( calls[ 1 ].options.body.toString(), 'snapshot' );
	assert.equal( calls[ 2 ].options.method, 'DELETE' );
	assert.match( calls[ 3 ].url, /ref=refs%2Fpull%2F123%2Fmerge/ );
	assert.equal( calls[ 4 ].options.method, 'DELETE' );
} );

test( 'a stale duplicate asset is replaced and the upload retried once', async () => {
	/** @type {any[]} */
	const calls = [];
	let uploads = 0;
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			calls.push( { url, options } );
			if ( url.startsWith( 'https://uploads.github.com' ) ) {
				uploads++;
				return uploads === 1
					? response( { message: 'already_exists' }, 422 )
					: response( { id: 12, name: 'snapshot.zip' } );
			}
			if ( options.method === 'DELETE' ) {
				return response( null, 204 );
			}
			return response( [ { id: 9, name: 'snapshot.zip' } ] );
		}
	);
	const asset = await api.uploadReleaseAsset(
		5,
		'snapshot.zip',
		Buffer.from( 'snapshot' ),
		'application/zip'
	);
	assert.equal( asset.id, 12 );
	assert.equal( uploads, 2 );
	assert.match( calls[ 1 ].url, /releases\/5\/assets\?per_page/ );
	assert.match( calls[ 2 ].url, /releases\/assets\/9$/ );
	assert.equal( calls[ 2 ].options.method, 'DELETE' );
	assert.equal( calls[ 3 ].options.body.toString(), 'snapshot' );
} );

test( 'a duplicate asset failure after the retry surfaces as an error', async () => {
	let uploads = 0;
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			if ( url.startsWith( 'https://uploads.github.com' ) ) {
				uploads++;
				return response( { message: 'already_exists' }, 422 );
			}
			if ( options.method === 'DELETE' ) {
				return response( null, 204 );
			}
			return response( [ { id: 9, name: 'snapshot.zip' } ] );
		}
	);
	await assert.rejects(
		api.uploadReleaseAsset(
			5,
			'snapshot.zip',
			Buffer.from( 'snapshot' ),
			'application/zip'
		),
		/HTTP 422/
	);
	assert.equal( uploads, 2 );

	let unrelatedUploads = 0;
	const unrelated = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url ) => {
			if ( url.startsWith( 'https://uploads.github.com' ) ) {
				unrelatedUploads++;
				return response( { message: 'Validation Failed' }, 422 );
			}
			return response( [] );
		}
	);
	await assert.rejects(
		unrelated.uploadReleaseAsset(
			5,
			'snapshot.zip',
			Buffer.from( 'snapshot' ),
			'application/zip'
		),
		/HTTP 422/
	);
	assert.equal( unrelatedUploads, 1 );
} );

test( 'Git object operations create and atomically move a pointer ref', async () => {
	/** @type {any[]} */
	const calls = [];
	const api = new GitHubApi(
		'WordPress/wordpress-develop',
		'token',
		async ( url, options ) => {
			calls.push( { url, options } );
			return response( {
				sha: 'a'.repeat( 40 ),
				object: { sha: 'b'.repeat( 40 ) },
			} );
		}
	);
	await api.getGitReference( 'heads/docs-preview-code-reference' );
	await api.createGitBlob( '{"blueprint":true}\n' );
	await api.createGitTree( 'code-reference-trunk.json', 'a'.repeat( 40 ) );
	await api.createGitCommit(
		'Update Code Reference preview',
		'b'.repeat( 40 ),
		'c'.repeat( 40 )
	);
	await api.createGitReference(
		'heads/docs-preview-code-reference',
		'd'.repeat( 40 )
	);
	await api.updateGitReference(
		'heads/docs-preview-code-reference',
		'e'.repeat( 40 )
	);
	assert.match( calls[ 0 ].url, /git\/ref\/heads\/docs-preview/ );
	assert.deepEqual( calls[ 1 ].options.json, {
		content: '{"blueprint":true}\n',
		encoding: 'utf-8',
	} );
	assert.equal( calls[ 2 ].options.json.tree[ 0 ].mode, '100644' );
	assert.deepEqual( calls[ 3 ].options.json.parents, [ 'c'.repeat( 40 ) ] );
	assert.equal(
		calls[ 4 ].options.json.ref,
		'refs/heads/docs-preview-code-reference'
	);
	assert.deepEqual( calls[ 5 ].options.json, {
		sha: 'e'.repeat( 40 ),
		force: true,
	} );
} );

test( 'only the marked bot comment is updated', async () => {
	/** @type {any[]} */
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
