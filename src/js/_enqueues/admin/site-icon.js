(function($) {
	var frame;

	function calculateImageSelectOptions ( attachment ) {
		var realWidth  = attachment.get( 'width' ),
			realHeight = attachment.get( 'height' ),
			xInit = 512,
			yInit = 512,
			ratio = xInit / yInit,
			xImg  = xInit,
			yImg  = yInit,
			x1, y1, imgSelectOptions;

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

	$( function() {
		// Build the choose from library frame.
		$( '#choose-from-library-link' ).on( 'click', function( event ) {
			var $el = $(this);
			event.preventDefault();

			// If the media frame already exists, reopen it.
			if ( frame ) {
				frame.open();
				return;
			}

			// Create the media frame.
			frame = wp.media({
				button: {
					// Set the text of the button.
					text: $el.data('update'),
					// don't close, we might need to crop
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

			frame.on( 'cropped', function( attachment) {
				$( '#site_icon_hidden_field' ).val(attachment.id);
				switchToUpdate(attachment.url);
				frame.close();
				// Startover with a frame that is so fresh and so clean clean.
				frame = null;
			});

			// When an image is selected, run a callback.
			frame.on( 'select', function() {
				// Grab the selected attachment.
				var attachment = frame.state().get('selection').first();

				if ( attachment.attributes.height === $el.data('size') && $el.data('size') === attachment.attributes.width ) {
					// set the value of the hidden input to the attachment id
					$( '#site_icon_hidden_field').val(attachment.id);
					switchToUpdate(attachment.attributes.url);
					// close the model
					frame.close();
				} else {
					frame.setState( 'cropper' );
				}
			});

			frame.open();
		});
	});

	function switchToUpdate( url ){
		// set site-icon-img src to the url and remove the hidden class
		$( '#site-icon-img' ).attr( 'src', url );
		$( '#site-icon-img' ).removeClass( 'hidden' );
		// remove hidden class from remove
		$( '#js-remove-site-icon' ).removeClass( 'hidden' );
		// tmp store the classes of the #choose-from-library-link button
		// if the button is not in the update state, swap the classes
		if( $( '#choose-from-library-link' ).attr('data-state') !== '1' ){
			var classes = $( '#choose-from-library-link' ).attr( 'class' );
			$( '#choose-from-library-link' ).attr('class', $( '#choose-from-library-link' ).attr('data-alt-classes') );
			$( '#choose-from-library-link' ).attr('data-alt-classes', classes );
			$( '#choose-from-library-link' ).attr('data-state', '1');
		}

		// swap the text of the button
		$( '#choose-from-library-link' ).text( $( '#choose-from-library-link' ).attr('data-update-text') );
	}

	$( '#js-remove-site-icon' ).on( 'click', function( event ) {
		// set the value of the hidden input to blank
		$( '#site_icon_hidden_field').val('false');
		//  toggle hidden class on site-icon-img
		$( '#site-icon-img' ).toggleClass( 'hidden' );
		// toggle hidden class on remove button
		$( this ).toggleClass( 'hidden' );

		// tmp store the classes of the #choose-from-library-link button
		var classes = $( '#choose-from-library-link' ).attr('class');
		$( '#choose-from-library-link' ).attr('class', $( '#choose-from-library-link' ).attr('data-alt-classes') );
		$( '#choose-from-library-link' ).attr('data-alt-classes', classes );

		// swap the text of the button
		$( '#choose-from-library-link' ).text( $( '#choose-from-library-link' ).attr('data-choose-text') );

		$( '#choose-from-library-link' ).attr('data-state', '');

		// prevent the default action
		event.preventDefault();
	});
}(jQuery));
