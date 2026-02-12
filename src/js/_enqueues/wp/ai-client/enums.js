/**
 * Constants for PHP AI Client SDK Enums.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

export const FileType = {
	INLINE: 'inline',
	REMOTE: 'remote',
};

export const MediaOrientation = {
	SQUARE: 'square',
	LANDSCAPE: 'landscape',
	PORTRAIT: 'portrait',
};

export const FinishReason = {
	STOP: 'stop',
	LENGTH: 'length',
	CONTENT_FILTER: 'content_filter',
	TOOL_CALLS: 'tool_calls',
	ERROR: 'error',
};

export const OperationState = {
	STARTING: 'starting',
	PROCESSING: 'processing',
	SUCCEEDED: 'succeeded',
	FAILED: 'failed',
	CANCELED: 'canceled',
};

export const ToolType = {
	FUNCTION_DECLARATIONS: 'function_declarations',
	WEB_SEARCH: 'web_search',
};

export const ProviderType = {
	CLOUD: 'cloud',
	SERVER: 'server',
	CLIENT: 'client',
};

export const MessagePartType = {
	TEXT: 'text',
	FILE: 'file',
	FUNCTION_CALL: 'function_call',
	FUNCTION_RESPONSE: 'function_response',
};

export const MessagePartChannel = {
	CONTENT: 'content',
	THOUGHT: 'thought',
};

export const Modality = {
	TEXT: 'text',
	DOCUMENT: 'document',
	IMAGE: 'image',
	AUDIO: 'audio',
	VIDEO: 'video',
};

export const MessageRole = {
	USER: 'user',
	MODEL: 'model',
};

export const Capability = {
	TEXT_GENERATION: 'text_generation',
	IMAGE_GENERATION: 'image_generation',
	TEXT_TO_SPEECH_CONVERSION: 'text_to_speech_conversion',
	SPEECH_GENERATION: 'speech_generation',
	MUSIC_GENERATION: 'music_generation',
	VIDEO_GENERATION: 'video_generation',
	EMBEDDING_GENERATION: 'embedding_generation',
	CHAT_HISTORY: 'chat_history',
};
