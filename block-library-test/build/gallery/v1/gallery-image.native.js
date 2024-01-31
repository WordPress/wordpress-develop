"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _url = require("@wordpress/url");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
var _galleryButton = _interopRequireDefault(require("./gallery-button"));
var _galleryImageStyle = _interopRequireDefault(require("./gallery-image-style.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const {
  compose
} = _reactNative.StyleSheet;
const separatorStyle = compose(_galleryImageStyle.default.separator, {
  borderRightWidth: _reactNative.StyleSheet.hairlineWidth
});
const buttonStyle = compose(_galleryImageStyle.default.button, {
  aspectRatio: 1
});
const ICON_SIZE_ARROW = 15;
class GalleryImage extends _element.Component {
  constructor() {
    super(...arguments);
    this.onSelectImage = this.onSelectImage.bind(this);
    this.onSelectCaption = this.onSelectCaption.bind(this);
    this.onMediaPressed = this.onMediaPressed.bind(this);
    this.onCaptionChange = this.onCaptionChange.bind(this);
    this.onSelectMedia = this.onSelectMedia.bind(this);
    this.updateMediaProgress = this.updateMediaProgress.bind(this);
    this.finishMediaUploadWithSuccess = this.finishMediaUploadWithSuccess.bind(this);
    this.finishMediaUploadWithFailure = this.finishMediaUploadWithFailure.bind(this);
    this.renderContent = this.renderContent.bind(this);
    this.state = {
      captionSelected: false,
      isUploadInProgress: false,
      didUploadFail: false
    };
  }
  onSelectCaption() {
    if (!this.state.captionSelected) {
      this.setState({
        captionSelected: true
      });
    }
    if (!this.props.isSelected) {
      this.props.onSelect();
    }
  }
  onMediaPressed() {
    const {
      id,
      url,
      isSelected
    } = this.props;
    const {
      captionSelected,
      isUploadInProgress,
      didUploadFail
    } = this.state;
    this.onSelectImage();
    if (isUploadInProgress) {
      (0, _reactNativeBridge.requestImageUploadCancelDialog)(id);
    } else if (didUploadFail || id && (0, _url.getProtocol)(url) === 'file:') {
      (0, _reactNativeBridge.requestImageFailedRetryDialog)(id);
    } else if (isSelected && !captionSelected) {
      (0, _reactNativeBridge.requestImageFullscreenPreview)(url);
    }
  }
  onSelectImage() {
    if (!this.props.isBlockSelected) {
      this.props.onSelectBlock();
    }
    if (!this.props.isSelected) {
      this.props.onSelect();
    }
    if (this.state.captionSelected) {
      this.setState({
        captionSelected: false
      });
    }
  }
  onSelectMedia(media) {
    const {
      setAttributes
    } = this.props;
    setAttributes(media);
  }
  onCaptionChange(caption) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      caption
    });
  }
  componentDidUpdate(prevProps) {
    const {
      isSelected,
      image,
      url
    } = this.props;
    if (image && !url) {
      this.props.setAttributes({
        url: image.source_url,
        alt: image.alt_text
      });
    }

    // Unselect the caption so when the user selects other image and comeback
    // the caption is not immediately selected.
    if (this.state.captionSelected && !isSelected && prevProps.isSelected) {
      this.setState({
        captionSelected: false
      });
    }
  }
  updateMediaProgress() {
    if (!this.state.isUploadInProgress) {
      this.setState({
        isUploadInProgress: true
      });
    }
  }
  finishMediaUploadWithSuccess(payload) {
    this.setState({
      isUploadInProgress: false,
      didUploadFail: false
    });
    this.props.setAttributes({
      id: payload.mediaServerId,
      url: payload.mediaUrl
    });
  }
  finishMediaUploadWithFailure() {
    this.setState({
      isUploadInProgress: false,
      didUploadFail: true
    });
  }
  renderContent(params) {
    const {
      url,
      isFirstItem,
      isLastItem,
      isSelected,
      caption,
      onRemove,
      onMoveForward,
      onMoveBackward,
      'aria-label': ariaLabel,
      isCropped,
      getStylesFromColorScheme,
      isRTL
    } = this.props;
    const {
      isUploadInProgress,
      captionSelected
    } = this.state;
    const {
      isUploadFailed,
      retryMessage
    } = params;
    const resizeMode = isCropped ? 'cover' : 'contain';
    const captionPlaceholderStyle = getStylesFromColorScheme(_galleryImageStyle.default.captionPlaceholder, _galleryImageStyle.default.captionPlaceholderDark);
    const shouldShowCaptionEditable = !isUploadFailed && isSelected;
    const shouldShowCaptionExpanded = !isUploadFailed && !isSelected && !!caption;
    const captionContainerStyle = shouldShowCaptionExpanded ? _galleryImageStyle.default.captionExpandedContainer : _galleryImageStyle.default.captionContainer;
    const captionStyle = shouldShowCaptionExpanded ? _galleryImageStyle.default.captionExpanded : _galleryImageStyle.default.caption;
    const mediaPickerOptions = [{
      destructiveButton: true,
      id: 'removeImage',
      label: (0, _i18n.__)('Remove'),
      onPress: onRemove,
      separated: true,
      value: 'removeImage'
    }];
    return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.Image, {
      alt: ariaLabel,
      height: _galleryImageStyle.default.image.height,
      isSelected: isSelected,
      isUploadFailed: isUploadFailed,
      isUploadInProgress: isUploadInProgress,
      mediaPickerOptions: mediaPickerOptions,
      onSelectMediaUploadOption: this.onSelectMedia,
      resizeMode: resizeMode,
      url: url,
      retryMessage: retryMessage,
      retryIcon: _icons.warning
    }), !isUploadInProgress && isSelected && (0, _react.createElement)(_reactNative.View, {
      style: _galleryImageStyle.default.toolbarContainer
    }, (0, _react.createElement)(_reactNative.View, {
      style: _galleryImageStyle.default.toolbar
    }, (0, _react.createElement)(_reactNative.View, {
      style: _galleryImageStyle.default.moverButtonContainer
    }, (0, _react.createElement)(_galleryButton.default, {
      style: buttonStyle,
      icon: isRTL ? _icons.arrowRight : _icons.arrowLeft,
      iconSize: ICON_SIZE_ARROW,
      onClick: isFirstItem ? undefined : onMoveBackward,
      accessibilityLabel: (0, _i18n.__)('Move Image Backward'),
      "aria-disabled": isFirstItem,
      disabled: !isSelected
    }), (0, _react.createElement)(_reactNative.View, {
      style: separatorStyle
    }), (0, _react.createElement)(_galleryButton.default, {
      style: buttonStyle,
      icon: isRTL ? _icons.arrowLeft : _icons.arrowRight,
      iconSize: ICON_SIZE_ARROW,
      onClick: isLastItem ? undefined : onMoveForward,
      accessibilityLabel: (0, _i18n.__)('Move Image Forward'),
      "aria-disabled": isLastItem,
      disabled: !isSelected
    })))), !isUploadInProgress && (shouldShowCaptionEditable || shouldShowCaptionExpanded) && (0, _react.createElement)(_reactNative.View, {
      style: captionContainerStyle
    }, (0, _react.createElement)(_reactNative.ScrollView, {
      nestedScrollEnabled: true,
      keyboardShouldPersistTaps: "handled",
      bounces: false
    }, (0, _react.createElement)(_blockEditor.Caption, {
      inlineToolbar: true,
      isSelected: isSelected && captionSelected,
      onChange: this.onCaptionChange,
      onFocus: this.onSelectCaption,
      placeholder: isSelected ? (0, _i18n.__)('Add caption') : null,
      placeholderTextColor: captionPlaceholderStyle.color,
      style: captionStyle,
      value: caption
    }))));
  }
  render() {
    const {
      id,
      onRemove,
      getStylesFromColorScheme,
      isSelected
    } = this.props;
    const containerStyle = getStylesFromColorScheme(_galleryImageStyle.default.galleryImageContainer, _galleryImageStyle.default.galleryImageContainerDark);
    return (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
      onPress: this.onMediaPressed,
      accessible: !isSelected // We need only child views to be accessible after the selection.
      ,
      accessibilityLabel: this.accessibilityLabelImageContainer() // if we don't set this explicitly it reads system provided accessibilityLabels of all child components and those include pretty technical words which don't make sense
      ,
      accessibilityRole: 'imagebutton' // this makes VoiceOver to read a description of image provided by system on iOS and lets user know this is a button which conveys the message of tappablity
    }, (0, _react.createElement)(_reactNative.View, {
      style: containerStyle
    }, (0, _react.createElement)(_blockEditor.MediaUploadProgress, {
      mediaId: id,
      onUpdateMediaProgress: this.updateMediaProgress,
      onFinishMediaUploadWithSuccess: this.finishMediaUploadWithSuccess,
      onFinishMediaUploadWithFailure: this.finishMediaUploadWithFailure,
      onMediaUploadStateReset: onRemove,
      renderContent: this.renderContent
    })));
  }
  accessibilityLabelImageContainer() {
    const {
      caption,
      'aria-label': ariaLabel
    } = this.props;
    return !caption ? ariaLabel : ariaLabel + '. ' + (0, _i18n.sprintf)( /* translators: accessibility text. %s: image caption. */
    (0, _i18n.__)('Image caption. %s'), caption);
  }
}
var _default = exports.default = (0, _compose.withPreferredColorScheme)(GalleryImage);
//# sourceMappingURL=gallery-image.native.js.map