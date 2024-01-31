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
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/navigation-submenu",
  title: "Submenu",
  category: "design",
  parent: ["core/navigation"],
  description: "Add a submenu to your navigation.",
  textdomain: "default",
  attributes: {
    label: {
      type: "string"
    },
    type: {
      type: "string"
    },
    description: {
      type: "string"
    },
    rel: {
      type: "string"
    },
    id: {
      type: "number"
    },
    opensInNewTab: {
      type: "boolean",
      "default": false
    },
    url: {
      type: "string"
    },
    title: {
      type: "string"
    },
    kind: {
      type: "string"
    },
    isTopLevelItem: {
      type: "boolean"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "overlayTextColor", "customOverlayTextColor", "overlayBackgroundColor", "customOverlayBackgroundColor", "fontSize", "customFontSize", "showSubmenuIcon", "maxNestingLevel", "openSubmenusOnClick", "style"],
  supports: {
    reusable: false,
    html: false
  },
  editorStyle: "wp-block-navigation-submenu-editor",
  style: "wp-block-navigation-submenu"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: ({
    context
  }) => {
    if (context === 'list-view') {
      return _icons.page;
    }
    return _icons.addSubmenu;
  },
  __experimentalLabel(attributes, {
    context
  }) {
    const {
      label
    } = attributes;
    const customName = attributes?.metadata?.name;

    // In the list view, use the block's menu label as the label.
    // If the menu label is empty, fall back to the default label.
    if (context === 'list-view' && (customName || label)) {
      return attributes?.metadata?.name || label;
    }
    return label;
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