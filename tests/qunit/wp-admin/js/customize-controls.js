/* global JSON, wp */

wp.customize.settingConstructor.abbreviation = wp.customize.Setting.extend({
	validate: function( value ) {
		return value.toUpperCase();
	}
});

jQuery( window ).load( function() {
	'use strict';

	var controlId, controlLabel, controlType, controlContent, controlDescription, controlData, mockControl,
		mockControlInstance, controlExpectedValues, sectionId, sectionContent, sectionData, mockSection,
		sectionInstance, sectionExpectedValues, panelId, panelTitle, panelDescription, panelContent, panelData,
		mockPanel, panelExpectedValues, testCustomizerModel, settingId, settingValue, mockSetting;

	testCustomizerModel = function( model, expectedValues ) {
		if ( ! expectedValues.type || ! wp.customize[ expectedValues.type ] ) {
			throw new Error( 'Must pass value type in expectedValues.' );
		}
		var type = expectedValues.type;
		QUnit.test( 'Model extends proper type', function( assert ) {
			assert.ok( model.extended( wp.customize[ type ] ) );
		} );

		if ( expectedValues.hasOwnProperty( 'id' ) ) {
			QUnit.test( type + ' instance has the right id', function( assert ) {
				assert.equal( model.id, expectedValues.id );
			});
		}
		if ( expectedValues.hasOwnProperty( 'title') ) {
			QUnit.test( type + ' instance has the right title.', function( assert ) {
				assert.equal( model.params.title, expectedValues.title );
			});
		}
		if ( expectedValues.hasOwnProperty( 'description' ) ) {
			QUnit.test( type + ' instance has the right description.', function( assert ) {
				assert.equal( model.params.description, expectedValues.description );
			});
		}
		if ( expectedValues.hasOwnProperty( 'content' ) ) {
			QUnit.test( type + ' instance has the right content.', function( assert ) {
				assert.equal( model.params.content, expectedValues.content );
			});
		}
		if ( expectedValues.hasOwnProperty( 'priority' ) ) {
			QUnit.test( type + ' instance has the right priority.', function( assert ) {
				assert.equal( model.priority(), expectedValues.priority );
			});
		}
		if ( expectedValues.hasOwnProperty( 'active' ) ) {
			QUnit.test( type + ' instance has the right active state.', function( assert ) {
				assert.equal( model.active(), expectedValues.active );
			});
		}
		QUnit.test( type + ' can be deactivated', function( assert ) {
			model.activate();
			model.deactivate();
			assert.equal( model.active(), false );
			model.activate();
			assert.equal( model.active(), true );
			assert.ok(true);
		});

		if ( type === 'Panel' || type === 'Section' ) {
			if ( expectedValues.hasOwnProperty( 'expanded' ) ) {
				QUnit.test( type + ' instance has the right expanded state.', function( assert ) {
					assert.equal( model.expanded(), expectedValues.expanded );
				} );
			}

			QUnit.test( type + ' instance is collapsed after calling .collapse()', function( assert ) {
				model.collapse();
				assert.ok( ! model.expanded() );
			});

			QUnit.test( type + ' instance is expanded after calling .expand()', function( assert ) {
				model.expand();
				assert.ok( model.expanded() );
			});
		}

	};

	QUnit.module( 'Customizer notifications collection' );
	QUnit.test( 'Notifications collection exists', function( assert ) {
		assert.ok( wp.customize.notifications );
		assert.equal( wp.customize.notifications.defaultConstructor, wp.customize.Notification );
	} );

	QUnit.test( 'Notification objects are rendered as part of notifications collection', function( assert ) {
		var container = jQuery( '#customize-notifications-test' ), items, collection;

		collection = new wp.customize.Notifications({
			container: container
		});
		collection.add( 'mycode-1', new wp.customize.Notification( 'mycode-1', { message: 'My message 1' } ) );
		collection.render();
		items = collection.container.find( 'li' );
		assert.equal( items.length, 1 );
		assert.equal( items.first().data( 'code' ), 'mycode-1' );

		collection.add( 'mycode-2', new wp.customize.Notification( 'mycode-2', {
			message: 'My message 2',
			dismissible: true
		} ) );
		collection.render();
		items = collection.container.find( 'li' );
		assert.equal( items.length, 2 );
		assert.equal( items.first().data( 'code' ), 'mycode-2' );
		assert.equal( items.last().data( 'code' ), 'mycode-1' );

		assert.equal( items.first().find( '.notice-dismiss' ).length, 1 );
		assert.equal( items.last().find( '.notice-dismiss' ).length, 0 );

		collection.remove( 'mycode-2' );
		collection.render();
		items = collection.container.find( 'li' );
		assert.equal( items.length, 1 );
		assert.equal( items.first().data( 'code' ), 'mycode-1' );

		collection.remove( 'mycode-1' );
		collection.render();
		assert.ok( collection.container.is( ':hidden' ), 'Notifications area is hidden.' );
	} );

	QUnit.module( 'Customizer Previewed Device' );
	QUnit.test( 'Previewed device defaults to desktop.', function( assert ) {
		assert.equal( wp.customize.previewedDevice.get(), 'desktop' );
	} );

	QUnit.module( 'Customizer Setting in Fixture' );
	QUnit.test( 'Setting has fixture value', function( assert ) {
		assert.equal( wp.customize( 'fixture-setting' )(), 'Lorem Ipsum' );
	} );
	QUnit.test( 'Setting has notifications', function( assert ) {
		var setting = wp.customize( 'fixture-setting' );
		assert.ok( setting.notifications.extended( wp.customize.Values ) );
		assert.equal( wp.customize.Notification, setting.notifications.prototype.constructor.defaultConstructor );
	} );
	QUnit.test( 'Setting has findControls method', function( assert ) {
		var controls, setting = wp.customize( 'fixture-setting' );
		assert.equal( 'function', typeof setting.findControls );
		controls = setting.findControls();
		assert.equal( 1, controls.length );
		assert.equal( 'fixture-control', controls[0].id );
	} );
	QUnit.test( 'Setting constructor object exists', function( assert ) {
		assert.ok( _.isObject( wp.customize.settingConstructor ) );
	} );
	QUnit.test( 'Custom setting constructor is used', function( assert ) {
		var setting = wp.customize( 'fixture-setting-abbr' );
		assert.ok( setting.extended( wp.customize.settingConstructor.abbreviation ) );
		setting.set( 'usa' );
		assert.equal( 'USA', setting.get() );
	} );

	QUnit.module( 'Customizer Control in Fixture' );
	QUnit.test( 'Control exists', function( assert ) {
		assert.ok( wp.customize.control.has( 'fixture-control' ) );
	} );
	QUnit.test( 'Control has the fixture setting', function( assert ) {
		var control = wp.customize.control( 'fixture-control' );
		assert.equal( control.setting(), 'Lorem Ipsum' );
		assert.equal( control.setting.id, 'fixture-setting' );
	} );
	QUnit.test( 'Control has the section fixture section ID', function( assert ) {
		var control = wp.customize.control( 'fixture-control' );
		assert.equal( control.section(), 'fixture-section' );
	} );
	QUnit.test( 'Control has notifications', function ( assert ) {
		var control = wp.customize.control( 'fixture-control' ), settingNotification, controlOnlyNotification, doneEmbedded;
		assert.ok( control.notifications.extended( wp.customize.Values ) );
		assert.equal( wp.customize.Notification, control.notifications.prototype.constructor.defaultConstructor );
		assert.ok( _.isFunction( control.getNotificationsContainerElement ) );
		assert.ok( _.isFunction( control.renderNotifications ) );

		doneEmbedded = assert.async();
		control.deferred.embedded.done( function() {
			var notificationContainerElement;

			assert.equal( 0, _.size( control.notifications._value ) );
			assert.equal( 0, _.size( control.settings['default'].notifications._value ) );

			notificationContainerElement = control.getNotificationsContainerElement();
			assert.equal( 1, notificationContainerElement.length );
			assert.ok( notificationContainerElement.is( '.customize-control-notifications-container' ) );
			assert.equal( 0, notificationContainerElement.find( '> ul > li' ).length );
			assert.equal( 0, notificationContainerElement.height() );

			settingNotification = new wp.customize.Notification( 'setting_invalidity', { message: 'Invalid setting' } );
			controlOnlyNotification = new wp.customize.Notification( 'control_invalidity', { message: 'Invalid control' } );
			control.settings['default'].notifications.add( settingNotification.code, settingNotification );
			control.notifications.add( controlOnlyNotification.code, controlOnlyNotification );

			// Note that renderNotifications is being called manually here since rendering normally happens asynchronously.
			control.notifications.render();

			assert.equal( 2, notificationContainerElement.find( '> ul > li' ).length );
			assert.notEqual( 'none', notificationContainerElement.css( 'display' ) );
			assert.equal( 2, _.size( control.notifications._value ) );
			assert.equal( 1, _.size( control.settings['default'].notifications._value ) );

			control.notifications.remove( controlOnlyNotification.code );
			control.notifications.render();
			assert.equal( 1, notificationContainerElement.find( '> ul > li' ).length );
			assert.notEqual( 'none', notificationContainerElement.css( 'display' ) );

			control.settings['default'].notifications.remove( settingNotification.code );
			control.notifications.render();
			assert.equal( 0, notificationContainerElement.find( '> ul > li' ).length );
			notificationContainerElement.stop().hide(); // Clean up.

			doneEmbedded();
		} );
	} );

	QUnit.module( 'Customizer control without associated settings' );
	QUnit.test( 'Control can be created without settings', function( assert ) {
		var control = new wp.customize.Control( 'settingless', {
			params: {
				content: jQuery( '<li class="settingless">Hello World</li>' ),
				section: 'fixture-section'
			}
		} );
		wp.customize.control.add( control.id, control );
		assert.equal( control.deferred.embedded.state(), 'resolved' );
		assert.ok( null === control.setting );
		assert.ok( jQuery.isEmptyObject( control.settings ) );
	} );

	// Begin sections.
	QUnit.module( 'Customizer Section in Fixture' );
	QUnit.test( 'Fixture section exists', function( assert ) {
		assert.ok( wp.customize.section.has( 'fixture-section' ) );
	} );
	QUnit.test( 'Fixture section has control among controls()', function( assert ) {
		var section = wp.customize.section( 'fixture-section' );
		assert.ok( -1 !== _.pluck( section.controls(), 'id' ).indexOf( 'fixture-control' ) );
	} );
	QUnit.test( 'Fixture section has has expected panel', function( assert ) {
		var section = wp.customize.section( 'fixture-section' );
		assert.equal( section.panel(), 'fixture-panel' );
	} );

	QUnit.module( 'Customizer Default Section with Template in Fixture' );
	QUnit.test( 'Fixture section exists', function( assert ) {
		assert.ok( wp.customize.section.has( 'fixture-section-default-templated' ) );
	} );
	QUnit.test( 'Fixture section has expected content', function( assert ) {
		var id = 'fixture-section-default-templated', section;
		section = wp.customize.section( id );
		assert.ok( ! section.params.content );
		assert.ok( !! section.container );
		assert.ok( !! section.headContainer );
		assert.ok( !! section.contentContainer );
		assert.ok( section.container.has( section.headContainer ) );
		assert.ok( section.container.has( section.contentContainer ) );
		assert.ok( section.headContainer.is( '.control-section.control-section-default' ) );
		assert.ok( 1 === section.headContainer.find( '> .accordion-section-title' ).length );
		assert.ok( section.contentContainer.is( '.accordion-section-content' ) );
		assert.equal( section.headContainer.attr( 'aria-owns' ), section.contentContainer.attr( 'id' ) );
	} );

	QUnit.module( 'Customizer Custom Type (titleless) Section with Template in Fixture' );
	QUnit.test( 'Fixture section exists', function( assert ) {
		assert.ok( wp.customize.section.has( 'fixture-section-titleless-templated' ) );
	} );
	QUnit.test( 'Fixture section has expected content', function( assert ) {
		var id = 'fixture-section-titleless-templated', section;
		section = wp.customize.section( id );
		assert.ok( ! section.params.content );
		assert.ok( !! section.container );
		assert.ok( !! section.headContainer );
		assert.ok( !! section.contentContainer );
		assert.ok( section.container.has( section.headContainer ) );
		assert.ok( section.container.has( section.contentContainer ) );
		assert.ok( section.container.is( '.control-section.control-section-titleless' ) );
		assert.ok( 0 === section.headContainer.find( '> .accordion-section-title' ).length );
		assert.ok( section.contentContainer.is( '.accordion-section-content' ) );
		assert.equal( section.headContainer.attr( 'aria-owns' ), section.contentContainer.attr( 'id' ) );
	} );
	QUnit.module( 'Customizer Custom Type Section Lacking Specific Template' );
	QUnit.test( 'Fixture section has expected content', function( assert ) {
		var id = 'fixture-section-reusing-default-template', section;
		section = wp.customize.section( id );
		assert.ok( ! section.params.content );
		assert.ok( !! section.container );
		assert.ok( !! section.headContainer );
		assert.ok( !! section.contentContainer );
		assert.ok( section.container.has( section.headContainer ) );
		assert.ok( section.container.has( section.contentContainer ) );
		assert.ok( section.headContainer.is( '.control-section.control-section-' + section.params.type ) );
		assert.ok( 1 === section.headContainer.find( '> .accordion-section-title' ).length );
		assert.ok( section.contentContainer.is( '.accordion-section-content' ) );
		assert.equal( section.headContainer.attr( 'aria-owns' ), section.contentContainer.attr( 'id' ) );
	} );
	QUnit.module( 'Customizer Section lacking any params' );
	QUnit.test( 'Fixture section has default params supplied', function( assert ) {
		var id = 'fixture-section-without-params', section, defaultParams;
		section = wp.customize.section( id );
		defaultParams = {
			title: '',
			description: '',
			priority: 100,
			panel: null,
			type: 'default',
			content: null,
			active: true,
			customizeAction: ''
		};
		jQuery.each( defaultParams, function ( key, value ) {
			assert.ok( 'undefined' !== typeof section.params[ key ] );
			assert.equal( value, section.params[ key ] );
		} );
		assert.ok( _.isNumber( section.params.instanceNumber ) );
	} );


	// Begin panels.
	QUnit.module( 'Customizer Panel in Fixture' );
	QUnit.test( 'Fixture panel exists', function( assert ) {
		assert.ok( wp.customize.panel.has( 'fixture-panel' ) );
	} );
	QUnit.test( 'Fixture panel has content', function( assert ) {
		var panel = wp.customize.panel( 'fixture-panel' );
		assert.ok( !! panel.params.content );
		assert.ok( !! panel.container );
		assert.ok( !! panel.headContainer );
		assert.ok( !! panel.contentContainer );
		assert.ok( panel.container.has( panel.headContainer ) );
		assert.ok( panel.container.has( panel.contentContainer ) );
	} );
	QUnit.test( 'Fixture panel has section among its sections()', function( assert ) {
		var panel = wp.customize.panel( 'fixture-panel' );
		assert.ok( -1 !== _.pluck( panel.sections(), 'id' ).indexOf( 'fixture-section' ) );
	} );
	QUnit.test( 'Panel is not expanded by default', function( assert ) {
		var panel = wp.customize.panel( 'fixture-panel' );
		assert.ok( ! panel.expanded() );
	} );
	QUnit.test( 'Panel is not expanded by default', function( assert ) {
		var panel = wp.customize.panel( 'fixture-panel' );
		assert.ok( ! panel.expanded() );
	} );
	QUnit.test( 'Focusing on a control will expand the panel and section', function( assert ) {
		var panel, section, control;
		panel = wp.customize.panel( 'fixture-panel' );
		section = wp.customize.section( 'fixture-section' );
		control = wp.customize.control( 'fixture-control' );
		assert.ok( ! panel.expanded() );
		assert.ok( ! section.expanded() );
		control.focus();
		assert.ok( section.expanded() );
		assert.ok( panel.expanded() );
	} );

	QUnit.module( 'Customizer Default Panel with Template in Fixture' );
	QUnit.test( 'Fixture section exists', function( assert ) {
		assert.ok( wp.customize.panel.has( 'fixture-panel-default-templated' ) );
	} );
	QUnit.test( 'Fixture panel has expected content', function( assert ) {
		var id = 'fixture-panel-default-templated', panel;
		panel = wp.customize.panel( id );
		assert.ok( ! panel.params.content );
		assert.ok( !! panel.container );
		assert.ok( !! panel.headContainer );
		assert.ok( !! panel.contentContainer );
		assert.ok( panel.container.has( panel.headContainer ) );
		assert.ok( panel.container.has( panel.contentContainer ) );
		assert.ok( panel.headContainer.is( '.control-panel.control-panel-default' ) );
		assert.ok( 1 === panel.headContainer.find( '> .accordion-section-title' ).length );
		assert.ok( panel.contentContainer.is( '.control-panel-content' ) );
		assert.equal( panel.headContainer.attr( 'aria-owns' ), panel.contentContainer.attr( 'id' ) );
	} );

	QUnit.module( 'Customizer Custom Type Panel (titleless) with Template in Fixture' );
	QUnit.test( 'Fixture panel exists', function( assert ) {
		assert.ok( wp.customize.panel.has( 'fixture-panel-titleless-templated' ) );
	} );
	QUnit.test( 'Fixture panel has expected content', function( assert ) {
		var id = 'fixture-panel-titleless-templated', panel;
		panel = wp.customize.panel( id );
		assert.ok( ! panel.params.content );
		assert.ok( !! panel.container );
		assert.ok( !! panel.headContainer );
		assert.ok( !! panel.contentContainer );
		assert.ok( panel.container.has( panel.headContainer ) );
		assert.ok( panel.container.has( panel.contentContainer ) );
		assert.ok( panel.headContainer.is( '.control-panel.control-panel-titleless' ) );
		assert.ok( 0 === panel.headContainer.find( '> .accordion-section-title' ).length );
		assert.ok( panel.contentContainer.is( '.control-panel-content' ) );
		assert.equal( panel.headContainer.attr( 'aria-owns' ), panel.contentContainer.attr( 'id' ) );
	} );

	QUnit.module( 'Customizer Custom Type Panel Lacking Specific Template' );
	QUnit.test( 'Fixture panel has expected content', function( assert ) {
		var id = 'fixture-panel-reusing-default-template', panel;
		panel = wp.customize.panel( id );
		assert.ok( ! panel.params.content );
		assert.ok( !! panel.container );
		assert.ok( !! panel.headContainer );
		assert.ok( !! panel.contentContainer );
		assert.ok( panel.container.has( panel.headContainer ) );
		assert.ok( panel.container.has( panel.contentContainer ) );
		assert.ok( panel.headContainer.is( '.control-panel.control-panel-' + panel.params.type ) );
		assert.ok( 1 === panel.headContainer.find( '> .accordion-section-title' ).length );
		assert.ok( panel.contentContainer.is( '.control-panel-content' ) );
		assert.equal( panel.headContainer.attr( 'aria-owns' ), panel.contentContainer.attr( 'id' ) );
	} );
	QUnit.module( 'Customizer Panel lacking any params' );
	QUnit.test( 'Fixture panel has default params supplied', function( assert ) {
		var id = 'fixture-panel-without-params', panel, defaultParams;
		panel = wp.customize.panel( id );
		defaultParams = {
			title: '',
			description: '',
			priority: 100,
			type: 'default',
			content: null,
			active: true
		};
		jQuery.each( defaultParams, function ( key, value ) {
			assert.ok( 'undefined' !== typeof panel.params[ key ] );
			assert.equal( value, panel.params[ key ] );
		} );
		assert.ok( _.isNumber( panel.params.instanceNumber ) );
	} );

	QUnit.module( 'Dynamically-created Customizer Setting Model' );
	settingId = 'new_blogname';
	settingValue = 'Hello World';

	QUnit.test( 'Create a new setting', function( assert ) {
		mockSetting = wp.customize.create(
			settingId,
			settingId,
			settingValue,
			{
				transport: 'refresh',
				previewer: wp.customize.previewer
			}
		);
		assert.equal( mockSetting(), settingValue );
		assert.equal( mockSetting.id, settingId );
	} );

	QUnit.module( 'Dynamically-created Customizer Section Model' );

	sectionId = 'mock_title_tagline';
	sectionContent = '<li id="accordion-section-mock_title_tagline" class="accordion-section control-section control-section-default"> <h3 class="accordion-section-title" tabindex="0"> Section Fixture <span class="screen-reader-text">Press return or enter to open</span> </h3> <ul class="accordion-section-content"> <li class="customize-section-description-container"> <div class="customize-section-title"> <button class="customize-section-back" tabindex="-1"> <span class="screen-reader-text">Back</span> </button> <h3> <span class="customize-action">Customizing &#9656; Fixture Panel</span> Section Fixture </h3> </div> </li> </ul> </li>';
	sectionData = {
		content: sectionContent,
		active: true,
		type: 'default'
	};

	mockSection = new wp.customize.Section( sectionId, { params: sectionData } );

	sectionExpectedValues = {
		type: 'Section',
		id: sectionId,
		content: sectionContent,
		priority: 100,
		active: true // @todo This should default to true.
	};

	testCustomizerModel( mockSection, sectionExpectedValues );

	QUnit.test( 'Section has been embedded', function( assert ) {
		assert.equal( mockSection.deferred.embedded.state(), 'resolved' );
	} );

	wp.customize.section.add( sectionId, mockSection );

	QUnit.test( 'Section instance added to the wp.customize.section object', function( assert ) {
		assert.ok( wp.customize.section.has( sectionId ) );
	});

	sectionInstance = wp.customize.section( sectionId );

	QUnit.test( 'Section instance has right content when accessed from wp.customize.section()', function( assert ) {
		assert.equal( sectionInstance.params.content, sectionContent );
	});

	QUnit.test( 'Section instance has no children yet', function( assert ) {
		assert.equal( sectionInstance.controls().length, 0 );
	});

	QUnit.module( 'Dynamically-created Customizer Control Model' );

	controlId = 'new_blogname';
	controlLabel = 'Site Title';
	controlType = 'text';
	controlContent = '<li id="customize-control-blogname" class="customize-control customize-control-text"></li>';
	controlDescription = 'Test control description';

	controlData = {
		content: controlContent,
		description: controlDescription,
		label: controlLabel,
		settings: { 'default': 'new_blogname' },
		type: controlType,
		active: true // @todo This should default to true.
	};

	mockControl = new wp.customize.Control( controlId, {
		params: controlData,
		previewer: wp.customize.previewer
	});

	controlExpectedValues = {
		type: 'Control',
		content: controlContent,
		description: controlDescription,
		label: controlLabel,
		id: controlId,
		priority: 10
	};

	testCustomizerModel( mockControl, controlExpectedValues );

	QUnit.test( 'Control instance does not yet belong to a section.', function( assert ) {
		assert.equal( mockControl.section(), undefined );
	});
	QUnit.test( 'Control has not been embedded yet', function( assert ) {
		assert.equal( mockControl.deferred.embedded.state(), 'pending' );
	} );

	QUnit.test( 'Control instance has the right selector.', function( assert ) {
		assert.equal( mockControl.selector, '#customize-control-new_blogname' );
	});

	wp.customize.control.add( controlId, mockControl );

	QUnit.test( 'Control instance was added to the control class.', function( assert ) {
		assert.ok( wp.customize.control.has( controlId ) );
	});

	mockControlInstance = wp.customize.control( controlId );

	QUnit.test( 'Control instance has the right id when accessed from api.control().', function( assert ) {
		assert.equal( mockControlInstance.id, controlId );
	});

	QUnit.test( 'Control section can be set as expected', function( assert ) {
		mockControl.section( mockSection.id );
		assert.equal( mockControl.section(), mockSection.id );
	});
	QUnit.test( 'Associating a control with a section allows it to be embedded', function( assert ) {
		assert.equal( mockControl.deferred.embedded.state(), 'resolved' );
	});

	QUnit.test( 'Control is now available on section.controls()', function( assert ) {
		assert.equal( sectionInstance.controls().length, 1 );
		assert.equal( sectionInstance.controls()[0], mockControl );
	});

	QUnit.module( 'Dynamically-created Customizer Panel Model' );

	panelId = 'mockPanelId';
	panelTitle = 'Mock Panel Title';
	panelDescription = 'Mock panel description';
	panelContent = '<li id="accordion-panel-mockPanelId" class="accordion-section control-section control-panel control-panel-default"> <h3 class="accordion-section-title" tabindex="0"> Fixture Panel <span class="screen-reader-text">Press return or enter to open this panel</span> </h3> <ul class="accordion-sub-container control-panel-content"> <li class="panel-meta customize-info accordion-section cannot-expand"> <button class="customize-panel-back" tabindex="-1"><span class="screen-reader-text">Back</span></button> <div class="accordion-section-title"> <span class="preview-notice">You are customizing <strong class="panel-title">Fixture Panel</strong></span> <button class="customize-help-toggle dashicons dashicons-editor-help" tabindex="0" aria-expanded="false"><span class="screen-reader-text">Help</span></button> </div> </li> </ul> </li>';
	panelData = {
		content: panelContent,
		title: panelTitle,
		description: panelDescription,
		active: true, // @todo This should default to true.
		type: 'default'
	};

	mockPanel = new wp.customize.Panel( panelId, { params: panelData } );

	panelExpectedValues = {
		type: 'Panel',
		id: panelId,
		title: panelTitle,
		description: panelDescription,
		content: panelContent,
		priority: 100,
		active: true
	};

	testCustomizerModel( mockPanel, panelExpectedValues );

	QUnit.test( 'Panel instance is not contextuallyActive', function( assert ) {
		assert.equal( mockPanel.isContextuallyActive(), false );
	});

	QUnit.module( 'Test wp.customize.findControlsForSettings' );
	QUnit.test( 'findControlsForSettings(blogname)', function( assert ) {
		var controlsForSettings, settingId = 'fixture-setting', controlId = 'fixture-control';
		assert.ok( wp.customize.control.has( controlId ) );
		assert.ok( wp.customize.has( settingId ) );
		controlsForSettings = wp.customize.findControlsForSettings( [ settingId ] );
		assert.ok( _.isObject( controlsForSettings ), 'Response is object' );
		assert.ok( _.isArray( controlsForSettings['fixture-setting'] ), 'Response has a fixture-setting array' );
		assert.equal( 1, controlsForSettings['fixture-setting'].length );
		assert.equal( wp.customize.control( controlId ), controlsForSettings['fixture-setting'][0] );
	} );

	QUnit.module( 'Customize Controls wp.customize.dirtyValues' );
	QUnit.test( 'dirtyValues() returns expected values', function( assert ) {
		wp.customize.state( 'changesetStatus' ).set( 'auto-draft' );
		wp.customize.each( function( setting ) {
			setting._dirty = false;
		} );
		assert.ok( _.isEmpty( wp.customize.dirtyValues() ) );
		assert.ok( _.isEmpty( wp.customize.dirtyValues( { unsaved: false } ) ) );

		wp.customize( 'fixture-setting' )._dirty = true;
		assert.ok( ! _.isEmpty( wp.customize.dirtyValues() ) );
		assert.ok( _.isEmpty( wp.customize.dirtyValues( { unsaved: true } ) ) );

		wp.customize( 'fixture-setting' ).set( 'Modified' );
		assert.ok( ! _.isEmpty( wp.customize.dirtyValues() ) );
		assert.ok( ! _.isEmpty( wp.customize.dirtyValues( { unsaved: true } ) ) );
		assert.equal( 'Modified', wp.customize.dirtyValues()['fixture-setting'] );

		// When the changeset does not exist, all dirty settings are necessarily unsaved.
		wp.customize.state( 'changesetStatus' ).set( '' );
		wp.customize( 'fixture-setting' )._dirty = true;
		assert.ok( ! _.isEmpty( wp.customize.dirtyValues() ) );
		assert.ok( ! _.isEmpty( wp.customize.dirtyValues( { unsaved: true } ) ) );
	} );

	QUnit.module( 'Customize Controls: wp.customize.requestChangesetUpdate()' );
	QUnit.test( 'requestChangesetUpdate makes request and returns promise', function( assert ) {
		var request, originalBeforeSetup = jQuery.ajaxSettings.beforeSend;

		jQuery.ajaxSetup( {
			beforeSend: function( e, data ) {
				var queryParams, changesetData;
				queryParams = wp.customize.utils.parseQueryString( data.data );

				assert.equal( 'customize_save', queryParams.action );
				assert.ok( ! _.isUndefined( queryParams.customize_changeset_data ) );
				assert.ok( ! _.isUndefined( queryParams.nonce ) );
				assert.ok( ! _.isUndefined( queryParams.customize_theme ) );
				assert.equal( wp.customize.settings.changeset.uuid, queryParams.customize_changeset_uuid );
				assert.equal( 'on', queryParams.wp_customize );

				changesetData = JSON.parse( queryParams.customize_changeset_data );
				assert.ok( ! _.isUndefined( changesetData.additionalSetting ) );
				assert.ok( ! _.isUndefined( changesetData['fixture-setting'] ) );

				assert.equal( 'additionalValue', changesetData.additionalSetting.value );
				assert.equal( 'requestChangesetUpdate', changesetData['fixture-setting'].value );

				// Prevent Ajax request from completing.
				return false;
			}
		} );

		wp.customize.each( function( setting ) {
			setting._dirty = false;
		} );

		request = wp.customize.requestChangesetUpdate();
		assert.equal( 'resolved', request.state());
		request.done( function( data ) {
			assert.ok( _.isEqual( {}, data ) );
		} );

		wp.customize( 'fixture-setting' ).set( 'requestChangesetUpdate' );

		request = wp.customize.requestChangesetUpdate( {
			additionalSetting: {
				value: 'additionalValue'
			}
		} );

		request.always( function( data ) {
			assert.equal( 'canceled', data.statusText );
			jQuery.ajaxSetup( { beforeSend: originalBeforeSetup } );
		} );
	} );

	QUnit.module( 'Customize Utils: wp.customize.utils.getRemainingTime()' );
	QUnit.test( 'utils.getRemainingTime calculates time correctly', function( assert ) {
		var datetime = '2599-08-06 12:12:13', timeRemaining, timeRemainingWithDateInstance, timeRemaingingWithTimestamp;

		timeRemaining = wp.customize.utils.getRemainingTime( datetime );
		timeRemainingWithDateInstance = wp.customize.utils.getRemainingTime( new Date( datetime.replace( /-/g, '/' ) ) );
		timeRemaingingWithTimestamp = wp.customize.utils.getRemainingTime( ( new Date( datetime.replace( /-/g, '/' ) ) ).getTime() );

		assert.equal( typeof timeRemaining, 'number', timeRemaining );
		assert.equal( typeof timeRemainingWithDateInstance, 'number', timeRemaining );
		assert.equal( typeof timeRemaingingWithTimestamp, 'number', timeRemaining );
		assert.deepEqual( timeRemaining, timeRemainingWithDateInstance );
		assert.deepEqual( timeRemaining, timeRemaingingWithTimestamp );
	});

	QUnit.module( 'Customize Utils: wp.customize.utils.getCurrentTimestamp()' );
	QUnit.test( 'utils.getCurrentTimestamp returns timestamp', function( assert ) {
		var currentTimeStamp;
		currentTimeStamp = wp.customize.utils.getCurrentTimestamp();
		assert.equal( typeof currentTimeStamp, 'number' );
	});

	QUnit.module( 'Customize Controls: wp.customize.DateTimeControl' );
	QUnit.test( 'Test DateTimeControl creation and its methods', function( assert ) {
		var control, controlId = 'date_time', section, sectionId = 'fixture-section',
			datetime = '2599-08-06 18:12:13', dateTimeArray, dateTimeArrayInampm, timeString,
			day, year, month, minute, meridian, hour;

		section = wp.customize.section( sectionId );

		control = new wp.customize.DateTimeControl( controlId, {
			params: {
				section: section.id,
				type: 'date_time',
				setting: new wp.customize.Value( datetime ),
				includeTime: true,
				content: '<li id="customize-control-' + controlId + '" class="customize-control"></li>'
			}
		} );

		wp.customize.control.add( controlId, control );

		// Test control creations.
		assert.ok( control.templateSelector, '#customize-control-date_time-content' );
		assert.ok( control.section(), sectionId );
		assert.equal( _.size( control.inputElements ), control.elements.length );
		assert.ok( control.setting(), datetime );

		day = control.inputElements.day;
		month = control.inputElements.month;
		year = control.inputElements.year;
		minute = control.inputElements.minute;
		hour = control.inputElements.hour;
		meridian = control.inputElements.meridian;

		year( '23' );
		assert.ok( control.invalidDate );

		year( '2100' );
		month( '8' );
		assert.ok( ! control.invalidDate );
		day( 'test' );
		assert.ok( control.invalidDate );
		day( '3' );
		assert.ok( ! control.invalidDate );

		// Test control.parseDateTime().
		control.params.twelveHourFormat = false;
		dateTimeArray = control.parseDateTime( datetime );
		assert.deepEqual( dateTimeArray, {
			year: '2599',
			month: '08',
			hour: '18',
			minute: '12',
			second: '13',
			day: '06'
		} );

		control.params.twelveHourFormat = true;
		dateTimeArrayInampm = control.parseDateTime( datetime );
		assert.deepEqual( dateTimeArrayInampm, {
			year: '2599',
			month: '08',
			hour: '6',
			minute: '12',
			meridian: 'pm',
			day: '06'
		} );

		year( '2010' );
		month( '12' );
		day( '18' );
		hour( '3' );
		minute( '44' );
		meridian( 'am' );

		// Test control.convertInputDateToString().
		timeString = control.convertInputDateToString();
		assert.equal( timeString, '2010-12-18 03:44:00' );

		meridian( 'pm' );
		timeString = control.convertInputDateToString();
		assert.equal( timeString, '2010-12-18 15:44:00' );

		control.params.includeTime = false;
		timeString = control.convertInputDateToString();
		assert.equal( timeString, '2010-12-18' );
		control.params.includeTime = true;

		// Test control.updateDaysForMonth().
		year( 2017 );
		month( 2 );
		day( 28 );
		assert.ok( ! control.invalidDate );
		day( 31 );
		assert.ok( control.invalidDate );

		day( 20 );
		assert.equal( day(), 20, 'Should not update if its less the correct number of days' );

		// Test control.convertHourToTwentyFourHourFormat().
		assert.equal( control.convertHourToTwentyFourHourFormat( 11, 'pm' ), 23 );
		assert.equal( control.convertHourToTwentyFourHourFormat( 12, 'pm' ), 12 );
		assert.equal( control.convertHourToTwentyFourHourFormat( 12, 'am' ), 0 );
		assert.equal( control.convertHourToTwentyFourHourFormat( 11, 'am' ), 11 );

		// Test control.toggleFutureDateNotification().
		assert.deepEqual( control.toggleFutureDateNotification(), control );
		control.toggleFutureDateNotification( true );
		assert.ok( control.notifications.has( 'not_future_date' ) );
		control.toggleFutureDateNotification( false );
		assert.notOk( control.notifications.has( 'not_future_date' ) );

		// Test control.populateDateInputs().
		control.setting._value = '2000-12-30 12:34:56';
		control.populateDateInputs();
		assert.equal( '2000', control.inputElements.year.get() );
		assert.equal( '12', control.inputElements.month.get() );
		assert.equal( '30', control.inputElements.day.get() );
		assert.equal( '12', control.inputElements.hour.get() );
		assert.equal( '34', control.inputElements.minute.get() );
		assert.equal( 'pm', control.inputElements.meridian.get() );

		// Test control.validateInputs().
		hour( 33 );
		assert.ok( control.validateInputs() );
		hour( 10 );
		assert.notOk( control.validateInputs() );
		minute( 123 );
		assert.ok( control.validateInputs() );
		minute( 20 );
		assert.notOk( control.validateInputs() );

		// Test control.populateSetting().
		day( 2 );
		month( 11 );
		year( 2018 );
		hour( 4 );
		minute( 20 );
		meridian( 'pm' );
		control.populateSetting();
		assert.equal( control.setting(), '2018-11-02 16:20:00' );

		hour( 123 );
		control.populateSetting();
		assert.equal( control.setting(), '2018-11-02 16:20:00' ); // Should not update if invalid hour.

		hour( 5 );
		control.populateSetting();
		assert.equal( control.setting(), '2018-11-02 17:20:00' );

		// Test control.isFutureDate().
		day( 2 );
		month( 11 );
		year( 2318 );
		hour( 4 );
		minute( 20 );
		meridian( 'pm' );
		assert.ok( control.isFutureDate() );

		year( 2016 );
		assert.notOk( control.isFutureDate() );

		// Tear down.
		wp.customize.control.remove( controlId );
	});

	QUnit.module( 'Customize Sections: wp.customize.OuterSection' );
	QUnit.test( 'Test OuterSection', function( assert ) {
		var section, sectionId = 'test_outer_section', body = jQuery( 'body' ),
			defaultSection, defaultSectionId = 'fixture-section';

		defaultSection = wp.customize.section( defaultSectionId );

		section = new wp.customize.OuterSection( sectionId, {
			params: {
				content: defaultSection.params.content,
				type: 'outer'
			}
		} );

		wp.customize.section.add( sectionId, section );
		wp.customize.section.add( defaultSectionId, section );

		assert.equal( section.containerPaneParent, '.customize-outer-pane-parent' );
		assert.equal( section.containerParent.selector, '#customize-outer-theme-controls' );

		defaultSection.expand();
		section.expand();
		assert.ok( body.hasClass( 'outer-section-open' ) );
		assert.ok( section.container.hasClass( 'open' ) );
		assert.ok( defaultSection.expanded() ); // Ensure it does not affect other sections state.

		section.collapse();
		assert.notOk( body.hasClass( 'outer-section-open' ) );
		assert.notOk( section.container.hasClass( 'open' ) ); // Ensure it does not affect other sections state.
		assert.ok( defaultSection.expanded() );

		// Tear down.
		wp.customize.section.remove( sectionId );
	});

	QUnit.module( 'Customize Controls: PreviewLinkControl' );
	QUnit.test( 'Test PreviewLinkControl creation and its methods', function( assert ) {
		var section, sectionId = 'publish_settings', newLink;

		section = wp.customize.section( sectionId );
		section.deferred.embedded.resolve();

		assert.expect( 9 );
		section.deferred.embedded.done( function() {
			_.each( section.controls(), function( control ) {
				if ( 'changeset_preview_link' === control.id ) {
					assert.equal( control.templateSelector, 'customize-preview-link-control' );
					assert.equal( _.size( control.previewElements ), control.elements.length );

					// Test control.ready().
					newLink = 'http://example.org?' + wp.customize.settings.changeset.uuid;
					control.setting.set( newLink );

					assert.equal( control.previewElements.input(), newLink );
					assert.equal( control.previewElements.url(), newLink );
					assert.equal( control.previewElements.url.element.parent().attr( 'href' ), newLink );
					assert.equal( control.previewElements.url.element.parent().attr( 'target' ), wp.customize.settings.changeset.uuid );

					// Test control.toggleSaveNotification().
					control.toggleSaveNotification( true );
					assert.ok( control.notifications.has( 'changes_not_saved' ) );
					control.toggleSaveNotification( false );
					assert.notOk( control.notifications.has( 'changes_not_saved' ) );

					// Test control.updatePreviewLink().
					control.updatePreviewLink();
					assert.equal( control.setting.get(), wp.customize.previewer.getFrontendPreviewUrl() );
				}
			} );
		} );
	});
});
