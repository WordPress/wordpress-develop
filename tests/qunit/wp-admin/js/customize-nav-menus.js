/* global wp */
jQuery( window ).load( function() {

	var api = wp.customize,
		primaryMenuId = 3,
		socialMenuId = 2;

	QUnit.module( 'Customize Nav Menus' );

	/**
	 * Generate 20 IDs and verify they are all unique.
	 */
	QUnit.test( 'generatePlaceholderAutoIncrementId generates unique IDs', function( assert ) {
		var testIterations = 20,
			ids = [ api.Menus.generatePlaceholderAutoIncrementId() ];

		while ( testIterations ) {
			var placeholderID = api.Menus.generatePlaceholderAutoIncrementId();

			assert.ok( -1 === ids.indexOf( placeholderID ) );
			ids.push( placeholderID );
			testIterations -= 1;
		}

	} );

	QUnit.test( 'it should parse _wpCustomizeMenusSettings.defaults into itself', function( assert ) {
		assert.deepEqual( window._wpCustomizeNavMenusSettings, api.Menus.data );
	} );

	QUnit.test( 'empty menus should have no Menu Item Controls', function( assert ) {
		assert.ok( 0 === wp.customize.Menus.getMenuControl( socialMenuId ).getMenuItemControls().length, 'empty menus' );
	} );

	QUnit.test( 'populated menus should have no Menu Item Controls', function( assert ) {
		assert.ok( 0 !== wp.customize.Menus.getMenuControl( primaryMenuId ).getMenuItemControls().length, 'non-empty menus' );
	} );

	// @todo Add tests for api.Menus.AvailableMenuItemsPanelView
	// (and api.Menus.AvailableItemCollection, api.Menus.AvailableItemModel).

	QUnit.test( 'there is a properly configured MenusPanel', function( assert ) {
		var panel, sections;

		panel = api.panel( 'nav_menus' );
		assert.ok( panel );
		assert.ok( panel.extended( api.Menus.MenusPanel ) );

		sections = panel.sections();
		assert.ok( 'menu_locations' === sections[0].id, 'first section is menu_locations' );
		assert.ok( sections[1].extended( api.Menus.MenuSection ), 'second section is MenuSection' );
		assert.ok( sections[ sections.length - 1 ].extended( api.Menus.NewMenuSection ), 'last section is NewMenuSection' );
	} );
	// @todo Add more tests for api.Menus.MenusPanel behaviors.

	QUnit.test( 'there an expected MenuSection for the primary menu', function( assert ) {
		var section, controls, lastControl;

		section = api.section( 'nav_menu[' + primaryMenuId + ']' );
		assert.ok( section, 'section exists' );
		assert.ok( section.extended( api.Menus.MenuSection ), 'section is a api.Menus.MenuSection' );
		assert.ok( section.deferred.initSortables, 'has section.deferred.initSortables' );
		assert.ok( section.active(), 'section active() is true' );
		assert.ok( section.active.set( false ).get(), 'section active() cannot be set false' );

		controls = section.controls();
		assert.ok( controls[0].extended( api.Menus.MenuNameControl ), 'first control in menu section is MenuNameControl' );
		assert.ok( controls[1].extended( api.Menus.MenuItemControl ), 'second control in menu section is MenuItemControl' );

		lastControl = controls[ controls.length - 1 ];
		assert.ok( lastControl.extended( api.Control ), 'last control in menu section is a base Control' );
		assert.ok( lastControl.params.templateId === 'nav-menu-delete-button', 'last control in menu section has a delete-button template' );
	} );
	// @todo Add more tests for api.Menus.MenuSection behaviors.

	QUnit.test( 'changing a MenuNameControl change the corresponding menu value', function( assert ) {
		var section, control;

		section = api.section( 'nav_menu[' + primaryMenuId + ']' );
		control = section.controls()[0];
		assert.ok( control.extended( api.Menus.MenuNameControl ), 'control is a MenuNameControl' );
		assert.equal( control.setting().name, 'Primary menu' );
		assert.ok( ! control.setting._dirty );
		control.container.find( 'input[type=text]:first' ).val( 'Main menu' ).trigger( 'change' );
		assert.equal( control.setting().name, 'Main menu' );
		assert.ok( control.setting._dirty );
	} );
	// @todo Add more tests for api.Menus.MenuNameControl

	QUnit.test( 'manipulating a MenuItemControl works', function( assert ) {
		var section, control, value;
		section = api.section( 'nav_menu[' + primaryMenuId + ']' );
		assert.ok( section );

		control = section.controls()[1];
		assert.ok( control.extended( api.Menus.MenuItemControl ), 'control is a MenuItemControl' );

		control.actuallyEmbed();

		control.container.find( '.edit-menu-item-title' ).val( 'Hello World' ).trigger( 'change' );
		assert.equal( control.setting().title, 'Hello World' );
		value = _.clone( control.setting() );
		value.title = 'Hola Mundo';
		assert.equal( control.container.find( '.edit-menu-item-title' ).val(), 'Hello World' );
		assert.equal( value.position, 1 );
		assert.equal( control.priority(), 1 );

		// @todo Test control.moveDown().
	} );
	// @todo Add more tests for api.Menus.MenuItemControl.

	// @todo Add tests for api.Menus.NewMenuSection.
	// @todo Add tests for api.Menus.MenuLocationControl.
	// @todo Add tests for api.Menus.MenuLocationsControl.
	// @todo Add tests for api.Menus.MenuAutoAddControl.
	// @todo Add tests for api.Menus.MenuControl.
	// @todo Add tests for api.Menus.applySavedData.
	// @todo Add tests for api.Menus.focusMenuItemControl.
	// @todo Add tests for api.Menus.createNavMenu.

	QUnit.test( 'api.Menus.getMenuControl() should return the expected control', function( assert ) {
		var control = api.Menus.getMenuControl( primaryMenuId );
		assert.ok( !! control, 'control is returned' );
		assert.ok( control.extended( api.Menus.MenuControl ), 'control is a MenuControl' );
	} );

	QUnit.test( 'api.Menus.getMenuItemControl() should return the expected control', function( assert ) {
		var control = api.Menus.getMenuItemControl( 2000 );
		assert.ok( !! control, 'control is returned' );
		assert.ok( control.extended( api.Menus.MenuItemControl ), 'control is a MenuItemControl' );
	} );

} );
