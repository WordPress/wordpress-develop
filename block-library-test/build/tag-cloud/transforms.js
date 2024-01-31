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
  from: [{
    type: 'block',
    blocks: ['core/categories'],
    transform: () => (0, _blocks.createBlock)('core/tag-cloud')
  }],
  to: [{
    type: 'block',
    blocks: ['core/categories'],
    transform: () => (0, _blocks.createBlock)('core/categories')
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map