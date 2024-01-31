"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = HTMLEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _preview = _interopRequireDefault(require("./preview"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function HTMLEdit({
  attributes,
  setAttributes,
  isSelected
}) {
  const [isPreview, setIsPreview] = (0, _element.useState)();
  const isDisabled = (0, _element.useContext)(_components.Disabled.Context);
  const instanceId = (0, _compose.useInstanceId)(HTMLEdit, 'html-edit-desc');
  function switchToPreview() {
    setIsPreview(true);
  }
  function switchToHTML() {
    setIsPreview(false);
  }
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: 'block-library-html__edit',
    'aria-describedby': isPreview ? instanceId : undefined
  });
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    className: "components-tab-button",
    isPressed: !isPreview,
    onClick: switchToHTML
  }, "HTML"), (0, _react.createElement)(_components.ToolbarButton, {
    className: "components-tab-button",
    isPressed: isPreview,
    onClick: switchToPreview
  }, (0, _i18n.__)('Preview')))), isPreview || isDisabled ? (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_preview.default, {
    content: attributes.content,
    isSelected: isSelected
  }), (0, _react.createElement)(_components.VisuallyHidden, {
    id: instanceId
  }, (0, _i18n.__)('HTML preview is not yet fully accessible. Please switch screen reader to virtualized mode to navigate the below iFrame.'))) : (0, _react.createElement)(_blockEditor.PlainText, {
    value: attributes.content,
    onChange: content => setAttributes({
      content
    }),
    placeholder: (0, _i18n.__)('Write HTML…'),
    "aria-label": (0, _i18n.__)('HTML')
  }));
}
//# sourceMappingURL=edit.js.map