"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TermDescriptionEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function TermDescriptionEdit({
  attributes,
  setAttributes,
  mergedStyle
}) {
  const {
    textAlign
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    }),
    style: mergedStyle
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)("div", {
    className: "wp-block-term-description__placeholder"
  }, (0, _react.createElement)("span", null, (0, _i18n.__)('Term Description')))));
}
//# sourceMappingURL=edit.js.map