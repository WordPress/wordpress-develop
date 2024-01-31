"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
var _richText = require("@wordpress/rich-text");
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function getListContentSchema({
  phrasingContentSchema
}) {
  const listContentSchema = {
    ...phrasingContentSchema,
    ul: {},
    ol: {
      attributes: ['type', 'start', 'reversed']
    }
  };

  // Recursion is needed.
  // Possible: ul > li > ul.
  // Impossible: ul > ul.
  ['ul', 'ol'].forEach(tag => {
    listContentSchema[tag].children = {
      li: {
        children: listContentSchema
      }
    };
  });
  return listContentSchema;
}
function getListContentFlat(blocks) {
  return blocks.flatMap(({
    name,
    attributes,
    innerBlocks = []
  }) => {
    if (name === 'core/list-item') {
      return [attributes.content, ...getListContentFlat(innerBlocks)];
    }
    return getListContentFlat(innerBlocks);
  });
}
const transforms = {
  from: [{
    type: 'block',
    isMultiBlock: true,
    blocks: ['core/paragraph', 'core/heading'],
    transform: blockAttributes => {
      let childBlocks = [];
      if (blockAttributes.length > 1) {
        childBlocks = blockAttributes.map(({
          content
        }) => {
          return (0, _blocks.createBlock)('core/list-item', {
            content
          });
        });
      } else if (blockAttributes.length === 1) {
        const value = (0, _richText.create)({
          html: blockAttributes[0].content
        });
        childBlocks = (0, _richText.split)(value, '\n').map(result => {
          return (0, _blocks.createBlock)('core/list-item', {
            content: (0, _richText.toHTMLString)({
              value: result
            })
          });
        });
      }
      return (0, _blocks.createBlock)('core/list', {
        anchor: blockAttributes.anchor
      }, childBlocks);
    }
  }, {
    type: 'raw',
    selector: 'ol,ul',
    schema: args => ({
      ol: getListContentSchema(args).ol,
      ul: getListContentSchema(args).ul
    }),
    transform: _utils.createListBlockFromDOMElement
  }, ...['*', '-'].map(prefix => ({
    type: 'prefix',
    prefix,
    transform(content) {
      return (0, _blocks.createBlock)('core/list', {}, [(0, _blocks.createBlock)('core/list-item', {
        content
      })]);
    }
  })), ...['1.', '1)'].map(prefix => ({
    type: 'prefix',
    prefix,
    transform(content) {
      return (0, _blocks.createBlock)('core/list', {
        ordered: true
      }, [(0, _blocks.createBlock)('core/list-item', {
        content
      })]);
    }
  }))],
  to: [...['core/paragraph', 'core/heading'].map(block => ({
    type: 'block',
    blocks: [block],
    transform: (_attributes, childBlocks) => {
      return getListContentFlat(childBlocks).map(content => (0, _blocks.createBlock)(block, {
        content
      }));
    }
  }))]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map