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
    blocks: ['core/paragraph'],
    transform: attributes => (0, _blocks.createBlock)('core/verse', attributes)
  }],
  to: [{
    type: 'block',
    blocks: ['core/paragraph'],
    transform: attributes => (0, _blocks.createBlock)('core/paragraph', attributes)
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map