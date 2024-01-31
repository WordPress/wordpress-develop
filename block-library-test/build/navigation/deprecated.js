"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _migrateFontFamily = _interopRequireDefault(require("../utils/migrate-font-family"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const TYPOGRAPHY_PRESET_DEPRECATION_MAP = {
  fontStyle: 'var:preset|font-style|',
  fontWeight: 'var:preset|font-weight|',
  textDecoration: 'var:preset|text-decoration|',
  textTransform: 'var:preset|text-transform|'
};
const migrateIdToRef = ({
  navigationMenuId,
  ...attributes
}) => {
  return {
    ...attributes,
    ref: navigationMenuId
  };
};
const migrateWithLayout = attributes => {
  if (!!attributes.layout) {
    return attributes;
  }
  const {
    itemsJustification,
    orientation,
    ...updatedAttributes
  } = attributes;
  if (itemsJustification || orientation) {
    Object.assign(updatedAttributes, {
      layout: {
        type: 'flex',
        ...(itemsJustification && {
          justifyContent: itemsJustification
        }),
        ...(orientation && {
          orientation
        })
      }
    });
  }
  return updatedAttributes;
};
const v6 = {
  attributes: {
    navigationMenuId: {
      type: 'number'
    },
    textColor: {
      type: 'string'
    },
    customTextColor: {
      type: 'string'
    },
    rgbTextColor: {
      type: 'string'
    },
    backgroundColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    rgbBackgroundColor: {
      type: 'string'
    },
    showSubmenuIcon: {
      type: 'boolean',
      default: true
    },
    openSubmenusOnClick: {
      type: 'boolean',
      default: false
    },
    overlayMenu: {
      type: 'string',
      default: 'mobile'
    },
    __unstableLocation: {
      type: 'string'
    },
    overlayBackgroundColor: {
      type: 'string'
    },
    customOverlayBackgroundColor: {
      type: 'string'
    },
    overlayTextColor: {
      type: 'string'
    },
    customOverlayTextColor: {
      type: 'string'
    }
  },
  supports: {
    align: ['wide', 'full'],
    anchor: true,
    html: false,
    inserter: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalTextTransform: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    spacing: {
      blockGap: true,
      units: ['px', 'em', 'rem', 'vh', 'vw'],
      __experimentalDefaultControls: {
        blockGap: true
      }
    },
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      default: {
        type: 'flex'
      }
    }
  },
  save() {
    return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
  },
  isEligible: ({
    navigationMenuId
  }) => !!navigationMenuId,
  migrate: migrateIdToRef
};
const v5 = {
  attributes: {
    navigationMenuId: {
      type: 'number'
    },
    orientation: {
      type: 'string',
      default: 'horizontal'
    },
    textColor: {
      type: 'string'
    },
    customTextColor: {
      type: 'string'
    },
    rgbTextColor: {
      type: 'string'
    },
    backgroundColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    rgbBackgroundColor: {
      type: 'string'
    },
    itemsJustification: {
      type: 'string'
    },
    showSubmenuIcon: {
      type: 'boolean',
      default: true
    },
    openSubmenusOnClick: {
      type: 'boolean',
      default: false
    },
    overlayMenu: {
      type: 'string',
      default: 'never'
    },
    __unstableLocation: {
      type: 'string'
    },
    overlayBackgroundColor: {
      type: 'string'
    },
    customOverlayBackgroundColor: {
      type: 'string'
    },
    overlayTextColor: {
      type: 'string'
    },
    customOverlayTextColor: {
      type: 'string'
    }
  },
  supports: {
    align: ['wide', 'full'],
    anchor: true,
    html: false,
    inserter: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalTextTransform: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    spacing: {
      blockGap: true,
      units: ['px', 'em', 'rem', 'vh', 'vw'],
      __experimentalDefaultControls: {
        blockGap: true
      }
    }
  },
  save() {
    return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
  },
  isEligible: ({
    itemsJustification,
    orientation
  }) => !!itemsJustification || !!orientation,
  migrate: (0, _compose.compose)(migrateIdToRef, migrateWithLayout)
};
const v4 = {
  attributes: {
    orientation: {
      type: 'string',
      default: 'horizontal'
    },
    textColor: {
      type: 'string'
    },
    customTextColor: {
      type: 'string'
    },
    rgbTextColor: {
      type: 'string'
    },
    backgroundColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    rgbBackgroundColor: {
      type: 'string'
    },
    itemsJustification: {
      type: 'string'
    },
    showSubmenuIcon: {
      type: 'boolean',
      default: true
    },
    openSubmenusOnClick: {
      type: 'boolean',
      default: false
    },
    overlayMenu: {
      type: 'string',
      default: 'never'
    },
    __unstableLocation: {
      type: 'string'
    },
    overlayBackgroundColor: {
      type: 'string'
    },
    customOverlayBackgroundColor: {
      type: 'string'
    },
    overlayTextColor: {
      type: 'string'
    },
    customOverlayTextColor: {
      type: 'string'
    }
  },
  supports: {
    align: ['wide', 'full'],
    anchor: true,
    html: false,
    inserter: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalTextTransform: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true
    },
    spacing: {
      blockGap: true,
      units: ['px', 'em', 'rem', 'vh', 'vw'],
      __experimentalDefaultControls: {
        blockGap: true
      }
    }
  },
  save() {
    return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
  },
  migrate: (0, _compose.compose)(migrateIdToRef, migrateWithLayout, _migrateFontFamily.default),
  isEligible({
    style
  }) {
    return style?.typography?.fontFamily;
  }
};
const migrateIsResponsive = function (attributes) {
  delete attributes.isResponsive;
  return {
    ...attributes,
    overlayMenu: 'mobile'
  };
};
const migrateTypographyPresets = function (attributes) {
  var _attributes$style$typ;
  return {
    ...attributes,
    style: {
      ...attributes.style,
      typography: Object.fromEntries(Object.entries((_attributes$style$typ = attributes.style.typography) !== null && _attributes$style$typ !== void 0 ? _attributes$style$typ : {}).map(([key, value]) => {
        const prefix = TYPOGRAPHY_PRESET_DEPRECATION_MAP[key];
        if (prefix && value.startsWith(prefix)) {
          const newValue = value.slice(prefix.length);
          if ('textDecoration' === key && 'strikethrough' === newValue) {
            return [key, 'line-through'];
          }
          return [key, newValue];
        }
        return [key, value];
      }))
    }
  };
};
const deprecated = [v6, v5, v4,
// Remove `isResponsive` attribute.
{
  attributes: {
    orientation: {
      type: 'string',
      default: 'horizontal'
    },
    textColor: {
      type: 'string'
    },
    customTextColor: {
      type: 'string'
    },
    rgbTextColor: {
      type: 'string'
    },
    backgroundColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    rgbBackgroundColor: {
      type: 'string'
    },
    itemsJustification: {
      type: 'string'
    },
    showSubmenuIcon: {
      type: 'boolean',
      default: true
    },
    openSubmenusOnClick: {
      type: 'boolean',
      default: false
    },
    isResponsive: {
      type: 'boolean',
      default: 'false'
    },
    __unstableLocation: {
      type: 'string'
    },
    overlayBackgroundColor: {
      type: 'string'
    },
    customOverlayBackgroundColor: {
      type: 'string'
    },
    overlayTextColor: {
      type: 'string'
    },
    customOverlayTextColor: {
      type: 'string'
    }
  },
  supports: {
    align: ['wide', 'full'],
    anchor: true,
    html: false,
    inserter: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalTextTransform: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true
    }
  },
  isEligible(attributes) {
    return attributes.isResponsive;
  },
  migrate: (0, _compose.compose)(migrateIdToRef, migrateWithLayout, _migrateFontFamily.default, migrateIsResponsive),
  save() {
    return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
  }
}, {
  attributes: {
    orientation: {
      type: 'string'
    },
    textColor: {
      type: 'string'
    },
    customTextColor: {
      type: 'string'
    },
    rgbTextColor: {
      type: 'string'
    },
    backgroundColor: {
      type: 'string'
    },
    customBackgroundColor: {
      type: 'string'
    },
    rgbBackgroundColor: {
      type: 'string'
    },
    itemsJustification: {
      type: 'string'
    },
    showSubmenuIcon: {
      type: 'boolean',
      default: true
    }
  },
  supports: {
    align: ['wide', 'full'],
    anchor: true,
    html: false,
    inserter: true,
    fontSize: true,
    __experimentalFontStyle: true,
    __experimentalFontWeight: true,
    __experimentalTextTransform: true,
    color: true,
    __experimentalFontFamily: true,
    __experimentalTextDecoration: true
  },
  save() {
    return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
  },
  isEligible(attributes) {
    if (!attributes.style || !attributes.style.typography) {
      return false;
    }
    for (const styleAttribute in TYPOGRAPHY_PRESET_DEPRECATION_MAP) {
      const attributeValue = attributes.style.typography[styleAttribute];
      if (attributeValue && attributeValue.startsWith(TYPOGRAPHY_PRESET_DEPRECATION_MAP[styleAttribute])) {
        return true;
      }
    }
    return false;
  },
  migrate: (0, _compose.compose)(migrateIdToRef, migrateWithLayout, _migrateFontFamily.default, migrateTypographyPresets)
}, {
  attributes: {
    className: {
      type: 'string'
    },
    textColor: {
      type: 'string'
    },
    rgbTextColor: {
      type: 'string'
    },
    backgroundColor: {
      type: 'string'
    },
    rgbBackgroundColor: {
      type: 'string'
    },
    fontSize: {
      type: 'string'
    },
    customFontSize: {
      type: 'number'
    },
    itemsJustification: {
      type: 'string'
    },
    showSubmenuIcon: {
      type: 'boolean'
    }
  },
  isEligible(attribute) {
    return attribute.rgbTextColor || attribute.rgbBackgroundColor;
  },
  supports: {
    align: ['wide', 'full'],
    anchor: true,
    html: false,
    inserter: true
  },
  migrate: (0, _compose.compose)(migrateIdToRef, attributes => {
    const {
      rgbTextColor,
      rgbBackgroundColor,
      ...restAttributes
    } = attributes;
    return {
      ...restAttributes,
      customTextColor: attributes.textColor ? undefined : attributes.rgbTextColor,
      customBackgroundColor: attributes.backgroundColor ? undefined : attributes.rgbBackgroundColor
    };
  }),
  save() {
    return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
  }
}];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map