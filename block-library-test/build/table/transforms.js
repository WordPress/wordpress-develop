"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const tableContentPasteSchema = ({
  phrasingContentSchema
}) => ({
  tr: {
    allowEmpty: true,
    children: {
      th: {
        allowEmpty: true,
        children: phrasingContentSchema,
        attributes: ['scope', 'colspan', 'rowspan']
      },
      td: {
        allowEmpty: true,
        children: phrasingContentSchema,
        attributes: ['colspan', 'rowspan']
      }
    }
  }
});
const tablePasteSchema = args => ({
  table: {
    children: {
      thead: {
        allowEmpty: true,
        children: tableContentPasteSchema(args)
      },
      tfoot: {
        allowEmpty: true,
        children: tableContentPasteSchema(args)
      },
      tbody: {
        allowEmpty: true,
        children: tableContentPasteSchema(args)
      }
    }
  }
});
const transforms = {
  from: [{
    type: 'raw',
    selector: 'table',
    schema: tablePasteSchema,
    transform: node => {
      const attributes = Array.from(node.children).reduce((sectionAcc, section) => {
        if (!section.children.length) {
          return sectionAcc;
        }
        const sectionName = section.nodeName.toLowerCase().slice(1);
        const sectionAttributes = Array.from(section.children).reduce((rowAcc, row) => {
          if (!row.children.length) {
            return rowAcc;
          }
          const rowAttributes = Array.from(row.children).reduce((colAcc, col) => {
            const rowspan = (0, _utils.normalizeRowColSpan)(col.getAttribute('rowspan'));
            const colspan = (0, _utils.normalizeRowColSpan)(col.getAttribute('colspan'));
            colAcc.push({
              tag: col.nodeName.toLowerCase(),
              content: col.innerHTML,
              rowspan,
              colspan
            });
            return colAcc;
          }, []);
          rowAcc.push({
            cells: rowAttributes
          });
          return rowAcc;
        }, []);
        sectionAcc[sectionName] = sectionAttributes;
        return sectionAcc;
      }, {});
      return (0, _blocks.createBlock)('core/table', attributes);
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map