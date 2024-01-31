"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _transforms = _interopRequireDefault(require("./transforms"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/html",
  title: "Custom HTML",
  category: "widgets",
  description: "Add custom HTML code and preview it as you edit.",
  keywords: ["embed"],
  textdomain: "default",
  attributes: {
    content: {
      type: "string",
      source: "raw"
    }
  },
  supports: {
    customClassName: false,
    className: false,
    html: false
  },
  editorStyle: "wp-block-html-editor"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.html,
  example: {
    attributes: {
      content: '<marquee>' + (0, _i18n.__)('Welcome to the wonderful world of blocks…') + '</marquee>'
    }
  },
  edit: _edit.default,
  save: _save.default,
  transforms: _transforms.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map