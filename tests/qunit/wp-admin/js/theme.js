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
					if ( event.shiftKey || event.ctrlKey || event.metaKey ) {
						return;
					}

					// Right arrow
					if ( event.altKey && event.keyCode === 39 ) {
						event.preventDefault();
						this.nextTheme();
					}
					// Left arrow
					else if ( event.altKey && event.keyCode === 37 ) {
						event.preventDefault();
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

		QUnit.test( 'Arrow keys with Alt modifier', function( assert ) {
			// Right arrow
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 39,
				altKey: true,
				shiftKey: false,
				ctrlKey: false
			}) );
			assert.equal( nextCalled, 1, 'Alt + Right arrow triggers nextTheme' );

			// Left arrow
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 37,
				altKey: true,
				shiftKey: false,
				ctrlKey: false
			}) );
			assert.equal( prevCalled, 1, 'Alt + Left arrow triggers previousTheme' );
		} );

		QUnit.test( 'Arrow keys without Alt do nothing', function( assert ) {
			// Right arrow without Alt - should NOT call nextTheme
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 39,
				altKey: false,
				shiftKey: false,
				ctrlKey: false
			}) );
			assert.equal( nextCalled, 0, 'Right arrow without Alt does nothing' );

			// Left arrow without Alt - should NOT call previousTheme
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 37,
				altKey: false,
				shiftKey: false,
				ctrlKey: false
			}) );
			assert.equal( prevCalled, 0, 'Left arrow without Alt does nothing' );
		} );

		QUnit.test( 'PreventDefault is called for arrow keys with Alt', function( assert ) {
			// This test would need to check if preventDefault was called
			var event = $.Event( 'keydown', {
				keyCode: 39,
				altKey: true,
				shiftKey: false,
				ctrlKey: false
			});

			// Mock the preventDefault method to track if it's called
			var preventDefaultCalled = false;
			event.preventDefault = function() {
				preventDefaultCalled = true;
			};

			themePreview.keyEvent( event );
			assert.ok( preventDefaultCalled, 'preventDefault is called for arrow keys with Alt' );
		});

		QUnit.test( 'Shift+Arrow keys do nothing', function( assert ) {
			// Shift + Right
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 39,
				altKey: false,
				shiftKey: true,
				ctrlKey: false
			}) );
			assert.equal( nextCalled, 0, 'Shift+Right does nothing' );

			// Shift + Left
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 37,
				altKey: false,
				shiftKey: true,
				ctrlKey: false
			}) );
			assert.equal( prevCalled, 0, 'Shift+Left does nothing' );
		} );

		QUnit.test( 'Ctrl+Arrow keys do nothing', function( assert ) {
			// Ctrl + Right
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 39,
				altKey: false,
				ctrlKey: true,
				shiftKey: false
			}) );
			assert.equal( nextCalled, 0, 'Ctrl+Right does nothing' );

			// Ctrl + Left
			themePreview.keyEvent( $.Event( 'keydown', {
				keyCode: 37,
				altKey: false,
				ctrlKey: true,
				shiftKey: false
			}) );
			assert.equal( prevCalled, 0, 'Ctrl+Left does nothing' );
		} );
	} );
	QUnit.module( 'Theme Installer sorting', function( hooks ) {
		var originalRouter, originalSort, originalClearSearch, navigated, view;

		hooks.beforeEach( function() {
			navigated = [];
			originalRouter = _.clone( wp.themes.router );
			wp.themes.router.browsePath = '?browse=';
			wp.themes.router.baseUrl = function( url ) {
				return 'theme-install.php' + url;
			};
			wp.themes.router.navigate = function( url ) {
				navigated.push( url );
			};

			$( '#qunit-fixture' ).html(
				'<div class="wrap">' +
					'<ul class="filter-links"><li><a href="#" class="current" data-sort="favorites">Favorites</a></li></ul>' +
					'<button class="drawer-toggle" aria-expanded="true"></button>' +
				'</div>'
			);
			$( 'body' ).addClass( 'show-filters' );

			view = Object.create( wp.themes.view.Installer.prototype );
			originalSort = view.sort;
			originalClearSearch = view.clearSearch;
			view.activeClass = 'current';
			view.sort = function() {};
			view.clearSearch = function() {};
		} );

		hooks.afterEach( function() {
			wp.themes.router = originalRouter;
			view.sort = originalSort;
			view.clearSearch = originalClearSearch;
			$( 'body' ).removeClass( 'show-filters filters-applied' );
		} );

		QUnit.test( 'Clicking the active tab restores its URL and closes the filter drawer', function( assert ) {
			var $link = $( '.filter-links a' );

			view.onSort( $.Event( 'click', { target: $link[ 0 ] } ) );

			assert.strictEqual( navigated.length, 1, 'The router is updated once.' );
			assert.ok( /\?browse=favorites$/.test( navigated[ 0 ] ), 'The URL is restored to the active tab.' );
			assert.notOk( $( 'body' ).hasClass( 'show-filters' ), 'The filter drawer is closed.' );
		} );
	} );
})( jQuery );
