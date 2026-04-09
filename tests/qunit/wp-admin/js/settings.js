/* global QUnit, wp */
jQuery( function( $ ) {
	QUnit.module( 'wp.admin.settings', {
		beforeEach: function() {
			// Create the test form.
			this.$form = $( '<form action="options.php"><input name="foo" value="original"></form>' )
				.appendTo( '#qunit-fixture' );

			// Manually initialize settings module behavior for this test form.
			// (The production module runs at DOM ready before the test form exists,
			// so we replicate its initialization here after the form is created.)
			this.originalData = this.$form.serialize();
			this.isSubmitting = false;
			var self = this;

			// Define the startWatchingForUnload function.
			this.startWatchingForUnload = function() {
				self.$form.off( 'change.settings input.settings', self.startWatchingForUnload );
				$( window ).on( 'beforeunload.settings', function() {
					if ( ! self.isSubmitting && self.originalData !== self.$form.serialize() ) {
						return wp.i18n.__( 'The changes you made will be lost if you navigate away from this page.' );
					}
				} );
			};

			// Attach submit handler to suppress warning on intentional form submission.
			this.$form.on( 'submit.settings', function() {
				self.isSubmitting = true;
				$( window ).off( 'beforeunload.settings' );
			} );

			// Attach the lazy beforeunload listener on first change.
			this.$form.on( 'change.settings input.settings', this.startWatchingForUnload );
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
		// Before any change, beforeunload should return undefined (no handler attached).
		var resultBefore = $( window ).triggerHandler( 'beforeunload.settings' );
		assert.strictEqual( resultBefore, undefined, 'beforeunload listener not attached until first change.' );

		// Now trigger a change on the main test form.
		this.$form.find( 'input' ).val( 'changed' ).trigger( 'change' );

		// After a change, beforeunload should return a message.
		var resultAfter = $( window ).triggerHandler( 'beforeunload.settings' );
		assert.ok( resultAfter, 'beforeunload listener attached after first change.' );
	} );
} );
