/**
 * Store resolvers.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

import apiFetch from '@wordpress/api-fetch';
import { receiveProviders, receiveProviderModels } from './actions';

/**
 * Resolver for getProviders selector.
 *
 * @since 7.0.0
 *
 * @return {Function} Action function to resolve the selector.
 */
export function getProviders() {
	return async ( { dispatch } ) => {
		const providers = await apiFetch( {
			path: '/wp-ai/v1/providers',
		} );

		dispatch( receiveProviders( providers || [] ) );
	};
}

/**
 * Resolver for getProvider selector.
 *
 * Falls through to getProviders to ensure providers are loaded.
 *
 * @since 7.0.0
 *
 * @return {Function} Action function to resolve the selector.
 */
export function getProvider() {
	return ( { select } ) => {
		select.getProviders();
	};
}

/**
 * Resolver for getProviderModels selector.
 *
 * @since 7.0.0
 *
 * @param {string} providerId Provider ID.
 * @return {Function} Action function to resolve the selector.
 */
export function getProviderModels( providerId ) {
	return async ( { dispatch } ) => {
		let models = [];
		try {
			models = await apiFetch( {
				path: `/wp-ai/v1/providers/${ providerId }/models`,
			} );
		} catch ( error ) {
			// If the provider is not configured, ignore the error and return an empty models array.
			if (
				typeof error === 'object' &&
				error !== null &&
				'code' in error &&
				error.code === 'ai_provider_not_configured'
			) {
				models = [];
			} else {
				throw error;
			}
		}

		dispatch( receiveProviderModels( providerId, models ) );
	};
}

/**
 * Resolver for getProviderModel selector.
 *
 * Falls through to getProviderModels to ensure models are loaded.
 *
 * @since 7.0.0
 *
 * @param {string} providerId Provider ID.
 * @return {Function} Action function to resolve the selector.
 */
export function getProviderModel( providerId ) {
	return ( { select } ) => {
		select.getProviderModels( providerId );
	};
}
