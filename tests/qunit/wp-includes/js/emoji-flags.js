( function( QUnit ) {
	/*
	 * wp-emoji.js is loaded with `everythingExceptFlag`: the browser renders emoji but failed the
	 * flag test. Every kind of flag that test checks must then be replaced by an image, and nothing
	 * else. See https://core.trac.wordpress.org/ticket/63451.
	 */
	QUnit.module( 'wp.emoji flag fallback' );

	function imageSources( html ) {
		var container = document.createElement( 'div' );

		container.innerHTML = html;
		return Array.prototype.map.call( container.querySelectorAll( 'img.emoji' ), function( img ) {
			return img.getAttribute( 'src' );
		} );
	}

	QUnit.test( 'replaces a country flag', function( assert ) {
		var sources = imageSources( window.wp.emoji.parse( '\uD83C\uDDF0\uD83C\uDDF7' ) ); // Flag: South Korea.

		assert.strictEqual( sources.length, 1, 'one image' );
		assert.ok( /\/1f1f0-1f1f7\.(svg|png)$/.test( sources[0] ), 'for 1f1f0-1f1f7' );
	} );

	QUnit.test( 'replaces the England flag', function( assert ) {
		var sources = imageSources( window.wp.emoji.parse( '\uD83C\uDFF4\uDB40\uDC67\uDB40\uDC62\uDB40\uDC65\uDB40\uDC6E\uDB40\uDC67\uDB40\uDC7F' ) ); // Flag: England.

		assert.strictEqual( sources.length, 1, 'one image' );
		assert.ok( /\/1f3f4-e0067-e0062-e0065-e006e-e0067-e007f\.(svg|png)$/.test( sources[0] ), 'for 1f3f4-e0067-e0062-e0065-e006e-e0067-e007f' );
	} );

	QUnit.test( 'replaces the Scotland flag', function( assert ) {
		var sources = imageSources( window.wp.emoji.parse( '\uD83C\uDFF4\uDB40\uDC67\uDB40\uDC62\uDB40\uDC73\uDB40\uDC63\uDB40\uDC74\uDB40\uDC7F' ) ); // Flag: Scotland.

		assert.strictEqual( sources.length, 1, 'one image' );
		assert.ok( /\/1f3f4-e0067-e0062-e0073-e0063-e0074-e007f\.(svg|png)$/.test( sources[0] ), 'for 1f3f4-e0067-e0062-e0073-e0063-e0074-e007f' );
	} );

	QUnit.test( 'replaces the Wales flag', function( assert ) {
		var sources = imageSources( window.wp.emoji.parse( '\uD83C\uDFF4\uDB40\uDC67\uDB40\uDC62\uDB40\uDC77\uDB40\uDC6C\uDB40\uDC73\uDB40\uDC7F' ) ); // Flag: Wales.

		assert.strictEqual( sources.length, 1, 'one image' );
		assert.ok( /\/1f3f4-e0067-e0062-e0077-e006c-e0073-e007f\.(svg|png)$/.test( sources[0] ), 'for 1f3f4-e0067-e0062-e0077-e006c-e0073-e007f' );
	} );

	QUnit.test( 'replaces the transgender flag', function( assert ) {
		var sources = imageSources( window.wp.emoji.parse( '\uD83C\uDFF3\uFE0F\u200D\u26A7\uFE0F' ) ); // Transgender flag.

		assert.strictEqual( sources.length, 1, 'one image' );
		assert.ok( /\/1f3f3-fe0f-200d-26a7-fe0f\.(svg|png)$/.test( sources[0] ), 'for 1f3f3-fe0f-200d-26a7-fe0f' );
	} );

	QUnit.test( 'replaces the rainbow flag', function( assert ) {
		var sources = imageSources( window.wp.emoji.parse( '\uD83C\uDFF3\uFE0F\u200D\uD83C\uDF08' ) ); // Rainbow flag.

		assert.strictEqual( sources.length, 1, 'one image' );
		assert.ok( /\/1f3f3-fe0f-200d-1f308\.(svg|png)$/.test( sources[0] ), 'for 1f3f3-fe0f-200d-1f308' );
	} );

	QUnit.test( 'replaces the pirate flag', function( assert ) {
		var sources = imageSources( window.wp.emoji.parse( '\uD83C\uDFF4\u200D\u2620\uFE0F' ) ); // Pirate flag.

		assert.strictEqual( sources.length, 1, 'one image' );
		assert.ok( /\/1f3f4-200d-2620-fe0f\.(svg|png)$/.test( sources[0] ), 'for 1f3f4-200d-2620-fe0f' );
	} );

	QUnit.test( 'leaves an emoji that is not a flag as text', function( assert ) {
		var text = '\uD83D\uDE00'; // Grinning face.

		assert.strictEqual( window.wp.emoji.parse( text ), text );
	} );

	QUnit.test( 'leaves a black flag on its own as text', function( assert ) {
		var text = '\uD83C\uDFF4'; // Black flag.

		assert.strictEqual( window.wp.emoji.parse( text ), text );
	} );
} )( window.QUnit );
