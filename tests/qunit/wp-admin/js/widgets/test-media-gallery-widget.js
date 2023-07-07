/* global wp */
/* jshint qunit: true */
/* eslint-env qunit */
/* eslint-disable no-magic-numbers */

( function() {
	'use strict';

	QUnit.module( 'Gallery Media Widget' );

	QUnit.test( 'gallery widget control', function( assert ) {
		var GalleryWidgetControl;
		assert.equal( typeof wp.mediaWidgets.controlConstructors.media_gallery, 'function', 'wp.mediaWidgets.controlConstructors.media_gallery is a function' );
		GalleryWidgetControl = wp.mediaWidgets.controlConstructors.media_gallery;
		assert.ok( GalleryWidgetControl.prototype instanceof wp.mediaWidgets.MediaWidgetControl, 'wp.mediaWidgets.controlConstructors.media_gallery subclasses wp.mediaWidgets.MediaWidgetControl' );
	});

	QUnit.test( 'gallery media model', function( assert ) {
		var GalleryWidgetModel, galleryWidgetModelInstance;
		assert.equal( typeof wp.mediaWidgets.modelConstructors.media_gallery, 'function', 'wp.mediaWidgets.modelConstructors.media_gallery is a function' );
		GalleryWidgetModel = wp.mediaWidgets.modelConstructors.media_gallery;
		assert.ok( GalleryWidgetModel.prototype instanceof wp.mediaWidgets.MediaWidgetModel, 'wp.mediaWidgets.modelConstructors.media_gallery subclasses wp.mediaWidgets.MediaWidgetModel' );

		galleryWidgetModelInstance = new GalleryWidgetModel();
		_.each( galleryWidgetModelInstance.attributes, function( value, key ) {
			assert.equal( value, GalleryWidgetModel.prototype.schema[ key ][ 'default' ], 'Should properly set default for ' + key );
		});
	});

})();
