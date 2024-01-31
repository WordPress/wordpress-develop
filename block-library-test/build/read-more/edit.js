"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ReadMore;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _blocks = require("@wordpress/blocks");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

function ReadMore({
  attributes: {
    content,
    linkTarget
  },
  setAttributes,
  insertBlocksAfter
}) {
  const blockProps = (0, _blockEditor.useBlockProps)();
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }))), (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "a",
    "aria-label": (0, _i18n.__)('“Read more” link text'),
    placeholder: (0, _i18n.__)('Read more'),
    value: content,
    onChange: newValue => setAttributes({
      content: newValue
    }),
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)())),
    withoutInteractiveFormatting: true,
    ...blockProps
  }));
}
//# sourceMappingURL=edit.js.map