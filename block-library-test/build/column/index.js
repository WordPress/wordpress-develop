"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/column",
  title: "Column",
  category: "design",
  parent: ["core/columns"],
  description: "A single column within a columns block.",
  textdomain: "default",
  attributes: {
    verticalAlignment: {
      type: "string"
    },
    width: {
      type: "string"
    },
    allowedBlocks: {
      type: "array"
    },
    templateLock: {
      type: ["string", "boolean"],
      "enum": ["all", "insert", "contentOnly", false]
    }
  },
  supports: {
    __experimentalOnEnter: true,
    anchor: true,
    reusable: false,
    html: false,
    color: {
      gradients: true,
      heading: true,
      button: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      blockGap: true,
      padding: true,
      __experimentalDefaultControls: {
        padding: true,
        blockGap: true
      }
    },
    __experimentalBorder: {
      color: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        style: true,
        width: true
      }
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
    layout: true
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.column,
  edit: _edit.default,
  save: _save.default,
  deprecated: _deprecated.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map