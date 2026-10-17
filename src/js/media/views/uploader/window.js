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

		this.controller.on( 'activate', this.refresh, this );

		this.controller.on( 'detach', function() {
			this.$browser.remove();
		}, this );
	},

	refresh: function() {
		if ( this.uploader ) {
			this.uploader.refresh();
		}
	},

	ready: function() {
		var postId = wp.media.view.settings.post.id,
			dropzone, blockDrop;

		// If the uploader already exists, bail.
		if ( this.uploader ) {
			return;
		}

		if ( postId ) {
			this.options.uploader.params.post_id = postId;
		}
		this.uploader = new wp.Uploader( this.options.uploader );

		dropzone = this.uploader.dropzone;
		dropzone.on( 'dropzone:enter', _.bind( this.show, this ) );
		dropzone.on( 'dropzone:leave', _.bind( this.hide, this ) );

		// Ignore files dragged onto states that don't support uploading, e.g. cropping.
		// Listen in the capture phase to run before the uploader's own drag and drop handlers.
		blockDrop = _.bind( this.blockDrop, this );
		dropzone.each( function() {
			var element = this;

			_.each( [ 'dragenter', 'dragover', 'drop' ], function( type ) {
				element.addEventListener( type, blockDrop, true );
			} );
		} );

		$( this.uploader ).on( 'uploader:ready', _.bind( this._ready, this ) );
	},

	/**
	 * Prevents dropping files when the current state doesn't support uploading.
	 *
	 * States opt out of uploading by setting their `uploader` attribute to false.
	 *
	 * @since 7.2.0
	 *
	 * @param {DragEvent} event The drag event.
	 */
	blockDrop: function( event ) {
		var state = this.controller.state();

		if ( ! state || false !== state.get( 'uploader' ) ) {
			return;
		}

		// Only block files; other content, such as text, can still be dragged.
		if ( ! event.dataTransfer || -1 === _.indexOf( event.dataTransfer.types, 'Files' ) ) {
			return;
		}

		event.stopPropagation();
		event.preventDefault();
		event.dataTransfer.dropEffect = 'none';
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
