import { spawn } from 'node:child_process';
import { writeFile } from 'node:fs/promises';

/**
 * @param {string} command
 * @param {string[]} args
 * @param {{allowFailure?: boolean, capture?: boolean, cwd?: string, env?: NodeJS.ProcessEnv, label?: string, logFile?: string, quiet?: boolean}} [options]
 */
export async function run( command, args, options = {} ) {
	const capture = options.capture || Boolean( options.logFile );
	if ( ! options.quiet ) {
		process.stdout.write( `$ ${ [ command, ...args ].join( ' ' ) }\n` );
	}

	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, args, {
			cwd: options.cwd,
			env: { ...process.env, ...options.env },
			stdio: capture ? [ 'ignore', 'pipe', 'pipe' ] : 'inherit',
		} );
		let stdout = '';
		let stderr = '';
		// Decoding per chunk would corrupt any character split across a pipe
		// boundary, so the streams decode as a whole.
		child.stdout?.setEncoding( 'utf8' );
		child.stderr?.setEncoding( 'utf8' );
		child.stdout?.on( 'data', ( chunk ) => {
			stdout += chunk;
		} );
		child.stderr?.on( 'data', ( chunk ) => {
			stderr += chunk;
		} );
		child.once( 'error', reject );
		child.once( 'close', ( code, signal ) => {
			( async () => {
				if ( options.logFile ) {
					await writeFile(
						options.logFile,
						`${ stdout }${ stderr }`
					);
				}
				const result = { code, signal, stdout, stderr };
				if ( code !== 0 && ! options.allowFailure ) {
					const detail = `${ stdout }${ stderr }`.trim();
					throw new Error(
						`${ options.label || command } failed${
							signal
								? ` with signal ${ signal }`
								: ` with exit code ${ code }`
						}${ detail ? `\n${ detail }` : '' }`
					);
				}
				return result;
			} )().then( resolve, reject );
		} );
	} );
}
