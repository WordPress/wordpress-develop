"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _i18n = require("@wordpress/i18n");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
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
  name: "core/details",
  title: "Details",
  category: "text",
  description: "Hide and show additional content.",
  keywords: ["accordion", "summary", "toggle", "disclosure"],
  textdomain: "default",
  attributes: {
    showContent: {
      type: "boolean",
      "default": false
    },
    summary: {
      type: "rich-text",
      source: "rich-text",
      selector: "summary"
    }
  },
  supports: {
    align: ["wide", "full"],
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    __experimentalBorder: {
      color: true,
      width: true,
      style: true
    },
    html: false,
    spacing: {
      margin: true,
      padding: true,
      blockGap: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
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
    layout: {
      allowEditing: false
    }
  },
  editorStyle: "wp-block-details-editor",
  style: "wp-block-details"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.details,
  example: {
    attributes: {
      summary: 'La Mancha',
      showContent: true
    },
    innerBlocks: [{
      name: 'core/paragraph',
      attributes: {
        content: (0, _i18n.__)('In a village of La Mancha, the name of which I have no desire to call to mind, there lived not long since one of those gentlemen that keep a lance in the lance-rack, an old buckler, a lean hack, and a greyhound for coursing.')
      }
    }]
  },
  save: _save.default,
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map