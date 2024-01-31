"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _transforms = _interopRequireDefault(require("./transforms"));
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/text-columns",
  title: "Text Columns (deprecated)",
  icon: "columns",
  category: "design",
  description: "This block is deprecated. Please use the Columns block instead.",
  textdomain: "default",
  attributes: {
    content: {
      type: "array",
      source: "query",
      selector: "p",
      query: {
        children: {
          type: "string",
          source: "html"
        }
      },
      "default": [{}, {}]
    },
    columns: {
      type: "number",
      "default": 2
    },
    width: {
      type: "string"
    }
  },
  supports: {
    inserter: false
  },
  editorStyle: "wp-block-text-columns-editor",
  style: "wp-block-text-columns"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  transforms: _transforms.default,
  getEditWrapperProps(attributes) {
    const {
      width
    } = attributes;
    if ('wide' === width || 'full' === width) {
      return {
        'data-align': width
      };
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