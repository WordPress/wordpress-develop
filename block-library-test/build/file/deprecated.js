"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

// Version of the file block without PR#43050 removing the translated aria-label.
const v3 = {
  attributes: {
    id: {
      type: 'number'
    },
    href: {
      type: 'string'
    },
    fileId: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'id'
    },
    fileName: {
      type: 'string',
      source: 'html',
      selector: 'a:not([download])'
    },
    textLinkHref: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'href'
    },
    textLinkTarget: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'target'
    },
    showDownloadButton: {
      type: 'boolean',
      default: true
    },
    downloadButtonText: {
      type: 'string',
      source: 'html',
      selector: 'a[download]'
    },
    displayPreview: {
      type: 'boolean'
    },
    previewHeight: {
      type: 'number',
      default: 600
    }
  },
  supports: {
    anchor: true,
    align: true
  },
  save({
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
    const pdfEmbedLabel = _blockEditor.RichText.isEmpty(fileName) ? (0, _i18n.__)('PDF embed') : (0, _i18n.sprintf)( /* translators: %s: filename. */
    (0, _i18n.__)('Embed of %s.'), fileName);
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
};

// In #41239 the button was made an element button which added a `wp-element-button` classname
// to the download link element.
const v2 = {
  attributes: {
    id: {
      type: 'number'
    },
    href: {
      type: 'string'
    },
    fileId: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'id'
    },
    fileName: {
      type: 'string',
      source: 'html',
      selector: 'a:not([download])'
    },
    textLinkHref: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'href'
    },
    textLinkTarget: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'target'
    },
    showDownloadButton: {
      type: 'boolean',
      default: true
    },
    downloadButtonText: {
      type: 'string',
      source: 'html',
      selector: 'a[download]'
    },
    displayPreview: {
      type: 'boolean'
    },
    previewHeight: {
      type: 'number',
      default: 600
    }
  },
  supports: {
    anchor: true,
    align: true
  },
  save({
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
    const pdfEmbedLabel = _blockEditor.RichText.isEmpty(fileName) ? (0, _i18n.__)('PDF embed') : (0, _i18n.sprintf)( /* translators: %s: filename. */
    (0, _i18n.__)('Embed of %s.'), fileName);
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
      className: "wp-block-file__button",
      download: true,
      "aria-describedby": describedById
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: downloadButtonText
    })));
  }
};

// Version of the file block without PR#28062 accessibility fix.
const v1 = {
  attributes: {
    id: {
      type: 'number'
    },
    href: {
      type: 'string'
    },
    fileName: {
      type: 'string',
      source: 'html',
      selector: 'a:not([download])'
    },
    textLinkHref: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'href'
    },
    textLinkTarget: {
      type: 'string',
      source: 'attribute',
      selector: 'a:not([download])',
      attribute: 'target'
    },
    showDownloadButton: {
      type: 'boolean',
      default: true
    },
    downloadButtonText: {
      type: 'string',
      source: 'html',
      selector: 'a[download]'
    },
    displayPreview: {
      type: 'boolean'
    },
    previewHeight: {
      type: 'number',
      default: 600
    }
  },
  supports: {
    anchor: true,
    align: true
  },
  save({
    attributes
  }) {
    const {
      href,
      fileName,
      textLinkHref,
      textLinkTarget,
      showDownloadButton,
      downloadButtonText,
      displayPreview,
      previewHeight
    } = attributes;
    const pdfEmbedLabel = _blockEditor.RichText.isEmpty(fileName) ? (0, _i18n.__)('PDF embed') : (0, _i18n.sprintf)( /* translators: %s: filename. */
    (0, _i18n.__)('Embed of %s.'), fileName);
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
    })), !_blockEditor.RichText.isEmpty(fileName) && (0, _react.createElement)("a", {
      href: textLinkHref,
      target: textLinkTarget,
      rel: textLinkTarget ? 'noreferrer noopener' : undefined
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: fileName
    })), showDownloadButton && (0, _react.createElement)("a", {
      href: href,
      className: "wp-block-file__button",
      download: true
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: downloadButtonText
    })));
  }
};
const deprecated = [v3, v2, v1];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map