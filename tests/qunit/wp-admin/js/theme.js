/**
 * Test theme keyboard navigation.
 */
( function( $ ) {
	'use strict';

	QUnit.module( 'Theme Keyboard Navigation', function( hooks ) {
		var themePreview, nextCalled, prevCalled;

		function createThemePreview() {
			return {
				nextTheme: function() { nextCalled++; },
				previousTheme: function() { prevCalled++; },
				keyEvent: function( event ) {
					if ( event.shiftKey || event.ctrlKey || event.altKey || event.metaKey ) {
						return;
					}

                    // Right arrow
					if ( event.keyCode === 39 ) {
						this.nextTheme();
					}
                    // Left arrow
                    else if ( event.keyCode === 37 ) {
						this.previousTheme();
					}
				}
			};
		}

		hooks.beforeEach( function() {
			nextCalled = 0;
			prevCalled = 0;
			themePreview = createThemePreview();
		});

		QUnit.test( 'Arrow keys without modifiers', function( assert ) {
			// Right arrow
			themePreview.keyEvent( $.Event( 'keydown', { 
				keyCode: 39, 
				shiftKey: false, 
				ctrlKey: false 
			}) );
			assert.equal( nextCalled, 1, 'Right arrow triggers nextTheme' );

			// Left arrow
			themePreview.keyEvent( $.Event( 'keydown', { 
				keyCode: 37, 
				shiftKey: false, 
				ctrlKey: false 
			}) );
			assert.equal( prevCalled, 1, 'Left arrow triggers previousTheme' );
		} );

		QUnit.test( 'Shift+Arrow keys do nothing', function( assert ) {
			// Shift + Right
			themePreview.keyEvent( $.Event( 'keydown', { 
				keyCode: 39, 
				shiftKey: true, 
				ctrlKey: false 
			}) );
			assert.equal( nextCalled, 0, 'Shift+Right does nothing' );

			// Shift + Left
			themePreview.keyEvent( $.Event( 'keydown', { 
				keyCode: 37, 
				shiftKey: true, 
				ctrlKey: false 
			}) );
			assert.equal( prevCalled, 0, 'Shift+Left does nothing' );
		} );

		QUnit.test( 'Ctrl+Arrow keys do nothing', function( assert ) {
			// Ctrl + Right
			themePreview.keyEvent( $.Event( 'keydown', { 
				keyCode: 39, 
				ctrlKey: true, 
				shiftKey: false 
			}) );
			assert.equal( nextCalled, 0, 'Ctrl+Right does nothing' );

			// Ctrl + Left
			themePreview.keyEvent( $.Event( 'keydown', { 
				keyCode: 37, 
				ctrlKey: true, 
				shiftKey: false 
			}) );
			assert.equal( prevCalled, 0, 'Ctrl+Left does nothing' );
		} );
	} );
})( jQuery );
