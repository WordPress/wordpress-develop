"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = initBlock;
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

/**
 * Function to register an individual block.
 *
 * @param {Object} block The block to be registered.
 *
 * @return {WPBlockType | undefined} The block, if it has been successfully registered;
 *                        otherwise `undefined`.
 */
function initBlock(block) {
  if (!block) {
    return;
  }
  const {
    metadata,
    settings,
    name
  } = block;
  return (0, _blocks.registerBlockType)({
    name,
    ...metadata
  }, settings);
}
//# sourceMappingURL=init-block.js.map