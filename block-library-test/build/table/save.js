"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function save({
  attributes
}) {
  const {
    hasFixedLayout,
    head,
    body,
    foot,
    caption
  } = attributes;
  const isEmpty = !head.length && !body.length && !foot.length;
  if (isEmpty) {
    return null;
  }
  const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);
  const borderProps = (0, _blockEditor.__experimentalGetBorderClassesAndStyles)(attributes);
  const classes = (0, _classnames.default)(colorProps.className, borderProps.className, {
    'has-fixed-layout': hasFixedLayout
  });
  const hasCaption = !_blockEditor.RichText.isEmpty(caption);
  const Section = ({
    type,
    rows
  }) => {
    if (!rows.length) {
      return null;
    }
    const Tag = `t${type}`;
    return (0, _react.createElement)(Tag, null, rows.map(({
      cells
    }, rowIndex) => (0, _react.createElement)("tr", {
      key: rowIndex
    }, cells.map(({
      content,
      tag,
      scope,
      align,
      colspan,
      rowspan
    }, cellIndex) => {
      const cellClasses = (0, _classnames.default)({
        [`has-text-align-${align}`]: align
      });
      return (0, _react.createElement)(_blockEditor.RichText.Content, {
        className: cellClasses ? cellClasses : undefined,
        "data-align": align,
        tagName: tag,
        value: content,
        key: cellIndex,
        scope: tag === 'th' ? scope : undefined,
        colSpan: colspan,
        rowSpan: rowspan
      });
    }))));
  };
  return (0, _react.createElement)("figure", {
    ..._blockEditor.useBlockProps.save()
  }, (0, _react.createElement)("table", {
    className: classes === '' ? undefined : classes,
    style: {
      ...colorProps.style,
      ...borderProps.style
    }
  }, (0, _react.createElement)(Section, {
    type: "head",
    rows: head
  }), (0, _react.createElement)(Section, {
    type: "body",
    rows: body
  }), (0, _react.createElement)(Section, {
    type: "foot",
    rows: foot
  })), hasCaption && (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "figcaption",
    value: caption,
    className: (0, _blockEditor.__experimentalGetElementClassName)('caption')
  }));
}
//# sourceMappingURL=save.js.map