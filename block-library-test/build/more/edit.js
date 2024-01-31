"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = MoreEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _keycodes = require("@wordpress/keycodes");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

const DEFAULT_TEXT = (0, _i18n.__)('Read more');
function MoreEdit({
  attributes: {
    customText,
    noTeaser
  },
  insertBlocksAfter,
  setAttributes
}) {
  const onChangeInput = event => {
    setAttributes({
      customText: event.target.value !== '' ? event.target.value : undefined
    });
  };
  const onKeyDown = ({
    keyCode
  }) => {
    if (keyCode === _keycodes.ENTER) {
      insertBlocksAfter([(0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)())]);
    }
  };
  const getHideExcerptHelp = checked => checked ? (0, _i18n.__)('The excerpt is hidden.') : (0, _i18n.__)('The excerpt is visible.');
  const toggleHideExcerpt = () => setAttributes({
    noTeaser: !noTeaser
  });
  const style = {
    width: `${(customText ? customText : DEFAULT_TEXT).length + 1.2}em`
  };
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Hide the excerpt on the full content page'),
    checked: !!noTeaser,
    onChange: toggleHideExcerpt,
    help: getHideExcerptHelp
  }))), (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)()
  }, (0, _react.createElement)("input", {
    "aria-label": (0, _i18n.__)('“Read more” link text'),
    type: "text",
    value: customText,
    placeholder: DEFAULT_TEXT,
    onChange: onChangeInput,
    onKeyDown: onKeyDown,
    style: style
  })));
}
//# sourceMappingURL=edit.js.map