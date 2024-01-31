"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
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

const blockAttributes = {
  value: {
    type: 'string',
    source: 'html',
    selector: 'blockquote',
    multiline: 'p'
  },
  citation: {
    type: 'string',
    source: 'html',
    selector: 'cite',
    default: ''
  },
  mainColor: {
    type: 'string'
  },
  customMainColor: {
    type: 'string'
  },
  textColor: {
    type: 'string'
  },
  customTextColor: {
    type: 'string'
  }
};
function parseBorderColor(styleString) {
  if (!styleString) {
    return;
  }
  const matches = styleString.match(/border-color:([^;]+)[;]?/);
  if (matches && matches[1]) {
    return matches[1];
  }
}
function multilineToInline(value) {
  value = value || `<p></p>`;
  const padded = `</p>${value}<p>`;
  const values = padded.split(`</p><p>`);
  values.shift();
  values.pop();
  return values.join('<br>');
}
const v5 = {
  attributes: {
    value: {
      type: 'string',
      source: 'html',
      selector: 'blockquote',
      multiline: 'p',
      __experimentalRole: 'content'
    },
    citation: {
      type: 'string',
      source: 'html',
      selector: 'cite',
      default: '',
      __experimentalRole: 'content'
    },
    textAlign: {
      type: 'string'
    }
  },
  save({
    attributes
  }) {
    const {
      textAlign,
      citation,
      value
    } = attributes;
    const shouldShowCitation = !_blockEditor.RichText.isEmpty(citation);
    return (0, _react.createElement)("figure", {
      ..._blockEditor.useBlockProps.save({
        className: (0, _classnames.default)({
          [`has-text-align-${textAlign}`]: textAlign
        })
      })
    }, (0, _react.createElement)("blockquote", null, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: value,
      multiline: true
    }), shouldShowCitation && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    })));
  },
  migrate({
    value,
    ...attributes
  }) {
    return {
      value: multilineToInline(value),
      ...attributes
    };
  }
};

// TODO: this is ripe for a bit of a clean up according to the example in https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/#example

