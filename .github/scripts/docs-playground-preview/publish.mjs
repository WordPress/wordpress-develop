#!/usr/bin/env node

import fs from 'node:fs/promises';
import fssync from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const HARNESS_ROOT = path.resolve(SCRIPT_DIR, '../../..');
const CONFIG_DIR = path.join(HARNESS_ROOT, '.github', 'docs-playground-preview');
const DEPENDENCIES_FILE = path.join(CONFIG_DIR, 'dependencies.json');
const COMMENT_MARKER = '<!-- wp-docs-playground-preview -->';
const RELEASE_TAG = 'docs-playground-preview-artifacts';
const RELEASE_NAME = 'Docs Playground Preview Artifacts';
const BUILD_WORKFLOW_NAME = 'Docs Playground Preview Build';
const BUILD_WORKFLOW_PATH = '.github/workflows/docs-playground-preview-build.yml';
const ARTIFACT_PREFIX = 'docs-playground-preview';

function usage() {
	return `Usage:
  GITHUB_TOKEN=<token> GITHUB_REPOSITORY=WordPress/wordpress-develop GITHUB_EVENT_PATH=<workflow_run-event.json> node .github/scripts/docs-playground-preview/publish.mjs

Publishes a sticky pull request comment containing a WordPress Playground link for a validated docs snapshot artifact.
`;
}

async function main() {
	if (process.argv.includes('--help') || process.argv.includes('-h')) {
		process.stdout.write(usage());
		return;
	}

	const token = requiredEnv('GITHUB_TOKEN');
	const repository = requiredEnv('GITHUB_REPOSITORY');
	const eventPath = requiredEnv('GITHUB_EVENT_PATH');
	if (repository !== 'WordPress/wordpress-develop') {
		console.log(`Skipping unsupported repository: ${repository}`);
		return;
	}

	const event = JSON.parse(await fs.readFile(eventPath, 'utf8'));
	const run = event.workflow_run;
	if (!run) {
		throw new Error('GITHUB_EVENT_PATH does not contain workflow_run.');
	}
	if (run.name !== BUILD_WORKFLOW_NAME || run.path !== BUILD_WORKFLOW_PATH || run.event !== 'pull_request') {
		console.log(`Skipping unrelated workflow run: ${run.name || '(unknown)'}`);
		return;
	}

	const [owner, repo] = repository.split('/');
	const github = new GitHubClient(token, owner, repo);
	const pr = await resolvePullRequest(github, run);
	if (!pr) {
		console.log(`No open pull request found for workflow run ${run.id}.`);
		return;
	}
	if (pr.head.sha !== run.head_sha) {
		console.log(`Skipping superseded docs preview for PR #${pr.number}: run ${run.head_sha}, current ${pr.head.sha}.`);
		return;
	}

	if (run.conclusion !== 'success') {
		await upsertComment(github, pr.number, failureComment(pr, run));
		console.log(`Updated PR #${pr.number} docs preview comment for ${run.conclusion || 'unknown'} build.`);
		return;
	}

	const deps = JSON.parse(await fs.readFile(DEPENDENCIES_FILE, 'utf8'));
	const artifactName = `${ARTIFACT_PREFIX}-pr${pr.number}-${run.head_sha}`;
	const artifact = await findArtifact(github, run.id, artifactName);
	if (!artifact) {
		throw new Error(`Unable to find expected artifact: ${artifactName}`);
	}

	const tempDir = await fs.mkdtemp(path.join(os.tmpdir(), 'docs-preview-publish-'));
	try {
		const artifactZip = path.join(tempDir, 'artifact.zip');
		await github.downloadArtifact(artifact.id, artifactZip);
		const extracted = path.join(tempDir, 'artifact');
		await fs.mkdir(extracted, { recursive: true });
		await runCommand('unzip', ['-q', artifactZip, '-d', extracted], { label: 'extract artifact' });

		await validateOuterArtifact(artifactZip, deps.budgets);

		const snapshotPath = path.join(extracted, 'docs-playground-snapshot.zip');
		const manifestPath = path.join(extracted, 'docs-preview-manifest.json');
		await assertFile(snapshotPath, 'snapshot artifact');
		await assertFile(manifestPath, 'manifest artifact');

		const manifest = JSON.parse(await fs.readFile(manifestPath, 'utf8'));
		const snapshotSha256 = await sha256File(snapshotPath);
		validateManifest(manifest, pr, run, snapshotSha256);

		const snapshotInfo = await inspectZip(snapshotPath);
		enforceBudgets(deps.budgets, snapshotInfo);
		await assertSnapshotShape(snapshotInfo, deps.budgets);
		const pathInZip = detectPathInZip(snapshotInfo);

		const release = await ensureRelease(github);
		const assetName = `pr-${pr.number}-${run.head_sha}-docs-playground-snapshot.zip`;
		const uploadedAsset = await replaceReleaseAsset(github, release, assetName, snapshotPath);
		await pruneOldPrAssets(github, release, pr.number, assetName, 2);

		const blueprint = createPlaygroundBlueprint(deps, uploadedAsset.browser_download_url, pathInZip);
		const playgroundUrl = `https://playground.wordpress.net/#${encodeURIComponent(JSON.stringify(blueprint))}`;
		await upsertComment(github, pr.number, successComment(pr, run, manifest, snapshotInfo, playgroundUrl, uploadedAsset.browser_download_url));
		console.log(`Updated PR #${pr.number} docs Playground preview comment.`);
	} finally {
		await fs.rm(tempDir, { recursive: true, force: true });
	}
}

