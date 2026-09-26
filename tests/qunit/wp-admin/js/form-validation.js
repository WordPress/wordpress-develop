/* global validateForm */

( function( QUnit, $ ) {
	QUnit.module( 'form validation' );

	QUnit.test( 'validateForm() associates an inline error with an empty required field', function( assert ) {
		var form = $( '<form><div class="form-field form-required"><input id="tag-name" type="text"><p id="tag-name-error" class="form-error">A name is required.</p></div></form>' );
		$( 'body' ).append( form );

		assert.false( validateForm( form ) );
		assert.true( form.find( '.form-required' ).hasClass( 'form-invalid' ), 'The required field wrapper is invalid.' );
		assert.strictEqual( form.find( 'input' ).attr( 'aria-invalid' ), 'true', 'The input is marked invalid.' );
		assert.strictEqual( form.find( 'input' ).attr( 'aria-describedby' ), 'tag-name-error', 'The error is associated with the input.' );
	} );

	QUnit.test( 'validateForm() preserves existing descriptions', function( assert ) {
		var form = $( '<form><div class="form-field form-required"><input id="tag-name" type="text" aria-describedby="name-description"><p id="tag-name-error" class="form-error">A name is required.</p></div></form>' );
		$( 'body' ).append( form );

		validateForm( form );

		assert.strictEqual( form.find( 'input' ).attr( 'aria-describedby' ), 'name-description tag-name-error', 'The existing description and error are both associated.' );
	} );

	QUnit.test( 'validateForm() clears invalid state when a required field changes', function( assert ) {
		var form = $( '<form><div class="form-field form-required"><input id="tag-name" type="text"><p id="tag-name-error" class="form-error">A name is required.</p></div></form>' );
		$( 'body' ).append( form );

		validateForm( form );
		form.find( 'input' ).val( 'Tag' ).trigger( 'change' );

		assert.false( form.find( '.form-required' ).hasClass( 'form-invalid' ), 'The wrapper is no longer invalid.' );
		assert.notOk( form.find( 'input' ).attr( 'aria-invalid' ), 'The input is no longer marked invalid.' );
	} );
} )( QUnit, jQuery );
