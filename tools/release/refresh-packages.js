/* eslint-disable no-console */
/**
 * External dependencies
 */
const fs = require( 'fs' );
const spawn = require( 'cross-spawn' );
const { zip, uniq, identity, groupBy } = require( 'lodash' );

/**
 * Constants
 */
const WORDPRESS_PACKAGES_PREFIX = '@wordpress/';
const rootDir = __dirname + '/../..';
const { getArgFromCLI } = require( `${ rootDir }/node_modules/@wordpress/scripts/utils` );
const distTag = getArgFromCLI( '--dist-tag' ) || 'latest';

/**
 * The main function of this task.
 *
 * It installs any missing WordPress packages, and updates the
 * mismatched dependencies versions, e.g. it would detect that Gutenberg
 * updated react from 16.0.4 to 17.0.2 and install the latter.
 */
function refreshDependencies() {
	const initialPackageJSON = readJSONFile( `${ rootDir }/package.json` );

	// Install any missing WordPress packages:
	const missingWordPressPackages = getMissingWordPressPackages();
	if ( missingWordPressPackages.length ) {
		console.log( "The following @wordpress dependencies are missing: " );
		console.log( missingWordPressPackages );
		console.log( "Installing via npm..." );
		installPackages( missingWordPressPackages.map( name => [name, distTag] ) );
	}

	// Update any outdated non-WordPress packages:
	const versionMismatches = getMismatchedNonWordPressDependencies();
	if ( versionMismatches.length ) {
		console.log( "The following dependencies are outdated: " );
		console.log( versionMismatches );
		console.log( "Updating via npm..." );
		const requiredPackages = versionMismatches.map( ( { name, required } ) => [name, required] );
		installPackages( requiredPackages );
	}

	const finalPackageJSON = readJSONFile( `${ rootDir }/package.json` );
	outputPackageDiffReport(
		getPackageVersionDiff( initialPackageJSON, finalPackageJSON ),
	);
	process.exit( 0 );
}

refreshDependencies();

function readJSONFile( fileName ) {
	const data = fs.readFileSync( fileName, 'utf8' );
	return JSON.parse( data );
}

function installPackages( packages ) {
	const packagesWithVersion = packages.map(
		( [packageName, version] ) => `${ packageName }@${ version }`,
	);
	return spawn.sync( 'npm', ['install', ...packagesWithVersion, '--save'], {
		stdio: 'inherit',
	} );
}

function getMissingWordPressPackages() {
	const perPackageDeps = getPerPackageDeps();
	const currentPackages = perPackageDeps.map( ( [name] ) => name );

	const requiredWpPackages = uniq( perPackageDeps
		// Capture the @wordpress dependencies of our dependencies into a flat list.
		.flatMap( ( [, dependencies] ) => getWordPressPackages( { dependencies } ) )
		.sort(),
	);

	return requiredWpPackages.filter(
		packageName => !currentPackages.includes( packageName ) );
}

function getMismatchedNonWordPressDependencies() {
	// Get the installed dependencies from package-lock.json
	const currentPackageJSON = readJSONFile( `${ rootDir }/package.json` );
	const currentPackages = getWordPressPackages( currentPackageJSON );

	const packageLock = readJSONFile( `${ rootDir }/package-lock.json` );
	const versionConflicts = Object.entries( packageLock.dependencies )
		.filter( ( [packageName] ) => currentPackages.includes( packageName ) )
		.flatMap( ( [, { dependencies }] ) => Object.entries( dependencies || {} ) )
		.filter( identity )
		.map( ( [name, { version }] ) => ( {
			name,
			required: version,
			actual: packageLock.dependencies[ name ].version,
		} ) )
		.filter( ( { required, actual } ) => required !== actual )
	;

	// Ensure that all the conflicts can be resolved with the same version
	const unresolvableConflicts = Object.entries( groupBy( versionConflicts, ( [name] ) => name ) )
		.map( ( [name, group] ) => [name, group.map( ( [, { required }] ) => required )] )
		.filter( ( [, group] ) => group.length > 1 );
	if ( unresolvableConflicts.length > 0 ) {
		console.error( "Can't resolve some conflicts automatically." );
		console.error( "Multiple required versions of the following packages were detected:" );
		console.error( unresolvableConflicts );
		process.exit( 1 );
	}
	return versionConflicts;
}

function getPerPackageDeps() {
	// Get the dependencies currently listed in the wordpress-develop package.json
	const currentPackageJSON = readJSONFile( 'package.json' );
	const currentPackages = getWordPressPackages( currentPackageJSON );

	// Get the dependencies that the above dependencies list in their package.json.
	const deps = currentPackages
		.map( ( packageName ) => `node_modules/${ packageName }/package.json` )
		.map( ( jsonPath ) => readJSONFile( jsonPath ).dependencies );
	return zip( currentPackages, deps );
}

function getWordPressPackages( { dependencies = {} } ) {
	return Object.keys( dependencies )
		.filter( isWordPressPackage );
}

function isWordPressPackage( packageName ) {
	return packageName.startsWith( WORDPRESS_PACKAGES_PREFIX );
}

function getPackageVersionDiff( initialPackageJSON, finalPackageJSON
) {
	const diff = ['dependencies', 'devDependencies'].reduce(
		( result, keyPackageJSON ) => {
			return Object.keys(
				finalPackageJSON[ keyPackageJSON ] || {},
			).reduce( ( _result, dependency ) => {
				const initial =
					initialPackageJSON[ keyPackageJSON ][ dependency ];
				const final = finalPackageJSON[ keyPackageJSON ][ dependency ];
				if ( initial !== final ) {
					_result.push( { dependency, initial, final } );
				}
				return _result;
			}, result );
		},
		[],
	);
	return diff.sort( ( a, b ) => a.dependency.localeCompare( b.dependency ) );
}

function outputPackageDiffReport( packageDiff ) {
	const readableDiff =
		packageDiff
			.map( ( { dependency, initial, final } ) => {
				return `${ dependency }: ${ initial } -> ${ final }`;
			} )
			.filter( identity );
	if ( !readableDiff.length ) {
		console.log( 'No changes detected' );
		return;
	}
	console.log(
		[
			'The following package versions were changed:',
			...readableDiff,
		].join( '\n' ),
	);
}

/* eslint-enable no-console */
