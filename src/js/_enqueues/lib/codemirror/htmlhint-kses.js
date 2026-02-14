/* global HTMLHint */
/* eslint no-magic-numbers: ["error", { "ignore": [0, 1] }] */
HTMLHint.addRule({
	id: 'kses',
	description: 'Element or attribute cannot be used.',
	init: function( parser, reporter, options ) {
		'use strict';

		parser.addListener( 'tagstart', ( event ) => {
			const tagName = event.tagName.toLowerCase();
			if ( ! options[ tagName ] ) {
				reporter.error( 'Tag <' + event.tagName + '> is not allowed.', event.line, event.col, this, event.raw );
				return;
			}

			const allowedAttributes = options[ tagName ];
			const col = event.col + event.tagName.length + 1;
			for ( const attr of event.attrs ) {
				const attrName = attr.name.toLowerCase();
				if ( ! allowedAttributes[ attrName ] ) {
					reporter.error( 'Tag attribute [' + attr.raw + ' ] is not allowed.', event.line, col + attr.index, this, attr.raw );
				}
			}
		});
	}
});
