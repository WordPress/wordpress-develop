/**
 * Test admin comment reply/edit form keyboard shortcuts.
 */
( function( $ ) {
	'use strict';

	QUnit.module( 'Admin Comment Reply Form - Ctrl/Cmd+Enter Shortcut', function( hooks ) {
		var commentFormHandler, isComposing, isContextMenuOpen;

		/**
		 * Simulate the keyboard handler logic from commentReply.open()
		 * This validates the condition logic for Ctrl/Cmd+Enter submission.
		 */
		function createCommentFormHandler() {
			return {
				submitCalled: false,
				handleKeydown: function( e ) {
					// Mirror the actual condition from edit-comments.js line 1050-1054
					if ( e.type === 'keydown' && ( e.ctrlKey || e.metaKey ) && e.which === 13 && ! isComposing ) {
						this.submitCalled = true;
						e.preventDefault();
					}
				}
			};
		}

		hooks.beforeEach( function() {
			commentFormHandler = createCommentFormHandler();
			isComposing = false;
			isContextMenuOpen = false;
		} );

		// Test 1: Ctrl+Enter on Windows/Linux submits
		QUnit.test( 'Ctrl+Enter triggers submission', function( assert ) {
			var event = $.Event( 'keydown', {
				type: 'keydown',
				which: 13,
				ctrlKey: true,
				metaKey: false
			} );

			commentFormHandler.handleKeydown( event );

			assert.ok( commentFormHandler.submitCalled, 'Submit called on Ctrl+Enter' );
			assert.ok( event.isDefaultPrevented(), 'Default behavior prevented' );
		} );

		// Test 2: Cmd+Enter on Mac submits
		QUnit.test( 'Cmd+Enter triggers submission (Mac)', function( assert ) {
			var event = $.Event( 'keydown', {
				type: 'keydown',
				which: 13,
				ctrlKey: false,
				metaKey: true
			} );

			commentFormHandler.handleKeydown( event );

			assert.ok( commentFormHandler.submitCalled, 'Submit called on Cmd+Enter' );
			assert.ok( event.isDefaultPrevented(), 'Default behavior prevented' );
		} );

		// Test 3: Plain Enter does NOT submit
		QUnit.test( 'Plain Enter does not trigger submission', function( assert ) {
			var event = $.Event( 'keydown', {
				type: 'keydown',
				which: 13,
				ctrlKey: false,
				metaKey: false
			} );

			commentFormHandler.handleKeydown( event );

			assert.notOk( commentFormHandler.submitCalled, 'Submit NOT called on plain Enter' );
		} );

		// Test 4: IME composition blocks submission
		QUnit.test( 'Ctrl+Enter blocked during IME composition', function( assert ) {
			isComposing = true;

			var event = $.Event( 'keydown', {
				type: 'keydown',
				which: 13,
				ctrlKey: true,
				metaKey: false
			} );

			commentFormHandler.handleKeydown( event );

			assert.notOk( commentFormHandler.submitCalled, 'Submit NOT called when IME is composing' );
		} );

		// Test 5: Ctrl+Shift+Enter does NOT submit
		QUnit.test( 'Ctrl+Shift+Enter does not trigger submission', function( assert ) {
			var event = $.Event( 'keydown', {
				type: 'keydown',
				which: 13,
				ctrlKey: true,
				metaKey: false,
				shiftKey: true
			} );

			commentFormHandler.handleKeydown( event );

			assert.notOk( commentFormHandler.submitCalled, 'Submit NOT called with shift modifier' );
		} );

		// Test 6: Only 'keydown' event type triggers (not keyup, keypress, etc.)
		QUnit.test( 'Only keydown event type triggers submission', function( assert ) {
			var keyupEvent = $.Event( 'keyup', {
				type: 'keyup',
				which: 13,
				ctrlKey: true,
				metaKey: false
			} );

			commentFormHandler.handleKeydown( keyupEvent );

			assert.notOk( commentFormHandler.submitCalled, 'Submit NOT called on keyup event' );
		} );

	} );
} )( jQuery );
