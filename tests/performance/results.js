#!/usr/bin/env node

const fs = require('fs');
const testSuites = [
    'front-end-classic-theme',
    'front-end-block-theme',
];

console.log( '\n>> 🎉 Results 🎉 \n' );

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
        console.table( tableData );
    });
}