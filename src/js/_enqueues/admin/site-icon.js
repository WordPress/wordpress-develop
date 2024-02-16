/**
 * Handle the site icon setting in options-general.php.
 *
 * @since 6.5.0
 * @output wp-admin/js/site-icon.js
 */

/* global jQuery, wp */

( function( $ ) {
	/**
	 * Calculates image selection options based on the attachment dimensions.
	 *
	 * @since 6.5.0
	 *
	 * @param {Object} attachment The attachment object representing the image.
	 * @return {Object} The image selection options.
	 */
	function calculateImageSelectOptions( attachment ) {
		var realWidth = attachment.get( 'width' ),
			realHeight = attachment.get( 'height' ),
			xInit = 512,
			yInit = 512,
			ratio = xInit / yInit,
			xImg = xInit,
			yImg = yInit,
			x1,
			y1,
			imgSelectOptions;

		if ( realWidth / realHeight > ratio ) {
			yInit = realHeight;
			xInit = yInit * ratio;
		} else {
			xInit = realWidth;
			yInit = xInit / ratio;
		}

		x1 = ( realWidth - xInit ) / 2;
		y1 = ( realHeight - yInit ) / 2;

		imgSelectOptions = {
			aspectRatio: xInit + ':' + yInit,
			handles: true,
			keys: true,
			instance: true,
			persistent: true,
			imageWidth: realWidth,
			imageHeight: realHeight,
			minWidth: xImg > xInit ? xInit : xImg,
			minHeight: yImg > yInit ? yInit : yImg,
			x1: x1,
			y1: y1,
			x2: xInit + x1,
			y2: yInit + y1
		};

		return imgSelectOptions;
	}

	/**
	 * Initializes the media frame for selecting or cropping an image.
	 *
	 * @since 6.5.0
	 */
	( function() {
		var frame;

		// Build the choose from library frame.
		$( '#choose-from-library-button' ).on( 'click', function() {
			var $el = $( this );

			// Create the media frame.
			frame = wp.media({
				button: {

					// Set the text of the button.
					text: $el.data( 'update' ),

					// Don't close, we might need to crop.
					close: false
				},
				states: [
					new wp.media.controller.Library({
						title: $el.data( 'choose-text' ),
						library: wp.media.query({ type: 'image' }),
						date: false,
						suggestedWidth: $el.data( 'size' ),
						suggestedHeight: $el.data( 'size' )
					}),
					new wp.media.controller.SiteIconCropper({
						control: {
							params: {
								width: $el.data( 'size' ),
								height: $el.data( 'size' )
							}
						},
						imgSelectOptions: calculateImageSelectOptions
					})
				]
			});

			frame.on( 'cropped', function( attachment ) {
				$( '#site_icon_hidden_field' ).val( attachment.id );
				switchToUpdate( attachment );
				frame.close();
				// Start over with a frame that is so fresh and so clean clean.
				frame = null;
			});

			// When an image is selected, run a callback.
			frame.on( 'select', function() {

				// Grab the selected attachment.
				var attachment = frame.state().get( 'selection' ).first();

				if (
					attachment.attributes.height === $el.data( 'size' ) &&
					$el.data( 'size' ) === attachment.attributes.width
				) {

					// Set the value of the hidden input to the attachment id.
					$( '#site_icon_hidden_field' ).val( attachment.id );
					switchToUpdate( attachment.attributes );
					frame.close();
				} else {
					frame.setState( 'cropper' );
				}
			});

			frame.open();
		});
	})();

	/**
	 * Updates the UI when a site icon is selected.
	 *
	 * @since 6.5.0
	 *
	 * @param {array} attributes The attributes for the attachment.
	 */
	function switchToUpdate( attributes ) {
		var $chooseButton = $( '#choose-from-library-button' ),
			$iconPreview = $( '#site-icon-preview' ),
			i18nAltString;

			if ( attributes.alt ) {
				i18nAltString = wp.i18n.sprintf(
					/* translators: %s: The selected image alt text. */
					wp.i18n.__( 'Current image: %s' ),
					attributes.alt
				);
			} else {
				i18nAltString = wp.i18n.sprintf(
					/* Translators: %s: The selected image filename. */
					wp.i18n.__( 'The current image has no alternative text. The file name is: %s' ),
					attributes.filename
				);
			}

			// Set site-icon-img src to the url and remove the hidden class.
			$iconPreview.find( 'img' ).not( '.browser-preview' )
			.each( function( i, img ) {
				$( img )
				.attr({
					'src': attributes.url,
					'alt': i18nAltString 
				});
			});
			$iconPreview.removeClass( 'hidden' );

		// Remove hidden class from the remove button.
		$( '#js-remove-site-icon' ).removeClass( 'hidden' );

		// If the choose button is not in the update state, swap the classes.
		if ( $chooseButton.attr( 'data-state' ) !== '1' ) {
			$chooseButton.attr({
				'class': $chooseButton.attr( 'data-alt-classes' ),
				'data-alt-classes': $chooseButton.attr( 'class' ),
				'data-state': '1'
			});
		}

		// Swap the text of the choose button.
		$chooseButton.text(
			$chooseButton.attr( 'data-update-text' )
		);
	}

	/**
	 * Initializes the functionality to remove the site icon.
	 *
	 * @since 6.5.0
	 */
	( function () {
		var $chooseButton = $( '#choose-from-library-button' );
		$( '#js-remove-site-icon' ).on( 'click', function() {
			$( '#site_icon_hidden_field' ).val( 'false' );
			$( '#site-icon-preview' ).toggleClass( 'hidden' );
			$( this ).toggleClass( 'hidden' );

			/**
			 * Resets initial state to the button, for correct visual style and state.
			 * Updates the text of the button.
			 * Sets focus state to the button.
			 */
			$chooseButton
				.attr({
					'class': $chooseButton.attr( 'data-alt-classes' ),
					'data-alt-classes': $chooseButton.attr( 'class' ),
					'data-state': ''
				})
				.text( $chooseButton.attr( 'data-choose-text' ) )
				.trigger( 'focus' );
		});
	})();
})( jQuery );
