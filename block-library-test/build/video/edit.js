"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blob = require("@wordpress/blob");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _icons = require("@wordpress/icons");
var _notices = require("@wordpress/notices");
var _util = require("../embed/util");
var _editCommonSettings = _interopRequireDefault(require("./edit-common-settings"));
var _tracksEditor = _interopRequireDefault(require("./tracks-editor"));
var _tracks = _interopRequireDefault(require("./tracks"));
var _caption = require("../utils/caption");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

// Much of this description is duplicated from MediaPlaceholder.
const placeholder = content => {
  return (0, _react.createElement)(_components.Placeholder, {
    className: "block-editor-media-placeholder",
    withIllustration: true,
    icon: _icons.video,
    label: (0, _i18n.__)('Video'),
    instructions: (0, _i18n.__)('Upload a video file, pick one from your media library, or add one with a URL.')
  }, content);
};
const ALLOWED_MEDIA_TYPES = ['video'];
const VIDEO_POSTER_ALLOWED_MEDIA_TYPES = ['image'];
function VideoEdit({
  isSelected: isSingleSelected,
  attributes,
  className,
  setAttributes,
  insertBlocksAfter,
  onReplace
}) {
  const instanceId = (0, _compose.useInstanceId)(VideoEdit);
  const videoPlayer = (0, _element.useRef)();
  const posterImageButton = (0, _element.useRef)();
  const {
    id,
    controls,
    poster,
    src,
    tracks
  } = attributes;
  const isTemporaryVideo = !id && (0, _blob.isBlobURL)(src);
  const {
    getSettings
  } = (0, _data.useSelect)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    if (!id && (0, _blob.isBlobURL)(src)) {
      const file = (0, _blob.getBlobByURL)(src);
      if (file) {
        getSettings().mediaUpload({
          filesList: [file],
          onFileChange: ([media]) => onSelectVideo(media),
          onError: onUploadError,
          allowedTypes: ALLOWED_MEDIA_TYPES
        });
      }
    }
  }, []);
  (0, _element.useEffect)(() => {
    // Placeholder may be rendered.
    if (videoPlayer.current) {
      videoPlayer.current.load();
    }
  }, [poster]);
  function onSelectVideo(media) {
    if (!media || !media.url) {
      // In this case there was an error
      // previous attributes should be removed
      // because they may be temporary blob urls.
      setAttributes({
        src: undefined,
        id: undefined,
        poster: undefined,
        caption: undefined
      });
      return;
    }

    // Sets the block's attribute and updates the edit component from the
    // selected media.
    setAttributes({
      src: media.url,
      id: media.id,
      poster: media.image?.src !== media.icon ? media.image?.src : undefined,
      caption: media.caption
    });
  }
  function onSelectURL(newSrc) {
    if (newSrc !== src) {
      // Check if there's an embed block that handles this URL.
      const embedBlock = (0, _util.createUpgradedEmbedBlock)({
        attributes: {
          url: newSrc
        }
      });
      if (undefined !== embedBlock && onReplace) {
        onReplace(embedBlock);
        return;
      }
      setAttributes({
        src: newSrc,
        id: undefined,
        poster: undefined
      });
    }
  }
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  function onUploadError(message) {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  }
  const classes = (0, _classnames.default)(className, {
    'is-transient': isTemporaryVideo
  });
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: classes
  });
  if (!src) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: _icons.video
      }),
      onSelect: onSelectVideo,
      onSelectURL: onSelectURL,
      accept: "video/*",
      allowedTypes: ALLOWED_MEDIA_TYPES,
      value: attributes,
      onError: onUploadError,
      placeholder: placeholder
    }));
  }
  function onSelectPoster(image) {
    setAttributes({
      poster: image.url
    });
  }
  function onRemovePoster() {
    setAttributes({
      poster: undefined
    });

    // Move focus back to the Media Upload button.
    posterImageButton.current.focus();
  }
  const videoPosterDescription = `video-block__poster-image-description-${instanceId}`;
  return (0, _react.createElement)(_react.Fragment, null, isSingleSelected && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_tracksEditor.default, {
    tracks: tracks,
    onChange: newTracks => {
      setAttributes({
        tracks: newTracks
      });
    }
  })), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    mediaId: id,
    mediaURL: src,
    allowedTypes: ALLOWED_MEDIA_TYPES,
    accept: "video/*",
    onSelect: onSelectVideo,
    onSelectURL: onSelectURL,
    onError: onUploadError
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_editCommonSettings.default, {
    setAttributes: setAttributes,
    attributes: attributes
  }), (0, _react.createElement)(_blockEditor.MediaUploadCheck, null, (0, _react.createElement)(_components.BaseControl, {
    className: "editor-video-poster-control"
  }, (0, _react.createElement)(_components.BaseControl.VisualLabel, null, (0, _i18n.__)('Poster image')), (0, _react.createElement)(_blockEditor.MediaUpload, {
    title: (0, _i18n.__)('Select poster image'),
    onSelect: onSelectPoster,
    allowedTypes: VIDEO_POSTER_ALLOWED_MEDIA_TYPES,
    render: ({
      open
    }) => (0, _react.createElement)(_components.Button, {
      variant: "primary",
      onClick: open,
      ref: posterImageButton,
      "aria-describedby": videoPosterDescription
    }, !poster ? (0, _i18n.__)('Select') : (0, _i18n.__)('Replace'))
  }), (0, _react.createElement)("p", {
    id: videoPosterDescription,
    hidden: true
  }, poster ? (0, _i18n.sprintf)( /* translators: %s: poster image URL. */
  (0, _i18n.__)('The current poster image url is %s'), poster) : (0, _i18n.__)('There is no poster image currently selected')), !!poster && (0, _react.createElement)(_components.Button, {
    onClick: onRemovePoster,
    variant: "tertiary"
  }, (0, _i18n.__)('Remove')))))), (0, _react.createElement)("figure", {
    ...blockProps
  }, (0, _react.createElement)(_components.Disabled, {
    isDisabled: !isSingleSelected
  }, (0, _react.createElement)("video", {
    controls: controls,
    poster: poster,
    src: src,
    ref: videoPlayer
  }, (0, _react.createElement)(_tracks.default, {
    tracks: tracks
  }))), isTemporaryVideo && (0, _react.createElement)(_components.Spinner, null), (0, _react.createElement)(_caption.Caption, {
    attributes: attributes,
    setAttributes: setAttributes,
    isSelected: isSingleSelected,
    insertBlocksAfter: insertBlocksAfter,
    label: (0, _i18n.__)('Video caption text'),
    showToolbarButton: isSingleSelected
  })));
}
var _default = exports.default = VideoEdit;
//# sourceMappingURL=edit.js.map