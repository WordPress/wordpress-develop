/* global wp, jQuery */

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
 *
 * @param {jQuery} $ The jQuery object.
 */
( function( $ ) {
	var frame;
	// Build the choose from library frame.
	$( '#choose-from-library-link' ).on( 'click', function() {
		var $el = $(this);

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
					title: $el.data( 'choose' ),
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
			$( '#site_icon_hidden_field' ).val(attachment.id);
			switchToUpdate(attachment.url);
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
				$( '#site_icon_hidden_field' ).val(attachment.id);
				switchToUpdate(attachment.attributes.url);
				frame.close();
			} else {
				frame.setState( 'cropper' );
			}
		});

		frame.open();
	});
}( jQuery ));

/**
 * Updates the site icon preview with the specified URL.
 *
 * @since 6.5.0
 *
 * @param {string} url The URL of the site icon image.
 */
function switchToUpdate( url ) {
	var chooseLink = jQuery( '#choose-from-library-link' );
	var iconPreview = jQuery( '#site-icon-preview' );
	// Set site-icon-img src to the url and remove the hidden class.
	iconPreview.find( 'img' ).not( '.browser-preview' )
		.each( function( i, img ) {
			jQuery(img).attr( 'src', url );
		});
	iconPreview.removeClass( 'hidden' );
	// Remove hidden class from remove.
	jQuery( '#js-remove-site-icon' ).removeClass( 'hidden' );
	// If the button is not in the update state, swap the classes.
	if ( chooseLink.attr( 'data-state' ) !== '1' ) {
		var classes = chooseLink.attr( 'class' );
		chooseLink.attr( 'class', chooseLink.attr( 'data-alt-classes' ) );
		chooseLink.attr( 'data-alt-classes', classes );
		chooseLink.attr( 'data-state', '1' );
	}

	// Swap the text of the button.
	chooseLink.text(
		chooseLink.attr( 'data-update-text' )
	);
}

/**
 * Initializes the functionality to remove the site icon.
 *
 * @since 6.5.0
 *
 * @param {jQuery} $ The jQuery object.
 */
( function ( $ ) {
	var chooseLink = $( '#choose-from-library-link' );
	$( '#js-remove-site-icon' ).on( 'click', function() {
		var classes = chooseLink.attr( 'class' );

		$( '#site_icon_hidden_field' ).val( 'false' );
		$( '#site-icon-preview' ).toggleClass( 'hidden' );
		$(this).toggleClass( 'hidden' );

		chooseLink.attr( 'class', chooseLink.attr( 'data-alt-classes' ) );
		chooseLink.attr( 'data-alt-classes', classes );

		// Swap the text of the button.
		chooseLink.text( chooseLink.attr( 'data-choose-text' ) );
		// Set the state of the button so it can be changed on new icon.
		chooseLink.attr( 'data-state', '' );
	});
}( jQuery ));
