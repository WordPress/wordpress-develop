"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _migrateFontFamily = _interopRequireDefault(require("../utils/migrate-font-family"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const v1 = {
  attributes: {
    content: {
      type: 'string',
      source: 'html',
      selector: 'pre',
      default: ''
    },
    textAlign: {
      type: 'string'
    }
  },
  save({
    attributes
  }) {
    const {
      textAlign,
      content
    } = attributes;
    return (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "pre",
      style: {
        textAlign
      },
      value: content
    });
  }
};
const v2 = {
  attributes: {
    content: {
      type: 'string',
      source: 'html',
      selector: 'pre',
      default: '',
      __unstablePreserveWhiteSpace: true,
      __experimentalRole: 'content'
    },
    textAlign: {
      type: 'string'
    }
  },
  supports: {
    anchor: true,
    color: {
      gradients: true,
      link: true
    },
    typography: {
      fontSize: true,
      __experimentalFontFamily: true
    },
    spacing: {
      padding: true
    }
  },
  save({
    attributes
  }) {
    const {
      textAlign,
      content
    } = attributes;
    const className = (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    });
    return (0, _react.createElement)("pre", {
      ..._blockEditor.useBlockProps.save({
        className
      })
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      value: content
    }));
  },
  migrate: _migrateFontFamily.default,
  isEligible({
    style
  }) {
    return style?.typography?.fontFamily;
  }
};

/**
 * New deprecations need to be placed first
 * for them to have higher priority.
 *
 * Old deprecations may need to be updated as well.
 *
 * See block-deprecation.md
 */
var _default = exports.default = [v2, v1];
//# sourceMappingURL=deprecated.js.map