/**
 * Provider API functions.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

import { resolveSelect } from '@wordpress/data';
import { store } from './store';

/**
 * Gets all registered AI providers.
 *
 * @since 7.0.0
 *
 * @return {Promise<Array>} Promise resolving to array of providers.
 */
export async function getProviders() {
	return await resolveSelect( store ).getProviders();
}

/**
 * Gets a specific provider by its ID.
 *
 * @since 7.0.0
 *
 * @param {string} id Provider ID.
 * @return {Promise<Object|undefined>} Promise resolving to provider object, or undefined if not found.
 */
export async function getProvider( id ) {
	return await resolveSelect( store ).getProvider( id );
}

/**
 * Gets all models for a specific provider.
 *
 * @since 7.0.0
 *
 * @param {string} providerId Provider ID.
 * @return {Promise<Array>} Promise resolving to array of models for the provider.
 */
export async function getProviderModels( providerId ) {
	return await resolveSelect( store ).getProviderModels( providerId );
}

/**
 * Gets a specific model by its ID for a provider.
 *
 * @since 7.0.0
 *
 * @param {string} providerId Provider ID.
 * @param {string} modelId    Model ID.
 * @return {Promise<Object|undefined>} Promise resolving to model object, or undefined if not found.
 */
export async function getProviderModel( providerId, modelId ) {
	const models = await resolveSelect( store ).getProviderModels( providerId );
	return models.find( ( model ) => model.id === modelId );
}
