/*global wpNavMenu */
( function( QUnit, $ ) {
	QUnit.module( 'nav-menu' );
	var assert,
		eventsExpected = 3,
		eventsFired = 0;

	function menuItem( id, depth, parentID, checked ) {
		return $( '<li>', {
			id: 'menu-item-' + id,
			'class': 'menu-item menu-item-depth-' + depth,
		} ).append(
			$( '<input>', {
				type: 'checkbox',
				'class': 'menu-item-checkbox',
				checked: checked,
			} ),
			$( '<input>', {
				'class': 'menu-item-data-db-id',
				value: id,
			} ),
			$( '<input>', {
				'class': 'menu-item-data-parent-id',
				value: parentID,
			} ),
			$( '<input>', {
				'class': 'edit-menu-item-title',
				value: 'Item ' + id,
			} )
		);
	}

	function setupBulkMoveTest( menuItems, parentID, position ) {
		var originalMenuList = wpNavMenu.menuList,
			originalMenusChanged = wpNavMenu.menusChanged,
			menu = $( '<ul>', { id: 'menu-to-edit' } ).append( menuItems ),
			parentDropdown = $( '<select>', { id: 'bulk-menu-item-parent' } ).append(
				$( '<option>', { value: '' } ),
				$( '<option>', { value: parentID, selected: true } )
			),
			positionDropdown = $( '<select>', { id: 'bulk-menu-item-position' } ).append(
				$( '<option>', { value: '' } ),
				$( '<option>', { value: position, selected: true } )
			),
			fieldset = $( '<fieldset>', { id: 'bulk-menu-items-move' } ).append(
				parentDropdown,
				positionDropdown,
				$( '<button>', { 'class': 'menu-items-move' } )
			);

		$( '#qunit-fixture' ).append( fieldset, menu );
		wpNavMenu.jQueryExtensions();
		$.fn.menuItemDepth = function() {
			return parseInt( this.attr( 'class' ).match( /menu-item-depth-(\d+)/ )[1], 10 );
		};
		wpNavMenu.menuList = menu;
		wpNavMenu.menusChanged = false;

		return {
			menu: menu,
			parentDropdown: parentDropdown,
			positionDropdown: positionDropdown,
			restore: function() {
				wpNavMenu.jQueryExtensions();
				wpNavMenu.menuList = originalMenuList;
				wpNavMenu.menusChanged = originalMenusChanged;
			}
		};
	}

	function menuItemIDs( menu ) {
		return menu.children().map( function() {
			return parseInt( this.id.replace( 'menu-item-', '' ), 10 );
		} ).get();
	}

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

	QUnit.test( 'Bulk moving preserves selected subtrees and order.', function( assert ) {
		var test = setupBulkMoveTest(
			[
				menuItem( 1, 0, 0, false ),
				menuItem( 2, 0, 0, true ),
				menuItem( 3, 1, 2, true ),
				menuItem( 4, 0, 0, false ),
				menuItem( 5, 0, 0, true ),
				menuItem( 6, 0, 0, false )
			],
			1,
			1
		);

		assert.equal( wpNavMenu.getSelectedMenuItems().length, 2, 'Selected descendants are part of their selected ancestor subtree.' );

		wpNavMenu.moveSelectedMenuItems();

		assert.deepEqual( menuItemIDs( test.menu ), [ 1, 2, 3, 5, 4, 6 ], 'Selected subtrees are moved as a contiguous group in their existing order.' );
		assert.equal( $( '#menu-item-2' ).menuItemDepth(), 1, 'The first selected root has the new depth.' );
		assert.equal( $( '#menu-item-3' ).menuItemDepth(), 2, 'Its descendant keeps its relative depth.' );
		assert.equal( $( '#menu-item-5' ).menuItemDepth(), 1, 'The second selected root has the new depth.' );
		assert.equal( $( '#menu-item-2 .menu-item-data-parent-id' ).val(), '1', 'The first selected root has the new parent.' );
		assert.equal( $( '#menu-item-3 .menu-item-data-parent-id' ).val(), '2', 'The descendant keeps its parent.' );
		assert.equal( $( '#menu-item-5 .menu-item-data-parent-id' ).val(), '1', 'The second selected root has the new parent.' );

		test.restore();
	} );

	QUnit.test( 'Bulk moving rejects stale parents and cycles.', function( assert ) {
		var test = setupBulkMoveTest(
			[
				menuItem( 1, 0, 0, true ),
				menuItem( 2, 1, 1, false ),
				menuItem( 3, 0, 0, false )
			],
			2,
			1
		);

		wpNavMenu.moveSelectedMenuItems();

		assert.deepEqual( menuItemIDs( test.menu ), [ 1, 2, 3 ], 'A selected subtree cannot move below its descendant.' );
		assert.equal( $( '#menu-item-1 .menu-item-data-parent-id' ).val(), '0', 'The rejected move preserves the parent.' );
		assert.notOk( wpNavMenu.menusChanged, 'The rejected move does not dirty the menu.' );
		test.restore();

		$( '#qunit-fixture' ).empty();
		test = setupBulkMoveTest(
			[
				menuItem( 1, 0, 0, true ),
				menuItem( 2, 0, 0, false )
			],
			99,
			1
		);

		wpNavMenu.moveSelectedMenuItems();

		assert.deepEqual( menuItemIDs( test.menu ), [ 1, 2 ], 'A missing parent does not become a top-level move.' );
		assert.notOk( wpNavMenu.menusChanged, 'The missing-parent move does not dirty the menu.' );
		test.restore();
	} );

	QUnit.test( 'Bulk moving enforces the maximum subtree depth.', function( assert ) {
		var test = setupBulkMoveTest(
			[
				menuItem( 1, 10, 0, false ),
				menuItem( 2, 9, 0, false ),
				menuItem( 3, 0, 0, true ),
				menuItem( 4, 1, 3, false )
			],
			1,
			1
		);

		wpNavMenu.updateBulkMoveControls();

		assert.notOk( test.parentDropdown.find( 'option[value="1"]' ).length, 'A parent that would exceed the maximum depth is excluded.' );
		assert.ok( test.parentDropdown.find( 'option[value="2"]' ).length, 'A parent at the maximum valid boundary remains available.' );

		test.parentDropdown.append( $( '<option>', { value: 1, selected: true } ) );
		test.positionDropdown.append( $( '<option>', { value: 1, selected: true } ) );
		wpNavMenu.moveSelectedMenuItems();

		assert.equal( $( '#menu-item-3' ).menuItemDepth(), 0, 'The action guard preserves the selected root depth.' );
		assert.equal( $( '#menu-item-4' ).menuItemDepth(), 1, 'The action guard preserves the child depth.' );
		assert.notOk( wpNavMenu.menusChanged, 'The rejected deep move does not dirty the menu.' );
		test.restore();
	} );

	QUnit.test( 'Bulk moving supports the final starting position.', function( assert ) {
		var test = setupBulkMoveTest(
			[
				menuItem( 4, 0, 0, true ),
				menuItem( 5, 0, 0, true ),
				menuItem( 1, 0, 0, false ),
				menuItem( 2, 1, 1, false ),
				menuItem( 3, 1, 1, false ),
				menuItem( 6, 0, 0, false )
			],
			1,
			3
		);

		wpNavMenu.updateBulkMovePositionDropdown();
		assert.deepEqual( test.positionDropdown.find( 'option' ).map( function() {
			return this.value;
		} ).get(), [ '', '1', '2', '3' ], 'Every valid starting position is available.' );

		test.positionDropdown.val( '3' );
		wpNavMenu.moveSelectedMenuItems();

		assert.deepEqual( menuItemIDs( test.menu ), [ 1, 2, 3, 4, 5, 6 ], 'Selected items append after the final existing sibling.' );
		assert.equal( $( '#menu-item-4 .menu-item-data-parent-id' ).val(), '1', 'The appended group gets the selected parent.' );
		assert.equal( $( '#menu-item-5 .menu-item-data-parent-id' ).val(), '1', 'Every appended root gets the selected parent.' );
		test.restore();
	} );


} )( window.QUnit, jQuery );
