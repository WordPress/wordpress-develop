"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _shared = require("./shared");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function save({
  attributes
}) {
  const {
    backgroundType,
    gradient,
    contentPosition,
    customGradient,
    customOverlayColor,
    dimRatio,
    focalPoint,
    useFeaturedImage,
    hasParallax,
    isDark,
    isRepeated,
    overlayColor,
    url,
    alt,
    id,
    minHeight: minHeightProp,
    minHeightUnit,
    tagName: Tag
  } = attributes;
  const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
  const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
  const minHeight = minHeightProp && minHeightUnit ? `${minHeightProp}${minHeightUnit}` : minHeightProp;
  const isImageBackground = _shared.IMAGE_BACKGROUND_TYPE === backgroundType;
  const isVideoBackground = _shared.VIDEO_BACKGROUND_TYPE === backgroundType;
  const isImgElement = !(hasParallax || isRepeated);
  const style = {
    minHeight: minHeight || undefined
  };
  const bgStyle = {
    backgroundColor: !overlayColorClass ? customOverlayColor : undefined,
    background: customGradient ? customGradient : undefined
  };
  const objectPosition =
  // prettier-ignore
  focalPoint && isImgElement ? (0, _shared.mediaPosition)(focalPoint) : undefined;
  const backgroundImage = url ? `url(${url})` : undefined;
  const backgroundPosition = (0, _shared.mediaPosition)(focalPoint);
  const classes = (0, _classnames.default)({
    'is-light': !isDark,
    'has-parallax': hasParallax,
    'is-repeated': isRepeated,
    'has-custom-content-position': !(0, _shared.isContentPositionCenter)(contentPosition)
  }, (0, _shared.getPositionClassName)(contentPosition));
  const imgClasses = (0, _classnames.default)('wp-block-cover__image-background', id ? `wp-image-${id}` : null, {
    'has-parallax': hasParallax,
    'is-repeated': isRepeated
  });
  const gradientValue = gradient || customGradient;
  return (0, _react.createElement)(Tag, {
    ..._blockEditor.useBlockProps.save({
      className: classes,
      style
    })
  }, (0, _react.createElement)("span", {
    "aria-hidden": "true",
    className: (0, _classnames.default)('wp-block-cover__background', overlayColorClass, (0, _shared.dimRatioToClass)(dimRatio), {
      'has-background-dim': dimRatio !== undefined,
      // For backwards compatibility. Former versions of the Cover Block applied
      // `.wp-block-cover__gradient-background` in the presence of
      // media, a gradient and a dim.
      'wp-block-cover__gradient-background': url && gradientValue && dimRatio !== 0,
      'has-background-gradient': gradientValue,
      [gradientClass]: gradientClass
    }),
    style: bgStyle
  }), !useFeaturedImage && isImageBackground && url && (isImgElement ? (0, _react.createElement)("img", {
    className: imgClasses,
    alt: alt,
    src: url,
    style: {
      objectPosition
    },
    "data-object-fit": "cover",
    "data-object-position": objectPosition
  }) : (0, _react.createElement)("div", {
    role: alt ? 'img' : undefined,
    "aria-label": alt ? alt : undefined,
    className: imgClasses,
    style: {
      backgroundPosition,
      backgroundImage
    }
  })), isVideoBackground && url && (0, _react.createElement)("video", {
    className: (0, _classnames.default)('wp-block-cover__video-background', 'intrinsic-ignore'),
    autoPlay: true,
    muted: true,
    loop: true,
    playsInline: true,
    src: url,
    style: {
      objectPosition
    },
    "data-object-fit": "cover",
    "data-object-position": objectPosition
  }), (0, _react.createElement)("div", {
    ..._blockEditor.useInnerBlocksProps.save({
      className: 'wp-block-cover__inner-container'
    })
  }));
}
//# sourceMappingURL=save.js.map