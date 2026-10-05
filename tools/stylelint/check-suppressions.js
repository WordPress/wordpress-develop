const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );

function readSuppressions( contents, filePath ) {
	const suppressions = JSON.parse( contents );

	if (
		suppressions === null ||
		typeof suppressions !== 'object' ||
		Array.isArray( suppressions )
	) {
		throw new Error( `Invalid suppressions file: ${ filePath }` );
	}

	for ( const [ sourceFile, rules ] of Object.entries( suppressions ) ) {
		if ( rules === null || typeof rules !== 'object' || Array.isArray( rules ) ) {
			throw new Error( `Invalid suppressions for ${ sourceFile } in ${ filePath }` );
		}

		for ( const [ rule, data ] of Object.entries( rules ) ) {
			if (
				data === null ||
				typeof data !== 'object' ||
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

function countSuppressions( suppressions ) {
	return Object.values( suppressions ).reduce(
		( total, rules ) =>
			total +
			Object.values( rules ).reduce( ( fileTotal, data ) => fileTotal + data.count, 0 ),
		0
	);
}

function hasIncreasedSuppression( current, baseline ) {
	for ( const [ sourceFile, rules ] of Object.entries( current ) ) {
		for ( const [ rule, data ] of Object.entries( rules ) ) {
			const baselineCount = baseline[ sourceFile ]?.[ rule ]?.count ?? 0;

			if ( data.count > baselineCount ) {
				return true;
			}
		}
	}

	return false;
}

function main() {
	const baseRef = process.argv[ 2 ];

	if ( ! baseRef ) {
		throw new Error( 'Usage: node check-suppressions.js <base-ref>' );
	}

	const baselineContents = execFileSync(
		'git',
		[ 'show', `${ baseRef }:stylelint-suppressions.json` ],
		{ encoding: 'utf8' }
	);
	const baseline = readSuppressions(
		baselineContents,
		`${ baseRef }:stylelint-suppressions.json`
	);
	const current = readSuppressions(
		fs.readFileSync( 'stylelint-suppressions.json', 'utf8' ),
		'stylelint-suppressions.json'
	);
	const baselineCount = countSuppressions( baseline );
	const currentCount = countSuppressions( current );

	if (
		currentCount > baselineCount ||
		hasIncreasedSuppression( current, baseline )
	) {
		throw new Error(
			`Stylelint suppressions must not increase (base: ${ baselineCount }, current: ${ currentCount }).`
		);
	}

	console.log(
		`Stylelint suppressions did not increase (base: ${ baselineCount }, current: ${ currentCount }).`
	);
}

try {
	main();
} catch ( error ) {
	console.error( error );
	process.exitCode = 1;
}
