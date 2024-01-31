"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/block",
  title: "Pattern",
  category: "reusable",
  description: "Reuse this design across your site.",
  keywords: ["reusable"],
  textdomain: "default",
  attributes: {
    ref: {
      type: "number"
    },
    overrides: {
      type: "object"
    }
  },
  supports: {
    customClassName: false,
    html: false,
    inserter: false,
    renaming: false
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  edit: _edit.default,
  icon: _icons.symbol
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map