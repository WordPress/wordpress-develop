"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _migrateFontFamily = _interopRequireDefault(require("../utils/migrate-font-family"));
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const v0 = {
  attributes: {
    ordered: {
      type: 'boolean',
      default: false,
      __experimentalRole: 'content'
    },
    values: {
      type: 'string',
      source: 'html',
      selector: 'ol,ul',
      multiline: 'li',
      __unstableMultilineWrapperTags: ['ol', 'ul'],
      default: '',
      __experimentalRole: 'content'
    },
    type: {
      type: 'string'
    },
    start: {
      type: 'number'
    },
    reversed: {
      type: 'boolean'
    },
    placeholder: {
      type: 'string'
    }
  },
  supports: {
    anchor: true,
    className: false,
    typography: {
      fontSize: true,
      __experimentalFontFamily: true
    },
    color: {
      gradients: true,
      link: true
    },
    __unstablePasteTextInline: true,
    __experimentalSelector: 'ol,ul',
    __experimentalSlashInserter: true
  },
  save({
    attributes
  }) {
    const {
      ordered,
      values,
      type,
      reversed,
      start
    } = attributes;
    const TagName = ordered ? 'ol' : 'ul';
    return (0, _react.createElement)(TagName, {
      ..._blockEditor.useBlockProps.save({
        type,
        reversed,
        start
      })
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: values,
      multiline: "li"
    }));
  },
  migrate: _migrateFontFamily.default,
  isEligible({
    style
  }) {
    return style?.typography?.fontFamily;
  }
};
const v1 = {
  attributes: {
    ordered: {
      type: 'boolean',
      default: false,
      __experimentalRole: 'content'
    },
    values: {
      type: 'string',
      source: 'html',
      selector: 'ol,ul',
      multiline: 'li',
      __unstableMultilineWrapperTags: ['ol', 'ul'],
      default: '',
      __experimentalRole: 'content'
    },
    type: {
      type: 'string'
    },
    start: {
      type: 'number'
    },
    reversed: {
      type: 'boolean'
    },
    placeholder: {
      type: 'string'
    }
  },
  supports: {
    anchor: true,
    className: false,
    typography: {
      fontSize: true,
      __experimentalFontFamily: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    __unstablePasteTextInline: true,
    __experimentalSelector: 'ol,ul',
    __experimentalSlashInserter: true
  },
  save({
    attributes
  }) {
    const {
      ordered,
      values,
      type,
      reversed,
      start
    } = attributes;
    const TagName = ordered ? 'ol' : 'ul';
    return (0, _react.createElement)(TagName, {
      ..._blockEditor.useBlockProps.save({
        type,
        reversed,
        start
      })
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: values,
      multiline: "li"
    }));
  },
  migrate: _utils.migrateToListV2
};

// In #53301 changed to use the inline style instead of type attribute.
const v2 = {
  attributes: {
    ordered: {
      type: 'boolean',
      default: false,
      __experimentalRole: 'content'
    },
    values: {
      type: 'string',
      source: 'html',
      selector: 'ol,ul',
      multiline: 'li',
      __unstableMultilineWrapperTags: ['ol', 'ul'],
      default: '',
      __experimentalRole: 'content'
    },
    type: {
      type: 'string'
    },
    start: {
      type: 'number'
    },
    reversed: {
      type: 'boolean'
    },
    placeholder: {
      type: 'string'
    }
  },
  supports: {
    anchor: true,
    className: false,
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
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    __unstablePasteTextInline: true,
    __experimentalSelector: 'ol,ul',
    __experimentalSlashInserter: true
  },
  isEligible({
    type
  }) {
    return !!type;
  },
  save({
    attributes
  }) {
    const {
      ordered,
      type,
      reversed,
      start
    } = attributes;
    const TagName = ordered ? 'ol' : 'ul';
    return (0, _react.createElement)(TagName, {
      ..._blockEditor.useBlockProps.save({
        type,
        reversed,
        start
      })
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
  },
  migrate: _utils.migrateTypeToInlineStyle
};

/**
 * New deprecations need to be placed first
 * for them to have higher priority.
 *
 * Old deprecations may need to be updated as well.
 *
 * See block-deprecation.md
 */
var _default = exports.default = [v2, v1, v0];
//# sourceMappingURL=deprecated.js.map