function requiredEnv(name) {
	const value = process.env[name];
	if (!value) {
		throw new Error(`Missing required environment variable: ${name}`);
	}
	return value;
}

async function resolvePullRequest(github, run) {
	const fromPayload = run.pull_requests || [];
	for (const candidate of fromPayload) {
		if (!candidate.number) {
			continue;
		}
		const pr = await github.request('GET', `/repos/${github.owner}/${github.repo}/pulls/${candidate.number}`);
		if (pr.state === 'open') {
			return pr;
		}
	}

	const prs = await github.paginate(`/repos/${github.owner}/${github.repo}/pulls`, {
		state: 'open',
		head: `${run.head_repository.owner.login}:${run.head_branch}`,
		per_page: 100,
	});
	return prs.find((pr) => pr.head.sha === run.head_sha) || null;
}

async function findArtifact(github, runId, name) {
	const artifacts = await github.paginate(`/repos/${github.owner}/${github.repo}/actions/runs/${runId}/artifacts`, {
		per_page: 100,
	});
	return artifacts.find((artifact) => artifact.name === name && !artifact.expired) || null;
}

async function validateOuterArtifact(artifactZip, budgets) {
	const info = await inspectZip(artifactZip);
	if (info.entries.length > budgets.maxOuterArtifactEntries) {
		throw new Error(`Artifact has too many entries: ${info.entries.length}`);
	}
	const names = new Set(info.entries.map((entry) => entry.name));
	const expected = new Set(['docs-playground-snapshot.zip', 'docs-preview-manifest.json']);
	for (const name of names) {
		if (!expected.has(name)) {
			throw new Error(`Artifact contains unexpected entry: ${name}`);
		}
	}
	for (const name of expected) {
		if (!names.has(name)) {
			throw new Error(`Artifact is missing expected entry: ${name}`);
		}
	}
}

function validateManifest(manifest, pr, run, snapshotSha256) {
	if (manifest.schemaVersion !== 1) {
		throw new Error(`Unsupported manifest schemaVersion: ${manifest.schemaVersion}`);
	}
	if (String(manifest.prNumber) !== String(pr.number)) {
		throw new Error(`Manifest PR mismatch: ${manifest.prNumber} !== ${pr.number}`);
	}
	if (manifest.headSha !== run.head_sha) {
		throw new Error(`Manifest SHA mismatch: ${manifest.headSha} !== ${run.head_sha}`);
	}
	if (manifest.snapshotSha256 !== snapshotSha256) {
		throw new Error('Manifest snapshotSha256 does not match the artifact snapshot.');
	}
	if (!manifest.checks?.routes?.['/reference/']) {
		throw new Error('Manifest does not include /reference/ smoke-check results.');
	}
}

function createPlaygroundBlueprint(deps, snapshotUrl, pathInZip) {
	return {
		$schema: deps.playground.blueprintSchema,
		meta: {
			title: 'WordPress Core API Docs Preview',
			author: 'WordPress Core',
			description: 'Official DevHub Code Reference preview generated from a WordPress Core pull request.',
		},
		preferredVersions: {
			php: deps.playground.phpVersion,
			wp: deps.playground.wordpressVersion,
		},
		landingPage: '/reference/classes/wp_html_tag_processor/',
		login: true,
		steps: [
			{
				step: 'unzip',
				zipFile: {
					resource: 'url',
					url: snapshotUrl,
				},
				extractToPath: '/wordpress',
			},
			{
				step: 'runPHP',
				code: restoreSiteUrlPhp(),
			},
		],
	};
}

