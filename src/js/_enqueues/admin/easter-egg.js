/**
 * The WordPress admin easter egg.
 *
 * Originally written by Matt Mullenweg and shipped in WordPress 3.6 as
 * wp-admin/js/revisions-js.php, where it triggered on comparing a post revision to
 * itself. Removed in r24820 (see #24852) because that revisions UI was rewritten and
 * the trigger disappeared with it, not because the egg was unwanted. Restored here
 * with a new home behind the command palette.
 *
 * The 2013 original shipped as a Dean Edwards packed blob with its dialogue run
 * through the Dvorak/QWERTY substitution cipher in dvortr(). Both are unwound here:
 * obfuscated code is not GPL source (see #15262), and the mystery is meant to live in
 * the trigger, not in unreadable code. This file is only fetched once the egg has
 * already been invoked, so it never loads on a normal admin screen.
 *
 * Timings, dialogue, and staging are faithful to the original.
 *
 * @output wp-admin/js/easter-egg.js
 */

( function ( wp ) {
	'use strict';

	var TYPE_SPEED  = 100,  // Milliseconds per character.
		LINE_PAUSE  = 2000, // Between lines.
		START_DELAY = 3000, // Before the first line.
		FADE_TIME   = 3000, // Cursor fade-in, once the lights go out.
		ACT_PAUSE   = 4000; // Between the two acts.

	var ACT_ONE = [
		'Self-comparison detected.',
		'Initiating infinite loop eschewal protocol.',
		'Self destruct in... 3',
		'2',
		'1'
	];

	// %s is replaced with the current user's display name.
	var ACT_TWO = [
		'Wake up, %s...',
		'The Matrix has you...',
		'Follow the white rabbit.'
	];

	// Left on screen at the end, as the original's noscript fallback did.
	var CLOSING = "Don't let this happen again.";

	var STYLE = [
		'.wp-easter-egg{position:fixed;inset:0;z-index:2147483647;margin:0;padding:2.5em;',
			'font-family:courier,monospace;font-size:16px;line-height:1.6;',
			'background:#fff;color:#000;overflow:hidden;',
			'transition:background-color .6s linear,color .6s linear}',
		'.wp-easter-egg.is-dark{background:#000;color:#0f0}',
		'.wp-easter-egg p{margin:0;white-space:pre-wrap}',
		'.wp-easter-egg .cursor{opacity:0;transition:opacity ' + ( FADE_TIME / 1000 ) + 's linear}',
		'.wp-easter-egg .cursor.is-visible{opacity:1;animation:wp-easter-egg-blink 1s step-end infinite}',
		'@keyframes wp-easter-egg-blink{50%{opacity:0}}',
		'@media (prefers-reduced-motion:reduce){',
			'.wp-easter-egg .cursor.is-visible{animation:none}',
			'.wp-easter-egg,.wp-easter-egg .cursor{transition:none}}'
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
		overlay.className = 'wp-easter-egg';
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

			type( ACT_ONE[ index ], function () {
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
			overlay.className = 'wp-easter-egg is-dark';
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
				type( CLOSING, function () {
					cursor.className = 'cursor';
				} );
				return;
			}

			type( ACT_TWO[ index ].replace( '%s', displayName ), function () {
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

	wp.easterEgg = { run: run };
}( window.wp = window.wp || {} ) );
