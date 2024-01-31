"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blocks = require("@wordpress/blocks");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
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

function backgroundImageStyles(url) {
  return url ? {
    backgroundImage: `url(${url})`
  } : {};
}

/**
 * Original function to determine the background opacity classname
 *
 * Used in deprecations: v1-7.
 *
 * @param {number} ratio ratio to use for opacity.
 * @return {string}       background opacity class   .
 */
function dimRatioToClassV1(ratio) {
  return ratio === 0 || ratio === 50 || !ratio ? null : 'has-background-dim-' + 10 * Math.round(ratio / 10);
}
function migrateDimRatio(attributes) {
  return {
    ...attributes,
    dimRatio: !attributes.url ? 100 : attributes.dimRatio
  };
}
function migrateTag(attributes) {
  if (!attributes.tagName) {
    attributes = {
      ...attributes,
      tagName: 'div'
    };
  }
  return {
    ...attributes
  };
}
const blockAttributes = {
  url: {
    type: 'string'
  },
  id: {
    type: 'number'
  },
  hasParallax: {
    type: 'boolean',
    default: false
  },
  dimRatio: {
    type: 'number',
    default: 50
  },
  overlayColor: {
    type: 'string'
  },
  customOverlayColor: {
    type: 'string'
  },
  backgroundType: {
    type: 'string',
    default: 'image'
  },
  focalPoint: {
    type: 'object'
  }
};
const v8ToV11BlockAttributes = {
  url: {
    type: 'string'
  },
  id: {
    type: 'number'
  },
  alt: {
    type: 'string',
    source: 'attribute',
    selector: 'img',
    attribute: 'alt',
    default: ''
  },
  hasParallax: {
    type: 'boolean',
    default: false
  },
  isRepeated: {
    type: 'boolean',
    default: false
  },
  dimRatio: {
    type: 'number',
    default: 100
  },
  overlayColor: {
    type: 'string'
  },
  customOverlayColor: {
    type: 'string'
  },
  backgroundType: {
    type: 'string',
    default: 'image'
  },
  focalPoint: {
    type: 'object'
  },
  minHeight: {
    type: 'number'
  },
  minHeightUnit: {
    type: 'string'
  },
  gradient: {
    type: 'string'
  },
  customGradient: {
    type: 'string'
  },
  contentPosition: {
    type: 'string'
  },
  isDark: {
    type: 'boolean',
    default: true
  },
  allowedBlocks: {
    type: 'array'
  },
  templateLock: {
    type: ['string', 'boolean'],
    enum: ['all', 'insert', false]
  }
};
const v12BlockAttributes = {
  ...v8ToV11BlockAttributes,
  useFeaturedImage: {
    type: 'boolean',
    default: false
  },
  tagName: {
    type: 'string',
    default: 'div'
  }
};
const v7toV11BlockSupports = {
  anchor: true,
  align: true,
  html: false,
  spacing: {
    padding: true,
    __experimentalDefaultControls: {
      padding: true
    }
  },
  color: {
    __experimentalDuotone: '> .wp-block-cover__image-background, > .wp-block-cover__video-background',
    text: false,
    background: false
  }
};
const v12BlockSupports = {
  ...v7toV11BlockSupports,
  spacing: {
    padding: true,
    margin: ['top', 'bottom'],
    blockGap: true,
    __experimentalDefaultControls: {
      padding: true,
      blockGap: true
    }
  },
  __experimentalBorder: {
    color: true,
    radius: true,
    style: true,
    width: true,
    __experimentalDefaultControls: {
      color: true,
      radius: true,
      style: true,
      width: true
    }
  },
  color: {
    __experimentalDuotone: '> .wp-block-cover__image-background, > .wp-block-cover__video-background',
    heading: true,
    text: true,
    background: false,
    __experimentalSkipSerialization: ['gradients'],
    enableContrastChecker: false
  },
  typography: {
    fontSize: true,
    lineHeight: true,
    __experimentalFontFamily: true,
    __experimentalFontWeight: true,
    __experimentalFontStyle: true,
    __experimentalTextTransform: true,
    __experimentalTextDecoration: true,
    __experimentalLetterSpacing: true,
    __experimentalDefaultControls: {
      fontSize: true
    }
  },
  layout: {
    allowJustification: false
  }
};

