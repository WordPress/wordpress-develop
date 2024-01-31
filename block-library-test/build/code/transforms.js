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
    type: 'enter',
    regExp: /^```$/,
    transform: () => (0, _blocks.createBlock)('core/code')
  }, {
    type: 'block',
    blocks: ['core/paragraph'],
    transform: ({
      content
    }) => (0, _blocks.createBlock)('core/code', {
      content
    })
  }, {
    type: 'block',
    blocks: ['core/html'],
    transform: ({
      content: text
    }) => {
      return (0, _blocks.createBlock)('core/code', {
        // The HTML is plain text (with plain line breaks), so
        // convert it to rich text.
        content: (0, _richText.toHTMLString)({
          value: (0, _richText.create)({
            text
          })
        })
      });
    }
  }, {
    type: 'raw',
    isMatch: node => node.nodeName === 'PRE' && node.children.length === 1 && node.firstChild.nodeName === 'CODE',
    schema: {
      pre: {
        children: {
          code: {
            children: {
              '#text': {}
            }
          }
        }
      }
    }
  }],
  to: [{
    type: 'block',
    blocks: ['core/paragraph'],
    transform: ({
      content
    }) => (0, _blocks.createBlock)('core/paragraph', {
      content
    })
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map