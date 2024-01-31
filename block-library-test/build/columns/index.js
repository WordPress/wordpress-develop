"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _variations = _interopRequireDefault(require("./variations"));
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
  name: "core/columns",
  title: "Columns",
  category: "design",
  allowedBlocks: ["core/column"],
  description: "Display content in multiple columns, with blocks added to each column.",
  textdomain: "default",
  attributes: {
    verticalAlignment: {
      type: "string"
    },
    isStackedOnMobile: {
      type: "boolean",
      "default": true
    },
    templateLock: {
      type: ["string", "boolean"],
      "enum": ["all", "insert", "contentOnly", false]
    }
  },
  supports: {
    anchor: true,
    align: ["wide", "full"],
    html: false,
    color: {
      gradients: true,
      link: true,
      heading: true,
      button: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      blockGap: {
        __experimentalDefault: "2em",
        sides: ["horizontal", "vertical"]
      },
      margin: ["top", "bottom"],
      padding: true,
      __experimentalDefaultControls: {
        padding: true,
        blockGap: true
      }
    },
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      allowEditing: false,
      "default": {
        type: "flex",
        flexWrap: "nowrap"
      }
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        style: true,
        width: true
      }
    },
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
  editorStyle: "wp-block-columns-editor",
  style: "wp-block-columns"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.columns,
  variations: _variations.default,
  example: {
    viewportWidth: 600,
    // Columns collapse "@media (max-width: 599px)".
    innerBlocks: [{
      name: 'core/column',
      innerBlocks: [{
        name: 'core/paragraph',
        attributes: {
          /* translators: example text. */
          content: (0, _i18n.__)('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent et eros eu felis.')
        }
      }, {
        name: 'core/image',
        attributes: {
          url: 'https://s.w.org/images/core/5.3/Windbuchencom.jpg'
        }
      }, {
        name: 'core/paragraph',
        attributes: {
          /* translators: example text. */
          content: (0, _i18n.__)('Suspendisse commodo neque lacus, a dictum orci interdum et.')
        }
      }]
    }, {
      name: 'core/column',
      innerBlocks: [{
        name: 'core/paragraph',
        attributes: {
          /* translators: example text. */
          content: (0, _i18n.__)('Etiam et egestas lorem. Vivamus sagittis sit amet dolor quis lobortis. Integer sed fermentum arcu, id vulputate lacus. Etiam fermentum sem eu quam hendrerit.')
        }
      }, {
        name: 'core/paragraph',
        attributes: {
          /* translators: example text. */
          content: (0, _i18n.__)('Nam risus massa, ullamcorper consectetur eros fermentum, porta aliquet ligula. Sed vel mauris nec enim.')
        }
      }]
    }]
  },
  deprecated: _deprecated.default,
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