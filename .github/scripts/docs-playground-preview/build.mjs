#!/usr/bin/env node

import fs from 'node:fs/promises';
import fssync from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const HARNESS_ROOT = path.resolve(SCRIPT_DIR, '../../..');
const CONFIG_DIR = path.join(HARNESS_ROOT, '.github', 'docs-playground-preview');
const DEPENDENCIES_FILE = path.join(CONFIG_DIR, 'dependencies.json');
const PLAYGROUND_BIN = path.join(CONFIG_DIR, 'node_modules', '.bin', process.platform === 'win32' ? 'wp-playground-cli.cmd' : 'wp-playground-cli');

function usage() {
	return `Usage:
  node .github/scripts/docs-playground-preview/build.mjs --wp <wordpress-develop> --out <dir> --pr-number <n> --head-sha <sha>

Required:
  --wp          Exact PR checkout to parse. wordpress-develop and release roots are supported.
  --out         Output directory. The script writes docs-playground-snapshot.zip and docs-preview-manifest.json.
  --pr-number   Pull request number for manifest/artifact provenance.
  --head-sha     Pull request head SHA for manifest/artifact provenance.
`;
}

function parseArgs(argv) {
	const options = {};
	for (let i = 0; i < argv.length; i += 1) {
		const arg = argv[i];
		if (arg === '--help' || arg === '-h') {
			options.help = true;
		} else if (arg === '--wp' || arg === '--out' || arg === '--pr-number' || arg === '--head-sha') {
			options[arg.slice(2).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = mustValue(argv, ++i, arg);
		} else {
			throw new Error(`Unknown argument: ${arg}`);
		}
	}
	return options;
}

function mustValue(argv, index, flag) {
	if (!argv[index] || argv[index].startsWith('--')) {
		throw new Error(`${flag} requires a value`);
	}
	return argv[index];
}

async function main() {
	const options = parseArgs(process.argv.slice(2));
	if (options.help) {
		process.stdout.write(usage());
		return;
	}
	for (const key of ['wp', 'out', 'prNumber', 'headSha']) {
		if (!options[key]) {
			throw new Error(`Missing required option: --${key.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`);
		}
	}

	const deps = JSON.parse(await fs.readFile(DEPENDENCIES_FILE, 'utf8'));
	await assertFile(PLAYGROUND_BIN, 'Playground CLI. Run npm ci in .github/docs-playground-preview first.');

	const outDir = path.resolve(options.out);
	const workDir = path.join(outDir, 'work');
	const logDir = path.join(outDir, 'logs');
	await fs.rm(outDir, { recursive: true, force: true });
	await fs.mkdir(workDir, { recursive: true });
	await fs.mkdir(logDir, { recursive: true });

	const manifest = {
		schemaVersion: 1,
		createdAt: new Date().toISOString(),
		prNumber: String(options.prNumber),
		headSha: options.headSha,
		harnessSha: process.env.GITHUB_SHA || null,
		playground: deps.playground,
		upstreams: deps.upstreams,
		knownLimitations: [
			'This preview targets Code Reference pages under /reference/, not the full developer.wordpress.org handbook/search/global-site surface.',
			'External source links are not PR-SHA-aware in v1; source snippet rendering is validated instead.',
		],
		checks: {},
		sizes: {},
	};

	const sourceInfo = await stageWordPressSource(options.wp, path.join(workDir, 'source'));
	manifest.wordpress = {
		sourceRoot: sourceInfo.sourceRoot,
		version: sourceInfo.version,
	};

	const upstreamDir = path.join(workDir, 'upstreams');
	const parserDir = await prepareParser(deps, upstreamDir);
	const referenceJson = path.join(workDir, 'reference.json');
	await generateParserJson(parserDir, sourceInfo.stagedPath, referenceJson, logDir);
	await normalizeParserJson(referenceJson, '/tmp/devhub-source');
	manifest.checks.parserJson = await assertParserJson(referenceJson);

	const zipsDir = path.join(workDir, 'zips');
	await fs.mkdir(zipsDir, { recursive: true });
	await preparePreviewZips(deps, upstreamDir, parserDir, zipsDir, logDir);

	const buildBlueprint = path.join(workDir, 'build-blueprint.json');
	await writeBuildBlueprint(deps, buildBlueprint);
	const snapshotPath = path.join(outDir, 'docs-playground-snapshot.zip');
	const rawSnapshotPath = path.join(workDir, 'docs-playground-snapshot-raw.zip');

	await run(
		PLAYGROUND_BIN,
		[
			'build-snapshot',
			'--php',
			deps.playground.phpVersion,
			'--wp',
			deps.playground.wordpressVersion,
			'--blueprint',
			buildBlueprint,
			'--blueprint-may-read-adjacent-files',
			'--mount',
			`${sourceInfo.stagedPath}:/tmp/devhub-source`,
			'--outfile',
			rawSnapshotPath,
			'--verbosity',
			'normal',
		],
		{ label: 'build Playground snapshot', logFile: path.join(logDir, 'build-snapshot.log') }
	);
	await normalizeSnapshotArchive(rawSnapshotPath, snapshotPath);

	const zipInfo = await inspectZip(snapshotPath);
	manifest.sizes.snapshotCompressedBytes = zipInfo.compressedBytes;
	manifest.sizes.snapshotUncompressedBytes = zipInfo.uncompressedBytes;
	manifest.snapshotSha256 = await sha256File(snapshotPath);
	enforceBudgets(deps.budgets, zipInfo);
	await assertSnapshotShape(zipInfo);
	await validateSnapshotWithServer(deps, snapshotPath, logDir, manifest);

	await fs.writeFile(path.join(outDir, 'docs-preview-manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
	console.log(`Snapshot: ${snapshotPath}`);
	console.log(`Manifest: ${path.join(outDir, 'docs-preview-manifest.json')}`);
}

async function stageWordPressSource(inputPath, stagedPath) {
	const sourceRoot = await findWordPressRoot(path.resolve(inputPath));
	const version = await readWordPressVersion(sourceRoot);
	await fs.mkdir(stagedPath, { recursive: true });
	await run('rsync', [
		'-a',
		'--delete',
		'--exclude=.git',
		'--exclude=.github',
		'--exclude=node_modules',
		'--exclude=/wp-content/plugins',
		'--exclude=/wp-content/themes',
		`${sourceRoot}/`,
		`${stagedPath}/`,
	], { label: 'stage WordPress source' });

	for (const relative of ['wp-content/plugins', 'wp-content/themes']) {
		if (await exists(path.join(stagedPath, relative))) {
			throw new Error(`Staged source contains forbidden directory: ${relative}`);
		}
	}

	return { sourceRoot, stagedPath, version };
}

async function findWordPressRoot(candidate) {
	if (await exists(path.join(candidate, 'wp-includes', 'version.php'))) {
		return candidate;
	}
	if (await exists(path.join(candidate, 'src', 'wp-includes', 'version.php'))) {
		return path.join(candidate, 'src');
	}
	throw new Error(`No wp-includes/version.php found in ${candidate} or ${path.join(candidate, 'src')}`);
}

async function readWordPressVersion(sourceRoot) {
	const versionFile = await fs.readFile(path.join(sourceRoot, 'wp-includes', 'version.php'), 'utf8');
	const match = versionFile.match(/\$wp_version\s*=\s*'([^']+)'/);
	if (!match) {
		throw new Error(`Unable to read $wp_version from ${path.join(sourceRoot, 'wp-includes', 'version.php')}`);
	}
	return match[1];
}

async function prepareParser(deps, upstreamDir) {
	const parser = deps.upstreams.phpdocParser;
	const parserDir = path.join(upstreamDir, 'phpdoc-parser');
	await downloadArchive(parser.repo, parser.ref, parserDir);
	await assertFile(path.join(parserDir, parser.expectedFile), `phpdoc-parser ${parser.expectedFile}`);
	await run('composer', ['install', '--no-interaction', '--no-dev', '--prefer-dist'], {
		cwd: parserDir,
		label: 'install phpdoc-parser dependencies',
	});
	return parserDir;
}

async function generateParserJson(parserDir, sourcePath, outputPath, logDir) {
	const result = await run(
		'php',
		['-d', 'memory_limit=4G', path.join(parserDir, 'generate-json-manually.php'), '-d', sourcePath, '-o', outputPath],
		{ label: 'generate parser JSON', capture: true, allowFailure: true }
	);
	await fs.writeFile(path.join(logDir, 'parser.log'), `${result.stdout}${result.stderr}`);
	if (result.code !== 0) {
		throw new Error(`Parser JSON generation failed. See ${path.join(logDir, 'parser.log')}`);
	}
	await assertFile(outputPath, 'generated parser JSON');
}

async function normalizeParserJson(jsonPath, root) {
	const records = JSON.parse(await fs.readFile(jsonPath, 'utf8'));
	if (!Array.isArray(records)) {
		throw new Error(`Parser JSON must be a top-level array: ${jsonPath}`);
	}
	for (const record of records) {
		if (record && typeof record === 'object') {
			record.root = root;
		}
	}
	await fs.writeFile(jsonPath, `${JSON.stringify(records)}\n`);
}

async function assertParserJson(jsonPath) {
	const records = JSON.parse(await fs.readFile(jsonPath, 'utf8'));
	const badPaths = records
		.map((record) => String(record?.path || ''))
		.filter((recordPath) => recordPath.startsWith('wp-content/plugins/') || recordPath.startsWith('wp-content/themes/'));
	if (badPaths.length) {
		throw new Error(`Parser JSON includes plugin/theme paths, for example: ${badPaths.slice(0, 5).join(', ')}`);
	}
	return {
		records: records.length,
		rootValues: [...new Set(records.map((record) => record.root))],
	};
}

async function preparePreviewZips(deps, upstreamDir, parserDir, zipsDir, logDir) {
	const developerDir = path.join(upstreamDir, 'wporg-developer');
	const parentDir = path.join(upstreamDir, 'wporg-parent-2021');
	const muPluginsDir = path.join(upstreamDir, 'wporg-mu-plugins');
	const postsToPostsDir = path.join(upstreamDir, 'posts-to-posts');

	await downloadArchive(deps.upstreams.wporgDeveloper.repo, deps.upstreams.wporgDeveloper.ref, developerDir);
	await downloadArchive(deps.upstreams.wporgParent2021.repo, deps.upstreams.wporgParent2021.ref, parentDir);
	await downloadArchive(deps.upstreams.wporgMuPlugins.repo, deps.upstreams.wporgMuPlugins.ref, muPluginsDir);
	await downloadArchive(deps.upstreams.postsToPosts.repo, deps.upstreams.postsToPosts.ref, postsToPostsDir);

	const developerTheme = path.join(developerDir, deps.upstreams.wporgDeveloper.themePath);
	const parentTheme = path.join(parentDir, deps.upstreams.wporgParent2021.themePath);

	await run('npm', ['install', '--legacy-peer-deps', '--no-audit', '--no-fund'], { cwd: parentTheme, label: 'install wporg-parent-2021 dependencies' });
	await run('npm', ['run', 'build'], { cwd: parentTheme, label: 'build wporg-parent-2021' });
	await run('npm', ['install', '--legacy-peer-deps', '--no-audit', '--no-fund'], { cwd: developerTheme, label: 'install wporg-developer-2023 dependencies' });
	await run('npm', ['run', 'build'], { cwd: developerTheme, label: 'build wporg-developer-2023' });
	await run('npm', ['install', '--legacy-peer-deps', '--no-audit', '--no-fund'], { cwd: muPluginsDir, label: 'install wporg-mu-plugins dependencies' });
	await run('composer', ['install', '--no-interaction', '--no-dev', '--prefer-dist'], { cwd: muPluginsDir, label: 'install wporg-mu-plugins Composer dependencies' });
	await run('npm', ['run', 'build'], { cwd: muPluginsDir, label: 'build wporg-mu-plugins' });
	await run('composer', ['config', 'allow-plugins.composer/installers', 'true'], { cwd: postsToPostsDir, label: 'allow posts-to-posts Composer installer plugin' });
	await run('composer', ['install', '--no-interaction', '--no-dev', '--prefer-dist'], { cwd: postsToPostsDir, label: 'install posts-to-posts Composer dependencies' });

	await zipDirectory(parentTheme, path.join(zipsDir, 'wporg-parent-2021.zip'), 'wporg-parent-2021');
	await zipDirectory(developerTheme, path.join(zipsDir, 'wporg-developer-2023.zip'), 'wporg-developer-2023');
	await zipDirectory(path.join(muPluginsDir, deps.upstreams.wporgMuPlugins.muPluginsPath), path.join(zipsDir, 'wporg-mu-plugins.zip'), '.');
	await zipDirectory(postsToPostsDir, path.join(zipsDir, 'posts-to-posts.zip'), 'posts-to-posts');
	await zipDirectory(parserDir, path.join(zipsDir, 'phpdoc-parser.zip'), 'phpdoc-parser');
	await downloadFile(deps.upstreams.codeSyntaxBlock.url, path.join(zipsDir, 'code-syntax-block.zip'));

	for (const [label, file] of Object.entries({
		'parent theme CSS': path.join(parentTheme, 'build', 'style.css'),
		'developer theme block': path.join(developerTheme, 'build', 'code-description', 'block.json'),
		'mu-plugin table of contents block': path.join(muPluginsDir, deps.upstreams.wporgMuPlugins.muPluginsPath, 'blocks', 'table-of-contents', 'build', 'block.json'),
		'posts-to-posts scb framework': path.join(postsToPostsDir, 'vendor', 'scribu', 'scb-framework', 'load.php'),
		'phpdoc-parser autoload': path.join(parserDir, 'vendor', 'autoload.php'),
	})) {
		await assertFile(file, label);
	}

	await fs.writeFile(path.join(logDir, 'zip-list.txt'), (await fs.readdir(zipsDir)).join('\n'));
}

async function zipDirectory(source, outFile, zipRootName) {
	await fs.rm(outFile, { force: true });
	if (zipRootName === '.') {
		await run('zip', ['-rq', outFile, '.'], { cwd: source, label: `zip ${source}` });
		return;
	}
	const stage = await fs.mkdtemp(path.join(os.tmpdir(), 'docs-preview-zip-'));
	const dest = path.join(stage, zipRootName);
	await syncDirectory(source, dest, ['node_modules', '.git', '.github', 'tests']);
	await run('zip', ['-rq', outFile, zipRootName], { cwd: stage, label: `zip ${zipRootName}` });
	await fs.rm(stage, { recursive: true, force: true });
}

async function writeBuildBlueprint(deps, blueprintPath) {
	const blueprint = {
		$schema: deps.playground.blueprintSchema,
		meta: {
			title: 'WordPress Core API Docs Preview',
			author: 'WordPress Core',
			description: 'Build-time import of WordPress Core API docs through the official DevHub Code Reference stack.',
		},
		preferredVersions: {
			php: deps.playground.phpVersion,
			wp: deps.playground.wordpressVersion,
		},
		landingPage: '/reference/classes/wp_html_tag_processor/',
		extraLibraries: ['wp-cli'],
		features: {
			networking: true,
		},
		steps: [
			{ step: 'writeFile', path: '/wordpress/wp-content/mu-plugins/000-devhub-preview-local.php', data: localMuPluginPhp() },
			{ step: 'unzip', zipFile: bundled('zips/wporg-mu-plugins.zip'), extractToPath: '/wordpress/wp-content/mu-plugins' },
			{ step: 'installTheme', themeData: bundled('zips/wporg-parent-2021.zip'), ifAlreadyInstalled: 'overwrite' },
			{ step: 'installTheme', themeData: bundled('zips/wporg-developer-2023.zip'), ifAlreadyInstalled: 'overwrite' },
			{ step: 'unzip', zipFile: bundled('zips/code-syntax-block.zip'), extractToPath: '/wordpress/wp-content/plugins' },
			{ step: 'unzip', zipFile: bundled('zips/posts-to-posts.zip'), extractToPath: '/wordpress/wp-content/plugins' },
			{ step: 'unzip', zipFile: bundled('zips/phpdoc-parser.zip'), extractToPath: '/wordpress/wp-content/plugins' },
			{ step: 'writeFile', path: '/tmp/reference.json', data: bundled('reference.json') },
			{ step: 'writeFile', path: '/tmp/bootstrap-reference-navigation.php', data: referenceNavigationBootstrapPhp() },
			{ step: 'writeFile', path: '/tmp/devhub-preview-bootstrap.php', data: devhubPreviewBootstrapPhp() },
			{ step: 'writeFile', path: '/tmp/devhub-preview-deactivate-parser.php', data: devhubPreviewDeactivateParserPhp() },
			{ step: 'writeFile', path: '/tmp/devhub-preview-finalize.php', data: devhubPreviewFinalizePhp() },
			{ step: 'wp-cli', command: 'wp eval-file /tmp/devhub-preview-bootstrap.php' },
			{ step: 'wp-cli', command: 'wp parser import /tmp/reference.json --quick --user=1' },
			{ step: 'wp-cli', command: 'wp eval-file /tmp/devhub-preview-deactivate-parser.php' },
			{ step: 'wp-cli', command: 'wp eval-file /tmp/devhub-preview-finalize.php' },
			{ step: 'rmdir', path: '/wordpress/wp-content/plugins/phpdoc-parser' },
		],
	};
	await fs.writeFile(blueprintPath, `${JSON.stringify(blueprint, null, 2)}\n`);
}

function bundled(pathName) {
	return { resource: 'bundled', path: pathName };
}

function devhubPreviewBootstrapPhp() {
	return `<?php
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$mark_plugin_active = function( $plugin ) {
\t$result = validate_plugin( $plugin );
\tif ( is_wp_error( $result ) ) {
\t\tthrow new Exception( sprintf( 'Invalid plugin %s: %s', $plugin, $result->get_error_message() ) );
\t}

\t$active = get_option( 'active_plugins', array() );
\tif ( ! in_array( $plugin, $active, true ) ) {
\t\t$active[] = $plugin;
\t\t$active = array_values( array_unique( $active ) );
\t\tsort( $active );
\t\tupdate_option( 'active_plugins', $active );
\t}
};

$ensure_page = function( $slug, $title ) {
\t$page = get_page_by_path( $slug, OBJECT, 'page' );
\t$args = array(
\t\t'post_type'   => 'page',
\t\t'post_title'  => $title,
\t\t'post_status' => 'publish',
\t\t'post_name'   => $slug,
\t);

\tif ( $page ) {
\t\t$args['ID'] = $page->ID;
\t\t$result = wp_update_post( wp_slash( $args ), true );
\t} else {
\t\t$result = wp_insert_post( wp_slash( $args ), true );
\t}

\tif ( is_wp_error( $result ) ) {
\t\tthrow new Exception( sprintf( 'Unable to create %s page: %s', $title, $result->get_error_message() ) );
\t}

\treturn (int) $result;
};

switch_theme( 'wporg-developer-2023' );
$mark_plugin_active( 'code-syntax-block/index.php' );
$mark_plugin_active( 'phpdoc-parser/plugin.php' );

update_option( 'permalink_structure', '/%year%/%monthnum%/%postname%/' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ensure_page( 'home', 'Home' ) );
$ensure_page( 'reference', 'Reference' );

require '/tmp/bootstrap-reference-navigation.php';
flush_rewrite_rules( false );
`;
}

function devhubPreviewDeactivateParserPhp() {
	return `<?php
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$active = array_values( array_diff( get_option( 'active_plugins', array() ), array( 'phpdoc-parser/plugin.php' ) ) );
sort( $active );
update_option( 'active_plugins', $active );
`;
}

function devhubPreviewFinalizePhp() {
	return `<?php
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$result = validate_plugin( 'posts-to-posts/posts-to-posts.php' );
if ( is_wp_error( $result ) ) {
\tthrow new Exception( sprintf( 'Invalid posts-to-posts plugin: %s', $result->get_error_message() ) );
}
$active = get_option( 'active_plugins', array() );
if ( ! in_array( 'posts-to-posts/posts-to-posts.php', $active, true ) ) {
\t$active[] = 'posts-to-posts/posts-to-posts.php';
\t$active = array_values( array_unique( $active ) );
\tsort( $active );
\tupdate_option( 'active_plugins', $active );
}

update_option( 'wp_parser_root_import_dir', '/tmp/devhub-source' );
if ( ! class_exists( 'DevHub_Parser' ) ) {
\tthrow new Exception( 'DevHub_Parser is not loaded.' );
}
if ( ! DevHub_Parser::cache_source_code() ) {
\tthrow new Exception( 'Source-code caching failed.' );
}

flush_rewrite_rules( false );
`;
}

function localMuPluginPhp() {
	return `<?php
/**
 * Local bootstrap for the WordPress Core DevHub Playground preview.
 *
 * This file intentionally does not register Code Reference post types,
 * templates, parser filters, relationships, or render callbacks.
 * It only supplies local environment contracts and blocks production-only
 * background imports that are unrelated to the Code Reference snapshot.
 */

if ( ! defined( 'WPORG_DEVELOPER_PREVIEW' ) ) {
\tdefine( 'WPORG_DEVELOPER_PREVIEW', true );
}

if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
\tdefine( 'WP_ENVIRONMENT_TYPE', 'local' );
}

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
\tdefine( 'DISABLE_WP_CRON', true );
}

if ( ! defined( 'FEATURE_2021_GLOBAL_HEADER_FOOTER' ) ) {
\tdefine( 'FEATURE_2021_GLOBAL_HEADER_FOOTER', true );
}

add_filter( 'pre_option_blog_public', function() {
\treturn '0';
} );

add_filter( 'wp_is_application_passwords_available', '__return_false' );
add_filter( 'automatic_updater_disabled', '__return_true' );

foreach ( array( 'update_core', 'update_plugins', 'update_themes' ) as $transient ) {
\tadd_filter(
\t\t"pre_site_transient_{$transient}",
\t\tfunction() {
\t\t\treturn (object) array(
\t\t\t\t'last_checked' => time(),
\t\t\t\t'checked'      => array(),
\t\t\t\t'no_update'    => array(),
\t\t\t\t'response'     => array(),
\t\t\t\t'translations' => array(),
\t\t\t\t'updates'      => array(),
\t\t\t);
\t\t}
\t);
}

function wporg_developer_preview_is_external_import_hook( $hook ) {
\t$blocked_hooks = array(
\t\t'devhub_cli_manifest_import',
\t\t'devhub_cli_markdown_import',
\t);

\tif ( in_array( $hook, $blocked_hooks, true ) ) {
\t\treturn true;
\t}

\treturn is_string( $hook ) && (bool) preg_match(
\t\t'/^devhub_(blocks|rest-api|wpcs|scf|adv-admin)_import_(manifest|all_markdown)$/',
\t\t$hook
\t);
}

function wporg_developer_preview_is_external_import_url( $url ) {
\t$host = wp_parse_url( $url, PHP_URL_HOST );
\t$path = wp_parse_url( $url, PHP_URL_PATH );

\tif ( 'raw.githubusercontent.com' !== $host || ! is_string( $path ) ) {
\t\treturn false;
\t}

\t$blocked_paths = array(
\t\t'/wp-cli/handbook/',
\t\t'/WP-API/docs/',
\t\t'/WordPress/gutenberg/',
\t\t'/WordPress-Coding-Standards/docs/',
\t\t'/WordPress/secure-custom-fields/',
\t\t'/WordPress/Advanced-administration-handbook/',
\t);

\tforeach ( $blocked_paths as $blocked_path ) {
\t\tif ( 0 === strpos( $path, $blocked_path ) ) {
\t\t\treturn true;
\t\t}
\t}

\treturn false;
}

add_filter(
\t'pre_schedule_event',
\tfunction( $pre, $event ) {
\t\tif ( is_object( $event ) && wporg_developer_preview_is_external_import_hook( $event->hook ) ) {
\t\t\treturn false;
\t\t}
\t\treturn $pre;
\t},
\t10,
\t2
);

add_filter(
\t'pre_http_request',
\tfunction( $pre, $parsed_args, $url ) {
\t\tif ( wporg_developer_preview_is_external_import_url( $url ) ) {
\t\t\treturn new WP_Error(
\t\t\t\t'wporg_developer_preview_external_import_disabled',
\t\t\t\t'External handbook and WP-CLI documentation imports are disabled in the local Code Reference preview.'
\t\t\t);
\t\t}
\t\treturn $pre;
\t},
\t10,
\t3
);

add_action(
\t'after_setup_theme',
\tfunction() {
\t\tif ( class_exists( 'DevHub_CLI' ) ) {
\t\t\tremove_action( 'init', array( 'DevHub_CLI', 'action_init_register_cron_jobs' ) );
\t\t}
\t},
\t100
);

add_action(
\t'init',
\tfunction() {
\t\tforeach ( _get_cron_array() ?: array() as $timestamp => $hooks ) {
\t\t\tforeach ( array_keys( $hooks ) as $hook ) {
\t\t\t\tif ( wporg_developer_preview_is_external_import_hook( $hook ) ) {
\t\t\t\t\twp_clear_scheduled_hook( $hook );
\t\t\t\t}
\t\t\t}
\t\t}
\t},
\t1000
);
`;
}

function referenceNavigationBootstrapPhp() {
	return `<?php
$items = array(
\tarray( 'Dashboard widgets', 'https://developer.wordpress.org/apis/handbook/dashboard-widgets/' ),
\tarray( 'Database', 'https://developer.wordpress.org/apis/handbook/database/' ),
\tarray( 'HTTP API', 'https://developer.wordpress.org/apis/handbook/making-http-requests/' ),
\tarray( 'Filesystem', 'https://developer.wordpress.org/apis/handbook/filesystem/' ),
\tarray( 'Global Variables', 'https://developer.wordpress.org/apis/handbook/global-variables/' ),
\tarray( 'Metadata', 'https://developer.wordpress.org/apis/handbook/metadata/' ),
\tarray( 'Options', 'https://developer.wordpress.org/apis/handbook/options/' ),
\tarray( 'Plugins', 'https://developer.wordpress.org/plugins/' ),
\tarray( 'Quicktags', 'https://developer.wordpress.org/apis/handbook/quicktags/' ),
\tarray( 'REST API', 'https://developer.wordpress.org/rest-api/' ),
\tarray( 'Rewrite', 'https://developer.wordpress.org/apis/handbook/rewrite/' ),
\tarray( 'Settings', 'https://developer.wordpress.org/apis/handbook/settings/' ),
\tarray( 'Shortcode', 'https://developer.wordpress.org/apis/handbook/shortcode/' ),
\tarray( 'Theme Modification', 'https://developer.wordpress.org/themes/' ),
\tarray( 'Transients', 'https://developer.wordpress.org/apis/handbook/transients/' ),
\tarray( 'XML-RPC', 'https://developer.wordpress.org/apis/handbook/xml-rpc/' ),
);

$content = '';
foreach ( $items as $item ) {
\tlist( $label, $url ) = $item;
\t$content .= sprintf(
\t\t"<!-- wp:navigation-link %s /-->\\n",
\t\twp_json_encode(
\t\t\tarray(
\t\t\t\t'label'          => $label,
\t\t\t\t'type'           => 'custom',
\t\t\t\t'url'            => $url,
\t\t\t\t'kind'           => 'custom',
\t\t\t\t'isTopLevelLink' => true,
\t\t\t)
\t\t)
\t);
}

$post_id = 148843;
$post    = get_post( $post_id );
$postarr = array(
\t'post_title'   => 'Reference API Menu',
\t'post_name'    => 'reference-api-menu',
\t'post_type'    => 'wp_navigation',
\t'post_status'  => 'publish',
\t'post_content' => $content,
);

if ( $post && 'wp_navigation' === $post->post_type ) {
\t$postarr['ID'] = $post_id;
\t$result = wp_update_post( wp_slash( $postarr ), true );
} else {
\t$postarr['import_id'] = $post_id;
\t$result = wp_insert_post( wp_slash( $postarr ), true );
}

if ( is_wp_error( $result ) ) {
\tthrow new Exception( $result->get_error_message() );
}

echo "Reference API navigation seeded.\\n";
`;
}

async function validateSnapshotWithServer(deps, snapshotPath, logDir, manifest) {
	const restoreBlueprint = path.join(path.dirname(snapshotPath), 'restore-blueprint.json');
	const pathInZip = await detectPathInZip(snapshotPath);
	const blueprint = {
		$schema: deps.playground.blueprintSchema,
		preferredVersions: {
			php: deps.playground.phpVersion,
			wp: deps.playground.wordpressVersion,
		},
		landingPage: '/reference/classes/wp_html_tag_processor/',
		steps: [
			{
				step: 'unzip',
				zipFile: { resource: 'vfs', path: '/tmp/docs-playground-snapshot.zip' },
				extractToPath: '/wordpress',
			},
			{
				step: 'runPHP',
				code: restoreSiteUrlPhp(),
			},
		],
	};
	await fs.writeFile(restoreBlueprint, `${JSON.stringify(blueprint, null, 2)}\n`);

	const port = await getFreePort();
	const child = spawn(PLAYGROUND_BIN, [
		'server',
		'--php',
		deps.playground.phpVersion,
		'--wp',
		deps.playground.wordpressVersion,
		'--blueprint',
		restoreBlueprint,
		'--mount',
		`${snapshotPath}:/tmp/docs-playground-snapshot.zip`,
		'--port',
		String(port),
		'--verbosity',
		'normal',
	], { stdio: ['ignore', 'pipe', 'pipe'] });

	let output = '';
	child.stdout.on('data', (chunk) => { output += chunk.toString(); });
	child.stderr.on('data', (chunk) => { output += chunk.toString(); });

	try {
		await waitForHttp(`http://127.0.0.1:${port}/reference/`, 180_000);
		const routeResults = {};
		for (const route of deps.routes) {
			const body = await fetchText(`http://127.0.0.1:${port}${route}`);
			if (route.includes('wp_html_tag_processor') && !body.includes('WP_HTML_Tag_Processor')) {
				throw new Error(`Route ${route} did not include WP_HTML_Tag_Processor.`);
			}
			if (route === '/reference/' && !body.includes('HTTP API')) {
				throw new Error('/reference/ did not render the Reference API navigation.');
			}
			routeResults[route] = { bytes: body.length };
		}
		manifest.checks.routes = routeResults;
		manifest.checks.pathInZip = pathInZip || '';
	} finally {
		child.kill('SIGTERM');
		await fs.writeFile(path.join(logDir, 'restore-smoke.log'), output);
	}
}

async function normalizeSnapshotArchive(inputZip, outputZip) {
	const stage = await fs.mkdtemp(path.join(os.tmpdir(), 'docs-preview-snapshot-'));
	const extractDir = path.join(stage, 'extract');
	await fs.mkdir(extractDir, { recursive: true });
	const result = await run('unzip', ['-q', inputZip, '-d', extractDir], {
		capture: true,
		allowFailure: true,
		label: `extract ${inputZip}`,
	});
	if (result.code !== 0 && result.code !== 1) {
		throw new Error(`Unable to extract raw snapshot ${inputZip}.\n${result.stdout}${result.stderr}`);
	}

	const root = await exists(path.join(extractDir, 'wordpress', 'wp-content', 'database', '.ht.sqlite'))
		? path.join(extractDir, 'wordpress')
		: extractDir;
	await assertFile(path.join(root, 'wp-content', 'database', '.ht.sqlite'), 'snapshot SQLite database');
	await fs.rm(outputZip, { force: true });
	await run('zip', ['-rq', outputZip, '.'], { cwd: root, label: 'normalize Playground snapshot archive' });
	await fs.rm(stage, { recursive: true, force: true });
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

async function detectPathInZip(zipPath) {
	const entries = (await listZipEntries(zipPath)).map((entry) => entry.name);
	if (entries.includes('wp-content/database/.ht.sqlite')) {
		return '';
	}
	if (entries.includes('wordpress/wp-content/database/.ht.sqlite')) {
		return 'wordpress';
	}
	throw new Error('Snapshot zip does not contain wp-content/database/.ht.sqlite at root or wordpress/.');
}

async function assertSnapshotShape(zipInfo) {
	if (!zipInfo.entries.some((entry) => entry.name === 'wp-content/database/.ht.sqlite' || entry.name === 'wordpress/wp-content/database/.ht.sqlite')) {
		throw new Error('Snapshot zip is missing wp-content/database/.ht.sqlite.');
	}
	const forbidden = zipInfo.entries
		.map((entry) => entry.name)
		.filter((entry) => entry.includes('/.git/') || entry.includes('/node_modules/') || entry.includes('/devhub-source/') || entry.includes('/phpdoc-parser/'));
	if (forbidden.length) {
		throw new Error(`Snapshot zip contains forbidden paths, for example: ${forbidden.slice(0, 5).join(', ')}`);
	}
}

function enforceBudgets(budgets, zipInfo) {
	if (zipInfo.compressedBytes > budgets.compressedFailBytes) {
		throw new Error(`Snapshot exceeds compressed budget: ${zipInfo.compressedBytes} > ${budgets.compressedFailBytes}`);
	}
	if (zipInfo.uncompressedBytes > budgets.uncompressedFailBytes) {
		throw new Error(`Snapshot exceeds uncompressed budget: ${zipInfo.uncompressedBytes} > ${budgets.uncompressedFailBytes}`);
	}
	if (zipInfo.compressedBytes > budgets.compressedWarnBytes) {
		console.warn(`Warning: snapshot exceeds compressed warning budget: ${zipInfo.compressedBytes}`);
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
	const result = await run('zipinfo', ['-l', zipPath], { capture: true, label: `inspect ${zipPath}` });
	const entries = [];
	for (const line of result.stdout.split('\n')) {
		const match = line.match(/^\s*([-dl][^\s]*)\s+\S+\s+\S+\s+(\d+)\s+\S+\s+\d+\s+\S+\s+\S+\s+\S+\s+(.+)$/);
		if (!match) {
			continue;
		}
		const rawName = match[3].trim();
		const name = normalizeZipEntryName(rawName);
		if (!name || /[\x00-\x1f]/.test(rawName)) {
			throw new Error(`Unsafe zip entry: ${name}`);
		}
		if (match[1].startsWith('l')) {
			throw new Error(`Symlink zip entry is not allowed: ${name}`);
		}
		entries.push({ name, size: Number(match[2]) });
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

async function downloadArchive(repo, ref, dest) {
	await fs.rm(dest, { recursive: true, force: true });
	await fs.mkdir(dest, { recursive: true });
	const tmp = path.join(os.tmpdir(), `docs-playground-${repo.replace('/', '-')}-${ref}.tar.gz`);
	await downloadFile(`https://github.com/${repo}/archive/${ref}.tar.gz`, tmp);
	await run('tar', ['-xzf', tmp, '--strip-components=1', '-C', dest], { label: `extract ${repo}@${ref}` });
	await fs.rm(tmp, { force: true });
}

async function downloadFile(url, dest) {
	await fs.mkdir(path.dirname(dest), { recursive: true });
	await run('curl', ['-fL', url, '-o', dest], { label: `download ${url}` });
}

async function syncDirectory(source, dest, excludes = []) {
	await fs.mkdir(dest, { recursive: true });
	const args = ['-a', '--delete'];
	for (const exclude of excludes) {
		args.push(`--exclude=${exclude}`);
	}
	args.push(`${source}/`, `${dest}/`);
	await run('rsync', args, { label: `sync ${source}` });
}

async function waitForHttp(url, timeoutMs) {
	const deadline = Date.now() + timeoutMs;
	let lastError;
	while (Date.now() < deadline) {
		try {
			await fetchText(url);
			return;
		} catch (error) {
			lastError = error;
			await sleep(3000);
		}
	}
	throw new Error(`Timed out waiting for ${url}: ${lastError?.message || 'unknown error'}`);
}

async function fetchText(url) {
	return new Promise((resolve, reject) => {
		const req = http.get(url, (res) => {
			let body = '';
			res.setEncoding('utf8');
			res.on('data', (chunk) => { body += chunk; });
			res.on('end', () => {
				if ((res.statusCode || 0) >= 400) {
					reject(new Error(`${url} returned ${res.statusCode}`));
				} else {
					resolve(body);
				}
			});
		});
		req.on('error', reject);
		req.setTimeout(30_000, () => {
			req.destroy(new Error(`Timed out fetching ${url}`));
		});
	});
}

async function getFreePort() {
	return new Promise((resolve, reject) => {
		const server = http.createServer();
		server.listen(0, '127.0.0.1', () => {
			const address = server.address();
			server.close(() => resolve(address.port));
		});
		server.on('error', reject);
	});
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
	if (!(await exists(file))) {
		throw new Error(`Missing ${description}: ${file}`);
	}
}

async function exists(file) {
	try {
		await fs.access(file);
		return true;
	} catch {
		return false;
	}
}

async function run(command, args, options = {}) {
	if (!options.capture) {
		console.log(`$ ${[command, ...args].join(' ')}`);
	}
	return new Promise((resolve, reject) => {
		const child = spawn(command, args, {
			cwd: options.cwd || HARNESS_ROOT,
			env: { ...process.env, ...(options.env || {}) },
			stdio: options.capture || options.logFile ? ['ignore', 'pipe', 'pipe'] : 'inherit',
		});
		let stdout = '';
		let stderr = '';
		child.stdout?.on('data', (chunk) => {
			stdout += chunk.toString();
			if (options.logFile && !options.capture) process.stdout.write(chunk);
		});
		child.stderr?.on('data', (chunk) => {
			stderr += chunk.toString();
			if (options.logFile && !options.capture) process.stderr.write(chunk);
		});
		child.on('error', reject);
		child.on('close', async (code) => {
			if (options.logFile) {
				await fs.writeFile(options.logFile, `${stdout}${stderr}`);
			}
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

function sleep(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

main().catch((error) => {
	console.error(error.stack || error.message);
	process.exit(1);
});
