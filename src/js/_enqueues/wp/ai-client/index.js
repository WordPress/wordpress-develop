/**
 * WordPress AI Client - Client-side API.
 *
 * @since 7.0.0
 *
 * @output wp-includes/js/dist/ai-client.js
 *
 * @package WordPress
 * @subpackage AI
 */

import { PromptBuilder } from './builders/prompt-builder';
import {
	getProviders,
	getProvider,
	getProviderModels,
	getProviderModel,
} from './providers/api';
import { store } from './providers/store';
import * as enums from './enums';

/**
 * Creates a new prompt builder for fluent API usage.
 *
 * @since 7.0.0
 *
 * @param {string|Object|Array} [promptInput] Optional initial prompt content.
 * @return {PromptBuilder} The prompt builder instance.
 */
export function prompt( promptInput ) {
	return new PromptBuilder( promptInput );
}

export {
	getProviders,
	getProvider,
	getProviderModels,
	getProviderModel,
	store,
	enums,
};

// Expose the API in the global `wp.aiClient` namespace for external use.
const AiClient = {
	prompt,
	getProviders,
	getProvider,
	getProviderModels,
	getProviderModel,
	store,
	enums,
};

if (
	typeof window !== 'undefined' &&
	'wp' in window &&
	typeof window.wp === 'object'
) {
	window.wp.aiClient = AiClient;
}
