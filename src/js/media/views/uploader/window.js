var $ = jQuery,
	UploaderWindow;

/**
 * wp.media.view.UploaderWindow
 *
 * An uploader window that allows for dragging and dropping media.
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 *
 * @param {Object} [options]                   Options hash passed to the view.
 * @param {Object} [options.uploader]          Uploader properties.
 * @param {jQuery} [options.uploader.browser]
 * @param {jQuery} [options.uploader.dropzone] jQuery collection of the dropzone.
 * @param {Object} [options.uploader.params]
 */
UploaderWindow = wp.media.View.extend(/** @lends wp.media.view.UploaderWindow.prototype */{
	tagName:   'div',
	className: 'uploader-window',
	template:  wp.template('uploader-window'),

	initialize: function() {
		var uploader;

		this.$browser = $( '<button type="button" class="browser" />' ).hide().appendTo( 'body' );

		uploader = this.options.uploader = _.defaults( this.options.uploader || {}, {
			dropzone:  this.$el,
			browser:   this.$browser,
			params:    {}
		});

		// Ensure the dropzone is a jQuery collection.
		if ( uploader.dropzone && ! (uploader.dropzone instanceof $) ) {
			uploader.dropzone = $( uploader.dropzone );
		}

		this.controller.on( 'activate change:library', this.refresh, this );

		this.controller.on( 'detach', function() {
			this.$browser.remove();
		}, this );
	},

	refresh: function() {
		if ( this.uploader ) {
			this.updateUploadFilters();
			this.uploader.refresh();
		}
	},

	/**
	 * Restricts uploads to the active library's media types.
	 *
	 * @since 7.2.0
	 */
	updateUploadFilters: function() {
		var uploader = this.uploader.uploader,
			state = this.controller.state(),
			library = state && state.get( 'library' ),
			types = library && library.props.get( 'type' ),
			extensions = null;

		if ( this._uploadLibrary !== library ) {
			if ( this._uploadLibrary ) {
				this.stopListening( this._uploadLibrary.props );
			}
			this._uploadLibrary = library;
			if ( library ) {
				this.listenTo( library.props, 'change:type', this.refresh );
			}
		}

		types = _.chain( _.isArray( types ) ? types : [ types ] )
			.map( function( type ) { return type ? type.split( ',' ) : []; } )
			.flatten()
			.map( function( type ) { return type.trim().toLowerCase(); } )
			.compact()
			.value();

		if ( types.length && ! _.contains( types, '*' ) && wp.Uploader.mimeTypes ) {
			extensions = [];
			_.each( wp.Uploader.mimeTypes, function( mime, pattern ) {
				var matches;

				mime = mime.toLowerCase();
				matches = _.some( types, function( type ) {
					return type === mime || type === mime.split( '/' )[0] || type === mime.split( '/' )[0] + '/*';
				} );

				if ( matches ) {
					extensions = extensions.concat( pattern.toLowerCase().split( '|' ) );
				}
			} );
			extensions = _.uniq( extensions );
		}

		// Context is independent of plugin-owned filters, including identical MIME lists.
		if ( ! _.isEqual( extensions, uploader.getOption( 'context_extensions' ) ) ) {
			uploader.setOption( 'context_extensions', extensions );
		}
	},

	ready: function() {
		var postId = wp.media.view.settings.post.id,
			dropzone;

		// If the uploader already exists, bail.
		if ( this.uploader ) {
			return;
		}

		if ( postId ) {
			this.options.uploader.params.post_id = postId;
		}
		this.uploader = new wp.Uploader( this.options.uploader );
		this.refresh();

		dropzone = this.uploader.dropzone;
		dropzone.on( 'dropzone:enter', _.bind( this.show, this ) );
		dropzone.on( 'dropzone:leave', _.bind( this.hide, this ) );

		$( this.uploader ).on( 'uploader:ready', _.bind( this._ready, this ) );
	},

	_ready: function() {
		this.controller.trigger( 'uploader:ready' );
	},

	show: function() {
		var $el = this.$el.show();

		// Ensure that the animation is triggered by waiting until
		// the transparent element is painted into the DOM.
		_.defer( function() {
			$el.css({ opacity: 1 });
		});
	},

	hide: function() {
		var $el = this.$el.css({ opacity: 0 });

		wp.media.transition( $el ).done( function() {
			// Transition end events are subject to race conditions.
			// Make sure that the value is set as intended.
			if ( '0' === $el.css('opacity') ) {
				$el.hide();
			}
		});

		// https://core.trac.wordpress.org/ticket/27341
		_.delay( function() {
			if ( '0' === $el.css('opacity') && $el.is(':visible') ) {
				$el.hide();
			}
		}, 500 );
	}
});

module.exports = UploaderWindow;
