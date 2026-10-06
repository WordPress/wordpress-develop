/**
 * The teletype. See #65907.
 *
 * @output wp-admin/js/teletype.js
 */

/**
 * IIFE.
 *
 * @param {Object} wp       The global WordPress JS object.
 * @param {Object} settings The settings object.
 */
( function ( wp, settings ) {
	'use strict';

	var TYPE_SPEED  = 100,  // Milliseconds per character.
		LINE_PAUSE  = 2000, // Between lines.
		START_DELAY = 3000, // Before the first line.
		FADE_TIME   = 3000, // Cursor fade-in, once the lights go out.
		ACT_PAUSE   = 4000, // Between the two acts, and before the exit.
		RAIN_SIZE   = 16,   // Glyph size of the falling rain, in pixels.
		RAIN_SPEED  = 60;   // Milliseconds between rain rows.

	// Half-width katakana and digits.
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
	 * Maps a string through the character tables.
	 *
	 * @param {string} value Text to map.
	 * @return {string} Mapped text.
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
		'.wp-teletype { --wp-teletype-transition-duration: ' + FADE_TIME / 1000 + 's }',
		settings.styles,
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

		/**
		 * Recalculate canvas size on resize event.
		 */
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

		/**
		 * Draw the rain.
		 */
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

		/**
		 * Rain animation frame tick event.
		 *
		 * @param {DOMHighResTimeStamp} now Event timestamp.
		 */
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
	 * @param {string} displayName  The current user's display name.
	 * @param {Object} [i18n]       Translated interface strings.
	 * @param {string} [i18n.label] Accessible name of the dialog.
	 * @param {string} [i18n.exit]  Text of the Exit button.
	 */
	function run( displayName, i18n ) {
		var aborted          = false,
			timer            = null,
			stopRain         = null,
			previousFocus    = document.activeElement,
			previousOverflow = document.documentElement.style.overflow;

		i18n = i18n || {};

		var style = document.createElement( 'style' );
		style.textContent = STYLE;

		/*
		 * The scene is a modal dialog so that it is reachable and escapable rather than
		 * something that happens silently over the top of an admin page.
		 */
		var overlay = document.createElement( 'div' );
		overlay.className = 'wp-teletype';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', i18n.label || 'Press Escape to leave.' );
		overlay.setAttribute( 'tabindex', '-1' );

		// Typed a character at a time, so it reaches the accessibility tree as whole
		// lines through the live region below instead.
		var line = document.createElement( 'p' );
		line.setAttribute( 'aria-hidden', 'true' );
		line.setAttribute( 'lang', 'en' );

		// The dialogue is English whatever the profile language, so it says so. The
		// dialog label and Exit button are translated and inherit the page language.
		var narration = document.createElement( 'div' );
		narration.className = 'narration';
		narration.setAttribute( 'lang', 'en' );
		narration.setAttribute( 'aria-live', 'polite' );
		narration.setAttribute( 'aria-atomic', 'true' );

		var exit = document.createElement( 'button' );
		exit.type = 'button';
		exit.className = 'exit';
		exit.textContent = i18n.exit || 'Exit';

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

		/**
		 * setTimout helper.
		 *
		 * @param {number}   ms   Milliseconds to wait.
		 * @param {callback} next Callback function.
		 */
		function wait( ms, next ) {
			timer = window.setTimeout( function () {
				if ( ! aborted ) {
					next();
				}
			}, ms );
		}

		/**
		 * Clear the first line.
		 */
		function clear() {
			while ( line.firstChild !== cursor ) {
				line.removeChild( line.firstChild );
			}
		}

		/**
		 * Add a new line.
		 */
		function newline() {
			line.insertBefore( document.createElement( 'br' ), cursor );
		}

		/**
		 * Types one line, a character at a time, ahead of the cursor.
		 *
		 * @param {string}   text String to type.
		 * @param {callback} done Event callback at completion.
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
		 *
		 * @param {number} index Line index to type.
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

			// Describe the rain once for screen readers.
			say( 'The screen goes black. Green code rains down it.' );

			wait( ACT_PAUSE, function () {
				actTwo( 0 );
			} );
		}

		/**
		 * Act two: one line at a time, cleared between each. The last line holds, then
		 * the scene takes itself down and gives the admin page back.
		 *
		 * @param {number} index Line index to type.
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

		/**
		 * End the easter egg.
		 */
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

		/**
		 * Keydown event listener while easter egg running.
		 *
		 * @param {KeyboardEvent} event Fired keyboard event.
		 */
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
}( window.wp = window.wp || {}, window.wpTeletype ) );
