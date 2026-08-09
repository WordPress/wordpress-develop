import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
	createSnapshotArguments,
	PLAYGROUND_BUILD_WORKERS,
} from '../lib/playground.mjs';

test( 'snapshot builds serialize Playground filesystem access', () => {
	assert.equal( PLAYGROUND_BUILD_WORKERS, 1 );
	assert.deepEqual(
		createSnapshotArguments( {
			php: '8.4',
			wp: '7.2-beta1',
			blueprint: '/tmp/blueprint.json',
			outfile: '/tmp/snapshot.zip',
			workers: 2,
			command: 'server',
		} ),
		{
			php: '8.4',
			wp: '7.2-beta1',
			blueprint: '/tmp/blueprint.json',
			outfile: '/tmp/snapshot.zip',
			workers: PLAYGROUND_BUILD_WORKERS,
			command: 'build-snapshot',
		}
	);
} );
