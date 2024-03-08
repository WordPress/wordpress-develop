if ( ! Object.hasOwn( globalThis, 'wp' ) || ! Object.hasOwn( globalThis.wp, 'apiFetch' ) ) {
	throw new Error( `Script dependency not found, missing: \`wp.apiFetch\`` );
}

export default globalThis['wp']['apiFetch'];
