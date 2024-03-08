if ( ! Object.hasOwn( globalThis, 'wp' ) || ! Object.hasOwn( globalThis.wp, 'a11y' ) ) {
	throw new Error( `Script dependency not found, missing: \`wp.a11y\`` );
}

export const setup = globalThis['wp']['a11y']['setup'];
export const speak = globalThis['wp']['a11y']['speak'];
