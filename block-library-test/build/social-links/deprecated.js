"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * The specific handling by `className` below is needed because `itemsJustification`
 * was introduced in https://github.com/WordPress/gutenberg/pull/28980/files and wasn't
 * declared in block.json.
 *
 * @param {Object} attributes Block's attributes.
 */
const migrateWithLayout = attributes => {
  if (!!attributes.layout) {
    return attributes;
  }
  const {
    className
  } = attributes;
  // Matches classes with `items-justified-` prefix.
  const prefix = `items-justified-`;
  const justifiedItemsRegex = new RegExp(`\\b${prefix}[^ ]*[ ]?\\b`, 'g');
  const newAttributes = {
    ...attributes,
    className: className?.replace(justifiedItemsRegex, '').trim()
  };
  /**
   * Add `layout` prop only if `justifyContent` is defined, for backwards
   * compatibility. In other cases the block's default layout will be used.
   * Also noting that due to the missing attribute, it's possible for a block
   * to have more than one of `justified` classes.
   */
  const justifyContent = className?.match(justifiedItemsRegex)?.[0]?.trim();
  if (justifyContent) {
    Object.assign(newAttributes, {
      layout: {
        type: 'flex',
        justifyContent: justifyContent.slice(prefix.length)
      }
    });
  }
  return newAttributes;
};

// Social Links block deprecations.
const deprecated = [
// V1. Remove CSS variable use for colors.
{
  attributes: {
    iconColor: {
      type: 'string'
    },
    customIconColor: {
      type: 'string'
    },
    iconColorValue: {
      type: 'string'
    },
    iconBackgroundColor: {
      type: 'string'
    },
    customIconBackgroundColor: {
      type: 'string'
    },
    iconBackgroundColorValue: {
      type: 'string'
    },
    openInNewTab: {
      type: 'boolean',
      default: false
    },
    size: {
      type: 'string'
    }
  },
  providesContext: {
    openInNewTab: 'openInNewTab'
  },
  supports: {
    align: ['left', 'center', 'right'],
    anchor: true
  },
  migrate: migrateWithLayout,
  save: props => {
    const {
      attributes: {
        iconBackgroundColorValue,
        iconColorValue,
        itemsJustification,
        size
      }
    } = props;
    const className = (0, _classnames.default)(size, {
      'has-icon-color': iconColorValue,
      'has-icon-background-color': iconBackgroundColorValue,
      [`items-justified-${itemsJustification}`]: itemsJustification
    });
    const style = {
      '--wp--social-links--icon-color': iconColorValue,
      '--wp--social-links--icon-background-color': iconBackgroundColorValue
    };
    return (0, _react.createElement)("ul", {
      ..._blockEditor.useBlockProps.save({
        className,
        style
      })
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
  }
}];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map