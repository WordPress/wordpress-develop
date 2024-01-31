"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _coreData = require("@wordpress/core-data");
var _date = require("@wordpress/date");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

/**
 * Renders the `core/comment-date` block on the editor.
 *
 * @param {Object} props                   React props.
 * @param {Object} props.setAttributes     Callback for updating block attributes.
 * @param {Object} props.attributes        Block attributes.
 * @param {string} props.attributes.format Format of the date.
 * @param {string} props.attributes.isLink Whether the author name should be linked.
 * @param {Object} props.context           Inherited context.
 * @param {string} props.context.commentId The comment ID.
 *
 * @return {JSX.Element} React element.
 */
function Edit({
  attributes: {
    format,
    isLink
  },
  context: {
    commentId
  },
  setAttributes
}) {
  const blockProps = (0, _blockEditor.useBlockProps)();
  let [date] = (0, _coreData.useEntityProp)('root', 'comment', 'date', commentId);
  const [siteFormat = (0, _date.getSettings)().formats.date] = (0, _coreData.useEntityProp)('root', 'site', 'date_format');
  const inspectorControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_blockEditor.__experimentalDateFormatPicker, {
    format: format,
    defaultFormat: siteFormat,
    onChange: nextFormat => setAttributes({
      format: nextFormat
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link to comment'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  })));
  if (!commentId || !date) {
    date = (0, _i18n._x)('Comment Date', 'block title');
  }
  let commentDate = date instanceof Date ? (0, _react.createElement)("time", {
    dateTime: (0, _date.dateI18n)('c', date)
  }, (0, _date.dateI18n)(format || siteFormat, date)) : (0, _react.createElement)("time", null, date);
  if (isLink) {
    commentDate = (0, _react.createElement)("a", {
      href: "#comment-date-pseudo-link",
      onClick: event => event.preventDefault()
    }, commentDate);
  }
  return (0, _react.createElement)(_react.Fragment, null, inspectorControls, (0, _react.createElement)("div", {
    ...blockProps
  }, commentDate));
}
//# sourceMappingURL=edit.js.map