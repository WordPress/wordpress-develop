/**
 * Store reducer.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

import { RECEIVE_PROVIDERS, RECEIVE_PROVIDER_MODELS } from './actions';

const DEFAULT_STATE = {
	providers: [],
	modelsByProvider: {},
	providerLookupMap: {},
	providerModelsLookupMap: {},
};

/**
 * Reducer managing the AI providers and models.
 *
 * @since 7.0.0
 *
 * @param {Object} state  Current state.
 * @param {Object} action Action to handle.
 * @return {Object} New state.
 */
export default function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case RECEIVE_PROVIDERS: {
			const { providers } = action;

			const providerLookupMap = {};
			providers.forEach( ( provider, index ) => {
				providerLookupMap[ provider.id ] = index;
			} );

			return {
				...state,
				providers,
				providerLookupMap,
			};
		}

		case RECEIVE_PROVIDER_MODELS: {
			const { providerId, models } = action;

			const providerModelsLookupMap = {};
			models.forEach( ( model, index ) => {
				providerModelsLookupMap[ model.id ] = index;
			} );

			return {
				...state,
				modelsByProvider: {
					...state.modelsByProvider,
					[ providerId ]: models,
				},
				providerModelsLookupMap: {
					...state.providerModelsLookupMap,
					[ providerId ]: providerModelsLookupMap,
				},
			};
		}

		default:
			return state;
	}
}