const v4 = {
  attributes: {
    ...blockAttributes
  },
  save({
    attributes
  }) {
    const {
      mainColor,
      customMainColor,
      customTextColor,
      textColor,
      value,
      citation,
      className
    } = attributes;
    const isSolidColorStyle = className?.includes(_shared.SOLID_COLOR_CLASS);
    let figureClasses, figureStyles;

    // Is solid color style
    if (isSolidColorStyle) {
      const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', mainColor);
      figureClasses = (0, _classnames.default)({
        'has-background': backgroundClass || customMainColor,
        [backgroundClass]: backgroundClass
      });
      figureStyles = {
        backgroundColor: backgroundClass ? undefined : customMainColor
      };
      // Is normal style and a custom color is being used ( we can set a style directly with its value)
    } else if (customMainColor) {
      figureStyles = {
        borderColor: customMainColor
      };
    }
    const blockquoteTextColorClass = (0, _blockEditor.getColorClassName)('color', textColor);
    const blockquoteClasses = (0, _classnames.default)({
      'has-text-color': textColor || customTextColor,
      [blockquoteTextColorClass]: blockquoteTextColorClass
    });
    const blockquoteStyles = blockquoteTextColorClass ? undefined : {
      color: customTextColor
    };
    return (0, _react.createElement)("figure", {
      ..._blockEditor.useBlockProps.save({
        className: figureClasses,
        style: figureStyles
      })
    }, (0, _react.createElement)("blockquote", {
      className: blockquoteClasses,
      style: blockquoteStyles
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: value,
      multiline: true
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    })));
  },
  migrate({
    value,
    className,
    mainColor,
    customMainColor,
    customTextColor,
    ...attributes
  }) {
    const isSolidColorStyle = className?.includes(_shared.SOLID_COLOR_CLASS);
    let style;
    if (customMainColor) {
      if (!isSolidColorStyle) {
        // Block supports: Set style.border.color if a deprecated block has a default style and a `customMainColor` attribute.
        style = {
          border: {
            color: customMainColor
          }
        };
      } else {
        // Block supports: Set style.color.background if a deprecated block has a solid style and a `customMainColor` attribute.
        style = {
          color: {
            background: customMainColor
          }
        };
      }
    }

    // Block supports: Set style.color.text if a deprecated block has a `customTextColor` attribute.
    if (customTextColor && style) {
      style.color = {
        ...style.color,
        text: customTextColor
      };
    }
    return {
      value: multilineToInline(value),
      className,
      backgroundColor: isSolidColorStyle ? mainColor : undefined,
      borderColor: isSolidColorStyle ? undefined : mainColor,
      textAlign: isSolidColorStyle ? 'left' : undefined,
      style,
      ...attributes
    };
  }
};
const v3 = {
  attributes: {
    ...blockAttributes,
    // figureStyle is an attribute that never existed.
    // We are using it as a way to access the styles previously applied to the figure.
    figureStyle: {
      source: 'attribute',
      selector: 'figure',
      attribute: 'style'
    }
  },
  save({
    attributes
  }) {
    const {
      mainColor,
      customMainColor,
      textColor,
      customTextColor,
      value,
      citation,
      className,
      figureStyle
    } = attributes;
    const isSolidColorStyle = className?.includes(_shared.SOLID_COLOR_CLASS);
    let figureClasses, figureStyles;

    // Is solid color style
    if (isSolidColorStyle) {
      const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', mainColor);
      figureClasses = (0, _classnames.default)({
        'has-background': backgroundClass || customMainColor,
        [backgroundClass]: backgroundClass
      });
      figureStyles = {
        backgroundColor: backgroundClass ? undefined : customMainColor
      };
      // Is normal style and a custom color is being used ( we can set a style directly with its value)
    } else if (customMainColor) {
      figureStyles = {
        borderColor: customMainColor
      };
      // If normal style and a named color are being used, we need to retrieve the color value to set the style,
      // as there is no expectation that themes create classes that set border colors.
    } else if (mainColor) {
      // Previously here we queried the color settings to know the color value
      // of a named color. This made the save function impure and the block was refactored,
      // because meanwhile a change in the editor made it impossible to query color settings in the save function.
      // Here instead of querying the color settings to know the color value, we retrieve the value
      // directly from the style previously serialized.
      const borderColor = parseBorderColor(figureStyle);
      figureStyles = {
        borderColor
      };
    }
    const blockquoteTextColorClass = (0, _blockEditor.getColorClassName)('color', textColor);
    const blockquoteClasses = (textColor || customTextColor) && (0, _classnames.default)('has-text-color', {
      [blockquoteTextColorClass]: blockquoteTextColorClass
    });
    const blockquoteStyles = blockquoteTextColorClass ? undefined : {
      color: customTextColor
    };
    return (0, _react.createElement)("figure", {
      className: figureClasses,
      style: figureStyles
    }, (0, _react.createElement)("blockquote", {
      className: blockquoteClasses,
      style: blockquoteStyles
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: value,
      multiline: true
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    })));
  },
  migrate({
    value,
    className,
    figureStyle,
    mainColor,
    customMainColor,
    customTextColor,
    ...attributes
  }) {
    const isSolidColorStyle = className?.includes(_shared.SOLID_COLOR_CLASS);
    let style;
    if (customMainColor) {
      if (!isSolidColorStyle) {
        // Block supports: Set style.border.color if a deprecated block has a default style and a `customMainColor` attribute.
        style = {
          border: {
            color: customMainColor
          }
        };
      } else {
        // Block supports: Set style.color.background if a deprecated block has a solid style and a `customMainColor` attribute.
        style = {
          color: {
            background: customMainColor
          }
        };
      }
    }

    // Block supports: Set style.color.text if a deprecated block has a `customTextColor` attribute.
    if (customTextColor && style) {
      style.color = {
        ...style.color,
        text: customTextColor
      };
    }
    // If is the default style, and a main color is set,
    // migrate the main color value into a custom border color.
    // The custom border color value is retrieved by parsing the figure styles.
    if (!isSolidColorStyle && mainColor && figureStyle) {
      const borderColor = parseBorderColor(figureStyle);
      if (borderColor) {
        return {
          value: multilineToInline(value),
          ...attributes,
          className,
          // Block supports: Set style.border.color if a deprecated block has `mainColor`, inline border CSS and is not a solid color style.
          style: {
            border: {
              color: borderColor
            }
          }
        };
      }
    }
    return {
      value: multilineToInline(value),
      className,
      backgroundColor: isSolidColorStyle ? mainColor : undefined,
      borderColor: isSolidColorStyle ? undefined : mainColor,
      textAlign: isSolidColorStyle ? 'left' : undefined,
      style,
      ...attributes
    };
  }
};
const v2 = {
  attributes: blockAttributes,
  save({
    attributes
  }) {
    const {
      mainColor,
      customMainColor,
      textColor,
      customTextColor,
      value,
      citation,
      className
    } = attributes;
    const isSolidColorStyle = className?.includes(_shared.SOLID_COLOR_CLASS);
    let figureClass, figureStyles;
    // Is solid color style
    if (isSolidColorStyle) {
      figureClass = (0, _blockEditor.getColorClassName)('background-color', mainColor);
      if (!figureClass) {
        figureStyles = {
          backgroundColor: customMainColor
        };
      }
      // Is normal style and a custom color is being used ( we can set a style directly with its value)
    } else if (customMainColor) {
      figureStyles = {
        borderColor: customMainColor
      };
      // Is normal style and a named color is being used, we need to retrieve the color value to set the style,
      // as there is no expectation that themes create classes that set border colors.
    } else if (mainColor) {
      var _select$getSettings$c;
      const colors = (_select$getSettings$c = (0, _data.select)(_blockEditor.store).getSettings().colors) !== null && _select$getSettings$c !== void 0 ? _select$getSettings$c : [];
      const colorObject = (0, _blockEditor.getColorObjectByAttributeValues)(colors, mainColor);
      figureStyles = {
        borderColor: colorObject.color
      };
    }
    const blockquoteTextColorClass = (0, _blockEditor.getColorClassName)('color', textColor);
    const blockquoteClasses = textColor || customTextColor ? (0, _classnames.default)('has-text-color', {
      [blockquoteTextColorClass]: blockquoteTextColorClass
    }) : undefined;
    const blockquoteStyle = blockquoteTextColorClass ? undefined : {
      color: customTextColor
    };
    return (0, _react.createElement)("figure", {
      className: figureClass,
      style: figureStyles
    }, (0, _react.createElement)("blockquote", {
      className: blockquoteClasses,
      style: blockquoteStyle
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: value,
      multiline: true
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    })));
  },
  migrate({
    value,
    className,
    mainColor,
    customMainColor,
    customTextColor,
    ...attributes
  }) {
    const isSolidColorStyle = className?.includes(_shared.SOLID_COLOR_CLASS);
    let style = {};
    if (customMainColor) {
      if (!isSolidColorStyle) {
        // Block supports: Set style.border.color if a deprecated block has a default style and a `customMainColor` attribute.
        style = {
          border: {
            color: customMainColor
          }
        };
      } else {
        // Block supports: Set style.color.background if a deprecated block has a solid style and a `customMainColor` attribute.
        style = {
          color: {
            background: customMainColor
          }
        };
      }
    }

    // Block supports: Set style.color.text if a deprecated block has a `customTextColor` attribute.
    if (customTextColor && style) {
      style.color = {
        ...style.color,
        text: customTextColor
      };
    }
    return {
      value: multilineToInline(value),
      className,
      backgroundColor: isSolidColorStyle ? mainColor : undefined,
      borderColor: isSolidColorStyle ? undefined : mainColor,
      textAlign: isSolidColorStyle ? 'left' : undefined,
      style,
      ...attributes
    };
  }
};
const v1 = {
  attributes: {
    ...blockAttributes
  },
  save({
    attributes
  }) {
    const {
      value,
      citation
    } = attributes;
    return (0, _react.createElement)("blockquote", null, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: value,
      multiline: true
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    }));
  },
  migrate({
    value,
    ...attributes
  }) {
    return {
      value: multilineToInline(value),
      ...attributes
    };
  }
};
const v0 = {
  attributes: {
    ...blockAttributes,
    citation: {
      type: 'string',
      source: 'html',
      selector: 'footer'
    },
    align: {
      type: 'string',
      default: 'none'
    }
  },
  save({
    attributes
  }) {
    const {
      value,
      citation,
      align
    } = attributes;
    return (0, _react.createElement)("blockquote", {
      className: `align${align}`
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: value,
      multiline: true
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "footer",
      value: citation
    }));
  },
  migrate({
    value,
    ...attributes
  }) {
    return {
      value: multilineToInline(value),
      ...attributes
    };
  }
};

/**
 * New deprecations need to be placed first
 * for them to have higher priority.
 *
 * Old deprecations may need to be updated as well.
 *
 * See block-deprecation.md
 */
var _default = exports.default = [v5, v4, v3, v2, v1, v0];
//# sourceMappingURL=deprecated.js.map