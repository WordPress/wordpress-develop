/**
 * PromptBuilder for client-side AI prompting.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

import apiFetch from '@wordpress/api-fetch';
import {
	Capability,
	MessagePartChannel,
	MessagePartType,
	MessageRole,
	Modality,
} from '../enums';
import { File } from '../files/file';
import { GenerativeAiResult } from '../results/generative-ai-result';

/**
 * Fluent builder for constructing AI prompts.
 *
 * @since 7.0.0
 */
export class PromptBuilder {
	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param {string|Object|Array} [promptInput] Optional initial prompt content.
	 */
	constructor( promptInput ) {
		this.messages = [];
		this.modelConfig = {};
		this.providerId = undefined;
		this.modelId = undefined;
		this.modelPreferences = [];
		this.requestOptions = undefined;

		if ( promptInput ) {
			if ( this._isMessagesList( promptInput ) ) {
				this.messages = promptInput;
			} else {
				this.messages.push(
					this._parseMessage( promptInput, MessageRole.USER )
				);
			}
		}
	}

	/**
	 * Adds text to the current message.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} text The text to add.
	 * @return {PromptBuilder} this
	 */
	withText( text ) {
		const part = {
			channel: MessagePartChannel.CONTENT,
			type: MessagePartType.TEXT,
			text,
		};
		this._appendPartToMessages( part );
		return this;
	}

	/**
	 * Adds a file to the current message.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} file The file object.
	 * @return {PromptBuilder} this
	 */
	withFile( file ) {
		const part = {
			channel: MessagePartChannel.CONTENT,
			type: MessagePartType.FILE,
			file,
		};
		this._appendPartToMessages( part );
		return this;
	}

	/**
	 * Adds a function response to the current message.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} functionResponse The function response.
	 * @return {PromptBuilder} this
	 */
	withFunctionResponse( functionResponse ) {
		const part = {
			channel: MessagePartChannel.CONTENT,
			type: MessagePartType.FUNCTION_RESPONSE,
			functionResponse,
		};
		this._appendPartToMessages( part );
		return this;
	}

	/**
	 * Adds message parts to the current message.
	 *
	 * @since 7.0.0
	 *
	 * @param {...Object} parts The message parts to add.
	 * @return {PromptBuilder} this
	 */
	withMessageParts( ...parts ) {
		for ( const part of parts ) {
			this._appendPartToMessages( part );
		}
		return this;
	}

	/**
	 * Adds history messages to the conversation.
	 *
	 * @since 7.0.0
	 *
	 * @param {...Object} messages The messages to add.
	 * @return {PromptBuilder} this
	 */
	withHistory( ...messages ) {
		this.messages.push( ...messages );
		return this;
	}

	/**
	 * Sets the model to use.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} providerId The provider ID.
	 * @param {string} modelId    The model ID.
	 * @return {PromptBuilder} this
	 */
	usingModel( providerId, modelId ) {
		this.providerId = providerId;
		this.modelId = modelId;
		return this;
	}

	/**
	 * Sets the model preferences.
	 *
	 * @since 7.0.0
	 *
	 * @param {...(string|Array)} preferredModels The preferred models.
	 * @return {PromptBuilder} this
	 */
	usingModelPreference( ...preferredModels ) {
		this.modelPreferences = preferredModels;
		return this;
	}

	/**
	 * Merges the provided model configuration.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} config The model configuration to merge.
	 * @return {PromptBuilder} this
	 */
	usingModelConfig( config ) {
		this.modelConfig = { ...this.modelConfig, ...config };
		return this;
	}

	/**
	 * Sets the provider to use.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} providerId The provider ID.
	 * @return {PromptBuilder} this
	 */
	usingProvider( providerId ) {
		this.providerId = providerId;
		return this;
	}

	/**
	 * Sets the system instruction.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} systemInstruction The system instruction.
	 * @return {PromptBuilder} this
	 */
	usingSystemInstruction( systemInstruction ) {
		this.modelConfig.systemInstruction = systemInstruction;
		return this;
	}

	/**
	 * Sets the max tokens.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} maxTokens The max tokens.
	 * @return {PromptBuilder} this
	 */
	usingMaxTokens( maxTokens ) {
		this.modelConfig.maxTokens = maxTokens;
		return this;
	}

	/**
	 * Sets the temperature.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} temperature The temperature.
	 * @return {PromptBuilder} this
	 */
	usingTemperature( temperature ) {
		this.modelConfig.temperature = temperature;
		return this;
	}

