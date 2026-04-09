/* global QUnit */
jQuery( function( $ ) {
	QUnit.module( 'wp.admin.settings', {
		beforeEach: function() {
			// Provide a stub form and reset module state between tests.
			this.$form = $( '<form action="options.php"><input name="foo" value="original"></form>' )
				.appendTo( '#qunit-fixture' );
		},
		afterEach: function() {
			$( window ).off( 'beforeunload.settings' );
			this.$form.remove();
		}
	} );

	QUnit.test( 'No warning when no changes made', function( assert ) {
		// beforeunload should not be bound yet (lazy attach).
		var result = $( window ).triggerHandler( 'beforeunload.settings' );
		assert.strictEqual( result, undefined, 'No warning shown when form is unchanged.' );
	} );

	QUnit.test( 'Warning fires when form is dirty', function( assert ) {
		// Simulate a field change.
		this.$form.find( 'input' ).val( 'changed' ).trigger( 'change' );

		// Now beforeunload should be attached.
		var result = $( window ).triggerHandler( 'beforeunload.settings' );
		assert.ok( result, 'Warning message returned when form has unsaved changes.' );
		assert.ok( result.indexOf( 'changes you made' ) > -1, 'Warning message contains expected text.' );
	} );

	QUnit.test( 'No warning after form is submitted', function( assert ) {
		// Simulate a change.
		this.$form.find( 'input' ).val( 'changed' ).trigger( 'change' );

		// Simulate form submission (saves settings).
		this.$form.trigger( 'submit' );

		// Now beforeunload should not fire or should be removed.
		var result = $( window ).triggerHandler( 'beforeunload.settings' );
		assert.strictEqual( result, undefined, 'No warning after intentional form submit.' );
	} );

	QUnit.test( 'No warning when form is reverted to original', function( assert ) {
		// Simulate a change.
		this.$form.find( 'input' ).val( 'changed' ).trigger( 'change' );

		// Revert to original value.
		this.$form.find( 'input' ).val( 'original' ).trigger( 'change' );

		// No warning because serialize matches original.
		var result = $( window ).triggerHandler( 'beforeunload.settings' );
		assert.strictEqual( result, undefined, 'No warning when changes are reverted to original state.' );
	} );

	QUnit.test( 'beforeunload listener is lazy (not attached until first change)', function( assert ) {
		// Create a second form to test lazy attach.
		var $testForm = $( '<form action="options.php"><input name="test" value="val"></form>' )
			.appendTo( '#qunit-fixture' );

		// Initially, no beforeunload listener.
		var countBefore = 0;
		$( window ).on( 'beforeunload.settings', function() { countBefore++; } );

		// Trigger beforeunload before any changes.
		$( window ).triggerHandler( 'beforeunload.settings' );

		// Should not have fired (lazy attach).
		assert.strictEqual( countBefore, 0, 'beforeunload not attached until first user change.' );

		$testForm.remove();
	} );
} );
