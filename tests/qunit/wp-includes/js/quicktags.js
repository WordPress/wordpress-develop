/* global QTags, quicktags */

jQuery( function() {
	var editors, originalButtons, originalDir, buttonsInitDone;

	function createEditor( settings, clock ) {
		var id = 'quicktags-test-' + editors.length, editor;
		jQuery( '<textarea>', { id: id } ).appendTo( '#qunit-fixture' );
		editor = quicktags( jQuery.extend( { id: id }, settings ) );
		editors.push( editor );
		clock.tick( 0 );
		return editor;
	}

	QUnit.module( 'quicktags', {
		beforeEach: function() {
			editors = [];
			originalButtons = window.edButtons;
			window.edButtons = originalButtons.slice();
			buttonsInitDone = QTags.buttonsInitDone;
			originalDir = document.documentElement.getAttribute( 'dir' );
			document.documentElement.dir = 'ltr';
		},
		afterEach: function() {
			editors.forEach( function( editor ) { editor.remove(); } );
			window.edButtons = originalButtons;
			QTags.buttonsInitDone = buttonsInitDone;
			if ( originalDir === null ) {
				document.documentElement.removeAttribute( 'dir' );
			} else {
				document.documentElement.setAttribute( 'dir', originalDir );
			}
		}
	} );

	QUnit.test( 'honors an empty default-button list', function( assert ) {
		var editor = createEditor( { buttons: '' }, this.clock );
		assert.strictEqual( editor.toolbar.querySelectorAll( '.ed_button' ).length, 0, 'No default buttons are added' );
	} );

	QUnit.test( 'keeps the defaults for omitted and other falsey settings', function( assert ) {
		for ( const settings of [ {}, { buttons: undefined }, { buttons: false }, { buttons: null }, { buttons: 0 } ] ) {
			var editor = createEditor( settings, this.clock );
			assert.ok( editor.getButtonElement( 'strong' ), 'Keeps defaults for ' + String( settings.buttons ) );
		}
	} );

	QUnit.test( 'honors a nonempty default-button list', function( assert ) {
		var editor = createEditor( { buttons: 'strong,em' }, this.clock );
		assert.ok( editor.getButtonElement( 'strong' ) );
		assert.ok( editor.getButtonElement( 'em' ) );
		assert.strictEqual( editor.toolbar.querySelectorAll( '.ed_button' ).length, 2 );
	} );

	QUnit.test( 'preserves custom buttons and each list when adding a button after initialization', function( assert ) {
		var empty = createEditor( { buttons: '' }, this.clock );
		var defaults = createEditor( {}, this.clock );
		QTags.addButton( 'quicktags_test_custom', 'Custom', '<custom>', '</custom>' );
		assert.ok( empty.getButtonElement( 'quicktags_test_custom' ), 'Custom buttons remain available' );
		assert.notOk( empty.getButtonElement( 'strong' ), 'Refreshing keeps the empty default list' );
		assert.ok( defaults.getButtonElement( 'quicktags_test_custom' ) );
		assert.ok( defaults.getButtonElement( 'strong' ), 'Other instances keep their defaults' );
	} );

	QUnit.test( 'preserves the separate RTL direction control', function( assert ) {
		document.documentElement.dir = 'rtl';
		var editor = createEditor( { buttons: '' }, this.clock );
		assert.ok( editor.getButtonElement( 'textdirection' ) );
		assert.notOk( editor.getButtonElement( 'strong' ) );
	} );
} );