	/**
	 * Sets the top P.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} topP The top P.
	 * @return {PromptBuilder} this
	 */
	usingTopP( topP ) {
		this.modelConfig.topP = topP;
		return this;
	}

	/**
	 * Sets the top K.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} topK The top K.
	 * @return {PromptBuilder} this
	 */
	usingTopK( topK ) {
		this.modelConfig.topK = topK;
		return this;
	}

	/**
	 * Sets the stop sequences.
	 *
	 * @since 7.0.0
	 *
	 * @param {...string} stopSequences The stop sequences.
	 * @return {PromptBuilder} this
	 */
	usingStopSequences( ...stopSequences ) {
		const current = this.modelConfig.stopSequences || [];
		this.modelConfig.stopSequences = [ ...current, ...stopSequences ];
		return this;
	}

	/**
	 * Sets the candidate count.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} candidateCount The candidate count.
	 * @return {PromptBuilder} this
	 */
	usingCandidateCount( candidateCount ) {
		this.modelConfig.candidateCount = candidateCount;
		return this;
	}

	/**
	 * Sets the function declarations.
	 *
	 * @since 7.0.0
	 *
	 * @param {...Object} functionDeclarations The function declarations.
	 * @return {PromptBuilder} this
	 */
	usingFunctionDeclarations( ...functionDeclarations ) {
		const current = this.modelConfig.functionDeclarations || [];
		this.modelConfig.functionDeclarations = [
			...current,
			...functionDeclarations,
		];
		return this;
	}

	/**
	 * Sets the presence penalty.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} presencePenalty The presence penalty.
	 * @return {PromptBuilder} this
	 */
	usingPresencePenalty( presencePenalty ) {
		this.modelConfig.presencePenalty = presencePenalty;
		return this;
	}

	/**
	 * Sets the frequency penalty.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} frequencyPenalty The frequency penalty.
	 * @return {PromptBuilder} this
	 */
	usingFrequencyPenalty( frequencyPenalty ) {
		this.modelConfig.frequencyPenalty = frequencyPenalty;
		return this;
	}

	/**
	 * Sets the web search configuration.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} webSearch The web search configuration.
	 * @return {PromptBuilder} this
	 */
	usingWebSearch( webSearch ) {
		this.modelConfig.webSearch = webSearch;
		return this;
	}

	/**
	 * Sets the request options.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} requestOptions The request options.
	 * @return {PromptBuilder} this
	 */
	usingRequestOptions( requestOptions ) {
		this.requestOptions = requestOptions;
		return this;
	}

	/**
	 * Sets the top logprobs.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} [topLogprobs] The top logprobs.
	 * @return {PromptBuilder} this
	 */
	usingTopLogprobs( topLogprobs ) {
		if ( topLogprobs !== undefined ) {
			this.modelConfig.topLogprobs = topLogprobs;
			this.modelConfig.logprobs = true;
		} else {
			this.modelConfig.logprobs = true;
		}
		return this;
	}

	/**
	 * Sets the output MIME type.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} mimeType The MIME type.
	 * @return {PromptBuilder} this
	 */
	asOutputMimeType( mimeType ) {
		this.modelConfig.outputMimeType = mimeType;
		return this;
	}

	/**
	 * Sets the output schema.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} schema The output schema.
	 * @return {PromptBuilder} this
	 */
	asOutputSchema( schema ) {
		this.modelConfig.outputSchema = schema;
		return this;
	}

	/**
	 * Sets the output modalities.
	 *
	 * @since 7.0.0
	 *
	 * @param {...string} modalities The output modalities.
	 * @return {PromptBuilder} this
	 */
	asOutputModalities( ...modalities ) {
		this._includeOutputModalities( ...modalities );
		return this;
	}

	/**
	 * Sets the output file type.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} fileType The output file type.
	 * @return {PromptBuilder} this
	 */
	asOutputFileType( fileType ) {
		this.modelConfig.outputFileType = fileType;
		return this;
	}

	/**
	 * Configures the response as JSON.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} [schema] Optional schema for the JSON response.
	 * @return {PromptBuilder} this
	 */
	asJsonResponse( schema ) {
		this.asOutputMimeType( 'application/json' );
		if ( schema ) {
			this.asOutputSchema( schema );
		}
		return this;
	}

