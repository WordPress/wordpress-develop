/* global wpLink, jQuery */

QUnit.module( 'wpLink.correctURL', {
	beforeEach: function() {
		// Re-initialize after QUnit resets the fixture, so inputs.url references the live element.
		wpLink.init();
	}
} );

QUnit.test( 'should not prepend http:// to a lowercase https:// URL', function( assert ) {
	jQuery( '#wp-link-url' ).val( 'https://example.com' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'https://example.com',
		'A lowercase https:// URL should not be modified.'
	);
} );

QUnit.test( 'should not prepend http:// to an uppercase HTTPS:// URL', function( assert ) {
	jQuery( '#wp-link-url' ).val( 'HTTPS://example.net' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'HTTPS://example.net',
		'An uppercase HTTPS:// URL should not be modified (common on mobile keyboards).'
	);
} );

QUnit.test( 'should not prepend http:// to a mixed-case Http:// URL', function( assert ) {
	jQuery( '#wp-link-url' ).val( 'Http://example.org' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'Http://example.org',
		'A mixed-case Http:// URL should not be modified.'
	);
} );

QUnit.test( 'should prepend http:// to a bare domain without a scheme', function( assert ) {
	jQuery( '#wp-link-url' ).val( 'example.com' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'http://example.com',
		'A bare domain should get http:// prepended.'
	);
} );

QUnit.test( 'should not prepend http:// to a scheme containing a hyphen', function( assert ) {
	jQuery( '#wp-link-url' ).val( 'chrome-extension://abcdef/options.html' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'chrome-extension://abcdef/options.html',
		'A scheme containing a hyphen should not be modified.'
	);
} );

QUnit.test( 'should not prepend http:// to a scheme containing a plus sign and digits', function( assert ) {
	jQuery( '#wp-link-url' ).val( 'web+demo2://example.com' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'web+demo2://example.com',
		'A scheme containing a plus sign and digits should not be modified.'
	);
} );

QUnit.test( 'should prepend http:// to a bare host and port', function( assert ) {
	jQuery( '#wp-link-url' ).val( 'example.com:8080/path' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'http://example.com:8080/path',
		'A bare host and port is not a scheme, so it should get http:// prepended.'
	);
} );

QUnit.test( 'should not prepend http:// to a fragment URL', function( assert ) {
	jQuery( '#wp-link-url' ).val( '#section' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'#section',
		'A fragment URL should not be modified.'
	);
} );

QUnit.test( 'should not prepend http:// to a root-relative URL', function( assert ) {
	jQuery( '#wp-link-url' ).val( '/page/subpage/' );
	wpLink.correctURL();
	assert.strictEqual(
		jQuery( '#wp-link-url' ).val(),
		'/page/subpage/',
		'A root-relative URL should not be modified.'
	);
} );
