/**
 * File wrapper for AI client files.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

import { FileType } from '../enums';

/**
 * Represents a file in the AI client.
 *
 * @since 7.0.0
 */
export class File {
	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} file The raw file object.
	 */
	constructor( file ) {
		this._file = file;
	}

	/**
	 * Gets the type of file storage.
	 *
	 * @since 7.0.0
	 *
	 * @return {string} The file type.
	 */
	get fileType() {
		return this._file.fileType;
	}

	/**
	 * Gets the MIME type of the file.
	 *
	 * @since 7.0.0
	 *
	 * @return {string} The MIME type.
	 */
	get mimeType() {
		return this._file.mimeType;
	}

	/**
	 * Gets the URL for remote files.
	 *
	 * @since 7.0.0
	 *
	 * @return {string|undefined} The URL.
	 */
	get url() {
		return this._file.url;
	}

	/**
	 * Gets the base64 data for inline files.
	 *
	 * @since 7.0.0
	 *
	 * @return {string|undefined} The base64 data.
	 */
	get base64Data() {
		return this._file.base64Data;
	}

	/**
	 * Checks if the file is an inline file.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if the file is inline.
	 */
	isInline() {
		return this.fileType === FileType.INLINE;
	}

	/**
	 * Checks if the file is a remote file.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if the file is remote.
	 */
	isRemote() {
		return this.fileType === FileType.REMOTE;
	}

	/**
	 * Gets the data as a data URI for inline files.
	 *
	 * @since 7.0.0
	 *
	 * @return {string|undefined} The data URI.
	 */
	getDataUri() {
		if ( ! this.base64Data ) {
			return undefined;
		}

		return `data:${ this.mimeType };base64,${ this.base64Data }`;
	}

	/**
	 * Checks if the file is a video.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if the file is a video.
	 */
	isVideo() {
		return this.mimeType.startsWith( 'video/' );
	}

	/**
	 * Checks if the file is an image.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if the file is an image.
	 */
	isImage() {
		return this.mimeType.startsWith( 'image/' );
	}

	/**
	 * Checks if the file is audio.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if the file is audio.
	 */
	isAudio() {
		return this.mimeType.startsWith( 'audio/' );
	}

	/**
	 * Checks if the file is text.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if the file is text.
	 */
	isText() {
		return this.mimeType.startsWith( 'text/' );
	}

	/**
	 * Checks if the file is a document.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if the file is a document.
	 */
	isDocument() {
		return (
			this.mimeType === 'application/pdf' ||
			this.mimeType.startsWith( 'application/msword' ) ||
			this.mimeType.startsWith(
				'application/vnd.openxmlformats-officedocument'
			) ||
			this.mimeType.startsWith( 'application/vnd.ms-' )
		);
	}

	/**
	 * Checks if the file is a specific MIME type.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} type The mime type to check.
	 * @return {boolean} True if the file is of the specified type.
	 */
	isMimeType( type ) {
		return this.mimeType.startsWith( type + '/' ) || this.mimeType === type;
	}
}
