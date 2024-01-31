"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _blocks = require("@wordpress/blocks");
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
  name: "core/missing",
  title: "Unsupported",
  category: "text",
  description: "Your site doesn\u2019t include support for this block.",
  textdomain: "default",
  attributes: {
    originalName: {
      type: "string"
    },
    originalUndelimitedContent: {
      type: "string"
    },
    originalContent: {
      type: "string",
      source: "raw"
    }
  },
  supports: {
    className: false,
    customClassName: false,
    inserter: false,
    html: false,
    reusable: false
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  name,
  __experimentalLabel(attributes, {
    context
  }) {
    if (context === 'accessibility') {
      const {
        originalName
      } = attributes;
      const originalBlockType = originalName ? (0, _blocks.getBlockType)(originalName) : undefined;
      if (originalBlockType) {
        return originalBlockType.settings.title || originalName;
      }
      return '';
    }
  },
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