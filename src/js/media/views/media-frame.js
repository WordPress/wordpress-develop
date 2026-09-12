var Frame = wp.media.view.Frame,
	l10n = wp.media.view.l10n,
	$ = jQuery,
	MediaFrame;

/**
 * wp.media.view.MediaFrame
 *
 * The frame used to create the media modal.
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.view.Frame
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 * @mixes wp.media.controller.StateMachine
 */
MediaFrame = Frame.extend(/** @lends wp.media.view.MediaFrame.prototype */{
	className: 'media-frame',
	template:  wp.template('media-frame'),
	regions:   ['menu','title','content','toolbar','router'],

	events: {
		'click .media-frame-menu-toggle': 'toggleMenu'
	},

	/**
	 * Initializes the media frame.
	 *
	 * @constructs
	 */
	initialize: function() {
		Frame.prototype.initialize.apply( this, arguments );

		_.defaults( this.options, {
			title:    l10n.mediaFrameDefaultTitle,
			modal:    true,
			uploader: true
		});

		// Ensure core UI is enabled.
		this.$el.addClass('wp-core-ui');

		// Initialize modal container view.
		if ( this.options.modal ) {
			this.modal = new wp.media.view.Modal({
				controller: this,
				title:      this.options.title
			});

			this.modal.content( this );
		}

		// Force the uploader off if the upload limit has been exceeded or
		// if the browser isn't supported.
		if ( wp.Uploader.limitExceeded || ! wp.Uploader.browser.supported ) {
			this.options.uploader = false;
		}

		// Initialize window-wide uploader.
		if ( this.options.uploader ) {
			this.uploader = new wp.media.view.UploaderWindow({
				controller: this,
				uploader: {
					dropzone:  this.modal ? this.modal.$el : this.$el,
					container: this.$el
				}
			});
			this.views.set( '.media-frame-uploader', this.uploader );
		}

		this.on( 'attach', _.bind( this.views.ready, this.views ), this );

		// Bind default title creation.
		this.on( 'title:create:default', this.createTitle, this );
		this.title.mode('default');

		// Bind default menu.
		this.on( 'menu:create:default', this.createMenu, this );

		// Set the menu ARIA tab panel attributes when the modal opens.
		this.on( 'open', this.setMenuTabPanelAriaAttributes, this );
		// Set the router ARIA tab panel attributes when the modal opens.
		this.on( 'open', this.setRouterTabPanelAriaAttributes, this );

		// Update the menu ARIA tab panel attributes when the content updates.
		this.on( 'content:render', this.setMenuTabPanelAriaAttributes, this );
		// Update the router ARIA tab panel attributes when the content updates.
		this.on( 'content:render', this.setRouterTabPanelAriaAttributes, this );
	},

	/**
	 * Sets the attributes to be used on the menu ARIA tab panel.
	 *
	 * @since 5.3.0
	 *
	 * @return {void}
	 */
	setMenuTabPanelAriaAttributes: function() {
		var stateId = this.state().get( 'id' ),
			tabPanelEl = this.$el.find( '.media-frame-tab-panel' ),
			ariaLabelledby;

		tabPanelEl.removeAttr( 'role aria-labelledby' );

		if ( this.state().get( 'menu' ) && this.menuView && this.menuView.isVisible ) {
			ariaLabelledby = 'menu-item-' + stateId;

			// Set the tab panel attributes only if the tabs are visible.
			tabPanelEl
				.attr( {
					role: 'tabpanel',
					'aria-labelledby': ariaLabelledby,
				} );
		}
	},

	/**
	 * Sets the attributes to be used on the router ARIA tab panel.
	 *
	 * @since 5.3.0
	 *
	 * @return {void}
	 */
	setRouterTabPanelAriaAttributes: function() {
		var tabPanelEl = this.$el.find( '.media-frame-content' ),
			ariaLabelledby;

		tabPanelEl.removeAttr( 'role aria-labelledby' );

		// Set the tab panel attributes only if the tabs are visible.
		if ( this.state().get( 'router' ) && this.routerView && this.routerView.isVisible && this.content._mode ) {
			ariaLabelledby = 'menu-item-' + this.content._mode;

			tabPanelEl
				.attr( {
					role: 'tabpanel',
					'aria-labelledby': ariaLabelledby,
				} );
		}
	},

	/**
	 * Renders the media frame.
	 *
	 * @return {wp.media.view.MediaFrame} Returns itself to allow chaining.
	 */
	render: function() {
		// Activate the default state if no active state exists.
		if ( ! this.state() && this.options.state ) {
			this.setState( this.options.state );
		}
		/**
		 * call 'render' directly on the parent class
		 */
		return Frame.prototype.render.apply( this, arguments );
	},
	/**
	 * Creates the title view.
	 *
	 * @param {Object} title The title object for creating the title view.
	 * @this wp.media.controller.Region
	 */
	createTitle: function( title ) {
		title.view = new wp.media.View({
			controller: this,
			tagName: 'h1'
		});
	},
	/**
	 * Creates the menu view.
	 *
	 * @param {Object} menu The menu object for creating the menu view.
	 * @this wp.media.controller.Region
	 */
	createMenu: function( menu ) {
		menu.view = new wp.media.view.Menu({
			controller: this,

			attributes: {
				role:               'tablist',
				'aria-orientation': 'vertical'
			}
		});

		this.menuView = menu.view;
	},

	/**
	 * Toggles the menu visibility.
	 *
	 * @param {JQuery.Event} event The click event.
	 */
	toggleMenu: function( event ) {
		var menu = this.$el.find( '.media-menu' );

		menu.toggleClass( 'visible' );
		$( event.target ).attr( 'aria-expanded', menu.hasClass( 'visible' ) );
	},

	/**
	 * Creates the toolbar view.
	 *
	 * @param {Object} toolbar The toolbar object for creating the toolbar view.
	 * @this wp.media.controller.Region
	 */
	createToolbar: function( toolbar ) {
		toolbar.view = new wp.media.view.Toolbar({
			controller: this
		});
	},
	/**
	 * Creates the router view.
	 *
	 * @param {Object} router The router object for creating the router view.
	 * @this wp.media.controller.Region
	 */
	createRouter: function( router ) {
		router.view = new wp.media.view.Router({
			controller: this,

			attributes: {
				role:               'tablist',
				'aria-orientation': 'horizontal'
			}
		});

		this.routerView = router.view;
	},
	/**
	 * Creates the iframe states.
	 *
	 * @param {Object} options The options for creating the iframe states.
	 */
	createIframeStates: function( options ) {
		var settings = wp.media.view.settings,
			tabs = settings.tabs,
			tabUrl = settings.tabUrl,
			$postId;

		if ( ! tabs || ! tabUrl ) {
			return;
		}

		// Add the post ID to the tab URL if it exists.
		$postId = $('#post_ID');
		if ( $postId.length ) {
			tabUrl += '&post_id=' + $postId.val();
		}

		// Generate the tab states.
		_.each( tabs, function( title, id ) {
			this.state( 'iframe:' + id ).set( _.defaults({
				tab:     id,
				src:     tabUrl + '&tab=' + id,
				title:   title,
				content: 'iframe',
				menu:    'default'
			}, options ) );
		}, this );

		this.on( 'content:create:iframe', this.iframeContent, this );
		this.on( 'content:deactivate:iframe', this.iframeContentCleanup, this );
		this.on( 'menu:render:default', this.iframeMenu, this );
		this.on( 'open', this.hijackThickbox, this );
		this.on( 'close', this.restoreThickbox, this );
	},

	/**
	 * Creates the iframe content view.
	 *
	 * @param {Object} content The content object for creating the iframe content view.
	 * @this wp.media.controller.Region
	 */
	iframeContent: function( content ) {
		this.$el.addClass('hide-toolbar');
		content.view = new wp.media.view.Iframe({
			controller: this
		});
	},

	/**
	 * Cleans up the iframe content view.
	 */
	iframeContentCleanup: function() {
		this.$el.removeClass('hide-toolbar');
	},

	/**
	 * Creates the iframe menu.
	 *
	 * @param {wp.media.view.Menu} view The menu view.
	 */
	iframeMenu: function( view ) {
		var views = {};

		if ( ! view ) {
			return;
		}

		_.each( wp.media.view.settings.tabs, function( title, id ) {
			views[ 'iframe:' + id ] = {
				text: this.state( 'iframe:' + id ).get('title'),
				priority: 200
			};
		}, this );

		view.set( views );
	},

	/**
	 * Hijacks the Thickbox close function to close the media modal.
	 */
	hijackThickbox: function() {
		var frame = this;

		if ( ! window.tb_remove || this._tb_remove ) {
			return;
		}

		this._tb_remove = window.tb_remove;
		window.tb_remove = function() {
			frame.close();
			frame.reset();
			frame.setState( frame.options.state );
			frame._tb_remove.call( window );
		};
	},

	/**
	 * Restores the Thickbox close function.
	 */
	restoreThickbox: function() {
		if ( ! this._tb_remove ) {
			return;
		}

		window.tb_remove = this._tb_remove;
		delete this._tb_remove;
	}
});

