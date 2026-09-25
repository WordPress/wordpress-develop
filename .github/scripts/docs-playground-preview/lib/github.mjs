import { REQUEST_TIMEOUT_MS, TRANSFER_TIMEOUT_MS } from './http.mjs';
import { COMMENT_MARKER, RELEASE_TAG } from './publication.mjs';

const API = 'https://api.github.com';
const UPLOADS = 'https://uploads.github.com';
const RETRY_ATTEMPTS = 3;
const RETRY_BASE_MS = 1000;
const BUILD_WORKFLOW = 'docs-playground-preview-build.yml';
const BUILD_JOB = 'Build Code Reference snapshot';

/**
 * @param {Record<string, unknown>} values
 */
function query( values ) {
	const parameters = new URLSearchParams();
	for ( const [ name, value ] of Object.entries( values ) ) {
		if ( value !== undefined && value !== null ) {
			parameters.set( name, String( value ) );
		}
	}
	return parameters.toString();
}

export class GitHubApi {
	/**
	 * @param {string} repository
	 * @param {string} token
	 * @param {(...args: any[]) => Promise<any>} [fetchImplementation]
	 */
	constructor( repository, token, fetchImplementation = globalThis.fetch ) {
		this.repository = repository;
		this.token = token;
		this.fetch = fetchImplementation;
	}

	/**
	 * @param {string} url
	 * @param {Record<string, any>} [options]
	 */
	async request( url, options = {} ) {
		const { timeoutMs = REQUEST_TIMEOUT_MS, ...init } = options;
		const headers = {
			Accept: 'application/vnd.github+json',
			Authorization: `Bearer ${ this.token }`,
			'X-GitHub-Api-Version': '2022-11-28',
			...options.headers,
		};
		const target = url.startsWith( 'https://' ) ? url : `${ API }${ url }`;
		// Only reads repeat. Repeating a mutation could post a second comment
		// or a second upload for an attempt the server had already applied.
		const repeatable = ! init.method || init.method === 'GET';
		for ( let attempt = 1; ; attempt++ ) {
			const retrying = repeatable && attempt < RETRY_ATTEMPTS;
			let response;
			try {
				response = await this.fetch( target, {
					...init,
					headers,
					signal: AbortSignal.timeout( timeoutMs ),
					body:
						options.json === undefined
							? options.body
							: JSON.stringify( options.json ),
				} );
			} catch ( error ) {
				if ( ! retrying ) {
					throw error;
				}
				await this.backoff( attempt );
				continue;
			}
			if ( options.allowNotFound && response.status === 404 ) {
				return null;
			}
			if ( ! response.ok ) {
				if (
					retrying &&
					( response.status === 429 || response.status >= 500 )
				) {
					await this.backoff( attempt );
					continue;
				}
				const detail = await response.text();
				const error = Object.assign(
					new Error(
						`GitHub API returned HTTP ${
							response.status
						} for ${ url }: ${ detail.slice( 0, 300 ) }`
					),
					{ status: response.status }
				);
				throw error;
			}
			if ( response.status === 204 ) {
				return null;
			}
			return response.json();
		}
	}

	/**
	 * Spreads repeated attempts so that concurrent jobs meeting the same
	 * outage do not return in lockstep.
	 *
	 * @param {number} attempt
	 */
	async backoff( attempt ) {
		const delay =
			RETRY_BASE_MS * 2 ** ( attempt - 1 ) +
			Math.random() * RETRY_BASE_MS;
		await new Promise( ( resolve ) => setTimeout( resolve, delay ) );
	}

	/**
	 * @param {string} path
	 * @param {string | null} [field]
	 * @returns {AsyncGenerator<any[], void, void>}
	 */
	async *pageBatches( path, field = null ) {
		for ( let page = 1; ; page++ ) {
			const separator = path.includes( '?' ) ? '&' : '?';
			const response = await this.request(
				`${ path }${ separator }per_page=100&page=${ page }`
			);
			const batch = field ? response[ field ] : response;
			yield batch;
			if ( batch.length < 100 ) {
				return;
			}
		}
	}

	/**
	 * @param {string} path
	 * @param {string | null} [field]
	 */
	async pages( path, field = null ) {
		const values = [];
		for await ( const batch of this.pageBatches( path, field ) ) {
			values.push( ...batch );
		}
		return values;
	}

	/**
	 * @param {number} runId
	 */
	getRun( runId ) {
		return this.request(
			`/repos/${ this.repository }/actions/runs/${ runId }`
		);
	}

	async getTrunkHeadSha() {
		const reference = await this.request(
			`/repos/${ this.repository }/git/ref/heads/trunk`
		);
		return reference.object?.sha || null;
	}

