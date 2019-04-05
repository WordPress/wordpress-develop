/**
 * wp.media.view.Heading
 *
 * A reusable heading component for the media library
 *
 * Used to add accessibility friendly headers in the media library/modal.
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
var Heading = wp.media.View.extend( {
	tagName: function() {
		return this.options.level || 'h1';
	},
	className: 'media-views-heading',

	initialize: function() {

		if ( this.options.className ) {
			this.$el.addClass( this.options.className );
		}

		this.text = this.options.text;
	},

	render: function() {
		this.$el.html( this.text );
		return this;
	}
} );

module.exports = Heading;
