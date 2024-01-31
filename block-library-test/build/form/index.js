"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _variations = _interopRequireDefault(require("./variations"));
var _hooks = require("@wordpress/hooks");
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/form",
  title: "Form",
  category: "common",
  allowedBlocks: ["core/paragraph", "core/heading", "core/form-input", "core/form-submit-button", "core/form-submission-notification", "core/group", "core/columns"],
  description: "A form.",
  keywords: ["container", "wrapper", "row", "section"],
  textdomain: "default",
  icon: "feedback",
  attributes: {
    submissionMethod: {
      type: "string",
      "default": "email"
    },
    method: {
      type: "string",
      "default": "post"
    },
    action: {
      type: "string"
    },
    email: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    className: false,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
        link: true
      }
    },
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __experimentalSelector: "form"
  },
  viewScript: "file:./view.min.js"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  edit: _edit.default,
  save: _save.default,
  variations: _variations.default
};
const init = () => {
  // Prevent adding forms inside forms.
  const DISALLOWED_PARENTS = ['core/form'];
  (0, _hooks.addFilter)('blockEditor.__unstableCanInsertBlockType', 'core/block-library/preventInsertingFormIntoAnotherForm', (canInsert, blockType, rootClientId, {
    getBlock,
    getBlockParentsByBlockName
  }) => {
    if (blockType.name !== 'core/form') {
      return canInsert;
    }
    for (const disallowedParentType of DISALLOWED_PARENTS) {
      const hasDisallowedParent = getBlock(rootClientId)?.name === disallowedParentType || getBlockParentsByBlockName(rootClientId, disallowedParentType).length;
      if (hasDisallowedParent) {
        return false;
      }
    }
    return true;
  });
  return (0, _initBlock.default)({
    name,
    metadata,
    settings
  });
};
exports.init = init;
//# sourceMappingURL=index.js.map