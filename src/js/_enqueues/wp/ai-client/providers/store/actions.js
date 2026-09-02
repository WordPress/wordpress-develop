/**
 * Store action creators.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

export const RECEIVE_PROVIDERS = 'RECEIVE_PROVIDERS';
export const RECEIVE_PROVIDER_MODELS = 'RECEIVE_PROVIDER_MODELS';

/**
 * Returns an action object used to receive providers into the store.
 *
 * @since 7.0.0
 *
 * @param {Array} providers Array of providers to store.
 * @return {Object} Action object.
 */
export function receiveProviders( providers ) {
	return {
		type: RECEIVE_PROVIDERS,
		providers,
	};
}

/**
 * Returns an action object used to receive models for a specific provider into the store.
 *
 * @since 7.0.0
 *
 * @param {string} providerId Provider ID.
 * @param {Array}  models     Array of models to store for the provider.
 * @return {Object} Action object.
 */
export function receiveProviderModels( providerId, models ) {
	return {
		type: RECEIVE_PROVIDER_MODELS,
		providerId,
		models,
	};
}
