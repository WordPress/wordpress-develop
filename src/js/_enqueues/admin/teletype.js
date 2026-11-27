/**
 * The teletype. See #65907.
 *
 * @output wp-admin/js/teletype.js
 */

/**
 * Defines wp.teletype, which plays the scene.
 *
 * @param {any}                          wp       The global WordPress JS object.
 * @param {WPTeletypeSettings|undefined} settings Settings printed by wp_enqueue_command_palette_assets().
 */
( ( wp, settings ) => {
	'use strict';

	const TYPE_SPEED  = 100,  // Milliseconds per character.
		LINE_PAUSE  = 2000, // Between lines.
		START_DELAY = 3000, // Before the first line.
		FADE_TIME   = 3000, // Cursor fade-in, once the lights go out.
		ACT_PAUSE   = 4000, // Between the two acts, and before the exit.
		RAIN_SIZE   = 16,   // Glyph size of the falling rain, in pixels.
		RAIN_SPEED  = 60;   // Milliseconds between rain rows.

	// Half-width katakana and digits.
	const GLYPHS = 'アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン0123456789';

	/**
	 * Runs the falling glyph rain behind the scene.
	 *
	 * @param {HTMLElement} parent Element to render into.
	 * @return {() => void} Stops the rain and removes it.
	 */
	const rain = ( parent ) => {
		const canvas  = document.createElement( 'canvas' );
		const context = canvas.getContext( '2d' );

		/** @type {number[]} */
		const columns = [];
		let width     = 0;
		let height    = 0;
		let frame     = 0;
		let last      = 0;

		canvas.className = 'rain';
		canvas.setAttribute( 'aria-hidden', 'true' );

		/**
		 * Sizes the canvas to its parent and adds or drops columns to fit.
		 */
		const resize = () => {
			const ratio = window.devicePixelRatio || 1;

			width  = parent.clientWidth;
			height = parent.clientHeight;

			canvas.width        = width * ratio;
			canvas.height       = height * ratio;
			canvas.style.width  = `${ width }px`;
			canvas.style.height = `${ height }px`;

			if ( context ) {
				context.setTransform( ratio, 0, 0, ratio, 0, 0 );
				context.font = `${ RAIN_SIZE }px courier, monospace`;
			}

			const count = Math.ceil( width / RAIN_SIZE );

			// New columns start at a random height so they do not arrive as a straight line.
			while ( columns.length < count ) {
				columns.push( Math.random() * height );
			}

			columns.length = count;
		};

		/**
		 * Draws one row of glyphs and advances each column.
		 */
		const draw = () => {
			if ( ! context ) {
				return;
			}

			// Painting over the last frame rather than clearing it leaves the trails.
			context.fillStyle = 'rgba(0,0,0,.08)';
			context.fillRect( 0, 0, width, height );
			context.fillStyle = '#0f0';

			columns.forEach( ( y, i ) => {
				context.fillText(
					GLYPHS.charAt( Math.floor( Math.random() * GLYPHS.length ) ),
					i * RAIN_SIZE,
					y
				);

				columns[ i ] = y > height && Math.random() > 0.975 ? 0 : y + RAIN_SIZE;
			} );
		};

		/**
		 * Draws a row once every RAIN_SPEED milliseconds.
		 *
		 * @param {DOMHighResTimeStamp} now Current frame time.
		 */
		const tick = ( now ) => {
			frame = window.requestAnimationFrame( tick );

			if ( now - last < RAIN_SPEED ) {
				return;
			}

			last = now;
			draw();
		};

		resize();
		parent.appendChild( canvas );
		window.addEventListener( 'resize', resize );
		frame = window.requestAnimationFrame( tick );

		return () => {
			window.cancelAnimationFrame( frame );
			window.removeEventListener( 'resize', resize );
			canvas.remove();
		};
	};

	/**
	 * Plays the scene.
	 *
	 * @param {string}         displayName The current user's display name.
	 * @param {WPTeletypeI18n} i18n        Translated strings.
	 */
	const run = ( displayName, i18n ) => {
		let aborted = false;

		/** @type {number|undefined} */
		let timer;

		/** @type {(() => void)|null} */
		let stopRain = null;

		const previousFocus    = document.activeElement;
		const previousOverflow = document.documentElement.style.overflow;
		const actOneLines      = [ ...i18n.actOne, ...i18n.countdown ];

		const stylesheet = document.createElement( 'link' );
		stylesheet.rel  = 'stylesheet';
		stylesheet.href = settings?.styles ?? '';

		const overlay = document.createElement( 'div' );
		overlay.className = 'wp-teletype';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', i18n.label );
		overlay.setAttribute( 'tabindex', '-1' );
		overlay.style.setProperty( '--wp-teletype-transition-duration', `${ FADE_TIME / 1000 }s` );

		// Hidden from screen readers, which get whole lines from the live region.
		const line = document.createElement( 'p' );
		line.setAttribute( 'aria-hidden', 'true' );

		const narration = document.createElement( 'div' );
		narration.className = 'narration';
		narration.setAttribute( 'aria-live', 'polite' );
		narration.setAttribute( 'aria-atomic', 'true' );

		const exit = document.createElement( 'button' );
		exit.type = 'button';
		exit.className = 'exit';
		exit.textContent = i18n.exit;

		const cursor = document.createElement( 'span' );
		cursor.className = 'cursor';
		cursor.textContent = '▌';

		line.append( cursor );
		overlay.append( line, narration, exit );

		/**
		 * Hands a whole line to the live region.
		 *
		 * @param {string} text Line to announce.
		 */
		const say = ( text ) => {
			narration.textContent = text;
		};

		/**
		 * Calls a function after a delay unless the scene has ended.
		 *
		 * @param {number}     ms   Milliseconds to wait.
		 * @param {() => void} next Function to call.
		 */
		const wait = ( ms, next ) => {
			timer = window.setTimeout( () => {
				if ( ! aborted ) {
					next();
				}
			}, ms );
		};

		/**
		 * Removes the typed text, leaving the cursor.
		 */
		const clear = () => {
			while ( line.firstChild && line.firstChild !== cursor ) {
				line.firstChild.remove();
			}
		};

		/**
		 * Starts a new line ahead of the cursor.
		 */
		const newline = () => {
			cursor.before( document.createElement( 'br' ) );
		};

		/**
		 * Types one line, a character at a time, ahead of the cursor.
		 *
		 * @param {string}     text Text to type.
		 * @param {() => void} done Called once the line is typed.
		 */
		const type = ( text, done ) => {
			const chars = [ ...text ];

			const next = () => {
				if ( aborted ) {
					return;
				}

				const char = chars.shift();

				if ( undefined === char ) {
					done();
					return;
				}

				cursor.before( char );
				wait( TYPE_SPEED, next );
			};

			next();
		};

		/**
		 * Types act one on the white screen, each line under the last.
		 *
		 * @param {number} index Index of the line to type.
		 */
		const actOne = ( index ) => {
			if ( index >= actOneLines.length ) {
				lightsOut();
				return;
			}

			say( actOneLines[ index ] );
			type( actOneLines[ index ], () => {
				newline();
				wait( LINE_PAUSE, () => actOne( index + 1 ) );
			} );
		};

		/**
		 * Switches the screen to black, starts the rain, and fades the cursor in.
		 */
		const lightsOut = () => {
			overlay.className = 'wp-teletype is-dark';
			clear();
			cursor.className = 'cursor is-visible';

			// The rain is decoration, so it sits out a reduced-motion preference.
			if ( ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				stopRain = rain( overlay );
			}

			// Describe the rain once for screen readers.
			say( i18n.rain );

			wait( ACT_PAUSE, () => actTwo( 0 ) );
		};

		/**
		 * Types act two one line at a time, then ends the scene after the last line.
		 *
		 * @param {number} index Index of the line to type.
		 */
		const actTwo = ( index ) => {
			const text = i18n.actTwo[ index ].replace( '%s', () => displayName );

			say( text );
			type( text, () => {
				if ( index + 1 >= i18n.actTwo.length ) {
					wait( ACT_PAUSE, abort );
					return;
				}

				wait( LINE_PAUSE, () => {
					clear();
					actTwo( index + 1 );
				} );
			} );
		};

		/**
		 * Exits on Escape and keeps Tab on the Exit button.
		 *
		 * @param {KeyboardEvent} event The keydown event.
		 */
		const onKeydown = ( event ) => {
			if ( 'Escape' === event.key ) {
				abort();
				return;
			}

			// The scene has one control, so the trap has one stop.
			if ( 'Tab' === event.key ) {
				event.preventDefault();
				exit.focus();
			}
		};

		/**
		 * Ends the scene and restores the page, its scrolling, and focus.
		 */
		const abort = () => {
			if ( aborted ) {
				return;
			}

			aborted = true;
			window.clearTimeout( timer );
			stopRain?.();

			document.removeEventListener( 'keydown', onKeydown );
			document.documentElement.style.overflow = previousOverflow;
			overlay.remove();
			stylesheet.remove();

			if ( previousFocus instanceof HTMLElement ) {
				previousFocus.focus();
			}
		};

		overlay.addEventListener( 'click', abort );

		// Show the scene once its styles are in, so it never flashes unstyled.
		stylesheet.addEventListener( 'load', () => {
			document.addEventListener( 'keydown', onKeydown );
			document.body.append( overlay );
			document.documentElement.style.overflow = 'hidden';
			overlay.focus();

			wait( START_DELAY, () => actOne( 0 ) );
		} );
		stylesheet.addEventListener( 'error', abort );

		document.head.append( stylesheet );
	};

	wp.teletype = { run };
} )( /** @type {any} */ ( window.wp = window.wp || {} ), window.wpTeletype );
