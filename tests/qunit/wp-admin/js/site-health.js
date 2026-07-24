/* global SiteHealth */

QUnit.module( 'Site Health', function() {
	QUnit.test( 'updates state only for valid direct tests', function( assert ) {
		assert.deepEqual(
			SiteHealth.site_status.results,
			{
				direct_test: {
					status: 'good'
				}
			},
			'The test results contain locale-independent data.'
		);
		assert.deepEqual(
			SiteHealth.site_status.issues,
			{
				critical: 0,
				good: 1,
				recommended: 0
			},
			'The issue counts reflect the test results.'
		);
	} );
} );
