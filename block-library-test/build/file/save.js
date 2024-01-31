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
    href,
    fileId,
    fileName,
    textLinkHref,
    textLinkTarget,
    showDownloadButton,
    downloadButtonText,
    displayPreview,
    previewHeight
  } = attributes;
  const pdfEmbedLabel = _blockEditor.RichText.isEmpty(fileName) ? 'PDF embed' :
  // To do: use toPlainText, but we need ensure it's RichTextData. See
  // https://github.com/WordPress/gutenberg/pull/56710.
  fileName.toString();
  const hasFilename = !_blockEditor.RichText.isEmpty(fileName);

  // Only output an `aria-describedby` when the element it's referring to is
  // actually rendered.
  const describedById = hasFilename ? fileId : undefined;
  return href && (0, _react.createElement)("div", {
    ..._blockEditor.useBlockProps.save()
  }, displayPreview && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("object", {
    className: "wp-block-file__embed",
    data: href,
    type: "application/pdf",
    style: {
      width: '100%',
      height: `${previewHeight}px`
    },
    "aria-label": pdfEmbedLabel
  })), hasFilename && (0, _react.createElement)("a", {
    id: describedById,
    href: textLinkHref,
    target: textLinkTarget,
    rel: textLinkTarget ? 'noreferrer noopener' : undefined
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: fileName
  })), showDownloadButton && (0, _react.createElement)("a", {
    href: href,
    className: (0, _classnames.default)('wp-block-file__button', (0, _blockEditor.__experimentalGetElementClassName)('button')),
    download: true,
    "aria-describedby": describedById
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: downloadButtonText
  })));
}
//# sourceMappingURL=save.js.map