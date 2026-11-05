/**
 * External dependencies
 */
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

/**
 * Runs a WP-CLI command in the local environment's `cli` container.
 *
 * `wp-cli.yml` points WP-CLI at `src/`, but the site may run from `build/`,
 * so `--path` is set from `LOCAL_DIR` to match the site under test.
 *
 * @param {string[]} args    WP-CLI arguments, without the leading `wp`.
 * @param {Object}   options Options for `execFileSync()`.
 * @return {string|null} The command's standard output, or `null` if `options.stdio` does not pipe it.
 */
export function wpCli( args, options = {} ) {
	return execFileSync(
		process.execPath,
		[
			join( process.cwd(), 'tools/local-env/scripts/docker.js' ),
			'exec',
			'--user',
			'wp_php',
			'cli',
			'wp',
			`--path=/var/www/${ process.env.LOCAL_DIR ?? 'src' }`,
			...args,
		],
		{
			encoding: 'utf-8',
			...options,
			env: { ...process.env, ...options.env, DOTENV_CONFIG_QUIET: 'true' },
		}
	);
}