// Deprecation for blocks that does not have the aria-label when the image background is fixed or repeated.
const v13 = {
  attributes: v12BlockAttributes,
  supports: v12BlockSupports,
  save({
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
      role: "img",
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
};

// Deprecation for blocks to prevent auto overlay color from overriding previously set values.
const v12 = {
  attributes: v12BlockAttributes,
  supports: v12BlockSupports,
  isEligible(attributes) {
    return attributes.customOverlayColor !== undefined || attributes.overlayColor !== undefined;
  },
  migrate(attributes) {
    return {
      ...attributes,
      isUserOverlayColor: true
    };
  },
  save({
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
      role: "img",
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
};

// Deprecation for blocks that does not have a HTML tag option.
const v11 = {
  attributes: v8ToV11BlockAttributes,
  supports: v7toV11BlockSupports,
  save({
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
      minHeightUnit
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
    return (0, _react.createElement)("div", {
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
      role: "img",
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
  },
  migrate: migrateTag
};

// Deprecation for blocks that renders fixed background as backgroud from the main block container.
const v10 = {
  attributes: v8ToV11BlockAttributes,
  supports: v7toV11BlockSupports,
  save({
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
      minHeightUnit
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
    const minHeight = minHeightProp && minHeightUnit ? `${minHeightProp}${minHeightUnit}` : minHeightProp;
    const isImageBackground = _shared.IMAGE_BACKGROUND_TYPE === backgroundType;
    const isVideoBackground = _shared.VIDEO_BACKGROUND_TYPE === backgroundType;
    const isImgElement = !(hasParallax || isRepeated);
    const style = {
      ...(isImageBackground && !isImgElement && !useFeaturedImage ? backgroundImageStyles(url) : {}),
      minHeight: minHeight || undefined
    };
    const bgStyle = {
      backgroundColor: !overlayColorClass ? customOverlayColor : undefined,
      background: customGradient ? customGradient : undefined
    };
    const objectPosition =
    // prettier-ignore
    focalPoint && isImgElement ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%` : undefined;
    const classes = (0, _classnames.default)({
      'is-light': !isDark,
      'has-parallax': hasParallax,
      'is-repeated': isRepeated,
      'has-custom-content-position': !(0, _shared.isContentPositionCenter)(contentPosition)
    }, (0, _shared.getPositionClassName)(contentPosition));
    const gradientValue = gradient || customGradient;
    return (0, _react.createElement)("div", {
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
    }), !useFeaturedImage && isImageBackground && isImgElement && url && (0, _react.createElement)("img", {
      className: (0, _classnames.default)('wp-block-cover__image-background', id ? `wp-image-${id}` : null),
      alt: alt,
      src: url,
      style: {
        objectPosition
      },
      "data-object-fit": "cover",
      "data-object-position": objectPosition
    }), isVideoBackground && url && (0, _react.createElement)("video", {
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
  },
  migrate: migrateTag
};

// Deprecation for blocks with `minHeightUnit` set but no `minHeight`.
const v9 = {
  attributes: v8ToV11BlockAttributes,
  supports: v7toV11BlockSupports,
  save({
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
      hasParallax,
      isDark,
      isRepeated,
      overlayColor,
      url,
      alt,
      id,
      minHeight: minHeightProp,
      minHeightUnit
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
    const minHeight = minHeightUnit ? `${minHeightProp}${minHeightUnit}` : minHeightProp;
    const isImageBackground = _shared.IMAGE_BACKGROUND_TYPE === backgroundType;
    const isVideoBackground = _shared.VIDEO_BACKGROUND_TYPE === backgroundType;
    const isImgElement = !(hasParallax || isRepeated);
    const style = {
      ...(isImageBackground && !isImgElement ? backgroundImageStyles(url) : {}),
      minHeight: minHeight || undefined
    };
    const bgStyle = {
      backgroundColor: !overlayColorClass ? customOverlayColor : undefined,
      background: customGradient ? customGradient : undefined
    };
    const objectPosition =
    // prettier-ignore
    focalPoint && isImgElement ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%` : undefined;
    const classes = (0, _classnames.default)({
      'is-light': !isDark,
      'has-parallax': hasParallax,
      'is-repeated': isRepeated,
      'has-custom-content-position': !(0, _shared.isContentPositionCenter)(contentPosition)
    }, (0, _shared.getPositionClassName)(contentPosition));
    const gradientValue = gradient || customGradient;
    return (0, _react.createElement)("div", {
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
    }), isImageBackground && isImgElement && url && (0, _react.createElement)("img", {
      className: (0, _classnames.default)('wp-block-cover__image-background', id ? `wp-image-${id}` : null),
      alt: alt,
      src: url,
      style: {
        objectPosition
      },
      "data-object-fit": "cover",
      "data-object-position": objectPosition
    }), isVideoBackground && url && (0, _react.createElement)("video", {
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
  },
  migrate: migrateTag
};

// v8: deprecated to remove duplicated gradient classes and swap `wp-block-cover__gradient-background` for `wp-block-cover__background`.
const v8 = {
  attributes: v8ToV11BlockAttributes,
  supports: v7toV11BlockSupports,
  save({
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
      hasParallax,
      isDark,
      isRepeated,
      overlayColor,
      url,
      alt,
      id,
      minHeight: minHeightProp,
      minHeightUnit
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
    const minHeight = minHeightUnit ? `${minHeightProp}${minHeightUnit}` : minHeightProp;
    const isImageBackground = _shared.IMAGE_BACKGROUND_TYPE === backgroundType;
    const isVideoBackground = _shared.VIDEO_BACKGROUND_TYPE === backgroundType;
    const isImgElement = !(hasParallax || isRepeated);
    const style = {
      ...(isImageBackground && !isImgElement ? backgroundImageStyles(url) : {}),
      minHeight: minHeight || undefined
    };
    const bgStyle = {
      backgroundColor: !overlayColorClass ? customOverlayColor : undefined,
      background: customGradient ? customGradient : undefined
    };
    const objectPosition =
    // prettier-ignore
    focalPoint && isImgElement ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%` : undefined;
    const classes = (0, _classnames.default)({
      'is-light': !isDark,
      'has-parallax': hasParallax,
      'is-repeated': isRepeated,
      'has-custom-content-position': !(0, _shared.isContentPositionCenter)(contentPosition)
    }, (0, _shared.getPositionClassName)(contentPosition));
    return (0, _react.createElement)("div", {
      ..._blockEditor.useBlockProps.save({
        className: classes,
        style
      })
    }, (0, _react.createElement)("span", {
      "aria-hidden": "true",
      className: (0, _classnames.default)(overlayColorClass, (0, _shared.dimRatioToClass)(dimRatio), 'wp-block-cover__gradient-background', gradientClass, {
        'has-background-dim': dimRatio !== undefined,
        'has-background-gradient': gradient || customGradient,
        [gradientClass]: !url && gradientClass
      }),
      style: bgStyle
    }), isImageBackground && isImgElement && url && (0, _react.createElement)("img", {
      className: (0, _classnames.default)('wp-block-cover__image-background', id ? `wp-image-${id}` : null),
      alt: alt,
      src: url,
      style: {
        objectPosition
      },
      "data-object-fit": "cover",
      "data-object-position": objectPosition
    }), isVideoBackground && url && (0, _react.createElement)("video", {
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
  },
  migrate: migrateTag
};
const v7 = {
  attributes: {
    ...blockAttributes,
    isRepeated: {
      type: 'boolean',
      default: false
    },
    minHeight: {
      type: 'number'
    },
    minHeightUnit: {
      type: 'string'
    },
    gradient: {
      type: 'string'
    },
    customGradient: {
      type: 'string'
    },
    contentPosition: {
      type: 'string'
    },
    alt: {
      type: 'string',
      source: 'attribute',
      selector: 'img',
      attribute: 'alt',
      default: ''
    }
  },
  supports: v7toV11BlockSupports,
  save({
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
      hasParallax,
      isRepeated,
      overlayColor,
      url,
      alt,
      id,
      minHeight: minHeightProp,
      minHeightUnit
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
    const minHeight = minHeightUnit ? `${minHeightProp}${minHeightUnit}` : minHeightProp;
    const isImageBackground = _shared.IMAGE_BACKGROUND_TYPE === backgroundType;
    const isVideoBackground = _shared.VIDEO_BACKGROUND_TYPE === backgroundType;
    const isImgElement = !(hasParallax || isRepeated);
    const style = {
      ...(isImageBackground && !isImgElement ? backgroundImageStyles(url) : {}),
      backgroundColor: !overlayColorClass ? customOverlayColor : undefined,
      background: customGradient && !url ? customGradient : undefined,
      minHeight: minHeight || undefined
    };
    const objectPosition =
    // prettier-ignore
    focalPoint && isImgElement ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%` : undefined;
    const classes = (0, _classnames.default)(dimRatioToClassV1(dimRatio), overlayColorClass, {
      'has-background-dim': dimRatio !== 0,
      'has-parallax': hasParallax,
      'is-repeated': isRepeated,
      'has-background-gradient': gradient || customGradient,
      [gradientClass]: !url && gradientClass,
      'has-custom-content-position': !(0, _shared.isContentPositionCenter)(contentPosition)
    }, (0, _shared.getPositionClassName)(contentPosition));
    return (0, _react.createElement)("div", {
      ..._blockEditor.useBlockProps.save({
        className: classes,
        style
      })
    }, url && (gradient || customGradient) && dimRatio !== 0 && (0, _react.createElement)("span", {
      "aria-hidden": "true",
      className: (0, _classnames.default)('wp-block-cover__gradient-background', gradientClass),
      style: customGradient ? {
        background: customGradient
      } : undefined
    }), isImageBackground && isImgElement && url && (0, _react.createElement)("img", {
      className: (0, _classnames.default)('wp-block-cover__image-background', id ? `wp-image-${id}` : null),
      alt: alt,
      src: url,
      style: {
        objectPosition
      },
      "data-object-fit": "cover",
      "data-object-position": objectPosition
    }), isVideoBackground && url && (0, _react.createElement)("video", {
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
      className: "wp-block-cover__inner-container"
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null)));
  },
  migrate: (0, _compose.compose)(migrateDimRatio, migrateTag)
};
const v6 = {
  attributes: {
    ...blockAttributes,
    isRepeated: {
      type: 'boolean',
      default: false
    },
    minHeight: {
      type: 'number'
    },
    minHeightUnit: {
      type: 'string'
    },
    gradient: {
      type: 'string'
    },
    customGradient: {
      type: 'string'
    },
    contentPosition: {
      type: 'string'
    }
  },
  supports: {
    align: true
  },
  save({
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
      hasParallax,
      isRepeated,
      overlayColor,
      url,
      minHeight: minHeightProp,
      minHeightUnit
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
    const minHeight = minHeightUnit ? `${minHeightProp}${minHeightUnit}` : minHeightProp;
    const isImageBackground = _shared.IMAGE_BACKGROUND_TYPE === backgroundType;
    const isVideoBackground = _shared.VIDEO_BACKGROUND_TYPE === backgroundType;
    const style = isImageBackground ? backgroundImageStyles(url) : {};
    const videoStyle = {};
    if (!overlayColorClass) {
      style.backgroundColor = customOverlayColor;
    }
    if (customGradient && !url) {
      style.background = customGradient;
    }
    style.minHeight = minHeight || undefined;
    let positionValue;
    if (focalPoint) {
      positionValue = `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`;
      if (isImageBackground && !hasParallax) {
        style.backgroundPosition = positionValue;
      }
      if (isVideoBackground) {
        videoStyle.objectPosition = positionValue;
      }
    }
    const classes = (0, _classnames.default)(dimRatioToClassV1(dimRatio), overlayColorClass, {
      'has-background-dim': dimRatio !== 0,
      'has-parallax': hasParallax,
      'is-repeated': isRepeated,
      'has-background-gradient': gradient || customGradient,
      [gradientClass]: !url && gradientClass,
      'has-custom-content-position': !(0, _shared.isContentPositionCenter)(contentPosition)
    }, (0, _shared.getPositionClassName)(contentPosition));
    return (0, _react.createElement)("div", {
      ..._blockEditor.useBlockProps.save({
        className: classes,
        style
      })
    }, url && (gradient || customGradient) && dimRatio !== 0 && (0, _react.createElement)("span", {
      "aria-hidden": "true",
      className: (0, _classnames.default)('wp-block-cover__gradient-background', gradientClass),
      style: customGradient ? {
        background: customGradient
      } : undefined
    }), isVideoBackground && url && (0, _react.createElement)("video", {
      className: "wp-block-cover__video-background",
      autoPlay: true,
      muted: true,
      loop: true,
      playsInline: true,
      src: url,
      style: videoStyle
    }), (0, _react.createElement)("div", {
      className: "wp-block-cover__inner-container"
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null)));
  },
  migrate: (0, _compose.compose)(migrateDimRatio, migrateTag)
};
const v5 = {
  attributes: {
    ...blockAttributes,
    minHeight: {
      type: 'number'
    },
    gradient: {
      type: 'string'
    },
    customGradient: {
      type: 'string'
    }
  },
  supports: {
    align: true
  },
  save({
    attributes
  }) {
    const {
      backgroundType,
      gradient,
      customGradient,
      customOverlayColor,
      dimRatio,
      focalPoint,
      hasParallax,
      overlayColor,
      url,
      minHeight
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
    const style = backgroundType === _shared.IMAGE_BACKGROUND_TYPE ? backgroundImageStyles(url) : {};
    if (!overlayColorClass) {
      style.backgroundColor = customOverlayColor;
    }
    if (focalPoint && !hasParallax) {
      style.backgroundPosition = `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`;
    }
    if (customGradient && !url) {
      style.background = customGradient;
    }
    style.minHeight = minHeight || undefined;
    const classes = (0, _classnames.default)(dimRatioToClassV1(dimRatio), overlayColorClass, {
      'has-background-dim': dimRatio !== 0,
      'has-parallax': hasParallax,
      'has-background-gradient': customGradient,
      [gradientClass]: !url && gradientClass
    });
    return (0, _react.createElement)("div", {
      className: classes,
      style: style
    }, url && (gradient || customGradient) && dimRatio !== 0 && (0, _react.createElement)("span", {
      "aria-hidden": "true",
      className: (0, _classnames.default)('wp-block-cover__gradient-background', gradientClass),
      style: customGradient ? {
        background: customGradient
      } : undefined
    }), _shared.VIDEO_BACKGROUND_TYPE === backgroundType && url && (0, _react.createElement)("video", {
      className: "wp-block-cover__video-background",
      autoPlay: true,
      muted: true,
      loop: true,
      src: url
    }), (0, _react.createElement)("div", {
      className: "wp-block-cover__inner-container"
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null)));
  },
  migrate: (0, _compose.compose)(migrateDimRatio, migrateTag)
};
const v4 = {
  attributes: {
    ...blockAttributes,
    minHeight: {
      type: 'number'
    },
    gradient: {
      type: 'string'
    },
    customGradient: {
      type: 'string'
    }
  },
  supports: {
    align: true
  },
  save({
    attributes
  }) {
    const {
      backgroundType,
      gradient,
      customGradient,
      customOverlayColor,
      dimRatio,
      focalPoint,
      hasParallax,
      overlayColor,
      url,
      minHeight
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const gradientClass = (0, _blockEditor.__experimentalGetGradientClass)(gradient);
    const style = backgroundType === _shared.IMAGE_BACKGROUND_TYPE ? backgroundImageStyles(url) : {};
    if (!overlayColorClass) {
      style.backgroundColor = customOverlayColor;
    }
    if (focalPoint && !hasParallax) {
      style.backgroundPosition = `${focalPoint.x * 100}% ${focalPoint.y * 100}%`;
    }
    if (customGradient && !url) {
      style.background = customGradient;
    }
    style.minHeight = minHeight || undefined;
    const classes = (0, _classnames.default)(dimRatioToClassV1(dimRatio), overlayColorClass, {
      'has-background-dim': dimRatio !== 0,
      'has-parallax': hasParallax,
      'has-background-gradient': customGradient,
      [gradientClass]: !url && gradientClass
    });
    return (0, _react.createElement)("div", {
      className: classes,
      style: style
    }, url && (gradient || customGradient) && dimRatio !== 0 && (0, _react.createElement)("span", {
      "aria-hidden": "true",
      className: (0, _classnames.default)('wp-block-cover__gradient-background', gradientClass),
      style: customGradient ? {
        background: customGradient
      } : undefined
    }), _shared.VIDEO_BACKGROUND_TYPE === backgroundType && url && (0, _react.createElement)("video", {
      className: "wp-block-cover__video-background",
      autoPlay: true,
      muted: true,
      loop: true,
      src: url
    }), (0, _react.createElement)("div", {
      className: "wp-block-cover__inner-container"
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null)));
  },
  migrate: (0, _compose.compose)(migrateDimRatio, migrateTag)
};
const v3 = {
  attributes: {
    ...blockAttributes,
    title: {
      type: 'string',
      source: 'html',
      selector: 'p'
    },
    contentAlign: {
      type: 'string',
      default: 'center'
    }
  },
  supports: {
    align: true
  },
  save({
    attributes
  }) {
    const {
      backgroundType,
      contentAlign,
      customOverlayColor,
      dimRatio,
      focalPoint,
      hasParallax,
      overlayColor,
      title,
      url
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const style = backgroundType === _shared.IMAGE_BACKGROUND_TYPE ? backgroundImageStyles(url) : {};
    if (!overlayColorClass) {
      style.backgroundColor = customOverlayColor;
    }
    if (focalPoint && !hasParallax) {
      style.backgroundPosition = `${focalPoint.x * 100}% ${focalPoint.y * 100}%`;
    }
    const classes = (0, _classnames.default)(dimRatioToClassV1(dimRatio), overlayColorClass, {
      'has-background-dim': dimRatio !== 0,
      'has-parallax': hasParallax,
      [`has-${contentAlign}-content`]: contentAlign !== 'center'
    });
    return (0, _react.createElement)("div", {
      className: classes,
      style: style
    }, _shared.VIDEO_BACKGROUND_TYPE === backgroundType && url && (0, _react.createElement)("video", {
      className: "wp-block-cover__video-background",
      autoPlay: true,
      muted: true,
      loop: true,
      src: url
    }), !_blockEditor.RichText.isEmpty(title) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "p",
      className: "wp-block-cover-text",
      value: title
    }));
  },
  migrate(attributes) {
    const newAttribs = {
      ...attributes,
      dimRatio: !attributes.url ? 100 : attributes.dimRatio,
      tagName: !attributes.tagName ? 'div' : attributes.tagName
    };
    const {
      title,
      contentAlign,
      ...restAttributes
    } = newAttribs;
    return [restAttributes, [(0, _blocks.createBlock)('core/paragraph', {
      content: attributes.title,
      align: attributes.contentAlign,
      fontSize: 'large',
      placeholder: (0, _i18n.__)('Write title…')
    })]];
  }
};
const v2 = {
  attributes: {
    ...blockAttributes,
    title: {
      type: 'string',
      source: 'html',
      selector: 'p'
    },
    contentAlign: {
      type: 'string',
      default: 'center'
    },
    align: {
      type: 'string'
    }
  },
  supports: {
    className: false
  },
  save({
    attributes
  }) {
    const {
      url,
      title,
      hasParallax,
      dimRatio,
      align,
      contentAlign,
      overlayColor,
      customOverlayColor
    } = attributes;
    const overlayColorClass = (0, _blockEditor.getColorClassName)('background-color', overlayColor);
    const style = backgroundImageStyles(url);
    if (!overlayColorClass) {
      style.backgroundColor = customOverlayColor;
    }
    const classes = (0, _classnames.default)('wp-block-cover-image', dimRatioToClassV1(dimRatio), overlayColorClass, {
      'has-background-dim': dimRatio !== 0,
      'has-parallax': hasParallax,
      [`has-${contentAlign}-content`]: contentAlign !== 'center'
    }, align ? `align${align}` : null);
    return (0, _react.createElement)("div", {
      className: classes,
      style: style
    }, !_blockEditor.RichText.isEmpty(title) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "p",
      className: "wp-block-cover-image-text",
      value: title
    }));
  },
  migrate(attributes) {
    const newAttribs = {
      ...attributes,
      dimRatio: !attributes.url ? 100 : attributes.dimRatio,
      tagName: !attributes.tagName ? 'div' : attributes.tagName
    };
    const {
      title,
      contentAlign,
      align,
      ...restAttributes
    } = newAttribs;
    return [restAttributes, [(0, _blocks.createBlock)('core/paragraph', {
      content: attributes.title,
      align: attributes.contentAlign,
      fontSize: 'large',
      placeholder: (0, _i18n.__)('Write title…')
    })]];
  }
};
const v1 = {
  attributes: {
    ...blockAttributes,
    title: {
      type: 'string',
      source: 'html',
      selector: 'h2'
    },
    align: {
      type: 'string'
    },
    contentAlign: {
      type: 'string',
      default: 'center'
    }
  },
  supports: {
    className: false
  },
  save({
    attributes
  }) {
    const {
      url,
      title,
      hasParallax,
      dimRatio,
      align
    } = attributes;
    const style = backgroundImageStyles(url);
    const classes = (0, _classnames.default)('wp-block-cover-image', dimRatioToClassV1(dimRatio), {
      'has-background-dim': dimRatio !== 0,
      'has-parallax': hasParallax
    }, align ? `align${align}` : null);
    return (0, _react.createElement)("section", {
      className: classes,
      style: style
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "h2",
      value: title
    }));
  },
  migrate(attributes) {
    const newAttribs = {
      ...attributes,
      dimRatio: !attributes.url ? 100 : attributes.dimRatio,
      tagName: !attributes.tagName ? 'div' : attributes.tagName
    };
    const {
      title,
      contentAlign,
      align,
      ...restAttributes
    } = newAttribs;
    return [restAttributes, [(0, _blocks.createBlock)('core/paragraph', {
      content: attributes.title,
      align: attributes.contentAlign,
      fontSize: 'large',
      placeholder: (0, _i18n.__)('Write title…')
    })]];
  }
};
var _default = exports.default = [v13, v12, v11, v10, v9, v8, v7, v6, v5, v4, v3, v2, v1];
//# sourceMappingURL=deprecated.js.map