/**
 * Tests list-table overflow indicators, keyboard access, and observer cleanup.
 *
 * @since 7.2.0
 */

/* global sinon */
jQuery( function() {
	const content = document.getElementById( 'wpbody-content' );
	const tableMarkup =
		'<table class="wp-list-table"><tbody><tr><td>Post title</td></tr></tbody></table>';
	const wrapperMarkup = '<div class="wp-list-table-scroll" tabindex="0">' + tableMarkup + '</div>';

	/**
	 * Gets the overflow indicator state at each edge of a wrapper.
	 *
	 * @since 7.2.0
	 *
	 * @param {HTMLElement} wrapper The list-table scroll wrapper.
	 *
	 * @return {boolean[]} The start and end overflow states, in that order.
	 */
	function overflow( wrapper ) {
		return [
			wrapper.classList.contains( 'has-scroll-overflow-start' ),
			wrapper.classList.contains( 'has-scroll-overflow-end' ),
		];
	}

	/**
	 * Sets a wrapper's scroll position and dispatches a scroll event.
	 *
	 * @since 7.2.0
	 *
	 * @param {HTMLElement} wrapper  The list-table scroll wrapper.
	 * @param {number}      position The horizontal scroll offset, in pixels.
	 *
	 * @return {void}
	 */
	function scrollTo( wrapper, position ) {
		wrapper.scrollLeft = position;
		wrapper.dispatchEvent( new Event( 'scroll' ) );
	}

	/**
	 * Waits for resize observers to receive layout changes.
	 *
	 * @since 7.2.0
	 *
	 * @return {Promise<number>} Resolves with the second animation frame's timestamp.
	 */
	function afterResize() {
		// ResizeObserver delivers changes after animation callbacks, before the next frame.
		return new Promise( function( resolve ) {
			window.requestAnimationFrame( function() {
				window.requestAnimationFrame( resolve );
			} );
		} );
	}

	const initialOverflow = overflow( content.firstElementChild );

	QUnit.module( 'List table overflow', {
		beforeEach: async function() {
			this.sandbox = sinon.createSandbox();
			content.innerHTML = wrapperMarkup;
			this.wrapper = content.firstElementChild;
			this.table = this.wrapper.firstElementChild;
			await afterResize();
		},
		afterEach: async function() {
			content.replaceChildren();

			// Allow the mutation observer to clean up before restoring spies.
			await Promise.resolve();
			this.sandbox.restore();
		},
	} );

	QUnit.test( 'Initializes overflow indicators for tables present on page load', function( assert ) {
		assert.deepEqual(
			initialOverflow,
			[ false, true ],
			'Only the end has hidden content on page load.'
		);
		assert.strictEqual(
			this.wrapper.childElementCount,
			1,
			'Initialization leaves only the table inside its wrapper.'
		);
	} );

	[ 'ltr', 'rtl' ].forEach( function( direction ) {
		QUnit.test(
			'Updates both overflow indicators when scrolling in ' + direction,
			function( assert ) {
				const wrapper = this.wrapper;
				const sign = direction === 'rtl' ? -1 : 1;
				wrapper.dir = direction;

				scrollTo( wrapper, 0 );
				assert.deepEqual(
					overflow( wrapper ),
					[ false, true ],
					'Only the end has hidden content at the start.'
				);

				scrollTo( wrapper, sign * 200 );
				assert.deepEqual(
					overflow( wrapper ),
					[ true, true ],
					'Both ends have hidden content in the middle.'
				);

				scrollTo( wrapper, sign * 400 );
				assert.deepEqual(
					overflow( wrapper ),
					[ true, false ],
					'Only the start has hidden content at the end.'
				);

				scrollTo( wrapper, 0 );
				assert.deepEqual(
					overflow( wrapper ),
					[ false, true ],
					'Returning to the start clears its overflow indicator.'
				);
			}
		);

		QUnit.test(
			'Handles fractional positions and elastic overscroll in ' + direction,
			function( assert ) {
				const wrapper = this.wrapper;
				const sign = direction === 'rtl' ? -1 : 1;
				wrapper.dir = direction;

				// Supply positions that not all browsers expose at the default zoom level.
				Object.defineProperty( wrapper, 'scrollLeft', {
					configurable: true,
					writable: true,
					value: 0,
				} );

				scrollTo( wrapper, sign * 0.5 );
				assert.deepEqual(
					overflow( wrapper ),
					[ false, true ],
					'A fractional offset at the start does not show an overflow indicator.'
				);

				scrollTo( wrapper, sign * 1.5 );
				assert.deepEqual(
					overflow( wrapper ),
					[ true, true ],
					'Content beyond the tolerance shows the start overflow indicator.'
				);

				scrollTo( wrapper, sign * 398.5 );
				assert.deepEqual(
					overflow( wrapper ),
					[ true, true ],
					'Content beyond the tolerance shows the end overflow indicator.'
				);

				scrollTo( wrapper, sign * 399.5 );
				assert.deepEqual(
					overflow( wrapper ),
					[ true, false ],
					'A fractional offset at the end does not show an overflow indicator.'
				);

				scrollTo( wrapper, sign * -20 );
				assert.deepEqual(
					overflow( wrapper ),
					[ false, true ],
					'Overscrolling past the start does not show a start overflow indicator.'
				);

				scrollTo( wrapper, sign * 420 );
				assert.deepEqual(
					overflow( wrapper ),
					[ true, false ],
					'Overscrolling past the end does not show an end overflow indicator.'
				);

				this.table.style.width = '600px';
				wrapper.dispatchEvent( new Event( 'scroll' ) );
				assert.deepEqual(
					overflow( wrapper ),
					[ false, false ],
					'A fitting table has no overflow indicators even with a stale scroll offset.'
				);
			}
		);
	} );

	QUnit.test(
		'Updates overflow indicators when the table or its viewport changes width',
		async function( assert ) {
			assert.strictEqual( this.wrapper.tabIndex, 0, 'A scrolling wrapper is in the Tab order.' );

			this.table.style.width = '600px';
			await afterResize();
			assert.deepEqual(
				overflow( this.wrapper ),
				[ false, false ],
				'Shrinking the table clears the overflow indicator without a scroll event.'
			);
			assert.strictEqual( this.wrapper.tabIndex, -1, 'A fitting table does not add a Tab stop.' );

			this.wrapper.style.width = '400px';
			await afterResize();
			assert.deepEqual(
				overflow( this.wrapper ),
				[ false, true ],
				'Shrinking the viewport reveals overflow without a scroll event.'
			);
			assert.strictEqual(
				this.wrapper.tabIndex,
				0,
				'The wrapper returns to the Tab order when scrolling is needed.'
			);

			this.wrapper.style.width = '800px';
			await afterResize();
			assert.deepEqual(
				overflow( this.wrapper ),
				[ false, false ],
				'Widening the viewport clears the overflow indicator.'
			);
			assert.strictEqual(
				this.wrapper.tabIndex,
				-1,
				'The wrapper leaves the Tab order when the table fits again.'
			);
		}
	);

	[ 'ltr', 'rtl' ].forEach( function( direction ) {
		QUnit.test( 'Preserves narrow icon columns in ' + direction, async function( assert ) {
			this.wrapper.style.width = '400px';
			this.table.classList.add( 'widefat', 'fixed' );
			this.table.style.width = '100%';
			this.table.innerHTML = '<thead><tr><th style="min-width: 600px">Title</th><th class="sortable column-score" style="width: 3.5em"><a href="#"><span style="width: 20px; height: 20px">S</span><span class="sorting-indicators"><span class="sorting-indicator asc"></span><span class="sorting-indicator desc"></span></span></a></th></tr></thead><tbody><tr><td>Post title</td><td style="padding: 8px 10px"><div class="score-icon" style="display: inline-block; width: 12px; height: 12px; margin: 3px 10px 0 5px; border-radius: 50%; background: red"></div></td></tr></tbody>';
			await afterResize();
			assert.strictEqual( this.table.querySelector( '.score-icon' ).parentElement.tagName, 'TD', 'Inline indicators retain their original formatting context.' );

			const frame = document.createElement( 'iframe' );
			frame.width = '1000';
			const loaded = new Promise( function( resolve ) {
				frame.onload = resolve;
			} );
			const stylesheet = new URL( '../../src/wp-admin/css/list-tables.css', document.baseURI );
			frame.srcdoc = '<!DOCTYPE html><html dir="' + direction + '"><head><link rel="stylesheet" href="' + stylesheet.href + '"><style>.column-score a { display: inline-block; padding: 8px 0 8px 11px; }</style></head><body style="font: 13px sans-serif">' + this.wrapper.outerHTML + '</body></html>';
			document.getElementById( 'qunit-fixture' ).appendChild( frame );
			await loaded;
			const table = frame.contentDocument.querySelector( 'table' );
			const icon = table.querySelector( '.score-icon' );
			assert.strictEqual( icon.parentElement.scrollWidth, icon.parentElement.clientWidth, 'The small indicator is fully visible without inner scrolling.' );
			const label = table.querySelector( 'a > span' ).getBoundingClientRect();
			const arrows = table.querySelector( '.sorting-indicators' ).getBoundingClientRect();
			assert.ok( arrows.top < label.bottom && arrows.bottom > label.top, 'Sort arrows stay beside the header icon.' );
			frame.remove();
		} );

		QUnit.test( 'Keeps editing rows aligned with all columns in ' + direction, async function( assert ) {
			const frame = document.createElement( 'iframe' );
			frame.width = '1000';
			const loaded = new Promise( function( resolve ) {
				frame.onload = resolve;
			} );
			const stylesheet = new URL( '../../src/wp-admin/css/list-tables.css', document.baseURI );
			frame.srcdoc = '<!DOCTYPE html><html dir="' + direction + '"><head><link rel="stylesheet" href="' + stylesheet.href + '"></head><body id="wpbody-content"><div class="wp-list-table-scroll has-scroll-overflow-start has-scroll-overflow-end"><table class="wp-list-table widefat" style="width: 800px; border-collapse: collapse"><thead><tr><th>One</th><th>Two</th><th>Three</th><th>Four</th><th>Five</th></tr></thead><tbody><tr><td>A</td><td>B</td><td>C</td><td>D</td><td>E</td></tr><tr class="inline-edit-row"><td colspan="5">Quick Edit</td></tr><tr class="inline-editor"><td colspan="5">Inline editor</td></tr><tr id="replyrow" class="inline-edit-row"><td colspan="5"><fieldset class="comment-reply"><legend>Reply</legend><textarea style="width: 100%; box-sizing: border-box"></textarea><button>Update Comment</button></fieldset></td></tr></tbody></table></div></body></html>';
			document.getElementById( 'qunit-fixture' ).appendChild( frame );
			await loaded;
			const wrapper = frame.contentDocument.querySelector( '.wp-list-table-scroll' );
			const table = wrapper.querySelector( 'table' );
			const cells = table.querySelectorAll( 'td[colspan]' );

			[ 900, 600 ].forEach( function( width ) {
				wrapper.style.width = width + 'px';
				cells.forEach( function( cell ) {
					assert.ok(
						Math.abs( cell.getBoundingClientRect().width - table.getBoundingClientRect().width ) < 1,
						cell.textContent + ' spans every column with a ' + width + 'px viewport.'
					);
				} );
			} );

			cells.forEach( function( cell ) {
				[ '::before', '::after' ].forEach( function( pseudo ) {
					assert.strictEqual( frame.contentWindow.getComputedStyle( cell.parentElement, pseudo ).visibility, 'hidden', cell.textContent + ' hides its ' + pseudo + ' fade.' );
				} );
			} );
			const editor = table.querySelector( '.comment-reply' );
			[ 0, direction === 'rtl' ? -200 : 200 ].forEach( function( position ) {
				wrapper.scrollLeft = position;
				const viewport = wrapper.getBoundingClientRect();
				const bounds = editor.getBoundingClientRect();
				assert.ok( bounds.left >= viewport.left && bounds.right <= viewport.right, 'Comment Quick Edit stays within the visible viewport at scroll position ' + position + '.' );
				assert.ok( Math.abs( bounds.width - ( wrapper.clientWidth - 4 ) ) < 1, 'Comment Quick Edit fills the viewport inside the cell padding.' );
			} );
			frame.remove();
		} );

		QUnit.test( 'Wraps preformatted content with its column in ' + direction, async function( assert ) {
			this.table.classList.add( 'widefat', 'fixed' );
			this.table.style.width = '100%';
			this.table.innerHTML = '<thead><tr><th class="column-primary">Content</th><th style="min-width: 180px">Other column</th></tr></thead><tbody><tr><td><pre style="white-space: pre"></pre></td><td>Other content</td></tr></tbody>';
			const sampleText = '    ' + 'W'.repeat( 200 ) + '\n    Second line';
			this.table.querySelector( 'pre' ).textContent = sampleText;
			await afterResize();

			// Use the production stylesheet in an isolated desktop viewport.
			const frame = document.createElement( 'iframe' );
			frame.width = '1000';
			const loaded = new Promise( function( resolve ) {
				frame.onload = resolve;
			} );
			const stylesheet = new URL( '../../src/wp-admin/css/list-tables.css', document.baseURI );
			frame.srcdoc = '<!DOCTYPE html><html dir="' + direction + '"><head><link rel="stylesheet" href="' + stylesheet.href + '"></head><body style="font: 13px sans-serif">' + this.wrapper.outerHTML + '</body></html>';
			document.getElementById( 'qunit-fixture' ).appendChild( frame );
			await loaded;
			const wrapper = frame.contentDocument.querySelector( '.wp-list-table-scroll' );
			const pre = wrapper.querySelector( 'pre' );
			wrapper.style.width = '800px';
			const wideHeight = pre.clientHeight;

			wrapper.style.width = '600px';
			assert.strictEqual( pre.parentElement.tagName, 'TD', 'Wrapping preserves the existing cell markup.' );
			assert.strictEqual( wrapper.scrollWidth, wrapper.clientWidth, 'Long preformatted text does not force the fitting table to scroll.' );
			assert.strictEqual( pre.scrollWidth, pre.clientWidth, 'Preformatted text wraps without an inner scrollbar.' );
			assert.ok( pre.clientHeight > wideHeight, 'Narrower columns wrap the text onto more lines.' );
			assert.strictEqual( pre.textContent, sampleText, 'Wrapping preserves the original whitespace and text.' );

			wrapper.style.width = '400px';
			assert.ok( wrapper.scrollWidth > wrapper.clientWidth, 'Column minimum widths still cause table scrolling when needed.' );
			frame.remove();
		} );
	} );

	QUnit.test( 'Preserves plugin-owned scrolling and content markup', async function( assert ) {
		this.table.innerHTML = '<tbody><tr><td><div class="plugin-scroller" style="width: 300px; overflow: auto" tabindex="0"><pre style="width: 1000px">Plugin content</pre></div><div class="row-actions"><a href="#">Edit</a></div></td></tr></tbody>';
		await afterResize();
		const cell = this.table.querySelector( 'td' );
		const plugin = cell.querySelector( '.plugin-scroller' );
		assert.strictEqual( plugin.parentElement, cell, 'The plugin retains its original parent for direct-child styling.' );
		assert.ok( plugin.scrollWidth > plugin.clientWidth, 'The plugin still owns its overflow.' );
		assert.strictEqual( plugin.tabIndex, 0, 'The plugin retains its own keyboard access.' );
		assert.strictEqual( cell.querySelector( '.row-actions' ).parentElement, cell, 'Actions retain their original parent.' );
	} );

	QUnit.test( 'Preserves focus when a focused wrapper stops overflowing', async function( assert ) {
		this.wrapper.focus();
		assert.strictEqual(
			document.activeElement,
			this.wrapper,
			'The overflowing wrapper can receive focus.'
		);

		this.table.style.width = '600px';
		await afterResize();
		assert.strictEqual(
			this.wrapper.tabIndex,
			-1,
			'The fitting wrapper is removed from the Tab order.'
		);
		assert.strictEqual(
			document.activeElement,
			this.wrapper,
			'Removing overflow does not move focus.'
		);

		const link = document.createElement( 'a' );
		link.href = '#';
		link.textContent = 'Edit';
		this.table.querySelector( 'td' ).appendChild( link );
		link.focus();
		assert.strictEqual( link.tabIndex, 0, 'Links inside a fitting wrapper remain in the Tab order.' );
		assert.strictEqual(
			document.activeElement,
			link,
			'Focus can move to a link inside the fitting wrapper.'
		);
	} );

	QUnit.test(
		'Skips wrappers with visible overflow, as in the mobile layout',
		async function( assert ) {
			this.wrapper.style.overflowX = 'visible';
			this.wrapper.style.width = '400px';
			await afterResize();
			assert.ok(
				this.wrapper.scrollWidth > this.wrapper.clientWidth,
				'The table extends beyond its wrapper.'
			);
			assert.strictEqual(
				this.wrapper.tabIndex,
				-1,
				'A wrapper that does not provide scrolling is not a Tab stop.'
			);

			this.wrapper.style.overflowX = 'auto';
			this.wrapper.style.width = '800px';
			await afterResize();
			assert.strictEqual(
				this.wrapper.tabIndex,
				0,
				'The wrapper returns to the Tab order when its scrolling layout returns.'
			);
		}
	);

	QUnit.test(
		'Cleans up a replaced wrapper and initializes its replacement',
		async function( assert ) {
			const oldWrapper = this.wrapper;
			const disconnect = this.sandbox.spy( window.ResizeObserver.prototype, 'disconnect' );
			scrollTo( oldWrapper, 200 );

			content.innerHTML = wrapperMarkup;

			// Preserve overflow outside the observed content to expose a leaked scroll listener.
			document.getElementById( 'qunit-fixture' ).appendChild( oldWrapper );
			await afterResize();
			assert.deepEqual(
				overflow( content.firstElementChild ),
				[ false, true ],
				'The replacement is initialized.'
			);
			assert.deepEqual(
				overflow( oldWrapper ),
				[ false, false ],
				'The old wrapper loses its overflow indicators.'
			);
			assert.strictEqual( disconnect.callCount, 1, 'The old resize observer is disconnected.' );

			scrollTo( oldWrapper, 200 );
			assert.deepEqual(
				overflow( oldWrapper ),
				[ false, false ],
				'Scrolling the removed wrapper no longer updates overflow indicators.'
			);

			scrollTo( content.firstElementChild, 200 );
			assert.deepEqual(
				overflow( content.firstElementChild ),
				[ true, true ],
				'The replacement responds to scrolling.'
			);
		}
	);

	QUnit.test(
		'Tracks a replacement table without duplicating observers for other mutations',
		async function( assert ) {
			const observe = this.sandbox.spy( window.ResizeObserver.prototype, 'observe' );
			const disconnect = this.sandbox.spy( window.ResizeObserver.prototype, 'disconnect' );

			this.table.querySelector( 'td' ).textContent = 'Updated post title';
			await Promise.resolve();
			assert.notOk( observe.called, 'Changing a row does not add another resize observer.' );
			assert.notOk( disconnect.called, 'Changing a row retains the existing observer.' );

			this.wrapper.innerHTML = tableMarkup;
			await afterResize();
			assert.strictEqual(
				disconnect.callCount,
				1,
				'Replacing the table disconnects its old observer.'
			);
			assert.strictEqual(
				observe.callCount,
				2,
				'The new observer watches the table and its wrapper.'
			);

			this.wrapper.firstElementChild.style.width = '600px';
			await afterResize();
			assert.deepEqual(
				overflow( this.wrapper ),
				[ false, false ],
				'Resizing the replacement table updates its overflow indicators.'
			);
		}
	);

	[ null, '0' ].forEach( function( tabIndex ) {
		QUnit.test(
			'Restores the original tabindex attribute on cleanup: ' + tabIndex,
			async function( assert ) {
				const wrapper = document.createElement( 'div' );
				wrapper.className = 'wp-list-table-scroll';
				if ( tabIndex !== null ) {
					wrapper.setAttribute( 'tabindex', tabIndex );
				}
				wrapper.innerHTML = tableMarkup;
				wrapper.firstElementChild.style.width = '600px';
				content.appendChild( wrapper );
				await afterResize();
				assert.strictEqual(
					wrapper.tabIndex,
					-1,
					'The fitting wrapper is removed from the Tab order.'
				);

				wrapper.remove();
				await Promise.resolve();
				assert.strictEqual(
					wrapper.getAttribute( 'tabindex' ),
					tabIndex,
					'Cleanup restores the original attribute.'
				);
			}
		);
	} );

	QUnit.test(
		'Moves overflow tracking when a table moves to another wrapper',
		async function( assert ) {
			const oldWrapper = this.wrapper;
			const newWrapper = document.createElement( 'div' );
			newWrapper.className = 'wp-list-table-scroll';
			scrollTo( oldWrapper, 200 );

			content.appendChild( newWrapper );
			newWrapper.appendChild( this.table );
			await afterResize();
			assert.deepEqual(
				overflow( oldWrapper ),
				[ false, false ],
				'The old wrapper loses its overflow indicators.'
			);
			assert.deepEqual( overflow( newWrapper ), [ false, true ], 'The new wrapper is initialized.' );

			scrollTo( newWrapper, 200 );
			assert.deepEqual(
				overflow( newWrapper ),
				[ true, true ],
				'The new wrapper responds to scrolling.'
			);
		}
	);

	QUnit.start();
} );
