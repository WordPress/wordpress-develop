/*global wpNavMenu */
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


} )( window.QUnit, jQuery );

/*global wpNavMenu */
( function( QUnit, $ ) {
	var api = window.wpNavMenu,
		originalPost,
		placeholder,
		fixture;

	/**
	 * Returns the next negative placeholder ID.
	 *
	 * Walker_Nav_Menu_Checklist indexes every field name with the global
	 * $_nav_menu_placeholder, which restarts at -1 on each request. Reloading
	 * a checklist therefore renders the same item under a different ID.
	 *
	 * @return {number} The next placeholder.
	 */
	function nextPlaceholder() {
		placeholder = placeholder - 1;
		return placeholder;
	}

	/**
	 * Starts a fresh placeholder run, as a new request would.
	 */
	function newRequest() {
		placeholder = 0;
	}

	/**
	 * Builds a single checklist item.
	 *
	 * Field names are indexed by the placeholder while the checkbox value
	 * carries the real object ID, matching
	 * Walker_Nav_Menu_Checklist::start_el().
	 *
	 * @param {number} objectId Value of the object ID field.
	 * @param {Object} options  Optional title, object, type, url, checked.
	 * @return {string} The `li` markup.
	 */
	function checklistItem( objectId, options ) {
		options = options || {};

		var name    = 'menu-item[' + nextPlaceholder() + ']',
			title   = options.title || ( 'Item ' + objectId ),
			object  = 'undefined' === typeof options.object ? 'page' : options.object,
			type    = options.type || 'post_type',
			url     = options.url || ( 'https://example.org/?p=' + objectId ),
			checked = options.checked ? ' checked="checked"' : '';

		return '<li>' +
			'<label class="menu-item-title">' +
			'<input type="checkbox"' + checked + ' class="menu-item-checkbox" name="' + name + '[menu-item-object-id]" value="' + objectId + '" /> ' + title +
			'</label>' +
			'<input type="hidden" class="menu-item-db-id" name="' + name + '[menu-item-db-id]" value="0" />' +
			'<input type="hidden" class="menu-item-object" name="' + name + '[menu-item-object]" value="' + object + '" />' +
			'<input type="hidden" class="menu-item-parent-id" name="' + name + '[menu-item-parent-id]" value="" />' +
			'<input type="hidden" class="menu-item-type" name="' + name + '[menu-item-type]" value="' + type + '" />' +
			'<input type="hidden" class="menu-item-title" name="' + name + '[menu-item-title]" value="' + title + '" />' +
			'<input type="hidden" class="menu-item-url" name="' + name + '[menu-item-url]" value="' + url + '" />' +
			'</li>';
	}

	/**
	 * Builds the synthetic Home row.
	 *
	 * When no static front page is set, nav-menu.php gives Home the
	 * placeholder as its object ID, so the ID changes on every render.
	 *
	 * @return {string} The `li` markup.
	 */
	function homeItem() {
		return checklistItem( placeholder - 1, {
			type: 'custom',
			object: 'custom',
			title: 'Home',
			url: 'https://example.org/'
		} );
	}

	/**
	 * Builds a post type archive row, which also uses a placeholder ID.
	 *
	 * @return {string} The `li` markup.
	 */
	function archiveItem() {
		return checklistItem( placeholder - 1, {
			type: 'post_type_archive',
			object: 'post',
			title: 'Post Archives',
			url: 'https://example.org/archive/'
		} );
	}

	/**
	 * Wraps items in a meta box with an active and an inactive tab panel.
	 *
	 * @param {string}   id            Meta box ID.
	 * @param {string}   className     `posttypediv` or `taxonomydiv`.
	 * @param {string[]} activeItems   Items for the active panel.
	 * @param {string[]} inactiveItems Items for the inactive panel.
	 * @return {string} The meta box markup.
	 */
	function metabox( id, className, activeItems, inactiveItems ) {
		return '<div id="' + id + '" class="' + className + '">' +
			'<div id="tabs-panel-' + id + '-most-recent" class="tabs-panel tabs-panel-active">' +
			'<ul class="categorychecklist form-no-clear">' + activeItems.join( '' ) + '</ul>' +
			'</div>' +
			'<div id="tabs-panel-' + id + '-all" class="tabs-panel tabs-panel-inactive">' +
			'<ul class="categorychecklist form-no-clear">' + ( inactiveItems || [] ).join( '' ) + '</ul>' +
			'</div>' +
			'<p class="button-controls" data-items-type="' + id + '">' +
			'<label class="add-to-menu"><input type="checkbox" class="select-all" /> Select all</label>' +
			'<span class="add-to-menu"><input type="submit" class="button submit-add-to-menu" value="Add to Menu" id="submit-' + id + '" /></span>' +
			'</p>' +
			'<a class="page-numbers" href="https://example.org/wp-admin/nav-menus.php?paged=2">2</a>' +
			'</div>';
	}

	/**
	 * Renders the meta boxes inside the menus screen containers the event
	 * handlers are delegated from.
	 *
	 * @param {string} metaboxMarkup One or more meta boxes.
	 */
	function renderScreen( metaboxMarkup ) {
		fixture.html(
			'<div id="menu-settings-column">' +
			'<div id="nav-menu-meta">' +
			'<div class="postbox">' +
			'<div class="inside">' + metaboxMarkup + '</div>' +
			'</div>' +
			'</div>' +
			'</div>' +
			'<input type="hidden" id="menu" value="7" />' +
			'<input type="hidden" id="menu-settings-column-nonce" value="nonce" />' +
			'<ul id="menu-to-edit"></ul>'
		);

		api.attachTabsPanelListeners();
	}

	/**
	 * Counts the cached selections for a meta box.
	 *
	 * @param {jQuery} metaboxEl Meta box element.
	 * @return {number} Number of cached items.
	 */
	function countSelected( metaboxEl ) {
		return Object.keys( api.getSelectedMenuItems( metaboxEl ) ).length;
	}

	/**
	 * Finds a checklist checkbox by its object ID value.
	 *
	 * @param {jQuery} context  Element to search within.
	 * @param {number} objectId Object ID.
	 * @return {jQuery} The checkbox.
	 */
	function checkboxFor( context, objectId ) {
		return context.find( 'input.menu-item-checkbox[value="' + objectId + '"]' );
	}

	QUnit.module( 'nav-menu: menu item selections across pagination', {
		beforeEach: function() {
			/*
			 * getItemData() is registered by wpNavMenu.jQueryExtensions(),
			 * which normally runs from wpNavMenu.init() on the menus screen.
			 */
			api.jQueryExtensions();
			api.selectedMenuItems = {};
			fixture = $( '#qunit-fixture' );
			newRequest();

			window.ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
			originalPost = $.post;
		},
		afterEach: function() {
			$.post = originalPost;
			api.selectedMenuItems = {};
		}
	} );

	QUnit.test( 'getMenuItemCheckboxId() reads the placeholder out of the field name.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [ checklistItem( 42 ) ] ) );

		assert.strictEqual(
			api.getMenuItemCheckboxId( fixture.find( 'input.menu-item-checkbox' ) ),
			-1,
			'The first row is indexed by placeholder -1, not by its object ID.'
		);
	} );

	QUnit.test( 'getMenuItemCheckboxId() returns 0 for an unrelated field.', function( assert ) {
		fixture.html( '<input type="checkbox" name="not-a-menu-item" />' );

		assert.strictEqual(
			api.getMenuItemCheckboxId( fixture.find( 'input' ) ),
			0,
			'A name that is not a menu item field yields 0.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemsKey() uses the meta box ID.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [ checklistItem( 1 ) ] ) );

		assert.strictEqual(
			api.getSelectedMenuItemsKey( fixture.find( '#posttype-page' ) ),
			'posttype-page',
			'Each meta box gets its own cache key.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemsKey() falls back to the enclosing postbox ID.', function( assert ) {
		fixture.html( '<div class="postbox" id="add-page"><div class="posttypediv"></div></div>' );

		assert.strictEqual(
			api.getSelectedMenuItemsKey( fixture.find( '.posttypediv' ) ),
			'add-page',
			'A meta box without its own ID uses the postbox ID.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemsKey() returns an empty string when there is no meta box.', function( assert ) {
		assert.strictEqual(
			api.getSelectedMenuItemsKey( $() ),
			'',
			'No stray cache bucket is created for clicks outside a meta box.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemKey() survives the placeholder changing between renders.', function( assert ) {
		var firstRender = {
				'menu-item-type': 'post_type',
				'menu-item-object': 'page',
				'menu-item-object-id': '11',
				'menu-item-url': 'https://example.org/?p=11'
			},
			secondRender = $.extend( {}, firstRender );

		assert.strictEqual(
			api.getSelectedMenuItemKey( firstRender, -1 ),
			api.getSelectedMenuItemKey( secondRender, -7 ),
			'The same page keeps one key even though its placeholder changed.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemKey() keeps two different pages apart.', function( assert ) {
		var first = {
				'menu-item-type': 'post_type',
				'menu-item-object': 'page',
				'menu-item-object-id': '11'
			},
			second = $.extend( {}, first, { 'menu-item-object-id': '12' } );

		assert.notStrictEqual(
			api.getSelectedMenuItemKey( first, -1 ),
			api.getSelectedMenuItemKey( second, -2 ),
			'Two different pages get different keys.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemKey() identifies Home by URL, not by its placeholder.', function( assert ) {
		var firstRender = {
				'menu-item-type': 'custom',
				'menu-item-object': 'custom',
				'menu-item-object-id': '-1',
				'menu-item-url': 'https://example.org/',
				'menu-item-title': 'Home'
			},
			secondRender = $.extend( {}, firstRender, { 'menu-item-object-id': '-4' } );

		assert.strictEqual(
			api.getSelectedMenuItemKey( firstRender, -1 ),
			api.getSelectedMenuItemKey( secondRender, -4 ),
			'Home keeps one key across renders even though its object ID is a placeholder.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemKey() identifies a post type archive across renders.', function( assert ) {
		var firstRender = {
				'menu-item-type': 'post_type_archive',
				'menu-item-object': 'post',
				'menu-item-object-id': '-2',
				'menu-item-url': 'https://example.org/archive/',
				'menu-item-title': 'Post Archives'
			},
			secondRender = $.extend( {}, firstRender, { 'menu-item-object-id': '-9' } );

		assert.strictEqual(
			api.getSelectedMenuItemKey( firstRender, -2 ),
			api.getSelectedMenuItemKey( secondRender, -9 ),
			'A post type archive keeps one key across renders.'
		);
	} );

	QUnit.test( 'getSelectedMenuItemKey() keeps two custom links apart.', function( assert ) {
		var first = {
				'menu-item-type': 'custom',
				'menu-item-object': 'custom',
				'menu-item-object-id': '-1',
				'menu-item-url': 'https://example.org/one',
				'menu-item-title': 'One'
			},
			second = $.extend( {}, first, {
				'menu-item-url': 'https://example.org/two',
				'menu-item-title': 'Two'
			} );

		assert.notStrictEqual(
			api.getSelectedMenuItemKey( first, -1 ),
			api.getSelectedMenuItemKey( second, -2 ),
			'Custom links sharing a placeholder do not overwrite each other.'
		);
	} );

	QUnit.test( 'The cache stores the full item data, not just a marker.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [ checklistItem( 11, { checked: true } ) ] ) );

		var metaboxEl = fixture.find( '#posttype-page' ),
			cached;

		api.syncSelectedMenuItems( metaboxEl );
		cached = api.getSelectedMenuItems( metaboxEl );
		cached = cached[ Object.keys( cached )[0] ];

		assert.strictEqual( cached['menu-item-object-id'], '11', 'The object ID is stored.' );
		assert.strictEqual( cached['menu-item-type'], 'post_type', 'The type is stored.' );
		assert.strictEqual( cached['menu-item-object'], 'page', 'The object is stored.' );
		assert.strictEqual( cached['menu-item-title'], 'Item 11', 'The title is stored.' );
		assert.strictEqual( cached['menu-item-url'], 'https://example.org/?p=11', 'The URL is stored.' );
	} );

	QUnit.test( 'syncSelectedMenuItems() ignores inactive tab panels.', function( assert ) {
		renderScreen( metabox(
			'posttype-page',
			'posttypediv',
			[ checklistItem( 11, { checked: true } ) ],
			[ checklistItem( 99, { checked: true } ) ]
		) );

		var metaboxEl = fixture.find( '#posttype-page' );

		api.syncSelectedMenuItems( metaboxEl );

		assert.strictEqual( countSelected( metaboxEl ), 1, 'Only the visible panel is cached.' );
	} );

	QUnit.test( 'Clicking a checkbox caches it, and clicking again removes it.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [ checklistItem( 11 ) ] ) );

		var metaboxEl = fixture.find( '#posttype-page' ),
			checkbox = checkboxFor( metaboxEl, 11 );

		checkbox.trigger( 'click' );
		assert.strictEqual( countSelected( metaboxEl ), 1, 'The click handler cached the item.' );

		checkbox.trigger( 'click' );
		assert.strictEqual( countSelected( metaboxEl ), 0, 'Clicking again removed it.' );
	} );

	QUnit.test( 'Clicking select all caches every visible row.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [
			checklistItem( 11 ),
			checklistItem( 12 ),
			checklistItem( 13 )
		] ) );

		var metaboxEl = fixture.find( '#posttype-page' );

		metaboxEl.find( '.select-all' ).trigger( 'click' );

		assert.strictEqual( countSelected( metaboxEl ), 3, 'All three rows were cached.' );
	} );

	QUnit.test( 'Clicking a tab clears the cache for that meta box.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [ checklistItem( 11, { checked: true } ) ] ) );

		var metaboxEl = fixture.find( '#posttype-page' );

		api.syncSelectedMenuItems( metaboxEl );
		assert.strictEqual( countSelected( metaboxEl ), 1, 'The item starts out cached.' );

		metaboxEl.prepend( '<ul class="category-tabs"><li><a class="nav-tab-link" data-type="tabs-panel-posttype-page-all" href="#">View All</a></li></ul>' );
		metaboxEl.find( '.nav-tab-link' ).trigger( 'click' );

		assert.strictEqual( countSelected( metaboxEl ), 0, 'Switching tabs emptied the cache.' );
	} );

	QUnit.test( 'Paginating away and back restores the earlier ticks.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [
			checklistItem( 11, { checked: true } ),
			checklistItem( 12 ),
			checklistItem( 13, { checked: true } )
		] ) );

		var metaboxEl = fixture.find( '#posttype-page' ),
			pageTwo,
			pageOneAgain;

		/*
		 * Each response is a fresh request, so the placeholder run restarts
		 * and the same rows come back under different IDs.
		 */
		newRequest();
		pageTwo = metabox( 'posttype-page', 'posttypediv', [ checklistItem( 21 ), checklistItem( 22 ) ] );

		$.post = function( url, data, success ) {
			success( JSON.stringify( { 'replace-id': 'posttype-page', markup: pageTwo } ) );
		};
		fixture.find( 'a.page-numbers' ).trigger( 'click' );

		metaboxEl = fixture.find( '#posttype-page' );
		assert.strictEqual(
			metaboxEl.find( 'input.menu-item-checkbox:checked' ).length,
			0,
			'No page 2 row is ticked by mistake.'
		);
		assert.strictEqual( countSelected( metaboxEl ), 2, 'Both page 1 selections are held.' );

		newRequest();
		pageOneAgain = metabox( 'posttype-page', 'posttypediv', [
			checklistItem( 11 ),
			checklistItem( 12 ),
			checklistItem( 13 )
		] );

		$.post = function( url, data, success ) {
			success( JSON.stringify( { 'replace-id': 'posttype-page', markup: pageOneAgain } ) );
		};
		fixture.find( 'a.page-numbers' ).trigger( 'click' );

		metaboxEl = fixture.find( '#posttype-page' );
		assert.strictEqual( checkboxFor( metaboxEl, 11 ).prop( 'checked' ), true, 'Item 11 is ticked again.' );
		assert.strictEqual( checkboxFor( metaboxEl, 12 ).prop( 'checked' ), false, 'Item 12 stays unticked.' );
		assert.strictEqual( checkboxFor( metaboxEl, 13 ).prop( 'checked' ), true, 'Item 13 is ticked again.' );
	} );

	QUnit.test( 'A ticked Home row survives pagination.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [ homeItem(), checklistItem( 12 ) ] ) );

		var metaboxEl = fixture.find( '#posttype-page' ),
			homeId = metaboxEl.find( 'input.menu-item-checkbox' ).first().val(),
			pageOneAgain;

		metaboxEl.find( 'input.menu-item-checkbox' ).first().trigger( 'click' );
		assert.strictEqual( countSelected( metaboxEl ), 1, 'Home was cached.' );

		// A later request renders Home under a different placeholder.
		newRequest();
		nextPlaceholder();
		nextPlaceholder();
		pageOneAgain = metabox( 'posttype-page', 'posttypediv', [ homeItem(), checklistItem( 12 ) ] );

		$.post = function( url, data, success ) {
			success( JSON.stringify( { 'replace-id': 'posttype-page', markup: pageOneAgain } ) );
		};
		fixture.find( 'a.page-numbers' ).trigger( 'click' );

		metaboxEl = fixture.find( '#posttype-page' );
		assert.notStrictEqual(
			metaboxEl.find( 'input.menu-item-checkbox' ).first().val(),
			homeId,
			'Home really did come back under a different object ID.'
		);
		assert.strictEqual(
			metaboxEl.find( 'input.menu-item-checkbox' ).first().prop( 'checked' ),
			true,
			'Home is still ticked.'
		);
	} );

	QUnit.test( 'A ticked post type archive row survives pagination.', function( assert ) {
		renderScreen( metabox( 'posttype-post', 'posttypediv', [ archiveItem(), checklistItem( 31 ) ] ) );

		var metaboxEl = fixture.find( '#posttype-post' ),
			archiveId = metaboxEl.find( 'input.menu-item-checkbox' ).first().val(),
			pageOneAgain;

		metaboxEl.find( 'input.menu-item-checkbox' ).first().trigger( 'click' );
		assert.strictEqual( countSelected( metaboxEl ), 1, 'The archive row was cached.' );

		// A later request renders the archive under a different placeholder.
		newRequest();
		nextPlaceholder();
		nextPlaceholder();
		pageOneAgain = metabox( 'posttype-post', 'posttypediv', [ archiveItem(), checklistItem( 31 ) ] );

		$.post = function( url, data, success ) {
			success( JSON.stringify( { 'replace-id': 'posttype-post', markup: pageOneAgain } ) );
		};
		fixture.find( 'a.page-numbers' ).trigger( 'click' );

		metaboxEl = fixture.find( '#posttype-post' );
		assert.notStrictEqual(
			metaboxEl.find( 'input.menu-item-checkbox' ).first().val(),
			archiveId,
			'The archive row really did come back under a different object ID.'
		);
		assert.strictEqual(
			metaboxEl.find( 'input.menu-item-checkbox' ).first().prop( 'checked' ),
			true,
			'The archive row is still ticked.'
		);
	} );

	QUnit.test( 'Taxonomy meta boxes keep their selections across pagination.', function( assert ) {
		renderScreen( metabox( 'taxonomy-category', 'taxonomydiv', [
			checklistItem( 5, { type: 'taxonomy', object: 'category', title: 'News' } ),
			checklistItem( 6, { type: 'taxonomy', object: 'category', title: 'Sport' } )
		] ) );

		var metaboxEl = fixture.find( '#taxonomy-category' ),
			pageOneAgain;

		checkboxFor( metaboxEl, 5 ).trigger( 'click' );

		newRequest();
		pageOneAgain = metabox( 'taxonomy-category', 'taxonomydiv', [
			checklistItem( 5, { type: 'taxonomy', object: 'category', title: 'News' } ),
			checklistItem( 6, { type: 'taxonomy', object: 'category', title: 'Sport' } )
		] );

		$.post = function( url, data, success ) {
			success( JSON.stringify( { 'replace-id': 'taxonomy-category', markup: pageOneAgain } ) );
		};
		fixture.find( 'a.page-numbers' ).trigger( 'click' );

		metaboxEl = fixture.find( '#taxonomy-category' );
		assert.strictEqual( checkboxFor( metaboxEl, 5 ).prop( 'checked' ), true, 'The category is ticked again.' );
		assert.strictEqual( checkboxFor( metaboxEl, 6 ).prop( 'checked' ), false, 'The other category is untouched.' );
	} );

	QUnit.test( 'Adding to the menu sends cached items from every page.', function( assert ) {
		renderScreen( metabox( 'posttype-page', 'posttypediv', [ checklistItem( 11, { checked: true } ) ] ) );

		var metaboxEl = fixture.find( '#posttype-page' ),
			originalAdd = api.addItemToMenu,
			pageTwo,
			sent;

		api.syncSelectedMenuItems( metaboxEl );

		newRequest();
		pageTwo = metabox( 'posttype-page', 'posttypediv', [ checklistItem( 21 ), checklistItem( 22 ) ] );

		$.post = function( url, data, success ) {
			success( JSON.stringify( { 'replace-id': 'posttype-page', markup: pageTwo } ) );
		};
		fixture.find( 'a.page-numbers' ).trigger( 'click' );

		metaboxEl = fixture.find( '#posttype-page' );
		checkboxFor( metaboxEl, 21 ).trigger( 'click' );

		api.addItemToMenu = function( menuItems ) {
			sent = menuItems;
		};
		metaboxEl.addSelectedToMenu( api.addMenuItemToBottom );
		api.addItemToMenu = originalAdd;

		var objectIds = $.map( sent, function( item ) {
			return item['menu-item-object-id'];
		} ).sort();

		assert.deepEqual(
			objectIds,
			[ '11', '21' ],
			'The page 1 selection and the page 2 selection are both sent.'
		);
	} );

} )( window.QUnit, jQuery );
