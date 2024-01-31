"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
Object.defineProperty(exports, "imageFillStyles", {
  enumerable: true,
  get: function () {
    return _mediaContainer.imageFillStyles;
  }
});
var _react = require("react");
var _reactNative = require("react-native");
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _url = require("@wordpress/url");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
var _style = _interopRequireDefault(require("./style.scss"));
var _iconRetry = _interopRequireDefault(require("./icon-retry"));
var _mediaContainer = require("./media-container.js");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Constants
 */
const ALLOWED_MEDIA_TYPES = [_blockEditor.MEDIA_TYPE_IMAGE, _blockEditor.MEDIA_TYPE_VIDEO];
const ICON_TYPE = {
  PLACEHOLDER: 'placeholder',
  RETRY: 'retry'
};
class MediaContainer extends _element.Component {
  constructor() {
    super(...arguments);
    this.updateMediaProgress = this.updateMediaProgress.bind(this);
    this.finishMediaUploadWithSuccess = this.finishMediaUploadWithSuccess.bind(this);
    this.finishMediaUploadWithFailure = this.finishMediaUploadWithFailure.bind(this);
    this.mediaUploadStateReset = this.mediaUploadStateReset.bind(this);
    this.onSelectMediaUploadOption = this.onSelectMediaUploadOption.bind(this);
    this.onMediaPressed = this.onMediaPressed.bind(this);
    this.state = {
      isUploadInProgress: false
    };
  }
  componentDidMount() {
    const {
      mediaId,
      mediaUrl
    } = this.props;

    // Make sure we mark any temporary images as failed if they failed while
    // the editor wasn't open.
    if (mediaId && mediaUrl && (0, _url.getProtocol)(mediaUrl) === 'file:') {
      (0, _reactNativeBridge.mediaUploadSync)();
    }
  }
  onSelectMediaUploadOption(params) {
    const {
      id,
      url,
      type
    } = params;
    const {
      onSelectMedia
    } = this.props;
    onSelectMedia({
      media_type: type,
      id,
      url
    });
  }
  onMediaPressed() {
    const {
      isUploadInProgress
    } = this.state;
    const {
      mediaId,
      mediaUrl,
      mediaType,
      isMediaSelected,
      onMediaSelected
    } = this.props;
    if (isUploadInProgress) {
      (0, _reactNativeBridge.requestImageUploadCancelDialog)(mediaId);
    } else if (mediaId && (0, _url.getProtocol)(mediaUrl) === 'file:') {
      (0, _reactNativeBridge.requestImageFailedRetryDialog)(mediaId);
    } else if (mediaType === _blockEditor.MEDIA_TYPE_IMAGE && isMediaSelected) {
      (0, _reactNativeBridge.requestImageFullscreenPreview)(mediaUrl);
    } else if (mediaType === _blockEditor.MEDIA_TYPE_IMAGE) {
      onMediaSelected();
    }
  }
  getIcon(iconType) {
    const {
      mediaType,
      getStylesFromColorScheme
    } = this.props;
    let iconStyle;
    switch (iconType) {
      case ICON_TYPE.RETRY:
        iconStyle = mediaType === _blockEditor.MEDIA_TYPE_IMAGE ? _style.default.iconRetry : getStylesFromColorScheme(_style.default.iconRetryVideo, _style.default.iconRetryVideoDark);
        return (0, _react.createElement)(_components.Icon, {
          icon: _iconRetry.default,
          ...iconStyle
        });
      case ICON_TYPE.PLACEHOLDER:
        iconStyle = getStylesFromColorScheme(_style.default.iconPlaceholder, _style.default.iconPlaceholderDark);
        break;
    }
    return (0, _react.createElement)(_components.Icon, {
      icon: _icons.media,
      ...iconStyle
    });
  }
  updateMediaProgress() {
    if (!this.state.isUploadInProgress) {
      this.setState({
        isUploadInProgress: true
      });
    }
  }
  finishMediaUploadWithSuccess(payload) {
    const {
      onMediaUpdate
    } = this.props;
    onMediaUpdate({
      id: payload.mediaServerId,
      url: payload.mediaUrl
    });
    this.setState({
      isUploadInProgress: false
    });
  }
  finishMediaUploadWithFailure() {
    this.setState({
      isUploadInProgress: false
    });
  }
  mediaUploadStateReset() {
    const {
      onMediaUpdate
    } = this.props;
    onMediaUpdate({
      id: null,
      url: null
    });
    this.setState({
      isUploadInProgress: false
    });
  }
  renderImage(params, openMediaOptions) {
    const {
      isUploadInProgress
    } = this.state;
    const {
      aligmentStyles,
      focalPoint,
      imageFill,
      isMediaSelected,
      isSelected,
      mediaAlt,
      mediaUrl,
      mediaWidth,
      shouldStack
    } = this.props;
    const {
      isUploadFailed,
      isUploadPaused,
      retryMessage
    } = params;
    const focalPointValues = !focalPoint ? _components.IMAGE_DEFAULT_FOCAL_POINT : focalPoint;
    return (0, _react.createElement)(_reactNative.View, {
      style: [imageFill && _style.default.imageWithFocalPoint, imageFill && shouldStack && {
        height: _style.default.imageFill.height
      }]
    }, (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
      accessible: !isSelected,
      onPress: this.onMediaPressed,
      disabled: !isSelected
    }, (0, _react.createElement)(_reactNative.View, {
      style: [imageFill && _style.default.imageCropped, _style.default.mediaImageContainer, !isUploadInProgress && aligmentStyles]
    }, (0, _react.createElement)(_components.Image, {
      align: "center",
      alt: mediaAlt,
      focalPoint: imageFill && focalPointValues,
      isSelected: isMediaSelected,
      isUploadFailed: isUploadFailed,
      isUploadPaused: isUploadPaused,
      isUploadInProgress: isUploadInProgress,
      onSelectMediaUploadOption: this.onSelectMediaUploadOption,
      openMediaOptions: openMediaOptions,
      retryMessage: retryMessage,
      url: mediaUrl,
      width: !isUploadInProgress && mediaWidth
    }))));
  }
  renderVideo(params) {
    const {
      aligmentStyles,
      mediaUrl,
      isSelected,
      getStylesFromColorScheme
    } = this.props;
    const {
      isUploadInProgress
    } = this.state;
    const {
      isUploadFailed,
      retryMessage
    } = params;
    const showVideo = (0, _url.isURL)(mediaUrl) && !isUploadInProgress && !isUploadFailed;
    const videoPlaceholderStyles = getStylesFromColorScheme(_style.default.videoPlaceholder, _style.default.videoPlaceholderDark);
    const retryVideoTextStyles = [_style.default.uploadFailedText, getStylesFromColorScheme(_style.default.uploadFailedTextVideo, _style.default.uploadFailedTextVideoDark)];
    return (0, _react.createElement)(_reactNative.View, {
      style: _style.default.mediaVideo
    }, (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
      accessible: !isSelected,
      onPress: this.onMediaPressed,
      disabled: !isSelected
    }, (0, _react.createElement)(_reactNative.View, {
      style: [_style.default.videoContainer, aligmentStyles]
    }, (0, _react.createElement)(_reactNative.View, {
      style: [_style.default.videoContent, {
        aspectRatio: _blockEditor.VIDEO_ASPECT_RATIO
      }]
    }, showVideo && (0, _react.createElement)(_reactNative.View, {
      style: _style.default.videoPlayer
    }, (0, _react.createElement)(_blockEditor.VideoPlayer, {
      isSelected: isSelected,
      style: _style.default.video,
      source: {
        uri: mediaUrl
      },
      paused: true
    })), !showVideo && (0, _react.createElement)(_reactNative.View, {
      style: videoPlaceholderStyles
    }, (0, _react.createElement)(_reactNative.View, {
      style: _style.default.modalIcon
    }, isUploadFailed ? this.getIcon(ICON_TYPE.RETRY) : this.getIcon(ICON_TYPE.PLACEHOLDER)), isUploadFailed && (0, _react.createElement)(_reactNative.Text, {
      style: retryVideoTextStyles
    }, retryMessage))))));
  }
  renderContent(params, openMediaOptions) {
    const {
      mediaType
    } = this.props;
    let mediaElement = null;
    switch (mediaType) {
      case _blockEditor.MEDIA_TYPE_IMAGE:
        mediaElement = this.renderImage(params, openMediaOptions);
        break;
      case _blockEditor.MEDIA_TYPE_VIDEO:
        mediaElement = this.renderVideo(params);
        break;
    }
    return mediaElement;
  }
  renderPlaceholder() {
    return (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
      icon: this.getIcon(ICON_TYPE.PLACEHOLDER),
      labels: {
        title: (0, _i18n.__)('Media area')
      },
      onSelect: this.onSelectMediaUploadOption,
      allowedTypes: ALLOWED_MEDIA_TYPES,
      onFocus: this.props.onFocus,
      className: 'no-block-outline'
    });
  }
  render() {
    const {
      mediaUrl,
      mediaId,
      mediaType,
      onSetOpenPickerRef
    } = this.props;
    const coverUrl = mediaType === _blockEditor.MEDIA_TYPE_IMAGE ? mediaUrl : null;
    if (mediaUrl) {
      return (0, _react.createElement)(_blockEditor.MediaUpload, {
        isReplacingMedia: true,
        onSelect: this.onSelectMediaUploadOption,
        allowedTypes: ALLOWED_MEDIA_TYPES,
        value: mediaId,
        render: ({
          open,
          getMediaOptions
        }) => {
          onSetOpenPickerRef(open);
          return (0, _react.createElement)(_react.Fragment, null, getMediaOptions(), (0, _react.createElement)(_blockEditor.MediaUploadProgress, {
            enablePausedUploads: true,
            coverUrl: coverUrl,
            mediaId: mediaId,
            onUpdateMediaProgress: this.updateMediaProgress,
            onFinishMediaUploadWithSuccess: this.finishMediaUploadWithSuccess,
            onFinishMediaUploadWithFailure: this.finishMediaUploadWithFailure,
            onMediaUploadStateReset: this.mediaUploadStateReset,
            renderContent: params => {
              return this.renderContent(params, open);
            }
          }));
        }
      });
    }
    return this.renderPlaceholder();
  }
}
var _default = exports.default = (0, _compose.compose)([_compose.withPreferredColorScheme])(MediaContainer);
//# sourceMappingURL=media-container.native.js.map