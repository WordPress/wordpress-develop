"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
exports.imageFillStyles = imageFillStyles;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _blob = require("@wordpress/blob");
var _notices = require("@wordpress/notices");
var _icons = require("@wordpress/icons");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Constants
 */
const ALLOWED_MEDIA_TYPES = ['image', 'video'];
const noop = () => {};
function imageFillStyles(url, focalPoint) {
  return url ? {
    backgroundImage: `url(${url})`,
    backgroundPosition: focalPoint ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%` : `50% 50%`
  } : {};
}
const ResizableBoxContainer = (0, _element.forwardRef)(({
  isSelected,
  isStackedOnMobile,
  ...props
}, ref) => {
  const isMobile = (0, _compose.useViewportMatch)('small', '<');
  return (0, _react.createElement)(_components.ResizableBox, {
    ref: ref,
    showHandle: isSelected && (!isMobile || !isStackedOnMobile),
    ...props
  });
});
function ToolbarEditButton({
  mediaId,
  mediaUrl,
  onSelectMedia
}) {
  return (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    mediaId: mediaId,
    mediaURL: mediaUrl,
    allowedTypes: ALLOWED_MEDIA_TYPES,
    accept: "image/*,video/*",
    onSelect: onSelectMedia
  }));
}
function PlaceholderContainer({
  className,
  mediaUrl,
  onSelectMedia
}) {
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  const onUploadError = message => {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  };
  return (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
    icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
      icon: _icons.media
    }),
    labels: {
      title: (0, _i18n.__)('Media area')
    },
    className: className,
    onSelect: onSelectMedia,
    accept: "image/*,video/*",
    allowedTypes: ALLOWED_MEDIA_TYPES,
    onError: onUploadError,
    disableMediaButtons: mediaUrl
  });
}
function MediaContainer(props, ref) {
  const {
    className,
    commitWidthChange,
    focalPoint,
    imageFill,
    isSelected,
    isStackedOnMobile,
    mediaAlt,
    mediaId,
    mediaPosition,
    mediaType,
    mediaUrl,
    mediaWidth,
    onSelectMedia,
    onWidthChange,
    enableResize
  } = props;
  const isTemporaryMedia = !mediaId && (0, _blob.isBlobURL)(mediaUrl);
  const {
    toggleSelection
  } = (0, _data.useDispatch)(_blockEditor.store);
  if (mediaUrl) {
    const onResizeStart = () => {
      toggleSelection(false);
    };
    const onResize = (event, direction, elt) => {
      onWidthChange(parseInt(elt.style.width));
    };
    const onResizeStop = (event, direction, elt) => {
      toggleSelection(true);
      commitWidthChange(parseInt(elt.style.width));
    };
    const enablePositions = {
      right: enableResize && mediaPosition === 'left',
      left: enableResize && mediaPosition === 'right'
    };
    const backgroundStyles = mediaType === 'image' && imageFill ? imageFillStyles(mediaUrl, focalPoint) : {};
    const mediaTypeRenderers = {
      image: () => (0, _react.createElement)("img", {
        src: mediaUrl,
        alt: mediaAlt
      }),
      video: () => (0, _react.createElement)("video", {
        controls: true,
        src: mediaUrl
      })
    };
    return (0, _react.createElement)(ResizableBoxContainer, {
      as: "figure",
      className: (0, _classnames.default)(className, 'editor-media-container__resizer', {
        'is-transient': isTemporaryMedia
      }),
      style: backgroundStyles,
      size: {
        width: mediaWidth + '%'
      },
      minWidth: "10%",
      maxWidth: "100%",
      enable: enablePositions,
      onResizeStart: onResizeStart,
      onResize: onResize,
      onResizeStop: onResizeStop,
      axis: "x",
      isSelected: isSelected,
      isStackedOnMobile: isStackedOnMobile,
      ref: ref
    }, (0, _react.createElement)(ToolbarEditButton, {
      onSelectMedia: onSelectMedia,
      mediaUrl: mediaUrl,
      mediaId: mediaId
    }), (mediaTypeRenderers[mediaType] || noop)(), isTemporaryMedia && (0, _react.createElement)(_components.Spinner, null), (0, _react.createElement)(PlaceholderContainer, {
      ...props
    }));
  }
  return (0, _react.createElement)(PlaceholderContainer, {
    ...props
  });
}
var _default = exports.default = (0, _element.forwardRef)(MediaContainer);
//# sourceMappingURL=media-container.js.map