	/**
	 * Checks if the current prompt is supported by the selected model.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} [capability] Optional capability to check support for.
	 * @return {Promise<boolean>} True if supported.
	 */
	async isSupported( capability ) {
		const response = await apiFetch( {
			path: '/wp-ai/v1/is-supported',
			method: 'POST',
			data: {
				messages: this.messages,
				modelConfig: this.modelConfig,
				providerId: this.providerId,
				modelId: this.modelId,
				modelPreferences: this.modelPreferences,
				capability,
				requestOptions: this.requestOptions,
			},
		} );

		return response.supported;
	}

	/**
	 * Checks if the prompt is supported for text generation.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<boolean>} True if text generation is supported.
	 */
	async isSupportedForTextGeneration() {
		return this.isSupported( Capability.TEXT_GENERATION );
	}

	/**
	 * Checks if the prompt is supported for image generation.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<boolean>} True if image generation is supported.
	 */
	async isSupportedForImageGeneration() {
		return this.isSupported( Capability.IMAGE_GENERATION );
	}

	/**
	 * Checks if the prompt is supported for text to speech conversion.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<boolean>} True if text to speech conversion is supported.
	 */
	async isSupportedForTextToSpeechConversion() {
		return this.isSupported( Capability.TEXT_TO_SPEECH_CONVERSION );
	}

	/**
	 * Checks if the prompt is supported for video generation.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<boolean>} True if video generation is supported.
	 */
	async isSupportedForVideoGeneration() {
		return this.isSupported( Capability.VIDEO_GENERATION );
	}

	/**
	 * Checks if the prompt is supported for speech generation.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<boolean>} True if speech generation is supported.
	 */
	async isSupportedForSpeechGeneration() {
		return this.isSupported( Capability.SPEECH_GENERATION );
	}

	/**
	 * Checks if the prompt is supported for music generation.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<boolean>} True if music generation is supported.
	 */
	async isSupportedForMusicGeneration() {
		return this.isSupported( Capability.MUSIC_GENERATION );
	}

	/**
	 * Checks if the prompt is supported for embedding generation.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<boolean>} True if embedding generation is supported.
	 */
	async isSupportedForEmbeddingGeneration() {
		return this.isSupported( Capability.EMBEDDING_GENERATION );
	}

	/**
	 * Generates a result using the configured model and prompt.
	 *
	 * @since 7.0.0
	 *
	 * @param {string} [capability] Optional capability to use.
	 * @return {Promise<GenerativeAiResult>} The generation result.
	 */
	async generateResult( capability ) {
		const result = await apiFetch( {
			path: '/wp-ai/v1/generate',
			method: 'POST',
			data: {
				messages: this.messages,
				modelConfig: this.modelConfig,
				providerId: this.providerId,
				modelId: this.modelId,
				modelPreferences: this.modelPreferences,
				capability,
				requestOptions: this.requestOptions,
			},
		} );

		return new GenerativeAiResult( result );
	}

	/**
	 * Generates a text result.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<GenerativeAiResult>} The generation result.
	 */
	async generateTextResult() {
		this._includeOutputModalities( Modality.TEXT );
		return this.generateResult( Capability.TEXT_GENERATION );
	}

	/**
	 * Generates an image result.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<GenerativeAiResult>} The generation result.
	 */
	async generateImageResult() {
		this._includeOutputModalities( Modality.IMAGE );
		return this.generateResult( Capability.IMAGE_GENERATION );
	}

	/**
	 * Generates a speech result.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<GenerativeAiResult>} The generation result.
	 */
	async generateSpeechResult() {
		this._includeOutputModalities( Modality.AUDIO );
		return this.generateResult( Capability.SPEECH_GENERATION );
	}

	/**
	 * Converts text to speech result.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<GenerativeAiResult>} The generation result.
	 */
	async convertTextToSpeechResult() {
		this._includeOutputModalities( Modality.AUDIO );
		return this.generateResult( Capability.TEXT_TO_SPEECH_CONVERSION );
	}

	/**
	 * Generates text.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<string>} The generated text.
	 */
	async generateText() {
		const result = await this.generateTextResult();
		return result.toText();
	}

	/**
	 * Generates multiple texts.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} [candidateCount] Optional candidate count.
	 * @return {Promise<string[]>} The generated texts.
	 */
	async generateTexts( candidateCount ) {
		if ( candidateCount ) {
			this.usingCandidateCount( candidateCount );
		}
		const result = await this.generateTextResult();
		return result.toTexts();
	}

	/**
	 * Generates an image.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<File>} The generated image file.
	 */
	async generateImage() {
		const result = await this.generateImageResult();
		return new File( result.toImageFile() );
	}

