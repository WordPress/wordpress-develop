"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/pattern",
  title: "Pattern placeholder",
  category: "theme",
  description: "Show a block pattern.",
  supports: {
    html: false,
    inserter: false,
    renaming: false
  },
  textdomain: "default",
  attributes: {
    slug: {
      type: "string"
    }
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map