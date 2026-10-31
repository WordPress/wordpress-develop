/**
 * wp.media.view.PriorityList
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
var PriorityList = wp.media.View.extend(/** @lends wp.media.view.PriorityList.prototype */{
	tagName:   'div',

	initialize: function() {
		this._views = {};

		this.set( _.extend( {}, this._views, this.options.views ), { silent: true });
		delete this.options.views;

		if ( ! this.options.silent ) {
			this.render();
		}
	},
	/**
	 * Adds a view to the list, sorted by its priority.
	 *
	 * @param {string}               id      The ID of the view to set.
	 * @param {wp.media.View|Object} view    The view to set.
	 * @param {Object}               options The options for setting the view.
	 * @return {wp.media.view.PriorityList} Returns itself to allow chaining.
	 */
	set: function( id, view, options ) {
		var priority, views, index;

		options = options || {};

		// Accept an object with an `id` : `view` mapping.
		if ( _.isObject( id ) ) {
			_.each( id, function( view, id ) {
				this.set( id, view );
			}, this );
			return this;
		}

		if ( ! (view instanceof Backbone.View) ) {
			view = this.toView( view, id, options );
		}
		view.controller = view.controller || this.controller;

		this.unset( id );

		priority = view.options.priority || 10;
		views = this.views.get() || [];

		_.find( views, function( existing, i ) {
			if ( existing.options.priority > priority ) {
				index = i;
				return true;
			}
		});

		this._views[ id ] = view;
		this.views.add( view, {
			at: _.isNumber( index ) ? index : views.length || 0
		});

		return this;
	},
	/**
	 * Retrieves a view by its ID.
	 *
	 * @param {string} id The ID of the view to retrieve.
	 * @return {wp.media.View} Returns the view if found, otherwise undefined.
	 */
	get: function( id ) {
		return this._views[ id ];
	},
	/**
	 * Removes a view by its ID.
	 *
	 * @param {string} id The ID of the view to remove.
	 * @return {wp.media.view.PriorityList} Returns itself to allow chaining.
	 */
	unset: function( id ) {
		var view = this.get( id );

		if ( view ) {
			view.remove();
		}

		delete this._views[ id ];
		return this;
	},
	/**
	 * Creates a view from an object of options.
	 *
	 * @param {Object} options The options for creating the view.
	 * @return {wp.media.View} Returns the created view.
	 */
	toView: function( options ) {
		return new wp.media.View( options );
	}
});

module.exports = PriorityList;
