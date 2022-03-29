/* global wp, sinon, JSON */
var communityEventsData, dateI18n, pagenow;

jQuery( document ).ready( function () {
	var getFormattedDate = wp.communityEvents.getFormattedDate,
		getTimeZone = wp.communityEvents.getTimeZone,
		getTimeZoneAbbreviation = wp.communityEvents.getTimeZoneAbbreviation,
		populateDynamicEventFields = wp.communityEvents.populateDynamicEventFields,
		startDate = 1600185600 * 1000, // Tue Sep 15 9:00:00 AM PDT 2020
		HOUR_IN_MS = 60 * 60 * 1000,
		DAY_IN_MS = HOUR_IN_MS * 24,
		WEEK_IN_MS = DAY_IN_MS * 7;

	QUnit.module( 'dashboard', function( hooks ) {
		hooks.beforeEach( function() {
			this.oldDateI18n = dateI18n;
			this.oldPagenow = pagenow;

			dateI18n = wp.date.dateI18n;
			pagenow = 'dashboard';

			communityEventsData = {
				time_format: 'g:i a',
				l10n: {
					date_formats: {
						single_day_event: 'l, M j, Y',
						multiple_day_event: '%1$s %2$d–%3$d, %4$d',
						multiple_month_event: '%1$s %2$d – %3$s %4$d, %5$d'
					}
				}
			};
		} );

		hooks.afterEach( function() {
			dateI18n = this.oldDateI18n;
			pagenow = this.oldPagenow;
		} );

		QUnit.module( 'communityEvents.populateDynamicEventFields', function() {
			QUnit.test( 'dynamic fields should be added', function( assert ) {
				var timeFormat = communityEventsData.time_format;

				var getFormattedDateStub = sinon.stub( wp.communityEvents, 'getFormattedDate' ),
					getTimeZoneStub = sinon.stub( wp.communityEvents, 'getTimeZone' ),
					getTimeZoneAbbreviationStub = sinon.stub( wp.communityEvents, 'getTimeZoneAbbreviation' );

				getFormattedDateStub.returns( 'Tuesday, Sep 15, 2020' );
				getTimeZoneStub.returns( 'America/Chicago' );
				getTimeZoneAbbreviationStub.returns( 'CDT' );

				var rawEvents = [
					{
						start_unix_timestamp: 1600185600,
						end_unix_timestamp: 1600189200
					},

					{
						start_unix_timestamp: 1602232400,
						end_unix_timestamp: 1602236000
					}
				];

				var expected = JSON.parse( JSON.stringify( rawEvents ) );
				expected[0].user_formatted_date = 'Tuesday, Sep 15, 2020';
				expected[0].user_formatted_time = '11:00 am';
				expected[0].timeZoneAbbreviation = 'CDT';

				expected[1].user_formatted_date = 'Tuesday, Sep 15, 2020'; // This is expected to be the same as item 0, because of the stub.
				expected[1].user_formatted_time = '3:33 am';
				expected[1].timeZoneAbbreviation = 'CDT';

				var actual = populateDynamicEventFields( rawEvents, timeFormat );

				assert.strictEqual(
					JSON.stringify( actual ),
					JSON.stringify( expected )
				);

				getFormattedDateStub.restore();
				getTimeZoneStub.restore();
				getTimeZoneAbbreviationStub.restore();
			} );
		} );


		QUnit.module( 'communityEvents.getFormattedDate', function() {
			QUnit.test( 'single month event should use corresponding format', function( assert ) {
				var actual = getFormattedDate(
					startDate,
					startDate + HOUR_IN_MS,
					'America/Vancouver',
					communityEventsData.l10n.date_formats
				);

				assert.strictEqual( actual, 'Tuesday, Sep 15, 2020' );
			} );

			QUnit.test( 'multiple day event should use corresponding format', function( assert ) {
				var actual = getFormattedDate(
					startDate,
					startDate + ( 2 * DAY_IN_MS ),
					'America/Vancouver',
					communityEventsData.l10n.date_formats
				);

				assert.strictEqual( actual, 'September 15–17, 2020' );
			} );

			QUnit.test( 'multiple month event should use corresponding format', function( assert ) {
				var actual = getFormattedDate(
					startDate,
					startDate + ( 3 * WEEK_IN_MS ),
					'America/Vancouver',
					communityEventsData.l10n.date_formats
				);

				assert.strictEqual( actual, 'September 15 – October 6, 2020' );
			} );

			QUnit.test( 'undefined end date should be treated as a single-day event', function( assert ) {
				var actual = getFormattedDate(
					startDate,
					undefined,
					'America/Vancouver',
					communityEventsData.l10n.date_formats
				);

				assert.strictEqual( actual, 'Tuesday, Sep 15, 2020' );
			} );

			QUnit.test( 'empty end date should be treated as a single-day event', function( assert ) {
				var actual = getFormattedDate(
					startDate,
					'',
					'America/Vancouver',
					communityEventsData.l10n.date_formats
				);

				assert.strictEqual( actual, 'Tuesday, Sep 15, 2020' );
			} );
		} );


		QUnit.module( 'communityEvents.getTimeZone', function() {
			QUnit.test( 'modern browsers should return a time zone name', function( assert ) {
				// Simulate a modern browser.
				var stub = sinon.stub( Intl.DateTimeFormat.prototype, 'resolvedOptions' );
				stub.returns( { timeZone: 'America/Chicago' } );

				var actual = getTimeZone( startDate );

				stub.restore();

				assert.strictEqual( actual, 'America/Chicago' );
			} );

			QUnit.test( 'older browsers should fallback to a raw UTC offset', function( assert ) {
				// Simulate IE11.
				var resolvedOptionsStub = sinon.stub( Intl.DateTimeFormat.prototype, 'resolvedOptions' );
				var getTimezoneOffsetStub = sinon.stub( Date.prototype, 'getTimezoneOffset' );

				resolvedOptionsStub.returns( { timeZone: undefined } );

				getTimezoneOffsetStub.returns( 300 );
				var actual = getTimeZone( startDate );
				assert.strictEqual( actual, -300, 'negative offset' ); // Intentionally opposite, see `getTimeZone()`.

				getTimezoneOffsetStub.returns( 0 );
				actual = getTimeZone( startDate );
				assert.strictEqual( actual, 0, 'no offset' );

				getTimezoneOffsetStub.returns( -300 );
				actual = getTimeZone( startDate );
				assert.strictEqual( actual, 300, 'positive offset' ); // Intentionally opposite, see `getTimeZone()`.

				resolvedOptionsStub.restore();
				getTimezoneOffsetStub.restore();
			} );
		} );


		QUnit.module( 'communityEvents.getTimeZoneAbbreviation', function() {
			QUnit.test( 'modern browsers should return a time zone abbreviation', function( assert ) {
				// Modern browsers append a short time zone code to the time string.
				var stub = sinon.stub( Date.prototype, 'toLocaleTimeString' );
				stub.returns( '4:00:00 PM CDT' );

				var actual = getTimeZoneAbbreviation( startDate );

				stub.restore();

				assert.strictEqual( actual, 'CDT' );
			} );

			QUnit.test( 'older browsers should fallback to a formatted UTC offset', function( assert ) {
				var toLocaleTimeStringStub = sinon.stub( Date.prototype, 'toLocaleTimeString' );
				var getTimezoneOffsetStub = sinon.stub( Date.prototype, 'getTimezoneOffset' );

				// IE 11 doesn't add the abbreviation like modern browsers do.
				toLocaleTimeStringStub.returns( '4:00:00 PM' );

				getTimezoneOffsetStub.returns( 300 );
				var actual = getTimeZoneAbbreviation( startDate );
				assert.strictEqual( actual, 'GMT-5', 'negative offset' ); // Intentionally opposite, see `getTimeZone()`.

				getTimezoneOffsetStub.returns( 0 );
				actual = getTimeZoneAbbreviation( startDate );
				assert.strictEqual( actual, 'GMT+0', 'no offset' );

				getTimezoneOffsetStub.returns( -300 );
				actual = getTimeZoneAbbreviation( startDate );
				assert.strictEqual( actual, 'GMT+5', 'positive offset' ); // Intentionally opposite, see `getTimeZone()`.

				toLocaleTimeStringStub.restore();
				getTimezoneOffsetStub.restore();
			} );
		} );
	} );
} );
