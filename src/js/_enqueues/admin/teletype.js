/**
 * The WordPress admin easter egg.
 *
 * Originally written by Matt Mullenweg and committed in r8306 (July 10, 2008) as
 * wp-admin/js/revisions-js.php, under the cover of "Javascriptify revision selections to
 * deal elegantly while comparing." Trunk was 2.6-beta3 at the time, so it first shipped
 * in WordPress 2.6 and survived through 3.5.x. wp-admin/revision.php included it when a
 * post revision was compared to itself. Removed in r24820 (see #24852) for 3.6 because
 * that revisions UI was rewritten and the trigger disappeared with it, not because the
 * egg was unwanted: "There will be opportunities for other easter eggs. This one has had
 * its decade." Restored here with a new home behind the command palette.
 *
 * The original shipped as a Dean Edwards packed blob whose dialogue was additionally run
 * through the Dvorak/QWERTY substitution cipher in dvortr(). #15262 raised that packed
 * code is not GPL source, and r16826 answered it with a comment pointing at the ticket
 * rather than with readable code. The packing is dropped here: all of the logic ships as
 * ordinary source. The cipher is kept, on the dialogue only, so the payoff does not turn
 * up in a plain-text search of wp-admin/js. It hides the lines from a grep, not from a
 * reader, and the decoder sits a few lines below. This file is also only fetched once the
 * egg has already been invoked, so it never loads on a normal admin screen.
 *
 * Timings, dialogue, and staging are faithful to the original.
 *
 * @output wp-admin/js/teletype.js
 */

