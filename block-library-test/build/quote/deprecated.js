"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.migrateToQuoteV2 = exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blocks = require("@wordpress/blocks");
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const migrateToQuoteV2 = attributes => {
  const {
    value,
    ...restAttributes
  } = attributes;
  return [{
    ...restAttributes
  }, value ? (0, _blocks.parseWithAttributeSchema)(value, {
    type: 'array',
    source: 'query',
    selector: 'p',
    query: {
      content: {
        type: 'string',
        source: 'html'
      }
    }
  }).map(({
    content
  }) => (0, _blocks.createBlock)('core/paragraph', {
    content
  })) : (0, _blocks.createBlock)('core/paragraph')];
};
exports.migrateToQuoteV2 = migrateToQuoteV2;
const v3 = {
  attributes: {
    value: {
      type: 'string',
      source: 'html',
      selector: 'blockquote',
      multiline: 'p',
      default: '',
      __experimentalRole: 'content'
    },
    citation: {
      type: 'string',
      source: 'html',
      selector: 'cite',
      default: '',
      __experimentalRole: 'content'
    },
    align: {
      type: 'string'
    }
  },
  supports: {
    anchor: true,
    __experimentalSlashInserter: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalDefaultControls: {
        fontSize: true,
        fontAppearance: true
      }
    }
  },
  save({
    attributes
  }) {
    const {
      align,
      value,
      citation
    } = attributes;
    const className = (0, _classnames.default)({
      [`has-text-align-${align}`]: align
    });
    return (0, _react.createElement)("blockquote", {
      ..._blockEditor.useBlockProps.save({
        className
      })
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      multiline: true,
      value: value
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    }));
  },
  migrate: migrateToQuoteV2
};
const v2 = {
  attributes: {
    value: {
      type: 'string',
      source: 'html',
      selector: 'blockquote',
      multiline: 'p',
      default: ''
    },
    citation: {
      type: 'string',
      source: 'html',
      selector: 'cite',
      default: ''
    },
    align: {
      type: 'string'
    }
  },
  migrate: migrateToQuoteV2,
  save({
    attributes
  }) {
    const {
      align,
      value,
      citation
    } = attributes;
    return (0, _react.createElement)("blockquote", {
      style: {
        textAlign: align ? align : null
      }
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      multiline: true,
      value: value
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    }));
  }
};
const v1 = {
  attributes: {
    value: {
      type: 'string',
      source: 'html',
      selector: 'blockquote',
      multiline: 'p',
      default: ''
    },
    citation: {
      type: 'string',
      source: 'html',
      selector: 'cite',
      default: ''
    },
    align: {
      type: 'string'
    },
    style: {
      type: 'number',
      default: 1
    }
  },
  migrate(attributes) {
    if (attributes.style === 2) {
      const {
        style,
        ...restAttributes
      } = attributes;
      return migrateToQuoteV2({
        ...restAttributes,
        className: attributes.className ? attributes.className + ' is-style-large' : 'is-style-large'
      });
    }
    return migrateToQuoteV2(attributes);
  },
  save({
    attributes
  }) {
    const {
      align,
      value,
      citation,
      style
    } = attributes;
    return (0, _react.createElement)("blockquote", {
      className: style === 2 ? 'is-large' : '',
      style: {
        textAlign: align ? align : null
      }
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      multiline: true,
      value: value
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "cite",
      value: citation
    }));
  }
};
const v0 = {
  attributes: {
    value: {
      type: 'string',
      source: 'html',
      selector: 'blockquote',
      multiline: 'p',
      default: ''
    },
    citation: {
      type: 'string',
      source: 'html',
      selector: 'footer',
      default: ''
    },
    align: {
      type: 'string'
    },
    style: {
      type: 'number',
      default: 1
    }
  },
  migrate(attributes) {
    if (!isNaN(parseInt(attributes.style))) {
      const {
        style,
        ...restAttributes
      } = attributes;
      return migrateToQuoteV2({
        ...restAttributes
      });
    }
    return migrateToQuoteV2(attributes);
  },
  save({
    attributes
  }) {
    const {
      align,
      value,
      citation,
      style
    } = attributes;
    return (0, _react.createElement)("blockquote", {
      className: `blocks-quote-style-${style}`,
      style: {
        textAlign: align ? align : null
      }
    }, (0, _react.createElement)(_blockEditor.RichText.Content, {
      multiline: true,
      value: value
    }), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "footer",
      value: citation
    }));
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
var _default = exports.default = [v3, v2, v1, v0];
//# sourceMappingURL=deprecated.js.map