	/**
	 * @param {number} number
	 */
	getPullRequest( number ) {
		return this.request( `/repos/${ this.repository }/pulls/${ number }` );
	}

	/**
	 * @param {Record<string, any>} run
	 */
	async findPullRequestForRun( run ) {
		const owner = run.head_repository?.owner?.login;
		if ( ! owner || ! run.head_branch ) {
			throw new Error( 'Workflow run has no fork head identity.' );
		}
		const pulls = await this.pages(
			`/repos/${ this.repository }/pulls?${ query( {
				state: 'open',
				base: 'trunk',
				head: `${ owner }:${ run.head_branch }`,
			} ) }`
		);
		const matches = pulls.filter(
			( pullRequest ) =>
				pullRequest.head.sha === run.head_sha &&
				pullRequest.head.repo?.full_name ===
					run.head_repository.full_name
		);
		if ( matches.length === 0 ) {
			return null;
		}
		if ( matches.length !== 1 ) {
			throw new Error(
				`Expected one pull request for the workflow run; found ${ matches.length }.`
			);
		}
		return matches[ 0 ];
	}

	/**
	 * @param {Record<string, any>} run
	 */
	async findLatestPreviewRun( run ) {
		const runs = await this.pages(
			`/repos/${
				this.repository
			}/actions/workflows/${ BUILD_WORKFLOW }/runs?${ query( {
				event: 'pull_request',
				head_sha: run.head_sha,
			} ) }`,
			'workflow_runs'
		);
		const matching = runs
			.filter(
				( candidate ) =>
					candidate.head_sha === run.head_sha &&
					candidate.head_branch === run.head_branch &&
					candidate.head_repository?.full_name ===
						run.head_repository?.full_name
			)
			.sort( ( left, right ) => right.id - left.id );
		for ( const candidate of matching ) {
			const current = await this.getRun( candidate.id );
			if ( ! ( await this.isSkippedPreviewBuild( current ) ) ) {
				return current;
			}
		}
		return null;
	}

	/**
	 * @param {Record<string, any>} run
	 */
	async latestPreviewRun( run ) {
		const latest = await this.findLatestPreviewRun( run );
		if ( latest ) {
			return latest;
		}
		throw new Error( 'No current docs preview build matches this head.' );
	}

	async latestTrunkPreviewRun() {
		// Every authorization repeats this lookup, and the API answers newest
		// first, so the first page holding a match ends the scan instead of
		// walking the entire trunk build history.
		const batches = this.pageBatches(
			`/repos/${
				this.repository
			}/actions/workflows/${ BUILD_WORKFLOW }/runs?${ query( {
				event: 'push',
				branch: 'trunk',
			} ) }`,
			'workflow_runs'
		);
		for await ( const batch of batches ) {
			const latest = batch
				.filter(
					( run ) =>
						run.event === 'push' &&
						run.head_branch === 'trunk' &&
						run.head_repository?.full_name === this.repository
				)
				.sort( ( left, right ) => right.id - left.id )[ 0 ];
			if ( latest ) {
				return this.getRun( latest.id );
			}
		}
		throw new Error( 'No current trunk docs preview build exists.' );
	}

	/**
	 * @param {Record<string, any>} run
	 */
	async isSkippedPreviewBuild( run ) {
		const jobs = await this.pages(
			`/repos/${ this.repository }/actions/runs/${ run.id }/attempts/${ run.run_attempt }/jobs`,
			'jobs'
		);
		const matches = jobs.filter( ( job ) => job.name === BUILD_JOB );
		if ( matches.length !== 1 ) {
			throw new Error(
				`Expected one Code Reference build job; found ${ matches.length }.`
			);
		}
		return matches[ 0 ].conclusion === 'skipped';
	}

	getRelease() {
		return this.request(
			`/repos/${ this.repository }/releases/tags/${ RELEASE_TAG }`,
			{ allowNotFound: true }
		);
	}

	createRelease() {
		return this.request( `/repos/${ this.repository }/releases`, {
			method: 'POST',
			json: {
				tag_name: RELEASE_TAG,
				name: 'Code Reference Playground previews',
				body: 'Automated prerelease for Code Reference Playground snapshots.',
				prerelease: true,
			},
		} );
	}

	/**
	 * @param {number} releaseId
	 */
	listReleaseAssets( releaseId ) {
		return this.pages(
			`/repos/${ this.repository }/releases/${ releaseId }/assets`
		);
	}

