/* global wp */
( function () {
	var form = document.querySelector( 'form[action="options.php"]' );

	if ( ! form ) {
		return;
	}

	var originalFormContent = new URLSearchParams( new FormData( form ) ).toString();
	var __ = wp.i18n.__;

	function beforeUnloadHandler( event ) {
		var currentContent = new URLSearchParams( new FormData( form ) ).toString();
		if ( originalFormContent !== currentContent ) {
			event.preventDefault();
			return __(
				'The changes you made will be lost if you navigate away from this page.'
			);
		}
	}

	// Add the beforeunload listener only once a field is modified, to avoid
	// breaking bfcache.
	document.addEventListener( 'change', function () {
		window.addEventListener( 'beforeunload', beforeUnloadHandler );
	}, { once: true } );

	form.addEventListener( 'submit', function () {
		window.removeEventListener( 'beforeunload', beforeUnloadHandler );
	} );
} )();
