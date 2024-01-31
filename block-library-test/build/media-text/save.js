"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _mediaContainer = require("./media-container");
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

const DEFAULT_MEDIA_WIDTH = 50;
const noop = () => {};
function save({
  attributes
}) {
  const {
    isStackedOnMobile,
    mediaAlt,
    mediaPosition,
    mediaType,
    mediaUrl,
    mediaWidth,
    mediaId,
    verticalAlignment,
    imageFill,
    focalPoint,
    linkClass,
    href,
    linkTarget,
    rel
  } = attributes;
  const mediaSizeSlug = attributes.mediaSizeSlug || _constants.DEFAULT_MEDIA_SIZE_SLUG;
  const newRel = !rel ? undefined : rel;
  const imageClasses = (0, _classnames.default)({
    [`wp-image-${mediaId}`]: mediaId && mediaType === 'image',
    [`size-${mediaSizeSlug}`]: mediaId && mediaType === 'image'
  });
  let image = (0, _react.createElement)("img", {
    src: mediaUrl,
    alt: mediaAlt,
    className: imageClasses || null
  });
  if (href) {
    image = (0, _react.createElement)("a", {
      className: linkClass,
      href: href,
      target: linkTarget,
      rel: newRel
    }, image);
  }
  const mediaTypeRenders = {
    image: () => image,
    video: () => (0, _react.createElement)("video", {
      controls: true,
      src: mediaUrl
    })
  };
  const className = (0, _classnames.default)({
    'has-media-on-the-right': 'right' === mediaPosition,
    'is-stacked-on-mobile': isStackedOnMobile,
    [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
    'is-image-fill': imageFill
  });
  const backgroundStyles = imageFill ? (0, _mediaContainer.imageFillStyles)(mediaUrl, focalPoint) : {};
  let gridTemplateColumns;
  if (mediaWidth !== DEFAULT_MEDIA_WIDTH) {
    gridTemplateColumns = 'right' === mediaPosition ? `auto ${mediaWidth}%` : `${mediaWidth}% auto`;
  }
  const style = {
    gridTemplateColumns
  };
  if ('right' === mediaPosition) {
    return (0, _react.createElement)("div", {
      ..._blockEditor.useBlockProps.save({
        className,
        style
      })
    }, (0, _react.createElement)("div", {
      ..._blockEditor.useInnerBlocksProps.save({
        className: 'wp-block-media-text__content'
      })
    }), (0, _react.createElement)("figure", {
      className: "wp-block-media-text__media",
      style: backgroundStyles
    }, (mediaTypeRenders[mediaType] || noop)()));
  }
  return (0, _react.createElement)("div", {
    ..._blockEditor.useBlockProps.save({
      className,
      style
    })
  }, (0, _react.createElement)("figure", {
    className: "wp-block-media-text__media",
    style: backgroundStyles
  }, (mediaTypeRenders[mediaType] || noop)()), (0, _react.createElement)("div", {
    ..._blockEditor.useInnerBlocksProps.save({
      className: 'wp-block-media-text__content'
    })
  }));
}
//# sourceMappingURL=save.js.map