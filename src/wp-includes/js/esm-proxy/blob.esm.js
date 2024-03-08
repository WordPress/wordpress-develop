if ( ! Object.hasOwn( globalThis, 'wp' ) || ! Object.hasOwn( globalThis.wp, 'blob' ) ) {
	throw new Error( `Script dependency not found, missing: \`wp.blob\`` );
}

export const createBlobURL = globalThis['wp']['blob'].createBlobURL;
export const downloadBlob = globalThis['wp']['blob'].downloadBlob;
export const getBlobByURL = globalThis['wp']['blob'].getBlobByURL;
export const getBlobTypeByURL = globalThis['wp']['blob'].getBlobTypeByURL;
export const isBlobURL = globalThis['wp']['blob'].isBlobURL;
export const revokeBlobURL = globalThis['wp']['blob'].revokeBlobURL;