function restoreSiteUrlPhp() {
	return `<?php
require_once '/wordpress/wp-load.php';

$scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$path   = dirname( $_SERVER['SCRIPT_NAME'] ?? '' );

if ( '.' === $path || '/' === $path ) {
\t$path = '';
}

$url = rtrim( $scheme . '://' . $host . $path, '/' );
update_option( 'siteurl', $url );
update_option( 'home', $url );
flush_rewrite_rules( false );
`;
}

function successComment(pr, run, manifest, snapshotInfo, playgroundUrl, snapshotUrl) {
	const createdAt = manifest.createdAt ? new Date(manifest.createdAt).toISOString() : 'unknown';
	const routeList = Object.keys(manifest.checks?.routes || {})
		.map((route) => `- \`${route}\``)
		.join('\n');

	return `${COMMENT_MARKER}
## WordPress Core API Docs Preview

[Open the generated docs preview in WordPress Playground](${playgroundUrl})

This preview was generated from PR #${pr.number} at \`${run.head_sha.slice(0, 12)}\` using the official DevHub Code Reference theme and importer.

- Generated: ${createdAt}
- Snapshot: ${formatBytes(snapshotInfo.compressedBytes)} compressed, ${formatBytes(snapshotInfo.uncompressedBytes)} uncompressed
- WordPress source parsed: \`${escapeMarkdown(manifest.wordpress?.version || 'unknown')}\`
- Snapshot asset: [download ZIP](${snapshotUrl})

Validated routes:
${routeList || '- No route data recorded.'}

This comment is replaced when a newer commit finishes building.`;
}

function failureComment(pr, run) {
	const conclusion = run.conclusion || 'unknown';
	return `${COMMENT_MARKER}
## WordPress Core API Docs Preview

The docs preview for PR #${pr.number} at \`${run.head_sha.slice(0, 12)}\` did not produce a usable Playground snapshot.

- Workflow conclusion: \`${escapeMarkdown(conclusion)}\`
- Workflow run: ${run.html_url}

This comment is replaced when a newer commit finishes building.`;
}

async function upsertComment(github, issueNumber, body) {
	const comments = await github.paginate(`/repos/${github.owner}/${github.repo}/issues/${issueNumber}/comments`, {
		per_page: 100,
	});
	const existing = comments.find((comment) => comment.user?.type === 'Bot' && comment.body?.includes(COMMENT_MARKER));
	if (existing) {
		await github.request('PATCH', `/repos/${github.owner}/${github.repo}/issues/comments/${existing.id}`, { body });
		return;
	}
	await github.request('POST', `/repos/${github.owner}/${github.repo}/issues/${issueNumber}/comments`, { body });
}

async function ensureRelease(github) {
	try {
		return await github.request('GET', `/repos/${github.owner}/${github.repo}/releases/tags/${RELEASE_TAG}`);
	} catch (error) {
		if (error.status !== 404) {
			throw error;
		}
		return github.request('POST', `/repos/${github.owner}/${github.repo}/releases`, {
			tag_name: RELEASE_TAG,
			name: RELEASE_NAME,
			body: 'Ephemeral artifacts used by WordPress Core API docs Playground previews.',
			draft: false,
			prerelease: true,
		});
	}
}

async function replaceReleaseAsset(github, release, assetName, filePath) {
	const assets = await github.paginate(`/repos/${github.owner}/${github.repo}/releases/${release.id}/assets`, {
		per_page: 100,
	});
	for (const asset of assets) {
		if (asset.name === assetName) {
			await github.request('DELETE', `/repos/${github.owner}/${github.repo}/releases/assets/${asset.id}`);
		}
	}
	return github.uploadReleaseAsset(release.upload_url, assetName, filePath);
}

