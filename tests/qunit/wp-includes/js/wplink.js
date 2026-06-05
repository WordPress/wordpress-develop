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
