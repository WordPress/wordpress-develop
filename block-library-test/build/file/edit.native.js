"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.FileEdit = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _clipboard = _interopRequireDefault(require("@react-native-clipboard/clipboard"));
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _url = require("@wordpress/url");
var _coreData = require("@wordpress/core-data");
var _style = _interopRequireDefault(require("./style.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const URL_COPIED_NOTIFICATION_DURATION_MS = 1500;
const MIN_WIDTH = 40;
class FileEdit extends _element.Component {
  constructor(props) {
    super(props);
    this.state = {
      isUploadInProgress: false,
      isSidebarLinkSettings: false,
      placeholderTextWidth: 0,
      maxWidth: 0
    };
    this.timerRef = null;
    this.onLayout = this.onLayout.bind(this);
    this.onSelectFile = this.onSelectFile.bind(this);
    this.onChangeFileName = this.onChangeFileName.bind(this);
    this.onChangeDownloadButtonText = this.onChangeDownloadButtonText.bind(this);
    this.updateMediaProgress = this.updateMediaProgress.bind(this);
    this.finishMediaUploadWithSuccess = this.finishMediaUploadWithSuccess.bind(this);
    this.finishMediaUploadWithFailure = this.finishMediaUploadWithFailure.bind(this);
    this.getFileComponent = this.getFileComponent.bind(this);
    this.onChangeDownloadButtonVisibility = this.onChangeDownloadButtonVisibility.bind(this);
    this.onCopyURL = this.onCopyURL.bind(this);
    this.onChangeOpenInNewWindow = this.onChangeOpenInNewWindow.bind(this);
    this.onChangeLinkDestinationOption = this.onChangeLinkDestinationOption.bind(this);
    this.onShowLinkSettings = this.onShowLinkSettings.bind(this);
    this.onFilePressed = this.onFilePressed.bind(this);
    this.mediaUploadStateReset = this.mediaUploadStateReset.bind(this);
  }
  componentDidMount() {
    const {
      attributes,
      setAttributes
    } = this.props;
    const {
      downloadButtonText
    } = attributes;
    if (_blockEditor.RichText.isEmpty(downloadButtonText)) {
      setAttributes({
        downloadButtonText: (0, _i18n._x)('Download', 'button label')
      });
    }
    if (attributes.id && attributes.url && (0, _url.getProtocol)(attributes.url) === 'file:') {
      (0, _reactNativeBridge.mediaUploadSync)();
    }
  }
  componentWillUnmount() {
    clearTimeout(this.timerRef);
  }
  componentDidUpdate(prevProps) {
    if (prevProps.isSidebarOpened && !this.props.isSidebarOpened && this.state.isSidebarLinkSettings) {
      this.setState({
        isSidebarLinkSettings: false
      });
    }
  }
  onSelectFile(media) {
    this.props.setAttributes({
      href: media.url,
      fileName: media.title,
      textLinkHref: media.url,
      id: media.id
    });
  }
  onChangeFileName(fileName) {
    this.props.setAttributes({
      fileName
    });
  }
  onChangeDownloadButtonText(downloadButtonText) {
    this.props.setAttributes({
      downloadButtonText
    });
  }
  onChangeDownloadButtonVisibility(showDownloadButton) {
    this.props.setAttributes({
      showDownloadButton
    });
  }
  onChangeLinkDestinationOption(newHref) {
    // Choose Media File or Attachment Page (when file is in Media Library)
    this.props.setAttributes({
      textLinkHref: newHref
    });
  }
  onCopyURL() {
    if (this.state.isUrlCopied) {
      return;
    }
    const {
      href
    } = this.props.attributes;
    _clipboard.default.setString(href);
    this.setState({
      isUrlCopied: true
    });
    this.timerRef = setTimeout(() => {
      this.setState({
        isUrlCopied: false
      });
    }, URL_COPIED_NOTIFICATION_DURATION_MS);
  }
  onChangeOpenInNewWindow(newValue) {
    this.props.setAttributes({
      textLinkTarget: newValue ? '_blank' : false
    });
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
      href: payload.mediaUrl,
      id: payload.mediaServerId,
      textLinkHref: payload.mediaUrl
    });
    this.setState({
      isUploadInProgress: false
    });
  }
  finishMediaUploadWithFailure(payload) {
    this.props.setAttributes({
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
      href: null,
      textLinkHref: null,
      fileName: null
    });
    this.setState({
      isUploadInProgress: false
    });
  }
  onShowLinkSettings() {
    this.setState({
      isSidebarLinkSettings: true
    }, this.props.openSidebar);
  }
  getToolbarEditButton(open) {
    return (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
      title: (0, _i18n.__)('Edit file'),
      icon: _icons.replace,
      onClick: open
    }), (0, _react.createElement)(_components.ToolbarButton, {
      title: (0, _i18n.__)('Link To'),
      icon: _icons.link,
      onClick: this.onShowLinkSettings
    })));
  }
  getInspectorControls({
    showDownloadButton,
    textLinkTarget,
    href,
    textLinkHref
  }, media, isUploadInProgress, isUploadFailed) {
    let linkDestinationOptions = [{
      value: href,
      label: (0, _i18n.__)('URL')
    }];
    const attachmentPage = media && media.link;
    const {
      isSidebarLinkSettings
    } = this.state;
    if (attachmentPage) {
      linkDestinationOptions = [{
        value: href,
        label: (0, _i18n.__)('Media file')
      }, {
        value: attachmentPage,
        label: (0, _i18n.__)('Attachment page')
      }];
    }
    const actionButtonStyle = this.props.getStylesFromColorScheme(_style.default.actionButton, _style.default.actionButtonDark);
    const isCopyUrlDisabled = isUploadFailed || isUploadInProgress;
    const dimmedActionButtonStyle = this.props.getStylesFromColorScheme(_style.default.dimmedActionButton, _style.default.dimmedActionButtonDark);
    const finalButtonStyle = isCopyUrlDisabled ? dimmedActionButtonStyle : actionButtonStyle;
    return (0, _react.createElement)(_blockEditor.InspectorControls, null, isSidebarLinkSettings || (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('File block settings')
    }), (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.SelectControl, {
      icon: _icons.link,
      label: (0, _i18n.__)('Link to'),
      value: textLinkHref,
      onChange: this.onChangeLinkDestinationOption,
      options: linkDestinationOptions,
      hideCancelButton: true
    }), (0, _react.createElement)(_components.ToggleControl, {
      icon: _icons.external,
      label: (0, _i18n.__)('Open in new tab'),
      checked: textLinkTarget === '_blank',
      onChange: this.onChangeOpenInNewWindow
    }), !isSidebarLinkSettings && (0, _react.createElement)(_components.ToggleControl, {
      icon: _icons.button,
      label: (0, _i18n.__)('Show download button'),
      checked: showDownloadButton,
      onChange: this.onChangeDownloadButtonVisibility
    }), (0, _react.createElement)(_components.TextControl, {
      disabled: isCopyUrlDisabled,
      label: this.state.isUrlCopied ? (0, _i18n.__)('Copied!') : (0, _i18n.__)('Copy file URL'),
      labelStyle: this.state.isUrlCopied || finalButtonStyle,
      onPress: this.onCopyURL
    })));
  }
  getStyleForAlignment(align) {
    const getFlexAlign = alignment => {
      switch (alignment) {
        case 'right':
          return 'flex-end';
        case 'center':
          return 'center';
        default:
          return 'flex-start';
      }
    };
    return {
      alignSelf: getFlexAlign(align)
    };
  }
  getTextAlignmentForAlignment(align) {
    switch (align) {
      case 'right':
        return 'right';
      case 'center':
        return 'center';
      default:
        return 'left';
    }
  }
  onFilePressed() {
    const {
      attributes
    } = this.props;
    if (this.state.isUploadInProgress) {
      (0, _reactNativeBridge.requestImageUploadCancelDialog)(attributes.id);
    } else if (attributes.id && (0, _url.getProtocol)(attributes.href) === 'file:') {
      (0, _reactNativeBridge.requestImageFailedRetryDialog)(attributes.id);
    }
  }
  onLayout({
    nativeEvent
  }) {
    const {
      width
    } = nativeEvent.layout;
    const {
      paddingLeft,
      paddingRight
    } = _style.default.defaultButton;
    this.setState({
      maxWidth: width - (paddingLeft + paddingRight)
    });
  }

  // Render `Text` with `placeholderText` styled as a placeholder
  // to calculate its width which then is set as a `minWidth`
  // This should be fixed on RNAztec level. In the mean time,
  // We use the same strategy implemented in Button block.
  getPlaceholderWidth(placeholderText) {
    const {
      maxWidth,
      placeholderTextWidth
    } = this.state;
    return (0, _react.createElement)(_reactNative.Text, {
      style: _style.default.placeholder,
      onTextLayout: ({
        nativeEvent
      }) => {
        const textWidth = nativeEvent.lines[0] && nativeEvent.lines[0].width;
        if (textWidth && textWidth !== placeholderTextWidth) {
          this.setState({
            placeholderTextWidth: Math.min(textWidth, maxWidth)
          });
        }
      }
    }, placeholderText);
  }
  getFileComponent(openMediaOptions, getMediaOptions) {
    const {
      attributes,
      media,
      isSelected
    } = this.props;
    const {
      isButtonFocused,
      placeholderTextWidth
    } = this.state;
    const {
      fileName,
      downloadButtonText,
      id,
      showDownloadButton,
      align
    } = attributes;
    const minWidth = isButtonFocused || !isButtonFocused && downloadButtonText && downloadButtonText !== '' ? MIN_WIDTH : placeholderTextWidth;
    const placeholderText = isButtonFocused || !isButtonFocused && downloadButtonText && downloadButtonText !== '' ? '' : (0, _i18n.__)('Add text…');
    return (0, _react.createElement)(_blockEditor.MediaUploadProgress, {
      mediaId: id,
      onUpdateMediaProgress: this.updateMediaProgress,
      onFinishMediaUploadWithSuccess: this.finishMediaUploadWithSuccess,
      onFinishMediaUploadWithFailure: this.finishMediaUploadWithFailure,
      onMediaUploadStateReset: this.mediaUploadStateReset,
      renderContent: ({
        isUploadInProgress,
        isUploadFailed
      }) => {
        const dimmedStyle = (this.state.isUploadInProgress || isUploadFailed) && _style.default.disabledButton;
        const finalButtonStyle = [_style.default.defaultButton, dimmedStyle];
        const errorIconStyle = Object.assign({}, _style.default.errorIcon, _style.default.uploadFailed);
        return (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
          accessible: !isSelected,
          onPress: this.onFilePressed,
          disabled: !isSelected
        }, (0, _react.createElement)(_reactNative.View, {
          onLayout: this.onLayout,
          testID: "file-edit-container"
        }, this.getPlaceholderWidth(placeholderText), isUploadInProgress || this.getToolbarEditButton(openMediaOptions), getMediaOptions(), isSelected && this.getInspectorControls(attributes, media, isUploadInProgress, isUploadFailed), (0, _react.createElement)(_reactNative.View, {
          style: _style.default.container
        }, (0, _react.createElement)(_blockEditor.RichText, {
          withoutInteractiveFormatting: true,
          __unstableMobileNoFocusOnMount: true,
          onChange: this.onChangeFileName,
          placeholder: (0, _i18n.__)('File name'),
          tagName: "p",
          underlineColorAndroid: "transparent",
          value: fileName,
          deleteEnter: true,
          textAlign: this.getTextAlignmentForAlignment(align)
        }), isUploadFailed && (0, _react.createElement)(_reactNative.View, {
          style: _style.default.errorContainer
        }, (0, _react.createElement)(_components.Icon, {
          icon: _icons.warning,
          style: errorIconStyle
        }), (0, _react.createElement)(_blockEditor.PlainText, {
          editable: false,
          value: (0, _i18n.__)('Error'),
          style: _style.default.uploadFailed
        }))), showDownloadButton && this.state.maxWidth > 0 && (0, _react.createElement)(_reactNative.View, {
          style: [finalButtonStyle, this.getStyleForAlignment(align)]
        }, (0, _react.createElement)(_blockEditor.RichText, {
          withoutInteractiveFormatting: true,
          __unstableMobileNoFocusOnMount: true,
          tagName: "p",
          textAlign: "center",
          minWidth: minWidth,
          maxWidth: this.state.maxWidth,
          deleteEnter: true,
          style: _style.default.buttonText,
          value: downloadButtonText,
          placeholder: placeholderText,
          unstableOnFocus: () => this.setState({
            isButtonFocused: true
          }),
          onBlur: () => this.setState({
            isButtonFocused: false
          }),
          selectionColor: _style.default.buttonText.color,
          placeholderTextColor: _style.default.placeholderTextColor.color,
          underlineColorAndroid: "transparent",
          onChange: this.onChangeDownloadButtonText
        }))));
      }
    });
  }
  render() {
    const {
      attributes,
      wasBlockJustInserted,
      isSelected
    } = this.props;
    const {
      href
    } = attributes;
    if (!href) {
      return (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
        icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
          icon: _icons.file
        }),
        labels: {
          title: (0, _i18n.__)('File'),
          instructions: (0, _i18n.__)('Choose a file')
        },
        onSelect: this.onSelectFile,
        onFocus: this.props.onFocus,
        allowedTypes: [_blockEditor.MEDIA_TYPE_ANY],
        autoOpenMediaUpload: isSelected && wasBlockJustInserted
      });
    }
    return (0, _react.createElement)(_blockEditor.MediaUpload, {
      allowedTypes: [_blockEditor.MEDIA_TYPE_ANY],
      isReplacingMedia: true,
      onSelect: this.onSelectFile,
      render: ({
        open,
        getMediaOptions
      }) => {
        return this.getFileComponent(open, getMediaOptions);
      }
    });
  }
}
exports.FileEdit = FileEdit;
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, props) => {
  const {
    attributes,
    isSelected,
    clientId
  } = props;
  const {
    id,
    href
  } = attributes;
  const {
    isEditorSidebarOpened
  } = select('core/edit-post');
  const isNotFileHref = id && (0, _url.getProtocol)(href) !== 'file:';
  return {
    media: isNotFileHref ? select(_coreData.store).getMedia(id) : undefined,
    isSidebarOpened: isSelected && isEditorSidebarOpened(),
    wasBlockJustInserted: select(_blockEditor.store).wasBlockJustInserted(clientId, 'inserter_menu')
  };
}), (0, _data.withDispatch)(dispatch => {
  const {
    openGeneralSidebar
  } = dispatch('core/edit-post');
  return {
    openSidebar: () => openGeneralSidebar('edit-post/block')
  };
}), _compose.withPreferredColorScheme])(FileEdit);
//# sourceMappingURL=edit.native.js.map