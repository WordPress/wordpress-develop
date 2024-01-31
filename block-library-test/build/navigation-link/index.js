"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _blockEditor = require("@wordpress/block-editor");
var _hooks = require("@wordpress/hooks");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _hooks2 = require("./hooks");
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
  name: "core/navigation-link",
  title: "Custom Link",
  category: "design",
  parent: ["core/navigation"],
  allowedBlocks: ["core/navigation-link", "core/navigation-submenu", "core/page-list"],
  description: "Add a page, link, or another item to your navigation.",
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
    isTopLevelLink: {
      type: "boolean"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "overlayTextColor", "customOverlayTextColor", "overlayBackgroundColor", "customOverlayBackgroundColor", "fontSize", "customFontSize", "showSubmenuIcon", "maxNestingLevel", "style"],
  supports: {
    reusable: false,
    html: false,
    __experimentalSlashInserter: true,
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
    },
    renaming: false
  },
  editorStyle: "wp-block-navigation-link-editor",
  style: "wp-block-navigation-link"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.customLink,
  __experimentalLabel: ({
    label
  }) => label,
  merge(leftAttributes, {
    label: rightLabel = ''
  }) {
    return {
      ...leftAttributes,
      label: leftAttributes.label + rightLabel
    };
  },
  edit: _edit.default,
  save: _save.default,
  example: {
    attributes: {
      label: (0, _i18n._x)('Example Link', 'navigation link preview example'),
      url: 'https://example.com'
    }
  },
  deprecated: [{
    isEligible(attributes) {
      return attributes.nofollow;
    },
    attributes: {
      label: {
        type: 'string'
      },
      type: {
        type: 'string'
      },
      nofollow: {
        type: 'boolean'
      },
      description: {
        type: 'string'
      },
      id: {
        type: 'number'
      },
      opensInNewTab: {
        type: 'boolean',
        default: false
      },
      url: {
        type: 'string'
      }
    },
    migrate({
      nofollow,
      ...rest
    }) {
      return {
        rel: nofollow ? 'nofollow' : '',
        ...rest
      };
    },
    save() {
      return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
    }
  }],
  transforms: _transforms.default
};
const init = () => {
  (0, _hooks.addFilter)('blocks.registerBlockType', 'core/navigation-link', _hooks2.enhanceNavigationLinkVariations);
  return (0, _initBlock.default)({
    name,
    metadata,
    settings
  });
};
exports.init = init;
//# sourceMappingURL=index.js.map