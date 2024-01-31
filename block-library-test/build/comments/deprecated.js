"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

// v1: Deprecate the initial version of the block which was called "Comments
// Query Loop" instead of "Comments".
const v1 = {
  attributes: {
    tagName: {
      type: 'string',
      default: 'div'
    }
  },
  apiVersion: 3,
  supports: {
    align: ['wide', 'full'],
    html: false,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
        link: true
      }
    }
  },
  save({
    attributes: {
      tagName: Tag
    }
  }) {
    const blockProps = _blockEditor.useBlockProps.save();
    const {
      className
    } = blockProps;
    const classes = className?.split(' ') || [];

    // The ID of the previous version of the block
    // didn't have the `wp-block-comments` class,
    // so we need to remove it here in order to mimic it.
    const newClasses = classes?.filter(cls => cls !== 'wp-block-comments');
    const newBlockProps = {
      ...blockProps,
      className: newClasses.join(' ')
    };
    return (0, _react.createElement)(Tag, {
      ...newBlockProps
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
  }
};
var _default = exports.default = [v1];
//# sourceMappingURL=deprecated.js.map