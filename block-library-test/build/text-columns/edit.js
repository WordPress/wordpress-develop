"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TextColumnsEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _deprecated = _interopRequireDefault(require("@wordpress/deprecated"));
/**
 * WordPress dependencies
 */

function TextColumnsEdit({
  attributes,
  setAttributes
}) {
  const {
    width,
    content,
    columns
  } = attributes;
  (0, _deprecated.default)('The Text Columns block', {
    since: '5.3',
    alternative: 'the Columns block'
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.BlockAlignmentToolbar, {
    value: width,
    onChange: nextWidth => setAttributes({
      width: nextWidth
    }),
    controls: ['center', 'wide', 'full']
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Columns'),
    value: columns,
    onChange: value => setAttributes({
      columns: value
    }),
    min: 2,
    max: 4,
    required: true
  }))), (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)({
      className: `align${width} columns-${columns}`
    })
  }, Array.from({
    length: columns
  }).map((_, index) => {
    return (0, _react.createElement)("div", {
      className: "wp-block-column",
      key: `column-${index}`
    }, (0, _react.createElement)(_blockEditor.RichText, {
      tagName: "p",
      value: content?.[index]?.children,
      onChange: nextContent => {
        setAttributes({
          content: [...content.slice(0, index), {
            children: nextContent
          }, ...content.slice(index + 1)]
        });
      },
      "aria-label": (0, _i18n.sprintf)(
      // translators: %d: column index (starting with 1)
      (0, _i18n.__)('Column %d text'), index + 1),
      placeholder: (0, _i18n.__)('New Column')
    }));
  })));
}
//# sourceMappingURL=edit.js.map