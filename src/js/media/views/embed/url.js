var View = wp.media.View,
	$ = jQuery,
	l10n = wp.media.view.l10n,
	EmbedUrl;

/**
 * wp.media.view.EmbedUrl
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
EmbedUrl = View.extend(/** @lends wp.media.view.EmbedUrl.prototype */{
	tagName:   'span',
	className: 'embed-url',

	events: {
		'input': 'url'
	},

	initialize: function() {
		this.$input = $( '<input id="embed-url-field" type="url" />' )
			.attr( 'aria-label', l10n.insertFromUrlTitle )
			.val( this.model.get('url') );
		this.input = this.$input[0];

		this.spinner = $('<span class="spinner" />')[0];
		this.$el.append([ this.input, this.spinner ]);

		this.listenTo( this.model, 'change:url', this.render );

		if ( this.model.get( 'url' ) ) {
			_.delay( _.bind( function () {
				this.model.trigger( 'change:url' );
			}, this ), 500 );
		}
	},
	/**
	 * @return {wp.media.view.EmbedUrl} Returns itself to allow chaining.
	 */
	render: function() {
		var $input = this.$input;

		if ( $input.is(':focus') ) {
			return;
		}

		this.input.value = this.model.get('url') || 'http://';
		/**
		 * Call `render` directly on parent class with passed arguments
		 */
		View.prototype.render.apply( this, arguments );
		return this;
	},

	url: function( event ) {
		this.model.set( 'url', $.trim( event.target.value ) );
	}
});

module.exports = EmbedUrl;
