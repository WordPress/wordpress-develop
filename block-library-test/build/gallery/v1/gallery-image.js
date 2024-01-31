"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _keycodes = require("@wordpress/keycodes");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _blob = require("@wordpress/blob");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
var _shared = require("./shared");
var _constants = require("./constants");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const isTemporaryImage = (id, url) => !id && (0, _blob.isBlobURL)(url);
class GalleryImage extends _element.Component {
  constructor() {
    super(...arguments);
    this.onSelectImage = this.onSelectImage.bind(this);
    this.onRemoveImage = this.onRemoveImage.bind(this);
    this.bindContainer = this.bindContainer.bind(this);
    this.onEdit = this.onEdit.bind(this);
    this.onSelectImageFromLibrary = this.onSelectImageFromLibrary.bind(this);
    this.onSelectCustomURL = this.onSelectCustomURL.bind(this);
    this.state = {
      isEditing: false
    };
  }
  bindContainer(ref) {
    this.container = ref;
  }
  onSelectImage() {
    if (!this.props.isSelected) {
      this.props.onSelect();
    }
  }
  onRemoveImage(event) {
    if (this.container === this.container.ownerDocument.activeElement && this.props.isSelected && [_keycodes.BACKSPACE, _keycodes.DELETE].indexOf(event.keyCode) !== -1) {
      event.preventDefault();
      this.props.onRemove();
    }
  }
  onEdit() {
    this.setState({
      isEditing: true
    });
  }
  componentDidUpdate() {
    const {
      image,
      url,
      __unstableMarkNextChangeAsNotPersistent
    } = this.props;
    if (image && !url) {
      __unstableMarkNextChangeAsNotPersistent();
      this.props.setAttributes({
        url: image.source_url,
        alt: image.alt_text
      });
    }
  }
  deselectOnBlur() {
    this.props.onDeselect();
  }
  onSelectImageFromLibrary(media) {
    const {
      setAttributes,
      id,
      url,
      alt,
      caption,
      sizeSlug
    } = this.props;
    if (!media || !media.url) {
      return;
    }
    let mediaAttributes = (0, _shared.pickRelevantMediaFiles)(media, sizeSlug);

    // If the current image is temporary but an alt text was meanwhile
    // written by the user, make sure the text is not overwritten.
    if (isTemporaryImage(id, url)) {
      if (alt) {
        const {
          alt: omittedAlt,
          ...restMediaAttributes
        } = mediaAttributes;
        mediaAttributes = restMediaAttributes;
      }
    }

    // If a caption text was meanwhile written by the user,
    // make sure the text is not overwritten by empty captions.
    if (caption && !mediaAttributes.caption) {
      const {
        caption: omittedCaption,
        ...restMediaAttributes
      } = mediaAttributes;
      mediaAttributes = restMediaAttributes;
    }
    setAttributes(mediaAttributes);
    this.setState({
      isEditing: false
    });
  }
  onSelectCustomURL(newURL) {
    const {
      setAttributes,
      url
    } = this.props;
    if (newURL !== url) {
      setAttributes({
        url: newURL,
        id: undefined
      });
      this.setState({
        isEditing: false
      });
    }
  }
  render() {
    const {
      url,
      alt,
      id,
      linkTo,
      link,
      isFirstItem,
      isLastItem,
      isSelected,
      caption,
      onRemove,
      onMoveForward,
      onMoveBackward,
      setAttributes,
      'aria-label': ariaLabel
    } = this.props;
    const {
      isEditing
    } = this.state;
    let href;
    switch (linkTo) {
      case _constants.LINK_DESTINATION_MEDIA:
        href = url;
        break;
      case _constants.LINK_DESTINATION_ATTACHMENT:
        href = link;
        break;
    }
    const img =
    // Disable reason: Image itself is not meant to be interactive, but should
    // direct image selection and unfocus caption fields.
    /* eslint-disable jsx-a11y/no-noninteractive-element-interactions */
    (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("img", {
      src: url,
      alt: alt,
      "data-id": id,
      onKeyDown: this.onRemoveImage,
      tabIndex: "0",
      "aria-label": ariaLabel,
      ref: this.bindContainer
    }), (0, _blob.isBlobURL)(url) && (0, _react.createElement)(_components.Spinner, null))
    /* eslint-enable jsx-a11y/no-noninteractive-element-interactions */;
    const className = (0, _classnames.default)({
      'is-selected': isSelected,
      'is-transient': (0, _blob.isBlobURL)(url)
    });
    return (
      // eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions
      (0, _react.createElement)("figure", {
        className: className,
        onClick: this.onSelectImage,
        onFocus: this.onSelectImage
      }, !isEditing && (href ? (0, _react.createElement)("a", {
        href: href
      }, img) : img), isEditing && (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
        labels: {
          title: (0, _i18n.__)('Edit gallery image')
        },
        icon: _icons.image,
        onSelect: this.onSelectImageFromLibrary,
        onSelectURL: this.onSelectCustomURL,
        accept: "image/*",
        allowedTypes: ['image'],
        value: {
          id,
          src: url
        }
      }), (0, _react.createElement)(_components.ButtonGroup, {
        className: "block-library-gallery-item__inline-menu is-left"
      }, (0, _react.createElement)(_components.Button, {
        icon: _icons.chevronLeft,
        onClick: isFirstItem ? undefined : onMoveBackward,
        label: (0, _i18n.__)('Move image backward'),
        "aria-disabled": isFirstItem,
        disabled: !isSelected
      }), (0, _react.createElement)(_components.Button, {
        icon: _icons.chevronRight,
        onClick: isLastItem ? undefined : onMoveForward,
        label: (0, _i18n.__)('Move image forward'),
        "aria-disabled": isLastItem,
        disabled: !isSelected
      })), (0, _react.createElement)(_components.ButtonGroup, {
        className: "block-library-gallery-item__inline-menu is-right"
      }, (0, _react.createElement)(_components.Button, {
        icon: _icons.edit,
        onClick: this.onEdit,
        label: (0, _i18n.__)('Replace image'),
        disabled: !isSelected
      }), (0, _react.createElement)(_components.Button, {
        icon: _icons.closeSmall,
        onClick: onRemove,
        label: (0, _i18n.__)('Remove image'),
        disabled: !isSelected
      })), !isEditing && (isSelected || caption) && (0, _react.createElement)(_blockEditor.RichText, {
        tagName: "figcaption",
        className: (0, _blockEditor.__experimentalGetElementClassName)('caption'),
        "aria-label": (0, _i18n.__)('Image caption text'),
        placeholder: isSelected ? (0, _i18n.__)('Add caption') : null,
        value: caption,
        onChange: newCaption => setAttributes({
          caption: newCaption
        }),
        inlineToolbar: true
      }))
    );
  }
}
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, ownProps) => {
  const {
    getMedia
  } = select(_coreData.store);
  const {
    id
  } = ownProps;
  return {
    image: id ? getMedia(parseInt(id, 10)) : null
  };
}), (0, _data.withDispatch)(dispatch => {
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = dispatch(_blockEditor.store);
  return {
    __unstableMarkNextChangeAsNotPersistent
  };
})])(GalleryImage);
//# sourceMappingURL=gallery-image.js.map