"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit.js"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/page-list-item",
  title: "Page List Item",
  category: "widgets",
  parent: ["core/page-list"],
  description: "Displays a page inside a list of all pages.",
  keywords: ["page", "menu", "navigation"],
  textdomain: "default",
  attributes: {
    id: {
      type: "number"
    },
    label: {
      type: "string"
    },
    title: {
      type: "string"
    },
    link: {
      type: "string"
    },
    hasChildren: {
      type: "boolean"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "overlayTextColor", "customOverlayTextColor", "overlayBackgroundColor", "customOverlayBackgroundColor", "fontSize", "customFontSize", "showSubmenuIcon", "style", "openSubmenusOnClick"],
  supports: {
    reusable: false,
    html: false,
    lock: false,
    inserter: false,
    __experimentalToolbar: false
  },
  editorStyle: "wp-block-page-list-editor",
  style: "wp-block-page-list"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  __experimentalLabel: ({
    label
  }) => label,
  icon: _icons.page,
  example: {},
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map