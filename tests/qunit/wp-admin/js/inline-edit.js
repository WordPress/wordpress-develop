/* global QUnit, inlineEditPost, inlineEditTax */

( function() {
	'use strict';

	QUnit.module( 'wp.inlineEdit' );

	QUnit.test( 'Duplicate post saves are ignored while a request is active', function( assert ) {
		inlineEditPost.saving = true;
		assert.strictEqual( inlineEditPost.save( 1 ), false, 'A duplicate post save is ignored.' );
		inlineEditPost.saving = false;
	} );

	QUnit.test( 'Duplicate taxonomy saves are ignored while a request is active', function( assert ) {
		inlineEditTax.saving = true;
		assert.strictEqual( inlineEditTax.save( 1 ), false, 'A duplicate taxonomy save is ignored.' );
		inlineEditTax.saving = false;
	} );
} )();
