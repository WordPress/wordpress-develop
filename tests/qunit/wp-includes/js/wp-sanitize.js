/* global wp */

QUnit.module( 'wp.sanitize.stripTags' );

QUnit.test( 'stripTags should return empty string for null input', function( assert ) {
	const result = wp.sanitize.stripTags( null );
	assert.strictEqual( result, '', 'stripTags( null ) should return ""' );
} );

QUnit.test( 'stripTags should return empty string for undefined input', function( assert ) {
	const result = wp.sanitize.stripTags( undefined );
	assert.strictEqual( result, '', 'stripTags( undefined ) should return ""' );
} );

QUnit.test( 'stripTags should strip tags from string', function( assert ) {
	const result = wp.sanitize.stripTags( '<p>Hello <b>World</b></p>' );
	assert.strictEqual( result, 'Hello World', 'stripTags( "<p>Hello <b>World</b></p>" ) should return "Hello World"' );
} );

QUnit.test( 'stripTags should convert numbers to strings', function( assert ) {
	const result = wp.sanitize.stripTags( 123 );
	assert.strictEqual( result, '123', 'stripTags( 123 ) should return "123"' );
} );

QUnit.module( 'wp.sanitize.stripTagsAndEncodeText' );

QUnit.test( 'stripTagsAndEncodeText should return empty string for null input', function( assert ) {
	const result = wp.sanitize.stripTagsAndEncodeText( null );
	assert.strictEqual( result, '', 'stripTagsAndEncodeText( null ) should return ""' );
} );

QUnit.test( 'stripTagsAndEncodeText should return empty string for undefined input', function( assert ) {
	const result = wp.sanitize.stripTagsAndEncodeText( undefined );
	assert.strictEqual( result, '', 'stripTagsAndEncodeText( undefined ) should return ""' );
} );

QUnit.test( 'stripTagsAndEncodeText should strip tags and encode text', function( assert ) {
	const result = wp.sanitize.stripTagsAndEncodeText( '<p>Hello & <b>World</b></p>' );
	assert.strictEqual( result, 'Hello &amp; World', 'stripTagsAndEncodeText( "<p>Hello & <b>World</b></p>" ) should return "Hello &amp; World"' );
} );
