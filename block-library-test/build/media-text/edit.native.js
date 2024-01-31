"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
var _constants = require("./constants");
var _mediaContainer = _interopRequireDefault(require("./media-container"));
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

const TEMPLATE = [['core/paragraph']];
// this limits the resize to a safe zone to avoid making broken layouts
const BREAKPOINTS = {
  mobile: 480
};
const applyWidthConstraints = width => Math.max(_constants.WIDTH_CONSTRAINT_PERCENTAGE, Math.min(width, 100 - _constants.WIDTH_CONSTRAINT_PERCENTAGE));
class MediaTextEdit extends _element.Component {
  constructor() {
    super(...arguments);
    this.onSelectMedia = this.onSelectMedia.bind(this);
    this.onMediaUpdate = this.onMediaUpdate.bind(this);
    this.onWidthChange = this.onWidthChange.bind(this);
    this.commitWidthChange = this.commitWidthChange.bind(this);
    this.onLayoutChange = this.onLayoutChange.bind(this);
    this.onMediaSelected = this.onMediaSelected.bind(this);
    this.onReplaceMedia = this.onReplaceMedia.bind(this);
    this.onSetOpenPickerRef = this.onSetOpenPickerRef.bind(this);
    this.onSetImageFill = this.onSetImageFill.bind(this);
    this.state = {
      mediaWidth: null,
      containerWidth: 0,
      isMediaSelected: false
    };
  }
  static getDerivedStateFromProps(props, state) {
    return {
      isMediaSelected: state.isMediaSelected && props.isSelected && !props.isAncestorSelected
    };
  }
  onSelectMedia(media) {
    const {
      setAttributes
    } = this.props;
    let mediaType;
    let src;
    // For media selections originated from a file upload.
    if (media.media_type) {
      if (media.media_type === 'image') {
        mediaType = 'image';
      } else {
        // only images and videos are accepted so if the media_type is not an image we can assume it is a video.
        // video contain the media type of 'file' in the object returned from the rest api.
        mediaType = 'video';
      }
    } else {
      // For media selections originated from existing files in the media library.
      mediaType = media.type;
    }
    if (mediaType === 'image' && media.sizes) {
      // Try the "large" size URL, falling back to the "full" size URL below.
      src = media.sizes.large?.url || media?.media_details?.sizes?.large?.source_url;
    }
    setAttributes({
      mediaAlt: media.alt,
      mediaId: media.id,
      mediaType,
      mediaUrl: src || media.url,
      imageFill: undefined,
      focalPoint: undefined
    });
  }
  onMediaUpdate(media) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      mediaId: media.id,
      mediaUrl: media.url
    });
  }
  onWidthChange(width) {
    this.setState({
      mediaWidth: applyWidthConstraints(width)
    });
  }
  commitWidthChange(width) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      mediaWidth: applyWidthConstraints(width)
    });
    this.setState({
      mediaWidth: null
    });
  }
  onLayoutChange({
    nativeEvent
  }) {
    const {
      width
    } = nativeEvent.layout;
    const {
      containerWidth
    } = this.state;
    if (containerWidth === width) {
      return null;
    }
    this.setState({
      containerWidth: width
    });
  }
  onMediaSelected() {
    this.setState({
      isMediaSelected: true
    });
  }
  onReplaceMedia() {
    if (this.openPickerRef) {
      this.openPickerRef();
    }
  }
  onSetOpenPickerRef(openPicker) {
    this.openPickerRef = openPicker;
  }
  onSetImageFill() {
    const {
      attributes,
      setAttributes
    } = this.props;
    const {
      imageFill
    } = attributes;
    setAttributes({
      imageFill: !imageFill
    });
  }
  getControls() {
    const {
      attributes
    } = this.props;
    const {
      imageFill
    } = attributes;
    return (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Settings')
    }, (0, _react.createElement)(_components.ToggleControl, {
      label: (0, _i18n.__)('Crop image to fill entire column'),
      checked: imageFill,
      onChange: this.onSetImageFill
    })));
  }
  renderMediaArea(shouldStack) {
    const {
      isMediaSelected,
      containerWidth
    } = this.state;
    const {
      attributes,
      isSelected
    } = this.props;
    const {
      mediaAlt,
      mediaId,
      mediaPosition,
      mediaType,
      mediaUrl,
      mediaWidth,
      imageFill,
      focalPoint,
      verticalAlignment
    } = attributes;
    const mediaAreaWidth = mediaWidth && !shouldStack ? containerWidth * mediaWidth / 100 - _style.default.mediaAreaPadding.width : containerWidth;
    const aligmentStyles = _style.default[`is-vertically-aligned-${verticalAlignment || 'center'}`];
    return (0, _react.createElement)(_mediaContainer.default, {
      commitWidthChange: this.commitWidthChange,
      isMediaSelected: isMediaSelected,
      onFocus: this.props.onFocus,
      onMediaSelected: this.onMediaSelected,
      onMediaUpdate: this.onMediaUpdate,
      onSelectMedia: this.onSelectMedia,
      onSetOpenPickerRef: this.onSetOpenPickerRef,
      onWidthChange: this.onWidthChange,
      mediaWidth: mediaAreaWidth,
      mediaAlt,
      mediaId,
      mediaType,
      mediaUrl,
      mediaPosition,
      imageFill,
      focalPoint,
      isSelected,
      aligmentStyles,
      shouldStack
    });
  }
  render() {
    const {
      attributes,
      backgroundColor,
      setAttributes,
      isSelected,
      isRTL,
      style,
      blockWidth
    } = this.props;
    const {
      isStackedOnMobile,
      imageFill,
      mediaPosition,
      mediaWidth,
      mediaType,
      verticalAlignment
    } = attributes;
    const {
      containerWidth,
      isMediaSelected
    } = this.state;
    const isMobile = containerWidth < BREAKPOINTS.mobile;
    const shouldStack = isStackedOnMobile && isMobile;
    const temporaryMediaWidth = shouldStack ? 100 : this.state.mediaWidth || mediaWidth;
    const widthString = `${temporaryMediaWidth}%`;
    const innerBlockWidth = shouldStack ? 100 : 100 - temporaryMediaWidth;
    const innerBlockWidthString = `${innerBlockWidth}%`;
    const hasMedia = mediaType === _blockEditor.MEDIA_TYPE_IMAGE || mediaType === _blockEditor.MEDIA_TYPE_VIDEO;
    const innerBlockContainerStyle = [{
      width: innerBlockWidthString
    }, !shouldStack ? _style.default.innerBlock : {
      ...(mediaPosition === 'left' ? _style.default.innerBlockStackMediaLeft : _style.default.innerBlockStackMediaRight)
    }, (style?.backgroundColor || backgroundColor.color) && _style.default.innerBlockPaddings];
    const containerStyles = {
      ..._style.default['wp-block-media-text'],
      ..._style.default[`is-vertically-aligned-${verticalAlignment || 'center'}`],
      ...(mediaPosition === 'right' ? _style.default['has-media-on-the-right'] : {}),
      ...(shouldStack && _style.default['is-stacked-on-mobile']),
      ...(shouldStack && mediaPosition === 'right' ? _style.default['is-stacked-on-mobile.has-media-on-the-right'] : {}),
      ...(isSelected && _style.default['is-selected']),
      backgroundColor: style?.backgroundColor || backgroundColor.color,
      paddingBottom: 0
    };
    const mediaContainerStyle = [{
      flex: 1
    }, shouldStack ? {
      ...(mediaPosition === 'left' && _style.default.mediaStackLeft),
      ...(mediaPosition === 'right' && _style.default.mediaStackRight)
    } : {
      ...(mediaPosition === 'left' && _style.default.mediaLeft),
      ...(mediaPosition === 'right' && _style.default.mediaRight)
    }];
    const toolbarControls = [{
      icon: isRTL ? _icons.pullRight : _icons.pullLeft,
      title: (0, _i18n.__)('Show media on left'),
      isActive: mediaPosition === 'left',
      onClick: () => setAttributes({
        mediaPosition: 'left'
      })
    }, {
      icon: isRTL ? _icons.pullLeft : _icons.pullRight,
      title: (0, _i18n.__)('Show media on right'),
      isActive: mediaPosition === 'right',
      onClick: () => setAttributes({
        mediaPosition: 'right'
      })
    }];
    const onVerticalAlignmentChange = alignment => {
      setAttributes({
        verticalAlignment: alignment
      });
    };
    return (0, _react.createElement)(_react.Fragment, null, mediaType === _blockEditor.MEDIA_TYPE_IMAGE && this.getControls(), (0, _react.createElement)(_blockEditor.BlockControls, null, hasMedia && (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.Button, {
      label: (0, _i18n.__)('Edit media'),
      icon: _icons.replace,
      onClick: this.onReplaceMedia
    })), (!isMediaSelected || mediaType === _blockEditor.MEDIA_TYPE_VIDEO) && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToolbarGroup, {
      controls: toolbarControls
    }), (0, _react.createElement)(_blockEditor.BlockVerticalAlignmentToolbar, {
      onChange: onVerticalAlignmentChange,
      value: verticalAlignment
    }))), (0, _react.createElement)(_reactNative.View, {
      style: containerStyles,
      onLayout: this.onLayoutChange
    }, (0, _react.createElement)(_reactNative.View, {
      style: [(shouldStack || !imageFill) && {
        width: widthString
      }, mediaContainerStyle]
    }, this.renderMediaArea(shouldStack)), (0, _react.createElement)(_reactNative.View, {
      style: innerBlockContainerStyle
    }, (0, _react.createElement)(_blockEditor.InnerBlocks, {
      template: TEMPLATE,
      blockWidth: blockWidth
    }))));
  }
}
var _default = exports.default = (0, _compose.compose)((0, _blockEditor.withColors)('backgroundColor'), (0, _data.withSelect)((select, {
  clientId
}) => {
  const {
    getSelectedBlockClientId,
    getBlockParents,
    getSettings
  } = select(_blockEditor.store);
  const parents = getBlockParents(clientId, true);
  const selectedBlockClientId = getSelectedBlockClientId();
  const isAncestorSelected = selectedBlockClientId && parents.includes(selectedBlockClientId);
  return {
    isSelected: selectedBlockClientId === clientId,
    isAncestorSelected,
    isRTL: getSettings().isRTL
  };
}))(MediaTextEdit);
//# sourceMappingURL=edit.native.js.map