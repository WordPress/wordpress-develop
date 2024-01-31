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
var _deprecated = _interopRequireDefault(require("./deprecated"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/navigation",
  title: "Navigation",
  category: "theme",
  allowedBlocks: ["core/navigation-link", "core/search", "core/social-links", "core/page-list", "core/spacer", "core/home-link", "core/site-title", "core/site-logo", "core/navigation-submenu", "core/loginout", "core/buttons"],
  description: "A collection of blocks that allow visitors to get around your site.",
  keywords: ["menu", "navigation", "links"],
  textdomain: "default",
  attributes: {
    ref: {
      type: "number"
    },
    textColor: {
      type: "string"
    },
    customTextColor: {
      type: "string"
    },
    rgbTextColor: {
      type: "string"
    },
    backgroundColor: {
      type: "string"
    },
    customBackgroundColor: {
      type: "string"
    },
    rgbBackgroundColor: {
      type: "string"
    },
    showSubmenuIcon: {
      type: "boolean",
      "default": true
    },
    openSubmenusOnClick: {
      type: "boolean",
      "default": false
    },
    overlayMenu: {
      type: "string",
      "default": "mobile"
    },
    icon: {
      type: "string",
      "default": "handle"
    },
    hasIcon: {
      type: "boolean",
      "default": true
    },
    __unstableLocation: {
      type: "string"
    },
    overlayBackgroundColor: {
      type: "string"
    },
    customOverlayBackgroundColor: {
      type: "string"
    },
    overlayTextColor: {
      type: "string"
    },
    customOverlayTextColor: {
      type: "string"
    },
    maxNestingLevel: {
      type: "number",
      "default": 5
    },
    templateLock: {
      type: ["string", "boolean"],
      "enum": ["all", "insert", "contentOnly", false]
    }
  },
  providesContext: {
    textColor: "textColor",
    customTextColor: "customTextColor",
    backgroundColor: "backgroundColor",
    customBackgroundColor: "customBackgroundColor",
    overlayTextColor: "overlayTextColor",
    customOverlayTextColor: "customOverlayTextColor",
    overlayBackgroundColor: "overlayBackgroundColor",
    customOverlayBackgroundColor: "customOverlayBackgroundColor",
    fontSize: "fontSize",
    customFontSize: "customFontSize",
    showSubmenuIcon: "showSubmenuIcon",
    openSubmenusOnClick: "openSubmenusOnClick",
    style: "style",
    maxNestingLevel: "maxNestingLevel"
  },
  supports: {
    align: ["wide", "full"],
    ariaLabel: true,
    html: false,
    inserter: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalTextTransform: true,
      __experimentalFontFamily: true,
      __experimentalLetterSpacing: true,
      __experimentalTextDecoration: true,
      __experimentalSkipSerialization: ["textDecoration"],
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    spacing: {
      blockGap: true,
      units: ["px", "em", "rem", "vh", "vw"],
      __experimentalDefaultControls: {
        blockGap: true
      }
    },
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      allowVerticalAlignment: false,
      allowSizingOnChildren: true,
      "default": {
        type: "flex"
      }
    },
    __experimentalStyle: {
      elements: {
        link: {
          color: {
            text: "inherit"
          }
        }
      }
    },
    interactivity: true,
    renaming: false
  },
  editorStyle: "wp-block-navigation-editor",
  style: "wp-block-navigation"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.navigation,
  example: {
    attributes: {
      overlayMenu: 'never'
    },
    innerBlocks: [{
      name: 'core/navigation-link',
      attributes: {
        // translators: 'Home' as in a website's home page.
        label: (0, _i18n.__)('Home'),
        url: 'https://make.wordpress.org/'
      }
    }, {
      name: 'core/navigation-link',
      attributes: {
        // translators: 'About' as in a website's about page.
        label: (0, _i18n.__)('About'),
        url: 'https://make.wordpress.org/'
      }
    }, {
      name: 'core/navigation-link',
      attributes: {
        // translators: 'Contact' as in a website's contact page.
        label: (0, _i18n.__)('Contact'),
        url: 'https://make.wordpress.org/'
      }
    }]
  },
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