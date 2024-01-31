"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _blocks = require("@wordpress/blocks");
var _state = require("./state");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const ALIGNMENT_CONTROLS = [{
  icon: _icons.alignLeft,
  title: (0, _i18n.__)('Align column left'),
  align: 'left'
}, {
  icon: _icons.alignCenter,
  title: (0, _i18n.__)('Align column center'),
  align: 'center'
}, {
  icon: _icons.alignRight,
  title: (0, _i18n.__)('Align column right'),
  align: 'right'
}];
const cellAriaLabel = {
  head: (0, _i18n.__)('Header cell text'),
  body: (0, _i18n.__)('Body cell text'),
  foot: (0, _i18n.__)('Footer cell text')
};
const placeholder = {
  head: (0, _i18n.__)('Header label'),
  foot: (0, _i18n.__)('Footer label')
};
function TSection({
  name,
  ...props
}) {
  const TagName = `t${name}`;
  return (0, _react.createElement)(TagName, {
    ...props
  });
}
function TableEdit({
  attributes,
  setAttributes,
  insertBlocksAfter,
  isSelected
}) {
  const {
    hasFixedLayout,
    caption,
    head,
    foot
  } = attributes;
  const [initialRowCount, setInitialRowCount] = (0, _element.useState)(2);
  const [initialColumnCount, setInitialColumnCount] = (0, _element.useState)(2);
  const [selectedCell, setSelectedCell] = (0, _element.useState)();
  const colorProps = (0, _blockEditor.__experimentalUseColorProps)(attributes);
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const tableRef = (0, _element.useRef)();
  const [hasTableCreated, setHasTableCreated] = (0, _element.useState)(false);

  /**
   * Updates the initial column count used for table creation.
   *
   * @param {number} count New initial column count.
   */
  function onChangeInitialColumnCount(count) {
    setInitialColumnCount(count);
  }

  /**
   * Updates the initial row count used for table creation.
   *
   * @param {number} count New initial row count.
   */
  function onChangeInitialRowCount(count) {
    setInitialRowCount(count);
  }

  /**
   * Creates a table based on dimensions in local state.
   *
   * @param {Object} event Form submit event.
   */
  function onCreateTable(event) {
    event.preventDefault();
    setAttributes((0, _state.createTable)({
      rowCount: parseInt(initialRowCount, 10) || 2,
      columnCount: parseInt(initialColumnCount, 10) || 2
    }));
    setHasTableCreated(true);
  }

  /**
   * Toggles whether the table has a fixed layout or not.
   */
  function onChangeFixedLayout() {
    setAttributes({
      hasFixedLayout: !hasFixedLayout
    });
  }

  /**
   * Changes the content of the currently selected cell.
   *
   * @param {Array} content A RichText content value.
   */
  function onChange(content) {
    if (!selectedCell) {
      return;
    }
    setAttributes((0, _state.updateSelectedCell)(attributes, selectedCell, cellAttributes => ({
      ...cellAttributes,
      content
    })));
  }

  /**
   * Align text within the a column.
   *
   * @param {string} align The new alignment to apply to the column.
   */
  function onChangeColumnAlignment(align) {
    if (!selectedCell) {
      return;
    }

    // Convert the cell selection to a column selection so that alignment
    // is applied to the entire column.
    const columnSelection = {
      type: 'column',
      columnIndex: selectedCell.columnIndex
    };
    const newAttributes = (0, _state.updateSelectedCell)(attributes, columnSelection, cellAttributes => ({
      ...cellAttributes,
      align
    }));
    setAttributes(newAttributes);
  }

  /**
   * Get the alignment of the currently selected cell.
   *
   * @return {string | undefined} The new alignment to apply to the column.
   */
  function getCellAlignment() {
    if (!selectedCell) {
      return;
    }
    return (0, _state.getCellAttribute)(attributes, selectedCell, 'align');
  }

  /**
   * Add or remove a `head` table section.
   */
  function onToggleHeaderSection() {
    setAttributes((0, _state.toggleSection)(attributes, 'head'));
  }

  /**
   * Add or remove a `foot` table section.
   */
  function onToggleFooterSection() {
    setAttributes((0, _state.toggleSection)(attributes, 'foot'));
  }

  /**
   * Inserts a row at the currently selected row index, plus `delta`.
   *
   * @param {number} delta Offset for selected row index at which to insert.
   */
  function onInsertRow(delta) {
    if (!selectedCell) {
      return;
    }
    const {
      sectionName,
      rowIndex
    } = selectedCell;
    const newRowIndex = rowIndex + delta;
    setAttributes((0, _state.insertRow)(attributes, {
      sectionName,
      rowIndex: newRowIndex
    }));
    // Select the first cell of the new row.
    setSelectedCell({
      sectionName,
      rowIndex: newRowIndex,
      columnIndex: 0,
      type: 'cell'
    });
  }

  /**
   * Inserts a row before the currently selected row.
   */
  function onInsertRowBefore() {
    onInsertRow(0);
  }

  /**
   * Inserts a row after the currently selected row.
   */
  function onInsertRowAfter() {
    onInsertRow(1);
  }

  /**
   * Deletes the currently selected row.
   */
  function onDeleteRow() {
    if (!selectedCell) {
      return;
    }
    const {
      sectionName,
      rowIndex
    } = selectedCell;
    setSelectedCell();
    setAttributes((0, _state.deleteRow)(attributes, {
      sectionName,
      rowIndex
    }));
  }

  /**
   * Inserts a column at the currently selected column index, plus `delta`.
   *
   * @param {number} delta Offset for selected column index at which to insert.
   */
  function onInsertColumn(delta = 0) {
    if (!selectedCell) {
      return;
    }
    const {
      columnIndex
    } = selectedCell;
    const newColumnIndex = columnIndex + delta;
    setAttributes((0, _state.insertColumn)(attributes, {
      columnIndex: newColumnIndex
    }));
    // Select the first cell of the new column.
    setSelectedCell({
      rowIndex: 0,
      columnIndex: newColumnIndex,
      type: 'cell'
    });
  }

  /**
   * Inserts a column before the currently selected column.
   */
  function onInsertColumnBefore() {
    onInsertColumn(0);
  }

  /**
   * Inserts a column after the currently selected column.
   */
  function onInsertColumnAfter() {
    onInsertColumn(1);
  }

  /**
   * Deletes the currently selected column.
   */
  function onDeleteColumn() {
    if (!selectedCell) {
      return;
    }
    const {
      sectionName,
      columnIndex
    } = selectedCell;
    setSelectedCell();
    setAttributes((0, _state.deleteColumn)(attributes, {
      sectionName,
      columnIndex
    }));
  }
  (0, _element.useEffect)(() => {
    if (!isSelected) {
      setSelectedCell();
    }
  }, [isSelected]);
  (0, _element.useEffect)(() => {
    if (hasTableCreated) {
      tableRef?.current?.querySelector('td div[contentEditable="true"]')?.focus();
      setHasTableCreated(false);
    }
  }, [hasTableCreated]);
  const sections = ['head', 'body', 'foot'].filter(name => !(0, _state.isEmptyTableSection)(attributes[name]));
  const tableControls = [{
    icon: _icons.tableRowBefore,
    title: (0, _i18n.__)('Insert row before'),
    isDisabled: !selectedCell,
    onClick: onInsertRowBefore
  }, {
    icon: _icons.tableRowAfter,
    title: (0, _i18n.__)('Insert row after'),
    isDisabled: !selectedCell,
    onClick: onInsertRowAfter
  }, {
    icon: _icons.tableRowDelete,
    title: (0, _i18n.__)('Delete row'),
    isDisabled: !selectedCell,
    onClick: onDeleteRow
  }, {
    icon: _icons.tableColumnBefore,
    title: (0, _i18n.__)('Insert column before'),
    isDisabled: !selectedCell,
    onClick: onInsertColumnBefore
  }, {
    icon: _icons.tableColumnAfter,
    title: (0, _i18n.__)('Insert column after'),
    isDisabled: !selectedCell,
    onClick: onInsertColumnAfter
  }, {
    icon: _icons.tableColumnDelete,
    title: (0, _i18n.__)('Delete column'),
    isDisabled: !selectedCell,
    onClick: onDeleteColumn
  }];
  const renderedSections = sections.map(name => (0, _react.createElement)(TSection, {
    name: name,
    key: name
  }, attributes[name].map(({
    cells
  }, rowIndex) => (0, _react.createElement)("tr", {
    key: rowIndex
  }, cells.map(({
    content,
    tag: CellTag,
    scope,
    align,
    colspan,
    rowspan
  }, columnIndex) => (0, _react.createElement)(CellTag, {
    key: columnIndex,
    scope: CellTag === 'th' ? scope : undefined,
    colSpan: colspan,
    rowSpan: rowspan,
    className: (0, _classnames.default)({
      [`has-text-align-${align}`]: align
    }, 'wp-block-table__cell-content')
  }, (0, _react.createElement)(_blockEditor.RichText, {
    value: content,
    onChange: onChange,
    onFocus: () => {
      setSelectedCell({
        sectionName: name,
        rowIndex,
        columnIndex,
        type: 'cell'
      });
    },
    "aria-label": cellAriaLabel[name],
    placeholder: placeholder[name]
  })))))));
  const isEmpty = !sections.length;
  return (0, _react.createElement)("figure", {
    ...(0, _blockEditor.useBlockProps)({
      ref: tableRef
    })
  }, !isEmpty && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    label: (0, _i18n.__)('Change column alignment'),
    alignmentControls: ALIGNMENT_CONTROLS,
    value: getCellAlignment(),
    onChange: nextAlign => onChangeColumnAlignment(nextAlign)
  })), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_components.ToolbarDropdownMenu, {
    hasArrowIndicator: true,
    icon: _icons.table,
    label: (0, _i18n.__)('Edit table'),
    controls: tableControls
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings'),
    className: "blocks-table-settings"
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Fixed width table cells'),
    checked: !!hasFixedLayout,
    onChange: onChangeFixedLayout
  }), !isEmpty && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Header section'),
    checked: !!(head && head.length),
    onChange: onToggleHeaderSection
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Footer section'),
    checked: !!(foot && foot.length),
    onChange: onToggleFooterSection
  })))), !isEmpty && (0, _react.createElement)("table", {
    className: (0, _classnames.default)(colorProps.className, borderProps.className, {
      'has-fixed-layout': hasFixedLayout,
      // This is required in the editor only to overcome
      // the fact the editor rewrites individual border
      // widths into a shorthand format.
      'has-individual-borders': (0, _components.__experimentalHasSplitBorders)(attributes?.style?.border)
    }),
    style: {
      ...colorProps.style,
      ...borderProps.style
    }
  }, renderedSections), !isEmpty && (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "caption",
    tagName: "figcaption",
    className: (0, _blockEditor.__experimentalGetElementClassName)('caption'),
    "aria-label": (0, _i18n.__)('Table caption text'),
    placeholder: (0, _i18n.__)('Add caption'),
    value: caption,
    onChange: value => setAttributes({
      caption: value
    })
    // Deselect the selected table cell when the caption is focused.
    ,
    onFocus: () => setSelectedCell(),
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }), isEmpty && (0, _react.createElement)(_components.Placeholder, {
    label: (0, _i18n.__)('Table'),
    icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
      icon: _icons.blockTable,
      showColors: true
    }),
    instructions: (0, _i18n.__)('Insert a table for sharing data.')
  }, (0, _react.createElement)("form", {
    className: "blocks-table__placeholder-form",
    onSubmit: onCreateTable
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    type: "number",
    label: (0, _i18n.__)('Column count'),
    value: initialColumnCount,
    onChange: onChangeInitialColumnCount,
    min: "1",
    className: "blocks-table__placeholder-input"
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    type: "number",
    label: (0, _i18n.__)('Row count'),
    value: initialRowCount,
    onChange: onChangeInitialRowCount,
    min: "1",
    className: "blocks-table__placeholder-input"
  }), (0, _react.createElement)(_components.Button, {
    __next40pxDefaultSize: true,
    variant: "primary",
    type: "submit"
  }, (0, _i18n.__)('Create Table')))));
}
var _default = exports.default = TableEdit;
//# sourceMappingURL=edit.js.map