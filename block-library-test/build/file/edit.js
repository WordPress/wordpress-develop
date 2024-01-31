"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.MIN_PREVIEW_HEIGHT = exports.MAX_PREVIEW_HEIGHT = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blob = require("@wordpress/blob");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
var _notices = require("@wordpress/notices");
var _inspector = _interopRequireDefault(require("./inspector"));
var _utils = require("./utils");
var _removeAnchorTag = _interopRequireDefault(require("../utils/remove-anchor-tag"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const MIN_PREVIEW_HEIGHT = exports.MIN_PREVIEW_HEIGHT = 200;
const MAX_PREVIEW_HEIGHT = exports.MAX_PREVIEW_HEIGHT = 2000;
function ClipboardToolbarButton({
  text,
  disabled
}) {
  const {
    createNotice
  } = (0, _data.useDispatch)(_notices.store);
  const ref = (0, _compose.useCopyToClipboard)(text, () => {
    createNotice('info', (0, _i18n.__)('Copied URL to clipboard.'), {
      isDismissible: true,
      type: 'snackbar'
    });
  });
  return (0, _react.createElement)(_components.ToolbarButton, {
    className: "components-clipboard-toolbar-button",
    ref: ref,
    disabled: disabled
  }, (0, _i18n.__)('Copy URL'));
}
function FileEdit({
  attributes,
  isSelected,
  setAttributes,
  clientId
}) {
  const {
    id,
    fileName,
    href,
    textLinkHref,
    textLinkTarget,
    showDownloadButton,
    downloadButtonText,
    displayPreview,
    previewHeight
  } = attributes;
  const {
    getSettings
  } = (0, _data.useSelect)(_blockEditor.store);
  const {
    media
  } = (0, _data.useSelect)(select => ({
    media: id === undefined ? undefined : select(_coreData.store).getMedia(id)
  }), [id]);
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  const {
    toggleSelection
  } = (0, _data.useDispatch)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    // Upload a file drag-and-dropped into the editor.
    if ((0, _blob.isBlobURL)(href)) {
      const file = (0, _blob.getBlobByURL)(href);
      getSettings().mediaUpload({
        filesList: [file],
        onFileChange: ([newMedia]) => onSelectFile(newMedia),
        onError: onUploadError
      });
      (0, _blob.revokeBlobURL)(href);
    }
    if (_blockEditor.RichText.isEmpty(downloadButtonText)) {
      setAttributes({
        downloadButtonText: (0, _i18n._x)('Download', 'button label')
      });
    }
  }, []);
  function onSelectFile(newMedia) {
    if (!newMedia || !newMedia.url) {
      return;
    }
    const isPdf = newMedia.url.endsWith('.pdf');
    setAttributes({
      href: newMedia.url,
      fileName: newMedia.title,
      textLinkHref: newMedia.url,
      id: newMedia.id,
      displayPreview: isPdf ? true : undefined,
      previewHeight: isPdf ? 600 : undefined,
      fileId: `wp-block-file--media-${clientId}`
    });
  }
  function onUploadError(message) {
    setAttributes({
      href: undefined
    });
    createErrorNotice(message, {
      type: 'snackbar'
    });
  }
  function changeLinkDestinationOption(newHref) {
    // Choose Media File or Attachment Page (when file is in Media Library).
    setAttributes({
      textLinkHref: newHref
    });
  }
  function changeOpenInNewWindow(newValue) {
    setAttributes({
      textLinkTarget: newValue ? '_blank' : false
    });
  }
  function changeShowDownloadButton(newValue) {
    setAttributes({
      showDownloadButton: newValue
    });
  }
  function changeDisplayPreview(newValue) {
    setAttributes({
      displayPreview: newValue
    });
  }
  function handleOnResizeStop(event, direction, elt, delta) {
    toggleSelection(true);
    const newHeight = parseInt(previewHeight + delta.height, 10);
    setAttributes({
      previewHeight: newHeight
    });
  }
  function changePreviewHeight(newValue) {
    const newHeight = Math.max(parseInt(newValue, 10), MIN_PREVIEW_HEIGHT);
    setAttributes({
      previewHeight: newHeight
    });
  }
  const attachmentPage = media && media.link;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)((0, _blob.isBlobURL)(href) && (0, _components.__unstableGetAnimateClassName)({
      type: 'loading'
    }), {
      'is-transient': (0, _blob.isBlobURL)(href)
    })
  });
  const displayPreviewInEditor = (0, _utils.browserSupportsPdfs)() && displayPreview;
  if (!href) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: _icons.file
      }),
      labels: {
        title: (0, _i18n.__)('File'),
        instructions: (0, _i18n.__)('Upload a file or pick one from your media library.')
      },
      onSelect: onSelectFile,
      onError: onUploadError,
      accept: "*"
    }));
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_inspector.default, {
    hrefs: {
      href,
      textLinkHref,
      attachmentPage
    },
    openInNewWindow: !!textLinkTarget,
    showDownloadButton,
    changeLinkDestinationOption,
    changeOpenInNewWindow,
    changeShowDownloadButton,
    displayPreview,
    changeDisplayPreview,
    previewHeight,
    changePreviewHeight
  }), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    mediaId: id,
    mediaURL: href,
    accept: "*",
    onSelect: onSelectFile,
    onError: onUploadError
  }), (0, _react.createElement)(ClipboardToolbarButton, {
    text: href,
    disabled: (0, _blob.isBlobURL)(href)
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, displayPreviewInEditor && (0, _react.createElement)(_components.ResizableBox, {
    size: {
      height: previewHeight
    },
    minHeight: MIN_PREVIEW_HEIGHT,
    maxHeight: MAX_PREVIEW_HEIGHT,
    minWidth: "100%",
    grid: [10, 10],
    enable: {
      top: false,
      right: false,
      bottom: true,
      left: false,
      topRight: false,
      bottomRight: false,
      bottomLeft: false,
      topLeft: false
    },
    onResizeStart: () => toggleSelection(false),
    onResizeStop: handleOnResizeStop,
    showHandle: isSelected
  }, (0, _react.createElement)("object", {
    className: "wp-block-file__preview",
    data: href,
    type: "application/pdf",
    "aria-label": (0, _i18n.__)('Embed of the selected PDF file.')
  }), !isSelected && (0, _react.createElement)("div", {
    className: "wp-block-file__preview-overlay"
  })), (0, _react.createElement)("div", {
    className: 'wp-block-file__content-wrapper'
  }, (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "a",
    value: fileName,
    placeholder: (0, _i18n.__)('Write file name…'),
    withoutInteractiveFormatting: true,
    onChange: text => setAttributes({
      fileName: (0, _removeAnchorTag.default)(text)
    }),
    href: textLinkHref
  }), showDownloadButton && (0, _react.createElement)("div", {
    className: 'wp-block-file__button-richtext-wrapper'
  }, (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "div" // Must be block-level or else cursor disappears.
    ,
    "aria-label": (0, _i18n.__)('Download button text'),
    className: (0, _classnames.default)('wp-block-file__button', (0, _blockEditor.__experimentalGetElementClassName)('button')),
    value: downloadButtonText,
    withoutInteractiveFormatting: true,
    placeholder: (0, _i18n.__)('Add text…'),
    onChange: text => setAttributes({
      downloadButtonText: (0, _removeAnchorTag.default)(text)
    })
  })))));
}
var _default = exports.default = FileEdit;
//# sourceMappingURL=edit.js.map