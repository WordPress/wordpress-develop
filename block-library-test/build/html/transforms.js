"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
var _richText = require("@wordpress/rich-text");
/**
 * WordPress dependencies
 */

const transforms = {
  from: [{
    type: 'block',
    blocks: ['core/code'],
    transform: ({
      content: html
    }) => {
      return (0, _blocks.createBlock)('core/html', {
        // The code block may output HTML formatting, so convert it
        // to plain text.
        content: (0, _richText.create)({
          html
        }).text
      });
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map