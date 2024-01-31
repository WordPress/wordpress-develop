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
    blocks: ['core/post-content'],
    transform: () => (0, _blocks.createBlock)('core/post-excerpt')
  }],
  to: [{
    type: 'block',
    blocks: ['core/post-content'],
    transform: () => (0, _blocks.createBlock)('core/post-content')
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map