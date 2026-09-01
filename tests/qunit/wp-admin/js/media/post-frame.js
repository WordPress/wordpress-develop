/* global wp */
/* jshint qunit: true */
/* eslint-env qunit */

( function() {
	'use strict';

	QUnit.module( 'Media Frame Post' );

	QUnit.test( 'closing an abandoned gallery clears transient gallery state', function( assert ) {
		var frame = wp.media({
				frame: 'post',
				state: 'gallery',
				multiple: true
			}),
			gallery = frame.state( 'gallery' ),
			add = frame.state( 'gallery-library' ),
			edit = frame.state( 'gallery-edit' ),
			attachments = [
				wp.media.model.Attachment.create( { id: 5748901, type: 'image' } ),
				wp.media.model.Attachment.create( { id: 5748902, type: 'image' } )
			];

		gallery.get( 'selection' ).add( attachments );
		add.get( 'selection' ).add( attachments );
		edit.get( 'library' ).add( attachments );

		frame.setState( 'gallery-edit' );
		frame.trigger( 'close' );

		assert.equal( edit.get( 'library' ).length, 0, 'The abandoned gallery selection is cleared.' );
		assert.equal( gallery.get( 'selection' ).length, 0, 'The create-gallery selection is cleared.' );
		assert.equal( add.get( 'selection' ).length, 0, 'The add-to-gallery selection is cleared.' );

		frame.dispose();
	} );
}() );
