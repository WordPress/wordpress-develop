"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.unlock = exports.lock = void 0;
var _privateApis = require("@wordpress/private-apis");
/**
 * WordPress dependencies
 */

const {
  lock,
  unlock
} = (0, _privateApis.__dangerousOptInToUnstableAPIsOnlyForCoreModules)('I know using unstable features means my theme or plugin will inevitably break in the next version of WordPress.', '@wordpress/block-library');
exports.unlock = unlock;
exports.lock = lock;
//# sourceMappingURL=lock-unlock.js.map