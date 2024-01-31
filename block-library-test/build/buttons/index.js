"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
var _transforms = _interopRequireDefault(require("./transforms"));
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
  name: "core/buttons",
  title: "Buttons",
  category: "design",
  allowedBlocks: ["core/button"],
  description: "Prompt visitors to take action with a group of button-style links.",
  keywords: ["link"],
  textdomain: "default",
  supports: {
    anchor: true,
    align: ["wide", "full"],
    html: false,
    __experimentalExposeControlsToChildren: true,
    spacing: {
      blockGap: true,
      margin: ["top", "bottom"],
      __experimentalDefaultControls: {
        blockGap: true
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
      allowSwitching: false,
      allowInheriting: false,
      "default": {
        type: "flex"
      }
    }
  },
  editorStyle: "wp-block-buttons-editor",
  style: "wp-block-buttons"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.buttons,
  example: {
    innerBlocks: [{
      name: 'core/button',
      attributes: {
        text: (0, _i18n.__)('Find out more')
      }
    }, {
      name: 'core/button',
      attributes: {
        text: (0, _i18n.__)('Contact us')
      }
    }]
  },
  deprecated: _deprecated.default,
  transforms: _transforms.default,
  edit: _edit.default,
  save: _save.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map