/**
 * GenerativeAiResult wrapper class.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

import { MessagePartChannel, MessagePartType } from '../enums';

/**
 * Represents the result of a generative AI operation.
 *
 * @since 7.0.0
 */
export class GenerativeAiResult {
	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} result The raw result object.
	 */
	constructor( result ) {
		if ( ! result.candidates || result.candidates.length === 0 ) {
			throw new Error( 'At least one candidate must be provided' );
		}
		this._result = result;
	}

	/**
	 * Gets the unique identifier for this result.
	 *
	 * @since 7.0.0
	 *
	 * @return {string} The ID.
	 */
	get id() {
		return this._result.id;
	}

	/**
	 * Gets the generated candidates.
	 *
	 * @since 7.0.0
	 *
	 * @return {Array} The candidates.
	 */
	get candidates() {
		return this._result.candidates;
	}

	/**
	 * Gets the token usage statistics.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The token usage.
	 */
	get tokenUsage() {
		return this._result.tokenUsage;
	}

	/**
	 * Gets the provider metadata.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The provider metadata.
	 */
	get providerMetadata() {
		return this._result.providerMetadata;
	}

	/**
	 * Gets the model metadata.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The model metadata.
	 */
	get modelMetadata() {
		return this._result.modelMetadata;
	}

	/**
	 * Gets additional data.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object|undefined} The additional data.
	 */
	get additionalData() {
		return this._result.additionalData;
	}

	/**
	 * Gets the total number of candidates.
	 *
	 * @since 7.0.0
	 *
	 * @return {number} The total number of candidates.
	 */
	getCandidateCount() {
		return this._result.candidates.length;
	}

	/**
	 * Checks if the result has multiple candidates.
	 *
	 * @since 7.0.0
	 *
	 * @return {boolean} True if there are multiple candidates.
	 */
	hasMultipleCandidates() {
		return this.getCandidateCount() > 1;
	}

	/**
	 * Converts the first candidate to text.
	 *
	 * @since 7.0.0
	 *
	 * @return {string} The text content.
	 */
	toText() {
		const message = this._result.candidates[ 0 ].message;
		for ( const part of message.parts ) {
			if (
				part.channel === MessagePartChannel.CONTENT &&
				part.type === MessagePartType.TEXT
			) {
				return part.text;
			}
		}

		throw new Error( 'No text content found in first candidate' );
	}

	/**
	 * Converts the first candidate to a file.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The file.
	 */
	toFile() {
		const message = this._result.candidates[ 0 ].message;
		for ( const part of message.parts ) {
			if (
				part.channel === MessagePartChannel.CONTENT &&
				part.type === MessagePartType.FILE
			) {
				return part.file;
			}
		}

		throw new Error( 'No file content found in first candidate' );
	}

	/**
	 * Converts the first candidate to an image file.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The image file.
	 */
	toImageFile() {
		const file = this.toFile();

		if ( ! file.mimeType.startsWith( 'image/' ) ) {
			throw new Error(
				`File is not an image. MIME type: ${ file.mimeType }`
			);
		}

		return file;
	}

	/**
	 * Converts the first candidate to an audio file.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The audio file.
	 */
	toAudioFile() {
		const file = this.toFile();

		if ( ! file.mimeType.startsWith( 'audio/' ) ) {
			throw new Error(
				`File is not an audio file. MIME type: ${ file.mimeType }`
			);
		}

		return file;
	}

	/**
	 * Converts the first candidate to a video file.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The video file.
	 */
	toVideoFile() {
		const file = this.toFile();

		if ( ! file.mimeType.startsWith( 'video/' ) ) {
			throw new Error(
				`File is not a video file. MIME type: ${ file.mimeType }`
			);
		}

		return file;
	}

	/**
	 * Converts the first candidate to a message.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object} The message.
	 */
	toMessage() {
		return this._result.candidates[ 0 ].message;
	}

	/**
	 * Converts all candidates to text.
	 *
	 * @since 7.0.0
	 *
	 * @return {string[]} Array of text content.
	 */
	toTexts() {
		const texts = [];
		for ( const candidate of this._result.candidates ) {
			const message = candidate.message;
			for ( const part of message.parts ) {
				if (
					part.channel === MessagePartChannel.CONTENT &&
					part.type === MessagePartType.TEXT
				) {
					texts.push( part.text );
					break;
				}
			}
		}
		return texts;
	}

	/**
	 * Converts all candidates to files.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object[]} Array of files.
	 */
	toFiles() {
		const files = [];
		for ( const candidate of this._result.candidates ) {
			const message = candidate.message;
			for ( const part of message.parts ) {
				if (
					part.channel === MessagePartChannel.CONTENT &&
					part.type === MessagePartType.FILE
				) {
					files.push( part.file );
					break;
				}
			}
		}
		return files;
	}

	/**
	 * Converts all candidates to image files.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object[]} Array of image files.
	 */
	toImageFiles() {
		return this.toFiles().filter( ( file ) =>
			file.mimeType.startsWith( 'image/' )
		);
	}

	/**
	 * Converts all candidates to audio files.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object[]} Array of audio files.
	 */
	toAudioFiles() {
		return this.toFiles().filter( ( file ) =>
			file.mimeType.startsWith( 'audio/' )
		);
	}

	/**
	 * Converts all candidates to video files.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object[]} Array of video files.
	 */
	toVideoFiles() {
		return this.toFiles().filter( ( file ) =>
			file.mimeType.startsWith( 'video/' )
		);
	}

	/**
	 * Converts all candidates to messages.
	 *
	 * @since 7.0.0
	 *
	 * @return {Object[]} Array of messages.
	 */
	toMessages() {
		return this._result.candidates.map(
			( candidate ) => candidate.message
		);
	}
}
