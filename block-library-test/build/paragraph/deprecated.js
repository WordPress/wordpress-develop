"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const supports = {
  className: false
};
const blockAttributes = {
  align: {
    type: 'string'
  },
  content: {
    type: 'string',
    source: 'html',
    selector: 'p',
    default: ''
  },
  dropCap: {
    type: 'boolean',
    default: false
  },
  placeholder: {
    type: 'string'
  },
  textColor: {
    type: 'string'
  },
  backgroundColor: {
    type: 'string'
  },
  fontSize: {
    type: 'string'
  },
  direction: {
    type: 'string',
    enum: ['ltr', 'rtl']
  },
  style: {
    type: 'object'
  }
};
const migrateCustomColorsAndFontSizes = attributes => {
  if (!attributes.customTextColor && !attributes.customBackgroundColor && !attributes.customFontSize) {
    return attributes;
  }
  const style = {};
  if (attributes.customTextColor || attributes.customBackgroundColor) {
    style.color = {};
  }
  if (attributes.customTextColor) {
    style.color.text = attributes.customTextColor;
  }
  if (attributes.customBackgroundColor) {
    style.color.background = attributes.customBackgroundColor;
  }
  if (attributes.customFontSize) {
    style.typography = {
      fontSize: attributes.customFontSize
    };
  }
  const {
    customTextColor,
    customBackgroundColor,
    customFontSize,
    ...restAttributes
  } = attributes;
  return {
    ...restAttributes,
    style
  };
};
const {
  style,
  ...restBlockAttributes
} = blockAttributes;
const deprecated = [
// Version without drop cap on aligned text.
{
  supports,
  attributes: {
    ...restBlockAttributes,
    customTextColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    customFontSize: {
      type: 'number'
    }
  },
  save({
    attributes
  }) {
    const {
      align,
      content,
      dropCap,
      direction
    } = attributes;
    const className = (0, _classnames.default)({
      'has-drop-cap': align === ((0, _i18n.isRTL)() ? 'left' : 'right') || align === 'center' ? false : dropCap,
      [`has-text-align-${align}`]: align
    });
    return (0, _react.createElement)("p", {
      ..._blockEditor.useBlockProps.save({
        className,
        dir: direction
      })
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: content
    }));
  }
}, {
  supports,
  attributes: {
    ...restBlockAttributes,
    customTextColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    customFontSize: {
      type: 'number'
    }
  },
  migrate: migrateCustomColorsAndFontSizes,
  save({
    attributes
  }) {
    const {
      align,
      content,
      dropCap,
      backgroundColor,
      textColor,
      customBackgroundColor,
      customTextColor,
      fontSize,
      customFontSize,
      direction
    } = attributes;
    const textClass = (0, _blockEditor.getColorClassName)('color', textColor);
    const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', backgroundColor);
    const fontSizeClass = (0, _blockEditor.getFontSizeClass)(fontSize);
    const className = (0, _classnames.default)({
      'has-text-color': textColor || customTextColor,
      'has-background': backgroundColor || customBackgroundColor,
      'has-drop-cap': dropCap,
      [`has-text-align-${align}`]: align,
      [fontSizeClass]: fontSizeClass,
      [textClass]: textClass,
      [backgroundClass]: backgroundClass
    });
    const styles = {
      backgroundColor: backgroundClass ? undefined : customBackgroundColor,
      color: textClass ? undefined : customTextColor,
      fontSize: fontSizeClass ? undefined : customFontSize
    };
    return (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "p",
      style: styles,
      className: className ? className : undefined,
      value: content,
      dir: direction
    });
  }
}, {
  supports,
  attributes: {
    ...restBlockAttributes,
    customTextColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    customFontSize: {
      type: 'number'
    }
  },
  migrate: migrateCustomColorsAndFontSizes,
  save({
    attributes
  }) {
    const {
      align,
      content,
      dropCap,
      backgroundColor,
      textColor,
      customBackgroundColor,
      customTextColor,
      fontSize,
      customFontSize,
      direction
    } = attributes;
    const textClass = (0, _blockEditor.getColorClassName)('color', textColor);
    const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', backgroundColor);
    const fontSizeClass = (0, _blockEditor.getFontSizeClass)(fontSize);
    const className = (0, _classnames.default)({
      'has-text-color': textColor || customTextColor,
      'has-background': backgroundColor || customBackgroundColor,
      'has-drop-cap': dropCap,
      [fontSizeClass]: fontSizeClass,
      [textClass]: textClass,
      [backgroundClass]: backgroundClass
    });
    const styles = {
      backgroundColor: backgroundClass ? undefined : customBackgroundColor,
      color: textClass ? undefined : customTextColor,
      fontSize: fontSizeClass ? undefined : customFontSize,
      textAlign: align
    };
    return (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "p",
      style: styles,
      className: className ? className : undefined,
      value: content,
      dir: direction
    });
  }
}, {
  supports,
  attributes: {
    ...restBlockAttributes,
    customTextColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    customFontSize: {
      type: 'number'
    },
    width: {
      type: 'string'
    }
  },
  migrate: migrateCustomColorsAndFontSizes,
  save({
    attributes
  }) {
    const {
      width,
      align,
      content,
      dropCap,
      backgroundColor,
      textColor,
      customBackgroundColor,
      customTextColor,
      fontSize,
      customFontSize
    } = attributes;
    const textClass = (0, _blockEditor.getColorClassName)('color', textColor);
    const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', backgroundColor);
    const fontSizeClass = fontSize && `is-${fontSize}-text`;
    const className = (0, _classnames.default)({
      [`align${width}`]: width,
      'has-background': backgroundColor || customBackgroundColor,
      'has-drop-cap': dropCap,
      [fontSizeClass]: fontSizeClass,
      [textClass]: textClass,
      [backgroundClass]: backgroundClass
    });
    const styles = {
      backgroundColor: backgroundClass ? undefined : customBackgroundColor,
      color: textClass ? undefined : customTextColor,
      fontSize: fontSizeClass ? undefined : customFontSize,
      textAlign: align
    };
    return (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "p",
      style: styles,
      className: className ? className : undefined,
      value: content
    });
  }
}, {
  supports,
  attributes: {
    ...restBlockAttributes,
    fontSize: {
      type: 'number'
    }
  },
  save({
    attributes
  }) {
    const {
      width,
      align,
      content,
      dropCap,
      backgroundColor,
      textColor,
      fontSize
    } = attributes;
    const className = (0, _classnames.default)({
      [`align${width}`]: width,
      'has-background': backgroundColor,
      'has-drop-cap': dropCap
    });
    const styles = {
      backgroundColor,
      color: textColor,
      fontSize,
      textAlign: align
    };
    return (0, _react.createElement)("p", {
      style: styles,
      className: className ? className : undefined
    }, content);
  },
  migrate(attributes) {
    return migrateCustomColorsAndFontSizes({
      ...attributes,
      customFontSize: Number.isFinite(attributes.fontSize) ? attributes.fontSize : undefined,
      customTextColor: attributes.textColor && '#' === attributes.textColor[0] ? attributes.textColor : undefined,
      customBackgroundColor: attributes.backgroundColor && '#' === attributes.backgroundColor[0] ? attributes.backgroundColor : undefined
    });
  }
}, {
  supports,
  attributes: {
    ...blockAttributes,
    content: {
      type: 'string',
      source: 'html',
      default: ''
    }
  },
  save({
    attributes
  }) {
    return (0, _react.createElement)(_element.RawHTML, null, attributes.content);
  },
  migrate(attributes) {
    return attributes;
  }
}];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map