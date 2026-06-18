/**
 * @output wp-admin/js/on-this-day.js
 */

( function( $ ) {
	function flashState( $button, message ) {
		var original = $button.data( 'wpOtdShareLabel' ) || $button.text();

		$button.text( message ).addClass( 'wp-on-this-day-is-copied' );

		window.setTimeout( function() {
			$button.text( original ).removeClass( 'wp-on-this-day-is-copied' );
		}, 2000 );
	}

	function legacyCopy( text ) {
		var $textarea = $( '<textarea readonly></textarea>' )
			.val( text )
			.css( {
				position: 'absolute',
				left: '-9999px'
			} )
			.appendTo( 'body' );

		$textarea[0].select();

		try {
			document.execCommand( 'copy' );
		} catch ( error ) {}

		$textarea.remove();
	}

	function copyShareUrl( $button, url ) {
		var success = $button.data( 'wpOtdShareCopied' ) || 'Link copied!';

		function done() {
			flashState( $button, success );
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then(
				done,
				function() {
					legacyCopy( url );
					done();
				}
			);
			return;
		}

		legacyCopy( url );
		done();
	}

	$( '.wp-on-this-day-post-share' ).on( 'click', function( event ) {
		var $button = $( this ),
			url = $button.data( 'wpOtdShareUrl' ),
			shareData = {
				title: $button.data( 'wpOtdShareTitle' ) || document.title,
				url: url
			};

		event.preventDefault();

		if ( ! url ) {
			return;
		}

		if (
			navigator.share &&
			( ! navigator.canShare || navigator.canShare( shareData ) )
		) {
			navigator.share( shareData ).then(
				function() {
					flashState( $button, $button.data( 'wpOtdShareShared' ) || 'Shared!' );
				},
				function( error ) {
					if ( ! error || 'AbortError' !== error.name ) {
						copyShareUrl( $button, url );
					}
				}
			);
			return;
		}

		copyShareUrl( $button, url );
	} );

	function setupCarousel() {
		var $root = $( this ),
			$slides = $root.find( '.wp-on-this-day-post' ),
			$counter = $root.find( '.wp-on-this-day-carousel-current' ),
			current = Math.max( 0, $slides.index( $slides.filter( '.wp-on-this-day-is-active' ) ) );

		if ( $slides.length < 2 ) {
			return;
		}

		function show( target ) {
			current = ( ( target % $slides.length ) + $slides.length ) % $slides.length;

			$slides
				.removeClass( 'wp-on-this-day-is-active' )
				.attr( 'aria-hidden', 'true' )
				.eq( current )
				.addClass( 'wp-on-this-day-is-active' )
				.attr( 'aria-hidden', 'false' );
			$counter.text( current + 1 );
		}

		$root.find( '.wp-on-this-day-carousel-prev' ).on( 'click', function() {
			show( current - 1 );
		} );
		$root.find( '.wp-on-this-day-carousel-next' ).on( 'click', function() {
			show( current + 1 );
		} );

		$root.on( 'keydown', function( event ) {
			if ( 'ArrowLeft' === event.key ) {
				show( current - 1 );
			}

			if ( 'ArrowRight' === event.key ) {
				show( current + 1 );
			}
		} );

		show( current );
	}

	$( '.wp-on-this-day-carousel' ).each( setupCarousel );
}( jQuery ) );
