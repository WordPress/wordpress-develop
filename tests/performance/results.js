#!/usr/bin/env node

const fs = require('fs');
const testSuites = [
    'home-classic-theme',
    'home-block-theme',
];

console.log( '\n>> 🎉 Results 🎉 \n' );

function median( array ) {
	const mid = Math.floor( array.length / 2 ),
		numbers = [ ...array ].sort( ( a, b ) => a - b );
	return array.length % 2 !== 0
		? numbers[ mid ]
		: ( numbers[ mid - 1 ] + numbers[ mid ] ) / 2;
}

for ( const testSuite of testSuites ) {
    const resultsFilename = __dirname + '/specs/' + testSuite + '.test.results.json';
    fs.readFile( resultsFilename, "utf8", ( err, data ) => {
        if ( err ) {
            console.log( "File read failed:", err );
            return;
        }
        const convertString = testSuite.charAt(0).toUpperCase() + testSuite.slice(1);
        console.log( convertString.replace(/[-]+/g, " ") + ':' );

        tableData = JSON.parse( data );
        const rawResults = [];

        for (var key in tableData) {
            if ( tableData.hasOwnProperty( key ) ) {
                rawResults[key] = median( tableData[key] );
            }
        }
        console.table( rawResults );
    });
}
