/**
 * Globals which the TypeScript `dom` lib does not declare.
 *
 * Each is optional, because none of them can be assumed to exist. Code must guard every one of them
 * with a `typeof` check, or reach it as a property of `window`, before use.
 */

/**
 * Present only when the script is running inside a Worker.
 *
 * This is declared by the `webworker` lib, which cannot be added alongside the `dom` lib because the
 * two conflict. Code which may run in either context therefore has to declare it.
 */
declare var WorkerGlobalScope: Function | undefined;
