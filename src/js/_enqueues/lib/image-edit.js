/**
 * The functions necessary for editing images.
 *
 * @since 2.9.0
 * @output wp-admin/js/image-edit.js
 */

 /* global ajaxurl, confirm */

(function($) {
	var __ = wp.i18n.__;

	/**
	 * Contains all the methods to initialize and control the image editor.
	 *
	 * @namespace imageEdit
	 */
	var imageEdit = window.imageEdit = {
	iasapi : {},
	hold : {},
	postid : '',
	_view : false,

	/**
	 * Enable crop tool.
	 */
	toggleCropTool: function( postid, nonce, cropButton ) {
		var img = $( '#image-preview-' + postid ),
			selection = this.iasapi.getSelection();

		imageEdit.toggleControls( cropButton );
		var $el = $( cropButton );
		var state = ( $el.attr( 'aria-expanded' ) === 'true' ) ? 'true' : 'false';
		// Crop tools have been closed.
		if ( 'false' === state ) {
			// Cancel selection, but do not unset inputs.
			this.iasapi.cancelSelection();
			imageEdit.setDisabled($('.imgedit-crop-clear'), 0);
		} else {
			imageEdit.setDisabled($('.imgedit-crop-clear'), 1);
			// Get values from inputs to restore previous selection.
			var startX = ( $( '#imgedit-start-x-' + postid ).val() ) ? $('#imgedit-start-x-' + postid).val() : 0;
			var startY = ( $( '#imgedit-start-y-' + postid ).val() ) ? $('#imgedit-start-y-' + postid).val() : 0;
			var width = ( $( '#imgedit-sel-width-' + postid ).val() ) ? $('#imgedit-sel-width-' + postid).val() : img.innerWidth();
			var height = ( $( '#imgedit-sel-height-' + postid ).val() ) ? $('#imgedit-sel-height-' + postid).val() : img.innerHeight();
			// Ensure selection is available, otherwise reset to full image.
			if ( isNaN( selection.x1 ) ) {
				this.setCropSelection( postid, { 'x1': startX, 'y1': startY, 'x2': width, 'y2': height, 'width': width, 'height': height } );
				selection = this.iasapi.getSelection();
			}

			// If we don't already have a selection, select the entire image.
			if ( 0 === selection.x1 && 0 === selection.y1 && 0 === selection.x2 && 0 === selection.y2 ) {
				this.iasapi.setSelection( 0, 0, img.innerWidth(), img.innerHeight(), true );
				this.iasapi.setOptions( { show: true } );
				this.iasapi.update();
			} else {
				this.iasapi.setSelection( startX, startY, width, height, true );
				this.iasapi.setOptions( { show: true } );
				this.iasapi.update();
			}
		}
	},

	/**
	 * Handle crop tool clicks.
	 */
	handleCropToolClick: function( postid, nonce, cropButton ) {

		if ( cropButton.classList.contains( 'imgedit-crop-clear' ) ) {
			this.iasapi.cancelSelection();
			imageEdit.setDisabled($('.imgedit-crop-apply'), 0);

			$('#imgedit-sel-width-' + postid).val('');
			$('#imgedit-sel-height-' + postid).val('');
			$('#imgedit-start-x-' + postid).val('0');
			$('#imgedit-start-y-' + postid).val('0');
			$('#imgedit-selection-' + postid).val('');
		} else {
			// Otherwise, perform the crop.
			imageEdit.crop( postid, nonce , cropButton );
		}
	},

	/**
	 * Converts a value to an integer.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} f The float value that should be converted.
	 *
	 * @return {number} The integer representation from the float value.
	 */
	intval : function(f) {
		/*
		 * Bitwise OR operator: one of the obscure ways to truncate floating point figures,
		 * worth reminding JavaScript doesn't have a distinct "integer" type.
		 */
		return f | 0;
	},

	/**
	 * Adds the disabled attribute and class to a single form element or a field set.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {jQuery}         el The element that should be modified.
	 * @param {boolean|number} s  The state for the element. If set to true
	 *                            the element is disabled,
	 *                            otherwise the element is enabled.
	 *                            The function is sometimes called with a 0 or 1
	 *                            instead of true or false.
	 *
	 * @return {void}
	 */
	setDisabled : function( el, s ) {
		/*
		 * `el` can be a single form element or a fieldset. Before #28864, the disabled state on
		 * some text fields  was handled targeting $('input', el). Now we need to handle the
		 * disabled state on buttons too so we can just target `el` regardless if it's a single
		 * element or a fieldset because when a fieldset is disabled, its descendants are disabled too.
		 */
		if ( s ) {
			el.removeClass( 'disabled' ).prop( 'disabled', false );
		} else {
			el.addClass( 'disabled' ).prop( 'disabled', true );
		}
	},

	/**
	 * Initializes the image editor.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 *
	 * @return {void}
	 */
	init : function(postid) {
		var t = this, old = $('#image-editor-' + t.postid);

		if ( t.postid !== postid && old.length ) {
			t.close(t.postid);
		}

		t.hold.sizer = parseFloat( $('#imgedit-sizer-' + postid).val() );
		t.postid = postid;
		$('#imgedit-response-' + postid).empty();

		$('#imgedit-panel-' + postid).on( 'keypress', function(e) {
			var nonce = $( '#imgedit-nonce-' + postid ).val();
			if ( e.which === 26 && e.ctrlKey ) {
				imageEdit.undo( postid, nonce );
			}

			if ( e.which === 25 && e.ctrlKey ) {
				imageEdit.redo( postid, nonce );
			}
		});

		$('#imgedit-panel-' + postid).on( 'keypress', 'input[type="text"]', function(e) {
			var k = e.keyCode;

			// Key codes 37 through 40 are the arrow keys.
			if ( 36 < k && k < 41 ) {
				$(this).trigger( 'blur' );
			}

			// The key code 13 is the Enter key.
			if ( 13 === k ) {
				e.preventDefault();
				e.stopPropagation();
				return false;
			}
		});

		$( document ).on( 'image-editor-ui-ready', this.focusManager );
	},

	/**
	 * Calculate the image size and save it to memory.
	 *
	 * @since 6.7.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 *
	 * @return {void}
	 */
	calculateImgSize: function( postid ) {
		var t = this,
		x = t.intval( $( '#imgedit-x-' + postid ).val() ),
		y = t.intval( $( '#imgedit-y-' + postid ).val() );

		t.hold.w = t.hold.ow = x;
		t.hold.h = t.hold.oh = y;
		t.hold.xy_ratio = x / y;
		t.hold.sizer = parseFloat( $( '#imgedit-sizer-' + postid ).val() );
		t.currentCropSelection = null;
	},

	/**
	 * Toggles the wait/load icon in the editor.
	 *
	 * @since 2.9.0
	 * @since 5.5.0 Added the triggerUIReady parameter.
	 *
	 * @memberof imageEdit
	 *
	 * @param {number}  postid         The post ID.
	 * @param {number}  toggle         Is 0 or 1, fades the icon in when 1 and out when 0.
	 * @param {boolean} triggerUIReady Whether to trigger a custom event when the UI is ready. Default false.
	 *
	 * @return {void}
	 */
	toggleEditor: function( postid, toggle, triggerUIReady ) {
		var wait = $('#imgedit-wait-' + postid);

		if ( toggle ) {
			wait.fadeIn( 'fast' );
		} else {
			wait.fadeOut( 'fast', function() {
				if ( triggerUIReady ) {
					$( document ).trigger( 'image-editor-ui-ready' );
				}
			} );
		}
	},

	/**
	 * Shows or hides image menu popup.
	 *
	 * @since 6.3.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {HTMLElement} el The activated control element.
	 *
	 * @return {boolean} Always returns false.
	 */
	togglePopup : function(el) {
		var $el = $( el );
		var $targetEl = $( el ).attr( 'aria-controls' );
		var $target = $( '#' + $targetEl );
		$el
			.attr( 'aria-expanded', 'false' === $el.attr( 'aria-expanded' ) ? 'true' : 'false' );
		// Open menu and set z-index to appear above image crop area if it is enabled.
		$target
			.toggleClass( 'imgedit-popup-menu-open' ).slideToggle( 'fast' ).css( { 'z-index' : 200000 } );
		// Move focus to first item in menu when opening menu.
		if ( 'true' === $el.attr( 'aria-expanded' ) ) {
			$target.find( 'button' ).first().trigger( 'focus' );
		}

		return false;
	},

	/**
	 * Observes whether the popup should remain open based on focus position.
	 *
	 * @since 6.4.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {HTMLElement} el The activated control element.
	 *
	 * @return {boolean} Always returns false.
	 */
	monitorPopup : function() {
		var $parent = document.querySelector( '.imgedit-rotate-menu-container' );
		var $toggle = document.querySelector( '.imgedit-rotate-menu-container .imgedit-rotate' );

		setTimeout( function() {
			var $focused = document.activeElement;
			var $contains = $parent.contains( $focused );

			// If $focused is defined and not inside the menu container, close the popup.
			if ( $focused && ! $contains ) {
				if ( 'true' === $toggle.getAttribute( 'aria-expanded' ) ) {
					imageEdit.togglePopup( $toggle );
				}
			}
		}, 100 );

		return false;
	},

	/**
	 * Navigate popup menu by arrow keys.
	 *
	 * @since 6.3.0
	 * @since 6.7.0 Added the event parameter.
	 *
	 * @memberof imageEdit
	 *
	 * @param {Event} event The key or click event.
	 * @param {HTMLElement} el The current element.
	 *
	 * @return {boolean} Always returns false.
	 */
	browsePopup : function(event, el) {
		var $el = $( el );
		var $collection = $( el ).parent( '.imgedit-popup-menu' ).find( 'button' );
		var $index = $collection.index( $el );
		var $prev = $index - 1;
		var $next = $index + 1;
		var $last = $collection.length;
		if ( $prev < 0 ) {
			$prev = $last - 1;
		}
		if ( $next === $last ) {
			$next = 0;
		}
		var target = false;
		if ( event.keyCode === 40 ) {
			target = $collection.get( $next );
		} else if ( event.keyCode === 38 ) {
			target = $collection.get( $prev );
		}
		if ( target ) {
			target.focus();
			event.preventDefault();
		}

		return false;
	},

	/**
	 * Close popup menu and reset focus on feature activation.
	 *
	 * @since 6.3.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {HTMLElement} el The current element.
	 *
	 * @return {boolean} Always returns false.
	 */
	closePopup : function(el) {
		var $parent = $(el).parent( '.imgedit-popup-menu' );
		var $controlledID = $parent.attr( 'id' );
		var $target = $( 'button[aria-controls="' + $controlledID + '"]' );
		$target
			.attr( 'aria-expanded', 'false' ).trigger( 'focus' );
		$parent
			.toggleClass( 'imgedit-popup-menu-open' ).slideToggle( 'fast' );

		return false;
	},

	/**
	 * Shows or hides the image edit help box.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {HTMLElement} el The element to create the help window in.
	 *
	 * @return {boolean} Always returns false.
	 */
	toggleHelp : function(el) {
		var $el = $( el );
		$el
			.attr( 'aria-expanded', 'false' === $el.attr( 'aria-expanded' ) ? 'true' : 'false' )
			.parents( '.imgedit-group-top' ).toggleClass( 'imgedit-help-toggled' ).find( '.imgedit-help' ).slideToggle( 'fast' );

		return false;
	},

	/**
	 * Shows or hides image edit input fields when enabled.
	 *
	 * @since 6.3.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {HTMLElement} el The element to trigger the edit panel.
	 *
	 * @return {boolean} Always returns false.
	 */
	toggleControls : function(el) {
		var $el = $( el );
		var $target = $( '#' + $el.attr( 'aria-controls' ) );
		$el
			.attr( 'aria-expanded', 'false' === $el.attr( 'aria-expanded' ) ? 'true' : 'false' );
		$target
			.parent( '.imgedit-group' ).toggleClass( 'imgedit-panel-active' );

		return false;
	},

	/**
	 * Gets the value from the image edit target.
	 *
	 * The image edit target contains the image sizes where the (possible) changes
	 * have to be applied to.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 *
	 * @return {string} The value from the imagedit-save-target input field when available,
	 *                  'full' when not selected, or 'all' if it doesn't exist.
	 */
	getTarget : function( postid ) {
		var element = $( '#imgedit-save-target-' + postid );

		if ( element.length ) {
			return element.find( 'input[name="imgedit-target-' + postid + '"]:checked' ).val() || 'full';
		}

		return 'all';
	},

	/**
	 * Recalculates the height or width and keeps the original aspect ratio.
	 *
	 * If the original image size is exceeded a red exclamation mark is shown.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number}         postid The current post ID.
	 * @param {number}         x      Is 0 when it applies the y-axis
	 *                                and 1 when applicable for the x-axis.
	 * @param {jQuery}         el     Element.
	 *
	 * @return {void}
	 */
	scaleChanged : function( postid, x, el ) {
		var w = $('#imgedit-scale-width-' + postid), h = $('#imgedit-scale-height-' + postid),
		warn = $('#imgedit-scale-warn-' + postid), w1 = '', h1 = '',
		scaleBtn = $('#imgedit-scale-button');

		if ( false === this.validateNumeric( el ) ) {
			return;
		}

		if ( x ) {
			h1 = ( w.val() !== '' ) ? Math.round( w.val() / this.hold.xy_ratio ) : '';
			h.val( h1 );
		} else {
			w1 = ( h.val() !== '' ) ? Math.round( h.val() * this.hold.xy_ratio ) : '';
			w.val( w1 );
		}

		if ( ( h1 && h1 > this.hold.oh ) || ( w1 && w1 > this.hold.ow ) ) {
			warn.css('visibility', 'visible');
			scaleBtn.prop('disabled', true);
		} else {
			warn.css('visibility', 'hidden');
			scaleBtn.prop('disabled', false);
		}
	},

	/**
	 * Gets the selected aspect ratio.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 *
	 * @return {string} The aspect ratio.
	 */
	getSelRatio : function(postid) {
		var x = this.hold.w, y = this.hold.h,
			X = this.intval( $('#imgedit-crop-width-' + postid).val() ),
			Y = this.intval( $('#imgedit-crop-height-' + postid).val() );

		if ( X && Y ) {
			return X + ':' + Y;
		}

		if ( x && y ) {
			return x + ':' + y;
		}

		return '1:1';
	},

	/**
	 * Removes the last action from the image edit history.
	 * The history consist of (edit) actions performed on the image.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid  The post ID.
	 * @param {number} setSize 0 or 1, when 1 the image resets to its original size.
	 *
	 * @return {string} JSON string containing the history or an empty string if no history exists.
	 */
	filterHistory : function(postid, setSize) {
		// Apply undo state to history.
		var history = $('#imgedit-history-' + postid).val(), pop, n, o, i, op = [];

		if ( history !== '' ) {
			// Read the JSON string with the image edit history.
			history = JSON.parse(history);
			pop = this.intval( $('#imgedit-undone-' + postid).val() );
			if ( pop > 0 ) {
				while ( pop > 0 ) {
					history.pop();
					pop--;
				}
			}

			// Reset size to its original state.
			if ( setSize ) {
				if ( !history.length ) {
					this.hold.w = this.hold.ow;
					this.hold.h = this.hold.oh;
					return '';
				}

				// Restore original 'o'.
				o = history[history.length - 1];

				// c = 'crop', r = 'rotate', f = 'flip'.
				o = o.c || o.r || o.f || false;

				if ( o ) {
					// fw = Full image width.
					this.hold.w = o.fw;
					// fh = Full image height.
					this.hold.h = o.fh;
				}
			}

			// Filter the last step/action from the history.
			for ( n in history ) {
				i = history[n];
				if ( i.hasOwnProperty('c') ) {
					op[n] = { 'c': { 'x': i.c.x, 'y': i.c.y, 'w': i.c.w, 'h': i.c.h, 'r': i.c.r } };
				} else if ( i.hasOwnProperty('r') ) {
					op[n] = { 'r': i.r.r };
				} else if ( i.hasOwnProperty('f') ) {
					op[n] = { 'f': i.f.f };
				}
			}
			return JSON.stringify(op);
		}
		return '';
	},
	/**
	 * Binds the necessary events to the image.
	 *
	 * When the image source is reloaded the image will be reloaded.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number}   postid   The post ID.
	 * @param {string}   nonce    The nonce to verify the request.
	 * @param {function} callback Function to execute when the image is loaded.
	 *
	 * @return {void}
	 */
	refreshEditor : function(postid, nonce, callback) {
		var t = this, data, img;

		t.toggleEditor(postid, 1);
		data = {
			'action': 'imgedit-preview',
			'_ajax_nonce': nonce,
			'postid': postid,
			'history': t.filterHistory(postid, 1),
			'rand': t.intval(Math.random() * 1000000)
		};

		img = $( '<img id="image-preview-' + postid + '" alt="" />' )
			.on( 'load', { history: data.history }, function( event ) {
				var max1, max2,
					parent = $( '#imgedit-crop-' + postid ),
					t = imageEdit,
					historyObj;

				// Checks if there already is some image-edit history.
				if ( '' !== event.data.history ) {
					historyObj = JSON.parse( event.data.history );
					// If last executed action in history is a crop action.
					if ( historyObj[historyObj.length - 1].hasOwnProperty( 'c' ) ) {
						/*
						 * A crop action has completed and the crop button gets disabled
						 * ensure the undo button is enabled.
						 */
						t.setDisabled( $( '#image-undo-' + postid) , true );
						// Move focus to the undo button to avoid a focus loss.
						$( '#image-undo-' + postid ).trigger( 'focus' );
					}
				}

				parent.empty().append(img);

				// w, h are the new full size dimensions.
				max1 = Math.max( t.hold.w, t.hold.h );
				max2 = Math.max( $(img).width(), $(img).height() );
				t.hold.sizer = max1 > max2 ? max2 / max1 : 1;

				t.initCrop(postid, img, parent);

				if ( (typeof callback !== 'undefined') && callback !== null ) {
					callback();
				}

				if ( $('#imgedit-history-' + postid).val() && $('#imgedit-undone-' + postid).val() === '0' ) {
					$('button.imgedit-submit-btn', '#imgedit-panel-' + postid).prop('disabled', false);
				} else {
					$('button.imgedit-submit-btn', '#imgedit-panel-' + postid).prop('disabled', true);
				}
				var successMessage = __( 'Image updated.' );

				t.toggleEditor(postid, 0);
				wp.a11y.speak( successMessage, 'assertive' );
			})
			.on( 'error', function() {
				var errorMessage = __( 'Could not load the preview image. Please reload the page and try again.' );

				$( '#imgedit-crop-' + postid )
					.empty()
					.append( '<div class="notice notice-error" tabindex="-1" role="alert"><p>' + errorMessage + '</p></div>' );

				t.toggleEditor( postid, 0, true );
				wp.a11y.speak( errorMessage, 'assertive' );
			} )
			.attr('src', ajaxurl + '?' + $.param(data));
	},
	/**
	 * Performs an image edit action.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 * @param {string} nonce  The nonce to verify the request.
	 * @param {string} action The action to perform on the image.
	 *                        The possible actions are: "scale" and "restore".
	 *
	 * @return {boolean|void} Executes a post request that refreshes the page
	 *                        when the action is performed.
	 *                        Returns false if an invalid action is given,
	 *                        or when the action cannot be performed.
	 */
	action : function(postid, nonce, action) {
		var t = this, data, w, h, fw, fh;

		if ( t.notsaved(postid) ) {
			return false;
		}

		data = {
			'action': 'image-editor',
			'_ajax_nonce': nonce,
			'postid': postid
		};

		if ( 'scale' === action ) {
			w = $('#imgedit-scale-width-' + postid),
			h = $('#imgedit-scale-height-' + postid),
			fw = t.intval(w.val()),
			fh = t.intval(h.val());

			if ( fw < 1 ) {
				w.trigger( 'focus' );
				return false;
			} else if ( fh < 1 ) {
				h.trigger( 'focus' );
				return false;
			}

			if ( fw === t.hold.ow || fh === t.hold.oh ) {
				return false;
			}

			data['do'] = 'scale';
			data.fwidth = fw;
			data.fheight = fh;
		} else if ( 'restore' === action ) {
			data['do'] = 'restore';
		} else {
			return false;
		}

		t.toggleEditor(postid, 1);
		$.post( ajaxurl, data, function( response ) {
			$( '#image-editor-' + postid ).empty().append( response.data.html );
			t.toggleEditor( postid, 0, true );
			// Refresh the attachment model so that changes propagate.
			if ( t._view ) {
				t._view.refresh();
			}
		} ).done( function( response ) {
			// Whether the executed action was `scale` or `restore`, the response does have a message.
			if ( response && response.data.message.msg ) {
				wp.a11y.speak( response.data.message.msg );
				return;
			}

			if ( response && response.data.message.error ) {
				wp.a11y.speak( response.data.message.error );
			}
		} );
	},

	/**
	 * Stores the changes that are made to the image.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number}  postid   The post ID to get the image from the database.
	 * @param {string}  nonce    The nonce to verify the request.
	 *
	 * @return {boolean|void}  If the actions are successfully saved a response message is shown.
	 *                         Returns false if there is no image editing history,
	 *                         thus there are not edit-actions performed on the image.
	 */
	save : function(postid, nonce) {
		var data,
			target = this.getTarget(postid),
			history = this.filterHistory(postid, 0),
			self = this;

		if ( '' === history ) {
			return false;
		}

		this.toggleEditor(postid, 1);
		data = {
			'action': 'image-editor',
			'_ajax_nonce': nonce,
			'postid': postid,
			'history': history,
			'target': target,
			'context': $('#image-edit-context').length ? $('#image-edit-context').val() : null,
			'do': 'save'
		};
		// Post the image edit data to the backend.
		$.post( ajaxurl, data, function( response ) {
			// If a response is returned, close the editor and show an error.
			if ( response.data.error ) {
				$( '#imgedit-response-' + postid )
					.html( '<div class="notice notice-error" tabindex="-1" role="alert"><p>' + response.data.error + '</p></div>' );

				imageEdit.close(postid);
				wp.a11y.speak( response.data.error );
				return;
			}

			if ( response.data.fw && response.data.fh ) {
				$( '#media-dims-' + postid ).html( response.data.fw + ' &times; ' + response.data.fh );
			}

			if ( response.data.thumbnail ) {
				$( '.thumbnail', '#thumbnail-head-' + postid ).attr( 'src', '' + response.data.thumbnail );
			}

			if ( response.data.msg ) {
				$( '#imgedit-response-' + postid )
					.html( '<div class="notice notice-success" tabindex="-1" role="alert"><p>' + response.data.msg + '</p></div>' );

				wp.a11y.speak( response.data.msg );
			}

			if ( self._view ) {
				self._view.save();
			} else {
				imageEdit.close(postid);
			}
		});
	},

	/**
	 * Creates the image edit window.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid   The post ID for the image.
	 * @param {string} nonce    The nonce to verify the request.
	 * @param {Object} view     The image editor view to be used for the editing.
	 *
	 * @return {void|promise} Either returns void if the button was already activated
	 *                        or returns an instance of the image editor, wrapped in a promise.
	 */
	open : function( postid, nonce, view ) {
		this._view = view;

		var dfd, data,
			elem = $( '#image-editor-' + postid ),
			head = $( '#media-head-' + postid ),
			btn = $( '#imgedit-open-btn-' + postid ),
			spin = btn.siblings( '.spinner' );

		/*
		 * Instead of disabling the button, which causes a focus loss and makes screen
		 * readers announce "unavailable", return if the button was already clicked.
		 */
		if ( btn.hasClass( 'button-activated' ) ) {
			return;
		}

		spin.addClass( 'is-active' );

		data = {
			'action': 'image-editor',
			'_ajax_nonce': nonce,
			'postid': postid,
			'do': 'open'
		};

		dfd = $.ajax( {
			url:  ajaxurl,
			type: 'post',
			data: data,
			beforeSend: function() {
				btn.addClass( 'button-activated' );
			}
		} ).done( function( response ) {
			var errorMessage;

			if ( '-1' === response ) {
				errorMessage = __( 'Could not load the preview image.' );
				elem.html( '<div class="notice notice-error" tabindex="-1" role="alert"><p>' + errorMessage + '</p></div>' );
			}

			if ( response.data && response.data.html ) {
				elem.html( response.data.html );
			}

			head.fadeOut( 'fast', function() {
				elem.fadeIn( 'fast', function() {
					if ( errorMessage ) {
						$( document ).trigger( 'image-editor-ui-ready' );
					}
				} );
				btn.removeClass( 'button-activated' );
				spin.removeClass( 'is-active' );
			} );
			// Initialize the Image Editor now that everything is ready.
			imageEdit.init( postid );
		} );

		return dfd;
	},

	/**
	 * Initializes the cropping tool and sets a default cropping selection.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 *
	 * @return {void}
	 */
	imgLoaded : function(postid) {
		var img = $('#image-preview-' + postid), parent = $('#imgedit-crop-' + postid);

		// Ensure init has run even when directly loaded.
		if ( 'undefined' === typeof this.hold.sizer ) {
			this.init( postid );
		}
		this.calculateImgSize( postid );

		this.initCrop(postid, img, parent);
		this.setCropSelection( postid, { 'x1': 0, 'y1': 0, 'x2': 0, 'y2': 0, 'width': img.innerWidth(), 'height': img.innerHeight() } );

		this.toggleEditor( postid, 0, true );
	},

	/**
	 * Manages keyboard focus in the Image Editor user interface.
	 *
	 * @since 5.5.0
	 *
	 * @return {void}
	 */
	focusManager: function() {
		/*
		 * Editor is ready. Move focus to one of the admin alert notices displayed
		 * after a user action or to the first focusable element. Since the DOM
		 * update is pretty large, the timeout helps browsers update their
		 * accessibility tree to better support assistive technologies.
		 */
		setTimeout( function() {
			var elementToSetFocusTo = $( '.notice[role="alert"]' );

			if ( ! elementToSetFocusTo.length ) {
				elementToSetFocusTo = $( '.imgedit-wrap' ).find( ':tabbable:first' );
			}

			elementToSetFocusTo.attr( 'tabindex', '-1' ).trigger( 'focus' );
		}, 100 );
	},

	/**
	 * Initializes the cropping tool.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number}      postid The post ID.
	 * @param {HTMLElement} image  The preview image.
	 * @param {HTMLElement} parent The preview image container.
	 *
	 * @return {void}
	 */
	initCrop : function(postid, image, parent) {
		var t = this,
			selW = $('#imgedit-sel-width-' + postid),
			selH = $('#imgedit-sel-height-' + postid),
			$image = $( image ),
			$img;

		// Already initialized?
		if ( $image.data( 'imgAreaSelect' ) ) {
			return;
		}

		t.iasapi = $image.imgAreaSelect({
			parent: parent,
			instance: true,
			handles: true,
			keys: true,
			minWidth: 3,
			minHeight: 3,

			/**
			 * Sets the CSS styles and binds events for locking the aspect ratio.
			 *
			 * @ignore
			 *
			 * @param {jQuery} img The preview image.
			 */
			onInit: function( img ) {
				// Ensure that the imgAreaSelect wrapper elements are position:absolute
				// (even if we're in a position:fixed modal).
				$img = $( img );
				$img.next().css( 'position', 'absolute' )
					.nextAll( '.imgareaselect-outer' ).css( 'position', 'absolute' );
				/**
				 * Binds mouse down event to the cropping container.
				 *
				 * @return {void}
				 */
				parent.children().on( 'mousedown touchstart', function(e) {
					var ratio = false,
					 	sel = t.iasapi.getSelection(),
					 	cx = t.intval( $( '#imgedit-crop-width-' + postid ).val() ),
					 	cy = t.intval( $( '#imgedit-crop-height-' + postid ).val() );

					if ( cx && cy ) {
						ratio = t.getSelRatio( postid );
					} else if ( e.shiftKey && sel && sel.width && sel.height ) {
						ratio = sel.width + ':' + sel.height;
					}

					t.iasapi.setOptions({
						aspectRatio: ratio
					});
				});
			},

			/**
			 * Event triggered when starting a selection.
			 *
			 * @ignore
			 *
			 * @return {void}
			 */
			onSelectStart: function() {
				imageEdit.setDisabled($('#imgedit-crop-sel-' + postid), 1);
				imageEdit.setDisabled($('.imgedit-crop-clear'), 1);
				imageEdit.setDisabled($('.imgedit-crop-apply'), 1);
			},
			/**
			 * Event triggered when the selection is ended.
			 *
			 * @ignore
			 *
			 * @param {Object} img jQuery object representing the image.
			 * @param {Object} c   The selection.
			 *
			 * @return {Object}
			 */
			onSelectEnd: function(img, c) {
				imageEdit.setCropSelection(postid, c);
				if ( ! $('#imgedit-crop > *').is(':visible') ) {
					imageEdit.toggleControls($('.imgedit-crop.button'));
				}
			},

			/**
			 * Event triggered when the selection changes.
			 *
			 * @ignore
			 *
			 * @param {Object} img jQuery object representing the image.
			 * @param {Object} c   The selection.
			 *
			 * @return {void}
			 */
			onSelectChange: function(img, c) {
				var sizer = imageEdit.hold.sizer,
					oldSel = imageEdit.currentCropSelection;

				if ( oldSel != null && oldSel.width == c.width && oldSel.height == c.height ) {
					return;
				}

				selW.val( Math.min( imageEdit.hold.w, imageEdit.round( c.width / sizer ) ) );
				selH.val( Math.min( imageEdit.hold.h, imageEdit.round( c.height / sizer ) ) );

				t.currentCropSelection = c;
			}
		});
	},

	/**
	 * Stores the current crop selection.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 * @param {Object} c      The selection.
	 *
	 * @return {boolean}
	 */
	setCropSelection : function(postid, c) {
		var sel,
			selW = $( '#imgedit-sel-width-' + postid ),
			selH = $( '#imgedit-sel-height-' + postid ),
			sizer = this.hold.sizer,
			hold = this.hold;

		c = c || 0;

		if ( !c || ( c.width < 3 && c.height < 3 ) ) {
			this.setDisabled( $( '.imgedit-crop', '#imgedit-panel-' + postid ), 1 );
			this.setDisabled( $( '#imgedit-crop-sel-' + postid ), 1 );
			$('#imgedit-sel-width-' + postid).val('');
			$('#imgedit-sel-height-' + postid).val('');
			$('#imgedit-start-x-' + postid).val('0');
			$('#imgedit-start-y-' + postid).val('0');
			$('#imgedit-selection-' + postid).val('');
			return false;
		}

		// adjust the selection within the bounds of the image on 100% scale
		var excessW = hold.w - ( Math.round( c.x1 / sizer ) + parseInt( selW.val() ) );
		var excessH = hold.h - ( Math.round( c.y1 / sizer ) + parseInt( selH.val() ) );
		var x = Math.round( c.x1 / sizer ) + Math.min( 0, excessW );
		var y = Math.round( c.y1 / sizer ) + Math.min( 0, excessH );

		// use 100% scaling to prevent rounding errors
		sel = { 'r': 1, 'x': x, 'y': y, 'w': selW.val(), 'h': selH.val() };

		this.setDisabled($('.imgedit-crop', '#imgedit-panel-' + postid), 1);
		$('#imgedit-selection-' + postid).val( JSON.stringify(sel) );
	},


	/**
	 * Closes the image editor.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number}  postid The post ID.
	 * @param {boolean} warn   Deprecated: Check whether to warn on unsaved before closing. New behavior is to always check for unsaved before closing.
	 *
	 * @return {void|boolean} Returns false if there is a warning.
	 */
	close : function(postid, warn) {

		if ( this.notsaved(postid) ) {
			return false;
		}

		this.iasapi = {};
		this.hold = {};

		// If we've loaded the editor in the context of a Media Modal,
		// then switch to the previous view, whatever that might have been.
		if ( this._view ){
			this._view.back();
		}

		// In case we are not accessing the image editor in the context of a View,
		// close the editor the old-school way.
		else {
			$('#image-editor-' + postid).fadeOut('fast', function() {
				$( '#media-head-' + postid ).fadeIn( 'fast', function() {
					// Move focus back to the Edit Image button. Runs also when saving.
					$( '#imgedit-open-btn-' + postid ).trigger( 'focus' );
				});
				$(this).empty();
			});
		}


	},

	/**
	 * Checks if the image edit history is saved.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 *
	 * @return {boolean} Returns true if the history is not saved.
	 */
	notsaved : function(postid) {
		var h = $('#imgedit-history-' + postid).val(),
			history = ( h !== '' ) ? JSON.parse(h) : [],
			pop = this.intval( $('#imgedit-undone-' + postid).val() );

		if ( pop < history.length ) {
			if ( confirm( $('#imgedit-leaving-' + postid).text() ) ) {
				return false;
			}
			return true;
		}
		return false;
	},

	/**
	 * Adds an image edit action to the history.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {Object} op     The original position.
	 * @param {number} postid The post ID.
	 * @param {string} nonce  The nonce.
	 *
	 * @return {void}
	 */
	addStep : function(op, postid, nonce) {
		var t = this, elem = $('#imgedit-history-' + postid),
			history = ( elem.val() !== '' ) ? JSON.parse( elem.val() ) : [],
			undone = $( '#imgedit-undone-' + postid ),
			pop = t.intval( undone.val() );

		while ( pop > 0 ) {
			history.pop();
			pop--;
		}
		undone.val(0); // Reset.

		history.push(op);
		elem.val( JSON.stringify(history) );

		t.refreshEditor(postid, nonce, function() {
			t.setDisabled($('#image-undo-' + postid), true);
			t.setDisabled($('#image-redo-' + postid), false);
		});
	},

	/**
	 * Rotates the image.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {string} angle  The angle the image is rotated with.
	 * @param {number} postid The post ID.
	 * @param {string} nonce  The nonce.
	 * @param {Object} t      The target element.
	 *
	 * @return {boolean}
	 */
	rotate : function(angle, postid, nonce, t) {
		if ( $(t).hasClass('disabled') ) {
			return false;
		}
		this.closePopup(t);
		this.addStep({ 'r': { 'r': angle, 'fw': this.hold.h, 'fh': this.hold.w }}, postid, nonce);

		// Clear the selection fields after rotating.
		$( '#imgedit-sel-width-' + postid ).val( '' );
		$( '#imgedit-sel-height-' + postid ).val( '' );
		this.currentCropSelection = null;
	},

	/**
	 * Flips the image.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} axis   The axle the image is flipped on.
	 * @param {number} postid The post ID.
	 * @param {string} nonce  The nonce.
	 * @param {Object} t      The target element.
	 *
	 * @return {boolean}
	 */
	flip : function (axis, postid, nonce, t) {
		if ( $(t).hasClass('disabled') ) {
			return false;
		}
		this.closePopup(t);
		this.addStep({ 'f': { 'f': axis, 'fw': this.hold.w, 'fh': this.hold.h }}, postid, nonce);

		// Clear the selection fields after flipping.
		$( '#imgedit-sel-width-' + postid ).val( '' );
		$( '#imgedit-sel-height-' + postid ).val( '' );
		this.currentCropSelection = null;
	},

	/**
	 * Crops the image.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 * @param {string} nonce  The nonce.
	 * @param {Object} t      The target object.
	 *
	 * @return {void|boolean} Returns false if the crop button is disabled.
	 */
	crop : function (postid, nonce, t) {
		var sel = $('#imgedit-selection-' + postid).val(),
			w = this.intval( $('#imgedit-sel-width-' + postid).val() ),
			h = this.intval( $('#imgedit-sel-height-' + postid).val() );

		if ( $(t).hasClass('disabled') || sel === '' ) {
			return false;
		}

		sel = JSON.parse(sel);
		if ( sel.w > 0 && sel.h > 0 && w > 0 && h > 0 ) {
			sel.fw = w;
			sel.fh = h;
			this.addStep({ 'c': sel }, postid, nonce);
		}

		// Clear the selection fields after cropping.
		$( '#imgedit-sel-width-' + postid ).val( '' );
		$( '#imgedit-sel-height-' + postid ).val( '' );
		$( '#imgedit-start-x-' + postid ).val( '0' );
		$( '#imgedit-start-y-' + postid ).val( '0' );
		this.currentCropSelection = null;
	},

	/**
	 * Undoes an image edit action.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid   The post ID.
	 * @param {string} nonce    The nonce.
	 *
	 * @return {void|false} Returns false if the undo button is disabled.
	 */
	undo : function (postid, nonce) {
		var t = this, button = $('#image-undo-' + postid), elem = $('#imgedit-undone-' + postid),
			pop = t.intval( elem.val() ) + 1;

		if ( button.hasClass('disabled') ) {
			return;
		}

		elem.val(pop);
		t.refreshEditor(postid, nonce, function() {
			var elem = $('#imgedit-history-' + postid),
				history = ( elem.val() !== '' ) ? JSON.parse( elem.val() ) : [];

			t.setDisabled($('#image-redo-' + postid), true);
			t.setDisabled(button, pop < history.length);
			// When undo gets disabled, move focus to the redo button to avoid a focus loss.
			if ( history.length === pop ) {
				$( '#image-redo-' + postid ).trigger( 'focus' );
			}
		});
	},

	/**
	 * Reverts a undo action.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 * @param {string} nonce  The nonce.
	 *
	 * @return {void}
	 */
	redo : function(postid, nonce) {
		var t = this, button = $('#image-redo-' + postid), elem = $('#imgedit-undone-' + postid),
			pop = t.intval( elem.val() ) - 1;

		if ( button.hasClass('disabled') ) {
			return;
		}

		elem.val(pop);
		t.refreshEditor(postid, nonce, function() {
			t.setDisabled($('#image-undo-' + postid), true);
			t.setDisabled(button, pop > 0);
			// When redo gets disabled, move focus to the undo button to avoid a focus loss.
			if ( 0 === pop ) {
				$( '#image-undo-' + postid ).trigger( 'focus' );
			}
		});
	},

	/**
	 * Sets the selection for the height and width in pixels.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid The post ID.
	 * @param {jQuery} el     The element containing the values.
	 *
	 * @return {void|boolean} Returns false when the x or y value is lower than 1,
	 *                        void when the value is not numeric or when the operation
	 *                        is successful.
	 */
	setNumSelection : function( postid, el ) {
		var sel, elX = $('#imgedit-sel-width-' + postid), elY = $('#imgedit-sel-height-' + postid),
			elX1 = $('#imgedit-start-x-' + postid), elY1 = $('#imgedit-start-y-' + postid),
			xS = this.intval( elX1.val() ), yS = this.intval( elY1.val() ),
			x = this.intval( elX.val() ), y = this.intval( elY.val() ),
			img = $('#image-preview-' + postid), imgh = img.height(), imgw = img.width(),
			sizer = this.hold.sizer, x1, y1, x2, y2, ias = this.iasapi;

		this.currentCropSelection = null;

		if ( false === this.validateNumeric( el ) ) {
			return;
		}

		if ( x < 1 ) {
			elX.val('');
			return false;
		}

		if ( y < 1 ) {
			elY.val('');
			return false;
		}

		if ( ( ( x && y ) || ( xS && yS ) ) && ( sel = ias.getSelection() ) ) {
			x2 = sel.x1 + Math.round( x * sizer );
			y2 = sel.y1 + Math.round( y * sizer );
			x1 = ( xS === sel.x1 ) ? sel.x1 : Math.round( xS * sizer );
			y1 = ( yS === sel.y1 ) ? sel.y1 : Math.round( yS * sizer );

			if ( x2 > imgw ) {
				x1 = 0;
				x2 = imgw;
				elX.val( Math.min( this.hold.w, Math.round( x2 / sizer ) ) );
			}

			if ( y2 > imgh ) {
				y1 = 0;
				y2 = imgh;
				elY.val( Math.min( this.hold.h, Math.round( y2 / sizer ) ) );
			}

			ias.setSelection( x1, y1, x2, y2 );
			ias.update();
			this.setCropSelection(postid, ias.getSelection());
			this.currentCropSelection = ias.getSelection();
		}
	},

	/**
	 * Rounds a number to a whole.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} num The number.
	 *
	 * @return {number} The number rounded to a whole number.
	 */
	round : function(num) {
		var s;
		num = Math.round(num);

		if ( this.hold.sizer > 0.6 ) {
			return num;
		}

		s = num.toString().slice(-1);

		if ( '1' === s ) {
			return num - 1;
		} else if ( '9' === s ) {
			return num + 1;
		}

		return num;
	},

	/**
	 * Sets a locked aspect ratio for the selection.
	 *
	 * @since 2.9.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {number} postid     The post ID.
	 * @param {number} n          The ratio to set.
	 * @param {jQuery} el         The element containing the values.
	 *
	 * @return {void}
	 */
	setRatioSelection : function(postid, n, el) {
		var sel, r, x = this.intval( $('#imgedit-crop-width-' + postid).val() ),
			y = this.intval( $('#imgedit-crop-height-' + postid).val() ),
			h = $('#image-preview-' + postid).height();

		if ( false === this.validateNumeric( el ) ) {
			this.iasapi.setOptions({
				aspectRatio: null
			});

			return;
		}

		if ( x && y ) {
			this.iasapi.setOptions({
				aspectRatio: x + ':' + y
			});

			if ( sel = this.iasapi.getSelection(true) ) {
				r = Math.ceil( sel.y1 + ( ( sel.x2 - sel.x1 ) / ( x / y ) ) );

				if ( r > h ) {
					r = h;
					var errorMessage = __( 'Selected crop ratio exceeds the boundaries of the image. Try a different ratio.' );

					$( '#imgedit-crop-' + postid )
						.prepend( '<div class="notice notice-error" tabindex="-1" role="alert"><p>' + errorMessage + '</p></div>' );

					wp.a11y.speak( errorMessage, 'assertive' );
					if ( n ) {
						$('#imgedit-crop-height-' + postid).val( '' );
					} else {
						$('#imgedit-crop-width-' + postid).val( '');
					}
				} else {
					var error = $( '#imgedit-crop-' + postid ).find( '.notice-error' );
					if ( 'undefined' !== typeof( error ) ) {
						error.remove();
					}
				}

				this.iasapi.setSelection( sel.x1, sel.y1, sel.x2, r );
				this.iasapi.update();
			}
		}
	},

	/**
	 * Validates if a value in a jQuery.HTMLElement is numeric.
	 *
	 * @since 4.6.0
	 *
	 * @memberof imageEdit
	 *
	 * @param {jQuery} el The html element.
	 *
	 * @return {void|boolean} Returns false if the value is not numeric,
	 *                        void when it is.
	 */
	validateNumeric: function( el ) {
		if ( false === this.intval( $( el ).val() ) ) {
			$( el ).val( '' );
			return false;
		}
	}
};
})(jQuery);
