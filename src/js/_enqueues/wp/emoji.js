/**
 * @output wp-includes/js/wp-emoji.js
 */

/**
 * Additional options accepted by wp.emoji.parse().
 *
 * @typedef WPEmojiParseArgs
 * @type {Object}
 * @property {string}                 [className] Class name to give each generated image.
 * @property {Record<string, string>} [imgAttr]   Attributes to set on each generated image, in
 *                                                place of the default ones.
 */

/**
 * wp-emoji.js is used to replace emoji with images in browsers when the browser
 * doesn't support emoji natively.
 *
 * @param {Window}          window   The global window object.
 * @param {WPEmojiSettings} settings The settings object.
 */
( function( window, settings ) {
	/**
	 * Replaces emoji with images when browsers don't support emoji.
	 *
	 * @since 4.2.0
	 * @access private
	 *
	 * @class
	 *
	 * @see  Twitter Emoji library
	 * @link https://github.com/twitter/twemoji
	 *
	 * @return {Object} The wpEmoji parse and test functions.
	 */
	function wpEmoji() {
		// Compression and maintain local scope.
		const document = window.document;

		// Private.
		/** @type {Twemoji|undefined} */
		let twemoji;

		/** @type {number|undefined} */
		let timer;

		let loaded = false;
		let count = 0;

		/**
		 * Detect if the browser supports SVG.
		 *
		 * @since 4.6.0
		 * @private
		 *
		 * @see Modernizr
		 * @link https://github.com/Modernizr/Modernizr/blob/master/feature-detects/svg/asimg.js
		 *
		 * @return {boolean} True if the browser supports svg, false if not.
		 */
		function browserSupportsSvgAsImage() {
			if ( !! document.implementation.hasFeature ) {
				return document.implementation.hasFeature( 'http://www.w3.org/TR/SVG11/feature#Image', '1.1' );
			}

			// document.implementation.hasFeature is deprecated. It can be presumed
			// if future browsers remove it, the browser will support SVGs as images.
			return true;
		}

		/**
		 * Runs when the document load event is fired, so we can do our first parse of
		 * the page.
		 *
		 * Listens to all the DOM mutations and checks for added nodes that contain
		 * emoji characters and replaces those with twitter emoji images.
		 *
		 * @since 4.2.0
		 * @private
		 */
		function load() {
			if ( loaded ) {
				return;
			}

			// Ensure twemoji is available on the global window before proceeding.
			if ( typeof window.twemoji === 'undefined' ) {
				// Break if waiting for longer than 30 seconds.
				if ( count > 600 ) {
					return;
				}

				// Still waiting.
				window.clearTimeout( timer );
				timer = window.setTimeout( load, 50 );
				count++;

				return;
			}

			twemoji = window.twemoji;
			loaded = true;

			// Initialize the mutation observer, which checks all added nodes for
			// replaceable emoji characters.
			new MutationObserver( function( mutationRecords ) {
				let i = mutationRecords.length;

				while ( i-- ) {
					const addedNodes = mutationRecords[ i ].addedNodes;
					const removedNodes = mutationRecords[ i ].removedNodes;

					let ii = addedNodes.length;

					/*
					 * Checks if an image has been replaced by a text element
					 * with the same text as the alternate description of the replaced image.
					 * (presumably because the image could not be loaded).
					 * If it is, do absolutely nothing.
					 *
					 * Node type 3 is a TEXT_NODE.
					 *
					 * @link https://developer.mozilla.org/en-US/docs/Web/API/Node/nodeType
					 */
					if (
						ii === 1 && removedNodes.length === 1 &&
						addedNodes[0].nodeType === 3 &&
						removedNodes[0].nodeName === 'IMG' &&
						/** @type {Text} */ ( addedNodes[0] ).data === /** @type {HTMLImageElement} */ ( removedNodes[0] ).alt &&
						'load-failed' === /** @type {HTMLImageElement} */ ( removedNodes[0] ).getAttribute( 'data-error' )
					) {
						return;
					}

					// Loop through all the added nodes.
					while ( ii-- ) {
						let node = addedNodes[ ii ];

						// Node type 3 is a TEXT_NODE.
						if ( node.nodeType === 3 ) {
							if ( ! node.parentNode ) {
								continue;
							}

							node = node.parentNode;
						}

						if ( test( node.textContent ) ) {
							parse( /** @type {HTMLElement} */ ( node ) );
						}
					}
				}
			} ).observe( document.body, {
				childList: true,
				subtree: true
			} );

			parse( document.body );
		}

		/**
		 * Tests if a text string contains emoji characters.
		 *
		 * @since 4.3.0
		 *
		 * @memberOf wp.emoji
		 *
		 * @param {?string} text The string to test.
		 *
		 * @return {boolean} Whether the string contains emoji characters.
		 */
		function test( text ) {
			// Single char. U+20E3 to detect keycaps. U+00A9 "copyright sign" and U+00AE "registered sign" not included.
			const single = /[\u203C\u2049\u20E3\u2122\u2139\u2194-\u2199\u21A9\u21AA\u2300\u231A\u231B\u2328\u2388\u23CF\u23E9-\u23F3\u23F8-\u23FA\u24C2\u25AA\u25AB\u25B6\u25C0\u25FB-\u25FE\u2600-\u2604\u260E\u2611\u2614\u2615\u2618\u261D\u2620\u2622\u2623\u2626\u262A\u262E\u262F\u2638\u2639\u263A\u2648-\u2653\u2660\u2663\u2665\u2666\u2668\u267B\u267F\u2692\u2693\u2694\u2696\u2697\u2699\u269B\u269C\u26A0\u26A1\u26AA\u26AB\u26B0\u26B1\u26BD\u26BE\u26C4\u26C5\u26C8\u26CE\u26CF\u26D1\u26D3\u26D4\u26E9\u26EA\u26F0-\u26F5\u26F7-\u26FA\u26FD\u2702\u2705\u2708-\u270D\u270F\u2712\u2714\u2716\u271D\u2721\u2728\u2733\u2734\u2744\u2747\u274C\u274E\u2753\u2754\u2755\u2757\u2763\u2764\u2795\u2796\u2797\u27A1\u27B0\u27BF\u2934\u2935\u2B05\u2B06\u2B07\u2B1B\u2B1C\u2B50\u2B55\u3030\u303D\u3297\u3299]/;

			// Surrogate pair range. Only tests for the second half.
			const pair = /[\uDC00-\uDFFF]/;

			if ( text ) {
				return  pair.test( text ) || single.test( text );
			}

			return false;
		}

		/**
		 * Parses any emoji characters into Twemoji images.
		 *
		 * - When passed an element the emoji characters are replaced inline.
		 * - When passed a string the emoji characters are replaced and the result is
		 * returned.
		 *
		 * @since 4.2.0
		 *
		 * @memberOf wp.emoji
		 *
		 * @param {HTMLElement|string} object The element or string to parse.
		 * @param {WPEmojiParseArgs}   [args] Additional options for Twemoji.
		 *
		 * @return {HTMLElement|string} A string where all emoji are now image tags of
		 *                              emoji. Or the element that was passed as the first argument.
		 */
		function parse( object, args ) {
			/*
			 * If the browser has full support, twemoji is not loaded or our
			 * object is not what was expected, we do not parse anything.
			 */
			if ( settings.supports.everything || ! twemoji || ! object ||
				( 'string' !== typeof object && ( ! object.childNodes || ! object.childNodes.length ) ) ) {

				return object;
			}

			// Compose the params for the twitter emoji library.
			args = args || {};

			/** @type {TwemojiParseOptions} */
			const params = {
				base: browserSupportsSvgAsImage() ? settings.svgUrl : settings.baseUrl,
				ext:  browserSupportsSvgAsImage() ? settings.svgExt : settings.ext,
				className: args.className || 'emoji',
				callback: function( icon, options ) {
					// Ignore some standard characters that TinyMCE recommends in its character map.
					switch ( icon ) {
						case 'a9':
						case 'ae':
						case '2122':
						case '2194':
						case '2660':
						case '2663':
						case '2665':
						case '2666':
							return false;
					}

					if ( settings.supports.everythingExceptFlag &&
						! /^1f1(?:e[6-9a-f]|f[0-9a-f])-1f1(?:e[6-9a-f]|f[0-9a-f])$/.test( icon ) && // Country flags.
						! /^(1f3f3-fe0f-200d-1f308|1f3f4-200d-2620-fe0f)$/.test( icon )             // Rainbow and pirate flags.
					) {
						return false;
					}

					return ''.concat( options.base, icon, options.ext );
				},
				attributes: function() {
					return {
						role: 'img'
					};
				},
				onerror: function() {
					/*
					 * TODO: This handler never does anything. It refers to the Twemoji library object
					 * in three places where it means the image element, which is what Twemoji's own
					 * onerror uses and what `this` is bound to here. The library object has no
					 * parentNode, so the condition below is never true: the data-error attribute is
					 * never set, and a broken image is never replaced by its alt text. The
					 * MutationObserver above tests for that same attribute, so it is dead too.
					 *
					 * Fixing this changes behavior, so it is being tracked separately. The
					 * @ts-expect-error directives below are what keep that decision from being made
					 * silently here; they will start failing once the references are corrected.
					 */
					// @ts-expect-error -- See the note above.
					if ( twemoji.parentNode ) {
						this.setAttribute( 'data-error', 'load-failed' );
						// @ts-expect-error -- See the note above.
						twemoji.parentNode.replaceChild( document.createTextNode( twemoji.alt ), twemoji );
					}
				},
				doNotParse: function( node ) {
					const className = node && /** @type {Element} */ ( node ).className;

					if (
						className &&
						typeof className === 'string' &&
						className.indexOf( 'wp-exclude-emoji' ) !== -1
					) {
						// Do not parse this node. Emojis will not be replaced in this node and all sub-nodes.
						return true;
					}

					return false;
				}
			};

			if ( typeof args.imgAttr === 'object' ) {
				const imgAttr = args.imgAttr;

				params.attributes = function() {
					return imgAttr;
				};
			}

			return twemoji.parse( object, params );
		}

		load();

		return {
			parse: parse,
			test: test
		};
	}

	window.wp = window.wp || {};

	/**
	 * @namespace wp.emoji
	 */
	window.wp.emoji = new wpEmoji();

} )( window, window._wpemojiSettings );
