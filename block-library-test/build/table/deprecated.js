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

// As the previous arbitrary colors won't match theme color palettes, the hex
// value will be mapped to the style.color.background attribute as if it was
// a custom color selection.
const oldColors = {
  'subtle-light-gray': '#f3f4f5',
  'subtle-pale-green': '#e9fbe5',
  'subtle-pale-blue': '#e7f5fe',
  'subtle-pale-pink': '#fcf0ef'
};

// In #41140 support was added to global styles for caption elements which
// added a `wp-element-caption` classname to the embed figcaption element.
const v3 = {
  attributes: {
    hasFixedLayout: {
      type: 'boolean',
      default: false
    },
    caption: {
      type: 'string',
      source: 'html',
      selector: 'figcaption',
      default: ''
    },
    head: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'thead tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            },
            align: {
              type: 'string',
              source: 'attribute',
              attribute: 'data-align'
            }
          }
        }
      }
    },
    body: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'tbody tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            },
            align: {
              type: 'string',
              source: 'attribute',
              attribute: 'data-align'
            }
          }
        }
      }
    },
    foot: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'tfoot tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            },
            align: {
              type: 'string',
              source: 'attribute',
              attribute: 'data-align'
            }
          }
        }
      }
    }
  },
  supports: {
    anchor: true,
    align: true,
    color: {
      __experimentalSkipSerialization: true,
      gradients: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
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
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __experimentalBorder: {
      __experimentalSkipSerialization: true,
      color: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        style: true,
        width: true
      }
    },
    __experimentalSelector: '.wp-block-table > table'
  },
  save({
    attributes
  }) {
    const {
      hasFixedLayout,
      head,
      body,
      foot,
      caption
    } = attributes;
    const isEmpty = !head.length && !body.length && !foot.length;
    if (isEmpty) {
      return null;
    }
    const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);
    const borderProps = (0, _blockEditor.__experimentalGetBorderClassesAndStyles)(attributes);
    const classes = (0, _classnames.default)(colorProps.className, borderProps.className, {
      'has-fixed-layout': hasFixedLayout
    });
    const hasCaption = !_blockEditor.RichText.isEmpty(caption);
    const Section = ({
      type,
      rows
    }) => {
      if (!rows.length) {
        return null;
      }
      const Tag = `t${type}`;
      return (0, _react.createElement)(Tag, null, rows.map(({
        cells
      }, rowIndex) => (0, _react.createElement)("tr", {
        key: rowIndex
      }, cells.map(({
        content,
        tag,
        scope,
        align
      }, cellIndex) => {
        const cellClasses = (0, _classnames.default)({
          [`has-text-align-${align}`]: align
        });
        return (0, _react.createElement)(_blockEditor.RichText.Content, {
          className: cellClasses ? cellClasses : undefined,
          "data-align": align,
          tagName: tag,
          value: content,
          key: cellIndex,
          scope: tag === 'th' ? scope : undefined
        });
      }))));
    };
    return (0, _react.createElement)("figure", {
      ..._blockEditor.useBlockProps.save()
    }, (0, _react.createElement)("table", {
      className: classes === '' ? undefined : classes,
      style: {
        ...colorProps.style,
        ...borderProps.style
      }
    }, (0, _react.createElement)(Section, {
      type: "head",
      rows: head
    }), (0, _react.createElement)(Section, {
      type: "body",
      rows: body
    }), (0, _react.createElement)(Section, {
      type: "foot",
      rows: foot
    })), hasCaption && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "figcaption",
      value: caption
    }));
  }
};

