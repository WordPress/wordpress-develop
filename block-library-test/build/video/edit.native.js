"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _element = require("@wordpress/element");
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _url = require("@wordpress/url");
var _hooks = require("@wordpress/hooks");
var _icons = require("@wordpress/icons");
var _data = require("@wordpress/data");
var _notices = require("@wordpress/notices");
var _util = require("../embed/util");
var _style = _interopRequireDefault(require("./style.scss"));
var _iconRetry = _interopRequireDefault(require("./icon-retry"));
var _editCommonSettings = _interopRequireDefault(require("./edit-common-settings"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const ICON_TYPE = {
  PLACEHOLDER: 'placeholder',
  RETRY: 'retry',
  UPLOAD: 'upload'
};
class VideoEdit extends _element.Component {
  constructor(props) {
    super(props);
    this.state = {
      isCaptionSelected: false,
      videoContainerHeight: 0
    };
    this.mediaUploadStateReset = this.mediaUploadStateReset.bind(this);
    this.onSelectMediaUploadOption = this.onSelectMediaUploadOption.bind(this);
    this.onSelectURL = this.onSelectURL.bind(this);
    this.finishMediaUploadWithSuccess = this.finishMediaUploadWithSuccess.bind(this);
    this.finishMediaUploadWithFailure = this.finishMediaUploadWithFailure.bind(this);
    this.updateMediaProgress = this.updateMediaProgress.bind(this);
    this.onVideoPressed = this.onVideoPressed.bind(this);
    this.onVideoContanerLayout = this.onVideoContanerLayout.bind(this);
    this.onFocusCaption = this.onFocusCaption.bind(this);
  }
  componentDidMount() {
    const {
      attributes
    } = this.props;
    if (attributes.id && (0, _url.getProtocol)(attributes.src) === 'file:') {
      (0, _reactNativeBridge.mediaUploadSync)();
    }
  }
  componentWillUnmount() {
    // This action will only exist if the user pressed the trash button on the block holder.
    if ((0, _hooks.hasAction)('blocks.onRemoveBlockCheckUpload') && this.state.isUploadInProgress) {
      (0, _hooks.doAction)('blocks.onRemoveBlockCheckUpload', this.props.attributes.id);
    }
  }
  static getDerivedStateFromProps(props, state) {
    // Avoid a UI flicker in the toolbar by insuring that isCaptionSelected
    // is updated immediately any time the isSelected prop becomes false.
    return {
      isCaptionSelected: props.isSelected && state.isCaptionSelected
    };
  }
  onVideoPressed() {
    const {
      attributes
    } = this.props;
    if (this.state.isUploadInProgress) {
      (0, _reactNativeBridge.requestImageUploadCancelDialog)(attributes.id);
    } else if (attributes.id && (0, _url.getProtocol)(attributes.src) === 'file:') {
      (0, _reactNativeBridge.requestImageFailedRetryDialog)(attributes.id);
    }
    this.setState({
      isCaptionSelected: false
    });
  }
  onFocusCaption() {
    if (!this.state.isCaptionSelected) {
      this.setState({
        isCaptionSelected: true
      });
    }
  }
  updateMediaProgress(payload) {
    const {
      setAttributes
    } = this.props;
    if (payload.mediaUrl) {
      setAttributes({
        url: payload.mediaUrl
      });
    }
    if (!this.state.isUploadInProgress) {
      this.setState({
        isUploadInProgress: true
      });
    }
  }
  finishMediaUploadWithSuccess(payload) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      src: payload.mediaUrl,
      id: payload.mediaServerId
    });
    this.setState({
      isUploadInProgress: false
    });
  }
  finishMediaUploadWithFailure(payload) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      id: payload.mediaId
    });
    this.setState({
      isUploadInProgress: false
    });
  }
  mediaUploadStateReset() {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      id: null,
      src: null
    });
    this.setState({
      isUploadInProgress: false
    });
  }
  onSelectMediaUploadOption({
    id,
    url
  }) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      id,
      src: url
    });
  }
  onSelectURL(url) {
    const {
      createErrorNotice,
      onReplace,
      setAttributes
    } = this.props;
    if ((0, _url.isURL)(url) && /^https?:/.test((0, _url.getProtocol)(url))) {
      // Check if there's an embed block that handles this URL.
      const embedBlock = (0, _util.createUpgradedEmbedBlock)({
        attributes: {
          url
        }
      });
      if (undefined !== embedBlock) {
        onReplace(embedBlock);
        return;
      }
      setAttributes({
        src: url,
        id: undefined,
        poster: undefined
      });
    } else {
      createErrorNotice((0, _i18n.__)('Invalid URL.'));
    }
  }
  onVideoContanerLayout(event) {
    const {
      width
    } = event.nativeEvent.layout;
    const height = width / _blockEditor.VIDEO_ASPECT_RATIO;
    if (height !== this.state.videoContainerHeight) {
      this.setState({
        videoContainerHeight: height
      });
    }
  }
  getIcon(iconType) {
    let iconStyle;
    switch (iconType) {
      case ICON_TYPE.RETRY:
        return (0, _react.createElement)(_components.Icon, {
          icon: _iconRetry.default,
          ..._style.default.icon
        });
      case ICON_TYPE.PLACEHOLDER:
        iconStyle = this.props.getStylesFromColorScheme(_style.default.icon, _style.default.iconDark);
        break;
      case ICON_TYPE.UPLOAD:
        iconStyle = this.props.getStylesFromColorScheme(_style.default.iconUploading, _style.default.iconUploadingDark);
        break;
    }
    return (0, _react.createElement)(_components.Icon, {
      icon: _icons.video,
      ...iconStyle
    });
  }
  render() {
    const {
      setAttributes,
      attributes,
      isSelected,
      wasBlockJustInserted
    } = this.props;
    const {
      id,
      src,
      guid
    } = attributes;
    const {
      videoContainerHeight
    } = this.state;
    const toolbarEditButton = (0, _react.createElement)(_blockEditor.MediaUpload, {
      allowedTypes: [_blockEditor.MEDIA_TYPE_VIDEO],
      isReplacingMedia: true,
      onSelect: this.onSelectMediaUploadOption,
      onSelectURL: this.onSelectURL,
      render: ({
        open,
        getMediaOptions
      }) => {
        return (0, _react.createElement)(_components.ToolbarGroup, null, getMediaOptions(), (0, _react.createElement)(_components.ToolbarButton, {
          label: (0, _i18n.__)('Edit video'),
          icon: _icons.replace,
          onClick: open
        }));
      }
    });

    // NOTE: `guid` is not part of the block's attribute definition. This case
    // handled here is a temporary fix until a we find a better approach.
    const isSourcePresent = src || guid && id;
    if (!isSourcePresent) {
      return (0, _react.createElement)(_reactNative.View, {
        style: {
          flex: 1
        }
      }, (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
        allowedTypes: [_blockEditor.MEDIA_TYPE_VIDEO],
        onSelect: this.onSelectMediaUploadOption,
        onSelectURL: this.onSelectURL,
        icon: this.getIcon(ICON_TYPE.PLACEHOLDER),
        onFocus: this.props.onFocus,
        autoOpenMediaUpload: isSelected && wasBlockJustInserted
      }));
    }
    return (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
      accessible: !isSelected,
      onPress: this.onVideoPressed,
      disabled: !isSelected
    }, (0, _react.createElement)(_reactNative.View, {
      style: {
        flex: 1
      }
    }, !this.state.isCaptionSelected && (0, _react.createElement)(_blockEditor.BlockControls, null, toolbarEditButton), isSelected && (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Settings')
    }, (0, _react.createElement)(_editCommonSettings.default, {
      setAttributes: setAttributes,
      attributes: attributes
    }))), (0, _react.createElement)(_blockEditor.MediaUploadProgress, {
      mediaId: id,
      onFinishMediaUploadWithSuccess: this.finishMediaUploadWithSuccess,
      onFinishMediaUploadWithFailure: this.finishMediaUploadWithFailure,
      onUpdateMediaProgress: this.updateMediaProgress,
      onMediaUploadStateReset: this.mediaUploadStateReset,
      renderContent: ({
        isUploadInProgress,
        isUploadFailed,
        retryMessage
      }) => {
        const showVideo = (0, _url.isURL)(src) && !isUploadInProgress && !isUploadFailed;
        const icon = this.getIcon(isUploadFailed ? ICON_TYPE.RETRY : ICON_TYPE.UPLOAD);
        const styleIconContainer = isUploadFailed ? _style.default.modalIconRetry : _style.default.modalIcon;
        const iconContainer = (0, _react.createElement)(_reactNative.View, {
          style: styleIconContainer
        }, icon);
        const videoStyle = {
          height: videoContainerHeight,
          ..._style.default.video
        };
        const containerStyle = showVideo && isSelected ? _style.default.containerFocused : _style.default.container;
        return (0, _react.createElement)(_reactNative.View, {
          onLayout: this.onVideoContanerLayout,
          style: containerStyle
        }, showVideo && (0, _react.createElement)(_reactNative.View, {
          style: _style.default.videoContainer
        }, (0, _react.createElement)(_blockEditor.VideoPlayer, {
          isSelected: isSelected && !this.state.isCaptionSelected,
          style: videoStyle,
          source: {
            uri: src
          },
          paused: true
        })), !showVideo && (0, _react.createElement)(_reactNative.View, {
          style: {
            height: videoContainerHeight,
            width: '100%',
            ...this.props.getStylesFromColorScheme(_style.default.placeholderContainer, _style.default.placeholderContainerDark)
          }
        }, videoContainerHeight > 0 && iconContainer, isUploadFailed && (0, _react.createElement)(_reactNative.Text, {
          style: _style.default.uploadFailedText
        }, retryMessage)));
      }
    }), (0, _react.createElement)(_blockEditor.BlockCaption, {
      accessible: true,
      accessibilityLabelCreator: caption => _blockEditor.RichText.isEmpty(caption) ? /* translators: accessibility text. Empty video caption. */
      (0, _i18n.__)('Video caption. Empty') : (0, _i18n.sprintf)( /* translators: accessibility text. %s: video caption. */
      (0, _i18n.__)('Video caption. %s'), caption),
      clientId: this.props.clientId,
      isSelected: this.state.isCaptionSelected,
      onFocus: this.onFocusCaption,
      onBlur: this.props.onBlur // Always assign onBlur as props.
      ,
      insertBlocksAfter: this.props.insertBlocksAfter
    })));
  }
}
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, {
  clientId
}) => ({
  wasBlockJustInserted: select(_blockEditor.store).wasBlockJustInserted(clientId, 'inserter_menu')
})), (0, _data.withDispatch)(dispatch => {
  const {
    createErrorNotice
  } = dispatch(_notices.store);
  return {
    createErrorNotice
  };
}), _compose.withPreferredColorScheme])(VideoEdit);
//# sourceMappingURL=edit.native.js.map