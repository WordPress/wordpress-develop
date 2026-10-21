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

	QUnit.test( 'Scrolls oversized blocks in any column without moving row actions', async function( assert ) {
		this.table.innerHTML = '<tbody><tr><td class="column-example"><p>Ordinary text.</p><pre style="width: 1000px">Wide content</pre><div class="row-actions"><a href="#">Edit</a></div></td><td>Other column</td></tr></tbody>';
		await afterResize();
		const cell = this.table.querySelector( '.column-example' );
		const area = cell.querySelector( 'pre' ).parentElement;
		assert.ok( area.classList.contains( 'wp-list-table-content-scroll' ), 'The oversized block has a scrolling wrapper.' );
		assert.ok( area.scrollWidth > area.clientWidth, 'Only the content area overflows.' );
		assert.strictEqual( area.tabIndex, 0, 'The scrolling content is keyboard accessible.' );
		assert.strictEqual( cell.querySelector( '.row-actions' ).parentElement, cell, 'Actions stay outside the scroller.' );
		assert.strictEqual( cell.querySelector( 'p' ).parentElement.tabIndex, -1, 'Ordinary text adds no Tab stop.' );
		const actionX = cell.querySelector( '.row-actions' ).getBoundingClientRect().x;
		area.scrollLeft = 100;
		assert.strictEqual( cell.querySelector( '.row-actions' ).getBoundingClientRect().x, actionX, 'Scrolling content leaves actions in place.' );
	} );

	[ 'ltr', 'rtl' ].forEach( function( direction ) {
		QUnit.test( 'Shrinks oversized content with its column in ' + direction, async function( assert ) {
			this.table.classList.add( 'widefat', 'fixed' );
			this.table.style.width = '100%';
			this.table.innerHTML = '<thead><tr><th class="column-primary">Content</th><th style="min-width: 180px">Other column</th></tr></thead><tbody><tr><td><p>Ordinary text can wrap when the available space gets smaller.</p><pre style="width: 1000px">Wide content</pre><div class="plugin-scroller" style="overflow: auto"><pre style="width: 1000px">Plugin content</pre></div></td><td>Other content</td></tr></tbody>';
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
			const area = wrapper.querySelector( 'td > .wp-list-table-content-scroll > pre' ).parentElement;
			wrapper.style.width = '800px';
			const wideWidth = area.clientWidth;

			wrapper.style.width = '600px';
			assert.ok( area.clientWidth < wideWidth, 'The content area shrinks when the table has less space.' );
			assert.strictEqual( wrapper.scrollWidth, wrapper.clientWidth, 'Oversized content does not force the fitting table to scroll.' );
			assert.ok( area.scrollWidth > area.clientWidth, 'The oversized block still scrolls inside its column.' );
			const ordinary = wrapper.querySelector( 'p' ).parentElement;
			assert.strictEqual( ordinary.scrollWidth, ordinary.clientWidth, 'Ordinary text wraps without a scrollbar.' );
			const plugin = wrapper.querySelector( '.plugin-scroller' );
			assert.ok( plugin.scrollWidth > plugin.clientWidth, 'The plugin retains its own scrollbar after shrinking.' );
			assert.strictEqual( plugin.parentElement.scrollWidth, plugin.parentElement.clientWidth, 'The plugin does not acquire a second scrollbar.' );

			wrapper.style.width = '400px';
			assert.ok( wrapper.scrollWidth > wrapper.clientWidth, 'Column minimum widths still cause table scrolling when needed.' );
			frame.remove();
		} );
	} );

	QUnit.test( 'Updates content keyboard access after text changes', async function( assert ) {
		this.table.innerHTML = '<tbody><tr><td><pre>Short</pre></td></tr></tbody>';
		await afterResize();
		const pre = this.table.querySelector( 'pre' );
		const area = pre.parentElement;
		assert.strictEqual( area.tabIndex, -1, 'Short content does not add a Tab stop.' );
		pre.firstChild.data = 'W'.repeat( 200 );
		await afterResize();
		assert.strictEqual( area.tabIndex, 0, 'Text changes make overflowing content keyboard accessible.' );
		area.focus();
		pre.firstChild.data = 'Short';
		await afterResize();
		assert.strictEqual( area.tabIndex, -1, 'Fitting content is removed from the Tab order.' );
		assert.strictEqual( document.activeElement, area, 'Existing focus is preserved.' );
	} );

	QUnit.test( 'Leaves existing plugin scrolling inside its content block', async function( assert ) {
		this.table.innerHTML = '<tbody><tr><td><div class="plugin-scroller" style="overflow: auto"><pre style="width: 1000px">Plugin content</pre></div></td></tr></tbody>';
		await afterResize();
		const plugin = this.table.querySelector( '.plugin-scroller' );
		const area = plugin.parentElement;
		assert.ok( plugin.scrollWidth > plugin.clientWidth, 'The plugin still owns its overflow.' );
		assert.strictEqual( area.scrollWidth, area.clientWidth, 'The enclosing content area does not add another scrollbar.' );
		assert.strictEqual( area.tabIndex, -1, 'The enclosing content area adds no Tab stop.' );
	} );

	QUnit.test( 'Removes generated content wrappers when a table is removed', async function( assert ) {
		this.table.innerHTML = '<tbody><tr><td><pre>Content</pre></td></tr></tbody>';
		await afterResize();
		const cell = this.table.querySelector( 'td' );
		this.table.remove();
		await afterResize();
		assert.strictEqual( cell.firstElementChild.tagName, 'PRE', 'Cleanup restores the original content structure.' );
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
