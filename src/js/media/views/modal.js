var $ = jQuery,
	Modal;

/**
 * wp.media.view.Modal
 *
 * A modal view, which the media modal uses as its default container.
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
Modal = wp.media.View.extend(/** @lends wp.media.view.Modal.prototype */{
	tagName:  'div',
	template: wp.template('media-modal'),

	events: {
		'click .media-modal-backdrop, .media-modal-close': 'escapeHandler',
		'keydown': 'keydown'
	},

	clickedOpenerEl: null,

	initialize: function() {
		_.defaults( this.options, {
			container:      document.body,
			title:          '',
			propagate:      true,
			hasCloseButton: true
		});

		this.focusManager = new wp.media.view.FocusManager({
			el: this.el
		});
	},
	/**
	 * @return {Object}
	 */
	prepare: function() {
		return {
			title:          this.options.title,
			hasCloseButton: this.options.hasCloseButton
		};
	},

	/**
	 * @return {wp.media.view.Modal} Returns itself to allow chaining.
	 */
	attach: function() {
		if ( this.views.attached ) {
			return this;
		}

		if ( ! this.views.rendered ) {
			this.render();
		}

		this.$el.appendTo( this.options.container );

		// Manually mark the view as attached and trigger ready.
		this.views.attached = true;
		this.views.ready();

		return this.propagate('attach');
	},

	/**
	 * @return {wp.media.view.Modal} Returns itself to allow chaining.
	 */
	detach: function() {
		if ( this.$el.is(':visible') ) {
			this.close();
		}

		this.$el.detach();
		this.views.attached = false;
		return this.propagate('detach');
	},

	/**
	 * @return {wp.media.view.Modal} Returns itself to allow chaining.
	 */
	open: function() {
		var $el = this.$el,
			mceEditor;

		if ( $el.is(':visible') ) {
			return this;
		}

		this.clickedOpenerEl = document.activeElement;

		if ( ! this.views.attached ) {
			this.attach();
		}

		// Disable page scrolling.
		$( 'body' ).addClass( 'modal-open' );

		$el.show();

		// Try to close the onscreen keyboard.
		if ( 'ontouchend' in document ) {
			if ( ( mceEditor = window.tinymce && window.tinymce.activeEditor ) && ! mceEditor.isHidden() && mceEditor.iframeElement ) {
				mceEditor.iframeElement.focus();
				mceEditor.iframeElement.blur();

				setTimeout( function() {
					mceEditor.iframeElement.blur();
				}, 100 );
			}
		}

		// Set initial focus on the content instead of this view element, to avoid page scrolling.
		this.$( '.media-modal' ).trigger( 'focus' );

		// Hide the page content from assistive technologies.
		this.focusManager.setAriaHiddenOnBodyChildren( $el );

		return this.propagate('open');
	},

	/**
	 * @param {Object} options
	 * @return {wp.media.view.Modal} Returns itself to allow chaining.
	 */
	close: function( options ) {
		if ( ! this.views.attached || ! this.$el.is(':visible') ) {
			return this;
		}

		// Pause current audio/video even after closing the modal.
		$( '.mejs-pause button' ).trigger( 'click' );

		// Enable page scrolling.
		$( 'body' ).removeClass( 'modal-open' );

		// Hide the modal element by adding display:none.
		this.$el.hide();

		/*
		 * Make visible again to assistive technologies all body children that
		 * have been made hidden when the modal opened.
		 */
		this.focusManager.removeAriaHiddenFromBodyChildren();

		// Move focus back in useful location once modal is closed.
		if ( null !== this.clickedOpenerEl ) {
			// Move focus back to the element that opened the modal.
			this.clickedOpenerEl.focus();
		} else {
			// Fallback to the admin page main element.
			$( '#wpbody-content' )
				.attr( 'tabindex', '-1' )
				.trigger( 'focus' );
		}

		this.propagate('close');

		if ( options && options.escape ) {
			this.propagate('escape');
		}

		return this;
	},
	/**
	 * @return {wp.media.view.Modal} Returns itself to allow chaining.
	 */
	escape: function() {
		return this.close({ escape: true });
	},
	/**
	 * @param {Object} event
	 */
	escapeHandler: function( event ) {
		event.preventDefault();
		this.escape();
	},

	/**
	 * @param {Array|Object} content Views to register to '.media-modal-content'
	 * @return {wp.media.view.Modal} Returns itself to allow chaining.
	 */
	content: function( content ) {
		this.views.set( '.media-modal-content', content );
		return this;
	},

	/**
	 * Triggers a modal event and if the `propagate` option is set,
	 * forwards events to the modal's controller.
	 *
	 * @param {string} id
	 * @return {wp.media.view.Modal} Returns itself to allow chaining.
	 */
	propagate: function( id ) {
		this.trigger( id );

		if ( this.options.propagate ) {
			this.controller.trigger( id );
		}

		return this;
	},
	/**
	 * @param {Object} event
	 */
	keydown: function( event ) {
		// Close the modal when escape is pressed.
		if ( 27 === event.which && this.$el.is(':visible') ) {
			this.escape();
			event.stopImmediatePropagation();
		}
	}
});

module.exports = Modal;
