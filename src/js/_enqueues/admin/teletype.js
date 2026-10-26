/**
 * The teletype. See #65907.
 *
 * @output wp-admin/js/teletype.js
 */

/**
 * Defines wp.teletype, which plays the scene.
 *
 * @param {Object} wp       The global WordPress JS object.
 * @param {Object} settings Settings printed by wp_enqueue_command_palette_assets().
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
		 * Sizes the canvas to its parent and adds or drops columns to fit.
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

			// New columns start at a random height so they do not arrive as a straight line.
			for ( i = columns.length; i < count; i++ ) {
				columns.push( Math.random() * height );
			}

			columns.length = count;
		}

		/**
		 * Draws one row of glyphs and advances each column.
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
		 * Draws a row once every RAIN_SPEED milliseconds.
		 *
		 * @param {DOMHighResTimeStamp} now Current frame time.
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
	 * @param {string}   displayName The current user's display name.
	 * @param {Object}   i18n        Translated strings.
	 * @param {string}   i18n.label  Accessible name of the dialog.
	 * @param {string}   i18n.exit   Text of the Exit button.
	 * @param {string}   i18n.rain   Description of the rain for screen readers.
	 * @param {string[]} i18n.actOne Lines typed on the white screen.
	 * @param {string[]} i18n.actTwo Lines typed on the black screen; %s is the display name.
	 */
	function run( displayName, i18n ) {
		var aborted          = false,
			timer            = null,
			stopRain         = null,
			previousFocus    = document.activeElement,
			previousOverflow = document.documentElement.style.overflow,
			actOneLines      = i18n.actOne.concat( [ '2', '1' ] );

		var style = document.createElement( 'style' );
		style.textContent = STYLE;

		var overlay = document.createElement( 'div' );
		overlay.className = 'wp-teletype';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', i18n.label );
		overlay.setAttribute( 'tabindex', '-1' );

		// Hidden from screen readers, which get whole lines from the live region.
		var line = document.createElement( 'p' );
		line.setAttribute( 'aria-hidden', 'true' );

		var narration = document.createElement( 'div' );
		narration.className = 'narration';
		narration.setAttribute( 'aria-live', 'polite' );
		narration.setAttribute( 'aria-atomic', 'true' );

		var exit = document.createElement( 'button' );
		exit.type = 'button';
		exit.className = 'exit';
		exit.textContent = i18n.exit;

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
		 * Calls a function after a delay unless the scene has ended.
		 *
		 * @param {number}   ms   Milliseconds to wait.
		 * @param {Function} next Function to call.
		 */
		function wait( ms, next ) {
			timer = window.setTimeout( function () {
				if ( ! aborted ) {
					next();
				}
			}, ms );
		}

		/**
		 * Removes the typed text, leaving the cursor.
		 */
		function clear() {
			while ( line.firstChild !== cursor ) {
				line.removeChild( line.firstChild );
			}
		}

		/**
		 * Starts a new line ahead of the cursor.
		 */
		function newline() {
			line.insertBefore( document.createElement( 'br' ), cursor );
		}

		/**
		 * Types one line, a character at a time, ahead of the cursor.
		 *
		 * @param {string}   text Text to type.
		 * @param {Function} done Called once the line is typed.
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
		 * Types act one on the white screen, each line under the last.
		 *
		 * @param {number} index Index of the line to type.
		 */
		function actOne( index ) {
			if ( index >= actOneLines.length ) {
				lightsOut();
				return;
			}

			say( actOneLines[ index ] );
			type( actOneLines[ index ], function () {
				newline();
				wait( LINE_PAUSE, function () {
					actOne( index + 1 );
				} );
			} );
		}

		/**
		 * Switches the screen to black, starts the rain, and fades the cursor in.
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
			say( i18n.rain );

			wait( ACT_PAUSE, function () {
				actTwo( 0 );
			} );
		}

		/**
		 * Types act two one line at a time, then ends the scene after the last line.
		 *
		 * @param {number} index Index of the line to type.
		 */
		function actTwo( index ) {
			var text = i18n.actTwo[ index ].replace( '%s', function () {
				return displayName;
			} );

			say( text );
			type( text, function () {
				if ( index + 1 >= i18n.actTwo.length ) {
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
		 * Ends the scene and restores the page, its scrolling, and focus.
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
		 * Exits on Escape and keeps Tab on the Exit button.
		 *
		 * @param {KeyboardEvent} event The keydown event.
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
