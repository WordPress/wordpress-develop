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
    blocks: ['core/columns'],
    transform: ({
      className,
      columns,
      content,
      width
    }) => (0, _blocks.createBlock)('core/columns', {
      align: 'wide' === width || 'full' === width ? width : undefined,
      className,
      columns
    }, content.map(({
      children
    }) => (0, _blocks.createBlock)('core/column', {}, [(0, _blocks.createBlock)('core/paragraph', {
      content: children
    })])))
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map