import assert from 'node:assert/strict';
import { test } from 'node:test';

import { renderRuntimePlugin, validateProvenance } from '../lib/runtime.mjs';

const provenance = {
	sourceRepository: 'example/wordpress-develop',
	sourceSha: 'a'.repeat( 40 ),
	generationTimestamp: '2026-08-09T12:34:56.000Z',
	runUrl: 'https://github.com/example/wordpress-develop/actions/runs/123',
};

test( 'runtime provenance requires exact public build identity', () => {
	assert.equal( validateProvenance( provenance ), provenance );
	assert.throws(
		() => validateProvenance( { ...provenance, sourceSha: 'trunk' } ),
		/commit is invalid/
	);
	assert.throws(
		() =>
			validateProvenance( {
				...provenance,
				runUrl: 'https://example.com/',
			} ),
		/run URL is invalid/
	);
} );

test( 'runtime plugin exposes the banner, health, network, and policy behavior', () => {
	const plugin = renderRuntimePlugin( provenance );
	assert.match( plugin, /wporg-code-reference-preview-provenance/ );
	assert.match( plugin, /wp_body_open/ );
	assert.match( plugin, /wp_footer/ );
	assert.match( plugin, /docs-preview\/v1/ );
	assert.match( plugin, /docs_preview_network_disabled/ );
	for ( const name of [
		'DISABLE_WP_CRON',
		'AUTOMATIC_UPDATER_DISABLED',
		'WP_AUTO_UPDATE_CORE',
		'DISALLOW_FILE_MODS',
	] ) {
		assert.match( plugin, new RegExp( name ) );
	}
	assert.match( plugin, new RegExp( provenance.sourceSha ) );
	assert.match( plugin, new RegExp( provenance.runUrl ) );
} );

test( 'local runtime provenance may omit an Actions run', () => {
	const plugin = renderRuntimePlugin( { ...provenance, runUrl: null } );
	assert.match( plugin, /'runUrl'\s+=> null/ );
} );
