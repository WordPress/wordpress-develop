/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Code editor search', () => {
	test.beforeEach( async ( { admin, page } ) => {
		await admin.visitAdminPage(
			'/theme-editor.php?file=style.css&theme=twentytwentyone'
		);

		const warning = page.getByRole( 'button', { name: 'I understand' } );
		if ( await warning.isVisible() ) {
			await warning.click();
		}
	} );

	test( 'distinguishes the active persistent search result while the search field has focus', async ( {
		page,
	} ) => {
		const codeMirror = page.locator( '.CodeMirror' );
		await expect( codeMirror ).toBeVisible();

		await codeMirror.evaluate( ( element ) => {
			const editor = element.CodeMirror;
			const lines = Array( 220 ).fill( 'filler content' );
			lines[ 2 ] = 'line 3 unique-ticket-marker';
			lines[ 9 ] = 'line 10 unique-ticket-marker';
			lines[ 179 ] = 'line 180 unique-ticket-marker';
			editor.setValue( lines.join( '\n' ) );
			editor.execCommand( 'selectAll' );
			editor.focus();
		} );

		await page.keyboard.press( 'Control+f' );
		const searchField = page.locator( '.CodeMirror-search-field' );
		await expect( searchField ).toBeFocused();
		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getOption( 'styleSelectedText' )
				)
			)
			.toBe( false );

		await searchField.fill( 'unique-ticket-marker' );
		await searchField.press( 'Enter' );

		const activeMatch = page.locator( '.CodeMirror-searching-selected' );
		const inactiveMatch = page
			.locator( '.cm-searching:not(.CodeMirror-searching-selected)' )
			.first();
		await expect( activeMatch ).toHaveCount( 1 );
		await expect( activeMatch ).toHaveCSS(
			'background-color',
			'rgb(240, 195, 60)'
		);
		await expect( inactiveMatch ).not.toHaveCSS(
			'background-color',
			'rgb(240, 195, 60)'
		);
		await expect( activeMatch ).toHaveCSS( 'outline-style', 'solid' );

		await codeMirror.locator( '.CodeMirror-code' ).focus();
		await expect( codeMirror ).toHaveClass( /CodeMirror-focused/ );
		await expect( activeMatch ).toHaveCount( 0 );
		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getOption( 'styleSelectedText' )
				)
			)
			.toBe( false );

		await searchField.focus();
		await expect( activeMatch ).toHaveCount( 1 );
		await searchField.press( 'Enter' );
		await searchField.press( 'Enter' );
		await expect
			.poll( () =>
				codeMirror.evaluate(
					( element ) => element.CodeMirror.getCursor().line
				)
			)
			.toBe( 179 );
		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) => {
					const marker = element.CodeMirror.getAllMarks().find(
						( item ) =>
							'CodeMirror-searching-selected' === item.className
					);
					return marker?.find()?.from?.line;
				} )
			)
			.toBe( 179 );
		await expect( searchField ).toBeFocused();
		await expect( activeMatch ).toHaveCount( 1 );
	} );

	for ( const styleSelectedText of [ false, 'consumer-selection' ] ) {
		test( `respects an explicit styleSelectedText setting of ${ styleSelectedText }`, async ( {
			page,
		} ) => {
			await page.evaluate( ( option ) => {
				const textarea = document.createElement( 'textarea' );
				document.body.appendChild( textarea );
				const editor = window.wp.codeEditor.initialize( textarea, {
					codemirror: { styleSelectedText: option },
				} ).codemirror;
				editor.setValue( 'unique-ticket-marker\nunique-ticket-marker' );
				editor.setCursor( { line: 0, ch: 0 } );
				editor.focus();
			}, styleSelectedText );

			const codeMirror = page.locator( '.CodeMirror' ).last();
			await page.keyboard.press( 'Control+f' );
			const searchField = codeMirror.locator(
				'.CodeMirror-search-field'
			);
			await expect( searchField ).toBeFocused();
			await searchField.fill( 'unique-ticket-marker' );
			await searchField.press( 'Enter' );

			await expect
				.poll( () =>
					codeMirror.evaluate( ( element ) =>
						element.CodeMirror.getSelection()
					)
				)
				.toBe( 'unique-ticket-marker' );
			await expect
				.poll( () =>
					codeMirror.evaluate( ( element ) =>
						element.CodeMirror.getOption( 'styleSelectedText' )
					)
				)
				.toBe( styleSelectedText );

			await codeMirror.locator( '.CodeMirror-code' ).focus();
			await expect
				.poll( () =>
					codeMirror.evaluate( ( element ) =>
						element.CodeMirror.getOption( 'styleSelectedText' )
					)
				)
				.toBe( styleSelectedText );
		} );
	}

	test( 'respects styleSelectedText changes after initialization', async ( {
		page,
	} ) => {
		await page.evaluate( () => {
			const textarea = document.createElement( 'textarea' );
			document.body.appendChild( textarea );
			const editor =
				window.wp.codeEditor.initialize( textarea ).codemirror;
			editor.setValue( 'unique-ticket-marker\nunique-ticket-marker' );
			editor.setCursor( { line: 0, ch: 0 } );
			editor.setOption( 'styleSelectedText', 'runtime-selection' );
			editor.focus();
		} );

		const codeMirror = page.locator( '.CodeMirror' ).last();
		await page.keyboard.press( 'Control+f' );
		const searchField = codeMirror.locator( '.CodeMirror-search-field' );
		await expect( searchField ).toBeFocused();
		await searchField.fill( 'unique-ticket-marker' );
		await searchField.press( 'Enter' );

		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getSelection()
				)
			)
			.toBe( 'unique-ticket-marker' );
		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getOption( 'styleSelectedText' )
				)
			)
			.toBe( 'runtime-selection' );

		await codeMirror.locator( '.CodeMirror-code' ).focus();
		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getOption( 'styleSelectedText' )
				)
			)
			.toBe( 'runtime-selection' );
	} );

	test( 'respects the global styleSelectedText default', async ( {
		page,
	} ) => {
		await page.evaluate( () => {
			const previousDefault =
				window.wp.CodeMirror.defaults.styleSelectedText;
			window.wp.CodeMirror.defaults.styleSelectedText =
				'global-selection';

			const textarea = document.createElement( 'textarea' );
			document.body.appendChild( textarea );
			const editor =
				window.wp.codeEditor.initialize( textarea ).codemirror;
			window.wp.CodeMirror.defaults.styleSelectedText = previousDefault;
			editor.setValue( 'unique-ticket-marker\nunique-ticket-marker' );
			editor.setCursor( { line: 0, ch: 0 } );
			editor.focus();
		} );

		const codeMirror = page.locator( '.CodeMirror' ).last();
		await page.keyboard.press( 'Control+f' );
		const searchField = codeMirror.locator( '.CodeMirror-search-field' );
		await expect( searchField ).toBeFocused();
		await searchField.fill( 'unique-ticket-marker' );
		await searchField.press( 'Enter' );

		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getSelection()
				)
			)
			.toBe( 'unique-ticket-marker' );
		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getOption( 'styleSelectedText' )
				)
			)
			.toBe( 'global-selection' );

		await codeMirror.locator( '.CodeMirror-code' ).focus();
		await expect
			.poll( () =>
				codeMirror.evaluate( ( element ) =>
					element.CodeMirror.getOption( 'styleSelectedText' )
				)
			)
			.toBe( 'global-selection' );
	} );
} );
