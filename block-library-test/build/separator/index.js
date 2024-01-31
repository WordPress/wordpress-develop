"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _transforms = _interopRequireDefault(require("./transforms"));
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
  name: "core/separator",
  title: "Separator",
  category: "design",
  description: "Create a break between ideas or sections with a horizontal separator.",
  keywords: ["horizontal-line", "hr", "divider"],
  textdomain: "default",
  attributes: {
    opacity: {
      type: "string",
      "default": "alpha-channel"
    }
  },
  supports: {
    anchor: true,
    align: ["center", "wide", "full"],
    color: {
      enableContrastChecker: false,
      __experimentalSkipSerialization: true,
      gradients: true,
      background: true,
      text: false,
      __experimentalDefaultControls: {
        background: true
      }
    },
    spacing: {
      margin: ["top", "bottom"]
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "wide",
    label: "Wide Line"
  }, {
    name: "dots",
    label: "Dots"
  }],
  editorStyle: "wp-block-separator-editor",
  style: "wp-block-separator"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.separator,
  example: {
    attributes: {
      customColor: '#065174',
      className: 'is-style-wide'
    }
  },
  transforms: _transforms.default,
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