async function pruneOldPrAssets(github, release, prNumber, keepAssetName, keepCount) {
	const assets = await github.paginate(`/repos/${github.owner}/${github.repo}/releases/${release.id}/assets`, {
		per_page: 100,
	});
	const prefix = `pr-${prNumber}-`;
	const candidates = assets
		.filter((asset) => asset.name.startsWith(prefix) && asset.name.endsWith('-docs-playground-snapshot.zip') && asset.name !== keepAssetName)
		.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
	for (const asset of candidates.slice(Math.max(0, keepCount - 1))) {
		await github.request('DELETE', `/repos/${github.owner}/${github.repo}/releases/assets/${asset.id}`);
	}
}

async function inspectZip(zipPath) {
	const entries = await listZipEntries(zipPath);
	const stat = await fs.stat(zipPath);
	return {
		compressedBytes: stat.size,
		uncompressedBytes: entries.reduce((sum, entry) => sum + entry.size, 0),
		entries,
	};
}

async function listZipEntries(zipPath) {
	const result = await runCommand('zipinfo', ['-l', zipPath], { capture: true, label: `inspect ${zipPath}` });
	const entries = [];
	for (const line of result.stdout.split('\n')) {
		const match = line.match(/^\s*[-dl][^\s]*\s+\S+\s+\S+\s+(\d+)\s+.*?\s+(.+)$/);
		if (!match) {
			continue;
		}
		const rawName = match[2].trim();
		const name = normalizeZipEntryName(rawName);
		if (!name || /[\x00-\x1f]/.test(rawName)) {
			throw new Error(`Unsafe zip entry: ${name}`);
		}
		if (line.trim().startsWith('l')) {
			throw new Error(`Symlink zip entry is not allowed: ${name}`);
		}
		entries.push({ name, size: Number(match[1]) });
	}
	if (!entries.length) {
		throw new Error(`No zip entries found in ${zipPath}`);
	}
	return entries;
}

function normalizeZipEntryName(name) {
	const normalized = name.replace(/^\/+/, '');
	if (!normalized || normalized.split('/').includes('..')) {
		throw new Error(`Unsafe zip entry: ${name}`);
	}
	return normalized;
}

function enforceBudgets(budgets, zipInfo) {
	if (zipInfo.compressedBytes > budgets.compressedFailBytes) {
		throw new Error(`Snapshot exceeds compressed budget: ${zipInfo.compressedBytes} > ${budgets.compressedFailBytes}`);
	}
	if (zipInfo.uncompressedBytes > budgets.uncompressedFailBytes) {
		throw new Error(`Snapshot exceeds uncompressed budget: ${zipInfo.uncompressedBytes} > ${budgets.uncompressedFailBytes}`);
	}
}

async function assertSnapshotShape(zipInfo, budgets) {
	if (zipInfo.entries.length > budgets.maxSnapshotEntries) {
		throw new Error(`Snapshot has too many entries: ${zipInfo.entries.length}`);
	}
	const names = zipInfo.entries.map((entry) => entry.name);
	if (!names.some((name) => name === 'wp-content/database/.ht.sqlite' || name === 'wordpress/wp-content/database/.ht.sqlite')) {
		throw new Error('Snapshot zip is missing wp-content/database/.ht.sqlite.');
	}
	const forbidden = names.filter((entry) => entry.includes('/.git/') || entry.includes('/node_modules/') || entry.includes('/devhub-source/') || entry.includes('/phpdoc-parser/'));
	if (forbidden.length) {
		throw new Error(`Snapshot zip contains forbidden paths, for example: ${forbidden.slice(0, 5).join(', ')}`);
	}
}

function detectPathInZip(zipInfo) {
	const names = zipInfo.entries.map((entry) => entry.name);
	if (names.includes('wp-content/database/.ht.sqlite')) {
		return '';
	}
	if (names.includes('wordpress/wp-content/database/.ht.sqlite')) {
		return 'wordpress';
	}
	throw new Error('Snapshot zip does not contain wp-content/database/.ht.sqlite at root or wordpress/.');
}

async function sha256File(file) {
	const hash = createHash('sha256');
	await new Promise((resolve, reject) => {
		const stream = fssync.createReadStream(file);
		stream.on('data', (chunk) => hash.update(chunk));
		stream.on('error', reject);
		stream.on('end', resolve);
	});
	return hash.digest('hex');
}

async function assertFile(file, description) {
	try {
		await fs.access(file);
	} catch {
		throw new Error(`Missing ${description}: ${file}`);
	}
}

