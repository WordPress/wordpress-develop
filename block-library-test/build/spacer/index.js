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
  name: "core/spacer",
  title: "Spacer",
  category: "design",
  description: "Add white space between blocks and customize its height.",
  textdomain: "default",
  attributes: {
    height: {
      type: "string",
      "default": "100px"
    },
    width: {
      type: "string"
    }
  },
  usesContext: ["orientation"],
  supports: {
    anchor: true,
    spacing: {
      margin: ["top", "bottom"],
      __experimentalDefaultControls: {
        margin: true
      }
    }
  },
  editorStyle: "wp-block-spacer-editor",
  style: "wp-block-spacer"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.resizeCornerNE,
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