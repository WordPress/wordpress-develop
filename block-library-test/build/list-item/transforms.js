"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

const transforms = {
  to: [{
    type: 'block',
    blocks: ['core/paragraph'],
    transform: (attributes, innerBlocks = []) => [(0, _blocks.createBlock)('core/paragraph', attributes), ...innerBlocks.map(block => (0, _blocks.cloneBlock)(block))]
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map