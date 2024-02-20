/**
 * Handle the site icon setting in options-general.php.
 *
 * @since 6.5.0
 * @output wp-admin/js/site-icon.js
 */

/* global jQuery, wp */

( function( $ ) {
	var $chooseButton = $( '#choose-from-library-button' ),
	$iconPreview = $( '#site-icon-preview' ),
	$browserIconPreview = $( '#browser-icon-preview' ),
	$appIconPreview = $( '#app-icon-preview' ),
	$hiddenDataField = $( '#site_icon_hidden_field' ),
	$removeButton = $( '#js-remove-site-icon' ),
	frame;

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
	$chooseButton.on( 'click', function() {
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
			$hiddenDataField.val( attachment.id );
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
				$hiddenDataField.val( attachment.id );
				switchToUpdate( attachment.attributes );
				frame.close();
			} else {
				frame.setState( 'cropper' );
			}
		});

		frame.open();
	});

	/**
	 * Updates the UI when a site icon is selected.
	 *
	 * @since 6.5.0
	 *
	 * @param {array} attributes The attributes for the attachment.
	 */
	function switchToUpdate( attributes ) {
		var i18nAltString,
		i18nAppAlternativeString,
		i18nBrowserAlternativeString;

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

		i18nAppAlternativeString = wp.i18n.sprintf(
			/* Translators: 1: Prefix used for alternative text of the preview of the site icon. 2: Alternative text of the site icon image, */
			wp.i18n.__( '%1$s: %2$s' ),
			$appIconPreview.data( 'alt-prefix' ),
			i18nAltString
		);

		i18nBrowserAlternativeString = wp.i18n.sprintf(
			/* Translators: 1: Prefix used for alternative text of the preview of the site icon. 2: Alternative text of the site icon image, */
			wp.i18n.__( '%1$s: %2$s' ),
			$browserIconPreview.data( 'alt-prefix' ),
			i18nAltString
		);

		// Set site-icon-img src and alternative text to browser preview.
		$browserIconPreview.attr({
			'src': attributes.url,
			'alt': i18nBrowserAlternativeString 
		});

		// Set site-icon-img src and alternative text to app icon preview.
		$appIconPreview.attr({
			'src': attributes.url,
			'alt': i18nAppAlternativeString 
		});

		// Remove hidden class from icon preview div.
		$iconPreview.removeClass( 'hidden' );

		// Remove hidden class from the remove button.
		$removeButton.removeClass( 'hidden' );

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
	 * Handles the click event of the remove button.
	 *
	 * @since 6.5.0
	 */
	$removeButton.on( 'click', function() {
		$hiddenDataField.val( 'false' );
		$( this ).toggleClass( 'hidden' );
		$iconPreview.toggleClass( 'hidden' );
		$iconPreview.find( 'img' ).not( '.browser-preview' )
			.each( function( i, img ) {
				$( img )
					.attr({
						'src': '',
						'alt': '' 
					});
			});

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
})( jQuery );