( function ( wp ) {
	'use strict';

	var TYPE_SPEED  = 100,  // Milliseconds per character.
		LINE_PAUSE  = 2000, // Between lines.
		START_DELAY = 3000, // Before the first line.
		FADE_TIME   = 3000, // Cursor fade-in, once the lights go out.
		ACT_PAUSE   = 4000; // Between the two acts.

	/*
	 * The dialogue is stored in its Dvorak/QWERTY-substituted form and run back through
	 * dvortr() as each line is typed, exactly as the 2008 original stored it. The point is
	 * to keep the payoff out of a plain-text search of wp-admin/js, not to hide anything
	 * from anyone reading this file: the cipher is right here, and every line of logic
	 * around it is ordinary source.
	 */
	var ACT_ONE = [
		'O.nu[jrmlapcorb e.y.jy.ev',
		'Cbcycaycbi cbucbcy. nrrl .ojd.,an lpryrjrnv',
		'O.nu e.oypgjy cbvvv 3',
		'2',
		'1'
	];

	// %s is replaced with the current user's display name.
	var ACT_TWO = [
		'<at. glw %ovvv',
		'Yd. Maypcq dao frgvvv',
		'Urnnr, yd. ,dcy. paxxcyv'
	];

	// Left on screen at the end, as the original's noscript fallback did.
	var CLOSING = 'Erb-y n.y ydco dall.b aiacbv';

	var FROM = '\',.pyfgcrl/=\\aoeuidhtns-;qjkxbmwvz"<>PYFGCRL?+|AOEUIDHTNS_:QJKXBMWVZ[]',
		TO   = 'qwertyuiop[]\\asdfghjkl;\'zxcvbnm,./QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?-=';

	/**
	 * Applies the Dvorak/QWERTY substitution cipher.
	 *
	 * @param {string} value Text to substitute.
	 * @return {string} Substituted text.
	 */
	function dvortr( value ) {
		var map = {},
			i;

		for ( i = 0; i < FROM.length; i++ ) {
			map[ FROM.charAt( i ) ] = TO.charAt( i );
		}

		return value.replace( /[\s\S]/g, function ( character ) {
			return map[ character ] || character;
		} );
	}

	var STYLE = [
		'.wp-teletype{position:fixed;inset:0;z-index:2147483647;margin:0;padding:2.5em;',
			'font-family:courier,monospace;font-size:16px;line-height:1.6;',
			'background:#fff;color:#000;overflow:hidden;',
			'transition:background-color .6s linear,color .6s linear}',
		'.wp-teletype.is-dark{background:#000;color:#0f0}',
		'.wp-teletype p{margin:0;white-space:pre-wrap}',
		'.wp-teletype .cursor{opacity:0;transition:opacity ' + ( FADE_TIME / 1000 ) + 's linear}',
		'.wp-teletype .cursor.is-visible{opacity:1;animation:wp-teletype-blink 1s step-end infinite}',
		'@keyframes wp-teletype-blink{50%{opacity:0}}',
		'@media (prefers-reduced-motion:reduce){',
			'.wp-teletype .cursor.is-visible{animation:none}',
			'.wp-teletype,.wp-teletype .cursor{transition:none}}'
	].join( '' );

	/**
	 * Plays the scene.
	 *
	 * @param {string} displayName The current user's display name.
	 */
	function run( displayName ) {
		var aborted          = false,
			timer            = null,
			previousOverflow = document.documentElement.style.overflow;

		var style = document.createElement( 'style' );
		style.textContent = STYLE;

		var overlay = document.createElement( 'div' );
		overlay.className = 'wp-teletype';
		overlay.setAttribute( 'aria-hidden', 'true' );

		var line = document.createElement( 'p' );

		var cursor = document.createElement( 'span' );
		cursor.className = 'cursor';
		cursor.textContent = '▌';

		line.appendChild( cursor );
		overlay.appendChild( line );

		function wait( ms, next ) {
			timer = window.setTimeout( function () {
				if ( ! aborted ) {
					next();
				}
			}, ms );
		}

		function clear() {
			while ( line.firstChild !== cursor ) {
				line.removeChild( line.firstChild );
			}
		}

		function newline() {
			line.insertBefore( document.createElement( 'br' ), cursor );
		}

		/**
		 * Types one line, a character at a time, ahead of the cursor.
		 */
		function type( text, done ) {
			var chars = text.split( '' );

			( function next() {
				if ( aborted ) {
					return;
				}

				if ( ! chars.length ) {
					done();
					return;
				}

				line.insertBefore( document.createTextNode( chars.shift() ), cursor );
				wait( TYPE_SPEED, next );
			}() );
		}

		/**
		 * Act one: typed on the lights-on screen, each line under the last.
		 */
		function actOne( index ) {
			if ( index >= ACT_ONE.length ) {
				lightsOut();
				return;
			}

			type( dvortr( ACT_ONE[ index ] ), function () {
				newline();
				wait( LINE_PAUSE, function () {
					actOne( index + 1 );
				} );
			} );
		}

		/**
		 * The turn: screen to black, text to green, cursor fades up alone.
		 */
		function lightsOut() {
			overlay.className = 'wp-teletype is-dark';
			clear();
			cursor.className = 'cursor is-visible';
			wait( ACT_PAUSE, function () {
				actTwo( 0 );
			} );
		}

		/**
		 * Act two: one line at a time, cleared between each.
		 */
		function actTwo( index ) {
			if ( index >= ACT_TWO.length ) {
				type( dvortr( CLOSING ), function () {
					cursor.className = 'cursor';
				} );
				return;
			}

			type( dvortr( ACT_TWO[ index ] ).replace( '%s', function () {
				return displayName;
			} ), function () {
				wait( LINE_PAUSE, function () {
					clear();
					actTwo( index + 1 );
				} );
			} );
		}

		function abort( event ) {
			if ( event && 'keydown' === event.type && 'Escape' !== event.key ) {
				return;
			}

			aborted = true;
			window.clearTimeout( timer );
			document.removeEventListener( 'keydown', abort );
			document.documentElement.style.overflow = previousOverflow;
			overlay.remove();
			style.remove();
		}

		document.addEventListener( 'keydown', abort );
		overlay.addEventListener( 'click', abort );

		document.head.appendChild( style );
		document.body.appendChild( overlay );
		document.documentElement.style.overflow = 'hidden';

		wait( START_DELAY, function () {
			actOne( 0 );
		} );
	}

	wp.teletype = { run: run };
}( window.wp = window.wp || {} ) );
