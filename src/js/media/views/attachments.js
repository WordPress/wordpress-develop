var View = wp.media.View,
	$ = jQuery,
	Attachments,
	infiniteScrolling = wp.media.view.settings.infiniteScrolling;

Attachments = View.extend(/** @lends wp.media.view.Attachments.prototype */{
	tagName:   'ul',
	className: 'attachments',

	attributes: {
		tabIndex: -1
	},

	/**
	 * Represents the overview of attachments in the Media Library.
	 *
	 * The constructor binds events to the collection this view represents when
	 * adding or removing attachments or resetting the entire collection.
	 *
	 * @since 3.5.0
	 *
	 * @constructs
	 * @memberof wp.media.view
	 *
	 * @augments wp.media.View
	 *
	 * @listens collection:add
	 * @listens collection:remove
	 * @listens collection:reset
	 * @listens controller:library:selection:add
	 * @listens scrollElement:scroll
	 * @listens this:ready
	 * @listens controller:open
	 */
	initialize: function() {
		this.el.id = _.uniqueId('__attachments-view-');

		/**
		 * @since 5.8.0 Added the `infiniteScrolling` parameter.
		 *
		 * @param infiniteScrolling  Whether to enable infinite scrolling or use
		 *                           the default "load more" button.
		 * @param refreshSensitivity The time in milliseconds to throttle the scroll
		 *                           handler.
		 * @param refreshThreshold   The amount of pixels that should be scrolled before
		 *                           loading more attachments from the server.
		 * @param AttachmentView     The view class to be used for models in the
		 *                           collection.
		 * @param sortable           A jQuery sortable options object
		 *                           ( http://api.jqueryui.com/sortable/ ).
		 * @param resize             A boolean indicating whether or not to listen to
		 *                           resize events.
		 * @param idealColumnWidth   The width in pixels which a column should have when
		 *                           calculating the total number of columns.
		 */
		_.defaults( this.options, {
			infiniteScrolling:  infiniteScrolling || false,
			refreshSensitivity: wp.media.isTouchDevice ? 300 : 200,
			refreshThreshold:   3,
			AttachmentView:     wp.media.view.Attachment,
			sortable:           false,
			resize:             true,
			idealColumnWidth:   $( window ).width() < 640 ? 135 : 150
		});

		this._viewsByCid = {};
		this.$window = $( window );
		this.resizeEvent = 'resize.media-modal-columns';

		this.collection.on( 'add', function( attachment ) {
			this.views.add( this.createAttachmentView( attachment ), {
				at: this.collection.indexOf( attachment )
			});
		}, this );

		/*
		 * Find the view to be removed, delete it and call the remove function to clear
		 * any set event handlers.
		 */
		this.collection.on( 'remove', function( attachment ) {
			var view = this._viewsByCid[ attachment.cid ];
			delete this._viewsByCid[ attachment.cid ];

			if ( view ) {
				view.remove();
			}
		}, this );

		this.collection.on( 'reset', this.render, this );

		this.controller.on( 'library:selection:add', this.attachmentFocus, this );

		if ( this.options.infiniteScrolling ) {
			// Throttle the scroll handler and bind this.
			this.scroll = _.chain( this.scroll ).bind( this ).throttle( this.options.refreshSensitivity ).value();

			this.options.scrollElement = this.options.scrollElement || this.el;
			$( this.options.scrollElement ).on( 'scroll', this.scroll );
		}

		this.initSortable();

		_.bindAll( this, 'setColumns' );

		if ( this.options.resize ) {
			this.on( 'ready', this.bindEvents );
			this.controller.on( 'open', this.setColumns );

			/*
			 * Call this.setColumns() after this view has been rendered in the
			 * DOM so attachments get proper width applied.
			 */
			_.defer( this.setColumns, this );
		}
	},

	/**
	 * Listens to the resizeEvent on the window.
	 *
	 * Adjusts the amount of columns accordingly. First removes any existing event
	 * handlers to prevent duplicate listeners.
	 *
	 * @since 4.0.0
	 *
	 * @listens window:resize
	 *
	 * @return {void}
	 */
	bindEvents: function() {
		this.$window.off( this.resizeEvent ).on( this.resizeEvent, _.debounce( this.setColumns, 50 ) );
	},

	/**
	 * Focuses the first item in the collection.
	 *
	 * @since 4.0.0
	 *
	 * @return {void}
	 */
	attachmentFocus: function() {
		/*
		 * @todo When uploading new attachments, this tries to move focus to
		 * the attachments grid. Actually, a progress bar gets initially displayed
		 * and then updated when uploading completes, so focus is lost.
		 * Additionally: this view is used for both the attachments list and
		 * the list of selected attachments in the bottom media toolbar. Thus, when
		 * uploading attachments, it is called twice and returns two different `this`.
		 * `this.columns` is truthy within the modal.
		 */
		if ( this.columns ) {
			// Move focus to the grid list within the modal.
			this.$el.focus();
		}
	},

	/**
	 * Restores focus to the selected item in the collection.
	 *
	 * Moves focus back to the first selected attachment in the grid. Used when
	 * tabbing backwards from the attachment details sidebar.
	 * See media.view.AttachmentsBrowser.
	 *
	 * @since 4.0.0
	 *
	 * @return {void}
	 */
	restoreFocus: function() {
		this.$( 'li.selected:first' ).focus();
	},

	/**
	 * Handles events for arrow key presses.
	 *
	 * Focuses the attachment in the direction of the used arrow key if it exists.
	 *
	 * @since 4.0.0
	 *
	 * @param {KeyboardEvent} event The keyboard event that triggered this function.
	 *
	 * @return {void}
	 */
	arrowEvent: function( event ) {
		var attachments = this.$el.children( 'li' ),
			perRow = this.columns,
			index = attachments.filter( ':focus' ).index(),
			row = ( index + 1 ) <= perRow ? 1 : Math.ceil( ( index + 1 ) / perRow );

		if ( index === -1 ) {
			return;
		}

		// Left arrow = 37.
		if ( 37 === event.keyCode ) {
			if ( 0 === index ) {
				return;
			}
			attachments.eq( index - 1 ).focus();
		}

		// Up arrow = 38.
		if ( 38 === event.keyCode ) {
			if ( 1 === row ) {
				return;
			}
			attachments.eq( index - perRow ).focus();
		}

		// Right arrow = 39.
		if ( 39 === event.keyCode ) {
			if ( attachments.length === index ) {
				return;
			}
			attachments.eq( index + 1 ).focus();
		}

		// Down arrow = 40.
		if ( 40 === event.keyCode ) {
			if ( Math.ceil( attachments.length / perRow ) === row ) {
				return;
			}
			attachments.eq( index + perRow ).focus();
		}
	},

	/**
	 * Clears any set event handlers.
	 *
	 * @since 3.5.0
	 *
	 * @return {void}
	 */
	dispose: function() {
		this.collection.props.off( null, null, this );
		if ( this.options.resize ) {
			this.$window.off( this.resizeEvent );
		}

		// Call 'dispose' directly on the parent class.
		View.prototype.dispose.apply( this, arguments );
	},

	/**
	 * Calculates the amount of columns.
	 *
	 * Calculates the amount of columns and sets it on the data-columns attribute
	 * of .media-frame-content.
	 *
	 * @since 4.0.0
	 *
	 * @return {void}
	 */
	setColumns: function() {
		var prev = this.columns,
			width = this.$el.width();

		if ( width ) {
			this.columns = Math.min( Math.round( width / this.options.idealColumnWidth ), 12 ) || 1;

			if ( ! prev || prev !== this.columns ) {
				this.$el.closest( '.media-frame-content' ).attr( 'data-columns', this.columns );
			}
		}
	},

	/**
	 * Initializes jQuery sortable on the attachment list.
	 *
	 * Fails gracefully if jQuery sortable doesn't exist or isn't passed
	 * in the options.
	 *
	 * @since 3.5.0
	 *
	 * @fires collection:reset
	 *
	 * @return {void}
	 */
	initSortable: function() {
		var collection = this.collection;

		if ( ! this.options.sortable || ! $.fn.sortable ) {
			return;
		}

		this.$el.sortable( _.extend({
			// If the `collection` has a `comparator`, disable sorting.
			disabled: !! collection.comparator,

			/*
			 * Change the position of the attachment as soon as the mouse pointer
			 * overlaps a thumbnail.
			 */
			tolerance: 'pointer',

			// Record the initial `index` of the dragged model.
			start: function( event, ui ) {
				ui.item.data('sortableIndexStart', ui.item.index());
			},

			/*
			 * Update the model's index in the collection. Do so silently, as the view
			 * is already accurate.
			 */
			update: function( event, ui ) {
				var model = collection.at( ui.item.data('sortableIndexStart') ),
					comparator = collection.comparator;

				// Temporarily disable the comparator to prevent `add`
				// from re-sorting.
				delete collection.comparator;

				// Silently shift the model to its new index.
				collection.remove( model, {
					silent: true
				});
				collection.add( model, {
					silent: true,
					at:     ui.item.index()
				});

				// Restore the comparator.
				collection.comparator = comparator;

				// Fire the `reset` event to ensure other collections sync.
				collection.trigger( 'reset', collection );

				// If the collection is sorted by menu order, update the menu order.
				collection.saveMenuOrder();
			}
		}, this.options.sortable ) );

		/*
		 * If the `orderby` property is changed on the `collection`,
		 * check to see if we have a `comparator`. If so, disable sorting.
		 */
		collection.props.on( 'change:orderby', function() {
			this.$el.sortable( 'option', 'disabled', !! collection.comparator );
		}, this );

		this.collection.props.on( 'change:orderby', this.refreshSortable, this );
		this.refreshSortable();
	},

	/**
	 * Disables jQuery sortable if collection has a comparator or collection.orderby
	 * equals menuOrder.
	 *
	 * @since 3.5.0
	 *
	 * @return {void}
	 */
	refreshSortable: function() {
		if ( ! this.options.sortable || ! $.fn.sortable ) {
			return;
		}

		var collection = this.collection,
			orderby = collection.props.get('orderby'),
			enabled = 'menuOrder' === orderby || ! collection.comparator;

		this.$el.sortable( 'option', 'disabled', ! enabled );
	},

	/**
	 * Creates a new view for an attachment and adds it to _viewsByCid.
	 *
	 * @since 3.5.0
	 *
	 * @param {wp.media.model.Attachment} attachment
	 *
	 * @return {wp.media.View} The created view.
	 */
	createAttachmentView: function( attachment ) {
		var view = new this.options.AttachmentView({
			controller:           this.controller,
			model:                attachment,
			collection:           this.collection,
			selection:            this.options.selection
		});

		return this._viewsByCid[ attachment.cid ] = view;
	},

	/**
	 * Prepares view for display.
	 *
	 * Creates views for every attachment in collection if the collection is not
	 * empty, otherwise clears all views and loads more attachments.
	 *
	 * @since 3.5.0
	 *
	 * @return {void}
	 */
	prepare: function() {
		if ( this.collection.length ) {
			this.views.set( this.collection.map( this.createAttachmentView, this ) );
		} else {
			this.views.unset();
			if ( this.options.infiniteScrolling ) {
				this.collection.more().done( this.scroll );
			}
		}
	},

	/**
	 * Triggers the scroll function to check if we should query for additional
	 * attachments right away.
	 *
	 * @since 3.5.0
	 *
	 * @return {void}
	 */
	ready: function() {
		if ( this.options.infiniteScrolling ) {
			this.scroll();
		}
	},

	/**
	 * Handles scroll events.
	 *
	 * Shows the spinner if we're close to the bottom. Loads more attachments from
	 * server if we're {refreshThreshold} times away from the bottom.
	 *
	 * @since 3.5.0
	 *
	 * @return {void}
	 */
	scroll: function() {
		var view = this,
			el = this.options.scrollElement,
			scrollTop = el.scrollTop,
			toolbar;

		/*
		 * The scroll event occurs on the document, but the element that should be
		 * checked is the document body.
		 */
		if ( el === document ) {
			el = document.body;
			scrollTop = $(document).scrollTop();
		}

		if ( ! $(el).is(':visible') || ! this.collection.hasMore() ) {
			return;
		}

		toolbar = this.views.parent.toolbar;

		// Show the spinner only if we are close to the bottom.
		if ( el.scrollHeight - ( scrollTop + el.clientHeight ) < el.clientHeight / 3 ) {
			toolbar.get('spinner').show();
		}

		if ( el.scrollHeight < scrollTop + ( el.clientHeight * this.options.refreshThreshold ) ) {
			this.collection.more().done(function() {
				view.scroll();
				toolbar.get('spinner').hide();
			});
		}
	}
});

module.exports = Attachments;
