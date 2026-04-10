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
	tagName:	 'span',
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

		this.error = $('<div class="notice notice-error embed-url-error"><p></p></div>')[0];

		this.$error = $(this.error);
		this.$error.hide();

		this.$el.append([ this.input, this.spinner, this.error ]);

		this.listenTo( this.model, 'change:url', this.render );

		if ( this.model.get( 'url' ) ) {
			_.delay( _.bind( function () {
				this.model.trigger( 'change:url' );
			}, this ), 500 );
		}

		this.updateUrl = _.debounce( this.updateUrl, 500 );
	},
	/**
	 * @return {wp.media.view.EmbedUrl} Returns itself to allow chaining.
	 */
	render: function() {
		var $input = this.$input;

		if ( $input.is(':focus') ) {
			return;
		}

		if ( this.model.get( 'url' ) ) {
			this.input.value = this.model.get('url');
		} else {
			this.input.setAttribute( 'placeholder', 'https://' );
		}

		/**
		 * Call `render` directly on parent class with passed arguments
		 */
		View.prototype.render.apply( this, arguments );
		return this;
	},

	url: function( event ) {
		var $el = $( event.target );
		var url = $el.val() || '';
		var valid = this.isValidUrlInput( event.target );
		this.updateUrl( url, valid );
	},

	isValidUrlInput: function ( el ) {
		var url = ( el.value || '' ).trim();
		try {
			url = new URL( url );
			return [ 'http:', 'https:' ].includes(url.protocol);
		} catch ( e ) {
			return false;
		}
	},

	updateUrl: function ( url, valid ) {
		if ( valid ) {
			this.model.set( 'url', url.trim() );
			this.$error.hide();
		} else {
			if ( url.length > 0 ) {
				this.model.set( 'url', '' );
				this.$error.find( 'p' ).text( l10n.invalidUrl );
				this.$error.show();
			} else {
				this.$error.hide();
			}
		}
	}
});

module.exports = EmbedUrl;
