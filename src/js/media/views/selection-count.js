var View = wp.media.View,
	_n = wp.i18n._n,
	sprintf = wp.i18n.sprintf,
	SelectionCount;

/**
 * wp.media.view.SelectionCount
 *
 * Displays the number of selected items during bulk select mode in the
 * Media Library grid view.
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
SelectionCount = View.extend(/** @lends wp.media.view.SelectionCount.prototype */{
	tagName:   'div',
	className: 'selection-count media-button hidden',

	initialize: function() {
		this.controller.on( 'selection:toggle', this.updateCount, this );
		this.controller.on( 'select:activate', this.updateCount, this );
	},

	updateCount: function() {
		var count = this.controller.state().get( 'selection' ).length;

		this.$el.text(
			/* translators: %s: Number of selected media items. */
			sprintf( _n( '%s item selected', '%s items selected', count ), count )
		);
	},

	render: function() {
		View.prototype.render.apply( this, arguments );
		this.updateCount();
		return this;
	}
});

module.exports = SelectionCount;