	/**
	 * @param {number} releaseId
	 * @param {string} name
	 * @param {Uint8Array} bytes
	 * @param {string} contentType
	 */
	async uploadReleaseAsset( releaseId, name, bytes, contentType ) {
		const upload = () =>
			this.request(
				`${ UPLOADS }/repos/${
					this.repository
				}/releases/${ releaseId }/assets?${ query( { name } ) }`,
				{
					method: 'POST',
					headers: {
						Accept: 'application/vnd.github+json',
						'Content-Type': contentType,
					},
					body: bytes,
					timeoutMs: TRANSFER_TIMEOUT_MS,
				}
			);
		try {
			return await upload();
		} catch ( error ) {
			if (
				! ( error instanceof Error ) ||
				! ( 'status' in error ) ||
				error.status !== 422
			) {
				throw error;
			}
			const assets = await this.listReleaseAssets( releaseId );
			const stale = assets.find( ( asset ) => asset.name === name );
			if ( ! stale ) {
				throw error;
			}
			await this.deleteReleaseAsset( stale.id );
			return upload();
		}
	}

	/**
	 * @param {string} reference
	 */
	getGitReference( reference ) {
		return this.request(
			`/repos/${ this.repository }/git/ref/${ reference }`,
			{ allowNotFound: true }
		);
	}

	/**
	 * @param {string} content
	 */
	createGitBlob( content ) {
		return this.request( `/repos/${ this.repository }/git/blobs`, {
			method: 'POST',
			json: { content, encoding: 'utf-8' },
		} );
	}

	/**
	 * @param {string} path
	 * @param {string} blobSha
	 */
	createGitTree( path, blobSha ) {
		return this.request( `/repos/${ this.repository }/git/trees`, {
			method: 'POST',
			json: {
				tree: [
					{
						path,
						mode: '100644',
						type: 'blob',
						sha: blobSha,
					},
				],
			},
		} );
	}

	/**
	 * @param {string} message
	 * @param {string} treeSha
	 * @param {string | null} [parentSha]
	 */
	createGitCommit( message, treeSha, parentSha = null ) {
		return this.request( `/repos/${ this.repository }/git/commits`, {
			method: 'POST',
			json: {
				message,
				tree: treeSha,
				parents: parentSha ? [ parentSha ] : [],
			},
		} );
	}

	/**
	 * @param {string} reference
	 * @param {string} sha
	 */
	createGitReference( reference, sha ) {
		return this.request( `/repos/${ this.repository }/git/refs`, {
			method: 'POST',
			json: { ref: `refs/${ reference }`, sha },
		} );
	}

	/**
	 * @param {string} reference
	 * @param {string} sha
	 */
	updateGitReference( reference, sha ) {
		return this.request(
			`/repos/${ this.repository }/git/refs/${ reference }`,
			{ method: 'PATCH', json: { sha, force: true } }
		);
	}

	/**
	 * @param {number} assetId
	 */
	deleteReleaseAsset( assetId ) {
		return this.request(
			`/repos/${ this.repository }/releases/assets/${ assetId }`,
			{ method: 'DELETE' }
		);
	}

	/**
	 * @param {string} ref
	 */
	listActionCaches( ref ) {
		return this.pages(
			`/repos/${ this.repository }/actions/caches?${ query( { ref } ) }`,
			'actions_caches'
		);
	}

	/**
	 * @param {number} cacheId
	 */
	deleteActionCache( cacheId ) {
		return this.request(
			`/repos/${ this.repository }/actions/caches/${ cacheId }`,
			{ method: 'DELETE' }
		);
	}

	/**
	 * @param {number} pullRequestNumber
	 */
	async findPreviewComment( pullRequestNumber ) {
		const comments = await this.pages(
			`/repos/${ this.repository }/issues/${ pullRequestNumber }/comments`
		);
		return (
			comments.find(
				( comment ) =>
					comment.user?.type === 'Bot' &&
					comment.body.includes( COMMENT_MARKER )
			) || null
		);
	}

	/**
	 * @param {number} pullRequestNumber
	 * @param {string} body
	 */
	createComment( pullRequestNumber, body ) {
		return this.request(
			`/repos/${ this.repository }/issues/${ pullRequestNumber }/comments`,
			{ method: 'POST', json: { body } }
		);
	}

	/**
	 * @param {number} commentId
	 * @param {string} body
	 */
	updateComment( commentId, body ) {
		return this.request(
			`/repos/${ this.repository }/issues/comments/${ commentId }`,
			{ method: 'PATCH', json: { body } }
		);
	}

	/**
	 * @param {number} pullRequestNumber
	 */
	removeLabel( pullRequestNumber ) {
		return this.request(
			`/repos/${ this.repository }/issues/${ pullRequestNumber }/labels/docs-preview`,
			{ method: 'DELETE', allowNotFound: true }
		);
	}
}
