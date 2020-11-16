/* global wp */
jQuery( window ).load( function() {

	var api = wp.customize, $ = jQuery;

	QUnit.module( 'Customize Widgets' );

	QUnit.test( 'fixtures should be present', function( assert ) {
		var widgetControl;
		assert.ok( api.panel( 'widgets' ) );
		assert.ok( api.section( 'sidebar-widgets-sidebar-1' ) );
		widgetControl = api.control( 'widget_search[2]' );
		assert.ok( widgetControl );
		assert.ok( api.control( 'sidebars_widgets[sidebar-1]' ) );
		assert.ok( api( 'widget_search[2]' ) );
		assert.ok( api( 'sidebars_widgets[sidebar-1]' ) );
		assert.ok( widgetControl.params.content );
		assert.ok( widgetControl.params.widget_control );
		assert.ok( widgetControl.params.widget_content );
		assert.ok( widgetControl.params.widget_id );
		assert.ok( widgetControl.params.widget_id_base );
	});

	QUnit.test( 'widget contents should embed (with widget-added event) when section and control expand', function( assert ) {
		var control, section, widgetAddedEvent = null, widgetControlRootElement = null;
		control = api.control( 'widget_search[2]' );
		section = api.section( 'sidebar-widgets-sidebar-1' );

		$( document ).on( 'widget-added', function( event, widgetElement ) {
			widgetAddedEvent = event;
			widgetControlRootElement = widgetElement;
		});

		assert.ok( ! section.expanded() );
		assert.ok( 0 === control.container.find( '> .widget' ).length );

		// Preview sets the active state.
		section.active.set( true );
		control.active.set( true );
		api.control( 'sidebars_widgets[sidebar-1]' ).active.set( true );

		section.expand();
		assert.ok( ! widgetAddedEvent, 'expected widget added event not fired' );
		assert.ok( 1 === control.container.find( '> .widget' ).length, 'expected there to be one .widget element in the container' );
		assert.ok( 0 === control.container.find( '.widget-content' ).children().length );

		control.expand();
		assert.ok( 1 === control.container.find( '.widget-content' ).children().length );
		assert.ok( widgetAddedEvent );
		assert.ok( widgetControlRootElement.is( control.container.find( '> .widget' ) ) );
		assert.ok( 1 === control.container.find( '.widget-content #widget-search-2-title' ).length );

		$( document ).off( 'widget-added' );
	});

	QUnit.test( 'widgets panel should have notice', function( assert ) {
		var panel = api.panel( 'widgets' );
		assert.ok( panel.extended( api.Widgets.WidgetsPanel ) );

		panel.deferred.embedded.done( function() {
			assert.ok( 1 === panel.contentContainer.find( '.no-widget-areas-rendered-notice' ).length );
			assert.ok( panel.contentContainer.find( '.no-widget-areas-rendered-notice' ).is( ':visible' ) );
			api.section( 'sidebar-widgets-sidebar-1' ).active( true );
			api.control( 'sidebars_widgets[sidebar-1]' ).active( true );
			api.trigger( 'pane-contents-reflowed' );
			assert.ok( ! panel.contentContainer.find( '.no-widget-areas-rendered-notice' ).is( ':visible' ) );
		} );

		assert.expect( 4 );
	});
});
