window.wpPerfLab = window.wpPerfLab || {};

( function( document ) {
	window.wpPerfLab.webpUploadsFallbackWebpImages = function( media ) {
		for ( var i = 0; i < media.length; i++ ) {
			try {
				var image         = media[ i ],
					media_details = image.media_details,
					media_sources = media_details.sources,
					sizes         = media_details.sizes,
					sizes_keys    = Object.keys( sizes );

				// If the full image has no JPEG version available, no sub-size will have JPEG available either.
				if ( sizes.full && ! sizes.full.sources['image/jpeg'] ) {
					continue;
				}

				var images = document.querySelectorAll( 'img.wp-image-' + image.id );

				for ( var j = 0; j < images.length; j++ ) {

					var src = images[ j ].src;

					// If there are sources but no sizes, then attempt to replace src through sources. In that case, there is nothing more to replace.
					if ( media_sources && ! sizes_keys.length ) {
						// Only modify src if available and the relevant sources are set.
						if ( src && media_sources['image/webp'] && media_sources['image/jpeg'] ) {
							src = src.replace( media_sources['image/webp'].file, media_sources['image/jpeg'].file );
							images[ j ].setAttribute( 'src', src );
						}
						continue;
					}

					var srcset = images[ j ].getAttribute( 'srcset' );

					for ( var k = 0; k < sizes_keys.length; k++ ) {
						var media_sizes_sources = sizes[ sizes_keys[ k ] ].sources;
						if ( ! media_sizes_sources || ! media_sizes_sources['image/webp'] || ! media_sizes_sources['image/jpeg'] ) {
							continue;
						}

						// Check to see if the image src has any size set, then update it.
						if ( media_sizes_sources['image/webp'].source_url === src ) {
							src = media_sizes_sources['image/jpeg'].source_url;

							// If there is no srcset and the src has been replaced, there is nothing more to replace.
							if ( ! srcset ) {
								break;
							}
						}

						if ( srcset ) {
							srcset = srcset.replace( media_sizes_sources['image/webp'].source_url, media_sizes_sources['image/jpeg'].source_url );
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

	var restApi = document.getElementById( 'webpUploadsFallbackWebpImages' ).getAttribute( 'data-rest-api' );

	var loadMediaDetails = function( nodes ) {
		var ids = [];
		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			var srcset = node.getAttribute( 'srcset' ) || '';

			if (
				node.nodeName !== "IMG" ||
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
			for ( var i = 0; i < 100 && i + page * 100 < ids.length; i++ ) {
				pageIds.push( ids[ i + page * 100 ] );
			}

			var jsonp = document.createElement( 'script' );
			var restPath = 'wp/v2/media/?_fields=id,media_details&_jsonp=wpPerfLab.webpUploadsFallbackWebpImages&per_page=100&include=' + pageIds.join( ',' );
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
			childList: true,
		} );
	} catch ( e ) {
	}
} )( document );
