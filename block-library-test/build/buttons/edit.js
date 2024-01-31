"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _blocks = require("@wordpress/blocks");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const DEFAULT_BLOCK = {
  name: 'core/button',
  attributesToCopy: ['backgroundColor', 'border', 'className', 'fontFamily', 'fontSize', 'gradient', 'style', 'textColor', 'width']
};
function ButtonsEdit({
  attributes,
  className
}) {
  var _layout$orientation;
  const {
    fontSize,
    layout,
    style
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)(className, {
      'has-custom-font-size': fontSize || style?.typography?.fontSize
    })
  });
  const {
    preferredStyle,
    hasButtonVariations
  } = (0, _data.useSelect)(select => {
    const preferredStyleVariations = select(_blockEditor.store).getSettings().__experimentalPreferredStyleVariations;
    const buttonVariations = select(_blocks.store).getBlockVariations('core/button', 'inserter');
    return {
      preferredStyle: preferredStyleVariations?.value?.['core/button'],
      hasButtonVariations: buttonVariations.length > 0
    };
  }, []);
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    defaultBlock: DEFAULT_BLOCK,
    // This check should be handled by the `Inserter` internally to be consistent across all blocks that use it.
    directInsert: !hasButtonVariations,
    template: [['core/button', {
      className: preferredStyle && `is-style-${preferredStyle}`
    }]],
    templateInsertUpdatesSelection: true,
    orientation: (_layout$orientation = layout?.orientation) !== null && _layout$orientation !== void 0 ? _layout$orientation : 'horizontal'
  });
  return (0, _react.createElement)("div", {
    ...innerBlocksProps
  });
}
var _default = exports.default = ButtonsEdit;
//# sourceMappingURL=edit.js.map