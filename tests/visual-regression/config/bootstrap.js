import { configureToMatchImageSnapshot } from 'jest-image-snapshot';

const toMatchImageSnapshot = configureToMatchImageSnapshot( {
	failureThreshold: 1,
} );

// Extend Jest's "expect" with image snapshot functionality.
expect.extend( { toMatchImageSnapshot } );
