"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

const transforms = {
  from: [{
    type: 'block',
    blocks: ['core/pullquote'],
    transform: ({
      value,
      citation,
      anchor,
      fontSize,
      style
    }) => {
      return (0, _blocks.createBlock)('core/quote', {
        citation,
        anchor,
        fontSize,
        style
      }, [(0, _blocks.createBlock)('core/paragraph', {
        content: value
      })]);
    }
  }, {
    type: 'prefix',
    prefix: '>',
    transform: content => (0, _blocks.createBlock)('core/quote', {}, [(0, _blocks.createBlock)('core/paragraph', {
      content
    })])
  }, {
    type: 'raw',
    schema: () => ({
      blockquote: {
        children: '*'
      }
    }),
    selector: 'blockquote',
    transform: (node, handler) => {
      return (0, _blocks.createBlock)('core/quote',
      // Don't try to parse any `cite` out of this content.
      // * There may be more than one cite.
      // * There may be more attribution text than just the cite.
      // * If the cite is nested in the quoted text, it's wrong to
      //   remove it.
      {}, handler({
        HTML: node.innerHTML,
        mode: 'BLOCKS'
      }));
    }
  }, {
    type: 'block',
    isMultiBlock: true,
    blocks: ['*'],
    isMatch: ({}, blocks) => {
      // When a single block is selected make the tranformation
      // available only to specific blocks that make sense.
      if (blocks.length === 1) {
        return ['core/paragraph', 'core/heading', 'core/list', 'core/pullquote'].includes(blocks[0].name);
      }
      return !blocks.some(({
        name
      }) => name === 'core/quote');
    },
    __experimentalConvert: blocks => (0, _blocks.createBlock)('core/quote', {}, blocks.map(block => (0, _blocks.createBlock)(block.name, block.attributes, block.innerBlocks)))
  }],
  to: [{
    type: 'block',
    blocks: ['core/pullquote'],
    isMatch: ({}, block) => {
      return block.innerBlocks.every(({
        name
      }) => name === 'core/paragraph');
    },
    transform: ({
      citation,
      anchor,
      fontSize,
      style
    }, innerBlocks) => {
      const value = innerBlocks.map(({
        attributes
      }) => `${attributes.content}`).join('<br>');
      return (0, _blocks.createBlock)('core/pullquote', {
        value,
        citation,
        anchor,
        fontSize,
        style
      });
    }
  }, {
    type: 'block',
    blocks: ['core/paragraph'],
    transform: ({
      citation
    }, innerBlocks) => _blockEditor.RichText.isEmpty(citation) ? innerBlocks : [...innerBlocks, (0, _blocks.createBlock)('core/paragraph', {
      content: citation
    })]
  }, {
    type: 'block',
    blocks: ['core/group'],
    transform: ({
      citation,
      anchor
    }, innerBlocks) => (0, _blocks.createBlock)('core/group', {
      anchor
    }, _blockEditor.RichText.isEmpty(citation) ? innerBlocks : [...innerBlocks, (0, _blocks.createBlock)('core/paragraph', {
      content: citation
    })])
  }],
  ungroup: ({
    citation
  }, innerBlocks) => _blockEditor.RichText.isEmpty(citation) ? innerBlocks : [...innerBlocks, (0, _blocks.createBlock)('core/paragraph', {
    content: citation
  })]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map