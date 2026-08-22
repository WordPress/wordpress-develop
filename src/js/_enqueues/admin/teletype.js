/**
 * The WordPress admin easter egg.
 *
 * Originally written by Matt Mullenweg for WordPress 2.6 and removed in 3.6 along with
 * the revisions UI that triggered it. Restored here behind the command palette, keeping
 * the timings and staging of the original. See #65907.
 *
 * The dialogue is stored in a Dvorak/QWERTY substitution cipher so that the payoff does
 * not turn up in a plain-text search of wp-admin/js. The decoder is dvortr(), below.
 *
 * @output wp-admin/js/teletype.js
 */

( function ( wp ) {
	'use strict';

	var TYPE_SPEED  = 100,  // Milliseconds per character.
		LINE_PAUSE  = 2000, // Between lines.
		START_DELAY = 3000, // Before the first line.
		FADE_TIME   = 3000, // Cursor fade-in, once the lights go out.
		ACT_PAUSE   = 4000, // Between the two acts, and before the exit.
		RAIN_SIZE   = 16,   // Glyph size of the falling rain, in pixels.
		RAIN_SPEED  = 60;   // Milliseconds between rain rows.

	// Half-width katakana and digits, as the films used.
	var GLYPHS = 'アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン0123456789';

	var ACT_ONE = [
		'O.nu[p.u.p.bj. e.y.jy.ev',
		'Cbcycaycbi cbucbcy. nrrl .ojd.,an lpryrjrnv',
		'Pgbbcbi a prgycb. macby.babj. jfjn.v Xajt cbvvv 3',
		'2',
		'1'
	];

	// %s is replaced with the current user's display name.
	var ACT_TWO = [
		'<at. glw %ovvv',
		'Yd. Maypcq dao frgvvv',
		'Urnnr, yd. ,dcy. paxxcyv'
	];

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
		// Focused only to move the reading position in, never by tabbing to it.
		'.wp-teletype:focus{outline:none}',
		'.wp-teletype p{position:relative;z-index:1;margin:0;white-space:pre-wrap}',
		'.wp-teletype .rain{position:absolute;inset:0;opacity:.5}',
		'.wp-teletype .narration{position:absolute;width:1px;height:1px;margin:-1px;',
			'padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0}',
		'.wp-teletype .exit{position:absolute;z-index:2;top:1.5em;right:1.5em;',
			'padding:.7em 1.8em;border:0;border-radius:999px;background:#2271b1;color:#fff;',
			'font-family:inherit;font-size:14px;line-height:1;cursor:pointer}',
		'.wp-teletype .exit:hover{background:#135e96}',
		'.wp-teletype .exit:focus-visible{outline:2px solid #fff;outline-offset:2px}',
		'.wp-teletype .cursor{opacity:0;transition:opacity ' + ( FADE_TIME / 1000 ) + 's linear}',
		'.wp-teletype .cursor.is-visible{opacity:1;animation:wp-teletype-blink 1s step-end infinite}',
		'@keyframes wp-teletype-blink{50%{opacity:0}}',
		'@media (prefers-reduced-motion:reduce){',
			'.wp-teletype .cursor.is-visible{animation:none}',
			'.wp-teletype,.wp-teletype .cursor{transition:none}}'
	].join( '' );

	/**
	 * Runs the falling glyph rain behind the scene.
	 *
	 * @param {HTMLElement} parent Element to render into.
	 * @return {Function} Stops the rain and removes it.
	 */
	function rain( parent ) {
		var canvas  = document.createElement( 'canvas' ),
			context = canvas.getContext( '2d' ),
			columns = [],
			width   = 0,
			height  = 0,
			frame   = null,
			last    = 0;

		canvas.className = 'rain';
		canvas.setAttribute( 'aria-hidden', 'true' );

		function resize() {
			var ratio = window.devicePixelRatio || 1,
				count,
				i;

			width  = parent.clientWidth;
			height = parent.clientHeight;

			canvas.width        = width * ratio;
			canvas.height       = height * ratio;
			canvas.style.width  = width + 'px';
			canvas.style.height = height + 'px';

			context.setTransform( ratio, 0, 0, ratio, 0, 0 );
			context.font = RAIN_SIZE + 'px courier, monospace';

			count = Math.ceil( width / RAIN_SIZE );

			/*
			 * Columns keep their position across a resize so the rain does not restart,
			 * and any new ones start at a random height so the edge does not arrive as
			 * a straight line.
			 */
			for ( i = columns.length; i < count; i++ ) {
				columns.push( Math.random() * height );
			}

			columns.length = count;
		}

		function draw() {
			var i;

			// Painting over the last frame rather than clearing it leaves the trails.
			context.fillStyle = 'rgba(0,0,0,.08)';
			context.fillRect( 0, 0, width, height );
			context.fillStyle = '#0f0';

			for ( i = 0; i < columns.length; i++ ) {
				context.fillText(
					GLYPHS.charAt( Math.floor( Math.random() * GLYPHS.length ) ),
					i * RAIN_SIZE,
					columns[ i ]
				);

				if ( columns[ i ] > height && Math.random() > 0.975 ) {
					columns[ i ] = 0;
				} else {
					columns[ i ] += RAIN_SIZE;
				}
			}
		}

		function tick( now ) {
			frame = window.requestAnimationFrame( tick );

			if ( now - last < RAIN_SPEED ) {
				return;
			}

			last = now;
			draw();
		}

		resize();
		parent.appendChild( canvas );
		window.addEventListener( 'resize', resize );
		frame = window.requestAnimationFrame( tick );

		return function () {
			window.cancelAnimationFrame( frame );
			window.removeEventListener( 'resize', resize );
			canvas.remove();
		};
	}

	/**
	 * Plays the scene.
	 *
	 * @param {string} displayName The current user's display name.
	 */
	function run( displayName ) {
		var aborted          = false,
			timer            = null,
			stopRain         = null,
			previousFocus    = document.activeElement,
			previousOverflow = document.documentElement.style.overflow;

		var style = document.createElement( 'style' );
		style.textContent = STYLE;

		/*
		 * The scene is a modal dialog so that it is reachable and escapable rather than
		 * something that happens silently over the top of an admin page. The dialogue
		 * is English whatever the profile language, so it says so.
		 */
		var overlay = document.createElement( 'div' );
		overlay.className = 'wp-teletype';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', 'A WordPress easter egg. Press Escape to leave.' );
		overlay.setAttribute( 'lang', 'en' );
		overlay.setAttribute( 'tabindex', '-1' );

		// Typed a character at a time, so it reaches the accessibility tree as whole
		// lines through the live region below instead.
		var line = document.createElement( 'p' );
		line.setAttribute( 'aria-hidden', 'true' );

		var narration = document.createElement( 'div' );
		narration.className = 'narration';
		narration.setAttribute( 'aria-live', 'polite' );
		narration.setAttribute( 'aria-atomic', 'true' );

		var exit = document.createElement( 'button' );
		exit.type = 'button';
		exit.className = 'exit';
		exit.textContent = 'Exit';

		var cursor = document.createElement( 'span' );
		cursor.className = 'cursor';
		cursor.textContent = '▌';

		line.appendChild( cursor );
		overlay.appendChild( line );
		overlay.appendChild( narration );
		overlay.appendChild( exit );

		/**
		 * Hands a whole line to the live region.
		 *
		 * @param {string} text Line to announce.
		 */
		function say( text ) {
			narration.textContent = text;
		}

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

			say( dvortr( ACT_ONE[ index ] ) );
			type( dvortr( ACT_ONE[ index ] ), function () {
				newline();
				wait( LINE_PAUSE, function () {
					actOne( index + 1 );
				} );
			} );
		}

		/**
		 * The turn: screen to black, text to green, rain starts, cursor fades up alone.
		 */
		function lightsOut() {
			overlay.className = 'wp-teletype is-dark';
			clear();
			cursor.className = 'cursor is-visible';

			// The rain is decoration, so it sits out a reduced-motion preference.
			if ( ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				stopRain = rain( overlay );
			}

			// The rain is the whole joke of this act, so describe it once.
			say( 'The screen goes black. Green code rains down it.' );

			wait( ACT_PAUSE, function () {
				actTwo( 0 );
			} );
		}

		/**
		 * Act two: one line at a time, cleared between each. The last line holds, then
		 * the scene takes itself down and gives the admin page back.
		 */
		function actTwo( index ) {
			var text = dvortr( ACT_TWO[ index ] ).replace( '%s', function () {
				return displayName;
			} );

			say( text );
			type( text, function () {
				if ( index + 1 >= ACT_TWO.length ) {
					wait( ACT_PAUSE, abort );
					return;
				}

				wait( LINE_PAUSE, function () {
					clear();
					actTwo( index + 1 );
				} );
			} );
		}

		function abort() {
			if ( aborted ) {
				return;
			}

			aborted = true;
			window.clearTimeout( timer );

			if ( stopRain ) {
				stopRain();
			}

			document.removeEventListener( 'keydown', onKeydown );
			document.documentElement.style.overflow = previousOverflow;
			overlay.remove();
			style.remove();

			if ( previousFocus && previousFocus.focus ) {
				previousFocus.focus();
			}
		}

		function onKeydown( event ) {
			if ( 'Escape' === event.key ) {
				abort();
				return;
			}

			// The scene has one control, so the trap has one stop.
			if ( 'Tab' === event.key ) {
				event.preventDefault();
				exit.focus();
			}
		}

		document.addEventListener( 'keydown', onKeydown );
		overlay.addEventListener( 'click', function () {
			abort();
		} );

		document.head.appendChild( style );
		document.body.appendChild( overlay );
		document.documentElement.style.overflow = 'hidden';
		overlay.focus();

		wait( START_DELAY, function () {
			actOne( 0 );
		} );
	}

	wp.teletype = { run: run };
}( window.wp = window.wp || {} ) );
