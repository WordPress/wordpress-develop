"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CommentsEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _commentsInspectorControls = _interopRequireDefault(require("./comments-inspector-controls"));
var _commentsLegacy = _interopRequireDefault(require("./comments-legacy"));
var _template = _interopRequireDefault(require("./template"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function CommentsEdit(props) {
  const {
    attributes,
    setAttributes
  } = props;
  const {
    tagName: TagName,
    legacy
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: _template.default
  });
  if (legacy) {
    return (0, _react.createElement)(_commentsLegacy.default, {
      ...props
    });
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_commentsInspectorControls.default, {
    attributes: attributes,
    setAttributes: setAttributes
  }), (0, _react.createElement)(TagName, {
    ...innerBlocksProps
  }));
}
//# sourceMappingURL=index.js.map