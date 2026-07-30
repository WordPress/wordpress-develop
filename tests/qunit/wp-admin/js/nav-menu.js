/*global wpNavMenu, wp */
( function( QUnit, $ ) {
	QUnit.module( 'nav-menu' );
	var assert,
		eventsExpected = 3,
		eventsFired = 0;

	// Fail if we don't see the expected number of events triggered in 3 seconds.
	setTimeout( function( assert ) {
		// QUnit may load this file without running it, in which case `assert`
		// will never be set to `assertPassed` below.
		assert && assert.equal(
			eventsFired,
			eventsExpected,
			eventsExpected + ' wpNavMenu events should fire.'
		);
	}, 3000 );

	QUnit.test( 'Testing wpNavMenu event triggers.', function( assertPassed ) {
		assert = assertPassed;

		assert.expect( 3 );

		var testString = '<div>Hello World</div>';

		// Mock global menus.
		if ( ! window.hasOwnProperty( 'menus' ) ) {
			window.menus = {
				'itemAdded': false,
				'itemDeleted': false
			};
		}

		// Mock global wp.a11y.
		window.wp = window.wp || {};
		window.wp.a11y = {
			'speak': function() {}
		};

		// Mock the internal function calls so the don't fail.
		$.fn.hideAdvancedMenuItemFields = function() {
			return {
				'appendTo':       function() { return true; },
				'prependTo':      function() { return true; }
			};
		};

		$.fn.extend( {
			'childMenuItems':  function() { return $(); },
			'shiftDepthClass': function() { return $(); }
		} );

		// Set up the events we should test.
		var eventsToTest = [
			{
				'event':         'addMenuItemToBottom',
				'data':          testString,
				'expect':        $( testString ),
				'shouldTrigger': 'menu-item-added'
			},
			{
				'event':         'addMenuItemToTop',
				'data':          testString,
				'expect':        $( testString ),
				'shouldTrigger': 'menu-item-added'
			},
			{
				'event':         'removeMenuItem',
				'data':          $( testString ),
				'expect':        $( testString ),
				'shouldTrigger': 'menu-removing-item'
			}
		];

		// Test each of the events.
		_.each( eventsToTest, function( theEvent ) {

			var done = assert.async();

			$( document ).on( theEvent.shouldTrigger, function( evt, passed ) {
				assert.equal(
					passed.html(),
					theEvent.expect.html(),
					'The ' + theEvent.event + ' should trigger ' + theEvent.shouldTrigger + '.'
				);
				eventsFired++;
				done();
			} );
			wpNavMenu[ theEvent.event ]( theEvent.data );
			$( document ).off( theEvent.shouldTrigger );
		} );

	} );

	QUnit.test( 'Quick search appends pages and retries failed requests.', function( assert ) {
		var originalPost = $.post,
			originalSpeak = wp.a11y.speak,
			originalAjaxurl = window.ajaxurl,
			requests = [],
			messages = [],
			fixture = $( '#qunit-fixture' ),
			clock = this.clock,
			panel, input, checklist, loadMore, state;

		fixture.html(
			'<form id="nav-menu-meta">' +
				'<div class="accordion-section-content">' +
					'<div class="tabs-panel">' +
						'<input class="quick-search" name="quick-search-posttype-page" value="term">' +
						'<span class="spinner"></span>' +
						'<ul class="categorychecklist"></ul>' +
						'<button type="button" class="quick-search-load-more" data-load-more-text="Load more" hidden>Load more</button>' +
					'</div>' +
					'<div class="button-controls"><input class="select-all" type="checkbox"></div>' +
				'</div>' +
			'</form>'
		);

		panel = fixture.find( '.tabs-panel' );
		input = panel.find( '.quick-search' );
		checklist = panel.find( '.categorychecklist' );
		loadMore = panel.find( '.quick-search-load-more' );

		wp.a11y.speak = function( message ) {
			messages.push( message );
		};
		window.ajaxurl = '/';
		$.post = function( url, params ) {
			var request = $.Deferred();

			request.params = params;
			request.hasMore = '0';
			request.getResponseHeader = function() {
				return request.hasMore;
			};
			request.abort = function() {
				request.aborted = true;
			};
			requests.push( request );
			return request;
		};

		wpNavMenu.attachQuickSearchListeners();
		wpNavMenu.updateQuickSearchResults( input );
		assert.strictEqual( loadMore.attr( 'aria-disabled' ), 'true', 'Loading state is exposed to assistive technology.' );
		requests[0].reject( requests[0], 'error' );

		assert.notOk( loadMore.attr( 'aria-disabled' ), 'Loading state is removed after the request.' );
		assert.notOk( loadMore.prop( 'hidden' ), 'Retry is shown after an initial failure.' );
		assert.strictEqual( loadMore.text(), 'Try again', 'The retry control has an accurate label.' );

		loadMore.trigger( 'focus' ).trigger( 'click' );
		requests[1].resolve( '<li><input type="checkbox" value="direct"></li>', 'success', requests[1] );
		assert.strictEqual( panel.data( 'quick-search-state' ).page, 1, 'Direct calls initialize search state.' );
		assert.strictEqual( document.activeElement, checklist.find( 'input' ).get( 0 ), 'Retry success repairs focus.' );

		input.val( 'active term' );
		wpNavMenu.updateQuickSearchResults( input );
		input.val( 'x' );
		wpNavMenu.updateQuickSearchResults( input );
		assert.ok( requests[2].aborted, 'A direct short query aborts its request.' );
		assert.notOk( panel.find( '.spinner' ).hasClass( 'is-active' ), 'A direct short query clears the spinner.' );

		input.val( 'old term' ).trigger( 'input' );
		clock.tick( 500 );
		input.val( 'term' ).trigger( 'input' );
		clock.tick( 500 );

		state = panel.data( 'quick-search-state' );
		assert.ok( requests[3].aborted, 'Changing the query aborts its request.' );

		requests[4].hasMore = '1';
		requests[4].resolve( '<li><input type="checkbox" name="menu-item[-1][menu-item-type]" value="1"></li>', 'success', requests[4] );
		requests[3].resolve( '<li><input type="checkbox" value="0"></li>', 'success', requests[3] );

		assert.strictEqual( checklist.find( 'input' ).val(), '1', 'A stale response is ignored.' );
		assert.strictEqual( state.page, 1, 'The first page is recorded after success.' );
		assert.notOk( loadMore.prop( 'hidden' ), 'Load more is shown when another page exists.' );
		assert.strictEqual( messages[3], '1 search result found.', 'The initial results are announced.' );

		wpNavMenu.updateQuickSearchResults( input, true );
		wpNavMenu.updateQuickSearchResults( input, true );
		assert.strictEqual( requests.length, 6, 'A second request is blocked while a page is loading.' );
		requests[5].reject( requests[5], 'error' );

		assert.strictEqual( state.page, 1, 'A failed request does not advance the page.' );
		assert.notOk( loadMore.prop( 'hidden' ), 'Load more remains available after a failure.' );
		assert.strictEqual( checklist.children().length, 1, 'A failed request preserves existing results.' );

		loadMore.trigger( 'focus' );
		wpNavMenu.updateQuickSearchResults( input, true );
		requests[6].resolve(
			'<li><input type="checkbox" name="menu-item[-1][menu-item-type]" value="2"></li>' +
			'<li><input type="checkbox" name="menu-item[-2][menu-item-type]" value="3"></li>',
			'success',
			requests[6]
		);

		assert.strictEqual( requests[6].params.paged, 2, 'A retry requests the same page.' );
		assert.strictEqual( checklist.children().length, 3, 'The next page is appended.' );
		assert.deepEqual(
			checklist.find( 'input' ).map( function() {
				return this.name;
			} ).get(),
			[
				'menu-item[-1][menu-item-type]',
				'menu-item[-2][menu-item-type]',
				'menu-item[-3][menu-item-type]'
			],
			'Appended results have unique menu item IDs.'
		);
		assert.ok( loadMore.prop( 'hidden' ), 'Load more is hidden after the final page.' );
		assert.strictEqual( document.activeElement, checklist.find( 'input' ).eq( 1 ).get( 0 ), 'Focus moves to the first new result.' );
		assert.strictEqual( messages.pop(), '2 additional search results loaded.', 'The added results are announced.' );

		$.post = function() {
			var request = $.Deferred();

			request.getResponseHeader = function() {
				return '0';
			};
			request.resolve( '<li><input type="checkbox" value="synchronous"></li>', 'success', request );
			return request;
		};
		input.val( 'synchronous term' );
		wpNavMenu.updateQuickSearchResults( input );
		state = panel.data( 'quick-search-state' );

		assert.strictEqual( checklist.find( 'input' ).val(), 'synchronous', 'A synchronous response is processed.' );
		assert.notOk( state.loading, 'A synchronous response clears the loading state.' );
		assert.strictEqual( state.request, null, 'A completed synchronous request is not retained.' );

		$.post = originalPost;
		wp.a11y.speak = originalSpeak;
		window.ajaxurl = originalAjaxurl;
	} );

} )( window.QUnit, jQuery );
