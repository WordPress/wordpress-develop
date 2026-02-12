/**
 * Providers/Models data store.
 *
 * @since 7.0.0
 *
 * @package WordPress
 * @subpackage AI
 */

import { createReduxStore, register } from '@wordpress/data';
import reducer from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';
import * as resolvers from './resolvers';
import { STORE_NAME } from './name';

export const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	resolvers,
} );

register( store );
