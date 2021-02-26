( function( QUnit ) {
	QUnit.module( 'jQuery' );

	QUnit.test( 'jQuery is run in noConflict mode', function( assert ) {
		assert.expect( 1 );

		assert.ok( 'undefined' === typeof window.$ );
	} );

} )( window.QUnit );
