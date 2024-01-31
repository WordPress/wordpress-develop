"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
var _shared = require("./shared");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const transforms = {
  from: [{
    type: 'block',
    isMultiBlock: true,
    blocks: ['core/paragraph'],
    transform: attributes => attributes.map(({
      content,
      anchor,
      align: textAlign
    }) => (0, _blocks.createBlock)('core/heading', {
      content,
      anchor,
      textAlign
    }))
  }, {
    type: 'raw',
    selector: 'h1,h2,h3,h4,h5,h6',
    schema: ({
      phrasingContentSchema,
      isPaste
    }) => {
      const schema = {
        children: phrasingContentSchema,
        attributes: isPaste ? [] : ['style', 'id']
      };
      return {
        h1: schema,
        h2: schema,
        h3: schema,
        h4: schema,
        h5: schema,
        h6: schema
      };
    },
    transform(node) {
      const attributes = (0, _blocks.getBlockAttributes)('core/heading', node.outerHTML);
      const {
        textAlign
      } = node.style || {};
      attributes.level = (0, _shared.getLevelFromHeadingNodeName)(node.nodeName);
      if (textAlign === 'left' || textAlign === 'center' || textAlign === 'right') {
        attributes.align = textAlign;
      }
      return (0, _blocks.createBlock)('core/heading', attributes);
    }
  }, ...[1, 2, 3, 4, 5, 6].map(level => ({
    type: 'prefix',
    prefix: Array(level + 1).join('#'),
    transform(content) {
      return (0, _blocks.createBlock)('core/heading', {
        level,
        content
      });
    }
  })), ...[1, 2, 3, 4, 5, 6].map(level => ({
    type: 'enter',
    regExp: new RegExp(`^/(h|H)${level}$`),
    transform: () => (0, _blocks.createBlock)('core/heading', {
      level
    })
  }))],
  to: [{
    type: 'block',
    isMultiBlock: true,
    blocks: ['core/paragraph'],
    transform: attributes => attributes.map(({
      content,
      textAlign: align
    }) => (0, _blocks.createBlock)('core/paragraph', {
      content,
      align
    }))
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map