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
    }
  },
  supports: {
    align: ['wide', 'full'],
    html: false,
    color: {
      gradients: true
    },
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalTextTransform: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true
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