"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = FileBlockInspector;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _edit = require("./edit");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function FileBlockInspector({
  hrefs,
  openInNewWindow,
  showDownloadButton,
  changeLinkDestinationOption,
  changeOpenInNewWindow,
  changeShowDownloadButton,
  displayPreview,
  changeDisplayPreview,
  previewHeight,
  changePreviewHeight
}) {
  const {
    href,
    textLinkHref,
    attachmentPage
  } = hrefs;
  let linkDestinationOptions = [{
    value: href,
    label: (0, _i18n.__)('URL')
  }];
  if (attachmentPage) {
    linkDestinationOptions = [{
      value: href,
      label: (0, _i18n.__)('Media file')
    }, {
      value: attachmentPage,
      label: (0, _i18n.__)('Attachment page')
    }];
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, href.endsWith('.pdf') && (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('PDF settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show inline embed'),
    help: displayPreview ? (0, _i18n.__)("Note: Most phone and tablet browsers won't display embedded PDFs.") : null,
    checked: !!displayPreview,
    onChange: changeDisplayPreview
  }), displayPreview && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Height in pixels'),
    min: _edit.MIN_PREVIEW_HEIGHT,
    max: Math.max(_edit.MAX_PREVIEW_HEIGHT, previewHeight),
    value: previewHeight,
    onChange: changePreviewHeight
  })), (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link to'),
    value: textLinkHref,
    options: linkDestinationOptions,
    onChange: changeLinkDestinationOption
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open in new tab'),
    checked: openInNewWindow,
    onChange: changeOpenInNewWindow
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show download button'),
    checked: showDownloadButton,
    onChange: changeShowDownloadButton
  }))));
}
//# sourceMappingURL=inspector.js.map