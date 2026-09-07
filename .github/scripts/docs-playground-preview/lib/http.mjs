// A stalled connection without a deadline runs until the job timeout kills it,
// which leaves a preview reported as still building and no useful log.
export const REQUEST_TIMEOUT_MS = 30_000;

// Snapshots reach 100 MiB and travel through a third-party proxy, so transfers
// need a deadline far longer than an API call yet shorter than any job.
export const TRANSFER_TIMEOUT_MS = 300_000;
