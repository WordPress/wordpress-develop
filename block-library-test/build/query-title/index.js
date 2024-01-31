"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _variations = _interopRequireDefault(require("./variations"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/query-title",
  title: "Query Title",
  category: "theme",
  description: "Display the query title.",
  textdomain: "default",
  attributes: {
    type: {
      type: "string"
    },
    textAlign: {
      type: "string"
    },
    level: {
      type: "number",
      "default": 1
    },
    showPrefix: {
      type: "boolean",
      "default": true
    },
    showSearchTerm: {
      type: "boolean",
      "default": true
    }
  },
  supports: {
    align: ["wide", "full"],
    html: false,
    color: {
      gradients: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    }
  },
  style: "wp-block-query-title"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.title,
  edit: _edit.default,
  variations: _variations.default,
  deprecated: _deprecated.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map