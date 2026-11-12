/**
 * External dependencies
 */
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

/**
 * WordPress dependencies
 */
import { expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { wpCli } from '../utils/wp-cli';

/**
 * Writes options, then reads options, through WP-CLI.
 *
 * @param {string}   baseURL The URL of the site.
 * @param {string[]} names   The names of the options to read.
 * @param {Object}   values  The option values to write. A value of `false` deletes the option.
 * @return {Object} The stored values of the options in `names`.
 */
function syncOptions( baseURL, names, values = {} ) {
	const input = Buffer.from( JSON.stringify( { names, values } ) ).toString(
		'base64'
	);

	return JSON.parse(
		wpCli( [
			`--url=${ baseURL }`,
			'eval',
			`
				// Read and restore the stored offset, not the value calculated for a named timezone.
				remove_filter( 'pre_option_gmt_offset', 'wp_timezone_override_offset' );
				$input = json_decode( base64_decode( '${ input }' ), true );
				foreach ( $input['values'] as $option => $value ) {
					// get_option() returns false for an option that does not exist.
					false === $value ? delete_option( $option ) : update_option( $option, $value );
				}
				$stored = array();
				foreach ( $input['names'] as $option ) {
					$stored[ $option ] = get_option( $option );
				}
				echo wp_json_encode( (object) $stored );
			`,
		] )
	);
}

/**
 * Waits for all restores to finish, so that one failure does not stop the others.
 *
 * @param {Promise[]} restores The restores in progress.
 * @throws {AggregateError} If a restore failed.
 */
async function settleRestores( restores ) {
	const errors = ( await Promise.allSettled( restores ) )
		.filter( ( { status } ) => status === 'rejected' )
		.map( ( { reason } ) => reason );

	if ( errors.length > 0 ) {
		throw new AggregateError(
			errors,
			errors.map( ( { message } ) => message ).join( '\n' )
		);
	}
}

/**
 * Reads and changes site settings through REST. Teardown restores the keys passed to `remember` or `set`.
 */
export const siteSettings = async ( { requestUtils }, use ) => {
	const originals = {};
	const get = () => requestUtils.rest( { path: '/wp/v2/settings' } );
	const post = ( data ) =>
		requestUtils.rest( { method: 'POST', path: '/wp/v2/settings', data } );
	const remember = async ( keys ) => {
		const current = await get();
		for ( const key of keys ) {
			if ( ! Object.hasOwn( current, key ) ) {
				throw new Error( `Unknown setting: ${ key }` );
			}
			if ( ! Object.hasOwn( originals, key ) ) {
				originals[ key ] = current[ key ];
			}
		}
		return Object.fromEntries(
			keys.map( ( key ) => [ key, current[ key ] ] )
		);
	};

	await use( {
		get,
		remember,
		set: async ( patch ) => {
			await remember( Object.keys( patch ) );
			await post( patch );
		},
	} );

	await settleRestores(
		Object.entries( originals ).map( async ( [ key, value ] ) => {
			const original = { [ key ]: value };
			expect( await post( original ) ).toMatchObject( original );
		} )
	);
};

/**
 * Reads and changes site options through WP-CLI. Teardown restores the keys passed to `remember` or `set`.
 */
export const siteOptions = async ( { baseURL }, use ) => {
	let originals = {};
	const remember = async ( names ) => {
		const current = syncOptions( baseURL, names );
		originals = { ...current, ...originals };
		return current;
	};

	await use( {
		get: async ( names ) => syncOptions( baseURL, names ),
		remember,
		set: async ( values ) => {
			await remember( Object.keys( values ) );
			syncOptions( baseURL, [], values );
		},
	} );

	const names = Object.keys( originals );
	if ( names.length > 0 ) {
		expect( syncOptions( baseURL, names, originals ) ).toEqual( originals );
	}
};

/**
 * Writes must-use plugins. Teardown deletes each file that `write` wrote.
 */
export const muPlugin = async ( {}, use ) => {
	const directory = join(
		process.cwd(),
		process.env.LOCAL_DIR ?? 'src',
		'wp-content/mu-plugins'
	);
	const files = new Set();

	await use( {
		write: async ( fileName, php ) => {
			const file = join( directory, fileName );
			files.add( file );
			await mkdir( directory, { recursive: true } );
			await writeFile( file, php );
			return file;
		},
	} );

	await settleRestores(
		[ ...files ].map( ( file ) => rm( file, { force: true } ) )
	);
};
