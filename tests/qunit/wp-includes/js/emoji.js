( function( QUnit ) {
	var FLAG = '🇰🇷'; // Flag: South Korea.

	QUnit.module( 'wp.emoji' );

	function assertNotParsed( assert, container, done ) {
		window.setTimeout( function() {
			assert.strictEqual( container.querySelector( 'img.emoji' ), null, 'the excluded element should not contain an emoji image' );
			container.remove();
			done();
		}, 50 );
	}

	QUnit.test( 'does not parse an excluded element appended whole after load', function( assert ) {
		var done = assert.async(),
			span = document.createElement( 'span' );

		span.className = 'wp-exclude-emoji';
		span.textContent = FLAG;
		document.body.appendChild( span );

		assertNotParsed( assert, span, done );
	} );

	QUnit.test( 'does not parse an excluded element whose text is replaced after load', function( assert ) {
		var done = assert.async(),
			span = document.createElement( 'span' );

		span.className = 'wp-exclude-emoji';
		document.body.appendChild( span );

		window.setTimeout( function() {
			span.textContent = FLAG;
			assertNotParsed( assert, span, done );
		}, 50 );
	} );

	QUnit.test( 'does not parse text appended inside an already-excluded ancestor', function( assert ) {
		var done = assert.async(),
			wrapper = document.createElement( 'span' ),
			child = document.createElement( 'em' );

		wrapper.className = 'wp-exclude-emoji';
		wrapper.appendChild( child );
		document.body.appendChild( wrapper );

		window.setTimeout( function() {
			child.textContent = FLAG;
			assertNotParsed( assert, wrapper, done );
		}, 50 );
	} );
} )( window.QUnit );