// Map some of the modal's methods to the frame.
_.each(['open','close','attach','detach','escape'], function( method ) {
	/**
	 * Opens the media frame modal.
	 *
	 * @function open
	 * @memberOf wp.media.view.MediaFrame
	 * @instance
	 *
	 * @return {wp.media.view.MediaFrame} Returns itself to allow chaining.
	 */
	/**
	 * Closes the media frame modal.
	 *
	 * @function close
	 * @memberOf wp.media.view.MediaFrame
	 * @instance
	 *
	 * @return {wp.media.view.MediaFrame} Returns itself to allow chaining.
	 */
	/**
	 * Attaches the media frame to the DOM.
	 *
	 * @function attach
	 * @memberOf wp.media.view.MediaFrame
	 * @instance
	 *
	 * @return {wp.media.view.MediaFrame} Returns itself to allow chaining.
	 */
	/**
	 * Detaches the media frame from the DOM.
	 *
	 * @function detach
	 * @memberOf wp.media.view.MediaFrame
	 * @instance
	 *
	 * @return {wp.media.view.MediaFrame} Returns itself to allow chaining.
	 */
	/**
	 * Triggers the escape action on the media frame modal.
	 *
	 * @function escape
	 * @memberOf wp.media.view.MediaFrame
	 * @instance
	 *
	 * @return {wp.media.view.MediaFrame} Returns itself to allow chaining.
	 */
	MediaFrame.prototype[ method ] = function() {
		if ( this.modal ) {
			this.modal[ method ].apply( this.modal, arguments );
		}
		return this;
	};
});

module.exports = MediaFrame;
