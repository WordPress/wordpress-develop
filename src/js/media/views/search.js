var Search;

/**
 * wp.media.view.Search
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
Search = wp.media.View.extend(/** @lends wp.media.view.Search.prototype */{
	tagName:   'input',
	className: 'search',
	id:        'media-search-input',

	attributes: {
		type: 'search'
	},

	events: {
		'input': 'search'
	},

	/**
	 * @return {wp.media.view.Search} Returns itself to allow chaining.
	 */
	render: function() {
		this.el.value = this.model.escape('search');
		return this;
	},

	search: _.debounce( function( event ) {
		var searchTerm = event.target.value.trim();

		// Trigger the search only after 2 ASCII characters.
		if ( searchTerm && searchTerm.length > 1 ) {
			this.model.set( 'search', searchTerm );
		} else {
			this.model.unset( 'search' );
		}
	}, 500 )
});

module.exports = Search;
