/* globals wp, Backbone */
/* jshint qunit: true */
/* eslint-env qunit */

( function() {
	'use strict';

	QUnit.module( 'Media Widgets' );

	QUnit.test( 'namespace', function( assert ) {
		assert.equal( typeof wp.mediaWidgets, 'object', 'wp.mediaWidgets is an object' );
		assert.equal( typeof wp.mediaWidgets.controlConstructors, 'object', 'wp.mediaWidgets.controlConstructors is an object' );
		assert.equal( typeof wp.mediaWidgets.modelConstructors, 'object', 'wp.mediaWidgets.modelConstructors is an object' );
		assert.equal( typeof wp.mediaWidgets.widgetControls, 'object', 'wp.mediaWidgets.widgetControls is an object' );
		assert.equal( typeof wp.mediaWidgets.handleWidgetAdded, 'function', 'wp.mediaWidgets.handleWidgetAdded is an function' );
		assert.equal( typeof wp.mediaWidgets.handleWidgetUpdated, 'function', 'wp.mediaWidgets.handleWidgetUpdated is an function' );
		assert.equal( typeof wp.mediaWidgets.init, 'function', 'wp.mediaWidgets.init is an function' );
	});

	QUnit.test( 'media widget control', function( assert ) {
		assert.equal( typeof wp.mediaWidgets.MediaWidgetControl, 'function', 'wp.mediaWidgets.MediaWidgetControl' );
		assert.ok( wp.mediaWidgets.MediaWidgetControl.prototype instanceof Backbone.View, 'wp.mediaWidgets.MediaWidgetControl subclasses Backbone.View' );
	});

	QUnit.test( 'media widget model', function( assert ) {
		var widgetModelInstance;
		assert.equal( typeof wp.mediaWidgets.MediaWidgetModel, 'function', 'wp.mediaWidgets.MediaWidgetModel is a function' );
		assert.ok( wp.mediaWidgets.MediaWidgetModel.prototype instanceof Backbone.Model, 'wp.mediaWidgets.MediaWidgetModel subclasses Backbone.Model' );

		widgetModelInstance = new wp.mediaWidgets.MediaWidgetModel();
		assert.equal( widgetModelInstance.get( 'title' ), '', 'wp.mediaWidgets.MediaWidgetModel defaults title to empty string' );
		assert.equal( widgetModelInstance.get( 'attachment_id' ), 0, 'wp.mediaWidgets.MediaWidgetModel defaults attachment_id to 0' );
		assert.equal( widgetModelInstance.get( 'url' ), 0, 'wp.mediaWidgets.MediaWidgetModel defaults url to empty string' );

		widgetModelInstance.set({
			title: 'chicken and ribs',
			attachment_id: '1',
			url: 'https://wordpress.org'
		});
		assert.equal( widgetModelInstance.get( 'title' ), 'chicken and ribs', 'wp.mediaWidgets.MediaWidgetModel properly sets the title attribute' );
		assert.equal( widgetModelInstance.get( 'url' ), 'https://wordpress.org', 'wp.mediaWidgets.MediaWidgetModel properly sets the url attribute' );
		assert.equal( widgetModelInstance.get( 'attachment_id' ), 1, 'wp.mediaWidgets.MediaWidgetModel properly sets and casts the attachment_id attribute' );
	});

})();