// Deprecation migrating table block to use colors block support feature.
const v2 = {
  attributes: {
    hasFixedLayout: {
      type: 'boolean',
      default: false
    },
    backgroundColor: {
      type: 'string'
    },
    caption: {
      type: 'string',
      source: 'html',
      selector: 'figcaption',
      default: ''
    },
    head: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'thead tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            },
            align: {
              type: 'string',
              source: 'attribute',
              attribute: 'data-align'
            }
          }
        }
      }
    },
    body: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'tbody tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            },
            align: {
              type: 'string',
              source: 'attribute',
              attribute: 'data-align'
            }
          }
        }
      }
    },
    foot: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'tfoot tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            },
            align: {
              type: 'string',
              source: 'attribute',
              attribute: 'data-align'
            }
          }
        }
      }
    }
  },
  supports: {
    anchor: true,
    align: true,
    __experimentalSelector: '.wp-block-table > table'
  },
  save: ({
    attributes
  }) => {
    const {
      hasFixedLayout,
      head,
      body,
      foot,
      backgroundColor,
      caption
    } = attributes;
    const isEmpty = !head.length && !body.length && !foot.length;
    if (isEmpty) {
      return null;
    }
    const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', backgroundColor);
    const classes = (0, _classnames.default)(backgroundClass, {
      'has-fixed-layout': hasFixedLayout,
      'has-background': !!backgroundClass
    });
    const hasCaption = !_blockEditor.RichText.isEmpty(caption);
    const Section = ({
      type,
      rows
    }) => {
      if (!rows.length) {
        return null;
      }
      const Tag = `t${type}`;
      return (0, _react.createElement)(Tag, null, rows.map(({
        cells
      }, rowIndex) => (0, _react.createElement)("tr", {
        key: rowIndex
      }, cells.map(({
        content,
        tag,
        scope,
        align
      }, cellIndex) => {
        const cellClasses = (0, _classnames.default)({
          [`has-text-align-${align}`]: align
        });
        return (0, _react.createElement)(_blockEditor.RichText.Content, {
          className: cellClasses ? cellClasses : undefined,
          "data-align": align,
          tagName: tag,
          value: content,
          key: cellIndex,
          scope: tag === 'th' ? scope : undefined
        });
      }))));
    };
    return (0, _react.createElement)("figure", {
      ..._blockEditor.useBlockProps.save()
    }, (0, _react.createElement)("table", {
      className: classes === '' ? undefined : classes
    }, (0, _react.createElement)(Section, {
      type: "head",
      rows: head
    }), (0, _react.createElement)(Section, {
      type: "body",
      rows: body
    }), (0, _react.createElement)(Section, {
      type: "foot",
      rows: foot
    })), hasCaption && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "figcaption",
      value: caption
    }));
  },
  isEligible: attributes => {
    return attributes.backgroundColor && attributes.backgroundColor in oldColors && !attributes.style;
  },
  // This version is the first to introduce the style attribute to the
  // table block. As a result, we'll explicitly override that.
  migrate: attributes => {
    return {
      ...attributes,
      backgroundColor: undefined,
      style: {
        color: {
          background: oldColors[attributes.backgroundColor]
        }
      }
    };
  }
};
const v1 = {
  attributes: {
    hasFixedLayout: {
      type: 'boolean',
      default: false
    },
    backgroundColor: {
      type: 'string'
    },
    head: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'thead tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            }
          }
        }
      }
    },
    body: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'tbody tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            }
          }
        }
      }
    },
    foot: {
      type: 'array',
      default: [],
      source: 'query',
      selector: 'tfoot tr',
      query: {
        cells: {
          type: 'array',
          default: [],
          source: 'query',
          selector: 'td,th',
          query: {
            content: {
              type: 'string',
              source: 'html'
            },
            tag: {
              type: 'string',
              default: 'td',
              source: 'tag'
            },
            scope: {
              type: 'string',
              source: 'attribute',
              attribute: 'scope'
            }
          }
        }
      }
    }
  },
  supports: {
    align: true
  },
  save({
    attributes
  }) {
    const {
      hasFixedLayout,
      head,
      body,
      foot,
      backgroundColor
    } = attributes;
    const isEmpty = !head.length && !body.length && !foot.length;
    if (isEmpty) {
      return null;
    }
    const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', backgroundColor);
    const classes = (0, _classnames.default)(backgroundClass, {
      'has-fixed-layout': hasFixedLayout,
      'has-background': !!backgroundClass
    });
    const Section = ({
      type,
      rows
    }) => {
      if (!rows.length) {
        return null;
      }
      const Tag = `t${type}`;
      return (0, _react.createElement)(Tag, null, rows.map(({
        cells
      }, rowIndex) => (0, _react.createElement)("tr", {
        key: rowIndex
      }, cells.map(({
        content,
        tag,
        scope
      }, cellIndex) => (0, _react.createElement)(_blockEditor.RichText.Content, {
        tagName: tag,
        value: content,
        key: cellIndex,
        scope: tag === 'th' ? scope : undefined
      })))));
    };
    return (0, _react.createElement)("table", {
      className: classes
    }, (0, _react.createElement)(Section, {
      type: "head",
      rows: head
    }), (0, _react.createElement)(Section, {
      type: "body",
      rows: body
    }), (0, _react.createElement)(Section, {
      type: "foot",
      rows: foot
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
var _default = exports.default = [v3, v2, v1];
//# sourceMappingURL=deprecated.js.map