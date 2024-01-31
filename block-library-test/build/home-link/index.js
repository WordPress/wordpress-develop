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
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/home-link",
  category: "design",
  parent: ["core/navigation"],
  title: "Home Link",
  description: "Create a link that always points to the homepage of the site. Usually not necessary if there is already a site title link present in the header.",
  textdomain: "default",
  attributes: {
    label: {
      type: "string"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "fontSize", "customFontSize", "style"],
  supports: {
    reusable: false,
    html: false,
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
    }
  },
  editorStyle: "wp-block-home-link-editor",
  style: "wp-block-home-link"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.home,
  edit: _edit.default,
  save: _save.default,
  example: {
    attributes: {
      label: (0, _i18n._x)('Home Link', 'block example')
    }
  }
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map