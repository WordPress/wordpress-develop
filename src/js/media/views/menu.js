var MenuItem = wp.media.view.MenuItem,
	PriorityList = wp.media.view.PriorityList,
	Menu;

/**
 * wp.media.view.Menu
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.view.PriorityList
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
Menu = PriorityList.extend(/** @lends wp.media.view.Menu.prototype */{
	tagName:   'div',
	className: 'media-menu',
	property:  'state',
	ItemView:  MenuItem,
	region:    'menu',

	attributes: {
		role:               'tablist',
		'aria-orientation': 'horizontal'
	},

	/**
	 * Initializes the menu view.
	 */
	initialize: function() {
		this._views = {};

		this.set( _.extend( {}, this._views, this.options.views ), { silent: true });
		delete this.options.views;

		if ( ! this.options.silent ) {
			this.render();
		}

		// Initialize the Focus Manager.
		this.focusManager = new wp.media.view.FocusManager( {
			el:   this.el,
			mode: 'tabsNavigation'
		} );

		// The menu is always rendered and can be visible or hidden on some frames.
		this.isVisible = true;
	},

	/**
	 * Creates a view for the given options and id.
	 *
	 * @param {Object} options
	 * @param {string} id
	 * @return {wp.media.View} The view instance.
	 */
	toView: function( options, id ) {
		options = options || {};
		options[ this.property ] = options[ this.property ] || id;
		return new this.ItemView( options ).render();
	},

	/**
	 * Updates the menu when the state changes.
	 */
	ready: function() {
		/**
		 * call 'ready' directly on the parent class
		 */
		PriorityList.prototype.ready.apply( this, arguments );
		this.visibility();

		// Set up aria tabs initial attributes.
		this.focusManager.setupAriaTabs();
	},

	/**
	 * Sets the menu items.
	 */
	set: function() {
		/**
		 * call 'set' directly on the parent class
		 */
		PriorityList.prototype.set.apply( this, arguments );
		this.visibility();
	},

	/**
	 * Unsets the menu items.
	 */
	unset: function() {
		/**
		 * call 'unset' directly on the parent class
		 */
		PriorityList.prototype.unset.apply( this, arguments );
		this.visibility();
	},

	/**
	 * Updates the menu visibility.
	 */
	visibility: function() {
		var region = this.region,
			view = this.controller[ region ].get(),
			views = this.views.get(),
			hide = ! views || views.length < 2;

		if ( this === view ) {
			// Flag this menu as hidden or visible.
			this.isVisible = ! hide;
			// Set or remove a CSS class to hide the menu.
			this.controller.$el.toggleClass( 'hide-' + region, hide );
		}
	},
	/**
	 * Selects the menu item with the given id.
	 *
	 * @param {string} id The menu item id.
	 */
	select: function( id ) {
		var view = this.get( id );

		if ( ! view ) {
			return;
		}

		this.deselect();
		view.$el.addClass('active');

		// Set up again the aria tabs initial attributes after the menu updates.
		this.focusManager.setupAriaTabs();
	},

	/**
	 * Deselects the menu items.
	 */
	deselect: function() {
		this.$el.children().removeClass('active');
	},

	/**
	 * Hides the menu item with the given id.
	 *
	 * @param {string} id The menu item id.
	 */
	hide: function( id ) {
		var view = this.get( id );

		if ( ! view ) {
			return;
		}

		view.$el.addClass('hidden');
	},

	/**
	 * Shows the menu item with the given id.
	 *
	 * @param {string} id The menu item id.
	 */
	show: function( id ) {
		var view = this.get( id );

		if ( ! view ) {
			return;
		}

		view.$el.removeClass('hidden');
	}
});

module.exports = Menu;