function formatBytes(bytes) {
	const units = ['B', 'KB', 'MB', 'GB'];
	let value = bytes;
	let index = 0;
	while (value >= 1024 && index < units.length - 1) {
		value /= 1024;
		index += 1;
	}
	return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function escapeMarkdown(value) {
	return String(value).replace(/[`\\]/g, '\\$&');
}

async function runCommand(command, args, options = {}) {
	if (!options.capture) {
		console.log(`$ ${[command, ...args].join(' ')}`);
	}
	return new Promise((resolve, reject) => {
		const child = spawn(command, args, {
			cwd: options.cwd || HARNESS_ROOT,
			env: { ...process.env, ...(options.env || {}) },
			stdio: options.capture ? ['ignore', 'pipe', 'pipe'] : 'inherit',
		});
		let stdout = '';
		let stderr = '';
		child.stdout?.on('data', (chunk) => {
			stdout += chunk.toString();
		});
		child.stderr?.on('data', (chunk) => {
			stderr += chunk.toString();
		});
		child.on('error', reject);
		child.on('close', (code) => {
			const result = { code, stdout, stderr };
			if (code !== 0 && !options.allowFailure) {
				const output = `${stdout}${stderr}`.trim();
				reject(new Error(`${options.label || command} failed with exit code ${code}${output ? `\n${output}` : ''}`));
			} else {
				resolve(result);
			}
		});
	});
}

class GitHubClient {
	constructor(token, owner, repo) {
		this.token = token;
		this.owner = owner;
		this.repo = repo;
		this.api = 'https://api.github.com';
	}

	async request(method, route, body) {
		const response = await fetch(`${this.api}${route}`, {
			method,
			headers: {
				Accept: 'application/vnd.github+json',
				Authorization: `Bearer ${this.token}`,
				'Content-Type': 'application/json',
				'User-Agent': 'wordpress-develop-docs-playground-preview',
				'X-GitHub-Api-Version': '2022-11-28',
			},
			...(body === undefined ? {} : { body: JSON.stringify(body) }),
		});
		const text = await response.text();
		const data = text ? JSON.parse(text) : null;
		if (!response.ok) {
			const error = new Error(data?.message || `${method} ${route} failed with ${response.status}`);
			error.status = response.status;
			error.data = data;
			throw error;
		}
		return data;
	}

	async paginate(route, params = {}) {
		const results = [];
		let page = 1;
		while (true) {
			const query = new URLSearchParams({ ...params, page: String(page) });
			const data = await this.request('GET', `${route}?${query}`);
			if (Array.isArray(data)) {
				results.push(...data);
				if (data.length < Number(params.per_page || 30)) {
					break;
				}
			} else if (Array.isArray(data.artifacts)) {
				results.push(...data.artifacts);
				if (data.artifacts.length < Number(params.per_page || 30)) {
					break;
				}
			} else {
				throw new Error(`Unsupported paginated response for ${route}`);
			}
			page += 1;
		}
		return results;
	}

	async downloadArtifact(artifactId, outFile) {
		const response = await fetch(`${this.api}/repos/${this.owner}/${this.repo}/actions/artifacts/${artifactId}/zip`, {
			headers: {
				Accept: 'application/vnd.github+json',
				Authorization: `Bearer ${this.token}`,
				'User-Agent': 'wordpress-develop-docs-playground-preview',
				'X-GitHub-Api-Version': '2022-11-28',
			},
		});
		if (!response.ok) {
			throw new Error(`Artifact download failed with ${response.status}`);
		}
		await fs.writeFile(outFile, Buffer.from(await response.arrayBuffer()));
	}

	async uploadReleaseAsset(uploadUrl, name, filePath) {
		const target = uploadUrl.replace(/\{.*$/, '');
		const query = new URLSearchParams({ name });
		const response = await fetch(`${target}?${query}`, {
			method: 'POST',
			headers: {
				Accept: 'application/vnd.github+json',
				Authorization: `Bearer ${this.token}`,
				'Content-Type': 'application/zip',
				'User-Agent': 'wordpress-develop-docs-playground-preview',
				'X-GitHub-Api-Version': '2022-11-28',
			},
			body: fssync.createReadStream(filePath),
			duplex: 'half',
		});
		const text = await response.text();
		const data = text ? JSON.parse(text) : null;
		if (!response.ok) {
			throw new Error(data?.message || `Release asset upload failed with ${response.status}`);
		}
		return data;
	}
}

main().catch((error) => {
	console.error(error.stack || error.message);
	process.exit(1);
});
