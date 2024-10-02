var MenuItem;

/**
 * wp.media.view.MenuItem
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
MenuItem = wp.media.View.extend(/** @lends wp.media.view.MenuItem.prototype */{
	tagName:   'button',
	className: 'media-menu-item',

	attributes: {
		type: 'button',
		role: 'tab'
	},

	events: {
		'click': '_click'
	},

	/**
	 * Allows to override the click event.
	 */
	_click: function() {
		var clickOverride = this.options.click;

		if ( clickOverride ) {
			clickOverride.call( this );
		} else {
			this.click();
		}
	},

	click: function() {
		var state = this.options.state;

		if ( state ) {
			this.controller.setState( state );
			// Toggle the menu visibility in the responsive view.
			this.views.parent.$el.removeClass( 'visible' ); // @todo Or hide on any click, see below.
		}
	},

	/**
	 * @return {wp.media.view.MenuItem} returns itself to allow chaining.
	 */
	render: function() {
		var options = this.options,
			menuProperty = options.state || options.contentMode;

		if ( options.text ) {
			this.$el.text( options.text );
		} else if ( options.html ) {
			this.$el.html( options.html );
		}

		// Set the menu item ID based on the frame state associated to the menu item.
		this.$el.attr( 'id', 'menu-item-' + menuProperty );

		return this;
	}
});

module.exports = MenuItem;
