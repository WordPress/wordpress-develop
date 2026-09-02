/**
 * Store selectors.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

const EMPTY_MODELS_ARRAY = [];

/**
 * Returns all registered AI providers.
 *
 * @since 7.0.0
 *
 * @param {Object} state Store state.
 * @return {Array} Array of providers.
 */
export const getProviders = ( state ) => {
	return state.providers;
};

/**
 * Returns a specific provider by its ID.
 *
 * @since 7.0.0
 *
 * @param {Object} state Store state.
 * @param {string} id    Provider ID.
 * @return {Object|undefined} Provider object, or undefined if not found.
 */
export function getProvider( state, id ) {
	if ( ! ( id in state.providerLookupMap ) ) {
		return undefined;
	}

	const index = state.providerLookupMap[ id ];
	return state.providers[ index ];
}

/**
 * Returns all models for a specific provider.
 *
 * @since 7.0.0
 *
 * @param {Object} state      Store state.
 * @param {string} providerId Provider ID.
 * @return {Array} Array of models for the provider.
 */
export const getProviderModels = ( state, providerId ) => {
	return state.modelsByProvider[ providerId ] || EMPTY_MODELS_ARRAY;
};

/**
 * Returns a specific model by its ID for a provider.
 *
 * @since 7.0.0
 *
 * @param {Object} state      Store state.
 * @param {string} providerId Provider ID.
 * @param {string} modelId    Model ID.
 * @return {Object|undefined} Model object, or undefined if not found.
 */
export function getProviderModel( state, providerId, modelId ) {
	if (
		! ( providerId in state.providerModelsLookupMap ) ||
		! ( modelId in state.providerModelsLookupMap[ providerId ] )
	) {
		return undefined;
	}

	const index = state.providerModelsLookupMap[ providerId ][ modelId ];
	return state.modelsByProvider[ providerId ][ index ];
}
