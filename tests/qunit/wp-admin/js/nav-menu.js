/* global wpNavMenu, postboxes, wp, sinon */
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

	QUnit.module( 'nav-menu screen options', {
		beforeEach: function() {
			var requests = this.requests = [];

			this.sandbox = sinon.createSandbox();
			this.originalColumns = window.columns;
			this.originalAjaxurl = window.ajaxurl;
			this.originalSaveMenuBoxesState = wpNavMenu.saveMenuBoxesState;
			this.originalBeforeUnload = window.onbeforeunload;

			window.ajaxurl = '/wp-admin/admin-ajax.php';
			window.columns = {
				useCheckboxesForHidden: this.sandbox.stub(),
				hidden: this.sandbox.stub().returns( '' ),
				saveManageColumnsState: this.sandbox.stub()
			};

			$( '#qunit-fixture' ).append(
				'<input id="screenoptionnonce" value="columns-nonce">' +
				'<input id="closedpostboxesnonce" value="postboxes-nonce">' +
				'<input type="checkbox" class="hide-postbox-tog" value="add-post-type-page" checked>' +
				'<input type="checkbox" class="hide-postbox-tog" value="add-custom-links" checked>' +
				'<div class="accordion-container"><ul>' +
				'<li class="accordion-section" id="add-post-type-page">Pages</li>' +
				'<li class="accordion-section" id="add-custom-links">Custom Links</li>' +
				'</ul></div>' +
				'<form id="update-nav-menu" method="post">' +
				'<input name="action" value="update">' +
				'<input id="menu-name" name="menu-name" value="Test menu" required>' +
				'<input name="nav-menu-data" value="">' +
				'<button type="submit" class="menu-save" name="save_menu" value="Save Menu">Save Menu</button>' +
				'</form>'
			);

			this.sandbox.stub( $, 'post' ).callsFake( function( url, data, success ) {
				var deferred = $.Deferred();

				if ( success ) {
					deferred.done( success );
				}

				requests.push( { url: url, data: data, deferred: deferred } );
				return deferred.promise();
			} );
			this.speak = this.sandbox.stub( wp.a11y, 'speak' );
			this.sandbox.stub( postboxes, 'add_postbox_toggles' );
			this.sandbox.stub( wpNavMenu, 'menuList' ).value( {
				hideAdvancedMenuItemFields: this.sandbox.stub(),
				find: this.sandbox.stub().returns( $() )
			} );

			wpNavMenu.initToggles();

			this.form = $( '#update-nav-menu' )[ 0 ];
			this.saveButton = $( '.menu-save', this.form )[ 0 ];
			this.submitMenu = function( submitter ) {
				var event = new window.SubmitEvent( 'submit', {
					bubbles: true,
					cancelable: true,
					submitter: submitter || this.saveButton
				} );

				this.form.dispatchEvent( event );
				return event;
			}.bind( this );
			this.requestSubmit = this.sandbox.stub( this.form, 'requestSubmit' ).callsFake( this.submitMenu );
			wpNavMenu.attachMenuSaveSubmitListeners();
		},

		afterEach: function() {
			this.sandbox.restore();
			window.onbeforeunload = this.originalBeforeUnload;

			if ( undefined === this.originalSaveMenuBoxesState ) {
				delete wpNavMenu.saveMenuBoxesState;
			} else {
				wpNavMenu.saveMenuBoxesState = this.originalSaveMenuBoxesState;
			}

			if ( undefined === this.originalColumns ) {
				delete window.columns;
			} else {
				window.columns = this.originalColumns;
			}

			if ( undefined === this.originalAjaxurl ) {
				delete window.ajaxurl;
			} else {
				window.ajaxurl = this.originalAjaxurl;
			}
		}
	} );

	QUnit.test( 'Advanced properties wait for the active save and send the latest state', function( assert ) {
		window.columns.hidden.returns( 'link-target' );
		window.columns.saveManageColumnsState();

		assert.strictEqual( this.requests.length, 1, 'The first change is saved immediately.' );
		assert.deepEqual( this.requests[ 0 ].data, {
			action: 'hidden-columns',
			hidden: 'link-target',
			screenoptionnonce: 'columns-nonce',
			page: 'nav-menus'
		}, 'The advanced properties use the hidden-columns action.' );

		window.columns.hidden.returns( 'link-target,title-attribute' );
		window.columns.saveManageColumnsState();
		window.columns.hidden.returns( 'title-attribute' );
		window.columns.saveManageColumnsState();

		assert.strictEqual( this.requests.length, 1, 'Rapid changes do not start overlapping saves.' );

		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 2, 'Pending changes are coalesced into one save.' );
		assert.strictEqual( this.requests[ 1 ].data.hidden, 'title-attribute', 'The final selection is sent.' );

		this.requests[ 1 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 2, 'There are no redundant saves.' );
		assert.strictEqual( this.speak.callCount, 2, 'Successful saves retain the accessibility announcement.' );
	} );

	QUnit.test( 'Boxes wait for the active save and collect the latest visibility', function( assert ) {
		$( '#add-post-type-page' ).hide();
		$( '.hide-postbox-tog' ).first().triggerHandler( 'click' );

		assert.strictEqual( this.requests.length, 1, 'The first change is saved immediately.' );
		assert.deepEqual( this.requests[ 0 ].data, {
			action: 'closed-postboxes',
			hidden: 'add-post-type-page',
			closedpostboxesnonce: 'postboxes-nonce',
			page: 'nav-menus'
		}, 'Boxes use the closed-postboxes action.' );

		$( '#add-custom-links' ).hide();
		$( '.hide-postbox-tog' ).last().triggerHandler( 'click' );

		assert.strictEqual( this.requests.length, 1, 'The second change waits for the first response.' );

		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 2, 'The pending change is saved after the response.' );
		assert.strictEqual( this.requests[ 1 ].data.hidden, 'add-post-type-page,add-custom-links', 'Both hidden boxes are saved.' );
	} );

	QUnit.test( 'Showing all advanced properties sends an empty hidden list', function( assert ) {
		window.columns.hidden.returns( 'link-target' );
		window.columns.saveManageColumnsState();
		window.columns.hidden.returns( '' );
		window.columns.saveManageColumnsState();
		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 2, 'Clearing the list is saved.' );
		assert.strictEqual( this.requests[ 1 ].data.hidden, '', 'An empty list is not discarded.' );
	} );

	QUnit.test( 'Changes made during a follow-up save are also persisted', function( assert ) {
		window.columns.hidden.returns( 'link-target' );
		window.columns.saveManageColumnsState();
		window.columns.hidden.returns( 'title-attribute' );
		window.columns.saveManageColumnsState();
		this.requests[ 0 ].deferred.resolve( '1' );

		window.columns.hidden.returns( 'description' );
		window.columns.saveManageColumnsState();

		assert.strictEqual( this.requests.length, 2, 'The follow-up save also prevents overlapping requests.' );

		this.requests[ 1 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 3, 'The next change is saved when the follow-up completes.' );
		assert.strictEqual( this.requests[ 2 ].data.hidden, 'description', 'The most recent state is sent.' );
	} );

	QUnit.test( 'A failed request releases pending changes without announcing success', function( assert ) {
		window.columns.hidden.returns( 'link-target' );
		window.columns.saveManageColumnsState();
		window.columns.hidden.returns( 'title-attribute' );
		window.columns.saveManageColumnsState();
		this.requests[ 0 ].deferred.reject();

		assert.strictEqual( this.requests.length, 2, 'The failed request does not strand pending changes.' );
		assert.strictEqual( this.requests[ 1 ].data.hidden, 'title-attribute', 'Recovery sends the latest selection.' );
		assert.strictEqual( this.speak.callCount, 0, 'Failure does not announce a successful save.' );

		this.requests[ 1 ].deferred.resolve( '1' );

		assert.strictEqual( this.speak.callCount, 1, 'The successful follow-up is announced.' );
	} );

	QUnit.test( 'An isolated change completes without starting another request', function( assert ) {
		window.columns.saveManageColumnsState();
		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 1, 'Completion alone does not schedule another save.' );

		window.columns.saveManageColumnsState();

		assert.strictEqual( this.requests.length, 2, 'A later change can start a new save immediately.' );
	} );

	QUnit.test( 'Boxes and advanced properties have independent save queues', function( assert ) {
		window.columns.saveManageColumnsState();
		$( '.hide-postbox-tog' ).first().triggerHandler( 'click' );

		assert.strictEqual( this.requests.length, 2, 'Each preference can have one active request.' );

		window.columns.hidden.returns( 'link-target' );
		window.columns.saveManageColumnsState();
		$( '#add-custom-links' ).hide();
		$( '.hide-postbox-tog' ).last().triggerHandler( 'click' );

		assert.strictEqual( this.requests.length, 2, 'Each preference retains its pending change.' );

		this.requests[ 1 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 3, 'Completing Boxes releases only the Boxes queue.' );
		assert.strictEqual( this.requests[ 2 ].data.action, 'closed-postboxes' );
		assert.strictEqual( this.requests[ 2 ].data.hidden, 'add-custom-links' );

		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requests.length, 4, 'Completing advanced properties releases their queue.' );
		assert.strictEqual( this.requests[ 3 ].data.action, 'hidden-columns' );
		assert.strictEqual( this.requests[ 3 ].data.hidden, 'link-target' );
	} );

	QUnit.test( 'Save Menu waits for both queues, including their follow-up requests', function( assert ) {
		window.columns.hidden.returns( 'link-target' );
		window.columns.saveManageColumnsState();
		$( '.hide-postbox-tog' ).first().triggerHandler( 'click' );
		window.columns.hidden.returns( 'link-target,title-attribute' );
		window.columns.saveManageColumnsState();
		$( '#add-custom-links' ).hide();
		$( '.hide-postbox-tog' ).last().triggerHandler( 'click' );

		assert.true( this.submitMenu().defaultPrevented, 'Submission waits while preferences are being saved.' );
		assert.true( this.saveButton.disabled, 'Save Menu indicates that the submission is waiting.' );
		assert.strictEqual( $( this.form ).attr( 'aria-busy' ), 'true' );
		assert.strictEqual( $( '[name="nav-menu-data"]', this.form ).val(), '', 'Menu data is not serialized before the wait.' );

		this.requests[ 0 ].deferred.resolve( '1' );
		assert.strictEqual( this.requests[ 2 ].data.hidden, 'link-target,title-attribute' );
		this.requests[ 2 ].deferred.resolve( '1' );

		assert.strictEqual( this.requestSubmit.callCount, 0, 'The other queue must also finish.' );

		this.requests[ 1 ].deferred.resolve( '1' );
		assert.strictEqual( this.requests[ 3 ].data.hidden, 'add-custom-links' );
		this.requests[ 3 ].deferred.resolve( '1' );

		assert.true( this.requestSubmit.calledOnceWithExactly( this.saveButton ), 'The original submit button is preserved.' );
		assert.false( this.requestSubmit.firstCall.returnValue.defaultPrevented, 'The resumed submission proceeds.' );
		assert.false( this.saveButton.disabled, 'The button is restored before resuming submission.' );
		assert.strictEqual( $( this.form ).attr( 'aria-busy' ), 'false' );
		assert.deepEqual( JSON.parse( $( '[name="nav-menu-data"]', this.form ).val() ), [
			{ name: 'action', value: 'update' },
			{ name: 'menu-name', value: 'Test menu' },
			{ name: 'nav-menu-data', value: '' }
		], 'Menu data is serialized once after the preference saves finish.' );
	} );

	QUnit.test( 'Save Menu waits for an active save even without a queued change', function( assert ) {
		window.columns.saveManageColumnsState();

		assert.true( this.submitMenu().defaultPrevented );

		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requestSubmit.callCount, 1 );
		assert.false( this.requestSubmit.firstCall.returnValue.defaultPrevented );
	} );

	QUnit.test( 'Save Menu rechecks a queue that becomes active again during the wait', function( assert ) {
		window.columns.saveManageColumnsState();
		$( '.hide-postbox-tog' ).first().triggerHandler( 'click' );
		this.submitMenu();
		this.requests[ 0 ].deferred.resolve( '1' );
		window.columns.hidden.returns( 'description' );
		window.columns.saveManageColumnsState();
		this.requests[ 1 ].deferred.resolve( '1' );

		assert.strictEqual( this.requestSubmit.callCount, 1 );
		assert.true( this.requestSubmit.firstCall.returnValue.defaultPrevented, 'The newly active save prevents navigation.' );
		assert.strictEqual( $( '[name="nav-menu-data"]', this.form ).val(), '', 'Menu serialization still waits.' );

		this.requests[ 2 ].deferred.resolve( '1' );

		assert.strictEqual( this.requestSubmit.callCount, 2 );
		assert.false( this.requestSubmit.secondCall.returnValue.defaultPrevented, 'Only the final attempt can navigate.' );
	} );

	QUnit.test( 'Repeated submit events while saving resume the form only once', function( assert ) {
		window.columns.saveManageColumnsState();

		assert.true( this.submitMenu().defaultPrevented );
		assert.true( this.submitMenu().defaultPrevented );

		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requestSubmit.callCount, 1 );
	} );

	QUnit.test( 'A failed preference save does not leave Save Menu disabled', function( assert ) {
		var disabledButton = $( '<button type="submit" class="menu-save" disabled>Disabled</button>' ).appendTo( this.form )[ 0 ];

		window.columns.saveManageColumnsState();
		this.submitMenu();
		this.requests[ 0 ].deferred.reject();

		assert.strictEqual( this.requestSubmit.callCount, 1, 'The menu submission is released after the failure.' );
		assert.false( this.saveButton.disabled, 'The active button is restored.' );
		assert.true( disabledButton.disabled, 'A button disabled before submission stays disabled.' );
		assert.strictEqual( this.speak.callCount, 0, 'The failed preference save is not announced as successful.' );
	} );

	QUnit.test( 'Save Menu submits immediately when both preference queues are idle', function( assert ) {
		assert.false( this.submitMenu().defaultPrevented );
		assert.strictEqual( this.requestSubmit.callCount, 0, 'An idle form does not need to be resubmitted.' );
		assert.false( this.saveButton.disabled );
	} );

	QUnit.test( 'An already canceled submission is not resumed after preference saves', function( assert ) {
		var event = new window.SubmitEvent( 'submit', { bubbles: true, cancelable: true } );

		window.columns.saveManageColumnsState();
		event.preventDefault();
		this.form.dispatchEvent( event );
		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( this.requestSubmit.callCount, 0, 'Another handler can cancel submission.' );
	} );

	QUnit.test( 'Deferred submission retains the unsaved-changes warning and refreshes item positions', function( assert ) {
		var beforeUnload = function() { return 'Unsaved menu changes'; },
			positions = $( '<input class="menu-item-data-position" name="menu-item-position[1]" value="99">' ).appendTo( this.form );

		wpNavMenu.menuList.find.returns( positions );
		window.onbeforeunload = beforeUnload;
		window.columns.saveManageColumnsState();
		wpNavMenu.eventOnClickMenuSave();
		this.submitMenu();

		assert.strictEqual( window.onbeforeunload, beforeUnload, 'The warning stays active while submission waits.' );

		positions = positions.add( $( '<input class="menu-item-data-position" name="menu-item-position[2]" value="99">' ).appendTo( this.form ) );
		wpNavMenu.menuList.find.returns( positions );
		this.requests[ 0 ].deferred.resolve( '1' );

		assert.deepEqual( positions.map( function() { return this.value; } ).get(), [ '1', '2' ], 'Positions reflect edits made during the wait.' );
		assert.strictEqual( window.onbeforeunload, null, 'The warning clears when the menu is ready to submit.' );
	} );

	QUnit.test( 'Native resubmission validates the current menu name and preserves the submitter', function( assert ) {
		var submissions = 0,
			submitter;

		this.requestSubmit.restore();
		this.form.addEventListener( 'submit', function( event ) {
			if ( ! event.defaultPrevented ) {
				submissions++;
				submitter = event.submitter;
				event.preventDefault();
			}
		} );

		window.columns.saveManageColumnsState();
		this.form.requestSubmit( this.saveButton );
		$( '#menu-name' ).val( '' );
		this.requests[ 0 ].deferred.resolve( '1' );

		assert.strictEqual( submissions, 0, 'Native validation stops submission if the menu name was cleared during the wait.' );
		assert.false( this.saveButton.disabled, 'The user can correct the name and submit again.' );

		$( '#menu-name' ).val( '   ' );
		this.form.requestSubmit( this.saveButton );

		assert.strictEqual( submissions, 0, 'The existing whitespace-only name validation is also retained.' );

		$( '#menu-name' ).val( 'Updated menu' );
		this.form.requestSubmit( this.saveButton );

		assert.strictEqual( submissions, 1, 'A valid menu can be submitted after correction.' );
		assert.strictEqual( submitter, this.saveButton, 'Native submission keeps the original Save Menu button.' );
	} );

} )( window.QUnit, jQuery );
