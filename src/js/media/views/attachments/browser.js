var View = wp.media.View,
	mediaTrash = wp.media.view.settings.mediaTrash,
	l10n = wp.media.view.l10n,
	$ = jQuery,
	AttachmentsBrowser,
	infiniteScrolling = wp.media.view.settings.infiniteScrolling,
	__ = wp.i18n.__,
	sprintf = wp.i18n.sprintf;

/**
 * wp.media.view.AttachmentsBrowser
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 *
 * @param {object}         [options]               The options hash passed to the view.
 * @param {boolean|string} [options.filters=false] Which filters to show in the browser's toolbar.
 *                                                 Accepts 'uploaded' and 'all'.
 * @param {boolean}        [options.search=true]   Whether to show the search interface in the
 *                                                 browser's toolbar.
 * @param {boolean}        [options.date=true]     Whether to show the date filter in the
 *                                                 browser's toolbar.
 * @param {boolean}        [options.display=false] Whether to show the attachments display settings
 *                                                 view in the sidebar.
 * @param {boolean|string} [options.sidebar=true]  Whether to create a sidebar for the browser.
 *                                                 Accepts true, false, and 'errors'.
 */
AttachmentsBrowser = View.extend(/** @lends wp.media.view.AttachmentsBrowser.prototype */{
	tagName:   'div',
	className: 'attachments-browser',

	initialize: function() {
		_.defaults( this.options, {
			filters: false,
			search:  true,
			date:    true,
			display: false,
			sidebar: true,
			AttachmentView: wp.media.view.Attachment.Library
		});

		this.controller.on( 'toggle:upload:attachment', this.toggleUploader, this );
		this.controller.on( 'edit:selection', this.editSelection );

		// In the Media Library, the sidebar is used to display errors before the attachments grid.
		if ( this.options.sidebar && 'errors' === this.options.sidebar ) {
			this.createSidebar();
		}

		/*
		 * In the grid mode (the Media Library), place the Inline Uploader before
		 * other sections so that the visual order and the DOM order match. This way,
		 * the Inline Uploader in the Media Library is right after the "Add New"
		 * button, see ticket #37188.
		 */
		if ( this.controller.isModeActive( 'grid' ) ) {
			this.createUploader();

			/*
			 * Create a multi-purpose toolbar. Used as main toolbar in the Media Library
			 * and also for other things, for example the "Drag and drop to reorder" and
			 * "Suggested dimensions" info in the media modal.
			 */
			this.createToolbar();
		} else {
			this.createToolbar();
			this.createUploader();
		}

		// Add a heading before the attachments list.
		this.createAttachmentsHeading();

		// Create the attachments wrapper view.
		this.createAttachmentsWrapperView();

		if ( ! infiniteScrolling ) {
			this.$el.addClass( 'has-load-more' );
			this.createLoadMoreView();
		}

		// For accessibility reasons, place the normal sidebar after the attachments, see ticket #36909.
		if ( this.options.sidebar && 'errors' !== this.options.sidebar ) {
			this.createSidebar();
		}

		this.updateContent();

		if ( ! infiniteScrolling ) {
			this.updateLoadMoreView();
		}

		if ( ! this.options.sidebar || 'errors' === this.options.sidebar ) {
			this.$el.addClass( 'hide-sidebar' );

			if ( 'errors' === this.options.sidebar ) {
				this.$el.addClass( 'sidebar-for-errors' );
			}
		}

		this.collection.on( 'add remove reset', this.updateContent, this );

		if ( ! infiniteScrolling ) {
			this.collection.on( 'add remove reset', this.updateLoadMoreView, this );
		}

		// The non-cached or cached attachments query has completed.
		this.collection.on( 'attachments:received', this.announceSearchResults, this );
	},

	/**
	 * Updates the `wp.a11y.speak()` ARIA live region with a message to communicate
	 * the number of search results to screen reader users. This function is
	 * debounced because the collection updates multiple times.
	 *
	 * @since 5.3.0
	 *
	 * @return {void}
	 */
	announceSearchResults: _.debounce( function() {
		var count,
			/* translators: Accessibility text. %d: Number of attachments found in a search. */
			mediaFoundHasMoreResultsMessage = __( 'Number of media items displayed: %d. Click load more for more results.' );

		if ( infiniteScrolling ) {
			/* translators: Accessibility text. %d: Number of attachments found in a search. */
			mediaFoundHasMoreResultsMessage = __( 'Number of media items displayed: %d. Scroll the page for more results.' );
		}

		if ( this.collection.mirroring.args.s ) {
			count = this.collection.length;

			if ( 0 === count ) {
				wp.a11y.speak( l10n.noMediaTryNewSearch );
				return;
			}

			if ( this.collection.hasMore() ) {
				wp.a11y.speak( mediaFoundHasMoreResultsMessage.replace( '%d', count ) );
				return;
			}

			wp.a11y.speak( l10n.mediaFound.replace( '%d', count ) );
		}
	}, 200 ),

	editSelection: function( modal ) {
		// When editing a selection, move focus to the "Go to library" button.
		modal.$( '.media-button-backToLibrary' ).focus();
	},

	/**
	 * @return {wp.media.view.AttachmentsBrowser} Returns itself to allow chaining.
	 */
	dispose: function() {
		this.options.selection.off( null, null, this );
		View.prototype.dispose.apply( this, arguments );
		return this;
	},

	createToolbar: function() {
		var LibraryViewSwitcher, Filters, toolbarOptions,
			showFilterByType = -1 !== $.inArray( this.options.filters, [ 'uploaded', 'all' ] );

		toolbarOptions = {
			controller: this.controller
		};

		if ( this.controller.isModeActive( 'grid' ) ) {
			toolbarOptions.className = 'media-toolbar wp-filter';
		}

		/**
		* @member {wp.media.view.Toolbar}
		*/
		this.toolbar = new wp.media.view.Toolbar( toolbarOptions );

		this.views.add( this.toolbar );

		this.toolbar.set( 'spinner', new wp.media.view.Spinner({
			priority: -20
		}) );

		if ( showFilterByType || this.options.date ) {
			/*
			 * Create a h2 heading before the select elements that filter attachments.
			 * This heading is visible in the modal and visually hidden in the grid.
			 */
			this.toolbar.set( 'filters-heading', new wp.media.view.Heading( {
				priority:   -100,
				text:       l10n.filterAttachments,
				level:      'h2',
				className:  'media-attachments-filter-heading'
			}).render() );
		}

		if ( showFilterByType ) {
			// "Filters" is a <select>, a visually hidden label element needs to be rendered before.
			this.toolbar.set( 'filtersLabel', new wp.media.view.Label({
				value: l10n.filterByType,
				attributes: {
					'for':  'media-attachment-filters'
				},
				priority:   -80
			}).render() );

			if ( 'uploaded' === this.options.filters ) {
				this.toolbar.set( 'filters', new wp.media.view.AttachmentFilters.Uploaded({
					controller: this.controller,
					model:      this.collection.props,
					priority:   -80
				}).render() );
			} else {
				Filters = new wp.media.view.AttachmentFilters.All({
					controller: this.controller,
					model:      this.collection.props,
					priority:   -80
				});

				this.toolbar.set( 'filters', Filters.render() );
			}
		}

		/*
		 * Feels odd to bring the global media library switcher into the Attachment browser view.
		 * Is this a use case for doAction( 'add:toolbar-items:attachments-browser', this.toolbar );
		 * which the controller can tap into and add this view?
		 */
		if ( this.controller.isModeActive( 'grid' ) ) {
			LibraryViewSwitcher = View.extend({
				className: 'view-switch media-grid-view-switch',
				template: wp.template( 'media-library-view-switcher')
			});

			this.toolbar.set( 'libraryViewSwitcher', new LibraryViewSwitcher({
				controller: this.controller,
				priority: -90
			}).render() );

			// DateFilter is a <select>, a visually hidden label element needs to be rendered before.
			this.toolbar.set( 'dateFilterLabel', new wp.media.view.Label({
				value: l10n.filterByDate,
				attributes: {
					'for': 'media-attachment-date-filters'
				},
				priority: -75
			}).render() );
			this.toolbar.set( 'dateFilter', new wp.media.view.DateFilter({
				controller: this.controller,
				model:      this.collection.props,
				priority: -75
			}).render() );

			// BulkSelection is a <div> with subviews, including screen reader text.
			this.toolbar.set( 'selectModeToggleButton', new wp.media.view.SelectModeToggleButton({
				text: l10n.bulkSelect,
				controller: this.controller,
				priority: -70
			}).render() );

			this.toolbar.set( 'deleteSelectedButton', new wp.media.view.DeleteSelectedButton({
				filters: Filters,
				style: 'primary',
				disabled: true,
				text: mediaTrash ? l10n.trashSelected : l10n.deletePermanently,
				controller: this.controller,
				priority: -80,
				click: function() {
					var changed = [], removed = [],
						selection = this.controller.state().get( 'selection' ),
						library = this.controller.state().get( 'library' );

					if ( ! selection.length ) {
						return;
					}

					if ( ! mediaTrash && ! window.confirm( l10n.warnBulkDelete ) ) {
						return;
					}

					if ( mediaTrash &&
						'trash' !== selection.at( 0 ).get( 'status' ) &&
						! window.confirm( l10n.warnBulkTrash ) ) {

						return;
					}

					selection.each( function( model ) {
						if ( ! model.get( 'nonces' )['delete'] ) {
							removed.push( model );
							return;
						}

						if ( mediaTrash && 'trash' === model.get( 'status' ) ) {
							model.set( 'status', 'inherit' );
							changed.push( model.save() );
							removed.push( model );
						} else if ( mediaTrash ) {
							model.set( 'status', 'trash' );
							changed.push( model.save() );
							removed.push( model );
						} else {
							model.destroy({wait: true});
						}
					} );

					if ( changed.length ) {
						selection.remove( removed );

						$.when.apply( null, changed ).then( _.bind( function() {
							library._requery( true );
							this.controller.trigger( 'selection:action:done' );
						}, this ) );
					} else {
						this.controller.trigger( 'selection:action:done' );
					}
				}
			}).render() );

			if ( mediaTrash ) {
				this.toolbar.set( 'deleteSelectedPermanentlyButton', new wp.media.view.DeleteSelectedPermanentlyButton({
					filters: Filters,
					style: 'link button-link-delete',
					disabled: true,
					text: l10n.deletePermanently,
					controller: this.controller,
					priority: -55,
					click: function() {
						var removed = [],
							destroy = [],
							selection = this.controller.state().get( 'selection' );

						if ( ! selection.length || ! window.confirm( l10n.warnBulkDelete ) ) {
							return;
						}

						selection.each( function( model ) {
							if ( ! model.get( 'nonces' )['delete'] ) {
								removed.push( model );
								return;
							}

							destroy.push( model );
						} );

						if ( removed.length ) {
							selection.remove( removed );
						}

						if ( destroy.length ) {
							$.when.apply( null, destroy.map( function (item) {
								return item.destroy();
							} ) ).then( _.bind( function() {
								this.controller.trigger( 'selection:action:done' );
							}, this ) );
						}
					}
				}).render() );
			}

		} else if ( this.options.date ) {
			// DateFilter is a <select>, a visually hidden label element needs to be rendered before.
			this.toolbar.set( 'dateFilterLabel', new wp.media.view.Label({
				value: l10n.filterByDate,
				attributes: {
					'for': 'media-attachment-date-filters'
				},
				priority: -75
			}).render() );
			this.toolbar.set( 'dateFilter', new wp.media.view.DateFilter({
				controller: this.controller,
				model:      this.collection.props,
				priority: -75
			}).render() );
		}

		if ( this.options.search ) {
			// Search is an input, a visually hidden label element needs to be rendered before.
			this.toolbar.set( 'searchLabel', new wp.media.view.Label({
				value: l10n.searchLabel,
				className: 'media-search-input-label',
				attributes: {
					'for': 'media-search-input'
				},
				priority:   60
			}).render() );
			this.toolbar.set( 'search', new wp.media.view.Search({
				controller: this.controller,
				model:      this.collection.props,
				priority:   60
			}).render() );
		}

		if ( this.options.dragInfo ) {
			this.toolbar.set( 'dragInfo', new View({
				el: $( '<div class="instructions">' + l10n.dragInfo + '</div>' )[0],
				priority: -40
			}) );
		}

		if ( this.options.suggestedWidth && this.options.suggestedHeight ) {
			this.toolbar.set( 'suggestedDimensions', new View({
				el: $( '<div class="instructions">' + l10n.suggestedDimensions.replace( '%1$s', this.options.suggestedWidth ).replace( '%2$s', this.options.suggestedHeight ) + '</div>' )[0],
				priority: -40
			}) );
		}
	},

	updateContent: function() {
		var view = this,
			noItemsView;

		if ( this.controller.isModeActive( 'grid' ) ) {
			// Usually the media library.
			noItemsView = view.attachmentsNoResults;
		} else {
			// Usually the media modal.
			noItemsView = view.uploader;
		}

		if ( ! this.collection.length ) {
			this.toolbar.get( 'spinner' ).show();
			this.dfd = this.collection.more().done( function() {
				if ( ! view.collection.length ) {
					noItemsView.$el.removeClass( 'hidden' );
				} else {
					noItemsView.$el.addClass( 'hidden' );
				}
				view.toolbar.get( 'spinner' ).hide();
			} );
		} else {
			noItemsView.$el.addClass( 'hidden' );
			view.toolbar.get( 'spinner' ).hide();
		}
	},

	createUploader: function() {
		this.uploader = new wp.media.view.UploaderInline({
			controller: this.controller,
			status:     false,
			message:    this.controller.isModeActive( 'grid' ) ? '' : l10n.noItemsFound,
			canClose:   this.controller.isModeActive( 'grid' )
		});

		this.uploader.$el.addClass( 'hidden' );
		this.views.add( this.uploader );
	},

	toggleUploader: function() {
		if ( this.uploader.$el.hasClass( 'hidden' ) ) {
			this.uploader.show();
		} else {
			this.uploader.hide();
		}
	},

	/**
	 * Creates the Attachments wrapper view.
	 *
	 * @since 5.8.0
	 *
	 * @return {void}
	 */
	createAttachmentsWrapperView: function() {
		this.attachmentsWrapper = new wp.media.View( {
			className: 'attachments-wrapper'
		} );

		// Create the list of attachments.
		this.views.add( this.attachmentsWrapper );
		this.createAttachments();
	},

	createAttachments: function() {
		this.attachments = new wp.media.view.Attachments({
			controller:           this.controller,
			collection:           this.collection,
			selection:            this.options.selection,
			model:                this.model,
			sortable:             this.options.sortable,
			scrollElement:        this.options.scrollElement,
			idealColumnWidth:     this.options.idealColumnWidth,

			// The single `Attachment` view to be used in the `Attachments` view.
			AttachmentView: this.options.AttachmentView
		});

		// Add keydown listener to the instance of the Attachments view.
		this.controller.on( 'attachment:keydown:arrow',     _.bind( this.attachments.arrowEvent, this.attachments ) );
		this.controller.on( 'attachment:details:shift-tab', _.bind( this.attachments.restoreFocus, this.attachments ) );

		this.views.add( '.attachments-wrapper', this.attachments );

		if ( this.controller.isModeActive( 'grid' ) ) {
			this.attachmentsNoResults = new View({
				controller: this.controller,
				tagName: 'p'
			});

			this.attachmentsNoResults.$el.addClass( 'hidden no-media' );
			this.attachmentsNoResults.$el.html( l10n.noMedia );

			this.views.add( this.attachmentsNoResults );
		}
	},

	/**
	 * Creates the load more button and attachments counter view.
	 *
	 * @since 5.8.0
	 *
	 * @return {void}
	 */
	createLoadMoreView: function() {
		var view = this;

		this.loadMoreWrapper = new View( {
			controller: this.controller,
			className: 'load-more-wrapper'
		} );

		this.loadMoreCount = new View( {
			controller: this.controller,
			tagName: 'p',
			className: 'load-more-count hidden'
		} );

		this.loadMoreButton = new wp.media.view.Button( {
			text: __( 'Load more' ),
			className: 'load-more hidden',
			style: 'primary',
			size: '',
			click: function() {
				view.loadMoreAttachments();
			}
		} );

		this.loadMoreSpinner = new wp.media.view.Spinner();

		this.loadMoreJumpToFirst = new wp.media.view.Button( {
			text: __( 'Jump to first loaded item' ),
			className: 'load-more-jump hidden',
			size: '',
			click: function() {
				view.jumpToFirstAddedItem();
			}
		} );

		this.views.add( '.attachments-wrapper', this.loadMoreWrapper );
		this.views.add( '.load-more-wrapper', this.loadMoreSpinner );
		this.views.add( '.load-more-wrapper', this.loadMoreCount );
		this.views.add( '.load-more-wrapper', this.loadMoreButton );
		this.views.add( '.load-more-wrapper', this.loadMoreJumpToFirst );
	},

	/**
	 * Updates the Load More view. This function is debounced because the
	 * collection updates multiple times at the add, remove, and reset events.
	 * We need it to run only once, after all attachments are added or removed.
	 *
	 * @since 5.8.0
	 *
	 * @return {void}
	 */
	updateLoadMoreView: _.debounce( function() {
		// Ensure the load more view elements are initially hidden at each update.
		this.loadMoreButton.$el.addClass( 'hidden' );
		this.loadMoreCount.$el.addClass( 'hidden' );
		this.loadMoreJumpToFirst.$el.addClass( 'hidden' ).prop( 'disabled', true );

		if ( ! this.collection.getTotalAttachments() ) {
			return;
		}

		if ( this.collection.length ) {
			this.loadMoreCount.$el.text(
				/* translators: 1: Number of displayed attachments, 2: Number of total attachments. */
				sprintf(
					__( 'Showing %1$s of %2$s media items' ),
					this.collection.length,
					this.collection.getTotalAttachments()
				)
			);

			this.loadMoreCount.$el.removeClass( 'hidden' );
		}

		/*
		 * Notice that while the collection updates multiple times hasMore() may
		 * return true when it's actually not true.
		 */
		if ( this.collection.hasMore() ) {
			this.loadMoreButton.$el.removeClass( 'hidden' );
		}

		// Find the media item to move focus to. The jQuery `eq()` index is zero-based.
		this.firstAddedMediaItem = this.$el.find( '.attachment' ).eq( this.firstAddedMediaItemIndex );

		// If there's a media item to move focus to, make the "Jump to" button available.
		if ( this.firstAddedMediaItem.length ) {
			this.firstAddedMediaItem.addClass( 'new-media' );
			this.loadMoreJumpToFirst.$el.removeClass( 'hidden' ).prop( 'disabled', false );
		}

		// If there are new items added, but no more to be added, move focus to Jump button.
		if ( this.firstAddedMediaItem.length && ! this.collection.hasMore() ) {
			this.loadMoreJumpToFirst.$el.trigger( 'focus' );
		}
	}, 10 ),

	/**
	 * Loads more attachments.
	 *
	 * @since 5.8.0
	 *
	 * @return {void}
	 */
	loadMoreAttachments: function() {
		var view = this;

		if ( ! this.collection.hasMore() ) {
			return;
		}

		/*
		 * The collection index is zero-based while the length counts the actual
		 * amount of items. Thus the length is equivalent to the position of the
		 * first added item.
		 */
		this.firstAddedMediaItemIndex = this.collection.length;

		this.$el.addClass( 'more-loaded' );
		this.collection.each( function( attachment ) {
			var attach_id = attachment.attributes.id;
			$( '[data-id="' + attach_id + '"]' ).addClass( 'found-media' );
		});

		view.loadMoreSpinner.show();
		this.collection.once( 'attachments:received', function() {
			view.loadMoreSpinner.hide();
		} );
		this.collection.more();
	},

	/**
	 * Moves focus to the first new added item.	.
	 *
	 * @since 5.8.0
	 *
	 * @return {void}
	 */
	jumpToFirstAddedItem: function() {
		// Set focus on first added item.
		this.firstAddedMediaItem.focus();
	},

	createAttachmentsHeading: function() {
		this.attachmentsHeading = new wp.media.view.Heading( {
			text: l10n.attachmentsList,
			level: 'h2',
			className: 'media-views-heading screen-reader-text'
		} );
		this.views.add( this.attachmentsHeading );
	},

	createSidebar: function() {
		var options = this.options,
			selection = options.selection,
			sidebar = this.sidebar = new wp.media.view.Sidebar({
				controller: this.controller
			});

		this.views.add( sidebar );

		if ( this.controller.uploader ) {
			sidebar.set( 'uploads', new wp.media.view.UploaderStatus({
				controller: this.controller,
				priority:   40
			}) );
		}

		selection.on( 'selection:single', this.createSingle, this );
		selection.on( 'selection:unsingle', this.disposeSingle, this );

		if ( selection.single() ) {
			this.createSingle();
		}
	},

	createSingle: function() {
		var sidebar = this.sidebar,
			single = this.options.selection.single();

		sidebar.set( 'details', new wp.media.view.Attachment.Details({
			controller: this.controller,
			model:      single,
			priority:   80
		}) );

		sidebar.set( 'compat', new wp.media.view.AttachmentCompat({
			controller: this.controller,
			model:      single,
			priority:   120
		}) );

		if ( this.options.display ) {
			sidebar.set( 'display', new wp.media.view.Settings.AttachmentDisplay({
				controller:   this.controller,
				model:        this.model.display( single ),
				attachment:   single,
				priority:     160,
				userSettings: this.model.get('displayUserSettings')
			}) );
		}

		// Show the sidebar on mobile.
		if ( this.model.id === 'insert' ) {
			sidebar.$el.addClass( 'visible' );
		}
	},

	disposeSingle: function() {
		var sidebar = this.sidebar;
		sidebar.unset('details');
		sidebar.unset('compat');
		sidebar.unset('display');
		// Hide the sidebar on mobile.
		sidebar.$el.removeClass( 'visible' );
	}
});

module.exports = AttachmentsBrowser;
