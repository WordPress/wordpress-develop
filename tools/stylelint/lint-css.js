/**
 * Runs Stylelint on core CSS and updates stylelint-suppressions.json when its
 * suppression counts can be reduced without increasing any individual count.
 */
const { spawn } = require( 'node:child_process' );
const fs = require( 'node:fs/promises' );
const path = require( 'node:path' );
const { createRequire } = require( 'node:module' );

const requireFromHere = createRequire( __filename );
const STYLELINT_FILES = 'src/**/*.{css,scss}';
const SUPPRESSIONS_FILE = path.resolve( 'stylelint-suppressions.json' );
const STYLELINT_CLI = path.resolve(
	path.dirname( requireFromHere.resolve( 'stylelint' ) ),
	'../bin/stylelint.mjs'
);

function isObject( value ) {
	return value !== null && typeof value === 'object' && ! Array.isArray( value );
}

function validateSuppressions( suppressions, filePath ) {
	if ( ! isObject( suppressions ) ) {
		throw new Error( `Invalid suppressions file: ${ filePath }` );
	}

	for ( const [ sourceFile, rules ] of Object.entries( suppressions ) ) {
		if ( ! isObject( rules ) ) {
			throw new Error( `Invalid suppressions for ${ sourceFile } in ${ filePath }` );
		}

		for ( const [ rule, data ] of Object.entries( rules ) ) {
			if (
				! isObject( data ) ||
				! Number.isSafeInteger( data.count ) ||
				data.count < 0
			) {
				throw new Error(
					`Invalid suppression count for ${ sourceFile } (${ rule }) in ${ filePath }`
				);
			}
		}
	}

	return suppressions;
}

async function readSuppressions( filePath ) {
	try {
		const contents = await fs.readFile( filePath, 'utf8' );
		return validateSuppressions( JSON.parse( contents ), filePath );
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			return {};
		}
		if ( error instanceof SyntaxError ) {
			throw new Error( `Failed to parse suppressions file at ${ filePath }`, {
				cause: error,
			} );
		}
		throw error;
	}
}

function countSuppressions( suppressions ) {
	return Object.values( suppressions ).reduce(
		( total, rules ) =>
			total +
			Object.values( rules ).reduce( ( fileTotal, data ) => fileTotal + data.count, 0 ),
		0
	);
}

function hasIncreasedSuppression( candidate, current ) {
	for ( const [ sourceFile, rules ] of Object.entries( candidate ) ) {
		for ( const [ rule, data ] of Object.entries( rules ) ) {
			const currentCount = current[ sourceFile ]?.[ rule ]?.count ?? 0;

			if ( data.count > currentCount ) {
				return true;
			}
		}
	}

	return false;
}

async function generateSuppressions( location ) {
	await new Promise( ( resolve, reject ) => {
		const stylelint = spawn(
			process.execPath,
			[
				STYLELINT_CLI,
				STYLELINT_FILES,
				'--suppress',
				'--suppress-location',
				location,
			],
			{ stdio: 'inherit' }
		);

		stylelint.on( 'error', reject );
		stylelint.on( 'close', ( code, signal ) => {
			if ( code === 0 ) {
				resolve();
				return;
			}

			reject(
				new Error(
					`Stylelint suppression generation failed${
						signal ? ` with signal ${ signal }` : ` with exit code ${ code }`
					}`
				)
			);
		} );
	} );
}

async function updateSuppressions() {
	const current = await readSuppressions( SUPPRESSIONS_FILE );
	const currentCount = countSuppressions( current );
	const temporaryDirectory = await fs.mkdtemp(
		path.join( path.dirname( SUPPRESSIONS_FILE ), '.stylelint-suppressions-' )
	);

	try {
		await generateSuppressions( temporaryDirectory );

		const candidateFile = path.join(
			temporaryDirectory,
			path.basename( SUPPRESSIONS_FILE )
		);
		const candidate = await readSuppressions( candidateFile );
		const candidateCount = countSuppressions( candidate );

		if (
			candidateCount < currentCount &&
			! hasIncreasedSuppression( candidate, current )
		) {
			await fs.rename( candidateFile, SUPPRESSIONS_FILE );
			console.log(
				`Stylelint suppressions reduced from ${ currentCount } to ${ candidateCount }.`
			);
			return false;
		}

		if (
			candidateCount > currentCount ||
			hasIncreasedSuppression( candidate, current )
		) {
			console.error(
				`Refusing to increase Stylelint suppressions (current: ${ currentCount }, candidate: ${ candidateCount }).`
			);
			return true;
		}

		return false;
	} finally {
		await fs.rm( temporaryDirectory, { recursive: true, force: true } );
	}
}

async function main() {
	const updateOnly = process.argv.includes( '--update-suppressions' );
	const increaseRefused = await updateSuppressions();

	if ( updateOnly ) {
		process.exitCode = increaseRefused ? 1 : 0;
		return;
	}

	const { default: stylelint } = await import( 'stylelint' );

	const { results, report } = await stylelint.lint( {
		files: STYLELINT_FILES,
		formatter: 'string',
	} );

	if ( report ) {
		console.log( report );
	}

	// Stylelint leaves `errored` set on results whose problems were all suppressed, so count what remains.
	const hasErrors = results.some( ( result ) =>
		result.parseErrors.length > 0 ||
		result.invalidOptionWarnings.length > 0 ||
		result.warnings.some( ( warning ) => warning.severity === 'error' )
	);

	process.exitCode = hasErrors ? 1 : 0;
}

main().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
