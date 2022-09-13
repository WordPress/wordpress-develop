/**
 * @output wp-includes/js/wp-image-mime-fallback.js
 */

window.wp = window.wp || {};

( function( document, settings ) {
	settings = settings || {};
	window.wp.imageMimeFallback = function( media ) {
		for ( var i = 0; i < media.length; i++ ) {
			try {
				var image         = media[ i ],
					media_details = image.media_details,
					sizes         = media_details.sizes,
					sizes_keys    = Object.keys( sizes );

				// Sort the key by the image width.
				sizes_keys = sizes_keys.sort( sortSizesKey( sizes ) );

				var images = document.querySelectorAll( 'img.wp-image-' + image.id );
				for ( var j = 0; j < images.length; j++ ) {

					var src = images[ j ].src;

					// If there are no sizes, there is nothing more to replace.
					if ( ! sizes_keys.length ) {
						continue;
					}

					var srcset = images[ j ].getAttribute( 'srcset' );

					for ( var k = 0; k < sizes_keys.length; k++ ) {
						var original_size_key  = sizes_keys[ k ],
							original_sizes     = sizes[ original_size_key ],
							original_url       = original_sizes.source_url;

						if ( original_url.match(/\.(jpeg|jpg|jpe)$/) ) {
							continue;
						}

						var get_update_image      = '',
							original_file         = original_sizes.file,
							original_aspect_ratio = original_sizes.width / original_sizes.height;

						// Check to see if the image src has any size set, then update it.
						if ( original_url === src ) {
							get_update_image = getReplaceImageSrc( media_details, sizes_keys, original_size_key, original_aspect_ratio );

							src = src.replace( original_file, get_update_image );

							// If there is no srcset and the src has been replaced, there is nothing more to replace.
							if ( ! srcset ) {
								break;
							}
						}

						if ( srcset ) {
							get_update_image = getReplaceImageSrc( media_details, sizes_keys, original_size_key, original_aspect_ratio );

							srcset = srcset.replace( original_file, get_update_image );
						}
					}

					if ( srcset ) {
						images[ j ].setAttribute( 'srcset', srcset );
					}

					if ( src ) {
						images[ j ].setAttribute( 'src', src );
					}
				}
			} catch ( e ) {
			}
		}
	};

	var sortSizesKey = function( sizes ) {
		return function innerSort(a, b) {
			return sizes[a].width - sizes[b].width;
		};
	};

	var getReplaceImageSrc = function( media_details, sizes_keys, original_size_key, original_aspect_ratio ) {
		var sizes = media_details.sizes,
			src   = media_details.original_image ? media_details.original_image : sizes.full.file;

		for ( var i = 0; i < sizes_keys.length; i++ ) {
			var size_key     = sizes_keys[ i ],
				size         = sizes[ size_key ],
				aspect_ratio = size.width / size.height,
				file         = size.file;

			if ( aspect_ratio === original_aspect_ratio && file.match(/\.(jpeg|jpg|jpe)$/) ) {
				src = file;

				// There is nothing more to replace once we find a replacement.
				break;
			}

			// If size key matches, there is nothing more to check fob bigger size.
			if ( size_key === original_size_key ) {
				break;
			}
		}
		return src;
	};

	var restApi = settings.restApi;

	var loadMediaDetails = function( nodes ) {
		var ids = [];
		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			var srcset = node.getAttribute( 'srcset' ) || '';

			if (
				node.nodeName !== 'IMG' ||
				( ! node.src.match( /\.webp$/i ) && ! srcset.match( /\.webp\s+/ ) )
			) {
				continue;
			}

			var attachment = node.className.match( /wp-image-(\d+)/i );
			if ( attachment && attachment[1] && ids.indexOf( attachment[1] ) === -1 ) {
				ids.push( attachment[1] );
			}
		}

		for ( var page = 0, pages = Math.ceil( ids.length / 100 ); page < pages; page++ ) {
			var pageIds = [];
			for ( var j = 0; j < 100 && j + page * 100 < ids.length; j++ ) {
				pageIds.push( ids[ j + page * 100 ] );
			}

			var jsonp    = document.createElement( 'script' ),
				restPath = 'wp/v2/media/?_fields=id,media_details&_jsonp=wp.imageMimeFallback&per_page=100&include=' + pageIds.join( ',' );

			if ( -1 !== restApi.indexOf( '?' ) ) {
				restPath = restPath.replace( '?', '&' );
			}

			jsonp.src = restApi + restPath;
			document.body.appendChild( jsonp );
		}
	};

	try {
		// Loop through already available images.
		loadMediaDetails( document.querySelectorAll( 'img' ) );

		// Start the mutation observer to update images added dynamically.
		var observer = new MutationObserver( function( mutationList ) {
			for ( var i = 0; i < mutationList.length; i++ ) {
				loadMediaDetails( mutationList[ i ].addedNodes );
			}
		} );

		observer.observe( document.body, {
			subtree: true,
			childList: true
		} );
	} catch ( e ) {
	}
} )( document, window._wpImageMimeFallbackSettings );
