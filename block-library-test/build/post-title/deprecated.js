"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _migrateFontFamily = _interopRequireDefault(require("../utils/migrate-font-family"));
/**
 * Internal dependencies
 */

const v1 = {
  attributes: {
    textAlign: {
      type: 'string'
    },
    level: {
      type: 'number',
      default: 2
    },
    isLink: {
      type: 'boolean',
      default: false
    },
    rel: {
      type: 'string',
      attribute: 'rel',
      default: ''
    },
    linkTarget: {
      type: 'string',
      default: '_self'
    }
  },
  supports: {
    align: ['wide', 'full'],
    html: false,
    color: {
      gradients: true,
      link: true
    },
    spacing: {
      margin: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true
    }
  },
  save() {
    return null;
  },
  migrate: _migrateFontFamily.default,
  isEligible({
    style
  }) {
    return style?.typography?.fontFamily;
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
var _default = exports.default = [v1];
//# sourceMappingURL=deprecated.js.map