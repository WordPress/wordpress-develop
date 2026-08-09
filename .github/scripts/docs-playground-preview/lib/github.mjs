import { COMMENT_MARKER, RELEASE_TAG } from './publication.mjs';

const API = 'https://api.github.com';
const UPLOADS = 'https://uploads.github.com';
const BUILD_WORKFLOW = 'docs-playground-preview-build.yml';
const BUILD_JOB = 'Build Code Reference snapshot';

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
	constructor( repository, token, fetchImplementation = globalThis.fetch ) {
		this.repository = repository;
		this.token = token;
		this.fetch = fetchImplementation;
	}

	async request( url, options = {} ) {
		const headers = {
			Accept: 'application/vnd.github+json',
			Authorization: `Bearer ${ this.token }`,
			'X-GitHub-Api-Version': '2022-11-28',
			...options.headers,
		};
		const response = await this.fetch(
			url.startsWith( 'https://' ) ? url : `${ API }${ url }`,
			{
				...options,
				headers,
				body:
					options.json === undefined
						? options.body
						: JSON.stringify( options.json ),
			}
		);
		if ( options.allowNotFound && response.status === 404 ) {
			return null;
		}
		if ( ! response.ok ) {
			const detail = await response.text();
			throw new Error(
				`GitHub API returned HTTP ${
					response.status
				} for ${ url }: ${ detail.slice( 0, 300 ) }`
			);
		}
		if ( response.status === 204 ) {
			return null;
		}
		return response.json();
	}

	async pages( path, field = null ) {
		const values = [];
		for ( let page = 1; ; page++ ) {
			const separator = path.includes( '?' ) ? '&' : '?';
			const response = await this.request(
				`${ path }${ separator }per_page=100&page=${ page }`
			);
			const batch = field ? response[ field ] : response;
			values.push( ...batch );
			if ( batch.length < 100 ) {
				return values;
			}
		}
	}

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

	getPullRequest( number ) {
		return this.request( `/repos/${ this.repository }/pulls/${ number }` );
	}

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

	async latestPreviewRun( run ) {
		const latest = await this.findLatestPreviewRun( run );
		if ( latest ) {
			return latest;
		}
		throw new Error( 'No current docs preview build matches this head.' );
	}

	async latestTrunkPreviewRun() {
		const runs = await this.pages(
			`/repos/${
				this.repository
			}/actions/workflows/${ BUILD_WORKFLOW }/runs?${ query( {
				event: 'push',
				branch: 'trunk',
			} ) }`,
			'workflow_runs'
		);
		const latest = runs
			.filter(
				( run ) =>
					run.event === 'push' &&
					run.head_branch === 'trunk' &&
					run.head_repository?.full_name === this.repository
			)
			.sort( ( left, right ) => right.id - left.id )[ 0 ];
		if ( ! latest ) {
			throw new Error( 'No current trunk docs preview build exists.' );
		}
		return this.getRun( latest.id );
	}

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

	listReleaseAssets( releaseId ) {
		return this.pages(
			`/repos/${ this.repository }/releases/${ releaseId }/assets`
		);
	}

	uploadReleaseAsset( releaseId, name, bytes, contentType ) {
		return this.request(
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
			}
		);
	}

	updateReleaseAsset( assetId, name ) {
		return this.request(
			`/repos/${ this.repository }/releases/assets/${ assetId }`,
			{ method: 'PATCH', json: { name } }
		);
	}

	deleteReleaseAsset( assetId ) {
		return this.request(
			`/repos/${ this.repository }/releases/assets/${ assetId }`,
			{ method: 'DELETE' }
		);
	}

	listActionCaches( ref ) {
		return this.pages(
			`/repos/${ this.repository }/actions/caches?${ query( { ref } ) }`,
			'actions_caches'
		);
	}

	deleteActionCache( cacheId ) {
		return this.request(
			`/repos/${ this.repository }/actions/caches/${ cacheId }`,
			{ method: 'DELETE' }
		);
	}

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

	createComment( pullRequestNumber, body ) {
		return this.request(
			`/repos/${ this.repository }/issues/${ pullRequestNumber }/comments`,
			{ method: 'POST', json: { body } }
		);
	}

	updateComment( commentId, body ) {
		return this.request(
			`/repos/${ this.repository }/issues/comments/${ commentId }`,
			{ method: 'PATCH', json: { body } }
		);
	}

	removeLabel( pullRequestNumber ) {
		return this.request(
			`/repos/${ this.repository }/issues/${ pullRequestNumber }/labels/docs-preview`,
			{ method: 'DELETE', allowNotFound: true }
		);
	}
}