	/**
	 * Generates multiple images.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} [candidateCount] Optional candidate count.
	 * @return {Promise<File[]>} The generated image files.
	 */
	async generateImages( candidateCount ) {
		if ( candidateCount ) {
			this.usingCandidateCount( candidateCount );
		}
		const result = await this.generateImageResult();
		return result.toImageFiles().map( ( file ) => new File( file ) );
	}

	/**
	 * Converts text to speech.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<File>} The generated speech file.
	 */
	async convertTextToSpeech() {
		const result = await this.convertTextToSpeechResult();
		return new File( result.toAudioFile() );
	}

	/**
	 * Converts text to multiple speeches.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} [candidateCount] Optional candidate count.
	 * @return {Promise<File[]>} The generated speech files.
	 */
	async convertTextToSpeeches( candidateCount ) {
		if ( candidateCount ) {
			this.usingCandidateCount( candidateCount );
		}
		const result = await this.convertTextToSpeechResult();
		return result.toAudioFiles().map( ( file ) => new File( file ) );
	}

	/**
	 * Generates speech.
	 *
	 * @since 7.0.0
	 *
	 * @return {Promise<File>} The generated speech file.
	 */
	async generateSpeech() {
		const result = await this.generateSpeechResult();
		return new File( result.toAudioFile() );
	}

	/**
	 * Generates multiple speeches.
	 *
	 * @since 7.0.0
	 *
	 * @param {number} [candidateCount] Optional candidate count.
	 * @return {Promise<File[]>} The generated speech files.
	 */
	async generateSpeeches( candidateCount ) {
		if ( candidateCount ) {
			this.usingCandidateCount( candidateCount );
		}
		const result = await this.generateSpeechResult();
		return result.toAudioFiles().map( ( file ) => new File( file ) );
	}

	/**
	 * Appends a MessagePart to the messages array.
	 *
	 * @since 7.0.0
	 *
	 * @param {Object} part The part to append.
	 */
	_appendPartToMessages( part ) {
		const lastMessage = this.messages[ this.messages.length - 1 ];

		if ( lastMessage && lastMessage.role === MessageRole.USER ) {
			lastMessage.parts.push( part );
			return;
		}

		this.messages.push( {
			role: MessageRole.USER,
			parts: [ part ],
		} );
	}

	/**
	 * Parses input into a Message.
	 *
	 * @since 7.0.0
	 *
	 * @param {string|Object|Array} input       The input to parse.
	 * @param {string}              defaultRole The default role.
	 * @return {Object} The parsed message.
	 */
	_parseMessage( input, defaultRole ) {
		if ( input && input.role && input.parts ) {
			return input;
		}

		if ( input && input.type ) {
			return { role: defaultRole, parts: [ input ] };
		}

		if ( typeof input === 'string' ) {
			if ( input.trim() === '' ) {
				throw new Error(
					'Cannot create a message from an empty string.'
				);
			}
			return {
				role: defaultRole,
				parts: [
					{
						channel: MessagePartChannel.CONTENT,
						type: MessagePartType.TEXT,
						text: input,
					},
				],
			};
		}

		if ( Array.isArray( input ) ) {
			if ( input.length === 0 ) {
				throw new Error(
					'Cannot create a message from an empty array.'
				);
			}
			const parts = [];
			for ( const item of input ) {
				if ( typeof item === 'string' ) {
					parts.push( {
						channel: MessagePartChannel.CONTENT,
						type: MessagePartType.TEXT,
						text: item,
					} );
				} else {
					parts.push( item );
				}
			}
			return { role: defaultRole, parts };
		}

		throw new Error( 'Invalid input for message.' );
	}

	/**
	 * Checks if the value is a list of Message objects.
	 *
	 * @since 7.0.0
	 *
	 * @param {*} value The value to check.
	 * @return {boolean} True if the value is a list of Message objects.
	 */
	_isMessagesList( value ) {
		if ( ! Array.isArray( value ) || value.length === 0 ) {
			return false;
		}
		return value[ 0 ].role !== undefined;
	}

	/**
	 * Includes output modalities if not already present.
	 *
	 * @since 7.0.0
	 *
	 * @param {...string} modalities The modalities to include.
	 */
	_includeOutputModalities( ...modalities ) {
		const current = this.modelConfig.outputModalities || [];
		const merged = Array.from( new Set( [ ...current, ...modalities ] ) );
		this.modelConfig.outputModalities = merged;
	}
}
