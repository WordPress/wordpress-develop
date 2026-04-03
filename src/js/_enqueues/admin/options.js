( function ( $ ) {
	var $form,
		originalFormContent,
		isSubmitting = false,
		hasListener = false,
		__ = wp.i18n.__;

	function beforeUnloadHandler() {
		if ( isSubmitting || ! $form || ! $form.length ) {
			return;
		}

		if ( originalFormContent !== $form.serialize() ) {
			return __(
				'The changes you made will be lost if you navigate away from this page.'
			);
		}
	}

	function addBeforeUnloadListener() {
		if ( ! hasListener ) {
			$( window ).on( 'beforeunload.options', beforeUnloadHandler );
			hasListener = true;
		}
	}

	function removeBeforeUnloadListener() {
		if ( hasListener ) {
			$( window ).off( 'beforeunload.options' );
			hasListener = false;
		}
	}

	$( function () {
		$form = $( 'form[action="options.php"]' );

		if ( ! $form.length ) {
			return;
		}

		originalFormContent = $form.serialize();

		// Add listener only when form is modified
		$form.on( 'change input', function () {
			addBeforeUnloadListener();
		} );

		$form.on( 'submit', function () {
			isSubmitting = true;
			removeBeforeUnloadListener();
		} );
	} );
} )( jQuery );
