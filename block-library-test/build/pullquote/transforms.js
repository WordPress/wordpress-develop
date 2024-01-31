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
    isMultiBlock: true,
    blocks: ['core/paragraph'],
    transform: attributes => {
      return (0, _blocks.createBlock)('core/pullquote', {
        value: (0, _richText.toHTMLString)({
          value: (0, _richText.join)(attributes.map(({
            content
          }) => (0, _richText.create)({
            html: content
          })), '\n')
        }),
        anchor: attributes.anchor
      });
    }
  }, {
    type: 'block',
    blocks: ['core/heading'],
    transform: ({
      content,
      anchor
    }) => {
      return (0, _blocks.createBlock)('core/pullquote', {
        value: content,
        anchor
      });
    }
  }],
  to: [{
    type: 'block',
    blocks: ['core/paragraph'],
    transform: ({
      value,
      citation
    }) => {
      const paragraphs = [];
      if (value) {
        paragraphs.push((0, _blocks.createBlock)('core/paragraph', {
          content: value
        }));
      }
      if (citation) {
        paragraphs.push((0, _blocks.createBlock)('core/paragraph', {
          content: citation
        }));
      }
      if (paragraphs.length === 0) {
        return (0, _blocks.createBlock)('core/paragraph', {
          content: ''
        });
      }
      return paragraphs;
    }
  }, {
    type: 'block',
    blocks: ['core/heading'],
    transform: ({
      value,
      citation
    }) => {
      // If there is no pullquote content, use the citation as the
      // content of the resulting heading. A nonexistent citation
      // will result in an empty heading.
      if (!value) {
        return (0, _blocks.createBlock)('core/heading', {
          content: citation
        });
      }
      const headingBlock = (0, _blocks.createBlock)('core/heading', {
        content: value
      });
      if (!citation) {
        return headingBlock;
      }
      return [headingBlock, (0, _blocks.createBlock)('core/heading', {
        content: citation
      })];
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map