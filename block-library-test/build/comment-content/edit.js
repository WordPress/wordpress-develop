"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Renders the `core/comment-content` block on the editor.
 *
 * @param {Object} props                      React props.
 * @param {Object} props.setAttributes        Callback for updating block attributes.
 * @param {Object} props.attributes           Block attributes.
 * @param {string} props.attributes.textAlign The `textAlign` attribute.
 * @param {Object} props.context              Inherited context.
 * @param {string} props.context.commentId    The comment ID.
 *
 * @return {JSX.Element} React element.
 */
function Edit({
  setAttributes,
  attributes: {
    textAlign
  },
  context: {
    commentId
  }
}) {
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const [content] = (0, _coreData.useEntityProp)('root', 'comment', 'content', commentId);
  const blockControls = (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: newAlign => setAttributes({
      textAlign: newAlign
    })
  }));
  if (!commentId || !content) {
    return (0, _react.createElement)(_react.Fragment, null, blockControls, (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)("p", null, (0, _i18n._x)('Comment Content', 'block title'))));
  }
  return (0, _react.createElement)(_react.Fragment, null, blockControls, (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_components.Disabled, null, (0, _react.createElement)(_element.RawHTML, {
    key: "html"
  }, content.rendered))));
}
//# sourceMappingURL=edit.js.map