"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _variations = _interopRequireDefault(require("./variations"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/social-link",
  title: "Social Icon",
  category: "widgets",
  parent: ["core/social-links"],
  description: "Display an icon linking to a social media profile or site.",
  textdomain: "default",
  attributes: {
    url: {
      type: "string"
    },
    service: {
      type: "string"
    },
    label: {
      type: "string"
    },
    rel: {
      type: "string"
    }
  },
  usesContext: ["openInNewTab", "showLabels", "iconColor", "iconColorValue", "iconBackgroundColor", "iconBackgroundColorValue"],
  supports: {
    reusable: false,
    html: false
  },
  editorStyle: "wp-block-social-link-editor"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.share,
  edit: _edit.default,
  variations: _variations.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map