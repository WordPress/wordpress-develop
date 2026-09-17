/**
 * Globals which exist only when a script is running inside a Worker.
 *
 * These are declared by TypeScript's `webworker` lib, which cannot be added alongside the `dom` lib
 * because the two conflict. Code which may run in either context therefore has to declare them, and
 * must guard each one with a `typeof` check before use.
 */

declare var WorkerGlobalScope: Function | undefined;
