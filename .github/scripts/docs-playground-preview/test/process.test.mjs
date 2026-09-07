import assert from 'node:assert/strict';
import { test } from 'node:test';

import { run } from '../lib/process.mjs';

test( 'captured output survives multibyte characters split across chunks', async () => {
	// Three bytes per character and far more than one pipe chunk guarantees a
	// character straddles a chunk boundary.
	const repetitions = 100000;
	const result = await run(
		process.execPath,
		[ '-e', `process.stdout.write( '…'.repeat( ${ repetitions } ) )` ],
		{ capture: true, quiet: true }
	);
	assert.equal( result.code, 0 );
	assert.equal( result.stdout, '…'.repeat( repetitions ) );
	assert.ok( ! result.stdout.includes( '�' ) );
} );
