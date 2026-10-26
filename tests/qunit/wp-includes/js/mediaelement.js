/* global wp, mejs, MediaElementPlayer, jQuery */

QUnit.module( 'mediaelement' );

QUnit.test( 'library exposes the globals WordPress depends on', function( assert ) {
	assert.strictEqual( typeof mejs, 'object', 'mejs global exists' );
	assert.ok( /^\d+\.\d+\.\d+/.test( mejs.version ), 'mejs.version is a semantic version: ' + mejs.version );
	assert.strictEqual( typeof MediaElementPlayer, 'function', 'MediaElementPlayer global exists' );
	assert.strictEqual( typeof mejs.MediaElementPlayer, 'function', 'mejs.MediaElementPlayer exists' );
	assert.strictEqual( typeof mejs.MediaElement, 'function', 'mejs.MediaElement exists' );
	assert.strictEqual( typeof mejs.players, 'object', 'mejs.players registry exists (used by media-audiovideo and mce-view)' );
	assert.strictEqual( typeof mejs.i18n, 'object', 'mejs.i18n exists' );
	assert.strictEqual( typeof mejs.Utils, 'object', 'mejs.Utils exists' );
	assert.strictEqual( typeof mejs.Utils.getTypeFromFile, 'function', 'mejs.Utils.getTypeFromFile exists' );
	assert.strictEqual( typeof mejs.Features, 'object', 'mejs.Features exists' );
} );

QUnit.test( 'mejs.i18n consumes the mejsL10n global localized by WordPress', function( assert ) {
	// index.html defines window.mejsL10n before the library loads.
	assert.strictEqual( typeof mejs.i18n.t, 'function', 'translation function exists' );
	assert.strictEqual( typeof mejs.i18n.language, 'function', 'language setter exists' );
	assert.strictEqual( typeof mejs.i18n.t( 'mejs.play' ), 'string', 'translating a key returns a string' );
} );

QUnit.test( 'jQuery integration is available', function( assert ) {
	assert.strictEqual( typeof jQuery.fn.mediaelementplayer, 'function', 'jQuery.fn.mediaelementplayer plugin exists (used by wp-mediaelement)' );
} );

QUnit.test( 'mediaelement-migrate back-compat shims are in place', function( assert ) {
	assert.strictEqual( mejs.MediaFeatures, mejs.Features, 'mejs.MediaFeatures aliases mejs.Features' );
	assert.strictEqual( mejs.Utility, mejs.Utils, 'mejs.Utility aliases mejs.Utils' );
	assert.strictEqual( typeof mejs.plugins, 'object', 'mejs.plugins shim exists' );
	assert.strictEqual(
		mejs.HtmlMediaElementShim.getTypeFromFile,
		mejs.Utils.getTypeFromFile,
		'mejs.HtmlMediaElementShim.getTypeFromFile aliases mejs.Utils.getTypeFromFile'
	);
} );

QUnit.test( 'wp.mediaelement wrapper is available', function( assert ) {
	assert.strictEqual( typeof wp.mediaelement, 'object', 'wp.mediaelement exists' );
	assert.strictEqual( typeof wp.mediaelement.initialize, 'function', 'wp.mediaelement.initialize exists' );
} );

QUnit.test( 'MediaElementPlayer methods used by WordPress exist', function( assert ) {
	var proto = MediaElementPlayer.prototype;

	// Used by media-audiovideo.js removePlayer() and wp-playlist.js.
	assert.strictEqual( typeof proto.remove, 'function', 'player.remove exists' );
	assert.strictEqual( typeof proto.pause, 'function', 'player.pause exists' );
	assert.strictEqual( typeof proto.globalBind, 'function', 'player.globalBind exists' );
	assert.strictEqual( typeof proto.globalUnbind, 'function', 'player.globalUnbind exists' );
	// Overridden by mediaelement-migrate.js for jQuery back-compat.
	assert.strictEqual( typeof proto.getElement, 'function', 'player.getElement exists' );
	assert.strictEqual( typeof proto.buildfeatures, 'function', 'player.buildfeatures exists' );
	assert.strictEqual( typeof proto._meReady, 'function', 'player._meReady exists' );
} );

QUnit.test( 'instantiating a player builds controls from the icon sprite', function( assert ) {
	var iconSprite = '/wp-includes/js/mediaelement/mejs-controls.svg',
		video = document.createElement( 'video' ),
		container, playButton, useElement, player;

	video.setAttribute( 'src', '/relative/test.mp4' );
	video.setAttribute( 'type', 'video/mp4' );
	video.setAttribute( 'width', '400' );
	video.setAttribute( 'height', '300' );
	document.getElementById( 'qunit-fixture' ).appendChild( video );

	player = new MediaElementPlayer( video, {
		iconSprite: iconSprite,
		classPrefix: 'mejs-',
		features: [ 'playpause', 'progress', 'volume' ]
	} );

	container = document.getElementById( 'qunit-fixture' ).querySelector( '.mejs-container' );
	assert.ok( container, 'player container was created' );

	playButton = container.querySelector( '.mejs-playpause-button' );
	assert.ok( playButton, 'play/pause button was created' );

	useElement = playButton.querySelector( 'svg use' );
	assert.ok( useElement, 'play button uses an SVG sprite icon' );
	assert.strictEqual(
		useElement.getAttribute( 'xlink:href' ).indexOf( iconSprite + '#' ),
		0,
		'icon references the iconSprite path passed via settings'
	);

	player.remove();
	assert.strictEqual( typeof mejs.players[ player.id ], 'undefined', 'player.remove() unregisters the player' );
} );

QUnit.test( 'a fluid-width player initializes in responsive mode', function( assert ) {
	// Playlists and themes render players with percentage widths, which sends
	// MediaElement.js 7.x through setResponsiveMode() where it calls native DOM
	// methods on player.container. This guards against back-compat shims
	// converting those properties to jQuery objects.
	var audio = document.createElement( 'audio' ),
		successCalled = false,
		player;

	audio.setAttribute( 'src', '/relative/test.mp3' );
	audio.setAttribute( 'type', 'audio/mp3' );
	audio.style.width = '100%';
	document.getElementById( 'qunit-fixture' ).appendChild( audio );

	player = new MediaElementPlayer( audio, {
		iconSprite: '/wp-includes/js/mediaelement/mejs-controls.svg',
		classPrefix: 'mejs-',
		features: [ 'playpause', 'progress', 'volume' ],
		success: function() {
			successCalled = true;
		}
	} );

	assert.ok( successCalled, 'the success callback fired (player.container survived responsive sizing)' );
	assert.ok( player.container instanceof window.HTMLElement, 'player.container is an HTML element' );
	assert.ok( player.$container instanceof jQuery, 'player.$container provides the jQuery wrapper for back compat' );

	player.remove();
} );
