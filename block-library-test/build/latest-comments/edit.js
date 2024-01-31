"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = LatestComments;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _serverSideRender = _interopRequireDefault(require("@wordpress/server-side-render"));
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

/**
 * Minimum number of comments a user can show using this block.
 *
 * @type {number}
 */
const MIN_COMMENTS = 1;
/**
 * Maximum number of comments a user can show using this block.
 *
 * @type {number}
 */
const MAX_COMMENTS = 100;
function LatestComments({
  attributes,
  setAttributes
}) {
  const {
    commentsToShow,
    displayAvatar,
    displayDate,
    displayExcerpt
  } = attributes;
  const serverSideAttributes = {
    ...attributes,
    style: {
      ...attributes?.style,
      spacing: undefined
    }
  };
  return (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)()
  }, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display avatar'),
    checked: displayAvatar,
    onChange: () => setAttributes({
      displayAvatar: !displayAvatar
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display date'),
    checked: displayDate,
    onChange: () => setAttributes({
      displayDate: !displayDate
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display excerpt'),
    checked: displayExcerpt,
    onChange: () => setAttributes({
      displayExcerpt: !displayExcerpt
    })
  }), (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Number of comments'),
    value: commentsToShow,
    onChange: value => setAttributes({
      commentsToShow: value
    }),
    min: MIN_COMMENTS,
    max: MAX_COMMENTS,
    required: true
  }))), (0, _react.createElement)(_components.Disabled, null, (0, _react.createElement)(_serverSideRender.default, {
    block: "core/latest-comments",
    attributes: serverSideAttributes
    // The preview uses the site's locale to make it more true to how
    // the block appears on the frontend. Setting the locale
    // explicitly prevents any middleware from setting it to 'user'.
    ,
    urlQueryArgs: {
      _locale: 'site'
    }
  })));
}
//# sourceMappingURL=edit.js.map