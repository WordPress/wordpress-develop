"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.ImageEdit = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _native = require("@react-navigation/native");
var _element = require("@wordpress/element");
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _url = require("@wordpress/url");
var _hooks = require("@wordpress/hooks");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
var _notices = require("@wordpress/notices");
var _editPost = require("@wordpress/edit-post");
var _styles = _interopRequireDefault(require("./styles.scss"));
var _utils = require("./utils");
var _constants = require("./constants");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

// eslint-disable-next-line no-restricted-imports

/**
 * Internal dependencies
 */

const getUrlForSlug = (image, sizeSlug) => {
  if (!sizeSlug) {
    return undefined;
  }
  return image?.media_details?.sizes?.[sizeSlug]?.source_url;
};
function LinkSettings({
  attributes,
  image,
  isLinkSheetVisible,
  setMappedAttributes
}) {
  const route = (0, _native.useRoute)();
  const {
    href: url,
    label,
    linkDestination,
    linkTarget,
    rel
  } = attributes;

  // Persist attributes passed from child screen.
  (0, _element.useEffect)(() => {
    const {
      inputValue: newUrl
    } = route.params || {};
    let newLinkDestination;
    switch (newUrl) {
      case attributes.url:
        newLinkDestination = _constants.LINK_DESTINATION_MEDIA;
        break;
      case image?.link:
        newLinkDestination = _constants.LINK_DESTINATION_ATTACHMENT;
        break;
      case '':
        newLinkDestination = _constants.LINK_DESTINATION_NONE;
        break;
      default:
        newLinkDestination = _constants.LINK_DESTINATION_CUSTOM;
        break;
    }
    setMappedAttributes({
      url: newUrl,
      linkDestination: newLinkDestination
    });
  }, [route.params?.inputValue]);
  let valueMask;
  switch (linkDestination) {
    case _constants.LINK_DESTINATION_MEDIA:
      valueMask = (0, _i18n.__)('Media File');
      break;
    case _constants.LINK_DESTINATION_ATTACHMENT:
      valueMask = (0, _i18n.__)('Attachment Page');
      break;
    case _constants.LINK_DESTINATION_CUSTOM:
      valueMask = (0, _i18n.__)('Custom URL');
      break;
    default:
      valueMask = (0, _i18n.__)('None');
      break;
  }
  const linkSettingsOptions = {
    url: {
      valueMask,
      autoFocus: false,
      autoFill: false
    },
    openInNewTab: {
      label: (0, _i18n.__)('Open in new tab')
    },
    linkRel: {
      label: (0, _i18n.__)('Link Rel'),
      placeholder: (0, _i18n._x)('None', 'Link rel attribute value placeholder')
    }
  };
  return (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Link Settings')
  }, (0, _react.createElement)(_components.LinkSettingsNavigation, {
    isVisible: isLinkSheetVisible,
    url: url,
    rel: rel,
    label: label,
    linkTarget: linkTarget,
    setAttributes: setMappedAttributes,
    withBottomSheet: false,
    hasPicker: true,
    options: linkSettingsOptions,
    showIcon: false,
    onLinkCellPressed: ({
      navigation
    }) => {
      navigation.navigate(_blockEditor.blockSettingsScreens.imageLinkDestinations, {
        inputValue: attributes.href,
        linkDestination: attributes.linkDestination,
        imageUrl: attributes.url,
        attachmentPageUrl: image?.link
      });
    }
  }));
}
const UPLOAD_STATE_IDLE = 0;
const UPLOAD_STATE_UPLOADING = 1;
const UPLOAD_STATE_SUCCEEDED = 2;
const UPLOAD_STATE_FAILED = 3;
class ImageEdit extends _element.Component {
  constructor(props) {
    super(props);
    this.state = {
      isCaptionSelected: false,
      uploadStatus: UPLOAD_STATE_IDLE
    };
    this.replacedFeaturedImage = false;
    this.finishMediaUploadWithSuccess = this.finishMediaUploadWithSuccess.bind(this);
    this.finishMediaUploadWithFailure = this.finishMediaUploadWithFailure.bind(this);
    this.mediaUploadStateReset = this.mediaUploadStateReset.bind(this);
    this.onSelectMediaUploadOption = this.onSelectMediaUploadOption.bind(this);
    this.updateMediaProgress = this.updateMediaProgress.bind(this);
    this.updateImageURL = this.updateImageURL.bind(this);
    this.onSetNewTab = this.onSetNewTab.bind(this);
    this.onSetSizeSlug = this.onSetSizeSlug.bind(this);
    this.onImagePressed = this.onImagePressed.bind(this);
    this.onSetFeatured = this.onSetFeatured.bind(this);
    this.onFocusCaption = this.onFocusCaption.bind(this);
    this.onSelectURL = this.onSelectURL.bind(this);
    this.accessibilityLabelCreator = this.accessibilityLabelCreator.bind(this);
    this.setMappedAttributes = this.setMappedAttributes.bind(this);
    this.onSizeChangeValue = this.onSizeChangeValue.bind(this);
  }
  componentDidMount() {
    const {
      attributes,
      setAttributes
    } = this.props;
    // This will warn when we have `id` defined, while `url` is undefined.
    // This may help track this issue: https://github.com/wordpress-mobile/WordPress-Android/issues/9768
    // where a cancelled image upload was resulting in a subsequent crash.
    if (attributes.id && !attributes.url) {
      // eslint-disable-next-line no-console
      console.warn('Attributes has id with no url.');
    }

    // Detect any pasted image and start an upload.
    if (!attributes.id && attributes.url && (0, _url.getProtocol)(attributes.url) === 'file:') {
      (0, _reactNativeBridge.requestMediaImport)(attributes.url, (id, url) => {
        if (url) {
          setAttributes({
            id,
            url
          });
        }
      });
    }

    // Make sure we mark any temporary images as failed if they failed while
    // the editor wasn't open.
    if (attributes.id && attributes.url && (0, _url.getProtocol)(attributes.url) === 'file:') {
      (0, _reactNativeBridge.mediaUploadSync)();
    }
  }
  componentWillUnmount() {
    // This action will only exist if the user pressed the trash button on the block holder.
    if ((0, _hooks.hasAction)('blocks.onRemoveBlockCheckUpload') && this.state.uploadStatus === UPLOAD_STATE_UPLOADING) {
      (0, _hooks.doAction)('blocks.onRemoveBlockCheckUpload', this.props.attributes.id);
    }
  }
  componentDidUpdate(previousProps) {
    const {
      image,
      attributes,
      setAttributes,
      featuredImageId
    } = this.props;
    const {
      url
    } = attributes;
    if (!previousProps.image && image) {
      if (!(0, _url.hasQueryArg)(url, 'w') && attributes?.sizeSlug) {
        const updatedUrl = getUrlForSlug(image, attributes.sizeSlug) || image.source_url;
        setAttributes({
          url: updatedUrl
        });
      }
    }
    const {
      id
    } = attributes;
    const {
      id: previousId
    } = previousProps.attributes;

    // The media changed and the previous media was set as the Featured Image,
    // we must keep track of the previous media's featured status to act on it
    // once the new media has a finalized ID.
    if (!!id && id !== previousId && !!featuredImageId && featuredImageId === previousId) {
      this.replacedFeaturedImage = true;
    }

    // The media changed and now has a finalized ID (e.g. upload completed), we
    // should attempt to replace the featured image if applicable.
    if (this.replacedFeaturedImage && !!image && this.canImageBeFeatured()) {
      this.replacedFeaturedImage = false;
      (0, _reactNativeBridge.setFeaturedImage)(id);
    }
    const {
      align
    } = attributes;
    const {
      __unstableMarkNextChangeAsNotPersistent
    } = this.props;

    // Update the attributes if the align is wide or full
    if (['wide', 'full'].includes(align)) {
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        width: undefined,
        height: undefined,
        aspectRatio: undefined,
        scale: undefined
      });
    }
  }
  static getDerivedStateFromProps(props, state) {
    // Avoid a UI flicker in the toolbar by insuring that isCaptionSelected
    // is updated immediately any time the isSelected prop becomes false.
    return {
      isCaptionSelected: props.isSelected && state.isCaptionSelected
    };
  }
  accessibilityLabelCreator(caption) {
    // Checks if caption is empty.
    return _blockEditor.RichText.isEmpty(caption) ? /* translators: accessibility text. Empty image caption. */
    'Image caption. Empty' : (0, _i18n.sprintf)( /* translators: accessibility text. %s: image caption. */
    (0, _i18n.__)('Image caption. %s'), caption);
  }
  onImagePressed() {
    const {
      attributes,
      image
    } = this.props;
    if (this.state.uploadStatus === UPLOAD_STATE_UPLOADING) {
      (0, _reactNativeBridge.requestImageUploadCancelDialog)(attributes.id);
    } else if (attributes.id && (0, _url.getProtocol)(attributes.url) === 'file:') {
      (0, _reactNativeBridge.requestImageFailedRetryDialog)(attributes.id);
    } else if (!this.state.isCaptionSelected) {
      (0, _reactNativeBridge.requestImageFullscreenPreview)(attributes.url, image && image.source_url);
    }
    this.setState({
      isCaptionSelected: false
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
    if (this.state.uploadStatus !== UPLOAD_STATE_UPLOADING) {
      this.setState({
        uploadStatus: UPLOAD_STATE_UPLOADING
      });
    }
  }
  finishMediaUploadWithSuccess(payload) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      url: payload.mediaUrl,
      id: payload.mediaServerId
    });
    this.setState({
      uploadStatus: UPLOAD_STATE_SUCCEEDED
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
      uploadStatus: UPLOAD_STATE_FAILED
    });
  }
  mediaUploadStateReset() {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      id: null,
      url: null
    });
    this.setState({
      uploadStatus: UPLOAD_STATE_IDLE
    });
  }
  updateImageURL(url) {
    this.props.setAttributes({
      url,
      width: undefined,
      height: undefined
    });
  }
  onSetNewTab(value) {
    const updatedLinkTarget = (0, _utils.getUpdatedLinkTargetSettings)(value, this.props.attributes);
    this.props.setAttributes(updatedLinkTarget);
  }
  onSetSizeSlug(sizeSlug) {
    const {
      image,
      setAttributes
    } = this.props;
    const url = getUrlForSlug(image, sizeSlug);
    if (!url) {
      return null;
    }
    setAttributes({
      url,
      width: undefined,
      height: undefined,
      sizeSlug
    });
  }
  onSelectMediaUploadOption(media) {
    const {
      imageDefaultSize
    } = this.props;
    const {
      id,
      url,
      destination
    } = this.props.attributes;
    const mediaAttributes = {
      id: media.id,
      url: media.url,
      caption: media.caption,
      alt: media.alt
    };
    let additionalAttributes;
    // Reset the dimension attributes if changing to a different image.
    if (!media.id || media.id !== id) {
      additionalAttributes = {
        width: undefined,
        height: undefined,
        sizeSlug: imageDefaultSize
      };
    } else {
      // Keep the same url when selecting the same file, so "Image Size" option is not changed.
      additionalAttributes = {
        url
      };
    }
    let href;
    switch (destination) {
      case _constants.LINK_DESTINATION_MEDIA:
        href = media.url;
        break;
      case _constants.LINK_DESTINATION_ATTACHMENT:
        href = media.link;
        break;
    }
    mediaAttributes.href = href;
    this.props.setAttributes({
      ...mediaAttributes,
      ...additionalAttributes
    });
  }
  onSelectURL(newURL) {
    const {
      createErrorNotice,
      imageDefaultSize,
      setAttributes
    } = this.props;
    if ((0, _url.isURL)(newURL)) {
      this.setState({
        isFetchingImage: true
      });

      // Use RN's Image.getSize to determine if URL is a valid image
      _reactNative.Image.getSize(newURL, () => {
        setAttributes({
          url: newURL,
          id: undefined,
          width: undefined,
          height: undefined,
          sizeSlug: imageDefaultSize
        });
        this.setState({
          isFetchingImage: false
        });
      }, () => {
        createErrorNotice((0, _i18n.__)('Image file not found.'));
        this.setState({
          isFetchingImage: false
        });
      });
    } else {
      createErrorNotice((0, _i18n.__)('Invalid URL.'));
    }
  }
  onFocusCaption() {
    if (this.props.onFocus) {
      this.props.onFocus();
    }
    if (!this.state.isCaptionSelected) {
      this.setState({
        isCaptionSelected: true
      });
    }
  }
  getPlaceholderIcon() {
    return (0, _react.createElement)(_components.Icon, {
      icon: _icons.image,
      ...this.props.getStylesFromColorScheme(_styles.default.iconPlaceholder, _styles.default.iconPlaceholderDark)
    });
  }
  showLoadingIndicator() {
    return (0, _react.createElement)(_reactNative.View, {
      style: _styles.default.image__loading
    }, (0, _react.createElement)(_reactNative.ActivityIndicator, {
      animating: true
    }));
  }
  getWidth() {
    const {
      attributes
    } = this.props;
    const {
      align,
      width
    } = attributes;
    return Object.values(_components.WIDE_ALIGNMENTS.alignments).includes(align) ? '100%' : width;
  }
  setMappedAttributes({
    url: href,
    linkDestination,
    ...restAttributes
  }) {
    const {
      setAttributes
    } = this.props;
    if (!href && !linkDestination) {
      linkDestination = _constants.LINK_DESTINATION_NONE;
    } else if (!linkDestination) {
      linkDestination = _constants.LINK_DESTINATION_CUSTOM;
    }
    return href === undefined || href === this.props.attributes.href ? setAttributes(restAttributes) : setAttributes({
      ...restAttributes,
      linkDestination,
      href
    });
  }
  getAltTextSettings() {
    const {
      attributes: {
        alt
      }
    } = this.props;
    const updateAlt = newAlt => {
      this.props.setAttributes({
        alt: newAlt
      });
    };
    return (0, _react.createElement)(_components.BottomSheetTextControl, {
      initialValue: alt,
      onChange: updateAlt,
      placeholder: (0, _i18n.__)('Add alt text'),
      label: (0, _i18n.__)('Alt Text'),
      icon: _icons.textColor,
      footerNote: (0, _react.createElement)(_react.Fragment, null, (0, _i18n.__)('Describe the purpose of the image. Leave empty if decorative.'), ' ', (0, _react.createElement)(_components.FooterMessageLink, {
        href: 'https://www.w3.org/WAI/tutorials/images/decision-tree/',
        value: (0, _i18n.__)('What is alt text?')
      }))
    });
  }
  onSizeChangeValue(newValue) {
    this.onSetSizeSlug(newValue);
  }
  onSetFeatured(mediaId) {
    const {
      closeSettingsBottomSheet
    } = this.props;
    (0, _reactNativeBridge.setFeaturedImage)(mediaId);
    closeSettingsBottomSheet();
  }
  getFeaturedButtonPanel(isFeaturedImage) {
    const {
      attributes,
      getStylesFromColorScheme
    } = this.props;
    const setFeaturedButtonStyle = getStylesFromColorScheme(_styles.default.setFeaturedButton, _styles.default.setFeaturedButtonDark);
    const removeFeaturedButton = () => (0, _react.createElement)(_components.BottomSheet.Cell, {
      label: (0, _i18n.__)('Remove as Featured Image'),
      labelStyle: [setFeaturedButtonStyle, _styles.default.removeFeaturedButton],
      cellContainerStyle: _styles.default.setFeaturedButtonCellContainer,
      separatorType: 'none',
      onPress: () => this.onSetFeatured(_constants.MEDIA_ID_NO_FEATURED_IMAGE_SET)
    });
    const setFeaturedButton = () => (0, _react.createElement)(_components.BottomSheet.Cell, {
      label: (0, _i18n.__)('Set as Featured Image'),
      labelStyle: setFeaturedButtonStyle,
      cellContainerStyle: _styles.default.setFeaturedButtonCellContainer,
      separatorType: 'none',
      onPress: () => this.onSetFeatured(attributes.id)
    });
    return isFeaturedImage ? removeFeaturedButton() : setFeaturedButton();
  }

  /**
   * Featured images must be set to a successfully uploaded self-hosted image,
   * which has an ID.
   *
   * @return {boolean} Boolean indicating whether or not the current may be set as featured.
   */
  canImageBeFeatured() {
    const {
      attributes: {
        id
      }
    } = this.props;
    return typeof id !== 'undefined' && this.state.uploadStatus !== UPLOAD_STATE_UPLOADING && this.state.uploadStatus !== UPLOAD_STATE_FAILED;
  }
  isGif(url) {
    return url.toLowerCase().includes('.gif');
  }
  render() {
    const {
      isCaptionSelected,
      isFetchingImage
    } = this.state;
    const {
      attributes,
      isSelected,
      image,
      clientId,
      imageDefaultSize,
      context,
      featuredImageId,
      wasBlockJustInserted,
      shouldUseFastImage
    } = this.props;
    const {
      align,
      url,
      alt,
      id,
      sizeSlug,
      className
    } = attributes;
    const hasImageContext = context ? Object.keys(context).length > 0 : false;
    const imageSizes = Array.isArray(this.props.imageSizes) ? this.props.imageSizes : [];
    // Only map available image sizes for the user to choose.
    const sizeOptions = imageSizes.filter(({
      slug
    }) => getUrlForSlug(image, slug)).map(({
      name,
      slug
    }) => ({
      value: slug,
      label: name
    }));
    let selectedSizeOption = sizeSlug || imageDefaultSize;
    let sizeOptionsValid = sizeOptions.find(option => option.value === selectedSizeOption);
    if (!sizeOptionsValid) {
      // Default to 'full' size if the default large size is not available.
      sizeOptionsValid = sizeOptions.find(option => option.value === 'full');
      selectedSizeOption = 'full';
    }
    const canImageBeFeatured = this.canImageBeFeatured();
    const isFeaturedImage = canImageBeFeatured && featuredImageId === attributes.id;
    const getToolbarEditButton = open => (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
      title: (0, _i18n.__)('Edit image'),
      icon: _icons.replace,
      onClick: open
    })));
    const getInspectorControls = () => (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Settings')
    }), (0, _react.createElement)(_components.PanelBody, {
      style: _styles.default.panelBody
    }, (0, _react.createElement)(_blockEditor.BlockStyles, {
      clientId: clientId,
      url: url
    })), (0, _react.createElement)(_components.PanelBody, null, image && sizeOptionsValid && (0, _react.createElement)(_components.BottomSheetSelectControl, {
      icon: _icons.fullscreen,
      label: (0, _i18n.__)('Size'),
      options: sizeOptions,
      onChange: this.onSizeChangeValue,
      value: selectedSizeOption
    }), this.getAltTextSettings()), (0, _react.createElement)(LinkSettings, {
      attributes: this.props.attributes,
      image: this.props.image,
      isLinkSheetVisible: this.state.isLinkSheetVisible,
      setMappedAttributes: this.setMappedAttributes
    }), (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Featured Image'),
      titleStyle: _styles.default.featuredImagePanelTitle
    }, canImageBeFeatured && this.getFeaturedButtonPanel(isFeaturedImage), (0, _react.createElement)(_components.FooterMessageControl, {
      label: (0, _i18n.__)('Changes to featured image will not be affected by the undo/redo buttons.'),
      cellContainerStyle: _styles.default.setFeaturedButtonCellContainer
    })));
    if (!url) {
      return (0, _react.createElement)(_reactNative.View, {
        style: _styles.default.content
      }, isFetchingImage && this.showLoadingIndicator(), (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
        allowedTypes: [_blockEditor.MEDIA_TYPE_IMAGE],
        onSelect: this.onSelectMediaUploadOption,
        onSelectURL: this.onSelectURL,
        icon: this.getPlaceholderIcon(),
        onFocus: this.props.onFocus,
        autoOpenMediaUpload: isSelected && wasBlockJustInserted
      }));
    }
    const alignToFlex = {
      left: 'flex-start',
      center: 'center',
      right: 'flex-end',
      full: 'center',
      wide: 'center'
    };
    const additionalImageProps = {
      height: '100%',
      resizeMode: context?.imageCrop ? 'cover' : 'contain'
    };
    const imageContainerStyles = [context?.fixedHeight && _styles.default.fixedHeight];
    const isGif = this.isGif(url);
    const badgeLabelShown = isFeaturedImage || isGif;
    let badgeLabelText = '';
    if (isFeaturedImage) {
      badgeLabelText = (0, _i18n.__)('Featured');
    } else if (isGif) {
      badgeLabelText = (0, _i18n.__)('GIF');
    }
    const getImageComponent = (openMediaOptions, getMediaOptions) => (0, _react.createElement)(_components.Badge, {
      label: badgeLabelText,
      show: badgeLabelShown
    }, (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
      accessible: !isSelected,
      onPress: this.onImagePressed,
      disabled: !isSelected
    }, (0, _react.createElement)(_reactNative.View, {
      style: _styles.default.content
    }, isSelected && getInspectorControls(), isSelected && getMediaOptions(), !this.state.isCaptionSelected && getToolbarEditButton(openMediaOptions), (0, _react.createElement)(_blockEditor.MediaUploadProgress, {
      enablePausedUploads: true,
      coverUrl: url,
      mediaId: id,
      onUpdateMediaProgress: this.updateMediaProgress,
      onFinishMediaUploadWithSuccess: this.finishMediaUploadWithSuccess,
      onFinishMediaUploadWithFailure: this.finishMediaUploadWithFailure,
      onMediaUploadStateReset: this.mediaUploadStateReset,
      renderContent: ({
        isUploadPaused,
        isUploadInProgress,
        isUploadFailed,
        retryMessage
      }) => {
        return (0, _react.createElement)(_reactNative.View, {
          style: imageContainerStyles
        }, isFetchingImage && this.showLoadingIndicator(), (0, _react.createElement)(_components.Image, {
          align: align && alignToFlex[align],
          alt: alt,
          isSelected: isSelected && !isCaptionSelected,
          isUploadFailed: isUploadFailed,
          isUploadPaused: isUploadPaused,
          isUploadInProgress: isUploadInProgress,
          shouldUseFastImage: shouldUseFastImage,
          onSelectMediaUploadOption: this.onSelectMediaUploadOption,
          openMediaOptions: openMediaOptions,
          retryMessage: retryMessage,
          url: url,
          shapeStyle: _styles.default[className] || className,
          width: this.getWidth(),
          ...(hasImageContext ? additionalImageProps : {})
        }));
      }
    }))), (0, _react.createElement)(_blockEditor.BlockCaption, {
      clientId: this.props.clientId,
      isSelected: this.state.isCaptionSelected,
      accessible: !this.state.isCaptionSelected,
      accessibilityLabelCreator: this.accessibilityLabelCreator,
      onFocus: this.onFocusCaption,
      onBlur: this.props.onBlur // Always assign onBlur as props.
      ,
      insertBlocksAfter: this.props.insertBlocksAfter
    }));
    return (0, _react.createElement)(_blockEditor.MediaUpload, {
      allowedTypes: [_blockEditor.MEDIA_TYPE_IMAGE],
      isReplacingMedia: true,
      onSelect: this.onSelectMediaUploadOption,
      onSelectURL: this.onSelectURL,
      render: ({
        open,
        getMediaOptions
      }) => {
        return getImageComponent(open, getMediaOptions);
      }
    });
  }
}
exports.ImageEdit = ImageEdit;
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, props) => {
  const {
    getMedia
  } = select(_coreData.store);
  const {
    getSettings,
    wasBlockJustInserted
  } = select(_blockEditor.store);
  const {
    getEditedPostAttribute
  } = select('core/editor');
  const {
    attributes: {
      id,
      url
    },
    isSelected,
    clientId
  } = props;
  const {
    imageSizes,
    imageDefaultSize,
    capabilities
  } = getSettings();
  const isNotFileUrl = id && (0, _url.getProtocol)(url) !== 'file:';
  const featuredImageId = getEditedPostAttribute('featured_media');
  const shouldGetMedia = isSelected && isNotFileUrl ||
  // Edge case to update the image after uploading if the block gets unselected
  // Check if it's the original image and not the resized one with queryparams.
  !isSelected && isNotFileUrl && url && !(0, _url.hasQueryArg)(url, 'w');
  const image = shouldGetMedia ? getMedia(id) : null;
  return {
    image,
    imageSizes,
    imageDefaultSize,
    shouldUseFastImage: capabilities?.shouldUseFastImage === true,
    featuredImageId,
    wasBlockJustInserted: wasBlockJustInserted(clientId, 'inserter_menu')
  };
}), (0, _data.withDispatch)(dispatch => {
  const {
    createErrorNotice
  } = dispatch(_notices.store);
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = dispatch(_blockEditor.store);
  return {
    __unstableMarkNextChangeAsNotPersistent,
    createErrorNotice,
    closeSettingsBottomSheet() {
      dispatch(_editPost.store).closeGeneralSidebar();
    }
  };
}), _compose.withPreferredColorScheme])(ImageEdit);
//# sourceMappingURL=edit.native.js.map