( function( $, QUnit, tinymce ) {
	var editor,
		count = 0;

	if ( tinymce.Env.ie && tinymce.Env.ie < 11 ) {
		return;
	}

	/**
	 * Initializes an editor with the wordpress plugin on a new textarea.
	 *
	 * @param {string}   id       The textarea ID.
	 * @param {Object}   settings Additional editor settings.
	 * @param {Function} callback Called with the editor once it is initialized.
	 */
	function initEditor( id, settings, callback ) {
		$( document.body ).append( '<textarea id="' + id + '">' );

		tinymce.init( $.extend( {
			selector: '#' + id,
			add_unload_trigger: false,
			skin: false,
			entities: 'raw',
			convert_urls: false,
			plugins: 'wordpress',
			init_instance_callback: callback
		}, settings ) );
	}

	/**
	 * Returns the attributes of the first iframe in the editor content, keyed by name.
	 *
	 * @param {string} content The editor content.
	 * @return {Object} The iframe attributes.
	 */
	function getIframeAttributes( content ) {
		var attributes = {};

		$.each( $( '<div>' ).html( content ).find( 'iframe' )[0].attributes, function( i, attribute ) {
			attributes[ attribute.name ] = attribute.value;
		} );

		return attributes;
	}

	QUnit.module( 'tinymce.plugins.wordpress', {
		beforeEach: function( assert ) {
			var done;

			if ( ! editor ) {
				done = assert.async();

				initEditor( 'editor', {}, function( ed ) {
					editor = ed;
					done();
				} );
			} else {
				editor.setContent( '' );
			}
		},
		afterEach: function( assert ) {
			count++;

			if ( count === assert.test.module.tests.length ) {
				editor.remove();
				$( '#editor' ).remove();
			}
		}
	} );

	QUnit.test( 'Iframe embed attributes are kept when the content is saved.', function( assert ) {
		var attributes;

		editor.setContent(
			'<iframe width="560" height="315" src="https://www.youtube.com/embed/abc" title="Video" ' +
			'frameborder="0" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" ' +
			'referrerpolicy="strict-origin-when-cross-origin" loading="lazy" allowfullscreen></iframe>'
		);

		attributes = getIframeAttributes( editor.getContent() );

		assert.equal( attributes.allow, 'autoplay; encrypted-media; fullscreen; picture-in-picture', 'allow is kept' );
		assert.equal( attributes.referrerpolicy, 'strict-origin-when-cross-origin', 'referrerpolicy is kept' );
		assert.equal( attributes.loading, 'lazy', 'loading is kept' );
		assert.ok( 'allowfullscreen' in attributes, 'allowfullscreen is kept' );
		assert.equal( attributes.title, 'Video', 'title is kept' );
	} );

	QUnit.test( 'Unknown iframe attributes are still removed.', function( assert ) {
		editor.setContent( '<iframe src="https://example.org/" onload="alert(1)" unknown="1"></iframe>' );

		assert.deepEqual(
			getIframeAttributes( editor.getContent() ),
			{ src: 'https://example.org/' },
			'only known attributes remain'
		);
	} );

	QUnit.test( 'Iframe attributes added through extended_valid_elements are kept.', function( assert ) {
		var done = assert.async();

		// A site customization of the iframe rule, which the plugin must extend rather than replace.
		initEditor( 'editor-extended', { extended_valid_elements: 'iframe[src|site-attribute]' }, function( extendedEditor ) {
			var attributes;

			extendedEditor.setContent( '<iframe src="https://example.org/" site-attribute="yes" allow="fullscreen"></iframe>' );

			attributes = getIframeAttributes( extendedEditor.getContent() );

			assert.equal( attributes['site-attribute'], 'yes', 'the site-defined attribute is kept' );
			assert.equal( attributes.allow, 'fullscreen', 'allow is kept' );

			extendedEditor.remove();
			$( '#editor-extended' ).remove();
			done();
		} );
	} );
}( window.jQuery, window.QUnit, window.tinymce ) );
