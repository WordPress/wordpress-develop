import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { InspectorControls, BlockControls, RichText, BlockIcon, AlignmentControl, useBlockProps, __experimentalUseColorProps as useColorProps, __experimentalUseBorderProps as useBorderProps, __experimentalGetElementClassName } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { Button, PanelBody, Placeholder, TextControl, ToggleControl, ToolbarDropdownMenu, __experimentalHasSplitBorders as hasSplitBorders } from '@wordpress/components';
import { alignLeft, alignRight, alignCenter, blockTable as icon, tableColumnAfter, tableColumnBefore, tableColumnDelete, tableRowAfter, tableRowBefore, tableRowDelete, table } from '@wordpress/icons';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { createTable, updateSelectedCell, getCellAttribute, insertRow, deleteRow, insertColumn, deleteColumn, toggleSection, isEmptyTableSection } from './state';
const ALIGNMENT_CONTROLS = [{
  icon: alignLeft,
  title: __('Align column left'),
  align: 'left'
}, {
  icon: alignCenter,
  title: __('Align column center'),
  align: 'center'
}, {
  icon: alignRight,
  title: __('Align column right'),
  align: 'right'
}];
const cellAriaLabel = {
  head: __('Header cell text'),
  body: __('Body cell text'),
  foot: __('Footer cell text')
};
const placeholder = {
  head: __('Header label'),
  foot: __('Footer label')
};
function TSection({
  name,
  ...props
}) {
  const TagName = `t${name}`;
  return createElement(TagName, {
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
  const [initialRowCount, setInitialRowCount] = useState(2);
  const [initialColumnCount, setInitialColumnCount] = useState(2);
  const [selectedCell, setSelectedCell] = useState();
  const colorProps = useColorProps(attributes);
  const borderProps = useBorderProps(attributes);
  const tableRef = useRef();
  const [hasTableCreated, setHasTableCreated] = useState(false);

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
    setAttributes(createTable({
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
    setAttributes(updateSelectedCell(attributes, selectedCell, cellAttributes => ({
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
    const newAttributes = updateSelectedCell(attributes, columnSelection, cellAttributes => ({
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
    return getCellAttribute(attributes, selectedCell, 'align');
  }

  /**
   * Add or remove a `head` table section.
   */
  function onToggleHeaderSection() {
    setAttributes(toggleSection(attributes, 'head'));
  }

  /**
   * Add or remove a `foot` table section.
   */
  function onToggleFooterSection() {
    setAttributes(toggleSection(attributes, 'foot'));
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
    setAttributes(insertRow(attributes, {
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
    setAttributes(deleteRow(attributes, {
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
    setAttributes(insertColumn(attributes, {
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
    setAttributes(deleteColumn(attributes, {
      sectionName,
      columnIndex
    }));
  }
  useEffect(() => {
    if (!isSelected) {
      setSelectedCell();
    }
  }, [isSelected]);
  useEffect(() => {
    if (hasTableCreated) {
      tableRef?.current?.querySelector('td div[contentEditable="true"]')?.focus();
      setHasTableCreated(false);
    }
  }, [hasTableCreated]);
  const sections = ['head', 'body', 'foot'].filter(name => !isEmptyTableSection(attributes[name]));
  const tableControls = [{
    icon: tableRowBefore,
    title: __('Insert row before'),
    isDisabled: !selectedCell,
    onClick: onInsertRowBefore
  }, {
    icon: tableRowAfter,
    title: __('Insert row after'),
    isDisabled: !selectedCell,
    onClick: onInsertRowAfter
  }, {
    icon: tableRowDelete,
    title: __('Delete row'),
    isDisabled: !selectedCell,
    onClick: onDeleteRow
  }, {
    icon: tableColumnBefore,
    title: __('Insert column before'),
    isDisabled: !selectedCell,
    onClick: onInsertColumnBefore
  }, {
    icon: tableColumnAfter,
    title: __('Insert column after'),
    isDisabled: !selectedCell,
    onClick: onInsertColumnAfter
  }, {
    icon: tableColumnDelete,
    title: __('Delete column'),
    isDisabled: !selectedCell,
    onClick: onDeleteColumn
  }];
  const renderedSections = sections.map(name => createElement(TSection, {
    name: name,
    key: name
  }, attributes[name].map(({
    cells
  }, rowIndex) => createElement("tr", {
    key: rowIndex
  }, cells.map(({
    content,
    tag: CellTag,
    scope,
    align,
    colspan,
    rowspan
  }, columnIndex) => createElement(CellTag, {
    key: columnIndex,
    scope: CellTag === 'th' ? scope : undefined,
    colSpan: colspan,
    rowSpan: rowspan,
    className: classnames({
      [`has-text-align-${align}`]: align
    }, 'wp-block-table__cell-content')
  }, createElement(RichText, {
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
  return createElement("figure", {
    ...useBlockProps({
      ref: tableRef
    })
  }, !isEmpty && createElement(Fragment, null, createElement(BlockControls, {
    group: "block"
  }, createElement(AlignmentControl, {
    label: __('Change column alignment'),
    alignmentControls: ALIGNMENT_CONTROLS,
    value: getCellAlignment(),
    onChange: nextAlign => onChangeColumnAlignment(nextAlign)
  })), createElement(BlockControls, {
    group: "other"
  }, createElement(ToolbarDropdownMenu, {
    hasArrowIndicator: true,
    icon: table,
    label: __('Edit table'),
    controls: tableControls
  }))), createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings'),
    className: "blocks-table-settings"
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Fixed width table cells'),
    checked: !!hasFixedLayout,
    onChange: onChangeFixedLayout
  }), !isEmpty && createElement(Fragment, null, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Header section'),
    checked: !!(head && head.length),
    onChange: onToggleHeaderSection
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Footer section'),
    checked: !!(foot && foot.length),
    onChange: onToggleFooterSection
  })))), !isEmpty && createElement("table", {
    className: classnames(colorProps.className, borderProps.className, {
      'has-fixed-layout': hasFixedLayout,
      // This is required in the editor only to overcome
      // the fact the editor rewrites individual border
      // widths into a shorthand format.
      'has-individual-borders': hasSplitBorders(attributes?.style?.border)
    }),
    style: {
      ...colorProps.style,
      ...borderProps.style
    }
  }, renderedSections), !isEmpty && createElement(RichText, {
    identifier: "caption",
    tagName: "figcaption",
    className: __experimentalGetElementClassName('caption'),
    "aria-label": __('Table caption text'),
    placeholder: __('Add caption'),
    value: caption,
    onChange: value => setAttributes({
      caption: value
    })
    // Deselect the selected table cell when the caption is focused.
    ,
    onFocus: () => setSelectedCell(),
    __unstableOnSplitAtEnd: () => insertBlocksAfter(createBlock(getDefaultBlockName()))
  }), isEmpty && createElement(Placeholder, {
    label: __('Table'),
    icon: createElement(BlockIcon, {
      icon: icon,
      showColors: true
    }),
    instructions: __('Insert a table for sharing data.')
  }, createElement("form", {
    className: "blocks-table__placeholder-form",
    onSubmit: onCreateTable
  }, createElement(TextControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    type: "number",
    label: __('Column count'),
    value: initialColumnCount,
    onChange: onChangeInitialColumnCount,
    min: "1",
    className: "blocks-table__placeholder-input"
  }), createElement(TextControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    type: "number",
    label: __('Row count'),
    value: initialRowCount,
    onChange: onChangeInitialRowCount,
    min: "1",
    className: "blocks-table__placeholder-input"
  }), createElement(Button, {
    __next40pxDefaultSize: true,
    variant: "primary",
    type: "submit"
  }, __('Create Table')))));
}
export default TableEdit;
//# sourceMappingURL=edit.js.map