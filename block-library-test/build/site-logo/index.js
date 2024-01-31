"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
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
  name: "core/site-logo",
  title: "Site Logo",
  category: "theme",
  description: "Display an image to represent this site. Update this block and the changes apply everywhere.",
  textdomain: "default",
  attributes: {
    width: {
      type: "number"
    },
    isLink: {
      type: "boolean",
      "default": true
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    },
    shouldSyncIcon: {
      type: "boolean"
    }
  },
  example: {
    viewportWidth: 500,
    attributes: {
      width: 350,
      className: "block-editor-block-types-list__site-logo-example"
    }
  },
  supports: {
    html: false,
    align: true,
    alignWide: false,
    color: {
      __experimentalDuotone: "img, .components-placeholder__illustration, .components-placeholder::before",
      text: false,
      background: false
    },
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "rounded",
    label: "Rounded"
  }],
  editorStyle: "wp-block-site-logo-editor",
  style: "wp-block-site-logo"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.siteLogo,
  example: {},
  edit: _edit.default,
  transforms: _transforms.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map